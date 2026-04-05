<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\ErrorMonitoringService;

class ExceptionHandler
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handle']);

        // Convert E_USER_ERROR / E_RECOVERABLE_ERROR to exceptions so they pass
        // through the same handler (exceptions are logged + monitored; raw PHP
        // errors are not). E_DEPRECATED and E_NOTICE are ignored here to avoid
        // breaking third-party vendor code.
        set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
            if ($errno & (E_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR)) {
                throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
            // Return false to let PHP process warnings/notices normally
            return false;
        });

        // Catch fatal errors (memory exhaustion, parse errors in eval, etc.)
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }
            $fatals = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
            if ($error['type'] & $fatals) {
                self::handle(new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                ));
            }
        });
    }

    public static function handle(\Throwable $e): void
    {
        // Log o erro completo para arquivo
        self::log($e);

        // Registrar no banco via ErrorMonitoringService (para dashboard e alertas)
        self::logToMonitoring($e);

        // Se for CLI, apenas exibe o erro e sai (o worker tem seu proprio handling, mas isso ajuda script avulsos)
        if (php_sapi_name() === 'cli') {
            echo "[" . get_class($e) . "] " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            exit(1);
        }

        $headersAlreadySent = headers_sent();
        if ($headersAlreadySent) {
            exit(1);
        }

        $statusCode = 500;
        http_response_code($statusCode);

        $isDebug = self::isDebug();
        $isJsonRequest = self::wantsJson();

        if ($isJsonRequest) {
            header('Content-Type: application/json; charset=utf-8');

            $response = [
                'error' => 'Internal Server Error',
                'message' => 'Ocorreu um erro inesperado no servidor.'
            ];

            if ($isDebug) {
                $response['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ];
            }

            echo json_encode($response);
            exit(1);
        }

        $errorView = __DIR__ . '/../Views/errors/500.php';
        if (!$isDebug && file_exists($errorView)) {
            header('Content-Type: text/html; charset=utf-8');
            require $errorView;
            exit(1);
        }

        if (!$isDebug) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Erro interno no servidor.';
            exit(1);
        }

        header('Content-Type: text/html; charset=utf-8');
        $safeMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<h1>Erro interno</h1>';
        echo '<p>' . $safeMessage . '</p>';
        exit(1); // @phpstan-ignore-line
    }

    private static function wantsJson(): bool
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '';
        if (strpos($path, '/api/') === 0) {
            return true;
        }

        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        return strpos($accept, 'application/json') !== false;
    }

    private static function isDebug(): bool
    {
        return (getenv('APP_DEBUG') === 'true') || (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true');
    }

    private static function log(\Throwable $e): void
    {
        $logFile = __DIR__ . '/../../storage/logs/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack Trace:\n%s\n------------------\n",
            $timestamp,
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        // Garantir diretório
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        error_log($message, 3, $logFile);
    }

    /**
     * Registra a exceção no ErrorMonitoringService (banco + alertas).
     * Envolvido em try/catch para NUNCA interferir no fluxo do handler.
     */
    private static function logToMonitoring(\Throwable $e): void
    {
        try {
            $service = new ErrorMonitoringService();
            $service->logError([
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
                'severity' => 'critical',
                'context' => [
                    'sapi' => php_sapi_name(),
                    'memory_usage' => memory_get_peak_usage(true),
                ],
            ]);
        } catch (\Throwable $ignored) {
            // Silenciar — o log de arquivo já foi gravado em self::log()
        }
    }
}
