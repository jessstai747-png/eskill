<?php

namespace Tests\Unit\Services\AI;

use PHPUnit\Framework\TestCase;
use App\Services\AI\Optimizers\TechSheetOptimizer;

class TechSheetOptimizerTest extends TestCase
{
    private TechSheetOptimizer $optimizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->optimizer = new TechSheetOptimizer();
    }

    public function testCompleteReturnProperStructure()
    {
        $attributes = [
            ['id' => 'BRAND', 'name' => 'Marca', 'value' => 'Sony'],
            ['id' => 'MODEL', 'name' => 'Modelo', 'value' => 'WF-1000XM4']
        ];

        // Use analyze method which accepts attributes directly
        $result = $this->optimizer->analyze($attributes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('completeness', $result);
        $this->assertArrayHasKey('required', $result);
        $this->assertArrayHasKey('suggested', $result);
        
        $this->assertIsFloat($result['completeness']);
        $this->assertGreaterThanOrEqual(0, $result['completeness']);
        $this->assertLessThanOrEqual(100, $result['completeness']);
    }

    public function testCompleteDetectsMissingRequired()
    {
        $attributes = [
            ['id' => 'COLOR', 'name' => 'Cor', 'value' => 'Preto']
        ];

        $result = $this->optimizer->analyze($attributes);

        $this->assertArrayHasKey('missing_required', $result);
        $this->assertNotEmpty($result['missing_required']);
    }

    public function testCompleteCalculatesCorrectPercentage()
    {
        // Test with context providing total_required
        $attributes = [
            ['id' => 'BRAND', 'value' => 'Sony'],
            ['id' => 'MODEL', 'value' => 'Test']
        ];

        $result = $this->optimizer->analyze($attributes, [
            'total_required' => 10
        ]);

        $this->assertEqualsWithDelta(20.0, $result['completeness'], 5.0);
    }

    public function testInferAttributeValueWithContext()
    {
        $context = [
            'title' => 'Fone Bluetooth Sony WF-1000XM4',
            'description' => 'Fone com Bluetooth 5.3',
            'attributes' => [
                ['id' => 'BRAND', 'value' => 'Sony']
            ]
        ];

        $result = $this->optimizer->inferValue('MODEL', $context);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('confidence', $result);
        
        if ($result['value']) {
            $this->assertGreaterThan(0, $result['confidence']);
            $this->assertLessThanOrEqual(1, $result['confidence']);
        }
    }

    public function testInferAttributeValueForCommonDefaults()
    {
        $result = $this->optimizer->inferValue('CONDITION', []);

        $this->assertEquals('new', $result['value']);
        $this->assertEquals(0.8, $result['confidence']);
    }

    public function testValidateAttributesAgainstCategory()
    {
        $attributes = [
            ['id' => 'BRAND', 'value' => 'Sony'],
            ['id' => 'MODEL', 'value' => 'WF-1000XM4'],
            ['id' => 'INVALID_ATTR', 'value' => 'test']
        ];

        $result = $this->optimizer->validate($attributes);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid_attributes', $result);
        $this->assertArrayHasKey('invalid_attributes', $result);
        
        $this->assertContains('INVALID_ATTR', $result['invalid_attributes']);
    }

    public function testGetAttributePriority()
    {
        $priorities = [
            'BRAND' => 'required',
            'MODEL' => 'required',
            'GTIN' => 'recommended',
            'RANDOM_ATTR' => 'optional'
        ];

        foreach ($priorities as $attrId => $expectedPriority) {
            $priority = $this->optimizer->getAttributePriority($attrId);
            $this->assertEquals($expectedPriority, $priority);
        }
    }

    public function testSuggestionsHaveProperFormat()
    {
        $attributes = [
            ['id' => 'BRAND', 'value' => 'Sony']
        ];

        $result = $this->optimizer->analyze($attributes);

        if (!empty($result['suggested'])) {
            foreach ($result['suggested'] as $suggestion) {
                $this->assertArrayHasKey('attribute_id', $suggestion);
                $this->assertArrayHasKey('suggested_value', $suggestion);
                $this->assertArrayHasKey('confidence', $suggestion);
                $this->assertArrayHasKey('priority', $suggestion);
                
                $this->assertIsFloat($suggestion['confidence']);
                $this->assertGreaterThan(0, $suggestion['confidence']);
                $this->assertLessThanOrEqual(1, $suggestion['confidence']);
            }
        }
    }

    public function testCompleteWithEmptyAttributes()
    {
        $result = $this->optimizer->analyze([]);

        $this->assertEquals(0.0, $result['completeness']);
        $this->assertNotEmpty($result['missing_required']);
    }
}
