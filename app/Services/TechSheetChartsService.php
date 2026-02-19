<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Charts Data Service
 * 
 * Fornece dados para gráficos e visualizações
 * - Tendências de completude
 * - Distribuição por categoria
 * - Timeline de melhorias
 * - Performance de fontes
 */
class TechSheetChartsService
{
    private PDO $db;
    private int $accountId;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Verifica se uma tabela possui uma coluna (para manter compatibilidade com schemas legados).
     */
    private function tableHasColumn(string $table, string $column): bool
    {
        // Apenas tabelas conhecidas (evita SQL injection em identificadores)
        if (!in_array($table, ['items', 'tech_sheet_item_summary', 'tech_sheet_execution_log', 'tech_sheet_suggestions'], true)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table} LIKE :col");
            $stmt->execute([':col' => $column]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Dados para gráfico de tendência de completude (30 dias)
     * 
     * @return array Formato Chart.js
     */
    public function getCompletenessTrend(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(s.last_analyzed_at) as date,
                AVG(s.completeness_percent) as avg_completeness,
                COUNT(DISTINCT s.item_id) as items_analyzed
            FROM tech_sheet_item_summary s
            INNER JOIN items i ON s.item_id = i.ml_item_id AND s.account_id = i.account_id
            WHERE s.account_id = :account_id
              AND s.last_analyzed_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              AND i.status = 'active'
            GROUP BY DATE(s.last_analyzed_at)
            ORDER BY date ASC
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $days,
        ]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($data, 'date'),
            'datasets' => [
                [
                    'label' => 'Completude Média (%)',
                    'data' => array_map(fn($row) => round($row['avg_completeness'], 1), $data),
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
            ],
        ];
    }

    /**
     * Dados para gráfico de distribuição por categoria (top 10)
     * 
     * @return array Formato Chart.js (bar chart)
     */
    public function getCategoryDistribution(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                i.category_id,
                COUNT(DISTINCT i.id) as item_count,
                AVG(s.completeness_percent) as avg_completeness
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND i.category_id IS NOT NULL
            GROUP BY i.category_id
            ORDER BY item_count DESC
            LIMIT 10
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($data, 'category_id'),
            'datasets' => [
                [
                    'label' => 'Número de Itens',
                    'data' => array_column($data, 'item_count'),
                    'backgroundColor' => '#10b981',
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Completude Média (%)',
                    'data' => array_map(fn($row) => round($row['avg_completeness'] ?? 0, 1), $data),
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'y1',
                ],
            ],
        ];
    }

    /**
     * Dados para gráfico de pizza - status das sugestões
     * 
     * @return array Formato Chart.js (pie chart)
     */
    public function getSuggestionsStatus(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY status
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $colors = [
            'pending' => '#f59e0b',
            'approved' => '#3b82f6',
            'applied' => '#10b981',
            'rejected' => '#ef4444',
        ];
        
        $labels = [];
        $values = [];
        $backgroundColors = [];
        
        foreach ($data as $row) {
            $labels[] = ucfirst($row['status']);
            $values[] = $row['count'];
            $backgroundColors[] = $colors[$row['status']] ?? '#6b7280';
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $values,
                    'backgroundColor' => $backgroundColors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                ],
            ],
        ];
    }

    /**
     * Dados para gráfico de performance por fonte
     * 
     * @return array Formato Chart.js (horizontal bar)
     */
    public function getSourcePerformance(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                source,
                COUNT(*) as total,
                AVG(confidence) as avg_confidence,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied_count
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY source
            ORDER BY total DESC
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mapeamento canônico de sources (usando constantes de TechSheetService)
        $sources = [
            TechSheetService::SOURCE_TITLE => 'Título',
            TechSheetService::SOURCE_BENCHMARK => 'Benchmark',
            TechSheetService::SOURCE_AI => 'IA',
            TechSheetService::SOURCE_INFERENCE => 'Inferência',
            TechSheetService::SOURCE_DEFAULT => 'Padrão',
            TechSheetService::SOURCE_MANUAL => 'Manual',
            // Fontes legadas (para compatibilidade com dados existentes)
            'title_extraction' => 'Título',
            'competitor' => 'Benchmark',
            'description' => 'Descrição',
            'autocomplete' => 'Autocomplete',
            'trends' => 'Tendências',
            'history' => 'Histórico',
            'search_strategy' => 'Estratégia',
        ];
        
        return [
            'labels' => array_map(fn($row) => $sources[$row['source']] ?? ucfirst($row['source']), $data),
            'datasets' => [
                [
                    'label' => 'Total de Sugestões',
                    'data' => array_column($data, 'total'),
                    'backgroundColor' => '#667eea',
                ],
                [
                    'label' => 'Aplicadas',
                    'data' => array_column($data, 'applied_count'),
                    'backgroundColor' => '#10b981',
                ],
            ],
        ];
    }

    /**
     * Timeline de melhorias (últimos 7 dias)
     * 
     * @return array Formato Chart.js (line chart)
     */
    public function getImprovementsTimeline(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as suggestions_generated,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_column($data, 'date'),
            'datasets' => [
                [
                    'label' => 'Sugestões Geradas',
                    'data' => array_column($data, 'suggestions_generated'),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Aprovadas',
                    'data' => array_column($data, 'approved'),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Aplicadas',
                    'data' => array_column($data, 'approved'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    /**
     * Heat map de completude por dia da semana e hora
     * 
     * @return array
     */
    public function getActivityHeatmap(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DAYOFWEEK(created_at) as day_of_week,
                HOUR(created_at) as hour,
                COUNT(*) as activity_count
            FROM tech_sheet_execution_log
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DAYOFWEEK(created_at), HOUR(created_at)
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Montar matriz 7x24
        $heatmap = array_fill(0, 7, array_fill(0, 24, 0));
        
        foreach ($data as $row) {
            $day = $row['day_of_week'] - 1; // 0-6
            $hour = $row['hour'];
            $heatmap[$day][$hour] = $row['activity_count'];
        }
        
        return [
            'days' => ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
            'hours' => range(0, 23),
            'data' => $heatmap,
        ];
    }

    /**
     * Dashboard completo com todos os gráficos
     * 
     * @return array
     */
    public function getDashboardCharts(): array
    {
        return [
            'completeness_trend' => $this->getCompletenessTrend(30),
            'category_distribution' => $this->getCategoryDistribution(),
            'suggestions_status' => $this->getSuggestionsStatus(),
            'source_performance' => $this->getSourcePerformance(),
            'improvements_timeline' => $this->getImprovementsTimeline(),
            'activity_heatmap' => $this->getActivityHeatmap(),
            'conversion_correlation' => $this->getConversionCorrelation(),
            'completeness_histogram' => $this->getCompletenessHistogram(),
            'top_missing_attributes' => $this->getTopMissingAttributes(),
            'weekly_progress' => $this->getWeeklyProgress(),
        ];
    }

    /**
     * 📊 Correlação entre completude e conversão (vendas)
     * Analisa se itens mais completos vendem mais
     * 
     * @return array
     */
    public function getConversionCorrelation(): array
    {
        $hasSoldQuantity = $this->tableHasColumn('items', 'sold_quantity');
        $hasPrice = $this->tableHasColumn('items', 'price');

        $totalSalesExpr = $hasSoldQuantity ? 'COALESCE(SUM(i.sold_quantity), 0)' : '0';
        $avgSalesExpr = $hasSoldQuantity ? 'COALESCE(AVG(i.sold_quantity), 0)' : '0';
        $totalRevenueExpr = ($hasSoldQuantity && $hasPrice)
            ? 'COALESCE(SUM(i.sold_quantity * i.price), 0)'
            : '0';

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN s.completeness_percent >= 90 THEN '90-100%'
                        WHEN s.completeness_percent >= 80 THEN '80-89%'
                        WHEN s.completeness_percent >= 70 THEN '70-79%'
                        WHEN s.completeness_percent >= 60 THEN '60-69%'
                        WHEN s.completeness_percent >= 50 THEN '50-59%'
                        ELSE '< 50%'
                    END as completeness_range,
                    COUNT(DISTINCT i.id) as item_count,
                    {$totalSalesExpr} as total_sales,
                    {$avgSalesExpr} as avg_sales_per_item,
                    {$totalRevenueExpr} as total_revenue
                FROM items i
                INNER JOIN tech_sheet_item_summary s 
                    ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
                WHERE i.account_id = :account_id
                  AND i.status = 'active'
                  AND s.completeness_percent IS NOT NULL
                GROUP BY completeness_range
                ORDER BY 
                    CASE completeness_range
                        WHEN '90-100%' THEN 1
                        WHEN '80-89%' THEN 2
                        WHEN '70-79%' THEN 3
                        WHEN '60-69%' THEN 4
                        WHEN '50-59%' THEN 5
                        ELSE 6
                    END
            ");
            
            $stmt->execute([':account_id' => $this->accountId]);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Em ambientes de teste/dev com schema incompleto, não derruba o dashboard.
            $data = [];
        }
        
        return [
            'labels' => array_column($data, 'completeness_range'),
            'datasets' => [
                [
                    'label' => 'Qtd. Vendida (média/item)',
                    'data' => array_map(fn($row) => round($row['avg_sales_per_item'], 1), $data),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                    'borderWidth' => 2,
                    'yAxisID' => 'y',
                ],
                [
                    'label' => 'Número de Itens',
                    'data' => array_column($data, 'item_count'),
                    'backgroundColor' => 'rgba(102, 126, 234, 0.5)',
                    'borderColor' => '#667eea',
                    'borderWidth' => 2,
                    'type' => 'line',
                    'yAxisID' => 'y1',
                ],
            ],
            'insight' => $this->generateConversionInsight($data),
        ];
    }

    /**
     * 📈 Histograma de completude
     * Distribuição dos itens por faixa de completude
     * 
     * @return array
     */
    public function getCompletenessHistogram(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                FLOOR(s.completeness_percent / 10) * 10 as bucket,
                COUNT(*) as count
            FROM tech_sheet_item_summary s
            INNER JOIN items i ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE s.account_id = :account_id
              AND i.status = 'active'
            GROUP BY bucket
            ORDER BY bucket ASC
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Preencher buckets vazios
        $buckets = [];
        for ($i = 0; $i <= 100; $i += 10) {
            $buckets[$i] = 0;
        }
        foreach ($data as $row) {
            $bucket = (int)$row['bucket'];
            $buckets[$bucket] = (int)$row['count'];
        }
        
        $labels = [];
        $values = [];
        $colors = [];
        
        foreach ($buckets as $bucket => $count) {
            $labels[] = $bucket . '-' . min($bucket + 9, 100) . '%';
            $values[] = $count;
            
            // Cores graduais de vermelho a verde
            if ($bucket >= 80) {
                $colors[] = '#10b981'; // Verde
            } elseif ($bucket >= 60) {
                $colors[] = '#f59e0b'; // Amarelo
            } elseif ($bucket >= 40) {
                $colors[] = '#f97316'; // Laranja
            } else {
                $colors[] = '#ef4444'; // Vermelho
            }
        }
        
        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Quantidade de Itens',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderWidth' => 1,
                    'borderColor' => '#ffffff',
                ],
            ],
        ];
    }

    /**
     * 🏆 Top atributos faltantes (mais comuns)
     * 
     * @return array
     */
    public function getTopMissingAttributes(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(s.meta, '$.missing_details.required')) as required_json,
                JSON_UNQUOTE(JSON_EXTRACT(s.meta, '$.missing_details.filter')) as filter_json,
                JSON_UNQUOTE(JSON_EXTRACT(s.meta, '$.missing_details.hidden')) as hidden_json
            FROM tech_sheet_item_summary s
            INNER JOIN items i ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE s.account_id = :account_id
              AND i.status = 'active'
              AND s.meta IS NOT NULL
            LIMIT 500
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $attributeCounts = [];
        
        foreach ($rows as $row) {
            foreach (['required_json', 'filter_json', 'hidden_json'] as $field) {
                if (!empty($row[$field])) {
                    $attrs = json_decode($row[$field], true);
                    if (is_array($attrs)) {
                        foreach ($attrs as $attr) {
                            $attrId = is_array($attr) ? ($attr['id'] ?? $attr['attribute_id'] ?? '') : $attr;
                            if ($attrId) {
                                $attributeCounts[$attrId] = ($attributeCounts[$attrId] ?? 0) + 1;
                            }
                        }
                    }
                }
            }
        }
        
        arsort($attributeCounts);
        $top15 = array_slice($attributeCounts, 0, 15, true);
        
        return [
            'labels' => array_keys($top15),
            'datasets' => [
                [
                    'label' => 'Vezes Faltando',
                    'data' => array_values($top15),
                    'backgroundColor' => '#ef4444',
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    /**
     * 📅 Progresso semanal (últimas 4 semanas)
     * 
     * @return array
     */
    public function getWeeklyProgress(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                YEARWEEK(s.last_analyzed_at, 1) as year_week,
                DATE_FORMAT(MIN(s.last_analyzed_at), '%d/%m') as week_start,
                COUNT(DISTINCT s.item_id) as items_analyzed,
                AVG(s.completeness_percent) as avg_completeness,
                SUM(CASE WHEN s.completeness_percent >= 80 THEN 1 ELSE 0 END) as items_above_80
            FROM tech_sheet_item_summary s
            INNER JOIN items i ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE s.account_id = :account_id
              AND s.last_analyzed_at >= DATE_SUB(CURDATE(), INTERVAL 4 WEEK)
              AND i.status = 'active'
            GROUP BY YEARWEEK(s.last_analyzed_at, 1)
            ORDER BY year_week ASC
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'labels' => array_map(fn($row) => 'Sem. ' . $row['week_start'], $data),
            'datasets' => [
                [
                    'label' => 'Itens Analisados',
                    'data' => array_column($data, 'items_analyzed'),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Itens ≥80% Completos',
                    'data' => array_column($data, 'items_above_80'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'avg_completeness_trend' => array_map(
                fn($row) => round($row['avg_completeness'], 1),
                $data
            ),
        ];
    }

    /**
     * Gera insight sobre correlação vendas x completude
     */
    private function generateConversionInsight(array $data): string
    {
        if (empty($data)) {
            return 'Dados insuficientes para análise.';
        }

        $highComplete = null;
        $lowComplete = null;

        foreach ($data as $row) {
            if (strpos($row['completeness_range'], '90') === 0) {
                $highComplete = $row;
            }
            if (strpos($row['completeness_range'], '< 50') !== false) {
                $lowComplete = $row;
            }
        }

        if ($highComplete && $lowComplete) {
            $highAvg = (float)$highComplete['avg_sales_per_item'];
            $lowAvg = (float)$lowComplete['avg_sales_per_item'];
            
            if ($lowAvg > 0) {
                $improvement = (($highAvg - $lowAvg) / $lowAvg) * 100;
                if ($improvement > 0) {
                    return sprintf(
                        '💡 Itens com completude 90-100%% vendem em média %.0f%% mais que itens com <50%%!',
                        $improvement
                    );
                }
            }
        }

        return '📊 Continue melhorando a completude para potencializar suas vendas.';
    }

    // =========================================================================
    // 🎯 SMART FILL METRICS
    // =========================================================================

    /**
     * Dashboard do Smart Fill - Estatísticas gerais
     * 
     * @return array Métricas completas do sistema Smart Fill
     */
    public function getSmartFillDashboard(): array
    {
        return [
            'summary' => $this->getSmartFillSummary(),
            'by_source' => $this->getSmartFillBySource(),
            'trend' => $this->getSmartFillTrend(),
            'top_attributes' => $this->getSmartFillTopAttributes(),
            'success_rate' => $this->getSmartFillSuccessRate(),
        ];
    }

    /**
     * Resumo geral do Smart Fill
     */
    public function getSmartFillSummary(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_suggestions,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                AVG(confidence) as avg_confidence,
                COUNT(DISTINCT item_id) as items_with_suggestions,
                COUNT(DISTINCT attribute_id) as attributes_covered
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular gaps restantes
        $gapsStmt = $this->db->prepare("
            SELECT 
                SUM(missing_required) as total_required_gaps,
                SUM(missing_filter) as total_filter_gaps,
                SUM(missing_hidden) as total_hidden_gaps
            FROM tech_sheet_item_summary
            WHERE account_id = :account_id
        ");
        $gapsStmt->execute([':account_id' => $this->accountId]);
        $gaps = $gapsStmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_suggestions' => (int)($summary['total_suggestions'] ?? 0),
            'pending' => (int)($summary['pending'] ?? 0),
            'approved' => (int)($summary['approved'] ?? 0),
            'applied' => (int)($summary['applied'] ?? 0),
            'rejected' => (int)($summary['rejected'] ?? 0),
            'avg_confidence' => round((float)($summary['avg_confidence'] ?? 0), 1),
            'items_with_suggestions' => (int)($summary['items_with_suggestions'] ?? 0),
            'attributes_covered' => (int)($summary['attributes_covered'] ?? 0),
            'remaining_gaps' => [
                'required' => (int)($gaps['total_required_gaps'] ?? 0),
                'filter' => (int)($gaps['total_filter_gaps'] ?? 0),
                'hidden' => (int)($gaps['total_hidden_gaps'] ?? 0),
            ],
            'coverage_rate' => $this->calculateCoverageRate($summary, $gaps),
        ];
    }

    /**
     * Calcula taxa de cobertura de gaps
     */
    private function calculateCoverageRate(array $summary, array $gaps): float
    {
        $totalGaps = ($gaps['total_required_gaps'] ?? 0) 
                   + ($gaps['total_filter_gaps'] ?? 0);
        $covered = ($summary['approved'] ?? 0) + ($summary['applied'] ?? 0);
        
        if ($totalGaps <= 0) {
            return 100.0;
        }
        
        return min(100.0, round(($covered / $totalGaps) * 100, 1));
    }

    /**
     * Sugestões por fonte (para gráfico de pizza)
     */
    public function getSmartFillBySource(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                source,
                COUNT(*) as total,
                AVG(confidence) as avg_confidence,
                SUM(CASE WHEN status IN ('approved', 'applied') THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY source
            ORDER BY total DESC
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sourceLabels = [
            'title' => '📝 Título',
            'description' => '📄 Descrição',
            'benchmark' => '📊 Benchmark',
            'autocomplete' => '🔍 Autocomplete',
            'trends' => '📈 Tendências',
            'history' => '📦 Histórico',
            'ai' => '🤖 IA',
            'default' => '📋 Padrão',
        ];

        $sourceColors = [
            'title' => '#667eea',
            'description' => '#48bb78',
            'benchmark' => '#ed8936',
            'autocomplete' => '#38b2ac',
            'trends' => '#9f7aea',
            'history' => '#fc8181',
            'ai' => '#4fd1c5',
            'default' => '#a0aec0',
        ];

        $result = [];
        foreach ($data as $row) {
            $source = $row['source'];
            $total = (int)$row['total'];
            $accepted = (int)$row['accepted'];
            
            $result[] = [
                'source' => $source,
                'label' => $sourceLabels[$source] ?? ucfirst($source),
                'color' => $sourceColors[$source] ?? '#a0aec0',
                'total' => $total,
                'avg_confidence' => round((float)$row['avg_confidence'], 1),
                'accepted' => $accepted,
                'rejected' => (int)$row['rejected'],
                'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            ];
        }

        return [
            'data' => $result,
            'chart' => [
                'labels' => array_column($result, 'label'),
                'datasets' => [
                    [
                        'data' => array_column($result, 'total'),
                        'backgroundColor' => array_column($result, 'color'),
                        'borderWidth' => 2,
                        'borderColor' => '#ffffff',
                    ],
                ],
            ],
        ];
    }

    /**
     * Tendência do Smart Fill (últimos 14 dias)
     */
    public function getSmartFillTrend(int $days = 14): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as suggestions_created,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied,
                AVG(confidence) as avg_confidence
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $days,
        ]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'labels' => array_column($data, 'date'),
            'datasets' => [
                [
                    'label' => 'Sugestões Criadas',
                    'data' => array_column($data, 'suggestions_created'),
                    'borderColor' => '#667eea',
                    'backgroundColor' => 'rgba(102, 126, 234, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Aplicadas',
                    'data' => array_column($data, 'applied'),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    /**
     * Top 10 atributos mais preenchidos pelo Smart Fill
     */
    public function getSmartFillTopAttributes(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                attribute_id,
                attribute_name,
                COUNT(*) as total,
                AVG(confidence) as avg_confidence,
                SUM(CASE WHEN status = 'applied' THEN 1 ELSE 0 END) as applied
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY attribute_id, attribute_name
            ORDER BY total DESC
            LIMIT 10
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'labels' => array_map(fn($row) => $row['attribute_name'] ?: $row['attribute_id'], $data),
            'datasets' => [
                [
                    'label' => 'Total de Sugestões',
                    'data' => array_column($data, 'total'),
                    'backgroundColor' => '#667eea',
                ],
                [
                    'label' => 'Aplicadas',
                    'data' => array_column($data, 'applied'),
                    'backgroundColor' => '#10b981',
                ],
            ],
            'raw' => $data,
        ];
    }

    /**
     * Taxa de sucesso por nível de confiança
     */
    public function getSmartFillSuccessRate(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN confidence >= 90 THEN '90-100%'
                    WHEN confidence >= 80 THEN '80-89%'
                    WHEN confidence >= 70 THEN '70-79%'
                    WHEN confidence >= 60 THEN '60-69%'
                    ELSE '< 60%'
                END as confidence_range,
                COUNT(*) as total,
                SUM(CASE WHEN status IN ('approved', 'applied') THEN 1 ELSE 0 END) as accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            FROM tech_sheet_suggestions
            WHERE account_id = :account_id
            GROUP BY confidence_range
            ORDER BY 
                CASE confidence_range
                    WHEN '90-100%' THEN 1
                    WHEN '80-89%' THEN 2
                    WHEN '70-79%' THEN 3
                    WHEN '60-69%' THEN 4
                    ELSE 5
                END
        ");
        $stmt->execute([':account_id' => $this->accountId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($data as $row) {
            $total = (int)$row['total'];
            $accepted = (int)$row['accepted'];
            
            $result[] = [
                'range' => $row['confidence_range'],
                'total' => $total,
                'accepted' => $accepted,
                'rejected' => (int)$row['rejected'],
                'success_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            ];
        }

        return [
            'data' => $result,
            'chart' => [
                'labels' => array_column($result, 'range'),
                'datasets' => [
                    [
                        'label' => 'Taxa de Aceitação (%)',
                        'data' => array_column($result, 'success_rate'),
                        'backgroundColor' => [
                            '#10b981', // 90-100%
                            '#34d399', // 80-89%
                            '#fbbf24', // 70-79%
                            '#f59e0b', // 60-69%
                            '#ef4444', // < 60%
                        ],
                        'borderWidth' => 1,
                        'borderColor' => '#ffffff',
                    ],
                ],
            ],
            'insight' => $this->generateSmartFillInsight($result),
        ];
    }

    /**
     * Gera insight sobre performance do Smart Fill
     */
    private function generateSmartFillInsight(array $data): string
    {
        $highConfidence = null;
        $lowConfidence = null;

        foreach ($data as $row) {
            if ($row['range'] === '90-100%') {
                $highConfidence = $row;
            }
            if ($row['range'] === '< 60%') {
                $lowConfidence = $row;
            }
        }

        if ($highConfidence && $highConfidence['success_rate'] >= 80) {
            return sprintf(
                '🎯 Sugestões com confiança 90-100%% têm %.0f%% de aceitação! Configure auto-aprovação para este nível.',
                $highConfidence['success_rate']
            );
        }

        if ($lowConfidence && $lowConfidence['success_rate'] < 50) {
            return '⚠️ Sugestões com baixa confiança (<60%) têm baixa aceitação. Considere aumentar o limite mínimo.';
        }

        return '📊 O Smart Fill está funcionando bem. Continue revisando as sugestões pendentes.';
    }

    /**
     * Widget resumido para dashboard principal
     */
    public function getSmartFillWidget(): array
    {
        $summary = $this->getSmartFillSummary();
        $bySource = $this->getSmartFillBySource();

        // Top 3 fontes
        $topSources = array_slice($bySource['data'], 0, 3);

        return [
            'total_pending' => $summary['pending'],
            'total_applied' => $summary['applied'],
            'avg_confidence' => $summary['avg_confidence'],
            'coverage_rate' => $summary['coverage_rate'],
            'remaining_required_gaps' => $summary['remaining_gaps']['required'],
            'top_sources' => array_map(fn($s) => [
                'label' => $s['label'],
                'count' => $s['total'],
                'color' => $s['color'],
            ], $topSources),
            'action_needed' => $summary['pending'] > 10 
                ? "🔔 {$summary['pending']} sugestões aguardando revisão"
                : ($summary['remaining_gaps']['required'] > 0 
                    ? "🎯 Execute Smart Fill para cobrir {$summary['remaining_gaps']['required']} gaps obrigatórios"
                    : "✅ Ficha técnica em dia!"),
        ];
    }
}
