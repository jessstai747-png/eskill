<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class MercadoLivreWebhookIngressHardeningTest extends TestCase
{
    public function testControllerUsesMarkQueuedForAsyncWebhookDispatch(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/MercadoLivreWebhookController.php');
        $this->assertIsString($source);
        $this->assertStringContainsString("markQueued('mercadolivre', \$eventHash, \$jobId", $source);
    }

    public function testControllerAllowsWebhookSecretOverrideViaGetenv(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/MercadoLivreWebhookController.php');
        $this->assertIsString($source);
        $this->assertStringContainsString("getenv('ML_WEBHOOK_SECRET')", $source);
    }

    public function testWebhookRoutesDoNotExposeDashboardAlias(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Routes/webhooks.php');
        $this->assertIsString($source);
        $this->assertStringNotContainsString("\$router->post('dashboard'", $source);
    }
}
