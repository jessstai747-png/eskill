<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Bridge de compatibilidade para usos legados de cache "Redis".
 * Encapsula CacheService e expõe a interface esperada pelos serviços atuais.
 */
class AdvancedRedisCacheService
{
    private const TAG_INDEX_TTL = 604800; // 7 dias
    private const INCREMENT_DEFAULT_TTL = 86400; // 24h

    private CacheService $cache;

    public function __construct(?CacheService $cache = null)
    {
        $this->cache = $cache ?? new CacheService();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->cache->get($key);
        return $value === null ? $default : $value;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    public function increment(string $key, int $amount = 1): int
    {
        $current = (int) ($this->cache->get($key) ?? 0);
        $next = $current + $amount;
        $this->cache->set($key, $next, self::INCREMENT_DEFAULT_TTL);
        return $next;
    }

    public function expire(string $key, int $ttl): bool
    {
        if (!$this->cache->has($key)) {
            return false;
        }

        $value = $this->cache->get($key);
        return $this->cache->set($key, $value, $ttl);
    }

    public function tag(string $tag, string $key): void
    {
        $indexKey = $this->tagIndexKey($tag);
        $keys = $this->cache->get($indexKey);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
        }

        $this->cache->set($indexKey, $keys, self::TAG_INDEX_TTL);
    }

    public function invalidateTag(string $tag): int
    {
        $indexKey = $this->tagIndexKey($tag);
        $keys = $this->cache->get($indexKey);
        if (!is_array($keys)) {
            return 0;
        }

        $deleted = 0;
        foreach ($keys as $key) {
            if (is_string($key) && $this->cache->delete($key)) {
                $deleted++;
            }
        }

        $this->cache->delete($indexKey);
        return $deleted;
    }

    public function ttl(string $key): int
    {
        // Driver de arquivo não expõe TTL restante de forma eficiente.
        // Mantemos compatibilidade de assinatura sem quebrar headers.
        // Se a chave não existir, segue sem TTL.
        return $this->cache->has($key) ? 0 : -1;
    }

    public function getStats(): array
    {
        return $this->cache->getStats();
    }

    /**
     * Verificação simples de saúde do cache configurado.
     */
    public function healthCheck(): array
    {
        try {
            $probeKey = 'health:cache:' . str_replace('.', '', uniqid('', true));
            $probeValue = ['ok' => true, 'ts' => time()];

            $saved = $this->cache->set($probeKey, $probeValue, 10);
            if (!$saved) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache não aceitou escrita',
                ];
            }

            $loaded = $this->cache->get($probeKey);
            $this->cache->delete($probeKey);

            if (!is_array($loaded) || ($loaded['ok'] ?? false) !== true) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache não retornou valor esperado',
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Cache operacional',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function tagIndexKey(string $tag): string
    {
        return 'cache:tag-index:' . $tag;
    }
}
