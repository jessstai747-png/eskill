<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MercadoLivreAuthService;
use Tests\TestCase;

/**
 * @covers \App\Services\MercadoLivreAuthService
 */
final class MercadoLivreAuthServiceUnitTest extends TestCase
{
    public function testDeveResolverTokenUrlCustomizadaDoConfig(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'token_url' => 'https://custom.mercadolivre.test/oauth/token',
                ],
            ]
        );

        $method = new \ReflectionMethod(MercadoLivreAuthService::class, 'getMercadoLivreTokenUrl');
        $method->setAccessible(true);

        $this->assertSame('https://custom.mercadolivre.test/oauth/token', $method->invoke($service));
    }

    public function testDeveCalcularBackoffExponencialComJitterConfiguravel(): void
    {
        $_ENV['ML_TRANSIENT_RETRY_BASE_SECONDS'] = '2';
        $_ENV['ML_TRANSIENT_RETRY_JITTER_SECONDS'] = '0';
        $_ENV['ML_TRANSIENT_RETRY_MAX_SECONDS'] = '30';
        putenv('ML_TRANSIENT_RETRY_BASE_SECONDS=2');
        putenv('ML_TRANSIENT_RETRY_JITTER_SECONDS=0');
        putenv('ML_TRANSIENT_RETRY_MAX_SECONDS=30');

        $service = new MercadoLivreAuthService(db: null, config: []);
        $method = new \ReflectionMethod(MercadoLivreAuthService::class, 'calculateTokenRetryDelaySeconds');
        $method->setAccessible(true);

        $this->assertSame(8, $method->invoke($service, 3));

        unset($_ENV['ML_TRANSIENT_RETRY_BASE_SECONDS'], $_ENV['ML_TRANSIENT_RETRY_JITTER_SECONDS'], $_ENV['ML_TRANSIENT_RETRY_MAX_SECONDS']);
        putenv('ML_TRANSIENT_RETRY_BASE_SECONDS');
        putenv('ML_TRANSIENT_RETRY_JITTER_SECONDS');
        putenv('ML_TRANSIENT_RETRY_MAX_SECONDS');
    }

    public function testDeveRetentarApenasFalhasTransientesNoRefresh(): void
    {
        $service = new MercadoLivreAuthService(db: null, config: []);
        $method = new \ReflectionMethod(MercadoLivreAuthService::class, 'shouldRetryTokenRequest');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 503, ''));
        $this->assertTrue($method->invoke($service, 0, 'Connection timed out'));
        $this->assertFalse($method->invoke($service, 400, ''));
    }

    public function testDeveIncluirParametrosObrigatoriosQuandoGerarAuthUrl(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'app_id' => 'CLIENT_ID',
                    'redirect_uri' => 'https://example.test/oauth/callback',
                    'auth_url' => 'https://auth.mercadolibre.com/authorization',
                ],
            ]
        );

        $url = $service->getAuthUrl(55);

        $parts = parse_url($url);
        $this->assertIsArray($parts);
        $this->assertSame('https', $parts['scheme'] ?? null);
        $this->assertSame('auth.mercadolibre.com', $parts['host'] ?? null);

        $query = [];
        parse_str($parts['query'] ?? '', $query);

        $expected = [
            'response_type' => 'code',
            'client_id' => 'CLIENT_ID',
            'redirect_uri' => 'https://example.test/oauth/callback',
            'scope' => 'read write',
            'code_challenge_method' => 'S256',
        ];

        $actual = array_intersect_key($query, $expected);
        ksort($expected);
        ksort($actual);

        $this->assertSame($expected, $actual);
        $this->assertNotSame('', (string)($query['code_challenge'] ?? ''));
    }

    public function testDevePersistirStateNaSessaoQuandoGerarAuthUrl(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'app_id' => 'CLIENT_ID',
                    'redirect_uri' => 'https://example.test/oauth/callback',
                    'auth_url' => 'https://auth.mercadolibre.com/authorization',
                ],
            ]
        );

        $url = $service->getAuthUrl(55);

        $parts = parse_url($url);
        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);

        $state = (string)($query['state'] ?? '');

        $this->assertNotSame('', $state);
        $this->assertStringStartsWith('55:', $state);
        $this->assertSame($state, $_SESSION['ml_oauth_state'] ?? null);
    }

    public function testDevePersistirPkceVerifierPorStateQuandoGerarAuthUrl(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'app_id' => 'CLIENT_ID',
                    'redirect_uri' => 'https://example.test/oauth/callback',
                    'auth_url' => 'https://auth.mercadolibre.com/authorization',
                ],
            ]
        );

        $url = $service->getAuthUrl(55);

        $parts = parse_url($url);
        $query = [];
        parse_str((string)($parts['query'] ?? ''), $query);

        $state = (string)($query['state'] ?? '');
        $verifier = $_SESSION['ml_oauth_pkce'][$state] ?? null;

        $this->assertIsString($verifier);
        $this->assertNotSame('', $verifier);
    }

    public function testDeveLancarExcecaoQuandoTrocarCodigoComStateInvalido(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'app_id' => 'CLIENT_ID',
                    'client_secret' => 'CLIENT_SECRET',
                    'redirect_uri' => 'https://example.test/oauth/callback',
                    'token_url' => 'https://api.mercadolibre.com/oauth/token',
                ],
            ]
        );

        $_SESSION['ml_oauth_state'] = 'stored-state';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Estado OAuth inválido ou expirado');

        $service->exchangeCodeForTokens('CODE', 'sent-state');
    }

    public function testDeveLancarExcecaoQuandoTrocarCodigoSemCodeVerifier(): void
    {
        $service = new MercadoLivreAuthService(
            db: null,
            config: [
                'mercadolivre' => [
                    'app_id' => 'CLIENT_ID',
                    'client_secret' => 'CLIENT_SECRET',
                    'redirect_uri' => 'https://example.test/oauth/callback',
                    'token_url' => 'https://api.mercadolibre.com/oauth/token',
                ],
            ]
        );

        $state = '1:abc';
        $_SESSION['ml_oauth_state'] = $state;
        $_SESSION['ml_oauth_pkce'] = [];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('code_verifier ausente ou expirado');

        $service->exchangeCodeForTokens('CODE', $state);
    }
}
