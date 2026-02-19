<?php

namespace App\Services;

use App\Services\MercadoLivreClient;
use App\Services\CacheService;
use App\Database;
use PDO;

/**
 * Serviço de Análise de Marca
 * 
 * Módulo especializado para analisar anúncios de uma marca específica no Mercado Livre,
 * identificando lacunas de dados, inconsistências e garantindo representação adequada.
 * 
 * Desenvolvido inicialmente para análise da marca AWA (motos e acessórios).
 */
class BrandAnalyzerService
{
    private MercadoLivreClient $client;
    private CacheService $cache;
    private ?PDO $db;
    private string $siteId;
    private ?int $accountId;

    /**
     * Categorias de motos e acessórios no Mercado Livre Brasil
     */
    private const MOTO_CATEGORIES = [
        'MLB1051'   => 'Motos',                           // Categoria principal de motos
        'MLB1747'   => 'Acessórios para Veículos',        // Categoria geral de acessórios
        'MLB214858' => 'Acessórios para Motos',           // Acessórios específicos para motos
        'MLB5750'   => 'Peças de Motos',                  // Peças de motos
    ];

    /**
     * Variações conhecidas do nome da marca AWA
     */
    private const BRAND_VARIATIONS = [
        'AWA',
        'Awa',
        'awa',
        'A.W.A',
        'A.W.A.',
        'a.w.a',
        'A W A',
    ];

    /**
     * ID do atributo de marca no ML
     */
    private const BRAND_ATTRIBUTE_ID = 'BRAND';

    public function __construct(?int $accountId = null)
    {
        $config = \App\Core\Config::getInstance()->all();
        $this->siteId = $config['mercadolivre']['site_id'] ?? 'MLB';
        $this->accountId = $accountId;
        $this->client = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();

        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    /**
     * Análise completa da marca AWA
     * 
     * @param array $options Opções de análise
     * @return array Resultado completo da análise
     */
    public function analyzeAwaBrand(array $options = []): array
    {
        $startTime = microtime(true);

        $categoryIds = $options['categories'] ?? array_keys(self::MOTO_CATEGORIES);
        $maxResults = $options['max_results'] ?? 1000;
        $includeDetails = $options['include_details'] ?? true;

        $results = [
            'brand' => 'AWA',
            'analysis_date' => date('Y-m-d H:i:s'),
            'categories_analyzed' => [],
            'total_listings' => 0,
            'listings_with_brand' => 0,
            'listings_without_brand' => 0,
            'listings_with_wrong_brand' => 0,
            'brand_consistency_score' => 0,
            'gaps_detected' => [],
            'inconsistencies' => [],
            'sellers' => [],
            'price_analysis' => [],
            'shipping_analysis' => [],
            'condition_analysis' => [],
            'items' => [],
            'summary' => [],
        ];

        // Analisar cada categoria
        foreach ($categoryIds as $categoryId) {
            $categoryName = self::MOTO_CATEGORIES[$categoryId] ?? $categoryId;

            $categoryAnalysis = $this->analyzeCategory($categoryId, $maxResults);

            $results['categories_analyzed'][] = [
                'id' => $categoryId,
                'name' => $categoryName,
                'total_found' => $categoryAnalysis['total'],
                'with_brand' => $categoryAnalysis['with_brand'],
                'without_brand' => $categoryAnalysis['without_brand'],
            ];

            // Agregar resultados
            $results['total_listings'] += $categoryAnalysis['total'];
            $results['listings_with_brand'] += $categoryAnalysis['with_brand'];
            $results['listings_without_brand'] += $categoryAnalysis['without_brand'];
            $results['listings_with_wrong_brand'] += $categoryAnalysis['with_wrong_brand'];

            // Adicionar itens
            if ($includeDetails) {
                $results['items'] = array_merge($results['items'], $categoryAnalysis['items']);
            }

            // Agregar gaps e inconsistências
            $results['gaps_detected'] = array_merge(
                $results['gaps_detected'],
                $categoryAnalysis['gaps']
            );
            $results['inconsistencies'] = array_merge(
                $results['inconsistencies'],
                $categoryAnalysis['inconsistencies']
            );

            // Agregar vendedores
            $results['sellers'] = $this->mergeSellers(
                $results['sellers'],
                $categoryAnalysis['sellers']
            );
        }

        // Calcular score de consistência da marca
        if ($results['total_listings'] > 0) {
            $results['brand_consistency_score'] = round(
                ($results['listings_with_brand'] / $results['total_listings']) * 100,
                2
            );
        }

        // Análise de preços consolidada
        $results['price_analysis'] = $this->analyzePrices($results['items']);

        // Análise de frete consolidada
        $results['shipping_analysis'] = $this->analyzeShipping($results['items']);

        // Análise de condição consolidada
        $results['condition_analysis'] = $this->analyzeConditions($results['items']);

        // Gerar resumo executivo
        $results['summary'] = $this->generateSummary($results);

        // Tempo de execução
        $results['execution_time'] = round(microtime(true) - $startTime, 2) . 's';

        // Persistir resultados para histórico
        $this->saveAnalysisHistory($results);

        return $results;
    }

    /**
     * Analisa uma categoria específica
     */
    private function analyzeCategory(string $categoryId, int $maxResults): array
    {
        $allItems = [];
        $withBrand = 0;
        $withoutBrand = 0;
        $withWrongBrand = 0;
        $gaps = [];
        $inconsistencies = [];
        $sellers = [];

        // Buscar por todas as variações da marca
        foreach (self::BRAND_VARIATIONS as $brandVariation) {
            $items = $this->searchBrandItems($categoryId, $brandVariation, $maxResults);

            foreach ($items as $item) {
                // Evitar duplicatas
                if (isset($allItems[$item['id']])) {
                    continue;
                }

                // Obter detalhes completos do item
                $itemDetails = $this->getItemDetails($item['id']);

                if (isset($itemDetails['error'])) {
                    continue;
                }

                // Analisar atributo de marca
                $brandAnalysis = $this->analyzeBrandAttribute($itemDetails);

                // Classificar item
                if ($brandAnalysis['has_brand']) {
                    if ($brandAnalysis['is_correct']) {
                        $withBrand++;
                    } else {
                        $withWrongBrand++;
                        $inconsistencies[] = [
                            'item_id' => $item['id'],
                            'type' => 'wrong_brand',
                            'current_value' => $brandAnalysis['current_value'],
                            'expected_value' => 'AWA',
                            'title' => $itemDetails['title'] ?? '',
                        ];
                    }
                } else {
                    $withoutBrand++;
                    $gaps[] = [
                        'item_id' => $item['id'],
                        'type' => 'missing_brand',
                        'title' => $itemDetails['title'] ?? '',
                        'seller_id' => $itemDetails['seller_id'] ?? null,
                    ];
                }

                // Coletar dados do vendedor
                $sellerId = $itemDetails['seller_id'] ?? null;
                if ($sellerId) {
                    if (!isset($sellers[$sellerId])) {
                        $sellers[$sellerId] = $this->getSellerDetails($sellerId);
                        $sellers[$sellerId]['items_count'] = 0;
                        $sellers[$sellerId]['items'] = [];
                    }
                    $sellers[$sellerId]['items_count']++;
                    $sellers[$sellerId]['items'][] = $item['id'];
                }

                // Armazenar item processado
                $allItems[$item['id']] = $this->extractItemData($itemDetails, $brandAnalysis);
            }
        }

        // Buscar também por título (pode encontrar itens AWA sem marca definida)
        $titleSearchItems = $this->searchByTitle($categoryId, 'AWA', $maxResults);

        foreach ($titleSearchItems as $item) {
            if (isset($allItems[$item['id']])) {
                continue;
            }

            $itemDetails = $this->getItemDetails($item['id']);

            if (isset($itemDetails['error'])) {
                continue;
            }

            $brandAnalysis = $this->analyzeBrandAttribute($itemDetails);

            // Este item menciona AWA no título mas pode não ter a marca definida
            if (!$brandAnalysis['has_brand']) {
                $withoutBrand++;
                $gaps[] = [
                    'item_id' => $item['id'],
                    'type' => 'brand_in_title_not_attribute',
                    'title' => $itemDetails['title'] ?? '',
                    'seller_id' => $itemDetails['seller_id'] ?? null,
                ];
            }

            $sellerId = $itemDetails['seller_id'] ?? null;
            if ($sellerId) {
                if (!isset($sellers[$sellerId])) {
                    $sellers[$sellerId] = $this->getSellerDetails($sellerId);
                    $sellers[$sellerId]['items_count'] = 0;
                    $sellers[$sellerId]['items'] = [];
                }
                $sellers[$sellerId]['items_count']++;
                $sellers[$sellerId]['items'][] = $item['id'];
            }

            $allItems[$item['id']] = $this->extractItemData($itemDetails, $brandAnalysis);
        }

        return [
            'total' => count($allItems),
            'with_brand' => $withBrand,
            'without_brand' => $withoutBrand,
            'with_wrong_brand' => $withWrongBrand,
            'items' => array_values($allItems),
            'gaps' => $gaps,
            'inconsistencies' => $inconsistencies,
            'sellers' => $sellers,
        ];
    }

    /**
     * Busca itens por marca
     */
    private function searchBrandItems(string $categoryId, string $brand, int $maxResults): array
    {
        $allItems = [];
        $offset = 0;
        $limit = 50;

        do {
            $params = [
                'category' => $categoryId,
                'BRAND' => $brand,
                'limit' => $limit,
                'offset' => $offset,
            ];

            $response = $this->client->get("/sites/{$this->siteId}/search", $params, 300);

            if (isset($response['error'])) {
                break;
            }

            if (isset($response['results'])) {
                $allItems = array_merge($allItems, $response['results']);
            }

            $offset += $limit;
            $total = $response['paging']['total'] ?? 0;
        } while ($offset < $total && count($allItems) < $maxResults);

        return array_slice($allItems, 0, $maxResults);
    }

    /**
     * Busca itens pelo título
     */
    private function searchByTitle(string $categoryId, string $keyword, int $maxResults): array
    {
        $allItems = [];
        $offset = 0;
        $limit = 50;

        do {
            $params = [
                'category' => $categoryId,
                'q' => $keyword,
                'limit' => $limit,
                'offset' => $offset,
            ];

            $response = $this->client->get("/sites/{$this->siteId}/search", $params, 300);

            if (isset($response['error'])) {
                break;
            }

            if (isset($response['results'])) {
                $allItems = array_merge($allItems, $response['results']);
            }

            $offset += $limit;
            $total = $response['paging']['total'] ?? 0;
        } while ($offset < $total && count($allItems) < $maxResults);

        return array_slice($allItems, 0, $maxResults);
    }

    /**
     * Obtém detalhes completos de um item
     */
    private function getItemDetails(string $itemId): array
    {
        // Cache de 5 minutos
        $cacheKey = "item_details:{$itemId}";

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $response = $this->client->get("/items/{$itemId}");

        if (!isset($response['error'])) {
            $this->cache->set($cacheKey, $response, 300);
        }

        return $response;
    }

    /**
     * Obtém detalhes do vendedor
     */
    private function getSellerDetails(int $sellerId): array
    {
        // Cache de 30 minutos
        $cacheKey = "seller_details:{$sellerId}";

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $response = $this->client->get("/users/{$sellerId}");

        if (isset($response['error'])) {
            return [
                'id' => $sellerId,
                'nickname' => 'Desconhecido',
                'reputation' => null,
                'location' => null,
            ];
        }

        $sellerData = [
            'id' => $sellerId,
            'nickname' => $response['nickname'] ?? 'Desconhecido',
            'registration_date' => $response['registration_date'] ?? null,
            'country_id' => $response['country_id'] ?? null,
            'address' => [
                'city' => $response['address']['city'] ?? null,
                'state' => $response['address']['state'] ?? null,
            ],
            'seller_reputation' => [
                'level_id' => $response['seller_reputation']['level_id'] ?? null,
                'power_seller_status' => $response['seller_reputation']['power_seller_status'] ?? null,
                'transactions' => $response['seller_reputation']['transactions'] ?? null,
                'metrics' => $response['seller_reputation']['metrics'] ?? null,
            ],
            'status' => [
                'site_status' => $response['status']['site_status'] ?? null,
            ],
        ];

        $this->cache->set($cacheKey, $sellerData, 1800);

        return $sellerData;
    }

    /**
     * Analisa o atributo de marca de um item
     */
    private function analyzeBrandAttribute(array $item): array
    {
        $result = [
            'has_brand' => false,
            'is_correct' => false,
            'current_value' => null,
            'normalized_value' => null,
        ];

        $attributes = $item['attributes'] ?? [];

        foreach ($attributes as $attr) {
            if ($attr['id'] === self::BRAND_ATTRIBUTE_ID) {
                $result['has_brand'] = true;
                $result['current_value'] = $attr['value_name'] ?? null;

                if ($result['current_value']) {
                    $normalizedValue = strtoupper(trim($result['current_value']));
                    $normalizedValue = str_replace(['.', ' '], '', $normalizedValue);
                    $result['normalized_value'] = $normalizedValue;

                    // Verificar se é uma variação válida de AWA
                    if ($normalizedValue === 'AWA') {
                        $result['is_correct'] = true;
                    }
                }
                break;
            }
        }

        return $result;
    }

    /**
     * Extrai dados relevantes de um item
     */
    private function extractItemData(array $item, array $brandAnalysis): array
    {
        return [
            'id' => $item['id'],
            'title' => $item['title'] ?? '',
            'category_id' => $item['category_id'] ?? null,
            'price' => $item['price'] ?? 0,
            'original_price' => $item['original_price'] ?? null,
            'currency_id' => $item['currency_id'] ?? 'BRL',
            'condition' => $item['condition'] ?? 'unknown',
            'available_quantity' => $item['available_quantity'] ?? 0,
            'sold_quantity' => $item['sold_quantity'] ?? 0,
            'listing_type_id' => $item['listing_type_id'] ?? null,
            'status' => $item['status'] ?? null,
            'permalink' => $item['permalink'] ?? null,
            'thumbnail' => $item['thumbnail'] ?? null,
            'seller_id' => $item['seller_id'] ?? null,
            'shipping' => [
                'free_shipping' => $item['shipping']['free_shipping'] ?? false,
                'logistic_type' => $item['shipping']['logistic_type'] ?? null,
                'mode' => $item['shipping']['mode'] ?? null,
                'store_pick_up' => $item['shipping']['store_pick_up'] ?? false,
            ],
            'brand_analysis' => $brandAnalysis,
            'date_created' => $item['date_created'] ?? null,
            'last_updated' => $item['last_updated'] ?? null,
            'catalog_product_id' => $item['catalog_product_id'] ?? null,
            'health' => $item['health'] ?? null,
        ];
    }

    /**
     * Mescla dados de vendedores
     */
    private function mergeSellers(array $existing, array $new): array
    {
        foreach ($new as $sellerId => $seller) {
            if (isset($existing[$sellerId])) {
                $existing[$sellerId]['items_count'] += $seller['items_count'];
                $existing[$sellerId]['items'] = array_merge(
                    $existing[$sellerId]['items'],
                    $seller['items']
                );
            } else {
                $existing[$sellerId] = $seller;
            }
        }

        return $existing;
    }

    /**
     * Analisa preços
     */
    private function analyzePrices(array $items): array
    {
        $prices = [];
        $promotionalPrices = [];

        foreach ($items as $item) {
            if (isset($item['price']) && $item['price'] > 0) {
                $prices[] = $item['price'];

                if (isset($item['original_price']) && $item['original_price'] > $item['price']) {
                    $promotionalPrices[] = [
                        'item_id' => $item['id'],
                        'original' => $item['original_price'],
                        'current' => $item['price'],
                        'discount' => round((($item['original_price'] - $item['price']) / $item['original_price']) * 100, 2),
                    ];
                }
            }
        }

        if (empty($prices)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'avg' => 0,
                'median' => 0,
            ];
        }

        sort($prices);
        $count = count($prices);
        $median = $count % 2 === 0
            ? ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2
            : $prices[floor($count / 2)];

        return [
            'count' => $count,
            'min' => min($prices),
            'max' => max($prices),
            'avg' => round(array_sum($prices) / $count, 2),
            'median' => round($median, 2),
            'promotional_items' => count($promotionalPrices),
            'promotions' => array_slice($promotionalPrices, 0, 10), // Top 10
            'price_ranges' => $this->calculatePriceRanges($prices),
        ];
    }

    /**
     * Calcula faixas de preço
     */
    private function calculatePriceRanges(array $prices): array
    {
        $ranges = [
            '0-50' => 0,
            '50-100' => 0,
            '100-200' => 0,
            '200-500' => 0,
            '500-1000' => 0,
            '1000+' => 0,
        ];

        foreach ($prices as $price) {
            if ($price < 50) {
                $ranges['0-50']++;
            } elseif ($price < 100) {
                $ranges['50-100']++;
            } elseif ($price < 200) {
                $ranges['100-200']++;
            } elseif ($price < 500) {
                $ranges['200-500']++;
            } elseif ($price < 1000) {
                $ranges['500-1000']++;
            } else {
                $ranges['1000+']++;
            }
        }

        return $ranges;
    }

    /**
     * Analisa frete
     */
    private function analyzeShipping(array $items): array
    {
        $freeShipping = 0;
        $paidShipping = 0;
        $fullShipping = 0; // Mercado Envios Full
        $storePickup = 0;

        foreach ($items as $item) {
            $shipping = $item['shipping'] ?? [];

            if ($shipping['free_shipping'] ?? false) {
                $freeShipping++;
            } else {
                $paidShipping++;
            }

            if (($shipping['logistic_type'] ?? '') === 'fulfillment') {
                $fullShipping++;
            }

            if ($shipping['store_pick_up'] ?? false) {
                $storePickup++;
            }
        }

        $total = count($items);

        return [
            'total_items' => $total,
            'free_shipping' => [
                'count' => $freeShipping,
                'percentage' => $total > 0 ? round(($freeShipping / $total) * 100, 2) : 0,
            ],
            'paid_shipping' => [
                'count' => $paidShipping,
                'percentage' => $total > 0 ? round(($paidShipping / $total) * 100, 2) : 0,
            ],
            'full_shipping' => [
                'count' => $fullShipping,
                'percentage' => $total > 0 ? round(($fullShipping / $total) * 100, 2) : 0,
            ],
            'store_pickup' => [
                'count' => $storePickup,
                'percentage' => $total > 0 ? round(($storePickup / $total) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Analisa condições dos produtos
     */
    private function analyzeConditions(array $items): array
    {
        $conditions = [
            'new' => 0,
            'used' => 0,
            'refurbished' => 0,
            'unknown' => 0,
        ];

        foreach ($items as $item) {
            $condition = $item['condition'] ?? 'unknown';
            if (isset($conditions[$condition])) {
                $conditions[$condition]++;
            } else {
                $conditions['unknown']++;
            }
        }

        $total = count($items);

        return [
            'total_items' => $total,
            'new' => [
                'count' => $conditions['new'],
                'percentage' => $total > 0 ? round(($conditions['new'] / $total) * 100, 2) : 0,
            ],
            'used' => [
                'count' => $conditions['used'],
                'percentage' => $total > 0 ? round(($conditions['used'] / $total) * 100, 2) : 0,
            ],
            'refurbished' => [
                'count' => $conditions['refurbished'],
                'percentage' => $total > 0 ? round(($conditions['refurbished'] / $total) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Gera resumo executivo
     */
    private function generateSummary(array $results): array
    {
        $totalGaps = count($results['gaps_detected']);
        $totalInconsistencies = count($results['inconsistencies']);
        $totalSellers = count($results['sellers']);

        // Identificar top sellers
        $topSellers = [];
        foreach ($results['sellers'] as $seller) {
            $topSellers[] = [
                'id' => $seller['id'],
                'nickname' => $seller['nickname'],
                'items_count' => $seller['items_count'],
            ];
        }
        usort($topSellers, fn($a, $b) => $b['items_count'] - $a['items_count']);
        $topSellers = array_slice($topSellers, 0, 5);

        // Análise de problemas críticos
        $criticalIssues = [];

        if ($results['brand_consistency_score'] < 80) {
            $criticalIssues[] = [
                'type' => 'low_brand_consistency',
                'severity' => 'high',
                'message' => "Score de consistência da marca baixo: {$results['brand_consistency_score']}%",
                'recommendation' => 'Revisar anúncios sem marca definida e corrigir.',
            ];
        }

        if ($totalGaps > 10) {
            $criticalIssues[] = [
                'type' => 'many_gaps',
                'severity' => 'medium',
                'message' => "{$totalGaps} anúncios com lacunas de dados identificados",
                'recommendation' => 'Priorizar correção dos anúncios com marca no título mas não no atributo.',
            ];
        }

        if ($totalInconsistencies > 5) {
            $criticalIssues[] = [
                'type' => 'brand_inconsistencies',
                'severity' => 'high',
                'message' => "{$totalInconsistencies} anúncios com marca incorreta",
                'recommendation' => 'Corrigir imediatamente anúncios com marca errada.',
            ];
        }

        $shippingAnalysis = $results['shipping_analysis'];
        if (($shippingAnalysis['free_shipping']['percentage'] ?? 0) < 50) {
            $criticalIssues[] = [
                'type' => 'low_free_shipping',
                'severity' => 'medium',
                'message' => "Apenas {$shippingAnalysis['free_shipping']['percentage']}% dos anúncios com frete grátis",
                'recommendation' => 'Aumentar ofertas de frete grátis para melhorar ranking.',
            ];
        }

        return [
            'total_listings' => $results['total_listings'],
            'brand_consistency_score' => $results['brand_consistency_score'],
            'total_gaps' => $totalGaps,
            'total_inconsistencies' => $totalInconsistencies,
            'total_sellers' => $totalSellers,
            'top_sellers' => $topSellers,
            'critical_issues' => $criticalIssues,
            'recommendations' => $this->generateRecommendations($results),
            'health_status' => $this->calculateHealthStatus($results),
        ];
    }

    /**
     * Gera recomendações baseadas na análise
     */
    private function generateRecommendations(array $results): array
    {
        $recommendations = [];

        // Recomendações de marca
        if ($results['listings_without_brand'] > 0) {
            $recommendations[] = [
                'priority' => 1,
                'category' => 'brand',
                'action' => 'Adicionar atributo BRAND',
                'description' => "Existem {$results['listings_without_brand']} anúncios sem o atributo de marca definido.",
                'impact' => 'Alto - Melhora visibilidade em buscas por marca',
            ];
        }

        // Recomendações de frete
        $shippingAnalysis = $results['shipping_analysis'];
        if (($shippingAnalysis['full_shipping']['percentage'] ?? 0) < 20) {
            $recommendations[] = [
                'priority' => 2,
                'category' => 'shipping',
                'action' => 'Ativar Mercado Envios Full',
                'description' => 'Poucos anúncios utilizam Mercado Envios Full.',
                'impact' => 'Alto - Melhora ranking e conversão',
            ];
        }

        // Recomendações de preço
        $priceAnalysis = $results['price_analysis'];
        if (($priceAnalysis['promotional_items'] ?? 0) < 3) {
            $recommendations[] = [
                'priority' => 3,
                'category' => 'pricing',
                'action' => 'Criar promoções',
                'description' => 'Poucos anúncios com preço promocional.',
                'impact' => 'Médio - Aumenta conversão e visibilidade',
            ];
        }

        // Recomendações de condição
        $conditionAnalysis = $results['condition_analysis'];
        if (($conditionAnalysis['new']['percentage'] ?? 0) < 70) {
            $recommendations[] = [
                'priority' => 4,
                'category' => 'product',
                'action' => 'Foco em produtos novos',
                'description' => 'Considerar aumentar proporção de produtos novos.',
                'impact' => 'Médio - Melhora confiança do comprador',
            ];
        }

        return $recommendations;
    }

    /**
     * Calcula status de saúde geral
     */
    private function calculateHealthStatus(array $results): array
    {
        $score = 100;
        $issues = [];

        // Penalidades
        if ($results['brand_consistency_score'] < 100) {
            $penalty = (100 - $results['brand_consistency_score']) * 0.5;
            $score -= $penalty;
            $issues[] = 'Consistência de marca';
        }

        $gapPercentage = $results['total_listings'] > 0
            ? (count($results['gaps_detected']) / $results['total_listings']) * 100
            : 0;

        if ($gapPercentage > 5) {
            $score -= min(20, $gapPercentage);
            $issues[] = 'Lacunas de dados';
        }

        $freeShippingPercentage = $results['shipping_analysis']['free_shipping']['percentage'] ?? 0;
        if ($freeShippingPercentage < 50) {
            $score -= (50 - $freeShippingPercentage) * 0.2;
            $issues[] = 'Frete grátis';
        }

        $score = max(0, round($score));

        $status = 'excellent';
        if ($score < 50) {
            $status = 'critical';
        } elseif ($score < 70) {
            $status = 'poor';
        } elseif ($score < 85) {
            $status = 'fair';
        } elseif ($score < 95) {
            $status = 'good';
        }

        return [
            'score' => $score,
            'status' => $status,
            'issues' => $issues,
        ];
    }

    /**
     * Salva histórico da análise no banco de dados
     */
    private function saveAnalysisHistory(array $results): void
    {
        if (!$this->db) {
            return;
        }

        try {
            // Criar tabela se não existir
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS brand_analysis_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    brand VARCHAR(100) NOT NULL,
                    analysis_date DATETIME NOT NULL,
                    total_listings INT NOT NULL,
                    listings_with_brand INT NOT NULL,
                    listings_without_brand INT NOT NULL,
                    consistency_score DECIMAL(5,2),
                    health_score INT,
                    health_status VARCHAR(20),
                    gaps_count INT,
                    inconsistencies_count INT,
                    sellers_count INT,
                    result_json TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $stmt = $this->db->prepare("
                INSERT INTO brand_analysis_history 
                (brand, analysis_date, total_listings, listings_with_brand, listings_without_brand, 
                 consistency_score, health_score, health_status, gaps_count, inconsistencies_count, 
                 sellers_count, result_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $summary = $results['summary'] ?? [];
            $health = $summary['health_status'] ?? [];

            $stmt->execute([
                $results['brand'],
                $results['analysis_date'],
                $results['total_listings'],
                $results['listings_with_brand'],
                $results['listings_without_brand'],
                $results['brand_consistency_score'],
                $health['score'] ?? 0,
                $health['status'] ?? 'unknown',
                count($results['gaps_detected']),
                count($results['inconsistencies']),
                count($results['sellers']),
                json_encode($results),
            ]);
        } catch (\Exception $e) {
            log_error('Erro ao salvar histórico de análise de marca', [
                'brand' => $results['brand_name'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém histórico de análises
     */
    public function getAnalysisHistory(string $brand = 'AWA', int $limit = 30): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT id, brand, analysis_date, total_listings, listings_with_brand, 
                       listings_without_brand, consistency_score, health_score, health_status,
                       gaps_count, inconsistencies_count, sellers_count, created_at
                FROM brand_analysis_history
                WHERE brand = ?
                ORDER BY analysis_date DESC
                LIMIT {$limitSql}
            ");

            $stmt->execute([$brand]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            log_error('Erro ao obter histórico de análise de marca', [
                'brand' => $brand,
                'limit' => $limit,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Exporta relatório completo
     */
    public function exportReport(array $results, string $format = 'json'): array
    {
        switch ($format) {
            case 'csv':
                return $this->exportCSV($results);
            case 'summary':
                return $this->exportSummary($results);
            default:
                return $results;
        }
    }

    /**
     * Exporta para formato CSV
     */
    private function exportCSV(array $results): array
    {
        $csvData = [];

        // Header
        $csvData[] = [
            'Item ID',
            'Título',
            'Preço',
            'Preço Original',
            'Moeda',
            'Condição',
            'Estoque',
            'Vendidos',
            'Tem Marca',
            'Marca Correta',
            'Valor Marca',
            'Frete Grátis',
            'Tipo Frete',
            'Vendedor ID',
            'Categoria',
            'Status',
            'Link',
        ];

        // Dados
        foreach ($results['items'] as $item) {
            $brandAnalysis = $item['brand_analysis'] ?? [];
            $shipping = $item['shipping'] ?? [];

            $csvData[] = [
                $item['id'],
                $item['title'],
                $item['price'],
                $item['original_price'] ?? '',
                $item['currency_id'],
                $item['condition'],
                $item['available_quantity'],
                $item['sold_quantity'],
                $brandAnalysis['has_brand'] ? 'Sim' : 'Não',
                $brandAnalysis['is_correct'] ? 'Sim' : 'Não',
                $brandAnalysis['current_value'] ?? '',
                ($shipping['free_shipping'] ?? false) ? 'Sim' : 'Não',
                $shipping['logistic_type'] ?? '',
                $item['seller_id'] ?? '',
                $item['category_id'] ?? '',
                $item['status'] ?? '',
                $item['permalink'] ?? '',
            ];
        }

        return [
            'format' => 'csv',
            'filename' => 'awa_brand_analysis_' . date('Y-m-d_H-i-s') . '.csv',
            'data' => $csvData,
        ];
    }

    /**
     * Exporta resumo executivo
     */
    private function exportSummary(array $results): array
    {
        $summary = $results['summary'] ?? [];
        $health = $summary['health_status'] ?? [];

        return [
            'format' => 'summary',
            'report' => [
                'titulo' => 'Análise de Marca AWA - Mercado Livre',
                'data_analise' => $results['analysis_date'],
                'tempo_execucao' => $results['execution_time'] ?? 'N/A',

                'visao_geral' => [
                    'total_anuncios' => $results['total_listings'],
                    'score_consistencia' => $results['brand_consistency_score'] . '%',
                    'score_saude' => ($health['score'] ?? 0) . '/100',
                    'status' => $health['status'] ?? 'unknown',
                ],

                'analise_marca' => [
                    'com_marca' => $results['listings_with_brand'],
                    'sem_marca' => $results['listings_without_brand'],
                    'marca_incorreta' => $results['listings_with_wrong_brand'],
                ],

                'categorias' => $results['categories_analyzed'],

                'vendedores' => [
                    'total' => $summary['total_sellers'] ?? 0,
                    'top_5' => $summary['top_sellers'] ?? [],
                ],

                'precos' => $results['price_analysis'],
                'frete' => $results['shipping_analysis'],
                'condicao' => $results['condition_analysis'],

                'problemas_criticos' => $summary['critical_issues'] ?? [],
                'recomendacoes' => $summary['recommendations'] ?? [],

                'lacunas' => [
                    'total' => count($results['gaps_detected']),
                    'itens' => array_slice($results['gaps_detected'], 0, 20), // Primeiros 20
                ],

                'inconsistencias' => [
                    'total' => count($results['inconsistencies']),
                    'itens' => $results['inconsistencies'],
                ],
            ],
        ];
    }

    /**
     * Detecta padrões de inconsistência
     */
    public function detectInconsistencyPatterns(array $results): array
    {
        $patterns = [];

        // Padrão 1: Vendedores com múltiplos anúncios sem marca
        $sellersWithGaps = [];
        foreach ($results['gaps_detected'] as $gap) {
            $sellerId = $gap['seller_id'] ?? null;
            if ($sellerId) {
                if (!isset($sellersWithGaps[$sellerId])) {
                    $sellersWithGaps[$sellerId] = 0;
                }
                $sellersWithGaps[$sellerId]++;
            }
        }

        foreach ($sellersWithGaps as $sellerId => $count) {
            if ($count >= 3) {
                $patterns[] = [
                    'type' => 'seller_multiple_gaps',
                    'seller_id' => $sellerId,
                    'count' => $count,
                    'description' => "Vendedor com {$count} anúncios sem marca definida",
                ];
            }
        }

        // Padrão 2: Títulos similares com e sem marca
        $titlesWithoutBrand = [];
        $titlesWithBrand = [];

        foreach ($results['items'] as $item) {
            $normalizedTitle = $this->normalizeTitle($item['title'] ?? '');
            $hasBrand = $item['brand_analysis']['has_brand'] ?? false;

            if ($hasBrand) {
                $titlesWithBrand[$normalizedTitle] = $item['id'];
            } else {
                $titlesWithoutBrand[$normalizedTitle] = $item['id'];
            }
        }

        // Encontrar títulos similares
        foreach ($titlesWithoutBrand as $title => $itemId) {
            foreach ($titlesWithBrand as $brandedTitle => $brandedItemId) {
                $similarity = similar_text($title, $brandedTitle, $percent);
                if ($percent > 80) {
                    $patterns[] = [
                        'type' => 'similar_titles_different_brand_status',
                        'item_without_brand' => $itemId,
                        'item_with_brand' => $brandedItemId,
                        'similarity' => round($percent, 2),
                        'description' => 'Títulos similares com status de marca diferente',
                    ];
                }
            }
        }

        return $patterns;
    }

    /**
     * Normaliza título para comparação
     */
    private function normalizeTitle(string $title): string
    {
        $title = strtolower($title);
        $title = preg_replace('/\s+/', ' ', $title);
        $title = preg_replace('/[^a-z0-9\s]/', '', $title);
        return trim($title);
    }

    /**
     * Análise rápida (menos detalhes, mais rápido)
     */
    public function quickAnalysis(array $options = []): array
    {
        $categoryId = $options['category'] ?? 'MLB214858'; // Acessórios para Motos
        $maxResults = $options['max_results'] ?? 100;

        $items = [];
        $withBrand = 0;
        $withoutBrand = 0;

        // Buscar apenas pela marca principal
        $searchResults = $this->searchBrandItems($categoryId, 'AWA', $maxResults);

        foreach ($searchResults as $item) {
            $hasBrand = false;

            // Verificar se tem atributo BRAND na resposta da busca
            foreach ($item['attributes'] ?? [] as $attr) {
                if ($attr['id'] === 'BRAND') {
                    $hasBrand = true;
                    break;
                }
            }

            if ($hasBrand) {
                $withBrand++;
            } else {
                $withoutBrand++;
            }

            $items[] = [
                'id' => $item['id'],
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? 0,
                'has_brand' => $hasBrand,
                'permalink' => $item['permalink'] ?? null,
            ];
        }

        $total = count($items);

        return [
            'brand' => 'AWA',
            'category' => $categoryId,
            'analysis_date' => date('Y-m-d H:i:s'),
            'total' => $total,
            'with_brand' => $withBrand,
            'without_brand' => $withoutBrand,
            'consistency_score' => $total > 0 ? round(($withBrand / $total) * 100, 2) : 0,
            'items' => $items,
        ];
    }

    /**
     * Compara a marca AWA com concorrentes na mesma categoria
     */
    public function compareWithCompetitors(string $categoryId, array $competitorBrands = []): array
    {
        // Marcas concorrentes padrão no segmento de motos
        $defaultCompetitors = ['PRO TORK', 'TEXX', 'LS2', 'SHIRO', 'HELT', 'NORISK'];
        $competitors = !empty($competitorBrands) ? $competitorBrands : $defaultCompetitors;

        $comparison = [
            'category_id' => $categoryId,
            'analysis_date' => date('Y-m-d H:i:s'),
            'awa' => $this->getBrandMetrics($categoryId, 'AWA'),
            'competitors' => [],
        ];

        foreach ($competitors as $brand) {
            $comparison['competitors'][$brand] = $this->getBrandMetrics($categoryId, $brand);
        }

        // Calcular ranking
        $allBrands = array_merge(
            [['brand' => 'AWA', 'data' => $comparison['awa']]],
            array_map(fn($brand, $data) => ['brand' => $brand, 'data' => $data], array_keys($comparison['competitors']), $comparison['competitors'])
        );

        usort($allBrands, fn($a, $b) => ($b['data']['total_listings'] ?? 0) - ($a['data']['total_listings'] ?? 0));

        $comparison['ranking'] = array_map(function ($item, $index) {
            return [
                'position' => $index + 1,
                'brand' => $item['brand'],
                'listings' => $item['data']['total_listings'] ?? 0,
                'avg_price' => $item['data']['avg_price'] ?? 0,
            ];
        }, $allBrands, array_keys($allBrands));

        // Posição da AWA no ranking
        foreach ($comparison['ranking'] as $item) {
            if ($item['brand'] === 'AWA') {
                $comparison['awa_position'] = $item['position'];
                break;
            }
        }

        return $comparison;
    }

    /**
     * Obtém métricas de uma marca em uma categoria
     */
    private function getBrandMetrics(string $categoryId, string $brand): array
    {
        $cacheKey = "brand_metrics:{$categoryId}:{$brand}";

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        $items = $this->searchBrandItems($categoryId, $brand, 200);

        $prices = [];
        $freeShipping = 0;
        $newCondition = 0;

        foreach ($items as $item) {
            if (isset($item['price']) && $item['price'] > 0) {
                $prices[] = $item['price'];
            }
            if ($item['shipping']['free_shipping'] ?? false) {
                $freeShipping++;
            }
            if (($item['condition'] ?? '') === 'new') {
                $newCondition++;
            }
        }

        $total = count($items);
        $metrics = [
            'brand' => $brand,
            'total_listings' => $total,
            'avg_price' => $total > 0 && count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0,
            'min_price' => count($prices) > 0 ? min($prices) : 0,
            'max_price' => count($prices) > 0 ? max($prices) : 0,
            'free_shipping_percentage' => $total > 0 ? round(($freeShipping / $total) * 100, 2) : 0,
            'new_condition_percentage' => $total > 0 ? round(($newCondition / $total) * 100, 2) : 0,
        ];

        $this->cache->set($cacheKey, $metrics, 1800); // Cache 30 min

        return $metrics;
    }

    /**
     * Analisa tendências da marca ao longo do tempo
     */
    public function analyzeTrends(int $days = 30): array
    {
        if (!$this->db) {
            return ['error' => 'Database not available'];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE(analysis_date) as date,
                    total_listings,
                    listings_with_brand,
                    listings_without_brand,
                    consistency_score,
                    health_score,
                    gaps_count,
                    inconsistencies_count,
                    sellers_count
                FROM brand_analysis_history
                WHERE brand = 'AWA'
                  AND analysis_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY analysis_date ASC
            ");

            $stmt->execute([$days]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($history) < 2) {
                return [
                    'message' => 'Dados insuficientes para análise de tendências',
                    'data_points' => count($history),
                    'history' => $history,
                ];
            }

            // Calcular tendências
            $firstRecord = $history[0];
            $lastRecord = $history[count($history) - 1];

            $trends = [
                'period' => "{$days} dias",
                'data_points' => count($history),
                'metrics' => [
                    'total_listings' => [
                        'start' => $firstRecord['total_listings'],
                        'end' => $lastRecord['total_listings'],
                        'change' => $lastRecord['total_listings'] - $firstRecord['total_listings'],
                        'change_percentage' => $firstRecord['total_listings'] > 0
                            ? round((($lastRecord['total_listings'] - $firstRecord['total_listings']) / $firstRecord['total_listings']) * 100, 2)
                            : 0,
                    ],
                    'consistency_score' => [
                        'start' => $firstRecord['consistency_score'],
                        'end' => $lastRecord['consistency_score'],
                        'change' => round($lastRecord['consistency_score'] - $firstRecord['consistency_score'], 2),
                        'trend' => $lastRecord['consistency_score'] > $firstRecord['consistency_score'] ? 'improving' : 'declining',
                    ],
                    'health_score' => [
                        'start' => $firstRecord['health_score'],
                        'end' => $lastRecord['health_score'],
                        'change' => $lastRecord['health_score'] - $firstRecord['health_score'],
                        'trend' => $lastRecord['health_score'] > $firstRecord['health_score'] ? 'improving' : 'declining',
                    ],
                    'gaps' => [
                        'start' => $firstRecord['gaps_count'],
                        'end' => $lastRecord['gaps_count'],
                        'change' => $lastRecord['gaps_count'] - $firstRecord['gaps_count'],
                        'trend' => $lastRecord['gaps_count'] < $firstRecord['gaps_count'] ? 'improving' : 'declining',
                    ],
                ],
                'timeline' => $history,
            ];

            return $trends;
        } catch (\Exception $e) {
            log_error('Erro ao analisar tendências de marca', [
                'error' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Gera alertas baseados na análise
     */
    public function generateAlerts(array $results): array
    {
        $alerts = [];

        // Alerta: Score de consistência baixo
        if (($results['brand_consistency_score'] ?? 100) < 70) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Score de Consistência Crítico',
                'message' => "O score de consistência da marca está em {$results['brand_consistency_score']}%, abaixo do mínimo recomendado de 70%.",
                'action' => 'Revisar e corrigir anúncios sem atributo de marca',
            ];
        }

        // Alerta: Muitas lacunas
        $gapsCount = count($results['gaps_detected'] ?? []);
        if ($gapsCount > 20) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Muitas Lacunas Detectadas',
                'message' => "{$gapsCount} anúncios encontrados com lacunas de dados.",
                'action' => 'Priorizar correção dos anúncios com marca no título mas não no atributo',
            ];
        }

        // Alerta: Inconsistências na marca
        $inconsistenciesCount = count($results['inconsistencies'] ?? []);
        if ($inconsistenciesCount > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Marca Incorreta Detectada',
                'message' => "{$inconsistenciesCount} anúncios com a marca preenchida incorretamente.",
                'action' => 'Corrigir imediatamente os anúncios com marca errada',
            ];
        }

        // Alerta: Frete grátis baixo
        $freeShippingPct = $results['shipping_analysis']['free_shipping']['percentage'] ?? 0;
        if ($freeShippingPct < 40) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Frete Grátis Abaixo do Recomendado',
                'message' => "Apenas {$freeShippingPct}% dos anúncios oferecem frete grátis.",
                'action' => 'Considerar aumentar ofertas de frete grátis para melhorar ranking',
            ];
        }

        // Alerta: Pouco uso do Full
        $fullShippingPct = $results['shipping_analysis']['full_shipping']['percentage'] ?? 0;
        if ($fullShippingPct < 15) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Baixo Uso do Mercado Envios Full',
                'message' => "Apenas {$fullShippingPct}% dos anúncios utilizam Mercado Envios Full.",
                'action' => 'Anúncios Full têm maior visibilidade e conversão',
            ];
        }

        // Alerta: Score de saúde
        $healthScore = $results['summary']['health_status']['score'] ?? 100;
        if ($healthScore < 60) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Saúde da Marca Comprometida',
                'message' => "O score de saúde da marca está em {$healthScore}/100.",
                'action' => 'Implementar urgentemente as recomendações de melhoria',
            ];
        }

        return $alerts;
    }

    /**
     * Identifica produtos mais vendidos da marca
     */
    public function getTopSellingProducts(string $categoryId = 'MLB214858', int $limit = 20): array
    {
        $items = $this->searchBrandItems($categoryId, 'AWA', 200);

        // Ordenar por quantidade vendida
        usort($items, function ($a, $b) {
            return ($b['sold_quantity'] ?? 0) - ($a['sold_quantity'] ?? 0);
        });

        $topProducts = array_slice($items, 0, $limit);

        return array_map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? 0,
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'available_quantity' => $item['available_quantity'] ?? 0,
                'permalink' => $item['permalink'] ?? null,
                'thumbnail' => $item['thumbnail'] ?? null,
                'condition' => $item['condition'] ?? 'unknown',
                'free_shipping' => $item['shipping']['free_shipping'] ?? false,
            ];
        }, $topProducts);
    }

    /**
     * Analisa oportunidades de mercado para a marca AWA
     */
    public function analyzeOpportunities(string $categoryId = 'MLB214858'): array
    {
        $awaItems = $this->searchBrandItems($categoryId, 'AWA', 500);

        // Coletar dados de preço e tipo de produto
        $awaPrices = [];
        $awaProducts = [];

        foreach ($awaItems as $item) {
            $price = $item['price'] ?? 0;
            if ($price > 0) {
                $awaPrices[] = $price;
            }
            $awaProducts[] = $this->normalizeTitle($item['title'] ?? '');
        }

        // Buscar produtos gerais da categoria para comparação
        $categoryResponse = $this->client->get("/sites/{$this->siteId}/search", [
            'category' => $categoryId,
            'limit' => 50,
            'sort' => 'sold_quantity_desc',
        ], 300);

        $opportunities = [];
        $categoryItems = $categoryResponse['results'] ?? [];

        foreach ($categoryItems as $item) {
            $normalizedTitle = $this->normalizeTitle($item['title'] ?? '');

            // Verificar se AWA já tem produto similar
            $hasAwaEquivalent = false;
            foreach ($awaProducts as $awaProduct) {
                $similarity = similar_text($normalizedTitle, $awaProduct, $percent);
                if ($percent > 60) {
                    $hasAwaEquivalent = true;
                    break;
                }
            }

            if (!$hasAwaEquivalent && ($item['sold_quantity'] ?? 0) > 10) {
                $opportunities[] = [
                    'type' => 'product_gap',
                    'title' => $item['title'] ?? '',
                    'price' => $item['price'] ?? 0,
                    'sold_quantity' => $item['sold_quantity'] ?? 0,
                    'brand' => $this->extractBrand($item),
                    'description' => 'Produto popular sem equivalente AWA identificado',
                ];
            }
        }

        // Análise de faixa de preço
        $avgAwaPrice = count($awaPrices) > 0 ? array_sum($awaPrices) / count($awaPrices) : 0;
        $minAwaPrice = count($awaPrices) > 0 ? min($awaPrices) : 0;
        $maxAwaPrice = count($awaPrices) > 0 ? max($awaPrices) : 0;

        return [
            'category_id' => $categoryId,
            'analysis_date' => date('Y-m-d H:i:s'),
            'awa_presence' => [
                'total_products' => count($awaItems),
                'avg_price' => round($avgAwaPrice, 2),
                'price_range' => [
                    'min' => $minAwaPrice,
                    'max' => $maxAwaPrice,
                ],
            ],
            'product_gaps' => array_slice($opportunities, 0, 10),
            'recommendations' => $this->generateOpportunityRecommendations($opportunities, $awaPrices),
        ];
    }

    /**
     * Extrai marca de um item
     */
    private function extractBrand(array $item): string
    {
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'BRAND') {
                return $attr['value_name'] ?? 'Desconhecida';
            }
        }
        return 'Desconhecida';
    }

    /**
     * Gera recomendações baseadas em oportunidades
     */
    private function generateOpportunityRecommendations(array $opportunities, array $awaPrices): array
    {
        $recommendations = [];

        if (count($opportunities) > 5) {
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Expandir Portfólio',
                'description' => count($opportunities) . ' produtos populares sem equivalente AWA identificados.',
                'action' => 'Analisar viabilidade de lançamento de produtos similares',
            ];
        }

        $avgPrice = count($awaPrices) > 0 ? array_sum($awaPrices) / count($awaPrices) : 0;

        foreach ($opportunities as $opp) {
            if (($opp['price'] ?? 0) < $avgPrice * 0.7) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'title' => 'Oportunidade de Preço',
                    'description' => "Produto '{$opp['title']}' tem preço abaixo da média AWA.",
                    'action' => 'Avaliar possibilidade de competir nesta faixa de preço',
                ];
                break; // Apenas uma recomendação deste tipo
            }
        }

        return $recommendations;
    }

    /**
     * Obtém estatísticas por vendedor da marca AWA
     */
    public function getSellerStatistics(): array
    {
        $analysis = $this->analyzeAwaBrand(['max_results' => 500, 'include_details' => true]);
        $sellers = $analysis['sellers'] ?? [];

        $statistics = [];

        foreach ($sellers as $sellerId => $seller) {
            // Calcular métricas adicionais
            $itemsWithBrand = 0;
            $itemsWithoutBrand = 0;
            $totalSales = 0;
            $prices = [];

            foreach ($analysis['items'] as $item) {
                if (($item['seller_id'] ?? null) == $sellerId) {
                    if ($item['brand_analysis']['has_brand'] ?? false) {
                        $itemsWithBrand++;
                    } else {
                        $itemsWithoutBrand++;
                    }
                    $totalSales += $item['sold_quantity'] ?? 0;
                    if (isset($item['price'])) {
                        $prices[] = $item['price'];
                    }
                }
            }

            $statistics[] = [
                'seller_id' => $sellerId,
                'nickname' => $seller['nickname'] ?? 'Desconhecido',
                'total_items' => $seller['items_count'] ?? 0,
                'items_with_brand' => $itemsWithBrand,
                'items_without_brand' => $itemsWithoutBrand,
                'brand_compliance' => $seller['items_count'] > 0
                    ? round(($itemsWithBrand / $seller['items_count']) * 100, 2)
                    : 0,
                'total_sales' => $totalSales,
                'avg_price' => count($prices) > 0 ? round(array_sum($prices) / count($prices), 2) : 0,
                'reputation' => $seller['seller_reputation'] ?? null,
                'location' => $seller['address'] ?? null,
            ];
        }

        // Ordenar por quantidade de itens
        usort($statistics, fn($a, $b) => $b['total_items'] - $a['total_items']);

        return [
            'analysis_date' => date('Y-m-d H:i:s'),
            'total_sellers' => count($statistics),
            'sellers' => $statistics,
            'summary' => [
                'avg_items_per_seller' => count($statistics) > 0
                    ? round(array_sum(array_column($statistics, 'total_items')) / count($statistics), 2)
                    : 0,
                'avg_brand_compliance' => count($statistics) > 0
                    ? round(array_sum(array_column($statistics, 'brand_compliance')) / count($statistics), 2)
                    : 0,
                'total_sales' => array_sum(array_column($statistics, 'total_sales')),
            ],
        ];
    }
}
