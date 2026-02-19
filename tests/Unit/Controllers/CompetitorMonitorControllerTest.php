<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\CompetitorMonitorController;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for CompetitorMonitorController
 *
 * Tests pure helper methods via reflection:
 * - getTimeAgo: relative time formatting
 * - Public method existence
 *
 * @covers \App\Controllers\CompetitorMonitorController
 */
class CompetitorMonitorControllerTest extends TestCase
{
    private CompetitorMonitorController $controller;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(CompetitorMonitorController::class);
        $this->controller = $this->ref->newInstanceWithoutConstructor();
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testControllerCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(CompetitorMonitorController::class, $this->controller);
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'getTracked',
            'getAlerts',
            'getStats',
            'track',
            'startMonitoring',
            'pauseMonitoring',
            'toggleMonitoring',
            'remove',
            'markAlertRead',
            'saveSettings',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                $this->ref->hasMethod($method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // getTimeAgo — PRIVATE PURE DATETIME
    // =========================================================================

    public function testGetTimeAgoNow(): void
    {
        $method = $this->ref->getMethod('getTimeAgo');
        $method->setAccessible(true);

        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $now);

        $this->assertSame('agora', $result);
    }

    public function testGetTimeAgoMinutes(): void
    {
        $method = $this->ref->getMethod('getTimeAgo');
        $method->setAccessible(true);

        $time = (new \DateTime())->modify('-15 minutes')->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $time);

        $this->assertStringContainsString('min', $result);
        $this->assertStringContainsString('atrás', $result);
    }

    public function testGetTimeAgoHours(): void
    {
        $method = $this->ref->getMethod('getTimeAgo');
        $method->setAccessible(true);

        $time = (new \DateTime())->modify('-3 hours')->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $time);

        $this->assertStringContainsString('h atrás', $result);
    }

    public function testGetTimeAgoDays(): void
    {
        $method = $this->ref->getMethod('getTimeAgo');
        $method->setAccessible(true);

        $time = (new \DateTime())->modify('-5 days')->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $time);

        $this->assertStringContainsString('d atrás', $result);
    }

    public function testGetTimeAgoOneMinute(): void
    {
        $method = $this->ref->getMethod('getTimeAgo');
        $method->setAccessible(true);

        $time = (new \DateTime())->modify('-1 minute')->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $time);

        // Should be "1min atrás"
        $this->assertStringContainsString('min atrás', $result);
    }

    // =========================================================================
    // CLASS STRUCTURE
    // =========================================================================

    public function testControllerHasExpectedProperties(): void
    {
        $expectedProps = ['db', 'accountId', 'request'];

        foreach ($expectedProps as $prop) {
            $this->assertTrue(
                $this->ref->hasProperty($prop),
                "Missing property: {$prop}"
            );
        }
    }
}
