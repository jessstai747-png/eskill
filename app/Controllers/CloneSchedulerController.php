<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CloneAutoSchedulerService;
use App\Services\CloneMLRecommendationsService;

/**
 * CloneSchedulerController
 * 
 * API para agendamentos automáticos e recomendações ML
 */
class CloneSchedulerController extends BaseController
{
    private int $accountId;

    public function __construct()
    {
        parent::__construct();
        $this->accountId = $this->requireAccountId();
    }
    
    // ==========================================
    // AGENDAMENTOS
    // ==========================================

    /**
     * GET /api/clone/schedules
     * Lista todos os agendamentos
     */
    public function listSchedules(): void
    {
        $filters = [
            'is_active' => $this->request->get('active') !== null ? $this->request->getBool('active') : null,
            'source_type' => $this->request->get('source_type'),
            'limit' => $this->request->getInt('limit', 50),
        ];

        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $schedules = $scheduler->listSchedules(array_filter($filters));
            $this->sendJson(['schedules' => $schedules]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/schedules/{id}
     * Obtém um agendamento
     */
    public function getSchedule(int $id): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $schedule = $scheduler->getSchedule($id);

            if (!$schedule) {
                $this->sendJsonError('Agendamento não encontrado', 404);
                return;
            }

            $this->sendJson($schedule);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/clone/schedules
     * Cria novo agendamento
     */
    public function createSchedule(): void
    {
        $data = $this->getJsonInput();

        $required = ['name', 'source_type', 'source_value'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendJsonError("Campo obrigatório: $field", 400);
                return;
            }
        }

        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->createSchedule($data);
            $this->sendJson($result);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 400);
        }
    }

    /**
     * PUT /api/clone/schedules/{id}
     * Atualiza agendamento
     */
    public function updateSchedule(int $id): void
    {
        $data = $this->getJsonInput();

        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->updateSchedule($id, $data);
            $this->sendJson($result);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 400);
        }
    }

    /**
     * DELETE /api/clone/schedules/{id}
     * Remove agendamento
     */
    public function deleteSchedule(int $id): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->deleteSchedule($id);
            $this->sendJson(['success' => $result]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/clone/schedules/{id}/pause
     * Pausa agendamento
     */
    public function pauseSchedule(int $id): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->pauseSchedule($id);
            $this->sendJson(['success' => $result]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/clone/schedules/{id}/resume
     * Resume agendamento
     */
    public function resumeSchedule(int $id): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->resumeSchedule($id);
            $this->sendJson(['success' => $result]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 400);
        }
    }

    /**
     * POST /api/clone/schedules/{id}/execute
     * Executa agendamento manualmente
     */
    public function executeSchedule(int $id): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $result = $scheduler->executeSchedule($id);
            $this->sendJson($result);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/schedules/{id}/history
     * Histórico de execuções
     */
    public function getScheduleHistory(int $id): void
    {
        $limit = $this->request->getInt('limit', 20);

        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $history = $scheduler->getRunHistory($id, $limit);
            $this->sendJson(['history' => $history]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/schedules/stats
     * Estatísticas de agendamentos
     */
    public function getScheduleStats(): void
    {
        try {
            $scheduler = new CloneAutoSchedulerService($this->accountId);
            $stats = $scheduler->getStats();
            $this->sendJson($stats);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }
    
    // ==========================================
    // RECOMENDAÇÕES ML
    // ==========================================

    /**
     * GET /api/clone/recommendations/sellers
     * Recomendações de sellers
     */
    public function getSellerRecommendations(): void
    {
        $options = [
            'limit' => $this->request->getInt('limit', 10),
            'category_id' => $this->request->get('category_id'),
        ];

        try {
            $ml = new CloneMLRecommendationsService($this->accountId);
            $recommendations = $ml->getSellerRecommendations($options);
            $this->sendJson(['recommendations' => $recommendations]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/recommendations/products
     * Recomendações de produtos
     */
    public function getProductRecommendations(): void
    {
        $options = [
            'limit' => $this->request->getInt('limit', 20),
            'category_id' => $this->request->get('category_id'),
            'min_price' => $this->request->getFloat('min_price', 50),
            'max_price' => $this->request->getFloat('max_price', 5000),
        ];

        try {
            $ml = new CloneMLRecommendationsService($this->accountId);
            $recommendations = $ml->getProductRecommendations($options);
            $this->sendJson(['recommendations' => $recommendations]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/recommendations/categories
     * Recomendações de categorias
     */
    public function getCategoryRecommendations(): void
    {
        $limit = $this->request->getInt('limit', 10);

        try {
            $ml = new CloneMLRecommendationsService($this->accountId);
            $recommendations = $ml->getCategoryRecommendations($limit);
            $this->sendJson(['recommendations' => $recommendations]);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/recommendations/trends
     * Análise de tendências
     */
    public function getTrendAnalysis(): void
    {
        $categoryId = $this->request->get('category_id');

        try {
            $ml = new CloneMLRecommendationsService($this->accountId);
            $trends = $ml->getTrendAnalysis($categoryId);
            $this->sendJson($trends);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/clone/recommendations/predict/{itemId}
     * Previsão de performance para um item
     */
    public function predictPerformance(string $itemId): void
    {
        try {
            $ml = new CloneMLRecommendationsService($this->accountId);
            $prediction = $ml->predictPerformance($itemId);
            $this->sendJson($prediction);
        } catch (\Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function sendJson($data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    private function sendJsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        $this->sendJson(['error' => $message, 'code' => $code]);
    }

    private function getJsonInput(): array
    {
        return $this->request->json() ?? [];
    }
}
