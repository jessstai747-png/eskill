<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\SettingsService;
use PDO;
use PDOStatement;

/**
 * @covers \App\Services\SettingsService
 */
class SettingsServiceTest extends TestCase
{
    private SettingsService $service;
    private PDO $mockDb;
    private PDOStatement $mockStmt;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(PDO::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);

        $this->service = new SettingsService(
            accountId: 123,
            db: $this->mockDb,
            skipDbAutoConnect: true
        );
    }

    // ── Constructor / DI ────────────────────────────────────────────

    public function testConstructorWithAllDeps(): void
    {
        $service = new SettingsService(
            accountId: 1,
            db: $this->mockDb,
            skipDbAutoConnect: true
        );
        $this->assertInstanceOf(SettingsService::class, $service);
    }

    public function testConstructorWithSkipDbOnly(): void
    {
        $service = new SettingsService(
            accountId: 1,
            skipDbAutoConnect: true
        );
        $this->assertInstanceOf(SettingsService::class, $service);
    }

    // ── get() ───────────────────────────────────────────────────────

    public function testGetReturnsValueFromDb(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['aid' => 123, 'key' => 'theme']);

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('dark');

        $result = $this->service->get('theme');
        $this->assertSame('dark', $result);
    }

    public function testGetReturnsDefaultWhenNotFound(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $result = $this->service->get('missing_key', 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testGetReturnsNullDefaultWhenNotFoundAndNoDefault(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $result = $this->service->get('nonexistent');
        $this->assertNull($result);
    }

    public function testGetReturnsZeroStringAsValue(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('0');

        $result = $this->service->get('some_flag', 'default');
        $this->assertSame('0', $result);
    }

    // ── set() ───────────────────────────────────────────────────────

    public function testSetExecutesUpsertQuery(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['aid' => 123, 'key' => 'theme', 'val' => 'dark']);

        $this->service->set('theme', 'dark');
    }

    public function testSetWithNumericValue(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['aid' => 123, 'key' => 'page_size', 'val' => 50]);

        $this->service->set('page_size', 50);
    }

    public function testSetWithEmptyStringValue(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['aid' => 123, 'key' => 'notes', 'val' => '']);

        $this->service->set('notes', '');
    }

    // ── getAll() ────────────────────────────────────────────────────

    public function testGetAllReturnsKeyValuePairs(): void
    {
        $expected = ['theme' => 'dark', 'lang' => 'pt-BR', 'page_size' => '25'];

        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute')
            ->with(['aid' => 123]);

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_KEY_PAIR)
            ->willReturn($expected);

        $result = $this->service->getAll();
        $this->assertSame($expected, $result);
    }

    public function testGetAllReturnsEmptyArrayWhenNoSettings(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchAll')
            ->willReturn([]);

        $result = $this->service->getAll();
        $this->assertSame([], $result);
    }

    // ── getDefaultTaxRate() ─────────────────────────────────────────

    public function testGetDefaultTaxRateReturnsFloat(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('12.5');

        $result = $this->service->getDefaultTaxRate();
        $this->assertSame(12.5, $result);
    }

    public function testGetDefaultTaxRateReturnsZeroWhenNotSet(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $result = $this->service->getDefaultTaxRate();
        $this->assertSame(0.0, $result);
    }

    // ── getDefaultPricingStrategy() ─────────────────────────────────

    public function testGetDefaultPricingStrategyReturnsString(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn('competitive');

        $result = $this->service->getDefaultPricingStrategy();
        $this->assertSame('competitive', $result);
    }

    public function testGetDefaultPricingStrategyReturnsEmptyStringWhenNotSet(): void
    {
        $this->mockDb->expects($this->once())
            ->method('prepare')
            ->willReturn($this->mockStmt);

        $this->mockStmt->expects($this->once())
            ->method('execute');

        $this->mockStmt->expects($this->once())
            ->method('fetchColumn')
            ->willReturn(false);

        $result = $this->service->getDefaultPricingStrategy();
        $this->assertSame('', $result);
    }
}
