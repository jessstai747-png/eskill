<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreAuthService;
use PHPUnit\Framework\TestCase;

class MercadoLivreAuthServiceOauthUrlTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            return;
        }

        session_start();
        $_SESSION = [];
    }

    public function testGetAuthUrlBuildsOAuthUrlAndStoresStateContext(): void
    {
        $service = new MercadoLivreAuthService(null, [
            'mercadolivre' => [
                'app_id' => '123456',
                'client_secret' => 'secret',
                'redirect_uri' => 'https://eskill.com.br/auth/callback',
                'auth_url' => 'https://auth.mercadolibre.com/authorization',
                'site_id' => 'MLB',
            ],
        ]);

        $url = $service->getAuthUrl(100198);

        $this->assertStringStartsWith('https://auth.mercadolibre.com/authorization?', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('client_id=123456', $url);
        $this->assertStringContainsString('redirect_uri=', $url);
        $this->assertStringContainsString('code_challenge=', $url);
        $this->assertArrayHasKey('ml_oauth_state', $_SESSION);
        $this->assertArrayHasKey('ml_oauth_states', $_SESSION);
        $this->assertArrayHasKey('ml_oauth_pkce', $_SESSION);
        $this->assertArrayHasKey($_SESSION['ml_oauth_state'], $_SESSION['ml_oauth_states']);
        $this->assertArrayHasKey($_SESSION['ml_oauth_state'], $_SESSION['ml_oauth_pkce']);
        $this->assertSame(100198, $_SESSION['ml_oauth_states'][$_SESSION['ml_oauth_state']]['user_id']);
    }

    public function testGetAuthUrlRejectsInvalidRedirectUri(): void
    {
        $service = new MercadoLivreAuthService(null, [
            'mercadolivre' => [
                'app_id' => '123456',
                'client_secret' => 'secret',
                'redirect_uri' => 'not-a-valid-url',
                'auth_url' => 'https://auth.mercadolibre.com/authorization',
                'site_id' => 'MLB',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ML_REDIRECT_URI inválido');

        $service->getAuthUrl(100198);
    }

    public function testGetAuthUrlRejectsPlaceholderConfiguration(): void
    {
        $service = new MercadoLivreAuthService(null, [
            'key' => 'change_me_with_32+_chars_minimum________________',
            'mercadolivre' => [
                'app_id' => 'your_mercadolibre_app_id',
                'client_secret' => 'your_mercadolibre_client_secret',
                'redirect_uri' => 'https://your-domain.com/dashboard',
                'auth_url' => 'https://auth.mercadolibre.com/authorization',
                'site_id' => 'MLB',
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('ML_APP_ID ausente ou com valor placeholder');

        $service->getAuthUrl(100198);
    }

    public function testGetOAuthConfigDiagnosticsDetectsPlaceholderAndAppKeyIssues(): void
    {
        $service = new MercadoLivreAuthService(null, [
            'key' => 'short-key',
            'url' => 'https://eskill.com.br',
            'mercadolivre' => [
                'app_id' => 'your_mercadolibre_app_id',
                'client_secret' => 'your_mercadolibre_client_secret',
                'redirect_uri' => 'https://your-domain.com/dashboard',
                'auth_url' => 'https://auth.mercadolibre.com/authorization',
                'token_url' => 'https://api.mercadolibre.com/oauth/token',
                'api_url' => 'https://api.mercadolibre.com',
                'site_id' => 'MLB',
            ],
        ]);

        $diagnostics = $service->getOAuthConfigDiagnostics();

        $this->assertFalse($diagnostics['ready']);
        $this->assertContains('ML_APP_ID ausente ou com valor placeholder', $diagnostics['issues']);
        $this->assertContains('ML_CLIENT_SECRET ausente ou com valor placeholder', $diagnostics['issues']);
        $this->assertContains('ML_REDIRECT_URI ausente ou com valor placeholder', $diagnostics['issues']);
        $this->assertContains('APP_KEY ausente, placeholder ou menor que 32 caracteres', $diagnostics['issues']);
    }
}
