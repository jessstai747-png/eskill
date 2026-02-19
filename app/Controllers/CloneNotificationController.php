<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneNotificationService;

/**
 * Clone Notification Controller
 * 
 * Gerencia configuração de webhooks Slack/Discord
 * para notificações de clonagem
 */
class CloneNotificationController
{
    private int $accountId;
    private int $userId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->accountId = (int)($_SESSION['account_id'] ?? 0);
        $this->userId = (int)($_SESSION['user_id'] ?? 0);
        
        if (!$this->userId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * GET /api/clone/notifications/webhooks
     * Lista webhooks configurados
     */
    public function listWebhooks(): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $webhooks = $service->listWebhooks();
            
            echo json_encode([
                'status' => 'success',
                'webhooks' => $webhooks,
                'total' => count($webhooks),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/notifications/slack
     * Configura webhook Slack
     */
    public function configureSlack(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['webhook_url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'webhook_url é obrigatório']);
            return;
        }
        
        // Validar URL do Slack
        if (!preg_match('#^https://hooks\.slack\.com/services/#', $input['webhook_url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'URL de webhook Slack inválida']);
            return;
        }
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            
            $options = [
                'channel' => $input['channel'] ?? null,
                'username' => $input['username'] ?? 'Clone Bot',
                'icon_emoji' => $input['icon_emoji'] ?? ':robot_face:',
                'events' => $input['events'] ?? ['*'],
                'min_severity' => $input['min_severity'] ?? 'info',
            ];
            
            $webhookId = $service->configureSlack($input['webhook_url'], $options);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Webhook Slack configurado com sucesso',
                'webhook_id' => $webhookId,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/notifications/discord
     * Configura webhook Discord
     */
    public function configureDiscord(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['webhook_url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'webhook_url é obrigatório']);
            return;
        }
        
        // Validar URL do Discord
        if (!preg_match('#^https://discord(app)?\.com/api/webhooks/#', $input['webhook_url'])) {
            http_response_code(400);
            echo json_encode(['error' => 'URL de webhook Discord inválida']);
            return;
        }
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            
            $options = [
                'username' => $input['username'] ?? 'Clone Bot',
                'avatar_url' => $input['avatar_url'] ?? null,
                'events' => $input['events'] ?? ['*'],
                'min_severity' => $input['min_severity'] ?? 'info',
            ];
            
            $webhookId = $service->configureDiscord($input['webhook_url'], $options);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Webhook Discord configurado com sucesso',
                'webhook_id' => $webhookId,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/notifications/webhook/{webhookId}/test
     * Testa conexão com webhook
     */
    public function testWebhook(int $webhookId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $result = $service->testWebhook($webhookId);
            
            if ($result['success']) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Teste enviado com sucesso',
                    'details' => $result,
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Falha ao enviar teste',
                    'error' => $result['error'] ?? 'Erro desconhecido',
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/clone/notifications/webhook/{webhookId}/enable
     * Ativa webhook
     */
    public function enableWebhook(int $webhookId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $service->enableWebhook($webhookId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Webhook ativado',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * PUT /api/clone/notifications/webhook/{webhookId}/disable
     * Desativa webhook
     */
    public function disableWebhook(int $webhookId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $service->disableWebhook($webhookId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Webhook desativado',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/clone/notifications/webhook/{webhookId}
     * Remove webhook
     */
    public function deleteWebhook(int $webhookId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $service->deleteWebhook($webhookId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Webhook removido',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/clone/notifications/history
     * Histórico de notificações
     */
    public function getHistory(): void
    {
        header('Content-Type: application/json');
        
        $limit = $this->request->getInt('limit', 100);
        $event = $this->request->get('event');
        
        try {
            $service = new CloneNotificationService($this->accountId, $this->userId);
            $history = $service->getNotificationHistory($limit, $event);
            
            echo json_encode([
                'status' => 'success',
                'history' => $history,
                'total' => count($history),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/clone/notifications/events
     * Lista eventos disponíveis
     */
    public function listEvents(): void
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'status' => 'success',
            'events' => [
                [
                    'id' => CloneNotificationService::EVENT_JOB_STARTED,
                    'name' => 'Job Iniciado',
                    'description' => 'Quando um job de clonagem é iniciado',
                ],
                [
                    'id' => CloneNotificationService::EVENT_JOB_COMPLETED,
                    'name' => 'Job Concluído',
                    'description' => 'Quando um job de clonagem é concluído',
                ],
                [
                    'id' => CloneNotificationService::EVENT_JOB_FAILED,
                    'name' => 'Job Falhou',
                    'description' => 'Quando um job de clonagem falha',
                ],
                [
                    'id' => CloneNotificationService::EVENT_ITEM_CLONED,
                    'name' => 'Item Clonado',
                    'description' => 'Quando um item é clonado com sucesso',
                ],
                [
                    'id' => CloneNotificationService::EVENT_ITEM_FAILED,
                    'name' => 'Item Falhou',
                    'description' => 'Quando a clonagem de um item falha',
                ],
                [
                    'id' => CloneNotificationService::EVENT_BATCH_PROGRESS,
                    'name' => 'Progresso do Batch',
                    'description' => 'Marcos de progresso (25%, 50%, 75%)',
                ],
                [
                    'id' => CloneNotificationService::EVENT_ALERT_CRITICAL,
                    'name' => 'Alerta Crítico',
                    'description' => 'Alertas críticos do sistema',
                ],
                [
                    'id' => CloneNotificationService::EVENT_METRICS_DAILY,
                    'name' => 'Métricas Diárias',
                    'description' => 'Resumo diário de métricas',
                ],
            ],
            'severities' => [
                ['id' => 'info', 'name' => 'Informação', 'color' => '#36a64f'],
                ['id' => 'warning', 'name' => 'Aviso', 'color' => '#FFA500'],
                ['id' => 'error', 'name' => 'Erro', 'color' => '#FF6B6B'],
                ['id' => 'critical', 'name' => 'Crítico', 'color' => '#dc3545'],
            ],
        ]);
    }
}
