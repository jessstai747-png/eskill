<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\HealthController;

class HealthControllerTest extends TestCase
{
    private HealthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new HealthController();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(HealthController::class, $this->controller);
    }

    public function testHasCheckMethod(): void
    {
        $this->assertTrue(method_exists($this->controller, 'check'));
    }

    public function testHasLiveMethod(): void
    {
        $this->assertTrue(method_exists($this->controller, 'live'));
    }

    public function testHasReadyMethod(): void
    {
        $this->assertTrue(method_exists($this->controller, 'ready'));
    }

    // =============================
    // TESTES DE LIVENESS
    // =============================

    public function testLiveReturnsAlive(): void
    {
        ob_start();
        $this->controller->live();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertNotNull($data);
        $this->assertEquals('alive', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    // =============================
    // TESTES DE READINESS
    // =============================

    public function testReadyReturnsStatus(): void
    {
        ob_start();
        $this->controller->ready();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertNotNull($data);
        $this->assertContains($data['status'], ['ready', 'not_ready']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
    }

    // =============================
    // TESTES DE CHECK COMPLETO
    // =============================

    public function testCheckReturnsFullStatus(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('checks', $data);
    }

    public function testCheckIncludesDatabaseStatus(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('status', $data['checks']['database']);
    }

    public function testCheckIncludesCacheStatus(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertArrayHasKey('cache', $data['checks']);
    }

    public function testCheckIncludesMemoryStatus(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertArrayHasKey('memory', $data['checks']);
        $this->assertArrayHasKey('usage', $data['checks']['memory']);
    }

    public function testCheckStatusIsHealthyDegradedOrUnhealthy(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertContains($data['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    // =============================
    // TESTES DE FORMATO
    // =============================

    public function testOutputIsValidJson(): void
    {
        ob_start();
        $this->controller->check();
        $output = ob_get_clean();

        $this->assertJson($output);
    }

    public function testTimestampIsIso8601(): void
    {
        ob_start();
        $this->controller->live();
        $output = ob_get_clean();

        $data = json_decode($output, true);

        // ISO 8601 format validation
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $data['timestamp']
        );
    }
}
