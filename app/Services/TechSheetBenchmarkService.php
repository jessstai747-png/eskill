<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\AI\SEO\AttributeKiller;
use App\Services\CacheService;
use PDO;

/**
 * 📊 Tech Sheet Benchmark Service
 * 
 * Analisa atributos de concorrentes para sugerir valores baseados em benchmark.
 * 
 * Funcionalidades:
 * - Busca concorrentes por título/categoria
 * - Extrai atributos mais frequentes
 * - Gera sugestões com confiança baseada em frequência
 * - Cache agressivo para evitar rate limit
 * 
 * @version 1.0.0
 */
class TechSheetBenchmarkService
{
    private PDO $db;
    private int $accountId;
    private MercadoLivreClient $mlClient;
    private CacheService $cache;
    
    /**
     * TTL do cache de busca de concorrentes (em segundos)
     */
    private int $searchCacheTtl = 3600; // 1 hora
    
    /**
     * TTL do cache de detalhes de item (em segundos)
     */
    private int $itemCacheTtl = 7200; // 2 horas
    
    /**
     * Máximo de concorrentes para analisar
     */
    private int $maxCompetitors = 10;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();
    }
    
    /**
     * Busca sugestões de atributos baseado em concorrentes
     * 
     * @param string $itemId ID do item a analisar
     * @param string $categoryId Categoria do item
     * @param array $missingAttributes Lista de atributos faltantes (gaps)
     * @return array Sugestões com source=competitor
     */
    public function getSuggestionsFromCompetitors(
        string $itemId,
        string $categoryId,
        array $missingAttributes
    ): array {
        $result = [
            'success' => true,
            'item_id' => $itemId,
            'category_id' => $categoryId,
            'suggestions' => [],
            'competitors_analyzed' => 0,
            'cache_hit' => false,
        ];
        
        if (empty($missingAttributes)) {
            return $result;
        }
        
        // Buscar item local para obter título
        $itemRow = $this->getLocalItemRow($itemId);
        if (!$itemRow) {
            $result['success'] = false;
            $result['error'] = 'Item não encontrado no cache local';
            return $result;
        }
        
        $title = $itemRow['title'] ?? '';
        if (empty($title)) {
            $result['success'] = false;
            $result['error'] = 'Título vazio';
            return $result;
        }
        
        // Buscar concorrentes (com cache)
        $competitors = $this->findCompetitors($title, $categoryId);
        $result['competitors_analyzed'] = count($competitors);
        
        if (empty($competitors)) {
            return $result;
        }
        
        // Extrair IDs dos atributos faltantes
        $missingIds = [];
        foreach ($missingAttributes as $attr) {
            $id = $attr['id'] ?? ($attr['attribute_id'] ?? null);
            if ($id) {
                $missingIds[$id] = $attr['name'] ?? $id;
            }
        }
        
        // Analisar atributos dos concorrentes
        $attributeFrequency = $this->analyzeCompetitorAttributes($competitors, array_keys($missingIds));
        
        // Gerar sugestões
        foreach ($attributeFrequency as $attrId => $data) {
            if ($data['count'] < 2) {
                // Precisa de pelo menos 2 concorrentes concordando
                continue;
            }
            
            // Calcular confiança baseada na frequência
            $frequency = $data['count'] / count($competitors);
            $confidence = $this->calculateConfidence($frequency, $data['unanimous']);
            
            // Selecionar valor mais frequente
            $topValue = $this->getTopValue($data['values']);
            if (!$topValue) {
                continue;
            }
            
            $result['suggestions'][] = [
                'attribute_id' => $attrId,
                'attribute_name' => $missingIds[$attrId] ?? $attrId,
                'suggested_value' => $topValue['value'],
                'source' => 'competitor',
                'confidence' => $confidence,
                'rationale' => $this->buildRationale($data, $topValue, count($competitors)),
                'alternatives' => $this->getAlternatives($data['values'], $topValue['value']),
                'meta' => [
                    'competitors_with_value' => $data['count'],
                    'total_competitors' => count($competitors),
                    'frequency_percent' => round($frequency * 100),
                    'unanimous' => $data['unanimous'],
                ],
            ];
        }
        
        // Ordenar por confiança
        usort($result['suggestions'], function ($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $result;
    }
    
    /**
     * Busca concorrentes por título e categoria
     */
    private function findCompetitors(string $title, string $categoryId): array
    {
        // Extrair keywords principais do título (primeiras 5 palavras significativas)
        $searchQuery = $this->extractSearchQuery($title);
        
        $cacheKey = "tech_sheet_competitors_{$categoryId}_" . md5($searchQuery);
        
        // Tentar cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $searchResult = $this->mlClient->get('/sites/MLB/search', [
                'q' => $searchQuery,
                'category' => $categoryId,
                'limit' => $this->maxCompetitors,
                'sort' => 'sold_quantity_desc',
            ]);
            
            $items = $searchResult['results'] ?? [];
            
            // Buscar detalhes de cada item para ter atributos completos
            $competitors = [];
            foreach ($items as $item) {
                $itemId = $item['id'] ?? null;
                if (!$itemId) {
                    continue;
                }
                
                // Buscar detalhes completos (com cache)
                $details = $this->getItemDetails($itemId);
                if ($details) {
                    $competitors[] = $details;
                }
                
                if (count($competitors) >= $this->maxCompetitors) {
                    break;
                }
            }
            
            // Cachear resultado
            $this->cache->set($cacheKey, $competitors, $this->searchCacheTtl);
            
            return $competitors;
            
        } catch (\Exception $e) {
            log_warning('Erro ao buscar concorrentes', ['service' => 'TechSheetBenchmarkService', 'error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Busca detalhes completos de um item (com cache)
     */
    private function getItemDetails(string $itemId): ?array
    {
        $cacheKey = "tech_sheet_item_details_{$itemId}";
        
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        try {
            $item = $this->mlClient->get("/items/{$itemId}");
            
            if (isset($item['error'])) {
                return null;
            }
            
            // Extrair apenas dados necessários
            $details = [
                'id' => $item['id'],
                'title' => $item['title'] ?? '',
                'category_id' => $item['category_id'] ?? '',
                'attributes' => $item['attributes'] ?? [],
                'sold_quantity' => $item['sold_quantity'] ?? 0,
                'price' => $item['price'] ?? 0,
            ];
            
            $this->cache->set($cacheKey, $details, $this->itemCacheTtl);
            
            return $details;
            
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Analisa atributos dos concorrentes
     */
    private function analyzeCompetitorAttributes(array $competitors, array $targetAttributeIds): array
    {
        $analysis = [];
        
        foreach ($targetAttributeIds as $attrId) {
            $analysis[$attrId] = [
                'count' => 0,
                'values' => [],
                'unanimous' => false,
            ];
        }
        
        foreach ($competitors as $competitor) {
            $attributes = $competitor['attributes'] ?? [];
            
            foreach ($attributes as $attr) {
                $attrId = $attr['id'] ?? null;
                if (!$attrId || !isset($analysis[$attrId])) {
                    continue;
                }
                
                // Pegar value_name ou value_id
                $value = $attr['value_name'] ?? ($attr['value_id'] ?? null);
                if (empty($value) || $value === '-' || $value === 'N/A') {
                    continue;
                }
                
                $normalizedValue = $this->normalizeValue($value);
                
                $analysis[$attrId]['count']++;
                
                if (!isset($analysis[$attrId]['values'][$normalizedValue])) {
                    $analysis[$attrId]['values'][$normalizedValue] = [
                        'value' => $value, // Manter valor original
                        'count' => 0,
                    ];
                }
                $analysis[$attrId]['values'][$normalizedValue]['count']++;
            }
        }
        
        // Verificar unanimidade
        foreach ($analysis as $attrId => &$data) {
            if ($data['count'] > 0 && count($data['values']) === 1) {
                $data['unanimous'] = true;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Calcula confiança baseada na frequência
     */
    private function calculateConfidence(float $frequency, bool $unanimous): int
    {
        // Base: 70 para benchmarks de concorrentes
        $base = 70;
        
        // Bônus por frequência alta
        if ($frequency >= 0.8) {
            $base += 15; // 85+
        } elseif ($frequency >= 0.6) {
            $base += 10; // 80+
        } elseif ($frequency >= 0.4) {
            $base += 5; // 75+
        }
        
        // Bônus por unanimidade
        if ($unanimous) {
            $base += 5;
        }
        
        return min(95, $base);
    }
    
    /**
     * Obtém o valor mais frequente
     */
    private function getTopValue(array $values): ?array
    {
        if (empty($values)) {
            return null;
        }
        
        $top = null;
        $maxCount = 0;
        
        foreach ($values as $normalized => $data) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $top = $data;
            }
        }
        
        return $top;
    }
    
    /**
     * Obtém valores alternativos
     */
    private function getAlternatives(array $values, string $topValue): array
    {
        $alternatives = [];
        
        foreach ($values as $normalized => $data) {
            if ($data['value'] !== $topValue) {
                $alternatives[] = $data['value'];
            }
        }
        
        return array_slice($alternatives, 0, 3);
    }
    
    /**
     * Constrói rationale/justificativa
     */
    private function buildRationale(array $data, array $topValue, int $totalCompetitors): string
    {
        $count = $data['count'];
        $valueCount = $topValue['count'];
        
        if ($data['unanimous']) {
            return "Todos os {$count} concorrentes analisados usam este valor";
        }
        
        return "{$valueCount} de {$count} concorrentes usam este valor (total analisados: {$totalCompetitors})";
    }
    
    /**
     * Extrai query de busca do título
     */
    private function extractSearchQuery(string $title): string
    {
        // Remover caracteres especiais
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $title);
        
        // Tokenizar
        $words = preg_split('/\s+/', mb_strtolower(trim($clean)));
        
        // Remover stopwords
        $stopwords = ['de', 'da', 'do', 'para', 'com', 'sem', 'e', 'ou', 'a', 'o', 'um', 'uma', 'os', 'as'];
        $words = array_filter($words, function ($w) use ($stopwords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopwords);
        });
        
        // Pegar as primeiras 5 palavras significativas
        $words = array_slice(array_values($words), 0, 5);
        
        return implode(' ', $words);
    }
    
    /**
     * Normaliza valor para comparação
     */
    private function normalizeValue(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return $normalized;
    }
    
    /**
     * Busca item no cache local
     */
    private function getLocalItemRow(string $itemId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ml_item_id, title, category_id, status, data, updated_at
             FROM items
             WHERE account_id = :account_id AND ml_item_id = :item_id
             LIMIT 1"
        );
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':item_id' => $itemId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
    
    /**
     * Integra sugestões de benchmark no TechSheetService
     * 
     * @param string $itemId
     * @param string $categoryId  
     * @param array $gaps Lista de gaps do AttributeKiller
     * @return array Sugestões formatadas para upsert
     */
    public function generateBenchmarkSuggestions(
        string $itemId,
        string $categoryId,
        array $gaps
    ): array {
        // Extrair atributos faltantes de todos os níveis de prioridade
        $missingAttributes = [];
        
        foreach (['required', 'filter', 'hidden', 'recommended'] as $level) {
            $levelGaps = $gaps[$level] ?? [];
            foreach ($levelGaps as $gap) {
                $missingAttributes[] = $gap;
            }
        }
        
        if (empty($missingAttributes)) {
            return [
                'success' => true,
                'suggestions' => [],
                'message' => 'Nenhuma lacuna para analisar',
            ];
        }
        
        $benchmarkResult = $this->getSuggestionsFromCompetitors(
            $itemId,
            $categoryId,
            $missingAttributes
        );
        
        if (!$benchmarkResult['success']) {
            return $benchmarkResult;
        }
        
        // Formatar para o padrão do TechSheetService
        $formatted = [];
        foreach ($benchmarkResult['suggestions'] as $sugg) {
            $formatted[] = [
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'category_id' => $categoryId,
                'attribute_id' => $sugg['attribute_id'],
                'attribute_name' => $sugg['attribute_name'],
                'suggested_value' => $sugg['suggested_value'],
                'source' => TechSheetService::SOURCE_BENCHMARK,  // Normalizado: era 'competitor'
                'confidence' => $sugg['confidence'],
                'status' => 'pending',
                'meta' => array_merge(
                    $sugg['meta'] ?? [],
                    [
                        'rationale' => $sugg['rationale'],
                        'alternatives' => $sugg['alternatives'] ?? [],
                        'analysis_type' => 'competitor_analysis',  // Guardar tipo original no meta
                    ]
                ),
            ];
        }
        
        return [
            'success' => true,
            'suggestions' => $formatted,
            'competitors_analyzed' => $benchmarkResult['competitors_analyzed'],
        ];
    }
}
