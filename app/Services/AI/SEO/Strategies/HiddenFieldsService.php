<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Services\MercadoLivreClient;

/**
 * 🔒 E2: Hidden Fields Service
 *
 * Gerencia campos ocultos do Mercado Livre que são indexados
 * mas não exibidos diretamente na listagem.
 *
 * Campos Ocultos Suportados:
 * - KEYWORDS: Palavras-chave ocultas (máx 60 chars)
 * - MPN: Manufacturer Part Number (código do fabricante)
 * - LINE: Linha do produto
 * - GTIN: Global Trade Item Number (EAN/UPC)
 * - ALPHANUMERIC_MODEL: Código alfanumérico do modelo
 *
 * @package App\Services\AI\SEO\Strategies
 */
class HiddenFieldsService
{
    private ?int $accountId;
    private ?MercadoLivreClient $client;

    /**
     * Mapa de campos ocultos com suas configurações
     */
    private const HIDDEN_FIELDS = [
        'KEYWORDS' => [
            'max_length' => 60,
            'weight' => 0.5,      // Peso SEO: 50%
            'priority' => 1,
            'indexable' => true,
            'description' => 'Palavras-chave ocultas para busca'
        ],
        'MPN' => [
            'max_length' => 70,
            'weight' => 0.3,      // Peso SEO: 30%
            'priority' => 2,
            'indexable' => true,
            'description' => 'Código do fabricante (part number)'
        ],
        'LINE' => [
            'max_length' => 255,
            'weight' => 0.3,
            'priority' => 3,
            'indexable' => true,
            'description' => 'Linha do produto'
        ],
        'GTIN' => [
            'max_length' => 14,
            'weight' => 0.2,
            'priority' => 4,
            'indexable' => true,
            'description' => 'Código de barras EAN/UPC'
        ],
        'ALPHANUMERIC_MODEL' => [
            'max_length' => 255,
            'weight' => 0.3,
            'priority' => 5,
            'indexable' => true,
            'description' => 'Código alfanumérico do modelo'
        ]
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = $accountId ? new MercadoLivreClient($accountId) : null;
    }

    /**
     * Analisa campos ocultos de um item
     *
     * @param string $itemId ID do item MLB
     * @return array Análise completa dos campos ocultos
     */
    public function analyzeItem(string $itemId): array
    {
        if (!$this->client) {
            throw new \RuntimeException('Cliente ML não configurado');
        }

        $item = $this->client->get("/items/{$itemId}");
        $attributes = $item['attributes'] ?? [];

        $analysis = [
            'item_id' => $itemId,
            'category_id' => $item['category_id'] ?? null,
            'hidden_fields' => [],
            'missing_fields' => [],
            'optimization_score' => 0,
            'recommendations' => []
        ];

        $filledCount = 0;
        $totalWeight = 0;

        foreach (self::HIDDEN_FIELDS as $fieldId => $config) {
            $currentValue = $this->findAttributeValue($attributes, $fieldId);
            $totalWeight += $config['weight'];

            $fieldAnalysis = [
                'id' => $fieldId,
                'current_value' => $currentValue,
                'max_length' => $config['max_length'],
                'is_filled' => !empty($currentValue),
                'usage_percent' => $currentValue
                    ? round((mb_strlen($currentValue) / $config['max_length']) * 100, 1)
                    : 0,
                'weight' => $config['weight'],
                'description' => $config['description']
            ];

            if (!empty($currentValue)) {
                $filledCount++;
                $analysis['hidden_fields'][$fieldId] = $fieldAnalysis;
            } else {
                $analysis['missing_fields'][$fieldId] = $fieldAnalysis;
                $analysis['recommendations'][] = [
                    'field' => $fieldId,
                    'action' => 'fill',
                    'message' => "Preencha {$config['description']} para melhorar indexação",
                    'impact' => $this->calculateImpact($config['weight'])
                ];
            }
        }

        // Calcular score de otimização
        $filledWeight = 0;
        foreach ($analysis['hidden_fields'] as $field) {
            $filledWeight += $field['weight'];
        }
        $analysis['optimization_score'] = $totalWeight > 0
            ? round(($filledWeight / $totalWeight) * 100)
            : 0;

        // Adicionar recomendações de otimização para campos existentes
        foreach ($analysis['hidden_fields'] as $fieldId => $field) {
            if ($field['usage_percent'] < 70) {
                $analysis['recommendations'][] = [
                    'field' => $fieldId,
                    'action' => 'optimize',
                    'message' => "Campo {$fieldId} usa apenas {$field['usage_percent']}% do espaço disponível",
                    'impact' => 'medium'
                ];
            }
        }

        return $analysis;
    }

    /**
     * Gera sugestões otimizadas para campos ocultos
     *
     * @param array $productData Dados do produto
     * @param string|null $categoryId ID da categoria
     * @return array Sugestões para cada campo oculto
     */
    public function generateSuggestions(array $productData, ?string $categoryId = null): array
    {
        $suggestions = [];
        $title = $productData['title'] ?? '';
        $brand = $productData['brand'] ?? '';
        $model = $productData['model'] ?? '';
        $attributes = $productData['attributes'] ?? [];

        // 1. KEYWORDS - Sinônimos e variações não usados no título
        $suggestions['KEYWORDS'] = $this->generateKeywordsSuggestion(
            $title,
            $brand,
            $model,
            $categoryId
        );

        // 2. MPN - Código do fabricante
        $suggestions['MPN'] = $this->generateMpnSuggestion(
            $model,
            $brand,
            $attributes
        );

        // 3. LINE - Linha do produto
        $suggestions['LINE'] = $this->generateLineSuggestion(
            $productData,
            $categoryId
        );

        // 4. GTIN - Código de barras (se disponível)
        $suggestions['GTIN'] = $this->extractGtin($attributes);

        // 5. ALPHANUMERIC_MODEL - Código alfanumérico
        $suggestions['ALPHANUMERIC_MODEL'] = $this->generateAlphanumericModel(
            $model,
            $brand,
            $attributes
        );

        return [
            'suggestions' => $suggestions,
            'category_id' => $categoryId,
            'generated_at' => date('Y-m-d H:i:s'),
            'total_fields' => count(array_filter($suggestions, fn($s) => !empty($s['value'])))
        ];
    }

    /**
     * Gera sugestão otimizada para campo KEYWORDS
     */
    private function generateKeywordsSuggestion(
        string $title,
        string $brand,
        string $model,
        ?string $categoryId
    ): array {
        $maxLength = self::HIDDEN_FIELDS['KEYWORDS']['max_length'];
        $keywords = [];

        // Buscar sinônimos não usados no título
        if ($categoryId) {
            $synonymService = new SynonymExpansionService($this->accountId);
            $titleWords = array_map('mb_strtolower', explode(' ', $title));

            // Extrair keyword principal do título
            $mainKeyword = $this->extractMainKeyword($title);
            if ($mainKeyword) {
                $expansion = $synonymService->expand($mainKeyword, $categoryId, [
                    'levels' => [2, 3, 4], // Níveis secundários
                    'limit_per_level' => 3
                ]);

                foreach ($expansion['synonyms'] ?? [] as $synonym) {
                    if (is_array($synonym)) {
                        $word = $synonym['word'] ?? $synonym['value'] ?? '';
                    } else {
                        $word = (string)$synonym;
                    }
                    if (empty($word)) continue;

                    $word = mb_strtolower($word);
                    if (!in_array($word, $titleWords)) {
                        $keywords[] = $word;
                    }
                }
            }
        }

        // Adicionar variações de marca/modelo
        if ($brand && stripos($title, $brand) === false) {
            $keywords[] = $brand;
        }
        if ($model && stripos($title, $model) === false) {
            $keywords[] = $model;
        }

        // Adicionar variações comuns
        $keywords = array_merge($keywords, $this->getCommonVariations($title));

        // Montar string otimizada
        $keywords = array_unique(array_filter($keywords));
        $value = $this->buildOptimizedString($keywords, $maxLength);

        return [
            'field_id' => 'KEYWORDS',
            'value' => $value,
            'length' => mb_strlen($value),
            'max_length' => $maxLength,
            'usage_percent' => round((mb_strlen($value) / $maxLength) * 100, 1),
            'keywords_used' => explode(' ', $value)
        ];
    }

    /**
     * Gera sugestão para campo MPN
     */
    private function generateMpnSuggestion(
        string $model,
        string $brand,
        array $attributes
    ): array {
        $maxLength = self::HIDDEN_FIELDS['MPN']['max_length'];
        $mpn = '';

        // Verificar se já existe MPN nos atributos
        $existingMpn = $this->findAttributeValue($attributes, 'MPN');
        if ($existingMpn) {
            $mpn = $existingMpn;
        } elseif ($model) {
            // Construir MPN a partir do modelo
            $mpn = $this->cleanForMpn($model);

            // Adicionar prefixo da marca se houver espaço
            if ($brand && mb_strlen($mpn) + mb_strlen($brand) + 1 <= $maxLength) {
                $mpn = mb_strtoupper(mb_substr($brand, 0, 3)) . '-' . $mpn;
            }
        }

        // Verificar código de fabricante em outros atributos
        $partNumber = $this->findAttributeValue($attributes, 'PART_NUMBER');
        if ($partNumber && mb_strlen($partNumber) <= $maxLength) {
            $mpn = $partNumber;
        }

        return [
            'field_id' => 'MPN',
            'value' => mb_substr($mpn, 0, $maxLength),
            'length' => mb_strlen($mpn),
            'max_length' => $maxLength,
            'source' => $existingMpn ? 'existing' : 'generated'
        ];
    }

    /**
     * Gera sugestão para campo LINE
     */
    private function generateLineSuggestion(array $productData, ?string $categoryId): array
    {
        $maxLength = self::HIDDEN_FIELDS['LINE']['max_length'];
        $line = '';

        $brand = $productData['brand'] ?? '';
        $model = $productData['model'] ?? '';
        $title = $productData['title'] ?? '';

        // Estratégia: Marca + Linha + Categoria
        $parts = [];

        if ($brand) {
            $parts[] = $brand;
        }

        // Extrair linha do título ou modelo
        $lineFromTitle = $this->extractProductLine($title);
        if ($lineFromTitle) {
            $parts[] = $lineFromTitle;
        }

        // Adicionar nome da categoria se disponível
        if ($categoryId && $this->client) {
            try {
                $category = $this->client->get("/categories/{$categoryId}");
                if (!empty($category['name'])) {
                    $parts[] = $category['name'];
                }
            } catch (\Exception $e) {
                // Ignorar erro de categoria
            }
        }

        $line = implode(' ', array_unique(array_filter($parts)));

        return [
            'field_id' => 'LINE',
            'value' => mb_substr($line, 0, $maxLength),
            'length' => mb_strlen($line),
            'max_length' => $maxLength
        ];
    }

    /**
     * Extrai GTIN dos atributos existentes
     */
    private function extractGtin(array $attributes): array
    {
        $maxLength = self::HIDDEN_FIELDS['GTIN']['max_length'];

        // Procurar em diferentes atributos
        $gtinFields = ['GTIN', 'EAN', 'UPC', 'ISBN', 'JAN'];
        $gtin = '';

        foreach ($gtinFields as $field) {
            $value = $this->findAttributeValue($attributes, $field);
            if ($value && preg_match('/^\d{8,14}$/', $value)) {
                $gtin = $value;
                break;
            }
        }

        return [
            'field_id' => 'GTIN',
            'value' => $gtin,
            'length' => strlen($gtin),
            'max_length' => $maxLength,
            'is_valid' => !empty($gtin) && $this->validateGtin($gtin)
        ];
    }

    /**
     * Gera código alfanumérico do modelo
     */
    private function generateAlphanumericModel(
        string $model,
        string $brand,
        array $attributes
    ): array {
        $maxLength = self::HIDDEN_FIELDS['ALPHANUMERIC_MODEL']['max_length'];

        // Verificar se já existe
        $existing = $this->findAttributeValue($attributes, 'ALPHANUMERIC_MODEL');
        if ($existing) {
            return [
                'field_id' => 'ALPHANUMERIC_MODEL',
                'value' => $existing,
                'length' => strlen($existing),
                'max_length' => $maxLength,
                'source' => 'existing'
            ];
        }

        // Gerar a partir do modelo
        $alphaModel = $this->cleanForMpn($model);

        // Incluir variações
        $variations = [];
        if ($model) {
            $variations[] = $alphaModel;
            $variations[] = str_replace(['-', '_', ' '], '', $alphaModel);
            $variations[] = preg_replace('/[^A-Z0-9]/i', '', $model);
        }

        $value = implode(' ', array_unique(array_filter($variations)));

        return [
            'field_id' => 'ALPHANUMERIC_MODEL',
            'value' => mb_substr($value, 0, $maxLength),
            'length' => mb_strlen($value),
            'max_length' => $maxLength,
            'source' => 'generated'
        ];
    }

    /**
     * Aplica campos ocultos otimizados a um item
     *
     * @param string $itemId ID do item
     * @param array $fields Campos a atualizar
     * @param bool $dryRun Se true, apenas simula
     * @return array Resultado da aplicação
     */
    public function applyToItem(string $itemId, array $fields, bool $dryRun = false): array
    {
        if (!$this->client) {
            throw new \RuntimeException('Cliente ML não configurado');
        }

        $attributes = [];
        $applied = [];
        $errors = [];

        foreach ($fields as $fieldId => $value) {
            if (!isset(self::HIDDEN_FIELDS[$fieldId])) {
                $errors[] = "Campo desconhecido: {$fieldId}";
                continue;
            }

            $config = self::HIDDEN_FIELDS[$fieldId];
            $cleanValue = $this->sanitizeValue($value, $config['max_length']);

            $attributes[] = [
                'id' => $fieldId,
                'value_name' => $cleanValue
            ];

            $applied[$fieldId] = [
                'value' => $cleanValue,
                'length' => strlen($cleanValue),
                'max_length' => $config['max_length']
            ];
        }

        if (empty($attributes)) {
            return [
                'success' => false,
                'error' => 'Nenhum campo válido para aplicar',
                'errors' => $errors
            ];
        }

        if ($dryRun) {
            return [
                'success' => true,
                'dry_run' => true,
                'would_apply' => $applied,
                'errors' => $errors
            ];
        }

        // Aplicar via API
        try {
            $this->client->put("/items/{$itemId}", [
                'attributes' => $attributes
            ]);

            return [
                'success' => true,
                'item_id' => $itemId,
                'applied' => $applied,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'attempted' => $applied
            ];
        }
    }

    /**
     * Obtém campos ocultos disponíveis para uma categoria
     */
    public function getAvailableFields(string $categoryId): array
    {
        if (!$this->client) {
            return ['fields' => self::HIDDEN_FIELDS];
        }

        try {
            $categoryAttrs = $this->client->get("/categories/{$categoryId}/attributes");
            $available = [];

            foreach ($categoryAttrs as $attr) {
                $attrId = $attr['id'] ?? '';
                if (isset(self::HIDDEN_FIELDS[$attrId])) {
                    $available[$attrId] = array_merge(
                        self::HIDDEN_FIELDS[$attrId],
                        [
                            'ml_name' => $attr['name'] ?? $attrId,
                            'required' => $attr['tags']['required'] ?? false,
                            'hint' => $attr['hint'] ?? null
                        ]
                    );
                }
            }

            // Adicionar campos não encontrados mas conhecidos
            foreach (self::HIDDEN_FIELDS as $fieldId => $config) {
                if (!isset($available[$fieldId])) {
                    $available[$fieldId] = array_merge($config, [
                        'available_in_category' => false
                    ]);
                } else {
                    $available[$fieldId]['available_in_category'] = true;
                }
            }

            return [
                'category_id' => $categoryId,
                'fields' => $available,
                'total_available' => count(array_filter(
                    $available,
                    fn($f) => $f['available_in_category'] ?? true
                ))
            ];
        } catch (\Exception $e) {
            return [
                'category_id' => $categoryId,
                'fields' => self::HIDDEN_FIELDS,
                'error' => $e->getMessage()
            ];
        }
    }

    // ========================================================================
    // MÉTODOS AUXILIARES
    // ========================================================================

    private function findAttributeValue(array $attributes, string $id): ?string
    {
        foreach ($attributes as $attr) {
            if (($attr['id'] ?? '') === $id) {
                return $attr['value_name'] ?? $attr['value_id'] ?? null;
            }
        }
        return null;
    }

    private function extractMainKeyword(string $title): ?string
    {
        // Remove palavras comuns e pega a primeira significativa
        $stopWords = ['para', 'com', 'sem', 'de', 'da', 'do', 'em', 'no', 'na', 'e', 'ou'];
        $words = preg_split('/\s+/', mb_strtolower($title));

        foreach ($words as $word) {
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                return $word;
            }
        }

        return $words[0] ?? null;
    }

    private function getCommonVariations(string $title): array
    {
        $variations = [];
        $title = mb_strtolower($title);

        // Mapeamento de variações comuns
        $variationMap = [
            'moto' => ['motocicleta', 'motoboy'],
            'bau' => ['bauleto', 'baú', 'bagageiro'],
            'universal' => ['compatível', 'serve'],
            'original' => ['genuíno', 'legítimo'],
            'novo' => ['lacrado', 'zero'],
        ];

        foreach ($variationMap as $word => $synonyms) {
            if (stripos($title, $word) !== false) {
                foreach ($synonyms as $syn) {
                    if (stripos($title, $syn) === false) {
                        $variations[] = $syn;
                    }
                }
            }
        }

        return $variations;
    }

    private function buildOptimizedString(array $words, int $maxLength): string
    {
        $result = '';

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word)) continue;

            $addition = ($result ? ' ' : '') . $word;
            if (strlen($result . $addition) <= $maxLength) {
                $result .= $addition;
            }
        }

        return $result;
    }

    private function cleanForMpn(string $value): string
    {
        // Remove caracteres especiais mantendo alfanuméricos e hífen
        $clean = preg_replace('/[^A-Za-z0-9\-]/', '', $value);
        return strtoupper($clean);
    }

    private function extractProductLine(string $title): ?string
    {
        // Padrões comuns de linha de produto
        $patterns = [
            '/linha\s+(\w+)/i',
            '/série\s+(\w+)/i',
            '/coleção\s+(\w+)/i',
            '/^(\w+)\s+(?:pro|premium|plus|max|ultra)/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    private function validateGtin(string $gtin): bool
    {
        if (!preg_match('/^\d{8,14}$/', $gtin)) {
            return false;
        }

        // Validação de checksum para EAN-13
        if (strlen($gtin) === 13) {
            $sum = 0;
            for ($i = 0; $i < 12; $i++) {
                $sum += (int)$gtin[$i] * ($i % 2 === 0 ? 1 : 3);
            }
            $checkDigit = (10 - ($sum % 10)) % 10;
            return $checkDigit === (int)$gtin[12];
        }

        return true; // Para outros formatos, aceitar sem validação
    }

    private function sanitizeValue(string $value, int $maxLength): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return mb_substr($value, 0, $maxLength);
    }

    private function calculateImpact(float $weight): string
    {
        if ($weight >= 0.5) return 'high';
        if ($weight >= 0.3) return 'medium';
        return 'low';
    }
}
