<?php

namespace App\Services;

use App\Database;

class AnalyticsService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get revenue trend over time (daily aggregation)
     */
    public function getRevenueTrend(string $startDate, string $endDate, string $granularity = 'day', ?int $accountId = null): array
    {
        $dateFormat = $granularity === 'day' ? '%Y-%m-%d' : '%Y-%m';

        $sql = "
            SELECT
                DATE_FORMAT(date_created, '$dateFormat') as period,
                SUM(total_amount) as revenue,
                COUNT(*) as orders,
                AVG(total_amount) as avg_ticket
            FROM ml_orders
            WHERE date_created BETWEEN ? AND ?
            AND status = 'paid'
        ";

        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        if ($accountId !== null && $accountId > 0) {
            $sql .= " AND ml_account_id = ?";
            $params[] = $accountId;
        }

        $sql .= "
            GROUP BY period
            ORDER BY period ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Calculate Customer Lifetime Value (Top segments)
     */
    public function getCustomerLTV(?int $accountId = null): array
    {
        $sql = "
            SELECT
                CASE
                    WHEN total >= 1000 THEN 'VIP'
                    WHEN total >= 500 THEN 'Premium'
                    WHEN total >= 100 THEN 'Regular'
                    ELSE 'New'
                END as segment,
                COUNT(*) as customer_count,
                AVG(total) as avg_ltv,
                SUM(total) as total_revenue
            FROM (
                SELECT
                    buyer_id,
                    SUM(total_amount) as total
                FROM ml_orders
                WHERE status = 'paid'
                AND buyer_id IS NOT NULL
        ";

        $params = [];
        if ($accountId !== null && $accountId > 0) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $sql .= "
                GROUP BY buyer_id
            ) as customer_totals
            GROUP BY segment
            ORDER BY avg_ltv DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Inventory Turnover Rate (Last 30 days)
     */
    public function getInventoryTurnover(?int $accountId = null): array
    {
        $sql = "
            SELECT
                i.category_id,
                COUNT(i.id) as total_items,
                SUM(i.available_quantity) as stock_units,
                SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(i.data, '$.sold_quantity')) AS UNSIGNED)) as units_sold,
                ROUND(
                    SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(i.data, '$.sold_quantity')) AS UNSIGNED)) /
                    NULLIF(SUM(i.available_quantity), 0) * 100,
                    2
                ) as turnover_rate
            FROM items i
            WHERE i.status = 'active'
        ";

        $params = [];
        if ($accountId !== null && $accountId > 0) {
            $sql .= " AND i.account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $sql .= "
            GROUP BY i.category_id
            HAVING turnover_rate > 0
            ORDER BY turnover_rate DESC
            LIMIT 10
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Profit Margin Analysis by Category
     */
    public function getProfitMargins(?int $accountId = null): array
    {
        $sql = "
            SELECT
                o.listing_type,
                COUNT(*) as order_count,
                SUM(o.total_amount) as revenue,
                SUM(o.net_profit) as profit,
                ROUND(AVG(o.gross_margin), 2) as avg_margin
            FROM ml_orders o
            WHERE o.status = 'paid'
            AND o.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ";

        $params = [];
        if ($accountId !== null && $accountId > 0) {
            $sql .= " AND o.ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $sql .= "
            GROUP BY o.listing_type
            ORDER BY avg_margin DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Conversion Funnel Analysis
     */
    public function getConversionFunnel(?int $accountId = null): array
    {
        $questionsSql = "SELECT COUNT(*) FROM ml_questions WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $ordersSql = "SELECT COUNT(*) FROM ml_orders WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];

        if ($accountId !== null && $accountId > 0) {
            $questionsSql .= " AND account_id = :account_id";
            $ordersSql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $questionsStmt = $this->db->prepare($questionsSql);
        $questionsStmt->execute($params);
        $questions = $questionsStmt->fetchColumn();

        $ordersStmt = $this->db->prepare($ordersSql);
        $ordersStmt->execute($params);
        $orders = $ordersStmt->fetchColumn();

        return [
            'questions' => (int)$questions,
            'orders' => (int)$orders,
            'conversion_rate' => $questions > 0 ? round(($orders / $questions) * 100, 2) : 0,
        ];
    }

    /**
     * Predictive Revenue Forecast (Simple linear regression)
     */
    public function getForecast(int $daysAhead = 7, ?int $accountId = null): array
    {
        $sql = "
            SELECT
                DATE(date_created) as day,
                SUM(total_amount) as revenue
            FROM ml_orders
            WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND status = 'paid'
        ";

        $params = [];
        if ($accountId !== null && $accountId > 0) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $sql .= "
            GROUP BY day
            ORDER BY day ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $historical = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($historical)) {
            return [];
        }

        $last7 = array_slice($historical, -7);
        $recentAvg = array_sum(array_column($last7, 'revenue')) / max(1, count($last7));

        $forecast = [];
        for ($i = 1; $i <= $daysAhead; $i++) {
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+$i days")),
                'predicted_revenue' => round($recentAvg, 2),
            ];
        }

        return $forecast;
    }

    /**
     * Real-time Dashboard Summary
     */
    public function getDashboardSummary(?int $accountId = null): array
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $orderSqlSuffix = " AND status = 'paid'";
        $orderParams = [];
        if ($accountId !== null && $accountId > 0) {
            $orderSqlSuffix .= " AND ml_account_id = :account_id";
            $orderParams['account_id'] = $accountId;
        }

        $todayStmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM ml_orders WHERE DATE(date_created) = :date" . $orderSqlSuffix);
        $todayStmt->execute(array_merge(['date' => $today], $orderParams));
        $revenueToday = (float)$todayStmt->fetchColumn();

        $yesterdayStmt = $this->db->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM ml_orders WHERE DATE(date_created) = :date" . $orderSqlSuffix);
        $yesterdayStmt->execute(array_merge(['date' => $yesterday], $orderParams));
        $revenueYesterday = (float)$yesterdayStmt->fetchColumn();

        $growth = $revenueYesterday > 0 ? (($revenueToday - $revenueYesterday) / $revenueYesterday) * 100 : 0;

        $questionsSql = "SELECT COUNT(*) FROM ml_questions WHERE status = 'UNANSWERED'";
        $itemsSql = "SELECT COUNT(*) FROM items WHERE status = 'active'";
        $sharedParams = [];
        if ($accountId !== null && $accountId > 0) {
            $questionsSql .= " AND account_id = :account_id";
            $itemsSql .= " AND account_id = :account_id";
            $sharedParams['account_id'] = $accountId;
        }

        $questionsStmt = $this->db->prepare($questionsSql);
        $questionsStmt->execute($sharedParams);
        $pendingQuestions = (int)$questionsStmt->fetchColumn();

        $itemsStmt = $this->db->prepare($itemsSql);
        $itemsStmt->execute($sharedParams);
        $activeItems = (int)$itemsStmt->fetchColumn();

        return [
            'revenue_today' => $revenueToday,
            'revenue_yesterday' => $revenueYesterday,
            'growth_rate' => round($growth, 2),
            'pending_questions' => $pendingQuestions,
            'active_items' => $activeItems,
        ];
    }
}
