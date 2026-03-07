<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\MercadoLivreClient;
use App\Services\CacheService;
use App\Database;
use PDO;

class CategoryService
{
    private MercadoLivreClient $client;
    private CacheService $cache;
    private string $siteId;

    public function __construct(?int $accountId = null)
    {
        $config = \App\Core\Config::getInstance()->all();
        $this->siteId = ($config['mercadolivre']['site_id'] ?? null) ?: 'MLB';
        $this->client = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();
    }

    /**
     * Lista todas as categorias do site
     */
    public function getAllCategories(): array
    {
        $cacheKey = "categories_{$this->siteId}";

        return $this->cache->remember($cacheKey, function () {
            // Try public access if no auth available (will use token if present)
            return $this->client->get("/sites/{$this->siteId}/categories", [], true);
        }, 86400); // 24 horas
    }

    /**
     * Obtém detalhes de uma categoria específica
     */
    public function getCategory(string $categoryId): array
    {
        $cacheKey = "category_{$categoryId}";

        return $this->cache->remember($cacheKey, function () use ($categoryId) {
            return $this->client->get("/categories/{$categoryId}", [], true);
        }, 86400); // 24 horas
    }

    /**
     * Obtém atributos de uma categoria
     */
    public function getCategoryAttributes(string $categoryId): array
    {
        $cacheKey = "category_attributes_{$categoryId}";

        return $this->cache->remember($cacheKey, function () use ($categoryId) {
            return $this->client->get("/categories/{$categoryId}/attributes", [], true);
        }, 43200); // 12 horas
    }

    /**
     * Busca categoria por nome - versão melhorada com predictor
     */
    public function searchCategoryByName(string $searchTerm): array
    {
        // Primeiro tenta o predictor do ML (não precisa de auth)
        $predictorResults = $this->searchWithPredictor($searchTerm);

        if (!empty($predictorResults) && !isset($predictorResults['error'])) {
            return $predictorResults;
        }

        // Fallback: busca nas categorias raiz
        $categories = $this->getAllCategories();

        if (isset($categories['error'])) {
            // Se falhar, retorna categorias padrão populares
            return $this->getPopularCategories($searchTerm);
        }

        $results = [];
        $searchLower = mb_strtolower($searchTerm);

        foreach ($categories as $category) {
            $name = mb_strtolower($category['name'] ?? '');
            if (strpos($name, $searchLower) !== false) {
                $results[] = [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'path_from_root' => [['id' => $category['id'], 'name' => $category['name']]]
                ];
            }
        }

        // Se não encontrou nas raiz, busca nas subcategorias populares
        if (empty($results)) {
            $results = $this->searchInPopularSubcategories($searchLower);
        }

        return $results;
    }

    /**
     * Busca usando o category predictor do ML
     */
    private function searchWithPredictor(string $searchTerm): array
    {
        try {
            // API pública do ML para prever categoria - but using auth to avoid block
            $response = $this->client->get("/sites/{$this->siteId}/domain_discovery/search", [
                'q' => $searchTerm,
                'limit' => 10
            ]);

            if (isset($response['error']) || !is_array($response)) {
                return [];
            }

            $results = [];
            foreach ($response as $item) {
                if (isset($item['category_id'], $item['category_name'])) {
                    $results[] = [
                        'id' => $item['category_id'],
                        'name' => $item['category_name'],
                        'domain_id' => $item['domain_id'] ?? null,
                        'domain_name' => $item['domain_name'] ?? null,
                        'path_from_root' => $item['path_from_root'] ?? []
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Remove acentos de uma string para busca tolerante
     */
    private function removeAccents(string $str): string
    {
        $search = [
            'á',
            'à',
            'ã',
            'â',
            'ä',
            'é',
            'è',
            'ê',
            'ë',
            'í',
            'ì',
            'î',
            'ï',
            'ó',
            'ò',
            'õ',
            'ô',
            'ö',
            'ú',
            'ù',
            'û',
            'ü',
            'ç',
            'ñ',
            'Á',
            'À',
            'Ã',
            'Â',
            'Ä',
            'É',
            'È',
            'Ê',
            'Ë',
            'Í',
            'Ì',
            'Î',
            'Ï',
            'Ó',
            'Ò',
            'Õ',
            'Ô',
            'Ö',
            'Ú',
            'Ù',
            'Û',
            'Ü',
            'Ç',
            'Ñ'
        ];
        $replace = [
            'a',
            'a',
            'a',
            'a',
            'a',
            'e',
            'e',
            'e',
            'e',
            'i',
            'i',
            'i',
            'i',
            'o',
            'o',
            'o',
            'o',
            'o',
            'u',
            'u',
            'u',
            'u',
            'c',
            'n',
            'a',
            'a',
            'a',
            'a',
            'a',
            'e',
            'e',
            'e',
            'e',
            'i',
            'i',
            'i',
            'i',
            'o',
            'o',
            'o',
            'o',
            'o',
            'u',
            'u',
            'u',
            'u',
            'c',
            'n'
        ];
        return str_replace($search, $replace, $str);
    }

    /**
     * Verifica se um termo está contido em outro (tolerante a acentos)
     */
    private function containsSearch(string $haystack, string $needle): bool
    {
        $haystackNorm = $this->removeAccents(mb_strtolower($haystack));
        $needleNorm = $this->removeAccents(mb_strtolower($needle));
        return strpos($haystackNorm, $needleNorm) !== false;
    }

    /**
     * Retorna categorias populares filtradas
     */
    private function getPopularCategories(string $searchTerm): array
    {
        $popular = [
            ['id' => 'MLB1071', 'name' => 'Acessórios para Veículos - Motos', 'path_from_root' => [['id' => 'MLB1747', 'name' => 'Acessórios para Veículos'], ['id' => 'MLB1071', 'name' => 'Motos']]],
            ['id' => 'MLB1747', 'name' => 'Acessórios para Veículos', 'path_from_root' => [['id' => 'MLB1747', 'name' => 'Acessórios para Veículos']]],
            ['id' => 'MLB1000', 'name' => 'Eletrônicos, Áudio e Vídeo', 'path_from_root' => [['id' => 'MLB1000', 'name' => 'Eletrônicos, Áudio e Vídeo']]],
            ['id' => 'MLB1648', 'name' => 'Computadores', 'path_from_root' => [['id' => 'MLB1648', 'name' => 'Computadores']]],
            ['id' => 'MLB1132', 'name' => 'Brinquedos e Hobbies', 'path_from_root' => [['id' => 'MLB1132', 'name' => 'Brinquedos e Hobbies']]],
            ['id' => 'MLB1168', 'name' => 'Celulares e Telefones', 'path_from_root' => [['id' => 'MLB1168', 'name' => 'Celulares e Telefones']]],
            ['id' => 'MLB1574', 'name' => 'Casa, Móveis e Decoração', 'path_from_root' => [['id' => 'MLB1574', 'name' => 'Casa, Móveis e Decoração']]],
            ['id' => 'MLB1276', 'name' => 'Esportes e Fitness', 'path_from_root' => [['id' => 'MLB1276', 'name' => 'Esportes e Fitness']]],
            ['id' => 'MLB1430', 'name' => 'Calçados, Roupas e Bolsas', 'path_from_root' => [['id' => 'MLB1430', 'name' => 'Calçados, Roupas e Bolsas']]],
            ['id' => 'MLB1953', 'name' => 'Ferramentas', 'path_from_root' => [['id' => 'MLB1953', 'name' => 'Ferramentas']]],
            ['id' => 'MLB1499', 'name' => 'Indústria e Comércio', 'path_from_root' => [['id' => 'MLB1499', 'name' => 'Indústria e Comércio']]],
            ['id' => 'MLB1182', 'name' => 'Instrumentos Musicais', 'path_from_root' => [['id' => 'MLB1182', 'name' => 'Instrumentos Musicais']]],
            ['id' => 'MLB3937', 'name' => 'Beleza e Cuidado Pessoal', 'path_from_root' => [['id' => 'MLB3937', 'name' => 'Beleza e Cuidado Pessoal']]],
        ];

        $results = [];

        foreach ($popular as $cat) {
            if ($this->containsSearch($cat['name'], $searchTerm)) {
                $results[] = $cat;
            }
        }

        return $results;
    }

    /**
     * Busca em subcategorias populares
     */
    private function searchInPopularSubcategories(string $searchTerm): array
    {
        $subcategories = [
            // Motos
            ['id' => 'MLB1071', 'name' => 'Acessórios para Motos', 'path_from_root' => [['id' => 'MLB1747', 'name' => 'Acessórios para Veículos'], ['id' => 'MLB1071', 'name' => 'Motos']]],
            ['id' => 'MLB1772', 'name' => 'Acessórios para Carros', 'path_from_root' => [['id' => 'MLB1747', 'name' => 'Acessórios para Veículos'], ['id' => 'MLB1772', 'name' => 'Carros']]],
            ['id' => 'MLB5672', 'name' => 'Peças para Motos', 'path_from_root' => [['id' => 'MLB1747', 'name' => 'Acessórios para Veículos'], ['id' => 'MLB5672', 'name' => 'Peças de Motos']]],
            // Eletrônicos
            ['id' => 'MLB1051', 'name' => 'Celulares e Smartphones', 'path_from_root' => [['id' => 'MLB1168', 'name' => 'Celulares e Telefones'], ['id' => 'MLB1051', 'name' => 'Celulares e Smartphones']]],
            ['id' => 'MLB1055', 'name' => 'Capas para Celular', 'path_from_root' => [['id' => 'MLB1168', 'name' => 'Celulares e Telefones'], ['id' => 'MLB1055', 'name' => 'Capas']]],
            ['id' => 'MLB1144', 'name' => 'Consoles de Videogames', 'path_from_root' => [['id' => 'MLB1144', 'name' => 'Games'], ['id' => 'MLB1144', 'name' => 'Consoles']]],
            ['id' => 'MLB1000', 'name' => 'Áudio e Eletrônicos', 'path_from_root' => [['id' => 'MLB1000', 'name' => 'Eletrônicos, Áudio e Vídeo']]],
            // Informática
            ['id' => 'MLB1649', 'name' => 'Notebooks', 'path_from_root' => [['id' => 'MLB1648', 'name' => 'Computadores'], ['id' => 'MLB1649', 'name' => 'Notebooks']]],
            ['id' => 'MLB430687', 'name' => 'Teclados e Mouses', 'path_from_root' => [['id' => 'MLB1648', 'name' => 'Computadores'], ['id' => 'MLB430687', 'name' => 'Periféricos de PC']]],
            ['id' => 'MLB1714', 'name' => 'Monitores', 'path_from_root' => [['id' => 'MLB1648', 'name' => 'Computadores'], ['id' => 'MLB1714', 'name' => 'Monitores e Acessórios']]],
        ];

        $results = [];
        foreach ($subcategories as $cat) {
            if ($this->containsSearch($cat['name'], $searchTerm)) {
                $results[] = $cat;
            }
        }

        return $results;
    }

    /**
     * Obtém marcas disponíveis para uma categoria
     */
    public function getBrandsForCategory(string $categoryId): array
    {
        // Primeiro tenta via attributes (padrão ML)
        $attributes = $this->getCategoryAttributes($categoryId);

        if (!isset($attributes['error']) && is_array($attributes)) {
            foreach ($attributes as $attribute) {
                if (isset($attribute['id']) && $attribute['id'] === 'BRAND') {
                    $values = $attribute['values'] ?? [];
                    if (!empty($values)) {
                        return array_map(function ($v) {
                            return [
                                'id' => $v['id'] ?? $v['name'],
                                'name' => $v['name'] ?? $v['id'],
                                'results' => $v['results'] ?? null
                            ];
                        }, array_slice($values, 0, 50));
                    }
                }
            }
        }

        // Fallback: busca marcas via search na categoria
        return $this->searchBrandsInCategory($categoryId);
    }

    /**
     * Busca marcas populares em uma categoria via search
     */
    private function searchBrandsInCategory(string $categoryId): array
    {
        $cacheKey = "brands_search_{$categoryId}";

        return $this->cache->remember($cacheKey, function () use ($categoryId) {
            try {
                // Faz uma busca na categoria para pegar os filtros de marca
                $response = $this->client->get("/sites/{$this->siteId}/search", [
                    'category' => $categoryId,
                    'limit' => 1
                ]);

                if (isset($response['error'])) {
                    return $this->getDefaultBrandsForCategory($categoryId);
                }

                // Procura o filtro de marca nos available_filters
                $filters = $response['available_filters'] ?? [];
                foreach ($filters as $filter) {
                    if ($filter['id'] === 'BRAND' || $filter['id'] === 'brand') {
                        $values = $filter['values'] ?? [];
                        return array_map(function ($v) {
                            return [
                                'id' => $v['id'] ?? $v['name'],
                                'name' => $v['name'] ?? $v['id'],
                                'results' => $v['results'] ?? null
                            ];
                        }, array_slice($values, 0, 50));
                    }
                }

                return $this->getDefaultBrandsForCategory($categoryId);
            } catch (\Exception $e) {
                return $this->getDefaultBrandsForCategory($categoryId);
            }
        }, 3600); // 1 hora
    }

    /**
     * Marcas padrão por categoria (fallback)
     */
    private function getDefaultBrandsForCategory(string $categoryId): array
    {
        $defaultBrands = [
            'MLB1071' => [ // Motos
                ['id' => 'AWA', 'name' => 'AWA'],
                ['id' => 'Pro Tork', 'name' => 'Pro Tork'],
                ['id' => 'Honda', 'name' => 'Honda'],
                ['id' => 'Yamaha', 'name' => 'Yamaha'],
                ['id' => 'Kawasaki', 'name' => 'Kawasaki'],
                ['id' => 'Shineray', 'name' => 'Shineray'],
                ['id' => 'Texx', 'name' => 'Texx'],
                ['id' => 'Helt', 'name' => 'Helt'],
                ['id' => 'LS2', 'name' => 'LS2'],
                ['id' => 'X11', 'name' => 'X11'],
            ],
            'MLB1168' => [ // Celulares
                ['id' => 'Apple', 'name' => 'Apple'],
                ['id' => 'Samsung', 'name' => 'Samsung'],
                ['id' => 'Xiaomi', 'name' => 'Xiaomi'],
                ['id' => 'Motorola', 'name' => 'Motorola'],
                ['id' => 'LG', 'name' => 'LG'],
                ['id' => 'Realme', 'name' => 'Realme'],
                ['id' => 'POCO', 'name' => 'POCO'],
            ],
            'MLB1648' => [ // Computadores
                ['id' => 'Dell', 'name' => 'Dell'],
                ['id' => 'HP', 'name' => 'HP'],
                ['id' => 'Lenovo', 'name' => 'Lenovo'],
                ['id' => 'Asus', 'name' => 'Asus'],
                ['id' => 'Acer', 'name' => 'Acer'],
                ['id' => 'Samsung', 'name' => 'Samsung'],
                ['id' => 'Apple', 'name' => 'Apple'],
            ],
            'MLB1000' => [ // Eletrônicos
                ['id' => 'Samsung', 'name' => 'Samsung'],
                ['id' => 'LG', 'name' => 'LG'],
                ['id' => 'Sony', 'name' => 'Sony'],
                ['id' => 'JBL', 'name' => 'JBL'],
                ['id' => 'Philips', 'name' => 'Philips'],
                ['id' => 'Panasonic', 'name' => 'Panasonic'],
            ],
            'MLB1132' => [ // Brinquedos
                ['id' => 'Lego', 'name' => 'Lego'],
                ['id' => 'Mattel', 'name' => 'Mattel'],
                ['id' => 'Hasbro', 'name' => 'Hasbro'],
                ['id' => 'Bandai', 'name' => 'Bandai'],
                ['id' => 'Funko', 'name' => 'Funko'],
            ],
            'MLB1574' => [ // Casa
                ['id' => 'Tramontina', 'name' => 'Tramontina'],
                ['id' => 'Electrolux', 'name' => 'Electrolux'],
                ['id' => 'Consul', 'name' => 'Consul'],
                ['id' => 'Brastemp', 'name' => 'Brastemp'],
                ['id' => 'Mondial', 'name' => 'Mondial'],
            ],
        ];

        return $defaultBrands[$categoryId] ?? [];
    }

    /**
     * Obtém subcategorias de uma categoria
     */
    public function getSubcategories(string $categoryId): array
    {
        $category = $this->getCategory($categoryId);

        if (isset($category['error'])) {
            return $category;
        }

        return $category['children_categories'] ?? [];
    }

    /**
     * Obtém árvore completa de categorias com hierarquia
     */
    public function getCategoryTree(): array
    {
        $categories = $this->getAllCategories();

        if (isset($categories['error'])) {
            return $categories;
        }

        // Adicionar parent_id baseado em path_from_root
        $tree = [];
        foreach ($categories as $category) {
            $item = $category;
            if (isset($category['path_from_root']) && is_array($category['path_from_root'])) {
                $path = $category['path_from_root'];
                if (count($path) > 1) {
                    $item['parent_id'] = $path[count($path) - 2]['id'];
                } else {
                    $item['parent_id'] = null;
                }
            }
            $tree[] = $item;
        }

        return $tree;
    }

    /**
     * Obtém atributos que podem ser usados como filtros de busca
     */
    public function getFilterableAttributes(string $categoryId): array
    {
        $attributes = $this->getCategoryAttributes($categoryId);

        if (isset($attributes['error'])) {
            return $attributes;
        }

        $filterable = [];

        foreach ($attributes as $attribute) {
            // Atributos que podem ser filtrados geralmente têm valores pré-definidos
            if (isset($attribute['values']) && is_array($attribute['values']) && count($attribute['values']) > 0) {
                // Atributos comuns para filtro: BRAND, MODEL, CONDITION, etc.
                $commonFilterable = ['BRAND', 'MODEL', 'ITEM_CONDITION', 'SELLER_TYPE'];

                if (
                    in_array($attribute['id'] ?? '', $commonFilterable) ||
                    (isset($attribute['values']) && count($attribute['values']) <= 50)
                ) {
                    $filterable[] = [
                        'id' => $attribute['id'] ?? '',
                        'name' => $attribute['name'] ?? '',
                        'type' => $attribute['value_type'] ?? 'string',
                        'values' => $attribute['values'] ?? [],
                        'tags' => $attribute['tags'] ?? [],
                    ];
                }
            }
        }

        return $filterable;
    }

    /**
     * Obtém atributos obrigatórios para uma categoria
     */
    public function getRequiredAttributes(string $categoryId): array
    {
        $attributes = $this->getCategoryAttributes($categoryId);

        if (isset($attributes['error'])) {
            return $attributes;
        }

        $required = [];

        foreach ($attributes as $attribute) {
            if (isset($attribute['tags']) && is_array($attribute['tags'])) {
                // Verificar se tem tag 'required' ou 'can_be_required'
                if (
                    in_array('required', $attribute['tags']) ||
                    in_array('can_be_required', $attribute['tags'])
                ) {
                    $required[] = [
                        'id' => $attribute['id'] ?? '',
                        'name' => $attribute['name'] ?? '',
                        'type' => $attribute['value_type'] ?? 'string',
                        'values' => $attribute['values'] ?? [],
                        'tags' => $attribute['tags'] ?? [],
                        'hint' => $attribute['hint'] ?? '',
                    ];
                }
            }
        }

        return $required;
    }

    /**
     * Valida atributos obrigatórios para criação de anúncio
     */
    public function validateRequiredAttributes(string $categoryId, array $providedAttributes): array
    {
        $required = $this->getRequiredAttributes($categoryId);

        if (isset($required['error'])) {
            return $required;
        }

        $errors = [];
        $providedIds = array_column($providedAttributes, 'id');

        foreach ($required as $reqAttr) {
            $attrId = $reqAttr['id'];

            // Verificar se o atributo foi fornecido
            if (!in_array($attrId, $providedIds)) {
                $errors[] = [
                    'attribute_id' => $attrId,
                    'attribute_name' => $reqAttr['name'],
                    'error' => 'Atributo obrigatório ausente',
                ];
                continue;
            }

            // Encontrar o valor fornecido
            $providedValue = null;
            foreach ($providedAttributes as $attr) {
                if (($attr['id'] ?? '') === $attrId) {
                    $providedValue = $attr['value'] ?? $attr['value_name'] ?? null;
                    break;
                }
            }

            // Validar se o valor é válido (se houver valores pré-definidos)
            if (!empty($reqAttr['values']) && $providedValue !== null) {
                $validValues = array_column($reqAttr['values'], 'id');
                $validValueNames = array_column($reqAttr['values'], 'name');

                if (
                    !in_array($providedValue, $validValues) &&
                    !in_array($providedValue, $validValueNames)
                ) {
                    $errors[] = [
                        'attribute_id' => $attrId,
                        'attribute_name' => $reqAttr['name'],
                        'error' => 'Valor inválido para o atributo',
                        'provided_value' => $providedValue,
                        'valid_values' => $validValueNames,
                    ];
                }
            }

            // Validar se o valor não está vazio
            if ($providedValue === null || $providedValue === '') {
                $errors[] = [
                    'attribute_id' => $attrId,
                    'attribute_name' => $reqAttr['name'],
                    'error' => 'Valor não pode estar vazio',
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Obtém valores possíveis para um atributo específico
     */
    public function getAttributeValues(string $categoryId, string $attributeId): array
    {
        $attributes = $this->getCategoryAttributes($categoryId);

        if (isset($attributes['error'])) {
            return $attributes;
        }

        foreach ($attributes as $attribute) {
            if (($attribute['id'] ?? '') === $attributeId) {
                return $attribute['values'] ?? [];
            }
        }

        return [];
    }
}
