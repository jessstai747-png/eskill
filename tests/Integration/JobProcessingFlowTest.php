<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\JobService;
use App\Services\QueueService;

class JobProcessingFlowTest extends TestCase
{
    private ?JobService $jobService = null;
    private ?QueueService $queueService = null;
    private string $queueName = 'job_processing_flow_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->queueName = 'job_processing_flow_test_' . bin2hex(random_bytes(6));
        $_ENV['JOB_QUEUE_NAME'] = $this->queueName;
        putenv('JOB_QUEUE_NAME=' . $this->queueName);

        try {
            $this->jobService = new JobService();
            $this->queueService = new QueueService();
        } catch (\Throwable $e) {
            $this->markTestSkipped('JobProcessingFlow requer DB e Redis ativos: ' . $e->getMessage());
        }
    }

    public function testJobDispatchAndQueueIntegration(): void
    {
        // Dispatch a test job
        $jobId = $this->jobService->dispatch('ai_generation', [
            'prompt' => 'Test prompt',
            'system' => 'Test system',
            'complexity' => 'basic'
        ]);
        
        $this->assertIsInt($jobId);
        $this->assertGreaterThan(0, $jobId);
        
        // Job should be in Redis queue
        $queuedJob = $this->queueService->pop($this->queueName, 2);
        
        $this->assertIsArray($queuedJob);
        $this->assertArrayHasKey('payload', $queuedJob);
        $this->assertArrayHasKey('job_id', $queuedJob['payload']);
        $this->assertEquals($jobId, $queuedJob['payload']['job_id']);
    }

    public function testJobStatusTracking(): void
    {
        // Create a job
        $jobId = $this->jobService->dispatch('ai_generation', [
            'prompt' => 'Status test',
            'system' => 'Test',
            'complexity' => 'basic'
        ]);
        
        // Get job from database
        $job = $this->jobService->getJob($jobId);
        
        $this->assertIsArray($job);
        $this->assertEquals('pending', $job['status']);
        $this->assertEquals('ai_generation', $job['type']);
    }

    public function testJobStats(): void
    {
        $stats = $this->jobService->getStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('pending', $stats);
        $this->assertArrayHasKey('processing', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('failed', $stats);
        
        foreach ($stats as $status => $count) {
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        }
    }

    protected function tearDown(): void
    {
        putenv('JOB_QUEUE_NAME');
        unset($_ENV['JOB_QUEUE_NAME']);
        parent::tearDown();
    }
}
