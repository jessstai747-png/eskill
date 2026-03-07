<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SearchService;
use App\Services\LazyLoadService;
use App\Helpers\SessionHelper;

class SearchController extends BaseController
{
    private SearchService $searchService;
    private LazyLoadService $lazyLoadService;
    
    public function __construct()
    {
        parent::__construct();
        $accountId = SessionHelper::getActiveAccountId();
        $this->searchService = new SearchService($accountId);
        $this->lazyLoadService = new LazyLoadService();
    }
    
    /**
     * Busca itens com filtros
     */
    public function search(): void
    {
        $filters = [
            'category' => $this->request->get('category'),
            'BRAND' => $this->request->get('brand'),
            'condition' => $this->request->get('condition'),
            'price_min' => $this->request->get('price_min'),
            'price_max' => $this->request->get('price_max'),
            'free_shipping' => $this->request->getBool('free_shipping'),
            'limit' => $this->request->getInt('limit', 50),
            'offset' => $this->request->getInt('offset', 0),
            'sort' => $this->request->get('sort'),
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        $results = $this->searchService->search($filters);
        
        header('Content-Type: application/json');
        echo json_encode($results);
    }
    
    /**
     * Analisa anúncios por categoria e marca
     */
    public function analyze(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');
        
        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Parâmetros "category" e "brand" são obrigatórios'
            ]);
            return;
        }
        
        // Coletar filtros adicionais
        $additionalFilters = [];
        
        $condition = $this->request->get('condition');
        if ($condition) {
            $additionalFilters['condition'] = $condition;
        }
        
        $priceMin = $this->request->get('price_min');
        if ($priceMin) {
            $additionalFilters['price_min'] = $this->request->getFloat('price_min');
        }
        
        $priceMax = $this->request->get('price_max');
        if ($priceMax) {
            $additionalFilters['price_max'] = $this->request->getFloat('price_max');
        }
        
        $freeShipping = $this->request->get('free_shipping');
        if ($freeShipping !== null) {
            $additionalFilters['free_shipping'] = $this->request->getBool('free_shipping');
        }
        
        $listingType = $this->request->get('listing_type');
        if ($listingType) {
            $additionalFilters['listing_type'] = $listingType;
        }
        
        $analysis = $this->searchService->analyzeListings($categoryId, $brand, $additionalFilters);
        
        header('Content-Type: application/json');
        echo json_encode($analysis);
    }
    
    /**
     * Busca com lazy loading (paginação)
     * GET /api/search/lazy
     */
    public function lazySearch(): void
    {
        $page = $this->request->getInt('page', 1);
        $perPage = $this->request->getInt('per_page', 20);
        
        $filters = [
            'category' => $this->request->get('category'),
            'BRAND' => $this->request->get('brand'),
            'condition' => $this->request->get('condition'),
            'price_min' => $this->request->get('price_min'),
            'price_max' => $this->request->get('price_max'),
            'free_shipping' => $this->request->getBool('free_shipping'),
            'sort' => $this->request->get('sort'),
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        $result = $this->lazyLoadService->paginate(
            fn($f) => $this->searchService->search($f),
            $page,
            $perPage,
            $filters
        );
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    /**
     * Busca com scroll infinito (load more)
     * GET /api/search/load-more
     */
    public function loadMore(): void
    {
        $offset = $this->request->getInt('offset', 0);
        $limit = $this->request->getInt('limit', 20);
        
        $filters = [
            'category' => $this->request->get('category'),
            'BRAND' => $this->request->get('brand'),
            'condition' => $this->request->get('condition'),
            'price_min' => $this->request->get('price_min'),
            'price_max' => $this->request->get('price_max'),
            'free_shipping' => $this->request->getBool('free_shipping'),
            'sort' => $this->request->get('sort'),
        ];
        
        // Remover filtros vazios
        $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        
        $result = $this->lazyLoadService->loadMore(
            fn($f) => $this->searchService->search($f),
            $offset,
            $limit,
            $filters
        );
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}

