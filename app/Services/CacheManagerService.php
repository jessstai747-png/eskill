<?php

declare(strict_types=1);

namespace App\Services;

class CacheManagerService
{
    private ?\Redis $redis = null;
    private bool $redisConnected = false;
    private string $prefix;

    public function __construct(string $prefix = 'eskill')
    {
        $this->prefix = $prefix;
        $this->connect();
    }

    public function get(string $key, string $namespace = ''): mixed
    {
        $fullKey = $this->buildKey($key, $namespace);

        if ($this->redisConnected && $this->redis !== null) {
            try {
                $raw = $this->redis->get($fullKey);
                if ($raw === false) {
                    return null;
                }

                $decoded = json_decode((string)$raw, true);
                return ($decoded !== null && json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
            } catch (\Throwable $e) {
                return null;
            }
        }

        return null;
    }

    public function set(string $key, mixed $value, string $namespace = '', int $ttl = 3600): bool
    {
        $fullKey = $this->buildKey($key, $namespace);

        if ($this->redisConnected && $this->redis !== null) {
            try {
                $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if ($ttl > 0) {
                    return (bool)$this->redis->setex($fullKey, $ttl, $encoded);
                }

                return (bool)$this->redis->set($fullKey, $encoded);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    public function has(string $key, string $namespace = ''): bool
    {
        return $this->get($key, $namespace) !== null;
    }

    public function delete(string $key, string $namespace = ''): bool
    {
        $fullKey = $this->buildKey($key, $namespace);

        if ($this->redisConnected && $this->redis !== null) {
            try {
                return (bool)$this->redis->del($fullKey);
            } catch (\Throwable $e) {
                return false;
            }
        }

        return false;
    }

    private function buildKey(string $key, string $namespace): string
    {
        $parts = [$this->prefix];
        if ($namespace !== '') {
            $parts[] = $namespace;
        }
        $parts[] = $key;
        return implode(':', $parts);
    }

    private function connect(): void
    {
        if (!extension_loaded('redis')) {
            return;
        }

        try {
            $this->redis = new \Redis();
            $host = (string)($_ENV['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: '127.0.0.1');
            $port = (int)($_ENV['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);
            $timeout = 2.0;

            $connected = @$this->redis->connect($host, $port, $timeout);
            if (!$connected) {
                $this->redis = null;
                return;
            }

            $password = (string)($_ENV['REDIS_PASSWORD'] ?? getenv('REDIS_PASSWORD') ?: '');
            if ($password !== '') {
                $this->redis->auth($password);
            }

            $db = (int)($_ENV['REDIS_DB'] ?? getenv('REDIS_DB') ?: 0);
            if ($db !== 0) {
                $this->redis->select($db);
            }

            $this->redisConnected = true;
        } catch (\Throwable $e) {
            $this->redis = null;
            $this->redisConnected = false;
        }
    }
}
