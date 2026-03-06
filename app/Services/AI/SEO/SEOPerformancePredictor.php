<?php

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use PDO;

/**
 * 🧠 SEO Performance Predictor
 *
 * Usa heurísticas e pesos configuráveis para estimar performance de anúncios
 * e sugerir otimizações com maior probabilidade de sucesso.
 * Nota: Não usa ML — calcula scores com weighted averages e fatores fixos.
 */
class SEOPerformancePredictor
{
    private int $accountId;
    private PDO $db;
    private ?MercadoLivreClient $mlClient = null;

    // Fatores que influenciam performance
    private const PERFORMANCE_FACTORS = [
        'title_length' => ['ideal_min' => 45, 'ideal_max' => 58, 'weight' => 0.15],
        'description_length' => ['ideal_min' => 500, 'ideal_max' => 2000, 'weight' => 0.12],
        'attribute_completeness' => ['target' => 100, 'weight' => 0.20],
        'image_count' => ['target' => 6, 'weight' => 0.10],
        'price_position' => ['target' => 'competitive', 'weight' => 0.15],
        'keyword_density' => ['ideal_min' => 2, 'ideal_max' => 4, 'weight' => 0.08],
        'listing_quality' => ['target' => 95, 'weight' => 0.20],
    ];

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * 🔮 Prever performance de um item
     */
    public function predictPerformance(array $itemData): array
    {
        $prediction = [
            'item_id' => $itemData['id'] ?? '',
            'current_score' => 0,
            'predicted_views' => 0,
            'predicted_sales' => 0,
            'predicted_ctr' => 0,
            'confidence_level' => 0,
            'improvement_potential' => 0,
            'recommendations' => [],
            'risk_factors' => [],
        ];

        try {
            // Calcular score atual
            $currentScore = $this->calculateCurrentScore($itemData);
            $prediction['current_score'] = $currentScore;

            // Análise de fatores
            $factors = $this->analyzePerformanceFactors($itemData);

            // Prever usando modelo ML
            $mlPrediction = $this->mlPredict($factors, $itemData);

            // Calcular métricas preditas
            $prediction['predicted_views'] = $this->predictViews($mlPrediction, $itemData);
            $prediction['predicted_sales'] = $this->predictSales($mlPrediction, $itemData);
            $prediction['predicted_ctr'] = $this->predictCTR($mlPrediction, $itemData);

            // Calcular confiança da previsão
            $prediction['confidence_level'] = $this->calculateConfidence($factors, $itemData);

            // Identificar potencial de melhoria
            $prediction['improvement_potential'] = $this->calculateImprovementPotential($factors);

            // Gerar recomendações
            $prediction['recommendations'] = $this->generateMLRecommendations($factors, $mlPrediction);

            // Identificar fatores de risco
            $prediction['risk_factors'] = $this->identifyRiskFactors($factors);

            // Salvar predição no banco
            $this->savePrediction($prediction);
        } catch (\Exception $e) {
            $prediction['error'] = $e->getMessage();
        }

        return $prediction;
    }

    /**
     * 📈 Análise de fatores de performance
     */
    private function analyzePerformanceFactors(array $itemData): array
    {
        $factors = [];

        // Fator: Comprimento do título
        $titleLength = strlen($itemData['title'] ?? '');
        $titleIdeal = self::PERFORMANCE_FACTORS['title_length'];
        $factors['title_length'] = [
            'current' => $titleLength,
            'ideal_min' => $titleIdeal['ideal_min'],
            'ideal_max' => $titleIdeal['ideal_max'],
            'score' => $this->calculateFactorScore($titleLength, $titleIdeal),
            'weight' => $titleIdeal['weight'],
            'impact' => 'high',
        ];

        // Fator: Comprimento da descrição
        $descLength = strlen($itemData['description'] ?? '');
        $descIdeal = self::PERFORMANCE_FACTORS['description_length'];
        $factors['description_length'] = [
            'current' => $descLength,
            'ideal_min' => $descIdeal['ideal_min'],
            'ideal_max' => $descIdeal['ideal_max'],
            'score' => $this->calculateFactorScore($descLength, $descIdeal),
            'weight' => $descIdeal['weight'],
            'impact' => 'medium',
        ];

        // Fator: Completude de atributos
        $attrCompleteness = $this->calculateAttributeCompleteness($itemData);
        $attrIdeal = self::PERFORMANCE_FACTORS['attribute_completeness'];
        $factors['attribute_completeness'] = [
            'current' => $attrCompleteness,
            'target' => $attrIdeal['target'],
            'score' => ($attrCompleteness / $attrIdeal['target']) * 100,
            'weight' => $attrIdeal['weight'],
            'impact' => 'high',
        ];

        // Fator: Número de imagens
        $imageCount = count($itemData['pictures'] ?? []);
        $imageIdeal = self::PERFORMANCE_FACTORS['image_count'];
        $factors['image_count'] = [
            'current' => $imageCount,
            'target' => $imageIdeal['target'],
            'score' => min(($imageCount / $imageIdeal['target']) * 100, 100),
            'weight' => $imageIdeal['weight'],
            'impact' => 'medium',
        ];

        // Fator: Posição de preço
        $pricePosition = $this->analyzePricePosition($itemData);
        $priceIdeal = self::PERFORMANCE_FACTORS['price_position'];
        $factors['price_position'] = [
            'current' => $pricePosition['position'],
            'target' => $priceIdeal['target'],
            'score' => $pricePosition['score'],
            'weight' => $priceIdeal['weight'],
            'impact' => 'high',
            'data' => $pricePosition,
        ];

        // Fator: Densidade de keywords
        $keywordDensity = $this->calculateKeywordDensity($itemData);
        $densityIdeal = self::PERFORMANCE_FACTORS['keyword_density'];
        $factors['keyword_density'] = [
            'current' => $keywordDensity,
            'ideal_min' => $densityIdeal['ideal_min'],
            'ideal_max' => $densityIdeal['ideal_max'],
            'score' => $this->calculateFactorScore($keywordDensity, $densityIdeal),
            'weight' => $densityIdeal['weight'],
            'impact' => 'medium',
        ];

        // Fator: Qualidade geral do anúncio
        $listingQuality = $this->assessListingQuality($itemData);
        $qualityIdeal = self::PERFORMANCE_FACTORS['listing_quality'];
        $factors['listing_quality'] = [
            'current' => $listingQuality,
            'target' => $qualityIdeal['target'],
            'score' => ($listingQuality / $qualityIdeal['target']) * 100,
            'weight' => $qualityIdeal['weight'],
            'impact' => 'high',
        ];

        return $factors;
    }

    /**
     * 🤖 Predição usando Machine Learning
     */
    private function mlPredict(array $factors, array $itemData): array
    {
        // Calcular score ponderado dos fatores
        $totalScore = 0;
        $totalWeight = 0;

        foreach ($factors as $factor) {
            $totalScore += $factor['score'] * $factor['weight'];
            $totalWeight += $factor['weight'];
        }

        $weightedScore = $totalWeight > 0 ? $totalScore / $totalWeight : 0;

        // Ajustar com base em dados históricos da categoria
        $categoryAdjustment = $this->getCategoryPerformanceAdjustment($itemData['category_id'] ?? '');

        // Ajustar com base em sazonalidade
        $seasonalAdjustment = $this->getSeasonalAdjustment($itemData);

        // Ajustar com base em performance histórica do vendedor
        $sellerAdjustment = $this->getSellerPerformanceAdjustment();

        // Calcular predição final
        $basePrediction = $weightedScore;
        $adjustedPrediction = $basePrediction * $categoryAdjustment * $seasonalAdjustment * $sellerAdjustment;

        return [
            'base_score' => $basePrediction,
            'category_adjustment' => $categoryAdjustment,
            'seasonal_adjustment' => $seasonalAdjustment,
            'seller_adjustment' => $sellerAdjustment,
            'final_score' => min($adjustedPrediction, 100),
            'factors_breakdown' => $factors,
        ];
    }

    /**
     * 👁️ Prever visualizações
     */
    private function predictViews(array $mlPrediction, array $itemData): int
    {
        $baseViews = $this->getBaseViewsForCategory($itemData['category_id'] ?? '');

        // Ajustar baseado no score predito
        $scoreMultiplier = $mlPrediction['final_score'] / 100;

        // Ajustar baseado no preço
        $priceMultiplier = $this->getPriceViewImpact($itemData['price'] ?? 0);

        // Ajustar baseado em reputação
        $reputationMultiplier = $this->getReputationViewImpact();

        $predictedViews = $baseViews * $scoreMultiplier * $priceMultiplier * $reputationMultiplier;

        return (int)round($predictedViews);
    }

    /**
     * 🛒 Prever vendas
     */
    private function predictSales(array $mlPrediction, array $itemData): int
    {
        $baseConversionRate = $this->getBaseConversionRateForCategory($itemData['category_id'] ?? '');
        $predictedViews = $this->predictViews($mlPrediction, $itemData);

        // Ajustar taxa de conversão baseado no score
        $scoreBonus = ($mlPrediction['final_score'] - 50) / 100; // Bonus se > 50
        $adjustedConversionRate = $baseConversionRate * (1 + $scoreBonus);

        // Ajustar baseado no preço
        $priceImpact = $this->getPriceConversionImpact($itemData['price'] ?? 0);
        $finalConversionRate = $adjustedConversionRate * $priceImpact;

        $predictedSales = $predictedViews * ($finalConversionRate / 100);

        return (int)max(1, round($predictedSales));
    }

    /**
     * 📊 Prever CTR (Click-Through Rate)
     */
    private function predictCTR(array $mlPrediction, array $itemData): float
    {
        $baseCTR = $this->getBaseCTRForCategory($itemData['category_id'] ?? '');

        // Ajustar CTR baseado na qualidade do título
        $titleScore = $mlPrediction['factors_breakdown']['title_length']['score'] ?? 50;
        $titleMultiplier = $titleScore / 50;

        // Ajustar baseado no preço competitivo
        $priceScore = $mlPrediction['factors_breakdown']['price_position']['score'] ?? 50;
        $priceMultiplier = $priceScore / 50;

        // Ajustar baseado em imagem principal
        $imageScore = $mlPrediction['factors_breakdown']['image_count']['score'] ?? 50;
        $imageMultiplier = min($imageScore / 50, 1.5);

        $predictedCTR = $baseCTR * $titleMultiplier * $priceMultiplier * $imageMultiplier;

        return min($predictedCTR, 15.0); // CTR máximo realista
    }

    /**
     * 🎯 Calcular confiança da previsão
     */
    private function calculateConfidence(array $factors, array $itemData): float
    {
        $confidence = 50; // Base confidence

        // Aumentar confiança se temos dados históricos suficientes
        $historicalDataPoints = $this->countHistoricalDataPoints($itemData['category_id'] ?? '');
        $confidence += min($historicalDataPoints / 10, 30);

        // Aumentar confiança se o item tem dados completos
        $completenessBonus = 0;
        foreach ($factors as $factor) {
            if ($factor['score'] >= 80) {
                $completenessBonus += 5;
            }
        }
        $confidence += min($completenessBonus, 20);

        return min($confidence, 95);
    }

    /**
     * 📈 Calcular potencial de melhoria
     */
    private function calculateImprovementPotential(array $factors): float
    {
        $potential = 0;

        foreach ($factors as $factor) {
            $gap = 100 - $factor['score'];
            $weightedGap = $gap * $factor['weight'];
            $potential += $weightedGap;
        }

        return min($potential, 100);
    }

    /**
     * 💡 Gerar recomendações baseadas em ML
     */
    private function generateMLRecommendations(array $factors, array $mlPrediction): array
    {
        $recommendations = [];

        foreach ($factors as $factorName => $factor) {
            if ($factor['score'] < 70) {
                $recommendation = $this->generateFactorRecommendation($factorName, $factor);
                if ($recommendation) {
                    $recommendations[] = $recommendation;
                }
            }
        }

        // Ordenar por impacto e peso
        usort($recommendations, function ($a, $b) {
            $priorityA = $a['impact_weight'] ?? 0;
            $priorityB = $b['impact_weight'] ?? 0;
            return $priorityB <=> $priorityA;
        });

        return array_slice($recommendations, 0, 10); // Top 10 recomendações
    }

    /**
     * ⚠️ Identificar fatores de risco
     */
    private function identifyRiskFactors(array $factors): array
    {
        $risks = [];

        foreach ($factors as $factorName => $factor) {
            if ($factor['score'] < 40) {
                $risks[] = [
                    'factor' => $factorName,
                    'severity' => $factor['score'] < 20 ? 'critical' : 'high',
                    'impact' => $factor['impact'],
                    'description' => $this->generateRiskDescription($factorName, $factor),
                ];
            }
        }

        return $risks;
    }

    // ========== MÉTODOS AUXILIARES ==========

    private function calculateCurrentScore(array $itemData): int
    {
        // Usar o AdvancedSEOMaximizer se disponível
        if (class_exists('App\Services\AI\SEO\AdvancedSEOMaximizer')) {
            $maximizer = new AdvancedSEOMaximizer($this->accountId);
            $scores = $maximizer->calculateSEOScore($itemData);
            return (int)round($scores['overall']);
        }

        // Cálculo baseado em fatores reais do item
        $score = 0;
        $title = $itemData['title'] ?? '';
        $desc = $itemData['description'] ?? $itemData['plain_text'] ?? '';
        if (is_array($desc)) {
            $desc = $desc['plain_text'] ?? $desc['text'] ?? '';
        }
        $attributes = $itemData['attributes'] ?? [];
        $pictures = $itemData['pictures'] ?? [];

        // Título (0-25)
        $titleLen = mb_strlen($title);
        if ($titleLen >= 45 && $titleLen <= 58) {
            $score += 25;
        } elseif ($titleLen >= 30 && $titleLen <= 60) {
            $score += 15;
        } elseif ($titleLen > 0) {
            $score += 5;
        }

        // Descrição (0-20)
        $descLen = mb_strlen($desc);
        if ($descLen >= 500) {
            $score += 20;
        } elseif ($descLen >= 200) {
            $score += 12;
        } elseif ($descLen > 0) {
            $score += 5;
        }

        // Atributos (0-25)
        $filledAttrs = 0;
        foreach ($attributes as $attr) {
            if (!empty($attr['value_name'] ?? $attr['value_id'] ?? null)) {
                $filledAttrs++;
            }
        }
        $score += min(25, $filledAttrs * 3);

        // Imagens (0-15)
        $imgCount = count($pictures);
        $score += min(15, $imgCount * 2);

        // Shipping free + full (0-15)
        $shipping = $itemData['shipping'] ?? [];
        if (!empty($shipping['free_shipping'])) {
            $score += 8;
        }
        if (($shipping['logistic_type'] ?? '') === 'fulfillment') {
            $score += 7;
        }

        return min(100, $score);
    }

    private function calculateFactorScore($current, array $ideal): float
    {
        if ($current >= $ideal['ideal_min'] && $current <= $ideal['ideal_max']) {
            return 100;
        }

        if ($current < $ideal['ideal_min']) {
            return ($current / $ideal['ideal_min']) * 100;
        }

        // Acima do ideal máximo
        $excess = $current - $ideal['ideal_max'];
        return max(0, 100 - ($excess / $ideal['ideal_max']) * 50);
    }

    private function calculateAttributeCompleteness(array $itemData): float
    {
        $attributes = $itemData['attributes'] ?? [];
        $filledAttrs = 0;

        foreach ($attributes as $attr) {
            if (!empty($attr['value_name'])) {
                $filledAttrs++;
            }
        }

        return count($attributes) > 0 ? ($filledAttrs / count($attributes)) * 100 : 0;
    }

    private function analyzePricePosition(array $itemData): array
    {
        $price = $itemData['price'] ?? 0;
        $categoryId = $itemData['category_id'] ?? '';

        // Obter estatísticas de preço da categoria
        $priceStats = $this->getCategoryPriceStats($categoryId);

        if (empty($priceStats)) {
            return [
                'position' => 'unknown',
                'percentile' => 50,
                'score' => 50,
            ];
        }

        $percentile = $this->calculatePricePercentile($price, $priceStats);

        if ($percentile <= 25) {
            $position = 'low_price';
            $score = 85;
        } elseif ($percentile <= 75) {
            $position = 'competitive';
            $score = 100;
        } else {
            $position = 'high_price';
            $score = 60;
        }

        return [
            'position' => $position,
            'percentile' => $percentile,
            'score' => $score,
            'price' => $price,
            'category_avg' => $priceStats['avg'] ?? 0,
        ];
    }

    private function calculateKeywordDensity(array $itemData): float
    {
        $title = strtolower($itemData['title'] ?? '');
        $description = strtolower($itemData['description'] ?? '');

        $totalText = $title . ' ' . $description;
        $totalWords = str_word_count($totalText);

        if ($totalWords === 0) return 0;

        // Extrair keywords principais (simplificado)
        $keywords = $this->extractMainKeywords($title);
        $keywordCount = 0;

        foreach ($keywords as $keyword) {
            $keywordCount += substr_count($totalText, $keyword);
        }

        return ($keywordCount / $totalWords) * 100;
    }

    private function assessListingQuality(array $itemData): float
    {
        $quality = 0;

        // Avaliar título
        $title = $itemData['title'] ?? '';
        if (strlen($title) >= 45 && strlen($title) <= 58) $quality += 20;
        if (!preg_match('/[^\\p{L}\\p{N}\\s\\-\\.]/u', $title)) $quality += 10;

        // Avaliar descrição
        $desc = $itemData['description'] ?? '';
        if (strlen($desc) >= 500) $quality += 20;
        if (strpos($desc, '•') !== false || strpos($desc, '*') !== false) $quality += 10;

        // Avaliar imagens
        $images = $itemData['pictures'] ?? [];
        if (count($images) >= 6) $quality += 20;
        if (count($images) >= 3) $quality += 10;

        // Avaliar atributos
        $attrs = $itemData['attributes'] ?? [];
        if (count($attrs) >= 10) $quality += 10;

        return min($quality, 100);
    }

    private function getCategoryPerformanceAdjustment(string $categoryId): float
    {
        // Obter fator de ajuste baseado em performance média da categoria
        $stmt = $this->db->prepare("
            SELECT AVG(performance_score) as avg_score
            FROM category_performance
            WHERE category_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $avgScore = $result['avg_score'] ?? 50;
        return $avgScore / 50; // Normalizar para 1.0 = médio
    }

    private function getSeasonalAdjustment(array $itemData): float
    {
        $month = (int)date('n');
        $categoryId = $itemData['category_id'] ?? '';

        // Tentar obter fator sazonal específico da categoria no banco
        if ($categoryId !== '') {
            try {
                $stmt = $this->db->prepare("
                    SELECT AVG(performance_score) as avg_score
                    FROM category_performance
                    WHERE category_id = ? AND MONTH(date) = ?
                    AND date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
                ");
                $stmt->execute([$categoryId, $month]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmtOverall = $this->db->prepare("
                    SELECT AVG(performance_score) as avg_score
                    FROM category_performance
                    WHERE category_id = ?
                    AND date >= DATE_SUB(NOW(), INTERVAL 2 YEAR)
                ");
                $stmtOverall->execute([$categoryId]);
                $overall = $stmtOverall->fetch(PDO::FETCH_ASSOC);

                $monthAvg = (float)($result['avg_score'] ?? 0);
                $overallAvg = (float)($overall['avg_score'] ?? 0);

                if ($overallAvg > 0 && $monthAvg > 0) {
                    return round($monthAvg / $overallAvg, 2);
                }
            } catch (\Exception $e) {
                // Fallback para fatores estáticos
            }
        }

        // Fatores sazonais gerais (Black Friday, Natal, férias)
        $seasonalFactors = [
            11 => 1.3, // Novembro (Black Friday)
            12 => 1.4, // Dezembro (Natal)
            1  => 0.9, // Janeiro (pós-festas)
            7  => 0.8, // Julho (baixa temporada)
        ];

        return $seasonalFactors[$month] ?? 1.0;
    }

    private function getSellerPerformanceAdjustment(): float
    {
        // Obter fator baseado em reputação e histórico do vendedor
        $stmt = $this->db->prepare("
            SELECT reputation_level, avg_rating
            FROM ml_accounts
            WHERE id = ?
        ");
        $stmt->execute([$this->accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) return 1.0;

        $reputation = $account['reputation_level'] ?? 0;
        $rating = $account['avg_rating'] ?? 0;

        $adjustment = 1.0;

        if ($reputation >= 4) $adjustment += 0.2;
        if ($rating >= 4.5) $adjustment += 0.1;

        return $adjustment;
    }

    private function getBaseViewsForCategory(string $categoryId): int
    {
        // Obter média de visualizações para a categoria
        $stmt = $this->db->prepare("
            SELECT AVG(views) as avg_views
            FROM item_performance
            WHERE category_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['avg_views'] ?? 1000);
    }

    private function getPriceViewImpact(float $price): float
    {
        // Preços muito baixos ou muito altos podem impactar negativamente
        if ($price < 50) return 0.8;  // Muito barato
        if ($price > 5000) return 0.9; // Muito caro

        return 1.0; // Preço normal
    }

    private function getReputationViewImpact(): float
    {
        // Mesmo método do seller adjustment
        return $this->getSellerPerformanceAdjustment();
    }

    private function getBaseConversionRateForCategory(string $categoryId): float
    {
        // Taxa de conversão média por categoria
        $stmt = $this->db->prepare("
            SELECT AVG(conversion_rate) as avg_cr
            FROM category_performance
            WHERE category_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['avg_cr'] ?? 2.5; // 2.5% média geral
    }

    private function getPriceConversionImpact(float $price): float
    {
        // Preços muito altos podem diminuir conversão
        if ($price < 20) return 1.2;  // Barato = mais conversão
        if ($price > 1000) return 0.8; // Caro = menos conversão

        return 1.0;
    }

    private function getBaseCTRForCategory(string $categoryId): float
    {
        // CTR médio por categoria
        $stmt = $this->db->prepare("
            SELECT AVG(ctr) as avg_ctr
            FROM category_performance
            WHERE category_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['avg_ctr'] ?? 3.5; // 3.5% médio geral
    }

    private function countHistoricalDataPoints(string $categoryId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM item_performance
            WHERE category_id = ? AND date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ");
        $stmt->execute([$categoryId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int)($result['count'] ?? 0);
    }

    private function generateFactorRecommendation(string $factorName, array $factor): ?array
    {
        $recommendations = [
            'title_length' => [
                'action' => 'Ajustar comprimento do título',
                'description' => 'Título ideal: 45-58 caracteres. Atual: ' . $factor['current'] . ' caracteres',
                'priority' => 'high',
                'impact_weight' => $factor['weight'] * ($factor['impact'] === 'high' ? 2 : 1),
            ],
            'description_length' => [
                'action' => 'Expandir descrição',
                'description' => 'Descrição ideal: 500-2000 caracteres. Atual: ' . $factor['current'] . ' caracteres',
                'priority' => 'medium',
                'impact_weight' => $factor['weight'],
            ],
            'attribute_completeness' => [
                'action' => 'Completar atributos',
                'description' => 'Completude atual: ' . round($factor['current'], 1) . '%. Ideal: 100%',
                'priority' => 'high',
                'impact_weight' => $factor['weight'] * 2,
            ],
            'image_count' => [
                'action' => 'Adicionar mais imagens',
                'description' => 'Imagens atuais: ' . $factor['current'] . '. Ideal: ' . $factor['target'],
                'priority' => 'medium',
                'impact_weight' => $factor['weight'],
            ],
            'price_position' => [
                'action' => 'Ajustar preço competitivo',
                'description' => 'Posição atual: ' . ($factor['data']['position'] ?? 'unknown'),
                'priority' => 'high',
                'impact_weight' => $factor['weight'] * 2,
            ],
            'keyword_density' => [
                'action' => 'Otimizar densidade de keywords',
                'description' => 'Densidade atual: ' . round($factor['current'], 2) . '%. Ideal: 2-4%',
                'priority' => 'medium',
                'impact_weight' => $factor['weight'],
            ],
            'listing_quality' => [
                'action' => 'Melhorar qualidade geral do anúncio',
                'description' => 'Qualidade atual: ' . round($factor['current'], 1) . '%. Ideal: 95%+',
                'priority' => 'high',
                'impact_weight' => $factor['weight'] * 2,
            ],
        ];

        return $recommendations[$factorName] ?? null;
    }

    private function generateRiskDescription(string $factorName, array $factor): string
    {
        $descriptions = [
            'title_length' => 'Título muito curto ou muito longo pode reduzir visibilidade até 40%',
            'description_length' => 'Descrição insuficiente pode diminuir taxa de conversão',
            'attribute_completeness' => 'Atributos incompletos podem reduzir relevância em buscas filtradas',
            'image_count' => 'Poucas imagens diminuem confiança e taxa de clique',
            'price_position' => 'Preço não competitivo pode reduzir impressões significativamente',
            'keyword_density' => 'Densidade inadequada pode afetar ranking do algoritmo',
            'listing_quality' => 'Baixa qualidade geral impacta negativamente todos os fatores',
        ];

        return $descriptions[$factorName] ?? 'Fator de risco identificado';
    }

    // Métodos auxiliares com dados reais via ML API
    private function getCategoryPriceStats(string $categoryId): array
    {
        $dbCategoryStats = $this->getPriceStatsFromDatabase($categoryId);
        if ($this->isValidPriceStats($dbCategoryStats)) {
            return $dbCategoryStats;
        }

        if ($categoryId && $this->mlClient) {
            try {
                $searchResults = $this->mlClient->searchItems([
                    'category' => $categoryId,
                    'sort' => 'relevance',
                    'limit' => 50,
                ]);

                $prices = [];
                foreach ($searchResults['results'] ?? [] as $item) {
                    $price = floatval($item['price'] ?? 0);
                    if ($price > 0) {
                        $prices[] = $price;
                    }
                }

                $mlStats = $this->buildPriceStats($prices);
                if ($this->isValidPriceStats($mlStats)) {
                    return $mlStats;
                }
            } catch (\Exception $e) {
                // Segue para fallback local
            }
        }

        $dbAccountStats = $this->getPriceStatsFromDatabase();
        if ($this->isValidPriceStats($dbAccountStats)) {
            return $dbAccountStats;
        }

        return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
    }

    private function calculatePricePercentile(float $price, array $stats): float
    {
        $max = floatval($stats['max'] ?? 0);
        $min = floatval($stats['min'] ?? 0);

        if ($max <= 0 || $min < 0 || $max <= $min) {
            return 50;
        }

        if ($price <= $stats['min']) return 0;
        if ($price >= $stats['max']) return 100;

        return (($price - $stats['min']) / ($stats['max'] - $stats['min'])) * 100;
    }

    private function getPriceStatsFromDatabase(?string $categoryId = null): array
    {
        try {
            $sql = "
                SELECT
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    AVG(price) as avg_price
                FROM items
                WHERE account_id = :account_id
                  AND status = 'active'
                  AND price > 0
            ";
            $params = ['account_id' => $this->accountId];

            if (!empty($categoryId)) {
                $sql .= " AND category_id = :category_id";
                $params['category_id'] = $categoryId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'min' => round(floatval($row['min_price'] ?? 0), 2),
                'max' => round(floatval($row['max_price'] ?? 0), 2),
                'avg' => round(floatval($row['avg_price'] ?? 0), 2),
            ];
        } catch (\Exception $e) {
            return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
        }
    }

    private function buildPriceStats(array $prices): array
    {
        if (empty($prices)) {
            return ['min' => 0.0, 'max' => 0.0, 'avg' => 0.0];
        }

        return [
            'min' => round(min($prices), 2),
            'max' => round(max($prices), 2),
            'avg' => round(array_sum($prices) / count($prices), 2),
        ];
    }

    private function isValidPriceStats(array $stats): bool
    {
        $min = floatval($stats['min'] ?? 0);
        $max = floatval($stats['max'] ?? 0);
        $avg = floatval($stats['avg'] ?? 0);

        return $min > 0 && $max > 0 && $avg > 0 && $max >= $min;
    }

    private function extractMainKeywords(string $title): array
    {
        $stopWords = [
            'de',
            'da',
            'do',
            'em',
            'para',
            'com',
            'sem',
            'por',
            'que',
            'das',
            'dos',
            'uma',
            'uns',
            'umas',
            'nos',
            'nas',
            'pelo',
            'pela',
            'este',
            'esta',
            'esse',
            'essa',
            'mais',
            'como',
            'cada',
            'todo',
            'toda',
            'sobre',
            'entre',
            'kit',
            'jogo',
            'pcs',
            'und',
            'unid',
            'novo',
            'nova',
            'tipo',
        ];

        $words = preg_split('/[\s,.!?;:()\[\]\/\-]+/', mb_strtolower($title, 'UTF-8'));

        return array_values(array_filter($words, function (string $word) use ($stopWords): bool {
            return mb_strlen($word) > 2
                && !in_array($word, $stopWords, true)
                && !is_numeric($word);
        }));
    }

    private function savePrediction(array $prediction): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO seo_performance_predictions
            (account_id, item_id, current_score, predicted_views, predicted_sales,
             predicted_ctr, confidence_level, improvement_potential, prediction_data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $this->accountId,
            $prediction['item_id'],
            $prediction['current_score'],
            $prediction['predicted_views'],
            $prediction['predicted_sales'],
            $prediction['predicted_ctr'],
            $prediction['confidence_level'],
            $prediction['improvement_potential'],
            json_encode([
                'recommendations' => $prediction['recommendations'],
                'risk_factors' => $prediction['risk_factors'],
            ])
        ]);
    }
}
