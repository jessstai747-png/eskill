<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\QueueService;

class QueueServiceTest extends TestCase
{
    private ?QueueService $queue = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->queue = new QueueService();
        } catch (\Throwable $e) {
            $this->markTestSkipped('QueueService requer extensão Redis e servidor Redis ativo: ' . $e->getMessage());
        }
    }

    public function testPushJob(): void
    {
        $jobId = $this->queue->push('test_job', ['data' => 'test']);
        
        $this->assertIsString($jobId);
        $this->assertStringStartsWith('job_', $jobId);
    }

    public function testPushAndPopJob(): void
    {
        // Push a job
        $payload = ['test_key' => 'test_value', 'timestamp' => time()];
        $jobId = $this->queue->push('test_job_type', $payload);
        
        $this->assertNotEmpty($jobId);
        
        // Pop the job (with short timeout for testing)
        $job = $this->queue->pop('default', 2);
        
        // Verify job structure
        $this->assertIsArray($job);
        $this->assertArrayHasKey('id', $job);
        $this->assertArrayHasKey('type', $job);
        $this->assertArrayHasKey('payload', $job);
        $this->assertArrayHasKey('created_at', $job);
    }

    public function testPopWithNoJobs(): void
    {
        // Pop from empty queue (short timeout)
        $job = $this->queue->pop('empty_queue', 1);
        
        $this->assertNull($job);
    }

    public function testPushToCustomQueue(): void
    {
        $jobId = $this->queue->push('custom_job', ['custom' => true], 'custom_queue');
        
        $this->assertNotEmpty($jobId);
        
        // Pop from custom queue
        $job = $this->queue->pop('custom_queue', 2);
        
        $this->assertIsArray($job);
        $this->assertEquals('custom_job', $job['type']);
    }
}
