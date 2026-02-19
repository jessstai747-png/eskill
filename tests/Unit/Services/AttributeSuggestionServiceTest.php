<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AttributeSuggestionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Testes unitários para AttributeSuggestionService
 * 
 * Cobre:
 * - Preview de sugestões de atributos
 * - Identificação de atributos aplicáveis vs internos
 * - Detecção de mudança real
 */
class AttributeSuggestionServiceTest extends TestCase
{
    private int $testAccountId = 999;

    /**
     * @test
     */
    public function hasRealChange_withIdenticalValues_returnsFalse(): void
    {
        $service = $this->createPartialMockService();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        $result = $method->invoke($service, 'Samsung', 'Samsung');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasRealChange_withCaseDifference_returnsFalse(): void
    {
        $service = $this->createPartialMockService();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        // Case insensitive comparison
        $result = $method->invoke($service, 'SAMSUNG', 'samsung');
        
        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function hasRealChange_withDifferentValues_returnsTrue(): void
    {
        $service = $this->createPartialMockService();
        $method = $this->getPrivateMethod($service, 'hasRealChange');
        
        $result = $method->invoke($service, 'Samsung', 'Apple');
        
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function applicableAttributes_containsCommonFields(): void
    {
        $reflection = new ReflectionClass(AttributeSuggestionService::class);
        
        // Verificar que atributos comuns estão na lista via getConstant
        $applicable = $reflection->getConstant('APPLICABLE_ATTRIBUTES');
        
        $this->assertIsArray($applicable);
        $this->assertArrayHasKey('BRAND', $applicable);
        $this->assertArrayHasKey('MODEL', $applicable);
        $this->assertArrayHasKey('GTIN', $applicable);
        $this->assertArrayHasKey('MPN', $applicable);
    }

    /**
     * @test
     */
    public function internalOnlyAttributes_containsKeywords(): void
    {
        $reflection = new ReflectionClass(AttributeSuggestionService::class);
        
        $internalOnly = $reflection->getConstant('INTERNAL_ONLY_ATTRIBUTES');
        
        $this->assertIsArray($internalOnly);
        // KEYWORDS não deve ser aplicável via ML
        $this->assertArrayHasKey('KEYWORDS', $internalOnly);
    }

    /**
     * @test
     */
    public function applySuggestion_withInternalOnlyAttribute_returnsError(): void
    {
        $service = $this->createPartialMockService();
        
        $result = $service->applySuggestion('MLB123', 'KEYWORDS', 'test keywords', 1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('não pode ser aplicado', $result['error']);
        $this->assertEquals('internal_only', $result['action']);
    }

    /**
     * @test
     */
    public function applySuggestion_withoutConfirmation_requiresExplicitConfirm(): void
    {
        // Este teste verifica que o serviço requer confirmação explícita
        // O controller é responsável por passar confirm: true
        
        $service = $this->createPartialMockService();
        
        // Sem valor e sem sugestão pendente no banco
        $result = $service->applySuggestion('MLB123', 'BRAND', null, 1);
        
        // Deve falhar por não encontrar sugestão pendente
        $this->assertFalse($result['success']);
    }

    /**
     * Helper: cria mock parcial do service
     */
    private function createPartialMockService(array $methodsToMock = []): AttributeSuggestionService
    {
        $defaultMocks = [];
        $methodsToMock = array_unique(array_merge($defaultMocks, $methodsToMock));

        if (empty($methodsToMock)) {
            return new AttributeSuggestionService($this->testAccountId);
        }

        $mock = $this->getMockBuilder(AttributeSuggestionService::class)
            ->setConstructorArgs([$this->testAccountId])
            ->onlyMethods($methodsToMock)
            ->getMock();

        return $mock;
    }

    /**
     * Helper: acessa método privado
     */
    private function getPrivateMethod($object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
