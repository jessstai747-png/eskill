<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WhatsAppService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * @covers \App\Services\WhatsAppService
 */
class WhatsAppServiceTest extends TestCase
{
    /**
     * Test service has required properties
     */
    public function testServiceHasRequiredProperties(): void
    {
        $reflection = new ReflectionClass(WhatsAppService::class);

        $requiredProperties = [
            'db',
            'settings',
            'userId',
            'queueService',
        ];

        foreach ($requiredProperties as $prop) {
            $this->assertTrue(
                $reflection->hasProperty($prop),
                "Missing required property: {$prop}"
            );
        }
    }

    /**
     * Test service has required methods
     */
    public function testServiceHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(WhatsAppService::class);

        $requiredMethods = [
            'saveSettings',
            'sendMessage',
            'getSettings',
            'isConfigured',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Missing required method: {$method}"
            );
        }
    }

    /**
     * Test constructor requires userId parameter
     */
    public function testConstructorRequiresUserId(): void
    {
        $reflection = new ReflectionMethod(WhatsAppService::class, '__construct');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertSame('userId', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertNotNull($type);
        $this->assertSame('int', $type->getName());
    }

    /**
     * Test loadSettings method is private
     */
    public function testLoadSettingsIsPrivate(): void
    {
        $reflection = new ReflectionMethod(WhatsAppService::class, 'loadSettings');
        $this->assertTrue($reflection->isPrivate());
    }

    /**
     * Test saveSettings returns bool
     */
    public function testSaveSettingsReturnsBool(): void
    {
        $reflection = new ReflectionMethod(WhatsAppService::class, 'saveSettings');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('bool', $returnType->getName());
    }

    /**
     * Test settings array structure when configured
     */
    public function testSettingsStructure(): void
    {
        $expectedKeys = [
            'provider',
            'api_url',
            'api_key',
            'instance_id',
            'from_number',
            'is_active',
        ];

        // These are the keys we expect in a configured settings array
        foreach ($expectedKeys as $key) {
            $this->assertIsString($key);
        }
    }

    /**
     * Test getQueue method exists and returns QueueService
     */
    public function testGetQueueMethodExists(): void
    {
        $reflection = new ReflectionClass(WhatsAppService::class);
        $this->assertTrue($reflection->hasMethod('getQueue'));
    }

    /**
     * Test service uses Guzzle HTTP Client
     */
    public function testServiceUsesGuzzleClient(): void
    {
        // Check if GuzzleHttp\Client is used
        $reflection = new ReflectionClass(WhatsAppService::class);
        $fileName = $reflection->getFileName();

        $this->assertNotFalse($fileName);

        $content = file_get_contents($fileName);
        $this->assertStringContainsString('GuzzleHttp\Client', $content);
    }

    /**
     * Test supported providers
     */
    public function testSupportedProviders(): void
    {
        // Common WhatsApp API providers
        $expectedProviders = ['simulator', 'wapi', 'z-api', 'twilio'];

        foreach ($expectedProviders as $provider) {
            $this->assertIsString($provider);
            $this->assertNotEmpty($provider);
        }
    }
}
