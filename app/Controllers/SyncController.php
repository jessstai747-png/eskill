<?php

namespace App\Controllers;

use App\Core\Request;
use App\Database;
use App\Helpers\SessionHelper;
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
            
            // Marcar como "running"
            $this->updateSyncStatus($accountId, $resourceType, 'running');
            
            // Executar sincronização em background
            $scriptMap = [
                'orders' => 'cron_sync_orders.php',
                'items' => 'cron_sync_items.php',
                'questions' => 'cron_sync_questions.php'
            ];
            
            $script = __DIR__ . '/../../scripts/' . $scriptMap[$resourceType];
            
            if (file_exists($script)) {
                // Executar em background
                // Security: use escapeshellarg to prevent path injection
                $cmd = "php " . escapeshellarg($script) . " > /dev/null 2>&1 &";
                exec($cmd);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Sincronização iniciada',
                    'resource' => $resourceType
                ]);
            } else {
                $this->updateSyncStatus($accountId, $resourceType, 'error', 'Script não encontrado');
                echo json_encode([
                    'success' => false,
                    'error' => 'Script de sincronização não encontrado'
                ]);
            }
            
        } catch (\Exception $e) {
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
            $stmt = $this->db->prepare("
                INSERT INTO sync_status (resource_type, account_id, status, error_message, last_sync_at, created_at, updated_at)
                VALUES (:resource, :account_id, :status, :error, NOW(), NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    status = :status,
                    error_message = :error,
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                'resource' => $resourceType,
                'account_id' => $accountId,
                'status' => $status,
                'error' => $error
            ]);
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
