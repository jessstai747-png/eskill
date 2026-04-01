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

    public function testOAuthConfigStatusCanRead503PayloadWithoutRequestJsonFailure(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/dashboard/accounts.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("window.ApiClient && typeof window.ApiClient.json === 'function'", $contents);
        $this->assertStringContainsString("response = await window.ApiClient.json('/api/auth/oauth-config-status');", $contents);
        $this->assertStringContainsString("const data = response.data?.data || {};", $contents);
        $this->assertStringNotContainsString("const response = await requestJson('/api/auth/oauth-config-status');", $contents);
    }

    public function testOAuthDashboardShowsInlineFailureAndDisablesButtonsOnDiagnosticError(): void
    {
        $path = dirname(__DIR__, 2) . '/app/Views/dashboard/accounts.php';
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString("oauthConfigReady = false;", $contents);
        $this->assertStringContainsString("setConnectButtonsEnabled(false, fallbackMessage);", $contents);
        $this->assertStringContainsString("title.textContent = 'Falha ao validar OAuth';", $contents);
    }
}
