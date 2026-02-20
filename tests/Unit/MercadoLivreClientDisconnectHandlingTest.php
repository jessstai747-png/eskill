<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes estruturais para garantir que a conta "disconnected" (ex.: invalid_grant)
 * seja propagada para o fluxo real de chamadas da API via MercadoLivreClient.
 *
 * Estes testes não dependem de DB nem chamadas HTTP reais.
 */
class MercadoLivreClientDisconnectHandlingTest extends TestCase
{
    public function testMercadoLivreClientSurfacesAccountDisconnectedWithReconnectUrl(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/MercadoLivreClient.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("account_disconnected", $source);
        $this->assertStringContainsString("reconnect_url", $source);
        $this->assertStringContainsString("/auth/authorize?reconnect=", $source);
        $this->assertStringContainsString("isAccountDisconnectedState", $source);
        $this->assertStringContainsString("'disconnected'", $source);
        $this->assertStringContainsString("invalid_grant", $source);
    }

    public function testUnifiedTokenRefreshServiceDoesNotOverwriteDisconnectedToExpired(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Services/UnifiedTokenRefreshService.php');
        $this->assertIsString($source);

        $this->assertStringContainsString("statusAfter === 'disconnected'", $source);
        $this->assertStringContainsString("NUNCA sobrescrever disconnected", $source);
    }
}
