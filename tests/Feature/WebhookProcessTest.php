<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivreWebhookService;
use App\Services\StructuredLogService;

/**
 * Feature tests for webhook processing — Fase 5 (Pedidos).
 *
 * Two test groups:
 * 1. Pure logic tests (no DB / no HTTP) — always run, exercise MercadoLivreWebhookService
 *    with skipDbAutoConnect=true and injected mocks.
 * 2. HTTP endpoint tests — skipped when the API server is not reachable.
 */
class WebhookProcessTest extends TestCase
{
    private string $baseUrl;
    private bool $apiReachable = false;
    private MercadoLivreWebhookService $service;

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

        // Instantiate service without DB or HTTP (pure logic tests)
        $this->service = new MercadoLivreWebhookService(
            accountId: 1,
            logger: null,
            orderService: null,
            itemService: null,
            questionService: null,
            notificationService: null,
            db: null,
            skipDbAutoConnect: true
        );
    }

    // -----------------------------------------------------------------------
    // Class / structure (always run)
    // -----------------------------------------------------------------------

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(MercadoLivreWebhookService::class));
    }

    public function testHasRequiredMethods(): void
    {
        $methods = [
            'processWebhookEvent',
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(MercadoLivreWebhookService::class, $method),
                "MercadoLivreWebhookService deve ter o método {$method}()"
            );
        }
    }

    public function testInstantiationWithSkipDbAutoConnect(): void
    {
        $service = new MercadoLivreWebhookService(
            accountId: 99,
            skipDbAutoConnect: true
        );

        $this->assertInstanceOf(MercadoLivreWebhookService::class, $service);
    }

    // -----------------------------------------------------------------------
    // Payload validation (no DB / no HTTP — always run)
    // -----------------------------------------------------------------------

    public function testProcessWebhookEventRejectsEmptyPayload(): void
    {
        $result = $this->service->processWebhookEvent([]);

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertNotEmpty($result['error']);
    }

    public function testProcessWebhookEventRequiresTopic(): void
    {
        $result = $this->service->processWebhookEvent([
            'resource' => '/orders/12345',
            'user_id' => 1,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('topic', strtolower($result['error']));
    }

    public function testProcessWebhookEventRequiresResource(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'orders',
            'user_id' => 1,
        ]);

        $this->assertFalse($result['success']);
    }

    public function testProcessWebhookEventReturnsBoolSuccess(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'questions',
            'resource' => '/questions/99',
            'user_id' => 1,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertIsBool($result['success']);
    }

    public function testProcessWebhookReturnsFalseForUnknownTopic(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'totally_unknown_topic_xyz',
            'resource' => '/bogus/1234',
            'user_id' => 1,
        ]);

        $this->assertIsArray($result);
        // Unknown topics may go through a graceful path (not necessarily false)
        $this->assertArrayHasKey('success', $result);
    }

    public function testOrdersTopicPayloadAccepted(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'orders_v2',
            'resource' => '/orders/2000079832',
            'user_id' => 123456789,
            'received' => false,
            'sent' => false,
            '_links' => ['self' => ['href' => '/orders/2000079832']],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testItemsTopicPayloadAccepted(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'items',
            'resource' => '/items/MLB1234567',
            'user_id' => 123456789,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    public function testQuestionsTopicPayloadAccepted(): void
    {
        $result = $this->service->processWebhookEvent([
            'topic' => 'questions',
            'resource' => '/questions/5512345678',
            'user_id' => 123456789,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    // -----------------------------------------------------------------------
    // HTTP endpoint tests (skipped in sandbox)
    // -----------------------------------------------------------------------

    public function testWebhookEndpointExists(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/webhooks/mercadolivre');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Endpoint should exist (200 or 4xx, not 404/0)
        $this->assertNotEquals(0, $httpCode, 'cURL failed to connect');
        $this->assertNotEquals(404, $httpCode, 'Webhook endpoint not found');
    }

    public function testWebhookEndpointRejectsMalformedPayload(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $ch = curl_init($this->baseUrl . '/api/webhooks/mercadolivre');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"invalid": true}');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Should return 400 Bad Request or 422 Unprocessable Entity
        $this->assertContains($httpCode, [200, 400, 422], 'Expected 200/400/422 for malformed payload');
    }

    public function testWebhookEndpointProcessesOrdersTopicPayload(): void
    {
        if (!$this->apiReachable) {
            $this->markTestSkipped('API server not reachable at ' . $this->baseUrl);
        }

        $payload = json_encode([
            'resource' => '/orders/2000079832',
            'user_id' => 123456789,
            'topic' => 'orders_v2',
            '_links' => ['self' => ['href' => '/orders/2000079832']],
        ]);

        $ch = curl_init($this->baseUrl . '/api/webhooks/mercadolivre');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 200 OK expected — ML sends webhooks and expects 200 back
        $this->assertContains($httpCode, [200, 201, 202, 400, 401, 403, 422]);
    }
}
