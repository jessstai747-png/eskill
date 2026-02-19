<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\SEOKillerController
 */
class SEOKillerControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\SEOKillerController::class);
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\SEOKillerController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testExtendsBaseController(): void
    {
        $parent = $this->reflection->getParentClass();
        $this->assertNotFalse($parent);
        $this->assertSame('App\\Controllers\\BaseController', $parent->getName());
    }

    public function testHasConstructor(): void
    {
        $this->assertNotNull($this->reflection->getConstructor());
    }

    /**
     * @dataProvider corePublicMethodsProvider
     */
    public function testCorePublicMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function corePublicMethodsProvider(): array
    {
        return [
            ['diagnose'],
            ['generateTitle'],
            ['fillAttributes'],
            ['generateDescription'],
            ['analyzeDescription'],
            ['optimizeItem'],
            ['sync'],
            ['completenessReport'],
            ['researchKeywords'],
            ['spyCompetitors'],
        ];
    }

    /**
     * @dataProvider bulkMethodsProvider
     */
    public function testBulkMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function bulkMethodsProvider(): array
    {
        return [
            ['bulkSelect'],
            ['bulkStart'],
            ['bulkProcess'],
            ['bulkStatus'],
            ['bulkJobs'],
            ['bulkMonitor'],
            ['bulkCancel'],
            ['bulkRetry'],
        ];
    }

    /**
     * @dataProvider autopilotMethodsProvider
     */
    public function testAutopilotMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function autopilotMethodsProvider(): array
    {
        return [
            ['getAutopilotConfig'],
            ['saveAutopilotConfig'],
            ['enableAutopilot'],
            ['disableAutopilot'],
            ['runAutopilot'],
            ['autopilotHistory'],
            ['autopilotStats'],
            ['getAutopilotStatus'],
            ['getAutopilotRealStatus'],
        ];
    }

    /**
     * @dataProvider performanceMethodsProvider
     */
    public function testPerformanceMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function performanceMethodsProvider(): array
    {
        return [
            ['getPerformanceDashboard'],
            ['getItemPerformance'],
            ['compareBeforeAfter'],
            ['getTopPerformers'],
            ['getConsolidatedMetrics'],
            ['getMetricsEvolution'],
            ['getCategoryPerformance'],
            ['exportPerformanceReport'],
        ];
    }

    /**
     * @dataProvider abTestMethodsProvider
     */
    public function testABTestMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function abTestMethodsProvider(): array
    {
        return [
            ['createABTest'],
            ['createTitleABTest'],
            ['listABTests'],
            ['stopABTest'],
            ['getABTestAnalysis'],
            ['getABTest'],
            ['applyABTestWinner'],
        ];
    }

    /**
     * @dataProvider strategiesMethodsProvider
     */
    public function testStrategiesMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function strategiesMethodsProvider(): array
    {
        return [
            ['runStrategiesAnalysis'],
            ['getStrategiesScore'],
            ['optimizeWithStrategies'],
            ['batchStrategiesAnalysis'],
            ['getStrategiesDashboard'],
            ['getStrategiesCacheStats'],
            ['clearStrategiesCache'],
        ];
    }

    /**
     * @dataProvider advancedMethodsProvider
     */
    public function testAdvancedMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function advancedMethodsProvider(): array
    {
        return [
            ['advancedMaximizeSEO'],
            ['predictPerformance'],
            ['intelligentAutoOptimize'],
            ['advancedKeywordsAnalysis'],
            ['advancedCompetitorAnalysis'],
            ['getOptimizationStats'],
        ];
    }

    public function testHasOver90PublicMethods(): void
    {
        $methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $ownMethods = array_filter($methods, fn($m) => $m->getDeclaringClass()->getName() === $this->reflection->getName());
        $this->assertGreaterThanOrEqual(90, count($ownMethods));
    }

    public function testAllPublicMethodsReturnVoid(): void
    {
        $methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $ownMethods = array_filter($methods, fn($m) => $m->getDeclaringClass()->getName() === $this->reflection->getName() && $m->getName() !== '__construct');
        foreach ($ownMethods as $method) {
            $retType = $method->getReturnType();
            if ($retType !== null) {
                $this->assertSame('void', $retType->getName(), "Method {$method->getName()} should return void");
            }
        }
    }

    // =========================================================================
    // Behavioral: getDefaultSettings
    // =========================================================================

    public function testGetDefaultSettingsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('getDefaultSettings');
        $this->assertIsArray($result);
    }

    public function testGetDefaultSettingsHasProviders(): void
    {
        $result = $this->invokePrivateMethod('getDefaultSettings');
        $this->assertArrayHasKey('providers', $result);
        $this->assertArrayHasKey('openai', $result['providers']);
        $this->assertArrayHasKey('claude', $result['providers']);
    }

    public function testGetDefaultSettingsHasBudget(): void
    {
        $result = $this->invokePrivateMethod('getDefaultSettings');
        $this->assertArrayHasKey('budget', $result);
        $this->assertArrayHasKey('monthly', $result['budget']);
        $this->assertArrayHasKey('alert_threshold', $result['budget']);
    }

    public function testGetDefaultSettingsHasAutomation(): void
    {
        $result = $this->invokePrivateMethod('getDefaultSettings');
        $this->assertArrayHasKey('automation', $result);
        $this->assertArrayHasKey('auto_new_items', $result['automation']);
        $this->assertArrayHasKey('min_score', $result['automation']);
    }

    public function testGetDefaultSettingsHasPreferences(): void
    {
        $result = $this->invokePrivateMethod('getDefaultSettings');
        $this->assertArrayHasKey('preferences', $result);
    }

    // =========================================================================
    // Behavioral: extractAttribute
    // =========================================================================

    public function testExtractAttributeFindsAttribute(): void
    {
        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Honda'],
                ['id' => 'MODEL', 'value_name' => 'CG 160'],
            ]
        ];
        $result = $this->invokePrivateMethod('extractAttribute', [$item, 'BRAND']);
        $this->assertSame('Honda', $result);
    }

    public function testExtractAttributeReturnsNullWhenNotFound(): void
    {
        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Honda'],
            ]
        ];
        $result = $this->invokePrivateMethod('extractAttribute', [$item, 'COLOR']);
        $this->assertNull($result);
    }

    public function testExtractAttributeFallsBackToValueId(): void
    {
        $item = [
            'attributes' => [
                ['id' => 'COLOR', 'value_id' => 'RED'],
            ]
        ];
        $result = $this->invokePrivateMethod('extractAttribute', [$item, 'COLOR']);
        $this->assertSame('RED', $result);
    }

    public function testExtractAttributeEmptyAttributes(): void
    {
        $item = [];
        $result = $this->invokePrivateMethod('extractAttribute', [$item, 'BRAND']);
        $this->assertNull($result);
    }

    // =========================================================================
    // Structural: Catch blocks in controller
    // =========================================================================

    public function testHasTryCatchBlocks(): void
    {
        $file = $this->reflection->getFileName();
        $source = file_get_contents($file);
        $catchCount = substr_count($source, 'catch (');
        $this->assertGreaterThanOrEqual(5, $catchCount);
    }
}
