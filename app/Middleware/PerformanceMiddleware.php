<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Log;

/**
 * Performance Middleware
 * 
 * Registra métricas de performance para cada requisição:
 * - Tempo de execução
 * - Uso de memória
 * - Peak memory
 * 
 * Adiciona headers X-Response-Time e X-Memory-Usage
 */
class PerformanceMiddleware
{
    private float $startTime;
    private int $startMemory;
    private float $slowThreshold;
    private bool $logEnabled;

    public function __construct(float $slowThreshold = 1.0, bool $logEnabled = true)
    {
        $this->slowThreshold = $slowThreshold; // Segundos
        $this->logEnabled = $logEnabled;
    }

    /**
     * Inicia medição de performance
     */
    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Finaliza medição e registra métricas
     */
    public function finish(): void
    {
        $duration = microtime(true) - $this->startTime;
        $memoryUsed = memory_get_usage(true) - $this->startMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Adicionar headers se ainda não enviados
        if (!headers_sent()) {
            header('X-Response-Time: ' . round($duration * 1000, 2) . 'ms');
            header('X-Memory-Usage: ' . $this->formatBytes($memoryUsed));
            header('X-Memory-Peak: ' . $this->formatBytes($peakMemory));
        }

        // Logar se habilitado
        if ($this->logEnabled) {
            $this->logMetrics($duration, $memoryUsed, $peakMemory);
        }
    }

    /**
     * Registra métricas no log
     */
    private function logMetrics(float $duration, int $memoryUsed, int $peakMemory): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $context = [
            'method' => $method,
            'uri' => $uri,
            'duration_ms' => round($duration * 1000, 2),
            'memory_used_mb' => round($memoryUsed / (1024 * 1024), 2),
            'memory_peak_mb' => round($peakMemory / (1024 * 1024), 2),
        ];

        // Log de requisição lenta
        if ($duration > $this->slowThreshold) {
            Log::channel('performance')->warning('Slow request: {method} {uri} took {duration_ms}ms', $context);
        } else {
            Log::channel('performance')->debug('Request: {method} {uri} - {duration_ms}ms', $context);
        }
    }

    /**
     * Formata bytes para leitura humana
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . 'KB';
        }

        return round($bytes / (1024 * 1024), 2) . 'MB';
    }

    /**
     * Handler estático para uso como shutdown function
     */
    public static function registerShutdown(float $slowThreshold = 1.0): void
    {
        $middleware = new self($slowThreshold);
        $middleware->start();

        register_shutdown_function([$middleware, 'finish']);
    }

    /**
     * Mede tempo de execução de uma função
     */
    public static function measure(callable $callback, string $operationName = 'operation'): mixed
    {
        $start = microtime(true);
        $memStart = memory_get_usage(true);

        $result = $callback();

        $duration = microtime(true) - $start;
        $memUsed = memory_get_usage(true) - $memStart;

        Log::performance($operationName, $duration, [
            'memory_used_mb' => round($memUsed / (1024 * 1024), 2),
        ]);

        return $result;
    }

    /**
     * Timer helper para blocos de código
     */
    public static function timer(string $name): PerformanceTimer
    {
        return new PerformanceTimer($name);
    }
}

/**
 * Helper class para medir tempo de blocos de código
 */
class PerformanceTimer
{
    private string $name;
    private float $startTime;
    private int $startMemory;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Para o timer e loga a métrica
     */
    public function stop(): float
    {
        $duration = microtime(true) - $this->startTime;
        $memUsed = memory_get_usage(true) - $this->startMemory;

        Log::performance($this->name, $duration, [
            'memory_used_mb' => round($memUsed / (1024 * 1024), 2),
        ]);

        return $duration;
    }

    /**
     * Retorna duração sem parar/logar
     */
    public function elapsed(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Destrutor - para e loga automaticamente se não foi chamado stop()
     */
    public function __destruct()
    {
        // Não logar automaticamente para evitar logs duplicados
    }
}
