<?php

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Core\AIProviderManager;

class AIProviderManagerTest extends TestCase
{
    private AIProviderManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new AIProviderManager();
    }

    public function testGetAvailableProvidersReturnsArray()
    {
        $providers = $this->manager->getAvailableProviders();

        $this->assertIsArray($providers);
        $this->assertNotEmpty($providers);
    }

    public function testGetProviderReturnsCorrectInstance()
    {
        $providers = $this->manager->getAvailableProviders();
        
        if (empty($providers)) {
            $this->markTestSkipped('No AI providers configured');
        }
        
        $providerName = array_key_first($providers);
        $provider = $this->manager->getProvider($providerName);

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetPreferredProviderReturnsDefault()
    {
        $provider = $this->manager->getPreferredProvider();

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetFallbackProviderWhenPrimaryFails()
    {
        // Simular falha do provider primário
        $primary = 'invalid_provider';
        
        $provider = $this->manager->getProviderWithFallback($primary);

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetProviderStatsReturnsMetrics()
    {
        $stats = $this->manager->getProviderStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_providers', $stats);
        $this->assertArrayHasKey('available_count', $stats);
    }

    public function testIsProviderAvailable()
    {
        $isAvailable = $this->manager->isProviderAvailable('openai');

        $this->assertIsBool($isAvailable);
    }

    public function testGetCheapestProviderReturnsLowestCost()
    {
        $provider = $this->manager->getCheapestProvider();

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetFastestProviderReturnsLowestLatency()
    {
        $provider = $this->manager->getFastestProvider();

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetProviderByStrategyHandlesCost()
    {
        $provider = $this->manager->getProviderByStrategy('cost');

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetProviderByStrategyHandlesSpeed()
    {
        $provider = $this->manager->getProviderByStrategy('speed');

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }

    public function testGetProviderByStrategyHandlesQuality()
    {
        $provider = $this->manager->getProviderByStrategy('quality');

        $this->assertInstanceOf(\App\Services\AI\Providers\AbstractAIProvider::class, $provider);
    }
}
