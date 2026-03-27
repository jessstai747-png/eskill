<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class FrontendIntegrationRegressionTest extends TestCase
{
    public function testCloneRealtimeDashboardUsesRegisteredSseRoute(): void
    {
        $viewPath = dirname(__DIR__, 2) . '/app/Views/dashboard/clone_realtime_dashboard.php';
        $contents = (string) file_get_contents($viewPath);

        $this->assertStringContainsString('/api/catalog/clone/dashboard/stream', $contents);
        $this->assertStringNotContainsString('/api/clone/dashboard/stream', $contents);
    }

    public function testCsrfHelperDoesNotUseUnsupportedXhrGetRequestHeader(): void
    {
        $scriptPath = dirname(__DIR__, 2) . '/public/js/csrf-helper.js';
        $contents = (string) file_get_contents($scriptPath);

        $this->assertStringNotContainsString('getRequestHeader(', $contents);
        $this->assertStringContainsString('XMLHttpRequest.prototype.setRequestHeader', $contents);
    }
}
