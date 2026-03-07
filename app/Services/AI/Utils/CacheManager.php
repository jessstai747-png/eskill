<?php

declare(strict_types=1);

namespace App\Services\AI\Utils;

use App\Services\CacheService;

/**
 * AI-specific Cache Manager
 * Handles caching of AI optimization results to reduce costs and improve performance
 */
class CacheManager
{
    private CacheService $cache;
    
    // TTL settings (in seconds)
    private const TTL = [
        'optimization' => 86400,      // 24 hours
        'suggestions' => 43200,       // 12 hours  
        'score' => 21600,             // 6 hours
        'keywords' => 604800,         // 7 days
        'competitors' => 43200,       // 12 hours
        'attributes' => 172800,       // 48 hours
        'default' => 21600            // 6 hours
    ];
    
    // Cache key prefixes
    private const PREFIX = 'ai:';
    
    // Statistics tracking
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'cost_saved' => 0.0
    ];
    
    public function __construct()
    {
        $this->cache = new CacheService();
    }
    
    /**
     * Get optimization result from cache
     * 
     * @param string $itemId Item ID
     * @param string $type Optimization type (title, description, full)
     * @return array|null Cached data or null
     */
    public function getOptimization(string $itemId, string $type = 'full'): ?array
    {
        $key = $this->buildKey('optimization', $itemId, $type);
        return $this->get($key, 0.03); // Estimated cost per optimization
    }
    
    /**
     * Store optimization result in cache
     * 
     * @param string $itemId
     * @param string $type
     * @param array $data
     * @param int|null $ttl Override default TTL
     * @return bool
     */
    public function setOptimization(string $itemId, string $type, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('optimization', $itemId, $type);
        return $this->set($key, $data, $ttl ?? self::TTL['optimization']);
    }
    
    /**
     * Get suggestions from cache
     */
    public function getSuggestions(string $itemId): ?array
    {
        $key = $this->buildKey('suggestions', $itemId);
        return $this->get($key, 0.02);
    }
    
    /**
     * Store suggestions in cache
     */
    public function setSuggestions(string $itemId, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('suggestions', $itemId);
        return $this->set($key, $data, $ttl ?? self::TTL['suggestions']);
    }
    
    /**
     * Get quality score from cache
     */
    public function getScore(string $itemId): ?array
    {
        $key = $this->buildKey('score', $itemId);
        return $this->get($key, 0.01);
    }
    
    /**
     * Store quality score in cache
     */
    public function setScore(string $itemId, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('score', $itemId);
        return $this->set($key, $data, $ttl ?? self::TTL['score']);
    }
    
    /**
     * Get keyword research from cache
     */
    public function getKeywords(string $query, string $categoryId = ''): ?array
    {
        $key = $this->buildKey('keywords', md5($query . $categoryId));
        return $this->get($key, 0.05);
    }
    
    /**
     * Store keyword research in cache
     */
    public function setKeywords(string $query, string $categoryId, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('keywords', md5($query . $categoryId));
        $stored = $this->set($key, $data, $ttl ?? self::TTL['keywords']);
        if ($stored && $categoryId !== '') {
            $this->registerKeywordKey($categoryId, $key, $ttl ?? self::TTL['keywords']);
        }

        return $stored;
    }
    
    /**
     * Get competitor analysis from cache
     */
    public function getCompetitors(string $query, string $categoryId = ''): ?array
    {
        $key = $this->buildKey('competitors', md5($query . $categoryId));
        return $this->get($key, 0.08);
    }
    
    /**
     * Store competitor analysis in cache
     */
    public function setCompetitors(string $query, string $categoryId, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('competitors', md5($query . $categoryId));
        return $this->set($key, $data, $ttl ?? self::TTL['competitors']);
    }
    
    /**
     * Get inferred attributes from cache
     */
    public function getAttributes(string $itemId): ?array
    {
        $key = $this->buildKey('attributes', $itemId);
        return $this->get($key, 0.04);
    }
    
    /**
     * Store inferred attributes in cache
     */
    public function setAttributes(string $itemId, array $data, ?int $ttl = null): bool
    {
        $key = $this->buildKey('attributes', $itemId);
        return $this->set($key, $data, $ttl ?? self::TTL['attributes']);
    }
    
    /**
     * Invalidate cache for an item
     * 
     * @param string $itemId
     * @param array|null $types Specific types to invalidate, or null for all
     * @return bool
     */
    public function invalidate(string $itemId, ?array $types = null): bool
    {
        $allTypes = ['optimization', 'suggestions', 'score', 'attributes'];
        $typesToInvalidate = $types ?? $allTypes;
        
        foreach ($typesToInvalidate as $type) {
            if ($type === 'optimization') {
                // Invalidate all optimization types
                foreach (['full', 'title', 'description'] as $optType) {
                    $key = $this->buildKey('optimization', $itemId, $optType);
                    $this->cache->delete($key);
                }
            } else {
                $key = $this->buildKey($type, $itemId);
                $this->cache->delete($key);
            }
        }
        
        return true;
    }

    /**
     * Invalidate cached keywords for a category
     */
    public function invalidateKeywordsByCategory(string $categoryId): bool
    {
        if ($categoryId === '') {
            return false;
        }

        $indexKey = $this->getKeywordsIndexKey($categoryId);
        $keys = $this->cache->get($indexKey);

        $removed = false;
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key)) {
                    $this->cache->delete($key);
                    $removed = true;
                }
            }
        }

        $this->cache->delete($indexKey);

        return $removed;
    }
    
    /**
     * Build cache key
     */
    private function buildKey(string $type, string $identifier, string $subType = ''): string
    {
        $key = self::PREFIX . $type . ':' . $identifier;
        if ($subType) {
            $key .= ':' . $subType;
        }
        return $key;
    }

    private function registerKeywordKey(string $categoryId, string $key, int $ttl): void
    {
        $indexKey = $this->getKeywordsIndexKey($categoryId);
        $existing = $this->cache->get($indexKey);
        $keys = is_array($existing) ? $existing : [];

        if (!in_array($key, $keys, true)) {
            $keys[] = $key;
        }

        $this->cache->set($indexKey, $keys, $ttl);
    }

    private function getKeywordsIndexKey(string $categoryId): string
    {
        return $this->buildKey('keywords_index', $categoryId);
    }
    
    /**
     * Get from cache with statistics tracking
     */
    private function get(string $key, float $estimatedCost = 0.0): ?array
    {
        try {
            $data = $this->cache->get($key);
            
            if ($data !== null) {
                $this->stats['hits']++;
                $this->stats['cost_saved'] += $estimatedCost;
                
                // Return cached data if it's an array
                if (is_array($data)) {
                    return $data;
                }
                
                // Try to decode if it's JSON string
                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }
            }
            
            $this->stats['misses']++;
            return null;
            
        } catch (\Exception $e) {
            log_warning('Erro no AI Cache GET', [
                'service' => 'AI\\CacheManager',
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            $this->stats['misses']++;
            return null;
        }
    }
    
    /**
     * Set in cache with statistics tracking
     */
    private function set(string $key, array $data, int $ttl): bool
    {
        try {
            // Add metadata
            $data['_cached_at'] = date('Y-m-d H:i:s');
            $data['_expires_at'] = date('Y-m-d H:i:s', time() + $ttl);
            
            $result = $this->cache->set($key, $data, $ttl);
            
            if ($result) {
                $this->stats['sets']++;
            }
            
            return $result;
            
        } catch (\Exception $e) {
            log_warning('Erro no AI Cache SET', [
                'service' => 'AI\\CacheManager',
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        return [
            'hits' => $this->stats['hits'],
            'misses' => $this->stats['misses'],
            'sets' => $this->stats['sets'],
            'hit_rate' => $total > 0 
                ? round(($this->stats['hits'] / $total) * 100, 2) . '%' 
                : '0%',
            'cost_saved' => round($this->stats['cost_saved'], 4),
            'cost_saved_formatted' => '$' . number_format($this->stats['cost_saved'], 4)
        ];
    }
    
    /**
     * Reset statistics
     */
    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'cost_saved' => 0.0
        ];
    }
    
    /**
     * Warm cache for frequently accessed items
     * 
     * @param array $itemIds
     * @param callable $dataProvider Function to generate data for cache miss
     * @return array Results array
     */
    public function warmCache(array $itemIds, callable $dataProvider): array
    {
        $results = [
            'warmed' => 0,
            'skipped' => 0,
            'errors' => 0
        ];
        
        foreach ($itemIds as $itemId) {
            // Skip if already cached
            if ($this->getScore($itemId) !== null) {
                $results['skipped']++;
                continue;
            }
            
            try {
                $data = $dataProvider($itemId);
                if ($data) {
                    $this->setScore($itemId, $data);
                    $results['warmed']++;
                }
            } catch (\Exception $e) {
                $results['errors']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Get TTL for a specific type
     */
    public function getTTL(string $type): int
    {
        return self::TTL[$type] ?? self::TTL['default'];
    }
}
