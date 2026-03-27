<?php

declare(strict_types=1);

namespace App\Services;

use Exception;

/**
 * Advanced Redis Cache Service V8.1
 * Intelligent caching with optimization and analytics
 */
class AdvancedRedisCacheService
{
    private $redis;
    private $isConnected = false;
    private $defaultTtl = 3600; // 1 hour
    private $keyPrefix = 'mlm_v81_';
    private $stats = [];
    
    public function __construct(array $config = []) {
        $this->initializeRedis($config);
        $this->initializeStats();
    }

    /**
     * Initialize Redis connection
     */
    private function initializeRedis(array $config): void {
        try {
            if (!extension_loaded('redis')) {
                throw new Exception('Redis extension not loaded');
            }

            $redisClass = 'Redis';
            $this->redis = new $redisClass();
            
            $host = $config['host'] ?? $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int) ($config['port'] ?? $_ENV['REDIS_PORT'] ?? 6379);
            $password = $config['password'] ?? $_ENV['REDIS_PASSWORD'] ?? null;
            if ($password === 'null' || $password === '') {
                $password = null;
            }
            $database = (int) ($config['database'] ?? $_ENV['REDIS_DB'] ?? 0);
            
            $connected = $this->redis->connect($host, $port, 2.5); // 2.5s timeout
            
            if (!$connected) {
                throw new Exception('Could not connect to Redis server');
            }
            
            if ($password) {
                if (!$this->redis->auth($password)) {
                    throw new Exception('Redis authentication failed');
                }
            }
            
            $this->redis->select($database);
            if (defined('Redis::OPT_SERIALIZER') && defined('Redis::SERIALIZER_JSON')) {
                $this->redis->setOption(constant('Redis::OPT_SERIALIZER'), constant('Redis::SERIALIZER_JSON'));
            }

            if (defined('Redis::OPT_PREFIX')) {
                $this->redis->setOption(constant('Redis::OPT_PREFIX'), $this->keyPrefix);
            }
            
            $this->isConnected = true;
            
        } catch (\Throwable $e) {
            $this->isConnected = false;
            log_error('Falha na conexão Redis', [
                'service' => 'AdvancedRedisCacheService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize cache statistics
     */
    private function initializeStats(): void {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'deletes' => 0,
            'errors' => 0
        ];
    }

    /**
     * Get cached data with intelligent fallback
     */
    public function get(string $key, ?callable $fallback = null, ?int $ttl = null): mixed {
        if (!$this->isConnected) {
            return $fallback ? $fallback() : null;
        }

        try {
            $value = $this->redis->get($key);
            
            if ($value !== false) {
                $this->stats['hits']++;
                return $value;
            }
            
            $this->stats['misses']++;
            
            // Execute fallback and cache result
            if ($fallback) {
                $result = $fallback();
                $this->set($key, $result, $ttl);
                return $result;
            }
            
            return null;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            log_warning('Erro no Redis GET', [
                'service' => 'AdvancedRedisCacheService',
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $fallback ? $fallback() : null;
        }
    }

    /**
     * Set cached data with optimization
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            $ttl = $ttl ?? $this->defaultTtl;
            
            // Optimize TTL based on data type and size
            $ttl = $this->optimizeTtl($key, $value, $ttl);
            
            $result = $this->redis->setex($key, $ttl, $value);
            
            if ($result) {
                $this->stats['sets']++;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            log_warning('Erro no Redis SET', [
                'service' => 'AdvancedRedisCacheService',
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Delete cached data
     */
    public function delete(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            $result = $this->redis->del($key) > 0;
            
            if ($result) {
                $this->stats['deletes']++;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            log_warning('Erro no Redis DELETE', [
                'service' => 'AdvancedRedisCacheService',
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if key exists
     */
    public function exists(string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Get remaining TTL for key
     */
    public function ttl(string $key): int {
        if (!$this->isConnected) {
            return -1;
        }

        try {
            return $this->redis->ttl($key);
        } catch (Exception $e) {
            return -1;
        }
    }

    /**
     * Set an expiration time (in seconds) for a key.
     */
    public function expire(string $key, int $ttlSeconds): bool
    {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return (bool) $this->redis->expire($key, $ttlSeconds);
        } catch (Exception $e) {
            $this->stats['errors']++;
            log_warning('Erro no Redis EXPIRE', [
                'service' => 'AdvancedRedisCacheService',
                'key' => $key,
                'ttl' => $ttlSeconds,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Increment counter
     */
    public function increment(string $key, int $value = 1): int {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            return $this->redis->incrBy($key, $value);
        } catch (Exception $e) {
            $this->stats['errors']++;
            return 0;
        }
    }

    /**
     * Decrement counter
     */
    public function decrement(string $key, int $value = 1): int {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            return $this->redis->decrBy($key, $value);
        } catch (Exception $e) {
            $this->stats['errors']++;
            return 0;
        }
    }

    /**
     * Add item to list (left push)
     */
    public function listPush(string $key, mixed $value, bool $trim = false, int $maxSize = 100): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            $this->redis->lPush($key, $value);
            
            if ($trim) {
                $this->redis->lTrim($key, 0, $maxSize - 1);
            }
            
            return true;
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Get list items
     */
    public function listGet(string $key, int $start = 0, int $end = -1): array {
        if (!$this->isConnected) {
            return [];
        }

        try {
            return $this->redis->lRange($key, $start, $end) ?: [];
        } catch (Exception $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    /**
     * Add item to set
     */
    public function setAdd(string $key, mixed ...$values): int {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            return $this->redis->sAdd($key, ...$values);
        } catch (Exception $e) {
            $this->stats['errors']++;
            return 0;
        }
    }

    /**
     * Get set members
     */
    public function setMembers(string $key): array {
        if (!$this->isConnected) {
            return [];
        }

        try {
            return $this->redis->sMembers($key) ?: [];
        } catch (Exception $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    /**
     * Check if value is in set
     */
    public function setContains(string $key, mixed $value): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->sIsMember($key, $value);
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Set hash field
     */
    public function hashSet(string $key, string $field, mixed $value): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->hSet($key, $field, $value) !== false;
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Get hash field
     */
    public function hashGet(string $key, string $field): mixed {
        if (!$this->isConnected) {
            return null;
        }

        try {
            $value = $this->redis->hGet($key, $field);
            return $value !== false ? $value : null;
        } catch (Exception $e) {
            $this->stats['errors']++;
            return null;
        }
    }

    /**
     * Get all hash fields
     */
    public function hashGetAll(string $key): array {
        if (!$this->isConnected) {
            return [];
        }

        try {
            return $this->redis->hGetAll($key) ?: [];
        } catch (Exception $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    /**
     * Cache multiple values (pipeline)
     */
    public function multiSet(array $keyValues, ?int $ttl = null): bool {
        if (!$this->isConnected || empty($keyValues)) {
            return false;
        }

        try {
            $pipe = $this->redis->pipeline();
            
            foreach ($keyValues as $key => $value) {
                $pipe->setex($key, $ttl ?? $this->defaultTtl, $value);
            }
            
            $results = $pipe->exec();
            $this->stats['sets'] += count($keyValues);
            
            return !in_array(false, $results ?: []);
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Get multiple values (pipeline)
     */
    public function multiGet(array $keys): array {
        if (!$this->isConnected || empty($keys)) {
            return [];
        }

        try {
            $values = $this->redis->mGet($keys);
            
            $result = [];
            foreach ($keys as $index => $key) {
                $value = $values[$index] ?? null;
                if ($value !== false) {
                    $result[$key] = $value;
                    $this->stats['hits']++;
                } else {
                    $this->stats['misses']++;
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            return [];
        }
    }

    /**
     * Tag-based cache invalidation
     */
    public function tag(string $tag, string $key): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->sAdd("tags:{$tag}", $key) !== false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Invalidate cache by tag
     */
    public function invalidateTag(string $tag): int {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            $keys = $this->redis->sMembers("tags:{$tag}");
            
            if (empty($keys)) {
                return 0;
            }
            
            // Delete all tagged keys
            $deleted = $this->redis->del($keys);
            
            // Clear the tag set
            $this->redis->del("tags:{$tag}");
            
            $this->stats['deletes'] += $deleted;
            
            return $deleted;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            return 0;
        }
    }

    /**
     * Clear all cache
     */
    public function flush(): bool {
        if (!$this->isConnected) {
            return false;
        }

        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            $this->stats['errors']++;
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? round(($this->stats['hits'] / $total) * 100, 2) : 0;
        
        $info = [];
        if ($this->isConnected) {
            try {
                $redisInfo = $this->redis->info();
                $info = [
                    'redis_version' => $redisInfo['redis_version'] ?? 'unknown',
                    'used_memory' => $redisInfo['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $redisInfo['connected_clients'] ?? 0,
                    'total_commands_processed' => $redisInfo['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $redisInfo['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $redisInfo['keyspace_misses'] ?? 0
                ];
            } catch (Exception $e) {
                // Ignore errors
            }
        }
        
        return [
            'connection' => $this->isConnected ? 'active' : 'inactive',
            'session_stats' => $this->stats,
            'hit_rate' => $hitRate . '%',
            'redis_info' => $info,
            'default_ttl' => $this->defaultTtl,
            'key_prefix' => $this->keyPrefix
        ];
    }

    /**
     * Get Redis server info
     */
    public function getServerInfo(): array {
        if (!$this->isConnected) {
            return ['status' => 'disconnected'];
        }

        try {
            $info = $this->redis->info();
            $keyCount = $this->redis->dbSize();
            
            return [
                'status' => 'connected',
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'memory_used' => $info['used_memory_human'] ?? '0B',
                'memory_peak' => $info['used_memory_peak_human'] ?? '0B',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'keys_count' => $keyCount,
                'hit_rate' => $this->calculateServerHitRate($info),
                'last_save' => $info['rdb_last_save_time'] ?? 0
            ];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Optimize TTL based on data characteristics
     */
    private function optimizeTtl(string $key, mixed $value, int $baseTtl): int {
        // User-specific data - shorter TTL
        if (str_contains($key, 'user:') || str_contains($key, 'session:')) {
            return min($baseTtl, 1800); // Max 30 minutes
        }
        
        // Product data - longer TTL
        if (str_contains($key, 'product:') || str_contains($key, 'item:')) {
            return $baseTtl * 2; // 2x longer
        }
        
        // Analytics data - medium TTL
        if (str_contains($key, 'analytics:') || str_contains($key, 'stats:')) {
            return $baseTtl / 2; // Half the time
        }
        
        // Large data - shorter TTL to manage memory
        if (is_string($value) && strlen($value) > 10000) {
            return $baseTtl / 2;
        }
        
        return $baseTtl;
    }

    /**
     * Calculate server hit rate
     */
    private function calculateServerHitRate(array $info): string {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        if ($total === 0) {
            return '0%';
        }
        
        return round(($hits / $total) * 100, 2) . '%';
    }

    /**
     * Health check
     */
    public function healthCheck(): array {
        if (!$this->isConnected) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis connection not available'
            ];
        }

        try {
            $start = microtime(true);
            $this->redis->ping();
            $latency = round((microtime(true) - $start) * 1000, 2);
            
            return [
                'status' => 'healthy',
                'latency_ms' => $latency,
                'connection' => 'active'
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Destructor - cleanup
     */
    public function __destruct() {
        if ($this->isConnected && $this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore cleanup errors
            }
        }
    }
    /**
     * Publish message to Redis Pub/Sub channel
     */
    public function publish(string $channel, string $message): int
    {
        if (!$this->isConnected) {
            return 0;
        }

        try {
            return $this->redis->publish($channel, $message);
        } catch (Exception $e) {
            log_warning('Falha no Redis publish', [
                'service' => 'AdvancedRedisCacheService',
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
