<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneROIAnalysisService;
use Exception;

/**
 * Clone ROI Analysis Controller
 * 
 * API para análise comparativa de ROI entre clones e originais
 */
class CloneROIAnalysisController
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
     * Obtém análise comparativa geral
     * GET /api/clone/roi/analysis
     */
    public function getAnalysis(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneROIAnalysisService($this->accountId);

            $filters = [
                'period' => $this->request->getInt('period', 30),
            ];

            $categoryId = $this->request->get('category_id');
            if (!empty($categoryId)) {
                $filters['category_id'] = $categoryId;
            }

            $sellerId = $this->request->get('seller_id');
            if (!empty($sellerId)) {
                $filters['seller_id'] = $sellerId;
            }

            $analysis = $service->getComparativeAnalysis($filters);

            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém comparação de um item específico
     * GET /api/clone/roi/items/{itemId}
     */
    public function getItemComparison(string $itemId): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneROIAnalysisService($this->accountId);
            $comparison = $service->getItemComparison($itemId);

            if (isset($comparison['error'])) {
                http_response_code(404);
                echo json_encode(['error' => $comparison['error']]);
                return;
            }

            echo json_encode([
                'success' => true,
                'comparison' => $comparison,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Registra métricas de um item
     * POST /api/clone/roi/items/{itemId}/metrics
     */
    public function recordMetrics(string $itemId): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $service = new CloneROIAnalysisService($this->accountId);
            $success = $service->recordMetrics($itemId, $input);

            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => 'Item não encontrado']);
                return;
            }

            echo json_encode([
                'success' => true,
                'message' => 'Métricas registradas',
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Sincroniza métricas da API ML
     * POST /api/clone/roi/sync
     */
    public function syncMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $limit = (int)($input['limit'] ?? 50);

            $service = new CloneROIAnalysisService($this->accountId);
            $result = $service->syncMetricsFromML($limit);

            echo json_encode([
                'success' => true,
                'result' => $result,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém ROI por período
     * GET /api/clone/roi/timeline
     */
    public function getTimeline(): void
    {
        header('Content-Type: application/json');

        try {
            $days = $this->request->getInt('days', 7);

            $service = new CloneROIAnalysisService($this->accountId);
            $timeline = $service->getROIByPeriod($days);

            echo json_encode([
                'success' => true,
                'days' => $days,
                'timeline' => $timeline,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
