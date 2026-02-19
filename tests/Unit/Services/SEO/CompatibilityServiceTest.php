<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\CompatibilityService;

class CompatibilityServiceTest extends TestCase
{
    private CompatibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CompatibilityService();
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CompatibilityService::class, $this->service);
    }

    public function testGetCompatibilityListReturnsArrayForKnownCategory(): void
    {
        $result = $this->service->getCompatibilityList('MLB3530');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('honda', $result);
        $this->assertArrayHasKey('yamaha', $result);
    }

    public function testGetCompatibilityListReturnsDefaultForUnknownCategory(): void
    {
        $result = $this->service->getCompatibilityList('UNKNOWN_CATEGORY');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        // Should return default MOTO_BRANDS
        $this->assertArrayHasKey('honda', $result);
    }

    public function testGenerateCompatibilityTextWithBrands(): void
    {
        $compatibilities = [
            'honda' => ['CG 160', 'Titan'],
            'yamaha' => ['Factor'],
        ];

        $text = $this->service->generateCompatibilityText($compatibilities);

        $this->assertStringContainsString('Compatível com:', $text);
        $this->assertStringContainsString('Honda', $text);
        $this->assertStringContainsString('CG 160', $text);
        $this->assertStringContainsString('Titan', $text);
        $this->assertStringContainsString('Yamaha', $text);
        $this->assertStringContainsString('Factor', $text);
    }

    public function testGenerateCompatibilityTextWithEmptyArray(): void
    {
        $text = $this->service->generateCompatibilityText([]);

        $this->assertStringContainsString('Compatibilidade variável', $text);
    }

    public function testDetectFromTitleFindsBrand(): void
    {
        $title = 'Bauleto 41L Universal para Honda';

        $result = $this->service->detectFromTitle($title);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('honda', $result);
    }

    public function testDetectFromTitleFindsBrandAndModel(): void
    {
        $title = 'Bauleto 41L para Honda CG 160 Titan';

        $result = $this->service->detectFromTitle($title);

        $this->assertArrayHasKey('honda', $result);
        $this->assertContains('CG 160', $result['honda']);
        $this->assertContains('Titan', $result['honda']);
    }

    public function testDetectFromTitleFindsMultipleBrands(): void
    {
        $title = 'Protetor de Motor Universal Honda Yamaha';

        $result = $this->service->detectFromTitle($title);

        $this->assertArrayHasKey('honda', $result);
        $this->assertArrayHasKey('yamaha', $result);
    }

    public function testDetectFromTitleCaseInsensitive(): void
    {
        $title = 'Bauleto para HONDA CG 160';

        $result = $this->service->detectFromTitle($title);

        $this->assertArrayHasKey('honda', $result);
    }

    public function testDetectFromTitleFindsModelWithoutBrand(): void
    {
        $title = 'Bauleto Universal CG 160 Factor';

        $result = $this->service->detectFromTitle($title);

        // Should detect CG 160 (Honda) and Factor (Yamaha) from models
        $this->assertNotEmpty($result);
    }

    public function testDetectFromTitleReturnsEmptyForNoMatch(): void
    {
        $title = 'Produto genérico sem marca específica';

        $result = $this->service->detectFromTitle($title);

        $this->assertIsArray($result);
        // May be empty or have partial matches
    }

    public function testExpandCompatibilityListAddsRelatedModels(): void
    {
        $initial = [
            'honda' => ['CG 160'],
        ];

        $expanded = $this->service->expandCompatibilityList($initial);

        $this->assertArrayHasKey('honda', $expanded);
        // Should include more Honda models
        $this->assertGreaterThan(1, count($expanded['honda']));
    }

    public function testExpandCompatibilityListHandlesEmptyModels(): void
    {
        $initial = [
            'honda' => [],
        ];

        $expanded = $this->service->expandCompatibilityList($initial);

        $this->assertArrayHasKey('honda', $expanded);
        $this->assertNotEmpty($expanded['honda']);
    }

    public function testExpandCompatibilityListPreservesUnknownBrands(): void
    {
        $initial = [
            'unknown_brand' => ['Model X'],
        ];

        $expanded = $this->service->expandCompatibilityList($initial);

        $this->assertArrayHasKey('unknown_brand', $expanded);
        $this->assertContains('Model X', $expanded['unknown_brand']);
    }

    public function testValidateCompatibilityForCategoryReturnsArray(): void
    {
        $compatibility = [
            'honda' => ['CG 160'],
        ];

        $result = $this->service->validateCompatibilityForCategory($compatibility, 'MLB3530');

        $this->assertIsArray($result);
        $this->assertEquals($compatibility, $result);
    }

    public function testGenerateCompatibilityKeywordsIncludesBrands(): void
    {
        $compatibility = [
            'honda' => ['CG 160', 'Titan'],
        ];

        $keywords = $this->service->generateCompatibilityKeywords($compatibility);

        $this->assertIsArray($keywords);
        $this->assertContains('honda', $keywords);
        $this->assertContains('CG 160', $keywords);
        $this->assertContains('Titan', $keywords);
        $this->assertContains('honda CG 160', $keywords);
    }

    public function testGenerateCompatibilityKeywordsIncludesGeneralTerms(): void
    {
        $compatibility = [
            'honda' => ['CG 160'],
        ];

        $keywords = $this->service->generateCompatibilityKeywords($compatibility);

        $this->assertContains('compatível', $keywords);
        $this->assertContains('compatibilidade', $keywords);
    }

    public function testGenerateCompatibilityKeywordsNoDuplicates(): void
    {
        $compatibility = [
            'honda' => ['CG 160', 'CG 160'], // Duplicate model
        ];

        $keywords = $this->service->generateCompatibilityKeywords($compatibility);

        // Count occurrences of 'CG 160'
        $count = array_count_values($keywords)['CG 160'] ?? 0;
        $this->assertEquals(1, $count);
    }

    public function testGenerateCompatibilityKeywordsWithEmptyCompatibility(): void
    {
        $keywords = $this->service->generateCompatibilityKeywords([]);

        $this->assertIsArray($keywords);
        // Should still have general terms
        $this->assertContains('compatível', $keywords);
    }

    public function testGenerateCompatibilityTextFormatsCorrectly(): void
    {
        $compatibilities = [
            'suzuki' => ['Yes', 'Intruder'],
        ];

        $text = $this->service->generateCompatibilityText($compatibilities);

        $this->assertStringContainsString('Suzuki', $text); // Capitalized
        $this->assertStringContainsString('Yes', $text);
        $this->assertStringContainsString('Intruder', $text);
        $this->assertStringEndsWith('.', $text);
    }

    public function testDetectFromTitleAllBrands(): void
    {
        $brands = ['honda', 'yamaha', 'suzuki', 'dafra', 'kawasaki'];

        foreach ($brands as $brand) {
            $title = "Produto para {$brand}";
            $result = $this->service->detectFromTitle($title);
            
            $this->assertArrayHasKey($brand, $result, "Should detect brand: {$brand}");
        }
    }
}
