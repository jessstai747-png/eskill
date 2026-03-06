<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SecureTokenService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\SecureTokenService
 */
final class SecureTokenServiceUnitTest extends TestCase
{
    private function buildService(array $config): SecureTokenService
    {
        $reflection = new \ReflectionClass(SecureTokenService::class);
        $service = $reflection->newInstanceWithoutConstructor();

        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $configProperty->setValue($service, $config);

        return $service;
    }

    public function testDeveResolverTokenUrlCustomizadaDoConfig(): void
    {
        $service = $this->buildService([
            'mercadolivre' => [
                'token_url' => 'https://secure-token.example.test/oauth/token',
            ],
        ]);

        $method = new \ReflectionMethod(SecureTokenService::class, 'getMercadoLivreTokenUrl');
        $method->setAccessible(true);

        $this->assertSame('https://secure-token.example.test/oauth/token', $method->invoke($service));
    }

    public function testDeveUsarUrlPadraoQuandoConfigNaoExistir(): void
    {
        $service = $this->buildService([]);

        $method = new \ReflectionMethod(SecureTokenService::class, 'getMercadoLivreTokenUrl');
        $method->setAccessible(true);

        $this->assertSame('https://api.mercadolibre.com/oauth/token', $method->invoke($service));
    }

    public function testDeveCalcularBackoffExponencialComJitterConfiguravel(): void
    {
        $_ENV['ML_TRANSIENT_RETRY_BASE_SECONDS'] = '2';
        $_ENV['ML_TRANSIENT_RETRY_JITTER_SECONDS'] = '0';
        $_ENV['ML_TRANSIENT_RETRY_MAX_SECONDS'] = '30';
        putenv('ML_TRANSIENT_RETRY_BASE_SECONDS=2');
        putenv('ML_TRANSIENT_RETRY_JITTER_SECONDS=0');
        putenv('ML_TRANSIENT_RETRY_MAX_SECONDS=30');

        $service = $this->buildService([]);

        $method = new \ReflectionMethod(SecureTokenService::class, 'calculateTokenRetryDelaySeconds');
        $method->setAccessible(true);

        $this->assertSame(8, $method->invoke($service, 3));

        unset($_ENV['ML_TRANSIENT_RETRY_BASE_SECONDS'], $_ENV['ML_TRANSIENT_RETRY_JITTER_SECONDS'], $_ENV['ML_TRANSIENT_RETRY_MAX_SECONDS']);
        putenv('ML_TRANSIENT_RETRY_BASE_SECONDS');
        putenv('ML_TRANSIENT_RETRY_JITTER_SECONDS');
        putenv('ML_TRANSIENT_RETRY_MAX_SECONDS');
    }

    public function testDeveRetentarApenasFalhasTransientesNoRefresh(): void
    {
        $service = $this->buildService([]);

        $method = new \ReflectionMethod(SecureTokenService::class, 'shouldRetryTokenRequest');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($service, 503, ''));
        $this->assertTrue($method->invoke($service, 0, 'Connection timed out'));
        $this->assertFalse($method->invoke($service, 400, ''));
    }
}
