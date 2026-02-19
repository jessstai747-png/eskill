<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CloneAnalyticsService;
use DateTimeImmutable;

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
        $this->accountId = $this->getActiveAccountId();
        $this->analyticsService = new CloneAnalyticsService($this->accountId);
    }

    /**
     * GET /api/clone/analytics/dashboard
     * Dashboard com métricas analíticas
     */
    public function getDashboard(): void
    {
        $this->withErrorHandling(function (): void {
            $period = $this->getPeriodParam('period', '30d');
            $metrics = $this->analyticsService->getDashboardMetrics($period);

            $this->jsonSuccess(['data' => $metrics]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/kpis
     * KPIs principais
     */
    public function getKPIs(): void
    {
        $this->withErrorHandling(function (): void {
            $period = $this->getPeriodParam('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $kpis = $this->analyticsService->getKPIs($dateFrom);

            $this->jsonSuccess(['data' => $kpis]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/trends
     * Tendências ao longo do tempo
     */
    public function getTrends(): void
    {
        $this->withErrorHandling(function (): void {
            $period = $this->getPeriodParam('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $trends = $this->analyticsService->getTrends($dateFrom);

            $this->jsonSuccess(['data' => $trends]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/performance
     * Métricas de performance
     */
    public function getPerformance(): void
    {
        $this->withErrorHandling(function (): void {
            $period = $this->getPeriodParam('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $performance = $this->analyticsService->getPerformanceMetrics($dateFrom);

            $this->jsonSuccess(['data' => $performance]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/breakdown
     * Breakdown por dimensões
     */
    public function getBreakdown(): void
    {
        $this->withErrorHandling(function (): void {
            $period = $this->getPeriodParam('period', '30d');
            $dateFrom = $this->parsePeriod($period);
            $breakdown = $this->analyticsService->getBreakdown($dateFrom);

            $this->jsonSuccess(['data' => $breakdown]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/compare
     * Comparação entre períodos
     */
    public function comparePeriods(): void
    {
        $this->withErrorHandling(function (): void {
            $period1 = $this->getPeriodParam('period1', '7d');
            $period2 = $this->getPeriodParam('period2', '30d');

            $comparison = $this->analyticsService->comparePeriods($period1, $period2);

            $this->jsonSuccess(['data' => $comparison]);
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/projection
     * Projeção baseada em tendências
     */
    public function getProjection(): void
    {
        $this->withErrorHandling(function (): void {
            $days = $this->request->getIntClamped('days', 1, 30, 7);

            $projection = $this->analyticsService->getProjection($days);

            $this->jsonSuccess(['data' => $projection]);
        }, __METHOD__);
    }

    /**
     * POST /api/clone/analytics/events
     * Registra evento de analytics
     */
    public function trackEvent(): void
    {
        $this->withErrorHandling(function (): void {
            $input = $this->request->json() ?? [];

            $eventName = trim((string) ($input['event_name'] ?? ''));
            if ($eventName === '') {
                $this->jsonError('event_name é obrigatório', 400);
            }

            $eventData = $input['event_data'] ?? [];
            if (!is_array($eventData)) {
                $this->jsonError('event_data deve ser um objeto', 400);
            }

            $this->analyticsService->trackEvent($eventName, $eventData);

            $this->jsonSuccess([], 'Evento registrado');
        }, __METHOD__);
    }

    /**
     * GET /api/clone/analytics/events
     * Lista eventos de analytics
     */
    public function getEvents(): void
    {
        $this->withErrorHandling(function (): void {
            $eventName = $this->request->get('event_name');
            $limit = $this->request->getIntClamped('limit', 1, 500, 100);

            $events = $this->analyticsService->getEvents($eventName, $limit);

            $this->jsonSuccess(['data' => $events]);
        }, __METHOD__);
    }

    /**
     * Converte período em data
     */
    private function parsePeriod(string $period): string
    {
        $now = new DateTimeImmutable('now');

        $from = match ($period) {
            '24h' => $now->modify('-24 hours'),
            '7d' => $now->modify('-7 days'),
            '30d' => $now->modify('-30 days'),
            '90d' => $now->modify('-90 days'),
            '1y' => $now->modify('-1 year'),
            default => $now->modify('-30 days'),
        };

        return $from->format('Y-m-d H:i:s');
    }

    private function getPeriodParam(string $key, string $default): string
    {
        $allowed = ['24h', '7d', '30d', '90d', '1y'];
        $value = $this->request->getEnum($key, $allowed, $default);
        return $value !== '' ? $value : $default;
    }
}
