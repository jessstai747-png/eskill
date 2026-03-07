<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Services\MercadoLivreClient;
use App\Services\AI\SEO\KeywordKiller;
use App\Services\SEO\SEOOptimizerService;

/**
 * 🚀 SEO Title Optimizer - Otimização Inteligente de Títulos
 * 
 * Aplica lógica SEO completa para gerar títulos otimizados:
 * - Keywords de alta conversão do Mercado Livre
 * - Análise de concorrentes
 * - Atributos obrigatórios da categoria
 * - Melhores práticas do ML
 * 
 * @author SEO Development Team
 * @version 1.0.0
 */
class SEOTitleOptimizer
{
    private ?int $accountId;
    private ?MercadoLivreClient $mlClient;
    private KeywordKiller $keywordKiller;
    private SEOOptimizerService $seoOptimizer;
    
    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->mlClient = $accountId ? new MercadoLivreClient($accountId) : null;
        $this->keywordKiller = new KeywordKiller($accountId);
        $this->seoOptimizer = new SEOOptimizerService();
    }
    
    /**
     * 🎯 Otimiza título completo com todas as estratégias
     */
    public function optimizeTitle(array $product): array
    {
        $result = [
            'success' => true,
            'original_title' => $product['title'] ?? '',
            'optimized_titles' => [],
            'metadata' => [],
            'strategy' => []
        ];
        
        try {
            // 1. Extrair keywords do produto
            $keywords = $this->keywordKiller->researchKeywords($product);
            
            // 2. Obter atributos obrigatórios da categoria
            $requiredAttrs = $this->getRequiredAttributes($product['category_id'] ?? '');
            
            // 3. Analisar concorrentes
            $competitorAnalysis = $this->analyzeCompetitors($product);
            
            // 4. Gerar variações de título
            $variations = $this->generateTitleVariations($product, $keywords, $requiredAttrs, $competitorAnalysis);
            
            // 5. Validar e ordenar por score
            $validated = $this->validateAndScoreTitles($variations, $product);
            
            $result['optimized_titles'] = $validated;
            $result['metadata'] = [
                'keywords_found' => $keywords['total_keywords'] ?? 0,
                'required_attributes' => count($requiredAttrs),
                'competitors_analyzed' => count($competitorAnalysis['titles'] ?? []),
                'generation_strategy' => 'ai_ml_keywords_competitors'
            ];
            
            $result['strategy'] = [
                'keywords_included' => $this->extractKeywordsFromTitle($validated[0]['title'] ?? ''),
                'attributes_covered' => $this->checkAttributesCoverage($validated[0]['title'] ?? '', $requiredAttrs),
                'length_optimized' => $this->validateLength($validated[0]['title'] ?? ''),
                'seo_score' => $validated[0]['seo_score'] ?? 0
            ];
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 📊 Gera variações de título com diferentes estratégias
     */
    private function generateTitleVariations(array $product, array $keywords, array $requiredAttrs, array $competitors): array
    {
        $baseTitle = $product['title'] ?? '';
        $brand = $product['brand'] ?? $this->extractAttribute($product, 'BRAND');
        $model = $product['model'] ?? $this->extractAttribute($product, 'MODEL');
        $primaryKeywords = $keywords['keywords']['primary'] ?? [];
        
        $variations = [];
        
        // Estratégia 1: Marca + Produto Principal + Atributo
        if ($brand && !empty($primaryKeywords)) {
            $variations[] = $this->buildTitle([
                'brand' => $brand,
                'keywords' => array_slice($primaryKeywords, 0, 2),
                'attributes' => $this->getTopAttributes($requiredAttrs, 1),
                'model' => $model
            ], 'brand_first');
        }
        
        // Estratégia 2: Keywords + Marca (se marca não for principal)
        if (!empty($primaryKeywords) && $brand) {
            $variations[] = $this->buildTitle([
                'keywords' => array_slice($primaryKeywords, 0, 2),
                'brand' => $brand,
                'attributes' => $this->getTopAttributes($requiredAttrs, 1),
                'model' => $model
            ], 'keywords_first');
        }
        
        // Estratégia 3: AI Optimized (usando SEOOptimizerService)
        $aiOptimized = $this->seoOptimizer->optimizeTitle($baseTitle, [
            'category' => $product['category_id'] ?? '',
            'brand' => $brand,
            'keywords' => array_merge($primaryKeywords, $keywords['keywords']['secondary'] ?? []),
            'attributes' => $requiredAttrs
        ]);
        
        if ($aiOptimized['success']) {
            $variations[] = [
                'title' => $aiOptimized['optimized_title'] ?? $baseTitle,
                'strategy' => 'ai_optimized',
                'confidence' => $aiOptimized['improvement_score'] ?? 70
            ];
        }
        
        // Estratégia 4: Baseada em concorrentes top
        if (!empty($competitors['titles'])) {
            $competitorBased = $this->createCompetitorBasedTitle($product, $competitors['titles'][0]);
            if ($competitorBased) {
                $variations[] = $competitorBased;
            }
        }
        
        // Estratégia 5: Long-tail focada
        $longTailKeywords = $keywords['keywords']['long_tail'] ?? [];
        if (!empty($longTailKeywords)) {
            $variations[] = $this->buildTitle([
                'keywords' => [$longTailKeywords[0]],
                'brand' => $brand,
                'attributes' => $this->getTopAttributes($requiredAttrs, 1),
                'suffix' => 'Importado'
            ], 'long_tail');
        }
        
        return $variations;
    }
    
    /**
     * 🔨 Constrói título com base na estratégia
     */
    private function buildTitle(array $components, string $strategy): array
    {
        $parts = [];
        
        switch ($strategy) {
            case 'brand_first':
                if (!empty($components['brand'])) $parts[] = $components['brand'];
                if (!empty($components['keywords'])) $parts = array_merge($parts, $components['keywords']);
                if (!empty($components['model'])) $parts[] = $components['model'];
                if (!empty($components['attributes'])) $parts = array_merge($parts, $components['attributes']);
                break;
                
            case 'keywords_first':
                if (!empty($components['keywords'])) $parts = array_merge($parts, $components['keywords']);
                if (!empty($components['brand'])) $parts[] = $components['brand'];
                if (!empty($components['model'])) $parts[] = $components['model'];
                if (!empty($components['attributes'])) $parts = array_merge($parts, $components['attributes']);
                break;
                
            case 'long_tail':
                if (!empty($components['keywords'])) $parts = array_merge($parts, $components['keywords']);
                if (!empty($components['brand'])) $parts[] = $components['brand'];
                if (!empty($components['attributes'])) $parts = array_merge($parts, $components['attributes']);
                if (!empty($components['suffix'])) $parts[] = $components['suffix'];
                break;
        }
        
        $title = implode(' ', array_unique($parts));
        $title = $this->cleanTitle($title);
        
        return [
            'title' => $this->truncateTitle($title),
            'strategy' => $strategy,
            'confidence' => $this->calculateConfidence($components, $strategy)
        ];
    }
    
    /**
     * ✅ Valida e pontua títulos gerados
     */
    private function validateAndScoreTitles(array $titles, array $product): array
    {
        $scored = [];
        
        foreach ($titles as $titleData) {
            $title = $titleData['title'] ?? '';
            
            $seoScore = 100;
            $issues = [];
            
            // Validação de comprimento
            $length = mb_strlen($title);
            if ($length < 40) {
                $seoScore -= 20;
                $issues[] = 'Título muito curto';
            } elseif ($length > 60) {
                $seoScore -= 15;
                $issues[] = 'Título muito longo';
            }
            
            // Verificação de marca
            $brand = $product['brand'] ?? $this->extractAttribute($product, 'BRAND');
            if ($brand && mb_stripos($title, $brand) === false) {
                $seoScore -= 15;
                $issues[] = 'Marca ausente';
            }
            
            // Verificação de keywords principais
            $keywordScore = $this->checkKeywordPresence($title, $product);
            $seoScore -= (100 - $keywordScore) * 0.3;
            
            // Verificação de atributos obrigatórios
            $requiredAttrs = $this->getRequiredAttributes($product['category_id'] ?? '');
            $attrsScore = $this->checkAttributesCoverage($title, $requiredAttrs);
            if ($attrsScore < 70) {
                $seoScore -= 10;
                $issues[] = 'Atributos importantes ausentes';
            }
            
            // Checar keyword stuffing
            if ($this->hasKeywordStuffing($title)) {
                $seoScore -= 25;
                $issues[] = 'Keyword stuffing detectado';
            }
            
            $scored[] = [
                'title' => $title,
                'strategy' => $titleData['strategy'] ?? 'unknown',
                'seo_score' => max(0, $seoScore),
                'confidence' => $titleData['confidence'] ?? 50,
                'issues' => $issues,
                'character_count' => $length,
                'has_brand' => $brand && mb_stripos($title, $brand) !== false,
                'keyword_coverage' => $keywordScore,
                'attributes_coverage' => $attrsScore
            ];
        }
        
        // Ordenar por score combinado
        usort($scored, function($a, $b) {
            $scoreA = ($a['seo_score'] * 0.7) + ($a['confidence'] * 0.3);
            $scoreB = ($b['seo_score'] * 0.7) + ($b['confidence'] * 0.3);
            return $scoreB <=> $scoreA;
        });
        
        return $scored;
    }
    
    /**
     * 🔍 Analisa títulos de concorrentes
     */
    private function analyzeCompetitors(array $product): array
    {
        if (!$this->mlClient) {
            return ['titles' => [], 'keywords' => []];
        }
        
        try {
            $searchTerm = mb_substr($product['title'] ?? '', 0, 30);
            $results = $this->mlClient->get('/sites/MLB/search', [
                'q' => $searchTerm,
                'limit' => 5,
                'sort' => 'sold_quantity_desc'
            ]);
            
            $titles = [];
            $allKeywords = [];
            
            foreach ($results['results'] ?? [] as $item) {
                $title = $item['title'] ?? '';
                $titles[] = $title;
                
                // Extrair keywords dos títulos concorrentes
                $words = preg_split('/[\s\-\/]+/', mb_strtolower($title));
                foreach ($words as $word) {
                    $word = trim($word);
                    if (mb_strlen($word) >= 3 && !$this->isStopword($word)) {
                        $allKeywords[$word] = ($allKeywords[$word] ?? 0) + 1;
                    }
                }
            }
            
            arsort($allKeywords);
            
            return [
                'titles' => $titles,
                'keywords' => array_slice($allKeywords, 0, 10, true)
            ];
            
        } catch (\Exception $e) {
            return ['titles' => [], 'keywords' => []];
        }
    }
    
    /**
     * 🏷️ Obtém atributos obrigatórios da categoria
     */
    private function getRequiredAttributes(string $categoryId): array
    {
        if (!$this->mlClient || !$categoryId) {
            return [];
        }
        
        try {
            $response = $this->mlClient->get("/categories/{$categoryId}");
            
            $required = [];
            foreach ($response['attributes'] ?? [] as $attr) {
                if (($attr['required'] ?? false) || ($attr['allow_variation'] ?? false)) {
                    $required[] = [
                        'id' => $attr['id'],
                        'name' => $attr['name'],
                        'value_type' => $attr['value_type'] ?? 'string'
                    ];
                }
            }
            
            return $required;
            
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Helper methods
     */
    
    private function extractAttribute(array $product, string $attrId): ?string
    {
        foreach ($product['attributes'] ?? [] as $attr) {
            if (($attr['id'] ?? '') === $attrId) {
                return $attr['value_name'] ?? null;
            }
        }
        return null;
    }
    
    private function getTopAttributes(array $attributes, int $limit): array
    {
        return array_slice(array_column($attributes, 'name'), 0, $limit);
    }
    
    private function cleanTitle(string $title): string
    {
        return trim(preg_replace('/\s+/', ' ', $title));
    }
    
    private function truncateTitle(string $title, int $maxLength = 60): string
    {
        if (mb_strlen($title) <= $maxLength) {
            return $title;
        }
        
        return mb_substr($title, 0, $maxLength - 3) . '...';
    }
    
    private function calculateConfidence(array $components, string $strategy): int
    {
        $confidence = 50;
        
        if (!empty($components['brand'])) $confidence += 15;
        if (!empty($components['keywords'])) $confidence += 20;
        if (!empty($components['attributes'])) $confidence += 10;
        
        if ($strategy === 'ai_optimized') $confidence += 10;
        
        return min(100, $confidence);
    }
    
    private function checkKeywordPresence(string $title, array $product): int
    {
        $score = 100;
        $keywords = $this->keywordKiller->researchKeywords($product);
        $primaryKeywords = $keywords['keywords']['primary'] ?? [];
        
        foreach ($primaryKeywords as $keyword) {
            if (mb_stripos($title, $keyword) === false) {
                $score -= 20;
            }
        }
        
        return max(0, $score);
    }
    
    private function checkAttributesCoverage(string $title, array $requiredAttrs): int
    {
        if (empty($requiredAttrs)) return 100;
        
        $covered = 0;
        foreach ($requiredAttrs as $attr) {
            if (mb_stripos($title, $attr['name']) !== false) {
                $covered++;
            }
        }
        
        return (int) (($covered / count($requiredAttrs)) * 100);
    }
    
    private function validateLength(string $title): bool
    {
        $length = mb_strlen($title);
        return $length >= 40 && $length <= 60;
    }
    
    private function extractKeywordsFromTitle(string $title): array
    {
        $words = preg_split('/[\s\-\/]+/', mb_strtolower($title));
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) >= 3 && !$this->isStopword($word)) {
                $keywords[] = $word;
            }
        }
        
        return array_unique($keywords);
    }
    
    private function hasKeywordStuffing(string $title): bool
    {
        $words = array_count_values(preg_split('/[\s\-\/]+/', mb_strtolower($title)));
        
        foreach ($words as $word => $count) {
            if (mb_strlen($word) >= 4 && $count >= 3) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isStopword(string $word): bool
    {
        $stopwords = ['de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'por', 'que', 'uma', 'um', 'seu', 'sua'];
        return in_array($word, $stopwords);
    }
    
    private function createCompetitorBasedTitle(array $product, string $competitorTitle): ?array
    {
        // Analisar estrutura do título concorrente e adaptar
        $words = preg_split('/[\s\-\/]+/', $competitorTitle);
        $structure = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) >= 3 && !$this->isStopword($word)) {
                $structure[] = $word;
            }
        }
        
        if (count($structure) >= 3) {
            $brand = $product['brand'] ?? $this->extractAttribute($product, 'BRAND');
            $title = implode(' ', array_slice($structure, 0, 2));
            
            if ($brand) {
                $title .= ' ' . $brand;
            }
            
            return [
                'title' => $this->truncateTitle($title),
                'strategy' => 'competitor_based',
                'confidence' => 75
            ];
        }
        
        return null;
    }
}