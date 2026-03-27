<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * RealMarketDataService - Serviço para dados reais de mercado do Mercado Livre
 *
 * Integra com APIs públicas e autenticadas do ML para:
 * - Análise de concorrentes
 * - Preços de mercado
 * - Tendências de categoria
 * - Qualidade de anúncios
 * - Estatísticas de vendas
 */
class RealMarketDataService
{
    private PDO $db;
    private MercadoLivreClient $mlClient;
    private ?int $accountId;
    private CacheService $cache;

    private const CACHE_TTL_SHORT = 300;      // 5 minutos
    private const CACHE_TTL_MEDIUM = 1800;    // 30 minutos
    private const CACHE_TTL_LONG = 86400;     // 24 horas

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();
    }

    /**
     * Análise completa de mercado para uma categoria
     */
    public function analyzeMarket(string $categoryId, ?string $keyword = null): array
    {
        $cacheKey = "market_analysis:{$categoryId}:" . md5($keyword ?? '');

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = [
            'success' => true,
            'category_id' => $categoryId,
            'keyword' => $keyword,
            'analyzed_at' => date('Y-m-d H:i:s'),
        ];

        // 1. Dados da categoria
        $result['category'] = $this->getCategoryDetails($categoryId);

        // 2. Análise de preços do mercado
        $result['pricing'] = $this->analyzePricing($categoryId, $keyword);

        // 3. Top sellers e tendências
        $result['trends'] = $this->getTrends($categoryId);

        // 4. Análise de concorrentes
        $result['competitors'] = $this->analyzeCompetitors($categoryId, $keyword);

        // 5. Filtros disponíveis (para entender o mercado)
        $result['filters'] = $this->getAvailableFilters($categoryId, $keyword);

        $this->cache->set($cacheKey, $result, self::CACHE_TTL_MEDIUM);

        return $result;
    }

    /**
     * Detalhes da categoria com path e atributos
     */
    public function getCategoryDetails(string $categoryId): array
    {
        $cacheKey = "category_details:{$categoryId}";

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $category = $this->mlClient->getCategory($categoryId);

        if (empty($category)) {
            return ['error' => 'Categoria não encontrada'];
        }

        $attributes = $this->mlClient->getCategoryAttributes($categoryId);

        $result = [
            'id' => $category['id'] ?? $categoryId,
            'name' => $category['name'] ?? '',
            'path_from_root' => array_map(function (array $p): array {
                return ['id' => $p['id'] ?? '', 'name' => $p['name'] ?? ''];
            }, $category['path_from_root'] ?? []),
            'total_items_in_this_category' => $category['total_items_in_this_category'] ?? null,
            'attribute_count' => count($attributes),
            'required_attributes' => array_filter(
                $attributes,
                fn(array $a): bool => ($a['tags']['required'] ?? false) ||
                    in_array('required', $a['tags'] ?? [])
            ),
            'filter_attributes' => array_filter(
                $attributes,
                fn(array $a): bool => ($a['tags']['allow_variations'] ?? false) ||
                    ($a['tags']['defines_picture'] ?? false)
            ),
        ];

        $this->cache->set($cacheKey, $result, self::CACHE_TTL_LONG);

        return $result;
    }

    /**
     * Análise de preços do mercado
     * Usa dados do banco local quando a API de search está bloqueada
     */
    public function analyzePricing(string $categoryId, ?string $keyword = null, int $sampleSize = 50): array
    {
        // Primeiro tentar API de search
        $params = [
            'category' => $categoryId,
            'limit' => $sampleSize,
            'sort' => 'relevance',
        ];

        if ($keyword) {
            $params['q'] = $keyword;
        }

        $searchResult = $this->mlClient->searchItems($params, self::CACHE_TTL_SHORT);

        // Se a API retornou erro (ex: forbidden), usar dados locais
        if (isset($searchResult['error']) || empty($searchResult['results'])) {
            return $this->analyzePricingFromLocalData($categoryId, $keyword, $sampleSize);
        }

        $items = $searchResult['results'];
        return $this->processPricingData($items, $searchResult['paging'] ?? []);
    }

    /**
     * Análise de preços usando dados do banco local
     */
    private function analyzePricingFromLocalData(string $categoryId, ?string $keyword = null, int $limit = 50): array
    {
        $where = ['i.category_id = :category_id', 'i.status = :status'];
        $params = [
            ':category_id' => $categoryId,
            ':status' => 'active',
        ];

        if ($keyword) {
            $where[] = 'i.title LIKE :keyword';
            $params[':keyword'] = '%' . $keyword . '%';
        }

        $whereSql = implode(' AND ', $where);

        $limitSql = max(1, min((int)$limit, 500));

        $stmt = $this->db->prepare("
            SELECT i.price, i.data
            FROM items i
            WHERE {$whereSql}
            ORDER BY i.updated_at DESC
            LIMIT {$limitSql}
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return [
                'sample_size' => 0,
                'source' => 'local_db',
                'message' => 'Sem dados locais para esta categoria. Sincronize os anúncios primeiro.',
            ];
        }

        $items = [];
        foreach ($rows as $row) {
            $data = json_decode($row['data'] ?? '{}', true) ?: [];
            $items[] = array_merge($data, ['price' => (float)$row['price']]);
        }

        $result = $this->processPricingData($items, ['total' => count($items)]);
        $result['source'] = 'local_db';

        return $result;
    }

    /**
     * Processa dados de pricing (usado tanto para API quanto para dados locais)
     */
    private function processPricingData(array $items, array $paging): array
    {
        $prices = [];
        $shippingFree = 0;
        $fullItems = 0;
        $officialStores = 0;
        $catalogProducts = 0;

        foreach ($items as $item) {
            $price = (float)($item['price'] ?? 0);
            if ($price > 0) {
                $prices[] = $price;
            }

            // Frete grátis
            if (($item['shipping']['free_shipping'] ?? false) === true) {
                $shippingFree++;
            }

            // Full (fulfillment)
            $tags = $item['shipping']['tags'] ?? [];
            if (in_array('fulfillment', $tags) || in_array('self_service_in', $tags)) {
                $fullItems++;
            }

            // Loja oficial
            if (!empty($item['official_store_id'])) {
                $officialStores++;
            }

            // Catálogo
            if (!empty($item['catalog_product_id'])) {
                $catalogProducts++;
            }
        }

        $totalItems = count($items);

        if (empty($prices)) {
            return [
                'sample_size' => $totalItems,
                'error' => 'Sem dados de preços disponíveis'
            ];
        }

        sort($prices);
        $count = count($prices);

        return [
            'sample_size' => $totalItems,
            'paging' => $paging,
            'price_stats' => [
                'min' => min($prices),
                'max' => max($prices),
                'avg' => round(array_sum($prices) / $count, 2),
                'median' => $count % 2 === 0
                    ? ($prices[(int) ($count / 2) - 1] + $prices[(int) ($count / 2)]) / 2
                    : $prices[(int) floor($count / 2)],
                'p10' => $prices[(int)floor($count * 0.1)] ?? $prices[0],
                'p25' => $prices[(int)floor($count * 0.25)] ?? $prices[0],
                'p75' => $prices[(int)floor($count * 0.75)] ?? end($prices),
                'p90' => $prices[(int)floor($count * 0.90)] ?? end($prices),
            ],
            'market_features' => [
                'free_shipping_percent' => $totalItems > 0 ? round(($shippingFree / $totalItems) * 100, 1) : 0,
                'full_percent' => $totalItems > 0 ? round(($fullItems / $totalItems) * 100, 1) : 0,
                'official_stores_percent' => $totalItems > 0 ? round(($officialStores / $totalItems) * 100, 1) : 0,
                'catalog_percent' => $totalItems > 0 ? round(($catalogProducts / $totalItems) * 100, 1) : 0,
            ],
            'recommendations' => $totalItems > 0
                ? $this->generatePricingRecommendations($prices, $shippingFree, $fullItems, $totalItems)
                : [],
        ];
    }

    /**
     * Gera recomendações de preço baseado no mercado
     */
    private function generatePricingRecommendations(array $prices, int $freeShipping, int $fullItems, int $total): array
    {
        $recommendations = [];
        $avg = array_sum($prices) / count($prices);
        $median = $prices[(int) floor(count($prices) / 2)];

        // Recomendação de preço competitivo
        $recommendations['competitive_price'] = [
            'value' => round($median * 0.95, 2),
            'description' => 'Preço 5% abaixo da mediana para ganhar destaque',
        ];

        // Recomendação de preço premium
        $recommendations['premium_price'] = [
            'value' => round($prices[(int)floor(count($prices) * 0.75)], 2),
            'description' => 'Preço no percentil 75 para posicionamento premium',
        ];

        // Frete grátis
        $freeShippingPercent = ($freeShipping / $total) * 100;
        if ($freeShippingPercent > 70) {
            $recommendations['shipping'] = [
                'action' => 'REQUIRED',
                'description' => 'Frete grátis é padrão nesta categoria (' . round($freeShippingPercent) . '%)',
            ];
        } elseif ($freeShippingPercent > 40) {
            $recommendations['shipping'] = [
                'action' => 'RECOMMENDED',
                'description' => 'Frete grátis é comum nesta categoria (' . round($freeShippingPercent) . '%)',
            ];
        }

        // Full
        $fullPercent = ($fullItems / $total) * 100;
        if ($fullPercent > 50) {
            $recommendations['fulfillment'] = [
                'action' => 'RECOMMENDED',
                'description' => 'Full/Fulfillment usado por ' . round($fullPercent) . '% dos anúncios',
            ];
        }

        return $recommendations;
    }

    /**
     * Tendências da categoria
     * Usa domain_discovery como fallback quando trends API está bloqueada
     */
    public function getTrends(string $categoryId): array
    {
        // Tentar API de trends primeiro
        $trends = $this->mlClient->getTrends($categoryId);

        if (!empty($trends)) {
            return [
                'keywords' => array_slice($trends, 0, 20),
                'total' => count($trends),
                'source' => 'ml_trends_api'
            ];
        }

        // Fallback: usar domain_discovery para descobrir categorias relacionadas
        $categoryInfo = $this->mlClient->getCategory($categoryId);
        $categoryName = $categoryInfo['name'] ?? '';

        if ($categoryName) {
            $domains = $this->discoverRelatedDomains($categoryName);
            if (!empty($domains)) {
                return [
                    'keywords' => array_column($domains, 'domain_name'),
                    'related_categories' => $domains,
                    'total' => count($domains),
                    'source' => 'domain_discovery'
                ];
            }
        }

        // Fallback final: extrair keywords dos atributos da categoria
        $attributes = $this->mlClient->getCategoryAttributes($categoryId);
        $keywords = $this->extractKeywordsFromAttributes($attributes);

        return [
            'keywords' => array_slice($keywords, 0, 20),
            'total' => count($keywords),
            'source' => 'category_attributes',
            'message' => 'Keywords extraídas dos atributos da categoria'
        ];
    }

    /**
     * Descobre domínios relacionados via API domain_discovery
     */
    public function discoverRelatedDomains(string $query): array
    {
        $cacheKey = "domain_discovery:" . md5($query);

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = $this->mlClient->get('/sites/MLB/domain_discovery/search', ['q' => $query], self::CACHE_TTL_MEDIUM, true);

        if (isset($result['error']) || !is_array($result)) {
            return [];
        }

        $domains = [];
        foreach ($result as $domain) {
            if (!is_array($domain)) continue;

            $domains[] = [
                'domain_id' => $domain['domain_id'] ?? '',
                'domain_name' => $domain['domain_name'] ?? '',
                'category_id' => $domain['category_id'] ?? '',
                'category_name' => $domain['category_name'] ?? '',
            ];
        }

        $this->cache->set($cacheKey, $domains, self::CACHE_TTL_MEDIUM);

        return $domains;
    }

    /**
     * Extrai keywords úteis dos atributos da categoria
     */
    private function extractKeywordsFromAttributes(array $attributes): array
    {
        $keywords = [];

        foreach ($attributes as $attr) {
            // Nome do atributo
            $name = $attr['name'] ?? '';
            if ($name && mb_strlen($name) >= 3) {
                $keywords[] = $name;
            }

            // Valores dos atributos (marcas, modelos, etc)
            foreach (($attr['values'] ?? []) as $value) {
                $valueName = $value['name'] ?? '';
                if ($valueName && mb_strlen($valueName) >= 3 && mb_strlen($valueName) <= 50) {
                    $keywords[] = $valueName;
                }
            }
        }

        // Remover duplicatas e limitar
        return array_slice(array_unique($keywords), 0, 50);
    }

    /**
     * Análise de concorrentes
     */
    public function analyzeCompetitors(string $categoryId, ?string $keyword = null, int $limit = 20): array
    {
        $params = [
            'category' => $categoryId,
            'limit' => $limit,
            'sort' => 'sold_quantity_desc', // Mais vendidos primeiro
        ];

        if ($keyword) {
            $params['q'] = $keyword;
        }

        $searchResult = $this->mlClient->searchItems($params, self::CACHE_TTL_SHORT);

        if (isset($searchResult['error']) || empty($searchResult['results'])) {
            return ['competitors' => [], 'error' => 'Não foi possível analisar concorrentes'];
        }

        $competitors = [];
        $sellerIds = [];

        foreach ($searchResult['results'] as $item) {
            $sellerId = $item['seller']['id'] ?? null;

            // Evitar duplicar sellers
            if ($sellerId && in_array($sellerId, $sellerIds)) {
                continue;
            }
            if ($sellerId) {
                $sellerIds[] = $sellerId;
            }

            $competitors[] = [
                'item_id' => $item['id'] ?? '',
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? 0,
                'original_price' => $item['original_price'] ?? null,
                'discount_percent' => $item['original_price']
                    ? round((1 - $item['price'] / $item['original_price']) * 100)
                    : 0,
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'condition' => $item['condition'] ?? 'new',
                'listing_type' => $item['listing_type_id'] ?? '',
                'seller' => [
                    'id' => $sellerId,
                    'nickname' => $item['seller']['nickname'] ?? null,
                    'power_seller_status' => $item['seller']['power_seller_status'] ?? null,
                    'reputation_level' => $item['seller']['seller_reputation']['level_id'] ?? null,
                ],
                'shipping' => [
                    'free' => $item['shipping']['free_shipping'] ?? false,
                    'mode' => $item['shipping']['mode'] ?? null,
                    'tags' => $item['shipping']['tags'] ?? [],
                ],
                'catalog_product_id' => $item['catalog_product_id'] ?? null,
                'official_store' => !empty($item['official_store_id']),
                'permalink' => $item['permalink'] ?? '',
            ];

            if (count($competitors) >= 10) {
                break; // Top 10 competitors
            }
        }

        return [
            'competitors' => $competitors,
            'total_in_category' => $searchResult['paging']['total'] ?? 0,
            'insights' => $this->generateCompetitorInsights($competitors),
        ];
    }

    /**
     * Insights sobre os concorrentes
     */
    private function generateCompetitorInsights(array $competitors): array
    {
        if (empty($competitors)) {
            return [];
        }

        $insights = [];

        // Preço médio dos top sellers
        $prices = array_column($competitors, 'price');
        $insights['avg_top_seller_price'] = round(array_sum($prices) / count($prices), 2);

        // % com frete grátis
        $freeShipping = count(array_filter($competitors, fn(array $c): bool => $c['shipping']['free'] ?? false));
        $insights['free_shipping_rate'] = round(($freeShipping / count($competitors)) * 100);

        // % com desconto
        $withDiscount = count(array_filter($competitors, fn(array $c): bool => ($c['discount_percent'] ?? 0) > 0));
        $insights['discount_rate'] = round(($withDiscount / count($competitors)) * 100);

        // % Full
        $fullCount = count(array_filter($competitors, function (array $c): bool {
            $tags = $c['shipping']['tags'] ?? [];
            return in_array('fulfillment', $tags) || in_array('self_service_in', $tags);
        }));
        $insights['full_rate'] = round(($fullCount / count($competitors)) * 100);

        // % no catálogo
        $catalogCount = count(array_filter($competitors, fn(array $c): bool => !empty($c['catalog_product_id'])));
        $insights['catalog_rate'] = round(($catalogCount / count($competitors)) * 100);

        // Vendas médias
        $sales = array_column($competitors, 'sold_quantity');
        $insights['avg_sales'] = round(array_sum($sales) / count($sales));
        $insights['max_sales'] = max($sales);

        return $insights;
    }

    /**
     * Filtros disponíveis na categoria (para entender o mercado)
     */
    public function getAvailableFilters(string $categoryId, ?string $keyword = null): array
    {
        $params = [
            'category' => $categoryId,
            'limit' => 1, // Apenas para pegar os filtros
        ];

        if ($keyword) {
            $params['q'] = $keyword;
        }

        $searchResult = $this->mlClient->searchItems($params, self::CACHE_TTL_MEDIUM);

        if (isset($searchResult['error'])) {
            return ['filters' => [], 'error' => 'Não foi possível obter filtros'];
        }

        $filters = [];
        foreach (($searchResult['available_filters'] ?? []) as $filter) {
            $filterId = $filter['id'] ?? '';
            $filterName = $filter['name'] ?? '';

            $values = [];
            foreach (($filter['values'] ?? []) as $value) {
                $values[] = [
                    'id' => $value['id'] ?? '',
                    'name' => $value['name'] ?? '',
                    'results' => $value['results'] ?? 0,
                ];
            }

            // Ordenar valores por quantidade de resultados
            usort($values, fn($a, $b) => $b['results'] <=> $a['results']);

            $filters[] = [
                'id' => $filterId,
                'name' => $filterName,
                'values' => array_slice($values, 0, 10), // Top 10 valores
                'total_values' => count($filter['values'] ?? []),
            ];
        }

        return [
            'filters' => $filters,
            'total_filters' => count($filters),
        ];
    }

    /**
     * Pesquisa inteligente de produtos similares
     */
    public function findSimilarProducts(string $title, string $categoryId, ?float $basePrice = null): array
    {
        // Extrair keywords do título
        $keywords = $this->extractKeywordsFromTitle($title);

        $params = [
            'category' => $categoryId,
            'q' => implode(' ', array_slice($keywords, 0, 5)), // Top 5 keywords
            'limit' => 30,
            'sort' => 'relevance',
        ];

        $searchResult = $this->mlClient->searchItems($params, self::CACHE_TTL_SHORT);

        if (isset($searchResult['error']) || empty($searchResult['results'])) {
            return ['products' => [], 'error' => 'Nenhum produto similar encontrado'];
        }

        $similar = [];
        foreach ($searchResult['results'] as $item) {
            $similarity = $this->calculateTitleSimilarity($title, $item['title'] ?? '');

            $similar[] = [
                'item_id' => $item['id'] ?? '',
                'title' => $item['title'] ?? '',
                'price' => $item['price'] ?? 0,
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'similarity_score' => $similarity,
                'price_diff' => $basePrice ? round((($item['price'] ?? 0) - $basePrice) / $basePrice * 100, 1) : null,
                'shipping' => [
                    'free' => $item['shipping']['free_shipping'] ?? false,
                ],
                'permalink' => $item['permalink'] ?? '',
            ];
        }

        // Ordenar por similaridade
        usort($similar, fn($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);

        return [
            'products' => array_slice($similar, 0, 10),
            'keywords_used' => array_slice($keywords, 0, 5),
        ];
    }

    /**
     * Extrai keywords importantes do título
     */
    private function extractKeywordsFromTitle(string $title): array
    {
        // Stopwords em português
        $stopwords = [
            'de',
            'da',
            'do',
            'das',
            'dos',
            'para',
            'com',
            'sem',
            'por',
            'em',
            'na',
            'no',
            'nas',
            'nos',
            'um',
            'uma',
            'uns',
            'umas',
            'o',
            'a',
            'os',
            'as',
            'e',
            'ou',
            'que',
            'é',
            'ao',
            'aos',
            'kit',
            'jogo',
            'par',
            'pcs',
            'unid',
            'un',
            'und',
            'peça',
            'peças'
        ];

        // Normalizar e separar
        $title = mb_strtolower($title);
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
        $words = preg_split('/\s+/', $title, -1, PREG_SPLIT_NO_EMPTY);

        // Filtrar stopwords e palavras curtas
        $keywords = array_filter($words, function (string $word) use ($stopwords): bool {
            return mb_strlen($word) >= 3 && !in_array($word, $stopwords);
        });

        return array_values(array_unique($keywords));
    }

    /**
     * Calcula similaridade entre títulos
     */
    private function calculateTitleSimilarity(string $title1, string $title2): float
    {
        $words1 = $this->extractKeywordsFromTitle($title1);
        $words2 = $this->extractKeywordsFromTitle($title2);

        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($union) > 0 ? round(count($intersection) / count($union) * 100, 1) : 0;
    }

    /**
     * Análise de qualidade de anúncio baseada em dados reais
     */
    public function analyzeListingQuality(string $itemId): array
    {
        // Buscar item da API
        $item = $this->mlClient->getItemDetails($itemId);

        // Fallback para dados locais se API falhar
        if (empty($item) || isset($item['error'])) {
            $item = $this->getItemFromLocalDb($itemId);

            if (!$item) {
                return ['success' => false, 'error' => 'Item não encontrado'];
            }
        }

        $scores = [];
        $issues = [];
        $recommendations = [];

        // 1. Título (max 30 pontos)
        $titleScore = $this->scoreTitle($item['title'] ?? '');
        $scores['title'] = $titleScore['score'];
        if ($titleScore['score'] < 25) {
            if (!empty($titleScore['recommendation'])) {
                $recommendations[] = $titleScore['recommendation'];
            }
            if (!empty($titleScore['issues'])) {
                foreach ($titleScore['issues'] as $issue) {
                    $issues[] = ['category' => 'title', 'message' => $issue, 'severity' => 'warning'];
                }
            }
        }

        // 2. Descrição (max 20 pontos)
        // scoreDescription() expects the full description_data array from ML API
        // (with plain_text, text keys). After getItemDetails() fix, raw array
        // is in 'description_data', while 'description' is plain text string.
        $descData = $item['description_data'] ?? [];
        if (empty($descData) && is_string($item['description'] ?? null)) {
            // Fallback: wrap plain text string so scoreDescription() can score it
            $descData = ['plain_text' => $item['description']];
        }
        $descScore = $this->scoreDescription($descData);
        $scores['description'] = $descScore['score'];
        if ($descScore['score'] < 15) {
            if (!empty($descScore['recommendation'])) {
                $recommendations[] = $descScore['recommendation'];
            }
            if (!empty($descScore['issues'])) {
                foreach ($descScore['issues'] as $issue) {
                    $issues[] = ['category' => 'description', 'message' => $issue, 'severity' => 'warning'];
                }
            }
        }

        // 3. Imagens (max 20 pontos)
        $imageScore = $this->scoreImages($item['pictures'] ?? []);
        $scores['images'] = $imageScore['score'];
        if ($imageScore['score'] < 15) {
            if (!empty($imageScore['recommendation'])) {
                $recommendations[] = $imageScore['recommendation'];
            }
            if (!empty($imageScore['issues'])) {
                foreach ($imageScore['issues'] as $issue) {
                    $issues[] = ['category' => 'images', 'message' => $issue, 'severity' => 'warning'];
                }
            }
        }

        // 4. Atributos (max 15 pontos)
        $attrScore = $this->scoreAttributes($item);
        $scores['attributes'] = $attrScore['score'];
        if ($attrScore['score'] < 10) {
            if (!empty($attrScore['recommendation'])) {
                $recommendations[] = $attrScore['recommendation'];
            }
            if (!empty($attrScore['issues'])) {
                foreach ($attrScore['issues'] as $issue) {
                    $issues[] = ['category' => 'attributes', 'message' => $issue, 'severity' => 'critical'];
                }
            }
        }

        // 5. Frete (max 10 pontos)
        $shippingScore = $this->scoreShipping($item['shipping'] ?? []);
        $scores['shipping'] = $shippingScore['score'];
        if ($shippingScore['score'] < 8) {
            if (!empty($shippingScore['recommendation'])) {
                $recommendations[] = $shippingScore['recommendation'];
            }
            if (!empty($shippingScore['issues'])) {
                foreach ($shippingScore['issues'] as $issue) {
                    $issues[] = ['category' => 'shipping', 'message' => $issue, 'severity' => 'warning'];
                }
            }
        }

        // 6. Preço/Promoção (max 5 pontos)
        $priceScore = $this->scorePricing($item);
        $scores['pricing'] = $priceScore['score'];

        // Calcular total (normalizado para 100)
        $totalRaw = array_sum($scores);
        $totalScore = round($totalRaw);

        // Definir overall score como média ponderada normalizada
        $overallScore = $totalScore;

        return [
            'success' => true,
            'item_id' => $itemId,
            'overall_score' => $overallScore,
            'total_score' => $totalScore,
            'max_score' => 100,
            'grade' => $this->getGrade($totalScore),
            'scores' => $scores,
            'issues' => $issues,
            'recommendations' => $recommendations,
            'analyzed_at' => date('Y-m-d H:i:s'),
            'source' => isset($item['_source']) ? $item['_source'] : 'api',
        ];
    }

    /**
     * Busca item do banco de dados local
     */
    private function getItemFromLocalDb(string $itemId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                ml_item_id as id,
                title,
                category_id,
                price,
                available_quantity,
                status,
                data
            FROM items
            WHERE ml_item_id = :item_id
        ");
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Decodificar dados JSON adicionais
        $data = json_decode($row['data'] ?? '{}', true) ?: [];

        return array_merge($row, $data, ['_source' => 'local_db']);
    }

    private function scoreTitle(string $title): array
    {
        $length = mb_strlen($title);
        $score = 0;
        $issues = [];

        // Comprimento ideal: 50-60 caracteres
        if ($length >= 50 && $length <= 60) {
            $score += 15;
        } elseif ($length >= 40 && $length <= 70) {
            $score += 10;
            $issues[] = 'Título fora do tamanho ideal (50-60 caracteres)';
        } elseif ($length >= 30) {
            $score += 5;
            $issues[] = 'Título muito curto';
        } else {
            $issues[] = 'Título muito curto (<30 caracteres)';
        }

        // Palavras-chave relevantes (não só genéricas)
        $wordCount = count(explode(' ', trim($title)));
        if ($wordCount >= 5) {
            $score += 10;
        } elseif ($wordCount >= 3) {
            $score += 5;
            $issues[] = 'Adicione mais palavras-chave ao título';
        }

        // Evitar caracteres especiais excessivos
        $specialChars = preg_match_all('/[!@#$%^&*()]/', $title);
        if ($specialChars === 0) {
            $score += 5;
        } elseif ($specialChars <= 2) {
            $score += 2;
        } else {
            $issues[] = 'Remova caracteres especiais desnecessários';
        }

        return [
            'score' => min(30, $score),
            'max' => 30,
            'issues' => $issues,
            'recommendation' => empty($issues) ? null : implode('; ', $issues),
        ];
    }

    private function scoreDescription(array $description): array
    {
        $plainText = $description['plain_text'] ?? '';
        $length = mb_strlen($plainText);
        $score = 0;
        $issues = [];

        // Comprimento mínimo 500 caracteres
        if ($length >= 1000) {
            $score += 10;
        } elseif ($length >= 500) {
            $score += 7;
            $issues[] = 'Descrição poderia ser mais detalhada';
        } elseif ($length >= 200) {
            $score += 3;
            $issues[] = 'Descrição muito curta';
        } else {
            $issues[] = 'Descrição insuficiente (<200 caracteres)';
        }

        // Estruturação (parágrafos, bullets)
        if (strpos($plainText, "\n") !== false) {
            $score += 5;
        } else {
            $issues[] = 'Estruture a descrição com parágrafos';
        }

        // HTML presente
        if (!empty($description['text'])) {
            $score += 5;
        }

        return [
            'score' => min(20, $score),
            'max' => 20,
            'issues' => $issues,
            'recommendation' => empty($issues) ? null : implode('; ', $issues),
        ];
    }

    private function scoreImages(array $pictures): array
    {
        $count = count($pictures);
        $score = 0;
        $issues = [];

        // Quantidade de imagens (ideal 6+)
        if ($count >= 6) {
            $score += 15;
        } elseif ($count >= 4) {
            $score += 10;
            $issues[] = 'Adicione mais imagens (ideal: 6+)';
        } elseif ($count >= 2) {
            $score += 5;
            $issues[] = 'Adicione mais imagens';
        } else {
            $issues[] = 'Imagens insuficientes';
        }

        // Verificar resolução (se disponível)
        $hasHighRes = false;
        foreach ($pictures as $pic) {
            $maxWidth = $pic['max_size'] ?? '';
            if (preg_match('/(\d+)x(\d+)/', $maxWidth, $m)) {
                if ((int)$m[1] >= 1200 && (int)$m[2] >= 1200) {
                    $hasHighRes = true;
                    break;
                }
            }
        }

        if ($hasHighRes) {
            $score += 5;
        } else if ($count > 0) {
            $issues[] = 'Use imagens em alta resolução (1200x1200+)';
        }

        return [
            'score' => min(20, $score),
            'max' => 20,
            'issues' => $issues,
            'recommendation' => empty($issues) ? null : implode('; ', $issues),
        ];
    }

    private function scoreAttributes(array $item): array
    {
        $attributes = $item['attributes'] ?? [];
        $count = count($attributes);
        $score = 0;
        $issues = [];

        // Quantidade de atributos preenchidos
        if ($count >= 15) {
            $score += 10;
        } elseif ($count >= 10) {
            $score += 7;
            $issues[] = 'Preencha mais atributos';
        } elseif ($count >= 5) {
            $score += 4;
            $issues[] = 'Muitos atributos faltando';
        } else {
            $issues[] = 'Preencha os atributos da ficha técnica';
        }

        // Verificar atributos importantes
        $hasBrand = false;
        $hasModel = false;
        $hasGtin = false;

        foreach ($attributes as $attr) {
            $id = strtoupper($attr['id'] ?? '');
            if ($id === 'BRAND') $hasBrand = true;
            if ($id === 'MODEL') $hasModel = true;
            if (in_array($id, ['GTIN', 'EAN', 'UPC'])) $hasGtin = true;
        }

        if ($hasBrand) $score += 2;
        else $issues[] = 'Adicione a marca (BRAND)';

        if ($hasModel) $score += 2;
        else $issues[] = 'Adicione o modelo (MODEL)';

        if ($hasGtin) $score += 1;

        return [
            'score' => min(15, $score),
            'max' => 15,
            'issues' => $issues,
            'recommendation' => empty($issues) ? null : implode('; ', $issues),
        ];
    }

    private function scoreShipping(array $shipping): array
    {
        $score = 0;
        $issues = [];

        // Frete grátis
        if ($shipping['free_shipping'] ?? false) {
            $score += 5;
        } else {
            $issues[] = 'Considere oferecer frete grátis';
        }

        // Full/Fulfillment
        $tags = $shipping['tags'] ?? [];
        if (in_array('fulfillment', $tags) || in_array('self_service_in', $tags)) {
            $score += 5;
        } elseif (in_array('me2', $tags) || $shipping['mode'] === 'me2') {
            $score += 3;
            $issues[] = 'Considere usar Full para entregas mais rápidas';
        } else {
            $issues[] = 'Use Mercado Envios para melhor visibilidade';
        }

        return [
            'score' => min(10, $score),
            'max' => 10,
            'issues' => $issues,
            'recommendation' => empty($issues) ? null : implode('; ', $issues),
        ];
    }

    private function scorePricing(array $item): array
    {
        $score = 0;

        // Tem preço original (desconto)
        if (!empty($item['original_price']) && $item['original_price'] > ($item['price'] ?? 0)) {
            $score += 3;
        }

        // Listing type (gold_special = melhor exposição)
        $listingType = $item['listing_type_id'] ?? '';
        if ($listingType === 'gold_special') {
            $score += 2;
        } elseif ($listingType === 'gold_pro') {
            $score += 1;
        }

        return [
            'score' => min(5, $score),
            'max' => 5,
            'issues' => [],
            'recommendation' => null,
        ];
    }

    private function getGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B+';
        if ($score >= 60) return 'B';
        if ($score >= 50) return 'C';
        if ($score >= 40) return 'D';
        return 'F';
    }

    /**
     * Sugere preço competitivo baseado no mercado
     */
    public function suggestPrice(string $categoryId, string $title, ?string $keyword = null): array
    {
        // Buscar produtos similares
        $similar = $this->findSimilarProducts($title, $categoryId);

        if (empty($similar['products'])) {
            // Fallback: análise geral da categoria
            $pricing = $this->analyzePricing($categoryId, $keyword);

            return [
                'success' => true,
                'method' => 'category_average',
                'suggested_price' => $pricing['price_stats']['median'] ?? null,
                'price_range' => [
                    'min' => $pricing['price_stats']['p25'] ?? null,
                    'max' => $pricing['price_stats']['p75'] ?? null,
                ],
                'confidence' => 'low',
                'message' => 'Baseado na mediana da categoria',
            ];
        }

        // Calcular preço baseado em produtos similares
        $prices = array_column($similar['products'], 'price');
        $avgPrice = array_sum($prices) / count($prices);

        // Ponderar por similaridade
        $weightedSum = 0;
        $weightSum = 0;
        foreach ($similar['products'] as $p) {
            $weight = $p['similarity_score'] / 100;
            $weightedSum += ($p['price'] ?? 0) * $weight;
            $weightSum += $weight;
        }

        $weightedAvg = $weightSum > 0 ? $weightedSum / $weightSum : $avgPrice;

        return [
            'success' => true,
            'method' => 'similar_products',
            'suggested_price' => round($weightedAvg, 2),
            'price_range' => [
                'min' => min($prices),
                'max' => max($prices),
            ],
            'competitive_price' => round($weightedAvg * 0.95, 2),
            'premium_price' => round($weightedAvg * 1.1, 2),
            'confidence' => count($similar['products']) >= 5 ? 'high' : 'medium',
            'based_on' => count($similar['products']) . ' produtos similares',
            'similar_products' => array_slice($similar['products'], 0, 5),
        ];
    }

    // =========================================================================
    // AUTOCOMPLETE & SEARCH HELPERS
    // =========================================================================

    /**
     * Autocomplete de busca usando API do ML
     */
    public function autocomplete(string $query, ?string $categoryId = null): array
    {
        // Usar método existente do MercadoLivreClient
        $suggestions = $this->mlClient->getAutocompleteSuggestions($query, $categoryId);

        $results = [];

        if (!isset($suggestions['error']) && !empty($suggestions['suggested_queries'])) {
            foreach ($suggestions['suggested_queries'] as $suggestion) {
                $text = is_array($suggestion) ? ($suggestion['q'] ?? '') : $suggestion;
                if ($text) {
                    $results[] = [
                        'text' => $text,
                        'type' => 'suggestion',
                    ];
                }
            }
        }

        // Complementar com domain_discovery
        $domains = $this->discoverRelatedDomains($query);
        if (!empty($domains['domains'])) {
            foreach (array_slice($domains['domains'], 0, 5) as $domain) {
                $results[] = [
                    'text' => $domain['category_name'],
                    'type' => 'category',
                    'category_id' => $domain['category_id'],
                ];
            }
        }

        return [
            'success' => true,
            'query' => $query,
            'suggestions' => $results,
        ];
    }

    /**
     * Buscar estatísticas gerais do mercado
     */
    public function getMarketStats(?string $categoryId = null): array
    {
        // Estatísticas da base local
        $params = ['account_id' => $this->accountId];
        $categoryCondition = $categoryId ? "AND category_id = :category_id" : "";
        if ($categoryId) {
            $params['category_id'] = $categoryId;
        }

        // Total de itens
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_items,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_items,
                AVG(price) as avg_price,
                MIN(price) as min_price,
                MAX(price) as max_price,
                COUNT(DISTINCT category_id) as categories,
                SUM(available_quantity) as total_stock
            FROM items
            WHERE account_id = :account_id {$categoryCondition}
        ");
        $stmt->execute($params);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Top categorias
        $stmt = $this->db->prepare("
            SELECT
                category_id,
                category_name,
                COUNT(*) as items_count,
                AVG(price) as avg_price
            FROM items
            WHERE account_id = :account_id {$categoryCondition}
            GROUP BY category_id, category_name
            ORDER BY items_count DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $topCategories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'stats' => [
                'total_items' => (int)($stats['total_items'] ?? 0),
                'active_items' => (int)($stats['active_items'] ?? 0),
                'avg_price' => round((float)($stats['avg_price'] ?? 0), 2),
                'price_range' => [
                    'min' => (float)($stats['min_price'] ?? 0),
                    'max' => (float)($stats['max_price'] ?? 0),
                ],
                'total_categories' => (int)($stats['categories'] ?? 0),
                'total_stock' => (int)($stats['total_stock'] ?? 0),
            ],
            'top_categories' => $topCategories,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obter atributos obrigatórios e recomendados de uma categoria
     */
    public function getCategoryRequirements(string $categoryId): array
    {
        $details = $this->getCategoryDetails($categoryId);

        if (isset($details['error'])) {
            return ['success' => false, 'error' => $details['error']];
        }

        $required = [];
        $recommended = [];
        $optional = [];

        $allAttrs = array_merge(
            $details['required_attributes'] ?? [],
            $details['other_attributes'] ?? []
        );

        foreach ($allAttrs as $attr) {
            $tags = $attr['tags'] ?? [];
            $attrInfo = [
                'id' => $attr['id'] ?? '',
                'name' => $attr['name'] ?? '',
                'value_type' => $attr['value_type'] ?? 'string',
                'hint' => $attr['hint'] ?? $attr['tooltip'] ?? null,
                'allowed_values' => isset($attr['values']) ? array_column($attr['values'], 'name', 'id') : null,
            ];

            if (!empty($tags['required']) || !empty($tags['catalog_required'])) {
                $required[] = $attrInfo;
            } elseif (!empty($tags['hidden'])) {
                $optional[] = $attrInfo;
            } else {
                $recommended[] = $attrInfo;
            }
        }

        return [
            'success' => true,
            'category_id' => $categoryId,
            'category_name' => $details['name'] ?? '',
            'requirements' => [
                'required' => $required,
                'recommended' => array_slice($recommended, 0, 20),
                'optional' => array_slice($optional, 0, 10),
            ],
            'totals' => [
                'required' => count($required),
                'recommended' => count($recommended),
                'optional' => count($optional),
            ],
        ];
    }
}
