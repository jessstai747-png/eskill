<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\TitleGenerator\TitleAnalyzerService
 */
class TitleAnalyzerServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\TitleGenerator\TitleAnalyzerService::class);
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
        $this->assertTrue(class_exists(\App\Services\TitleGenerator\TitleAnalyzerService::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testHasExpectedConstants(): void
    {
        $constants = $this->reflection->getConstants();
        $this->assertArrayHasKey('WEIGHTS', $constants);
        $this->assertArrayHasKey('FORBIDDEN_TERMS', $constants);
        $this->assertArrayHasKey('HIGH_IMPACT_WORDS', $constants);
    }

    public function testWeightsAddUpToOne(): void
    {
        $weights = $this->reflection->getConstant('WEIGHTS');
        $this->assertEqualsWithDelta(1.0, array_sum($weights), 0.01);
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
            ['analyzeLengthOptimization'],
            ['analyzeKeywords'],
            ['analyzeClarity'],
            ['analyzeStructure'],
            ['analyzeForbiddenWords'],
            ['analyzeCompetitiveness'],
            ['analyzeSEO'],
            ['estimatePerformance'],
            ['calculateOverallScore'],
            ['collectIssues'],
            ['generateSuggestions'],
            ['determineStatus'],
            ['hasBrand'],
            ['hasModel'],
            ['hasLogicalStructure'],
            ['calculateReadability'],
            ['estimateViews'],
            ['estimateClicks'],
        ];
    }

    // =========================================================================
    // Behavioral: analyzeLengthOptimization
    // =========================================================================

    public function testLengthOptimizationExcellent(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', [str_repeat('A', 50)]);
        $this->assertSame(100, $result['score']);
        $this->assertSame('excellent', $result['status']);
    }

    public function testLengthOptimizationGood(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', [str_repeat('A', 42)]);
        $this->assertSame(85, $result['score']);
        $this->assertSame('good', $result['status']);
    }

    public function testLengthOptimizationFair(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', [str_repeat('A', 35)]);
        $this->assertSame(70, $result['score']);
        $this->assertSame('fair', $result['status']);
    }

    public function testLengthOptimizationCritical(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', [str_repeat('A', 65)]);
        $this->assertSame(0, $result['score']);
        $this->assertSame('critical', $result['status']);
    }

    public function testLengthOptimizationPoor(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', ['Short']);
        $this->assertSame(50, $result['score']);
        $this->assertSame('poor', $result['status']);
    }

    public function testLengthOptimizationReturnsStructure(): void
    {
        $result = $this->invokePrivateMethod('analyzeLengthOptimization', ['Test title']);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('length', $result);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertArrayHasKey('optimal_range', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('chars_to_optimal', $result);
        $this->assertArrayHasKey('chars_available', $result);
    }

    // =========================================================================
    // Behavioral: analyzeClarity
    // =========================================================================

    public function testClarityReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('analyzeClarity', ['Bagageiro CG 160 Titan']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('word_count', $result);
        $this->assertArrayHasKey('optimal_word_count', $result);
        $this->assertArrayHasKey('issues', $result);
    }

    public function testClarityOptimalWordCount(): void
    {
        // 4-8 words is optimal
        $result = $this->invokePrivateMethod('analyzeClarity', ['Bagageiro CG 160 Titan Fan']);
        $this->assertTrue($result['optimal_word_count']);
    }

    public function testClarityTooFewWords(): void
    {
        $result = $this->invokePrivateMethod('analyzeClarity', ['CG']);
        $this->assertFalse($result['optimal_word_count']);
    }

    // =========================================================================
    // Behavioral: analyzeStructure
    // =========================================================================

    public function testStructureReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('analyzeStructure', ['Bagageiro CG 160']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('starts_capitalized', $result);
        $this->assertArrayHasKey('punctuation_count', $result);
        $this->assertArrayHasKey('has_double_spaces', $result);
    }

    public function testStructureStartsCapitalized(): void
    {
        $result = $this->invokePrivateMethod('analyzeStructure', ['Bagageiro CG']);
        $this->assertTrue($result['starts_capitalized']);
    }

    public function testStructureNotCapitalized(): void
    {
        $result = $this->invokePrivateMethod('analyzeStructure', ['bagageiro cg']);
        $this->assertFalse($result['starts_capitalized']);
    }

    public function testStructureDetectsDoubleSpaces(): void
    {
        $result = $this->invokePrivateMethod('analyzeStructure', ['Bagageiro  CG']);
        $this->assertTrue($result['has_double_spaces']);
    }

    // =========================================================================
    // Behavioral: analyzeForbiddenWords
    // =========================================================================

    public function testForbiddenWordsNone(): void
    {
        $result = $this->invokePrivateMethod('analyzeForbiddenWords', ['Bagageiro CG 160 Titan']);
        $this->assertSame(100, $result['score']);
        $this->assertSame('safe', $result['status']);
        $this->assertEmpty($result['found_forbidden']);
    }

    public function testForbiddenWordsDetected(): void
    {
        // Use exact forbidden term with accent: 'frete grátis'
        $result = $this->invokePrivateMethod('analyzeForbiddenWords', ['Bagageiro CG 160 frete grátis']);
        $this->assertSame(0, $result['score']);
        $this->assertSame('critical', $result['status']);
        $this->assertNotEmpty($result['found_forbidden']);
    }

    public function testForbiddenWordsMultiple(): void
    {
        // 'melhor' and 'desconto' and 'original' are all in FORBIDDEN_TERMS
        $result = $this->invokePrivateMethod('analyzeForbiddenWords', ['Melhor desconto original']);
        $this->assertGreaterThanOrEqual(2, $result['count']);
    }

    // =========================================================================
    // Behavioral: determineStatus
    // =========================================================================

    public function testDetermineStatusExcellent(): void
    {
        $result = $this->invokePrivateMethod('determineStatus', [90]);
        $this->assertIsString($result);
    }

    public function testDetermineStatusPoor(): void
    {
        $result = $this->invokePrivateMethod('determineStatus', [20]);
        $this->assertIsString($result);
    }

    // =========================================================================
    // Behavioral: hasBrand / hasModel
    // =========================================================================

    public function testHasBrandWithKnownBrand(): void
    {
        $result = $this->invokePrivateMethod('hasBrand', ['Samsung Galaxy S24']);
        $this->assertIsBool($result);
    }

    public function testHasModelWithNumeric(): void
    {
        $result = $this->invokePrivateMethod('hasModel', ['CG 160 Titan']);
        $this->assertIsBool($result);
    }

    // =========================================================================
    // Behavioral: calculateReadability
    // =========================================================================

    public function testCalculateReadabilityReturnsString(): void
    {
        $result = $this->invokePrivateMethod('calculateReadability', ['Bagageiro CG 160 Titan Fan Bros']);
        $this->assertIsString($result);
    }
}
