<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * Sistema de Cache Inteligente Multi-Layer
 * Gerencia cache para APIs do ML e otimizações automáticas
 * 
 * Features:
 * - Cache em memória (APCu)
 * - Cache em arquivo
 * - Cache em banco de dados
 * - TTL inteligente baseado no tipo de dados
 * - Invalidação automática
 * - Compressão de dados grandes
 * - Warm-up automático de cache
 */
class CacheManagerService
{
    private $db;
    private $logger;
    private $basePath;
    private $config;
    
    // TTL padrão por tipo de dados (em segundos)
    private const TTL_CONFIG = [
        'ml_item_details' => 300,      // 5 min - dados de produto
        'ml_category_attrs' => 1800,   // 30 min - atributos de categoria
        'ml_search_results' => 600,    // 10 min - resultados de busca
        'ml_user_profile' => 3600,     // 1 hora - perfil do usuário
        'pricing_analysis' => 900,     // 15 min - análise de preços
        'competitor_data' => 1800,     // 30 min - dados de concorrentes
        'seo_analysis' => 600,         // 10 min - análise SEO
        'catalog_metrics' => 300,      // 5 min - métricas do catálogo
        'system_config' => 86400,      // 24 horas - configurações do sistema
        'feature_flags' => 60,         // 1 min - feature flags (rápida mudança)
        'public_page' => 3600,         // 1 hora - páginas públicas (Full Cache)
        'sitemap' => 3600,             // 1 hora - sitemap xml
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new LoggingService();
        $this->basePath = __DIR__ . '/../../storage/cache/';
        
        $this->config = [
            'enable_memory' => extension_loaded('apcu') && apcu_enabled(),
            'enable_file' => true,
            'enable_database' => true,
            'compression_threshold' => 1024, // 1KB
            'max_memory_items' => 1000,
            'cleanup_probability' => 2, // 2% chance de executar limpeza
        ];
        
        $this->ensureCacheDirectory();
        $this->initializeCacheTable();
        
        // Limpeza probabilística
        if (rand(1, 100) <= $this->config['cleanup_probability']) {
            $this->cleanupExpiredCache();
        }
    }
    
    /**
     * Recupera dados do cache com fallback automático
     */
    public function get(string $key, string $type = 'default'): ?array
    {
        try {
            $cacheKey = $this->generateCacheKey($key, $type);
            
            // 1. Tenta memória primeiro (mais rápido)
            if ($this->config['enable_memory']) {
                $data = apcu_fetch($cacheKey, $success);
                if ($success) {
                    $this->logger->debug('CACHE_HIT_MEMORY', "Cache hit (memory): {$key}", [
                        'type' => $type,
                        'key' => $key
                    ]);
                    return json_decode($data, true);
                }
            }
            
            // 2. Tenta arquivo
            if ($this->config['enable_file']) {
                $data = $this->getFromFile($cacheKey);
                if ($data !== null) {
                    // Volta para memória para próximas consultas
                    if ($this->config['enable_memory']) {
                        apcu_store($cacheKey, json_encode($data), $this->getTTL($type));
                    }
                    
                    $this->logger->debug('CACHE_HIT_FILE', "Cache hit (file): {$key}", [
                        'type' => $type,
                        'key' => $key
                    ]);
                    return $data;
                }
            }
            
            // 3. Tenta banco de dados
            if ($this->config['enable_database']) {
                $data = $this->getFromDatabase($cacheKey);
                if ($data !== null) {
                    // Volta para camadas superiores
                    if ($this->config['enable_file']) {
                        $this->setToFile($cacheKey, $data, $type);
                    }
                    if ($this->config['enable_memory']) {
                        apcu_store($cacheKey, json_encode($data), $this->getTTL($type));
                    }
                    
                    $this->logger->debug('CACHE_HIT_DB', "Cache hit (database): {$key}", [
                        'type' => $type,
                        'key' => $key
                    ]);
                    return $data;
                }
            }
            
            $this->logger->debug('CACHE_MISS', "Cache miss: {$key}", [
                'type' => $type,
                'key' => $key
            ]);
            
            return null;
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_ERROR', "Erro ao recuperar cache: {$e->getMessage()}", [
                'key' => $key,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Armazena dados no cache em todas as camadas
     */
    public function set(string $key, array $data, string $type = 'default'): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($key, $type);
            $ttl = $this->getTTL($type);
            $jsonData = json_encode($data);
            $compressed = $this->shouldCompress($jsonData);
            
            $success = true;
            
            // 1. Memória
            if ($this->config['enable_memory']) {
                $result = apcu_store($cacheKey, $jsonData, $ttl);
                if (!$result) {
                    $this->logger->warning('CACHE_MEMORY_FAIL', "Falha ao armazenar em memória: {$key}");
                    $success = false;
                }
            }
            
            // 2. Arquivo
            if ($this->config['enable_file']) {
                $result = $this->setToFile($cacheKey, $data, $type, $compressed);
                if (!$result) {
                    $success = false;
                }
            }
            
            // 3. Banco de dados
            if ($this->config['enable_database']) {
                $result = $this->setToDatabase($cacheKey, $data, $type, $ttl, $compressed);
                if (!$result) {
                    $success = false;
                }
            }
            
            if ($success) {
                $this->logger->debug('CACHE_SET', "Cache armazenado: {$key}", [
                    'type' => $type,
                    'ttl' => $ttl,
                    'size' => strlen($jsonData),
                    'compressed' => $compressed
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_SET_ERROR', "Erro ao armazenar cache: {$e->getMessage()}", [
                'key' => $key,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Remove item do cache em todas as camadas
     */
    public function delete(string $key, string $type = 'default'): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($key, $type);
            
            // Remove de todas as camadas
            if ($this->config['enable_memory']) {
                apcu_delete($cacheKey);
            }
            
            if ($this->config['enable_file']) {
                $filePath = $this->getFilePath($cacheKey);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            if ($this->config['enable_database']) {
                $stmt = $this->db->prepare("DELETE FROM cache_entries WHERE cache_key = ?");
                $stmt->execute([$cacheKey]);
            }
            
            $this->logger->debug('CACHE_DELETE', "Cache removido: {$key}", [
                'type' => $type
            ]);
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_DELETE_ERROR', "Erro ao remover cache: {$e->getMessage()}", [
                'key' => $key,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Limpa cache por tipo ou padrão
     */
    public function clear(string $pattern = '*'): int
    {
        $cleared = 0;
        
        try {
            // Limpa memória (por padrão não suporta patterns, então limpa tudo se necessário)
            if ($this->config['enable_memory'] && $pattern === '*') {
                apcu_clear_cache();
                $cleared += 100; // estimativa
            }
            
            // Limpa arquivos
            if ($this->config['enable_file']) {
                $files = glob($this->basePath . '*.cache');
                foreach ($files as $file) {
                    if ($pattern === '*' || strpos(basename($file), $pattern) !== false) {
                        unlink($file);
                        $cleared++;
                    }
                }
            }
            
            // Limpa banco
            if ($this->config['enable_database']) {
                if ($pattern === '*') {
                    $stmt = $this->db->prepare("DELETE FROM cache_entries");
                } else {
                    $stmt = $this->db->prepare("DELETE FROM cache_entries WHERE cache_key LIKE ?");
                    $stmt->execute(["%{$pattern}%"]);
                }
                $cleared += $stmt->rowCount();
            }
            
            $this->logger->info('CACHE_CLEAR', "Cache limpo com padrão: {$pattern}", [
                'items_cleared' => $cleared
            ]);
            
            return $cleared;
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_CLEAR_ERROR', "Erro ao limpar cache: {$e->getMessage()}", [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return $cleared;
        }
    }
    
    /**
     * Estatísticas do cache
     */
    public function getStats(): array
    {
        $stats = [
            'memory' => ['enabled' => $this->config['enable_memory'], 'items' => 0, 'size' => 0],
            'file' => ['enabled' => $this->config['enable_file'], 'items' => 0, 'size' => 0],
            'database' => ['enabled' => $this->config['enable_database'], 'items' => 0, 'size' => 0],
        ];
        
        try {
            // Stats de memória
            if ($this->config['enable_memory']) {
                $info = apcu_cache_info();
                $stats['memory']['items'] = $info['num_entries'] ?? 0;
                $stats['memory']['size'] = $info['mem_size'] ?? 0;
            }
            
            // Stats de arquivo
            if ($this->config['enable_file']) {
                $files = glob($this->basePath . '*.cache');
                $stats['file']['items'] = count($files);
                $stats['file']['size'] = array_sum(array_map('filesize', $files));
            }
            
            // Stats de banco
            if ($this->config['enable_database']) {
                $stmt = $this->db->query("SELECT COUNT(*) as count, SUM(LENGTH(data)) as size FROM cache_entries WHERE expires_at > NOW()");
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $stats['database']['items'] = $result['count'] ?? 0;
                $stats['database']['size'] = $result['size'] ?? 0;
            }
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_STATS_ERROR', "Erro ao obter estatísticas: {$e->getMessage()}");
        }
        
        return $stats;
    }
    
    /**
     * Warm-up do cache com dados críticos
     */
    public function warmUp(int $accountId): bool
    {
        try {
            $this->logger->info('CACHE_WARMUP', "Iniciando warm-up do cache", [
                'account_id' => $accountId
            ]);
            
            // Lista de dados para pré-carregamento
            $warmupTasks = [
                'feature_flags' => function() {
                    $flags = new FeatureFlagService();
                    return $flags->getAllFlags();
                },
                'system_config' => function() {
                    return [
                        'app_name' => $_ENV['APP_NAME'] ?? 'ML Manager',
                        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Sao_Paulo',
                        'debug' => $_ENV['APP_DEBUG'] === 'true'
                    ];
                }
            ];
            
            $warmedUp = 0;
            foreach ($warmupTasks as $type => $callback) {
                try {
                    $data = $callback();
                    if ($this->set("warmup_{$type}_{$accountId}", $data, $type)) {
                        $warmedUp++;
                    }
                } catch (Exception $e) {
                    $this->logger->warning('CACHE_WARMUP_ITEM_ERROR', "Erro no warm-up de {$type}: {$e->getMessage()}");
                }
            }
            
            $this->logger->info('CACHE_WARMUP_COMPLETE', "Warm-up concluído", [
                'items_warmed' => $warmedUp,
                'total_tasks' => count($warmupTasks)
            ]);
            
            return $warmedUp > 0;
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_WARMUP_ERROR', "Erro no warm-up: {$e->getMessage()}");
            return false;
        }
    }
    
    // ========== MÉTODOS PRIVADOS ==========
    
    private function generateCacheKey(string $key, string $type): string
    {
        return "mlmanager_{$type}_" . md5($key);
    }
    
    private function getTTL(string $type): int
    {
        return self::TTL_CONFIG[$type] ?? 1800; // 30 min padrão
    }
    
    private function shouldCompress(string $data): bool
    {
        return strlen($data) > $this->config['compression_threshold'];
    }
    
    private function getFromFile(string $cacheKey): ?array
    {
        $filePath = $this->getFilePath($cacheKey);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        $data = json_decode($content, true);
        
        // Verifica expiração
        if (isset($data['expires_at']) && time() > $data['expires_at']) {
            unlink($filePath);
            return null;
        }
        
        return $data['data'] ?? null;
    }
    
    private function setToFile(string $cacheKey, array $data, string $type, bool $compressed = false): bool
    {
        $filePath = $this->getFilePath($cacheKey);
        
        $cacheData = [
            'data' => $data,
            'type' => $type,
            'created_at' => time(),
            'expires_at' => time() + $this->getTTL($type),
            'compressed' => $compressed
        ];
        
        if ($compressed) {
            $content = gzcompress(json_encode($cacheData));
        } else {
            $content = json_encode($cacheData);
        }
        
        return file_put_contents($filePath, $content, LOCK_EX) !== false;
    }
    
    private function getFromDatabase(string $cacheKey): ?array
    {
        $stmt = $this->db->prepare("SELECT data, compressed FROM cache_entries WHERE cache_key = ? AND expires_at > NOW()");
        $stmt->execute([$cacheKey]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $data = $result['data'];
        if ($result['compressed']) {
            $data = gzuncompress($data);
        }
        
        return json_decode($data, true);
    }
    
    private function setToDatabase(string $cacheKey, array $data, string $type, int $ttl, bool $compressed): bool
    {
        $jsonData = json_encode($data);
        if ($compressed) {
            $jsonData = gzcompress($jsonData);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO cache_entries (cache_key, data, type, compressed, expires_at, created_at) 
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())
            ON DUPLICATE KEY UPDATE 
            data = VALUES(data), 
            compressed = VALUES(compressed),
            expires_at = VALUES(expires_at)
        ");
        
        return $stmt->execute([$cacheKey, $jsonData, $type, $compressed ? 1 : 0, $ttl]);
    }
    
    private function getFilePath(string $cacheKey): string
    {
        return $this->basePath . $cacheKey . '.cache';
    }
    
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }
    
    private function initializeCacheTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS cache_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cache_key VARCHAR(255) UNIQUE NOT NULL,
                data LONGTEXT NOT NULL,
                type VARCHAR(50) NOT NULL,
                compressed BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                INDEX idx_cache_key_expires (cache_key, expires_at),
                INDEX idx_type_expires (type, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    private function cleanupExpiredCache(): void
    {
        try {
            // Limpa banco de dados
            if ($this->config['enable_database']) {
                $stmt = $this->db->prepare("DELETE FROM cache_entries WHERE expires_at < NOW()");
                $stmt->execute();
                $deletedDb = $stmt->rowCount();
                
                if ($deletedDb > 0) {
                    $this->logger->debug('CACHE_CLEANUP_DB', "Removidos {$deletedDb} itens expirados do banco");
                }
            }
            
            // Limpa arquivos expirados
            if ($this->config['enable_file']) {
                $deletedFiles = 0;
                $files = glob($this->basePath . '*.cache');
                
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content !== false) {
                        $data = json_decode($content, true);
                        if (isset($data['expires_at']) && time() > $data['expires_at']) {
                            unlink($file);
                            $deletedFiles++;
                        }
                    }
                }
                
                if ($deletedFiles > 0) {
                    $this->logger->debug('CACHE_CLEANUP_FILE', "Removidos {$deletedFiles} arquivos expirados");
                }
            }
            
        } catch (Exception $e) {
            $this->logger->error('CACHE_CLEANUP_ERROR', "Erro na limpeza automática: {$e->getMessage()}");
        }
    }
}