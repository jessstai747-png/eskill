<?php

namespace App\Services;

use App\Database;
use PDO;

class StatisticsService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obtém estatísticas gerais do sistema
     */
    public function getGeneralStats(): array
    {
        $stats = [
            'accounts' => $this->getAccountStats(),
            'orders' => $this->getOrderStats(),
            'items' => $this->getItemStats(),
            'revenue' => $this->getRevenueStats(),
            'performance' => $this->getPerformanceStats(),
        ];

        return $stats;
    }

    /**
     * Estatísticas de contas
     */
    private function getAccountStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired
                FROM ml_accounts
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Estatísticas de pedidos
     */
    private function getOrderStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM ml_orders
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Estatísticas de anúncios
     */
    private function getItemStats(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                    SUM(CASE WHEN catalog_product_id IS NOT NULL THEN 1 ELSE 0 END) as catalog,
                    SUM(CASE WHEN catalog_product_id IS NULL THEN 1 ELSE 0 END) as common,
                    AVG(price) as avg_price,
                    SUM(available_quantity) as total_stock
                FROM items
            ");

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => 'Tabela items não encontrada'];
        }
    }

    /**
     * Estatísticas de receita
     */
    private function getRevenueStats(): array
    {
        try {
            // Receita dos últimos 30 dias
            $stmt = $this->db->query("
                SELECT
                    DATE(date_created) as date,
                    COUNT(*) as orders_count,
                    SUM(total_amount) as daily_revenue
                FROM ml_orders
                WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND status IN ('paid', 'confirmed')
                GROUP BY DATE(date_created)
                ORDER BY date DESC
            ");

            $dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Receita total por mês
            $stmt = $this->db->query("
                SELECT
                    DATE_FORMAT(date_created, '%Y-%m') as month,
                    COUNT(*) as orders_count,
                    SUM(total_amount) as monthly_revenue
                FROM ml_orders
                WHERE date_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                AND status IN ('paid', 'confirmed')
                GROUP BY DATE_FORMAT(date_created, '%Y-%m')
                ORDER BY month DESC
            ");

            $monthlyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'daily' => $dailyRevenue,
                'monthly' => $monthlyRevenue,
                'total_30_days' => array_sum(array_column($dailyRevenue, 'daily_revenue')),
                'total_12_months' => array_sum(array_column($monthlyRevenue, 'monthly_revenue')),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Estatísticas de performance
     */
    private function getPerformanceStats(): array
    {
        try {
            // Taxa de conversão (pedidos / anúncios ativos)
            $ordersCount = $this->db->query("SELECT COUNT(*) FROM ml_orders")->fetchColumn();
            $itemsCount = $this->db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn();

            $conversionRate = $itemsCount > 0 ? ($ordersCount / $itemsCount) * 100 : 0;

            // Calcular tempo médio de resposta e chamadas da API a partir de logs
            $avgResponseTime = 0.0;
            $totalApiCallsToday = 0;
            $logFile = __DIR__ . '/../../storage/logs/api-' . date('Y-m-d') . '.log';
            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $totalApiCallsToday = count($lines);
                $responseTimes = [];
                foreach (array_slice($lines, -500) as $line) {
                    if (preg_match('/duration[=:]\s*([\d.]+)/', $line, $m)) {
                        $responseTimes[] = (float)$m[1];
                    }
                }
                $avgResponseTime = !empty($responseTimes)
                    ? round(array_sum($responseTimes) / count($responseTimes), 3)
                    : 0.0;
            }

            return [
                'conversion_rate' => round($conversionRate, 2),
                'avg_api_response_time' => $avgResponseTime,
                'total_api_calls_today' => $totalApiCallsToday,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Obtém estatísticas por período
     */
    public function getStatsByPeriod(string $startDate, string $endDate): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as orders_count,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    MIN(total_amount) as min_order_value,
                    MAX(total_amount) as max_order_value
                FROM ml_orders
                WHERE DATE(date_created) BETWEEN :start_date AND :end_date
            ");

            $stmt->execute([
                ':start_date' => $startDate,
                ':end_date' => $endDate,
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Obtém top produtos por vendas
     */
    public function getTopProducts(int $limit = 10): array
    {
        try {
            $limitSql = max(1, min((int)$limit, 200));

            $stmt = $this->db->prepare("
                SELECT
                    oi.item_id,
                    i.title,
                    i.price,
                    i.thumbnail,
                    i.category_id,
                    COUNT(oi.id) as order_count,
                    SUM(oi.quantity) as total_quantity,
                    SUM(oi.unit_price * oi.quantity) as total_revenue
                FROM ml_order_items oi
                INNER JOIN items i ON i.ml_item_id = oi.item_id
                WHERE oi.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY oi.item_id, i.title, i.price, i.thumbnail, i.category_id
                ORDER BY total_quantity DESC
                LIMIT {$limitSql}
            ");
            $stmt->execute();
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($results)) {
                // Fallback: buscar itens com mais sold_quantity
                $stmt = $this->db->prepare("
                    SELECT item_id, title, price, thumbnail, category_id,
                           sold_quantity as total_quantity,
                           (price * sold_quantity) as total_revenue,
                           sold_quantity as order_count
                    FROM items
                    WHERE status = 'active' AND sold_quantity > 0
                    ORDER BY sold_quantity DESC
                    LIMIT {$limitSql}
                ");
                $stmt->execute();
                $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }

            return [
                'products' => $results,
                'period' => '30d',
                'total' => count($results),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage(), 'products' => []];
        }
    }

    /**
     * Obtém estatísticas por categoria
     */
    public function getStatsByCategory(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    category_id,
                    COUNT(*) as items_count,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    SUM(available_quantity) as total_stock
                FROM items
                GROUP BY category_id
                ORDER BY items_count DESC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return ['error' => 'Tabela items não encontrada'];
        }
    }
}
