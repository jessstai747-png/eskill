<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\AICenterController;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for AICenterController
 *
 * Tests pure helper methods via reflection:
 * - formatUptime: DateTime formatting
 * - Public method existence and structure
 *
 * @covers \App\Controllers\AICenterController
 */
class AICenterControllerTest extends TestCase
{
    private AICenterController $controller;
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(AICenterController::class);
        $this->controller = $this->ref->newInstanceWithoutConstructor();
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testControllerCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(AICenterController::class, $this->controller);
    }

    public function testPublicMethodsExist(): void
    {
        $methods = [
            'index',
            'getOverviewStats',
            'getAutomationStatus',
            'saveConfig',
            'triggerWorkflow',
            'getPredictiveInsights',
            'getAutonomousStats',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                $this->ref->hasMethod($method),
                "Missing public method: {$method}"
            );
        }
    }

    // =========================================================================
    // formatUptime — PRIVATE PURE DATETIME
    // =========================================================================

    public function testFormatUptimeReturnsStringFormat(): void
    {
        $method = $this->ref->getMethod('formatUptime');
        $method->setAccessible(true);

        // Use a time 2 hours and 30 minutes ago
        $startTime = (new \DateTime())->modify('-2 hours -30 minutes')->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $startTime);

        $this->assertIsString($result);
        $this->assertMatchesRegularExpression('/\d+h \d+m/', $result);
    }

    public function testFormatUptimeReturnsZeroForNull(): void
    {
        $method = $this->ref->getMethod('formatUptime');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, null);
        $this->assertSame('0h 0m', $result);
    }

    public function testFormatUptimeReturnsZeroForFalse(): void
    {
        $method = $this->ref->getMethod('formatUptime');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, false);
        $this->assertSame('0h 0m', $result);
    }

    public function testFormatUptimeRecentTimestamp(): void
    {
        $method = $this->ref->getMethod('formatUptime');
        $method->setAccessible(true);

        // Time just now
        $startTime = (new \DateTime())->format('Y-m-d H:i:s');
        $result = $method->invoke($this->controller, $startTime);

        $this->assertStringContainsString('0h', $result);
    }

    // =========================================================================
    // CLASS STRUCTURE
    // =========================================================================

    public function testControllerExtendsBaseController(): void
    {
        $this->assertTrue($this->ref->isSubclassOf('App\Controllers\BaseController'));
    }

    public function testControllerHasExpectedProperties(): void
    {
        $expectedProps = ['decisionEngine', 'predictiveAnalytics', 'autoPilot', 'stateManager', 'userService', 'db'];

        foreach ($expectedProps as $prop) {
            $this->assertTrue(
                $this->ref->hasProperty($prop),
                "Missing property: {$prop}"
            );
        }
    }
}
