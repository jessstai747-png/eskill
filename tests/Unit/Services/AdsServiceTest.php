<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AdsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \App\Services\AdsService
 */
class AdsServiceTest extends TestCase
{
    /**
     * Test service extends MercadoLivreClient
     */
    public function testServiceExtendsMercadoLivreClient(): void
    {
        $reflection = new ReflectionClass(AdsService::class);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertSame('App\Services\MercadoLivreClient', $parent->getName());
    }

    /**
     * Test service has required methods
     */
    public function testServiceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(AdsService::class);

        $requiredMethods = [
            'getCampaigns',
            'getCampaignMetrics',
            'createCampaign',
            'updateCampaign',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Missing required method: {$method}"
            );
        }
    }

    /**
     * Test isList utility method with empty array
     */
    public function testIsListWithEmptyArray(): void
    {
        $reflection = new ReflectionMethod(AdsService::class, 'isList');
        $reflection->setAccessible(true);

        $service = $this->createMockService();

        $this->assertTrue($reflection->invoke($service, []));
    }

    /**
     * Test isList utility method with indexed array
     */
    public function testIsListWithIndexedArray(): void
    {
        $reflection = new ReflectionMethod(AdsService::class, 'isList');
        $reflection->setAccessible(true);

        $service = $this->createMockService();

        $this->assertTrue($reflection->invoke($service, ['a', 'b', 'c']));
        $this->assertTrue($reflection->invoke($service, [0 => 'x', 1 => 'y']));
    }

    /**
     * Test isList utility method with associative array
     */
    public function testIsListWithAssociativeArray(): void
    {
        $reflection = new ReflectionMethod(AdsService::class, 'isList');
        $reflection->setAccessible(true);

        $service = $this->createMockService();

        $this->assertFalse($reflection->invoke($service, ['key' => 'value']));
        $this->assertFalse($reflection->invoke($service, [1 => 'a', 3 => 'b']));
    }

    /**
     * Test getCampaigns returns array with campaigns key
     */
    public function testGetCampaignsReturnsArrayStructure(): void
    {
        $service = $this->getMockBuilder(AdsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['ensureValidAccessToken', 'getCachedCampaigns'])
            ->getMock();

        $service->method('ensureValidAccessToken')->willReturn(false);
        $service->method('getCachedCampaigns')->willReturn([]);

        $result = $service->getCampaigns();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('campaigns', $result);
        $this->assertArrayHasKey('_meta', $result);
    }

    /**
     * Test getCampaigns uses cache when no token
     */
    public function testGetCampaignsUsesCacheWhenNoToken(): void
    {
        $cachedCampaigns = [
            ['id' => 1, 'name' => 'Campaign 1'],
            ['id' => 2, 'name' => 'Campaign 2'],
        ];

        $service = $this->getMockBuilder(AdsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['ensureValidAccessToken', 'getCachedCampaigns'])
            ->getMock();

        $service->method('ensureValidAccessToken')->willReturn(false);
        $service->method('getCachedCampaigns')->willReturn($cachedCampaigns);

        $result = $service->getCampaigns();

        $this->assertSame($cachedCampaigns, $result['campaigns']);
        $this->assertSame('local_cache', $result['_meta']['data_source']);
    }

    /**
     * Test meta contains data source info
     */
    public function testMetaContainsDataSourceInfo(): void
    {
        $service = $this->getMockBuilder(AdsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['ensureValidAccessToken', 'getCachedCampaigns'])
            ->getMock();

        $service->method('ensureValidAccessToken')->willReturn(false);
        $service->method('getCachedCampaigns')->willReturn([]);

        $result = $service->getCampaigns();

        $this->assertArrayHasKey('data_source', $result['_meta']);
        $this->assertArrayHasKey('fetched_at', $result['_meta']);
    }

    /**
     * Test getCampaigns accepts status filter
     */
    public function testGetCampaignsAcceptsStatusFilter(): void
    {
        $reflection = new ReflectionMethod(AdsService::class, 'getCampaigns');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('status', $params[0]->getName());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertSame('active', $params[0]->getDefaultValue());
    }

    /**
     * Test service has database property
     */
    public function testServiceHasDatabaseProperty(): void
    {
        $reflection = new ReflectionClass(AdsService::class);
        $this->assertTrue($reflection->hasProperty('db'));
    }

    /**
     * Helper to create mock service
     */
    private function createMockService(): AdsService
    {
        return $this->getMockBuilder(AdsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }
}
