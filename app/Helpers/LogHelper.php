<?php
declare(strict_types=1);

/**
 * Helper Global para Logs
 * 
 * Funções auxiliares para facilitar o uso do sistema de logs em todo o sistema.
 */

use App\Services\StructuredLogService;

if (!function_exists('logger')) {
    /**
     * Obter instância do logger
     */
    function logger(): StructuredLogService
    {
        static $logger = null;
        static $lastLogPath = null;

        // Detectar caminho atual esperado
        $currentLogPath = getenv('LOG_PATH') ?: (__DIR__ . '/../../storage/logs/app.log');

        // Recriar logger se ainda não existir ou se o LOG_PATH mudou entre testes/execuções
        if ($logger === null || $lastLogPath !== $currentLogPath) {
            $logger = new StructuredLogService();
            $lastLogPath = $currentLogPath;
        }

        return $logger;
    }
}

if (!function_exists('log_debug')) {
    /**
     * Log de debug
     */
    function log_debug(string $message, array $context = []): void
    {
        try {
            logger()->debug($message, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper debug failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_info')) {
    /**
     * Log informativo
     */
    function log_info(string $message, array $context = []): void
    {
        try {
            logger()->info($message, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper info failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_warning')) {
    /**
     * Log de warning
     */
    function log_warning(string $message, array $context = []): void
    {
        try {
            logger()->warning($message, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper warning failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_error')) {
    /**
     * Log de erro
     */
    function log_error(string $message, array $context = []): void
    {
        try {
            logger()->error($message, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper error failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_critical')) {
    /**
     * Log crítico
     */
    function log_critical(string $message, array $context = []): void
    {
        try {
            logger()->critical($message, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper critical failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_exception')) {
    /**
     * Log de exceção
     */
    function log_exception(\Throwable $e, array $context = []): void
    {
        try {
            logger()->exception($e, $context);
        } catch (\Throwable $ex) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper exception failed: " . $ex->getMessage());
        }
    }
}

if (!function_exists('log_performance')) {
    /**
     * Log de performance
     */
    function log_performance(string $operation, float $duration, array $context = []): void
    {
        try {
            logger()->performance($operation, $duration, $context);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper performance failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('log_audit')) {
    /**
     * Log de auditoria
     */
    function log_audit(string $action, array $data = []): void
    {
        try {
            logger()->audit($action, $data);
        } catch (\Throwable $e) {
            // Silently fail to prevent breaking the application
            error_log("LogHelper audit failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('measure_time')) {
    /**
     * Medir tempo de execução de uma função
     * 
     * @param callable $callback Função a executar
     * @param string $operation Nome da operação para log
     * @return mixed Retorno da função executada
     */
    function measure_time(callable $callback, string $operation)
    {
        $start = microtime(true);
        
        try {
            $result = $callback();
            $duration = microtime(true) - $start;
            
            log_performance($operation, $duration, [
                'status' => 'success'
            ]);
            
            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            
            log_performance($operation, $duration, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
