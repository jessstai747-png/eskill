<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Analytics Service
 * 
 * Fornece métricas agregadas e insights sobre a completude
 * da ficha técnica em toda a base de anúncios
 */
class TechSheetAnalyticsService
{
    private PDO $db;
    private int $accountId;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Dashboard de métricas agregadas
     * 
     * @return array
     */
    public function getDashboard(): array
    {
        $overview = $this->getOverview();
        $byCategory = $this->getMetricsByCategory();
        $trending = $this->getTrendingImprovements();
        $suggestions = $this->getSuggestionsStats();

        return [
            'overview' => $overview,
            'by_category' => $byCategory,
            'trending' => $trending,
            'suggestions' => $suggestions,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Visão geral da conta
     * 
     * @return array
     */
    public function getOverview(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT i.id) as total_items,
                COUNT(DISTINCT i.category_id) as total_categories,
                COUNT(DISTINCT s.item_id) as analyzed_items,
                COALESCE(AVG(s.completeness_percent), 0) as avg_completeness,
                SUM(CASE WHEN s.missing_required > 0 THEN 1 ELSE 0 END) as items_with_critical_gaps,
                SUM(CASE WHEN s.missing_filter > 0 THEN 1 ELSE 0 END) as items_with_filter_gaps,
                SUM(CASE WHEN s.completeness_percent >= 80 THEN 1 ELSE 0 END) as items_above_80,
                SUM(CASE WHEN s.completeness_percent < 50 THEN 1 ELSE 0 END) as items_below_50
            FROM items i
            LEFT JOIN tech_sheet_item_summary s ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $analyzed_items = (int)$data['analyzed_items'];
        $total_items = (int)$data['total_items'];

        return [
            'total_items' => $total_items,
            'total_categories' => (int)$data['total_categories'],
            'analyzed_items' => $analyzed_items,
            'analysis_coverage' => $total_items > 0 ? round(($analyzed_items / $total_items) * 100, 1) : 0,
            'avg_completeness' => round((float)$data['avg_completeness'], 1),
            'items_with_critical_gaps' => (int)$data['items_with_critical_gaps'],
            'items_with_filter_gaps' => (int)$data['items_with_filter_gaps'],
            'items_above_80' => (int)$data['items_above_80'],
            'items_below_50' => (int)$data['items_below_50'],
        ];
    }

    /**
     * Métricas por categoria
     * 
     * @param int $limit
     * @return array
     */
    public function getMetricsByCategory(int $limit = 20): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT 
                i.category_id,
                COUNT(DISTINCT i.id) as item_count,
                COUNT(DISTINCT s.item_id) as analyzed_count,
                COALESCE(AVG(s.completeness_percent), 0) as avg_completeness,
                SUM(s.missing_required) as total_missing_required,
                SUM(s.missing_filter) as total_missing_filter,
                SUM(s.missing_hidden) as total_missing_hidden,
                MAX(s.last_analyzed_at) as last_analyzed_at
            FROM items i
            LEFT JOIN tech_sheet_item_summary s ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND i.category_id IS NOT NULL
            GROUP BY i.category_id
            ORDER BY item_count DESC
                        LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();

        $categories = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = [
                'category_id' => $row['category_id'],
                'item_count' => (int)$row['item_count'],
                'analyzed_count' => (int)$row['analyzed_count'],
                'avg_completeness' => round((float)$row['avg_completeness'], 1),
                'total_missing_required' => (int)$row['total_missing_required'],
                'total_missing_filter' => (int)$row['total_missing_filter'],
                'total_missing_hidden' => (int)$row['total_missing_hidden'],
                'last_analyzed_at' => $row['last_analyzed_at'],
                'health_score' => $this->calculateHealthScore($row),
            ];
        }

        return $categories;
    }

    /**
     * Itens com maior melhoria recente
     * 
     * @param int $days
     * @param int $limit
     * @return array
     */
    public function getTrendingImprovements(int $days = 7, int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT 
                l.item_id,
                i.title,
                i.category_id,
                    COUNT(l.id) as improvements_count,
                    MAX(l.created_at) as last_improvement,
                    s.completeness_percent as current_completeness
            FROM tech_sheet_execution_log l
            INNER JOIN items i ON l.item_id = i.ml_item_id AND l.account_id = i.account_id
            LEFT JOIN tech_sheet_item_summary s ON l.item_id = s.item_id AND l.account_id = s.account_id
            WHERE l.account_id = :account_id
              AND l.action = 'apply'
              AND l.result = 'success'
              AND l.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY l.item_id, i.title, i.category_id, s.completeness_percent
            ORDER BY improvements_count DESC, last_improvement DESC
                        LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas de sugestões
     * 
     * @return array
     */
    public function getSuggestionsStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                source,
                COUNT(*) as count,
                AVG(confidence) as avg_confidence
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY status, source
        ");
        $stmt->execute([':account_id' => $this->accountId]);

        $byStatus = [];
        $bySource = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $status = $row['status'];
            $source = $row['source'];
            $count = (int)$row['count'];
            $avgConf = round((float)$row['avg_confidence'], 1);

            if (!isset($byStatus[$status])) {
                $byStatus[$status] = 0;
            }
            $byStatus[$status] += $count;

            if (!isset($bySource[$source])) {
                $bySource[$source] = ['count' => 0, 'avg_confidence' => 0];
            }
            $bySource[$source]['count'] += $count;
            $bySource[$source]['avg_confidence'] = $avgConf;
        }

        return [
            'by_status' => $byStatus,
            'by_source' => $bySource,
        ];
    }

    /**
     * Calcula score de saúde da categoria (0-100)
     * 
     * @param array $row
     * @return int
     */
    private function calculateHealthScore(array $row): int
    {
        $itemCount = (int)$row['item_count'];
        $analyzedCount = (int)$row['analyzed_count'];
        $avgCompleteness = (float)$row['avg_completeness'];
        $missingRequired = (int)$row['total_missing_required'];

        if ($itemCount === 0) {
            return 0;
        }

        // Cobertura de análise (0-30 pontos)
        $coverage = $analyzedCount / $itemCount;
        $coverageScore = min(30, $coverage * 30);

        // Completude média (0-50 pontos)
        $completenessScore = ($avgCompleteness / 100) * 50;

        // Penalidade por lacunas críticas (0-20 pontos)
        $criticalPenalty = max(0, 20 - ($missingRequired * 2));

        $score = $coverageScore + $completenessScore + $criticalPenalty;

        return (int)round(min(100, max(0, $score)));
    }

    /**
     * Identificar categorias prioritárias para otimização
     * 
     * @param int $limit
     * @return array
     */
    public function getPriorityCategoriesForOptimization(int $limit = 10): array
    {
        $categories = $this->getMetricsByCategory(100);

        // Ordenar por:
        // 1. Maior número de lacunas críticas
        // 2. Menor completude média
        // 3. Maior número de itens
        usort($categories, function ($a, $b) {
            if ($a['total_missing_required'] !== $b['total_missing_required']) {
                return $b['total_missing_required'] <=> $a['total_missing_required'];
            }
            if ($a['avg_completeness'] !== $b['avg_completeness']) {
                return $a['avg_completeness'] <=> $b['avg_completeness'];
            }
            return $b['item_count'] <=> $a['item_count'];
        });

        return array_slice($categories, 0, $limit);
    }
}
