<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BulkSEOService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Testes unitários para BulkSEOService
 * 
 * Cobre:
 * - Validação de entrada (limites, arrays vazios)
 * - Lógica de detecção de mudança real
 * - Sanitização de IDs
 * - Estrutura de resposta
 * 
 * Nota: Testes de integração com API ML e banco estão em tests/Integration/
 */
class BulkSEOServiceTest extends TestCase
{
    private int $testAccountId = 999;

    /**
     * @test
     */
    public function dryRunBatch_withEmptyItemIds_returnsError(): void
    {
        $service = $this->createServiceInstance();
        
        $result = $service->dryRunBatch([]);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum item', $result['error']);
    }

    /**
     * @test
     */
    public function dryRunBatch_exceedsMaxLimit_returnsError(): void
    {
        $service = $this->createServiceInstance();
        
        // Criar array com mais de 50 itens
        $itemIds = array_map(fn($i) => "MLB{$i}", range(1, 60));
        
        $result = $service->dryRunBatch($itemIds);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Limite excedido', $result['error']);
    }

    /**
     * @test
     */
    public function applyBatch_withEmptyItems_returnsError(): void
    {
        $service = $this->createServiceInstance();
        
        $result = $service->applyBatch([], 1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhum item', $result['error']);
    }

    /**
     * @test
     */
    public function applyBatch_withNoApplyFlags_marksAsNoOp(): void
    {
        $service = $this->createServiceInstance();
        
        $items = [
            [
                'item_id' => 'MLB123',
                'apply_title' => false,
                'apply_description' => false,
            ],
        ];
        
        $result = $service->applyBatch($items, 1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['stats']['no_op']);
        $this->assertEquals('no_op', $result['items']['MLB123']['status']);
    }

    /**
     * @test
     */
    public function rollbackBatch_withEmptyVersionIds_returnsError(): void
    {
        $service = $this->createServiceInstance();
        
        $result = $service->rollbackBatch([], 1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Nenhuma versão', $result['error']);
    }

    /**
     * @test
     */
    public function hasRealChange_withIdenticalTexts_returnsFalse(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        $result = $method->invoke($service, 'Test Title', 'Test Title');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasRealChange_withWhitespaceDifference_returnsFalse(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        // Apenas diferença de espaços deve ser ignorada
        $result = $method->invoke($service, 'Test  Title', 'Test Title');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasRealChange_withDifferentTexts_returnsTrue(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        $result = $method->invoke($service, 'Old Title', 'New Optimized Title');
        
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function sanitizeItemIds_removesEmptyAndDuplicates(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'sanitizeItemIds');
        
        $input = ['MLB123', '', 'MLB456', 'MLB123', null, 'MLB789'];
        $result = $method->invoke($service, $input);
        
        $this->assertCount(3, $result);
        $this->assertContains('MLB123', $result);
        $this->assertContains('MLB456', $result);
        $this->assertContains('MLB789', $result);
    }

    /**
     * @test
     */
    public function interpretMLError_returnsUserFriendlyMessages(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'interpretMLError');

        // Test 400 - Dados inválidos
        $result = $method->invoke($service, ['status' => 400, 'message' => 'validation error']);
        $this->assertStringContainsString('Dados inválidos', $result);

        // Test 401 - Não autorizado
        $result = $method->invoke($service, ['status' => 401]);
        $this->assertStringContainsString('Token', $result);

        // Test 403 - Sem permissão
        $result = $method->invoke($service, ['status' => 403]);
        $this->assertStringContainsString('permissão', $result);

        // Test 404 - Não encontrado
        $result = $method->invoke($service, ['status' => 404]);
        $this->assertStringContainsString('encontrado', $result);

        // Test 429 - Rate limit
        $result = $method->invoke($service, ['status' => 429]);
        $this->assertStringContainsString('Limite', $result);

        // Test 500 - Erro interno ML
        $result = $method->invoke($service, ['status' => 500]);
        $this->assertStringContainsString('temporário', $result);
        
        // Test forbidden error
        $result = $method->invoke($service, ['error' => 'forbidden']);
        $this->assertStringContainsString('permissão', $result);
        
        // Test not_found error
        $result = $method->invoke($service, ['error' => 'not_found']);
        $this->assertStringContainsString('não encontrado', $result);
    }

    /**
     * @test
     */
    public function isRetryableError_identifiesRetryableCodes(): void
    {
        $service = $this->createServiceInstance();
        $method = $this->getPrivateMethod($service, 'isRetryableError');

        // Retryable errors
        $this->assertTrue($method->invoke($service, ['status' => 429])); // Rate limit
        $this->assertTrue($method->invoke($service, ['status' => 500])); // Internal error
        $this->assertTrue($method->invoke($service, ['status' => 502])); // Bad gateway
        $this->assertTrue($method->invoke($service, ['status' => 503])); // Service unavailable
        $this->assertTrue($method->invoke($service, ['status' => 504])); // Gateway timeout
        $this->assertTrue($method->invoke($service, ['error' => 'temporarily_unavailable'])); // Temp unavailable

        // Non-retryable errors
        $this->assertFalse($method->invoke($service, ['status' => 400])); // Bad request
        $this->assertFalse($method->invoke($service, ['status' => 401])); // Unauthorized
        $this->assertFalse($method->invoke($service, ['status' => 403])); // Forbidden
        $this->assertFalse($method->invoke($service, ['status' => 404])); // Not found
    }

    /**
     * @test
     */
    public function constants_haveCorrectValues(): void
    {
        // Verificar que as constantes de limites estão definidas corretamente
        $reflection = new ReflectionClass(BulkSEOService::class);
        
        $this->assertEquals(50, $reflection->getConstant('MAX_ITEMS_PER_BATCH'));
        $this->assertEquals(200, $reflection->getConstant('RATE_LIMIT_DELAY_MS'));
        $this->assertEquals(60, $reflection->getConstant('MAX_TITLE_LENGTH'));
    }

    /**
     * Helper: cria instância real do service (sem mock)
     * Métodos privados podem ser testados via reflection
     */
    private function createServiceInstance(): BulkSEOService
    {
        return new BulkSEOService($this->testAccountId);
    }

    /**
     * Helper: acessa método privado via reflection
     */
    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
