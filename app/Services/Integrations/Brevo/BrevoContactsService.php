<?php

namespace App\Services\Integrations\Brevo;

use App\Services\CacheService;
use App\Services\ValidationService;

class BrevoContactsService
{
    private BrevoClient $client;
    private CacheService $cache;
    private BrevoPersistenceRepository $repo;
    private int $cacheTtl;
    private string $listKeysIndexKey = 'brevo:contacts:list:keys';

    public function __construct(?BrevoClient $client = null, ?CacheService $cache = null, ?BrevoPersistenceRepository $repo = null)
    {
        $this->client = $client ?? new BrevoClient();
        $this->cache = $cache ?? new CacheService();
        $this->repo = $repo ?? new BrevoPersistenceRepository();
        $this->cacheTtl = (int)($_ENV['BREVO_CACHE_TTL_SECONDS'] ?? 300);
    }

    public function createContact(array $input): array
    {
        $payload = $this->validateCreateInput($input);

        $result = $this->client->post('contacts', $payload, 'brevo.contacts.create');

        $email = strtolower($payload['email']);
        $this->cache->forget($this->contactCacheKey($email));
        $this->invalidateListCaches();

        // Persistência local (upsert + last_synced_at)
        $data = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : [];
        $this->repo->upsertContact(array_merge($payload, $data, ['email' => $email]), $this->now());

        return $result;
    }

    public function getContact(string $email, bool $useCache = true): array
    {
        $email = $this->validateEmailIdentifier($email);
        $cacheKey = $this->contactCacheKey($email);

        if ($useCache) {
            $data = $this->cache->remember($cacheKey, function () use ($email) {
                return $this->client->get('contacts/' . rawurlencode($email), [], 'brevo.contacts.get');
            }, $this->cacheTtl);

            $this->repo->upsertContact($this->extractContactPayload($data, $email), null);
            return $data;
        }

        $data = $this->client->get('contacts/' . rawurlencode($email), [], 'brevo.contacts.get');
        $this->repo->upsertContact($this->extractContactPayload($data, $email), null);
        return $data;
    }

    public function updateContact(string $email, array $input): array
    {
        $email = $this->validateEmailIdentifier($email);
        $payload = $this->validateUpdateInput($input);

        $result = $this->client->put('contacts/' . rawurlencode($email), $payload, 'brevo.contacts.update');

        $this->cache->forget($this->contactCacheKey($email));
        $this->invalidateListCaches();

        // Persistência local (upsert + last_synced_at)
        $data = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : [];
        $this->repo->upsertContact(array_merge($payload, $data, ['email' => $email]), $this->now());

        return $result;
    }

    public function deleteContact(string $email): array
    {
        $email = $this->validateEmailIdentifier($email);

        $result = $this->client->delete('contacts/' . rawurlencode($email), [], 'brevo.contacts.delete');

        $this->cache->forget($this->contactCacheKey($email));
        $this->invalidateListCaches();

        $this->repo->softDeleteContact($email, $this->now());

        return $result;
    }

    public function listContacts(array $params = [], bool $useCache = true): array
    {
        $normalized = $this->validateListInput($params);
        $cacheKey = $this->listCacheKey($normalized);

        if ($useCache) {
            $cached = $this->cache->remember($cacheKey, function () use ($normalized) {
                return $this->client->get('contacts', $normalized, 'brevo.contacts.list');
            }, $this->cacheTtl);

            $this->trackListCacheKey($cacheKey);
            if (is_array($cached)) {
                $this->persistContactsFromListResponse($cached);
            }
            return $cached;
        }

        $result = $this->client->get('contacts', $normalized, 'brevo.contacts.list');
        $this->trackListCacheKey($cacheKey);
        $this->persistContactsFromListResponse($result);
        return $result;
    }

    /**
     * Sync paginado (pull) de contatos para DB local.
     *
     * Atenção: pode ser uma operação pesada.
     */
    public function syncContacts(int $limit = 500): array
    {
        $limit = max(1, min(1000, $limit));

        $runId = $this->repo->startSyncRun('contacts', ['limit' => $limit]);
        $startedAt = microtime(true);

        $offset = 0;
        $processed = 0;
        $pages = 0;

        try {
            while (true) {
                $wrapper = $this->client->get('contacts', [
                    'limit' => $limit,
                    'offset' => $offset,
                ], 'brevo.contacts.sync');

                $response = (isset($wrapper['data']) && is_array($wrapper['data'])) ? $wrapper['data'] : [];

                $contacts = [];
                if (is_array($response) && isset($response['contacts']) && is_array($response['contacts'])) {
                    $contacts = $response['contacts'];
                }

                foreach ($contacts as $contact) {
                    if (is_array($contact)) {
                        $this->repo->upsertContact($contact, $this->now());
                        $processed++;
                    }
                }

                $pages++;
                $offset += $limit;

                $total = (is_array($response) && isset($response['count'])) ? (int)$response['count'] : null;
                if ($total !== null && $offset >= $total) {
                    break;
                }

                if (count($contacts) < $limit) {
                    break;
                }
            }

            $this->invalidateListCaches();
            $this->repo->finishSyncRun($runId, 'success', $processed, 0, 200, null, [
                'pages' => $pages,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);

            return [
                'success' => true,
                'run_id' => $runId,
                'processed' => $processed,
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

    private function validateCreateInput(array $input): array
    {
        $validator = new ValidationService($input, [
            'email' => 'required|email',
        ]);

        if (!$validator->validate()) {
            throw new BrevoApiException('Dados inválidos para criar contato', 400, ['errors' => $validator->errors()]);
        }

        $payload = [
            'email' => strtolower((string)$input['email']),
        ];

        if (isset($input['attributes']) && is_array($input['attributes'])) {
            $payload['attributes'] = $input['attributes'];
        }

        if (isset($input['listIds']) && is_array($input['listIds'])) {
            $payload['listIds'] = array_values(array_map('intval', $input['listIds']));
        }

        if (isset($input['updateEnabled'])) {
            $payload['updateEnabled'] = (bool)$input['updateEnabled'];
        } else {
            $payload['updateEnabled'] = true;
        }

        if (isset($input['emailBlacklisted'])) {
            $payload['emailBlacklisted'] = (bool)$input['emailBlacklisted'];
        }

        if (isset($input['smsBlacklisted'])) {
            $payload['smsBlacklisted'] = (bool)$input['smsBlacklisted'];
        }

        if (isset($input['sms']) && is_string($input['sms'])) {
            $payload['sms'] = $input['sms'];
        }

        return $payload;
    }

    private function validateUpdateInput(array $input): array
    {
        if (!is_array($input) || $input === []) {
            throw new BrevoApiException('Dados inválidos para atualizar contato', 400, []);
        }

        $payload = [];

        if (isset($input['attributes']) && is_array($input['attributes'])) {
            $payload['attributes'] = $input['attributes'];
        }

        if (isset($input['listIds']) && is_array($input['listIds'])) {
            $payload['listIds'] = array_values(array_map('intval', $input['listIds']));
        }

        if (isset($input['unlinkListIds']) && is_array($input['unlinkListIds'])) {
            $payload['unlinkListIds'] = array_values(array_map('intval', $input['unlinkListIds']));
        }

        if (isset($input['emailBlacklisted'])) {
            $payload['emailBlacklisted'] = (bool)$input['emailBlacklisted'];
        }

        if (isset($input['smsBlacklisted'])) {
            $payload['smsBlacklisted'] = (bool)$input['smsBlacklisted'];
        }

        if (isset($input['smtpBlacklistSender']) && is_array($input['smtpBlacklistSender'])) {
            $payload['smtpBlacklistSender'] = $input['smtpBlacklistSender'];
        }

        if (isset($input['sms']) && is_string($input['sms'])) {
            $payload['sms'] = $input['sms'];
        }

        if ($payload === []) {
            throw new BrevoApiException('Nenhum campo atualizável informado', 400, []);
        }

        return $payload;
    }

    private function validateListInput(array $params): array
    {
        $normalized = [];

        if (isset($params['limit'])) {
            $normalized['limit'] = max(1, min(1000, (int)$params['limit']));
        }
        if (isset($params['offset'])) {
            $normalized['offset'] = max(0, (int)$params['offset']);
        }

        if (isset($params['modifiedSince']) && is_string($params['modifiedSince'])) {
            $normalized['modifiedSince'] = $params['modifiedSince'];
        }

        if (isset($params['sort']) && is_string($params['sort'])) {
            $normalized['sort'] = $params['sort'];
        }

        return $normalized;
    }

    private function validateEmailIdentifier(string $email): string
    {
        $email = strtolower(trim($email));
        $validator = new ValidationService(['email' => $email], ['email' => 'required|email']);
        if (!$validator->validate()) {
            throw new BrevoApiException('Identificador de contato inválido (email)', 400, ['email' => $email]);
        }
        return $email;
    }

    private function contactCacheKey(string $email): string
    {
        return 'brevo:contacts:by_email:' . sha1($email);
    }

    private function listCacheKey(array $params): string
    {
        ksort($params);
        return 'brevo:contacts:list:' . sha1(json_encode($params));
    }

    private function trackListCacheKey(string $key): void
    {
        $keys = $this->cache->get($this->listKeysIndexKey);
        if (!is_array($keys)) {
            $keys = [];
        }
        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->cache->set($this->listKeysIndexKey, $keys, 86400);
        }
    }

    private function invalidateListCaches(): void
    {
        $keys = $this->cache->get($this->listKeysIndexKey);
        if (!is_array($keys) || $keys === []) {
            return;
        }

        foreach ($keys as $key) {
            if (is_string($key) && $key !== '') {
                $this->cache->forget($key);
            }
        }

        $this->cache->set($this->listKeysIndexKey, [], 86400);
    }

    private function persistContactsFromListResponse(array $response): void
    {
        if (!isset($response['data']) || !is_array($response['data'])) {
            return;
        }

        $data = $response['data'];
        if (!isset($data['contacts']) || !is_array($data['contacts'])) {
            return;
        }

        foreach ($data['contacts'] as $contact) {
            if (is_array($contact)) {
                $this->repo->upsertContact($contact, null);
            }
        }
    }

    private function extractContactPayload($wrapper, string $fallbackEmail): array
    {
        if (!is_array($wrapper)) {
            return ['email' => $fallbackEmail];
        }

        $data = (isset($wrapper['data']) && is_array($wrapper['data'])) ? $wrapper['data'] : [];
        if (!isset($data['email']) || !is_string($data['email']) || $data['email'] === '') {
            $data['email'] = $fallbackEmail;
        }

        return $data;
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

