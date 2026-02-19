<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AutomationOrchestratorService;
use Exception;

/**
 * Automation Orchestrator Controller V9.0
 * API endpoints para sistema de automação e workflows
 */
class AutomationController extends BaseController
{
    private AutomationOrchestratorService $orchestrator;
    private string $accountId;

    public function __construct()
    {
        parent::__construct();
        $this->requireUserId();
        $this->accountId = (string) ($_SESSION['account_id'] ?? 'default');
        $this->orchestrator = new AutomationOrchestratorService();

        header('Content-Type: application/json');
    }

    /**
     * POST /api/automation/workflow/create
     * Criar novo workflow de automação
     */
    public function createWorkflow(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || !isset($input['workflow_definition'])) {
                throw new Exception("workflow_definition is required");
            }

            $result = $this->orchestrator->createWorkflow($input['workflow_definition']);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Workflow created successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WORKFLOW_CREATION_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/workflow/{workflowId}/execute
     * Executar workflow específico
     */
    public function executeWorkflow(string $workflowId): void
    {
        try {
            $result = $this->orchestrator->executeWorkflow($workflowId);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Workflow execution completed'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WORKFLOW_EXECUTION_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/queue/process
     * Processar fila de workflows
     */
    public function processQueue(): void
    {
        try {
            $result = $this->orchestrator->processWorkflowQueue();

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Workflow queue processed successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'QUEUE_PROCESSING_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/smart-automation/create
     * Criar automação inteligente baseada em regras
     */
    public function createSmartAutomation(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['conditions']) || !isset($input['actions'])) {
                throw new Exception("Both 'conditions' and 'actions' are required");
            }

            $result = $this->orchestrator->createSmartAutomation(
                $input['conditions'],
                $input['actions']
            );

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Smart automation created successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'SMART_AUTOMATION_CREATION_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/optimize
     * Otimizar automações existentes
     */
    public function optimizeAutomations(): void
    {
        try {
            $result = $this->orchestrator->optimizeAutomations();

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => 'Automation optimization completed successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'AUTOMATION_OPTIMIZATION_FAILED'
            ]);
        }
    }

    /**
     * GET /api/automation/dashboard
     * Dashboard de automação e workflows
     */
    public function getDashboard(): void
    {
        try {
            $dashboard = $this->orchestrator->getAutomationDashboard();

            echo json_encode([
                'success' => true,
                'data' => $dashboard,
                'message' => 'Automation dashboard retrieved successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'DASHBOARD_RETRIEVAL_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/workflow/templates/create
     * Criar templates de workflow reutilizáveis
     */
    public function createWorkflowTemplates(): void
    {
        try {
            // Templates pré-definidos para casos comuns de uso
            $templates = [
                'price_optimization' => [
                    'name' => 'Otimização Automática de Preços',
                    'description' => 'Workflow para otimização automática de preços baseada em concorrência e demanda',
                    'steps' => [
                        [
                            'type' => 'data_collection',
                            'action' => 'collect_competitor_prices',
                            'parameters' => ['category_id' => '{{category_id}}']
                        ],
                        [
                            'type' => 'analysis',
                            'action' => 'analyze_price_elasticity',
                            'parameters' => ['item_id' => '{{item_id}}']
                        ],
                        [
                            'type' => 'decision',
                            'action' => 'calculate_optimal_price',
                            'parameters' => ['margin_target' => '{{margin_target}}']
                        ],
                        [
                            'type' => 'execution',
                            'action' => 'update_item_price',
                            'parameters' => ['apply_immediately' => false]
                        ]
                    ],
                    'triggers' => ['schedule', 'price_change_detected', 'competitor_update'],
                    'estimated_duration' => 300
                ],
                'inventory_restock' => [
                    'name' => 'Reposição Automática de Estoque',
                    'description' => 'Workflow para reposição inteligente baseada em previsão de demanda',
                    'steps' => [
                        [
                            'type' => 'analysis',
                            'action' => 'predict_demand',
                            'parameters' => ['horizon_days' => 30]
                        ],
                        [
                            'type' => 'calculation',
                            'action' => 'calculate_reorder_point',
                            'parameters' => ['safety_stock' => '{{safety_stock}}']
                        ],
                        [
                            'type' => 'validation',
                            'action' => 'check_supplier_availability',
                            'parameters' => []
                        ],
                        [
                            'type' => 'execution',
                            'action' => 'create_purchase_order',
                            'parameters' => ['auto_approve' => false]
                        ]
                    ],
                    'triggers' => ['low_stock_alert', 'demand_spike_detected'],
                    'estimated_duration' => 180
                ],
                'campaign_optimization' => [
                    'name' => 'Otimização de Campanhas Publicitárias',
                    'description' => 'Workflow para otimização automática de campanhas baseada em performance',
                    'steps' => [
                        [
                            'type' => 'data_collection',
                            'action' => 'collect_campaign_metrics',
                            'parameters' => ['campaign_id' => '{{campaign_id}}']
                        ],
                        [
                            'type' => 'analysis',
                            'action' => 'analyze_campaign_performance',
                            'parameters' => []
                        ],
                        [
                            'type' => 'optimization',
                            'action' => 'optimize_bid_strategy',
                            'parameters' => ['target_roas' => '{{target_roas}}']
                        ],
                        [
                            'type' => 'execution',
                            'action' => 'update_campaign_settings',
                            'parameters' => ['apply_gradually' => true]
                        ]
                    ],
                    'triggers' => ['performance_threshold', 'budget_alert'],
                    'estimated_duration' => 240
                ],
                'listing_optimization' => [
                    'name' => 'Otimização de Anúncios',
                    'description' => 'Workflow para otimização completa de anúncios (SEO, imagens, descrição)',
                    'steps' => [
                        [
                            'type' => 'analysis',
                            'action' => 'analyze_seo_score',
                            'parameters' => ['item_id' => '{{item_id}}']
                        ],
                        [
                            'type' => 'optimization',
                            'action' => 'optimize_title_keywords',
                            'parameters' => []
                        ],
                        [
                            'type' => 'optimization',
                            'action' => 'enhance_description',
                            'parameters' => []
                        ],
                        [
                            'type' => 'validation',
                            'action' => 'validate_images_quality',
                            'parameters' => []
                        ],
                        [
                            'type' => 'execution',
                            'action' => 'update_listing',
                            'parameters' => ['preview_mode' => true]
                        ]
                    ],
                    'triggers' => ['low_seo_score', 'listing_created'],
                    'estimated_duration' => 420
                ],
                'competitor_monitoring' => [
                    'name' => 'Monitoramento de Concorrência',
                    'description' => 'Workflow para monitoramento contínuo da concorrência e alertas',
                    'steps' => [
                        [
                            'type' => 'data_collection',
                            'action' => 'scan_competitor_listings',
                            'parameters' => ['category_id' => '{{category_id}}']
                        ],
                        [
                            'type' => 'analysis',
                            'action' => 'detect_price_changes',
                            'parameters' => []
                        ],
                        [
                            'type' => 'analysis',
                            'action' => 'identify_new_competitors',
                            'parameters' => []
                        ],
                        [
                            'type' => 'notification',
                            'action' => 'send_alerts',
                            'parameters' => ['alert_threshold' => '{{alert_threshold}}']
                        ]
                    ],
                    'triggers' => ['schedule'],
                    'estimated_duration' => 600
                ]
            ];

            echo json_encode([
                'success' => true,
                'data' => [
                    'templates' => $templates,
                    'total_templates' => count($templates),
                    'categories' => [
                        'pricing' => ['price_optimization'],
                        'inventory' => ['inventory_restock'],
                        'marketing' => ['campaign_optimization'],
                        'seo' => ['listing_optimization'],
                        'monitoring' => ['competitor_monitoring']
                    ]
                ],
                'message' => 'Workflow templates retrieved successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TEMPLATE_CREATION_FAILED'
            ]);
        }
    }

    /**
     * POST /api/automation/workflow/template/{templateId}/instantiate
     * Instanciar workflow a partir de template
     */
    public function instantiateFromTemplate(string $templateId): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $parameters = $input['parameters'] ?? [];

            // Buscar template (simulado)
            $template = $this->getWorkflowTemplate($templateId);

            if (!$template) {
                throw new Exception("Template not found: {$templateId}");
            }

            // Substituir parâmetros no template
            $workflowDefinition = $this->replaceTemplateParameters($template, $parameters);

            // Criar workflow
            $result = $this->orchestrator->createWorkflow($workflowDefinition);

            echo json_encode([
                'success' => true,
                'data' => [
                    'workflow' => $result,
                    'template_used' => $templateId,
                    'parameters_applied' => $parameters
                ],
                'message' => 'Workflow created from template successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'TEMPLATE_INSTANTIATION_FAILED'
            ]);
        }
    }

    /**
     * GET /api/automation/workflow/{workflowId}/status
     * Obter status detalhado do workflow
     */
    public function getWorkflowStatus(string $workflowId): void
    {
        try {
            $db = \App\Database::getInstance();

            // Buscar workflow real do banco
            $stmt = $db->prepare("
                SELECT id, workflow_type, status, progress_percentage,
                       steps_completed, total_steps, current_step_data,
                       execution_history, started_at, estimated_completion,
                       updated_at, error_message
                FROM automation_workflows
                WHERE id = :workflow_id
                LIMIT 1
            ");
            $stmt->execute(['workflow_id' => $workflowId]);
            $workflow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$workflow) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Workflow não encontrado',
                    'error_code' => 'WORKFLOW_NOT_FOUND'
                ]);
                return;
            }

            $currentStep = json_decode($workflow['current_step_data'] ?? '{}', true) ?: [];
            $executionHistory = json_decode($workflow['execution_history'] ?? '[]', true) ?: [];

            $stepsCompleted = intval($workflow['steps_completed'] ?? 0);
            $totalSteps = intval($workflow['total_steps'] ?? 1);
            $progress = $totalSteps > 0 ? round(($stepsCompleted / $totalSteps) * 100, 1) : 0;

            $status = [
                'workflow_id' => $workflowId,
                'current_status' => $workflow['status'] ?? 'unknown',
                'progress_percentage' => $progress,
                'steps_completed' => $stepsCompleted,
                'total_steps' => $totalSteps,
                'current_step' => $currentStep,
                'execution_history' => $executionHistory,
                'started_at' => $workflow['started_at'] ?? null,
                'estimated_completion' => $workflow['estimated_completion'] ?? null,
                'error_message' => $workflow['error_message'] ?? null,
                'last_updated' => $workflow['updated_at'] ?? date('Y-m-d H:i:s'),
            ];

            echo json_encode([
                'success' => true,
                'data' => $status,
                'message' => 'Workflow status retrieved successfully'
            ]);
        } catch (\PDOException $e) {
            // Tabela pode não existir ainda — retornar 404 legível
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Workflow não encontrado ou tabela de workflows não inicializada',
                'error_code' => 'WORKFLOW_TABLE_MISSING'
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'WORKFLOW_STATUS_RETRIEVAL_FAILED'
            ]);
        }
    }

    /**
     * Métodos auxiliares
     */
    private function getWorkflowTemplate(string $templateId): ?array
    {
        // Tentar buscar do banco de dados
        try {
            $db = \App\Database::getInstance();
            $stmt = $db->prepare("
                SELECT template_id, type, name, description, steps_json
                FROM automation_workflow_templates
                WHERE template_id = :template_id AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute(['template_id' => $templateId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'type' => $row['type'] ?? $templateId,
                    'name' => $row['name'] ?? $templateId,
                    'description' => $row['description'] ?? '',
                    'steps' => json_decode($row['steps_json'] ?? '[]', true) ?: [],
                ];
            }
        } catch (\PDOException $e) {
            // Tabela pode não existir. Usar fallback built-in.
        }

        // Fallback: templates built-in para funcionalidades core
        $builtInTemplates = [
            'price_optimization' => [
                'type' => 'price_optimization',
                'steps' => [
                    ['type' => 'data_collection', 'action' => 'collect_competitor_prices'],
                    ['type' => 'analysis', 'action' => 'analyze_price_elasticity'],
                    ['type' => 'decision', 'action' => 'calculate_optimal_price'],
                    ['type' => 'execution', 'action' => 'update_item_price'],
                ],
            ],
            'seo_optimization' => [
                'type' => 'seo_optimization',
                'steps' => [
                    ['type' => 'data_collection', 'action' => 'analyze_current_seo'],
                    ['type' => 'analysis', 'action' => 'research_keywords'],
                    ['type' => 'optimization', 'action' => 'optimize_title_description'],
                    ['type' => 'validation', 'action' => 'validate_changes'],
                    ['type' => 'execution', 'action' => 'apply_optimizations'],
                ],
            ],
            'inventory_management' => [
                'type' => 'inventory_management',
                'steps' => [
                    ['type' => 'data_collection', 'action' => 'check_stock_levels'],
                    ['type' => 'analysis', 'action' => 'forecast_demand'],
                    ['type' => 'decision', 'action' => 'calculate_reorder_point'],
                    ['type' => 'notification', 'action' => 'send_restock_alert'],
                ],
            ],
        ];

        return $builtInTemplates[$templateId] ?? null;
    }

    private function replaceTemplateParameters(array $template, array $parameters): array
    {
        $definition = $template;

        // Substituir parâmetros nos steps
        foreach ($definition['steps'] as &$step) {
            if (isset($step['parameters'])) {
                foreach ($step['parameters'] as $key => &$value) {
                    if (is_string($value) && strpos($value, '{{') !== false) {
                        $paramName = trim($value, '{}');
                        if (isset($parameters[$paramName])) {
                            $value = $parameters[$paramName];
                        }
                    }
                }
            }
        }

        return $definition;
    }
}
