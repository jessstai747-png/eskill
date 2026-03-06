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
}