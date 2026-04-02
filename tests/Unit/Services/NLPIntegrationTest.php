<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AI\ML\NLPIntegrationService;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

class NLPIntegrationTest extends TestCase
{
    private NLPIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $this->service = new NLPIntegrationService($logger);

        if (!$this->service->healthCheck()) {
            $this->markTestSkipped('NLP FastAPI service is not available at http://127.0.0.1:8000.');
        }
    }

    public function testHealthCheckReturnsTrueWhenServerIsUp()
    {
        $result = $this->service->healthCheck();
        $this->assertTrue($result, "O servidor FastAPI deve estar rodando para este teste passar.");
    }

    public function testPredictIntentReturnsValidStructure()
    {
        $result = $this->service->predictIntent('MSG-123', 'produto chegou quebrado e vou no procon', 'MLB123', 150.0);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('intent', $result);
        $this->assertArrayHasKey('urgency_score', $result);
        $this->assertArrayHasKey('is_critical', $result);
        
        $this->assertEquals('reclamacao_critica', $result['intent']);
        $this->assertTrue($result['is_critical']);
        $this->assertGreaterThan(0.8, $result['urgency_score']);
    }
    
    public function testPredictIntentHandlesNormalQuestions()
    {
        $result = $this->service->predictIntent('MSG-124', 'serve na cg 160?', 'MLB124', 50.0);
        
        $this->assertIsArray($result);
        $this->assertEquals('compatibilidade', $result['intent']);
        $this->assertFalse($result['is_critical']);
    }
}
