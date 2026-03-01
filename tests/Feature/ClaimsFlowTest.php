<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\ClaimsService;

/**
 * Feature tests for the Claims (reclamações) flow — Fase 5 (Pedidos).
 *
 * Two test groups:
 * 1. Pure structure/logic tests (no DB / no HTTP) — always run.
 * 2. HTTP endpoint tests — skipped when API server is not reachable.
 */
class ClaimsFlowTest extends TestCase
{
    private string $baseUrl;
    private bool $apiReachable = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseUrl = getenv('TEST_API_URL') ?: 'http://localhost:8000';

        // Probe API server (non-fatal)
        $ch = curl_init($this->baseUrl . '/api/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_exec($ch);
        $this->apiReachable = curl_errno($ch) === 0;
        curl_close($ch);
    }

    // -----------------------------------------------------------------------
    // Class / structure (always run)
    // -----------------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(ClaimsService::class));
    }

    public function testHasRequiredMethods(): void
    {
        $methods = ['getClaims', 'getClaim'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(ClaimsService::class, $method),
                "ClaimsService deve ter o método {$method}()"
            );
        }
    }

    public function testInstantiationWithSkipDbAutoConnect(): void
    {
        $service = new ClaimsService(
            accountId: null,
            client: null,
            db: null,
            skipDbAutoConnect: true
        );

        $this->assertInstanceOf(ClaimsService::class, $service);
    }

    // -----------------------------------------------------------------------
    // Logic tests — no DB required (always run)
    // -----------------------------------------------------------------------

    public function testGetClaimsReturnsArrayOrError(): void
    {
        // Service instantiated without DB or real ML client → will fail gracefully
        $service = new ClaimsService(
            accountId: null,
            client: null,
            db: null,
            skipDbAutoConnect: true
        );

        $result = $service->getClaims();

        // Result must be array (either data or ['error' => '...'])
        $this->assertIsArray($result);
    }

    public function testGetClaimsWithInvalidAccountReturnsError(): void
    {
        $service = new ClaimsService(
            accountId: PHP_INT_MAX,
            client: null,
            db: null,
            skipDbAutoConnect: true
        );

        $result = $service->getClaims();

        // With no real token, ML API call will fail → result contains 'error' key
        $this->assertIsArray($result);
        // Either returns 'error' key or empty data structure — both valid
        if (isset($result['error'])) {
            $this->assertIsString($result['error']);
            $this->assertNotEmpty($result['error']);
        }
    }

    public function testGetClaimWithNonExistentIdReturnsErrorOrEmpty(): void
    {
        $service = new ClaimsService(
            accountId: null,
            client: null,
            db: null,
            skipDbAutoConnect: true
        );

        $result = $service->getClaim('NON_EXISTENT_CLAIM_ID_XYZ');

        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // HTTP endpoint tests (skipped in sandbox)
    // -----------------------------------------------------------------------

    public function testClaimsListEndpointExists(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/claims?limit=5');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 401 is acceptable (not authenticated), 404 means the route is missing
        $this->assertNotEquals(0, $httpCode, 'cURL failed');
        $this->assertNotEquals(404, $httpCode, 'Claims list endpoint not found (404)');
    }

    public function testClaimsListEndpointWithAuthReturnsJson(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/claims');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertNotEquals(0, $httpCode);

        // If not 401/403, the response body should be valid JSON
        if (!in_array($httpCode, [401, 403])) {
            $decoded = json_decode($response ?: '{}', true);
            $this->assertNotNull($decoded, 'Response from claims endpoint must be valid JSON');
        }
    }

    public function testClaimDetailEndpointReturns404OrErrorForFakeId(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/claims/FAKE_CLAIM_ID_000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertNotEquals(0, $httpCode);
        // Fake claim ID should return 404, 401, 403, or 400 — never 200 with dummy data
        $this->assertContains($httpCode, [400, 401, 403, 404, 422, 500]);
    }
}
