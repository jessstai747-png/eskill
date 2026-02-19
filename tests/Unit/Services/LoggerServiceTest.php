<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\LoggerService;
use Psr\Log\LogLevel;

class LoggerServiceTest extends TestCase
{
    private string $testLogPath;
    private LoggerService $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testLogPath = __DIR__ . '/../../../storage/logs/test';

        // Limpar diretório de teste
        if (is_dir($this->testLogPath)) {
            array_map('unlink', glob($this->testLogPath . '/*'));
        }

        $this->logger = new LoggerService('test', $this->testLogPath, LogLevel::DEBUG, false);
    }

    protected function tearDown(): void
    {
        // Limpar arquivos de teste
        if (is_dir($this->testLogPath)) {
            array_map('unlink', glob($this->testLogPath . '/*'));
            rmdir($this->testLogPath);
        }
        parent::tearDown();
    }

    // =============================
    // TESTES DE NÍVEIS DE LOG
    // =============================

    public function testDebugWritesToLog(): void
    {
        $this->logger->debug('Debug message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('Debug message', $content);
    }

    public function testInfoWritesToLog(): void
    {
        $this->logger->info('Info message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testWarningWritesToLog(): void
    {
        $this->logger->warning('Warning message');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('WARNING', $content);
    }

    public function testErrorWritesToErrorLog(): void
    {
        $this->logger->error('Error message');

        $logFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function testCriticalWritesToErrorLog(): void
    {
        $this->logger->critical('Critical message');

        $logFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('CRITICAL', $content);
    }

    // =============================
    // TESTES DE CONTEXTO
    // =============================

    public function testContextInterpolation(): void
    {
        $this->logger->info('User {name} logged in', ['name' => 'John']);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('User John logged in', $content);
    }

    public function testContextWithNumericValues(): void
    {
        $this->logger->info('Processed {count} items in {time}ms', [
            'count' => 42,
            'time' => 123.45,
        ]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('Processed 42 items', $content);
        $this->assertStringContainsString('123.45ms', $content);
    }

    public function testContextWithBooleanValues(): void
    {
        $this->logger->info('Feature enabled: {enabled}', ['enabled' => true]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('enabled: true', $content);
    }

    public function testContextWithDateTime(): void
    {
        $date = new \DateTime('2025-01-15 10:30:00');
        $this->logger->info('Scheduled for {date}', ['date' => $date]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('2025-01-15 10:30:00', $content);
    }

    // =============================
    // TESTES DE EXCEPTION LOGGING
    // =============================

    public function testExceptionLogging(): void
    {
        $exception = new \Exception('Test exception', 500);

        $this->logger->error('Operation failed', ['exception' => $exception]);

        $logFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('Test exception', $content);
        $this->assertStringContainsString('Exception', $content);
    }

    // =============================
    // TESTES DE NÍVEL MÍNIMO
    // =============================

    public function testMinLevelFiltersDebug(): void
    {
        $logger = new LoggerService('test', $this->testLogPath, LogLevel::INFO, false);

        $logger->debug('This should not be logged');
        $logger->info('This should be logged');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringNotContainsString('This should not be logged', $content);
        $this->assertStringContainsString('This should be logged', $content);
    }

    public function testMinLevelAllowsHigherSeverity(): void
    {
        $logger = new LoggerService('test', $this->testLogPath, LogLevel::WARNING, false);

        $logger->info('Info not logged');
        $logger->warning('Warning logged');
        $logger->error('Error logged');

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';

        // Info não deve existir no arquivo principal
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            $this->assertStringNotContainsString('Info not logged', $content);
            $this->assertStringContainsString('Warning logged', $content);
        }

        // Error deve estar no arquivo de erro
        $errorFile = $this->testLogPath . '/error-' . date('Y-m-d') . '.log';
        $errorContent = file_get_contents($errorFile);
        $this->assertStringContainsString('Error logged', $errorContent);
    }

    // =============================
    // TESTES DE FORMATO JSON
    // =============================

    public function testJsonFormat(): void
    {
        $logger = new LoggerService('test', $this->testLogPath, LogLevel::DEBUG, true);

        $logger->info('JSON log entry', ['key' => 'value']);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $decoded = json_decode(trim($content), true);

        $this->assertNotNull($decoded, 'Log deve ser JSON válido');
        $this->assertEquals('INFO', $decoded['level']);
        $this->assertEquals('test', $decoded['channel']);
        $this->assertStringContainsString('JSON log entry', $decoded['message']);
    }

    // =============================
    // TESTES DE HELPERS
    // =============================

    public function testLogApiRequest(): void
    {
        $this->logger->logApiRequest('GET', '/api/items', ['limit' => 10], ['status' => 200], 0.125);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('API Request', $content);
        $this->assertStringContainsString('GET', $content);
        $this->assertStringContainsString('/api/items', $content);
    }

    public function testLogUserAction(): void
    {
        $this->logger->logUserAction('login', ['user_id' => 123]);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('User Action', $content);
        $this->assertStringContainsString('login', $content);
    }

    public function testLogPerformance(): void
    {
        $this->logger->logPerformance('database_query', 0.045, ['query' => 'SELECT * FROM users']);

        $logFile = $this->testLogPath . '/test-' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('Performance', $content);
        $this->assertStringContainsString('database_query', $content);
    }

    // =============================
    // TESTES DE CHANNEL
    // =============================

    public function testChannelFactory(): void
    {
        $logger = LoggerService::channel('api');

        $this->assertInstanceOf(LoggerService::class, $logger);
    }

    // =============================
    // TESTES DE DIRETÓRIO
    // =============================

    public function testCreatesLogDirectoryIfNotExists(): void
    {
        $newPath = $this->testLogPath . '/subdir';

        $logger = new LoggerService('test', $newPath, LogLevel::DEBUG, false);
        $logger->info('Test');

        $this->assertDirectoryExists($newPath);

        // Cleanup
        array_map('unlink', glob($newPath . '/*'));
        rmdir($newPath);
    }

    // =============================
    // TESTES DE PSR-3 COMPLIANCE
    // =============================

    public function testImplementsPsrLoggerInterface(): void
    {
        $this->assertInstanceOf(\Psr\Log\LoggerInterface::class, $this->logger);
    }

    public function testAllPsrLevelsWork(): void
    {
        $this->logger->emergency('emergency');
        $this->logger->alert('alert');
        $this->logger->critical('critical');
        $this->logger->error('error');
        $this->logger->warning('warning');
        $this->logger->notice('notice');
        $this->logger->info('info');
        $this->logger->debug('debug');

        // Se chegou aqui sem exceção, passou
        $this->assertTrue(true);
    }
}
