<?php

declare(strict_types=1);

namespace App\Services;

class AIPredictiveAnalyticsService
{
    // =========================================================================
    // PUBLIC API
    // =========================================================================

    public function predictProductPerformance(array $product, array $options = []): array
    {
        $historical = $options['historical'] ?? ['sales_vector' => [], 'data_points' => 0, 'quality' => 'medium', 'velocity' => 0];
        $stage = $this->identifyCurrentStage($product, $historical);
        $transitions = $this->predictStageTransitions($stage);
        $strategies = $this->getLifecycleStrategies($stage, $transitions);
        $confidence = $this->calculateStageConfidence($stage, $historical);
        $timeline = $this->generateLifecycleTimeline($transitions);
        $risks = $this->identifyLifecycleRisks($stage, $historical);

        return [
            'stage'       => $stage,
            'transitions' => $transitions,
            'strategies'  => $strategies,
            'confidence'  => $confidence,
            'timeline'    => $timeline,
            'risks'       => $risks,
        ];
    }

    public function predictMarketDemand(array $category, array $options = []): array
    {
        $context = $this->getMarketContext($category);
        $factors = $this->getExternalFactors($context);
        return ['context' => $context, 'factors' => $factors];
    }

    public function predictOptimalPricing(array $product, array $options = []): array
    {
        $context = $this->getMarketContext($product);
        $factors = $this->identifyInfluenceFactors($product, $context);
        return ['context' => $context, 'factors' => $factors];
    }

    // =========================================================================
    // LIFECYCLE
    // =========================================================================

    private function identifyCurrentStage(array $product, array $historical): string
    {
        $salesVector = $historical['sales_vector'] ?? [];
        $dataPoints  = $historical['data_points'] ?? count($salesVector);

        if (empty($salesVector) || $dataPoints < 14) {
            return 'introduction';
        }

        $recent    = array_slice($salesVector, -7);
        $earlier   = array_slice($salesVector, 0, count($salesVector) - 7);
        $recentSum = (float) array_sum($recent);
        $earlierSum = (float) array_sum($earlier);

        if ($recentSum === 0.0 && $earlierSum > 0.0) {
            return 'decline';
        }

        $recentAvg  = count($recent) > 0 ? $recentSum / count($recent) : 0.0;
        $earlierAvg = count($earlier) > 0 ? $earlierSum / count($earlier) : 0.0;

        if ($earlierAvg > 0.0 && ($recentAvg / $earlierAvg) > 1.5) {
            return 'growth';
        }

        if ($recentAvg > 0.0 && ($earlierAvg / $recentAvg) > 1.5) {
            return 'decline';
        }

        return 'maturity';
    }

    private function predictStageTransitions(string $stage): array
    {
        $map = [
            'introduction' => ['next_stage' => 'growth',      'timeline' => '1-3 months',  'probability' => 0.70],
            'growth'       => ['next_stage' => 'maturity',    'timeline' => '3-6 months',  'probability' => 0.65],
            'maturity'     => ['next_stage' => 'decline',     'timeline' => '6-12 months', 'probability' => 0.50],
            'decline'      => ['next_stage' => 'end_of_life', 'timeline' => '3-6 months',  'probability' => 0.60],
        ];
        return $map[$stage] ?? ['next_stage' => 'unknown', 'timeline' => 'unknown', 'probability' => 0.0];
    }

    private function getLifecycleStrategies(string $stage, array $transitions): array
    {
        $strategies = [
            'introduction' => [
                'focus'       => 'visibility',
                'pricing'     => 'competitive_entry',
                'advertising' => 'awareness',
                'seo'         => 'broad_keywords',
                'inventory'   => 'moderate_stock',
                'prepare_for' => 'growth',
            ],
            'growth' => [
                'focus'       => 'market_expansion',
                'pricing'     => 'value_based',
                'advertising' => 'conversion',
                'seo'         => 'long_tail_keywords',
                'inventory'   => 'aggressive_stock',
                'prepare_for' => 'maturity',
            ],
            'maturity' => [
                'focus'       => 'profit_optimization',
                'pricing'     => 'premium_or_value',
                'advertising' => 'retention',
                'seo'         => 'brand_keywords',
                'inventory'   => 'lean_stock',
                'prepare_for' => 'decline',
            ],
            'decline' => [
                'focus'       => 'harvest_or_revitalize',
                'pricing'     => 'discount_driven',
                'advertising' => 'minimal',
                'seo'         => 'long_tail_niche',
                'inventory'   => 'minimal_stock',
                'prepare_for' => 'end_of_life',
            ],
        ];
        return $strategies[$stage] ?? $strategies['decline'];
    }

    private function calculateStageConfidence(string $stage, array $historical): float
    {
        $qualityBase = ['high' => 0.80, 'medium' => 0.65, 'low' => 0.45];
        $base        = $qualityBase[$historical['quality'] ?? 'medium'] ?? 0.65;

        $dataPoints = (int) ($historical['data_points'] ?? 0);
        $dataBonus  = $dataPoints >= 90 ? 0.10 : ($dataPoints >= 30 ? 0.05 : 0.0);

        $stageAdj = ['maturity' => 0.05, 'introduction' => -0.05];
        $adj      = $stageAdj[$stage] ?? 0.0;

        return min(0.95, max(0.30, $base + $dataBonus + $adj));
    }

    private function generateLifecycleTimeline(array $transitions): array
    {
        $successors = [
            'introduction' => 'growth',
            'growth'       => 'maturity',
            'maturity'     => 'decline',
        ];

        $nextStage = (string) ($transitions['next_stage'] ?? '');
        $prob      = (float)  ($transitions['probability'] ?? 0.0);
        $timeline  = (string) ($transitions['timeline']    ?? '');

        $phases = [
            ['stage' => $nextStage, 'timeline' => $timeline, 'probability' => $prob],
        ];

        if (isset($successors[$nextStage])) {
            $phases[] = [
                'stage'       => $successors[$nextStage],
                'timeline'    => '6-12 months',
                'probability' => round($prob * 0.6, 2),
            ];
        }

        return ['phases' => $phases, 'generated_at' => date('Y-m-d')];
    }

    private function identifyLifecycleRisks(string $stage, array $historical): array
    {
        $baseRisks = [
            'introduction' => [
                ['risk' => 'low_awareness',    'description' => 'Produto pouco conhecido no mercado',      'mitigation' => 'Investir em anúncios de awareness'],
                ['risk' => 'slow_adoption',    'description' => 'Adoção lenta pelo público-alvo',          'mitigation' => 'Oferecer promoções de entrada e reviews'],
            ],
            'growth' => [
                ['risk' => 'competitor_entry', 'description' => 'Concorrentes entrando no mercado',         'mitigation' => 'Fortalecer diferenciação e brand'],
                ['risk' => 'supply_shortage',  'description' => 'Demanda pode superar estoque disponível', 'mitigation' => 'Aumentar níveis de estoque preventivamente'],
            ],
            'maturity' => [
                ['risk' => 'price_pressure',    'description' => 'Pressão de preços pela concorrência intensa', 'mitigation' => 'Focar em valor e serviço diferenciado'],
                ['risk' => 'market_saturation', 'description' => 'Mercado saturado com muitas ofertas',          'mitigation' => 'Explorar nichos e segmentos diferenciados'],
            ],
            'decline' => [
                ['risk' => 'revenue_drop',     'description' => 'Queda progressiva de receita',  'mitigation' => 'Avaliar revitalização ou saída planejada'],
                ['risk' => 'excess_inventory', 'description' => 'Risco de encalhe de estoque',   'mitigation' => 'Reduzir compras e liquidar excedentes'],
            ],
        ];

        $risks    = $baseRisks[$stage] ?? $baseRisks['decline'];
        $velocity = (float) ($historical['velocity'] ?? 0.0);

        if ($velocity < -0.20) {
            $risks[] = [
                'risk'        => 'rapid_decline',
                'description' => 'Declínio acelerado das vendas detectado',
                'mitigation'  => 'Ação imediata: revisar preço, título e estoque',
            ];
        }

        return $risks;
    }

    // =========================================================================
    // RISK ANALYSIS
    // =========================================================================

    private function analyzeMarketRisks(array $product, array $context): array
    {
        $score   = 0.0;
        $factors = [];

        if (($context['trend'] ?? '') === 'negative') {
            $score   += 0.25;
            $factors[] = 'Tendência de mercado negativa';
        }
        if (($context['volatility'] ?? '') === 'high') {
            $score   += 0.20;
            $factors[] = 'Alta volatilidade de mercado';
        }
        if (($context['market_saturation'] ?? '') === 'high') {
            $score   += 0.20;
            $factors[] = 'Mercado altamente saturado';
        }

        return ['score' => $score, 'level' => $this->getRiskLevel($score), 'factors' => $factors];
    }

    private function analyzeCompetitiveRisks(array $product, array $context): array
    {
        $score   = 0.0;
        $factors = [];
        $count   = (int) ($context['competitor_count'] ?? 0);

        if ($count > 50) {
            $score   += 0.40;
            $factors[] = 'Alta concorrência (>50 vendedores)';
        } elseif ($count > 20) {
            $score   += 0.20;
            $factors[] = 'Concorrência moderada';
        }

        if (($context['trend'] ?? '') === 'negative') {
            $score   += 0.15;
            $factors[] = 'Tendência competitiva negativa';
        }

        return ['score' => $score, 'level' => $this->getRiskLevel($score), 'factors' => $factors];
    }

    private function analyzeSupplyChainRisks(array $product, array $historical): array
    {
        $qty           = (int) ($product['available_quantity'] ?? 0);
        $sales         = $historical['sales_vector'] ?? [];
        $avgDailySales = count($sales) > 0 ? (float) array_sum($sales) / count($sales) : 1.0;
        $daysOfStock   = $avgDailySales > 0.0 ? $qty / $avgDailySales : PHP_FLOAT_MAX;

        if ($daysOfStock < 7) {
            $score   = 0.70;
            $factors = ['Estoque crítico: menos de 7 dias'];
        } elseif ($daysOfStock < 14) {
            $score   = 0.50;
            $factors = ['Estoque baixo: menos de 14 dias'];
        } elseif ($daysOfStock < 30) {
            $score   = 0.30;
            $factors = ['Estoque moderado: menos de 30 dias'];
        } else {
            $score   = 0.10;
            $factors = ['Estoque adequado'];
        }

        return ['score' => $score, 'level' => $this->getRiskLevel($score), 'factors' => $factors];
    }

    private function analyzeRegulatoryRisks(array $product): array
    {
        $title = strtolower((string) ($product['title'] ?? ''));

        $regulatedKeywords = [
            'bateria'    => 0.10,
            'carregador' => 0.10,
            'bivolt'     => 0.10,
            'eletrico'   => 0.10,
            'elétrico'   => 0.10,
            'voltagem'   => 0.08,
            'tensao'     => 0.08,
            'tensão'     => 0.08,
            'inflamavel' => 0.12,
            'inflamável' => 0.12,
        ];

        $score   = 0.05;
        $factors = [];

        foreach ($regulatedKeywords as $keyword => $weight) {
            if (str_contains($title, $keyword)) {
                $score   += $weight;
                $factors[] = "Produto regulado: {$keyword}";
            }
        }

        if (empty($factors)) {
            $factors[] = 'Sem riscos regulatórios significativos identificados';
        }

        return ['score' => $score, 'level' => $this->getRiskLevel($score), 'factors' => $factors];
    }

    private function analyzeEconomicRisks(array $context): array
    {
        $score   = 0.05;
        $factors = ['Monitorar indicadores: inflação, taxa de juros, câmbio'];

        if (($context['volatility'] ?? '') === 'high') {
            $score   += 0.10;
            $factors[] = 'Alta volatilidade macroeconômica';
        }
        if (($context['trend'] ?? '') === 'negative') {
            $score   += 0.10;
            $factors[] = 'Tendência econômica negativa';
        }

        return ['score' => $score, 'level' => $this->getRiskLevel($score), 'factors' => $factors];
    }

    private function analyzeSeasonalRisks(array $product = [], array $context = []): array
    {
        return [
            'score'   => 0.15,
            'level'   => 'low',
            'factors' => ['Sazonalidade padrão identificada'],
        ];
    }

    private function calculateOverallRisk(array $risks): float
    {
        if (empty($risks)) {
            return 0.0;
        }
        return (float) array_sum(array_column($risks, 'score')) / count($risks);
    }

    private function generateMitigationPlan(array $risks): array
    {
        if (empty($risks)) {
            return ['actions' => [], 'total_risks_assessed' => 0];
        }

        uasort($risks, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        $actions = [];
        foreach ($risks as $type => $risk) {
            $score    = (float) ($risk['score'] ?? 0.0);
            $priority = match (true) {
                $score >= 0.60 => 'critical',
                $score >= 0.40 => 'high',
                $score >= 0.20 => 'medium',
                default        => 'low',
            };
            $actions[] = [
                'risk_type' => $type,
                'priority'  => $priority,
                'factors'   => $risk['factors'] ?? [],
                'action'    => "Mitigar risco: {$type}",
            ];
        }

        return ['actions' => $actions, 'total_risks_assessed' => count($risks)];
    }

    private function generateRiskAlerts(array $risks): array
    {
        $labelMap = [
            'market_risks'       => 'Risco de Mercado',
            'competitive_risks'  => 'Risco Competitivo',
            'supply_chain_risks' => 'Risco de Cadeia de Suprimentos',
            'regulatory_risks'   => 'Risco Regulatório',
            'economic_risks'     => 'Risco Econômico',
            'seasonal_risks'     => 'Risco Sazonal',
        ];

        $alerts = [];
        foreach ($risks as $type => $risk) {
            $score = (float) ($risk['score'] ?? 0.0);
            $label = $labelMap[$type] ?? (string) $type;

            if ($score >= 0.60) {
                $alerts[] = [
                    'type'           => $type,
                    'level'          => 'critical',
                    'label'          => $label,
                    'action_required' => true,
                    'score'          => $score,
                ];
            } elseif ($score >= 0.30) {
                $alerts[] = [
                    'type'           => $type,
                    'level'          => 'warning',
                    'label'          => $label,
                    'action_required' => false,
                    'score'          => $score,
                ];
            }
        }

        if (empty($alerts)) {
            return [['type' => 'all_clear', 'level' => 'info', 'label' => 'Todos os riscos dentro do aceitável', 'action_required' => false]];
        }

        return $alerts;
    }

    private function getRiskLevel(float $score): string
    {
        if ($score > 0.60) {
            return 'high';
        }
        if ($score > 0.30) {
            return 'medium';
        }
        return 'low';
    }

    // =========================================================================
    // SCENARIOS & CONFIDENCE
    // =========================================================================

    private function generateScenarios(array $forecast, array $factors): array
    {
        if (empty($forecast)) {
            return [
                'optimistic'  => ['value' => 100, 'probability' => 0.20],
                'base'        => ['value' => 100, 'probability' => 0.60],
                'pessimistic' => ['value' => 100, 'probability' => 0.20],
            ];
        }

        $avg      = array_sum($forecast) / count($forecast);
        $variance = array_sum(array_map(static fn($v) => ($v - $avg) ** 2, $forecast)) / count($forecast);
        $stdDev   = sqrt($variance);
        $trend    = (float) ($factors['trend'] ?? 0.0);

        $baseValue   = (int) round($avg);
        $optimistic  = max(0, (int) round($avg + $stdDev * 1.5 + abs($trend) * 5));
        $pessimistic = max(0, (int) round($avg - $stdDev * 1.5));

        if ($optimistic <= $baseValue) {
            $optimistic = $baseValue + 1;
        }
        if ($pessimistic >= $baseValue) {
            $pessimistic = max(0, $baseValue - 1);
        }

        return [
            'optimistic'  => ['value' => $optimistic,  'probability' => 0.20],
            'base'        => ['value' => $baseValue,   'probability' => 0.60],
            'pessimistic' => ['value' => $pessimistic, 'probability' => 0.20],
        ];
    }

    private function calculatePredictionConfidence(array $predictions, array $historical): array
    {
        $quality    = (string) ($historical['quality']     ?? 'medium');
        $dataPoints = (int)    ($historical['data_points'] ?? 0);

        $qualityBase = ['high' => 0.80, 'medium' => 0.65, 'low' => 0.40];
        $base        = $qualityBase[$quality] ?? 0.65;
        $dataBonus   = $dataPoints >= 90 ? 0.10 : ($dataPoints >= 30 ? 0.05 : 0.0);
        $overall     = min(0.95, $base + $dataBonus);

        $values       = array_column($predictions, 'avg_daily_sales');
        $modelAgreement = 0.70;
        if (count($values) > 1) {
            $avg      = array_sum($values) / count($values);
            $variance = array_sum(array_map(static fn($v) => ($v - $avg) ** 2, $values)) / count($values);
            $modelAgreement = $variance < 1.0 ? 0.90 : 0.70;
        }

        $reliability = match (true) {
            $overall >= 0.80 => 'high',
            $overall >= 0.60 => 'medium',
            default          => 'low',
        };

        return [
            'overall_confidence' => $overall,
            'model_agreement'    => $modelAgreement,
            'reliability'        => $reliability,
            'data_quality'       => $quality,
            'data_points'        => $dataPoints,
        ];
    }

    // =========================================================================
    // INSIGHTS & RECOMMENDATIONS
    // =========================================================================

    private function generatePredictiveInsights(array $consolidated, array $confidence): array
    {
        $insights = [];
        $forecast = (string) ($consolidated['forecast']      ?? '');
        $stage    = (string) ($consolidated['current_stage'] ?? '');

        if ($forecast === 'increasing') {
            $insights[] = [
                'type'     => 'demand',
                'priority' => 'medium',
                'message'  => 'Demanda crescente identificada — considere aumentar estoque',
            ];
        } elseif ($forecast === 'decreasing') {
            $insights[] = [
                'type'     => 'demand',
                'priority' => 'high',
                'message'  => 'Demanda em queda — ação imediata recomendada',
            ];
        }

        if ($stage === 'decline') {
            $insights[] = [
                'type'     => 'lifecycle',
                'priority' => 'high',
                'message'  => 'Produto em fase de declínio — avaliar estratégia de saída ou revitalização',
            ];
        }

        if (empty($insights)) {
            return [['type' => 'general', 'priority' => 'low', 'message' => 'Monitorar métricas continuamente']];
        }

        return $insights;
    }

    private function generatePredictiveRecommendations(array $consolidated, array $insights): array
    {
        if (empty($consolidated) && empty($insights)) {
            return [['priority' => 'low', 'action' => 'Continuar monitorando o produto', 'type' => 'general']];
        }

        $recs     = [];
        $forecast = (string) ($consolidated['forecast']      ?? '');
        $stage    = (string) ($consolidated['current_stage'] ?? '');

        if ($forecast === 'decreasing') {
            $recs[] = ['priority' => 'high',   'action' => 'Revisar preço e estratégia de desconto imediatamente', 'type' => 'pricing'];
        } elseif ($forecast === 'increasing') {
            $recs[] = ['priority' => 'medium', 'action' => 'Aumentar estoque para atender demanda crescente',      'type' => 'inventory'];
            $recs[] = ['priority' => 'medium', 'action' => 'Intensificar campanhas publicitárias',                 'type' => 'advertising'];
        }

        foreach ($insights as $insight) {
            if (($insight['type'] ?? '') === 'lifecycle' && ($insight['priority'] ?? '') === 'high') {
                $recs[] = ['priority' => 'high', 'action' => 'Avaliar revitalização ou descontinuação do produto', 'type' => 'lifecycle'];
            }
        }

        if ($stage === 'decline') {
            $hasLifecycleRec = array_filter($recs, static fn($r) => $r['type'] === 'lifecycle');
            if (empty($hasLifecycleRec)) {
                $recs[] = ['priority' => 'high', 'action' => 'Produto em declínio — planejar saída ordenada', 'type' => 'lifecycle'];
            }
        }

        if (empty($recs)) {
            return [['priority' => 'low', 'action' => 'Manter estratégia atual e monitorar', 'type' => 'general']];
        }

        $order = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($recs, static fn($a, $b): int => ($order[$a['priority'] ?? 'low'] ?? 2) <=> ($order[$b['priority'] ?? 'low'] ?? 2));

        return $recs;
    }

    // =========================================================================
    // UTILITIES
    // =========================================================================

    private function getFactorCoefficient(string $factor): float
    {
        $coefficients = [
            'seasonality'        => 0.5,
            'trend'              => 1.2,
            'market_competition' => 0.8,
            'price_sensitivity'  => 0.7,
            'quality_score'      => 0.9,
            'brand_strength'     => 1.0,
        ];
        return $coefficients[$factor] ?? 0.1;
    }

    private function getMarketContext(array $product): array
    {
        return [
            'trend'             => 'stable',
            'volatility'        => 'medium',
            'market_saturation' => 'medium',
            'competitor_count'  => 0,
        ];
    }

    private function getExternalFactors(array $context): array
    {
        return [
            'seasonality'  => 0.5,
            'trend'        => 0.0,
            'economic_risk' => 0.1,
        ];
    }

    private function identifyInfluenceFactors(array $product, array $context): array
    {
        return [
            ['factor' => 'price',   'coefficient' => $this->getFactorCoefficient('price_sensitivity')],
            ['factor' => 'quality', 'coefficient' => $this->getFactorCoefficient('quality_score')],
            ['factor' => 'brand',   'coefficient' => $this->getFactorCoefficient('brand_strength')],
        ];
    }

    private function evaluateModelPerformance(array $predictions): array
    {
        return ['accuracy' => 0.75, 'mse' => 0.02, 'evaluated_at' => date('Y-m-d')];
    }
}
