<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\TokenDashboardController;
use Tests\TestCase;

/**
 * @covers \App\Controllers\TokenDashboardController
 */
class TokenDashboardControllerTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TokenDashboardController::class));
    }

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'getMetrics',
            'listAccounts',
            'refreshAccount',
            'refreshAll',
            'getAuditHistory',
            'getStats',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(TokenDashboardController::class, $method),
                "TokenDashboardController deve ter método {$method}()"
            );
        }
    }

    public function testListAccountsSupportsExpiringFilter(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Controllers/TokenDashboardController.php');

        $this->assertStringContainsString("'expiring'", $source);
        $this->assertStringContainsString('DATE_ADD(NOW(), INTERVAL 24 HOUR)', $source);
    }

    public function testListAccountsSupportsFrontendSortAliases(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Controllers/TokenDashboardController.php');

        $this->assertStringContainsString("'name'", $source);
        $this->assertStringContainsString("'failure_count'", $source);
    }

    public function testListAccountsEnrichesApiValidationDiagnostics(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Controllers/TokenDashboardController.php');

        $this->assertStringContainsString('api_validation_status', $source);
        $this->assertStringContainsString('diagnostic_label', $source);
        $this->assertStringContainsString('diagnostic_message', $source);
    }

    public function testGetMetricsReturnsVoid(): void
    {
        $reflection = new \ReflectionMethod(TokenDashboardController::class, 'getMetrics');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('void', $returnType->getName());
    }
}
