<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;

/**
 * Structural tests for CloneAdvancedController
 * @covers \App\Controllers\CloneAdvancedController
 */
class CloneAdvancedControllerTest extends TestCase
{
    private \ReflectionClass $ref;

    protected function setUp(): void
    {
        $this->ref = new \ReflectionClass(\App\Controllers\CloneAdvancedController::class);
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\CloneAdvancedController::class));
    }

    public function testHasStrictTypes(): void
    {
        $file = $this->ref->getFileName();
        $this->assertNotFalse($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testNamespace(): void
    {
        $this->assertSame('App\\Controllers', $this->ref->getNamespaceName());
    }

    /**
     * @dataProvider publicMethodProvider
     */
    public function testPublicMethodExists(string $method): void
    {
        $this->assertTrue(
            $this->ref->hasMethod($method),
            "Missing public method: {$method}"
        );
        $this->assertTrue(
            $this->ref->getMethod($method)->isPublic(),
            "Method {$method} should be public"
        );
    }

    public static function publicMethodProvider(): array
    {
        return [
            ['__construct'],
            ['analyzeSeo'],
            ['analyzeBatchSeo'],
            ['optimizeTitle'],
            ['optimizeDescription'],
            ['getSeoSettings'],
            ['updateSeoSettings'],
            ['exportItemsCsv'],
            ['exportItemsJson'],
            ['exportJobs'],
            ['exportMetrics'],
            ['exportFullReport'],
            ['listExports'],
            ['downloadExport'],
            ['getHealth'],
            ['getDiagnostics'],
            ['batchRepricing'],
            ['batchStockUpdate'],
            ['batchStatusChange'],
            ['batchTitleUpdate'],
            ['batchPriceUpdate'],
            ['batchSyncMetrics'],
            ['batchSeoOptimize'],
            ['closeStaleItems'],
            ['getBatchHistory'],
            ['getAnalyticsSummary'],
            ['getPerformance'],
            ['getTrends'],
        ];
    }

    public function testPublicMethodCount(): void
    {
        $publicMethods = array_filter(
            $this->ref->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn(\ReflectionMethod $m) => $m->getDeclaringClass()->getName() === \App\Controllers\CloneAdvancedController::class
        );
        $this->assertGreaterThanOrEqual(28, count($publicMethods));
    }

    public function testHasAccountIdProperty(): void
    {
        $this->assertTrue($this->ref->hasProperty('accountId'));
        $prop = $this->ref->getProperty('accountId');
        $this->assertTrue($prop->isPrivate());
    }

    public function testHasRequestProperty(): void
    {
        $this->assertTrue($this->ref->hasProperty('request'));
        $prop = $this->ref->getProperty('request');
        $this->assertTrue($prop->isPrivate());
    }

    public function testDownloadExportAcceptsStringParameter(): void
    {
        $method = $this->ref->getMethod('downloadExport');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('filename', $params[0]->getName());
    }
}
