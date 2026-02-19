<?php

namespace App\Controllers;

use App\Services\AI\Core\AIOptimizationEngine;
use App\Services\AI\Optimizers\TitleOptimizer;
use App\Helpers\SessionHelper;
use App\Services\UserService;

/**
 * @deprecated Dashboard consolidated into SEO Killer. API endpoints remain functional.
 * New features should go in SEOKillerController.
 *
 * AI Optimization Controller - Handles HTTP requests for AI-powered listing optimization
 */
class AIOptimizationController extends BaseController
{
    private ?AIOptimizationEngine $engine = null;
    private ?int $accountId;
    private UserService $userService;
    
    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
        if (!$this->userService->isAuthenticated()) {
             header('Location: /login');
             exit;
        }

        $this->accountId = SessionHelper::getActiveAccountId();
    }
    
    /**
     * Get or create AI Engine (lazy loading)
     */
    private function getEngine(): AIOptimizationEngine
    {
        if ($this->engine === null) {
            $this->engine = new AIOptimizationEngine();
        }
        return $this->engine;
    }
    
    /**
     * @deprecated Consolidated into SEO Killer. Redirects to /dashboard/seo-killer#ai-insights
     * GET /dashboard/ai-optimization
     */
    public function index(): void
    {
        header('Location: /dashboard/seo-killer#ai-insights', true, 301);
        exit;
    }
    
    /**
     * Optimize a single listing - show editor
     * GET /ai/optimize/{itemId}
     */
    public function show(string $itemId): void
    {
        $suggestions = $this->getEngine()->getSuggestions($itemId);
        
        $pageTitle = 'Optimize Listing: ' . $itemId;
        $activePage = 'ai-optimization';

        ob_start();
        require __DIR__ . '/../Views/dashboard/ai_optimization/editor.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }
    
    /**
     * API: Optimize title
     * POST /api/ai/optimize/title
     */
    public function optimizeTitle(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id is required']);
            return;
        }
        
        $result = $this->getEngine()->optimizeTitle($data['item_id']);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Optimize complete listing
     * POST /api/ai/optimize/complete
     */
    public function optimizeComplete(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id is required']);
            return;
        }
        
        $options = [
            'optimize_title' => $data['optimize_title'] ?? true,
            'optimize_description' => $data['optimize_description'] ?? true,
            'optimize_attributes' => $data['optimize_attributes'] ?? true,
        ];
        
        $result = $this->getEngine()->optimizeListing($data['item_id'], $options);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Batch optimize
     * POST /api/ai/optimize/batch
     */
    public function batchOptimize(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_ids']) || !is_array($data['item_ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_ids array is required']);
            return;
        }
        
        $options = [
            'optimize_title' => $data['optimize_title'] ?? true,
            'optimize_description' => $data['optimize_description'] ?? true,
            'optimize_attributes' => $data['optimize_attributes'] ?? true,
            'delay_ms' => $data['delay_ms'] ?? 500, // Rate limiting
        ];
        
        $result = $this->getEngine()->batchOptimize($data['item_ids'], $options);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Get optimization suggestions
     * GET /api/ai/suggestions/{itemId}
     */
    public function suggestions(string $itemId): void
    {
        $result = $this->getEngine()->getSuggestions($itemId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Analyze title
     * POST /api/ai/analyze/title
     */
    public function analyzeTitle(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['title'])) {
            http_response_code(400);
            echo json_encode(['error' => 'title is required']);
            return;
        }
        
        $optimizer = new TitleOptimizer();
        $result = $optimizer->analyze($data['title'], $data['context'] ?? []);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Get provider info
     * GET /api/ai/info
     */
    public function info(): void
    {
        $info = $this->getEngine()->getProviderInfo();
        
        header('Content-Type: application/json');
        echo json_encode($info);
    }
    
    /**
     * Get optimization statistics
     * 
     * @return array
     */
    private function getOptimizationStats(): array
    {
        $metricsService = new \App\Services\ItemMetricsService($this->accountId);
        $scoreDist = $metricsService->getScoreDistribution();
        
        $queue = new \App\Services\AI\Core\BatchOptimizationQueue();
        $optCounts = $queue->getOptimizationCounts();
        
        $totalItems = $scoreDist['total_scored'] > 0 ? $scoreDist['total_scored'] : 1;
        
        return [
            'total_items' => $scoreDist['total_scored'],
            'optimized_items' => $optCounts['success'], // Count of completed optimizations
            'optimization_rate' => round(($optCounts['success'] / $totalItems) * 100, 1),
            'avg_score_before' => 0, // Would need historical tracking for this
            'avg_score_after' => $scoreDist['avg_score'],
            'pending' => [
                'critical' => $scoreDist['critical'],
                'medium' => $scoreDist['medium'],
                'low' => $scoreDist['low'],
            ],
        ];
    }


    /**
     * API: Optimize description
     * POST /api/ai/optimize/description
     */
    public function optimizeDescription(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id is required']);
            return;
        }
        
        // Get item data
        $itemService = new \App\Services\ItemService($this->accountId);
        $item = $itemService->getItem($data['item_id']);
        
        if (isset($item['error'])) {
            http_response_code(404);
            echo json_encode($item);
            return;
        }
        
        $descriptionOptimizer = new \App\Services\AI\Optimizers\DescriptionOptimizer();
        
        $result = $descriptionOptimizer->generate([
            'title' => $item['title'] ?? '',
            'category' => $item['category_id'] ?? '',
            'brand' => $item['attributes']['BRAND'] ?? '',
            'attributes' => $item['attributes'] ?? [],
            'current_description' => $item['description'] ?? '',
        ]);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Complete tech sheet
     * POST /api/ai/optimize/tech-sheet
     */
    public function optimizeTechSheet(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id is required']);
            return;
        }
        
        $techSheetOptimizer = new \App\Services\AI\Optimizers\TechSheetOptimizer($this->accountId);
        
        $result = $techSheetOptimizer->complete(
            $data['item_id'],
            $data['current_attributes'] ?? []
        );
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * API: Research keywords
     * GET /api/ai/keywords/research
     */
    public function researchKeywords(): void
    {
        $query = $this->request->get('query', '');
        $categoryId = $this->request->get('category_id', '');
        
        if (empty($query)) {
            http_response_code(400);
            echo json_encode(['error' => 'query parameter is required']);
            return;
        }
        
        $keywordService = new \App\Services\AI\Analyzers\KeywordResearchService($this->accountId);
        
        $result = $keywordService->researchKeywords($categoryId, $query);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Analyze competitors
     * GET /api/ai/competitors/analyze
     */
    public function analyzeCompetitors(): void
    {
        $query = $this->request->get('query', '');
        $categoryId = $this->request->get('category_id');
        $limit = $this->request->getInt('limit', 10);
        
        if (empty($query)) {
            http_response_code(400);
            echo json_encode(['error' => 'query parameter is required']);
            return;
        }
        
        $competitiveService = new \App\Services\AI\Analyzers\CompetitiveAnalysisService($this->accountId);
        
        $result = $competitiveService->analyzeCompetitors($query, [
            'category_id' => $categoryId,
            'limit' => $limit,
        ]);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Get provider status
     * GET /api/ai/providers/status
     */
    public function getProviderStatus(): void
    {
        $providerManager = new \App\Services\AI\Core\AIProviderManager();
        
        $result = [
            'available_providers' => $providerManager->getAvailableProviders(),
            'stats' => $providerManager->getStats(),
        ];
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * API: Start batch optimization (add to queue)
     * POST /api/ai/batch/start
     */
    public function startBatchOptimization(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_ids']) || !is_array($data['item_ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_ids array is required']);
            return;
        }
        
        $options = [
            'optimize_title' => $data['optimize_title'] ?? true,
            'optimize_description' => $data['optimize_description'] ?? true,
            'optimize_attributes' => $data['optimize_attributes'] ?? true,
        ];
        
        $priority = $data['priority'] ?? 0;
        
        $queue = new \App\Services\AI\Core\BatchOptimizationQueue();
        $batchId = $queue->addBatch($data['item_ids'], $options, $priority);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'batch_id' => $batchId,
            'total_items' => count($data['item_ids']),
            'message' => 'Batch optimization queued. Start the worker to process.'
        ]);
    }
    
    /**
     * API: Get batch status
     * GET /api/ai/batch/{batchId}/status
     */
    public function getBatchStatus(string $batchId): void
    {
        $queue = new \App\Services\AI\Core\BatchOptimizationQueue();
        $status = $queue->getBatchStatus($batchId);
        
        header('Content-Type: application/json');
        echo json_encode($status);
    }
    
    /**
     * API: Get batch results
     * GET /api/ai/batch/{batchId}/results
     */
    public function getBatchResults(string $batchId): void
    {
        $queue = new \App\Services\AI\Core\BatchOptimizationQueue();
        $results = $queue->getBatchResults($batchId);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    }
    
    /**
     * API: Get queue statistics
     * GET /api/ai/queue/stats
     */
    public function getQueueStats(): void
    {
        $queue = new \App\Services\AI\Core\BatchOptimizationQueue();
        $stats = $queue->getQueueStats();
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * API: Create A/B test
     * POST /api/ai/ab-test/create
     */
    public function createABTest(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['test_name'], $data['item_id'], $data['variant_a'], $data['variant_b'])) {
            http_response_code(400);
            echo json_encode(['error' => 'test_name, item_id, variant_a and variant_b are required']);
            return;
        }
        
        $abTesting = new \App\Services\AI\Testing\ABTestingService();
        $testId = $abTesting->createTest(
            $data['test_name'],
            $data['item_id'],
            $data['variant_a'],
            $data['variant_b']
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'test_id' => $testId,
            'message' => 'A/B test created successfully'
        ]);
    }
    
    /**
     * API: Get A/B test results
     * GET /api/ai/ab-test/{testId}/results
     */
    public function getABTestResults(string $testId): void
    {
        $abTesting = new \App\Services\AI\Testing\ABTestingService();
        $results = $abTesting->getTestResults((int)$testId);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    }
    
    /**
     * API: End A/B test
     * POST /api/ai/ab-test/{testId}/end
     */
    public function endABTest(string $testId): void
    {
        $abTesting = new \App\Services\AI\Testing\ABTestingService();
        $results = $abTesting->endTest((int)$testId);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    }

    /**
     * API: Get audit history
     * GET /api/ai/audit/{itemId}/history
     */
    public function getAuditHistory(string $itemId): void
    {
        $limit = $this->request->getInt('limit', 50);
        
        $auditLog = new \App\Services\AI\Core\AuditLogService();
        $history = $auditLog->getItemHistory($itemId, $limit);
        
        header('Content-Type: application/json');
        echo json_encode($history);
    }
    
    /**
     * API: Rollback optimization
     * POST /api/ai/audit/{logId}/rollback
     */
    public function rollbackOptimization(string $logId): void
    {
        $auditLog = new \App\Services\AI\Core\AuditLogService();
        $result = $auditLog->rollback((int)$logId);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Generate preview
     * POST /api/ai/preview/generate
     */
    public function generatePreview(): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'], $data['optimization_result'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id and optimization_result required']);
            return;
        }
        
        $previewService = new \App\Services\AI\Core\PreviewService();
        $preview = $previewService->generatePreview(
            $data['item_id'],
            $data['optimization_result']
        );
        
        header('Content-Type: application/json');
        echo json_encode($preview);
    }
    
    /**
     * API: Apply preview
     * POST /api/ai/preview/{previewId}/apply
     */
    public function applyPreview(string $previewId): void
    {
        $data = $this->request->json();
        
        if (!isset($data['item_id'], $data['selected_changes'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id and selected_changes required']);
            return;
        }
        
        $previewService = new \App\Services\AI\Core\PreviewService();
        $result = $previewService->applyPreview(
            $previewId,
            $data['item_id'],
            $data['selected_changes']
        );
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * API: Get dashboard metrics
     * GET /api/ai/analytics/dashboard
     */
    public function getDashboardAnalytics(): void
    {
        $days = $this->request->getInt('days', 30);
        
        $analytics = new \App\Services\AI\Analytics\AnalyticsService();
        $metrics = $analytics->getDashboardMetrics($days);
        
        header('Content-Type: application/json');
        echo json_encode($metrics);
    }
    
    /**
     * API: Get executive summary
     * GET /api/ai/analytics/summary
     */
    public function getExecutiveSummary(): void
    {
        $days = $this->request->getInt('days', 30);
        
        $analytics = new \App\Services\AI\Analytics\AnalyticsService();
        $summary = $analytics->getExecutiveSummary($days);
        
        header('Content-Type: application/json');
        echo json_encode($summary);
    }
    
    /**
     * API: Get cost breakdown
     * GET /api/ai/analytics/costs
     */
    public function getCostAnalytics(): void
    {
        $days = $this->request->getInt('days', 30);
        
        $analytics = new \App\Services\AI\Analytics\AnalyticsService();
        $costs = $analytics->getCostBreakdown($days);
        
        header('Content-Type: application/json');
        echo json_encode($costs);
    }

    /**
     * API: Fetch items by score range
     * GET /api/ai/items-by-score
     */
    public function fetchItemsByScore(): void
    {
        header('Content-Type: application/json');

        $minScore = filter_input(INPUT_GET, 'minScore', FILTER_VALIDATE_INT);
        $maxScore = filter_input(INPUT_GET, 'maxScore', FILTER_VALIDATE_INT);

        if ($minScore === false || $maxScore === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros minScore e maxScore inválidos ou ausentes.']);
            return;
        }

        $metricsService = new \App\Services\ItemMetricsService($this->accountId);
        $itemIds = $metricsService->getItemsByScore($minScore, $maxScore);

        echo json_encode($itemIds);
    }

    /**
     * API: Get optimization history list
     * GET /api/ai/optimization/history
     */
    public function getHistory(): void
    {
        header('Content-Type: application/json');
        
        try {
            $limit = $this->request->getIntClamped('limit', 1, 100, 50);
            $offset = $this->request->getInt('offset', 0);
            $status = $this->request->get('status');

            $limitSql = max(1, min(100, (int)$limit));
            $offsetSql = max(0, min(1000000, (int)$offset));
            
            $db = \App\Database::getInstance();
            
            $sql = "
                SELECT 
                    al.id,
                    al.item_id,
                    al.action,
                    al.score_before,
                    al.score_after,
                    al.cost,
                    al.ai_provider,
                    al.ai_model,
                    al.created_at,
                    i.title as item_title,
                    i.thumbnail as item_thumbnail
                FROM ai_audit_log al
                LEFT JOIN items i ON al.item_id = i.ml_item_id
                WHERE al.action IN ('optimize', 'apply', 'rollback')
            ";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND al.action = :status";
                $params['status'] = $status;
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT {$limitSql} OFFSET {$offsetSql}";
            
            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            
            $history = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $db->query("SELECT COUNT(*) FROM ai_audit_log WHERE action IN ('optimize', 'apply', 'rollback')");
            $total = (int)$countStmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $history,
                'total' => $total,
                'limit' => $limitSql,
                'offset' => $offsetSql
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get optimization history detail
     * GET /api/ai/optimization/history/{logId}
     */
    public function getHistoryDetail(string $logId): void
    {
        header('Content-Type: application/json');
        
        try {
            $db = \App\Database::getInstance();
            
            $stmt = $db->prepare("
                SELECT 
                    al.*,
                    i.title as item_title,
                    i.thumbnail as item_thumbnail,
                    i.price as current_price
                FROM ai_audit_log al
                LEFT JOIN items i ON al.item_id = i.ml_item_id
                WHERE al.id = :id
            ");
            $stmt->execute(['id' => $logId]);
            
            $log = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$log) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Log not found']);
                return;
            }
            
            // Parse JSON fields
            $log['changes'] = json_decode($log['changes'] ?? '{}', true);
            $log['metadata'] = json_decode($log['metadata'] ?? '{}', true);
            $log['before_state'] = json_decode($log['before_state'] ?? '{}', true);
            $log['after_state'] = json_decode($log['after_state'] ?? '{}', true);
            
            // Calculate impact estimates based on score improvement
            $scoreDiff = ($log['score_after'] ?? 0) - ($log['score_before'] ?? 0);
            $impact = [
                'views' => '+' . round($scoreDiff * 3.5) . '%',
                'visits' => '+' . round($scoreDiff * 2.0) . '%',
                'sales' => '+' . round($scoreDiff * 1.5) . '%',
                'revenue' => 'Calculando...'
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $log['id'],
                    'item_id' => $log['item_id'],
                    'item_title' => $log['item_title'],
                    'item_thumbnail' => $log['item_thumbnail'],
                    'action' => $log['action'],
                    'before' => [
                        'title' => $log['before_state']['title'] ?? $log['item_title'] ?? 'N/A',
                        'score' => $log['score_before'] ?? 0,
                        'state' => $log['before_state']
                    ],
                    'after' => [
                        'title' => $log['after_state']['title'] ?? $log['item_title'] . ' (Otimizado)',
                        'score' => $log['score_after'] ?? 0,
                        'state' => $log['after_state']
                    ],
                    'changes' => $log['changes'],
                    'impact' => $impact,
                    'cost' => $log['cost'],
                    'ai_provider' => $log['ai_provider'],
                    'ai_model' => $log['ai_model'],
                    'created_at' => $log['created_at']
                ]
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
