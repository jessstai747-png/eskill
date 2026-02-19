<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CompetitorAnalysisService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit Tests for CompetitorAnalysisService
 *
 * Tests private pure-logic methods via reflection:
 * - calculateCompetitionIndex: normalized seller/result scoring
 * - mapCompetitionLevel: index to level mapping
 * - calculateTop3MarketShare: top-3 seller market share
 * - assessMarketHealth/EntryBarrier/GrowthPotential: market assessment
 * - identifyRiskFactors: risk identification
 *
 * @covers \App\Services\CompetitorAnalysisService
 */
class CompetitorAnalysisServiceTest extends TestCase
{
    private CompetitorAnalysisService $service;
    private ReflectionClass $ref;
    private ReflectionMethod $calculateCompetitionIndex;
    private ReflectionMethod $mapCompetitionLevel;
    private ReflectionMethod $calculateTop3MarketShare;
    private ReflectionMethod $assessMarketHealth;
    private ReflectionMethod $assessEntryBarrier;
    private ReflectionMethod $assessGrowthPotential;
    private ReflectionMethod $identifyRiskFactors;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(CompetitorAnalysisService::class);
        $this->service = $this->ref->newInstanceWithoutConstructor();

        $methods = [
            'calculateCompetitionIndex',
            'mapCompetitionLevel',
            'calculateTop3MarketShare',
            'assessMarketHealth',
            'assessEntryBarrier',
            'assessGrowthPotential',
            'identifyRiskFactors',
        ];

        foreach ($methods as $method) {
            $this->$method = $this->ref->getMethod($method);
            $this->$method->setAccessible(true);
        }
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(CompetitorAnalysisService::class, $this->service);
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['analyzeCompetition', 'detectOpportunities'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->service, $method), "Missing: {$method}");
        }
    }

    // =========================================================================
    // calculateCompetitionIndex — PRIVATE PURE MATH
    // =========================================================================

    public function testCalculateCompetitionIndexZeroSellersZeroResults(): void
    {
        $result = $this->calculateCompetitionIndex->invoke($this->service, 0, 0);
        $this->assertSame(0, $result);
    }

    public function testCalculateCompetitionIndexFewSellers(): void
    {
        // 5 sellers → normalized = min(100, 5*4) = 20
        // 50 results → normalized = min(100, (50/200)*100) = 25
        // max(20, 25) = 25
        $result = $this->calculateCompetitionIndex->invoke($this->service, 5, 50);
        $this->assertSame(25, $result);
    }

    public function testCalculateCompetitionIndexManySellers(): void
    {
        // 25 sellers → normalized = min(100, 25*4) = 100
        // 100 results → normalized = min(100, (100/200)*100) = 50
        // max(100, 50) = 100
        $result = $this->calculateCompetitionIndex->invoke($this->service, 25, 100);
        $this->assertSame(100, $result);
    }

    public function testCalculateCompetitionIndexHighResults(): void
    {
        // 2 sellers → normalized = min(100, 2*4) = 8
        // 500 results → normalized = min(100, (500/200)*100) = 100 (capped)
        // max(8, 100) = 100
        $result = $this->calculateCompetitionIndex->invoke($this->service, 2, 500);
        $this->assertSame(100, $result);
    }

    public function testCalculateCompetitionIndexMediumRange(): void
    {
        // 10 sellers → normalized = min(100, 10*4) = 40
        // 80 results → normalized = min(100, (80/200)*100) = 40
        // max(40, 40) = 40
        $result = $this->calculateCompetitionIndex->invoke($this->service, 10, 80);
        $this->assertSame(40, $result);
    }

    public function testCalculateCompetitionIndexSellersCapAtHundred(): void
    {
        // 50 sellers → normalized = min(100, 50*4) = min(100, 200) = 100
        $result = $this->calculateCompetitionIndex->invoke($this->service, 50, 0);
        $this->assertSame(100, $result);
    }

    // =========================================================================
    // mapCompetitionLevel — PRIVATE PURE MAPPING
    // =========================================================================

    public function testMapCompetitionLevelVeryHigh(): void
    {
        $this->assertSame('very_high', $this->mapCompetitionLevel->invoke($this->service, 80));
        $this->assertSame('very_high', $this->mapCompetitionLevel->invoke($this->service, 100));
        $this->assertSame('very_high', $this->mapCompetitionLevel->invoke($this->service, 95));
    }

    public function testMapCompetitionLevelHigh(): void
    {
        $this->assertSame('high', $this->mapCompetitionLevel->invoke($this->service, 60));
        $this->assertSame('high', $this->mapCompetitionLevel->invoke($this->service, 79));
        $this->assertSame('high', $this->mapCompetitionLevel->invoke($this->service, 70));
    }

    public function testMapCompetitionLevelMedium(): void
    {
        $this->assertSame('medium', $this->mapCompetitionLevel->invoke($this->service, 40));
        $this->assertSame('medium', $this->mapCompetitionLevel->invoke($this->service, 59));
        $this->assertSame('medium', $this->mapCompetitionLevel->invoke($this->service, 50));
    }

    public function testMapCompetitionLevelLow(): void
    {
        $this->assertSame('low', $this->mapCompetitionLevel->invoke($this->service, 20));
        $this->assertSame('low', $this->mapCompetitionLevel->invoke($this->service, 39));
        $this->assertSame('low', $this->mapCompetitionLevel->invoke($this->service, 30));
    }

    public function testMapCompetitionLevelVeryLow(): void
    {
        $this->assertSame('very_low', $this->mapCompetitionLevel->invoke($this->service, 0));
        $this->assertSame('very_low', $this->mapCompetitionLevel->invoke($this->service, 19));
        $this->assertSame('very_low', $this->mapCompetitionLevel->invoke($this->service, 10));
    }

    // =========================================================================
    // calculateTop3MarketShare — PRIVATE PURE MATH
    // =========================================================================

    public function testCalculateTop3MarketShareNormal(): void
    {
        $sellers = [
            ['total_sales' => 100],
            ['total_sales' => 50],
            ['total_sales' => 30],
            ['total_sales' => 20],
        ];

        // total = 200, top3 = 180, share = 90%
        $result = $this->calculateTop3MarketShare->invoke($this->service, $sellers);
        $this->assertEqualsWithDelta(90.0, $result, 0.01);
    }

    public function testCalculateTop3MarketShareAllInTop3(): void
    {
        $sellers = [
            ['total_sales' => 100],
            ['total_sales' => 50],
            ['total_sales' => 30],
        ];

        // total = 180, top3 = 180, share = 100%
        $result = $this->calculateTop3MarketShare->invoke($this->service, $sellers);
        $this->assertEqualsWithDelta(100.0, $result, 0.01);
    }

    public function testCalculateTop3MarketShareSingleSeller(): void
    {
        $sellers = [
            ['total_sales' => 500],
        ];

        // total = 500, top3(only 1) = 500, share = 100%
        $result = $this->calculateTop3MarketShare->invoke($this->service, $sellers);
        $this->assertEqualsWithDelta(100.0, $result, 0.01);
    }

    public function testCalculateTop3MarketShareZeroSales(): void
    {
        $sellers = [
            ['total_sales' => 0],
            ['total_sales' => 0],
        ];

        // total = 0 → return 0
        $result = $this->calculateTop3MarketShare->invoke($this->service, $sellers);
        $this->assertSame(0.0, $result);
    }

    public function testCalculateTop3MarketShareEmpty(): void
    {
        $result = $this->calculateTop3MarketShare->invoke($this->service, []);
        $this->assertSame(0.0, $result);
    }

    public function testCalculateTop3MarketShareEvenDistribution(): void
    {
        $sellers = [
            ['total_sales' => 25],
            ['total_sales' => 25],
            ['total_sales' => 25],
            ['total_sales' => 25],
        ];

        // total = 100, top3 = 75, share = 75%
        $result = $this->calculateTop3MarketShare->invoke($this->service, $sellers);
        $this->assertEqualsWithDelta(75.0, $result, 0.01);
    }

    // =========================================================================
    // assessMarketHealth — PRIVATE PURE LOGIC
    // =========================================================================

    public function testAssessMarketHealthHealthyGrowth(): void
    {
        $competition = ['competition_level' => 'low', 'market_avg_price' => 600];
        $result = $this->assessMarketHealth->invoke($this->service, $competition);
        $this->assertSame('healthy_growth', $result);
    }

    public function testAssessMarketHealthSaturated(): void
    {
        $competition = ['competition_level' => 'very_high', 'market_avg_price' => 100];
        $result = $this->assessMarketHealth->invoke($this->service, $competition);
        $this->assertSame('saturated', $result);
    }

    public function testAssessMarketHealthStable(): void
    {
        $competition = ['competition_level' => 'medium', 'market_avg_price' => 200];
        $result = $this->assessMarketHealth->invoke($this->service, $competition);
        $this->assertSame('stable', $result);
    }

    public function testAssessMarketHealthLowCompLowPrice(): void
    {
        // Low competition but price <= 500 is NOT healthy_growth
        $competition = ['competition_level' => 'low', 'market_avg_price' => 300];
        $result = $this->assessMarketHealth->invoke($this->service, $competition);
        $this->assertSame('stable', $result);
    }

    // =========================================================================
    // assessEntryBarrier — PRIVATE PURE LOGIC
    // =========================================================================

    public function testAssessEntryBarrierHigh(): void
    {
        $competition = ['competition_level' => 'very_high'];
        $result = $this->assessEntryBarrier->invoke($this->service, $competition);
        $this->assertSame('high', $result);
    }

    public function testAssessEntryBarrierLow(): void
    {
        $competition = ['competition_level' => 'low'];
        $result = $this->assessEntryBarrier->invoke($this->service, $competition);
        $this->assertSame('low', $result);
    }

    public function testAssessEntryBarrierMedium(): void
    {
        $competition = ['competition_level' => 'medium'];
        $result = $this->assessEntryBarrier->invoke($this->service, $competition);
        $this->assertSame('medium', $result);
    }

    // =========================================================================
    // assessGrowthPotential — PRIVATE PURE LOGIC
    // =========================================================================

    public function testAssessGrowthPotentialHigh(): void
    {
        $competition = ['competition_level' => 'low', 'market_avg_price' => 500];
        $result = $this->assessGrowthPotential->invoke($this->service, $competition);
        $this->assertSame('high', $result);
    }

    public function testAssessGrowthPotentialLow(): void
    {
        $competition = ['competition_level' => 'very_high', 'market_avg_price' => 100];
        $result = $this->assessGrowthPotential->invoke($this->service, $competition);
        $this->assertSame('low', $result);
    }

    public function testAssessGrowthPotentialMedium(): void
    {
        $competition = ['competition_level' => 'medium', 'market_avg_price' => 200];
        $result = $this->assessGrowthPotential->invoke($this->service, $competition);
        $this->assertSame('medium', $result);
    }

    public function testAssessGrowthPotentialLowCompLowPrice(): void
    {
        // Low competition but low price → not high
        $competition = ['competition_level' => 'low', 'market_avg_price' => 100];
        $result = $this->assessGrowthPotential->invoke($this->service, $competition);
        $this->assertSame('medium', $result);
    }

    // =========================================================================
    // identifyRiskFactors — PRIVATE PURE LOGIC
    // =========================================================================

    public function testIdentifyRiskFactorsHighCompetition(): void
    {
        $competition = ['competition_level' => 'very_high', 'market_share_top3' => 50];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertContains('alta_concorrencia', $result);
        $this->assertNotContains('dominancia_de_mercado', $result);
    }

    public function testIdentifyRiskFactorsMarketDominance(): void
    {
        $competition = ['competition_level' => 'low', 'market_share_top3' => 80];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertNotContains('alta_concorrencia', $result);
        $this->assertContains('dominancia_de_mercado', $result);
    }

    public function testIdentifyRiskFactorsBothRisks(): void
    {
        $competition = ['competition_level' => 'very_high', 'market_share_top3' => 75];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertContains('alta_concorrencia', $result);
        $this->assertContains('dominancia_de_mercado', $result);
    }

    public function testIdentifyRiskFactorsNoRisks(): void
    {
        $competition = ['competition_level' => 'low', 'market_share_top3' => 40];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertEmpty($result);
    }

    public function testIdentifyRiskFactorsExactThreshold(): void
    {
        // market_share_top3 > 70 → exactly 70 should NOT trigger
        $competition = ['competition_level' => 'medium', 'market_share_top3' => 70];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertNotContains('dominancia_de_mercado', $result);
    }

    public function testIdentifyRiskFactorsJustAboveThreshold(): void
    {
        // market_share_top3 > 70 → 70.01 should trigger
        $competition = ['competition_level' => 'medium', 'market_share_top3' => 70.01];
        $result = $this->identifyRiskFactors->invoke($this->service, $competition);

        $this->assertContains('dominancia_de_mercado', $result);
    }
}
