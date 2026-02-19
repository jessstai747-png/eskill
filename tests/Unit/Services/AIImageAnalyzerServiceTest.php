<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AIImageAnalyzerService;

/**
 * Testes unitários para os métodos de integração real do AIImageAnalyzerService.
 * Foca na lógica pura (color naming, contrast, luminance) sem dependências externas.
 */
class AIImageAnalyzerServiceTest extends TestCase
{
    private AIImageAnalyzerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AIImageAnalyzerService();
    }

    // =============================
    // TESTES DE INSTANCIAÇÃO
    // =============================

    public function testCanBeInstantiated(): void
    {
        $this->assertInstanceOf(AIImageAnalyzerService::class, $this->service);
    }

    // =============================
    // TESTES DE getColorName (via Reflection)
    // =============================

    public function testGetColorNameReturnsStringForPrimaryColors(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getColorName');
        $method->setAccessible(true);

        // Vermelho puro
        $name = $method->invoke($this->service, 255, 0, 0);
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
        $this->assertEquals('Vermelho', $name);

        // Verde puro
        $name = $method->invoke($this->service, 0, 128, 0);
        $this->assertIsString($name);
        $this->assertEquals('Verde', $name);

        // Azul puro
        $name = $method->invoke($this->service, 0, 0, 255);
        $this->assertIsString($name);
        $this->assertEquals('Azul', $name);
    }

    public function testGetColorNameIdentifiesBlackAndWhite(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getColorName');
        $method->setAccessible(true);

        $this->assertEquals('Preto', $method->invoke($this->service, 0, 0, 0));
        $this->assertEquals('Branco', $method->invoke($this->service, 255, 255, 255));
    }

    public function testGetColorNameIdentifiesNearMatches(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getColorName');
        $method->setAccessible(true);

        // Cinza médio — deve ser mais próximo de Cinza
        $name = $method->invoke($this->service, 128, 128, 128);
        $this->assertEquals('Cinza', $name);

        // Amarelo puro
        $name = $method->invoke($this->service, 255, 255, 0);
        $this->assertEquals('Amarelo', $name);
    }

    // =============================
    // TESTES DE getRelativeLuminance (via Reflection)
    // =============================

    public function testGetRelativeLuminanceForBlackIsZero(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getRelativeLuminance');
        $method->setAccessible(true);

        $luminance = $method->invoke($this->service, 0, 0, 0);
        $this->assertEqualsWithDelta(0.0, $luminance, 0.001);
    }

    public function testGetRelativeLuminanceForWhiteIsOne(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getRelativeLuminance');
        $method->setAccessible(true);

        $luminance = $method->invoke($this->service, 255, 255, 255);
        $this->assertEqualsWithDelta(1.0, $luminance, 0.01);
    }

    public function testGetRelativeLuminanceOrder(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'getRelativeLuminance');
        $method->setAccessible(true);

        $dark = $method->invoke($this->service, 50, 50, 50);
        $mid = $method->invoke($this->service, 128, 128, 128);
        $light = $method->invoke($this->service, 200, 200, 200);

        $this->assertLessThan($mid, $dark);
        $this->assertLessThan($light, $mid);
    }

    // =============================
    // TESTES DE calculateContrastRatio (via Reflection)
    // =============================

    public function testCalculateContrastRatioBlackWhiteIs21(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'calculateContrastRatio');
        $method->setAccessible(true);

        $ratio = $method->invoke($this->service, [0, 0, 0], [255, 255, 255]);
        $this->assertEqualsWithDelta(21.0, $ratio, 0.5);
    }

    public function testCalculateContrastRatioSameColorIs1(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'calculateContrastRatio');
        $method->setAccessible(true);

        $ratio = $method->invoke($this->service, [128, 128, 128], [128, 128, 128]);
        $this->assertEqualsWithDelta(1.0, $ratio, 0.01);
    }

    public function testCalculateContrastRatioIsSymmetric(): void
    {
        $method = new \ReflectionMethod(AIImageAnalyzerService::class, 'calculateContrastRatio');
        $method->setAccessible(true);

        $ab = $method->invoke($this->service, [255, 0, 0], [0, 0, 255]);
        $ba = $method->invoke($this->service, [0, 0, 255], [255, 0, 0]);
        $this->assertEqualsWithDelta($ab, $ba, 0.01);
    }

    // =============================
    // TESTES DE MÉTODOS PÚBLICOS (estrutura)
    // =============================

    public function testHasRequiredPublicMethods(): void
    {
        $methods = [
            'analyzeImage',
            'analyzeImageQuality',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "AIImageAnalyzerService deve ter método {$method}()"
            );
        }
    }

    public function testHasIntegrationHelperMethods(): void
    {
        $methods = [
            'extractColorsWithGd',
            'detectBackgroundColor',
            'tryTesseractOcr',
            'tryLlmVisionOcr',
            'tryLlmVisionAnalysis',
            'getColorName',
            'getRelativeLuminance',
            'calculateContrastRatio',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "AIImageAnalyzerService deve ter método helper {$method}()"
            );
        }
    }
}
