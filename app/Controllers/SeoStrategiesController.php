<?php

namespace App\Controllers;

use App\Database;
use App\Services\SEO\SEOStrategiesEngine;
use App\Services\SEO\SEOMonitoringService;

/**
 * @deprecated Functionality consolidated in SEOKillerController (SEOStrategiesEngine service).
 * API endpoints remain functional for backward compatibility.
 */
class SeoStrategiesController extends BaseController
{
    private \PDO $db;
    private SEOStrategiesEngine $engine;
    private SEOMonitoringService $monitoring;
    
    public function __construct()
    {
        $this->db = (new Database())->getConnection();
        $this->engine = new SEOStrategiesEngine();
        $this->monitoring = new SEOMonitoringService();
    }
    
    /**
     * Helper para obter input JSON
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Helper para respostas JSON
     */
    protected function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    // POST /api/seo/strategies/optimize/full/{itemId}
    public function optimizeFull(string $itemId): void
    {
        try {
            $result = $this->engine->optimizeFull($itemId);
            $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // POST /api/seo/strategies/optimize/partial/{itemId}
    public function optimizePartial(string $itemId): void
    {
        try {
            $input = $this->getJsonInput();
            $strategies = $input['strategies'] ?? [];
            $result = $this->engine->optimizePartial($itemId, $strategies);
            $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // GET /api/seo/strategies/preview/{itemId}
    public function preview(string $itemId): void
    {
        try {
            $result = $this->engine->previewOptimization($itemId);
            $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // POST /api/seo/strategies/apply/{itemId}
    public function apply(string $itemId): void
    {
        try {
            $input = $this->getJsonInput();
            $optimizations = $input['optimizations'] ?? [];

            $accountId = $this->resolveAccountIdOrFail($itemId);

            $before = $this->collectStateSnapshot($itemId);
            $applyStatus = $this->engine->applyOptimization($itemId, $optimizations, $accountId);
            $after = $this->collectStateSnapshot($itemId, $before['score']);

            $historyRecord = $this->recordOptimization(
                $this->buildHistoryPayload($itemId, $accountId, $input, $optimizations, $applyStatus, $before, $after)
            );

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'apply_status' => $applyStatus,
                    'score_before' => $before['score'],
                    'score_after' => $after['score'],
                    'views_before' => $before['metrics']['views'],
                    'views_after' => $after['metrics']['views'],
                    'sales_before' => $before['metrics']['sales'],
                    'sales_after' => $after['metrics']['sales'],
                    'history_record' => $historyRecord,
                ],
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // GET /api/seo/strategies/score/{itemId}
    public function getScore(string $itemId): void
    {
        try {
            $analysis = $this->engine->optimizeFull($itemId); // Recalculate or fetch from cache
            $score = $analysis['overall_score'];
            $this->jsonResponse(['success' => true, 'data' => ['score' => $score]]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // GET /api/seo/strategies/history/{itemId}
    public function history(string $itemId): void
    {
        try {
            $rows = $this->fetchOptimizationHistory($itemId);
            $history = $this->formatOptimizationHistory($rows);
            $this->jsonResponse(['success' => true, 'data' => $history]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // POST /api/seo/monitoring/schedule/{itemId}
    public function scheduleMonitoring(string $itemId): void
    {
        try {
            $input = $this->getJsonInput();
            $interval = isset($input['interval_days']) ? (int)$input['interval_days'] : 7;
            $result = $this->monitoring->scheduleCheck($itemId, $interval);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Monitoring scheduled',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // GET /api/seo/monitoring/metrics/{itemId}
    public function getMetrics(string $itemId): void
    {
        try {
            $metrics = $this->monitoring->compareWithPrevious($itemId);
            $this->jsonResponse(['success' => true, 'data' => $metrics]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function fetchOptimizationHistory(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                optimization_type,
                score_before,
                score_after,
                views_before,
                views_after,
                sales_before,
                sales_after,
                status,
                applied_at,
                created_at
            FROM seo_optimizations
            WHERE item_id = :item_id
            ORDER BY COALESCE(applied_at, created_at) DESC, id DESC
            LIMIT 50
        ");

        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function formatOptimizationHistory(array $rows): array
    {
        return array_map(fn ($row) => $this->formatHistoryRow($row), $rows);
    }

    private function formatHistoryRow(array $row): array
    {
        $values = $this->applyDefaults($row, [
            'score_before' => 0,
            'score_after' => 0,
            'views_before' => 0,
            'views_after' => 0,
            'sales_before' => 0,
            'sales_after' => 0,
            'applied_at' => $row['created_at'] ?? null,
        ]);

        $date = $values['applied_at'] ?? $values['created_at'];
        $scoreBefore = (float)$values['score_before'];
        $scoreAfter = (float)$values['score_after'];
        $viewsBefore = (int)$values['views_before'];
        $viewsAfter = (int)$values['views_after'];
        $salesBefore = (int)$values['sales_before'];
        $salesAfter = (int)$values['sales_after'];

        return [
            'id' => (int)$row['id'],
            'date' => $date,
            'optimization_type' => $row['optimization_type'],
            'status' => $row['status'],
            'score_before' => $scoreBefore,
            'score_after' => $scoreAfter,
            'score_improvement' => $scoreAfter - $scoreBefore,
            'views_before' => $viewsBefore,
            'views_after' => $viewsAfter,
            'views_increase' => $viewsAfter - $viewsBefore,
            'sales_before' => $salesBefore,
            'sales_after' => $salesAfter,
            'sales_increase' => $salesAfter - $salesBefore,
        ];
    }

    private function inferOptimizationType(array $optimizations): string
    {
        $nonEmptyKeys = array_keys(array_filter($optimizations, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null && $value !== '';
        }));

        if (count($nonEmptyKeys) === 1) {
            $map = [
                'title' => 'title',
                'description' => 'description',
                'attributes' => 'attributes',
                'price' => 'price',
                'images' => 'images',
            ];
            return $map[$nonEmptyKeys[0]] ?? 'full';
        }

        return 'full';
    }

    private function determineOptimizationStatus(array $applyStatus): string
    {
        if (empty($applyStatus)) {
            return 'pending';
        }
        $values = array_values($applyStatus);
        if (in_array('failed', $values, true)) {
            return 'failed';
        }
        if (in_array('applied', $values, true)) {
            return 'applied';
        }
        if (in_array('queued', $values, true)) {
            return 'pending';
        }
        return 'pending';
    }

    private function recordOptimization(array $data): array
    {
        $row = $this->insertOptimizationRow($data);
        return $this->formatHistoryRow($row);
    }

    private function getItemAccountId(string $itemId): ?int
    {
        $stmt = $this->db->prepare("SELECT account_id FROM items WHERE ml_item_id = :id LIMIT 1");
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (int)$row['account_id'] : null;
    }

    private function collectStateSnapshot(string $itemId, ?float $fallbackScore = null): array
    {
        $analysis = $this->engine->optimizeFull($itemId);
        $score = $this->extractScore($analysis, $fallbackScore);
        $metrics = $this->normalizeMetrics($this->monitoring->collectMetrics($itemId));

        return [
            'analysis' => $analysis,
            'score' => $score,
            'metrics' => $metrics,
        ];
    }

    private function buildHistoryPayload(
        string $itemId,
        int $accountId,
        array $input,
        array $optimizations,
        array $applyStatus,
        array $before,
        array $after
    ): array {
        return [
            'account_id' => $accountId,
            'item_id' => $itemId,
            'optimization_type' => $input['optimization_type'] ?? $this->inferOptimizationType($optimizations),
            'score_before' => $before['score'],
            'score_after' => $after['score'],
            'views_before' => $before['metrics']['views'],
            'views_after' => $after['metrics']['views'],
            'sales_before' => $before['metrics']['sales'],
            'sales_after' => $after['metrics']['sales'],
            'changes_applied' => $optimizations,
            'ai_suggestions' => $input['ai_suggestions'] ?? null,
            'status' => $this->determineOptimizationStatus($applyStatus),
        ];
    }

    private function resolveAccountIdOrFail(string $itemId): int
    {
        $accountId = $this->getItemAccountId($itemId);
        if (!$accountId) {
            throw new \Exception('Conta não encontrada para o item informado.');
        }
        return $accountId;
    }

    private function insertOptimizationRow(array $data): array
    {
        $stmt = $this->db->prepare($this->optimizationInsertSql());
        $stmt->execute($this->buildOptimizationInsertPayload($data));

        return $this->buildInsertedRow($this->db, $data);
    }

    private function optimizationInsertSql(): string
    {
        return <<<SQL
            INSERT INTO seo_optimizations (
                account_id,
                item_id,
                optimization_type,
                score_before,
                score_after,
                views_before,
                views_after,
                sales_before,
                sales_after,
                changes_applied,
                ai_suggestions,
                status,
                applied_at
            ) VALUES (
                :account_id,
                :item_id,
                :optimization_type,
                :score_before,
                :score_after,
                :views_before,
                :views_after,
                :sales_before,
                :sales_after,
                :changes_applied,
                :ai_suggestions,
                :status,
                NOW()
            )
        SQL;
    }

    private function buildOptimizationInsertPayload(array $data): array
    {
        return [
            'account_id' => $data['account_id'],
            'item_id' => $data['item_id'],
            'optimization_type' => $data['optimization_type'],
            'score_before' => $data['score_before'],
            'score_after' => $data['score_after'],
            'views_before' => $data['views_before'],
            'views_after' => $data['views_after'],
            'sales_before' => $data['sales_before'],
            'sales_after' => $data['sales_after'],
            'changes_applied' => json_encode($data['changes_applied'], JSON_UNESCAPED_UNICODE),
            'ai_suggestions' => $data['ai_suggestions'] ? json_encode($data['ai_suggestions'], JSON_UNESCAPED_UNICODE) : null,
            'status' => $data['status'],
        ];
    }

    private function buildInsertedRow(\PDO $db, array $data): array
    {
        $timestamp = date('Y-m-d H:i:s');
        return [
            'id' => (int)$db->lastInsertId(),
            'item_id' => $data['item_id'],
            'optimization_type' => $data['optimization_type'],
            'score_before' => $data['score_before'],
            'score_after' => $data['score_after'],
            'views_before' => $data['views_before'],
            'views_after' => $data['views_after'],
            'sales_before' => $data['sales_before'],
            'sales_after' => $data['sales_after'],
            'status' => $data['status'],
            'changes_applied' => json_encode($data['changes_applied'], JSON_UNESCAPED_UNICODE),
            'ai_suggestions' => $data['ai_suggestions'] ? json_encode($data['ai_suggestions'], JSON_UNESCAPED_UNICODE) : null,
            'applied_at' => $timestamp,
            'created_at' => $timestamp,
        ];
    }

    private function applyDefaults(array $row, array $defaults): array
    {
        return $defaults + $row;
    }

    private function extractScore(array $analysis, ?float $fallback): float
    {
        return (float)($analysis['overall_score'] ?? $fallback ?? 0);
    }

    private function normalizeMetrics(array $metrics): array
    {
        return [
            'views' => (int)($metrics['views'] ?? 0),
            'sales' => (int)($metrics['sales'] ?? 0),
        ];
    }
}
