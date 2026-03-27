<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\RateLimitTrackerService;

class RateLimitTrackerServiceTest extends TestCase
{
    private ?RateLimitTrackerService $tracker = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->tracker = new RateLimitTrackerService();
            // Verifica se Redis está funcional tentando uma operação
            $this->tracker->trackCall('_test_init');
            $this->tracker->resetCounters('_test_init');
        } catch (\Throwable $e) {
            $this->markTestSkipped('RateLimitTrackerService requer Redis ativo: ' . $e->getMessage());
        }
    }

    public function testTrackCall(): void
    {
        $result = $this->tracker->trackCall('mercadolivre');
        $this->assertTrue($result);
    }

    public function testCanMakeCall(): void
    {
        $result = $this->tracker->canMakeCall('mercadolivre');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('can_call', $result);
        $this->assertArrayHasKey('usage', $result);
        $this->assertArrayHasKey('limits', $result);
        $this->assertArrayHasKey('usage_percentage', $result);
    }

    public function testGetCurrentUsage(): void
    {
        // Track some calls
        $this->tracker->trackCall('anthropic');
        $this->tracker->trackCall('anthropic');
        
        $usage = $this->tracker->getCurrentUsage('anthropic');
        
        $this->assertIsArray($usage);
        $this->assertArrayHasKey('minute', $usage);
        $this->assertArrayHasKey('hour', $usage);
        $this->assertArrayHasKey('day', $usage);
        $this->assertGreaterThanOrEqual(2, $usage['minute']);
    }

    public function testPredictLimitHit(): void
    {
        $prediction = $this->tracker->predictLimitHit('mercadolivre', 5);
        
        $this->assertIsArray($prediction);
        $this->assertArrayHasKey('will_hit_limit', $prediction);
        $this->assertArrayHasKey('recommendation', $prediction);
        $this->assertContains($prediction['recommendation'], ['normal', 'throttle']);
    }

    public function testShouldAlertWhenUnderThreshold(): void
    {
        // With no calls, should not alert
        $alert = $this->tracker->shouldAlert('mercadolivre');
        $this->assertNull($alert);
    }

    public function testGetStatus(): void
    {
        $status = $this->tracker->getStatus();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('mercadolivre', $status);
        $this->assertArrayHasKey('anthropic', $status);
        
        foreach ($status as $provider => $data) {
            $this->assertArrayHasKey('can_call', $data);
            $this->assertArrayHasKey('usage', $data);
            $this->assertArrayHasKey('prediction', $data);
        }
    }

    public function testResetCounters(): void
    {
        // Track some calls
        $this->tracker->trackCall('mercadolivre');
        
        // Reset
        $result = $this->tracker->resetCounters('mercadolivre');
        $this->assertTrue($result);
        
        // Usage should be 0 after reset
        $usage = $this->tracker->getCurrentUsage('mercadolivre');
        $this->assertEquals(0, $usage['minute']);
    }
}
