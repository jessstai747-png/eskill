<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\ApiAuthMiddleware;
use App\Services\ApiTokenService;
use App\Services\JobService;
use App\Services\OpenClawConnectorService;

/**
 * OpenClawConnectorController
 *
 * API endpoints para integração com o sistema OpenClaw.
 *
 * Endpoints:
 *   GET  /api/openclaw/health              — Health check
 *   GET  /api/openclaw/sellers             — Listar contas ML
 *   GET  /api/openclaw/sellers/{id}        — Detalhe de uma conta ML
 *   GET  /api/openclaw/sellers/{id}/items  — Listar itens de uma conta
 *   GET  /api/openclaw/sellers/{id}/items/{itemId}  — Detalhe de um item
 *   GET  /api/openclaw/sellers/{id}/items/stats     — Estatísticas de itens
 *   GET  /api/openclaw/sellers/{id}/orders          — Listar pedidos
 *   GET  /api/openclaw/sellers/{id}/orders/{orderId} — Detalhe de pedido
 *   POST /api/openclaw/actions             — Criar ação assíncrona
 *   GET  /api/openclaw/actions/{id}        — Status de uma ação
 *   GET  /api/openclaw/webhooks            — Listar webhooks
 *   POST /api/openclaw/webhooks            — Registrar webhook
 *   DELETE /api/openclaw/webhooks/{id}     — Remover webhook
 *   POST /api/openclaw/webhooks/{id}/test  — Testar webhook
 *   GET  /api/openclaw/webhook-events      — Listar eventos disponíveis
 */
class OpenClawConnectorController extends BaseController
{
    private OpenClawConnectorService $service;
    private ApiTokenService $tokenService;

    public function __construct()
    {
        parent::__construct();
        $this->service = new OpenClawConnectorService();
        $this->tokenService = new ApiTokenService();
    }

    // ========================================
    // Health
    // ========================================

    /**
     * GET /api/openclaw — Index / discovery do conector
     */
    public function index(): void
    {
        $this->json([
            'success' => true,
            'service' => 'openclaw-connector',
            'version' => '1.0.0',
            'documentation' => '/api-docs/',
            'endpoints' => [
                'GET  /api/openclaw/health' => 'Health check (requer auth)',
                'GET  /api/openclaw/sellers' => 'Listar contas ML',
                'GET  /api/openclaw/sellers/{id}' => 'Detalhe de conta ML',
                'GET  /api/openclaw/sellers/{id}/items' => 'Listar anúncios',
                'GET  /api/openclaw/sellers/{id}/items/stats' => 'Estatísticas de itens',
                'GET  /api/openclaw/sellers/{id}/items/{itemId}' => 'Detalhe de anúncio',
                'GET  /api/openclaw/sellers/{id}/orders' => 'Listar pedidos',
                'GET  /api/openclaw/sellers/{id}/orders/{orderId}' => 'Detalhe de pedido',
                'POST /api/openclaw/actions' => 'Criar ação assíncrona',
                'GET  /api/openclaw/actions/{id}' => 'Status de ação',
                'GET  /api/openclaw/webhooks' => 'Listar webhooks',
                'POST /api/openclaw/webhooks' => 'Registrar webhook',
                'DELETE /api/openclaw/webhooks/{id}' => 'Remover webhook',
                'POST /api/openclaw/webhooks/{id}/test' => 'Testar webhook',
                'GET  /api/openclaw/webhook-events' => 'Eventos disponíveis',
            ],
            'auth' => [
                'type' => 'Bearer Token',
                'header' => 'Authorization: Bearer <token>',
                'scopes' => ['openclaw:read', 'openclaw:write', 'openclaw:admin'],
            ],
        ], 200);
    }

    public function health(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->resolveUserId();
            $tokenData = ApiAuthMiddleware::getApiUser();

            $this->json([
                'success' => true,
                'service' => 'openclaw-connector',
                'version' => '1.0.0',
                'time' => date('c'),
                'db' => $this->service->isDbAvailable() ? 'ok' : 'unavailable',
                'auth' => [
                    'user_id' => $userId,
                    'token_id' => isset($tokenData['id']) ? (int)$tokenData['id'] : null,
                ],
                'capabilities' => [
                    'sellers',
                    'items',
                    'orders',
                    'actions',
                    'webhooks',
                ],
            ], 200);
        }, 'OpenClawConnectorController::health');
    }

    // ========================================
    // Sellers (Contas ML)
    // ========================================

    /**
     * GET /api/openclaw/sellers
     */
    public function sellers(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();

            $result = $this->service->listSellers($userId);
            $status = ($result['success'] ?? false) ? 200 : 503;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::sellers');
    }

    /**
     * GET /api/openclaw/sellers/{id}
     */
    public function getSeller(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID inválido', 400);
            }

            $seller = $this->service->getSeller($userId, $accountId);
            if ($seller === null) {
                $this->jsonError('Conta não encontrada', 404);
            }

            $this->json(['success' => true, 'seller' => $seller], 200);
        }, 'OpenClawConnectorController::getSeller');
    }

    // ========================================
    // Items (Anúncios)
    // ========================================

    /**
     * GET /api/openclaw/sellers/{id}/items
     */
    public function listItems(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID de conta inválido', 400);
            }

            $filters = [
                'status' => $this->request->input('status'),
                'category_id' => $this->request->input('category_id'),
                'search' => $this->request->input('search'),
                'page' => $this->request->inputInt('page', 1),
                'per_page' => min($this->request->inputInt('per_page', 50), 200),
            ];

            // Remover filtros nulos
            $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

            $result = $this->service->listItems($userId, $accountId, $filters);
            $status = ($result['success'] ?? false) ? 200 : 400;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::listItems');
    }

    /**
     * GET /api/openclaw/sellers/{id}/items/stats
     */
    public function itemsStats(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID de conta inválido', 400);
            }

            $result = $this->service->getItemsStats($userId, $accountId);
            $status = ($result['success'] ?? false) ? 200 : 400;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::itemsStats');
    }

    /**
     * GET /api/openclaw/sellers/{id}/items/{itemId}
     */
    public function getItem(string $id, string $itemId): void
    {
        $this->withErrorHandling(function () use ($id, $itemId): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID de conta inválido', 400);
            }

            $result = $this->service->getItem($userId, $accountId, $itemId);
            if (!($result['success'] ?? false)) {
                $this->json($result, 404);
            }

            $this->json($result, 200);
        }, 'OpenClawConnectorController::getItem');
    }

    // ========================================
    // Orders (Pedidos)
    // ========================================

    /**
     * GET /api/openclaw/sellers/{id}/orders
     */
    public function listOrders(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID de conta inválido', 400);
            }

            $filters = [
                'status' => $this->request->input('status'),
                'date_from' => $this->request->input('date_from'),
                'date_to' => $this->request->input('date_to'),
                'search' => $this->request->input('search'),
                'sort' => $this->request->input('sort'),
                'order' => $this->request->input('order'),
                'page' => $this->request->inputInt('page', 1),
                'per_page' => min($this->request->inputInt('per_page', 50), 200),
            ];

            $filters = array_filter($filters, fn($v) => $v !== null && $v !== '');

            $result = $this->service->listOrders($userId, $accountId, $filters);
            $status = ($result['success'] ?? false) ? 200 : 400;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::listOrders');
    }

    /**
     * GET /api/openclaw/sellers/{id}/orders/{orderId}
     */
    public function getOrder(string $id, string $orderId): void
    {
        $this->withErrorHandling(function () use ($id, $orderId): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $accountId = (int)$id;

            if ($accountId <= 0) {
                $this->jsonError('ID de conta inválido', 400);
            }

            $result = $this->service->getOrder($userId, $accountId, $orderId);
            if (!($result['success'] ?? false)) {
                $this->json($result, 404);
            }

            $this->json($result, 200);
        }, 'OpenClawConnectorController::getOrder');
    }

    // ========================================
    // Actions (Assíncronas)
    // ========================================

    /**
     * POST /api/openclaw/actions
     */
    public function createAction(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_WRITE, OpenClawConnectorService::SCOPE_ADMIN]);

            $payload = $this->request->json() ?? [];
            if (!is_array($payload)) {
                $this->jsonError('JSON inválido', 400);
            }

            $tokenData = ApiAuthMiddleware::getApiUser();
            $apiTokenId = isset($tokenData['id']) ? (int)$tokenData['id'] : null;

            $headerKey = $this->request->header('Idempotency-Key');
            $payloadKey = isset($payload['idempotency_key']) ? (string)$payload['idempotency_key'] : null;
            $idempotencyKey = is_string($payloadKey) && trim($payloadKey) !== ''
                ? $payloadKey
                : (is_string($headerKey) && trim($headerKey) !== '' ? $headerKey : null);

            $userId = $this->resolveUserId();

            $created = $this->service->createAction(
                userId: $userId,
                payload: $payload,
                apiTokenId: $apiTokenId,
                idempotencyKey: $idempotencyKey
            );

            if (($created['success'] ?? false) !== true) {
                $code = ($created['error'] ?? '') === 'validation_error' ? 400 : 503;
                $this->json($created, $code);
            }

            $run = $created['action_run'] ?? null;
            if (!is_array($run) || empty($run['id'])) {
                $this->jsonError('Falha ao criar action_run', 500);
            }

            $runId = (int)$run['id'];

            // Enfileirar execução (best-effort)
            $jobId = null;
            try {
                $jobService = new JobService();
                $jobId = $jobService->dispatch('assistant_action', ['action_run_id' => $runId]);
            } catch (\Throwable $e) {
                log_warning('OpenClawConnectorController: falha ao enfileirar action', [
                    'controller' => 'OpenClawConnectorController',
                    'action_run_id' => $runId,
                    'error' => $e->getMessage(),
                ]);
            }

            $created['job_id'] = $jobId;
            $created['action_run_id'] = $runId;

            $status = ($created['created'] ?? false) ? 202 : 200;
            $this->json($created, $status);
        }, 'OpenClawConnectorController::createAction');
    }

    /**
     * GET /api/openclaw/actions/{id}
     */
    public function getAction(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $actionRunId = (int)$id;

            if ($actionRunId <= 0) {
                $this->jsonError('ID inválido', 400);
            }

            $userId = $this->resolveUserId();
            $run = $this->service->getAction($userId, $actionRunId);

            if ($run === null) {
                $this->jsonError('Ação não encontrada', 404);
            }

            $this->json(['success' => true, 'action_run' => $run], 200);
        }, 'OpenClawConnectorController::getAction');
    }

    // ========================================
    // Webhooks Outbound
    // ========================================

    /**
     * GET /api/openclaw/webhooks
     */
    public function listWebhooks(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_READ, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();

            $result = $this->service->listWebhooks($userId);
            $status = ($result['success'] ?? false) ? 200 : 503;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::listWebhooks');
    }

    /**
     * POST /api/openclaw/webhooks
     */
    public function createWebhook(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_WRITE, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();

            $payload = $this->request->json() ?? [];
            if (!is_array($payload)) {
                $this->jsonError('JSON inválido', 400);
            }

            $name = isset($payload['name']) ? (string)$payload['name'] : '';
            $url = isset($payload['url']) ? (string)$payload['url'] : '';
            $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
            $secret = isset($payload['secret']) ? (string)$payload['secret'] : null;

            $result = $this->service->createWebhook($userId, $name, $url, $events, $secret);

            if (!($result['success'] ?? false)) {
                $code = ($result['error'] ?? '') === 'validation_error' ? 400 : 503;
                $this->json($result, $code);
            }

            $this->json($result, 201);
        }, 'OpenClawConnectorController::createWebhook');
    }

    /**
     * DELETE /api/openclaw/webhooks/{id}
     */
    public function deleteWebhook(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_WRITE, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $webhookId = (int)$id;

            if ($webhookId <= 0) {
                $this->jsonError('ID inválido', 400);
            }

            $result = $this->service->deleteWebhook($userId, $webhookId);
            if (!($result['success'] ?? false)) {
                $this->json($result, 404);
            }

            $this->json($result, 200);
        }, 'OpenClawConnectorController::deleteWebhook');
    }

    /**
     * POST /api/openclaw/webhooks/{id}/test
     */
    public function testWebhook(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireOpenClawScope([OpenClawConnectorService::SCOPE_WRITE, OpenClawConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();
            $webhookId = (int)$id;

            if ($webhookId <= 0) {
                $this->jsonError('ID inválido', 400);
            }

            $result = $this->service->testWebhook($userId, $webhookId);
            $status = ($result['success'] ?? false) ? 200 : 502;
            $this->json($result, $status);
        }, 'OpenClawConnectorController::testWebhook');
    }

    /**
     * GET /api/openclaw/webhook-events
     */
    public function webhookEvents(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireOpenClawScope([
                OpenClawConnectorService::SCOPE_READ,
                OpenClawConnectorService::SCOPE_WRITE,
                OpenClawConnectorService::SCOPE_ADMIN,
            ]);

            $this->json([
                'success' => true,
                'events' => OpenClawConnectorService::getAvailableWebhookEvents(),
            ], 200);
        }, 'OpenClawConnectorController::webhookEvents');
    }

    // ========================================
    // Internals
    // ========================================

    /**
     * Resolve user_id a partir de sessão ou token.
     */
    private function resolveUserId(): int
    {
        $sessionUserId = $this->getUserId();
        if ($sessionUserId !== null && $sessionUserId > 0) {
            return $sessionUserId;
        }

        $apiUserId = ApiAuthMiddleware::getApiUserId();
        if ($apiUserId !== null && $apiUserId > 0) {
            return $apiUserId;
        }

        $this->jsonError('Autenticação necessária', 401);
        return 0;
    }

    /**
     * Verifica se o token tem pelo menos um dos escopos requeridos.
     *
     * @param list<string> $scopes
     */
    private function requireOpenClawScope(array $scopes): void
    {
        $tokenData = ApiAuthMiddleware::getApiUser();
        if (!is_array($tokenData)) {
            // Usuário autenticado via sessão: permitir acesso
            if ($this->getUserId() !== null) {
                return;
            }
            $this->jsonError('Autenticação necessária', 401);
        }

        foreach ($scopes as $scope) {
            if ($this->tokenService->hasScope($tokenData, $scope)) {
                return;
            }
        }

        $this->jsonError('Permissão insuficiente. Scopes necessários: ' . implode(' ou ', $scopes), 403, [
            'required_scopes' => $scopes,
        ]);
    }
}
