<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\AIContentGeneratorService
 */
class AIContentGeneratorServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\AIContentGeneratorService::class);
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
        $this->assertTrue(class_exists(\App\Services\AIContentGeneratorService::class));
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
            ['generateProductDescription'],
            ['dispatchGeneration'],
            ['generateOptimizedTitle'],
            ['generateBulletPoints'],
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
            ['extractImportantWords'],
            ['validateMLTitle'],
            ['calculateKeywordDensity'],
            ['calculateReadabilityScore'],
            ['calculateSEOScore'],
            ['generateSuggestions'],
            ['optimizeForSEO'],
            ['extractFeatures'],
            ['findSEOOpportunities'],
        ];
    }

    // =========================================================================
    // Behavioral: extractImportantWords
    // =========================================================================

    public function testExtractImportantWordsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('extractImportantWords', ['Bagageiro CG 160 Titan Fan Bros Moto Honda']);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testExtractImportantWordsRemovesStopwords(): void
    {
        $result = $this->invokePrivateMethod('extractImportantWords', ['Bagageiro de CG para moto com acessorio']);
        // "de", "para", "com" are stopwords — should not appear
        $this->assertNotContains('de', $result);
        $this->assertNotContains('para', $result);
        $this->assertNotContains('com', $result);
    }

    public function testExtractImportantWordsMaxEight(): void
    {
        $text = 'Bagageiro CG 160 Titan Fan Bros Moto Honda Yamaha Suzuki Kawasaki preto aluminio reforçado';
        $result = $this->invokePrivateMethod('extractImportantWords', [$text]);
        $this->assertLessThanOrEqual(8, count($result));
    }

    public function testExtractImportantWordsSortsByLengthDesc(): void
    {
        $result = $this->invokePrivateMethod('extractImportantWords', ['Bagageiro CG Titan']);
        if (count($result) >= 2) {
            $this->assertGreaterThanOrEqual(mb_strlen($result[1]), mb_strlen($result[0]));
        } else {
            $this->assertNotEmpty($result);
        }
    }

    public function testExtractImportantWordsEmptyInput(): void
    {
        $result = $this->invokePrivateMethod('extractImportantWords', ['']);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // Behavioral: validateMLTitle
    // =========================================================================

    public function testValidateMLTitleReturnsStructure(): void
    {
        $result = $this->invokePrivateMethod('validateMLTitle', ['Bagageiro CG 160', ['brand' => 'Honda']]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('adjustments', $result);
    }

    public function testValidateMLTitleAddsBrandWhenMissing(): void
    {
        $result = $this->invokePrivateMethod('validateMLTitle', ['CG 160 Titan', ['brand' => 'Honda']]);
        $this->assertStringContainsString('Honda', $result['title']);
        $this->assertContains('brand_added', $result['adjustments']);
    }

    public function testValidateMLTitleDoesNotAddBrandWhenPresent(): void
    {
        $result = $this->invokePrivateMethod('validateMLTitle', ['Honda CG 160 Titan', ['brand' => 'Honda']]);
        $this->assertNotContains('brand_added', $result['adjustments']);
    }

    public function testValidateMLTitleTruncatesAt60(): void
    {
        $longTitle = str_repeat('A', 70);
        $result = $this->invokePrivateMethod('validateMLTitle', [$longTitle, ['brand' => '']]);
        $this->assertLessThanOrEqual(60, mb_strlen($result['title']));
        $this->assertContains('truncated', $result['adjustments']);
    }

    public function testValidateMLTitleTrimsWhitespace(): void
    {
        $result = $this->invokePrivateMethod('validateMLTitle', ['  Bagageiro  CG  160  ', []]);
        $this->assertStringNotContainsString('  ', $result['title']);
    }

    // =========================================================================
    // Behavioral: calculateKeywordDensity
    // =========================================================================

    public function testCalculateKeywordDensityReturnsFloat(): void
    {
        $result = $this->invokePrivateMethod('calculateKeywordDensity', ['Bagageiro CG 160 Titan Bagageiro']);
        $this->assertIsFloat($result);
    }

    public function testCalculateKeywordDensityEmptyContent(): void
    {
        $result = $this->invokePrivateMethod('calculateKeywordDensity', ['']);
        $this->assertEquals(0.0, $result);
    }

    public function testCalculateKeywordDensityPositiveForRepeatedWords(): void
    {
        $result = $this->invokePrivateMethod('calculateKeywordDensity', ['Bagageiro moto Bagageiro moto Bagageiro']);
        $this->assertGreaterThan(0.0, $result);
    }

    // =========================================================================
    // Behavioral: calculateReadabilityScore
    // =========================================================================

    public function testCalculateReadabilityScoreReturnsFloat(): void
    {
        $result = $this->invokePrivateMethod('calculateReadabilityScore', ['Frase curta. Outra frase.']);
        $this->assertIsFloat($result);
    }

    public function testCalculateReadabilityScoreBetween0And100(): void
    {
        $text = 'Este bagageiro CG 160 Titan serve como acessório essencial para moto. Ele oferece praticidade no dia a dia.';
        $result = $this->invokePrivateMethod('calculateReadabilityScore', [$text]);
        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThanOrEqual(100, $result);
    }

    public function testCalculateReadabilityScoreShortSentencesHighScore(): void
    {
        $result = $this->invokePrivateMethod('calculateReadabilityScore', ['Simples. Direto.']);
        $this->assertGreaterThan(50, $result);
    }

    // =========================================================================
    // Behavioral: calculateSEOScore
    // =========================================================================

    public function testCalculateSEOScoreReturnsFloat(): void
    {
        $result = $this->invokePrivateMethod('calculateSEOScore', ['Bagageiro CG 160 Titan Fan Bros Honda Moto']);
        $this->assertIsFloat($result);
    }

    public function testCalculateSEOScoreEmptyContent(): void
    {
        $result = $this->invokePrivateMethod('calculateSEOScore', ['']);
        // Empty string: length=0 -> lengthScore=0, density=0 -> densityScore=0
        // But readabilityScore for empty is 100 (0 avg words), so score = 0*0.4 + 0*0.3 + 100*0.3 = 30.0
        $this->assertEquals(30.0, $result);
    }

    public function testCalculateSEOScoreHigherForLongerContent(): void
    {
        $short = $this->invokePrivateMethod('calculateSEOScore', ['CG 160']);
        $long = $this->invokePrivateMethod('calculateSEOScore', [
            'Bagageiro CG 160 Titan para motos Honda. Este acessório oferece segurança e praticidade para o dia a dia. Compatível com Titan 160 e Fan 160. Material em aço reforçado.'
        ]);
        $this->assertGreaterThan($short, $long);
    }

    // =========================================================================
    // Behavioral: generateSuggestions
    // =========================================================================

    public function testGenerateSuggestionsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('generateSuggestions', [50.0, 'Texto curto']);
        $this->assertIsArray($result);
    }

    public function testGenerateSuggestionsSuggestsExpansionForShortContent(): void
    {
        $result = $this->invokePrivateMethod('generateSuggestions', [30.0, 'Texto curto']);
        $found = false;
        foreach ($result as $suggestion) {
            if (stripos($suggestion, 'Expandir') !== false || stripos($suggestion, 'descri') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // =========================================================================
    // Behavioral: findSEOOpportunities
    // =========================================================================

    public function testFindSEOOpportunitiesShortTitle(): void
    {
        $result = $this->invokePrivateMethod('findSEOOpportunities', [['title' => 'CG']]);
        $this->assertNotEmpty($result);
    }

    public function testFindSEOOpportunitiesMissingBrand(): void
    {
        $result = $this->invokePrivateMethod('findSEOOpportunities', [['title' => 'Bagageiro CG 160', 'brand' => 'Honda']]);
        $found = false;
        foreach ($result as $opp) {
            if (stripos($opp, 'Marca') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testFindSEOOpportunitiesShortDescription(): void
    {
        $result = $this->invokePrivateMethod('findSEOOpportunities', [['title' => 'Bagageiro CG 160 Titan Fan Bros Honda', 'description' => 'Curto']]);
        $found = false;
        foreach ($result as $opp) {
            if (stripos($opp, 'curta') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function testFindSEOOpportunitiesFewAttributes(): void
    {
        $result = $this->invokePrivateMethod('findSEOOpportunities', [
            ['title' => 'Bagageiro CG 160 Titan Fan Bros Honda', 'attributes' => [['name' => 'cor', 'value_name' => 'preto']]]
        ]);
        $found = false;
        foreach ($result as $opp) {
            if (stripos($opp, 'atributos') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    // =========================================================================
    // Behavioral: extractFeatures
    // =========================================================================

    public function testExtractFeaturesReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('extractFeatures', [
            ['attributes' => [['name' => 'Cor', 'value_name' => 'Preto'], ['name' => 'Material', 'value_name' => 'Aço']]],
            []
        ]);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testExtractFeaturesMaxEight(): void
    {
        $attrs = [];
        for ($i = 0; $i < 15; $i++) {
            $attrs[] = ['name' => "Attr{$i}", 'value_name' => "Val{$i}"];
        }
        $result = $this->invokePrivateMethod('extractFeatures', [['attributes' => $attrs], []]);
        $this->assertLessThanOrEqual(8, count($result));
    }

    public function testExtractFeaturesSkipsInvalidAttributes(): void
    {
        $result = $this->invokePrivateMethod('extractFeatures', [
            ['attributes' => ['invalid', ['name' => '', 'value_name' => ''], ['name' => 'Valid', 'value_name' => 'Yes']]],
            []
        ]);
        $this->assertCount(1, $result);
    }
}
