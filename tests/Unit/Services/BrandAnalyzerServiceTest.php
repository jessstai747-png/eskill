<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BrandAnalyzerService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\BrandAnalyzerService
 */
class BrandAnalyzerServiceTest extends TestCase
{
    /**
     * Test brand variations constant contains expected values
     */
    public function testBrandVariationsContainsExpectedValues(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $constant = $reflection->getConstant('BRAND_VARIATIONS');

        $this->assertIsArray($constant);
        $this->assertContains('AWA', $constant);
        $this->assertContains('Awa', $constant);
        $this->assertContains('awa', $constant);
        $this->assertContains('A.W.A', $constant);
    }

    /**
     * Test moto categories constant is defined
     */
    public function testMotoCategoriesConstantExists(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $constant = $reflection->getConstant('MOTO_CATEGORIES');

        $this->assertIsArray($constant);
        $this->assertNotEmpty($constant);

        // Check for main category
        $this->assertArrayHasKey('MLB1051', $constant);
        $this->assertSame('Motos', $constant['MLB1051']);
    }

    /**
     * Test brand attribute ID constant
     */
    public function testBrandAttributeIdConstant(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $constant = $reflection->getConstant('BRAND_ATTRIBUTE_ID');

        $this->assertSame('BRAND', $constant);
    }

    /**
     * Test service has required methods
     */
    public function testServiceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);

        $requiredMethods = [
            'analyzeBrand',
            'getItemsWithBrand',
            'getItemsMissingBrand',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Missing required method: {$method}"
            );
        }
    }

    /**
     * Test brand name normalization logic
     */
    public function testBrandVariationsAreCaseInsensitive(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $variations = $reflection->getConstant('BRAND_VARIATIONS');

        $lowercaseVariations = array_map('strtolower', $variations);
        $uniqueLowercase = array_unique($lowercaseVariations);

        // Should have variations that normalize to same value
        $this->assertLessThan(count($variations), count($uniqueLowercase));
    }

    /**
     * Test moto categories covers key ML categories
     */
    public function testMotoCategoriesCoversKeyCategories(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $categories = $reflection->getConstant('MOTO_CATEGORIES');

        $expectedCategories = [
            'MLB1051',     // Motos
            'MLB1747',     // Acessórios para Veículos
            'MLB214858',   // Acessórios para Motos
            'MLB5750',     // Peças de Motos
        ];

        foreach ($expectedCategories as $catId) {
            $this->assertArrayHasKey(
                $catId,
                $categories,
                "Missing expected category: {$catId}"
            );
        }
    }

    /**
     * Test service can be reflected (structural integrity)
     */
    public function testServiceStructuralIntegrity(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);

        // Should have constructor
        $this->assertTrue($reflection->hasMethod('__construct'));

        // Should have private properties
        $this->assertTrue($reflection->hasProperty('client'));
        $this->assertTrue($reflection->hasProperty('cache'));
        $this->assertTrue($reflection->hasProperty('siteId'));
    }

    /**
     * Test brand variations include common typos/formats
     */
    public function testBrandVariationsIncludeCommonFormats(): void
    {
        $reflection = new ReflectionClass(BrandAnalyzerService::class);
        $variations = $reflection->getConstant('BRAND_VARIATIONS');

        // Should include spaced version
        $hasSpacedVersion = false;
        foreach ($variations as $v) {
            if (str_contains($v, ' ')) {
                $hasSpacedVersion = true;
                break;
            }
        }
        $this->assertTrue($hasSpacedVersion, 'Should include spaced brand variation');

        // Should include dotted version
        $hasDottedVersion = false;
        foreach ($variations as $v) {
            if (str_contains($v, '.')) {
                $hasDottedVersion = true;
                break;
            }
        }
        $this->assertTrue($hasDottedVersion, 'Should include dotted brand variation');
    }
}
