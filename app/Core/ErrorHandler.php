<?php

declare(strict_types=1);

/**
 * Handler de Erros para Debug
 * Adicione no início do index.php para debug: require_once __DIR__ . '/error_handler.php';
 *
 * SEGURANÇA: display_errors só ativa em ambiente dev/local.
 */

$isDevEnvironment = in_array(($_ENV['APP_ENV'] ?? 'production'), ['local', 'development', 'dev'], true);

error_reporting(E_ALL);
ini_set('display_errors', $isDevEnvironment ? '1' : '0');
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php_errors.log');

// Handler personalizado
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    $error = [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
        'time' => date('Y-m-d H:i:s')
    ];

    log_error('PHP Error', $error);

    // Em desenvolvimento, mostrar erro
    if (ini_get('display_errors')) {
        echo "<div style='background:#fee;border:2px solid #f00;padding:10px;margin:10px;border-radius:5px;'>";
        echo "<strong>Erro:</strong> " . htmlspecialchars($message) . "<br>";
        echo "<strong>Arquivo:</strong> " . htmlspecialchars($file) . "<br>";
        echo "<strong>Linha:</strong> {$line}";
        echo "</div>";
    }

    return true;
});

// Handler de exceções
set_exception_handler(function ($exception) {
    $error = [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'time' => date('Y-m-d H:i:s')
    ];

    log_error('Uncaught Exception', $error);

    if (ini_get('display_errors')) {
        echo "<div style='background:#fee;border:2px solid #f00;padding:15px;margin:10px;border-radius:5px;'>";
        echo "<h3>Exceção: " . htmlspecialchars(get_class($exception)) . "</h3>";
        echo "<p><strong>Mensagem:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>Arquivo:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
        echo "<p><strong>Linha:</strong> " . $exception->getLine() . "</p>";
        echo "<pre style='background:#fff;padding:10px;overflow:auto;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    }
});
