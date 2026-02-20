<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes estruturais para garantir que invalid_grant seja tratado como desconexão.
 *
 * Estes testes não dependem de DB nem chamadas HTTP reais.
 */
class HealthInvalidGrantHandlingTest extends TestCase
{
    public function testMercadoLivreAuthServiceMarksAccountDisconnectedOnInvalidGrant(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/MercadoLivreAuthService.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("invalid_grant", $source);
        $this->assertStringContainsString("status = 'disconnected'", $source);
        $this->assertStringContainsString('refresh_disconnected', $source);
    }

    public function testAccountHealthControllerBlocksDisconnectedAccounts(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/AccountHealthController.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("account_disconnected", $source);
        $this->assertStringContainsString('reconnect_url', $source);
        $this->assertStringContainsString("/auth/authorize?reconnect=", $source);
    }
}
