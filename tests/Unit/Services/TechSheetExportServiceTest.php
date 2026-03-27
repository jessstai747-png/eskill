<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetExportService;

class TechSheetExportServiceTest extends TestCase
{
    private int $testAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testAccountId = 1;
    }

    public function testExportToCSVReturnsString(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        $csv = $service->exportToCSV(['limit' => 10]);

        $this->assertIsString($csv);
    }

    public function testCSVHasValidHeader(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        $csv = $service->exportToCSV(['limit' => 10]);

        if (!empty($csv)) {
            $lines = explode("\n", $csv);
            $header = $lines[0];
            
            $this->assertStringContainsString('item_id', $header);
            $this->assertStringContainsString('attribute_id', $header);
            $this->assertStringContainsString('suggested_value', $header);
        } else {
            $this->assertTrue(true); // Sem dados é válido
        }
    }

    public function testExportToJSONReturnsValidJSON(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        $json = $service->exportToJSON(['limit' => 10]);

        $this->assertIsString($json);
        
        $data = json_decode($json, true);
        $this->assertIsArray($data);
    }

    public function testJSONExportHasRequiredFields(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        $json = $service->exportToJSON(['limit' => 10]);

        $data = json_decode($json, true);
        
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertArrayHasKey('suggestions', $data);
        $this->assertArrayHasKey('total', $data);
    }

    public function testImportFromCSVReturnsValidStructure(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        
        $csvContent = "item_id,title,category_id,attribute_id,attribute_name,suggested_value,source,confidence,status,created_at\n";
        $csvContent .= "MLB123,Test,CAT1,ATTR1,Color,Blue,title,80,pending,2026-01-01 10:00:00\n";
        
        $result = $service->importFromCSV($csvContent, ['overwrite' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('imported', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    public function testImportFromJSONReturnsValidStructure(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        
        $jsonContent = json_encode([
            'version' => '1.0',
            'suggestions' => [
                [
                    'item_id' => 'MLB456',
                    'attribute_id' => 'ATTR2',
                    'attribute_name' => 'Size',
                    'suggested_value' => 'Large',
                    'source' => 'benchmark',
                    'confidence' => 90,
                ],
            ],
        ]);
        
        $result = $service->importFromJSON($jsonContent, ['overwrite' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('imported', $result);
    }

    public function testExportCategoryTemplateReturnsJSON(): void
    {
        $service = new TechSheetExportService($this->testAccountId);
        $template = $service->exportCategoryTemplate('MLB1234');

        $this->assertIsString($template);
        
        $data = json_decode($template, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('type', $data);
        $this->assertEquals('category_template', $data['type']);
    }
}
