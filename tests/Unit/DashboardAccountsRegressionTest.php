<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class DashboardAccountsRegressionTest extends TestCase
{
    public function testAccountsApiDoesNotExposeTokensEncryptedFlag(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Controllers/AuthController.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString('tokens_encrypted, site_id', $contents);
        $this->assertStringContainsString('nickname, email, status, token_expires_at,', $contents);
        $this->assertStringContainsString('site_id, created_at, updated_at', $contents);
    }

    public function testDeleteAccountRequestSendsCsrfToken(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/dashboard/accounts.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("method: 'DELETE'", $contents);
        $this->assertStringContainsString("'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content || ''", $contents);
    }

    public function testAccountsPageDoesNotKeepDevelopmentConsoleLogs(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/dashboard/accounts.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringNotContainsString("console.log('=== Script accounts.php iniciado ===');", $contents);
        $this->assertStringNotContainsString("console.log('loadAccounts() iniciando...');", $contents);
        $this->assertStringNotContainsString("console.log('=== connectNewAccount() chamada ===');", $contents);
    }
}
