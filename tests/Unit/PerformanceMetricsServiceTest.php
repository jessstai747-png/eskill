<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\PerformanceMetricsService;

class PerformanceMetricsServiceTest extends TestCase
{
    private PerformanceMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceMetricsService();
    }

    public function testGetMetrics(): void
    {
        $metrics = $this->service->getMetrics();
        
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('cache', $metrics);
        $this->assertArrayHasKey('queue', $metrics);
        $this->assertArrayHasKey('llm', $metrics);
        $this->assertArrayHasKey('database', $metrics);
        $this->assertArrayHasKey('system', $metrics);
        $this->assertArrayHasKey('timestamp', $metrics);
    }

    public function testCacheMetricsStructure(): void
    {
        $metrics = $this->service->getMetrics();
        $cache = $metrics['cache'];
        
        $this->assertIsArray($cache);
        $this->assertArrayHasKey('hit_rate', $cache);
        $this->assertArrayHasKey('connection', $cache);
    }

    public function testQueueMetricsStructure(): void
    {
        $metrics = $this->service->getMetrics();
        $queue = $metrics['queue'];
        
        $this->assertIsArray($queue);
        
        // Should have either job counts or error
        if (!isset($queue['error'])) {
            $this->assertArrayHasKey('pending', $queue);
            $this->assertArrayHasKey('completed', $queue);
        }
    }

    public function testLLMMetricsStructure(): void
    {
        $metrics = $this->service->getMetrics();
        $llm = $metrics['llm'];
        
        $this->assertIsArray($llm);
        
        if (!isset($llm['error'])) {
            $this->assertArrayHasKey('today', $llm);
            $this->assertArrayHasKey('week', $llm);
        }
    }

    public function testSystemMetricsStructure(): void
    {
        $metrics = $this->service->getMetrics();
        $system = $metrics['system'];
        
        $this->assertIsArray($system);
        $this->assertArrayHasKey('memory_usage', $system);
        $this->assertArrayHasKey('memory_peak', $system);
        $this->assertArrayHasKey('php_version', $system);
        
        $this->assertGreaterThan(0, $system['memory_usage']);
        $this->assertEquals(PHP_VERSION, $system['php_version']);
    }

    public function testGetHistoricalMetrics(): void
    {
        $historical = $this->service->getHistoricalMetrics('cache_hit_rate', 24);
        
        $this->assertIsArray($historical);
        // Com DB pode retornar dados reais; sem DB retorna placeholder (25 itens)
        $this->assertGreaterThanOrEqual(1, count($historical));
        
        foreach ($historical as $point) {
            $this->assertArrayHasKey('timestamp', $point);
            $this->assertArrayHasKey('value', $point);
        }
    }
}
