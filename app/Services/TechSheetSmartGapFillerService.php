<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\TechSheetService;
use App\Services\TechSheetBenchmarkService;
use App\Services\TitleAttributeExtractorService;
use App\Services\KeywordMinerService;
use App\Services\AI\SEO\AttributeKiller;
use PDO;

/**
 * 🎯 Tech Sheet Smart Gap Filler Service
 * 
 * Serviço inteligente para preencher lacunas da ficha técnica usando múltiplas
 * fontes de dados e estratégias SEO avançadas:
 * 
 * FONTES DE DADOS:
 * 1. Título do produto - Extração via regex e dicionários
 * 2. Descrição do produto - NLP para extrair atributos
 * 3. Concorrentes (Benchmark) - Análise de top sellers
 * 4. ML Autocomplete - Sugestões de busca do ML
 * 5. ML Trends - Tendências de busca por categoria
 * 6. Histórico de vendas - Atributos que vendem mais
 * 7. IA (Claude/OpenAI) - Para atributos complexos
 * 
 * ESTRATÉGIAS SEO:
 * - Keywords de alto volume de busca
 * - Long-tail keywords
 * - Sinônimos e variações
 * - Contextos de uso
 * - Compatibilidade expandida
 * 
 * @package App\Services
 */
class TechSheetSmartGapFillerService
{
    private PDO $db;
    private int $accountId;
    private MercadoLivreClient $mlClient;
    private TitleAttributeExtractorService $titleExtractor;
    private AttributeKiller $attributeKiller;
    private ?TechSheetBenchmarkService $benchmarkService = null;
    private ?KeywordMinerService $keywordMiner = null;
    
    /** @var array Configuração de confiança por fonte */
    private array $confidenceBySource = [
        'title_exact'       => 95, // Match exato no título
        'title_pattern'     => 88, // Padrão regex no título
        'description'       => 75, // Extraído da descrição
        'benchmark_top'     => 92, // Top 1 concorrente
        'benchmark_common'  => 85, // Valor mais comum
        'autocomplete'      => 80, // Autocomplete ML
        'trends'            => 78, // Tendências ML
        'history'           => 82, // Histórico de vendas
        'ai_inference'      => 70, // IA genérica
        'ai_validated'      => 85, // IA com validação
        'default'           => 60, // Valor padrão da categoria
    ];

    /** @var array Atributos que podem usar IA */
    private array $aiAllowedAttributes = [
        'COLOR', 'MAIN_COLOR', 'SIZE', 'MATERIAL', 'WEIGHT', 'DIMENSIONS',
        'STYLE', 'FINISH', 'PATTERN', 'SHAPE', 'CAPACITY', 'POWER',
    ];

    /** @var array Atributos que NUNCA podem usar IA (precisam de dados reais) */
    private array $aiBlockedAttributes = [
        'BRAND', 'MODEL', 'GTIN', 'MPN', 'EAN', 'UPC', 'ISBN', 'PART_NUMBER',
        'SKU', 'MANUFACTURER', 'SERIAL_NUMBER', 'ALPHANUMERIC_MODEL',
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->titleExtractor = new TitleAttributeExtractorService();
        $this->attributeKiller = new AttributeKiller($accountId);
    }

    /**
     * Obtém instância do benchmark service (lazy load)
     */
    private function getBenchmarkService(): TechSheetBenchmarkService
    {
        if ($this->benchmarkService === null) {
            $this->benchmarkService = new TechSheetBenchmarkService($this->accountId);
        }
        return $this->benchmarkService;
    }

    /**
     * Obtém instância do keyword miner (lazy load)
     */
    private function getKeywordMiner(): KeywordMinerService
    {
        if ($this->keywordMiner === null) {
            $this->keywordMiner = new KeywordMinerService();
        }
        return $this->keywordMiner;
    }

    /**
     * 🎯 Preenche lacunas de forma inteligente
     * Usa todas as fontes disponíveis para maximizar cobertura e confiança
     * 
     * @param string $itemId ID do item
     * @param array $options Opções: sources, min_confidence, max_suggestions, include_applied
     * @return array Resultado com sugestões ranqueadas
     */
    public function fillGaps(string $itemId, array $options = []): array
    {
        $startTime = microtime(true);
        
        // Configurações
        $enabledSources = $options['sources'] ?? ['title', 'description', 'benchmark', 'autocomplete', 'trends'];
        $minConfidence = (int)($options['min_confidence'] ?? 50);
        $maxSuggestionsPerGap = (int)($options['max_suggestions'] ?? 3);
        $includeAlreadyFilled = (bool)($options['include_applied'] ?? false);

        // 1. Obter dados do item
        $item = $this->getItemData($itemId);
        if (!$item['success']) {
            return $item;
        }

        $itemData = $item['data'];
        $categoryId = $itemData['category_id'] ?? '';
        $title = $itemData['title'] ?? '';
        $description = $item['description'] ?? '';

        // 2. Analisar gaps
        $gapsAnalysis = $this->attributeKiller->analyzeGaps($itemId, $categoryId, $itemData);
        $allGaps = $this->flattenGaps($gapsAnalysis['gaps'] ?? []);
        
        if (empty($allGaps)) {
            return [
                'success' => true,
                'item_id' => $itemId,
                'message' => 'Nenhuma lacuna encontrada - ficha técnica completa!',
                'gaps_count' => 0,
                'suggestions' => [],
            ];
        }

        // 3. Coletar sugestões de todas as fontes
        $allSuggestions = [];
        $sourcesUsed = [];

        // Fonte 1: Título
        if (in_array('title', $enabledSources)) {
            $titleSuggestions = $this->extractFromTitle($title, $allGaps, $categoryId);
            $allSuggestions = array_merge($allSuggestions, $titleSuggestions);
            if (!empty($titleSuggestions)) {
                $sourcesUsed['title'] = count($titleSuggestions);
            }
        }

        // Fonte 2: Descrição
        if (in_array('description', $enabledSources) && !empty($description)) {
            $descSuggestions = $this->extractFromDescription($description, $allGaps, $categoryId);
            $allSuggestions = array_merge($allSuggestions, $descSuggestions);
            if (!empty($descSuggestions)) {
                $sourcesUsed['description'] = count($descSuggestions);
            }
        }

        // Fonte 3: Benchmark de concorrentes
        if (in_array('benchmark', $enabledSources)) {
            $benchSuggestions = $this->getBenchmarkSuggestions($itemId, $categoryId, $title, $allGaps);
            $allSuggestions = array_merge($allSuggestions, $benchSuggestions);
            if (!empty($benchSuggestions)) {
                $sourcesUsed['benchmark'] = count($benchSuggestions);
            }
        }

        // Fonte 4: Autocomplete ML
        if (in_array('autocomplete', $enabledSources)) {
            $autocompleteSuggestions = $this->getAutocompleteSuggestions($title, $allGaps, $categoryId);
            $allSuggestions = array_merge($allSuggestions, $autocompleteSuggestions);
            if (!empty($autocompleteSuggestions)) {
                $sourcesUsed['autocomplete'] = count($autocompleteSuggestions);
            }
        }

        // Fonte 5: Trends ML
        if (in_array('trends', $enabledSources)) {
            $trendsSuggestions = $this->getTrendsSuggestions($categoryId, $allGaps);
            $allSuggestions = array_merge($allSuggestions, $trendsSuggestions);
            if (!empty($trendsSuggestions)) {
                $sourcesUsed['trends'] = count($trendsSuggestions);
            }
        }

        // Fonte 6: Histórico de vendas
        if (in_array('history', $enabledSources)) {
            $historySuggestions = $this->getHistorySuggestions($categoryId, $allGaps);
            $allSuggestions = array_merge($allSuggestions, $historySuggestions);
            if (!empty($historySuggestions)) {
                $sourcesUsed['history'] = count($historySuggestions);
            }
        }

        // Fonte 7: IA (apenas para atributos permitidos)
        if (in_array('ai', $enabledSources)) {
            $aiSuggestions = $this->getAISuggestions($itemData, $allGaps);
            $allSuggestions = array_merge($allSuggestions, $aiSuggestions);
            if (!empty($aiSuggestions)) {
                $sourcesUsed['ai'] = count($aiSuggestions);
            }
        }

        // 4. Consolidar e ranquear sugestões
        $consolidatedSuggestions = $this->consolidateSuggestions(
            $allSuggestions,
            $minConfidence,
            $maxSuggestionsPerGap
        );

        // 5. Salvar sugestões no banco
        $savedCount = $this->saveSuggestions($itemId, $categoryId, $consolidatedSuggestions);

        $elapsedMs = round((microtime(true) - $startTime) * 1000);

        return [
            'success' => true,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'gaps_analyzed' => count($allGaps),
            'gaps_covered' => count(array_unique(array_column($consolidatedSuggestions, 'attribute_id'))),
            'total_suggestions' => count($consolidatedSuggestions),
            'saved_count' => $savedCount,
            'sources_used' => $sourcesUsed,
            'suggestions' => $consolidatedSuggestions,
            'elapsed_ms' => $elapsedMs,
        ];
    }

    /**
     * Obtém dados do item do cache ou API
     */
    private function getItemData(string $itemId): array
    {
        // Tentar cache local primeiro
        $stmt = $this->db->prepare("
            SELECT ml_item_id, title, category_id, api_data, description
            FROM items 
            WHERE account_id = :account_id AND ml_item_id = :item_id
        ");
        $stmt->execute([':account_id' => $this->accountId, ':item_id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $apiData = $row['api_data'] ? json_decode($row['api_data'], true) : [];
            return [
                'success' => true,
                'data' => array_merge($apiData, [
                    'id' => $row['ml_item_id'],
                    'title' => $row['title'],
                    'category_id' => $row['category_id'],
                ]),
                'description' => $row['description'] ?? '',
            ];
        }

        // Fallback para API
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return ['success' => false, 'error' => 'Item não encontrado'];
        }

        // Obter descrição
        $descData = $this->mlClient->get("/items/{$itemId}/description");
        $description = $descData['plain_text'] ?? ($descData['text'] ?? '');

        return [
            'success' => true,
            'data' => $item,
            'description' => $description,
        ];
    }

    /**
     * Flatten gaps de todas as prioridades em um array único
     */
    private function flattenGaps(array $gapsByPriority): array
    {
        $all = [];
        foreach (['required', 'filter', 'hidden', 'recommended'] as $priority) {
            foreach ($gapsByPriority[$priority] ?? [] as $gap) {
                $gap['priority'] = $priority;
                $all[] = $gap;
            }
        }
        return $all;
    }

    /**
     * 📝 Extração do Título
     */
    private function extractFromTitle(string $title, array $gaps, string $categoryId): array
    {
        $categoryType = $this->titleExtractor->detectCategoryType($title);
        $extracted = $this->titleExtractor->extractFromTitle($title, [], $categoryType);
        
        $suggestions = [];
        foreach ($extracted as $ext) {
            $attrId = $ext['attribute_id'] ?? '';
            $value = $ext['value'] ?? '';
            
            if (empty($attrId) || empty($value)) {
                continue;
            }

            // Mapear para gap correspondente
            $matchedGap = $this->findMatchingGap($attrId, $value, $gaps);
            if (!$matchedGap) {
                continue;
            }

            $confidence = $this->calculateTitleConfidence($ext);
            
            $suggestions[] = [
                'attribute_id' => $matchedGap['id'],
                'attribute_name' => $matchedGap['name'] ?? $attrId,
                'suggested_value' => $value,
                'source' => 'title',
                'source_detail' => $ext['method'] ?? 'regex',
                'confidence' => $confidence,
                'priority' => $matchedGap['priority'] ?? 'recommended',
                'meta' => [
                    'original_attr' => $attrId,
                    'extraction_method' => $ext['method'] ?? 'regex',
                ],
            ];
        }

        return $suggestions;
    }

    /**
     * 📄 Extração da Descrição usando NLP simplificado
     */
    private function extractFromDescription(string $description, array $gaps, string $categoryId): array
    {
        $suggestions = [];
        $description = strip_tags($description);
        $descLower = mb_strtolower($description);

        foreach ($gaps as $gap) {
            $attrId = $gap['id'] ?? '';
            $attrName = $gap['name'] ?? '';
            $allowedValues = $gap['allowed_values'] ?? [];

            // Se tem valores permitidos, buscar na descrição
            if (!empty($allowedValues)) {
                foreach ($allowedValues as $allowed) {
                    $valueName = $allowed['name'] ?? ($allowed['value_name'] ?? '');
                    if (empty($valueName)) {
                        continue;
                    }

                    // Busca case-insensitive
                    if (mb_stripos($descLower, mb_strtolower($valueName)) !== false) {
                        $suggestions[] = [
                            'attribute_id' => $attrId,
                            'attribute_name' => $attrName,
                            'suggested_value' => $valueName,
                            'source' => 'description',
                            'source_detail' => 'allowed_value_match',
                            'confidence' => $this->confidenceBySource['description'],
                            'priority' => $gap['priority'] ?? 'recommended',
                            'meta' => [
                                'matched_in' => 'description',
                            ],
                        ];
                        break; // Pegar só o primeiro match
                    }
                }
            }

            // Padrões específicos para atributos comuns
            $patternSuggestion = $this->extractByPattern($description, $attrId);
            if ($patternSuggestion) {
                $suggestions[] = [
                    'attribute_id' => $attrId,
                    'attribute_name' => $attrName,
                    'suggested_value' => $patternSuggestion,
                    'source' => 'description',
                    'source_detail' => 'pattern_match',
                    'confidence' => $this->confidenceBySource['description'] - 5,
                    'priority' => $gap['priority'] ?? 'recommended',
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Extrai valor por padrão regex específico do atributo
     */
    private function extractByPattern(string $text, string $attrId): ?string
    {
        $patterns = [
            'WEIGHT' => '/(\d+(?:[.,]\d+)?)\s*(kg|g|gramas?|kilos?)/i',
            'CAPACITY' => '/(\d+(?:[.,]\d+)?)\s*(l|lt|litros?|ml)/i',
            'POWER' => '/(\d+(?:[.,]\d+)?)\s*(w|watts?|va|hp|cv)/i',
            'VOLTAGE' => '/(110v?|220v?|bivolt|bi-volt|127v?)/i',
            'DIMENSIONS' => '/(\d+(?:[.,]\d+)?)\s*x\s*(\d+(?:[.,]\d+)?)\s*(?:x\s*(\d+(?:[.,]\d+)?))?\s*(cm|m|mm)?/i',
            'HEIGHT' => '/altura[:\s]*(\d+(?:[.,]\d+)?)\s*(cm|m|mm)?/i',
            'WIDTH' => '/largura[:\s]*(\d+(?:[.,]\d+)?)\s*(cm|m|mm)?/i',
            'LENGTH' => '/comprimento[:\s]*(\d+(?:[.,]\d+)?)\s*(cm|m|mm)?/i',
        ];

        $pattern = $patterns[$attrId] ?? null;
        if (!$pattern) {
            return null;
        }

        if (preg_match($pattern, $text, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }

    /**
     * 🏆 Sugestões de Benchmark (concorrentes)
     */
    private function getBenchmarkSuggestions(string $itemId, string $categoryId, string $title, array $gaps): array
    {
        try {
            $benchmark = $this->getBenchmarkService();
            $result = $benchmark->generateBenchmarkSuggestions($itemId, $categoryId, $gaps);
            
            if (!($result['success'] ?? false)) {
                return [];
            }

            $suggestions = [];
            foreach ($result['suggestions'] ?? [] as $sugg) {
                $confidence = $sugg['confidence'] ?? $this->confidenceBySource['benchmark_common'];
                
                // Boost para valores mais frequentes
                if (isset($sugg['meta']['frequency']) && $sugg['meta']['frequency'] >= 0.5) {
                    $confidence = min(95, $confidence + 5);
                }

                $suggestions[] = [
                    'attribute_id' => $sugg['attribute_id'],
                    'attribute_name' => $sugg['attribute_name'] ?? $sugg['attribute_id'],
                    'suggested_value' => $sugg['suggested_value'],
                    'source' => 'benchmark',
                    'source_detail' => $sugg['source'] ?? 'competitor',
                    'confidence' => $confidence,
                    'priority' => $this->getGapPriority($sugg['attribute_id'], $gaps),
                    'meta' => $sugg['meta'] ?? [],
                ];
            }

            return $suggestions;
        } catch (\Exception $e) {
            log_warning('SmartGapFiller benchmark error', ['service' => 'TechSheetSmartGapFillerService', 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 🔍 Sugestões via Autocomplete ML
     */
    private function getAutocompleteSuggestions(string $title, array $gaps, string $categoryId): array
    {
        try {
            $miner = $this->getKeywordMiner();
            $suggestions = [];

            // Extrair keywords base do título
            $baseTerms = $this->extractBaseTerms($title);
            
            foreach ($baseTerms as $term) {
                $autocomplete = $miner->mineFromAutocomplete($term, [
                    'category_id' => $categoryId,
                    'limit' => 10,
                ]);

                if (empty($autocomplete['suggestions'])) {
                    continue;
                }

                // Analisar sugestões para extrair atributos
                foreach ($autocomplete['suggestions'] as $suggestion) {
                    $text = $suggestion['q'] ?? ($suggestion['text'] ?? '');
                    
                    // Extrair atributos da sugestão
                    $extracted = $this->titleExtractor->extractFromTitle($text, [], null);
                    
                    foreach ($extracted as $ext) {
                        $matchedGap = $this->findMatchingGap($ext['attribute_id'] ?? '', $ext['value'] ?? '', $gaps);
                        if (!$matchedGap) {
                            continue;
                        }

                        $suggestions[] = [
                            'attribute_id' => $matchedGap['id'],
                            'attribute_name' => $matchedGap['name'] ?? '',
                            'suggested_value' => $ext['value'],
                            'source' => 'autocomplete',
                            'source_detail' => 'ml_suggestion',
                            'confidence' => $this->confidenceBySource['autocomplete'],
                            'priority' => $matchedGap['priority'] ?? 'recommended',
                            'meta' => [
                                'autocomplete_query' => $term,
                                'autocomplete_result' => $text,
                            ],
                        ];
                    }
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            log_warning('SmartGapFiller autocomplete error', ['service' => 'TechSheetSmartGapFillerService', 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 📈 Sugestões via Trends ML
     */
    private function getTrendsSuggestions(string $categoryId, array $gaps): array
    {
        if (empty($categoryId)) {
            return [];
        }

        try {
            // Buscar trends da categoria
            $trends = $this->mlClient->get("/trends/MLB/categories/{$categoryId}");
            
            if (empty($trends['keywords']) && empty($trends['terms'])) {
                return [];
            }

            $suggestions = [];
            $trendTerms = $trends['keywords'] ?? ($trends['terms'] ?? []);

            foreach ($trendTerms as $trend) {
                $term = $trend['keyword'] ?? ($trend['term'] ?? '');
                if (empty($term)) {
                    continue;
                }

                // Extrair atributos do termo de tendência
                $extracted = $this->titleExtractor->extractFromTitle($term, [], null);
                
                foreach ($extracted as $ext) {
                    $matchedGap = $this->findMatchingGap($ext['attribute_id'] ?? '', $ext['value'] ?? '', $gaps);
                    if (!$matchedGap) {
                        continue;
                    }

                    $confidence = $this->confidenceBySource['trends'];
                    // Boost por volume de busca
                    if (isset($trend['volume']) && $trend['volume'] > 1000) {
                        $confidence = min(90, $confidence + 5);
                    }

                    $suggestions[] = [
                        'attribute_id' => $matchedGap['id'],
                        'attribute_name' => $matchedGap['name'] ?? '',
                        'suggested_value' => $ext['value'],
                        'source' => 'trends',
                        'source_detail' => 'category_trend',
                        'confidence' => $confidence,
                        'priority' => $matchedGap['priority'] ?? 'recommended',
                        'meta' => [
                            'trend_term' => $term,
                            'trend_volume' => $trend['volume'] ?? null,
                        ],
                    ];
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            log_warning('SmartGapFiller trends error', ['service' => 'TechSheetSmartGapFillerService', 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 📊 Sugestões baseadas em histórico de vendas
     */
    private function getHistorySuggestions(string $categoryId, array $gaps): array
    {
        try {
            // Buscar valores mais usados em itens com boas vendas
            $suggestions = [];
            
            foreach ($gaps as $gap) {
                $attrId = $gap['id'] ?? '';
                if (empty($attrId)) {
                    continue;
                }

                // Query: valores mais comuns em itens vendidos da categoria
                $stmt = $this->db->prepare("
                    SELECT 
                        JSON_UNQUOTE(JSON_EXTRACT(api_data, '$.attributes[*].value_name')) as attr_values,
                        COUNT(*) as freq,
                        SUM(COALESCE(sold_quantity, 0)) as total_sold
                    FROM items
                    WHERE account_id = :account_id
                      AND category_id = :category_id
                      AND status = 'active'
                      AND api_data IS NOT NULL
                      AND JSON_CONTAINS_PATH(api_data, 'one', '$.attributes')
                    GROUP BY attr_values
                    HAVING total_sold > 0
                    ORDER BY total_sold DESC
                    LIMIT 5
                ");
                $stmt->execute([
                    ':account_id' => $this->accountId,
                    ':category_id' => $categoryId,
                ]);
                
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($rows as $row) {
                    // Parse dos valores e encontrar o atributo específico
                    $attrValues = json_decode($row['attr_values'] ?? '[]', true);
                    if (!is_array($attrValues)) {
                        continue;
                    }

                    foreach ($attrValues as $value) {
                        if (empty($value)) {
                            continue;
                        }

                        // Verificar se valor é válido para o atributo
                        if ($this->isValueValidForGap($value, $gap)) {
                            $suggestions[] = [
                                'attribute_id' => $attrId,
                                'attribute_name' => $gap['name'] ?? '',
                                'suggested_value' => $value,
                                'source' => 'history',
                                'source_detail' => 'best_sellers',
                                'confidence' => $this->confidenceBySource['history'],
                                'priority' => $gap['priority'] ?? 'recommended',
                                'meta' => [
                                    'total_sold' => $row['total_sold'],
                                    'frequency' => $row['freq'],
                                ],
                            ];
                            break;
                        }
                    }
                }
            }

            return $suggestions;
        } catch (\Exception $e) {
            log_warning('SmartGapFiller history error', ['service' => 'TechSheetSmartGapFillerService', 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 🤖 Sugestões via IA (apenas para atributos permitidos)
     */
    private function getAISuggestions(array $itemData, array $gaps): array
    {
        $suggestions = [];
        
        foreach ($gaps as $gap) {
            $attrId = $gap['id'] ?? '';
            
            // Verificar se IA é permitida para este atributo
            if (in_array($attrId, $this->aiBlockedAttributes)) {
                continue;
            }
            
            // Verificar se está na whitelist de IA
            $isAllowed = false;
            foreach ($this->aiAllowedAttributes as $allowed) {
                if (stripos($attrId, $allowed) !== false) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed) {
                continue;
            }

            // Tentar inferir via AttributeKiller
            try {
                $inference = $this->attributeKiller->inferAttributeValue(
                    $attrId,
                    $itemData,
                    $gap['allowed_values'] ?? []
                );
                
                if (!empty($inference['value'])) {
                    $confidence = $this->confidenceBySource['ai_inference'];
                    
                    // Boost se valor está nos allowed_values
                    if ($this->isValueValidForGap($inference['value'], $gap)) {
                        $confidence = $this->confidenceBySource['ai_validated'];
                    }

                    $suggestions[] = [
                        'attribute_id' => $attrId,
                        'attribute_name' => $gap['name'] ?? '',
                        'suggested_value' => $inference['value'],
                        'source' => 'ai',
                        'source_detail' => $inference['method'] ?? 'inference',
                        'confidence' => $confidence,
                        'priority' => $gap['priority'] ?? 'recommended',
                        'meta' => [
                            'ai_method' => $inference['method'] ?? 'inference',
                        ],
                    ];
                }
            } catch (\Exception $e) {
                // Ignorar erro de IA individual
            }
        }

        return $suggestions;
    }

    /**
     * 🔄 Consolida sugestões de múltiplas fontes
     * Remove duplicatas e ranqueia por confiança
     */
    private function consolidateSuggestions(array $suggestions, int $minConfidence, int $maxPerGap): array
    {
        // Agrupar por attribute_id
        $grouped = [];
        foreach ($suggestions as $sugg) {
            $attrId = $sugg['attribute_id'] ?? '';
            if (empty($attrId)) {
                continue;
            }
            
            if (!isset($grouped[$attrId])) {
                $grouped[$attrId] = [];
            }
            $grouped[$attrId][] = $sugg;
        }

        // Consolidar cada grupo
        $consolidated = [];
        foreach ($grouped as $attrId => $attrSuggestions) {
            // Ordenar por confiança DESC
            usort($attrSuggestions, fn($a, $b) => ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0));
            
            // Deduplicate por valor
            $seen = [];
            $filtered = [];
            foreach ($attrSuggestions as $sugg) {
                $valueKey = mb_strtolower(trim($sugg['suggested_value'] ?? ''));
                if (isset($seen[$valueKey])) {
                    continue;
                }
                
                if (($sugg['confidence'] ?? 0) < $minConfidence) {
                    continue;
                }
                
                $seen[$valueKey] = true;
                $filtered[] = $sugg;
                
                if (count($filtered) >= $maxPerGap) {
                    break;
                }
            }

            $consolidated = array_merge($consolidated, $filtered);
        }

        // Ordenar final por prioridade e confiança
        usort($consolidated, function($a, $b) {
            $priorityOrder = ['required' => 0, 'filter' => 1, 'hidden' => 2, 'recommended' => 3];
            $aPriority = $priorityOrder[$a['priority'] ?? 'recommended'] ?? 3;
            $bPriority = $priorityOrder[$b['priority'] ?? 'recommended'] ?? 3;
            
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority;
            }
            
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });

        return $consolidated;
    }

    /**
     * Salva sugestões no banco
     */
    private function saveSuggestions(string $itemId, string $categoryId, array $suggestions): int
    {
        $saved = 0;
        
        foreach ($suggestions as $sugg) {
            $stmt = $this->db->prepare("
                INSERT INTO tech_sheet_suggestions 
                (account_id, item_id, category_id, attribute_id, attribute_name, 
                 suggested_value, source, confidence, status, meta)
                VALUES 
                (:account_id, :item_id, :category_id, :attribute_id, :attribute_name,
                 :suggested_value, :source, :confidence, 'pending', :meta)
                ON DUPLICATE KEY UPDATE
                    suggested_value = IF(VALUES(confidence) > confidence, VALUES(suggested_value), suggested_value),
                    source = IF(VALUES(confidence) > confidence, VALUES(source), source),
                    confidence = IF(VALUES(confidence) > confidence, VALUES(confidence), confidence),
                    meta = IF(VALUES(confidence) > confidence, VALUES(meta), meta),
                    updated_at = NOW()
            ");
            
            try {
                $stmt->execute([
                    ':account_id' => $this->accountId,
                    ':item_id' => $itemId,
                    ':category_id' => $categoryId,
                    ':attribute_id' => $sugg['attribute_id'],
                    ':attribute_name' => $sugg['attribute_name'] ?? '',
                    ':suggested_value' => $sugg['suggested_value'],
                    ':source' => $sugg['source'],
                    ':confidence' => $sugg['confidence'],
                    ':meta' => json_encode($sugg['meta'] ?? []),
                ]);
                $saved++;
            } catch (\Exception $e) {
                log_error('SmartGapFiller save error', ['service' => 'TechSheetSmartGapFillerService', 'error' => $e->getMessage()]);
            }
        }

        return $saved;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function findMatchingGap(string $attrId, string $value, array $gaps): ?array
    {
        // Match direto por ID
        foreach ($gaps as $gap) {
            if (strcasecmp($gap['id'] ?? '', $attrId) === 0) {
                return $gap;
            }
        }

        // Match por alias
        $aliases = [
            'COLOR' => ['MAIN_COLOR', 'PRIMARY_COLOR', 'SECONDARY_COLOR'],
            'MAIN_COLOR' => ['COLOR', 'PRIMARY_COLOR'],
            'SIZE' => ['CLOTHING_SIZE', 'SHOE_SIZE', 'HELMET_SIZE'],
            'STORAGE_CAPACITY' => ['INTERNAL_MEMORY', 'CAPACITY', 'HD_CAPACITY', 'SSD_CAPACITY'],
            'RAM' => ['RAM_MEMORY', 'MEMORY'],
            'SCREEN_SIZE' => ['DISPLAY_SIZE', 'MONITOR_SIZE'],
        ];

        $attrAliases = $aliases[$attrId] ?? [];
        foreach ($gaps as $gap) {
            $gapId = $gap['id'] ?? '';
            if (in_array($gapId, $attrAliases) || in_array($attrId, $aliases[$gapId] ?? [])) {
                return $gap;
            }
        }

        return null;
    }

    private function isValueValidForGap(string $value, array $gap): bool
    {
        $allowedValues = $gap['allowed_values'] ?? [];
        if (empty($allowedValues)) {
            return true; // Se não tem restrição, aceita qualquer valor
        }

        $valueLower = mb_strtolower(trim($value));
        foreach ($allowedValues as $allowed) {
            $allowedName = mb_strtolower(trim($allowed['name'] ?? ($allowed['value_name'] ?? '')));
            if ($valueLower === $allowedName) {
                return true;
            }
        }

        return false;
    }

    private function getGapPriority(string $attrId, array $gaps): string
    {
        foreach ($gaps as $gap) {
            if (($gap['id'] ?? '') === $attrId) {
                return $gap['priority'] ?? 'recommended';
            }
        }
        return 'recommended';
    }

    private function calculateTitleConfidence(array $extracted): int
    {
        $base = $this->confidenceBySource['title_pattern'];
        
        // Boost para métodos específicos
        $method = $extracted['method'] ?? '';
        if (str_contains($method, 'exact') || str_contains($method, 'brand_dict')) {
            $base = $this->confidenceBySource['title_exact'];
        }

        // Penalidade para valores muito curtos
        $value = $extracted['value'] ?? '';
        if (mb_strlen($value) < 2) {
            $base -= 10;
        }

        return max(50, min(98, $base));
    }

    private function extractBaseTerms(string $title): array
    {
        // Remove palavras comuns e extrai termos base
        $stopWords = ['de', 'para', 'com', 'sem', 'novo', 'nova', 'original', 'kit', 'par', 'jogo', 'un', 'und'];
        
        $words = preg_split('/[\s\-\/\+]+/', mb_strtolower($title));
        $words = array_filter($words, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopWords));
        
        // Retorna os 3 primeiros termos significativos
        return array_slice(array_values($words), 0, 3);
    }
}
