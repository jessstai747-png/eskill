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
}
