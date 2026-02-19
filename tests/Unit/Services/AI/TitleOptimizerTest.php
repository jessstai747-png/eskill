<?php

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Optimizers\TitleOptimizer;
use App\Services\AI\Providers\OpenAIProvider;

class TitleOptimizerTest extends TestCase
{
    private TitleOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new TitleOptimizer();
    }

    public function testAnalyzeReturnProperScore()
    {
        $result = $this->optimizer->analyze('Fone Bluetooth TWS', [
            'brand' => 'Sony',
            'category' => 'Eletrônicos'
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('char_count', $result);
        $this->assertArrayHasKey('issues', $result);
        $this->assertArrayHasKey('strengths', $result);
        
        $this->assertIsNumeric($result['score']);
        $this->assertGreaterThanOrEqual(0, $result['score']);
        $this->assertLessThanOrEqual(100, $result['score']);
    }

    public function testAnalyzeDetectsShortTitle()
    {
        $result = $this->optimizer->analyze('Fone', []);

        $this->assertContains('Título muito curto', $result['issues']);
        $this->assertLessThan(50, $result['score']);
    }

    public function testAnalyzeDetectsLongTitle()
    {
        $longTitle = str_repeat('Palavra ', 20); // > 60 chars
        $result = $this->optimizer->analyze($longTitle, []);

        $this->assertContains('Título muito longo', $result['issues']);
    }

    public function testAnalyzeDetectsMissingKeywords()
    {
        $result = $this->optimizer->analyze('Produto XYZ', [
            'keywords' => ['bluetooth', 'wireless', 'tws']
        ]);

        $this->assertArrayHasKey('missing_keywords', $result);
        $this->assertCount(3, $result['missing_keywords']);
    }

    public function testAnalyzeRecognizesGoodTitle()
    {
        $result = $this->optimizer->analyze(
            'Fone Bluetooth TWS Sony Esportivo Resistente Água IPX7',
            [
                'brand' => 'Sony',
                'keywords' => ['bluetooth', 'tws', 'esportivo']
            ]
        );

        $this->assertGreaterThan(70, $result['score']);
        $this->assertNotEmpty($result['strengths']);
    }

    public function testValidateAcceptsGoodTitle()
    {
        $result = $this->optimizer->validate(
            'Fone Bluetooth TWS Sony Esportivo com Cancelamento Ruído'
        );

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateRejectsInvalidChars()
    {
        $result = $this->optimizer->validate('Fone!!! Melhor!!! Compre!!!');

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testCompareVersionsReturnsScores()
    {
        $versions = [
            'Fone Bluetooth',
            'Fone Bluetooth TWS Sony',
            'Fone Bluetooth TWS Sony Esportivo Resistente Água'
        ];

        $result = $this->optimizer->compareVersions($versions, [
            'brand' => 'Sony'
        ]);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        
        foreach ($result as $comparison) {
            $this->assertArrayHasKey('title', $comparison);
            $this->assertArrayHasKey('score', $comparison);
            $this->assertArrayHasKey('analysis', $comparison);
        }
    }

    public function testCalculateScoreWithAllFactors()
    {
        $analysis = [
            'char_count' => 55,
            'brand_present' => true,
            'keywords_found' => ['bluetooth', 'tws'],
            'missing_keywords' => ['wireless'],
            'has_numbers' => false,
            'has_invalid_chars' => false
        ];

        $score = $this->optimizer->calculateScore($analysis);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }
}
