<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AssistantConnectorService;
use Tests\TestCase;

/**
 * @covers \App\Services\AssistantConnectorService
 */
class AssistantConnectorServiceTest extends TestCase
{
    public function testNormalizeMysqlDateTimeDeveRetornarNullQuandoVazio(): void
    {
        $this->assertNull(AssistantConnectorService::normalizeMysqlDateTime(null));
        $this->assertNull(AssistantConnectorService::normalizeMysqlDateTime(''));
        $this->assertNull(AssistantConnectorService::normalizeMysqlDateTime('   '));
    }

    public function testNormalizeMysqlDateTimeDeveAceitarIso8601(): void
    {
        $this->assertSame(
            '2026-02-23 15:53:10',
            AssistantConnectorService::normalizeMysqlDateTime('2026-02-23T15:53:10Z')
        );
    }

    public function testNormalizeMysqlDateTimeDeveAceitarEpochSeconds(): void
    {
        $this->assertSame('1970-01-01 00:00:00', AssistantConnectorService::normalizeMysqlDateTime('0'));
    }

    public function testNormalizeMysqlDateTimeInvalidoDeveRetornarNull(): void
    {
        $this->assertNull(AssistantConnectorService::normalizeMysqlDateTime('nao-e-data'));
    }

    public function testNormalizeActionDeveRetornarNullQuandoVazio(): void
    {
        $this->assertNull(AssistantConnectorService::normalizeAction(''));
        $this->assertNull(AssistantConnectorService::normalizeAction('   '));
    }

    public function testNormalizeActionDeveNormalizarParaLowercaseEValidarAllowlist(): void
    {
        $this->assertSame('answer_question', AssistantConnectorService::normalizeAction('ANSWER_QUESTION'));
        $this->assertSame('update_stock', AssistantConnectorService::normalizeAction(' update_stock '));
        $this->assertNull(AssistantConnectorService::normalizeAction('drop_database'));
    }

    public function testDeriveIdempotencyKeyDevePreferirProvidedKeyQuandoInformada(): void
    {
        $key = AssistantConnectorService::deriveIdempotencyKey(
            action: 'answer_question',
            accountId: 123,
            parameters: ['question_id' => '1', 'text' => 'oi'],
            providedKey: 'my-key'
        );

        $this->assertSame('my-key', $key);
    }

    public function testDeriveIdempotencyKeyDeveSerDeterministicaQuandoNaoHaProvidedKey(): void
    {
        $a = AssistantConnectorService::deriveIdempotencyKey(
            action: 'update_stock',
            accountId: 10,
            parameters: ['item_id' => 'MLB123', 'quantity' => 5],
            providedKey: null
        );

        $b = AssistantConnectorService::deriveIdempotencyKey(
            action: 'update_stock',
            accountId: 10,
            parameters: ['item_id' => 'MLB123', 'quantity' => 5],
            providedKey: null
        );

        $this->assertSame($a, $b);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a);
    }
}
