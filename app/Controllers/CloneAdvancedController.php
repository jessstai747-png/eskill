<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneSeoOptimizationService;
use App\Services\CloneDataExportService;
use App\Services\CloneHealthMonitorService;
use App\Services\CloneBatchOperationsService;
use App\Services\CloneItemManagerService;
use App\Services\MercadoLivreClient;

/**
 * CloneAdvancedController
 *
 * API para funcionalidades avançadas do módulo Clone:
 * - SEO Optimization
 * - Data Export
 * - Health Monitoring
 * - Batch Operations
 * - Analytics
 */
class CloneAdvancedController
{
    private int $accountId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->accountId = (int) ($_SESSION['account_id'] ?? 0);

        if ($this->accountId === 0) {
            $this->jsonError('Não autorizado', 401);
            exit;
        }
    }

    // ==========================================
    // SEO OPTIMIZATION
    // ==========================================

    /**
     * POST /api/clone/seo/analyze
     * Analisa SEO de um item
     */
    public function analyzeSeo(): void
    {
        $data = $this->getJsonInput();
        $itemId = $data['item_id'] ?? null;
        $level = $data['level'] ?? 'standard';

        if (!$itemId) {
            $this->jsonError('item_id é obrigatório', 400);
            return;
        }

        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $result = $seo->analyzeForClone($itemId, $level);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/seo/analyze/batch
     * Analisa SEO em lote
     */
    public function analyzeBatchSeo(): void
    {
        $data = $this->getJsonInput();
        $itemIds = $data['item_ids'] ?? [];

        if (empty($itemIds)) {
            $this->jsonError('item_ids é obrigatório', 400);
            return;
        }

        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $result = $seo->analyzeBatch($itemIds);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/seo/optimize/title
     * Otimiza um título
     */
    public function optimizeTitle(): void
    {
        $data = $this->getJsonInput();
        $title = $data['title'] ?? '';
        $level = $data['level'] ?? 'standard';

        if (empty($title)) {
            $this->jsonError('title é obrigatório', 400);
            return;
        }

        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $optimized = $seo->optimizeTitle($title, $level);

            $this->json([
                'original' => $title,
                'optimized' => $optimized,
                'original_length' => mb_strlen($title),
                'optimized_length' => mb_strlen($optimized)
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/seo/optimize/description
     * Otimiza uma descrição
     */
    public function optimizeDescription(): void
    {
        $data = $this->getJsonInput();
        $description = $data['description'] ?? '';
        $level = $data['level'] ?? 'standard';

        if (empty($description)) {
            $this->jsonError('description é obrigatório', 400);
            return;
        }

        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $optimized = $seo->optimizeDescription($description, $level);

            $this->json([
                'original' => $description,
                'optimized' => $optimized,
                'original_length' => mb_strlen($description),
                'optimized_length' => mb_strlen($optimized)
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/seo/settings
     * Obtém configurações de SEO
     */
    public function getSeoSettings(): void
    {
        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $settings = $seo->getSettings();
            $this->json($settings);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/clone/seo/settings
     * Atualiza configurações de SEO
     */
    public function updateSeoSettings(): void
    {
        $data = $this->getJsonInput();

        try {
            $seo = new CloneSeoOptimizationService($this->accountId);
            $result = $seo->updateSettings($data);
            $this->json(['success' => $result]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ==========================================
    // DATA EXPORT
    // ==========================================

    /**
     * POST /api/clone/export/items/csv
     * Exporta itens para CSV
     */
    public function exportItemsCsv(): void
    {
        $data = $this->getJsonInput();
        $filters = $data['filters'] ?? [];

        try {
            $export = new CloneDataExportService($this->accountId);
            $result = $export->exportItemsToCsv($filters);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/export/items/json
     * Exporta itens para JSON
     */
    public function exportItemsJson(): void
    {
        $data = $this->getJsonInput();
        $filters = $data['filters'] ?? [];

        try {
            $export = new CloneDataExportService($this->accountId);
            $result = $export->exportItemsToJson($filters);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/export/jobs
     * Exporta jobs para CSV
     */
    public function exportJobs(): void
    {
        $data = $this->getJsonInput();
        $filters = $data['filters'] ?? [];

        try {
            $export = new CloneDataExportService($this->accountId);
            $result = $export->exportJobsToCsv($filters);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/export/metrics
     * Exporta métricas
     */
    public function exportMetrics(): void
    {
        $data = $this->getJsonInput();
        $options = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        if (isset($data['period'])) {
            $options['period'] = $data['period'];
        }

        try {
            $export = new CloneDataExportService($this->accountId);
            $result = $export->exportMetrics($options);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/export/report
     * Gera relatório completo HTML
     */
    public function exportFullReport(): void
    {
        $data = $this->getJsonInput();
        $options = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        if (isset($data['period'])) {
            $options['period'] = $data['period'];
        }

        try {
            $export = new CloneDataExportService($this->accountId);
            $result = $export->exportFullReport($options);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/export/list
     * Lista exports disponíveis
     */
    public function listExports(): void
    {
        try {
            $export = new CloneDataExportService($this->accountId);
            $exports = $export->listExports();
            $this->json([
                'exports' => $exports,
                'count' => count($exports)
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/export/download/{filename}
     * Download de arquivo exportado
     */
    public function downloadExport(string $filename): void
    {
        $filename = basename($filename);
        $export = new CloneDataExportService($this->accountId);
        $path = $export->getExportPath($filename);

        if ($path === null || !file_exists($path)) {
            $this->jsonError('Arquivo não encontrado', 404);
            return;
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $contentTypes = [
            'csv' => 'text/csv',
            'json' => 'application/json',
            'html' => 'text/html'
        ];

        $contentType = $contentTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));

        readfile($path);
        exit;
    }

    // ==========================================
    // HEALTH MONITORING
    // ==========================================

    /**
     * GET /api/clone/health
     * Status de saúde do sistema
     */
    public function getHealth(): void
    {
        try {
            $mlClient = $this->createMlClient();
            $health = new CloneHealthMonitorService($this->accountId, null, $mlClient);
            $status = $health->getSystemHealth();
            $this->json($status);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/health/diagnostics
     * Diagnósticos detalhados
     */
    public function getDiagnostics(): void
    {
        try {
            $mlClient = $this->createMlClient();
            $health = new CloneHealthMonitorService($this->accountId, null, $mlClient);
            $diagnostics = $health->runDiagnostics();
            $this->json($diagnostics);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ==========================================
    // BATCH OPERATIONS
    // ==========================================

    /**
     * POST /api/clone/batch/repricing
     * Repricing em lote
     */
    public function batchRepricing(): void
    {
        $rules = $this->getJsonInput();

        if (empty($rules)) {
            $this->jsonError('Regras de repricing são obrigatórias', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchRepricing($rules);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/stock
     * Atualização de estoque em lote
     */
    public function batchStockUpdate(): void
    {
        $data = $this->getJsonInput();
        $updates = $data['updates'] ?? [];

        if (empty($updates)) {
            $this->jsonError('updates é obrigatório', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchStockUpdate($updates);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/status
     * Alteração de status em lote
     */
    public function batchStatusChange(): void
    {
        $data = $this->getJsonInput();
        $itemIds = $data['item_ids'] ?? [];
        $status = $data['status'] ?? null;

        if (empty($itemIds) || !$status) {
            $this->jsonError('item_ids e status são obrigatórios', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchStatusChange($itemIds, $status);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/titles
     * Atualização de títulos em lote
     */
    public function batchTitleUpdate(): void
    {
        $data = $this->getJsonInput();
        $updates = $data['updates'] ?? [];

        if (empty($updates)) {
            $this->jsonError('updates é obrigatório', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchTitleUpdate($updates);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/prices
     * Atualização de preços em lote
     */
    public function batchPriceUpdate(): void
    {
        $data = $this->getJsonInput();
        $updates = $data['updates'] ?? [];

        if (empty($updates)) {
            $this->jsonError('updates é obrigatório', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchPriceUpdate($updates);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/sync-metrics
     * Sincronização de métricas em lote
     */
    public function batchSyncMetrics(): void
    {
        $data = $this->getJsonInput();
        $itemIds = $data['item_ids'] ?? [];

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchSyncMetrics($itemIds);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/seo-optimize
     * Otimização SEO em lote
     */
    public function batchSeoOptimize(): void
    {
        $data = $this->getJsonInput();
        $itemIds = $data['item_ids'] ?? [];
        $level = $data['level'] ?? 'standard';

        if (empty($itemIds)) {
            $this->jsonError('item_ids é obrigatório', 400);
            return;
        }

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->batchSeoOptimization($itemIds, $level);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/batch/close-stale
     * Encerrar itens antigos sem vendas
     */
    public function closeStaleItems(): void
    {
        $data = $this->getJsonInput();
        $days = (int) ($data['days'] ?? 60);

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $result = $batch->closeStaleItems($days);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/batch/history
     * Histórico de operações em lote
     */
    public function getBatchHistory(): void
    {
        $limit = $this->request->getInt('limit', 50);

        try {
            $batch = new CloneBatchOperationsService($this->accountId);
            $history = $batch->getOperationsHistory($limit);
            $this->json(['operations' => $history]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ==========================================
    // ANALYTICS
    // ==========================================

    /**
     * GET /api/clone/analytics/summary
     * Resumo de analytics
     */
    public function getAnalyticsSummary(): void
    {
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $stats = $manager->getStats();

            $mlClient = $this->createMlClient();
            $health = new CloneHealthMonitorService($this->accountId, null, $mlClient);
            $healthStatus = $health->getSystemHealth();

            $this->json([
                'stats' => $stats,
                'health' => $healthStatus,
                'generated_at' => date('c')
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/analytics/performance
     * Performance por período
     */
    public function getPerformance(): void
    {
        $period = $this->request->get('period', '30d') ?? '30d';

        try {
            $manager = new CloneItemManagerService($this->accountId);
            $topSellers = $manager->getTopSellers(20);
            $stats = $manager->getStats();

            $this->json([
                'period' => $period,
                'top_sellers' => $topSellers,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/analytics/trends
     * Tendências
     */
    public function getTrends(): void
    {
        try {
            $db = \App\Database::getInstance();

            // Clones por dia (últimos 30 dias)
            $stmt = $db->prepare("
                SELECT DATE(created_at) as date, COUNT(*) as count
                FROM cloned_items
                WHERE target_account_id = :account_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $dailyClones = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Status distribution
            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count
                FROM cloned_items
                WHERE target_account_id = :account_id
                GROUP BY status
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $statusDist = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Jobs por status
            $stmt = $db->prepare("
                SELECT status, COUNT(*) as count
                FROM clone_jobs
                WHERE account_id = :account_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY status
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $jobsStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->json([
                'daily_clones' => $dailyClones,
                'status_distribution' => $statusDist,
                'jobs_by_status' => $jobsStatus
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function json($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        $this->json(['error' => $message, 'code' => $code]);
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Cria instância de MercadoLivreClient para diagnose (best-effort).
     * Retorna null se não for possível criar o client.
     */
    private function createMlClient(): ?MercadoLivreClient
    {
        try {
            return new MercadoLivreClient($this->accountId > 0 ? $this->accountId : null);
        } catch (\Throwable $e) {
            log_debug('CloneAdvancedController: ML client indisponível', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return null;
        }
    }
}
