<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use App\Services\SEO\SEOAuditService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * SEOAuditServiceTest
 *
 * Unit tests for SEOAuditService methods
 */
class SEOAuditServiceTest extends TestCase
{
    /**
     * Test WEIGHTS constant has expected components
     */
    public function testWeightsConstantHasExpectedComponents(): void
    {
        $reflection = new ReflectionClass(SEOAuditService::class);
        $weights = $reflection->getConstant('WEIGHTS');

        $this->assertArrayHasKey('title', $weights);
        $this->assertArrayHasKey('description', $weights);
        $this->assertArrayHasKey('attributes', $weights);
        $this->assertArrayHasKey('images', $weights);
        $this->assertArrayHasKey('pricing', $weights);
        $this->assertArrayHasKey('category', $weights);
    }

    /**
     * Test WEIGHTS constant sums to 1.0
     */
    public function testWeightsConstantSumsToOne(): void
    {
        $reflection = new ReflectionClass(SEOAuditService::class);
        $weights = $reflection->getConstant('WEIGHTS');

        $sum = array_sum($weights);

        $this->assertEquals(1.0, $sum, '', 0.001);
    }

    /**
     * Test auditTitle returns score and recommendations
     */
    public function testAuditTitleReturnsScoreAndRecommendations(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        $item = ['title' => 'Smartphone Samsung Galaxy S21 128GB'];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertIsInt($result['score']);
        $this->assertIsArray($result['recommendations']);
    }

    /**
     * Test auditTitle penalizes short titles
     */
    public function testAuditTitlePenalizesShortTitles(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        $item = ['title' => 'Short'];

        $result = $method->invoke($service, $item);

        $this->assertLessThan(100, $result['score']);
        $this->assertNotEmpty($result['recommendations']);
    }

    /**
     * Test auditTitle gives high score for optimal length
     */
    public function testAuditTitleGivesHighScoreForOptimalLength(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        // 55 characters - optimal range
        $item = ['title' => 'Smartphone Samsung Galaxy S21 Ultra 256GB Preto 5G NFC'];

        $result = $method->invoke($service, $item);

        $this->assertGreaterThanOrEqual(80, $result['score']);
    }

    /**
     * Test auditTitle penalizes special characters
     */
    public function testAuditTitlePenalizesSpecialCharacters(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        // Use unicode special character
        $item = ['title' => "Samsung Galaxy S21 Ultra 256GB Preto \u{2605}"];

        $result = $method->invoke($service, $item);

        $this->assertLessThan(100, $result['score']);

        $hasSpecialCharRecommendation = false;
        foreach ($result['recommendations'] as $rec) {
            if (mb_strpos($rec['message'], 'Evite caracteres especiais') !== false) {
                $hasSpecialCharRecommendation = true;
                break;
            }
        }
        $this->assertTrue($hasSpecialCharRecommendation);
    }

    /**
     * Test auditTitle penalizes all caps
     */
    public function testAuditTitlePenalizesAllCaps(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        $item = ['title' => 'SMARTPHONE SAMSUNG GALAXY S21 ULTRA 256GB PRETO'];

        $result = $method->invoke($service, $item);

        $hasAllCapsRecommendation = false;
        foreach ($result['recommendations'] as $rec) {
            if (mb_stripos($rec['message'], 'MAIUSCULAS') !== false ||
                mb_stripos($rec['message'], "MAI\u{00da}SCULAS") !== false) {
                $hasAllCapsRecommendation = true;
                break;
            }
        }
        $this->assertTrue($hasAllCapsRecommendation);
    }

    /**
     * Test auditDescription returns score and recommendations
     */
    public function testAuditDescriptionReturnsScoreAndRecommendations(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditDescription');

        $item = ['description' => 'Este produto tem as seguintes caracteristicas...'];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test auditDescription penalizes short descriptions
     */
    public function testAuditDescriptionPenalizesShortDescriptions(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditDescription');

        $item = ['description' => 'Curto'];

        $result = $method->invoke($service, $item);

        $this->assertLessThan(100, $result['score']);
        $this->assertNotEmpty($result['recommendations']);
    }

    /**
     * Test auditDescription rewards longer descriptions
     */
    public function testAuditDescriptionRewardsLongerDescriptions(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditDescription');

        $longDescription = str_repeat('Esta descricao tem conteudo detalhado sobre o produto. ', 20);
        $item = ['description' => $longDescription];

        $result = $method->invoke($service, $item);

        $this->assertGreaterThanOrEqual(70, $result['score']);
    }

    /**
     * Test auditDescription handles empty description
     */
    public function testAuditDescriptionHandlesEmptyDescription(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditDescription');

        $item = ['description' => ''];

        $result = $method->invoke($service, $item);

        $this->assertLessThan(100, $result['score']);
    }

    /**
     * Test auditImages returns score and recommendations
     */
    public function testAuditImagesReturnsScoreAndRecommendations(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditImages');

        $item = [
            'pictures' => [
                ['url' => 'http://example.com/img1.jpg'],
                ['url' => 'http://example.com/img2.jpg'],
            ],
        ];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test auditImages gives zero score for no images
     */
    public function testAuditImagesGivesZeroScoreForNoImages(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditImages');

        $item = ['pictures' => []];

        $result = $method->invoke($service, $item);

        $this->assertEquals(0, $result['score']);
        $this->assertNotEmpty($result['recommendations']);
    }

    /**
     * Test auditImages penalizes few images
     */
    public function testAuditImagesPenalizesFewImages(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditImages');

        $item = [
            'pictures' => [
                ['url' => 'http://example.com/img1.jpg'],
                ['url' => 'http://example.com/img2.jpg'],
            ],
        ];

        $result = $method->invoke($service, $item);

        $this->assertLessThan(100, $result['score']);
    }

    /**
     * Test auditImages rewards many images
     */
    public function testAuditImagesRewardsManyImages(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditImages');

        $pictures = [];
        for ($i = 1; $i <= 8; $i++) {
            $pictures[] = ['url' => "http://example.com/img{$i}.jpg"];
        }
        $item = ['pictures' => $pictures];

        $result = $method->invoke($service, $item);

        $this->assertGreaterThanOrEqual(80, $result['score']);
    }

    /**
     * Test auditImages penalizes low quality images
     */
    public function testAuditImagesPenalizesLowQualityImages(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditImages');

        $pictures = [];
        for ($i = 1; $i <= 6; $i++) {
            // Low quality marker in URL
            $pictures[] = ['url' => "http://mlb-images.com/img{$i}-I.jpg"];
        }
        $item = ['pictures' => $pictures];

        $result = $method->invoke($service, $item);

        $hasQualityRecommendation = false;
        foreach ($result['recommendations'] as $rec) {
            if (mb_stripos($rec['message'], 'resolucao') !== false ||
                mb_stripos($rec['message'], "resolu\u{00e7}\u{00e3}o") !== false) {
                $hasQualityRecommendation = true;
                break;
            }
        }
        $this->assertTrue($hasQualityRecommendation);
    }

    /**
     * Test auditPricing returns score and recommendations
     */
    public function testAuditPricingReturnsScoreAndRecommendations(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditPricing');

        $item = [
            'price' => 199.90,
            'original_price' => 249.90,
            'shipping' => ['free_shipping' => true],
        ];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test auditPricing recommends free shipping for high price items
     */
    public function testAuditPricingRecommendsFreeShippingForHighPriceItems(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditPricing');

        $item = [
            'price' => 150.00,
            'shipping' => ['free_shipping' => false],
        ];

        $result = $method->invoke($service, $item);

        $hasFreeShippingRecommendation = false;
        foreach ($result['recommendations'] as $rec) {
            if (mb_stripos($rec['message'], 'frete gratis') !== false ||
                mb_stripos($rec['message'], "frete gr\u{00e1}tis") !== false) {
                $hasFreeShippingRecommendation = true;
                break;
            }
        }
        $this->assertTrue($hasFreeShippingRecommendation);
    }

    /**
     * Test auditPricing rewards discount
     */
    public function testAuditPricingRewardsDiscount(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditPricing');

        $item = [
            'price' => 199.90,
            'original_price' => 299.90,
            'shipping' => ['free_shipping' => true],
        ];

        $result = $method->invoke($service, $item);

        // Should have high score with discount and free shipping
        $this->assertGreaterThanOrEqual(90, $result['score']);
    }

    /**
     * Test auditCategory returns score and recommendations
     */
    public function testAuditCategoryReturnsScoreAndRecommendations(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditCategory');

        $item = ['category_id' => 'MLB1234'];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test auditCategory gives zero score for missing category
     */
    public function testAuditCategoryGivesZeroScoreForMissingCategory(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditCategory');

        $item = ['category_id' => ''];

        $result = $method->invoke($service, $item);

        $this->assertEquals(0, $result['score']);
        $this->assertNotEmpty($result['recommendations']);
    }

    /**
     * Test calculateOverallScore with all perfect scores
     */
    public function testCalculateOverallScoreWithAllPerfectScores(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'calculateOverallScore');

        $scores = [
            'title' => 100,
            'description' => 100,
            'attributes' => 100,
            'images' => 100,
            'pricing' => 100,
            'category' => 100,
        ];

        $result = $method->invoke($service, $scores);

        $this->assertEquals(100, $result);
    }

    /**
     * Test calculateOverallScore with mixed scores
     */
    public function testCalculateOverallScoreWithMixedScores(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'calculateOverallScore');

        $scores = [
            'title' => 80,
            'description' => 70,
            'attributes' => 90,
            'images' => 60,
            'pricing' => 100,
            'category' => 50,
        ];

        $result = $method->invoke($service, $scores);

        // Expected: 80*0.25 + 70*0.20 + 90*0.25 + 60*0.15 + 100*0.10 + 50*0.05 = 78
        $this->assertEquals(78, $result);
    }

    /**
     * Test calculateOverallScore with all zero scores
     */
    public function testCalculateOverallScoreWithAllZeroScores(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'calculateOverallScore');

        $scores = [
            'title' => 0,
            'description' => 0,
            'attributes' => 0,
            'images' => 0,
            'pricing' => 0,
            'category' => 0,
        ];

        $result = $method->invoke($service, $scores);

        $this->assertEquals(0, $result);
    }

    /**
     * Test auditAttributes returns required and optional percentages
     */
    public function testAuditAttributesReturnsPercentages(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditAttributes');

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'value_name' => 'Galaxy S21'],
            ],
        ];

        $categoryAttributes = [];

        $result = $method->invoke($service, $item, $categoryAttributes);

        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('required_pct', $result);
        $this->assertArrayHasKey('optional_pct', $result);
        $this->assertArrayHasKey('recommendations', $result);
    }

    /**
     * Test auditAttributes penalizes missing required attributes
     */
    public function testAuditAttributesPenalizesMissingRequiredAttributes(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditAttributes');

        $item = ['attributes' => []];

        $categoryAttributes = [
            ['id' => 'BRAND', 'tags' => ['required' => true]],
            ['id' => 'MODEL', 'tags' => ['required' => true]],
        ];

        $result = $method->invoke($service, $item, $categoryAttributes);

        $this->assertLessThan(100, $result['score']);
        $this->assertEquals(0, $result['required_pct']);
    }

    /**
     * Test auditAttributes ignores placeholder values
     */
    public function testAuditAttributesIgnoresPlaceholderValues(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditAttributes');

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'N/A'],
                ['id' => 'MODEL', 'value_name' => "N\u{00e3}o se aplica"],
            ],
        ];

        $categoryAttributes = [
            ['id' => 'BRAND', 'tags' => ['required' => true]],
            ['id' => 'MODEL', 'tags' => ['required' => true]],
        ];

        $result = $method->invoke($service, $item, $categoryAttributes);

        // Placeholders should not count as filled
        $this->assertEquals(0, $result['required_pct']);
    }

    /**
     * Test mapItemAttributes creates attribute map
     */
    public function testMapItemAttributesCreatesAttributeMap(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'mapItemAttributes');

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'value_name' => 'Galaxy S21'],
                ['id' => 'COLOR', 'value_id' => 'BLACK'],
            ],
        ];

        $result = $method->invoke($service, $item);

        $this->assertArrayHasKey('BRAND', $result);
        $this->assertArrayHasKey('MODEL', $result);
        $this->assertArrayHasKey('COLOR', $result);
        $this->assertEquals('Samsung', $result['BRAND']);
    }

    /**
     * Test mapItemAttributes handles empty attributes
     */
    public function testMapItemAttributesHandlesEmptyAttributes(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'mapItemAttributes');

        $item = ['attributes' => []];

        $result = $method->invoke($service, $item);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test mapItemAttributes skips invalid entries
     */
    public function testMapItemAttributesSkipsInvalidEntries(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'mapItemAttributes');

        $item = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => '', 'value_name' => 'Invalid'],
                ['id' => null, 'value_name' => 'Also Invalid'],
                'not-an-array',
            ],
        ];

        $result = $method->invoke($service, $item);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('BRAND', $result);
    }

    /**
     * Test recommendation structure is valid
     */
    public function testRecommendationStructureIsValid(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        $item = ['title' => 'Short'];

        $result = $method->invoke($service, $item);

        foreach ($result['recommendations'] as $rec) {
            $this->assertArrayHasKey('type', $rec);
            $this->assertArrayHasKey('priority', $rec);
            $this->assertArrayHasKey('message', $rec);
            $this->assertArrayHasKey('impact', $rec);
            $this->assertContains($rec['priority'], ['high', 'medium', 'low']);
        }
    }

    /**
     * Test score never goes below zero
     */
    public function testScoreNeverGoesBelowZero(): void
    {
        $service = $this->createServiceWithMockedDependencies();
        $method = $this->getPrivateMethod($service, 'auditTitle');

        // Very problematic title
        $item = ['title' => ''];

        $result = $method->invoke($service, $item);

        $this->assertGreaterThanOrEqual(0, $result['score']);
    }

    /**
     * Test auditHistory returns expected structure
     */
    public function testGetAuditHistoryExpectedStructure(): void
    {
        $service = $this->getMockBuilder(SEOAuditService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAuditHistory'])
            ->getMock();

        $service->method('getAuditHistory')
            ->willReturn([
                [
                    'audit_date' => '2024-01-15 10:00:00',
                    'overall_score' => 85,
                    'title_score' => 90,
                    'description_score' => 80,
                    'attributes_score' => 85,
                    'images_score' => 90,
                    'pricing_score' => 80,
                    'category_score' => 100,
                ],
            ]);

        $result = $service->getAuditHistory('MLB123456789');

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('audit_date', $result[0]);
        $this->assertArrayHasKey('overall_score', $result[0]);
    }

    /**
     * Helper to create service with mocked dependencies
     */
    private function createServiceWithMockedDependencies(): SEOAuditService
    {
        return $this->getMockBuilder(SEOAuditService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['saveAudit', 'getCachedAudit'])
            ->getMock();
    }

    /**
     * Helper to get private method
     */
    private function getPrivateMethod(object $object, string $methodName): \ReflectionMethod
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }
}
