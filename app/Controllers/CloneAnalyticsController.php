<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CloneAnalyticsService;

/**
 * CloneAnalyticsController - API para Analytics Avançado
 */
class CloneAnalyticsController extends BaseController
{
    private CloneAnalyticsService $analyticsService;
    private ?int $accountId;

    public function __construct()
    {
        parent::__construct();
        $this->accountId = $_SESSION['account_id'] ?? null;
        $this->analyticsService = new CloneAnalyticsService($this->accountId);
    }

    /**
     * GET /api/clone/analytics/dashboard
     * Dashboard com métricas analíticas
     */
    public function getDashboard(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $metrics = $this->analyticsService->getDashboardMetrics($period);

            $this->jsonResponse([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/kpis
     * KPIs principais
     */
    public function getKPIs(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $kpis = $this->analyticsService->getKPIs($dateFrom);

            $this->jsonResponse([
                'success' => true,
                'data' => $kpis,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/trends
     * Tendências ao longo do tempo
     */
    public function getTrends(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $trends = $this->analyticsService->getTrends($dateFrom);

            $this->jsonResponse([
                'success' => true,
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/performance
     * Métricas de performance
     */
    public function getPerformance(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $performance = $this->analyticsService->getPerformanceMetrics($dateFrom);

            $this->jsonResponse([
                'success' => true,
                'data' => $performance,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/breakdown
     * Breakdown por dimensões
     */
    public function getBreakdown(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $breakdown = $this->analyticsService->getBreakdown($dateFrom);

            $this->jsonResponse([
                'success' => true,
                'data' => $breakdown,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/compare
     * Comparação entre períodos
     */
    public function comparePeriods(): void
    {
        try {
            $period1 = $this->request->get('period1', '7d');
            $period2 = $this->request->get('period2', '30d');

            $comparison = $this->analyticsService->comparePeriods($period1, $period2);

            $this->jsonResponse([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/projection
     * Projeção baseada em tendências
     */
    public function getProjection(): void
    {
        try {
            $days = $this->request->getIntClamped('days', 1, 30, 7);

            $projection = $this->analyticsService->getProjection($days);

            $this->jsonResponse([
                'success' => true,
                'data' => $projection,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/clone/analytics/events
     * Registra evento de analytics
     */
    public function trackEvent(): void
    {
        try {
            $input = $this->request->json();

            if (empty($input['event_name'])) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'event_name é obrigatório',
                ], 400);
                return;
            }

            $this->analyticsService->trackEvent(
                $input['event_name'],
                $input['event_data'] ?? []
            );

            $this->jsonResponse([
                'success' => true,
                'message' => 'Evento registrado',
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/analytics/events
     * Lista eventos de analytics
     */
    public function getEvents(): void
    {
        try {
            $eventName = $this->request->get('event_name');
            $limit = $this->request->getInt('limit', 100);

            $events = $this->analyticsService->getEvents($eventName, $limit);

            $this->jsonResponse([
                'success' => true,
                'data' => $events,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Converte período em data
     */
    private function parsePeriod(string $period): string
    {
        $map = [
            '24h' => '1 DAY',
            '7d' => '7 DAY',
            '30d' => '30 DAY',
            '90d' => '90 DAY',
            '1y' => '1 YEAR',
        ];

        $interval = $map[$period] ?? '30 DAY';
        
        $db = \App\Database::getInstance();
        $stmt = $db->query("SELECT DATE_SUB(NOW(), INTERVAL {$interval}) as date_from");
        return $stmt->fetchColumn();
    }

    /**
     * Resposta JSON padronizada
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
