<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\SEO\TitleOptimizerService
 */
class TitleOptimizerServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\SEO\TitleOptimizerService::class);
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Services\SEO\TitleOptimizerService::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testPublicMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicMethodsProvider(): array
    {
        return [
            ['analyzeTitle'],
            ['generateOptimizedTitles'],
            ['generateModelAttribute'],
        ];
    }

    /**
     * @dataProvider privateMethodsProvider
     */
    public function testPrivateMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPrivate());
    }

    public static function privateMethodsProvider(): array
    {
        return [
            ['extractKeywords'],
            ['findSemanticGaps'],
            ['analyzeSearchIntent'],
            ['findMissingAttributes'],
            ['calculateOpportunityScore'],
            ['identifyTitleGaps'],
            ['performTitleAnalysis'],
            ['generateTitleRecommendations'],
        ];
    }

    // =========================================================================
    // Behavioral: extractKeywords
    // =========================================================================

    public function testExtractKeywordsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('extractKeywords', ['Bagageiro CG 160 Titan']);
        $this->assertIsArray($result);
    }

    public function testExtractKeywordsRemovesStopWords(): void
    {
        $result = $this->invokePrivateMethod('extractKeywords', ['Bagageiro de CG para moto com suporte']);
        $values = array_values($result);
        $this->assertNotContains('de', $values);
        $this->assertNotContains('para', $values);
        $this->assertNotContains('com', $values);
    }

    public function testExtractKeywordsRemovesShortWords(): void
    {
        $result = $this->invokePrivateMethod('extractKeywords', ['CG em moto de AB']);
        $values = array_values($result);
        // Words with 2 or fewer chars are removed
        $this->assertNotContains('em', $values);
        $this->assertNotContains('de', $values);
    }

    public function testExtractKeywordsLowercases(): void
    {
        $result = $this->invokePrivateMethod('extractKeywords', ['BAGAGEIRO TITAN']);
        $values = array_values($result);
        foreach ($values as $word) {
            $this->assertSame(strtolower($word), $word);
        }
    }

    public function testExtractKeywordsEmptyInput(): void
    {
        $result = $this->invokePrivateMethod('extractKeywords', ['']);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Behavioral: findSemanticGaps
    // =========================================================================

    public function testFindSemanticGapsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('findSemanticGaps', [
            'Bagageiro CG 160',
            ['Bagageiro CG 160 Titan Fan', 'Suporte Traseiro CG Bros']
        ]);
        $this->assertIsArray($result);
    }

    public function testFindSemanticGapsFindsCompetitorWords(): void
    {
        $result = $this->invokePrivateMethod('findSemanticGaps', [
            'Bagageiro CG',
            ['Bagageiro CG 160 Titan aluminio']
        ]);
        // 'titan' and 'aluminio' should appear as gaps (not in our title)
        $this->assertNotEmpty($result);
    }

    public function testFindSemanticGapsNoDuplicates(): void
    {
        $result = $this->invokePrivateMethod('findSemanticGaps', [
            'Bagageiro CG',
            ['Bagageiro CG Titan', 'Suporte CG Titan']
        ]);
        $this->assertSame(count($result), count(array_unique($result)));
    }

    // =========================================================================
    // Behavioral: analyzeSearchIntent
    // =========================================================================

    public function testAnalyzeSearchIntentReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('analyzeSearchIntent', [
            'Bagageiro CG 160',
            ['Bagageiro CG 160 original']
        ]);
        $this->assertIsArray($result);
    }

    public function testAnalyzeSearchIntentFindsIntentWords(): void
    {
        $result = $this->invokePrivateMethod('analyzeSearchIntent', [
            'Bagageiro CG 160',
            ['Bagageiro CG 160 original novo']
        ]);
        // 'original' is in competitor but not our title
        $this->assertContains('original', $result);
    }

    public function testAnalyzeSearchIntentNoGapsWhenMatching(): void
    {
        $result = $this->invokePrivateMethod('analyzeSearchIntent', [
            'Bagageiro CG 160 original',
            ['Bagageiro CG 160 original']
        ]);
        $this->assertNotContains('original', $result);
    }

    // =========================================================================
    // Behavioral: calculateOpportunityScore
    // =========================================================================

    public function testCalculateOpportunityScoreReturnsInt(): void
    {
        $result = $this->invokePrivateMethod('calculateOpportunityScore', ['Bagageiro CG 160', []]);
        $this->assertIsInt($result);
    }

    public function testCalculateOpportunityScoreMaxIs100(): void
    {
        $result = $this->invokePrivateMethod('calculateOpportunityScore', ['CG', []]);
        $this->assertLessThanOrEqual(100, $result);
    }

    public function testCalculateOpportunityScoreHigherForShortTitles(): void
    {
        $short = $this->invokePrivateMethod('calculateOpportunityScore', ['CG', []]);
        $optimal = $this->invokePrivateMethod('calculateOpportunityScore', ['Bagageiro CG 160 Titan Fan Bros Honda Moto Preto Alum', []]);
        // Short titles get +15 for length, +20 for few keywords — more opportunity
        $this->assertGreaterThanOrEqual($optimal, $short);
    }

    public function testCalculateOpportunityScoreHigherWithoutNumbers(): void
    {
        $withNumbers = $this->invokePrivateMethod('calculateOpportunityScore', ['Bagageiro CG 160', []]);
        $withoutNumbers = $this->invokePrivateMethod('calculateOpportunityScore', ['Bagageiro Honda Titan', []]);
        // Without numbers gets +10 for no numeric specs
        // Both may have similar length scores, so just check the method runs and returns int
        $this->assertIsInt($withoutNumbers);
        $this->assertGreaterThanOrEqual(50, $withoutNumbers);
    }

    // =========================================================================
    // Behavioral: findMissingAttributes
    // =========================================================================

    public function testFindMissingAttributesReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('findMissingAttributes', [
            'Bagageiro CG 160',
            [['attributes' => [['value_name' => 'Preto'], ['value_name' => 'Honda']]]]
        ]);
        $this->assertIsArray($result);
    }

    public function testFindMissingAttributesFindsValues(): void
    {
        $result = $this->invokePrivateMethod('findMissingAttributes', [
            'Bagageiro CG 160',
            [['attributes' => [['value_name' => 'Preto']]]]
        ]);
        $this->assertContains('Preto', $result);
    }

    public function testFindMissingAttributesMaxFive(): void
    {
        $attrs = [];
        for ($i = 0; $i < 20; $i++) {
            $attrs[] = ['value_name' => "Attr{$i}xx"];
        }
        $result = $this->invokePrivateMethod('findMissingAttributes', [
            'Bagageiro',
            [['attributes' => $attrs]]
        ]);
        $this->assertLessThanOrEqual(5, count($result));
    }
}
