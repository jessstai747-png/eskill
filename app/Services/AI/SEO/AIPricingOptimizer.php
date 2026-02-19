<?php

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\MarginCalculatorService;
use PDO;

/**
 * AI Pricing Optimizer - Dynamic Pricing with Competitive Analysis
 * 
 * Otimização inteligente de preços usando análise competitiva e LLM:
 * - Análise de preços de concorrentes em tempo real (via API ML)
 * - Estimativa de demanda com heurísticas
 * - Cálculo de margem ótima
 * - Precificação dinâmica baseada em contexto
 * - Estratégias de pricing (penetração, skimming, competitiva)
 * - Integração com MarginCalculatorService para custos reais
 * 
 * Nota: Usa OpenAI para sugestões, mas não implementa ML próprio.
 * 
 * @package App\Services\AI\SEO
 * @version 2.1.0
 * @since 2025-12-31
 */
class AIPricingOptimizer
{
    private PDO $db;
    private int $accountId;
    private string $apiKey;
    private ?MarginCalculatorService $marginCalculator = null;

    // Margens padrão
    private const MIN_MARGIN = 0.10; // 10%
    private const IDEAL_MARGIN = 0.30; // 30%
    private const MAX_MARGIN = 0.50; // 50%

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->marginCalculator = new MarginCalculatorService($accountId);
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? '';

        if (empty($this->apiKey)) {
            // Manual .env parsing fallback
            $envPath = __DIR__ . '/../../../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode('=', $line, 2);
                        if (trim($name) === 'OPENAI_API_KEY') {
                            $this->apiKey = trim(trim($value), '"\'');
                            break;
                        }
                    }
                }
            }
        }
    }

    /**
     * Sugere preço ótimo baseado em múltiplos fatores
     * 
     * @param string $itemId ID do produto
     * @param array $options Opções de otimização
     * @return array Análise e sugestão de preço
     */
    public function suggestOptimalPrice(string $itemId, array $options = []): array
    {
        // Coletar dados necessários
        $currentPrice = $this->getCurrentPrice($itemId);

        // Usar custo real da tabela product_costs se disponível
        $cost = $options['cost'] ?? $this->getRealProductCost($itemId, $currentPrice);

        $competitors = $this->getCompetitorPrices($itemId);
        $demand = $this->estimateDemand($itemId);
        $historical = $this->getHistoricalPricing($itemId);

        // Calcular preços candidatos
        $strategies = $this->calculatePricingStrategies($cost, $competitors, $demand);

        // IA seleciona melhor estratégia
        $recommendation = $this->selectBestStrategy($strategies, [
            'current_price' => $currentPrice,
            'competitors' => $competitors,
            'demand' => $demand,
            'historical' => $historical,
            'goal' => $options['goal'] ?? 'balanced' // balanced, volume, profit
        ]);

        // Calcular margem real com MarginCalculatorService
        $marginAnalysis = $this->calculateRealMargin($recommendation['price'], $itemId, $cost);

        return [
            'item_id' => $itemId,
            'current_price' => $currentPrice,
            'suggested_price' => $recommendation['price'],
            'strategy' => $recommendation['strategy'],
            'expected_results' => $recommendation['expected'],
            'confidence' => $recommendation['confidence'],
            'all_strategies' => $strategies,
            'market_position' => $this->analyzeMarketPosition($currentPrice, $competitors),
            'reasoning' => $recommendation['reasoning'],
            'cost_data' => [
                'unit_cost' => $cost,
                'source' => $this->getCostSource($itemId),
                'margin_analysis' => $marginAnalysis
            ]
        ];
    }

    /**
     * Análise de elasticidade de preço
     * 
     * @param string $itemId ID do produto
     * @return array Análise de elasticidade
     */
    public function analyzePriceElasticity(string $itemId): array
    {
        $historical = $this->getHistoricalPricing($itemId);

        if (count($historical) < 5) {
            return [
                'error' => 'Insufficient historical data',
                'min_required' => 5,
                'available' => count($historical)
            ];
        }

        // Calcular elasticidade
        $elasticity = $this->calculateElasticity($historical);
        $interpretation = $this->interpretElasticity($elasticity);
        $recommendations = $this->generateElasticityRecommendations($elasticity);

        // Simular cenários
        $scenarios = $this->simulatePriceScenarios($itemId, $elasticity);

        return [
            'item_id' => $itemId,
            'elasticity_coefficient' => $elasticity,
            'interpretation' => $interpretation,
            // Campos extras para alinhar com o front
            'explanation' => $interpretation,
            'price_sensitivity' => $this->categorizeSensitivity($elasticity),
            'scenarios' => $scenarios,
            'recommendations' => $recommendations,
            'recommendation' => $recommendations[0] ?? ''
        ];
    }

    /**
     * Otimização de margem considerando volume
     * 
     * @param float $cost Custo do produto
     * @param array $constraints Restrições (margem mín, preço máx, etc)
     * @return array Análise margem vs volume
     */
    public function optimizeMargin(float $cost, array $constraints = []): array
    {
        $minMargin = $constraints['min_margin'] ?? self::MIN_MARGIN;
        $maxPrice = $constraints['max_price'] ?? $cost * 3;

        // Calcular pontos de equilíbrio em diferentes margens
        $marginPoints = [];

        for ($margin = $minMargin; $margin <= self::MAX_MARGIN; $margin += 0.05) {
            $price = $cost / (1 - $margin);

            if ($price > $maxPrice) break;

            $estimatedVolume = $this->estimateVolumeAtPrice($price);
            $revenue = $price * $estimatedVolume;
            $profit = ($price - $cost) * $estimatedVolume;

            $marginPoints[] = [
                'margin_percentage' => round($margin * 100, 1),
                'price' => round($price, 2),
                'estimated_volume' => $estimatedVolume,
                'revenue' => round($revenue, 2),
                'profit' => round($profit, 2),
                'roi' => round(($profit / ($cost * $estimatedVolume)) * 100, 1)
            ];
        }

        // Encontrar ponto ótimo
        $optimal = $this->findOptimalPoint($marginPoints);

        return [
            'cost' => $cost,
            'margin_analysis' => $marginPoints,
            'optimal_point' => $optimal,
            'recommendation' => $this->explainOptimalPoint($optimal),
            'break_even' => $this->calculateBreakEven($cost, $minMargin)
        ];
    }

    /**
     * Estratégia de pricing dinâmico
     * 
     * @param string $itemId ID do produto
     * @param array $rules Regras de precificação
     * @return array Regras e automação
     */
    public function createDynamicPricingRules(string $itemId, array $rules): array
    {
        $basePrice = $this->getCurrentPrice($itemId);

        // Validar e processar regras
        $processedRules = [];

        foreach ($rules as $rule) {
            $processedRules[] = [
                'id' => uniqid('rule_'),
                'condition' => $rule['condition'],
                'action' => $rule['action'],
                'value' => $rule['value'],
                'active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        // Salvar regras
        $this->savePricingRules($itemId, $processedRules);

        // Simular primeira aplicação
        $context = $this->buildRuleContext($itemId);
        $simulation = $this->simulateRuleApplication($basePrice, $processedRules, $context);

        return [
            'item_id' => $itemId,
            'base_price' => $basePrice,
            'rules' => $processedRules,
            'total_rules' => count($processedRules),
            'simulation' => $simulation,
            'next_evaluation' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ];
    }

    /**
     * Análise competitiva de preços
     * 
     * @param string $itemId ID do produto
     * @return array Posicionamento competitivo
     */
    public function analyzeCompetitivePricing(string $itemId): array
    {
        $currentPrice = $this->getCurrentPrice($itemId);
        $competitors = $this->getCompetitorPrices($itemId);

        if (empty($competitors)) {
            return [
                'error' => 'No competitor data available',
                'item_id' => $itemId
            ];
        }

        $prices = array_column($competitors, 'price');
        $stats = $this->calculatePriceStatistics($prices);

        $position = $this->determinePosition($currentPrice, $stats);
        $opportunities = $this->identifyPricingOpportunities($currentPrice, $competitors, $stats);

        // Resumo simples de competidores para o front (min/médio/máx)
        $competitorSummary = [
            'min' => $stats['min'],
            'avg' => $stats['avg'],
            'max' => $stats['max'],
            'count' => count($competitors),
        ];

        // Converter oportunidades em textos legíveis
        $opportunityMessages = [];
        foreach ($opportunities as $o) {
            $message = $o['message'] ?? '';
            if (!empty($o['action'])) {
                $message .= $message ? ' - ' . $o['action'] : $o['action'];
            }
            if ($message !== '') {
                $opportunityMessages[] = $message;
            }
        }

        return [
            'item_id' => $itemId,
            'your_price' => $currentPrice,
            'market_stats' => $stats,
            'competitors' => $competitorSummary,
            'position' => $position,
            'percentile' => $this->calculatePercentile($currentPrice, $prices),
            'opportunities' => $opportunityMessages,
            'opportunities_raw' => $opportunities,
            'competitor_count' => count($competitors),
            'price_distribution' => $this->groupPriceRanges($prices)
        ];
    }

    /**
     * Previsão de receita em diferentes cenários
     * 
     * @param string $itemId ID do produto
     * @param array $pricePoints Preços a testar
     * @return array Previsões de receita
     */
    public function forecastRevenue(string $itemId, array $pricePoints): array
    {
        $forecasts = [];
        $currentPrice = $this->getCurrentPrice($itemId);

        foreach ($pricePoints as $price) {
            $volume = $this->estimateVolumeAtPrice($price);
            $revenue = $price * $volume;

            $forecasts[] = [
                'price' => $price,
                'estimated_volume' => $volume,
                'estimated_revenue' => round($revenue, 2),
                'confidence' => $this->calculateForecastConfidence($price, $currentPrice),
                'timeframe' => '30_days'
            ];
        }

        // Identificar melhor cenário
        $best = null;
        foreach ($forecasts as $scenario) {
            if ($best === null || $scenario['estimated_revenue'] > $best['estimated_revenue']) {
                $best = $scenario;
            }
        }

        $gain = $this->calculatePotentialGain($best, $currentPrice);

        return [
            'item_id' => $itemId,
            // "scenarios" para alinhar com o front
            'scenarios' => $forecasts,
            // manter "forecasts" para compatibilidade
            'forecasts' => $forecasts,
            'best_scenario' => $best,
            'current_price' => $currentPrice,
            'potential_gain' => $gain['absolute'],
            'potential_gain_detail' => $gain
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getCurrentPrice(string $itemId): float
    {
        // 1) Tabela ml_items
        try {
            $stmt = $this->db->prepare("SELECT price FROM ml_items WHERE id = ? AND account_id = ? LIMIT 1");
            $stmt->execute([$itemId, $this->accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['price'] !== null) {
                return (float)$row['price'];
            }
        } catch (\Throwable $e) {
            log_debug('getCurrentPrice: ml_items lookup failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }

        // 2) Tabela items (legado)
        try {
            $stmt = $this->db->prepare("SELECT price FROM items WHERE account_id = ? AND ml_item_id = ? LIMIT 1");
            $stmt->execute([$this->accountId, $itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['price'] !== null) {
                return (float)$row['price'];
            }
        } catch (\Throwable $e) {
            log_debug('getCurrentPrice: items lookup failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }

        // 3) Mercado Livre API
        try {
            $client = new MercadoLivreClient($this->accountId);
            $item = $client->get("/items/{$itemId}");
            if (!empty($item['price'])) {
                return (float)$item['price'];
            }
        } catch (\Throwable $e) {
            log_debug('getCurrentPrice: ML API lookup failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }

        // 4) price_history média categoria/marca
        try {
            $stmt = $this->db->prepare("SELECT category_id, JSON_EXTRACT(data, '$.attributes.brand') as brand FROM items WHERE ml_item_id = ? LIMIT 1");
            $stmt->execute([$itemId]);
            $meta = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($meta && !empty($meta['category_id']) && !empty($meta['brand'])) {
                $stmt2 = $this->db->prepare("
                    SELECT avg_price
                    FROM price_history
                    WHERE category_id = ? AND brand = ?
                    ORDER BY recorded_at DESC
                    LIMIT 1
                ");
                $stmt2->execute([$meta['category_id'], $meta['brand']]);
                $hist = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($hist && $hist['avg_price'] !== null) {
                    return (float)$hist['avg_price'];
                }
            }
        } catch (\Throwable $e) {
            log_debug('getCurrentPrice: price_history lookup failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }

        return 0.0;
    }

    private function getCompetitorPrices(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                cw.competitor_item_id,
                cw.competitor_price as price,
                cw.competitor_sales as sales,
                cw.free_shipping
            FROM competitor_watchlist cw
            WHERE cw.account_id = ?
              AND cw.competitor_price IS NOT NULL
            ORDER BY cw.competitor_sales DESC
            LIMIT 20
        ");
        $stmt->execute([$this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function estimateDemand(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                AVG(views_increase) as avg_views,
                AVG(sales_increase) as avg_sales,
                COUNT(*) as data_points
            FROM seo_optimizations
            WHERE account_id = ?
            AND item_id = ?
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$this->accountId, $itemId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $currentAvg = (float)($data['avg_sales'] ?? 0);
        $prevStmt = $this->db->prepare("
            SELECT AVG(sales_increase) as avg_sales
            FROM seo_optimizations
            WHERE account_id = ?
              AND item_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
              AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $prevStmt->execute([$this->accountId, $itemId]);
        $prevData = $prevStmt->fetch(PDO::FETCH_ASSOC);
        $previousAvg = (float)($prevData['avg_sales'] ?? 0);

        $trend = 'stable';
        if ($previousAvg > 0) {
            $ratio = $currentAvg / $previousAvg;
            if ($ratio >= 1.1) {
                $trend = 'up';
            } elseif ($ratio <= 0.9) {
                $trend = 'down';
            }
        }

        $seasonality = $previousAvg > 0 ? round($currentAvg / $previousAvg, 2) : 1.0;

        return [
            'level' => $this->categorizeDemand($data['avg_sales'] ?? 0),
            'trend' => $trend,
            'seasonality_factor' => $seasonality
        ];
    }

    private function getHistoricalPricing(string $itemId): array
    {
        $history = [];

        // 1) Histórico diário real do item (item_metrics_history)
        try {
            $stmt = $this->db->prepare("
                SELECT date, price, sold_quantity
                FROM item_metrics_history
                WHERE account_id = :account_id AND item_id = :item_id
                ORDER BY date ASC
                LIMIT 90
            ");
            $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $history[] = [
                    'date' => $row['date'],
                    'price' => (float) $row['price'],
                    'sales' => (int) ($row['sold_quantity'] ?? 0),
                ];
            }
        } catch (\Throwable $e) {
            log_debug('getHistoricalPricing: item_metrics_history failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
        }

        // 2) Complemento agregado categoria/marca se poucos pontos
        if (count($history) < 5) {
            try {
                $stmt = $this->db->prepare("SELECT category_id, JSON_EXTRACT(data, '$.attributes.brand') as brand FROM items WHERE ml_item_id = ? LIMIT 1");
                $stmt->execute([$itemId]);
                $meta = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($meta && !empty($meta['category_id']) && !empty($meta['brand'])) {
                    $stmt2 = $this->db->prepare("
                        SELECT avg_price, min_price, max_price, total_items, recorded_at
                        FROM price_history
                        WHERE category_id = ? AND brand = ?
                        ORDER BY recorded_at ASC
                        LIMIT 60
                    ");
                    $stmt2->execute([$meta['category_id'], $meta['brand']]);
                    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($rows as $row) {
                        $history[] = [
                            'date' => $row['recorded_at'],
                            'price' => (float) $row['avg_price'],
                            'sales' => (int) ($row['total_items'] ?? 0),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                log_debug('getHistoricalPricing: price_history fallback failed', ['item_id' => $itemId, 'error' => $e->getMessage()]);
            }
        }

        return $history;
    }

    private function calculatePricingStrategies(float $cost, array $competitors, array $demand): array
    {
        $strategies = [];

        // 1. Penetração (preço baixo)
        $competitorPrices = array_column($competitors, 'price');
        $minCompetitor = !empty($competitorPrices) ? min($competitorPrices) : $cost * 1.5;

        $strategies['penetration'] = [
            'name' => 'Market Penetration',
            'price' => round($minCompetitor * 0.95, 2),
            'margin' => (($minCompetitor * 0.95) - $cost) / ($minCompetitor * 0.95),
            'rationale' => 'Undercut competitors to gain market share'
        ];

        // 2. Competitiva (preço médio)
        $avgCompetitor = !empty($competitorPrices) ? array_sum($competitorPrices) / count($competitorPrices) : $cost * 1.8;

        $strategies['competitive'] = [
            'name' => 'Competitive Pricing',
            'price' => round($avgCompetitor, 2),
            'margin' => ($avgCompetitor - $cost) / $avgCompetitor,
            'rationale' => 'Match market average for balanced positioning'
        ];

        // 3. Premium (preço alto)
        $maxCompetitor = !empty($competitorPrices) ? max($competitorPrices) : $cost * 2.2;

        $strategies['premium'] = [
            'name' => 'Premium Pricing',
            'price' => round($maxCompetitor * 1.05, 2),
            'margin' => (($maxCompetitor * 1.05) - $cost) / ($maxCompetitor * 1.05),
            'rationale' => 'Position as premium product with higher margin'
        ];

        // 4. Margem ideal
        $idealPrice = $cost / (1 - self::IDEAL_MARGIN);

        $strategies['margin_based'] = [
            'name' => 'Margin-Based',
            'price' => round($idealPrice, 2),
            'margin' => self::IDEAL_MARGIN,
            'rationale' => '30% margin ensures healthy profitability'
        ];

        return $strategies;
    }

    private function selectBestStrategy(array $strategies, array $context): array
    {
        $goal = $context['goal'];
        $currentPrice = $context['current_price'];

        switch ($goal) {
            case 'volume':
                $selected = $strategies['penetration'];
                break;
            case 'profit':
                $selected = $strategies['premium'];
                break;
            case 'balanced':
            default:
                $selected = $strategies['competitive'];
        }

        return [
            'strategy' => $selected['name'],
            'price' => $selected['price'],
            'confidence' => 0.85,
            'expected' => [
                'volume_change' => $this->estimateVolumeChange($currentPrice, $selected['price']),
                'revenue_change' => $this->estimateRevenueChange($currentPrice, $selected['price']),
                'margin' => round($selected['margin'] * 100, 1) . '%'
            ],
            'reasoning' => $selected['rationale']
        ];
    }

    private function analyzeMarketPosition(float $price, array $competitors): array
    {
        if (empty($competitors)) {
            return ['position' => 'unknown', 'message' => 'No competitor data'];
        }

        $prices = array_column($competitors, 'price');
        $min = min($prices);
        $max = max($prices);
        $avg = array_sum($prices) / count($prices);

        if ($price <= $min) {
            $position = 'lowest';
        } elseif ($price >= $max) {
            $position = 'highest';
        } elseif ($price < $avg) {
            $position = 'below_average';
        } elseif ($price > $avg) {
            $position = 'above_average';
        } else {
            $position = 'average';
        }

        return [
            'position' => $position,
            'vs_min' => round((($price - $min) / $min) * 100, 1),
            'vs_avg' => round((($price - $avg) / $avg) * 100, 1),
            'vs_max' => round((($price - $max) / $max) * 100, 1)
        ];
    }

    private function calculateElasticity(array $historical): float
    {
        // Elasticidade = % mudança em quantidade / % mudança em preço
        if (count($historical) < 2) return 0;

        $changes = [];
        for ($i = 1; $i < count($historical); $i++) {
            $priceChange = ($historical[$i]['price'] - $historical[$i - 1]['price']) / $historical[$i - 1]['price'];
            $qtyChange = ($historical[$i]['sales'] - $historical[$i - 1]['sales']) / $historical[$i - 1]['sales'];

            if ($priceChange != 0) {
                $changes[] = $qtyChange / $priceChange;
            }
        }

        return empty($changes) ? -1.0 : array_sum($changes) / count($changes);
    }

    private function interpretElasticity(float $elasticity): string
    {
        $abs = abs($elasticity);

        if ($abs > 1.5) return 'Highly elastic - Small price changes cause large volume changes';
        if ($abs > 1.0) return 'Elastic - Price sensitive product';
        if ($abs > 0.5) return 'Moderately elastic - Some price sensitivity';
        return 'Inelastic - Price changes have minimal impact on volume';
    }

    private function categorizeSensitivity(float $elasticity): string
    {
        $abs = abs($elasticity);

        if ($abs > 1.5) return 'very_high';
        if ($abs > 1.0) return 'high';
        if ($abs > 0.5) return 'moderate';
        return 'low';
    }

    private function simulatePriceScenarios(string $itemId, float $elasticity): array
    {
        $currentPrice = $this->getCurrentPrice($itemId);
        $scenarios = [];

        foreach ([-0.20, -0.10, 0, 0.10, 0.20] as $change) {
            $newPrice = $currentPrice * (1 + $change);
            $volumeChange = -$elasticity * $change; // Elasticidade é geralmente negativa
            $netEffect = $this->calculateNetEffect($change, $volumeChange);

            $scenarios[] = [
                'price_change' => round($change * 100, 0) . '%',
                'new_price' => round($newPrice, 2),
                'expected_volume_change' => round($volumeChange * 100, 1) . '%',
                'net_effect' => $netEffect,
                'net_revenue_effect' => $netEffect
            ];
        }

        return $scenarios;
    }

    private function generateElasticityRecommendations(float $elasticity): array
    {
        $recommendations = [];

        if (abs($elasticity) > 1.0) {
            $recommendations[] = 'Product is price-sensitive. Consider competing on price.';
            $recommendations[] = 'Small discounts can significantly increase volume.';
        } else {
            $recommendations[] = 'Product is not very price-sensitive. Focus on value proposition.';
            $recommendations[] = 'You have room to increase prices without losing much volume.';
        }

        return $recommendations;
    }

    /**
     * Estima volume de vendas em função do preço usando dados históricos reais
     * Fallback: modelo de elasticidade simplificado quando não há dados
     */
    private function estimateVolumeAtPrice(float $price): int
    {
        if ($price <= 0) {
            return 0;
        }

        try {
            // Buscar dados históricos de vendas em faixa de preço similar (±20%)
            $priceMin = $price * 0.80;
            $priceMax = $price * 1.20;

            $stmt = $this->db->prepare("
                SELECT
                    AVG(oi.quantity) AS avg_qty,
                    COUNT(*) AS data_points,
                    AVG(oi.unit_price) AS avg_price
                FROM ml_order_items oi
                JOIN ml_orders o ON (o.id = oi.order_id OR o.ml_order_id = oi.order_id)
                WHERE oi.unit_price BETWEEN :price_min AND :price_max
                  AND o.ml_account_id = :account_id
                  AND o.date_created >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                  AND o.status IN ('paid', 'delivered')
            ");
            $stmt->execute([
                'price_min' => $priceMin,
                'price_max' => $priceMax,
                'account_id' => $this->accountId,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && (int)$row['data_points'] >= 5) {
                $avgQty = (float)$row['avg_qty'];
                $avgHistPrice = (float)$row['avg_price'];

                // Aplicar elasticidade: se preço proposto < média histórica → mais volume
                if ($avgHistPrice > 0) {
                    $priceRatio = $avgHistPrice / $price;
                    // Elasticidade de demanda ~1.5 (típica e-commerce BR)
                    $elasticityFactor = pow($priceRatio, 1.5);
                    return max(1, (int) round($avgQty * $elasticityFactor));
                }

                return max(1, (int) round($avgQty));
            }
        } catch (\Exception $e) {
            // Tabela pode não existir — cair no fallback
        }

        // Fallback: baseline dinâmico da conta + elasticidade inversa moderada
        $baseVolume = 30.0;
        $referencePrice = $price;

        try {
            $stmt = $this->db->prepare("\n                SELECT AVG(sales_increase) as avg_sales\n                FROM seo_optimizations\n                WHERE account_id = :account_id\n                  AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)\n            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $avgSales = (float) ($stmt->fetchColumn() ?: 0);
            if ($avgSales > 0) {
                $baseVolume = max(1.0, $avgSales);
            }
        } catch (\Exception $e) {
            // mantém baseline padrão
        }

        try {
            $stmt = $this->db->prepare("\n                SELECT AVG(price) as avg_price\n                FROM items\n                WHERE account_id = :account_id\n                  AND status = 'active'\n                  AND price > 0\n            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $avgPrice = (float) ($stmt->fetchColumn() ?: 0);
            if ($avgPrice > 0) {
                $referencePrice = $avgPrice;
            }
        } catch (\Exception $e) {
            // mantém referência no preço informado
        }

        $priceFactor = $referencePrice > 0 ? ($referencePrice / $price) : 1.0;
        $elasticityFactor = pow($priceFactor, 1.2);
        $elasticityFactor = min(5.0, max(0.2, $elasticityFactor));

        return max(1, (int) round($baseVolume * $elasticityFactor));
    }

    private function findOptimalPoint(array $points): array
    {
        // Máximo lucro
        usort($points, fn($a, $b) => $b['profit'] <=> $a['profit']);
        return $points[0];
    }

    private function explainOptimalPoint(array $point): string
    {
        return "Optimal margin of {$point['margin_percentage']}% at price R$ {$point['price']} " .
            "generates estimated profit of R$ {$point['profit']} with ROI of {$point['roi']}%";
    }

    private function calculateBreakEven(float $cost, float $minMargin): array
    {
        $breakEvenPrice = $cost / (1 - $minMargin);
        $breakEvenVolume = $cost / ($breakEvenPrice - $cost);

        return [
            'price' => round($breakEvenPrice, 2),
            'volume' => ceil($breakEvenVolume),
            'margin' => round($minMargin * 100, 1) . '%'
        ];
    }

    private function savePricingRules(string $itemId, array $rules): void
    {
        // Salvar no banco
        $stmt = $this->db->prepare("
            INSERT INTO pricing_rules (account_id, item_id, rules, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE rules = VALUES(rules), updated_at = NOW()
        ");
        $stmt->execute([$this->accountId, $itemId, json_encode($rules)]);
    }

    private function simulateRuleApplication(float $basePrice, array $rules, array $context = []): array
    {
        $finalPrice = $basePrice;
        $appliedRules = [];

        foreach ($rules as $rule) {
            if ($this->evaluateCondition($rule['condition'], $context)) {
                $finalPrice = $this->applyPriceAction($finalPrice, $rule['action'], $rule['value']);
                $appliedRules[] = $rule['id'];
            }
        }

        return [
            'base_price' => $basePrice,
            'final_price' => round($finalPrice, 2),
            'applied_rules' => $appliedRules,
            'change_percentage' => round((($finalPrice - $basePrice) / $basePrice) * 100, 2)
        ];
    }

    private function evaluateCondition(string $condition, array $context = []): bool
    {
        $parts = preg_split('/\s+/', trim($condition));
        if (count($parts) < 3) {
            return false;
        }

        [$left, $operator, $right] = [$parts[0], $parts[1], implode(' ', array_slice($parts, 2))];
        $leftValue = $context[$left] ?? null;
        $rightValue = is_numeric($right) ? (float)$right : ($context[$right] ?? null);

        if ($leftValue === null || $rightValue === null) {
            return false;
        }

        return match ($operator) {
            '>' => $leftValue > $rightValue,
            '>=' => $leftValue >= $rightValue,
            '<' => $leftValue < $rightValue,
            '<=' => $leftValue <= $rightValue,
            '==' => $leftValue == $rightValue,
            '!=' => $leftValue != $rightValue,
            default => false
        };
    }

    private function buildRuleContext(string $itemId): array
    {
        $currentPrice = $this->getCurrentPrice($itemId);
        $competitors = $this->getCompetitorPrices($itemId);
        $avgCompetitor = 0;
        if (!empty($competitors)) {
            $prices = array_column($competitors, 'price');
            $avgCompetitor = array_sum($prices) / count($prices);
        }
        $demand = $this->estimateDemand($itemId);
        $demandLevel = match ($demand['level'] ?? 'medium') {
            'high' => 0.8,
            'low' => 0.2,
            default => 0.5
        };

        $stmt = $this->db->prepare("SELECT available_quantity FROM items WHERE ml_item_id = ?");
        $stmt->execute([$itemId]);
        $available = (int)($stmt->fetchColumn() ?: 0);

        return [
            'price' => $currentPrice,
            'competitor_avg' => $avgCompetitor,
            'demand_level' => $demandLevel,
            'stock' => $available
        ];
    }

    private function applyPriceAction(float $price, string $action, float $value): float
    {
        return match ($action) {
            'increase_percentage' => $price * (1 + $value),
            'decrease_percentage' => $price * (1 - $value),
            'set_fixed' => $value,
            default => $price
        };
    }

    private function calculatePriceStatistics(array $prices): array
    {
        sort($prices);
        $count = count($prices);

        return [
            'min' => min($prices),
            'max' => max($prices),
            'avg' => round(array_sum($prices) / $count, 2),
            'median' => $count % 2 == 0
                ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
                : $prices[floor($count / 2)],
            'stddev' => $this->calculateStdDev($prices)
        ];
    }

    private function calculateStdDev(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        return round(sqrt($variance), 2);
    }

    private function determinePosition(float $price, array $stats): string
    {
        if ($price <= $stats['min'] * 1.05) return 'lowest';
        if ($price >= $stats['max'] * 0.95) return 'highest';
        if ($price < $stats['avg']) return 'below_average';
        if ($price > $stats['avg']) return 'above_average';
        return 'average';
    }

    private function calculatePercentile(float $price, array $prices): float
    {
        sort($prices);
        $count = count($prices);
        $belowCount = count(array_filter($prices, fn($p) => $p < $price));

        return round(($belowCount / $count) * 100, 1);
    }

    private function identifyPricingOpportunities(float $price, array $competitors, array $stats): array
    {
        $opportunities = [];

        if ($price > $stats['max']) {
            $opportunities[] = [
                'type' => 'overpriced',
                'message' => 'Your price is higher than all competitors',
                'action' => 'Consider reducing to ' . round($stats['max'] * 0.95, 2)
            ];
        }

        if ($price < $stats['avg'] && $price > $stats['min']) {
            $opportunities[] = [
                'type' => 'increase_potential',
                'message' => 'You can increase price closer to average',
                'action' => 'Test price at ' . round($stats['avg'], 2)
            ];
        }

        return $opportunities;
    }

    private function groupPriceRanges(array $prices): array
    {
        $min = min($prices);
        $max = max($prices);
        $range = ($max - $min) / 3;

        return [
            'low' => count(array_filter($prices, fn($p) => $p < $min + $range)),
            'medium' => count(array_filter($prices, fn($p) => $p >= $min + $range && $p < $min + 2 * $range)),
            'high' => count(array_filter($prices, fn($p) => $p >= $min + 2 * $range))
        ];
    }

    private function calculateForecastConfidence(float $price, float $currentPrice): float
    {
        if ($currentPrice <= 0) {
            return 60;
        }

        $diff = abs($price - $currentPrice) / $currentPrice;
        if ($diff < 0.10) return 95;
        if ($diff < 0.20) return 85;
        if ($diff < 0.30) return 75;
        return 60;
    }

    private function calculatePotentialGain(array $best, float $currentPrice): array
    {
        $currentRevenue = $currentPrice * $this->estimateVolumeAtPrice($currentPrice);
        $gain = $best['estimated_revenue'] - $currentRevenue;

        return [
            'absolute' => round($gain, 2),
            'percentage' => $currentRevenue > 0 ? round(($gain / $currentRevenue) * 100, 1) : 0.0
        ];
    }

    private function estimateVolumeChange(float $from, float $to): string
    {
        $change = (($to - $from) / $from) * -1.2;
        return round($change * 100, 1) . '%';
    }

    private function estimateRevenueChange(float $from, float $to): string
    {
        $priceChange = ($to - $from) / $from;
        $volumeChange = $priceChange * -0.8; // Menor que elasticidade
        $revenueChange = (1 + $priceChange) * (1 + $volumeChange) - 1;

        return round($revenueChange * 100, 1) . '%';
    }

    private function calculateNetEffect(float $priceChange, float $volumeChange): string
    {
        $netEffect = (1 + $priceChange) * (1 + $volumeChange) - 1;
        return round($netEffect * 100, 1) . '%';
    }

    private function categorizeDemand(float $sales): string
    {
        if ($sales > 50) return 'high';
        if ($sales > 20) return 'medium';
        return 'low';
    }

    // ==================== INTEGRAÇÃO COM MarginCalculatorService ====================

    /**
     * Busca custo real do produto da tabela product_costs
     * Fallback para estimativa baseada no preço se não houver dados
     * 
     * @param string $itemId ID do produto
     * @param float $currentPrice Preço atual para estimativa de fallback
     * @return float Custo do produto
     */
    private function getRealProductCost(string $itemId, float $currentPrice): float
    {
        try {
            // Buscar na tabela product_costs
            $stmt = $this->db->prepare("
                SELECT custo_producao, custo_embalagem, custo_etiqueta, custo_frete_entrada
                FROM product_costs
                WHERE account_id = ? AND item_id = ?
                LIMIT 1
            ");
            $stmt->execute([$this->accountId, $itemId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                // Somar todos os custos fixos como custo total do produto
                $totalCost = (float)($row['custo_producao'] ?? 0)
                    + (float)($row['custo_embalagem'] ?? 0)
                    + (float)($row['custo_etiqueta'] ?? 0)
                    + (float)($row['custo_frete_entrada'] ?? 0);

                if ($totalCost > 0) {
                    return $totalCost;
                }
            }

            // Fallback: buscar da tabela ml_items (campo cost)
            $stmt2 = $this->db->prepare("
                SELECT cost FROM ml_items WHERE id = ? AND account_id = ? LIMIT 1
            ");
            $stmt2->execute([$itemId, $this->accountId]);
            $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

            if ($row2 && (float)$row2['cost'] > 0) {
                return (float)$row2['cost'];
            }
        } catch (\Throwable $e) {
            // Log silencioso
        }

        // Estimativa padrão: 70% do preço de venda
        return $currentPrice * 0.7;
    }

    /**
     * Identifica a fonte do custo (real ou estimado)
     * 
     * @param string $itemId ID do produto
     * @return string Fonte do custo
     */
    private function getCostSource(string $itemId): string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 1 FROM product_costs WHERE account_id = ? AND item_id = ? LIMIT 1
            ");
            $stmt->execute([$this->accountId, $itemId]);

            if ($stmt->fetch()) {
                return 'product_costs_table';
            }

            $stmt2 = $this->db->prepare("
                SELECT cost FROM ml_items WHERE id = ? AND account_id = ? AND cost > 0 LIMIT 1
            ");
            $stmt2->execute([$itemId, $this->accountId]);

            if ($stmt2->fetch()) {
                return 'ml_items_table';
            }
        } catch (\Throwable $e) {
            // Silencioso
        }

        return 'estimated';
    }

    /**
     * Calcula margem real usando MarginCalculatorService
     * 
     * @param float $price Preço de venda
     * @param string $itemId ID do produto
     * @param float $cost Custo do produto
     * @return array Análise de margem completa
     */
    private function calculateRealMargin(float $price, string $itemId, float $cost): array
    {
        try {
            // Buscar dados completos de custos
            $stmt = $this->db->prepare("
                SELECT * FROM product_costs WHERE account_id = ? AND item_id = ? LIMIT 1
            ");
            $stmt->execute([$this->accountId, $itemId]);
            $costData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($costData) {
                // Usar dados reais de custo
                $custos = [
                    'custo_producao' => (float)($costData['custo_producao'] ?? 0),
                    'custo_embalagem' => (float)($costData['custo_embalagem'] ?? 0),
                    'custo_etiqueta' => (float)($costData['custo_etiqueta'] ?? 0),
                    'custo_frete_entrada' => (float)($costData['custo_frete_entrada'] ?? 0),
                    'custo_frete_gratis' => (float)($costData['custo_frete_gratis'] ?? 0),
                    'taxa_comissao_ml' => (float)($costData['taxa_comissao_ml'] ?? 16),
                    'taxa_imposto' => (float)($costData['taxa_imposto'] ?? 9),
                    'acos_medio' => (float)($costData['acos_medio'] ?? 0)
                ];

                return $this->marginCalculator->calcularMargem($price, $custos);
            }

            // Custos estimados se não houver dados reais
            $custos = [
                'custo_producao' => $cost,
                'custo_embalagem' => 0,
                'custo_etiqueta' => 0,
                'custo_frete_entrada' => 0,
                'custo_frete_gratis' => 0,
                'taxa_comissao_ml' => 16, // ML padrão
                'taxa_imposto' => 9,      // Simples padrão
                'acos_medio' => 0
            ];

            return $this->marginCalculator->calcularMargem($price, $custos);
        } catch (\Throwable $e) {
            // Cálculo simplificado como fallback
            $mlFee = $price * 0.16;
            $tax = $price * 0.09;
            $profit = $price - $cost - $mlFee - $tax;
            $margin = $price > 0 ? ($profit / $price) * 100 : 0;

            return [
                'success' => true,
                'preco_venda' => round($price, 2),
                'lucro_unitario' => round($profit, 2),
                'margem_real' => round($margin, 2),
                'indicador' => $margin >= 20 ? 'saudavel' : ($margin >= 10 ? 'atencao' : 'critico'),
                'source' => 'fallback_calculation'
            ];
        }
    }

    /**
     * Obtém análise de margem completa usando MarginCalculatorService
     * Método público para integração com outros serviços
     * 
     * @param string $itemId ID do produto
     * @param float|null $price Preço a analisar (usa atual se null)
     * @return array Análise completa de margem
     */
    public function getMarginAnalysis(string $itemId, ?float $price = null): array
    {
        $currentPrice = $price ?? $this->getCurrentPrice($itemId);
        $cost = $this->getRealProductCost($itemId, $currentPrice);

        return [
            'item_id' => $itemId,
            'price' => $currentPrice,
            'cost' => $cost,
            'cost_source' => $this->getCostSource($itemId),
            'margin_breakdown' => $this->calculateRealMargin($currentPrice, $itemId, $cost)
        ];
    }
}
