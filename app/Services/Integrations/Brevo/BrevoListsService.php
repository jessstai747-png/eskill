<?php

declare(strict_types=1);

namespace App\Services\Integrations\Brevo;

use App\Services\CacheService;
use App\Services\ValidationService;

class BrevoListsService
{
    private BrevoClient $client;
    private CacheService $cache;
    private BrevoPersistenceRepository $repo;
    private int $cacheTtl;
    private string $listCacheKeysIndex = 'brevo:lists:keys';

    public function __construct(?BrevoClient $client = null, ?CacheService $cache = null, ?BrevoPersistenceRepository $repo = null)
    {
        $this->client = $client ?? new BrevoClient();
        $this->cache = $cache ?? new CacheService();
        $this->repo = $repo ?? new BrevoPersistenceRepository();
        $this->cacheTtl = (int)($_ENV['BREVO_CACHE_TTL_SECONDS'] ?? 300);
    }

    public function listLists(array $params = [], bool $useCache = true): array
    {
        $normalized = $this->validateListInput($params);
        $cacheKey = $this->listsCacheKey($normalized);

        if ($useCache) {
            $cached = $this->cache->remember($cacheKey, function () use ($normalized) {
                return $this->client->get('contacts/lists', $normalized, 'brevo.lists.list');
            }, $this->cacheTtl);

            $this->trackCacheKey($cacheKey);
            if (is_array($cached)) {
                $this->persistListsFromResponse($cached);
            }
            return $cached;
        }

        $result = $this->client->get('contacts/lists', $normalized, 'brevo.lists.list');
        $this->trackCacheKey($cacheKey);
        $this->persistListsFromResponse($result);
        return $result;
    }

    public function getList(int $listId, bool $useCache = true): array
    {
        $listId = $this->validateListId($listId);
        $cacheKey = $this->listCacheKey($listId);

        if ($useCache) {
            $data = $this->cache->remember($cacheKey, function () use ($listId) {
                return $this->client->get('contacts/lists/' . $listId, [], 'brevo.lists.get');
            }, $this->cacheTtl);

            $this->persistSingleListFromWrapper($data);

            return $data;
        }

        $data = $this->client->get('contacts/lists/' . $listId, [], 'brevo.lists.get');
        $this->persistSingleListFromWrapper($data);
        return $data;
    }

    public function createList(string $name, ?int $folderId = null): array
    {
        $payload = $this->validateCreateInput($name, $folderId);
        $result = $this->client->post('contacts/lists', $payload, 'brevo.lists.create');

        // Persistência local (upsert + last_synced_at)
        $data = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : [];
        $this->repo->upsertList(array_merge($payload, $data), $this->now());

        $this->invalidateCaches();
        return $result;
    }

    public function updateList(int $listId, string $name): array
    {
        $listId = $this->validateListId($listId);
        $payload = $this->validateUpdateInput($name);

        $result = $this->client->put('contacts/lists/' . $listId, $payload, 'brevo.lists.update');

        // Persistência local (upsert + last_synced_at)
        $this->repo->upsertList(['id' => $listId, 'name' => $payload['name']], $this->now());

        $this->cache->forget($this->listCacheKey($listId));
        $this->invalidateCaches();
        return $result;
    }

    public function deleteList(int $listId): array
    {
        $listId = $this->validateListId($listId);

        $result = $this->client->delete('contacts/lists/' . $listId, [], 'brevo.lists.delete');

        $this->repo->softDeleteList($listId, $this->now());

        $this->cache->forget($this->listCacheKey($listId));
        $this->invalidateCaches();
        return $result;
    }

    public function addContacts(int $listId, array $emails): array
    {
        $listId = $this->validateListId($listId);
        $emails = $this->validateEmails($emails);

        $result = $this->client->post('contacts/lists/' . $listId . '/contacts/add', [
            'emails' => $emails,
        ], 'brevo.lists.contacts.add');

        $this->invalidateCaches();
        return $result;
    }

    public function removeContacts(int $listId, array $emails): array
    {
        $listId = $this->validateListId($listId);
        $emails = $this->validateEmails($emails);

        $result = $this->client->post('contacts/lists/' . $listId . '/contacts/remove', [
            'emails' => $emails,
        ], 'brevo.lists.contacts.remove');

        $this->invalidateCaches();
        return $result;
    }

    /**
     * Sync paginado (pull) de listas para DB local.
     */
    public function syncLists(int $limit = 50): array
    {
        $limit = max(1, min(50, $limit));

        $runId = $this->repo->startSyncRun('lists', ['limit' => $limit]);
        $startedAt = microtime(true);

        $offset = 0;
        $processed = 0;
        $pages = 0;
        $seenIds = [];

        try {
            while (true) {
                $wrapper = $this->client->get('contacts/lists', [
                    'limit' => $limit,
                    'offset' => $offset,
                ], 'brevo.lists.sync');

                $response = (isset($wrapper['data']) && is_array($wrapper['data'])) ? $wrapper['data'] : [];

                $lists = [];
                if (is_array($response) && isset($response['lists']) && is_array($response['lists'])) {
                    $lists = $response['lists'];
                }

                foreach ($lists as $list) {
                    if (is_array($list)) {
                        $this->repo->upsertList($list, $this->now());
                        if (isset($list['id'])) {
                            $seenIds[] = (int)$list['id'];
                        }
                        $processed++;
                    }
                }

                $pages++;
                $offset += $limit;

                $total = (is_array($response) && isset($response['count'])) ? (int)$response['count'] : null;
                if ($total !== null && $offset >= $total) {
                    break;
                }

                if (count($lists) < $limit) {
                    break;
                }
            }

            $deleted = $this->repo->softDeleteListsNotIn($seenIds, $this->now());
            $this->invalidateCaches();

            $this->repo->finishSyncRun($runId, 'success', $processed, 0, 200, null, [
                'pages' => $pages,
                'deleted' => $deleted,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'success' => true,
                'run_id' => $runId,
                'processed' => $processed,
                'deleted' => $deleted,
                'pages' => $pages,
            ];
        } catch (BrevoApiException $e) {
            $this->repo->finishSyncRun($runId, 'failed', $processed, 1, $e->getStatusCode(), $e->getMessage(), [
                'pages' => $pages,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        } catch (\Throwable $e) {
            $this->repo->finishSyncRun($runId, 'failed', $processed, 1, null, $e->getMessage(), [
                'pages' => $pages,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        }
    }

    private function validateListInput(array $params): array
    {
        $normalized = [];

        if (isset($params['limit'])) {
            $normalized['limit'] = max(1, min(50, (int)$params['limit']));
        }
        if (isset($params['offset'])) {
            $normalized['offset'] = max(0, (int)$params['offset']);
        }
        if (isset($params['sort']) && is_string($params['sort'])) {
            $normalized['sort'] = $params['sort'];
        }

        return $normalized;
    }

    private function validateListId(int $listId): int
    {
        if ($listId <= 0) {
            throw new BrevoApiException('List id inválido', 400, ['listId' => $listId]);
        }
        return $listId;
    }

    private function validateCreateInput(string $name, ?int $folderId): array
    {
        $name = trim($name);
        $validator = new ValidationService(['name' => $name], ['name' => 'required|min:1']);
        if (!$validator->validate()) {
            throw new BrevoApiException('Nome da lista inválido', 400, ['errors' => $validator->errors()]);
        }

        $payload = ['name' => $name];
        if ($folderId !== null) {
            if ($folderId <= 0) {
                throw new BrevoApiException('Folder id inválido', 400, ['folderId' => $folderId]);
            }
            $payload['folderId'] = (int)$folderId;
        }

        return $payload;
    }

    private function validateUpdateInput(string $name): array
    {
        $name = trim($name);
        $validator = new ValidationService(['name' => $name], ['name' => 'required|min:1']);
        if (!$validator->validate()) {
            throw new BrevoApiException('Nome da lista inválido', 400, ['errors' => $validator->errors()]);
        }

        return ['name' => $name];
    }

    private function validateEmails(array $emails): array
    {
        if ($emails === []) {
            throw new BrevoApiException('Emails inválidos', 400, ['emails' => []]);
        }

        $normalized = [];
        foreach ($emails as $email) {
            if (!is_string($email)) {
                throw new BrevoApiException('Emails inválidos', 400, []);
            }
            $email = strtolower(trim($email));
            $validator = new ValidationService(['email' => $email], ['email' => 'required|email']);
            if (!$validator->validate()) {
                throw new BrevoApiException('Email inválido', 400, ['email' => $email]);
            }
            $normalized[] = $email;
        }

        return array_values(array_unique($normalized));
    }

    private function listsCacheKey(array $params): string
    {
        ksort($params);
        return 'brevo:lists:list:' . sha1(json_encode($params));
    }

    private function listCacheKey(int $listId): string
    {
        return 'brevo:lists:by_id:' . $listId;
    }

    private function trackCacheKey(string $key): void
    {
        $keys = $this->cache->get($this->listCacheKeysIndex);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->cache->set($this->listCacheKeysIndex, $keys, 86400);
        }
    }

    private function invalidateCaches(): void
    {
        $keys = $this->cache->get($this->listCacheKeysIndex);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    $this->cache->forget($key);
                }
            }
        }
        $this->cache->set($this->listCacheKeysIndex, [], 86400);
    }

    private function persistListsFromResponse(array $response): void
    {
        if (!isset($response['data']) || !is_array($response['data'])) {
            return;
        }

        $data = $response['data'];
        if (!isset($data['lists']) || !is_array($data['lists'])) {
            return;
        }

        foreach ($data['lists'] as $list) {
            if (is_array($list)) {
                $this->repo->upsertList($list, null);
            }
        }
    }

    private function persistSingleListFromWrapper($wrapper): void
    {
        if (!is_array($wrapper)) {
            return;
        }

        if (!isset($wrapper['data']) || !is_array($wrapper['data'])) {
            return;
        }

        $this->repo->upsertList($wrapper['data'], null);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

