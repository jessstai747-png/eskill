<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\FeatureFlagService;
use App\Services\MercadoLivre\AdvancedPricingEngine;
use App\Services\MercadoLivre\MLAnalyticsIntelligenceService;
use App\Services\MercadoLivre\MlAdvancedFeaturesService;
use Tests\TestCase;

/**
 * @covers \App\Services\MercadoLivre\MlAdvancedFeaturesService
 */
class MlAdvancedFeaturesServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Creates a FeatureFlagService mock with createFlag returning true by default.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&FeatureFlagService
     */
    private function mockFlags(bool $pricingEnabled = true, bool $analyticsEnabled = true): FeatureFlagService
    {
        $mock = $this->createMock(FeatureFlagService::class);
        $mock->method('createFlag')->willReturn(true);
        $mock->method('isEnabled')->willReturnMap([
            [MlAdvancedFeaturesService::FLAG_PRICING,   $pricingEnabled],
            [MlAdvancedFeaturesService::FLAG_ANALYTICS, $analyticsEnabled],
        ]);
        return $mock;
    }

    /**
     * Creates a no-constructor AdvancedPricingEngine mock.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&AdvancedPricingEngine
     */
    private function mockEngine(): AdvancedPricingEngine
    {
        return $this->getMockBuilder(AdvancedPricingEngine::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * Creates a no-constructor MLAnalyticsIntelligenceService mock.
     *
     * @return \PHPUnit\Framework\MockObject\MockObject&MLAnalyticsIntelligenceService
     */
    private function mockAnalytics(): MLAnalyticsIntelligenceService
    {
        return $this->getMockBuilder(MLAnalyticsIntelligenceService::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    // -----------------------------------------------------------------------
    // runAdvancedPricing — feature flag
    // -----------------------------------------------------------------------

    public function testRunAdvancedPricingReturnsSkippedWhenFlagDisabled(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(pricingEnabled: false));

        $result = $svc->runAdvancedPricing(1);

        $this->assertTrue($result['skipped']);
        $this->assertSame('feature_disabled', $result['reason']);
    }

    public function testRunAdvancedPricingDoesNotInvokeEngineWhenFlagDisabled(): void
    {
        $engine = $this->mockEngine();
        $engine->expects($this->never())->method('startDynamicPricing');

        $svc = new MlAdvancedFeaturesService($this->mockFlags(pricingEnabled: false));
        $svc->runAdvancedPricing(1, [], $engine);
    }

    // -----------------------------------------------------------------------
    // runAdvancedPricing — delegation
    // -----------------------------------------------------------------------

    public function testRunAdvancedPricingDelegatesToEngineWhenEnabled(): void
    {
        $expected = ['success' => true, 'price_changes' => 3];

        $engine = $this->mockEngine();
        $engine->method('startDynamicPricing')->willReturn($expected);

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedPricing(1, [], $engine);

        $this->assertSame($expected, $result);
    }

    public function testRunAdvancedPricingPassesRulesToEngine(): void
    {
        $rules = ['min_adjustment_threshold' => 0.02];

        $engine = $this->mockEngine();
        $engine->expects($this->once())
               ->method('startDynamicPricing')
               ->with($rules)
               ->willReturn(['success' => true]);

        $svc = new MlAdvancedFeaturesService($this->mockFlags());
        $svc->runAdvancedPricing(5, $rules, $engine);
    }

    // -----------------------------------------------------------------------
    // runAdvancedPricing — isolation
    // -----------------------------------------------------------------------

    public function testRunAdvancedPricingReturnsSafeResponseOnException(): void
    {
        $engine = $this->mockEngine();
        $engine->method('startDynamicPricing')->willThrowException(new \RuntimeException('ML API down'));

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedPricing(1, [], $engine);

        $this->assertTrue($result['skipped']);
        $this->assertSame('execution_error', $result['reason']);
        $this->assertSame('ML API down', $result['error']);
    }

    public function testRunAdvancedPricingIsolatesError(): void
    {
        // Verify that even a \Error (not just \Exception) is caught
        $engine = $this->mockEngine();
        $engine->method('startDynamicPricing')->willThrowException(new \Error('fatal'));

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedPricing(1, [], $engine);

        $this->assertTrue($result['skipped']);
        $this->assertSame('execution_error', $result['reason']);
    }

    // -----------------------------------------------------------------------
    // runAdvancedAnalytics — feature flag
    // -----------------------------------------------------------------------

    public function testRunAdvancedAnalyticsReturnsSkippedWhenFlagDisabled(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(analyticsEnabled: false));

        $result = $svc->runAdvancedAnalytics(1);

        $this->assertTrue($result['skipped']);
        $this->assertSame('feature_disabled', $result['reason']);
    }

    public function testRunAdvancedAnalyticsDoesNotInvokeServiceWhenFlagDisabled(): void
    {
        $analytics = $this->mockAnalytics();
        $analytics->expects($this->never())->method('getComprehensiveAnalytics');

        $svc = new MlAdvancedFeaturesService($this->mockFlags(analyticsEnabled: false));
        $svc->runAdvancedAnalytics(1, [], $analytics);
    }

    // -----------------------------------------------------------------------
    // runAdvancedAnalytics — delegation
    // -----------------------------------------------------------------------

    public function testRunAdvancedAnalyticsDelegatesToServiceWhenEnabled(): void
    {
        $expected = ['success' => true, 'analytics' => ['performance_overview' => []]];

        $analytics = $this->mockAnalytics();
        $analytics->method('getComprehensiveAnalytics')->willReturn($expected);

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedAnalytics(1, [], $analytics);

        $this->assertSame($expected, $result);
    }

    public function testRunAdvancedAnalyticsPassesFiltersToService(): void
    {
        $filters = ['period' => 'last_7_days'];

        $analytics = $this->mockAnalytics();
        $analytics->expects($this->once())
                  ->method('getComprehensiveAnalytics')
                  ->with($filters)
                  ->willReturn(['success' => true]);

        $svc = new MlAdvancedFeaturesService($this->mockFlags());
        $svc->runAdvancedAnalytics(3, $filters, $analytics);
    }

    // -----------------------------------------------------------------------
    // runAdvancedAnalytics — isolation
    // -----------------------------------------------------------------------

    public function testRunAdvancedAnalyticsReturnsSafeResponseOnException(): void
    {
        $analytics = $this->mockAnalytics();
        $analytics->method('getComprehensiveAnalytics')->willThrowException(new \RuntimeException('DB timeout'));

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedAnalytics(1, [], $analytics);

        $this->assertTrue($result['skipped']);
        $this->assertSame('execution_error', $result['reason']);
        $this->assertSame('DB timeout', $result['error']);
    }

    public function testRunAdvancedAnalyticsIsolatesError(): void
    {
        $analytics = $this->mockAnalytics();
        $analytics->method('getComprehensiveAnalytics')->willThrowException(new \Error('fatal error'));

        $svc    = new MlAdvancedFeaturesService($this->mockFlags());
        $result = $svc->runAdvancedAnalytics(1, [], $analytics);

        $this->assertTrue($result['skipped']);
        $this->assertSame('execution_error', $result['reason']);
    }

    // -----------------------------------------------------------------------
    // Boolean helpers
    // -----------------------------------------------------------------------

    public function testIsPricingEnabledDelegatesToFlagService(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(pricingEnabled: true));
        $this->assertTrue($svc->isPricingEnabled());
    }

    public function testIsPricingEnabledReturnsFalseWhenDisabled(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(pricingEnabled: false));
        $this->assertFalse($svc->isPricingEnabled());
    }

    public function testIsAnalyticsEnabledDelegatesToFlagService(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(analyticsEnabled: true));
        $this->assertTrue($svc->isAnalyticsEnabled());
    }

    public function testIsAnalyticsEnabledReturnsFalseWhenDisabled(): void
    {
        $svc = new MlAdvancedFeaturesService($this->mockFlags(analyticsEnabled: false));
        $this->assertFalse($svc->isAnalyticsEnabled());
    }

    // -----------------------------------------------------------------------
    // Flag registration
    // -----------------------------------------------------------------------

    public function testFlagConstantsMatchExpectedNames(): void
    {
        $this->assertSame('ml_advanced_pricing_enabled',   MlAdvancedFeaturesService::FLAG_PRICING);
        $this->assertSame('ml_advanced_analytics_enabled', MlAdvancedFeaturesService::FLAG_ANALYTICS);
    }

    public function testBothFlagsAreRegisteredOnConstruction(): void
    {
        $flags = $this->createMock(FeatureFlagService::class);
        $flags->expects($this->exactly(2))
              ->method('createFlag');

        new MlAdvancedFeaturesService($flags);
    }
}
