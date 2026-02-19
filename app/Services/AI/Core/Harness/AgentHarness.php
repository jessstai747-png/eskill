<?php

namespace App\Services\AI\Core\Harness;

use App\Database;
use Exception;

/**
 * The Main Agent Harness.
 * Orchestrates the lifecycle of long-running agents.
 * 
 * 1. Initializer: Checks environment.
 * 2. Feature List: Gets next job.
 * 3. Incremental Progress: Executes job.
 * 4. Clean State: Verifies state after job.
 */
class AgentHarness
{
    private HarnessInitializer $initializer;
    private FeatureManager $featureManager;
    private StateManager $stateManager;
    private bool $running = false;

    public function __construct(
        HarnessInitializer $initializer = null, 
        FeatureManager $featureManager = null,
        StateManager $stateManager = null
    ) {
        $this->initializer = $initializer ?? new HarnessInitializer();
        $this->featureManager = $featureManager ?? new FeatureManager();
        $this->stateManager = $stateManager ?? new StateManager('WorkerAgent');
    }

    /**
     * Start the harness loop.
     */
    public function run(): void
    {
        echo "🤖 [AgentHarness] Starting up...\n";

        // 1. Initializer Phase
        $this->stateManager->updateStatus('initializing');
        if (!$this->initializer->validateEnvironment()) {
            echo "❌ [AgentHarness] Environment check failed. Aborting.\n";
            $this->stateManager->updateStatus('failed'); // or similar
            return;
        }
        echo "✅ [AgentHarness] Environment healthy.\n";

        $this->running = true;
        
        // Handle signals for graceful shutdown
        $this->setupSignalHandlers();

        // 2. Main Loop
        while ($this->running) {
            try {
                $this->stateManager->heartbeat();
                $this->runCycle();
            } catch (Exception $e) {
                echo "❌ [AgentHarness] Critical Error: " . $e->getMessage() . "\n";
                $this->stateManager->updateStatus('error', null);
                sleep(5); // Prevent rapid failure loops
            }
        }

        echo "🛑 [AgentHarness] Shutdown complete.\n";
        $this->stateManager->updateStatus('stopped');
    }

    /**
     * Single cycle of the harness.
     */
    private function runCycle(): void
    {
        // 3. Feature Selection
        $this->stateManager->updateStatus('idle');
        $feature = $this->featureManager->getNextFeature();

        if (!$feature) {
            // echo "💤 [AgentHarness] No features pending. Waiting...\n";
            sleep(5);
            return;
        }

        // 4. Execution (Incremental Progress)
        $this->stateManager->updateStatus('working', $feature['item_id'] ?? null); // using item_id as feature identifier for now
        
        $status = 'UNKNOWN';
        
        if (isset($feature['type']) && $feature['type'] === 'question_answer') {
            // Process Question
            echo "💬 [AgentHarness] Processing Question {$feature['question_id']}...\n";
            try {
                $qService = new \App\Services\QuestionService();
                $result = $qService->generateDraftAnswer($feature['question_id']);
                $status = ($result['success'] ?? false) ? 'SUCCESS' : 'FAILED';
                
                // If success, we might want to "answer" it or stick to draft.
                // For now, drafting is the "work".
            } catch (\Exception $e) {
                echo "❌ [AgentHarness] Question Error: " . $e->getMessage() . "\n";
                $status = 'FAILED';
            }
        } else {
            // Standard Queue Job
            $status = ($feature['success'] ?? false) ? 'SUCCESS' : 'FAILED';
        }
        
        echo "📝 [AgentHarness] Feature {$feature['item_id']} completed: {$status}\n";

        // 5. Clean State Verification
        $this->checkMemoryUsage();
        $this->checkDatabaseConnections();
    }

    private function checkMemoryUsage(): void
    {
        if (!function_exists('memory_get_usage')) {
            return;
        }

        $currentUsage = memory_get_usage(true);
        $limit = $this->parseMemoryLimit((string)ini_get('memory_limit'));

        if ($limit > 0) {
            $usageRatio = $currentUsage / $limit;
            if ($usageRatio >= 0.90) {
                echo "⚠️ [AgentHarness] High memory usage: " . round($usageRatio * 100, 1) . "%\n";
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
        }
    }

    private function checkDatabaseConnections(): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->query('SELECT 1');
            if ($stmt === false) {
                echo "⚠️ [AgentHarness] Database health check returned false.\n";
            }
        } catch (\Throwable $e) {
            echo "⚠️ [AgentHarness] Database health check failed: " . $e->getMessage() . "\n";
        }
    }

    private function parseMemoryLimit(string $memoryLimit): int
    {
        $memoryLimit = trim($memoryLimit);
        if ($memoryLimit === '' || $memoryLimit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)$memoryLimit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int)$memoryLimit,
        };
    }

    private function setupSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn() => $this->running = false);
            pcntl_signal(SIGINT, fn() => $this->running = false);
        }
    }
}
