<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * Queue Service based on Redis
 * Simple FIFO queue implementation
 */
class QueueService
{
    private $redis;
    private $queueName = 'default_queue';
    private bool $connected = false;
    private int $database = 0;

    public function __construct()
    {
        // Reuse logic from AdvancedRedisCacheService or simple connection
        // For simplicity reusing Env vars directly
        if (!class_exists('Redis')) {
            throw new Exception('Extensão Redis não está disponível no ambiente');
        }

        $redisClass = 'Redis';
        $this->redis = new $redisClass();
        $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
        $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
        $this->database = (int)($_ENV['REDIS_DB'] ?? 0);

        try {
            $this->redis->connect($host, $port);
            $redisPass = $_ENV['REDIS_PASSWORD'] ?? '';
            if (!empty($redisPass) && $redisPass !== 'null') {
                $this->redis->auth($redisPass);
            }
            if (!$this->redis->select($this->database)) {
                throw new Exception('Falha ao selecionar Redis DB ' . $this->database);
            }
            $this->connected = true;
        } catch (Exception $e) {
            $this->connected = false;
            log_error('Erro de conexão no QueueService', [
                'service' => 'QueueService',
                'error' => $e->getMessage(),
                'redis_db' => $this->database,
            ]);
        }
    }

    /**
     * Push job to queue
     */
    public function push(string $jobType, array $payload, string $queue = 'default'): string
    {
        $this->ensureConnected();

        $id = uniqid('job_');
        $job = [
            'id' => $id,
            'type' => $jobType,
            'payload' => $payload,
            'created_at' => time(),
            'attempts' => 0
        ];

        $this->redis->rPush('queue:' . $queue, json_encode($job));
        return $id;
    }

    /**
     * Pop job from queue (Blocking)
     * @param int $timeout
     */
    public function pop(string $queue = 'default', int $timeout = 10): ?array
    {
        $this->ensureConnected();

        // blPop returns [key, value]
        $result = $this->redis->blPop(['queue:' . $queue], $timeout);

        if ($result && isset($result[1])) {
            return json_decode($result[1], true);
        }

        return null;
    }

    /**
     * Indica se a conexão Redis está ativa.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Garante que existe conexão antes de operar a fila.
     */
    private function ensureConnected(): void
    {
        if (!$this->connected) {
            // In PHPUnit integration runs without Redis, signal test skip instead of error.
            // PHPUNIT_DB_AVAILABLE is only defined in tests/bootstrap.php (integration path),
            // so this guard is completely inert in production.
            if (
                defined('PHPUNIT_DB_AVAILABLE')
                && !PHPUNIT_DB_AVAILABLE
                && class_exists('\PHPUnit\Framework\SkippedTestError')
            ) {
                throw new \PHPUnit\Framework\SkippedTestError(
                    'Redis unavailable: ' . ($GLOBALS['phpunit_db_error'] ?? 'Redis connection refused')
                );
            }
            throw new Exception('Redis indisponível no QueueService');
        }
    }
}
