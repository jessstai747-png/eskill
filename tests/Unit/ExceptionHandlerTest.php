<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\ExceptionHandler;
use Tests\TestCase;

/**
 * @covers \App\Core\ExceptionHandler
 */
class ExceptionHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG']);
        parent::tearDown();
    }

    // ── wantsJson ───────────────────────────────────────────────────

    public function test_wants_json_true_for_api_path(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/items/list';
        unset($_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_wants_json_false_for_html_request_without_ajax_or_json_accept(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    public function test_wants_json_true_for_xhr_request(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_wants_json_true_for_json_accept_header(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_wants_json_false_when_no_server_vars(): void
    {
        unset($_SERVER['REQUEST_URI'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_SERVER['HTTP_ACCEPT']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('wantsJson');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    // ── isDebug ─────────────────────────────────────────────────────

    public function test_is_debug_false_by_default(): void
    {
        putenv('APP_DEBUG');
        unset($_ENV['APP_DEBUG']);

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('isDebug');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    public function test_is_debug_true_with_env_var(): void
    {
        putenv('APP_DEBUG=true');

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('isDebug');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null));
    }

    public function test_is_debug_false_with_non_true_value(): void
    {
        putenv('APP_DEBUG=false');

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('isDebug');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null));
    }

    // ── log ─────────────────────────────────────────────────────────

    public function test_log_writes_to_error_file(): void
    {
        $logFile = __DIR__ . '/../../storage/logs/error.log';

        // Capture state before
        $sizeBefore = file_exists($logFile) ? filesize($logFile) : 0;

        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('log');
        $method->setAccessible(true);

        $exception = new \RuntimeException('Test exception for logging');
        $method->invoke(null, $exception);

        $this->assertFileExists($logFile);
        $sizeAfter = filesize($logFile);
        $this->assertGreaterThan($sizeBefore, $sizeAfter);

        // Verify content contains exception message
        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Test exception for logging', $content);
    }

    // ── register ────────────────────────────────────────────────────

    public function test_register_method_exists(): void
    {
        $this->assertTrue(
            method_exists(ExceptionHandler::class, 'register'),
            'ExceptionHandler should have register method'
        );
    }

    public function test_handle_method_exists(): void
    {
        $this->assertTrue(
            method_exists(ExceptionHandler::class, 'handle'),
            'ExceptionHandler should have handle method'
        );
    }

    // ── logToMonitoring ─────────────────────────────────────────────

    public function test_log_to_monitoring_does_not_throw(): void
    {
        // logToMonitoring should never throw, even if DB is down
        $reflection = new \ReflectionClass(ExceptionHandler::class);
        $method = $reflection->getMethod('logToMonitoring');
        $method->setAccessible(true);

        $exception = new \RuntimeException('Test exception for monitoring');

        // This should not throw even without DB connection
        $method->invoke(null, $exception);

        // If we got here, logToMonitoring swallowed the error as expected
        $this->assertTrue(true);
    }
}
