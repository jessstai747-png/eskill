<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\WebhookInboxService;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for WebhookInboxService deduplication and protocol behaviour.
 *
 * The service requires a real DB in its constructor.  We bypass that by using
 * ReflectionClass::newInstanceWithoutConstructor() and injecting a mock PDO.
 *
 * @covers \App\Services\WebhookInboxService
 */
class WebhookInboxServiceTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a WebhookInboxService with a mock PDO injected directly, skipping
     * the constructor (which calls Database::getInstance() and ensures tables).
     */
    private function makeService(PDO $db): WebhookInboxService
    {
        $reflector = new \ReflectionClass(WebhookInboxService::class);
        /** @var WebhookInboxService $service */
        $service = $reflector->newInstanceWithoutConstructor();

        $dbProp = $reflector->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($service, $db);

        return $service;
    }

    /** @return MockObject&PDOStatement */
    private function mockStmt(): MockObject
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        return $stmt;
    }

    /** @return MockObject&PDO */
    private function mockDb(PDOStatement $stmt): MockObject
    {
        $db = $this->createMock(PDO::class);
        $db->method('prepare')->willReturn($stmt);
        return $db;
    }

    // -------------------------------------------------------------------------
    // registerIncoming — deduplication contract
    // -------------------------------------------------------------------------

    public function testRegisterIncomingReturnsTrueOnSuccessfulInsert(): void
    {
        $stmt = $this->mockStmt();
        $stmt->method('execute')->willReturn(true);

        $service = $this->makeService($this->mockDb($stmt));

        $this->assertTrue(
            $service->registerIncoming('mercadolivre', 'evt-ok', ['topic' => 'orders_v2'])
        );
    }

    public function testRegisterIncomingReturnsFalseOnDuplicateKeyConstraint(): void
    {
        $pdoe = new PDOException('Duplicate entry');
        $pdoe->errorInfo = ['23000', 1062, "Duplicate entry 'evt-dup' for key 'provider'"];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException($pdoe);

        $service = $this->makeService($this->mockDb($stmt));

        $this->assertFalse(
            $service->registerIncoming('mercadolivre', 'evt-dup', ['topic' => 'orders_v2'])
        );
    }

    public function testRegisterIncomingRethrowsNonDuplicateKeyExceptions(): void
    {
        $pdoe = new PDOException('Access denied');
        $pdoe->errorInfo = ['28000', 1045, 'Access denied for user'];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willThrowException($pdoe);

        $service = $this->makeService($this->mockDb($stmt));

        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Access denied');

        $service->registerIncoming('mercadolivre', 'evt-auth', ['topic' => 'orders_v2']);
    }

    public function testRegisterIncomingUsesParameterizedQuery(): void
    {
        $stmt = $this->mockStmt();

        $db = $this->createMock(PDO::class);
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->callback(function (string $sql): bool {
                // SQL must use placeholders, not inline user data
                $this->assertStringNotContainsString("'mercadolivre'", $sql);
                $this->assertStringNotContainsString("'evt-inject'", $sql);
                $this->assertStringContainsString(':provider', $sql);
                $this->assertStringContainsString(':event_key', $sql);
                return true;
            }))
            ->willReturn($stmt);

        $service = $this->makeService($db);
        $service->registerIncoming('mercadolivre', 'evt-inject', ['topic' => 'items']);
    }

    public function testRegisterIncomingPassesPayloadHashAndJson(): void
    {
        $capturedParams = [];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $service = $this->makeService($this->mockDb($stmt));

        $payload = ['topic' => 'orders_v2', 'resource' => '/orders/99'];
        $service->registerIncoming('mercadolivre', 'evt-hash-check', $payload);

        $expectedHash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->assertSame($expectedHash, $capturedParams[':payload_hash']);
        $this->assertSame(
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $capturedParams[':payload_json']
        );
    }

    // -------------------------------------------------------------------------
    // hasSignatureReplay — dedup guard
    // -------------------------------------------------------------------------

    public function testHasSignatureReplayReturnsFalseWhenBothDeliveryIdAndNonceAreNull(): void
    {
        // No DB call should occur — method short-circuits
        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');

        $service = $this->makeService($db);

        $this->assertFalse(
            $service->hasSignatureReplay('mercadolivre', null, null)
        );
    }

    public function testHasSignatureReplayReturnsFalseWhenBothAreEmptyStrings(): void
    {
        $db = $this->createMock(PDO::class);
        $db->expects($this->never())->method('prepare');

        $service = $this->makeService($db);

        $this->assertFalse(
            $service->hasSignatureReplay('mercadolivre', '', '')
        );
    }

    public function testHasSignatureReplayReturnsTrueWhenRowFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('1');

        $service = $this->makeService($this->mockDb($stmt));

        $this->assertTrue(
            $service->hasSignatureReplay('mercadolivre', 'delivery-999')
        );
    }

    public function testHasSignatureReplayReturnsFalseWhenNoRowFound(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $service = $this->makeService($this->mockDb($stmt));

        $this->assertFalse(
            $service->hasSignatureReplay('mercadolivre', 'delivery-000')
        );
    }

    // -------------------------------------------------------------------------
    // markFailed — truncates long error messages
    // -------------------------------------------------------------------------

    public function testMarkFailedTruncatesErrorMessageTo1000Chars(): void
    {
        $capturedParams = [];
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->willReturnCallback(function (array $params) use (&$capturedParams): bool {
                $capturedParams = $params;
                return true;
            });

        $service = $this->makeService($this->mockDb($stmt));

        $longError = str_repeat('x', 2000);
        $service->markFailed('mercadolivre', 'evt-long', $longError);

        $this->assertSame(1000, mb_strlen($capturedParams[':error_message']));
    }
}
