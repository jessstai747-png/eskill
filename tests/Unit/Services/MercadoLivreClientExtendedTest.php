<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MercadoLivreClient;

/**
 * Testes adicionais para MercadoLivreClient
 *
 * Complementa o MercadoLivreClientTest existente (auth/tokens).
 * Foca em: métodos HTTP, cache, circuit breaker, getters, diagnóstico
 */
class MercadoLivreClientExtendedTest extends TestCase
{
    // ===========================
    // CLASS STRUCTURE
    // ===========================

    public function test_client_has_all_public_methods(): void
    {
        $requiredMethods = [
            'get', 'post', 'put', 'delete',
            'searchItems', 'getSellerId', 'getAccountId',
            'getMyItems', 'getTrends', 'getAutocompleteSuggestions',
            'getCategory', 'getCategoryAttributes',
            'getItemDetails', 'updateItem',
            'searchByKeyword', 'getCompetitorAnalysis',
            'getCircuitBreakerStats', 'diagnose',
            'loadAccount', 'ensureValidAccessToken', 'getAccessToken',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                method_exists(MercadoLivreClient::class, $method),
                "MercadoLivreClient deve ter método {$method}()"
            );
        }
    }

    // ===========================
    // CIRCUIT BREAKER
    // ===========================

    public function test_circuit_breaker_can_be_disabled(): void
    {
        MercadoLivreClient::disableCircuitBreaker();

        $client = new MercadoLivreClient(null);
        $stats = $client->getCircuitBreakerStats();

        // Quando desabilitado, retorna null
        $this->assertNull($stats);
    }

    public function test_circuit_breaker_can_be_toggled(): void
    {
        $this->assertTrue(
            method_exists(MercadoLivreClient::class, 'disableCircuitBreaker')
        );
        $this->assertTrue(
            method_exists(MercadoLivreClient::class, 'enableCircuitBreaker')
        );
    }

    // ===========================
    // CONSTRUCTOR
    // ===========================

    public function test_constructor_accepts_null(): void
    {
        $client = new MercadoLivreClient(null);
        $this->assertInstanceOf(MercadoLivreClient::class, $client);
        $this->assertNull($client->getAccountId());
    }

    public function test_constructor_stores_account_id(): void
    {
        $client = new MercadoLivreClient(42);
        $this->assertEquals(42, $client->getAccountId());
    }

    // ===========================
    // TOKEN HANDLING
    // ===========================

    public function test_getAccessToken_returns_string(): void
    {
        $client = new MercadoLivreClient(null);
        $token = $client->getAccessToken();

        $this->assertIsString($token);
    }

    public function test_missing_token_returns_error_structure(): void
    {
        // Usar reflection para invocar missingTokenError
        $client = new MercadoLivreClient(null);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('missingTokenError');
        $method->setAccessible(true);

        $result = $method->invoke($client, '/test/endpoint');

        $this->assertArrayHasKey('error', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('endpoint', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals(401, $result['status']);
        $this->assertEquals('missing_access_token', $result['error']);
    }

    // ===========================
    // NETWORK BLOCKING (ENV=testing)
    // ===========================

    public function test_get_without_token_returns_error(): void
    {
        MercadoLivreClient::disableCircuitBreaker();
        $client = new MercadoLivreClient(null);

        $result = $client->get('/items/MLB999999999');

        $this->assertIsArray($result);
        // Sem token, deve retornar erro
        $this->assertTrue(
            isset($result['error']) || isset($result['status']),
            'GET sem token deve retornar erro'
        );
    }

    public function test_post_without_token_returns_error(): void
    {
        MercadoLivreClient::disableCircuitBreaker();
        $client = new MercadoLivreClient(null);

        $result = $client->post('/items', ['title' => 'test']);

        $this->assertIsArray($result);
        $this->assertTrue(
            isset($result['error']) || isset($result['status']),
            'POST sem token deve retornar erro'
        );
    }

    public function test_put_without_token_returns_error(): void
    {
        MercadoLivreClient::disableCircuitBreaker();
        $client = new MercadoLivreClient(null);

        $result = $client->put('/items/MLB999', ['price' => 10]);

        $this->assertIsArray($result);
        $this->assertTrue(
            isset($result['error']) || isset($result['status']),
            'PUT sem token deve retornar erro'
        );
    }

    public function test_delete_without_token_returns_error(): void
    {
        MercadoLivreClient::disableCircuitBreaker();
        $client = new MercadoLivreClient(null);

        $result = $client->delete('/items/MLB999');

        $this->assertIsArray($result);
        $this->assertTrue(
            isset($result['error']) || isset($result['status']),
            'DELETE sem token deve retornar erro'
        );
    }

    // ===========================
    // SEARCH & CONVENIENCE METHODS
    // ===========================

    public function test_searchItems_calls_get(): void
    {
        MercadoLivreClient::disableCircuitBreaker();
        $client = new MercadoLivreClient(null);

        // searchItems é público e deve retornar array
        $result = $client->searchItems(['q' => 'teste'], 0);
        $this->assertIsArray($result);
    }

    public function test_getSellerId_returns_null_or_string(): void
    {
        $client = new MercadoLivreClient(null);
        $sellerId = $client->getSellerId();

        $this->assertTrue(
            is_null($sellerId) || is_string($sellerId),
            'getSellerId deve retornar null ou string'
        );
    }

    // ===========================
    // DIAGNOSE
    // ===========================

    public function test_diagnose_returns_array(): void
    {
        $client = new MercadoLivreClient(null);
        $result = $client->diagnose();

        $this->assertIsArray($result);
    }

    // ===========================
    // SOURCE CODE ANALYSIS
    // ===========================

    public function test_uses_guzzle_or_curl(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/MercadoLivreClient.php'
        );

        $this->assertTrue(
            str_contains($source, 'GuzzleHttp') || str_contains($source, 'curl_'),
            'Deve usar Guzzle ou cURL para requisições HTTP'
        );
    }

    public function test_has_retry_logic(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/MercadoLivreClient.php'
        );

        $this->assertStringContainsString('retry', strtolower($source));
    }

    public function test_has_transient_retry_for_5xx_and_connection_errors(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/MercadoLivreClient.php'
        );

        $this->assertStringContainsString('shouldRetryTransientHttpFailure', $source);
        $this->assertStringContainsString('calculateTransientRetryDelaySeconds', $source);
        $this->assertStringContainsString('ML_TRANSIENT_RETRY_BASE_SECONDS', $source);
        $this->assertStringContainsString('ML_TRANSIENT_RETRY_JITTER_SECONDS', $source);
        $this->assertStringContainsString('connection error, retrying once', strtolower($source));
    }

    public function test_has_cache_support(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/MercadoLivreClient.php'
        );

        $this->assertStringContainsString('CacheService', $source);
        $this->assertStringContainsString('cacheKey', $source);
    }

    public function test_searchItems_uses_site_id(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Services/MercadoLivreClient.php'
        );

        // searchItems deve usar /sites/{siteId}/search
        $this->assertStringContainsString('/sites/', $source);
        $this->assertStringContainsString('MLB', $source);
    }
}
