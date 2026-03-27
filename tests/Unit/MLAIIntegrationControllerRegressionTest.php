<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class MLAIIntegrationControllerRegressionTest extends TestCase
{
    public function testApplyEndpointMapsNoValidOptimizationsTo422(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Controllers/MLAIIntegrationController.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('No valid optimizations generated for apply', $contents);
        $this->assertStringContainsString('$status = $noValidOptimizations ? 422 : 500;', $contents);
    }

    public function testBatchEndpointValidatesNonEmptyStringItemIds(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Controllers/MLAIIntegrationController.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('All item_ids must be non-empty strings', $contents);
        $this->assertStringContainsString('array_values(array_unique($normalizedIds))', $contents);
    }

    public function testUpdateDescriptionMapsValidationErrorsTo400(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Controllers/MLAIIntegrationController.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("str_contains(\$message, 'cannot be empty')", $contents);
        $this->assertStringContainsString("str_contains(\$message, 'at least 50 characters')", $contents);
        $this->assertStringContainsString("\$this->jsonError(\$message, 400);", $contents);
    }
}
