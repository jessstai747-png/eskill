<?php

/**
 * Helper Global para Cache
 * 
 * Funções auxiliares para facilitar o uso do sistema de cache.
 */

use App\Services\AdvancedCacheService;

if (!function_exists('cache')) {
    /**
     * Obter instância do cache ou valor
     * 
     * Uso:
     *   cache()->get('key')
     *   cache('key')
     *   cache('key', 'default')
     *   cache()->set('key', 'value', 3600)
     */
    function cache(?string $key = null, $default = null)
    {
        static $cache = null;
        
        if ($cache === null) {
            $cache = new AdvancedCacheService();
        }

        if ($key === null) {
            return $cache;
        }

        return $cache->get($key, $default);
    }
}

if (!function_exists('cache_remember')) {
    /**
     * Obter do cache ou executar callback
     */
    function cache_remember(string $key, callable $callback, ?int $ttl = 3600, array $tags = [])
    {
        return cache()->remember($key, $callback, $ttl, $tags);
    }
}

if (!function_exists('cache_forget')) {
    /**
     * Remover do cache
     */
    function cache_forget(string $key): bool
    {
        return cache()->delete($key);
    }
}

if (!function_exists('cache_flush')) {
    /**
     * Limpar todo o cache
     */
    function cache_flush(): int
    {
        return cache()->clear();
    }
}

if (!function_exists('cache_tags')) {
    /**
     * Invalidar cache por tags
     */
    function cache_tags(array $tags): int
    {
        return cache()->invalidateTags($tags);
    }
}
