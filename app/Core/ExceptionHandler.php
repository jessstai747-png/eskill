<?php

declare(strict_types=1);

namespace App\Core;

class ExceptionHandler
{
    public static function register()
    {
        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(\Throwable $e)
    {
        // Log o erro completo para os desenvolvedores
        self::log($e);

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
        exit(1);
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

    private static function log(\Throwable $e)
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
}
