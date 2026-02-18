<?php
declare(strict_types=1);

namespace App\Services\Shipping;

use App\Services\MercadoLivreClient;
use App\Services\Shipping\ShippingSimulatorService;
use App\Services\Shipping\DimensionCalculatorService;

/**
 * Shipping Optimizer Service - Otimiza estratégia de envio
 * 
 * Analisa anúncios e recomenda a melhor estratégia de envio para:
 * - Maximizar conversão
 * - Reduzir custos
 * - Melhorar ranking nas buscas
 * - Aumentar competitividade
 * 
 * Considera fatores como:
 * - Produto (dimensões, peso, categoria, preço)
 * - Concorrência (o que outros vendem na categoria)
 * - Localização do vendedor
 * - Margem de lucro
 */
class ShippingOptimizerService
{
    private MercadoLivreClient $client;
    private ShippingSimulatorService $simulator;
    private DimensionCalculatorService $dimensionCalculator;

    // Pesos para decisão
    private const DECISION_WEIGHTS = [
        'conversion_impact' => 0.40,  // Impacto em conversão
        'cost_benefit' => 0.30,        // Custo x Benefício
        'ranking_boost' => 0.20,       // Melhoria de ranking
        'feasibility' => 0.10,         // Viabilidade
    ];

    public function __construct(?int $accountId = null)
    {
        $this->client = new MercadoLivreClient($accountId);
        $this->simulator = new ShippingSimulatorService($accountId);
        $this->dimensionCalculator = new DimensionCalculatorService();
    }

    /**
     * Otimiza estratégia de envio para um item
     */
    public function optimizeShipping(string $itemId, array $options = []): array
    {
        try {
            $item = $this->client->get("/items/{$itemId}");

            if (isset($item['error'])) {
                return [
                    'success' => false,
                    'error' => 'Item não encontrado',
                ];
            }

            // Análise atual
            $currentShipping = $this->analyzeCurrentShipping($item);

            // Simulação de alternativas
            $simulation = $this->simulator->simulateForItem($itemId, $options);

            // Análise de concorrência
            $competition = $this->analyzeCompetition($item['category_id'] ?? null, $item['price'] ?? 0);

            // Gerar recomendação otimizada
            $recommendation = $this->generateOptimizedRecommendation(
                $item,
                $currentShipping,
                $simulation,
                $competition,
                $options
            );

            return [
                'success' => true,
                'item_id' => $itemId,
                'title' => $item['title'] ?? '',
                'timestamp' => date('Y-m-d H:i:s'),
                'current_shipping' => $currentShipping,
                'simulation' => $simulation,
                'competition_analysis' => $competition,
                'recommendation' => $recommendation,
                'action_plan' => $this->generateActionPlan($currentShipping, $recommendation),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Analisa configuração atual de envio
     */
    private function analyzeCurrentShipping(array $item): array
    {
        $shipping = $item['shipping'] ?? [];
        $mode = $shipping['mode'] ?? 'not_specified';
        $logisticType = $shipping['logistic_type'] ?? 'default';
        $freeShipping = $shipping['free_shipping'] ?? false;

        $score = $this->calculateShippingScore($shipping);

        return [
            'mode' => $mode,
            'mode_label' => ShippingSimulatorService::SHIPPING_MODES[$mode] ?? $mode,
            'logistic_type' => $logisticType,
            'logistic_type_label' => ShippingSimulatorService::LOGISTIC_TYPES[$logisticType] ?? $logisticType,
            'free_shipping' => $freeShipping,
            'tags' => $shipping['tags'] ?? [],
            'dimensions' => $shipping['dimensions'] ?? null,
            'score' => $score,
            'score_label' => $this->getScoreLabel($score),
            'issues' => $this->identifyShippingIssues($shipping, $item),
        ];
    }

    /**
     * Calcula score da configuração atual (0-100)
     */
    private function calculateShippingScore(array $shipping): int
    {
        $score = 0;

        $mode = $shipping['mode'] ?? 'not_specified';
        $logisticType = $shipping['logistic_type'] ?? 'default';
        $freeShipping = $shipping['free_shipping'] ?? false;

        // Score por modo de envio
        $modeScores = [
            'not_specified' => 0,
            'custom' => 30,
            'me1' => 50,
'me2' => 70,
        ];
        $score += $modeScores[$mode] ?? 0;

        // Bonus por logistic type
        if ($logisticType === 'fulfillment') {
            $score = 100; // Full sempre tem score máximo
        } else if ($logisticType === 'flex') {
            $score = 95;
        } else if ($logisticType === 'xd_drop_off' || $logisticType === 'cross_docking') {
            $score += 10;
        }

        // Bonus por frete grátis
        if ($freeShipping && $score < 100) {
            $score += 20;
        }

        return min(100, max(0, $score));
    }

    /**
     * Label do score
     */
    private function getScoreLabel(int $score): string
    {
        if ($score >= 90) return 'Excelente';
        if ($score >= 70) return 'Muito Bom';
        if ($score >= 50) return 'Bom';
        if ($score >= 30) return 'Regular';
        return 'Ruim';
    }

    /**
     * Identifica problemas na configuração atual
     */
    private function identifyShippingIssues(array $shipping, array $item): array
    {
        $issues = [];

        $mode = $shipping['mode'] ?? 'not_specified';
        $logisticType = $shipping['logistic_type'] ?? 'default';
        $freeShipping = $shipping['free_shipping'] ?? false;

        // Modo não especificado
        if ($mode === 'not_specified') {
            $issues[] = [
                'severity' => 'critical',
                'issue' => 'Modo de envio não configurado',
                'impact' => 'Anúncio pode ter visibilidade zero',
                'solution' => 'Configure o modo de envio',
            ];
        }

        // Sem frete grátis
        if (!$freeShipping && $logisticType !== 'fulfillment' && $logisticType !== 'flex') {
            $issues[] = [
                'severity' => 'high',
                'issue' => 'Sem frete grátis',
                'impact' => 'Conversão até 40% menor',
                'solution' => 'Ativar frete grátis ou migrar para Full/Flex',
            ];
        }

        // Custom shipping (pior opção)
        if ($mode === 'custom') {
            $issues[] = [
                'severity' => 'medium',
                'issue' => 'Usando envio customizado',
                'impact' => 'Menor visibilidade e confiança',
                'solution' => 'Migrar para Mercado Envios',
            ];
        }

        // Sem dimensões configuradas
        if (empty($shipping['dimensions'])) {
            $issues[] = [
                'severity' => 'medium',
                'issue' => 'Dimensões não configuradas',
                'impact' => 'Custo de frete pode ser impreciso',
                'solution' => 'Configurar dimensões do produto',
            ];
        }

        return $issues;
    }

    /**
     * Analisa concorrência na categoria
     */
    private function analyzeCompetition(?string $categoryId, float $price): array
    {
        if (!$categoryId) {
            return [
                'available' => false,
                'reason' => 'Categoria não especificada',
            ];
        }

        try {
            // Buscar top anúncios da categoria
            $search = $this->client->get("/sites/MLB/search", [
                'category' => $categoryId,
                'sort' => 'relevance',
                'limit' => 50,
            ]);

            if (isset($search['error']) || empty($search['results'])) {
                return [
                    'available' => false,
                    'reason' => 'Não foi possível analisar concorrência',
                ];
            }

            $competitors = $search['results'];
            $shippingStats = $this->analyzeCompetitorsShipping($competitors);

            return [
                'available' => true,
                'total_analyzed' => count($competitors),
                'statistics' => $shippingStats,
                'insights' => $this->generateCompetitionInsights($shippingStats),
            ];

        } catch (\Exception $e) {
            return [
                'available' => false,
                'reason' => 'Erro ao analisar concorrência: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Analisa shipping dos concorrentes
     */
    private function analyzeCompetitorsShipping(array $competitors): array
    {
        $stats = [
            'free_shipping' => 0,
            'full' => 0,
            'flex' => 0,
            'me2' => 0,
            'custom' => 0,
            'not_specified' => 0,
        ];

        foreach ($competitors as $competitor) {
            $shipping = $competitor['shipping'] ?? [];
            
            if ($shipping['free_shipping'] ?? false) {
                $stats['free_shipping']++;
            }

            $logisticType = $shipping['logistic_type'] ?? 'default';
            $mode = $shipping['mode'] ?? 'not_specified';

            if ($logisticType === 'fulfillment') {
                $stats['full']++;
            } else if ($logisticType === 'flex') {
                $stats['flex']++;
            } else if ($mode === 'me2') {
                $stats['me2']++;
            } else if ($mode === 'custom') {
                $stats['custom']++;
            } else {
                $stats['not_specified']++;
            }
        }

        $total = count($competitors);

        return [
            'counts' => $stats,
            'percentages' => [
                'free_shipping' => round(($stats['free_shipping'] / $total) * 100, 1),
                'full' => round(($stats['full'] / $total) * 100, 1),
                'flex' => round(($stats['flex'] / $total) * 100, 1),
                'me2' => round(($stats['me2'] / $total) * 100, 1),
                'custom' => round(($stats['custom'] / $total) * 100, 1),
            ],
        ];
    }

    /**
     * Gera insights da concorrência
     */
    private function generateCompetitionInsights(array $stats): array
    {
        $insights = [];
        $percentages = $stats['percentages'];

        // Frete grátis
        if ($percentages['free_shipping'] >= 80) {
            $insights[] = [
                'type' => 'critical',
                'insight' => 'Frete grátis é padrão na categoria',
                'percentage' => $percentages['free_shipping'],
                'recommendation' => 'Obrigatório ter frete grátis para competir',
            ];
        } else if ($percentages['free_shipping'] >= 50) {
            $insights[] = [
                'type' => 'high',
                'insight' => 'Maioria dos concorrentes oferece frete grátis',
                'percentage' => $percentages['free_shipping'],
                'recommendation' => 'Fortemente recomendado ter frete grátis',
            ];
        }

        // Full
        if ($percentages['full'] >= 30) {
            $insights[] = [
                'type' => 'high',
                'insight' => 'Muitos concorrentes usam Full',
                'percentage' => $percentages['full'],
                'recommendation' => 'Considere migrar para Full para competir melhor',
            ];
        }

        // Flex
        if ($percentages['flex'] >= 20) {
            $insights[] = [
                'type' => 'medium',
                'insight' => 'Flex é comum na categoria',
                'percentage' => $percentages['flex'],
                'recommendation' => 'Avalie elegibilidade para Flex',
            ];
        }

        return $insights;
    }

    /**
     * Gera recomendação otimizada
     */
    private function generateOptimizedRecommendation(
        array $item,
        array $currentShipping,
        array $simulation,
        array $competition,
        array $options
    ): array {
        $price = $item['price'] ?? 0;
        $categoryId = $item['category_id'] ?? null;
        $sellerMargin = $options['target_margin'] ?? 0.30; // 30% de margem desejada

        // Obter recomendação do simulator
        $simulatorRec = $simulation['recommendation']['best'] ?? null;

        // Calcular viabilidade financeira
        $financialAnalysis = $this->analyzeFinancialViability(
            $price,
            $simulation['estimated_costs'] ?? [],
            $sellerMargin
        );

        // Considerar concorrência
        $competitionWeight = $competition['available'] ? 0.3 : 0;

        // Calcular score para cada opção
        $options = [];
        foreach ($simulation['estimated_costs'] ?? [] as $mode => $cost) {
            $score = $this->calculateOptionScore(
                $mode,
                $cost,
                $financialAnalysis[$mode] ?? [],
                $competition,
                $currentShipping
            );

            $options[] = [
                'mode' => $mode,
                'score' => $score,
                'cost_analysis' => $cost,
                'financial_analysis' => $financialAnalysis[$mode] ?? null,
            ];
        }

        // Ordenar por score
        usort($options, fn($a, $b) => $b['score'] <=> $a['score']);

        $bestOption = $options[0] ?? null;

        return [
            'recommended_mode' => $bestOption['mode'] ?? null,
            'confidence_score' => $bestOption['score'] ?? 0,
            'estimated_conversion_increase' => $this->estimateConversionIncrease(
                $currentShipping,
                $bestOption
            ),
            'estimated_cost_impact' => $this->estimateCostImpact(
                $price,
                $bestOption
            ),
            'all_options' => $options,
            'financial_viability' => $financialAnalysis,
            'next_steps' => $this->generateNextSteps($currentShipping, $bestOption),
        ];
    }

    /**
     * Analisa viabilidade financeira
     */
    private function analyzeFinancialViability(float $price, array $costs, float $targetMargin): array
    {
        $analysis = [];

        foreach ($costs as $mode => $cost) {
            $shippingCost = $cost['seller_cost'] ?? $cost['cost'];
            $storageCost = $cost['storage_cost'] ?? 0;
            $totalMonthlyCost = $storageCost;
            
            // Margem considerando frete
            $netPrice = $price - $shippingCost;
            $actualMargin = ($netPrice / $price);
            
            $viable = $actualMargin >= $targetMargin;

            $analysis[$mode] = [
                'shipping_cost' => $shippingCost,
                'storage_cost_monthly' => $storageCost,
                'net_price_per_sale' => round($netPrice, 2),
                'actual_margin' => round($actualMargin * 100, 1) . '%',
                'target_margin' => round($targetMargin * 100, 1) . '%',
                'viable' => $viable,
                'margin_difference' => round(($actualMargin - $targetMargin) * 100, 1) . '%',
            ];
        }

        return $analysis;
    }

    /**
     * Calcula score de uma opção
     */
    private function calculateOptionScore(
        string $mode,
        array $cost,
        array $financial,
        array $competition,
        array $current
    ): float {
        $score = 0;

        // Fator 1: Impacto em conversão (40%)
        $conversionScores = [
            'full' => 100,
            'flex' => 90,
            'me2' => 70,
            'custom' => 30,
        ];
        $score += ($conversionScores[$mode] ?? 0) * 0.40;

        // Fator 2: Custo x Benefício (30%)
        if ($financial && $financial['viable']) {
            $score += 100 * 0.30;
        } else if (!empty($financial)) {
            $marginDiff = (float)str_replace('%', '', $financial['margin_difference']);
            $costBenefitScore = max(0, 50 + ($marginDiff * 5));
            $score += $costBenefitScore * 0.30;
        }

        // Fator 3: Melhoria de ranking (20%)
        $rankingScores = [
            'full' => 100,
            'flex' => 90,
            'me2' => 60,
            'custom' => 20,
        ];
        $score += ($rankingScores[$mode] ?? 0) * 0.20;

        // Fator 4: Viabilidade técnica (10%)
        $available = $cost['available'] ?? true;
        $score += ($available ? 100 : 0) * 0.10;

        // Bonus se já é o modo atual (evitar mudanças desnecessárias)
        if ($current['mode'] === $mode) {
            $score *= 0.95; // Pequena penalidade para incentivar upgrade
        }

        return round($score, 1);
    }

    /**
     * Estima aumento de conversão
     */
    private function estimateConversionIncrease(array $current, ?array $recommended): string
    {
        if (!$recommended) return '+0%';

        $currentMode = $current['mode'];
        $recommendedMode = $recommended['mode'];

        $increases = [
            'not_specified' => [
                'full' => '+50%',
                'flex' => '+45%',
                'me2' => '+30%',
                'custom' => '+10%',
            ],
            'custom' => [
                'full' => '+40%',
                'flex' => '+35%',
                'me2' => '+20%',
            ],
            'me2' => [
                'full' => '+20%',
                'flex' => '+15%',
            ],
        ];

        return $increases[$currentMode][$recommendedMode] ?? '+0%';
    }

    /**
     * Estima impacto no custo
     */
    private function estimateCostImpact(float $price, ?array $recommended): array
    {
        if (!$recommended) {
            return ['impact' => 'N/A'];
        }

        $financial = $recommended['financial_analysis'] ?? [];

        return [
            'shipping_cost_per_sale' => $financial['shipping_cost'] ?? 0,
            'monthly_storage' => $financial['storage_cost_monthly'] ?? 0,
            'net_revenue_per_sale' => $financial['net_price_per_sale'] ?? $price,
            'margin' => $financial['actual_margin'] ?? 'N/A',
        ];
    }

    /**
     * Gera próximos passos
     */
    private function generateNextSteps(array $current, ?array $recommended): array
    {
        if (!$recommended) {
            return [];
        }

        $currentMode = $current['mode'];
        $recommendedMode = $recommended['mode'];

        if ($currentMode === $recommendedMode) {
            return [[
                'step' => 'Manter configuração atual',
                'description' => 'Sua configuração de envio já está otimizada',
                'priority' => 'low',
            ]];
        }

        $steps = [];

        // Steps específicos por modo recomendado
        switch ($recommendedMode) {
            case 'full':
                $steps = [
                    [
                        'step' => '1. Verificar elegibilidade Full',
                        'description' => 'Confirme se seu produto atende aos requisitos',
                        'priority' => 'high',
                        'link' => 'https://www.mercadolivre.com.br/oficial/full',
                    ],
                    [
                        'step' => '2. Calcular estoque inicial',
                        'description' => 'Defina quanto estoque enviará ao ML',
                        'priority' => 'high',
                    ],
                    [
                        'step' => '3. Criar pedido de envio Full',
                        'description' => 'Inicie o processo de onboarding Full',
                        'priority' => 'high',
                        'action' => 'create_full_shipment',
                    ],
                ];
                break;

            case 'flex':
                $steps = [
                    [
                        'step' => '1. Verificar elegibilidade Flex',
                        'description' => 'Confirme localização e requisitos',
                        'priority' => 'high',
                    ],
                    [
                        'step' => '2. Ativar Flex no anúncio',
                        'description' => 'Altere configuração de envio para Flex',
                        'priority' => 'high',
                        'action' => 'enable_flex',
                    ],
                ];
                break;

            case 'me2':
                $steps = [
                    [
                        'step' => '1. Configurar Mercado Envios',
                        'description' => 'Ative ME2 nas configurações do anúncio',
                        'priority' => 'high',
                        'action' => 'enable_me2',
                    ],
                    [
                        'step' => '2. Ativar frete grátis',
                        'description' => 'Se o preço permitir, ative frete grátis',
                        'priority' => 'medium',
                        'action' => 'enable_free_shipping',
                    ],
                ];
                break;
        }

        return $steps;
    }

    /**
     * Gera plano de ação
     */
    private function generateActionPlan(array $current, array $recommendation): array
    {
        $plan = [
            'current_situation' => [
                'mode' => $current['mode'],
                'score' => $current['score'],
                'issues_count' => count($current['issues']),
            ],
            'target_situation' => [
                'mode' => $recommendation['recommended_mode'] ?? null,
                'expected_score' => 90,
                'expected_conversion_increase' => $recommendation['estimated_conversion_increase'] ?? '+0%',
            ],
            'actions' => $recommendation['next_steps'] ?? [],
            'timeline' => $this->estimateImplementationTimeline($recommendation['recommended_mode'] ?? null),
        ];

        return $plan;
    }

    /**
     * Estima timeline de implementação
     */
    private function estimateImplementationTimeline(?string $mode): array
    {
        $timelines = [
            'full' => [
                'estimated_days' => '7-15 dias',
                'complexity' => 'Alta',
                'description' => 'Requer onboarding e envio de estoque',
            ],
            'flex' => [
                'estimated_days' => '1-3 dias',
                'complexity' => 'Baixa',
                'description' => 'Ativação simples se elegível',
            ],
            'me2' => [
                'estimated_days' => '1 dia',
                'complexity' => 'Muito Baixa',
                'description' => 'Apenas configurar no anúncio',
            ],
            'custom' => [
                'estimated_days' => '< 1 dia',
                'complexity' => 'Muito Baixa',
                'description' => 'Configuração imediata',
            ],
        ];

        return $timelines[$mode] ?? [];
    }

    /**
     * Otimiza múltiplos itens em lote
     */
    public function optimizeBatch(array $itemIds, array $options = []): array
    {
        $results = [];
        $summary = [
            'total' => count($itemIds),
            'processed' => 0,
            'errors' => 0,
            'recommendations_by_mode' => [],
        ];

        foreach ($itemIds as $itemId) {
            $result = $this->optimizeShipping($itemId, $options);
            $results[$itemId] = $result;

            if ($result['success']) {
                $summary['processed']++;
                $mode = $result['recommendation']['recommended_mode'] ?? 'unknown';
                $summary['recommendations_by_mode'][$mode] = 
                    ($summary['recommendations_by_mode'][$mode] ?? 0) + 1;
            } else {
                $summary['errors']++;
            }
        }

        return [
            'success' => true,
            'summary' => $summary,
            'results' => $results,
        ];
    }
}
