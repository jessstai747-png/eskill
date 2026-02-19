<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneAutomationService;
use Exception;

/**
 * Clone Automation Controller
 * 
 * Gerencia regras de auto-clonagem programada
 */
class CloneAutomationController
{
    private int $accountId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->accountId = $_SESSION['account_id'] ?? 0;
        
        if (!$this->accountId) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Lista todas as regras
     * GET /api/clone/automation/rules
     */
    public function listRules(): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            
            $filters = [];
            $status = $this->request->get('status');
            $triggerType = $this->request->get('trigger_type');

            if (!empty($status)) {
                $filters['status'] = $status;
            }
            if (!empty($triggerType)) {
                $filters['trigger_type'] = $triggerType;
            }
            
            $rules = $service->listRules($filters);
            
            echo json_encode([
                'success' => true,
                'rules' => $rules,
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cria uma nova regra
     * POST /api/clone/automation/rules
     */
    public function createRule(): void
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name'])) {
                throw new Exception('Nome da regra é obrigatório');
            }
            
            $service = new CloneAutomationService($this->accountId);
            $ruleId = $service->createRule($input);
            $rule = $service->getRule($ruleId);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Regra criada com sucesso',
                'rule' => $rule,
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém uma regra específica
     * GET /api/clone/automation/rules/{id}
     */
    public function getRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            $rule = $service->getRule($id);
            
            if (!$rule) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'rule' => $rule,
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Atualiza uma regra
     * PUT /api/clone/automation/rules/{id}
     */
    public function updateRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $service = new CloneAutomationService($this->accountId);
            
            if (!$service->getRule($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            $service->updateRule($id, $input);
            $rule = $service->getRule($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Regra atualizada',
                'rule' => $rule,
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Exclui uma regra
     * DELETE /api/clone/automation/rules/{id}
     */
    public function deleteRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            
            if (!$service->getRule($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            $service->deleteRule($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Regra excluída',
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Ativa uma regra
     * POST /api/clone/automation/rules/{id}/enable
     */
    public function enableRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            
            if (!$service->getRule($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            $service->enableRule($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Regra ativada',
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Pausa uma regra
     * POST /api/clone/automation/rules/{id}/pause
     */
    public function pauseRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            
            if (!$service->getRule($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            $service->pauseRule($id);
            
            echo json_encode([
                'success' => true,
                'message' => 'Regra pausada',
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Executa uma regra manualmente
     * POST /api/clone/automation/rules/{id}/execute
     */
    public function executeRule(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $dryRun = !empty($input['dry_run']);
            
            $service = new CloneAutomationService($this->accountId);
            $results = $service->executeRule($id, $dryRun);
            
            echo json_encode([
                'success' => true,
                'results' => $results,
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview de execução (dry run)
     * POST /api/clone/automation/rules/{id}/preview
     */
    public function previewExecution(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            $results = $service->executeRule($id, true);
            
            echo json_encode([
                'success' => true,
                'preview' => $results,
            ]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém histórico de execuções
     * GET /api/clone/automation/rules/{id}/history
     */
    public function getExecutionHistory(int $id): void
    {
        header('Content-Type: application/json');
        
        try {
            $limit = $this->request->getInt('limit', 50);
            
            $service = new CloneAutomationService($this->accountId);
            
            if (!$service->getRule($id)) {
                http_response_code(404);
                echo json_encode(['error' => 'Regra não encontrada']);
                return;
            }
            
            $history = $service->getExecutionHistory($id, $limit);
            
            echo json_encode([
                'success' => true,
                'history' => $history,
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém estatísticas
     * GET /api/clone/automation/stats
     */
    public function getStats(): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneAutomationService($this->accountId);
            $stats = $service->getStats();
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém tipos de trigger disponíveis
     * GET /api/clone/automation/triggers
     */
    public function getTriggerTypes(): void
    {
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'triggers' => [
                [
                    'type' => CloneAutomationService::TRIGGER_SCHEDULE,
                    'name' => 'Agendado',
                    'description' => 'Executa em horários programados',
                ],
                [
                    'type' => CloneAutomationService::TRIGGER_NEW_ITEM,
                    'name' => 'Novo Item',
                    'description' => 'Quando novo item é detectado no seller',
                ],
                [
                    'type' => CloneAutomationService::TRIGGER_PRICE_DROP,
                    'name' => 'Queda de Preço',
                    'description' => 'Quando preço cai X%',
                ],
                [
                    'type' => CloneAutomationService::TRIGGER_MANUAL,
                    'name' => 'Manual',
                    'description' => 'Executado apenas manualmente',
                ],
            ],
            'frequencies' => [
                ['value' => CloneAutomationService::FREQ_HOURLY, 'label' => 'A cada hora'],
                ['value' => CloneAutomationService::FREQ_DAILY, 'label' => 'Diariamente'],
                ['value' => CloneAutomationService::FREQ_WEEKLY, 'label' => 'Semanalmente'],
                ['value' => CloneAutomationService::FREQ_MONTHLY, 'label' => 'Mensalmente'],
            ],
        ]);
    }
}
