<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use PHPUnit\Framework\TestCase;
use App\Services\SEO\KeywordSourceService;
use App\Services\MercadoLivreClient;
use App\Services\KeywordResearchService;
use App\Services\AI\Utils\CacheManager;

class KeywordSourceServiceTest extends TestCase
{
    private ?KeywordSourceService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->service = new KeywordSourceService();
        } catch (\Throwable $e) {
            $this->markTestSkipped('KeywordSourceService requer dependências externas (DB/API): ' . $e->getMessage());
        }
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(KeywordSourceService::class, $this->service);
    }

    public function testGetKeywordsReturnsCorrectStructure(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category_id', $result);
        $this->assertArrayHasKey('base_keyword', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('keywords', $result);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function testGetKeywordsReturnsCategoryId(): void
    {
        $categoryId = 'MLB3530';
        $result = $this->service->getKeywords($categoryId, 'bauleto');

        $this->assertEquals($categoryId, $result['category_id']);
    }

    public function testGetKeywordsReturnsBaseKeyword(): void
    {
        $baseKeyword = 'bauleto 41l';
        $result = $this->service->getKeywords('MLB3530', $baseKeyword);

        $this->assertEquals($baseKeyword, $result['base_keyword']);
    }

    public function testGetKeywordsSourceIsValid(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto');

        $validSources = ['database', 'ml_api', 'ai'];
        $this->assertContains($result['source'], $validSources);
    }

    public function testGetKeywordsReturnsList(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto');

        $this->assertIsArray($result['keywords']);
    }

    public function testGetKeywordsUsesEmptyKeywordAsCategoryId(): void
    {
        $categoryId = 'MLB3530';
        $result = $this->service->getKeywords($categoryId, '');

        $this->assertEquals($categoryId, $result['base_keyword']);
    }

    public function testGetKeywordsTrimsBaseKeyword(): void
    {
        $result = $this->service->getKeywords('MLB3530', '  bauleto  ');

        $this->assertEquals('bauleto', $result['base_keyword']);
    }

    public function testGenerateKeywordsReturnsCorrectStructure(): void
    {
        $result = $this->service->generateKeywords('MLB3530', 'bauleto');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category_id', $result);
        $this->assertArrayHasKey('base_keyword', $result);
        $this->assertArrayHasKey('source', $result);
        $this->assertArrayHasKey('keywords', $result);
        $this->assertEquals('ai', $result['source']);
    }

    public function testGenerateKeywordsUsesAISource(): void
    {
        $result = $this->service->generateKeywords('MLB3530', 'capacete');

        $this->assertEquals('ai', $result['source']);
    }

    public function testInvalidateCacheDoesNotThrow(): void
    {
        // Should not throw any exception
        $this->service->invalidateCache('MLB3530');
        
        $this->assertTrue(true); // If we got here, no exception was thrown
    }

    public function testGetKeywordsWithDifferentCategories(): void
    {
        $categories = ['MLB3530', 'MLB1071', 'MLB1234'];

        foreach ($categories as $categoryId) {
            $result = $this->service->getKeywords($categoryId, 'produto');
            
            $this->assertIsArray($result);
            $this->assertEquals($categoryId, $result['category_id']);
        }
    }

    public function testKeywordsHaveCorrectItemStructure(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto moto');

        if (!empty($result['keywords'])) {
            $firstKeyword = $result['keywords'][0];
            
            $this->assertArrayHasKey('keyword', $firstKeyword);
            $this->assertArrayHasKey('score', $firstKeyword);
            $this->assertArrayHasKey('source', $firstKeyword);
        }
        
        $this->assertIsArray($result['keywords']);
    }

    public function testGeneratedAtIsValidDateTime(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto');

        $timestamp = strtotime($result['generated_at']);
        $this->assertNotFalse($timestamp);
        
        // Should be within last minute
        $now = time();
        $this->assertGreaterThan($now - 60, $timestamp);
    }

    public function testGetKeywordsWithSpecialCharacters(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'bauleto 41l pro-tork');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('keywords', $result);
    }

    public function testGetKeywordsWithUnicodeCharacters(): void
    {
        $result = $this->service->getKeywords('MLB3530', 'proteção moto');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('keywords', $result);
    }

    public function testMultipleCallsReturnConsistentStructure(): void
    {
        $result1 = $this->service->getKeywords('MLB3530', 'bauleto');
        $result2 = $this->service->getKeywords('MLB3530', 'bauleto');

        // Both should have same structure
        $this->assertEquals(array_keys($result1), array_keys($result2));
    }
}
