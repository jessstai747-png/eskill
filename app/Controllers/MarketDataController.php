<?php

namespace App\Controllers;

use App\Services\RealMarketDataService;
use App\Services\MercadoLivreClient;

/**
 * MarketDataController - API para dados reais de mercado
 * 
 * Endpoints para análise de mercado, concorrentes, preços e qualidade de anúncios.
 */
class MarketDataController extends BaseController
{
    private ?int $accountId;

    public function __construct()
    {
        parent::__construct();
        $this->accountId = $_SESSION['active_ml_account_id'] ?? null;
    }

    /**
     * GET /api/market/analyze/{categoryId}
     * Análise completa de mercado para uma categoria
     */
    public function analyzeMarket(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $keyword = $this->request->get('q') ?? $this->request->get('keyword');
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->analyzeMarket($categoryId, $keyword);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/category/{categoryId}
     * Detalhes da categoria
     */
    public function getCategory(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $service = new RealMarketDataService($this->accountId);
            $result = $service->getCategoryDetails($categoryId);
            
            echo json_encode([
                'success' => !isset($result['error']),
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/pricing/{categoryId}
     * Análise de preços do mercado
     */
    public function analyzePricing(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $keyword = $this->request->get('q') ?? $this->request->get('keyword');
            $sampleSize = $this->request->getInt('sample_size', 50);
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->analyzePricing($categoryId, $keyword, $sampleSize);
            
            echo json_encode([
                'success' => !isset($result['error']),
                'category_id' => $categoryId,
                'keyword' => $keyword,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/competitors/{categoryId}
     * Análise de concorrentes
     */
    public function analyzeCompetitors(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $keyword = $this->request->get('q') ?? $this->request->get('keyword');
            $limit = $this->request->getInt('limit', 20);
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->analyzeCompetitors($categoryId, $keyword, $limit);
            
            echo json_encode([
                'success' => !isset($result['error']),
                'category_id' => $categoryId,
                'keyword' => $keyword,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/trends/{categoryId}
     * Tendências da categoria
     */
    public function getTrends(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $service = new RealMarketDataService($this->accountId);
            $result = $service->getTrends($categoryId);
            
            echo json_encode([
                'success' => true,
                'category_id' => $categoryId,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/filters/{categoryId}
     * Filtros disponíveis no mercado
     */
    public function getFilters(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $keyword = $this->request->get('q') ?? $this->request->get('keyword');
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->getAvailableFilters($categoryId, $keyword);
            
            echo json_encode([
                'success' => !isset($result['error']),
                'category_id' => $categoryId,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /api/market/similar
     * Encontrar produtos similares
     */
    public function findSimilar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $input = $this->request->json() ?? [];
            
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';
            $basePrice = isset($input['price']) ? (float)$input['price'] : null;
            
            if (empty($title) || empty($categoryId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: title, category_id',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->findSimilarProducts($title, $categoryId, $basePrice);
            
            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/quality/{itemId}
     * Análise de qualidade do anúncio
     */
    public function analyzeQuality(string $itemId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $service = new RealMarketDataService($this->accountId);
            $result = $service->analyzeListingQuality($itemId);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * POST /api/market/suggest-price
     * Sugestão de preço baseado no mercado
     */
    public function suggestPrice(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $input = $this->request->json() ?? [];
            
            $categoryId = $input['category_id'] ?? '';
            $title = $input['title'] ?? '';
            $keyword = $input['keyword'] ?? null;
            
            if (empty($categoryId) || empty($title)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: category_id, title',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->suggestPrice($categoryId, $title, $keyword);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/search
     * Busca de produtos com dados reais
     */
    public function search(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $params = [];
            
            $q = $this->request->get('q');
            if (!empty($q)) {
                $params['q'] = $q;
            }
            $category = $this->request->get('category');
            if (!empty($category)) {
                $params['category'] = $category;
            }
            $sellerId = $this->request->get('seller_id');
            if (!empty($sellerId)) {
                $params['seller_id'] = $sellerId;
            }
            $sort = $this->request->get('sort');
            if (!empty($sort)) {
                $params['sort'] = $sort;
            }
            $limit = $this->request->getInt('limit');
            if ($limit > 0) {
                $params['limit'] = $limit;
            }
            $offset = $this->request->getInt('offset');
            if ($offset > 0) {
                $params['offset'] = $offset;
            }
            
            // Filtros adicionais
            $queryParams = filter_input_array(INPUT_GET) ?? [];
            foreach ($queryParams as $key => $value) {
                if (strpos($key, 'filter_') === 0) {
                    $filterKey = substr($key, 7);
                    $params[$filterKey] = $value;
                }
            }
            
            $client = new MercadoLivreClient($this->accountId);
            $result = $client->searchItems($params, 300);
            
            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'] ?? 'Erro na busca',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'paging' => $result['paging'] ?? [],
                'results' => $result['results'] ?? [],
                'available_filters' => $result['available_filters'] ?? [],
                'available_sorts' => $result['available_sorts'] ?? [],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/item/{itemId}
     * Detalhes de um item do mercado
     */
    public function getItem(string $itemId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $client = new MercadoLivreClient($this->accountId);
            $result = $client->getItemDetails($itemId);
            
            if (empty($result) || isset($result['error'])) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'] ?? 'Item não encontrado',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'item' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/autocomplete
     * Sugestões de autocompletar
     */
    public function autocomplete(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $query = $this->request->get('q', '');
            $categoryId = $this->request->get('category');
            
            if (empty($query)) {
                echo json_encode([
                    'success' => true,
                    'suggestions' => [],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $client = new MercadoLivreClient($this->accountId);
            $suggestions = $client->getAutocompleteSuggestions($query, $categoryId);
            
            echo json_encode([
                'success' => true,
                'query' => $query,
                'suggestions' => $suggestions,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/discover
     * Descoberta de categorias/domínios relacionados via domain_discovery
     */
    public function discover(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $query = $this->request->get('q', '');
            
            if (empty($query)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetro q (query) é obrigatório',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $service = new RealMarketDataService($this->accountId);
            $domains = $service->discoverRelatedDomains($query);
            
            echo json_encode([
                'success' => true,
                'query' => $query,
                'domains' => $domains,
                'total' => count($domains),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/attributes/{categoryId}
     * Atributos da categoria (para preenchimento de ficha técnica)
     */
    public function getAttributes(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $client = new MercadoLivreClient($this->accountId);
            $attributes = $client->getCategoryAttributes($categoryId);
            
            // Organizar por tipo/tag
            $required = [];
            $filter = [];
            $recommended = [];
            $other = [];
            
            foreach ($attributes as $attr) {
                $tags = $attr['tags'] ?? [];
                
                if (isset($tags['required']) || in_array('required', $tags)) {
                    $required[] = $attr;
                } elseif (isset($tags['allow_variations']) || isset($tags['defines_picture'])) {
                    $filter[] = $attr;
                } elseif (isset($tags['catalog_required'])) {
                    $recommended[] = $attr;
                } else {
                    $other[] = $attr;
                }
            }
            
            echo json_encode([
                'success' => true,
                'category_id' => $categoryId,
                'total_attributes' => count($attributes),
                'by_type' => [
                    'required' => $required,
                    'filter' => $filter,
                    'recommended' => $recommended,
                    'other' => $other,
                ],
                'counts' => [
                    'required' => count($required),
                    'filter' => count($filter),
                    'recommended' => count($recommended),
                    'other' => count($other),
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/children/{categoryId}
     * Subcategorias de uma categoria
     */
    public function getChildren(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $client = new MercadoLivreClient($this->accountId);
            $category = $client->getCategory($categoryId);
            
            if (empty($category)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Categoria não encontrada',
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $children = $category['children_categories'] ?? [];
            
            echo json_encode([
                'success' => true,
                'category_id' => $categoryId,
                'category_name' => $category['name'] ?? '',
                'children' => $children,
                'total_children' => count($children),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * GET /api/market/stats
     * Estatísticas gerais do mercado baseado em dados locais
     */
    public function getStats(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $db = \App\Database::getInstance();
            
            // Estatísticas gerais
            $stats = $db->query("
                SELECT 
                    COUNT(*) as total_items,
                    COUNT(DISTINCT category_id) as total_categories,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price
                FROM items
                WHERE account_id = " . (int)$this->accountId
            )->fetch(\PDO::FETCH_ASSOC);
            
            // Top categorias
            $topCategories = $db->query("
                SELECT 
                    category_id,
                    COUNT(*) as item_count,
                    AVG(price) as avg_price
                FROM items
                WHERE account_id = " . (int)$this->accountId . "
                GROUP BY category_id
                ORDER BY item_count DESC
                LIMIT 10
            ")->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'top_categories' => $topCategories,
                'generated_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * GET /api/market/requirements/{categoryId}
     * Requisitos de atributos para uma categoria
     */
    public function getRequirements(string $categoryId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $service = new RealMarketDataService($this->accountId);
            $result = $service->getCategoryRequirements($categoryId);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
    
    /**
     * GET /api/market/autocomplete
     * Autocomplete de busca
     */
    public function autocompleteSearch(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        try {
            $query = $this->request->get('q', '');
            $categoryId = $this->request->get('category_id');
            
            if (empty($query)) {
                echo json_encode([
                    'success' => true,
                    'suggestions' => [],
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            
            $service = new RealMarketDataService($this->accountId);
            $result = $service->autocomplete($query, $categoryId);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
