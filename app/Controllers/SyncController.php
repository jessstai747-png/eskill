<?php

namespace App\Controllers;

use App\Core\Request;
use App\Database;
use App\Helpers\SessionHelper;
use App\Services\JobService;
use App\Services\SyncStatusService;
use PDO;

/**
 * Controller de Status de Sincronização
 *
 * Endpoints para monitorar sincronizações automáticas
 */
class SyncController
{
    private PDO $db;
    private Request $request;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->request = new Request();
    }

    /**
     * Obtém status de sincronização de todos os recursos
     * GET /api/sync/status
     */
    public function status(): void
    {
        if (!SessionHelper::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $accountId = SessionHelper::getActiveAccountId();

            if (!$accountId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Nenhuma conta ativa selecionada'
                ]);
                return;
            }

            // Buscar status de todos os recursos
            try {
                $stmt = $this->db->prepare("
                    SELECT
                        resource_type,
                        last_sync_at,
                        status,
                        last_sync_id,
                        items_count,
                        error_message,
                        TIMESTAMPDIFF(SECOND, last_sync_at, NOW()) as seconds_ago
                    FROM sync_status
                    WHERE account_id = :account_id
                ");
                $stmt->execute(['account_id' => $accountId]);
                $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Table might not exist, return empty status
                $statuses = [];
            }

            // Formatar resposta
            $response = [];
            foreach ($statuses as $status) {
                $response[$status['resource_type']] = [
                    'last_sync' => $status['last_sync_at'],
                    'status' => $status['status'],
                    'items_count' => $status['items_count'],
                    'seconds_ago' => (int)$status['seconds_ago'],
                    'human_time' => $this->formatTimeAgo((int)$status['seconds_ago']),
                    'error' => $status['error_message']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $response,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao buscar status: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Força sincronização manual de um recurso
     * POST /api/sync/trigger
     */
    public function trigger(): void
    {
        if (!SessionHelper::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $resourceType = $input['resource'] ?? null;

            if (!in_array($resourceType, ['orders', 'items', 'questions'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Tipo de recurso inválido'
                ]);
                return;
            }

            $accountId = SessionHelper::getActiveAccountId();

            if (!$accountId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Nenhuma conta ativa selecionada'
                ]);
                return;
            }

            // ML-001: block sync for disconnected accounts (invalid_grant / reauth required)
            try {
                $stmt = $this->db->prepare(
                    "SELECT status, last_refresh_error FROM ml_accounts WHERE id = :id LIMIT 1"
                );
                $stmt->execute(['id' => $accountId]);
                $account = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($account !== false) {
                    $acctStatus = strtolower(trim((string)($account['status'] ?? '')));
                    $refreshErr  = strtolower(trim((string)($account['last_refresh_error'] ?? '')));
                    $isDisconnected = $acctStatus === 'disconnected'
                        || str_contains($refreshErr, 'invalid_grant');

                    if ($isDisconnected) {
                        http_response_code(422);
                        echo json_encode([
                            'success'       => false,
                            'error'         => 'Conta desconectada — reautorização OAuth necessária antes de sincronizar.',
                            'error_code'    => 'account_disconnected',
                            'needs_reconnect' => true,
                            'reconnect_url' => '/auth/authorize?reconnect=' . (int)$accountId,
                            'action'        => 'reconnect_account',
                        ]);
                        return;
                    }
                }
            } catch (\Exception $e) {
                // Non-fatal: proceed even if status-check query fails (table may not exist on fresh install)
                log_warning('SyncController::trigger — failed to verify account status', [
                    'account_id' => $accountId,
                    'error'      => $e->getMessage(),
                ]);
            }

            $jobTypeMap = [
                'orders' => 'sync_orders',
                'items' => 'sync_items',
                'questions' => 'sync_questions',
            ];
            $defaultLimitMap = [
                'orders' => 100,
                'items' => 50,
                'questions' => 50,
            ];

            $jobService = new JobService();
            $jobPayload = [
                'account_id' => (int)$accountId,
                'limit' => $defaultLimitMap[$resourceType] ?? 50,
                'source' => 'sync_controller_manual',
                'triggered_by_user_id' => SessionHelper::getUserId(),
            ];

            if ($resourceType === 'orders') {
                $jobPayload['page_limit'] = 20;
                $jobPayload['persist_checkpoint'] = true;
            }

            $jobId = $jobService->dispatch($jobTypeMap[$resourceType], $jobPayload);

            // Marcar como "running" após enqueue bem sucedido
            $this->updateSyncStatus($accountId, $resourceType, 'running');

            echo json_encode([
                'success' => true,
                'message' => 'Sincronização enfileirada',
                'resource' => $resourceType,
                'job_id' => $jobId,
                'job_type' => $jobTypeMap[$resourceType],
            ]);
        } catch (\Exception $e) {
            if (!empty($accountId) && !empty($resourceType) && in_array((string)$resourceType, ['orders', 'items', 'questions'], true)) {
                $this->updateSyncStatus((int)$accountId, (string)$resourceType, 'error', $e->getMessage());
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao iniciar sincronização: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Histórico de sincronizações
     * GET /api/sync/history?resource=orders
     */
    public function history(): void
    {
        if (!SessionHelper::isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        header('Content-Type: application/json');

        try {
            $resourceType = $this->request->get('resource', 'orders') ?? 'orders';
            $accountId = SessionHelper::getActiveAccountId();

            // Por enquanto, retorna apenas o último status
            // Futuramente, criar tabela de histórico
            try {
                $stmt = $this->db->prepare("
                    SELECT
                        last_sync_at,
                        status,
                        items_count,
                        error_message
                    FROM sync_status
                    WHERE account_id = :account_id
                    AND resource_type = :resource
                ");
                $stmt->execute([
                    'account_id' => $accountId,
                    'resource' => $resourceType
                ]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                $history = [];
            }

            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Atualiza status de sincronização
     */
    private function updateSyncStatus(int $accountId, string $resourceType, string $status, ?string $error = null): void
    {
        try {
            $service = new SyncStatusService($this->db);
            if ($status === 'running') {
                $service->markRunning($resourceType, $accountId);
                return;
            }

            if ($status === 'error') {
                $service->markError($resourceType, $accountId, $error ?? 'Erro de sincronização');
                return;
            }

            if ($status === 'success') {
                $service->markSuccess($resourceType, $accountId);
                return;
            }

            throw new \InvalidArgumentException('Status de sync inválido: ' . $status);
        } catch (\Exception $e) {
            log_error('Falha ao atualizar status de sincronização', [
                'resource' => $resourceType,
                'account_id' => $accountId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }


    /**
     * Formata tempo em formato legível
     */
    private function formatTimeAgo(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's atrás';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'min atrás';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . 'h atrás';
        } else {
            return floor($seconds / 86400) . 'd atrás';
        }
    }
}
