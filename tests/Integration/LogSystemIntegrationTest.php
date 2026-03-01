<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\StructuredLogService;

/**
 * Testes de Integração do Sistema de Logs
 */
class LogSystemIntegrationTest extends TestCase
{
    private StructuredLogService $logger;
    private string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Usar diretório dentro do workspace para testes (sandbox tem /tmp read-only)
        $workspaceStorage = dirname(__DIR__, 2) . '/storage/test-logs';
        if (!is_dir($workspaceStorage)) {
            mkdir($workspaceStorage, 0777, true);
        }
        $this->testLogPath = $workspaceStorage . '/test_app_' . uniqid() . '.log';
        putenv('LOG_PATH=' . $this->testLogPath);

        $this->logger = new StructuredLogService();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Limpar arquivo de teste
        if (file_exists($this->testLogPath)) {
            unlink($this->testLogPath);
        }

        // Limpar arquivos rotacionados
        $dir = dirname($this->testLogPath);
        $basename = basename($this->testLogPath, '.log');
        foreach (glob($dir . '/' . $basename . '*.log*') as $file) {
            unlink($file);
        }
    }

    public function testLogsAreWrittenToFile()
    {
        $this->logger->info('Test message');

        $this->assertFileExists($this->testLogPath);

        $content = file_get_contents($this->testLogPath);
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('Test message', $content);
    }

    public function testLogIsJsonFormatted()
    {
        $this->logger->info('JSON test');

        $content = file_get_contents($this->testLogPath);
        $lines = explode("\n", trim($content));
        $lastLine = end($lines);

        $json = json_decode($lastLine, true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('message', $json);
        $this->assertArrayHasKey('level_name', $json);
        $this->assertArrayHasKey('datetime', $json);
        $this->assertEquals('JSON test', $json['message']);
    }

    public function testDifferentLogLevelsAreRecorded()
    {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');

        $content = file_get_contents($this->testLogPath);

        $this->assertStringContainsString('Debug message', $content);
        $this->assertStringContainsString('Info message', $content);
        $this->assertStringContainsString('Warning message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function testContextIsIncluded()
    {
        $context = [
            'user_id' => 123,
            'action' => 'test_action',
            'data' => ['key' => 'value']
        ];

        $this->logger->info('Context test', $context);

        $content = file_get_contents($this->testLogPath);
        $lines = explode("\n", trim($content));
        $lastLine = end($lines);
        $json = json_decode($lastLine, true);

        $this->assertArrayHasKey('context', $json);
        $this->assertEquals(123, $json['context']['user_id']);
        $this->assertEquals('test_action', $json['context']['action']);
    }

    public function testExceptionLogging()
    {
        $exception = new \Exception('Test exception', 500);

        $this->logger->exception($exception);

        $content = file_get_contents($this->testLogPath);

        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('Exception', $content);
        $this->assertStringContainsString('trace', $content);
    }

    public function testPerformanceLogging()
    {
        $this->logger->performance('test_operation', 1.5, ['records' => 100]);

        $content = file_get_contents($this->testLogPath);
        $lines = explode("\n", trim($content));
        $lastLine = end($lines);
        $json = json_decode($lastLine, true);

        $this->assertArrayHasKey('context', $json);
        $this->assertArrayHasKey('performance', $json['context']);
        $this->assertEquals('test_operation', $json['context']['performance']['operation']);
        $this->assertEquals(1500, $json['context']['performance']['duration_ms']);
    }

    public function testAuditLogging()
    {
        $data = ['old' => 'value1', 'new' => 'value2'];

        $this->logger->audit('setting_changed', $data);

        $content = file_get_contents($this->testLogPath);
        $lines = explode("\n", trim($content));
        $lastLine = end($lines);
        $json = json_decode($lastLine, true);

        $this->assertArrayHasKey('context', $json);
        $this->assertTrue($json['context']['audit']);
        $this->assertEquals('setting_changed', $json['context']['action']);
    }

    public function testSearchFunctionality()
    {
        // Criar vários logs
        $this->logger->debug('Debug log');
        $this->logger->info('Info log');
        $this->logger->warning('Warning log');
        $this->logger->error('Error log');

        // Buscar todos
        $results = $this->logger->search(['limit' => 10]);
        $this->assertGreaterThan(0, count($results));

        // Buscar por nível
        $errorLogs = $this->logger->search(['level' => 'error', 'limit' => 10]);
        $this->assertGreaterThan(0, count($errorLogs));

        foreach ($errorLogs as $log) {
            $this->assertEquals('ERROR', $log['level_name']);
        }
    }

    public function testStatistics()
    {
        // Criar logs de diferentes níveis
        $this->logger->info('Info 1');
        $this->logger->info('Info 2');
        $this->logger->warning('Warning 1');
        $this->logger->error('Error 1');

        $stats = $this->logger->getStatistics();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_level', $stats);
        $this->assertGreaterThan(0, $stats['total']);
        $this->assertGreaterThan(0, $stats['by_level']['info']);
    }

    public function testGlobalHelperFunctions()
    {
        // Testar se as funções globais funcionam
        log_info('Global helper test');

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('Global helper test', $content);
    }

    public function testMeasureTimeHelper()
    {
        $result = measure_time(function () {
            usleep(100000); // 0.1 second
            return 'test_result';
        }, 'test_operation');

        $this->assertEquals('test_result', $result);

        $content = file_get_contents($this->testLogPath);
        $this->assertStringContainsString('test_operation', $content);
        $this->assertStringContainsString('performance', $content);
    }

    public function testConcurrentLogging()
    {
        // Simular logs concorrentes
        for ($i = 0; $i < 10; $i++) {
            $this->logger->info("Concurrent log {$i}", ['iteration' => $i]);
        }

        $content = file_get_contents($this->testLogPath);
        $lines = explode("\n", trim($content));

        // Deve ter pelo menos 10 logs
        $this->assertGreaterThanOrEqual(10, count($lines));
    }
}
