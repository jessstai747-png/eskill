<?php

namespace App\Controllers;

use App\Services\AnalyticsService;
use App\Services\UserService;

class AnalyticsController extends BaseController
{
    private $service;
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->service = new AnalyticsService();
        $this->userService = $userService;
    }

    /**
     * Main Analytics Dashboard
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Business Intelligence';
        $activePage = 'analytics';

        ob_start();
        require __DIR__ . '/../Views/dashboard/analytics/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Dashboard method (alias to index)
     */
    public function dashboard(): void
    {
        $this->index();
    }

    /**
     * API: Get revenue trend data
     */
    public function getRevenueTrend(): void
    {
        header('Content-Type: application/json');

        $start = $this->request->get('start', date('Y-m-01'));
        $end = $this->request->get('end', date('Y-m-d'));

        // Validate date format
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($datePattern, $start) || !preg_match($datePattern, $end)) {
            http_response_code(400);
            echo json_encode(['error' => 'Formato de data inválido. Use YYYY-MM-DD.']);
            return;
        }

        $allowedGranularity = ['day', 'week', 'month'];
        $granularity = $this->request->get('granularity', 'day');
        if (!in_array($granularity, $allowedGranularity, true)) {
            $granularity = 'day';
        }

        $data = $this->service->getRevenueTrend($start, $end, $granularity);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get customer LTV segments
     */
    public function getCustomerLTV(): void
    {
        header('Content-Type: application/json');
        $data = $this->service->getCustomerLTV();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get inventory turnover
     */
    public function getInventoryTurnover(): void
    {
        header('Content-Type: application/json');
        $data = $this->service->getInventoryTurnover();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get profit margins
     */
    public function getProfitMargins(): void
    {
        header('Content-Type: application/json');
        $data = $this->service->getProfitMargins();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get dashboard summary
     */
    public function getSummary(): void
    {
        header('Content-Type: application/json');
        $data = $this->service->getDashboardSummary();
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get forecast
     */
    public function getForecast(): void
    {
        header('Content-Type: application/json');
        $days = $this->request->getInt('days', 7);
        $data = $this->service->getForecast($days);
        echo json_encode(['success' => true, 'data' => $data]);
    }

    /**
     * API: Get API usage statistics (rate limits, request counts)
     */
    public function apiStats(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $hours = $this->request->getInt('hours', 24);
            $cutoff = date('Y-m-d H:i:s', time() - ($hours * 3600));

            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    MIN(created_at) as earliest,
                    MAX(created_at) as latest
                FROM rate_limits
                WHERE created_at > :cutoff
            ");
            $stmt->execute([':cutoff' => $cutoff]);
            $stats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmtTop = $db->prepare("
                SELECT ip_address, COUNT(*) as request_count
                FROM rate_limits
                WHERE created_at > :cutoff
                GROUP BY ip_address
                ORDER BY request_count DESC
                LIMIT 10
            ");
            $stmtTop->execute([':cutoff' => $cutoff]);
            $topIps = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'period_hours' => $hours,
                    'total_requests' => (int) ($stats['total_requests'] ?? 0),
                    'unique_ips' => (int) ($stats['unique_ips'] ?? 0),
                    'earliest' => $stats['earliest'] ?? null,
                    'latest' => $stats['latest'] ?? null,
                    'top_ips' => $topIps,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch API stats: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Get requests chart data (hourly distribution)
     */
    public function requestsChart(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $days = $this->request->getInt('days', 7);
            $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

            $stmt = $db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour_bucket,
                    COUNT(*) as request_count
                FROM rate_limits
                WHERE created_at > :cutoff
                GROUP BY hour_bucket
                ORDER BY hour_bucket ASC
            ");
            $stmt->execute([':cutoff' => $cutoff]);
            $chartData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'chart' => $chartData,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch requests chart: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Get sales metrics (revenue, orders, ticket médio)
     */
    public function salesMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $days = $this->request->getInt('days', 30);
            $cutoff = date('Y-m-d', strtotime("-{$days} days"));

            $stmt = $db->prepare("
                SELECT 
                    DATE(date_created) as day,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_ticket,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                FROM ml_orders
                WHERE DATE(date_created) >= :cutoff
                GROUP BY day
                ORDER BY day ASC
            ");
            $stmt->execute([':cutoff' => $cutoff]);
            $daily = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmtTotals = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_ticket,
                    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_revenue
                FROM ml_orders
                WHERE DATE(date_created) >= :cutoff
            ");
            $stmtTotals->execute([':cutoff' => $cutoff]);
            $totals = $stmtTotals->fetch(\PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'totals' => [
                        'total_orders' => (int) ($totals['total_orders'] ?? 0),
                        'total_revenue' => round((float) ($totals['total_revenue'] ?? 0), 2),
                        'avg_ticket' => round((float) ($totals['avg_ticket'] ?? 0), 2),
                        'paid_revenue' => round((float) ($totals['paid_revenue'] ?? 0), 2),
                    ],
                    'daily' => $daily,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch sales metrics: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Get listings metrics (active, paused, closed items)
     */
    public function listingsMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();

            $stmt = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(price) as total_value,
                    AVG(price) as avg_price,
                    SUM(available_quantity) as total_stock
                FROM items
                GROUP BY status
                ORDER BY count DESC
            ");
            $byStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmtCat = $db->query("
                SELECT 
                    category_id,
                    COUNT(*) as count,
                    AVG(price) as avg_price
                FROM items
                WHERE status = 'active'
                GROUP BY category_id
                ORDER BY count DESC
                LIMIT 15
            ");
            $byCategory = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'by_status' => $byStatus,
                    'by_category' => $byCategory,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch listings metrics: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Get questions metrics (answered, unanswered, response time)
     */
    public function questionsMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $days = $this->request->getInt('days', 30);
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

            $stmt = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM ml_questions
                WHERE created_at >= :cutoff
                GROUP BY status
            ");
            $stmt->execute([':cutoff' => $cutoff]);
            $byStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmtDaily = $db->prepare("
                SELECT 
                    DATE(created_at) as day,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'ANSWERED' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN status = 'UNANSWERED' THEN 1 ELSE 0 END) as unanswered
                FROM ml_questions
                WHERE created_at >= :cutoff
                GROUP BY day
                ORDER BY day ASC
            ");
            $stmtDaily->execute([':cutoff' => $cutoff]);
            $daily = $stmtDaily->fetchAll(\PDO::FETCH_ASSOC);

            $stmtAvgResponse = $db->prepare("
                SELECT 
                    AVG(TIMESTAMPDIFF(MINUTE, created_at, answer_date)) as avg_response_minutes
                FROM ml_questions
                WHERE answer_date IS NOT NULL
                AND created_at >= :cutoff
            ");
            $stmtAvgResponse->execute([':cutoff' => $cutoff]);
            $avgResponse = $stmtAvgResponse->fetch(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'by_status' => $byStatus,
                    'daily' => $daily,
                    'avg_response_minutes' => round((float) ($avgResponse['avg_response_minutes'] ?? 0), 1),
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to fetch questions metrics: ' . $e->getMessage()]);
        }
    }

    /**
     * API: Export analytics report (CSV download)
     */
    public function exportReport(): void
    {
        try {
            $db = \App\Database::getInstance();
            $type = $this->request->get('type', 'sales');
            $days = $this->request->getInt('days', 30);
            $format = $this->request->get('format', 'csv');
            $cutoff = date('Y-m-d', strtotime("-{$days} days"));

            $rows = match ($type) {
                'sales' => $this->exportSalesData($db, $cutoff),
                'listings' => $this->exportListingsData($db),
                'questions' => $this->exportQuestionsData($db, $cutoff),
                default => throw new \InvalidArgumentException("Invalid export type: {$type}"),
            };

            if ($format === 'json') {
                header('Content-Type: application/json');
                header("Content-Disposition: attachment; filename=\"analytics-{$type}-{$days}d.json\"");
                echo json_encode(['success' => true, 'data' => $rows, 'count' => count($rows)]);
                return;
            }

            header('Content-Type: text/csv; charset=utf-8');
            header("Content-Disposition: attachment; filename=\"analytics-{$type}-{$days}d.csv\"");

            $output = fopen('php://output', 'w');
            if (!empty($rows)) {
                fputcsv($output, array_keys($rows[0]));
                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }
            }
            fclose($output);
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to export report: ' . $e->getMessage()]);
        }
    }

    /**
     * Export sales data for CSV/JSON
     */
    private function exportSalesData(\PDO $db, string $cutoff): array
    {
        $stmt = $db->prepare("
            SELECT 
                order_id, user_id, status, total_amount, 
                listing_type, DATE(date_created) as order_date
            FROM ml_orders
            WHERE DATE(date_created) >= :cutoff
            ORDER BY date_created DESC
        ");
        $stmt->execute([':cutoff' => $cutoff]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export listings data for CSV/JSON
     */
    private function exportListingsData(\PDO $db): array
    {
        $stmt = $db->query("
            SELECT 
                item_id, title, status, price, 
                available_quantity, category_id, permalink
            FROM items
            ORDER BY status ASC, price DESC
        ");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Export questions data for CSV/JSON
     */
    private function exportQuestionsData(\PDO $db, string $cutoff): array
    {
        $stmt = $db->prepare("
            SELECT 
                question_id, item_id, status, question_text,
                answer_text, from_user_id, DATE(created_at) as question_date,
                DATE(answer_date) as answered_date
            FROM ml_questions
            WHERE created_at >= :cutoff
            ORDER BY created_at DESC
        ");
        $stmt->execute([':cutoff' => $cutoff . ' 00:00:00']);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
