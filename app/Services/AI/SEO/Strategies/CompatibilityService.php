<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Services\MercadoLivreClient;

/**
 * 🚗 E10: Compatibility Service
 * 
 * Gerencia compatibilidade expandida de produtos:
 * - Detecção automática de veículos/modelos compatíveis
 * - Expansão de compatibilidade via ML API
 * - Geração de atributos COMPATIBLE_MODELS
 * - Matching inteligente por especificações
 * 
 * @package App\Services\AI\SEO\Strategies
 */
class CompatibilityService
{
    private ?int $accountId;
    private ?MercadoLivreClient $client;

    /**
     * Mapeamento de marcas e modelos (cache local)
     */
    private const BRAND_MODELS = [
        'honda' => [
            'cg 160' => ['cg 160 start', 'cg 160 fan', 'cg 160 titan', 'cg 160 cargo'],
            'cg 150' => ['cg 150 fan', 'cg 150 titan', 'cg 150 esi'],
            'bros 160' => ['nxr 160 bros', 'bros 160 esdd'],
            'xre 300' => ['xre 300 abs', 'xre 300'],
            'cb 300' => ['cb 300r', 'cb 300f'],
            'cb 500' => ['cb 500f', 'cb 500x'],
            'pcx 150' => ['pcx 150', 'pcx 150 dlx'],
            'elite 125' => ['elite 125'],
            'pop 110' => ['pop 110i']
        ],
        'yamaha' => [
            'fazer 250' => ['fazer 250', 'ys 250 fazer'],
            'factor 150' => ['factor 150', 'ybr 150 factor'],
            'nmax 160' => ['nmax 160', 'nmax 160 abs'],
            'crosser 150' => ['xtz crosser 150'],
            'lander 250' => ['xtz 250 lander'],
            'mt-03' => ['mt 03', 'mt-03'],
            'r3' => ['yzf-r3', 'r3']
        ],
        'suzuki' => [
            'gsr 150' => ['gsr 150i'],
            'v-strom 650' => ['v-strom 650', 'dl 650'],
            'intruder 125' => ['intruder 125']
        ],
        'kawasaki' => [
            'ninja 300' => ['ninja 300', 'ninja 300 abs'],
            'z300' => ['z300'],
            'versys 300' => ['versys-x 300']
        ],
        'bmw' => [
            'g310' => ['g 310 r', 'g 310 gs'],
            'f850' => ['f 850 gs', 'f 850 gs adventure']
        ],
        'universal' => [
            'genérico' => ['universal', 'genérico', 'qualquer moto']
        ]
    ];

    /**
     * Atributos de compatibilidade do ML
     */
    private const COMPATIBILITY_ATTRIBUTES = [
        'COMPATIBLE_MODELS',
        'COMPATIBLE_VEHICLES',
        'VEHICLE_BRAND',
        'VEHICLE_MODEL',
        'VEHICLE_YEAR'
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = $accountId ? new MercadoLivreClient($accountId) : null;
    }

    /**
     * Analisa compatibilidade atual de um item
     */
    public function analyzeCompatibility(string $itemId): array
    {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado'];
        }

        try {
            $item = $this->client->get("/items/{$itemId}");
            
            $currentCompatibility = $this->extractCurrentCompatibility($item);
            $suggestedExpansion = $this->suggestExpansion($currentCompatibility);
            $missingAttributes = $this->detectMissingAttributes($item);

            return [
                'item_id' => $itemId,
                'current' => $currentCompatibility,
                'suggested_expansion' => $suggestedExpansion,
                'missing_attributes' => $missingAttributes,
                'score' => $this->calculateCompatibilityScore($currentCompatibility),
                'recommendations' => $this->getRecommendations($currentCompatibility, $missingAttributes)
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Expande compatibilidade com base em dados atuais
     */
    public function expandCompatibility(array $currentModels): array
    {
        $expanded = [];
        $addedModels = [];

        foreach ($currentModels as $model) {
            // Tratar modelo que pode ser array
            if (is_array($model)) {
                $model = $model['model'] ?? $model['value'] ?? $model[0] ?? '';
            }
            if (empty($model) || !is_string($model)) {
                continue;
            }
            
            $modelLower = mb_strtolower($model);
            
            // Detectar marca
            $brand = $this->detectBrand($modelLower);
            
            if ($brand) {
                // Buscar modelos relacionados
                $related = $this->findRelatedModels($brand, $modelLower);
                
                foreach ($related as $relatedModel) {
                    // Garantir que relatedModel é string
                    if (is_array($relatedModel)) {
                        $relatedModel = $relatedModel['model'] ?? $relatedModel['value'] ?? '';
                    }
                    if (empty($relatedModel)) continue;
                    
                    $key = mb_strtolower($relatedModel);
                    // Extrair strings de currentModels para comparação
                    $currentModelStrings = array_map(function($m) {
                        return is_array($m) ? ($m['model'] ?? $m['value'] ?? '') : $m;
                    }, $currentModels);
                    if (!in_array($key, array_map('mb_strtolower', array_filter($currentModelStrings)))) {
                        $expanded[] = [
                            'model' => $relatedModel,
                            'brand' => $brand,
                            'relation' => 'variant',
                            'confidence' => 0.85
                        ];
                        $addedModels[] = $relatedModel;
                    }
                }

                // Buscar família de modelos
                $family = $this->findModelFamily($brand, $modelLower);
                foreach ($family as $familyModel) {
                    // Garantir que familyModel é string
                    if (is_array($familyModel)) {
                        $familyModel = $familyModel['model'] ?? $familyModel['value'] ?? '';
                    }
                    if (empty($familyModel)) continue;
                    
                    $key = mb_strtolower($familyModel);
                    $addedModelStrings = array_filter(array_map(function($m) {
                        return is_array($m) ? ($m['model'] ?? $m['value'] ?? '') : $m;
                    }, $addedModels));
                    
                    if (!in_array($key, array_map('mb_strtolower', array_filter($currentModelStrings))) &&
                        !in_array($key, array_map('mb_strtolower', $addedModelStrings))) {
                        $expanded[] = [
                            'model' => $familyModel,
                            'brand' => $brand,
                            'relation' => 'family',
                            'confidence' => 0.7
                        ];
                    }
                }
            }
        }

        return [
            'original_count' => count($currentModels),
            'expanded' => $expanded,
            'expanded_count' => count($expanded),
            'total_coverage' => count($currentModels) + count($expanded)
        ];
    }

    /**
     * Busca compatibilidade via ML API
     */
    public function fetchFromMLApi(string $categoryId, string $searchQuery): array
    {
        if (!$this->client) {
            return ['error' => 'Cliente ML não configurado'];
        }

        $compatibility = [];

        try {
            // Buscar atributos da categoria
            $attributes = $this->client->get("/categories/{$categoryId}/attributes");
            
            foreach ($attributes as $attr) {
                if (in_array($attr['id'], self::COMPATIBILITY_ATTRIBUTES)) {
                    // Se tiver valores pré-definidos
                    if (!empty($attr['values'])) {
                        $compatibility[$attr['id']] = [
                            'attribute_id' => $attr['id'],
                            'name' => $attr['name'],
                            'values' => array_map(fn($v) => $v['name'], $attr['values']),
                            'value_count' => count($attr['values'])
                        ];
                    }
                }
            }

            // Buscar também de items similares
            $search = $this->client->get('/sites/MLB/search', [
                'category' => $categoryId,
                'q' => $searchQuery,
                'limit' => 5
            ]);

            $fromItems = [];
            foreach ($search['results'] ?? [] as $item) {
                $itemCompat = $this->extractFromItem($item);
                $fromItems = array_merge($fromItems, $itemCompat);
            }

            $compatibility['from_similar_items'] = array_unique($fromItems);

        } catch (\Exception $e) {
            $compatibility['error'] = $e->getMessage();
        }

        return [
            'category_id' => $categoryId,
            'compatibility_data' => $compatibility
        ];
    }

    /**
     * Gera atributo COMPATIBLE_MODELS formatado
     */
    public function generateCompatibleModelsAttribute(array $models): array
    {
        // Agrupar por marca
        $byBrand = [];
        
        foreach ($models as $model) {
            // Tratar modelo que pode ser array
            if (is_array($model)) {
                $model = $model['model'] ?? $model['value'] ?? $model[0] ?? '';
            }
            if (empty($model) || !is_string($model)) {
                continue;
            }
            
            $brand = $this->detectBrand(mb_strtolower($model));
            if (!$brand) $brand = 'outros';
            
            if (!isset($byBrand[$brand])) {
                $byBrand[$brand] = [];
            }
            $byBrand[$brand][] = $model;
        }

        // Formatar valores
        $formatted = [];
        foreach ($byBrand as $brand => $brandModels) {
            foreach ($brandModels as $m) {
                $formatted[] = [
                    'value' => $m,
                    'brand' => mb_strtoupper(mb_substr($brand, 0, 1)) . mb_substr($brand, 1)
                ];
            }
        }

        // Gerar texto para campo
        $modelStrings = array_map(function($m) {
            return is_array($m) ? ($m['model'] ?? $m['value'] ?? '') : $m;
        }, $models);
        $textValue = implode(', ', array_filter($modelStrings));

        return [
            'attribute_id' => 'COMPATIBLE_MODELS',
            'values' => $formatted,
            'text_value' => $textValue,
            'by_brand' => $byBrand,
            'total_models' => count($models)
        ];
    }

    /**
     * Sugere modelos compatíveis baseado em especificações
     */
    public function suggestBySpecs(array $specs): array
    {
        $suggestions = [];

        // Extrair dimensões relevantes
        $width = $specs['width'] ?? null;
        $height = $specs['height'] ?? null;
        $depth = $specs['depth'] ?? null;
        $capacity = $specs['capacity'] ?? null;

        // Lógica de compatibilidade por especificações
        // (Exemplo simplificado - em produção seria mais complexo)
        
        // Baús pequenos (até 30L) - compatíveis com scooters e motos leves
        if ($capacity && $this->parseCapacity($capacity) <= 30) {
            $suggestions = array_merge($suggestions, [
                'pcx 150', 'nmax 160', 'elite 125', 'pop 110', 'lead 110'
            ]);
        }
        
        // Baús médios (31-45L) - compatíveis com maioria das motos
        if ($capacity && $this->parseCapacity($capacity) > 30 && $this->parseCapacity($capacity) <= 45) {
            $suggestions = array_merge($suggestions, [
                'cg 160', 'fazer 250', 'bros 160', 'factor 150', 'gsr 150'
            ]);
        }

        // Baús grandes (46L+) - compatíveis com motos maiores
        if ($capacity && $this->parseCapacity($capacity) > 45) {
            $suggestions = array_merge($suggestions, [
                'xre 300', 'cb 500', 'v-strom 650', 'lander 250', 'g310 gs'
            ]);
        }

        // Universal sempre
        $suggestions[] = 'universal';

        return [
            'specs_analyzed' => $specs,
            'suggested_models' => array_unique($suggestions),
            'reasoning' => $this->getSpecsReasoning($specs)
        ];
    }

    /**
     * Valida compatibilidade informada
     */
    public function validateCompatibility(array $models, ?string $categoryId = null): array
    {
        $valid = [];
        $invalid = [];
        $warnings = [];

        foreach ($models as $model) {
            // Tratar modelo que pode ser array
            if (is_array($model)) {
                $model = $model['model'] ?? $model['value'] ?? $model[0] ?? '';
            }
            if (empty($model) || !is_string($model)) {
                continue;
            }
            
            $modelLower = mb_strtolower($model);
            $brand = $this->detectBrand($modelLower);

            if ($brand) {
                $valid[] = [
                    'model' => $model,
                    'brand' => $brand,
                    'status' => 'valid'
                ];
            } else {
                // Verificar se é formato conhecido
                if (preg_match('/^[a-z0-9\-\s]+$/i', $model)) {
                    $warnings[] = [
                        'model' => $model,
                        'message' => 'Modelo não reconhecido - verifique ortografia',
                        'status' => 'warning'
                    ];
                } else {
                    $invalid[] = [
                        'model' => $model,
                        'message' => 'Formato inválido para modelo',
                        'status' => 'invalid'
                    ];
                }
            }
        }

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'warnings' => $warnings,
            'validation_score' => count($valid) / max(1, count($models)) * 100
        ];
    }

    /**
     * Obtém todos os modelos disponíveis por marca
     */
    public function getAllModels(?string $brand = null): array
    {
        if ($brand) {
            $brandLower = mb_strtolower($brand);
            if (isset(self::BRAND_MODELS[$brandLower])) {
                $models = [];
                foreach (self::BRAND_MODELS[$brandLower] as $base => $variants) {
                    $models[$base] = $variants;
                }
                return [
                    'brand' => $brand,
                    'models' => $models
                ];
            }
            return ['error' => 'Marca não encontrada'];
        }

        // Retornar todas
        $all = [];
        foreach (self::BRAND_MODELS as $b => $modelGroups) {
            $all[$b] = [];
            foreach ($modelGroups as $base => $variants) {
                $all[$b][$base] = $variants;
            }
        }

        return [
            'brands' => array_keys(self::BRAND_MODELS),
            'models_by_brand' => $all
        ];
    }

    // ========================================================================
    // MÉTODOS PRIVADOS
    // ========================================================================

    private function extractCurrentCompatibility(array $item): array
    {
        $compatibility = [
            'models' => [],
            'brands' => [],
            'from_title' => [],
            'from_attributes' => []
        ];

        // Do título
        $title = $item['title'] ?? '';
        $compatibility['from_title'] = $this->extractModelsFromText($title);

        // Dos atributos
        foreach ($item['attributes'] ?? [] as $attr) {
            if (in_array($attr['id'], self::COMPATIBILITY_ATTRIBUTES)) {
                $value = $attr['value_name'] ?? '';
                if ($value) {
                    $compatibility['from_attributes'][] = $value;
                }
            }
        }

        // Consolidar
        $allModels = array_merge(
            $compatibility['from_title'],
            $compatibility['from_attributes']
        );
        $compatibility['models'] = array_unique($allModels);

        // Extrair marcas
        foreach ($compatibility['models'] as $model) {
            $brand = $this->detectBrand(mb_strtolower($model));
            if ($brand && !in_array($brand, $compatibility['brands'])) {
                $compatibility['brands'][] = $brand;
            }
        }

        return $compatibility;
    }

    private function extractModelsFromText(string $text): array
    {
        $models = [];
        $textLower = mb_strtolower($text);

        foreach (self::BRAND_MODELS as $brand => $modelGroups) {
            foreach ($modelGroups as $base => $variants) {
                // Verificar modelo base
                if (stripos($textLower, $base) !== false) {
                    $models[] = $base;
                }
                // Verificar variantes
                foreach ($variants as $variant) {
                    if (stripos($textLower, $variant) !== false) {
                        $models[] = $variant;
                    }
                }
            }
        }

        return array_unique($models);
    }

    private function detectBrand(string $text): ?string
    {
        foreach (self::BRAND_MODELS as $brand => $models) {
            if (stripos($text, $brand) !== false) {
                return $brand;
            }
            // Verificar também nos modelos
            foreach ($models as $base => $variants) {
                if (stripos($text, $base) !== false) {
                    return $brand;
                }
                foreach ($variants as $v) {
                    if (stripos($text, $v) !== false) {
                        return $brand;
                    }
                }
            }
        }
        return null;
    }

    private function findRelatedModels(string $brand, string $model): array
    {
        $related = [];

        if (!isset(self::BRAND_MODELS[$brand])) {
            return $related;
        }

        foreach (self::BRAND_MODELS[$brand] as $base => $variants) {
            if (stripos($model, $base) !== false || stripos($base, $model) !== false) {
                $related = array_merge($related, $variants);
            }
            foreach ($variants as $v) {
                if (stripos($model, $v) !== false || stripos($v, $model) !== false) {
                    $related = array_merge($related, $variants);
                    break;
                }
            }
        }

        return array_unique($related);
    }

    private function findModelFamily(string $brand, string $model): array
    {
        // Encontrar modelos da mesma "família" (mesmo cilindrada, tipo, etc)
        $family = [];

        // Extrair cilindrada/número do modelo
        preg_match('/(\d{2,3})/', $model, $matches);
        $cc = $matches[1] ?? null;

        if ($cc && isset(self::BRAND_MODELS[$brand])) {
            foreach (self::BRAND_MODELS[$brand] as $base => $variants) {
                if (strpos($base, $cc) !== false) {
                    $family[] = $base;
                }
            }
        }

        return $family;
    }

    private function extractFromItem(array $item): array
    {
        $models = [];

        foreach ($item['attributes'] ?? [] as $attr) {
            if (in_array($attr['id'], self::COMPATIBILITY_ATTRIBUTES)) {
                $value = $attr['value_name'] ?? '';
                if ($value) {
                    // Pode ser lista separada por vírgula
                    $parts = explode(',', $value);
                    foreach ($parts as $p) {
                        $models[] = trim($p);
                    }
                }
            }
        }

        return $models;
    }

    private function suggestExpansion(array $current): array
    {
        $currentModels = $current['models'] ?? [];
        return $this->expandCompatibility($currentModels);
    }

    private function detectMissingAttributes(array $item): array
    {
        $missing = [];
        $existingIds = array_map(fn($a) => $a['id'], $item['attributes'] ?? []);

        foreach (self::COMPATIBILITY_ATTRIBUTES as $attrId) {
            if (!in_array($attrId, $existingIds)) {
                $missing[] = $attrId;
            }
        }

        return $missing;
    }

    private function calculateCompatibilityScore(array $current): float
    {
        $modelCount = count($current['models'] ?? []);
        $brandCount = count($current['brands'] ?? []);
        $hasFromTitle = !empty($current['from_title']);
        $hasFromAttr = !empty($current['from_attributes']);

        $score = 0;
        
        // Pontuação por modelos
        $score += min(30, $modelCount * 5);
        
        // Pontuação por marcas
        $score += min(20, $brandCount * 5);
        
        // Bônus por fonte
        if ($hasFromTitle) $score += 15;
        if ($hasFromAttr) $score += 25;

        return min(100, $score);
    }

    private function getRecommendations(array $current, array $missing): array
    {
        $recommendations = [];

        if (empty($current['models'])) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Adicione modelos compatíveis para melhor visibilidade'
            ];
        }

        if (count($current['brands'] ?? []) < 2) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'Considere incluir compatibilidade com múltiplas marcas'
            ];
        }

        if (in_array('COMPATIBLE_MODELS', $missing)) {
            $recommendations[] = [
                'priority' => 'high',
                'message' => 'Preencha o atributo COMPATIBLE_MODELS'
            ];
        }

        if (empty($current['from_title'])) {
            $recommendations[] = [
                'priority' => 'medium',
                'message' => 'Mencione modelos compatíveis no título'
            ];
        }

        return $recommendations;
    }

    private function parseCapacity(string $capacity): float
    {
        preg_match('/(\d+(?:\.\d+)?)/', $capacity, $matches);
        return (float) ($matches[1] ?? 0);
    }

    private function getSpecsReasoning(array $specs): string
    {
        $capacity = $specs['capacity'] ?? null;
        
        if (!$capacity) {
            return 'Sem capacidade especificada - sugerindo compatibilidade universal';
        }

        $liters = $this->parseCapacity($capacity);
        
        if ($liters <= 30) {
            return "Capacidade pequena ({$liters}L) - ideal para scooters e motos urbanas";
        }
        if ($liters <= 45) {
            return "Capacidade média ({$liters}L) - compatível com maioria das motos";
        }
        return "Capacidade grande ({$liters}L) - ideal para motos maiores e viagens";
    }
}
