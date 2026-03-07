<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SearchService;
use App\Services\CategoryService;

class OpportunityDetectorService
{
    /**
     * Detecta produtos sem catálogo em uma categoria/marca
     */
    public function detectProductsWithoutCatalog(string $categoryId, string $brand): array
    {
        $searchService = new SearchService();
        $analysis = $searchService->analyzeListings($categoryId, $brand);
        
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        $withoutCatalog = [];
        
        // Analisar itens comuns que poderiam ter catálogo
        foreach (($analysis['common']['items'] ?? []) as $item) {
            // Verificar se tem atributos suficientes para criar catálogo
            $hasRequiredAttributes = $this->hasRequiredAttributesForCatalog($item);
            
            if ($hasRequiredAttributes) {
                $withoutCatalog[] = [
                    'item_id' => $item['id'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'seller' => $item['seller']['nickname'] ?? 'Unknown',
                    'reason' => 'Tem atributos necessários para catálogo',
                ];
            }
        }
        
        return [
            'total' => count($withoutCatalog),
            'items' => $withoutCatalog,
            'category_id' => $categoryId,
            'brand' => $brand,
        ];
    }
    
    /**
     * Verifica se item tem atributos necessários para catálogo
     */
    private function hasRequiredAttributesForCatalog(array $item): bool
    {
        // Verificar se tem atributos importantes
        $hasAttributes = isset($item['attributes']) && is_array($item['attributes']);
        
        if (!$hasAttributes) {
            return false;
        }
        
        // Verificar atributos essenciais (exemplo)
        $essentialAttributes = ['BRAND', 'MODEL', 'MPN'];
        $foundAttributes = 0;
        
        foreach ($item['attributes'] as $attr) {
            if (in_array($attr['id'] ?? '', $essentialAttributes)) {
                $foundAttributes++;
            }
        }
        
        // Se tem pelo menos 2 atributos essenciais, pode criar catálogo
        return $foundAttributes >= 2;
    }
    
    /**
     * Detecta categorias com pouca concorrência
     */
    public function detectLowCompetitionCategories(?string $parentCategoryId = null): array
    {
        $categoryService = new CategoryService();
        
        if ($parentCategoryId) {
            $categories = $categoryService->getSubcategories($parentCategoryId);
        } else {
            $categories = $categoryService->getAllCategories();
        }
        
        if (isset($categories['error'])) {
            return $categories;
        }
        
        $lowCompetition = [];
        
        foreach ($categories as $category) {
            $categoryId = $category['id'];
            
            // Buscar itens na categoria (sem marca específica)
            $searchService = new SearchService();
            $response = $searchService->search(['category' => $categoryId, 'limit' => 1]);
            
            if (!isset($response['error'])) {
                $totalItems = $response['paging']['total'] ?? 0;
                
                // Se tem menos de 100 itens, considera baixa concorrência
                if ($totalItems < 100 && $totalItems > 0) {
                    $lowCompetition[] = [
                        'category_id' => $categoryId,
                        'category_name' => $category['name'],
                        'total_items' => $totalItems,
                        'opportunity' => 'Baixa concorrência',
                    ];
                }
            }
        }
        
        return [
            'total' => count($lowCompetition),
            'categories' => $lowCompetition,
        ];
    }
    
    /**
     * Detecta produtos mais vendidos sem anúncio do usuário
     */
    public function detectBestSellersWithoutUserListing(string $categoryId, string $brand, int $accountId): array
    {
        $searchService = new SearchService($accountId);
        $analysis = $searchService->analyzeListings($categoryId, $brand);
        
        if (isset($analysis['error'])) {
            return $analysis;
        }
        
        // Obter seller_id da conta
        $db = \App\Database::getInstance();
        $stmt = $db->prepare("SELECT ml_user_id FROM ml_accounts WHERE id = :id");
        $stmt->execute(['id' => $accountId]);
        $account = $stmt->fetch();
        
        if (!$account) {
            return ['error' => 'Conta não encontrada'];
        }
        
        $userSellerId = $account['ml_user_id'];
        
        // Encontrar produtos mais vendidos onde o usuário não tem anúncio
        $allItems = array_merge(
            $analysis['catalog']['items'] ?? [],
            $analysis['common']['items'] ?? []
        );
        
        // Ordenar por vendas
        usort($allItems, fn($a, $b) => ($b['sold_quantity'] ?? 0) - ($a['sold_quantity'] ?? 0));
        
        $opportunities = [];
        foreach ($allItems as $item) {
            $sellerId = $item['seller']['id'] ?? null;
            
            // Se não é do usuário e tem muitas vendas
            if ($sellerId !== $userSellerId && ($item['sold_quantity'] ?? 0) > 10) {
                $opportunities[] = [
                    'item_id' => $item['id'],
                    'title' => $item['title'],
                    'price' => $item['price'],
                    'sold_quantity' => $item['sold_quantity'] ?? 0,
                    'seller' => $item['seller']['nickname'] ?? 'Unknown',
                    'opportunity' => 'Produto bem vendido sem seu anúncio',
                ];
            }
            
            // Limitar a 20 oportunidades
            if (count($opportunities) >= 20) {
                break;
            }
        }
        
        return [
            'total' => count($opportunities),
            'items' => $opportunities,
        ];
    }
}

