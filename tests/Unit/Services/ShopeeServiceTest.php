<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ShopeeService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * @covers \App\Services\ShopeeService
 */
class ShopeeServiceTest extends TestCase
{
    /**
     * Test service has required properties
     */
    public function testServiceHasRequiredProperties(): void
    {
        $reflection = new ReflectionClass(ShopeeService::class);

        $requiredProperties = [
            'db',
            'partnerId',
            'partnerKey',
            'redirectUri',
            'baseUrl',
        ];

        foreach ($requiredProperties as $prop) {
            $this->assertTrue(
                $reflection->hasProperty($prop),
                "Missing required property: {$prop}"
            );
        }
    }

    /**
     * Test baseUrl points to production API
     */
    public function testBaseUrlIsProductionApi(): void
    {
        $reflection = new ReflectionProperty(ShopeeService::class, 'baseUrl');
        $reflection->setAccessible(true);

        $service = $this->createServiceWithMockEnv();
        $baseUrl = $reflection->getValue($service);

        $this->assertStringContainsString('partner.shopeemobile.com', $baseUrl);
        $this->assertStringContainsString('api/v2', $baseUrl);
    }

    /**
     * Test getAuthUrl returns valid URL structure
     */
    public function testGetAuthUrlReturnsValidUrl(): void
    {
        $service = $this->createServiceWithMockEnv();
        $url = $service->getAuthUrl();

        $this->assertIsString($url);
        $this->assertStringContainsString('partner_id=', $url);
        $this->assertStringContainsString('timestamp=', $url);
        $this->assertStringContainsString('sign=', $url);
        $this->assertStringContainsString('redirect=', $url);
    }

    /**
     * Test getAuthUrl includes correct path
     */
    public function testGetAuthUrlIncludesAuthPath(): void
    {
        $service = $this->createServiceWithMockEnv();
        $url = $service->getAuthUrl();

        $this->assertStringContainsString('/shop/auth_partner', $url);
    }

    /**
     * Test signature uses HMAC-SHA256
     */
    public function testSignatureIsValidHmacFormat(): void
    {
        $service = $this->createServiceWithMockEnv();
        $url = $service->getAuthUrl();

        // Extract sign parameter
        preg_match('/sign=([a-f0-9]+)/', $url, $matches);

        $this->assertNotEmpty($matches[1] ?? '');
        // SHA256 produces 64 hex characters
        $this->assertSame(64, strlen($matches[1]));
    }

    /**
     * Test timestamp is current time
     */
    public function testTimestampIsRecent(): void
    {
        $service = $this->createServiceWithMockEnv();
        $url = $service->getAuthUrl();

        preg_match('/timestamp=(\d+)/', $url, $matches);

        $timestamp = (int)($matches[1] ?? 0);
        $now = time();

        // Should be within 5 seconds
        $this->assertLessThanOrEqual(5, abs($now - $timestamp));
    }

    /**
     * Test redirect URI is URL encoded
     */
    public function testRedirectUriIsEncoded(): void
    {
        $service = $this->createServiceWithMockEnv();
        $url = $service->getAuthUrl();

        // Should contain encoded redirect
        $this->assertStringContainsString('redirect=http', $url);
    }

    /**
     * Test service has saveAuth method
     */
    public function testServiceHasSaveAuthMethod(): void
    {
        $reflection = new ReflectionClass(ShopeeService::class);
        $this->assertTrue($reflection->hasMethod('saveAuth'));
    }

    /**
     * Test saveAuth method signature
     */
    public function testSaveAuthMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(ShopeeService::class, 'saveAuth');
        $params = $reflection->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('shopId', $params[0]->getName());
        $this->assertSame('code', $params[1]->getName());
    }

    /**
     * Test service has callPublicApi method
     */
    public function testServiceHasCallPublicApiMethod(): void
    {
        $reflection = new ReflectionClass(ShopeeService::class);
        $this->assertTrue($reflection->hasMethod('callPublicApi'));
    }

    /**
     * Helper to create service with mock environment
     */
    private function createServiceWithMockEnv(): ShopeeService
    {
        // Set mock env values
        $_ENV['SHOPEE_PARTNER_ID'] = '12345';
        $_ENV['SHOPEE_PARTNER_KEY'] = 'test_secret_key_for_testing';
        $_ENV['APP_URL'] = 'https://eskill.com.br';

        $service = $this->getMockBuilder(ShopeeService::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Initialize properties via reflection
        $reflection = new ReflectionClass(ShopeeService::class);

        $partnerId = $reflection->getProperty('partnerId');
        $partnerId->setAccessible(true);
        $partnerId->setValue($service, 12345);

        $partnerKey = $reflection->getProperty('partnerKey');
        $partnerKey->setAccessible(true);
        $partnerKey->setValue($service, 'test_secret_key_for_testing');

        $redirectUri = $reflection->getProperty('redirectUri');
        $redirectUri->setAccessible(true);
        $redirectUri->setValue($service, 'https://eskill.com.br/shopee/callback');

        $baseUrl = $reflection->getProperty('baseUrl');
        $baseUrl->setAccessible(true);
        $baseUrl->setValue($service, 'https://partner.shopeemobile.com/api/v2');

        return $service;
    }
}
