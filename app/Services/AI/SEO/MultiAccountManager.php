<?php

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

/**
 * Multi-Account Manager Service
 * 
 * Gerencia operações em múltiplas contas do Mercado Livre simultaneamente:
 * - Dashboard consolidado
 * - Comparações entre contas
 * - Relatórios unificados
 * - Operações em lote cross-account
 * - Agrupamento de contas
 * 
 * @package App\Services\AI\SEO
 * @version 1.9.0
 * @since 2025-12-31
 */
class MultiAccountManager
{
    private PDO $db;
    private int $userId;

    public function __construct(int $userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

    /**
     * Dashboard consolidado de todas as contas
     * 
     * @param array|null $accountIds IDs específicos ou null para todas
     * @param array $options Filtros e opções adicionais
     * @return array Dashboard com métricas agregadas
     */
    public function getDashboard(?array $accountIds = null, array $options = []): array
    {
        $accountIds = $accountIds ?? $this->getUserAccountIds();
        
        if (empty($accountIds)) {
            return [
                'accounts' => [],
                'totals' => $this->getEmptyTotals(),
                'trends' => [],
                'alerts' => []
            ];
        }

        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        // Métricas totais
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT so.item_id) as total_items,
                COUNT(so.id) as total_optimizations,
                AVG(so.score_improvement) as avg_improvement,
                SUM(CASE WHEN so.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as optimizations_7d,
                SUM(CASE WHEN so.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as optimizations_30d
            FROM seo_optimizations so
            WHERE so.account_id IN ($placeholders)
        ");
        $stmt->execute($accountIds);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Performance por conta
        $stmt = $this->db->prepare("
            SELECT 
                ma.id,
                ma.nickname,
                ma.country_id,
                ma.is_active,
                COUNT(DISTINCT so.item_id) as items_count,
                COUNT(so.id) as optimizations_count,
                AVG(so.score_before) as avg_score_before,
                AVG(so.score_after) as avg_score_after,
                AVG(so.score_improvement) as avg_improvement,
                (SELECT COUNT(*) FROM competitor_watchlist WHERE account_id = ma.id) as watchlist_count,
                (SELECT COUNT(*) FROM competitor_alerts WHERE account_id = ma.id AND is_read = 0) as unread_alerts
            FROM ml_accounts ma
            LEFT JOIN seo_optimizations so ON so.account_id = ma.id
            WHERE ma.id IN ($placeholders) AND ma.user_id = ?
            GROUP BY ma.id
            ORDER BY optimizations_count DESC
        ");
        $stmt->execute([...$accountIds, $this->userId]);
        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tendências (últimos 30 dias)
        $trends = $this->getTrends($accountIds);

        // Alertas consolidados
        $alerts = $this->getConsolidatedAlerts($accountIds, $options['limit_alerts'] ?? 10);

        // Distribuição por tipo de otimização
        $stmt = $this->db->prepare("
            SELECT 
                optimization_type,
                COUNT(*) as count
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY optimization_type
        ");
        $stmt->execute($accountIds);
        $optimization_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'accounts' => $accounts,
            'totals' => [
                'total_items' => (int)$totals['total_items'],
                'total_optimizations' => (int)$totals['total_optimizations'],
                'avg_improvement' => round((float)$totals['avg_improvement'], 2),
                'optimizations_7d' => (int)$totals['optimizations_7d'],
                'optimizations_30d' => (int)$totals['optimizations_30d'],
                'total_accounts' => count($accounts),
                'active_accounts' => count(array_filter($accounts, fn($a) => $a['is_active']))
            ],
            'trends' => $trends,
            'alerts' => $alerts,
            'optimization_distribution' => $optimization_distribution
        ];
    }

    /**
     * Compara performance entre múltiplas contas
     * 
     * @param array $accountIds IDs das contas a comparar
     * @param string $metric Métrica para comparar (score, sales, views, conversions)
     * @param int $days Período em dias
     * @return array Dados comparativos
     */
    public function comparePerformance(array $accountIds, string $metric = 'score', int $days = 30): array
    {
        if (empty($accountIds)) {
            return ['error' => 'No accounts specified'];
        }

        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        // Validar contas pertencem ao usuário
        $stmt = $this->db->prepare("
            SELECT id, nickname FROM ml_accounts 
            WHERE id IN ($placeholders) AND user_id = ?
        ");
        $stmt->execute([...$accountIds, $this->userId]);
        $validAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($validAccounts) !== count($accountIds)) {
            return ['error' => 'Invalid account IDs'];
        }

        $comparison = [];

        switch ($metric) {
            case 'score':
                $comparison = $this->compareScores($accountIds, $days);
                break;
            case 'sales':
                $comparison = $this->compareSales($accountIds, $days);
                break;
            case 'views':
                $comparison = $this->compareViews($accountIds, $days);
                break;
            case 'conversions':
                $comparison = $this->compareConversions($accountIds, $days);
                break;
            default:
                $comparison = $this->compareScores($accountIds, $days);
        }

        return [
            'accounts' => $validAccounts,
            'metric' => $metric,
            'period_days' => $days,
            'comparison' => $comparison,
            'winner' => $this->determineWinner($comparison),
            'insights' => $this->generateComparisonInsights($comparison, $metric)
        ];
    }

    /**
     * Relatório consolidado de múltiplas contas
     * 
     * @param array $accountIds IDs das contas
     * @param string $period Período (daily, weekly, monthly)
     * @param array $options Opções adicionais
     * @return array Relatório completo
     */
    public function consolidatedReport(array $accountIds, string $period = 'monthly', array $options = []): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        // Determinar range de datas
        $dateRange = $this->getDateRange($period);

        // Otimizações por conta
        $stmt = $this->db->prepare("
            SELECT 
                ma.id,
                ma.nickname,
                COUNT(so.id) as optimizations,
                AVG(so.score_improvement) as avg_improvement,
                COUNT(DISTINCT so.item_id) as items_optimized,
                SUM(CASE WHEN so.optimization_type = 'title' THEN 1 ELSE 0 END) as title_opts,
                SUM(CASE WHEN so.optimization_type = 'description' THEN 1 ELSE 0 END) as desc_opts,
                SUM(CASE WHEN so.optimization_type = 'attributes' THEN 1 ELSE 0 END) as attr_opts
            FROM ml_accounts ma
            LEFT JOIN seo_optimizations so ON so.account_id = ma.id
                AND so.created_at BETWEEN ? AND ?
            WHERE ma.id IN ($placeholders) AND ma.user_id = ?
            GROUP BY ma.id
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end'], ...$accountIds, $this->userId]);
        $accountStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Performance tracking
        $stmt = $this->db->prepare("
            SELECT 
                account_id,
                AVG(score_after - score_before) as avg_score_gain,
                SUM(views_increase) as total_views_increase,
                SUM(sales_increase) as total_sales_increase
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at BETWEEN ? AND ?
            GROUP BY account_id
        ");
        $stmt->execute([...$accountIds, $dateRange['start'], $dateRange['end']]);
        $performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Merge data
        $report = [];
        foreach ($accountStats as $stat) {
            $perf = array_filter($performance, fn($p) => $p['account_id'] == $stat['id']);
            $perf = reset($perf) ?: ['avg_score_gain' => 0, 'total_views_increase' => 0, 'total_sales_increase' => 0];
            
            $report[] = [
                'account_id' => $stat['id'],
                'nickname' => $stat['nickname'],
                'optimizations' => (int)$stat['optimizations'],
                'avg_improvement' => round((float)$stat['avg_improvement'], 2),
                'items_optimized' => (int)$stat['items_optimized'],
                'title_opts' => (int)$stat['title_opts'],
                'desc_opts' => (int)$stat['desc_opts'],
                'attr_opts' => (int)$stat['attr_opts'],
                'avg_score_gain' => round((float)$perf['avg_score_gain'], 2),
                'views_increase' => (int)$perf['total_views_increase'],
                'sales_increase' => (int)$perf['total_sales_increase']
            ];
        }

        // Calcular totais
        $totals = [
            'total_optimizations' => array_sum(array_column($report, 'optimizations')),
            'total_items' => array_sum(array_column($report, 'items_optimized')),
            'avg_improvement' => round(array_sum(array_column($report, 'avg_improvement')) / count($report), 2),
            'total_views_increase' => array_sum(array_column($report, 'views_increase')),
            'total_sales_increase' => array_sum(array_column($report, 'sales_increase'))
        ];

        // ROI estimado (baseado em vendas aumentadas)
        $avgProductPrice = $options['avg_product_price'] ?? 150.0;
        $estimatedRevenue = $totals['total_sales_increase'] * $avgProductPrice;

        return [
            'period' => $period,
            'date_range' => $dateRange,
            'accounts' => $report,
            'totals' => $totals,
            'roi' => [
                'sales_increase' => $totals['total_sales_increase'],
                'avg_product_price' => $avgProductPrice,
                'estimated_revenue' => round($estimatedRevenue, 2),
                'views_increase' => $totals['total_views_increase']
            ],
            'insights' => $this->generateReportInsights($report, $totals)
        ];
    }

    /**
     * Otimização em lote cross-account
     * 
     * @param array $accountIds IDs das contas
     * @param array $options Opções de otimização
     * @return array Resultado da operação
     */
    public function bulkOptimize(array $accountIds, array $options = []): array
    {
        $results = [];
        $totalProcessed = 0;
        $totalSuccess = 0;
        $totalErrors = 0;

        foreach ($accountIds as $accountId) {
            try {
                // Validar conta pertence ao usuário
                $stmt = $this->db->prepare("
                    SELECT id FROM ml_accounts WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$accountId, $this->userId]);
                
                if (!$stmt->fetch()) {
                    $results[] = [
                        'account_id' => $accountId,
                        'status' => 'error',
                        'message' => 'Account not found or unauthorized'
                    ];
                    $totalErrors++;
                    continue;
                }

                // Iniciar otimização em lote para esta conta
                $bulkOptimizer = new BulkOptimizer($accountId);

                $selection = $bulkOptimizer->selectPriorityItems((int)($options['max_items_per_account'] ?? 50));
                $items = $selection['items'] ?? [];
                $itemIds = array_values(array_filter(array_map(fn($item) => $item['id'] ?? null, $items)));

                if (empty($itemIds)) {
                    $results[] = [
                        'account_id' => $accountId,
                        'status' => 'success',
                        'job_id' => null,
                        'items_queued' => 0,
                        'message' => 'Nenhum item elegível para otimização'
                    ];
                    $totalSuccess++;
                    continue;
                }

                $result = $bulkOptimizer->startBulkOptimization($itemIds, [
                    'optimizations' => $options['optimizations'] ?? [
                        'optimize_title' => true,
                        'optimize_description' => true,
                        'fill_attributes' => true
                    ],
                    'auto_apply' => $options['auto_apply'] ?? false,
                    'filters' => $options['filters'] ?? ['seo_score' => ['max' => 70]],
                ]);

                $results[] = [
                    'account_id' => $accountId,
                    'status' => 'success',
                    'job_id' => $result['job_id'] ?? null,
                    'items_queued' => count($itemIds)
                ];

                $totalProcessed += count($itemIds);
                $totalSuccess++;

            } catch (\Exception $e) {
                $results[] = [
                    'account_id' => $accountId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                $totalErrors++;
            }
        }

        return [
            'total_accounts' => count($accountIds),
            'success_count' => $totalSuccess,
            'error_count' => $totalErrors,
            'total_items_queued' => $totalProcessed,
            'results' => $results
        ];
    }

    /**
     * Gerenciar grupos de contas
     * 
     * @param string $action create, update, delete, list
     * @param array $data Dados do grupo
     * @return array Resultado da operação
     */
    public function manageAccountGroups(string $action, array $data = []): array
    {
        switch ($action) {
            case 'create':
                return $this->createAccountGroup($data);
            case 'update':
                return $this->updateAccountGroup($data);
            case 'delete':
                return $this->deleteAccountGroup($data['group_id']);
            case 'list':
                return $this->listAccountGroups();
            case 'add_account':
                return $this->addAccountToGroup($data['group_id'], $data['account_id']);
            case 'remove_account':
                return $this->removeAccountFromGroup($data['group_id'], $data['account_id']);
            default:
                return ['error' => 'Invalid action'];
        }
    }

    /**
     * Trocar contexto de conta ativa
     * 
     * @param int $accountId ID da conta
     * @return array Dados da conta ativada
     */
    public function switchAccount(int $accountId): array
    {
        // Validar conta
        $stmt = $this->db->prepare("
            SELECT * FROM ml_accounts WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$accountId, $this->userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            return ['error' => 'Account not found'];
        }

        // Registrar switch no log
        $stmt = $this->db->prepare("
            INSERT INTO system_logs (user_id, action, details, created_at)
            VALUES (?, 'account_switch', ?, NOW())
        ");
        $stmt->execute([
            $this->userId,
            json_encode(['from' => $_SESSION['active_account_id'] ?? null, 'to' => $accountId])
        ]);

        // Atualizar sessão
        $_SESSION['active_account_id'] = $accountId;

        return [
            'success' => true,
            'account' => [
                'id' => $account['id'],
                'nickname' => $account['nickname'],
                'country_id' => $account['country_id'],
                'is_active' => (bool)$account['is_active']
            ]
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getUserAccountIds(): array
    {
        $stmt = $this->db->prepare("
            SELECT id FROM ml_accounts WHERE user_id = ?
        ");
        $stmt->execute([$this->userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getEmptyTotals(): array
    {
        return [
            'total_items' => 0,
            'total_optimizations' => 0,
            'avg_improvement' => 0,
            'optimizations_7d' => 0,
            'optimizations_30d' => 0,
            'total_accounts' => 0,
            'active_accounts' => 0
        ];
    }

    private function getTrends(array $accountIds): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as optimizations,
                AVG(score_improvement) as avg_improvement
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute($accountIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getConsolidatedAlerts(array $accountIds, int $limit): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';

        $limitSql = max(1, min(200, (int)$limit));
        
        $stmt = $this->db->prepare("
            SELECT 
                ca.*,
                ma.nickname as account_nickname
            FROM competitor_alerts ca
            JOIN ml_accounts ma ON ma.id = ca.account_id
            WHERE ca.account_id IN ($placeholders)
              AND ca.is_read = 0
            ORDER BY ca.priority DESC, ca.created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->execute($accountIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function compareScores(array $accountIds, int $days): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT 
                account_id,
                AVG(score_before) as avg_score_before,
                AVG(score_after) as avg_score_after,
                AVG(score_improvement) as avg_improvement
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY account_id
        ");
        $stmt->execute([...$accountIds, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function compareSales(array $accountIds, int $days): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT 
                account_id,
                SUM(sales_increase) as total_sales_increase,
                AVG(sales_increase) as avg_sales_increase
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY account_id
        ");
        $stmt->execute([...$accountIds, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function compareViews(array $accountIds, int $days): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT 
                account_id,
                SUM(views_increase) as total_views_increase,
                AVG(views_increase) as avg_views_increase
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY account_id
        ");
        $stmt->execute([...$accountIds, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function compareConversions(array $accountIds, int $days): array
    {
        $placeholders = str_repeat('?,', count($accountIds) - 1) . '?';
        
        $stmt = $this->db->prepare("
            SELECT 
                account_id,
                SUM(sales_increase) / NULLIF(SUM(views_increase), 0) * 100 as conversion_rate
            FROM seo_optimizations
            WHERE account_id IN ($placeholders)
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY account_id
        ");
        $stmt->execute([...$accountIds, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function determineWinner(array $comparison): ?array
    {
        if (empty($comparison)) return null;

        $winner = $comparison[0];
        foreach ($comparison as $item) {
            if (($item['avg_improvement'] ?? 0) > ($winner['avg_improvement'] ?? 0)) {
                $winner = $item;
            }
        }

        return $winner;
    }

    private function generateComparisonInsights(array $comparison, string $metric): array
    {
        $insights = [];

        if (empty($comparison)) {
            return ['No data available for comparison'];
        }

        // Best performer
        $winner = $this->determineWinner($comparison);
        $insights[] = "Account {$winner['account_id']} is the top performer with {$winner['avg_improvement']}% improvement";

        // Variação
        $values = array_column($comparison, 'avg_improvement');
        $stddev = $this->calculateStdDev($values);
        
        if ($stddev < 5) {
            $insights[] = "Performance is consistent across accounts (low variation)";
        } else {
            $insights[] = "Significant performance variation detected - review top performers' strategies";
        }

        return $insights;
    }

    private function generateReportInsights(array $report, array $totals): array
    {
        $insights = [];

        // Top performer
        usort($report, fn($a, $b) => $b['optimizations'] <=> $a['optimizations']);
        $top = $report[0] ?? null;
        
        if ($top) {
            $insights[] = "Most active account: {$top['nickname']} with {$top['optimizations']} optimizations";
        }

        // Eficiência
        if ($totals['total_optimizations'] > 0) {
            $avgItemsPerOpt = round($totals['total_items'] / $totals['total_optimizations'], 2);
            $insights[] = "Average efficiency: {$avgItemsPerOpt} items per optimization";
        }

        // ROI
        if (isset($totals['total_sales_increase']) && $totals['total_sales_increase'] > 0) {
            $insights[] = "Optimizations generated {$totals['total_sales_increase']} additional sales";
        }

        return $insights;
    }

    private function getDateRange(string $period): array
    {
        $end = date('Y-m-d H:i:s');
        
        switch ($period) {
            case 'daily':
                $start = date('Y-m-d 00:00:00');
                break;
            case 'weekly':
                $start = date('Y-m-d H:i:s', strtotime('-7 days'));
                break;
            case 'monthly':
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
                break;
            default:
                $start = date('Y-m-d H:i:s', strtotime('-30 days'));
        }

        return ['start' => $start, 'end' => $end];
    }

    private function calculateStdDev(array $values): float
    {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        
        return sqrt($variance);
    }

    private function createAccountGroup(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO account_groups (user_id, name, description, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $this->userId,
            $data['name'],
            $data['description'] ?? null
        ]);

        $groupId = $this->db->lastInsertId();

        // Adicionar contas ao grupo
        if (!empty($data['account_ids'])) {
            foreach ($data['account_ids'] as $accountId) {
                $this->addAccountToGroup($groupId, $accountId);
            }
        }

        return [
            'success' => true,
            'group_id' => $groupId,
            'message' => 'Group created successfully'
        ];
    }

    private function updateAccountGroup(array $data): array
    {
        $stmt = $this->db->prepare("
            UPDATE account_groups 
            SET name = ?, description = ?
            WHERE id = ? AND user_id = ?
        ");
        
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['group_id'],
            $this->userId
        ]);

        return [
            'success' => true,
            'message' => 'Group updated successfully'
        ];
    }

    private function deleteAccountGroup(int $groupId): array
    {
        // Remover associações
        $stmt = $this->db->prepare("
            DELETE FROM account_group_members WHERE group_id = ?
        ");
        $stmt->execute([$groupId]);

        // Deletar grupo
        $stmt = $this->db->prepare("
            DELETE FROM account_groups WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$groupId, $this->userId]);

        return [
            'success' => true,
            'message' => 'Group deleted successfully'
        ];
    }

    private function listAccountGroups(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                ag.*,
                COUNT(agm.account_id) as account_count
            FROM account_groups ag
            LEFT JOIN account_group_members agm ON agm.group_id = ag.id
            WHERE ag.user_id = ?
            GROUP BY ag.id
        ");
        $stmt->execute([$this->userId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function addAccountToGroup(int $groupId, int $accountId): array
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO account_group_members (group_id, account_id, created_at)
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$groupId, $accountId]);

        return [
            'success' => true,
            'message' => 'Account added to group'
        ];
    }

    private function removeAccountFromGroup(int $groupId, int $accountId): array
    {
        $stmt = $this->db->prepare("
            DELETE FROM account_group_members 
            WHERE group_id = ? AND account_id = ?
        ");
        $stmt->execute([$groupId, $accountId]);

        return [
            'success' => true,
            'message' => 'Account removed from group'
        ];
    }
}
