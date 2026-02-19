<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\CloneAnalyticsController
 */
class CloneAnalyticsControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\CloneAnalyticsController::class);
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\CloneAnalyticsController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testNamespace(): void
    {
        $this->assertSame('App\\Controllers', $this->reflection->getNamespaceName());
    }

    /**
     * @dataProvider publicEndpointsProvider
     */
    public function testPublicEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicEndpointsProvider(): array
    {
        return [
            ['__construct'],
            ['getDashboard'],
            ['getKPIs'],
            ['getTrends'],
            ['getPerformance'],
            ['getBreakdown'],
            ['comparePeriods'],
            ['getProjection'],
            ['trackEvent'],
            ['getEvents'],
        ];
    }
}
