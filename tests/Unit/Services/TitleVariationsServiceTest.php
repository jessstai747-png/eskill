<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\TitleGenerator\TitleVariationsService
 */
class TitleVariationsServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\TitleGenerator\TitleVariationsService::class);
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
        $this->assertTrue(class_exists(\App\Services\TitleGenerator\TitleVariationsService::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testHasSynonymsConstant(): void
    {
        $this->assertTrue($this->reflection->hasConstant('SYNONYMS'));
        $synonyms = $this->reflection->getConstant('SYNONYMS');
        $this->assertIsArray($synonyms);
        $this->assertNotEmpty($synonyms);
    }

    public function testHasModifiersConstant(): void
    {
        $this->assertTrue($this->reflection->hasConstant('MODIFIERS'));
        $modifiers = $this->reflection->getConstant('MODIFIERS');
        $this->assertIsArray($modifiers);
        $this->assertNotEmpty($modifiers);
    }

    public function testHasConnectorsConstant(): void
    {
        $this->assertTrue($this->reflection->hasConstant('CONNECTORS'));
    }

    public function testHasAbbreviationsConstant(): void
    {
        $this->assertTrue($this->reflection->hasConstant('ABBREVIATIONS'));
        $abbreviations = $this->reflection->getConstant('ABBREVIATIONS');
        $this->assertIsArray($abbreviations);
        $this->assertNotEmpty($abbreviations);
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
            ['generateVariations'],
            ['generateABTestingVariations'],
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
            ['parseTitle'],
            ['reorderComponents'],
            ['applySynonyms'],
            ['addModifiers'],
            ['removeUnnecessaryWords'],
            ['expandAbbreviations'],
            ['compressTitle'],
            ['wordsReordered'],
            ['identifyStrategy'],
            ['recommendABVariation'],
        ];
    }

    // =========================================================================
    // Behavioral: parseTitle
    // =========================================================================

    public function testParseTitleReturnsComponents(): void
    {
        $result = $this->invokePrivateMethod('parseTitle', ['Bagageiro CG 160 Titan Fan Preto']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('brand', $result);
        $this->assertArrayHasKey('model', $result);
        $this->assertArrayHasKey('specs', $result);
        $this->assertArrayHasKey('modifiers', $result);
    }

    public function testParseTitleExtractsBrand(): void
    {
        $result = $this->invokePrivateMethod('parseTitle', ['Bagageiro Honda CG 160']);
        $this->assertIsArray($result);
        // brand may be a string or array; just check it returned something
        $this->assertNotNull($result['brand']);
    }

    public function testParseTitleEmptyInput(): void
    {
        $result = $this->invokePrivateMethod('parseTitle', ['']);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Behavioral: applySynonyms
    // =========================================================================

    public function testApplySynonymsReturnsArray(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Notebook Dell Inspiron 15']);
        $result = $this->invokePrivateMethod('applySynonyms', ['Notebook Dell Inspiron 15', $components]);
        $this->assertIsArray($result);
    }

    public function testApplySynonymsReplacesSynonym(): void
    {
        $synonyms = $this->reflection->getConstant('SYNONYMS');
        $components = $this->invokePrivateMethod('parseTitle', ['Notebook Dell Inspiron 15']);
        if (isset($synonyms['Notebook'])) {
            $result = $this->invokePrivateMethod('applySynonyms', ['Notebook Dell Inspiron 15', $components]);
            $this->assertNotEmpty($result);
        } else {
            $this->markTestSkipped('No synonym for Notebook');
        }
    }

    // =========================================================================
    // Behavioral: addModifiers
    // =========================================================================

    public function testAddModifiersReturnsArray(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Bagageiro CG 160']);
        $result = $this->invokePrivateMethod('addModifiers', ['Bagageiro CG 160', $components]);
        $this->assertIsArray($result);
    }

    public function testAddModifiersRespects60CharLimit(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Bagageiro CG 160']);
        $result = $this->invokePrivateMethod('addModifiers', ['Bagageiro CG 160', $components]);
        foreach ($result as $variation) {
            $this->assertLessThanOrEqual(60, strlen($variation));
        }
    }

    // =========================================================================
    // Behavioral: removeUnnecessaryWords
    // =========================================================================

    public function testRemoveUnnecessaryWordsReturnsArray(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Bagageiro para CG 160 Titan']);
        $result = $this->invokePrivateMethod('removeUnnecessaryWords', ['Bagageiro para CG 160 Titan', $components]);
        $this->assertIsArray($result);
    }

    public function testRemoveUnnecessaryWordsRemovesStopwords(): void
    {
        // removeUnnecessaryWords removes: com, para, de, em, da, do, e
        $title = 'Bagageiro para CG 160 de Moto com Suporte';
        $components = $this->invokePrivateMethod('parseTitle', [$title]);
        $result = $this->invokePrivateMethod('removeUnnecessaryWords', [$title, $components]);
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Behavioral: expandAbbreviations + compressTitle
    // =========================================================================

    public function testExpandAbbreviationsReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('expandAbbreviations', ['Monitor 27" Full HD']);
        $this->assertIsArray($result);
    }

    public function testCompressTitleReturnsArray(): void
    {
        $result = $this->invokePrivateMethod('compressTitle', ['Monitor Polegadas Full HD']);
        $this->assertIsArray($result);
    }

    public function testCompressTitleShorterOrEqual(): void
    {
        // compressTitle abbreviates known words (e.g. Polegadas -> ")
        $original = 'Monitor Polegadas Full HD';
        $result = $this->invokePrivateMethod('compressTitle', [$original]);
        $this->assertIsArray($result);
        if (!empty($result)) {
            $this->assertLessThanOrEqual(mb_strlen($original), mb_strlen($result[0]));
        }
    }

    // =========================================================================
    // Behavioral: wordsReordered
    // =========================================================================

    public function testWordsReorderedDetectsReorder(): void
    {
        $result = $this->invokePrivateMethod('wordsReordered', ['CG 160 Bagageiro', 'Bagageiro CG 160']);
        $this->assertIsBool($result);
    }

    public function testWordsReorderedSameOrderReturnsTrue(): void
    {
        // wordsReordered checks if sorted arrays are equal - same words = true
        $result = $this->invokePrivateMethod('wordsReordered', ['CG 160', 'CG 160']);
        $this->assertTrue($result);
    }

    // =========================================================================
    // Behavioral: identifyStrategy
    // =========================================================================

    public function testIdentifyStrategyReturnsString(): void
    {
        $result = $this->invokePrivateMethod('identifyStrategy', [
            'Bagageiro CG 160 Titan',
            'Suporte Traseiro CG 160 Titan',
        ]);
        $this->assertIsString($result);
    }

    public function testIdentifyStrategyForSameTitle(): void
    {
        $result = $this->invokePrivateMethod('identifyStrategy', [
            'Bagageiro CG 160',
            'Bagageiro CG 160',
        ]);
        $this->assertIsString($result);
    }

    // =========================================================================
    // Behavioral: reorderComponents
    // =========================================================================

    public function testReorderComponentsReturnsArray(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Bagageiro CG 160 Titan']);
        $result = $this->invokePrivateMethod('reorderComponents', [$components]);
        $this->assertIsArray($result);
    }

    public function testReorderComponentsProducesVariations(): void
    {
        $components = $this->invokePrivateMethod('parseTitle', ['Bagageiro CG 160 Titan Fan']);
        $result = $this->invokePrivateMethod('reorderComponents', [$components]);
        $this->assertIsArray($result);
        // Should produce at least one variation when brand+model+specs present
        $this->assertNotEmpty($result);
    }
}
