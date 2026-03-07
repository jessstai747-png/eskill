<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Database;
use App\Services\AI\Core\AIOptimizationEngine;
use App\Services\AI\ML\CategoryLearningService;
use App\Services\AI\ML\KeywordClassifierService;
use App\Services\AI\ML\TrendPredictorService;
use PDO;

/**
 * 🤖 AI Optimization Worker
 * 
 * Background worker for AI-powered batch optimizations:
 * - Process optimization jobs in queue
 * - Run SEO strategies analysis
 * - Update category learning
 * - Train on successful optimizations
 */
class AIOptimizationWorker
{
    private PDO $db;
    private int $batchSize;
    private int $sleepInterval;
    private bool $running = true;

    // Job types
    public const JOB_SEO_ANALYSIS = 'seo_analysis';
    public const JOB_TITLE_OPTIMIZE = 'title_optimize';
    public const JOB_DESCRIPTION_OPTIMIZE = 'description_optimize';
    public const JOB_CATEGORY_LEARN = 'category_learn';
    public const JOB_TREND_UPDATE = 'trend_update';
    public const JOB_KEYWORD_CLASSIFY = 'keyword_classify';
    public const JOB_FULL_OPTIMIZE = 'full_optimize';

    // Job statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function __construct(int $batchSize = 10, int $sleepInterval = 5)
    {
        $this->db = Database::getInstance();
        $this->batchSize = $batchSize;
        $this->sleepInterval = $sleepInterval;
    }

    /**
     * 🚀 Start the worker
     */
    public function run(): void
    {
        $this->log("🤖 AI Optimization Worker started");
        $this->log("   Batch size: {$this->batchSize}");
        $this->log("   Sleep interval: {$this->sleepInterval}s");

        // Handle graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }

        while ($this->running) {
            $processed = $this->processBatch();

            if ($processed === 0) {
                $this->log("💤 No jobs found, sleeping for {$this->sleepInterval}s...");
                sleep($this->sleepInterval);
            }

            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }

        $this->log("🛑 Worker stopped gracefully");
    }

    /**
     * 📦 Process a batch of jobs
     */
    public function processBatch(): int
    {
        $jobs = $this->getNextJobs($this->batchSize);

        if (empty($jobs)) {
            return 0;
        }

        $processed = 0;

        foreach ($jobs as $job) {
            try {
                $this->markJobProcessing($job['id']);
                $this->processJob($job);
                $this->markJobCompleted($job['id']);
                $processed++;

                $this->log("✅ Job #{$job['id']} ({$job['job_type']}) completed");
            } catch (\Throwable $e) {
                $this->markJobFailed($job['id'], $e->getMessage());
                $this->log("❌ Job #{$job['id']} failed: " . $e->getMessage());
            }
        }

        return $processed;
    }

    /**
     * 🔄 Process a single job
     */
    private function processJob(array $job): void
    {
        $payload = json_decode($job['payload'] ?? '{}', true) ?: [];
        $accountId = (int) ($job['account_id'] ?? 1);

        switch ($job['job_type']) {
            case self::JOB_SEO_ANALYSIS:
                $this->runSeoAnalysis($accountId, $payload);
                break;

            case self::JOB_TITLE_OPTIMIZE:
                $this->runTitleOptimization($accountId, $payload);
                break;

            case self::JOB_DESCRIPTION_OPTIMIZE:
                $this->runDescriptionOptimization($accountId, $payload);
                break;

            case self::JOB_CATEGORY_LEARN:
                $this->runCategoryLearning($accountId, $payload);
                break;

            case self::JOB_TREND_UPDATE:
                $this->runTrendUpdate($accountId, $payload);
                break;

            case self::JOB_KEYWORD_CLASSIFY:
                $this->runKeywordClassification($accountId, $payload);
                break;

            case self::JOB_FULL_OPTIMIZE:
                $this->runFullOptimization($accountId, $payload);
                break;

            default:
                throw new \RuntimeException("Unknown job type: {$job['job_type']}");
        }
    }

    /**
     * 🔍 Run SEO Analysis
     */
    private function runSeoAnalysis(int $accountId, array $payload): void
    {
        $itemId = $payload['item_id'] ?? null;
        if (!$itemId) {
            throw new \InvalidArgumentException('item_id is required');
        }

        $engine = new AIOptimizationEngine(null, $accountId);
        $result = $engine->getSuggestions($itemId);

        // Store result
        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 📝 Run Title Optimization
     */
    private function runTitleOptimization(int $accountId, array $payload): void
    {
        $itemId = $payload['item_id'] ?? null;
        if (!$itemId) {
            throw new \InvalidArgumentException('item_id is required');
        }

        $engine = new AIOptimizationEngine(null, $accountId);
        $result = $engine->optimizeTitle($itemId);

        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 📄 Run Description Optimization
     */
    private function runDescriptionOptimization(int $accountId, array $payload): void
    {
        $itemId = $payload['item_id'] ?? null;
        if (!$itemId) {
            throw new \InvalidArgumentException('item_id is required');
        }

        $engine = new AIOptimizationEngine(null, $accountId);
        $result = $engine->optimizeListing($itemId, [
            'optimize_title' => false,
            'optimize_description' => true,
            'optimize_attributes' => false,
        ]);

        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 📚 Run Category Learning
     */
    private function runCategoryLearning(int $accountId, array $payload): void
    {
        $categoryId = $payload['category_id'] ?? null;
        if (!$categoryId) {
            throw new \InvalidArgumentException('category_id is required');
        }

        $service = new CategoryLearningService($accountId);
        $sampleSize = (int) ($payload['sample_size'] ?? 50);
        $result = $service->learnCategory($categoryId, $sampleSize);

        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 📈 Run Trend Update
     */
    private function runTrendUpdate(int $accountId, array $payload): void
    {
        $keywords = $payload['keywords'] ?? [];
        $categoryId = $payload['category_id'] ?? null;

        if (empty($keywords) && !$categoryId) {
            throw new \InvalidArgumentException('keywords or category_id is required');
        }

        $service = new TrendPredictorService($accountId);
        $results = [];

        if (!empty($keywords)) {
            foreach ($keywords as $keyword) {
                $results[$keyword] = $service->predictTrend($keyword, $categoryId);
            }
        } elseif ($categoryId) {
            $results['rising'] = $service->findRisingKeywords($categoryId);
            $results['report'] = $service->getCategoryTrendReport($categoryId);
        }

        $this->storeJobResult($payload['job_id'] ?? 0, $results);
    }

    /**
     * 🏷️ Run Keyword Classification
     */
    private function runKeywordClassification(int $accountId, array $payload): void
    {
        $keywords = $payload['keywords'] ?? [];
        if (empty($keywords)) {
            throw new \InvalidArgumentException('keywords is required');
        }

        $service = new KeywordClassifierService($accountId);
        $categoryContext = $payload['category_context'] ?? null;
        $result = $service->classifyAndGroup($keywords, $categoryContext);

        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 🚀 Run Full Optimization
     */
    private function runFullOptimization(int $accountId, array $payload): void
    {
        $itemId = $payload['item_id'] ?? null;
        if (!$itemId) {
            throw new \InvalidArgumentException('item_id is required');
        }

        $engine = new AIOptimizationEngine(null, $accountId);
        $result = $engine->optimizeListing($itemId);

        // Record optimization history
        $this->recordOptimizationHistory($accountId, $itemId, $result);

        $this->storeJobResult($payload['job_id'] ?? 0, $result);
    }

    /**
     * 📊 Record optimization history for training
     */
    private function recordOptimizationHistory(int $accountId, string $itemId, array $result): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_optimization_history (
                    account_id, item_id, optimization_type, before_score, after_score,
                    changes_json, created_at
                ) VALUES (
                    :account_id, :item_id, :optimization_type, :before_score, :after_score,
                    :changes_json, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $accountId,
                'item_id' => $itemId,
                'optimization_type' => 'full',
                'before_score' => $result['before_score'] ?? 0,
                'after_score' => $result['after_score'] ?? 0,
                'changes_json' => json_encode($result['changes'] ?? []),
            ]);
        } catch (\Exception $e) {
            // Table might not exist yet, ignore
        }
    }

    /**
     * 📥 Get next jobs from queue
     */
    private function getNextJobs(int $limit): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT id, job_type, account_id, payload, priority, created_at
                FROM ai_optimization_jobs
                WHERE status = :status
                AND (scheduled_at IS NULL OR scheduled_at <= NOW())
                ORDER BY priority DESC, created_at ASC
                LIMIT {$limitSql}
            ");

            $stmt->bindValue(':status', self::STATUS_PENDING);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Table might not exist
            return [];
        }
    }

    /**
     * Mark job as processing
     */
    private function markJobProcessing(int $jobId): void
    {
        $stmt = $this->db->prepare("
            UPDATE ai_optimization_jobs
            SET status = :status, started_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['status' => self::STATUS_PROCESSING, 'id' => $jobId]);
    }

    /**
     * Mark job as completed
     */
    private function markJobCompleted(int $jobId): void
    {
        $stmt = $this->db->prepare("
            UPDATE ai_optimization_jobs
            SET status = :status, completed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['status' => self::STATUS_COMPLETED, 'id' => $jobId]);
    }

    /**
     * Mark job as failed
     */
    private function markJobFailed(int $jobId, string $error): void
    {
        $stmt = $this->db->prepare("
            UPDATE ai_optimization_jobs
            SET status = :status, error = :error, completed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => self::STATUS_FAILED,
            'error' => substr($error, 0, 1000),
            'id' => $jobId,
        ]);
    }

    /**
     * Store job result
     */
    private function storeJobResult(int $jobId, array $result): void
    {
        if ($jobId <= 0) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE ai_optimization_jobs
                SET result = :result
                WHERE id = :id
            ");
            $stmt->execute([
                'result' => json_encode($result),
                'id' => $jobId,
            ]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * Handle shutdown signal
     */
    public function handleShutdown(): void
    {
        $this->log("⚠️ Shutdown signal received, finishing current batch...");
        $this->running = false;
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        // Use structured logging only
        logger()->info($message, [
            'worker' => 'AIOptimizationWorker',
            'batch_size' => $this->batchSize,
        ]);

        // Output to console only in CLI mode
        if (PHP_SAPI === 'cli') {
            $timestamp = date('Y-m-d H:i:s');
            echo "[{$timestamp}] {$message}\n";
        }
    }

    /**
     * 📤 Queue a job (static helper)
     */
    public static function queue(
        string $jobType,
        int $accountId,
        array $payload = [],
        int $priority = 0,
        ?string $scheduledAt = null
    ): int {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO ai_optimization_jobs (
                job_type, account_id, payload, priority, status,
                scheduled_at, created_at
            ) VALUES (
                :job_type, :account_id, :payload, :priority, :status,
                :scheduled_at, NOW()
            )
        ");

        $stmt->execute([
            'job_type' => $jobType,
            'account_id' => $accountId,
            'payload' => json_encode($payload),
            'priority' => $priority,
            'status' => self::STATUS_PENDING,
            'scheduled_at' => $scheduledAt,
        ]);

        return (int) $db->lastInsertId();
    }

    /**
     * 📊 Get queue stats
     */
    public static function getQueueStats(): array
    {
        $db = Database::getInstance();

        try {
            $stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, COALESCE(completed_at, NOW()))) as avg_duration
                FROM ai_optimization_jobs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY status
            ");

            $stats = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['status']] = [
                    'count' => (int) $row['count'],
                    'avg_duration' => round((float) $row['avg_duration'], 2),
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            return [];
        }
    }
}
