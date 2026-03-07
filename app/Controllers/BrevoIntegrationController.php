<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\SessionHelper;
use App\Services\CacheService;
use App\Services\Integrations\Brevo\BrevoApiException;
use App\Services\Integrations\Brevo\BrevoClient;
use App\Services\Integrations\Brevo\BrevoContactsService;
use App\Services\Integrations\Brevo\BrevoListsService;
use App\Services\Integrations\Brevo\BrevoPersistenceRepository;

class BrevoIntegrationController extends BaseController
{
    private BrevoClient $client;
    private BrevoContactsService $contacts;
    private BrevoListsService $lists;
    private BrevoPersistenceRepository $repo;
    private CacheService $cache;

    public function __construct()
    {
        parent::__construct();
        $this->client = new BrevoClient();
        $this->repo = new BrevoPersistenceRepository();
        $this->contacts = new BrevoContactsService($this->client, null, $this->repo);
        $this->lists = new BrevoListsService($this->client, null, $this->repo);
        $this->cache = new CacheService();
    }

    public function health(): void
    {
        $this->requireAuth();

        try {
            $result = $this->client->health();
            $payload = [
                'success' => true,
                'connected' => true,
                'status' => $result['status'] ?? 200,
                'data' => $result['data'] ?? [],
                'checked_at' => date('c'),
            ];
            $this->cache->set('brevo:health:last', $payload, 600);
            $this->sendJson(200, $payload);
        } catch (BrevoApiException $e) {
            $payload = [
                'success' => false,
                'connected' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'checked_at' => date('c'),
            ];
            $this->cache->set('brevo:health:last', $payload, 600);
            $this->sendJson($this->mapUpstreamErrorToHttp($e), $payload);
        }
    }

    public function status(): void
    {
        $this->requireAuth();
        $last = $this->cache->get('brevo:health:last') ?? null;

        $syncLists = null;
        $syncContacts = null;
        try {
            $syncLists = $this->repo->getLatestSyncRun('lists');
            $syncContacts = $this->repo->getLatestSyncRun('contacts');
        } catch (\Throwable $e) {
            // Se o DB não estiver disponível, ainda retornamos status básico.
        }

        $this->sendJson(200, [
            'success' => true,
            'last_check' => $last,
            'sync' => [
                'lists' => $syncLists,
                'contacts' => $syncContacts,
            ],
        ]);
    }

    public function syncLists(): void
    {
        $this->requireAuth();

        try {
            $limit = $this->request->getInt('limit', 50);
            $result = $this->lists->syncLists($limit);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        } catch (\Throwable $e) {
            $this->sendJson(500, [
                'success' => false,
                'error' => 'Falha ao executar sync de listas',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function syncContacts(): void
    {
        $this->requireAuth();

        try {
            $limit = $this->request->getInt('limit', 500);
            $result = $this->contacts->syncContacts($limit);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        } catch (\Throwable $e) {
            $this->sendJson(500, [
                'success' => false,
                'error' => 'Falha ao executar sync de contatos',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function syncAll(): void
    {
        $this->requireAuth();

        try {
            $listsLimit = $this->request->getInt('listsLimit', 50);
            $contactsLimit = $this->request->getInt('contactsLimit', 500);

            $lists = $this->lists->syncLists($listsLimit);
            $contacts = $this->contacts->syncContacts($contactsLimit);

            $this->sendJson(200, [
                'success' => true,
                'lists' => $lists,
                'contacts' => $contacts,
            ]);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        } catch (\Throwable $e) {
            $this->sendJson(500, [
                'success' => false,
                'error' => 'Falha ao executar sync completo',
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function listContacts(): void
    {
        $this->requireAuth();

        try {
            $params = [
                'limit' => $this->request->get('limit'),
                'offset' => $this->request->get('offset'),
                'modifiedSince' => $this->request->get('modifiedSince'),
                'sort' => $this->request->get('sort'),
            ];
            $params = array_filter($params, fn($v) => $v !== null && $v !== '');

            $result = $this->contacts->listContacts($params, true);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
            ]);
        }
    }

    public function getContact(string $email): void
    {
        $this->requireAuth();

        try {
            $result = $this->contacts->getContact($email, true);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
            ]);
        }
    }

    public function createContact(): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $result = $this->contacts->createContact($data);
            $this->sendJson(201, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function updateContact(string $email): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $result = $this->contacts->updateContact($email, $data);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function deleteContact(string $email): void
    {
        $this->requireAuth();

        try {
            $result = $this->contacts->deleteContact($email);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $this->sendJson($this->mapUpstreamErrorToHttp($e), [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
            ]);
        }
    }

    public function listLists(): void
    {
        $this->requireAuth();

        try {
            $params = [
                'limit' => $this->request->get('limit'),
                'offset' => $this->request->get('offset'),
                'sort' => $this->request->get('sort'),
            ];
            $params = array_filter($params, fn($v) => $v !== null && $v !== '');

            $result = $this->lists->listLists($params, true);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function getList(string $listId): void
    {
        $this->requireAuth();

        try {
            $result = $this->lists->getList((int)$listId, true);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function createList(): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $name = (string)($data['name'] ?? '');
            $folderId = isset($data['folderId']) ? (int)$data['folderId'] : null;
            $result = $this->lists->createList($name, $folderId);
            $this->sendJson(201, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function updateList(string $listId): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $name = (string)($data['name'] ?? '');
            $result = $this->lists->updateList((int)$listId, $name);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function deleteList(string $listId): void
    {
        $this->requireAuth();

        try {
            $result = $this->lists->deleteList((int)$listId);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function addContactsToList(string $listId): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $emails = is_array($data['emails'] ?? null) ? $data['emails'] : [];
            $result = $this->lists->addContacts((int)$listId, $emails);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    public function removeContactsFromList(string $listId): void
    {
        $this->requireAuth();

        $data = $this->request->json();
        if (!is_array($data)) {
            $this->sendJson(400, ['success' => false, 'error' => 'Dados inválidos']);
            return;
        }

        try {
            $emails = is_array($data['emails'] ?? null) ? $data['emails'] : [];
            $result = $this->lists->removeContacts((int)$listId, $emails);
            $this->sendJson(200, $result);
        } catch (BrevoApiException $e) {
            $http = $e->getStatusCode() === 400 ? 400 : $this->mapUpstreamErrorToHttp($e);
            $this->sendJson($http, [
                'success' => false,
                'error' => $e->getMessage(),
                'upstream_status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
            ]);
        }
    }

    private function requireAuth(): void
    {
        if (!SessionHelper::isAuthenticated()) {
            $this->sendJson(401, ['success' => false, 'error' => 'Não autenticado']);
            exit;
        }
    }

    private function sendJson(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }

    private function mapUpstreamErrorToHttp(BrevoApiException $e): int
    {
        $details = $e->getDetails();
        if (isset($details['missing_env'])) {
            return 500;
        }

        $up = $e->getStatusCode();
        if ($up === 429) {
            return 503;
        }

        return 502;
    }
}
