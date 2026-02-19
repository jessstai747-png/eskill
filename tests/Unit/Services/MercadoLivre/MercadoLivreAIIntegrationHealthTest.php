<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MercadoLivre;

use App\Services\MercadoLivre\MercadoLivreAIIntegrationService;
use App\Services\MercadoLivreClient;
use App\Services\AI\Core\AIProviderManager;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\TestCase;

class MercadoLivreAIIntegrationHealthTest extends TestCase
{
    public function testHealthStatusStructure(): void
    {
        // Test that getHealthStatus returns expected structure without requiring network/DB
        $service = new MercadoLivreAIIntegrationService(0); // env-token mode

        $health = $service->getHealthStatus();

        // Assert structure
        $this->assertIsArray($health);
        $this->assertArrayHasKey('ml', $health);
        $this->assertArrayHasKey('ai', $health);
        $this->assertArrayHasKey('integrated', $health);
        $this->assertArrayHasKey('recommendations', $health);
        $this->assertArrayHasKey('timestamp', $health);

        // ML health substructure
        $ml = $health['ml'];
        $this->assertIsArray($ml);
        $this->assertArrayHasKey('connected', $ml);
        $this->assertArrayHasKey('token_valid', $ml);
        $this->assertArrayHasKey('public_api', $ml);
        $this->assertArrayHasKey('auth_ok', $ml);
        $this->assertArrayHasKey('items_count', $ml);
        $this->assertArrayHasKey('seller_id', $ml);
        $this->assertArrayHasKey('token_source', $ml);
        $this->assertArrayHasKey('db_unavailable', $ml);
        $this->assertArrayHasKey('checks', $ml);
        $this->assertArrayHasKey('account_id', $ml);
        $this->assertArrayHasKey('mode', $ml);

        // AI health substructure
        $ai = $health['ai'];
        $this->assertIsArray($ai);
        $this->assertArrayHasKey('available_count', $ai);
        $this->assertArrayHasKey('total_providers', $ai);
        $this->assertArrayHasKey('preferred_provider', $ai);
        $this->assertArrayHasKey('fallback_enabled', $ai);
        $this->assertArrayHasKey('providers', $ai);

        // Recommendations
        $this->assertIsArray($health['recommendations']);

        // Integrated is bool
        $this->assertIsBool($health['integrated']);
    }

    public function testMLHealthReflectsDiagnoseOutput(): void
    {
        // Create a MercadoLivreClient mock that returns a known diagnose() output
        $mockClient = $this->createMock(MercadoLivreClient::class);
        $mockClient->method('diagnose')->willReturn([
            'connected' => true,
            'has_token' => true,
            'account_id' => 123,
            'token_source' => 'env',
            'db_unavailable' => false,
            'seller_id' => '456789',
            'user_info' => ['id' => '456789', 'nickname' => 'test_seller'],
            'token_status' => 'valid',
            'api_accessible' => true,
            'items_count' => 42,
            'error' => null,
            'checks' => [
                'token' => 'ok',
                'public_api' => 'ok',
                'auth' => 'ok',
                'items' => 'ok (42 items)',
            ],
            'token_valid' => true,
            'public_api' => true,
            'auth_ok' => true,
        ]);

        // Use reflection to create service bypassing constructor and inject mock
        $ref = new \ReflectionClass(MercadoLivreAIIntegrationService::class);
        $service = $ref->newInstanceWithoutConstructor();

        // Inject mlClient
        $mlClientProp = $ref->getProperty('mlClient');
        $mlClientProp->setAccessible(true);
        $mlClientProp->setValue($service, $mockClient);

        // Inject logger
        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setAccessible(true);
        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());
        $loggerProp->setValue($service, $logger);

        // Inject aiProviderManager
        $aiProp = $ref->getProperty('aiProviderManager');
        $aiProp->setAccessible(true);
        $aiProp->setValue($service, $this->createMock(AIProviderManager::class));

        // Inject accountId
        $accProp = $ref->getProperty('accountId');
        $accProp->setAccessible(true);
        $accProp->setValue($service, 123);

        // Inject mode
        $modeProp = $ref->getProperty('mode');
        $modeProp->setAccessible(true);
        $modeProp->setValue($service, 'env-token');

        $health = $service->getHealthStatus();

        // Assert ML health reflects the mock diagnose() output
        $ml = $health['ml'];
        $this->assertTrue($ml['connected']);
        $this->assertTrue($ml['token_valid']);
        $this->assertTrue($ml['public_api']);
        $this->assertTrue($ml['auth_ok']);
        $this->assertSame(42, $ml['items_count']);
        $this->assertSame('456789', $ml['seller_id']);
        $this->assertSame('env', $ml['token_source']);
        $this->assertFalse($ml['db_unavailable']);
        $this->assertSame(123, $ml['account_id']);
        $this->assertIsArray($ml['checks']);
        $this->assertSame('ok', $ml['checks']['token']);
        $this->assertSame('ok', $ml['checks']['auth']);
    }
}
