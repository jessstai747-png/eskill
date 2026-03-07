<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\UnifiedAIService;
use Exception;

/**
 * Automation Orchestrator Service
 * 
 * Coordinates multi-step AI workflows, chaining multiple services
 * into coherent business processes.
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class AutomationOrchestratorService 
{
    private $unifiedAI;
    private $executor = null;
    private array $workflowStore = [];
    
    public function __construct(?UnifiedAIService $unifiedAI = null)
    {
        $accountId = (string)($_SESSION['account_id'] ?? 'default');
        $this->unifiedAI = $unifiedAI ?? new UnifiedAIService($accountId);
    }

    /**
     * Compatibilidade com controller legado
     */
    public function createWorkflow(array $workflowDefinition): array
    {
        $workflowId = uniqid('wf_', true);
        $workflowType = (string)($workflowDefinition['type'] ?? 'full_optimization_loop');

        $this->workflowStore[$workflowId] = [
            'workflow_type' => $workflowType,
            'workflow_definition' => $workflowDefinition,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return [
            'workflow_id' => $workflowId,
            'workflow_type' => $workflowType,
            'status' => 'pending',
            'created_at' => $this->workflowStore[$workflowId]['created_at'],
        ];
    }

    /**
     * Compatibilidade com controller legado
     */
    public function executeWorkflow(string $workflowId): array
    {
        $stored = $this->workflowStore[$workflowId] ?? null;
        $workflowType = (string)($stored['workflow_type'] ?? 'full_optimization_loop');
        $definition = (array)($stored['workflow_definition'] ?? []);

        $context = (array)($definition['context'] ?? []);
        if ($workflowType === 'full_optimization_loop') {
            $context = array_merge([
                'title' => $definition['name'] ?? 'Workflow automático',
                'description' => $definition['description'] ?? '',
            ], $context);
        }

        $result = $this->runWorkflow($workflowType, $context);
        if (isset($this->workflowStore[$workflowId])) {
            $this->workflowStore[$workflowId]['status'] = ($result['success'] ?? false) ? 'completed' : 'failed';
            $this->workflowStore[$workflowId]['updated_at'] = date('Y-m-d H:i:s');
        }

        return array_merge(['workflow_id' => $workflowId], $result);
    }

    /**
     * Compatibilidade com controller legado
     */
    public function processWorkflowQueue(): array
    {
        return [
            'processed' => 0,
            'pending' => 0,
            'message' => 'Queue processing not configured in in-memory orchestrator',
        ];
    }

    /**
     * Compatibilidade com controller legado
     */
    public function createSmartAutomation(array $conditions, array $actions): array
    {
        return $this->createWorkflow([
            'type' => 'market_monitoring',
            'name' => 'Smart Automation',
            'conditions' => $conditions,
            'actions' => $actions,
            'context' => [
                'category_id' => $conditions['category_id'] ?? null,
            ],
        ]);
    }

    /**
     * Compatibilidade com controller legado
     */
    public function optimizeAutomations(): array
    {
        return [
            'optimized' => 0,
            'message' => 'No persisted workflows available for optimization in this runtime',
        ];
    }

    /**
     * Compatibilidade com controller legado
     */
    public function getAutomationDashboard(): array
    {
        $statusCounts = [
            'pending' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($this->workflowStore as $workflow) {
            $status = $workflow['status'] ?? 'pending';
            if (!isset($statusCounts[$status])) {
                $statusCounts[$status] = 0;
            }
            $statusCounts[$status]++;
        }

        return [
            'total_workflows' => count($this->workflowStore),
            'status_counts' => $statusCounts,
            'recent_workflows' => array_slice(array_values($this->workflowStore), -10),
        ];
    }
    
    /**
     * Run a predefined workflow
     * 
     * @param string $workflowType (e.g., 'full_optimization_loop', 'market_monitoring')
     * @param array $context Data needed for the workflow
     * @return array Workflow results
     */
    public function runWorkflow(string $workflowType, array $context): array
    {
        $startTime = microtime(true);
        $steps = [];
        
        try {
            switch ($workflowType) {
                case 'full_optimization_loop':
                    $result = $this->workflowFullOptimization($context, $steps);
                    break;
                    
                case 'market_monitoring':
                    $result = $this->workflowMarketMonitor($context, $steps);
                    break;
                    
                default:
                    throw new Exception("Workflow [$workflowType] not found.");
            }
            
            return [
                'success' => true,
                'workflow' => $workflowType,
                'duration' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'steps' => $steps,
                'final_output' => $result
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'workflow' => $workflowType,
                'error' => $e->getMessage(),
                'steps' => $steps
            ];
        }
    }
    
    /**
     * Workflow: Full Product Optimization
     * 1. Analyze Current State
     * 2. Optimize Content (SEO + Desc)
     * 3. Predict Performance
     * 4. Suggest Price
     */
    private function workflowFullOptimization(array $item, array &$steps): array
    {
        // Step 1: Analyze
        $steps[] = ['name' => 'Analyze SEO', 'status' => 'running'];
        $analysis = $this->unifiedAI->processAIRequest('analyze_seo', $item);
        $steps[count($steps)-1]['status'] = 'completed';
        
        // Step 2: Optimize
        $steps[] = ['name' => 'Optimize Content', 'status' => 'running'];
        $optimization = $this->unifiedAI->processAIRequest('optimize_seo', $item);
        
        // Merge generated data for next steps
        $optimizedItem = array_merge($item, [
            'title' => $optimization['result']['optimizations']['title'] ?? $item['title'],
            'description' => $optimization['result']['optimizations']['description'] ?? $item['description'] ?? ''
        ]);
        $steps[count($steps)-1]['status'] = 'completed';
        
        // Step 3: Predict
        $steps[] = ['name' => 'Predict Performance', 'status' => 'running'];
        $prediction = $this->unifiedAI->processAIRequest('predict_performance', $optimizedItem);
        $steps[count($steps)-1]['status'] = 'completed';
        
        return [
            'original' => $item,
            'optimized' => $optimizedItem,
            'analysis' => $analysis['result'],
            'prediction' => $prediction['result']
        ];
    }
    
    /**
     * Workflow: Market Monitoring
     * 1. Analyze Category Trends
     * 2. Get Competitor Benchmarks
     */
    private function workflowMarketMonitor(array $context, array &$steps): array
    {
        $categoryId = $context['category_id'] ?? null;
        if (!$categoryId) throw new Exception("Category ID required for market monitoring.");
        
        // Step 1: Market Analysis
        $steps[] = ['name' => 'Analyze Market', 'status' => 'running'];
        $market = $this->unifiedAI->processAIRequest('analyze_market', ['filters' => ['category' => $categoryId]]);
        $steps[count($steps)-1]['status'] = 'completed';
        
        // Step 2: Demand Prediction
        $steps[] = ['name' => 'Predict Demand', 'status' => 'running'];
        $demand = $this->unifiedAI->processAIRequest('predict_demand', ['category_id' => $categoryId]);
        $steps[count($steps)-1]['status'] = 'completed';
        
        return [
            'market_insights' => $market['result'],
            'demand_forecast' => $demand['result']
        ];
    }
}
