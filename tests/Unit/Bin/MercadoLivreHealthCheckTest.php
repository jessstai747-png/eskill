<?php

declare(strict_types=1);

namespace Tests\Unit\Bin;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MercadoLivreHealthCheck (bin/ml-health-check.php).
 *
 * The bin script exposes a global class `MercadoLivreHealthCheck` and a global
 * function `parseMlHealthArgs`. The main execution block is guarded by a
 * realpath check so this require_once is safe.
 *
 * @covers \MercadoLivreHealthCheck
 */
final class MercadoLivreHealthCheckTest extends TestCase
{
    /** @var array<string, mixed> Original $_ENV snapshot for restore */
    private array $originalEnv = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Safe to require: main execution block is guarded by realpath check.
        if (!class_exists('MercadoLivreHealthCheck', false)) {
            require_once __DIR__ . '/../../../bin/ml-health-check.php';
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $_ENV = $this->originalEnv;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // parseMlHealthArgs
    // ─────────────────────────────────────────────────────────────────────────

    public function testParseMlHealthArgsDefaultValues(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php']);

        $this->assertFalse($opts['json']);
        $this->assertFalse($opts['all_accounts']);
        $this->assertFalse($opts['skip_internal_api']);
        $this->assertNull($opts['account_id']);
        $this->assertNull($opts['api_token']);
        $this->assertNull($opts['app_url']);
    }

    public function testParseMlHealthArgsJsonFlag(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--json']);
        $this->assertTrue($opts['json']);
    }

    public function testParseMlHealthArgsAllAccountsFlag(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--all-accounts']);
        $this->assertTrue($opts['all_accounts']);
    }

    public function testParseMlHealthArgsSkipInternalApi(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--skip-internal-api']);
        $this->assertTrue($opts['skip_internal_api']);
    }

    public function testParseMlHealthArgsAccountId(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--account-id=42']);
        $this->assertSame(42, $opts['account_id']);
    }

    public function testParseMlHealthArgsAccountIdZeroIgnored(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--account-id=0']);
        $this->assertNull($opts['account_id']);
    }

    public function testParseMlHealthArgsApiToken(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--api-token=mysecrettoken']);
        $this->assertSame('mysecrettoken', $opts['api_token']);
    }

    public function testParseMlHealthArgsAppUrl(): void
    {
        $opts = parseMlHealthArgs(['bin/ml-health-check.php', '--app-url=https://staging.example.com']);
        $this->assertSame('https://staging.example.com', $opts['app_url']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // checkWebhookInfrastructure — via ReflectionMethod
    // ─────────────────────────────────────────────────────────────────────────

    /** @return array{sut: \MercadoLivreHealthCheck, ref: \ReflectionClass<\MercadoLivreHealthCheck>} */
    private function makeHealthCheck(): array
    {
        /** @var \ReflectionClass<\MercadoLivreHealthCheck> $ref */
        $ref = new \ReflectionClass(\MercadoLivreHealthCheck::class);
        /** @var \MercadoLivreHealthCheck $sut */
        $sut = $ref->newInstanceWithoutConstructor();

        // Bootstrap minimal properties expected by the private methods.
        $this->setPrivate($ref, $sut, 'options', ['json' => true]);
        $this->setPrivate($ref, $sut, 'jsonOnly', true);
        $this->setPrivate($ref, $sut, 'errors', 0);
        $this->setPrivate($ref, $sut, 'warnings', 0);
        $this->setPrivate($ref, $sut, 'checks', []);

        // Inject a no-op logger to avoid real StructuredLogService construction.
        $loggerProp = $ref->getProperty('logger');
        $loggerProp->setAccessible(true);
        $fakeLogger = $this->createMock(\App\Services\StructuredLogService::class);
        $loggerProp->setValue($sut, $fakeLogger);

        return ['sut' => $sut, 'ref' => $ref];
    }

    /**
     * @param \ReflectionClass<\MercadoLivreHealthCheck> $ref
     * @param \MercadoLivreHealthCheck $sut
     * @param mixed $value
     */
    private function setPrivate(\ReflectionClass $ref, \MercadoLivreHealthCheck $sut, string $prop, $value): void
    {
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($sut, $value);
    }

    /**
     * @param \ReflectionClass<\MercadoLivreHealthCheck> $ref
     * @param \MercadoLivreHealthCheck $sut
     * @return mixed
     */
    private function getPrivate(\ReflectionClass $ref, \MercadoLivreHealthCheck $sut, string $prop): mixed
    {
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        return $p->getValue($sut);
    }

    private function invokeWebhookCheck(
        \MercadoLivreHealthCheck $sut,
        \ReflectionClass $ref,
        string $appEnv,
        ?\PDO $db
    ): void {
        $m = $ref->getMethod('checkWebhookInfrastructure');
        $m->setAccessible(true);
        $m->invoke($sut, $appEnv, $db);
    }

    public function testCheckWebhookInfrastructureOkWhenSecretSetAndTableAccessible(): void
    {
        $_ENV['ML_WEBHOOK_SECRET'] = 'real-hmac-secret-here';

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $this->invokeWebhookCheck($sut, $ref, 'staging', $mockPdo);

        $this->assertSame(0, $this->getPrivate($ref, $sut, 'errors'));
        $this->assertSame(0, $this->getPrivate($ref, $sut, 'warnings'));

        $checks = $this->getPrivate($ref, $sut, 'checks');
        $levels = array_column($checks, 'level');
        $this->assertNotContains('error', $levels);
        $this->assertNotContains('warning', $levels);
    }

    public function testCheckWebhookInfrastructureFailsOnStagingWhenSecretMissing(): void
    {
        unset($_ENV['ML_WEBHOOK_SECRET']);

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $this->invokeWebhookCheck($sut, $ref, 'staging', $mockPdo);

        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'errors'));
    }

    public function testCheckWebhookInfrastructureFailsOnProductionWhenSecretMissing(): void
    {
        unset($_ENV['ML_WEBHOOK_SECRET']);

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $this->invokeWebhookCheck($sut, $ref, 'production', $mockPdo);

        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'errors'));
    }

    public function testCheckWebhookInfrastructureWarnsOnDevWhenSecretMissing(): void
    {
        unset($_ENV['ML_WEBHOOK_SECRET']);

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $this->invokeWebhookCheck($sut, $ref, 'development', $mockPdo);

        // In non-production/staging, missing secret is a warning, not an error.
        $this->assertSame(0, $this->getPrivate($ref, $sut, 'errors'));
        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'warnings'));
    }

    public function testCheckWebhookInfrastructureFailsOnStagingWhenTableInaccessible(): void
    {
        $_ENV['ML_WEBHOOK_SECRET'] = 'real-hmac-secret-here';

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willThrowException(new \RuntimeException('Table not found'));

        $this->invokeWebhookCheck($sut, $ref, 'staging', $mockPdo);

        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'errors'));
    }

    public function testCheckWebhookInfrastructureWarnsOnDevWhenTableInaccessible(): void
    {
        $_ENV['ML_WEBHOOK_SECRET'] = 'real-hmac-secret-here';

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willThrowException(new \RuntimeException('Table not found'));

        $this->invokeWebhookCheck($sut, $ref, 'development', $mockPdo);

        $this->assertSame(0, $this->getPrivate($ref, $sut, 'errors'));
        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'warnings'));
    }

    public function testCheckWebhookInfrastructureSkipsDbCheckWhenDbIsNull(): void
    {
        $_ENV['ML_WEBHOOK_SECRET'] = 'real-hmac-secret-here';

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $this->invokeWebhookCheck($sut, $ref, 'production', null);

        // Secret is ok; DB check skipped (info only) — no errors or warnings.
        $this->assertSame(0, $this->getPrivate($ref, $sut, 'errors'));
        $this->assertSame(0, $this->getPrivate($ref, $sut, 'warnings'));

        $checks = $this->getPrivate($ref, $sut, 'checks');
        $levels = array_column($checks, 'level');
        $this->assertContains('info', $levels);
    }

    public function testCheckWebhookInfrastructureTreatsPlaceholderSecretAsAbsent(): void
    {
        $_ENV['ML_WEBHOOK_SECRET'] = 'change_me';

        ['sut' => $sut, 'ref' => $ref] = $this->makeHealthCheck();

        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo = $this->createMock(\PDO::class);
        $mockPdo->method('query')->willReturn($mockStmt);

        $this->invokeWebhookCheck($sut, $ref, 'production', $mockPdo);

        // Placeholder is treated as missing → error in production.
        $this->assertGreaterThan(0, $this->getPrivate($ref, $sut, 'errors'));
    }
}
