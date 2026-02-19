<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Controllers\SEOToolsController;

/**
 * Testes do SeoController
 * 
 * Nota: Muitos métodos requerem sessão e conexão com API do ML,
 * então testamos principalmente a estrutura e validações.
 */
class SeoControllerTest extends TestCase
{
    private SEOToolsController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Iniciar sessão se necessário
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $this->controller = new SEOToolsController();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SEOToolsController::class, $this->controller);
    }

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'analyzeItem',
            'analyze',
            'analyzeBatch',
            'keywords',
            'keywordVolume',
            'keywordVariations',
            'trends',
            'optimizeTitle',
            'analyzeTitle',
            'suggestTitle',
            'buildListing',
            'buildDescription',
            'publishListing',
            'pricing',
            'suggestPrice',
            'calculatePrice',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->controller, $method),
                "SeoController deve ter o método {$method}()"
            );
        }
    }

    // =============================
    // TESTES DE REFLECTION (estrutura)
    // =============================

    public function testAccountIdIsNullableInt(): void
    {
        $reflection = new \ReflectionClass(SEOToolsController::class);
        $property = $reflection->getProperty('accountId');
        $property->setAccessible(true);

        $value = $property->getValue($this->controller);

        $this->assertTrue(
            $value === null || is_int($value),
            'accountId deve ser null ou int'
        );
    }

    public function testJsonMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'json'));
    }

    public function testGetJsonInputMethodExists(): void
    {
        $this->assertTrue(method_exists($this->controller, 'getJsonInput'));
    }

    // =============================
    // TESTES DE PARÂMETROS
    // =============================

    public function testAnalyzeItemAcceptsStringParameter(): void
    {
        $reflection = new \ReflectionMethod(SEOToolsController::class, 'analyzeItem');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('itemId', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }

    public function testKeywordsAcceptsStringParameter(): void
    {
        $reflection = new \ReflectionMethod(SEOToolsController::class, 'keywords');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
    }

    public function testTrendsAcceptsStringParameter(): void
    {
        $reflection = new \ReflectionMethod(SEOToolsController::class, 'trends');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
    }

    public function testPricingAcceptsStringParameter(): void
    {
        $reflection = new \ReflectionMethod(SEOToolsController::class, 'pricing');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('categoryId', $params[0]->getName());
    }

    // =============================
    // TESTES DE RETORNO
    // =============================

    public function testAllEndpointMethodsReturnVoid(): void
    {
        $methods = [
            'analyzeItem',
            'analyze',
            'analyzeBatch',
            'keywords',
            'keywordVolume',
            'optimizeTitle',
            'analyzeTitle',
            'suggestTitle',
            'buildListing',
            'buildDescription',
            'publishListing',
            'pricing',
            'suggestPrice',
            'calculatePrice',
        ];

        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod(SEOToolsController::class, $method);
            $returnType = $reflection->getReturnType();

            $this->assertNotNull(
                $returnType,
                "Método {$method}() deve ter tipo de retorno definido"
            );

            $this->assertEquals(
                'void',
                $returnType->getName(),
                "Método {$method}() deve retornar void"
            );
        }
    }

    // =============================
    // TESTES DE DOCBLOCK
    // =============================

    public function testMethodsHaveDocumentation(): void
    {
        $methods = [
            'analyzeItem',
            'analyze',
            'keywords',
            'optimizeTitle',
            'buildListing',
        ];

        foreach ($methods as $method) {
            $reflection = new \ReflectionMethod(SEOToolsController::class, $method);
            $docComment = $reflection->getDocComment();

            $this->assertNotFalse(
                $docComment,
                "Método {$method}() deve ter documentação"
            );
        }
    }

    // =============================
    // TESTES DE DEPENDÊNCIAS
    // =============================

    public function testUsesRequiredServices(): void
    {
        $reflection = new \ReflectionClass(SEOToolsController::class);
        $content = file_get_contents($reflection->getFileName());

        $requiredServices = [
            'SeoAnalyzerService',
            'KeywordResearchService',
            'TitleOptimizerService',
            'ListingBuilderService',
            'PricingStrategyService',
        ];

        foreach ($requiredServices as $service) {
            $this->assertStringContainsString(
                $service,
                $content,
                "SeoController deve usar {$service}"
            );
        }
    }
}
