<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreOrchestratorService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class MercadoLivreOrchestratorServiceTest extends TestCase
{
    private MercadoLivreOrchestratorService $service;
    private ReflectionMethod $normalizeAccountRefreshResultMethod;
    private ReflectionMethod $resolveBatchRefreshSuccessMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MercadoLivreOrchestratorService(__DIR__);
        $ref = new ReflectionClass(MercadoLivreOrchestratorService::class);

        $this->normalizeAccountRefreshResultMethod = $ref->getMethod('normalizeAccountRefreshResult');
        $this->normalizeAccountRefreshResultMethod->setAccessible(true);

        $this->resolveBatchRefreshSuccessMethod = $ref->getMethod('resolveBatchRefreshSuccess');
        $this->resolveBatchRefreshSuccessMethod->setAccessible(true);
    }

    public function testNormalizeAccountRefreshResultHandlesBooleanReturn(): void
    {
        $normalized = $this->normalizeAccountRefreshResultMethod->invoke($this->service, true, 42);

        $this->assertIsArray($normalized);
        $this->assertTrue((bool)$normalized['success']);
        $this->assertSame(42, (int)$normalized['account_id']);
        $this->assertSame('TokenRefreshJob::refreshAccount(bool)', (string)$normalized['source']);
    }

    public function testNormalizeAccountRefreshResultPreservesArrayReturn(): void
    {
        $raw = [
            'success' => true,
            'message' => 'ok',
        ];

        $normalized = $this->normalizeAccountRefreshResultMethod->invoke($this->service, $raw, 77);

        $this->assertIsArray($normalized);
        $this->assertTrue((bool)$normalized['success']);
        $this->assertSame('ok', (string)$normalized['message']);
        $this->assertSame(77, (int)$normalized['account_id']);
    }

    public function testResolveBatchRefreshSuccessUsesSuccessFlagWhenPresent(): void
    {
        $successFalse = (bool)$this->resolveBatchRefreshSuccessMethod->invoke($this->service, ['success' => false, 'failed' => 1]);
        $successTrue = (bool)$this->resolveBatchRefreshSuccessMethod->invoke($this->service, ['success' => true, 'failed' => 1]);

        $this->assertFalse($successFalse);
        $this->assertTrue($successTrue);
    }

    public function testResolveBatchRefreshSuccessDefaultsToTrueForLegacyPayload(): void
    {
        $success = (bool)$this->resolveBatchRefreshSuccessMethod->invoke($this->service, ['refreshed' => 0, 'failed' => 2]);
        $this->assertTrue($success);
    }
}
