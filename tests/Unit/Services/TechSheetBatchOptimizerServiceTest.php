<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\TechSheetBatchOptimizerService;

class TechSheetBatchOptimizerServiceTest extends TestCase
{
    private int $testAccountId = 999;

    public function testProcessBatchReturnsValidStructure(): void
    {
        $service = new TechSheetBatchOptimizerService($this->testAccountId);
        
        $processor = function($itemId, $itemData) {
            return true; // Sempre sucesso
        };
        
        $result = $service->processBatch(['ITEM1', 'ITEM2'], $processor, [
            'batch_size' => 10,
        ]);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('batches', $result);
    }

    public function testAnalyzeBatchPerformanceReturnsHistory(): void
    {
        $service = new TechSheetBatchOptimizerService($this->testAccountId);
        $result = $service->analyzeBatchPerformance();
        
        $this->assertIsArray($result);
    }

    public function testGetOptimizationSuggestionsReturnsArray(): void
    {
        $service = new TechSheetBatchOptimizerService($this->testAccountId);
        $result = $service->getOptimizationSuggestions();
        
        $this->assertIsArray($result);
        
        foreach ($result as $suggestion) {
            $this->assertArrayHasKey('type', $suggestion);
            $this->assertArrayHasKey('priority', $suggestion);
            $this->assertArrayHasKey('description', $suggestion);
        }
    }

    public function testClearOldCacheReturnsCount(): void
    {
        $service = new TechSheetBatchOptimizerService($this->testAccountId);
        $result = $service->clearOldCache();
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }
}
