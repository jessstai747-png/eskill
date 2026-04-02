<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneDataExportService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\CloneDataExportService
 */
class CloneDataExportServiceTest extends TestCase
{
    private ReflectionClass $reflection;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(CloneDataExportService::class);
        $this->tempDir = sys_get_temp_dir() . '/clone-export-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (is_dir($this->tempDir)) {
            foreach (glob($this->tempDir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    private function newService(PDO $pdo, int $accountId = 77): CloneDataExportService
    {
        /** @var CloneDataExportService $service */
        $service = $this->reflection->newInstanceWithoutConstructor();

        $this->setProperty($service, 'db', $pdo);
        $this->setProperty($service, 'accountId', $accountId);
        $this->setProperty($service, 'exportPath', $this->tempDir);

        return $service;
    }

    private function setProperty(CloneDataExportService $service, string $name, mixed $value): void
    {
        $property = $this->reflection->getProperty($name);
        $property->setAccessible(true);
        $property->setValue($service, $value);
    }

    /**
     * @return PDOStatement&MockObject
     */
    private function createStatementMock(): PDOStatement
    {
        return $this->createMock(PDOStatement::class);
    }

    public function testExportItemsToJsonReturnsBackwardCompatibleDownloadContract(): void
    {
        $itemsStatement = $this->createStatementMock();
        $itemsStatement->expects($this->once())
            ->method('execute')
            ->with($this->arrayHasKey('account_id'))
            ->willReturn(true);
        $itemsStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'id' => 1,
                    'target_item_id' => 'MLB123',
                    'source_item_id' => 'MLB321',
                    'source_seller_id' => '9988',
                    'title' => 'Carenagem lateral',
                    'price' => 99.9,
                    'category_id' => 'MLB1000',
                    'status' => 'active',
                    'created_at' => '2026-03-29 10:00:00',
                ],
            ]);

        $logStatement = $this->createStatementMock();
        $logStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return $params['account_id'] === 77
                    && $params['scope'] === 'items'
                    && $params['format'] === 'json'
                    && $params['count'] === 1
                    && is_string($params['filename'])
                    && is_string($params['filters_json']);
            }))
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(2))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls($itemsStatement, $logStatement);

        $service = $this->newService($pdo);
        $result = $service->exportItemsToJson(['status' => 'active']);

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('_a77_', $result['filename']);
        $this->assertSame($result['filename'], $result['file']);
        $this->assertSame('items', $result['scope']);
        $this->assertSame('json', $result['format']);
        $this->assertSame(1, $result['total_items']);
        $this->assertStringContainsString($result['filename'], $result['download_url']);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertFileExists($result['filepath']);
    }

    public function testListExportsIncludesDownloadUrlAndMetadataFromLogTable(): void
    {
        $filename = 'clone_report_2026-03-29_120000.html';
        file_put_contents($this->tempDir . '/' . $filename, '<html>report</html>');

        $logsStatement = $this->createStatementMock();
        $logsStatement->expects($this->once())
            ->method('execute')
            ->with(['account_id' => 77])
            ->willReturn(true);
        $logsStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'filename' => $filename,
                    'export_scope' => 'report',
                    'export_format' => 'html',
                    'item_count' => 12,
                    'size_bytes' => 17,
                    'created_at' => '2026-03-29T12:00:00+00:00',
                ],
            ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($logsStatement);

        $service = $this->newService($pdo);
        $exports = $service->listExports();

        $this->assertCount(1, $exports);
        $this->assertSame($filename, $exports[0]['filename']);
        $this->assertSame('report', $exports[0]['scope']);
        $this->assertSame('html', $exports[0]['format']);
        $this->assertSame(12, $exports[0]['item_count']);
        $this->assertStringEndsWith('/' . $filename, $exports[0]['download_url']);
    }

    public function testGetExportPathResolvesOnlyFilesInsideExportDirectory(): void
    {
        $filename = 'clone_items_a77_latest.csv';
        $filepath = $this->tempDir . '/' . $filename;
        file_put_contents($filepath, 'id;title');

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())
            ->method('prepare');
        $service = $this->newService($pdo);

        $this->assertSame($filepath, $service->getExportPath('../' . $filename));
        $this->assertNull($service->getExportPath('missing.csv'));
    }

    public function testListExportsOnlyReturnsFilesOwnedByCurrentAccount(): void
    {
        $ownedFilename = 'clone_report_a77_2026-03-29_120000.html';
        $foreignFilename = 'clone_report_a99_2026-03-29_120000.html';
        file_put_contents($this->tempDir . '/' . $ownedFilename, '<html>owned</html>');
        file_put_contents($this->tempDir . '/' . $foreignFilename, '<html>foreign</html>');

        $logsStatement = $this->createStatementMock();
        $logsStatement->expects($this->once())
            ->method('execute')
            ->with(['account_id' => 77])
            ->willReturn(true);
        $logsStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([
                [
                    'filename' => $ownedFilename,
                    'export_scope' => 'report',
                    'export_format' => 'html',
                    'item_count' => 5,
                    'size_bytes' => 18,
                    'created_at' => '2026-03-29T12:00:00+00:00',
                ],
            ]);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->willReturn($logsStatement);

        $service = $this->newService($pdo);
        $exports = $service->listExports();

        $this->assertCount(1, $exports);
        $this->assertSame($ownedFilename, $exports[0]['filename']);
    }

    public function testExportFullReportNormalizesLegacyPeriodIntoDays(): void
    {
        $itemsStatement = $this->createStatementMock();
        $itemsStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return $params['account_id'] === 77 && $params['days'] === 7;
            }))
            ->willReturn(true);
        $itemsStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $jobsStatement = $this->createStatementMock();
        $jobsStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return $params['account_id'] === 77 && $params['days'] === 7;
            }))
            ->willReturn(true);
        $jobsStatement->expects($this->once())
            ->method('fetchAll')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $itemsMetricsStatement = $this->createStatementMock();
        $itemsMetricsStatement->expects($this->once())
            ->method('execute')
            ->with(['account_id' => 77, 'days' => 7])
            ->willReturn(true);
        $itemsMetricsStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $jobsMetricsStatement = $this->createStatementMock();
        $jobsMetricsStatement->expects($this->once())
            ->method('execute')
            ->with(['account_id' => 77, 'days' => 7])
            ->willReturn(true);
        $jobsMetricsStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $performanceStatement = $this->createStatementMock();
        $performanceStatement->expects($this->once())
            ->method('execute')
            ->with(['account_id' => 77])
            ->willReturn(true);
        $performanceStatement->expects($this->once())
            ->method('fetch')
            ->with(PDO::FETCH_ASSOC)
            ->willReturn([]);

        $logStatement = $this->createStatementMock();
        $logStatement->expects($this->once())
            ->method('execute')
            ->with($this->callback(function (array $params): bool {
                return $params['account_id'] === 77
                    && $params['scope'] === 'report'
                    && $params['format'] === 'html'
                    && str_contains((string) $params['filters_json'], '"days":7');
            }))
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->exactly(6))
            ->method('prepare')
            ->willReturnOnConsecutiveCalls(
                $itemsStatement,
                $jobsStatement,
                $itemsMetricsStatement,
                $jobsMetricsStatement,
                $performanceStatement,
                $logStatement
            );

        $service = $this->newService($pdo);
        $result = $service->exportFullReport(['period' => '7d']);

        $this->assertTrue($result['success']);
        $this->assertSame('report', $result['scope']);
        $this->assertStringContainsString('_a77_', $result['filename']);
    }
}
