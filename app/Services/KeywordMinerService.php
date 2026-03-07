<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * KeywordMinerService - Mineração de keywords da API do Mercado Livre
 * 
 * Usa endpoints públicos disponíveis:
 * - /categories/{ID} - Subcategorias e nomes de produtos
 * - /categories/{ID}/attributes - Valores de atributos
 * - /sites/MLB/domain_discovery/search - Domínios relacionados
 */
class KeywordMinerService
{
    private PDO $db;
    private string $baseUrl = 'https://api.mercadolibre.com';
    private array $cache = [];
    
    // Categorias principais de motos/peças
    private array $motoCategoryIds = [
        'MLB1771' => 'Aces. de Motos e Quadriciclos',
        'MLB243551' => 'Peças de Motos e Quadriciclos',
        'MLB1763' => 'Motos',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Minera keywords de múltiplas fontes
     */
    public function mineKeywords(string $seedTerm, ?string $categoryId = null): array
    {
        $keywords = [
            'seed' => $seedTerm,
            'category_keywords' => [],
            'attribute_keywords' => [],
            'domain_keywords' => [],
            'combined' => [],
        ];

        // 1. Domain Discovery - termos relacionados
        $domains = $this->getDomainDiscovery($seedTerm);
        $keywords['domain_keywords'] = $domains;

        // 2. Se temos categoria, minerar subcategorias e atributos
        if ($categoryId) {
            $keywords['category_keywords'] = $this->getCategoryKeywords($categoryId);
            $keywords['attribute_keywords'] = $this->getAttributeKeywords($categoryId);
        } elseif (!empty($domains)) {
            // Usar categoria do primeiro domínio
            $firstCategory = $domains[0]['category_id'] ?? null;
            if ($firstCategory) {
                $keywords['category_keywords'] = $this->getCategoryKeywords($firstCategory);
                $keywords['attribute_keywords'] = $this->getAttributeKeywords($firstCategory);
            }
        }

        // 3. Combinar e ranquear
        $keywords['combined'] = $this->combineAndRank($keywords);

        return $keywords;
    }

    /**
     * Domain Discovery - encontra domínios/categorias relacionados
     */
    public function getDomainDiscovery(string $term): array
    {
        $cacheKey = "domain_$term";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $url = $this->baseUrl . '/sites/MLB/domain_discovery/search?q=' . urlencode($term);
        $response = $this->apiRequest($url);

        if (!$response || !is_array($response)) {
            return [];
        }

        $keywords = [];
        foreach ($response as $domain) {
            $keywords[] = [
                'keyword' => $domain['domain_name'] ?? '',
                'category' => $domain['category_name'] ?? '',
                'category_id' => $domain['category_id'] ?? '',
                'domain_id' => $domain['domain_id'] ?? '',
                'source' => 'domain_discovery',
                'relevance' => 90, // Alta relevância - veio da busca
            ];
        }

        $this->cache[$cacheKey] = $keywords;
        return $keywords;
    }

    /**
     * Minera keywords das subcategorias
     */
    public function getCategoryKeywords(string $categoryId): array
    {
        $cacheKey = "cat_$categoryId";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $url = $this->baseUrl . '/categories/' . $categoryId;
        $response = $this->apiRequest($url);

        if (!$response) {
            return [];
        }

        $keywords = [];

        // Nome da categoria
        if (!empty($response['name'])) {
            $keywords[] = [
                'keyword' => $response['name'],
                'type' => 'category_name',
                'source' => 'category',
                'relevance' => 85,
                'items_count' => $response['total_items_in_this_category'] ?? 0,
            ];
        }

        // Subcategorias
        foreach ($response['children_categories'] ?? [] as $child) {
            $keywords[] = [
                'keyword' => $child['name'],
                'type' => 'subcategory',
                'source' => 'category',
                'category_id' => $child['id'] ?? '',
                'relevance' => 80,
                'items_count' => $child['total_items_in_this_category'] ?? 0,
            ];
        }

        // Path from root
        foreach ($response['path_from_root'] ?? [] as $path) {
            if ($path['name'] !== $response['name']) {
                $keywords[] = [
                    'keyword' => $path['name'],
                    'type' => 'parent_category',
                    'source' => 'category',
                    'relevance' => 60,
                ];
            }
        }

        $this->cache[$cacheKey] = $keywords;
        return $keywords;
    }

    /**
     * Minera keywords dos valores de atributos
     */
    public function getAttributeKeywords(string $categoryId): array
    {
        $cacheKey = "attr_$categoryId";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $url = $this->baseUrl . '/categories/' . $categoryId . '/attributes';
        $response = $this->apiRequest($url);

        if (!$response || !is_array($response)) {
            return [];
        }

        $keywords = [];
        $importantAttributes = ['BRAND', 'MODEL', 'SIDE_POSITION', 'SURFACE_FINISH', 
                                'MOTORCYCLE_RIDING_STYLE', 'MATERIAL', 'COLOR', 'TYPE'];

        foreach ($response as $attr) {
            $attrId = $attr['id'] ?? '';
            $attrName = $attr['name'] ?? '';
            $isHidden = isset($attr['tags']['hidden']);
            
            // Pular atributos ocultos ou internos
            if ($isHidden && !in_array($attrId, $importantAttributes)) {
                continue;
            }

            // Nome do atributo como keyword
            if ($attrName && !in_array($attrId, ['GTIN', 'MPN', 'OEM', 'SELLER_SKU'])) {
                $keywords[] = [
                    'keyword' => $attrName,
                    'type' => 'attribute_name',
                    'attribute_id' => $attrId,
                    'source' => 'attribute',
                    'relevance' => 50,
                ];
            }

            // Valores predefinidos do atributo
            if (!empty($attr['values']) && is_array($attr['values'])) {
                foreach ($attr['values'] as $value) {
                    $valueName = $value['name'] ?? '';
                    if ($valueName && mb_strlen($valueName) > 1) {
                        // Calcular relevância baseada no tipo de atributo
                        $relevance = 70;
                        if (in_array($attrId, ['BRAND', 'MODEL'])) {
                            $relevance = 85;
                        } elseif (in_array($attrId, ['SIDE_POSITION', 'SURFACE_FINISH', 'COLOR'])) {
                            $relevance = 75;
                        }

                        $keywords[] = [
                            'keyword' => $valueName,
                            'type' => 'attribute_value',
                            'attribute_id' => $attrId,
                            'attribute_name' => $attrName,
                            'source' => 'attribute',
                            'relevance' => $relevance,
                        ];
                    }
                }
            }
        }

        $this->cache[$cacheKey] = $keywords;
        return $keywords;
    }

    /**
     * Minera todas as categorias de moto
     */
    public function mineAllMotoCategories(): array
    {
        $categories = [];
        $allUniqueKeywords = [];
        $seenKeywords = [];

        foreach ($this->motoCategoryIds as $catId => $catName) {
            $catKeywords = $this->getCategoryKeywords($catId);
            $attrKeywords = $this->getAttributeKeywords($catId);
            
            $categories[$catId] = [
                'name' => $catName,
                'category_keywords' => $catKeywords,
                'attribute_keywords' => $attrKeywords,
            ];

            // Adicionar keywords únicas
            foreach (array_merge($catKeywords, $attrKeywords) as $kw) {
                $key = mb_strtolower($kw['keyword']);
                if (!isset($seenKeywords[$key])) {
                    $seenKeywords[$key] = true;
                    $allUniqueKeywords[] = $kw;
                }
            }

            // Também minerar subcategorias
            foreach ($catKeywords as $kw) {
                if (($kw['type'] ?? '') === 'subcategory' && !empty($kw['category_id'])) {
                    $subCatId = $kw['category_id'];
                    $subAttrKw = $this->getAttributeKeywords($subCatId);
                    
                    $categories[$subCatId] = [
                        'name' => $kw['keyword'],
                        'parent' => $catId,
                        'attribute_keywords' => $subAttrKw,
                    ];
                    
                    // Adicionar keywords únicas das subcategorias
                    foreach ($subAttrKw as $subKw) {
                        $key = mb_strtolower($subKw['keyword']);
                        if (!isset($seenKeywords[$key])) {
                            $seenKeywords[$key] = true;
                            $allUniqueKeywords[] = $subKw;
                        }
                    }
                }
            }
        }

        // Ordenar por relevância
        usort($allUniqueKeywords, fn($a, $b) => ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0));

        return [
            'categories' => $categories,
            'all_keywords' => $allUniqueKeywords,
            'total_categories' => count($categories),
            'total_keywords' => count($allUniqueKeywords),
        ];
    }

    /**
     * Gera sugestões de título baseadas em keywords mineradas
     */
    public function generateTitleSuggestions(string $productName, string $categoryId): array
    {
        $keywords = $this->mineKeywords($productName, $categoryId);
        $suggestions = [];

        // Pegar keywords mais relevantes
        $topKeywords = array_slice($keywords['combined'], 0, 10);

        // Template base
        $templates = [
            '{product} {brand} {model}',
            '{product} Para {model} {brand}',
            '{brand} {product} {model} {attribute}',
            '{product} {attribute} {model}',
        ];

        // Extrair componentes
        $brand = '';
        $model = '';
        $attributes = [];

        foreach ($topKeywords as $kw) {
            $type = $kw['attribute_id'] ?? $kw['type'] ?? '';
            if ($type === 'BRAND' && !$brand) {
                $brand = $kw['keyword'];
            } elseif ($type === 'MODEL' && !$model) {
                $model = $kw['keyword'];
            } elseif (in_array($type, ['SURFACE_FINISH', 'SIDE_POSITION', 'COLOR', 'attribute_value'])) {
                $attributes[] = $kw['keyword'];
            }
        }

        foreach ($templates as $template) {
            $title = str_replace(
                ['{product}', '{brand}', '{model}', '{attribute}'],
                [$productName, $brand, $model, $attributes[0] ?? ''],
                $template
            );
            $title = preg_replace('/\s+/', ' ', trim($title));
            
            if (mb_strlen($title) <= 60 && mb_strlen($title) >= 20) {
                $suggestions[] = [
                    'title' => $title,
                    'length' => mb_strlen($title),
                    'keywords_used' => array_filter([$brand, $model, $attributes[0] ?? null]),
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Combina e ranqueia keywords de todas as fontes
     */
    private function combineAndRank(array $keywordGroups): array
    {
        $combined = [];
        $seen = [];

        foreach (['domain_keywords', 'category_keywords', 'attribute_keywords'] as $group) {
            foreach ($keywordGroups[$group] ?? [] as $kw) {
                $keyword = mb_strtolower($kw['keyword'] ?? '');
                if (!$keyword || isset($seen[$keyword])) {
                    continue;
                }
                $seen[$keyword] = true;
                $combined[] = $kw;
            }
        }

        // Ordenar por relevância
        usort($combined, function ($a, $b) {
            return ($b['relevance'] ?? 0) - ($a['relevance'] ?? 0);
        });

        return $combined;
    }

    /**
     * Faz requisição à API do ML
     */
    private function apiRequest(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: MeliManager/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Salva keywords mineradas no banco
     */
    public function saveMinedKeywords(array $keywords, string $source): int
    {
        $saved = 0;
        $stmt = $this->db->prepare("
            INSERT INTO market_keywords (keyword, category_id, source, relevance, metadata, created_at)
            VALUES (:keyword, :category_id, :source, :relevance, :metadata, NOW())
            ON DUPLICATE KEY UPDATE 
                relevance = GREATEST(relevance, VALUES(relevance)),
                updated_at = NOW()
        ");

        foreach ($keywords['combined'] ?? $keywords as $kw) {
            if (empty($kw['keyword'])) continue;

            try {
                $stmt->execute([
                    'keyword' => $kw['keyword'],
                    'category_id' => $kw['category_id'] ?? null,
                    'source' => $source,
                    'relevance' => $kw['relevance'] ?? 50,
                    'metadata' => json_encode($kw),
                ]);
                $saved++;
            } catch (\Exception $e) {
                log_warning('Falha ao salvar keyword minerada', [
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $saved;
    }

    /**
     * Busca keywords salvas
     */
    public function getStoredKeywords(?string $categoryId = null, int $limit = 100): array
    {
        $limitSql = max(1, min(1000, (int)$limit));
        $sql = "SELECT * FROM market_keywords WHERE 1=1";
        $params = [];

        if ($categoryId) {
            $sql .= " AND category_id = :category_id";
            $params['category_id'] = $categoryId;
        }

        $sql .= " ORDER BY relevance DESC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas de keywords mineradas
     */
    public function getStats(): array
    {
        return [
            'total_keywords' => $this->db->query("SELECT COUNT(*) FROM market_keywords")->fetchColumn(),
            'by_source' => $this->db->query("
                SELECT source, COUNT(*) as count, AVG(relevance) as avg_relevance 
                FROM market_keywords GROUP BY source
            ")->fetchAll(PDO::FETCH_ASSOC),
            'top_categories' => $this->db->query("
                SELECT category_id, COUNT(*) as count 
                FROM market_keywords 
                WHERE category_id IS NOT NULL 
                GROUP BY category_id 
                ORDER BY count DESC LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
