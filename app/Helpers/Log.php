<?php

namespace App\Helpers;

use App\Services\LoggerService;

/**
 * Log Facade - Acesso rápido ao sistema de logging
 * 
 * @example
 * Log::info('User logged in', ['user_id' => 123]);
 * Log::error('Payment failed', ['exception' => $e]);
 * Log::channel('api')->debug('Request received');
 */
class Log
{
    private static ?LoggerService $instance = null;

    /**
     * Obtém instância padrão do logger
     */
    private static function getInstance(): LoggerService
    {
        if (self::$instance === null) {
            self::$instance = LoggerService::channel('app');
        }
        return self::$instance;
    }

    /**
     * Cria logger para canal específico
     */
    public static function channel(string $channel): LoggerService
    {
        return LoggerService::channel($channel);
    }

    /**
     * Log emergency
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::getInstance()->emergency($message, $context);
    }

    /**
     * Log alert
     */
    public static function alert(string $message, array $context = []): void
    {
        self::getInstance()->alert($message, $context);
    }

    /**
     * Log critical
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    /**
     * Log error
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * Log warning
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    /**
     * Log notice
     */
    public static function notice(string $message, array $context = []): void
    {
        self::getInstance()->notice($message, $context);
    }

    /**
     * Log info
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * Log debug
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * Log API request
     */
    public static function api(string $method, string $endpoint, array $params = [], ?array $response = null, ?float $duration = null): void
    {
        self::channel('api')->logApiRequest($method, $endpoint, $params, $response, $duration);
    }

    /**
     * Log user action
     */
    public static function userAction(string $action, array $details = []): void
    {
        self::channel('user')->logUserAction($action, $details);
    }

    /**
     * Log performance metric
     */
    public static function performance(string $operation, float $duration, array $metadata = []): void
    {
        self::channel('performance')->logPerformance($operation, $duration, $metadata);
    }

    /**
     * Log security event
     */
    public static function security(string $event, array $context = []): void
    {
        self::channel('security')->warning($event, $context);
    }

    /**
     * Reset instance (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
