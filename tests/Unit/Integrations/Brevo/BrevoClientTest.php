<?php

namespace Tests\Unit\Integrations\Brevo;

use App\Services\AI\Core\RetryService;
use App\Services\Integrations\Brevo\BrevoApiException;
use App\Services\Integrations\Brevo\BrevoClient;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BrevoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['BREVO_API_KEY'] = 'test_key';
        $_ENV['BREVO_BASE_URL'] = 'https://api.brevo.com/v3';
        $_ENV['BREVO_TIMEOUT_SECONDS'] = 1;
    }

    protected function tearDown(): void
    {
        unset($_ENV['BREVO_API_KEY'], $_ENV['BREVO_BASE_URL'], $_ENV['BREVO_TIMEOUT_SECONDS']);
        parent::tearDown();
    }

    public function testHealthParsesJsonSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode(['email' => 'owner@example.com']))
        ]);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack, 'http_errors' => false, 'base_uri' => 'https://api.brevo.com/v3/']);

        $client = new BrevoClient($http, new RetryService(), new LoggingService());
        $res = $client->health();

        $this->assertTrue($res['success']);
        $this->assertSame(200, $res['status']);
        $this->assertSame('owner@example.com', $res['data']['email']);
    }

    public function testThrowsWhenApiKeyMissing(): void
    {
        unset($_ENV['BREVO_API_KEY']);
        $mock = new MockHandler([new Response(200, ['Content-Type' => 'application/json'], '{}')]);
        $stack = HandlerStack::create($mock);
        $http = new Client(['handler' => $stack, 'http_errors' => false, 'base_uri' => 'https://api.brevo.com/v3/']);

        $client = new BrevoClient($http, new RetryService(), new LoggingService());

        $this->expectException(BrevoApiException::class);
        $client->health();
    }
}
