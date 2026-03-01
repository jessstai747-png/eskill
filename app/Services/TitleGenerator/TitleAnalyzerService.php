<?php

declare(strict_types=1);

namespace App\Services\TitleGenerator;

use App\Services\MercadoLivreClient;
use App\Services\KeywordResearchService;

/**
 * Title Analyzer Service - Análise Detalhada de Títulos
 *
 * Analisa títulos de anúncios com múltiplas métricas:
 * - SEO score e otimização
 * - Análise competitiva
 * - Performance estimada
 * - Sugestões de melhoria
 */
class TitleAnalyzerService
{
    private ?int $accountId;
    private MercadoLivreClient $client;
    private KeywordResearchService $keywordResearch;

    // Pesos para cálculo de score
    private const WEIGHTS = [
        'length' => 0.15,
        'keywords' => 0.25,
        'clarity' => 0.20,
        'structure' => 0.15,
        'forbidden_words' => 0.10,
        'competitive' => 0.15,
    ];

    // Termos proibidos
    private const FORBIDDEN_TERMS = [
        'original',
        'genuíno',
        'autêntico',
        'oficial',
        'melhor',
        'top',
        'número 1',
        '#1',
        'n°1',
        'mais barato',
        'menor preço',
        'promoção',
        'oferta',
        'frete grátis',
        'entrega grátis',
        'desconto',
        'perfeito',
        'impecável',
        'estado de novo',
        'garantia vitalícia',
        'nunca usado'
    ];

    // Palavras de alto impacto (positivas)
    private const HIGH_IMPACT_WORDS = [
        'Pro',
        'Max',
        'Plus',
        'Ultra',
        'Premium',
        'Professional',
        'Advanced',
        'Special',
        'Edition',
        'Turbo',
        'Super',
        'Mega',
        'Extra'
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = new MercadoLivreClient($accountId);
        $this->keywordResearch = new KeywordResearchService($accountId);
    }

    /**
     * Análise completa de título
     */
    public function analyzeTitle(string $title, string $categoryId = ''): array
    {
        $analysis = [
            'title' => $title,
            'length' => strlen($title),
            'word_count' => str_word_count($title),
            'category_id' => $categoryId,
        ];

        // Análises individuais
        $analysis['length_analysis'] = $this->analyzeLengthOptimization($title);
        $analysis['keyword_analysis'] = $this->analyzeKeywords($title, $categoryId);
        $analysis['clarity_analysis'] = $this->analyzeClarity($title);
        $analysis['structure_analysis'] = $this->analyzeStructure($title);
        $analysis['forbidden_words_analysis'] = $this->analyzeForbiddenWords($title);
        $analysis['competitive_analysis'] = $this->analyzeCompetitiveness($title);

        // Análise SEO
        $analysis['seo_analysis'] = $this->analyzeSEO($title, $categoryId);

        // Estimativa de performance
        $analysis['performance_estimate'] = $this->estimatePerformance($title, $categoryId);

        // Score geral (0-100)
        $analysis['overall_score'] = $this->calculateOverallScore($analysis);

        // Issues e sugestões
        $analysis['issues'] = $this->collectIssues($analysis);
        $analysis['suggestions'] = $this->generateSuggestions($analysis);

        // Status
        $analysis['status'] = $this->determineStatus($analysis['overall_score']);

        return $analysis;
    }

    /**
     * Análise de comprimento
     */
    private function analyzeLengthOptimization(string $title): array
    {
        $length = strlen($title);
        $wordCount = str_word_count($title);

        $score = 0;
        $status = '';
        $message = '';

        if ($length > 60) {
            $score = 0;
            $status = 'critical';
            $message = "Título muito longo ({$length} caracteres). Máximo permitido: 60.";
        } elseif ($length >= 45 && $length <= 58) {
            $score = 100;
            $status = 'excellent';
            $message = "Comprimento ótimo para SEO ({$length} caracteres).";
        } elseif ($length >= 40 && $length < 45) {
            $score = 85;
            $status = 'good';
            $message = "Bom comprimento ({$length} caracteres). Considere adicionar mais 5-10 caracteres.";
        } elseif ($length >= 30 && $length < 40) {
            $score = 70;
            $status = 'fair';
            $message = "Título curto demais ({$length} caracteres). Adicione mais detalhes.";
        } else {
            $score = 50;
            $status = 'poor';
            $message = "Título muito curto ({$length} caracteres). Ideal: 45-58 caracteres.";
        }

        return [
            'score' => $score,
            'status' => $status,
            'length' => $length,
            'word_count' => $wordCount,
            'optimal_range' => '45-58',
            'message' => $message,
            'chars_to_optimal' => max(0, 45 - $length),
            'chars_available' => max(0, 60 - $length),
        ];
    }

    /**
     * Análise de keywords
     */
    private function analyzeKeywords(string $title, string $categoryId): array
    {
        $score = 50; // Base
        $foundKeywords = [];
        $keywordPositions = [];

        // Buscar keywords relevantes se temos categoria
        if (!empty($categoryId)) {
            try {
                $keywordData = $this->keywordResearch->researchKeywords($categoryId, $title);

                $relevantKeywords = $keywordData['keywords'] ?? [];

                foreach ($relevantKeywords as $kw) {
                    $keyword = is_array($kw) ? ($kw['keyword'] ?? $kw['term'] ?? '') : $kw;
                    if (empty($keyword)) continue;

                    $pos = stripos($title, $keyword);
                    if ($pos !== false) {
                        $foundKeywords[] = $keyword;
                        $keywordPositions[$keyword] = $pos;

                        // Bonus se keyword está no início
                        if ($pos <= 10) {
                            $score += 15;
                        } else {
                            $score += 10;
                        }
                    }
                }
            } catch (\Exception $e) {
                log_warning('Falha ao analisar keywords do titulo', [
                    'category_id' => $categoryId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Análise de palavras de alto impacto
        $highImpactFound = [];
        foreach (self::HIGH_IMPACT_WORDS as $word) {
            if (stripos($title, $word) !== false) {
                $highImpactFound[] = $word;
                $score += 5;
            }
        }

        // Análise de especificações técnicas (números + unidades)
        $technicalSpecs = [];
        if (preg_match_all('/(\d+)\s*(GB|TB|MP|mm|cm|kg|g|W|V|mAh|Hz|")/i', $title, $matches)) {
            $technicalSpecs = $matches[0];
            $score += count($technicalSpecs) * 5;
        }

        // Limitar score
        $score = min(100, $score);

        return [
            'score' => $score,
            'found_keywords' => $foundKeywords,
            'keyword_positions' => $keywordPositions,
            'keywords_in_first_15_chars' => count(array_filter($keywordPositions, fn($pos) => $pos <= 15)),
            'high_impact_words' => $highImpactFound,
            'technical_specs' => $technicalSpecs,
            'has_brand' => $this->hasBrand($title),
            'has_model' => $this->hasModel($title),
        ];
    }

    /**
     * Análise de clareza
     */
    private function analyzeClarity(string $title): array
    {
        $score = 50;
        $issues = [];

        $words = explode(' ', $title);
        $wordCount = count($words);

        // Contagem de palavras ideal: 4-8
        if ($wordCount >= 4 && $wordCount <= 8) {
            $score += 30;
        } elseif ($wordCount >= 3 && $wordCount <= 10) {
            $score += 20;
        } else {
            if ($wordCount < 3) {
                $issues[] = 'Título muito vago - adicione mais detalhes';
            } else {
                $issues[] = 'Título muito carregado - simplifique';
            }
        }

        // Verificar capitalização adequada
        $capitalizedWords = 0;
        foreach ($words as $word) {
            if (preg_match('/^[A-Z]/', $word)) {
                $capitalizedWords++;
            }
        }

        if ($capitalizedWords >= 1) {
            $score += 10;
        }

        // Verificar abreviações excessivas
        $abbreviations = preg_match_all('/\b[A-Z]{2,}\b/', $title);
        if ($abbreviations > 3) {
            $score -= 15;
            $issues[] = 'Muitas abreviações - pode dificultar compreensão';
        } elseif ($abbreviations <= 2) {
            $score += 10;
        }

        // Verificar números (especificações)
        $hasNumbers = preg_match('/\d/', $title);
        if ($hasNumbers) {
            $score += 10;
        } else {
            $issues[] = 'Considere adicionar especificações técnicas (ex: 256GB)';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'word_count' => $wordCount,
            'optimal_word_count' => $wordCount >= 4 && $wordCount <= 8,
            'capitalized_words' => $capitalizedWords,
            'abbreviation_count' => $abbreviations,
            'has_numbers' => $hasNumbers,
            'issues' => $issues,
            'readability' => $this->calculateReadability($title),
        ];
    }

    /**
     * Análise de estrutura
     */
    private function analyzeStructure(string $title): array
    {
        $score = 50;
        $structureIssues = [];

        // Primeira letra maiúscula
        if (preg_match('/^[A-Z]/', $title)) {
            $score += 15;
        } else {
            $structureIssues[] = 'Primeira letra deveria ser maiúscula';
        }

        // Pontuação excessiva
        $punctuationCount = preg_match_all('/[^\w\s\-]/', $title);
        if ($punctuationCount === 0) {
            $score += 15;
        } elseif ($punctuationCount <= 2) {
            $score += 10;
        } else {
            $structureIssues[] = 'Pontuação excessiva';
            $score -= 10;
        }

        // Espaçamento consistente
        if (!preg_match('/\s{2,}/', $title)) {
            $score += 10;
        } else {
            $structureIssues[] = 'Espaços duplos encontrados';
        }

        // Estrutura lógica: Marca -> Modelo -> Specs
        $hasLogicalStructure = $this->hasLogicalStructure($title);
        if ($hasLogicalStructure) {
            $score += 15;
        }

        // Uso de hífens/traços apropriado
        $dashCount = substr_count($title, '-');
        if ($dashCount <= 1) {
            $score += 5;
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'starts_capitalized' => preg_match('/^[A-Z]/', $title) === 1,
            'punctuation_count' => $punctuationCount,
            'has_double_spaces' => preg_match('/\s{2,}/', $title) === 1,
            'logical_structure' => $hasLogicalStructure,
            'dash_count' => $dashCount,
            'issues' => $structureIssues,
        ];
    }

    /**
     * Análise de palavras proibidas
     */
    private function analyzeForbiddenWords(string $title): array
    {
        $titleLower = mb_strtolower($title);
        $foundForbidden = [];

        foreach (self::FORBIDDEN_TERMS as $forbidden) {
            if (str_contains($titleLower, mb_strtolower($forbidden))) {
                $foundForbidden[] = $forbidden;
            }
        }

        $score = empty($foundForbidden) ? 100 : 0;
        $status = empty($foundForbidden) ? 'safe' : 'critical';

        return [
            'score' => $score,
            'status' => $status,
            'found_forbidden' => $foundForbidden,
            'count' => count($foundForbidden),
            'message' => empty($foundForbidden)
                ? 'Nenhum termo proibido detectado'
                : 'ATENÇÃO: Título contém termos proibidos pelo ML',
        ];
    }

    /**
     * Análise competitiva
     */
    private function analyzeCompetitiveness(string $title): array
    {
        $score = 50;
        $differentiators = [];

        // Diferenciadores premium
        foreach (self::HIGH_IMPACT_WORDS as $word) {
            if (stripos($title, $word) !== false) {
                $differentiators[] = $word;
                $score += 10;
            }
        }

        // Especificações técnicas detalhadas
        $techSpecCount = preg_match_all('/\d+/', $title);
        if ($techSpecCount >= 2) {
            $score += 15;
        } elseif ($techSpecCount >= 1) {
            $score += 10;
        }

        // Tem marca reconhecível
        if ($this->hasBrand($title)) {
            $score += 15;
        }

        // Tem modelo específico
        if ($this->hasModel($title)) {
            $score += 10;
        }

        $score = min(100, $score);

        return [
            'score' => $score,
            'differentiators' => $differentiators,
            'tech_spec_count' => $techSpecCount,
            'has_brand' => $this->hasBrand($title),
            'has_model' => $this->hasModel($title),
            'uniqueness_level' => $score >= 80 ? 'high' : ($score >= 60 ? 'medium' : 'low'),
        ];
    }

    /**
     * Análise SEO
     */
    private function analyzeSEO(string $title, string $categoryId): array
    {
        $seoScore = 50;
        $seoFactors = [];

        // Keywords no início (primeiros 20 caracteres)
        $firstPart = substr($title, 0, 20);
        if (preg_match('/[A-Z][a-z]+/', $firstPart)) {
            $seoScore += 15;
            $seoFactors[] = 'Marca no início (+15)';
        }

        // Comprimento otimizado
        $length = strlen($title);
        if ($length >= 45 && $length <= 58) {
            $seoScore += 20;
            $seoFactors[] = 'Comprimento ótimo (+20)';
        }

        // Palavras-chave relevantes
        if (preg_match('/\d+\s*(GB|TB|MP|mm|cm)/', $title)) {
            $seoScore += 15;
            $seoFactors[] = 'Especificações técnicas (+15)';
        }

        // sem termos proibidos
        $hasForbidden = false;
        foreach (self::FORBIDDEN_TERMS as $term) {
            if (stripos($title, $term) !== false) {
                $hasForbidden = true;
                break;
            }
        }
        if (!$hasForbidden) {
            $seoFactors[] = 'Sem termos proibidos (✓)';
        } else {
            $seoScore -= 30;
            $seoFactors[] = 'Contém termos proibidos (-30)';
        }

        $seoScore = max(0, min(100, $seoScore));

        return [
            'score' => $seoScore,
            'factors' => $seoFactors,
            'keyword_position' => stripos($title, ' ') ?: strlen($title),
            'has_technical_specs' => preg_match('/\d+/', $title) === 1,
            'optimization_level' => $seoScore >= 80 ? 'excellent' : ($seoScore >= 60 ? 'good' : 'needs_improvement'),
        ];
    }

    /**
     * Estimativa de performance
     */
    private function estimatePerformance(string $title, string $categoryId): array
    {
        // Análises anteriores
        $lengthAnalysis = $this->analyzeLengthOptimization($title);
        $keywordAnalysis = $this->analyzeKeywords($title, $categoryId);
        $clarityAnalysis = $this->analyzeClarity($title);

        // Média ponderada
        $performanceScore =
            ($lengthAnalysis['score'] * 0.20) +
            ($keywordAnalysis['score'] * 0.35) +
            ($clarityAnalysis['score'] * 0.25) +
            ($this->analyzeStructure($title)['score'] * 0.20);

        $performanceScore = (int)round($performanceScore);

        // Estimativas
        $clickThroughRate = 'medium';
        $conversionProbability = 'medium';
        $rankingPotential = 'medium';

        if ($performanceScore >= 85) {
            $clickThroughRate = 'high';
            $conversionProbability = 'high';
            $rankingPotential = 'excellent';
        } elseif ($performanceScore >= 70) {
            $clickThroughRate = 'good';
            $conversionProbability = 'good';
            $rankingPotential = 'good';
        } elseif ($performanceScore < 60) {
            $clickThroughRate = 'low';
            $conversionProbability = 'low';
            $rankingPotential = 'poor';
        }

        return [
            'performance_score' => $performanceScore,
            'click_through_rate_estimate' => $clickThroughRate,
            'conversion_probability' => $conversionProbability,
            'ranking_potential' => $rankingPotential,
            'estimated_views' => $this->estimateViews($performanceScore),
            'estimated_clicks' => $this->estimateClicks($performanceScore),
        ];
    }

    /**
     * Calcula score geral
     */
    private function calculateOverallScore(array $analysis): int
    {
        $score = 0;

        $score += ($analysis['length_analysis']['score'] ?? 0) * self::WEIGHTS['length'];
        $score += ($analysis['keyword_analysis']['score'] ?? 0) * self::WEIGHTS['keywords'];
        $score += ($analysis['clarity_analysis']['score'] ?? 0) * self::WEIGHTS['clarity'];
        $score += ($analysis['structure_analysis']['score'] ?? 0) * self::WEIGHTS['structure'];
        $score += ($analysis['forbidden_words_analysis']['score'] ?? 0) * self::WEIGHTS['forbidden_words'];
        $score += ($analysis['competitive_analysis']['score'] ?? 0) * self::WEIGHTS['competitive'];

        return (int)round($score);
    }

    /**
     * Coleta issues
     */
    private function collectIssues(array $analysis): array
    {
        $issues = [];

        // Length issues
        if ($analysis['length_analysis']['status'] === 'critical') {
            $issues[] = $analysis['length_analysis']['message'];
        }

        // Forbidden words
        if (!empty($analysis['forbidden_words_analysis']['found_forbidden'])) {
            $forbidden = implode(', ', $analysis['forbidden_words_analysis']['found_forbidden']);
            $issues[] = "Termos proibidos: $forbidden";
        }

        // Clarity issues
        $issues = array_merge($issues, $analysis['clarity_analysis']['issues'] ?? []);

        // Structure issues
        $issues = array_merge($issues, $analysis['structure_analysis']['issues'] ?? []);

        return array_unique($issues);
    }

    /**
     * Gera sugestões
     */
    private function generateSuggestions(array $analysis): array
    {
        $suggestions = [];

        // Length suggestions
        if ($analysis['length_analysis']['chars_to_optimal'] > 0) {
            $chars = $analysis['length_analysis']['chars_to_optimal'];
            $suggestions[] = "Adicione mais $chars caracteres para comprimento ótimo";
        }

        // Keyword suggestions
        if ($analysis['keyword_analysis']['score'] < 70) {
            $suggestions[] = "Adicione keywords relevantes no início do título";
        }
        if (empty($analysis['keyword_analysis']['technical_specs'])) {
            $suggestions[] = "Inclua especificações técnicas (ex: 256GB, 48MP)";
        }

        // Clarity suggestions
        if (!$analysis['clarity_analysis']['has_numbers']) {
            $suggestions[] = "Adicione números para especificar melhor o produto";
        }

        // Structure suggestions
        if (!$analysis['structure_analysis']['starts_capitalized']) {
            $suggestions[] = "Inicie o título com letra maiúscula";
        }

        // Competitive suggestions
        if ($analysis['competitive_analysis']['score'] < 70) {
            $suggestions[] = "Adicione diferenciadores (Pro, Max, Premium, etc.)";
        }

        return array_unique($suggestions);
    }

    /**
     * Determina status geral
     */
    private function determineStatus(int $score): string
    {
        if ($score >= 85) return 'excellent';
        if ($score >= 70) return 'good';
        if ($score >= 60) return 'fair';
        if ($score >= 50) return 'poor';
        return 'critical';
    }

    /**
     * Verifica se tem marca reconhecível
     */
    private function hasBrand(string $title): bool
    {
        $brands = [
            'Samsung',
            'Apple',
            'LG',
            'Sony',
            'Xiaomi',
            'Motorola',
            'Nike',
            'Adidas',
            'Puma',
            'Dell',
            'HP',
            'Lenovo',
            'Asus',
            'Acer',
            'Microsoft',
            'Logitech',
            'Canon',
            'Nikon',
            'JBL',
            'Philips',
            'Panasonic',
            'Brastemp'
        ];

        foreach ($brands as $brand) {
            if (stripos($title, $brand) !== false) {
                return true;
            }
        }

        // Verificar padrão de marca (palavra capitalizada no início)
        return preg_match('/^[A-Z][a-z]+/', $title) === 1;
    }

    /**
     * Verifica se tem modelo
     */
    private function hasModel(string $title): bool
    {
        // Padrão: Alfanumérico (ex: A52, 15 Pro, Galaxy S23)
        return preg_match('/\b[A-Z]?\d+[A-Za-z]*\s?(Pro|Max|Plus|Ultra|Mini|Lite)?\b/', $title) === 1;
    }

    /**
     * Verifica estrutura lógica
     */
    private function hasLogicalStructure(string $title): bool
    {
        // Pattern esperado: Marca + Modelo/Produto + Specs
        $words = explode(' ', $title);

        if (count($words) < 3) return false;

        // Primeira palavra capitalizada (marca)
        $hasBrand = preg_match('/^[A-Z]/', $words[0]);

        // Segunda palavra é modelo ou produto
        $hasModel = strlen($words[1]) >= 2;

        // Terceira+ palavras são especificações
        $hasSpecs = count($words) >= 3;

        return $hasBrand && $hasModel && $hasSpecs;
    }

    /**
     * Calcula legibilidade
     */
    private function calculateReadability(string $title): string
    {
        $wordCount = str_word_count($title);
        $avgWordLength = strlen(str_replace(' ', '', $title)) / max(1, $wordCount);

        if ($wordCount >= 4 && $wordCount <= 8 && $avgWordLength <= 8) {
            return 'easy';
        } elseif ($wordCount <= 10 && $avgWordLength <= 10) {
            return 'moderate';
        } else {
            return 'difficult';
        }
    }

    /**
     * Estima visualizações
     */
    private function estimateViews(int $score): string
    {
        if ($score >= 85) return '500-1000+ por semana';
        if ($score >= 70) return '200-500 por semana';
        if ($score >= 60) return '100-200 por semana';
        return '50-100 por semana';
    }

    /**
     * Estima cliques
     */
    private function estimateClicks(int $score): string
    {
        if ($score >= 85) return '50-100+ por semana';
        if ($score >= 70) return '20-50 por semana';
        if ($score >= 60) return '10-20 por semana';
        return '5-10 por semana';
    }
}
