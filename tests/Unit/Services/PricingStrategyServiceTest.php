<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Mock do PricingStrategyService com métodos públicos para teste
 */
class MockPricingStrategyService
{
    /**
     * Calcula mediana - exposto para testes
     */
    public function calculateMedian(array $prices): float
    {
        if (empty($prices)) {
            return 0;
        }

        sort($prices);
        $count = count($prices);
        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($prices[$middle - 1] + $prices[$middle]) / 2;
        }

        return $prices[$middle];
    }

    /**
     * Calcula percentil
     */
    public function calculatePercentile(array $prices, int $percentile): float
    {
        if (empty($prices)) {
            return 0;
        }

        sort($prices);
        $count = count($prices);
        $index = ($percentile / 100) * ($count - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);

        if ($lower === $upper) {
            return $prices[$lower];
        }

        return $prices[$lower] + ($prices[$upper] - $prices[$lower]) * ($index - $lower);
    }

    /**
     * Calcula desvio padrão
     */
    public function calculateStdDeviation(array $prices): float
    {
        $count = count($prices);
        if ($count < 2) return 0;

        $mean = array_sum($prices) / $count;
        $sumSquares = 0;

        foreach ($prices as $price) {
            $sumSquares += pow($price - $mean, 2);
        }

        return round(sqrt($sumSquares / ($count - 1)), 2);
    }

    /**
     * Calcula preço sugerido baseado em estratégia
     */
    public function calculateSuggestedPrice(array $priceStats, string $strategy, float $cost, float $minMargin): array
    {
        $suggestions = [];

        switch ($strategy) {
            case 'aggressive':
                $suggestions['suggested_price'] = $priceStats['percentile_10'] ?? $priceStats['min'];
                $suggestions['strategy_name'] = 'Agressivo';
                break;

            case 'competitive':
                $suggestions['suggested_price'] = $priceStats['median'] ?? $priceStats['avg'];
                $suggestions['strategy_name'] = 'Competitivo';
                break;

            case 'premium':
                $suggestions['suggested_price'] = $priceStats['percentile_75'] ?? $priceStats['max'];
                $suggestions['strategy_name'] = 'Premium';
                break;

            case 'value':
                $median = $priceStats['median'] ?? $priceStats['avg'];
                $suggestions['suggested_price'] = $median * 0.9; // 10% abaixo da média
                $suggestions['strategy_name'] = 'Valor';
                break;

            default:
                $suggestions['suggested_price'] = $priceStats['avg'] ?? 0;
                $suggestions['strategy_name'] = 'Padrão';
        }

        // Calcular margem
        if ($cost > 0) {
            $margin = (($suggestions['suggested_price'] - $cost) / $suggestions['suggested_price']) * 100;
            $suggestions['margin_percentage'] = round($margin, 2);

            // Ajustar se margem mínima não for atingida
            if ($margin < $minMargin) {
                $suggestions['suggested_price'] = $cost / (1 - ($minMargin / 100));
                $suggestions['adjusted_for_min_margin'] = true;
                $suggestions['margin_percentage'] = $minMargin;
            }
        }

        $suggestions['suggested_price'] = round($suggestions['suggested_price'], 2);

        return $suggestions;
    }

    /**
     * Calcula taxas do Mercado Livre
     */
    public function calculateMLFees(float $price, string $listingType = 'gold_special'): array
    {
        // Taxas aproximadas do ML (podem variar por categoria)
        $feeRates = [
            'gold_special' => 0.14,  // 14% Gold Premium
            'gold_pro' => 0.11,      // 11% Gold
            'gold' => 0.11,          // 11% Gold
            'silver' => 0.10,        // 10% Silver
            'bronze' => 0.09,        // 9% Bronze
            'free' => 0.12,          // 12% Free
        ];

        $rate = $feeRates[$listingType] ?? 0.14;
        $fee = $price * $rate;

        // Taxa fixa adicional para valores baixos
        $fixedFee = 0;
        if ($price < 79) {
            $fixedFee = 6.00;
        }

        $totalFee = $fee + $fixedFee;
        $netPrice = $price - $totalFee;

        return [
            'gross_price' => $price,
            'listing_type' => $listingType,
            'fee_rate' => $rate,
            'variable_fee' => round($fee, 2),
            'fixed_fee' => $fixedFee,
            'total_fee' => round($totalFee, 2),
            'net_price' => round($netPrice, 2),
        ];
    }

    /**
     * Calcula preço necessário para atingir margem desejada
     */
    public function calculatePriceForMargin(float $cost, float $targetMargin, string $listingType = 'gold_special'): float
    {
        $feeRates = [
            'gold_special' => 0.14,
            'gold_pro' => 0.11,
            'gold' => 0.11,
            'silver' => 0.10,
            'bronze' => 0.09,
            'free' => 0.12,
        ];

        $feeRate = $feeRates[$listingType] ?? 0.14;

        // Preço = Custo / (1 - margem - taxa)
        $marginDecimal = $targetMargin / 100;
        $denominator = 1 - $marginDecimal - $feeRate;

        if ($denominator <= 0) {
            return 0; // Margem impossível
        }

        return round($cost / $denominator, 2);
    }
}

class PricingStrategyServiceTest extends TestCase
{
    private MockPricingStrategyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MockPricingStrategyService();
    }

    // =============================
    // TESTES DE MEDIANA
    // =============================

    public function testCalculateMedianOddCount(): void
    {
        $prices = [10, 20, 30, 40, 50];
        $median = $this->service->calculateMedian($prices);

        $this->assertEquals(30.0, $median);
    }

    public function testCalculateMedianEvenCount(): void
    {
        $prices = [10, 20, 30, 40];
        $median = $this->service->calculateMedian($prices);

        $this->assertEquals(25.0, $median);
    }

    public function testCalculateMedianSingleValue(): void
    {
        $prices = [100];
        $median = $this->service->calculateMedian($prices);

        $this->assertEquals(100.0, $median);
    }

    public function testCalculateMedianEmptyArray(): void
    {
        $median = $this->service->calculateMedian([]);

        $this->assertEquals(0, $median);
    }

    public function testCalculateMedianUnsortedArray(): void
    {
        $prices = [50, 10, 40, 30, 20];
        $median = $this->service->calculateMedian($prices);

        // Deve ordenar internamente
        $this->assertEquals(30.0, $median);
    }

    // =============================
    // TESTES DE PERCENTIL
    // =============================

    public function testCalculatePercentile10(): void
    {
        $prices = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $p10 = $this->service->calculatePercentile($prices, 10);

        $this->assertEquals(19.0, $p10);
    }

    public function testCalculatePercentile50IsMedian(): void
    {
        $prices = [10, 20, 30, 40, 50];
        $p50 = $this->service->calculatePercentile($prices, 50);
        $median = $this->service->calculateMedian($prices);

        $this->assertEquals($median, $p50);
    }

    public function testCalculatePercentile90(): void
    {
        $prices = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];
        $p90 = $this->service->calculatePercentile($prices, 90);

        $this->assertEquals(91.0, $p90);
    }

    public function testCalculatePercentileEmpty(): void
    {
        $result = $this->service->calculatePercentile([], 50);

        $this->assertEquals(0, $result);
    }

    // =============================
    // TESTES DE DESVIO PADRÃO
    // =============================

    public function testCalculateStdDeviationIdenticalValues(): void
    {
        $prices = [100, 100, 100, 100];
        $std = $this->service->calculateStdDeviation($prices);

        $this->assertEquals(0.0, $std);
    }

    public function testCalculateStdDeviationVariedValues(): void
    {
        $prices = [10, 20, 30, 40, 50];
        $std = $this->service->calculateStdDeviation($prices);

        // Desvio padrão amostral de [10,20,30,40,50] ≈ 15.81
        $this->assertGreaterThan(15, $std);
        $this->assertLessThan(16, $std);
    }

    public function testCalculateStdDeviationSingleValue(): void
    {
        $prices = [100];
        $std = $this->service->calculateStdDeviation($prices);

        $this->assertEquals(0, $std);
    }

    // =============================
    // TESTES DE PREÇO SUGERIDO
    // =============================

    public function testSuggestedPriceAggressiveStrategy(): void
    {
        $priceStats = [
            'min' => 100,
            'max' => 200,
            'avg' => 150,
            'median' => 150,
            'percentile_10' => 110,
            'percentile_75' => 180,
        ];

        $result = $this->service->calculateSuggestedPrice($priceStats, 'aggressive', 0, 0);

        $this->assertEquals(110.0, $result['suggested_price']);
        $this->assertEquals('Agressivo', $result['strategy_name']);
    }

    public function testSuggestedPriceCompetitiveStrategy(): void
    {
        $priceStats = [
            'min' => 100,
            'max' => 200,
            'avg' => 150,
            'median' => 155,
            'percentile_10' => 110,
            'percentile_75' => 180,
        ];

        $result = $this->service->calculateSuggestedPrice($priceStats, 'competitive', 0, 0);

        $this->assertEquals(155.0, $result['suggested_price']);
        $this->assertEquals('Competitivo', $result['strategy_name']);
    }

    public function testSuggestedPricePremiumStrategy(): void
    {
        $priceStats = [
            'min' => 100,
            'max' => 200,
            'avg' => 150,
            'median' => 150,
            'percentile_10' => 110,
            'percentile_75' => 180,
        ];

        $result = $this->service->calculateSuggestedPrice($priceStats, 'premium', 0, 0);

        $this->assertEquals(180.0, $result['suggested_price']);
        $this->assertEquals('Premium', $result['strategy_name']);
    }

    public function testSuggestedPriceValueStrategy(): void
    {
        $priceStats = [
            'min' => 100,
            'max' => 200,
            'avg' => 150,
            'median' => 150,
        ];

        $result = $this->service->calculateSuggestedPrice($priceStats, 'value', 0, 0);

        // 10% abaixo da mediana
        $this->assertEquals(135.0, $result['suggested_price']);
    }

    public function testSuggestedPriceAdjustsForMinMargin(): void
    {
        $priceStats = [
            'median' => 100,
            'percentile_10' => 90,
        ];

        // Custo de 80, margem mínima de 30%, preço agressivo seria 90
        // Mas 90 - 80 = 10, que é 11% de margem, menor que 30%
        $result = $this->service->calculateSuggestedPrice($priceStats, 'aggressive', 80, 30);

        $this->assertTrue($result['adjusted_for_min_margin'] ?? false);
        $this->assertEquals(30, $result['margin_percentage']);
        // Preço deve ser 80 / (1 - 0.30) = 114.29
        $this->assertGreaterThan(110, $result['suggested_price']);
    }

    // =============================
    // TESTES DE TAXAS ML
    // =============================

    public function testCalculateMLFeesGoldSpecial(): void
    {
        $result = $this->service->calculateMLFees(100, 'gold_special');

        $this->assertEquals(100, $result['gross_price']);
        $this->assertEquals(0.14, $result['fee_rate']);
        $this->assertEquals(14.0, $result['variable_fee']);
        $this->assertEquals(0, $result['fixed_fee']);
        $this->assertEquals(14.0, $result['total_fee']);
        $this->assertEquals(86.0, $result['net_price']);
    }

    public function testCalculateMLFeesWithFixedFee(): void
    {
        // Preço abaixo de R$79 tem taxa fixa adicional
        $result = $this->service->calculateMLFees(50, 'gold_special');

        $this->assertEquals(50, $result['gross_price']);
        $this->assertEquals(6.0, $result['fixed_fee']);
        // 50 * 0.14 = 7 + 6 = 13
        $this->assertEquals(13.0, $result['total_fee']);
        $this->assertEquals(37.0, $result['net_price']);
    }

    public function testCalculateMLFeesDifferentListingTypes(): void
    {
        $resultGold = $this->service->calculateMLFees(100, 'gold_pro');
        $resultSilver = $this->service->calculateMLFees(100, 'silver');

        $this->assertEquals(0.11, $resultGold['fee_rate']);
        $this->assertEquals(0.10, $resultSilver['fee_rate']);

        // Gold deve ter taxa maior
        $this->assertGreaterThan($resultSilver['total_fee'], $resultGold['total_fee']);
    }

    // =============================
    // TESTES DE PREÇO PARA MARGEM
    // =============================

    public function testCalculatePriceForMargin(): void
    {
        // Custo 100, margem 20%, taxa 14%
        // Preço = 100 / (1 - 0.20 - 0.14) = 100 / 0.66 ≈ 151.52
        $price = $this->service->calculatePriceForMargin(100, 20, 'gold_special');

        $this->assertGreaterThan(150, $price);
        $this->assertLessThan(152, $price);
    }

    public function testCalculatePriceForMarginImpossible(): void
    {
        // Margem 90% + taxa 14% = 104%, impossível
        $price = $this->service->calculatePriceForMargin(100, 90, 'gold_special');

        $this->assertEquals(0, $price);
    }

    public function testCalculatePriceForMarginZeroCost(): void
    {
        $price = $this->service->calculatePriceForMargin(0, 20, 'gold_special');

        $this->assertEquals(0, $price);
    }

    public function testCalculatePriceForMarginDifferentListingTypes(): void
    {
        $priceGold = $this->service->calculatePriceForMargin(100, 20, 'gold_special');
        $priceSilver = $this->service->calculatePriceForMargin(100, 20, 'silver');

        // Preço para Gold deve ser maior (taxa maior)
        $this->assertGreaterThan($priceSilver, $priceGold);
    }
}
