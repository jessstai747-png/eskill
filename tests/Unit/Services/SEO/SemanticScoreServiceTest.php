<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use App\Database;
use PHPUnit\Framework\TestCase;
use App\Services\SEO\SemanticScoreService;

class SemanticScoreServiceTest extends TestCase
{
    private SemanticScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure the table exists for tests
        try {
            $db = Database::getInstance();
            $db->exec("CREATE TABLE IF NOT EXISTS seo_use_contexts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                context_type VARCHAR(100) NOT NULL,
                keyword VARCHAR(255) NOT NULL,
                category_id VARCHAR(50) DEFAULT '',
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // best effort
        }
        
        $this->service = new SemanticScoreService();
    }

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SemanticScoreService::class, $this->service);
    }

    public function testCalculateScoreReturnsFloatBetween0And100(): void
    {
        $score = $this->service->calculateScore('bauleto', 'Bauleto 41L Pro Tork', 'MLB3530');
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testCalculateScoreHigherForWordInTitle(): void
    {
        $scoreInTitle = $this->service->calculateScore('bauleto', 'Bauleto 41L Universal', 'MLB3530');
        $scoreNotInTitle = $this->service->calculateScore('capacete', 'Bauleto 41L Universal', 'MLB3530');
        
        $this->assertGreaterThan($scoreNotInTitle, $scoreInTitle);
    }

    public function testScoreWordsReturnsArrayWithScores(): void
    {
        $words = ['bauleto', 'moto', 'capacete'];
        $title = 'Bauleto 41L Universal Moto';
        
        $scores = $this->service->scoreWords($words, $title, 'MLB3530');
        
        $this->assertIsArray($scores);
        $this->assertCount(3, $scores);
        $this->assertArrayHasKey('bauleto', $scores);
        $this->assertArrayHasKey('moto', $scores);
        $this->assertArrayHasKey('capacete', $scores);
        
        foreach ($scores as $word => $score) {
            $this->assertIsFloat($score);
        }
    }

    public function testRankByScoreReturnsSortedDescending(): void
    {
        $words = ['acessorio', 'bauleto', 'moto', 'xyz'];
        $title = 'Bauleto 41L Universal Moto';
        
        $ranked = $this->service->rankByScore($words, $title, 'MLB3530');
        
        $this->assertIsArray($ranked);
        
        // Verify descending order
        $prevScore = PHP_FLOAT_MAX;
        foreach ($ranked as $word => $score) {
            $this->assertLessThanOrEqual($prevScore, $score, "Scores should be in descending order");
            $prevScore = $score;
        }
    }

    public function testRankByScorePutsMatchingWordsFirst(): void
    {
        $words = ['xyz', 'bauleto', 'abc'];
        $title = 'Bauleto Universal';
        
        $ranked = $this->service->rankByScore($words, $title, 'MLB3530');
        $keys = array_keys($ranked);
        
        // 'bauleto' should be first as it matches title exactly
        $this->assertEquals('bauleto', $keys[0]);
    }

    public function testHasUseContextReturnsBool(): void
    {
        $result = $this->service->hasUseContext('test_word');
        
        $this->assertIsBool($result);
    }

    public function testCalculateScoreWithEmptyTitle(): void
    {
        $score = $this->service->calculateScore('bauleto', '', 'MLB3530');
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function testCalculateScoreWithEmptyWord(): void
    {
        $score = $this->service->calculateScore('', 'Bauleto 41L', 'MLB3530');
        
        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function testScoreWordsWithEmptyArray(): void
    {
        $scores = $this->service->scoreWords([], 'Bauleto 41L', 'MLB3530');
        
        $this->assertIsArray($scores);
        $this->assertEmpty($scores);
    }

    public function testRankByScoreWithSingleWord(): void
    {
        $words = ['bauleto'];
        $title = 'Bauleto Universal';
        
        $ranked = $this->service->rankByScore($words, $title, 'MLB3530');
        
        $this->assertCount(1, $ranked);
        $this->assertArrayHasKey('bauleto', $ranked);
    }

    public function testCalculateScoreCaseInsensitive(): void
    {
        $scoreLower = $this->service->calculateScore('bauleto', 'BAULETO 41L', 'MLB3530');
        $scoreUpper = $this->service->calculateScore('BAULETO', 'bauleto 41l', 'MLB3530');
        
        // Both should get similar high scores since they match (case insensitive)
        $this->assertGreaterThan(50, $scoreLower);
        $this->assertGreaterThan(50, $scoreUpper);
    }

    public function testGetContextsReturnsArray(): void
    {
        $contexts = $this->service->getContexts('MLB3530');
        
        $this->assertIsArray($contexts);
    }

    public function testScoreWordsPreservesWordOrder(): void
    {
        $words = ['zebra', 'alpha', 'middle'];
        $title = 'Test Title';
        
        $scores = $this->service->scoreWords($words, $title, 'MLB1234');
        
        // Keys should be in same order as input
        $keys = array_keys($scores);
        $this->assertEquals(['zebra', 'alpha', 'middle'], $keys);
    }

    public function testCalculateScoreWithSimilarWords(): void
    {
        // "bauleto" vs "bauletos" should have good semantic similarity
        $score = $this->service->calculateScore('bauletos', 'Bauleto 41L Universal', 'MLB3530');
        
        // Should have decent score due to semantic similarity
        $this->assertGreaterThan(30, $score);
    }

    public function testCalculateScoreWithCompletlyDifferentWord(): void
    {
        $score = $this->service->calculateScore('xyzabc123', 'Bauleto 41L Universal Moto', 'MLB3530');
        
        // Completely unrelated word should have low score
        $this->assertLessThan(50, $score);
    }

    public function testScoreWordsHandlesSpecialCharacters(): void
    {
        $words = ['41l', 'pro-tork', 'ção'];
        $title = 'Bauleto 41L Pro-Tork Proteção';
        
        $scores = $this->service->scoreWords($words, $title, 'MLB3530');
        
        $this->assertCount(3, $scores);
        foreach ($scores as $score) {
            $this->assertIsFloat($score);
        }
    }
}
