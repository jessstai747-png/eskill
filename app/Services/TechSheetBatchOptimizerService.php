<?php

namespace App\Services;

use App\Database;
use App\Services\AdvancedCacheService;
use PDO;

/**
 * Tech Sheet Batch Performance Optimizer
 * 
 * Otimiza operações em lote para melhor performance
 * - Batch processing inteligente
 * - Cache de categorias
 * - Query optimization
 * - Parallel processing
 */
class TechSheetBatchOptimizerService
{
    private PDO $db;
    private int $accountId;
    private array $config;
    private array $categoryCache = [];
    private AdvancedCacheService $cache;
    private string $categoryCacheTag;
    private string $categoryCacheIndexKey;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->cache = new AdvancedCacheService();
        $this->categoryCacheTag = 'tech_sheet_category_cache:' . $this->accountId;
        $this->categoryCacheIndexKey = 'tech_sheet_category_cache_index:' . $this->accountId;
        
        $appConfig = \App\Core\Config::getInstance()->all();
        $this->config = [
            'batch_size' => 50,           // Tamanho do lote
            'parallel_workers' => 3,      // Workers paralelos
            'cache_ttl' => 3600,          // 1 hora
            'max_retries' => 3,           // Tentativas máximas
        ];
    }

    /**
     * Processa itens em lotes otimizados
     * 
     * @param array $itemIds
     * @param callable $processor
     * @param array $options
     * @return array
     */
    public function processBatch(array $itemIds, callable $processor, array $options = []): array
    {
        $batchSize = $options['batch_size'] ?? $this->config['batch_size'];
        $chunks = array_chunk($itemIds, $batchSize);
        
        $results = [
            'total' => count($itemIds),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'batches' => count($chunks),
            'details' => [],
            'errors' => [],
        ];

        foreach ($chunks as $batchNum => $chunk) {
            $batchStart = microtime(true);
            
            try {
                // Pre-load dados necessários
                $itemsData = $this->preloadItemsData($chunk);
                
                // Processar lote
                foreach ($chunk as $itemId) {
                    try {
                        $itemData = $itemsData[$itemId] ?? null;
                        
                        if (!$itemData) {
                            $results['failed']++;
                            continue;
                        }
                        
                        $result = $processor($itemId, $itemData);
                        
                        if ($result) {
                            $results['success']++;
                        } else {
                            $results['failed']++;
                        }
                        
                        $results['processed']++;
                        
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'item_id' => $itemId,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
                
                $batchDuration = microtime(true) - $batchStart;
                
                $results['details'][] = [
                    'batch' => $batchNum + 1,
                    'items' => count($chunk),
                    'duration' => round($batchDuration, 3),
                ];
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'batch' => $batchNum + 1,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Pre-carrega dados de itens em uma query
     */
    private function preloadItemsData(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        
        $stmt = $this->db->prepare("
            SELECT 
                i.ml_item_id,
                i.title,
                i.category_id,
                i.status,
                s.completeness_percent,
                s.missing_required,
                s.missing_filter,
                s.last_analyzed_at
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = ? 
              AND i.ml_item_id IN ($placeholders)
        ");
        
        $params = array_merge([$this->accountId], $itemIds);
        $stmt->execute($params);
        
        $items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[$row['ml_item_id']] = $row;
        }
        
        return $items;
    }

    /**
     * Gera sugestões em lote com otimizações
     * 
     * @param array $itemIds
     * @param array $options
     * @return array
     */
    public function generateBatchSuggestions(array $itemIds, array $options = []): array
    {
        $techSheetService = new TechSheetService($this->accountId);
        
        // Pre-carregar dados de categorias
        $this->preloadCategoryCache($itemIds);
        
        $processor = function($itemId, $itemData) use ($techSheetService, $options) {
            try {
                $result = $techSheetService->generateSuggestions($itemId, [
                    'use_title' => $options['use_title'] ?? true,
                    'use_benchmark' => $options['use_benchmark'] ?? false,
                    'use_ai' => $options['use_ai'] ?? false,
                    'min_confidence' => $options['min_confidence'] ?? 60,
                ]);
                
                return ($result['success'] ?? false) === true;
                
            } catch (\Exception $e) {
                return false;
            }
        };
        
        return $this->processBatch($itemIds, $processor, $options);
    }

    /**
     * Pre-carrega cache de categorias
     */
    private function preloadCategoryCache(array $itemIds): void
    {
        // Buscar categorias únicas dos itens
        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        
        $stmt = $this->db->prepare("
            SELECT DISTINCT category_id 
            FROM items 
            WHERE account_id = ? 
              AND ml_item_id IN ($placeholders)
              AND category_id IS NOT NULL
        ");
        
        $params = array_merge([$this->accountId], $itemIds);
        $stmt->execute($params);
        
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $ttl = (int)($this->config['cache_ttl'] ?? 3600);
        $index = $this->cache->get($this->categoryCacheIndexKey, []);
        if (!is_array($index)) {
            $index = [];
        }

        foreach ($categories as $categoryId) {
            $categoryId = (string)$categoryId;
            if ($categoryId === '') {
                continue;
            }

            $cacheKey = 'tech_sheet:category_cache:' . $this->accountId . ':' . $categoryId;
            if (!$this->cache->has($cacheKey)) {
                $this->cache->set($cacheKey, true, $ttl, [$this->categoryCacheTag]);
            }

            $this->categoryCache[$categoryId] = true;
            $index[$categoryId] = true;
        }

        if (!empty($index)) {
            $this->cache->set($this->categoryCacheIndexKey, array_keys($index), $ttl, [$this->categoryCacheTag]);
        }
    }

    /**
     * Aplica sugestões aprovadas em lote
     * 
     * @param array $itemIds
     * @param array $options
     * @return array
     */
    public function applyBatchSuggestions(array $itemIds, array $options = []): array
    {
        $techSheetService = new TechSheetService($this->accountId);
        $userId = $options['user_id'] ?? 0;
        
        $processor = function($itemId, $itemData) use ($techSheetService, $userId) {
            try {
                $result = $techSheetService->applyApproved($itemId, $userId);
                return $result['success'] ?? false;
                
            } catch (\Exception $e) {
                return false;
            }
        };
        
        return $this->processBatch($itemIds, $processor, [
            'batch_size' => 20, // Menor para API ML
        ]);
    }

    /**
     * Analisa performance de batches anteriores
     * 
     * @return array
     */
    public function analyzeBatchPerformance(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                action,
                COUNT(*) as total_operations,
                SUM(CASE WHEN result = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN result = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_duration_seconds
            FROM tech_sheet_execution_log
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at), action
            ORDER BY date DESC
            LIMIT 100
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Otimiza queries lentas
     * 
     * @return array sugestões de otimização
     */
    public function getOptimizationSuggestions(): array
    {
        $suggestions = [];
        
        // 1. Verificar índices faltantes
        $missingIndexes = $this->checkMissingIndexes();
        if (!empty($missingIndexes)) {
            $suggestions[] = [
                'type' => 'missing_indexes',
                'priority' => 'HIGH',
                'description' => 'Índices faltantes detectados',
                'details' => $missingIndexes,
            ];
        }
        
        // 2. Verificar itens sem análise recente
        $outdatedItems = $this->countOutdatedItems();
        if ($outdatedItems > 100) {
            $suggestions[] = [
                'type' => 'outdated_analysis',
                'priority' => 'MEDIUM',
                'description' => "{$outdatedItems} itens sem análise recente",
                'action' => 'Executar análise em lote',
            ];
        }
        
        // 3. Verificar fragmentação de cache
        $cacheSize = $this->getCacheSize();
        if ($cacheSize > 1000) {
            $suggestions[] = [
                'type' => 'cache_cleanup',
                'priority' => 'LOW',
                'description' => 'Cache grande ({$cacheSize} entries)',
                'action' => 'Limpar cache antigo',
            ];
        }
        
        return $suggestions;
    }

    /**
     * Verifica índices faltantes
     */
    private function checkMissingIndexes(): array
    {
        $missing = [];
        
        // Verificar se existem índices importantes
        $tables = [
            'tech_sheet_item_summary' => ['account_id', 'item_id', 'completeness_percent'],
            'tech_sheet_suggestions' => ['account_id', 'item_id', 'status', 'confidence'],
            'tech_sheet_execution_log' => ['account_id', 'item_id', 'result'],
        ];
        
        foreach ($tables as $table => $columns) {
            $stmt = $this->db->prepare("SHOW INDEX FROM {$table}");
            $stmt->execute();
            $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $indexedColumns = array_column($indexes, 'Column_name');
            
            foreach ($columns as $column) {
                if (!in_array($column, $indexedColumns)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }
        
        return $missing;
    }

    /**
     * Conta itens sem análise recente
     */
    private function countOutdatedItems(): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND (
                s.last_analyzed_at IS NULL 
                OR s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
              )
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtém tamanho do cache
     */
    private function getCacheSize(): int
    {
        $index = $this->cache->get($this->categoryCacheIndexKey, null);
        if (is_array($index)) {
            return count($index);
        }

        return count($this->categoryCache);
    }

    /**
     * Limpa cache antigo
     */
    public function clearOldCache(): int
    {
        $cleared = $this->cache->invalidateTags([$this->categoryCacheTag]);
        $this->cache->delete($this->categoryCacheIndexKey);
        $this->categoryCache = [];
        
        return $cleared;
    }
}
