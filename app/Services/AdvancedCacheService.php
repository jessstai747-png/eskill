<?php

namespace App\Services;

/**
 * Sistema de Cache Avançado
 * 
 * Cache em memória e arquivo com suporte a:
 * - TTL (Time To Live)
 * - Tags para invalidação em grupo
 * - Múltiplos drivers (file, memory)
 * - Compressão automática
 * - Estatísticas de hit/miss
 */
class AdvancedCacheService
{
    private string $cacheDir;
    private array $memoryCache = [];
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    private bool $compressionEnabled;
    private string $driver;

    /**
     * Construtor flexível compatível com diferentes assinaturas.
     *
     * Assinatura preferida:
     *   __construct(string $driver = 'file', array $options = [], bool $compression = true)
     *   - $options['path'] define diretório do cache em disco
     *
     * Assinatura legada (mantida para compatibilidade):
     *   __construct(?string $cacheDir = null, string $driver = 'file', bool $compression = true)
     *   - Detectamos automaticamente quando o primeiro parâmetro é um driver (file|memory)
     */
    public function __construct(
        string $driver = 'file',
        array $options = [],
        bool $compression = true
    ) {
        // Compatibilidade: se $driver parecer um caminho, assumir que é $cacheDir legado
        if ($driver !== 'file' && $driver !== 'memory') {
            // Tratar como caminho legado
            $legacyCacheDir = $driver;
            $driver = 'file';
            $options['path'] = $options['path'] ?? $legacyCacheDir;
        }

        $this->driver = $driver;
        $this->compressionEnabled = $compression && function_exists('gzencode');

        $defaultPath = __DIR__ . '/../../storage/cache';
        $this->cacheDir = $options['path'] ?? $defaultPath;

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Obter valor do cache
     */
    public function get(string $key, $default = null)
    {
        // Tentar memory cache primeiro (validando expiração)
        if (isset($this->memoryCache[$key])) {
            $data = $this->memoryCache[$key];

            if ($this->isExpired($data)) {
                // Expirado em memória, remover e continuar busca em disco
                unset($this->memoryCache[$key]);
            } else {
                $this->stats['hits']++;
                log_debug('Cache hit (memory)', ['key' => $key]);
                return $data['value'];
            }
        }

        // Tentar file cache
        if ($this->driver === 'file') {
            $filePath = $this->getFilePath($key);
            
            if (file_exists($filePath)) {
                $data = $this->readCacheFile($filePath);
                
                if ($data && !$this->isExpired($data)) {
                    $this->memoryCache[$key] = $data;
                    $this->stats['hits']++;
                    log_debug('Cache hit (file)', ['key' => $key]);
                    return $data['value'];
                }
                
                // Expirado, deletar
                if ($data) {
                    unlink($filePath);
                }
            }
        }

        $this->stats['misses']++;
        log_debug('Cache miss', ['key' => $key]);
        return $default;
    }

    /**
     * Armazenar valor no cache
     */
    public function set(string $key, $value, ?int $ttl = 3600, array $tags = []): bool
    {
        $data = [
            'value' => $value,
            'created_at' => time(),
            'expires_at' => $ttl ? time() + $ttl : null,
            'tags' => $tags
        ];

        // Salvar em memory cache
        $this->memoryCache[$key] = $data;

        // Salvar em file cache
        if ($this->driver === 'file') {
            $filePath = $this->getFilePath($key);
            $dir = dirname($filePath);
            
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $success = $this->writeCacheFile($filePath, $data);
            
            if ($success) {
                $this->stats['writes']++;
                $this->indexTags($key, $tags);
                log_debug('Cache set', [
                    'key' => $key,
                    'ttl' => $ttl,
                    'tags' => $tags,
                    'size' => strlen(serialize($value))
                ]);
            }
            
            return $success;
        }

        $this->stats['writes']++;
        return true;
    }

    /**
     * Verificar se chave existe
     */
    public function has(string $key): bool
    {
        // Verificar em memória sem depender do valor (null é válido)
        if (isset($this->memoryCache[$key])) {
            $data = $this->memoryCache[$key];
            if (!$this->isExpired($data)) {
                return true;
            }
            // Expirado em memória
            unset($this->memoryCache[$key]);
        }

        // Verificar em disco
        if ($this->driver === 'file') {
            $filePath = $this->getFilePath($key);
            if (file_exists($filePath)) {
                $data = $this->readCacheFile($filePath);
                if ($data && !$this->isExpired($data)) {
                    return true;
                }
                // Expirado em disco, remover
                if ($data) {
                    @unlink($filePath);
                }
            }
        }

        return false;
    }

    /**
     * Deletar chave
     */
    public function delete(string $key): bool
    {
        // Remover de memory cache
        unset($this->memoryCache[$key]);

        // Remover de file cache
        if ($this->driver === 'file') {
            $filePath = $this->getFilePath($key);
            
            if (file_exists($filePath)) {
                unlink($filePath);
                $this->stats['deletes']++;
                log_debug('Cache delete', ['key' => $key]);
                return true;
            }
        }

        return false;
    }

    /**
     * Limpar todo o cache
     */
    public function clear(): int
    {
        $this->memoryCache = [];
        $deleted = 0;

        if ($this->driver === 'file') {
            $deleted = $this->clearDirectory($this->cacheDir);
            log_info('Cache cleared', ['files_deleted' => $deleted]);
        }

        return $deleted;
    }

    /**
     * Invalidar cache por tags
     */
    public function invalidateTags(array $tags): int
    {
        $deleted = 0;
        
        foreach ($tags as $tag) {
            $keys = $this->getKeysByTag($tag);
            
            foreach ($keys as $key) {
                if ($this->delete($key)) {
                    $deleted++;
                }
            }
        }

        log_info('Cache invalidated by tags', [
            'tags' => $tags,
            'keys_deleted' => $deleted
        ]);

        return $deleted;
    }

    /**
     * Obter ou criar cache (callback)
     */
    public function remember(string $key, $param2, $param3 = null, array $tags = [])
    {
        // Suportar ambas assinaturas:
        // 1) remember($key, callable $callback, ?int $ttl = 3600, array $tags = [])
        // 2) remember($key, ?int $ttl, callable $callback, array $tags = [])

        $callback = null;
        $ttl = 3600;

        if (is_callable($param2)) {
            // Assinatura 1
            $callback = $param2;
            $ttl = is_int($param3) ? $param3 : 3600;
        } else {
            // Assinatura 2
            $ttl = is_int($param2) ? $param2 : 3600;
            $callback = $param3;
        }

        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('AdvancedCacheService::remember requer um callback válido.');
        }

        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl, $tags);

        return $value;
    }

    /**
     * Limpar cache expirado
     */
    public function clearExpired(): int
    {
        $deleted = 0;

        if ($this->driver !== 'file') {
            return $deleted;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $data = $this->readCacheFile($file->getPathname());
                
                if ($data && $this->isExpired($data)) {
                    unlink($file->getPathname());
                    $deleted++;
                }
            }
        }

        log_info('Expired cache cleared', ['files_deleted' => $deleted]);
        return $deleted;
    }

    /**
     * Obter estatísticas do cache
     */
    public function getStats(): array
    {
        $hitRate = $this->stats['hits'] + $this->stats['misses'] > 0
            ? ($this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses'])) * 100
            : 0;

        return array_merge($this->stats, [
            'hit_rate' => round($hitRate, 2) . '%',
            'memory_items' => count($this->memoryCache),
            'file_items' => $this->driver === 'file' ? $this->countCacheFiles() : 0,
            'total_size' => $this->driver === 'file' ? $this->getCacheSize() : 0
        ]);
    }

    /**
     * Helpers privados
     */

    private function getFilePath(string $key): string
    {
        $hash = md5($key);
        $dir = substr($hash, 0, 2); // Dois primeiros caracteres para distribuição
        return $this->cacheDir . '/' . $dir . '/' . $hash . '.cache';
    }

    private function readCacheFile(string $filePath): ?array
    {
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            return null;
        }

        // Descomprimir se necessário
        if ($this->compressionEnabled && substr($content, 0, 2) === "\x1f\x8b") {
            $content = gzdecode($content);
        }

        // Security: prevent object injection via allowed_classes: false (C2)
        return unserialize($content, ['allowed_classes' => false]);
    }

    private function writeCacheFile(string $filePath, array $data): bool
    {
        $content = serialize($data);

        // Comprimir se habilitado
        if ($this->compressionEnabled) {
            $content = gzencode($content, 6);
        }

        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }

    private function isExpired(array $data): bool
    {
        if ($data['expires_at'] === null) {
            return false;
        }

        return time() > $data['expires_at'];
    }

    private function indexTags(string $key, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $indexFile = $this->cacheDir . '/tags.index';
        $index = [];

        if (file_exists($indexFile)) {
            $index = unserialize(file_get_contents($indexFile), ['allowed_classes' => false]) ?: [];
        }

        foreach ($tags as $tag) {
            if (!isset($index[$tag])) {
                $index[$tag] = [];
            }
            $index[$tag][] = $key;
            $index[$tag] = array_unique($index[$tag]);
        }

        file_put_contents($indexFile, serialize($index), LOCK_EX);
    }

    private function getKeysByTag(string $tag): array
    {
        $indexFile = $this->cacheDir . '/tags.index';

        if (!file_exists($indexFile)) {
            return [];
        }

        $index = unserialize(file_get_contents($indexFile), ['allowed_classes' => false]) ?: [];
        return $index[$tag] ?? [];
    }

    private function clearDirectory(string $dir): int
    {
        $deleted = 0;

        if (!is_dir($dir)) {
            return $deleted;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }

    private function countCacheFiles(): int
    {
        $count = 0;

        if (!is_dir($this->cacheDir)) {
            return $count;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'cache') {
                $count++;
            }
        }

        return $count;
    }

    private function getCacheSize(): int
    {
        $size = 0;

        if (!is_dir($this->cacheDir)) {
            return $size;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->cacheDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }
}
