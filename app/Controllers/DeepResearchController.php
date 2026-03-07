<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\DeepResearchService;
use App\Database;

/**
 * Controller de Deep Research
 * 
 * Expõe APIs para pesquisa profunda de marcas, sellers e análise de mercado
 */
class DeepResearchController extends BaseController
{
    private DeepResearchService $service;
    private ?int $accountId;

    public function __construct()
    {
        parent::__construct();

        $this->accountId = $this->getAccountId();

        // Se não há conta na sessão, usar a primeira conta ativa
        if (!$this->accountId) {
            $this->accountId = $this->getDefaultAccountId();
        }

        $this->service = new DeepResearchService($this->accountId);
    }

    /**
     * Obtém ID da primeira conta ativa do ML
     */
    private function getDefaultAccountId(): ?int
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT id FROM ml_accounts WHERE status = 'active' LIMIT 1");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * GET /api/research/brand/{categoryId}/{brand}
     * Pesquisa profunda de uma marca em uma categoria
     */
    public function researchBrand(string $categoryId, string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $priceMin = $this->request->get('price_min');
            $priceMax = $this->request->get('price_max');

            $options = [
                'max_items' => $this->request->getInt('max_items', 500),
                'include_seller_details' => $this->request->getBool('include_sellers', true),
                'analyze_shipping' => $this->request->getBool('analyze_shipping', true),
                'calculate_commissions' => $this->request->getBool('calculate_commissions', true),
                // Filtros avançados
                'price_min' => $priceMin !== null && $priceMin !== '' ? (float) $priceMin : null,
                'price_max' => $priceMax !== null && $priceMax !== '' ? (float) $priceMax : null,
                'condition' => $this->request->get('condition'),
                'shipping' => $this->request->get('shipping'),
                'listing_type' => $this->request->get('listing_type'),
                'seller_reputation' => $this->request->get('seller_reputation'),
                'sort' => $this->request->get('sort'),
            ];

            $result = $this->service->researchBrand($categoryId, urldecode($brand), $options);

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/quick/{categoryId}/{brand}
     * Pesquisa rápida (menos detalhes, mais velocidade)
     */
    public function quickResearch(string $categoryId, string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->service->quickResearch($categoryId, urldecode($brand));

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/compare/{categoryId}/{brand1}/{brand2}
     * Compara duas marcas na mesma categoria
     */
    public function compareBrands(string $categoryId, string $brand1, string $brand2): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->service->compareBrands(
                $categoryId,
                urldecode($brand1),
                urldecode($brand2)
            );

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/research/brand
     * Pesquisa profunda via POST (permite mais opções)
     */
    public function researchBrandPost(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['category_id']) || empty($input['brand'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'category_id e brand são obrigatórios',
                ]);
                return;
            }

            $options = [
                'max_items' => (int) ($input['max_items'] ?? 500),
                'include_seller_details' => $input['include_seller_details'] ?? true,
                'analyze_shipping' => $input['analyze_shipping'] ?? true,
                'calculate_commissions' => $input['calculate_commissions'] ?? true,
            ];

            $result = $this->service->researchBrand($input['category_id'], $input['brand'], $options);

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/sellers/{categoryId}
     * Lista top sellers de uma categoria (sem filtro de marca)
     */
    public function topSellersCategory(string $categoryId): void
    {
        header('Content-Type: application/json');

        try {
            // Faz pesquisa sem marca específica para pegar visão geral
            $result = $this->service->researchBrand($categoryId, '', [
                'max_items' => $this->request->getInt('max_items', 200),
                'include_seller_details' => true,
                'analyze_shipping' => false,
                'calculate_commissions' => false,
            ]);

            // Retorna apenas dados de sellers
            echo json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'total_sellers' => $result['sellers']['total_sellers'] ?? 0,
                    'market_concentration' => $result['sellers']['market_concentration'] ?? [],
                    'top_sellers' => array_slice($result['sellers']['sellers'] ?? [], 0, 20),
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/opportunities/{categoryId}/{brand}
     * Retorna apenas oportunidades identificadas
     */
    public function getOpportunities(string $categoryId, string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->service->quickResearch($categoryId, urldecode($brand));

            echo json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'brand' => $brand,
                    'opportunities' => $result['opportunities'] ?? [],
                    'insights' => $result['insights'] ?? [],
                    'summary' => [
                        'high_priority' => count(array_filter($result['opportunities'] ?? [], fn($o) => $o['priority'] === 'high')),
                        'medium_priority' => count(array_filter($result['opportunities'] ?? [], fn($o) => $o['priority'] === 'medium')),
                        'low_priority' => count(array_filter($result['opportunities'] ?? [], fn($o) => $o['priority'] === 'low')),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/pricing-analysis/{categoryId}/{brand}
     * Análise detalhada de preços
     */
    public function pricingAnalysis(string $categoryId, string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->service->researchBrand($categoryId, urldecode($brand), [
                'max_items' => $this->request->getInt('max_items', 500),
                'include_seller_details' => false,
                'analyze_shipping' => false,
                'calculate_commissions' => true,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'brand' => $brand,
                    'total_listings' => $result['summary']['total_listings'] ?? 0,
                    'pricing' => $result['pricing'] ?? [],
                    'commissions' => $result['commissions'] ?? [],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/research/shipping-analysis/{categoryId}/{brand}
     * Análise detalhada de frete
     */
    public function shippingAnalysis(string $categoryId, string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->service->researchBrand($categoryId, urldecode($brand), [
                'max_items' => $this->request->getInt('max_items', 500),
                'include_seller_details' => false,
                'analyze_shipping' => true,
                'calculate_commissions' => false,
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'brand' => $brand,
                    'total_listings' => $result['summary']['total_listings'] ?? 0,
                    'shipping' => $result['shipping'] ?? [],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/research/simulate-profitability
     * Simula lucratividade com base em custo e preço alvo
     */
    public function simulateProfitability(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['cost_price'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Preço de custo (cost_price) é obrigatório',
                ]);
                return;
            }

            $costPrice = (float) $input['cost_price'];
            $targetPrice = isset($input['target_price']) ? (float) $input['target_price'] : null;
            $taxRegime = $input['tax_regime'] ?? 'simples';
            $taxRate = isset($input['tax_rate']) ? (float) $input['tax_rate'] : 10.0;

            $result = $this->service->simulateProfitability($costPrice, $targetPrice, $taxRegime, $taxRate);

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/research/keywords/analyze
     * Analisa palavras-chave de uma lista de itens ou da última pesquisa
     */
    public function analyzeKeywords(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $limit = (int) ($input['limit'] ?? 20);
            $items = $input['items'] ?? []; // Opcional, se vazio usa cache interno do serviço

            $result = $this->service->analyzeTopKeywords($items, $limit);

            echo json_encode([
                'success' => true,
                'data' => $result,
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exibe interface web de Deep Research
     * GET /research
     */
    public function index(): void
    {
        include __DIR__ . '/../Views/deep_research/index.php';
    }
}
