<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * ItemMetricsService - Métricas Detalhadas de Anúncios
 * 
 * Integração com Mercado Livre Metrics API
 * Endpoints: /items/{item_id}/visits, /items/{item_id}/health, /users/{user_id}/listings_quality
 * 
 * @link https://developers.mercadolivre.com.br/pt_br/metricas
 */
class ItemMetricsService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(?int $accountId = null)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Obtém visitas de um anúncio
     * 
     * @param string $itemId ID do anúncio
     * @param string $period Período: today, yesterday, week, month
     * @return array Dados de visitas
     */
    public function getItemVisits(string $itemId, string $period = 'week'): array
    {
        try {
            $endpoint = "/items/{$itemId}/visits";
            $params = ['date_from' => $this->getDateFrom($period)];

            $response = $this->get($endpoint, $params);

            if (isset($response['error'])) {
                return $this->getEmptyVisits();
            }

            return [
                'item_id' => $itemId,
                'period' => $period,
                'total_visits' => $response['total_visits'] ?? 0,
                'conversion_rate' => $this->calculateConversionRate($response),
                'visits_detail' => $response['results'] ?? [],
                'trends' => $this->analyzeVisitTrends($response['results'] ?? []),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao obter visitas do item', [
                'service' => 'ItemMetricsService',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return $this->getEmptyVisits();
        }
    }

    /**
     * Obtém saúde (health) de um anúncio
     * 
     * @param string $itemId ID do anúncio
     * @return array Dados de saúde do anúncio
     */
    public function getItemHealth(string $itemId): array
    {
        try {
            // Endpoint não documentado oficialmente, mas usado internamente pelo ML
            $response = $this->get("/items/{$itemId}/health");

            if (isset($response['error'])) {
                return $this->getEmptyHealth();
            }

            return [
                'item_id' => $itemId,
                'health_score' => $response['score'] ?? 0,
                'status' => $response['status'] ?? 'unknown',
                'issues' => $response['issues'] ?? [],
                'recommendations' => $response['recommendations'] ?? [],
                'details' => $this->formatHealthDetails($response),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao obter health do item', [
                'service' => 'ItemMetricsService',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return $this->getEmptyHealth();
        }
    }

    /**
     * Obtém qualidade geral dos anúncios do vendedor
     * 
     * @return array Qualidade das publicações
     */
    public function getListingsQuality(): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/users/{$userId}/listings_quality");

            if (isset($response['error'])) {
                return $this->getEmptyQuality();
            }

            return [
                'user_id' => $userId,
                'overall_score' => $response['score'] ?? 0,
                'total_listings' => $response['total_items'] ?? 0,
                'quality_distribution' => [
                    'excellent' => $response['excellent'] ?? 0,
                    'good' => $response['good'] ?? 0,
                    'regular' => $response['regular'] ?? 0,
                    'poor' => $response['poor'] ?? 0,
                ],
                'issues_summary' => $this->summarizeQualityIssues($response),
                'recommendations' => $this->generateQualityRecommendations($response),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao obter qualidade dos anúncios', [
                'service' => 'ItemMetricsService',
                'error' => $e->getMessage(),
            ]);
            return $this->getEmptyQuality();
        }
    }

    /**
     * Análise completa de métricas de um anúncio
     * 
     * @param string $itemId ID do anúncio
     * @return array Análise completa
     */
    public function analyzeItemPerformance(string $itemId): array
    {
        $visits = $this->getItemVisits($itemId, 'month');
        $health = $this->getItemHealth($itemId);
        $item = $this->get("/items/{$itemId}");

        // Calcular métricas adicionais
        $salesRate = $this->calculateSalesRate($item);
        $competitiveness = $this->analyzeCompetitiveness($item);
        $seoScore = $this->calculateSEOScore($item);

        return [
            'item_id' => $itemId,
            'title' => $item['title'] ?? 'N/A',
            'status' => $item['status'] ?? 'unknown',
            'performance_score' => $this->calculatePerformanceScore([
                'visits' => $visits,
                'health' => $health,
                'sales_rate' => $salesRate,
                'competitiveness' => $competitiveness,
                'seo_score' => $seoScore,
            ]),
            'visits_summary' => [
                'total' => $visits['total_visits'],
                'conversion_rate' => $visits['conversion_rate'],
                'trend' => $visits['trends']['trend'],
            ],
            'health_summary' => [
                'score' => $health['health_score'],
                'status' => $health['status'],
                'issues_count' => count($health['issues']),
            ],
            'sales_summary' => [
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'sales_rate' => $salesRate,
                'available_quantity' => $item['available_quantity'] ?? 0,
            ],
            'competitiveness' => $competitiveness,
            'seo_score' => $seoScore,
            'recommendations' => $this->generateItemRecommendations([
                'visits' => $visits,
                'health' => $health,
                'item' => $item,
            ]),
        ];
    }

    /**
     * Obtém métricas agregadas de múltiplos anúncios
     * 
     * @param array $itemIds IDs dos anúncios
     * @param string $period Período de análise
     * @return array Métricas agregadas
     */
    public function getBulkMetrics(array $itemIds, string $period = 'week'): array
    {
        $metrics = [
            'total_items' => count($itemIds),
            'total_visits' => 0,
            'total_sales' => 0,
            'average_conversion' => 0,
            'items' => [],
        ];

        foreach ($itemIds as $itemId) {
            try {
                $visits = $this->getItemVisits($itemId, $period);
                $item = $this->get("/items/{$itemId}");

                $itemMetric = [
                    'item_id' => $itemId,
                    'title' => $item['title'] ?? 'N/A',
                    'visits' => $visits['total_visits'],
                    'sold_quantity' => $item['sold_quantity'] ?? 0,
                    'conversion_rate' => $visits['conversion_rate'],
                ];

                $metrics['items'][] = $itemMetric;
                $metrics['total_visits'] += $visits['total_visits'];
                $metrics['total_sales'] += ($item['sold_quantity'] ?? 0);
            } catch (\Exception $e) {
                log_warning('Erro ao processar métricas do item', [
                    'service' => 'ItemMetricsService',
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $metrics['average_conversion'] = $metrics['total_items'] > 0
            ? array_sum(array_column($metrics['items'], 'conversion_rate')) / $metrics['total_items']
            : 0;

        return $metrics;
    }

    /**
     * Salva snapshot de métricas no banco
     * 
     * @param string $itemId ID do anúncio
     * @return bool Sucesso
     */
    public function saveMetricsSnapshot(string $itemId): bool
    {
        try {
            $visits = $this->getItemVisits($itemId, 'today');
            $health = $this->getItemHealth($itemId);
            $item = $this->get("/items/{$itemId}");

            $stmt = $this->db->prepare("
                INSERT INTO item_metrics_history (
                    account_id, item_id, date, visits, sold_quantity,
                    conversion_rate, health_score, price, data, created_at
                ) VALUES (
                    :account_id, :item_id, CURDATE(), :visits, :sold_quantity,
                    :conversion_rate, :health_score, :price, :data, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    visits = VALUES(visits),
                    sold_quantity = VALUES(sold_quantity),
                    conversion_rate = VALUES(conversion_rate),
                    health_score = VALUES(health_score),
                    price = VALUES(price),
                    data = VALUES(data),
                    updated_at = NOW()
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'visits' => $visits['total_visits'],
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'conversion_rate' => $visits['conversion_rate'],
                'health_score' => $health['health_score'],
                'price' => $item['price'] ?? 0,
                'data' => json_encode([
                    'visits' => $visits,
                    'health' => $health,
                    'item' => $item,
                ]),
            ]);

            return true;
        } catch (\Exception $e) {
            log_error('Erro ao salvar snapshot de métricas', [
                'service' => 'ItemMetricsService',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Obtém histórico de métricas
     * 
     * @param string $itemId ID do anúncio
     * @param int $days Dias de histórico
     * @return array Histórico
     */
    public function getMetricsHistory(string $itemId, int $days = 30): array
    {
        try {
            $dateFrom = date('Y-m-d', strtotime("-{$days} days"));

            $stmt = $this->db->prepare("
                SELECT 
                    date, visits, sold_quantity, conversion_rate,
                    health_score, price
                FROM item_metrics_history
                WHERE account_id = :account_id
                AND item_id = :item_id
                AND date >= :date_from
                ORDER BY date ASC
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'date_from' => $dateFrom,
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            log_warning('Erro ao buscar histórico de métricas', [
                'service' => 'ItemMetricsService',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getDateFrom(string $period): string
    {
        switch ($period) {
            case 'today':
                return date('Y-m-d');
            case 'yesterday':
                return date('Y-m-d', strtotime('-1 day'));
            case 'week':
                return date('Y-m-d', strtotime('-7 days'));
            case 'month':
                return date('Y-m-d', strtotime('-30 days'));
            default:
                return date('Y-m-d', strtotime('-7 days'));
        }
    }

    private function calculateConversionRate(array $visitsData): float
    {
        $totalVisits = $visitsData['total_visits'] ?? 0;
        $totalSales = $visitsData['total_sales'] ?? 0;

        return $totalVisits > 0 ? round(($totalSales / $totalVisits) * 100, 2) : 0;
    }

    private function analyzeVisitTrends(array $visitsDetail): array
    {
        if (empty($visitsDetail)) {
            return ['trend' => 'stable', 'change_percent' => 0];
        }

        $recent = array_slice($visitsDetail, -7);
        $previous = array_slice($visitsDetail, -14, 7);

        $recentAvg = !empty($recent) ? array_sum(array_column($recent, 'total')) / count($recent) : 0;
        $previousAvg = !empty($previous) ? array_sum(array_column($previous, 'total')) / count($previous) : 0;

        $changePercent = $previousAvg > 0
            ? round((($recentAvg - $previousAvg) / $previousAvg) * 100, 2)
            : 0;

        $trend = 'stable';
        if ($changePercent > 10) $trend = 'up';
        if ($changePercent < -10) $trend = 'down';

        return [
            'trend' => $trend,
            'change_percent' => $changePercent,
            'recent_avg' => round($recentAvg, 0),
            'previous_avg' => round($previousAvg, 0),
        ];
    }

    private function formatHealthDetails(array $response): array
    {
        return [
            'title_quality' => $response['title_quality'] ?? 'unknown',
            'description_quality' => $response['description_quality'] ?? 'unknown',
            'images_quality' => $response['images_quality'] ?? 'unknown',
            'attributes_completeness' => $response['attributes_completeness'] ?? 0,
            'shipping_quality' => $response['shipping_quality'] ?? 'unknown',
        ];
    }

    private function summarizeQualityIssues(array $response): array
    {
        $issues = $response['common_issues'] ?? [];
        $summary = [];

        foreach ($issues as $issue) {
            $type = $issue['type'] ?? 'other';
            if (!isset($summary[$type])) {
                $summary[$type] = 0;
            }
            $summary[$type]++;
        }

        return $summary;
    }

    private function generateQualityRecommendations(array $response): array
    {
        $recommendations = [];
        $score = $response['score'] ?? 0;

        if ($score < 70) {
            $recommendations[] = 'Melhore títulos usando palavras-chave relevantes';
            $recommendations[] = 'Complete todos os atributos obrigatórios';
            $recommendations[] = 'Adicione mais imagens de alta qualidade';
        }

        return $recommendations;
    }

    private function calculateSalesRate(array $item): float
    {
        $soldQuantity = $item['sold_quantity'] ?? 0;
        $availableQuantity = $item['available_quantity'] ?? 0;
        $totalStock = $soldQuantity + $availableQuantity;

        return $totalStock > 0 ? round(($soldQuantity / $totalStock) * 100, 2) : 0;
    }

    private function analyzeCompetitiveness(array $item): array
    {
        // Análise simplificada
        $price = $item['price'] ?? 0;
        $soldQuantity = $item['sold_quantity'] ?? 0;
        $shipping = $item['shipping'] ?? [];

        $score = 50; // Base

        if ($shipping['free_shipping'] ?? false) $score += 20;
        if ($soldQuantity > 100) $score += 15;
        if ($soldQuantity > 1000) $score += 10;

        return [
            'score' => min(100, $score),
            'free_shipping' => $shipping['free_shipping'] ?? false,
            'sales_performance' => $soldQuantity > 100 ? 'high' : ($soldQuantity > 10 ? 'medium' : 'low'),
        ];
    }

    private function calculateSEOScore(array $item): int
    {
        $score = 0;
        $title = $item['title'] ?? '';
        $pictures = $item['pictures'] ?? [];
        $attributes = $item['attributes'] ?? [];

        // Título
        $titleLength = strlen($title);
        if ($titleLength >= 45 && $titleLength <= 60) $score += 20;
        elseif ($titleLength >= 30) $score += 10;

        // Imagens
        if (count($pictures) >= 6) $score += 25;
        elseif (count($pictures) >= 3) $score += 15;

        // Atributos
        if (count($attributes) >= 8) $score += 25;
        elseif (count($attributes) >= 5) $score += 15;

        // Frete grátis
        if ($item['shipping']['free_shipping'] ?? false) $score += 20;

        // Video
        if (!empty($item['video_id'])) $score += 10;

        return min(100, $score);
    }

    private function calculatePerformanceScore(array $data): int
    {
        $weights = [
            'visits' => 0.25,
            'health' => 0.25,
            'sales_rate' => 0.20,
            'competitiveness' => 0.15,
            'seo_score' => 0.15,
        ];

        $score = 0;
        $score += ($data['visits']['total_visits'] > 1000 ? 100 : ($data['visits']['total_visits'] / 10)) * $weights['visits'];
        $score += $data['health']['health_score'] * $weights['health'];
        $score += $data['sales_rate'] * $weights['sales_rate'];
        $score += $data['competitiveness']['score'] * $weights['competitiveness'];
        $score += $data['seo_score'] * $weights['seo_score'];

        return (int)min(100, $score);
    }

    private function generateItemRecommendations(array $data): array
    {
        $recommendations = [];

        // Análise de visitas
        if ($data['visits']['total_visits'] < 100) {
            $recommendations[] = 'Baixo número de visitas - considere melhorar SEO e palavras-chave';
        }

        // Análise de saúde
        if ($data['health']['health_score'] < 70) {
            $recommendations[] = 'Saúde do anúncio baixa - verifique e corrija os problemas identificados';
        }

        // Análise de conversão
        if ($data['visits']['conversion_rate'] < 2) {
            $recommendations[] = 'Taxa de conversão baixa - revise preço, imagens e descrição';
        }

        return $recommendations;
    }

    private function getEmptyVisits(): array
    {
        return [
            'item_id' => null,
            'period' => 'week',
            'total_visits' => 0,
            'conversion_rate' => 0,
            'visits_detail' => [],
            'trends' => ['trend' => 'stable', 'change_percent' => 0],
        ];
    }

    private function getEmptyHealth(): array
    {
        return [
            'item_id' => null,
            'health_score' => 0,
            'status' => 'unknown',
            'issues' => [],
            'recommendations' => [],
            'details' => [],
        ];
    }

    private function getEmptyQuality(): array
    {
        return [
            'user_id' => null,
            'overall_score' => 0,
            'total_listings' => 0,
            'quality_distribution' => [
                'excellent' => 0,
                'good' => 0,
                'regular' => 0,
                'poor' => 0,
            ],
            'issues_summary' => [],
            'recommendations' => [],
        ];
    }
    /**
     * Get distribution of health scores
     * 
     * @return array
     */
    public function getScoreDistribution(): array
    {
        try {
            // Get latest health score for each item
            $stmt = $this->db->query("
                SELECT 
                    SUM(CASE WHEN health_score < 50 THEN 1 ELSE 0 END) as critical,
                    SUM(CASE WHEN health_score BETWEEN 50 AND 70 THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN health_score > 70 THEN 1 ELSE 0 END) as low,
                    AVG(health_score) as avg_score,
                    COUNT(*) as total_scored
                FROM (
                    SELECT health_score 
                    FROM item_metrics_history 
                    WHERE date = CURDATE()
                    GROUP BY item_id
                ) as latest_scores
            ");
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'critical' => (int)($result['critical'] ?? 0),
                'medium' => (int)($result['medium'] ?? 0),
                'low' => (int)($result['low'] ?? 0),
                'avg_score' => round((float)($result['avg_score'] ?? 0)),
                'total_scored' => (int)($result['total_scored'] ?? 0)
            ];
        } catch (\Exception $e) {
            log_warning('Falha ao obter distribuição de scores', [
                'service' => 'ItemMetricsService',
                'error' => $e->getMessage(),
            ]);
            return [
                'critical' => 0, 'medium' => 0, 'low' => 0, 
                'avg_score' => 0, 'total_scored' => 0
            ];
        }
    }

    /**
     * Get items within a specific score range
     * 
     * @param int $minScore
     * @param int $maxScore
     * @param int $limit
     * @return array
     */
    public function getItemsByScore(int $minScore, int $maxScore, int $limit = 50): array
    {
        try {
            $limitSql = max(1, min(500, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT item_id 
                FROM item_metrics_history 
                WHERE date = CURDATE() 
                AND health_score BETWEEN ? AND ?
                LIMIT {$limitSql}
            ");
            
            $stmt->execute([$minScore, $maxScore]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
            
        } catch (\Exception $e) {
            log_warning('Falha ao buscar items por score', [
                'service' => 'ItemMetricsService',
                'min_score' => $minScore,
                'max_score' => $maxScore,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
