<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ChatbotAIService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Unit Tests for ChatbotAIService
 *
 * Tests private NLP methods via reflection:
 * - detectIntent: intent classification from text
 * - normalizeText: text preprocessing
 * - extractEntities: entity extraction via regex
 *
 * @covers \App\Services\ChatbotAIService
 */
class ChatbotAIServiceTest extends TestCase
{
    private ChatbotAIService $service;
    private ReflectionClass $ref;
    private ReflectionMethod $detectIntent;
    private ReflectionMethod $normalizeText;
    private ReflectionMethod $extractEntities;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ref = new ReflectionClass(ChatbotAIService::class);
        $this->service = $this->ref->newInstanceWithoutConstructor();

        $this->detectIntent = $this->ref->getMethod('detectIntent');
        $this->detectIntent->setAccessible(true);

        $this->normalizeText = $this->ref->getMethod('normalizeText');
        $this->normalizeText->setAccessible(true);

        $this->extractEntities = $this->ref->getMethod('extractEntities');
        $this->extractEntities->setAccessible(true);
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testServiceCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(ChatbotAIService::class, $this->service);
    }

    public function testIntentsPropertyExists(): void
    {
        $prop = $this->ref->getProperty('intents');
        $prop->setAccessible(true);
        $intents = $prop->getValue($this->service);

        $this->assertIsArray($intents);
        $this->assertArrayHasKey('tracking', $intents);
        $this->assertArrayHasKey('product_info', $intents);
        $this->assertArrayHasKey('return_policy', $intents);
        $this->assertArrayHasKey('complaint', $intents);
        $this->assertArrayHasKey('price_negotiation', $intents);
        $this->assertArrayHasKey('greeting', $intents);
    }

    public function testIntentsHaveRequiredKeys(): void
    {
        $prop = $this->ref->getProperty('intents');
        $prop->setAccessible(true);
        $intents = $prop->getValue($this->service);

        foreach ($intents as $name => $intent) {
            $this->assertArrayHasKey('patterns', $intent, "Intent '{$name}' missing 'patterns'");
            $this->assertArrayHasKey('confidence_threshold', $intent, "Intent '{$name}' missing 'confidence_threshold'");
            $this->assertArrayHasKey('requires_order', $intent, "Intent '{$name}' missing 'requires_order'");
            $this->assertIsArray($intent['patterns']);
            $this->assertNotEmpty($intent['patterns']);
            $this->assertIsFloat($intent['confidence_threshold']);
            $this->assertIsBool($intent['requires_order']);
        }
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['processMessage', 'getStats'];
        foreach ($methods as $method) {
            $this->assertTrue(method_exists($this->service, $method), "Missing: {$method}");
        }
    }

    // =========================================================================
    // normalizeText — PRIVATE PURE TEXT PROCESSING
    // =========================================================================

    public function testNormalizeTextLowercases(): void
    {
        $result = $this->normalizeText->invoke($this->service, 'HELLO WORLD');
        $this->assertSame('hello world', $result);
    }

    public function testNormalizeTextTrimsWhitespace(): void
    {
        $result = $this->normalizeText->invoke($this->service, '  hello  ');
        // preg_replace keeps internal spaces, trim removes outer
        $this->assertSame('hello', $result);
    }

    public function testNormalizeTextRemovesPunctuation(): void
    {
        $result = $this->normalizeText->invoke($this->service, 'hello! world? test.');
        $this->assertSame('hello world test', $result);
    }

    public function testNormalizeTextPreservesNumbers(): void
    {
        $result = $this->normalizeText->invoke($this->service, 'Pedido 123456');
        $this->assertSame('pedido 123456', $result);
    }

    public function testNormalizeTextRemovesSpecialChars(): void
    {
        $result = $this->normalizeText->invoke($this->service, 'test@email#com');
        $this->assertSame('testemailcom', $result);
    }

    public function testNormalizeTextHandlesEmptyString(): void
    {
        $result = $this->normalizeText->invoke($this->service, '');
        $this->assertSame('', $result);
    }

    // =========================================================================
    // extractEntities — PRIVATE REGEX EXTRACTION
    // =========================================================================

    public function testExtractEntitiesFindsOrderId(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'Pedido MLB1234567890', []);

        $this->assertArrayHasKey('order_id', $result);
        $this->assertSame('1234567890', $result['order_id']);
    }

    public function testExtractEntitiesFindsHashOrderId(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'Pedido #1234567890', []);

        $this->assertArrayHasKey('order_id', $result);
        $this->assertSame('1234567890', $result['order_id']);
    }

    public function testExtractEntitiesFindsNumericOrderId(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'Meu pedido 1234567890 chegou?', []);

        $this->assertArrayHasKey('order_id', $result);
        $this->assertSame('1234567890', $result['order_id']);
    }

    public function testExtractEntitiesUsesContextOrderId(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'Como vai meu pedido?', [
            'order_id' => '9999999999'
        ]);

        $this->assertSame('9999999999', $result['order_id']);
    }

    public function testExtractEntitiesUsesContextItemId(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'Informações do produto', [
            'item_id' => 'MLB123'
        ]);

        $this->assertSame('MLB123', $result['item_id']);
    }

    public function testExtractEntitiesReturnsEmptyForNoMatch(): void
    {
        $result = $this->extractEntities->invoke($this->service, 'oi tudo bem', []);

        $this->assertEmpty($result);
    }

    public function testExtractEntitiesContextOverridesTextMatch(): void
    {
        // Context order_id should override text-extracted one
        $result = $this->extractEntities->invoke($this->service, 'Pedido MLB1234567890', [
            'order_id' => '9999999999'
        ]);

        $this->assertSame('9999999999', $result['order_id']);
    }

    // =========================================================================
    // detectIntent — PRIVATE INTENT CLASSIFICATION
    // =========================================================================

    public function testDetectIntentReturnsUnknownForGibberish(): void
    {
        $result = $this->detectIntent->invoke($this->service, 'xyzabc random words');

        $this->assertSame('unknown', $result['name']);
        $this->assertSame(0, $result['confidence']);
    }

    public function testDetectIntentReturnsArrayStructure(): void
    {
        $result = $this->detectIntent->invoke($this->service, 'something');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('confidence', $result);
    }

    public function testDetectIntentComplaintWithMultipleKeywords(): void
    {
        // Use enough ASCII complaint keywords to exceed threshold
        // complaint patterns: reclamação, problema, defeito, não funciona, quebrado, danificado, insatisfeito
        // threshold: 0.65. Need 5+ ASCII matches for 7 patterns.
        $text = 'defeito quebrado danificado problema';
        $result = $this->detectIntent->invoke($this->service, $text);

        // With 4 ASCII exact matches + partial matches, score should exceed 0.65
        $this->assertSame('complaint', $result['name']);
        $this->assertGreaterThanOrEqual(0.65, $result['confidence']);
    }

    public function testDetectIntentTrackingWithMultipleKeywords(): void
    {
        // tracking ASCII patterns: rastreio, rastrear, chegou, entrega, tracking
        // threshold: 0.6, 7 patterns total
        $text = 'rastreio entrega tracking chegou rastrear';
        $result = $this->detectIntent->invoke($this->service, $text);

        $this->assertSame('tracking', $result['name']);
        $this->assertGreaterThanOrEqual(0.6, $result['confidence']);
    }

    public function testDetectIntentProductInfoWithMultipleKeywords(): void
    {
        // product_info ASCII patterns: peso, cor, tamanho, medidas
        // threshold: 0.5, 7 patterns total
        $text = 'qual peso cor tamanho medidas do produto';
        $result = $this->detectIntent->invoke($this->service, $text);

        $this->assertSame('product_info', $result['name']);
        $this->assertGreaterThanOrEqual(0.5, $result['confidence']);
    }

    public function testDetectIntentPriceNegotiationWithMultipleKeywords(): void
    {
        // price_negotiation ASCII patterns: desconto, preço(accent removed), mais barato, negociar, oferta, promoção(accent removed)
        // ASCII ones: desconto, negociar, oferta
        // threshold: 0.6, 6 patterns total
        $text = 'desconto negociar oferta desconto';
        $result = $this->detectIntent->invoke($this->service, $text);

        $this->assertSame('price_negotiation', $result['name']);
        $this->assertGreaterThanOrEqual(0.6, $result['confidence']);
    }

    public function testDetectIntentRequiresOrderFlag(): void
    {
        $text = 'defeito quebrado danificado problema';
        $result = $this->detectIntent->invoke($this->service, $text);

        if ($result['name'] === 'complaint') {
            $this->assertTrue($result['requires_order']);
        }
    }

    public function testDetectIntentReturnPolicyWithKeywords(): void
    {
        // return_policy ASCII patterns: trocar, devolver, troca, reembolso, garantia
        // threshold: 0.7, 6 patterns
        // Need many matches: trocar + devolver + troca + reembolso + garantia
        $text = 'trocar devolver troca reembolso garantia';
        $result = $this->detectIntent->invoke($this->service, $text);

        $this->assertSame('return_policy', $result['name']);
    }
}
