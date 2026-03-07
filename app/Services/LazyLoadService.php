<?php

declare(strict_types=1);

namespace App\Services;

class LazyLoadService
{
    /**
     * Carrega dados de forma paginada (lazy loading)
     */
    public function paginate(callable $dataLoader, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        
        // Adicionar paginação aos filtros
        $filters['offset'] = $offset;
        $filters['limit'] = $perPage;
        
        // Carregar dados
        $data = $dataLoader($filters);
        
        // Se retornou erro, retornar como está
        if (isset($data['error'])) {
            return $data;
        }
        
        // Calcular total (se disponível)
        $total = $data['paging']['total'] ?? count($data['results'] ?? $data['items'] ?? []);
        $items = $data['results'] ?? $data['items'] ?? [];
        
        return [
            'data' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
            'has_more' => ($offset + $perPage) < $total,
        ];
    }
    
    /**
     * Carrega dados incrementalmente (scroll infinito)
     */
    public function loadMore(callable $dataLoader, int $offset = 0, int $limit = 20, array $filters = []): array
    {
        $filters['offset'] = $offset;
        $filters['limit'] = $limit;
        
        $data = $dataLoader($filters);
        
        if (isset($data['error'])) {
            return $data;
        }
        
        $items = $data['results'] ?? $data['items'] ?? [];
        $total = $data['paging']['total'] ?? 0;
        
        return [
            'items' => $items,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'has_more' => ($offset + $limit) < $total,
            'next_offset' => ($offset + $limit) < $total ? $offset + $limit : null,
        ];
    }
    
    /**
     * Carrega dados em chunks para processamento em lote
     */
    public function chunk(callable $dataLoader, int $chunkSize = 100, ?callable $processor = null): array
    {
        $offset = 0;
        $allProcessed = [];
        $totalProcessed = 0;
        
        do {
            $filters = [
                'offset' => $offset,
                'limit' => $chunkSize,
            ];
            
            $data = $dataLoader($filters);
            
            if (isset($data['error'])) {
                return $data;
            }
            
            $items = $data['results'] ?? $data['items'] ?? [];
            
            if (!empty($items) && $processor) {
                $processed = $processor($items);
                $allProcessed = array_merge($allProcessed, $processed);
            } else {
                $allProcessed = array_merge($allProcessed, $items);
            }
            
            $totalProcessed += count($items);
            $offset += $chunkSize;
            
            $total = $data['paging']['total'] ?? count($items);
            
        } while (count($items) === $chunkSize && $offset < $total);
        
        return [
            'total_processed' => $totalProcessed,
            'items' => $allProcessed,
        ];
    }
    
    /**
     * Carrega dados com cache e lazy loading
     */
    public function lazyLoadWithCache(
        callable $dataLoader,
        string $cacheKey,
        int $page = 1,
        int $perPage = 20,
        int $cacheTtl = 300,
        array $filters = []
    ): array {
        $cacheService = new CacheService();
        
        // Gerar chave de cache específica para página e filtros
        $cacheKeyFull = $cacheKey . '_page_' . $page . '_' . md5(json_encode($filters));
        
        // Tentar obter do cache
        if ($cacheService->has($cacheKeyFull)) {
            return $cacheService->get($cacheKeyFull);
        }
        
        // Carregar dados
        $result = $this->paginate($dataLoader, $page, $perPage, $filters);
        
        // Armazenar no cache
        if (!isset($result['error'])) {
            $cacheService->set($cacheKeyFull, $result, $cacheTtl);
        }
        
        return $result;
    }
}
