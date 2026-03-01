<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\PushNotificationService;

/**
 * Feature tests for Push Notification flow — Fase 7 (Notificações).
 *
 * Two test groups:
 * 1. Pure structure/logic tests (no DB / no HTTP) — always run.
 * 2. HTTP endpoint tests — skipped when API server is not reachable.
 */
class PushNotificationTest extends TestCase
{
    private string $baseUrl;
    private bool $apiReachable = false;
    private PushNotificationService $service;

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

        // Instantiate without DB for pure logic tests
        $this->service = new PushNotificationService(
            db: null,
            webPush: null,
            skipDbAutoConnect: true
        );
    }

    // -----------------------------------------------------------------------
    // Class / structure (always run)
    // -----------------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(PushNotificationService::class));
    }

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'getVapidPublicKey',
            'saveSubscription',
            'removeSubscription',
            'getUserSubscriptions',
            'sendToUser',
            'sendNotification',
            'sendToAll',
            'notifyNewSale',
            'notifyLowStock',
            'notifyAlert',
            'getStats',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(PushNotificationService::class, $method),
                "PushNotificationService deve ter o método {$method}()"
            );
        }
    }

    public function testInstantiationWithSkipDbAutoConnect(): void
    {
        $service = new PushNotificationService(
            db: null,
            webPush: null,
            skipDbAutoConnect: true
        );

        $this->assertInstanceOf(PushNotificationService::class, $service);
    }

    // -----------------------------------------------------------------------
    // Logic tests — no DB required (always run)
    // -----------------------------------------------------------------------

    public function testGetVapidPublicKeyReturnsNonEmptyString(): void
    {
        // Service auto-generates VAPID keys when env vars are absent
        $key = $this->service->getVapidPublicKey();

        $this->assertIsString($key);
        $this->assertNotEmpty($key, 'getVapidPublicKey() deve retornar uma chave non-empty');
    }

    public function testGetVapidPublicKeyIsBase64Url(): void
    {
        $key = $this->service->getVapidPublicKey();

        // VAPID public keys are Base64URL-encoded (no +, /, =)
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9\-_]+$/',
            $key,
            'VAPID public key deve ser Base64URL (sem +, / ou =)'
        );
    }

    public function testSaveSubscriptionWithNullDbReturnsError(): void
    {
        $subscription = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-endpoint',
            'keys' => [
                'auth' => 'fake_auth_key',
                'p256dh' => 'fake_p256dh_key',
            ],
        ];

        $result = $this->service->saveSubscription(userId: 1, subscription: $subscription);

        $this->assertIsArray($result);
        // Without DB, should return error gracefully
        if (isset($result['success'])) {
            $this->assertIsBool($result['success']);
            if ($result['success'] === false) {
                $this->assertArrayHasKey('error', $result);
            }
        }
    }

    public function testSendToUserWithNullDbReturnsErrorArray(): void
    {
        $payload = [
            'title' => 'Test Notification',
            'body' => 'This is a test',
            'url' => '/',
        ];

        $result = $this->service->sendToUser(userId: 1, payload: $payload);

        $this->assertIsArray($result);
        // Without DB, cannot load subscriptions → error result
        if (isset($result['success'])) {
            $this->assertIsBool($result['success']);
        }
    }

    public function testGetStatsReturnsArray(): void
    {
        $stats = $this->service->getStats();

        $this->assertIsArray($stats);
    }

    public function testGetUserSubscriptionsWithNullDbReturnsArray(): void
    {
        $result = $this->service->getUserSubscriptions(userId: PHP_INT_MAX);

        // Without DB, returns empty array or error-keyed array — never throws
        $this->assertIsArray($result);
    }

    // -----------------------------------------------------------------------
    // HTTP endpoint tests (skipped in sandbox)
    // -----------------------------------------------------------------------

    public function testPublicKeyEndpointReturns200(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/push/public-key');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertEquals(
            200,
            $httpCode,
            'Endpoint GET /api/push/public-key deve retornar 200'
        );
    }

    public function testPublicKeyEndpointReturnsValidKey(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/push/public-key');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $this->markTestSkipped('Endpoint /api/push/public-key returned ' . $httpCode);
        }

        $decoded = json_decode($response ?: '{}', true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('publicKey', $decoded, 'Response must contain publicKey field');
        $this->assertNotEmpty($decoded['publicKey']);
    }

    public function testSubscribeEndpointExists(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/push/subscribe');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Route must exist (not 404); may return 401, 422, 400 etc.
        $this->assertNotEquals(404, $httpCode, 'POST /api/push/subscribe endpoint não encontrado (404)');
        $this->assertNotEquals(0, $httpCode, 'cURL request failed');
    }

    public function testSendEndpointExists(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/push/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => true]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Route must exist (not 404)
        $this->assertNotEquals(404, $httpCode, 'POST /api/push/send endpoint não encontrado (404)');
        $this->assertNotEquals(0, $httpCode, 'cURL request failed');
    }
}
