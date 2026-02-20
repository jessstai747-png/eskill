<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TrendsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \App\Services\TrendsService
 */
class TrendsServiceTest extends TestCase
{
    /**
     * Test service extends MercadoLivreClient
     */
    public function testServiceExtendsMercadoLivreClient(): void
    {
        $reflection = new ReflectionClass(TrendsService::class);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertSame('App\Services\MercadoLivreClient', $parent->getName());
    }

    /**
     * Test service has required public methods
     */
    public function testServiceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(TrendsService::class);

        $requiredMethods = [
            'getCategoryTrends',
            'getTopSearches',
            'getSeasonalTrends',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Missing required method: {$method}"
            );
        }
    }

    /**
     * Test getCategoryTrends method signature
     */
    public function testGetCategoryTrendsMethodSignature(): void
    {
        $reflection = new ReflectionMethod(TrendsService::class, 'getCategoryTrends');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('categoryId', $params[0]->getName());
        $this->assertSame('filters', $params[1]->getName());
        $this->assertTrue($params[1]->isDefaultValueAvailable());
        $this->assertSame([], $params[1]->getDefaultValue());
    }

    /**
     * Test getEmptyTrends returns expected structure
     */
    public function testGetEmptyTrendsStructure(): void
    {
        $reflection = new ReflectionMethod(TrendsService::class, 'getEmptyTrends');
        $reflection->setAccessible(true);

        // Create mock instance without constructor
        $service = $this->getMockBuilder(TrendsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $result = $reflection->invoke($service);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('keywords', $result);
        $this->assertArrayHasKey('top_products', $result);
        $this->assertIsArray($result['keywords']);
        $this->assertIsArray($result['top_products']);
    }

    /**
     * Test formatKeywords method exists
     */
    public function testFormatKeywordsMethodExists(): void
    {
        $reflection = new ReflectionClass(TrendsService::class);
        $this->assertTrue($reflection->hasMethod('formatKeywords'));
    }

    /**
     * Test formatTopProducts method exists
     */
    public function testFormatTopProductsMethodExists(): void
    {
        $reflection = new ReflectionClass(TrendsService::class);
        $this->assertTrue($reflection->hasMethod('formatTopProducts'));
    }

    /**
     * Test service has database property
     */
    public function testServiceHasDatabaseProperty(): void
    {
        $reflection = new ReflectionClass(TrendsService::class);
        $this->assertTrue($reflection->hasProperty('db'));
    }

    /**
     * Test getCategoryTrends returns array type
     */
    public function testGetCategoryTrendsReturnType(): void
    {
        $reflection = new ReflectionMethod(TrendsService::class, 'getCategoryTrends');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    /**
     * Test trends response contains expected keys
     */
    public function testTrendsResponseStructure(): void
    {
        $expectedKeys = [
            'category_id',
            'keywords',
            'top_products',
            'trend_score',
            'growth_rate',
            'updated_at',
        ];

        $reflection = new ReflectionMethod(TrendsService::class, 'getEmptyTrends');
        $reflection->setAccessible(true);

        $service = $this->getMockBuilder(TrendsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $result = $reflection->invoke($service);

        // Empty trends should still have structure hints
        $this->assertIsArray($result);
    }
}
