<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ClawdbotWebhookService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\ClawdbotWebhookService
 */
final class ClawdbotWebhookServiceTest extends TestCase
{
    public function testValidateSignatureMatchesRawPayloadWhenNoTimestamp(): void
    {
        $secret = 'unit-test-secret-123';
        $raw = '{"hello":"world"}';
        $sig = hash_hmac('sha256', $raw, $secret);

        $svc = new ClawdbotWebhookService($secret);
        $ok = $svc->validateSignature($raw, 'sha256=' . $sig, null);

        $this->assertTrue($ok);
    }

    public function testValidateRequestUsesTimestampWhenProvided(): void
    {
        $secret = 'unit-test-secret-123';
        $raw = '{"hello":"world"}';
        $ts = (string)time();
        $sig = hash_hmac('sha256', $ts . '.' . $raw, $secret);

        $svc = new ClawdbotWebhookService($secret, 300);
        $ok = $svc->validateRequest($raw, [
            ClawdbotWebhookService::HEADER_TIMESTAMP => $ts,
            ClawdbotWebhookService::HEADER_SIGNATURE => $sig,
        ]);

        $this->assertTrue($ok);
    }

    public function testValidateRequestRejectsTimestampOutsideTolerance(): void
    {
        $secret = 'unit-test-secret-123';
        $raw = '{"hello":"world"}';
        $ts = (string)(time() - 1000);
        $sig = hash_hmac('sha256', $ts . '.' . $raw, $secret);

        $svc = new ClawdbotWebhookService($secret, 10);
        $ok = $svc->validateRequest($raw, [
            ClawdbotWebhookService::HEADER_TIMESTAMP => $ts,
            ClawdbotWebhookService::HEADER_SIGNATURE => $sig,
        ]);

        $this->assertFalse($ok);
    }

    public function testNormalizeCommandRunAgentReturnsDispatchJob(): void
    {
        $svc = new ClawdbotWebhookService('x');
        $cmd = $svc->normalizeCommand([
            'action' => 'run_agent',
            'agent' => 'guardian',
            'account_id' => 123,
            'event_id' => 'evt_1',
        ]);

        $this->assertSame('run_agent', $cmd['job_type']);
    }

    public function testNormalizeCommandDispatchJobRequiresPayloadObject(): void
    {
        $svc = new ClawdbotWebhookService('x');

        $this->expectException(\InvalidArgumentException::class);
        $svc->normalizeCommand([
            'action' => 'dispatch_job',
            'job_type' => 'run_agent',
            'job_payload' => 'not-an-object',
        ]);
    }
}
