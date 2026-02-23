<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\ApiAuthMiddleware;
use App\Services\ApiTokenService;
use App\Services\AssistantConnectorService;
use App\Services\JobService;

class AssistantConnectorController extends BaseController
{
    private AssistantConnectorService $service;
    private ApiTokenService $tokenService;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AssistantConnectorService();
        $this->tokenService = new ApiTokenService();
    }

    public function health(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->resolveUserId();
            $tokenData = ApiAuthMiddleware::getApiUser();

            $this->json([
                'success' => true,
                'service' => 'assistant-connector',
                'time' => date('c'),
                'db' => $this->service->isDbAvailable() ? 'ok' : 'unavailable',
                'auth' => [
                    'user_id' => $userId,
                    'token_id' => isset($tokenData['id']) ? (int)$tokenData['id'] : null,
                ],
            ], 200);
        }, 'AssistantConnectorController::health');
    }

    /**
     * GET /api/assistant/sellers
     */
    public function sellers(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireAssistantScope([AssistantConnectorService::SCOPE_READ, AssistantConnectorService::SCOPE_ADMIN]);
            $userId = $this->resolveUserId();

            $result = $this->service->listSellersForUser($userId);
            if (($result['success'] ?? false) !== true) {
                $this->json($result, 503);
            }

            $this->json($result, 200);
        }, 'AssistantConnectorController::sellers');
    }

    /**
     * POST /api/assistant/events
     */
    public function ingestEvent(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireAssistantScope([AssistantConnectorService::SCOPE_WRITE, AssistantConnectorService::SCOPE_ADMIN]);

            $payload = $this->request->json() ?? [];
            if (!is_array($payload)) {
                $this->jsonError('JSON inválido', 400);
            }

            $userId = $this->resolveUserId();
            $result = $this->service->ingestEvent($userId, $payload);
            if (($result['success'] ?? false) !== true) {
                $code = ($result['error'] ?? '') === 'validation_error' ? 400 : 503;
                $this->json($result, $code);
            }

            $status = ($result['created'] ?? false) ? 201 : 200;
            $this->json($result, $status);
        }, 'AssistantConnectorController::ingestEvent');
    }

    /**
     * POST /api/assistant/actions
     */
    public function createAction(): void
    {
        $this->withErrorHandling(function (): void {
            $this->requireAssistantScope([AssistantConnectorService::SCOPE_WRITE, AssistantConnectorService::SCOPE_ADMIN]);

            $payload = $this->request->json() ?? [];
            if (!is_array($payload)) {
                $this->jsonError('JSON inválido', 400);
            }

            $tokenData = ApiAuthMiddleware::getApiUser();
            $apiTokenId = isset($tokenData['id']) ? (int)$tokenData['id'] : null;

            // Idempotency-Key: header opcional (padrão de APIs idempotentes)
            $headerKey = $this->request->header('Idempotency-Key');
            $payloadKey = isset($payload['idempotency_key']) ? (string)$payload['idempotency_key'] : null;
            $idempotencyKey = is_string($payloadKey) && trim($payloadKey) !== ''
                ? $payloadKey
                : (is_string($headerKey) && trim($headerKey) !== '' ? $headerKey : null);

            $userId = $this->resolveUserId();

            $created = $this->service->createActionRun(
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
                $this->service->attachJobToActionRun($runId, $jobId);
            } catch (\Throwable $e) {
                log_warning('AssistantConnectorController: falha ao enfileirar assistant_action', [
                    'controller' => 'AssistantConnectorController',
                    'action_run_id' => $runId,
                    'error' => $e->getMessage(),
                ]);
            }

            $created['job_id'] = $jobId;
            $created['action_run_id'] = $runId;

            // 202 para execução assíncrona, 200 se idempotente e já existia
            $status = ($created['created'] ?? false) ? 202 : 200;
            $this->json($created, $status);
        }, 'AssistantConnectorController::createAction');
    }

    /**
     * GET /api/assistant/actions/{id}
     */
    public function getAction(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $this->requireAssistantScope([AssistantConnectorService::SCOPE_READ, AssistantConnectorService::SCOPE_ADMIN]);
            $actionRunId = (int)$id;
            if ($actionRunId <= 0) {
                $this->jsonError('ID inválido', 400);
            }

            $userId = $this->resolveUserId();
            $run = $this->service->getActionRunForUser($userId, $actionRunId);
            if ($run === null) {
                $this->jsonError('Não encontrado', 404);
            }

            $this->json([
                'success' => true,
                'action_run' => $run,
            ], 200);
        }, 'AssistantConnectorController::getAction');
    }

    // -------------------------
    // Internals
    // -------------------------

    /**
     * Resolve user_id a partir de session ou token.
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
     * @param array<int, string> $scopes
     */
    private function requireAssistantScope(array $scopes): void
    {
        $tokenData = ApiAuthMiddleware::getApiUser();
        if (!is_array($tokenData)) {
            // Session-based auth: permitir se usuário está logado.
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

        $this->jsonError('Permissão insuficiente', 403, [
            'required_scopes' => $scopes,
        ]);
    }
}
