<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AI\ML\PredictiveAnalytics;
use App\Services\DecisionEngineService;
use App\Services\AI\SEO\AutoPilot;
use App\Services\AI\Core\Harness\StateManager;
use App\Services\UserService;
use App\Database;

class AICenterController extends BaseController
{
    private $decisionEngine;
    private $predictiveAnalytics;
    private ?AutoPilot $autoPilot = null;
    private $stateManager;
    private $userService;
    private $db;

    public function __construct(
        DecisionEngineService $decisionEngine,
        PredictiveAnalytics $predictiveAnalytics,
        StateManager $stateManager,
        UserService $userService
    ) {
        parent::__construct();
        $this->decisionEngine = $decisionEngine;
        $this->predictiveAnalytics = $predictiveAnalytics;
        $this->stateManager = $stateManager;
        $this->userService = $userService;
        $this->db = Database::getInstance();
    }

    /**
     * Lazy-load AutoPilot with session accountId
     */
    private function getAutoPilot(): AutoPilot
    {
        if ($this->autoPilot === null) {
            $accountId = $this->getActiveAccountId() ?? 0;
            $this->autoPilot = new AutoPilot($accountId);
        }
        return $this->autoPilot;
    }

    /**
     * Display the AI Center Dashboard
     */
    public function index()
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $user = $this->userService->getCurrentUser();

        // Basic stats for the view
        $stats = $this->getOverviewStatsData($user['account_id'] ?? null);

        $pageTitle = 'AI Center';
        $activePage = 'ai-center';

        // Load view with data
        ob_start();
        require_once __DIR__ . '/../Views/dashboard/ai-center.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Helper to return JSON response
     */
    private function jsonResponse(array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * API: Get Overview Statistics
     * Aggregates data from all AI services
     */
    public function getOverviewStats()
    {
        $user = $this->userService->getCurrentUser();
        $accountId = $user['account_id'] ?? null;

        $data = $this->getOverviewStatsData($accountId);

        $this->jsonResponse($data);
    }

    /**
     * API: Get Automation Status (Harness + AutoPilot)
     */
    public function getAutomationStatus()
    {
        // harness state from DB
        $harnessState = $this->stateManager->getLastState();

        // AutoPilot config/status
        $autoPilotConfig = $this->getAutoPilot()->getConfig();
        $autoPilotStats = $this->getAutoPilot()->getStats();

        $this->jsonResponse([
            'harness' => [
                'status' => $harnessState['status'] ?? 'offline',
                'last_heartbeat' => $harnessState['last_heartbeat'] ?? null,
                'current_task' => $harnessState['current_feature_id'] ?? 'Idle',
                'uptime_formatted' => $this->formatUptime($harnessState['started_at'] ?? null)
            ],
            'autopilot' => [
                'enabled' => $autoPilotConfig['enabled'] ?? false,
                'mode' => $autoPilotConfig['mode'] ?? 'standard',
                'active_optimizations' => $autoPilotStats['active_items'] ?? 0,
                'last_run' => $autoPilotStats['last_run'] ?? null
            ]
        ]);
    }

    /**
     * Helper to gather stats data
     */
    private function getOverviewStatsData($accountId)
    {
        try {
            $decisionStats = $this->decisionEngine->getPerformanceMetrics();
        } catch (\Throwable $e) {
            $decisionStats = [
                'total_decisions' => 0,
                'pricing_updates' => 0,
                'inventory_alerts' => 0,
                'accuracy' => '0%',
                'error' => 'decision_metrics_unavailable',
            ];
        }

        try {
            $predictiveStats = $this->predictiveAnalytics->getDashboardMetrics($accountId);
        } catch (\Throwable $e) {
            $predictiveStats = [
                'predictions_total' => 0,
                'avg_confidence' => 0,
                'error' => 'predictive_metrics_unavailable',
            ];
        }

        // 3. SEO / AutoPilot Stats
        $seoStats = $this->getAutoPilot()->getStats();

        // 4. Activity Feed (Real Logs)
        $auditService = new \App\Services\AuditLogService();
        $recentActivity = $auditService->getLogs([
            'account_id' => $accountId,
            'limit' => 5
        ]);

        return [
            'decisions' => $decisionStats,
            'predictive' => $predictiveStats,
            'seo' => $seoStats,
            'activity' => $recentActivity
        ];
    }

    /**
     * API: Save AI Configuration
     */
    public function saveConfig()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        if (!$this->userService->isAuthenticated()) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $provider = $input['provider'] ?? null;
        $key = $input['key'] ?? null;
        $model = $input['model'] ?? 'claude-3-5-sonnet-20241022';

        if (!$provider || !$key) {
            $this->jsonResponse(['success' => false, 'error' => 'Missing provider or key'], 400);
            return;
        }

        try {
            $user = $this->userService->getCurrentUser();
            $accountId = $user['account_id'] ?? null;

            // Use full path as we didn't import AIConfigService at top
            $configService = new \App\Services\AI\Core\AIConfigService($accountId);
            $configService->setApiKey($provider, $key);
            // $configService->setModelPreference('general', $model); 

            $this->jsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Trigger Manual Workflow
     */
    public function triggerWorkflow()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $workflow = $input['workflow'] ?? '';
        $data = $input['data'] ?? [];

        if (empty($workflow)) {
            $this->jsonResponse(['success' => false, 'error' => 'Workflow required'], 400);
            return;
        }

        try {
            $user = $this->userService->getCurrentUser();
            $accountId = $user['account_id'] ?? null;

            // Initialize Orchestrator via UnifiedAI
            $unified = new \App\Services\UnifiedAIService($accountId);
            $orchestrator = new \App\Services\AutomationOrchestratorService($unified);

            $result = $orchestrator->runWorkflow($workflow, $data);

            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get Predictive Insights (Trends, Demand, Competitor)
     */
    public function getPredictiveInsights()
    {
        try {
            $user = $this->userService->getCurrentUser();
            $accountId = $user['account_id'] ?? null;

            // In a real scenario, these would come from request params or user config
            $defaultCategory = 'MLB1055';
            $defaultSku = 'TEST-SKU-PREDICT';

            $unified = new \App\Services\UnifiedAIService($accountId);

            // Parallel execution (simulated)
            $trends = $unified->processAIRequest('analyze_market_trends', ['category_id' => $defaultCategory]);
            $demand = $unified->processAIRequest('forecast_demand', ['sku' => $defaultSku, 'days' => 30]);

            // Get real tracked competitor if available
            $competitorId = $this->getFirstTrackedCompetitor($accountId);
            $competitor = $competitorId
                ? $unified->processAIRequest('analyze_competitor', ['competitor_id' => $competitorId])
                : ['result' => null, 'message' => 'Nenhum competidor monitorado'];

            $this->jsonResponse([
                'market_trends' => $trends['result'] ?? null,
                'demand_forecast' => $demand['result'] ?? null,
                'competitor_intel' => $competitor['result'] ?? null
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * API: Get Autonomous Operations Statistics
     */
    public function getAutonomousStats()
    {
        try {
            $user = $this->userService->getCurrentUser();
            $accountId = $user['account_id'] ?? null;

            // Inventory Alerts
            $inventoryManager = new \App\Services\InventoryAutoManager();
            $inventoryScan = $inventoryManager->checkAllItems($accountId);

            // Pending Listing Drafts
            $listingCreator = new \App\Services\ListingAutoCreator($accountId);
            $pendingDrafts = $listingCreator->getPendingDrafts(5);

            // Recent Pricing Runs
            $stmt = $this->db->query("
                SELECT * FROM pricing_optimization_runs ORDER BY completed_at DESC LIMIT 3
            ");
            $pricingRuns = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];

            $this->jsonResponse([
                'inventory' => [
                    'scanned' => $inventoryScan['scanned'],
                    'low_stock_count' => count($inventoryScan['low_stock']),
                    'critical_stock_count' => count($inventoryScan['critical_stock']),
                    'critical_items' => array_slice($inventoryScan['critical_stock'], 0, 3)
                ],
                'listings' => [
                    'pending_drafts' => count($pendingDrafts),
                    'recent_drafts' => $pendingDrafts
                ],
                'pricing' => [
                    'recent_runs' => $pricingRuns
                ]
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function formatUptime($startTime)
    {
        if (!$startTime) return '0h 0m';
        $start = new \DateTime($startTime);
        $now = new \DateTime();
        $diff = $start->diff($now);
        return $diff->format('%dh %im');
    }

    /**
     * Get first tracked competitor for the account
     */
    private function getFirstTrackedCompetitor(?int $accountId): ?string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT competitor_seller_id 
                FROM competitor_tracking 
                WHERE account_id = :account_id OR :account_id IS NULL
                LIMIT 1
            ");
            $stmt->execute(['account_id' => $accountId]);
            return $stmt->fetchColumn() ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
