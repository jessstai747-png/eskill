<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneEventTriggerService;
use App\Services\CloneTrendChartService;
use App\Services\UserService;

/**
 * CloneEventTriggerController
 * 
 * Endpoints para gerenciar triggers de eventos e gráficos de tendências
 */
class CloneEventTriggerController
{
    private UserService $userService;
    private ?int $accountId = null;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->userService = new UserService();
        
        if ($this->userService->isAuthenticated()) {
            $user = $this->userService->getCurrentUser();
            $this->accountId = $user['active_account_id'] ?? null;
        }
    }

    // =========================================================================
    // Event Triggers CRUD
    // =========================================================================

    /**
     * GET /api/clone/triggers
     * Lista todos os triggers
     */
    public function listTriggers(): void
    {
        $this->jsonResponse(function () {
            $activeOnly = ($this->request->get('active_only', '1') ?? '1') === '1';
            $service = new CloneEventTriggerService($this->accountId);
            
            return [
                'triggers' => $service->listTriggers($activeOnly),
                'stats' => $service->getTriggerStats(),
            ];
        });
    }

    /**
     * POST /api/clone/triggers
     * Cria novo trigger
     */
    public function createTrigger(): void
    {
        $this->jsonResponse(function () {
            $data = $this->getJsonInput();
            $service = new CloneEventTriggerService($this->accountId);
            
            return $service->createTrigger($data);
        });
    }

    /**
     * GET /api/clone/triggers/{id}
     * Obtém trigger específico
     */
    public function getTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $service = new CloneEventTriggerService($this->accountId);
            $trigger = $service->getTrigger($triggerId);
            
            if (!$trigger) {
                http_response_code(404);
                return ['error' => 'Trigger não encontrado'];
            }
            
            return $trigger;
        });
    }

    /**
     * PUT /api/clone/triggers/{id}
     * Atualiza trigger
     */
    public function updateTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $data = $this->getJsonInput();
            $service = new CloneEventTriggerService($this->accountId);
            
            return $service->updateTrigger($triggerId, $data);
        });
    }

    /**
     * DELETE /api/clone/triggers/{id}
     * Remove trigger
     */
    public function deleteTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $service = new CloneEventTriggerService($this->accountId);
            $success = $service->deleteTrigger($triggerId);
            
            return ['success' => $success];
        });
    }

    /**
     * POST /api/clone/triggers/{id}/activate
     * Ativa trigger
     */
    public function activateTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $service = new CloneEventTriggerService($this->accountId);
            return $service->updateTrigger($triggerId, ['is_active' => true]);
        });
    }

    /**
     * POST /api/clone/triggers/{id}/deactivate
     * Desativa trigger
     */
    public function deactivateTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $service = new CloneEventTriggerService($this->accountId);
            return $service->updateTrigger($triggerId, ['is_active' => false]);
        });
    }

    /**
     * POST /api/clone/triggers/{id}/test
     * Testa trigger (dry-run)
     */
    public function testTrigger(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $service = new CloneEventTriggerService($this->accountId);
            $trigger = $service->getTrigger($triggerId);
            
            if (!$trigger) {
                http_response_code(404);
                return ['error' => 'Trigger não encontrado'];
            }
            
            // Processar sem executar ações
            $trigger['actions'] = [['type' => 'log']];
            
            return $service->processTrigger($trigger);
        });
    }

    /**
     * GET /api/clone/triggers/{id}/history
     * Histórico de eventos do trigger
     */
    public function getTriggerHistory(string $triggerId): void
    {
        $this->jsonResponse(function () use ($triggerId) {
            $limit = $this->request->getInt('limit', 50);
            $service = new CloneEventTriggerService($this->accountId);
            
            return [
                'trigger_id' => $triggerId,
                'events' => $service->getEventHistory($triggerId, $limit),
            ];
        });
    }

    /**
     * GET /api/clone/triggers/stats
     * Estatísticas gerais de triggers
     */
    public function getTriggerStats(): void
    {
        $this->jsonResponse(function () {
            $service = new CloneEventTriggerService($this->accountId);
            return $service->getTriggerStats();
        });
    }

    // =========================================================================
    // Trend Charts
    // =========================================================================

    /**
     * GET /api/clone/charts/dashboard
     * Todos os gráficos do dashboard
     */
    public function getDashboardCharts(): void
    {
        $this->jsonResponse(function () {
            $service = new CloneTrendChartService($this->accountId);
            return $service->getDashboardCharts();
        });
    }

    /**
     * GET /api/clone/charts/clones-per-day
     * Gráfico de clonagens por dia
     */
    public function getClonesPerDayChart(): void
    {
        $this->jsonResponse(function () {
            $days = $this->request->getInt('days', 30);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getClonesPerDayChart($days);
        });
    }

    /**
     * GET /api/clone/charts/success-by-hour
     * Gráfico de taxa de sucesso por hora
     */
    public function getSuccessByHourChart(): void
    {
        $this->jsonResponse(function () {
            $service = new CloneTrendChartService($this->accountId);
            return $service->getSuccessRateByHourChart();
        });
    }

    /**
     * GET /api/clone/charts/by-category
     * Gráfico de clonagens por categoria
     */
    public function getByCategoryChart(): void
    {
        $this->jsonResponse(function () {
            $limit = $this->request->getInt('limit', 10);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getClonesPerCategoryChart($limit);
        });
    }

    /**
     * GET /api/clone/charts/seller-performance
     * Gráfico de performance por seller
     */
    public function getSellerPerformanceChart(): void
    {
        $this->jsonResponse(function () {
            $limit = $this->request->getInt('limit', 10);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getSellerPerformanceChart($limit);
        });
    }

    /**
     * GET /api/clone/charts/clone-time
     * Gráfico de tempo de clonagem
     */
    public function getCloneTimeChart(): void
    {
        $this->jsonResponse(function () {
            $days = $this->request->getInt('days', 14);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getCloneTimeChart($days);
        });
    }

    /**
     * GET /api/clone/charts/status-distribution
     * Gráfico de distribuição de status
     */
    public function getStatusDistributionChart(): void
    {
        $this->jsonResponse(function () {
            $service = new CloneTrendChartService($this->accountId);
            return $service->getStatusDistributionChart();
        });
    }

    /**
     * GET /api/clone/charts/schedule-executions
     * Gráfico de execuções de agendamentos
     */
    public function getScheduleExecutionsChart(): void
    {
        $this->jsonResponse(function () {
            $days = $this->request->getInt('days', 14);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getScheduleExecutionsChart($days);
        });
    }

    /**
     * GET /api/clone/charts/events-by-type
     * Gráfico de eventos por tipo
     */
    public function getEventsByTypeChart(): void
    {
        $this->jsonResponse(function () {
            $days = $this->request->getInt('days', 7);
            $service = new CloneTrendChartService($this->accountId);
            return $service->getEventsByTypeChart($days);
        });
    }

    /**
     * GET /api/clone/charts/quality-metrics
     * Gráfico radar de métricas de qualidade
     */
    public function getQualityMetricsChart(): void
    {
        $this->jsonResponse(function () {
            $service = new CloneTrendChartService($this->accountId);
            return $service->getQualityMetricsChart();
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function jsonResponse(callable $handler): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->accountId) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            return;
        }

        try {
            $result = $handler();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro interno: ' . $e->getMessage()]);
        }
    }

    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('JSON inválido');
        }
        
        return $data ?? [];
    }
}
