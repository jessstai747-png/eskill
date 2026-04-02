<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AI\ML\NLPIntegrationService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\ML\NLPIntegrationService
 */
class NLPIntegrationTest extends TestCase
{
    private NLPIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $this->service = new NLPIntegrationService($logger, 'http://127.0.0.1:65535', 'dev-secret-key', 1);
    }

    public function testHealthCheckReturnsTrueWhenFallbackEngineIsAvailable(): void
    {
        $result = $this->service->healthCheck();

        $this->assertTrue($result, 'O fallback local do NLP deve manter o motor operacional sem o FastAPI.');
    }

    public function testPredictIntentReturnsValidStructure(): void
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

    public function testPredictIntentHandlesNormalQuestions(): void
    {
        $result = $this->service->predictIntent('MSG-124', 'serve na cg 160?', 'MLB124', 50.0);

        $this->assertIsArray($result);
        $this->assertEquals('compatibilidade', $result['intent']);
        $this->assertFalse($result['is_critical']);
    }
}
