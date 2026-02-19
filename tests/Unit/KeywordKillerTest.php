<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Testes unitários para KeywordKiller
 * 
 * Testa extração de keywords, long-tail, modificadores e intenção de compra
 * SEM dependências externas (ML API, DB, AI)
 */
class KeywordKillerTest extends TestCase
{
    private object $killer;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new \ReflectionClass(\App\Services\AI\SEO\KeywordKiller::class);
        $this->killer = $ref->newInstanceWithoutConstructor();
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $ref = new \ReflectionMethod($this->killer, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->killer, $args);
    }

    // ========================================
    // extractBaseKeywords()
    // ========================================

    public function testExtractBaseKeywordsFromTitle(): void
    {
        $product = [
            'title' => 'Kit Reparo Carburador Honda CG 150 Titan',
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        $this->assertContains('kit', $keywords);
        $this->assertContains('reparo', $keywords);
        $this->assertContains('carburador', $keywords);
        $this->assertContains('honda', $keywords);
        $this->assertContains('titan', $keywords);
        // '150' has 3 chars, should be included
        $this->assertContains('150', $keywords);
    }

    public function testExtractBaseKeywordsFiltersStopwords(): void
    {
        $product = [
            'title' => 'Kit de Reparo para Motor',
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        $this->assertNotContains('de', $keywords);
        $this->assertNotContains('para', $keywords);
    }

    public function testExtractBaseKeywordsFiltersShortWords(): void
    {
        $product = [
            'title' => 'A BC Kit Motor XY',
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        // 'A' (1 char) and 'BC' (2 chars) should be filtered
        $this->assertNotContains('a', $keywords);
        $this->assertNotContains('bc', $keywords);
        // 'XY' has 2 chars, should be filtered
        $this->assertNotContains('xy', $keywords);
    }

    public function testExtractBaseKeywordsIncludesBrand(): void
    {
        $product = [
            'title' => 'Motor',
            'brand' => 'Yamaha',
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        $this->assertContains('yamaha', $keywords);
    }

    public function testExtractBaseKeywordsIncludesAttributes(): void
    {
        $product = [
            'title' => 'Motor',
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Honda'],
                ['id' => 'COLOR', 'value_name' => 'Preto'],
                ['id' => 'IRRELEVANT', 'value_name' => 'Algo'],
            ],
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        $this->assertContains('honda', $keywords);
        $this->assertContains('preto', $keywords);
        $this->assertNotContains('algo', $keywords); // IRRELEVANT not in key attributes
    }

    public function testExtractBaseKeywordsReturnsUnique(): void
    {
        $product = [
            'title' => 'Honda Motor Honda',
            'brand' => 'Honda',
        ];

        $keywords = $this->invoke('extractBaseKeywords', [$product]);

        $this->assertSame(count($keywords), count(array_unique($keywords)));
    }

    // ========================================
    // isStopword()
    // ========================================

    public function testIsStopword(): void
    {
        $this->assertTrue($this->invoke('isStopword', ['de']));
        $this->assertTrue($this->invoke('isStopword', ['para']));
        $this->assertTrue($this->invoke('isStopword', ['com']));
        $this->assertFalse($this->invoke('isStopword', ['motor']));
        $this->assertFalse($this->invoke('isStopword', ['carburador']));
    }

    // ========================================
    // extractModifiers()
    // ========================================

    public function testExtractModifiers(): void
    {
        $product = [
            'attributes' => [
                ['id' => 'COLOR', 'value_name' => 'Vermelho'],
                ['id' => 'SIZE', 'value_name' => 'Grande'],
                ['id' => 'BRAND', 'value_name' => 'Honda'], // Not a modifier attr
            ],
        ];

        $modifiers = $this->invoke('extractModifiers', [$product]);

        $this->assertCount(2, $modifiers, 'Deve extrair 2 modificadores (COLOR e SIZE)');
        $this->assertSame('cor', $modifiers[0]['type']);
        $this->assertSame('vermelho', $modifiers[0]['value']);
        $this->assertSame('tamanho', $modifiers[1]['type']);
    }

    public function testExtractModifiersEmptyAttributes(): void
    {
        $product = ['attributes' => []];
        $modifiers = $this->invoke('extractModifiers', [$product]);
        $this->assertEmpty($modifiers);
    }

    // ========================================
    // generateBuyingIntentKeywords()
    // ========================================

    public function testGenerateBuyingIntentKeywords(): void
    {
        $product = [
            'title' => 'Kit Carburador Honda CG 150',
        ];

        $keywords = $this->invoke('generateBuyingIntentKeywords', [$product]);

        $this->assertNotEmpty($keywords);
        
        // Must contain "comprar [produto]" pattern
        $comprarKeywords = array_filter($keywords, fn($k) => str_starts_with($k['keyword'], 'comprar'));
        $this->assertNotEmpty($comprarKeywords, 'Deve conter keyword "comprar [produto]"');
        
        // Must contain "onde comprar" pattern
        $ondeComprarKeywords = array_filter($keywords, fn($k) => str_starts_with($k['keyword'], 'onde comprar'));
        $this->assertNotEmpty($ondeComprarKeywords, 'Deve conter keyword "onde comprar [produto]"');
    }

    public function testBuyingIntentKeywordsHaveType(): void
    {
        $product = ['title' => 'Produto Teste'];
        $keywords = $this->invoke('generateBuyingIntentKeywords', [$product]);

        foreach ($keywords as $kw) {
            $this->assertArrayHasKey('type', $kw);
            $this->assertContains($kw['type'], ['transactional', 'commercial', 'informational']);
        }
    }

    public function testBuyingIntentKeywordsMaxLength60(): void
    {
        $product = ['title' => 'Kit Reparo Carburador Honda CG 150 Titan NX 400 Falcon Original'];
        $keywords = $this->invoke('generateBuyingIntentKeywords', [$product]);

        foreach ($keywords as $kw) {
            $this->assertLessThanOrEqual(60, mb_strlen($kw['keyword']),
                "Keyword '{$kw['keyword']}' excede 60 caracteres");
        }
    }

    // ========================================
    // generateLongTail()
    // ========================================

    public function testGenerateLongTailIsLimited(): void
    {
        $product = [
            'title' => 'Carburador Honda',
            'brand' => 'Honda',
            'attributes' => [
                ['id' => 'COLOR', 'value_name' => 'Preto'],
                ['id' => 'SIZE', 'value_name' => 'Grande'],
            ],
        ];
        $base = ['carburador', 'honda'];

        $result = $this->invoke('generateLongTail', [$product, $base]);

        $this->assertLessThanOrEqual(20, count($result), 'Long-tail deve ser limitado a 20');
    }

    public function testGenerateLongTailSortedByLength(): void
    {
        $product = ['title' => 'Motor', 'brand' => 'Honda'];
        $base = ['motor', 'honda', 'original'];

        $result = $this->invoke('generateLongTail', [$product, $base]);

        for ($i = 1; $i < count($result); $i++) {
            $this->assertGreaterThanOrEqual(
                mb_strlen($result[$i - 1]),
                mb_strlen($result[$i]),
                'Long-tail keywords devem ser ordenadas por comprimento'
            );
        }
    }

    // ========================================
    // classifyIntent()
    // ========================================

    public function testClassifyIntent(): void
    {
        $this->assertSame('transactional', $this->invoke('classifyIntent', ['comprar']));
        $this->assertSame('transactional', $this->invoke('classifyIntent', ['preço']));
        $this->assertSame('commercial', $this->invoke('classifyIntent', ['melhor']));
        $this->assertSame('commercial', $this->invoke('classifyIntent', ['original']));
        $this->assertSame('informational', $this->invoke('classifyIntent', ['frete grátis']));
    }

    // ========================================
    // Constants consistency
    // ========================================

    public function testKeywordTypesAreComplete(): void
    {
        $ref = new \ReflectionClass(\App\Services\AI\SEO\KeywordKiller::class);
        $types = $ref->getConstant('KEYWORD_TYPES');

        $expected = ['primary', 'secondary', 'long_tail', 'modifier', 'intent', 'competitor'];
        foreach ($expected as $type) {
            $this->assertArrayHasKey($type, $types, "Tipo '{$type}' deve existir em KEYWORD_TYPES");
        }
    }

    public function testBuyingIntentWordsNotEmpty(): void
    {
        $ref = new \ReflectionClass(\App\Services\AI\SEO\KeywordKiller::class);
        $words = $ref->getConstant('BUYING_INTENT_WORDS');

        $this->assertNotEmpty($words);
        $this->assertGreaterThanOrEqual(10, count($words), 'Deve haver pelo menos 10 buying intent words');
    }
}
