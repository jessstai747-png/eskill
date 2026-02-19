<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ListingBuilderService;

/**
 * Testes do ListingBuilderService
 */
class ListingBuilderServiceTest extends TestCase
{
    private ListingBuilderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ListingBuilderService();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ListingBuilderService::class, $this->service);
    }

    public function testCanBeInstantiatedWithNullAccountId(): void
    {
        // Testar instanciação sem account_id
        $service = new ListingBuilderService(null);
        $this->assertInstanceOf(ListingBuilderService::class, $service);
    }

    // =============================
    // TESTES DE MÉTODOS
    // =============================

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'buildListing',
            'buildDescription',
            'buildAttributes',
            'publishListing',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "ListingBuilderService deve ter método {$method}()"
            );
        }
    }

    // =============================
    // TESTES DE TEMPLATES
    // =============================

    public function testHasDescriptionTemplates(): void
    {
        $reflection = new \ReflectionClass(ListingBuilderService::class);
        $this->assertTrue(
            $reflection->hasConstant('DESCRIPTION_TEMPLATES') ||
                $reflection->hasProperty('descriptionTemplates'),
            'Deve ter templates de descrição'
        );
    }

    // =============================
    // TESTES DE ESTRUTURA
    // =============================

    public function testHasMercadoLivreClient(): void
    {
        $reflection = new \ReflectionClass(ListingBuilderService::class);
        $this->assertTrue($reflection->hasProperty('client'));
    }

    // =============================
    // TESTES DE RETORNO
    // =============================

    public function testBuildListingReturnsArray(): void
    {
        $reflection = new \ReflectionMethod(ListingBuilderService::class, 'buildListing');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    public function testBuildDescriptionReturnsString(): void
    {
        $reflection = new \ReflectionMethod(ListingBuilderService::class, 'buildDescription');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    // =============================
    // TESTES DE PARÂMETROS
    // =============================

    public function testBuildListingAcceptsArray(): void
    {
        $reflection = new \ReflectionMethod(ListingBuilderService::class, 'buildListing');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('productData', $params[0]->getName());
        $this->assertEquals('array', $params[0]->getType()->getName());
    }

    // =============================
    // TESTES DE DOCUMENTAÇÃO
    // =============================

    public function testClassHasDocumentation(): void
    {
        $reflection = new \ReflectionClass(ListingBuilderService::class);
        $docComment = $reflection->getDocComment();

        $this->assertNotFalse($docComment);
    }
}
