<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CloneDataExportService;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CloneExportWorkflowTest extends TestCase
{
    private ReflectionClass $reflection;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(CloneDataExportService::class);
        $this->tempDir = sys_get_temp_dir() . '/clone-export-integration-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->tempDir);
    }

    public function testExportListAndDownloadPathWorkTogether(): void
    {
        $itemsStatement = $this->createMock(PDOStatement::class);
        $itemsStatement->method('execute')->willReturn(true);
        $itemsStatement->method('fetchAll')->willReturn([
            [
                'id' => 10,
                'target_item_id' => 'MLB123',
                'source_item_id' => 'MLB321',
                'source_seller_id' => '9988',
                'title' => 'Painel completo',
                'price' => 199.9,
                'category_id' => 'MLB1000',
                'status' => 'active',
                'created_at' => '2026-03-29 12:00:00',
            ],
        ]);

        $logStatement = $this->createMock(PDOStatement::class);
        $logStatement->method('execute')->willReturn(true);

        $logsListStatement = $this->createMock(PDOStatement::class);
        $logsListStatement->method('execute')->willReturn(true);
        $logsListStatement->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnOnConsecutiveCalls(
            $itemsStatement,
            $logStatement,
            $logsListStatement
        );

        /** @var CloneDataExportService $service */
        $service = $this->reflection->newInstanceWithoutConstructor();

        foreach (
            [
                'db' => $pdo,
                'accountId' => 12,
                'exportPath' => $this->tempDir,
            ] as $property => $value
        ) {
            $reflectionProperty = $this->reflection->getProperty($property);
            $reflectionProperty->setAccessible(true);
            $reflectionProperty->setValue($service, $value);
        }

        $result = $service->exportItemsToJson(['status' => 'active']);
        $exports = $service->listExports();
        $path = $service->getExportPath($result['filename']);
        file_put_contents($this->tempDir . '/clone_items_a99_2026-03-29_120001.json', '{}');

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('_a12_', $result['filename']);
        $this->assertCount(1, $exports);
        $this->assertSame($result['filename'], $exports[0]['filename']);
        $this->assertSame($path, $result['filepath']);
        $this->assertFileExists($path);
        $this->assertNull($service->getExportPath('clone_items_a99_2026-03-29_120001.json'));
    }
}
