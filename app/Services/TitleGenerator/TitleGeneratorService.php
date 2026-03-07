<?php

declare(strict_types=1);

namespace App\Services\TitleGenerator;

use App\Services\MercadoLivreClient;
use App\Services\CategoryService;
use App\Services\KeywordResearchService;
use App\Services\SeoAnalyzerService;

/**
 * Title Generator Service - Geração Inteligente de Títulos
 * 
 * Gera títulos otimizados para SEO baseado em:
 * - Análise de categoria e atributos
 * - Pesquisa de keywords de alta conversão
 * - Análise de concorrentes de sucesso
 * - Tendências de mercado
 * - Regras do Mercado Livre (60 caracteres, termos proibidos)
 */
class TitleGeneratorService
{
    private MercadoLivreClient $client;
    private ?int $accountId;
    private KeywordResearchService $keywordResearch;
    private SeoAnalyzerService $seoAnalyzer;
    private CategoryService $categoryService;

    // Limite de caracteres do ML
    private const MAX_LENGTH = 60;
    private const OPTIMAL_MIN = 45;
    private const OPTIMAL_MAX = 58;

    // Termos proibidos no Mercado Livre
    private const FORBIDDEN_WORDS = [
        'original', 'genuíno', 'autêntico', 'oficial',
        'melhor', 'top', 'número 1', '#1',
        'mais barato', 'menor preço', 'promoção',
        'frete grátis', 'entrega grátis',
        'novo', 'lacrado' // Quando não for realmente novo
    ];

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->client = new MercadoLivreClient($accountId);
        $this->keywordResearch = new KeywordResearchService($accountId);
        $this->seoAnalyzer = new SeoAnalyzerService($accountId);
        $this->categoryService = new CategoryService($accountId);
    }

    /**
     * Gera títulos otimizados baseado em dados do produto
     * 
     * @param array $productData {
     *   category_id: string,
     *   brand?: string,
     *   model?: string,
     *   attributes?: array,
     *   keywords?: array,
     *   base_title?: string
     * }
     * @param array $options {
     *   count?: int (quantos títulos gerar, default 5),
     *   include_variations?: bool (incluir variações, default true),
     *   optimize_for?: string (conversion|ranking|both, default both),
     *   min_score?: int (score mínimo aceitável, default 70)
     * }
     * @return array
     */
    public function generateTitles(array $productData, array $options = []): array
    {
        $count = $options['count'] ?? 5;
        $includeVariations = $options['include_variations'] ?? true;
        $optimizeFor = $options['optimize_for'] ?? 'both';
        $minScore = $options['min_score'] ?? 70;

        // 1. Extrair informações base
        $categoryId = $productData['category_id'];
        $brand = $productData['brand'] ?? '';
        $model = $productData['model'] ?? '';
        $attributes = $productData['attributes'] ?? [];
        $baseTitle = $productData['base_title'] ?? '';

        // 2. Obter dados de categoria
        $categoryInfo = $this->categoryService->getCategoryDetails($categoryId);

        // 3. Pesquisar keywords relevantes
        $keywordData = $this->keywordResearch->researchKeywords($categoryId, [
            'product_name' => $baseTitle ?: "$brand $model",
            'include_trends' => true,
            'include_competition' => true
        ]);

        // 4. Analisar concorrentes de sucesso
        $topCompetitors = $this->analyzeTopCompetitors($categoryId, $brand, $model);

        // 5. Extrair componentes essenciais
        $components = $this->extractTitleComponents($productData, $keywordData, $topCompetitors);

        // 6. Gerar títulos base
        $generatedTitles = $this->generateBaseTitles($components, $count);

        // 7. Gerar variações se solicitado
        if ($includeVariations) {
            $variations = $this->generateVariations($generatedTitles, $components);
            $generatedTitles = array_merge($generatedTitles, $variations);
        }

        // 8. Avaliar e ranquear títulos
        $evaluatedTitles = $this->evaluateTitles($generatedTitles, $categoryId, $optimizeFor);

        // 9. Filtrar por score mínimo
        $evaluatedTitles = array_filter($evaluatedTitles, function ($title) use ($minScore) {
            return $title['score'] >= $minScore;
        });

        // 10. Ordenar por score e retornar top N
        usort($evaluatedTitles, fn($a, $b) => $b['score'] <=> $a['score']);
        $topTitles = array_slice($evaluatedTitles, 0, $count);

        return [
            'success' => true,
            'generated_count' => count($topTitles),
            'titles' => $topTitles,
            'best_title' => $topTitles[0] ?? null,
            'category_info' => [
                'id' => $categoryId,
                'name' => $categoryInfo['name'] ?? '',
            ],
            'insights' => [
                'top_keywords' => array_slice($keywordData['keywords'] ?? [], 0, 5),
                'avg_competitor_length' => $topCompetitors['avg_length'] ?? 0,
                'common_patterns' => $topCompetitors['patterns'] ?? [],
            ]
        ];
    }

    /**
     * Gera título a partir de anúncio existente (melhoria)
     */
    public function generateFromItem(string $itemId, array $options = []): array
    {
        try {
            $item = $this->client->getItem($itemId);

            $productData = [
                'category_id' => $item['category_id'],
                'brand' => $this->extractAttribute($item, 'BRAND'),
                'model' => $this->extractAttribute($item, 'MODEL'),
                'attributes' => $item['attributes'] ?? [],
                'base_title' => $item['title'] ?? '',
            ];

            $result = $this->generateTitles($productData, $options);
            $result['original_title'] = $item['title'] ?? '';
            $result['improvement'] = [
                'original_score' => $this->evaluateTitle($item['title'], $item['category_id'])['score'],
                'best_score' => $result['best_title']['score'] ?? 0,
                'score_gain' => ($result['best_title']['score'] ?? 0) - $this->evaluateTitle($item['title'], $item['category_id'])['score']
            ];

            return $result;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Gera variações de um título existente
     */
    public function generateVariationsFromTitle(string $title, string $categoryId, array $options = []): array
    {
        $count = $options['count'] ?? 5;

        // Parse título em componentes
        $components = $this->parseTitleIntoComponents($title);

        // Gerar variações
        $variations = $this->generateVariations([$title], $components);

        // Avaliar
        $evaluated = $this->evaluateTitles($variations, $categoryId);

        // Ordenar e filtrar
        usort($evaluated, fn($a, $b) => $b['score'] <=> $a['score']);
        $topVariations = array_slice($evaluated, 0, $count);

        return [
            'success' => true,
            'original_title' => $title,
            'original_score' => $this->evaluateTitle($title, $categoryId)['score'],
            'variations' => $topVariations,
            'best_variation' => $topVariations[0] ?? null,
        ];
    }

    /**
     * Analisa concorrentes de sucesso
     */
    private function analyzeTopCompetitors(string $categoryId, string $brand = '', string $model = ''): array
    {
        try {
            $searchQuery = trim("$brand $model");
            if (empty($searchQuery)) {
                $categoryInfo = $this->categoryService->getCategoryDetails($categoryId);
                $searchQuery = $categoryInfo['name'] ?? '';
            }

            $searchResults = $this->client->search([
                'category' => $categoryId,
                'q' => $searchQuery,
                'sort' => 'sold_quantity_desc', // Mais vendidos
                'limit' => 20
            ]);

            $titles = [];
            $lengths = [];
            $patterns = [];

            foreach ($searchResults['results'] ?? [] as $item) {
                $title = $item['title'] ?? '';
                if (empty($title)) continue;

                $titles[] = $title;
                $lengths[] = mb_strlen($title);

                // Extrair padrões (ex: "Marca Modelo Especificação")
                $words = explode(' ', $title);
                if (count($words) >= 3) {
                    $pattern = implode(' ', array_slice($words, 0, 3));
                    if (!isset($patterns[$pattern])) {
                        $patterns[$pattern] = 0;
                    }
                    $patterns[$pattern]++;
                }
            }

            arsort($patterns);

            return [
                'titles' => $titles,
                'avg_length' => !empty($lengths) ? (int)round(array_sum($lengths) / count($lengths)) : 0,
                'patterns' => array_keys(array_slice($patterns, 0, 5)),
                'top_performers' => array_slice($titles, 0, 5)
            ];

        } catch (\Exception $e) {
            log_warning('Falha ao analisar concorrentes top', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'titles' => [],
                'avg_length' => 50,
                'patterns' => [],
                'top_performers' => []
            ];
        }
    }

    /**
     * Extrai componentes para construção de título
     */
    private function extractTitleComponents(array $productData, array $keywordData, array $competitorData): array
    {
        $brand = $productData['brand'] ?? '';
        $model = $productData['model'] ?? '';
        $attributes = $productData['attributes'] ?? [];

        // Extrair especificações relevantes
        $specs = [];
        $specPriority = ['INTERNAL_MEMORY', 'RAM', 'COLOR', 'SIZE', 'CAPACITY', 'MATERIAL'];
        
        foreach ($specPriority as $attrId) {
            foreach ($attributes as $attr) {
                if (($attr['id'] ?? '') === $attrId) {
                    $specs[] = $attr['value_name'] ?? '';
                    break;
                }
            }
        }

        return [
            'brand' => $brand,
            'model' => $model,
            'specs' => array_filter($specs),
            'keywords' => array_slice($keywordData['keywords'] ?? [], 0, 10),
            'modifiers' => $this->getCommonModifiers(),
            'competitor_patterns' => $competitorData['patterns'] ?? []
        ];
    }

    /**
     * Gera títulos base a partir dos componentes
     */
    private function generateBaseTitles(array $components, int $count): array
    {
        $titles = [];
        $brand = $components['brand'] ?? '';
        $model = $components['model'] ?? '';
        $specs = $components['specs'] ?? [];
        $keywords = $components['keywords'] ?? [];

        // Pattern 1: Marca Modelo Especificações
        if ($brand && $model) {
            $baseTitle = "$brand $model";
            
            // Adicionar specs até o limite
            foreach ($specs as $spec) {
                $testTitle = "$baseTitle $spec";
                if (mb_strlen($testTitle) <= self::MAX_LENGTH) {
                    $baseTitle = $testTitle;
                }
            }
            $titles[] = $baseTitle;
        }

        // Pattern 2: Keyword-first (SEO optimizado)
        foreach ($keywords as $keyword) {
            if (is_array($keyword)) {
                $keyword = $keyword['keyword'] ?? $keyword['term'] ?? '';
            }
            
            if (empty($keyword)) continue;

            $keywordTitle = trim($keyword);
            if ($brand && !str_contains(mb_strtolower($keywordTitle), mb_strtolower($brand))) {
                $keywordTitle .= " $brand";
            }
            
            foreach ($specs as $spec) {
                $testTitle = "$keywordTitle $spec";
                if (mb_strlen($testTitle) <= self::MAX_LENGTH) {
                    $keywordTitle = $testTitle;
                } else {
                    break;
                }
            }

            if (mb_strlen($keywordTitle) >= self::OPTIMAL_MIN) {
                $titles[] = $keywordTitle;
            }

            if (count($titles) >= $count * 2) break;
        }

        // Pattern 3: Specs-first (para produtos onde spec é mais importante)
        if (!empty($specs)) {
            $specsTitle = implode(' ', array_slice($specs, 0, 2));
            if ($brand) $specsTitle .= " $brand";
            if ($model) {
                $testTitle = "$specsTitle $model";
                if (mb_strlen($testTitle) <= self::MAX_LENGTH) {
                    $specsTitle = $testTitle;
                }
            }
            if (mb_strlen($specsTitle) >= self::OPTIMAL_MIN) {
                $titles[] = $specsTitle;
            }
        }

        // Pattern 4: Baseado em padrões de concorrentes
        foreach ($components['competitor_patterns'] ?? [] as $pattern) {
            // Substituir partes do pattern com nossos dados
            $customPattern = $pattern;
            if ($brand) $customPattern = preg_replace('/\b[A-Z][a-z]+\b/', $brand, $customPattern, 1);
            if (strlen($customPattern) <= self::MAX_LENGTH && strlen($customPattern) >= self::OPTIMAL_MIN) {
                $titles[] = $customPattern;
            }
        }

        // Remover duplicatas
        $titles = array_unique($titles);

        return array_values($titles);
    }

    /**
     * Gera variações dos títulos
     */
    private function generateVariations(array $titles, array $components): array
    {
        $variations = [];
        $modifiers = $components['modifiers'] ?? $this->getCommonModifiers();

        foreach ($titles as $title) {
            // Variação 1: Com modificadores de qualidade
            foreach ($modifiers as $modifier) {
                $withModifier = "$title $modifier";
                if (strlen($withModifier) <= self::MAX_LENGTH) {
                    $variations[] = $withModifier;
                }
            }

            // Variação 2: Reordenar componentes
            $words = explode(' ', $title);
            if (count($words) >= 3) {
                // Mover última palavra para frente
                $reordered = array_pop($words);
                $reordered .= ' ' . implode(' ', $words);
                if (strlen($reordered) <= self::MAX_LENGTH) {
                    $variations[] = $reordered;
                }
            }

            // Variação 3: Abreviações para economizar espaço
            $abbreviated = $this->abbreviateTitle($title);
            if ($abbreviated !== $title && strlen($abbreviated) <= self::MAX_LENGTH) {
                $variations[] = $abbreviated;
            }
        }

        return array_unique($variations);
    }

    /**
     * Avalia e pontua títulos
     */
    private function evaluateTitles(array $titles, string $categoryId, string $optimizeFor = 'both'): array
    {
        $evaluated = [];

        foreach ($titles as $title) {
            $evaluation = $this->evaluateTitle($title, $categoryId, $optimizeFor);
            $evaluation['title'] = $title;
            $evaluated[] = $evaluation;
        }

        return $evaluated;
    }

    /**
     * Avalia um título específico
     */
    private function evaluateTitle(string $title, string $categoryId, string $optimizeFor = 'both'): array
    {
        $score = 0;
        $details = [];
        $issues = [];
        $suggestions = [];

        $length = strlen($title);

        // 1. Comprimento (peso 20%)
        $lengthScore = 0;
        if ($length >= self::OPTIMAL_MIN && $length <= self::OPTIMAL_MAX) {
            $lengthScore = 100;
            $details['length'] = "✓ Comprimento ótimo: {$length} caracteres";
        } elseif ($length <= self::MAX_LENGTH) {
            $lengthScore = 80;
            $details['length'] = "Comprimento aceitável: {$length} caracteres";
            if ($length < self::OPTIMAL_MIN) {
                $suggestions[] = "Adicione mais detalhes ao título (ideal: 45-58 caracteres)";
            }
        } else {
            $lengthScore = 0;
            $issues[] = "Título muito longo: {$length} caracteres (máximo: 60)";
            $details['length'] = "✗ Título excede limite";
        }
        $score += $lengthScore * 0.20;

        // 2. Palavras proibidas (peso 15%)
        $forbiddenScore = 100;
        $titleLower = mb_strtolower($title);
        foreach (self::FORBIDDEN_WORDS as $forbidden) {
            if (str_contains($titleLower, mb_strtolower($forbidden))) {
                $forbiddenScore = 0;
                $issues[] = "Contém termo proibido: '$forbidden'";
                break;
            }
        }
        $score += $forbiddenScore * 0.15;
        $details['forbidden_words'] = $forbiddenScore === 100 ? "✓ Sem termos proibidos" : "✗ Contém termos proibidos";

        // 3. Keywords no início (peso 25%)
        $keywordScore = $this->calculateKeywordScore($title, $categoryId);
        $score += $keywordScore * 0.25;
        $details['keywords'] = "Keywords relevantes: {$keywordScore}%";

        // 4. Clareza e especificidade (peso 20%)
        $clarityScore = $this->calculateClarityScore($title);
        $score += $clarityScore * 0.20;
        $details['clarity'] = "Clareza: {$clarityScore}%";

        // 5. Estrutura (peso 10%)
        $structureScore = $this->calculateStructureScore($title);
        $score += $structureScore * 0.10;
        $details['structure'] = "Estrutura: {$structureScore}%";

        // 6. Competitividade (peso 10%)
        $competitiveScore = $this->calculateCompetitiveScore($title);
        $score += $competitiveScore * 0.10;
        $details['competitive'] = "Competitividade: {$competitiveScore}%";

        // Ajustar por objetivo
        if ($optimizeFor === 'conversion') {
            // Priorizar clareza e keywords
            $score = ($clarityScore * 0.35) + ($keywordScore * 0.35) + ($lengthScore * 0.15) + ($structureScore * 0.15);
        } elseif ($optimizeFor === 'ranking') {
            // Priorizar keywords e estrutura
            $score = ($keywordScore * 0.40) + ($structureScore * 0.25) + ($competitiveScore * 0.20) + ($lengthScore * 0.15);
        }

        return [
            'score' => (int)round($score),
            'length' => $length,
            'details' => $details,
            'issues' => $issues,
            'suggestions' => $suggestions,
            'optimal' => $score >= 85,
            'acceptable' => $score >= 70,
        ];
    }

    /**
     * Calcula score de keywords
     */
    private function calculateKeywordScore(string $title, string $categoryId): int
    {
        $score = 50; // Base

        // Keywords no início do título têm mais peso
        $words = explode(' ', $title);
        $firstWords = implode(' ', array_slice($words, 0, 3));

        // Verificar capitalização (marcas geralmente são capitalizadas)
        if (preg_match('/^[A-Z][a-z]+/', $title)) {
            $score += 15;
        }

        // Verificar números (especificações técnicas)
        if (preg_match('/\d+/', $firstWords)) {
            $score += 15;
        }

        // Verificar se tem marca reconhecível
        $knownBrands = ['Samsung', 'Apple', 'LG', 'Sony', 'Xiaomi', 'Motorola', 'Nike', 'Adidas'];
        foreach ($knownBrands as $brand) {
            if (str_contains($title, $brand)) {
                $score += 20;
                break;
            }
        }

        return min(100, $score);
    }

    /**
     * Calcula score de clareza
     */
    private function calculateClarityScore(string $title): int
    {
        $score = 50;

        $words = explode(' ', $title);
        $wordCount = count($words);

        // Títulos com 4-8 palavras são geralmente mais claros
        if ($wordCount >= 4 && $wordCount <= 8) {
            $score += 30;
        } elseif ($wordCount >= 3 && $wordCount <= 10) {
            $score += 20;
        }

        // Penalizar muito jargão ou abreviações
        $abbreviations = preg_match_all('/\b[A-Z]{2,}\b/', $title);
        if ($abbreviations > 2) {
            $score -= 10;
        }

        // Bonus se tem especificações técnicas claras
        if (preg_match('/\d+\s*(GB|TB|MP|mm|cm|kg|g|W|V|mAh)/', $title)) {
            $score += 20;
        }

        return max(0, min(100, $score));
    }

    /**
     * Calcula score de estrutura
     */
    private function calculateStructureScore(string $title): int
    {
        $score = 50;

        // Boa estrutura: Marca Modelo Especificações
        $words = explode(' ', $title);
        
        // Primeira palavra capitalizada
        if (preg_match('/^[A-Z]/', $title)) {
            $score += 20;
        }

        // Sem pontuação excessiva
        $punctuationCount = preg_match_all('/[^\w\s]/', $title);
        if ($punctuationCount <= 2) {
            $score += 15;
        }

        // Espaçamento consistente
        if (!preg_match('/\s{2,}/', $title)) {
            $score += 15;
        }

        return min(100, $score);
    }

    /**
     * Calcula score competitivo
     */
    private function calculateCompetitiveScore(string $title): int
    {
        $score = 50;

        // Tem diferenciadores (especificações únicas)
        if (preg_match('/\b(Pro|Max|Plus|Ultra|Premium|Special|Edition)\b/i', $title)) {
            $score += 25;
        }

        // Tem especificações técnicas detalhadas
        if (preg_match_all('/\d+/', $title) >= 2) {
            $score += 25;
        }

        return min(100, $score);
    }

    /**
     * Abrevia título para economizar espaço
     */
    private function abbreviateTitle(string $title): string
    {
        $abbreviations = [
            'Gigabyte' => 'GB',
            'Terabyte' => 'TB',
            'Megapixel' => 'MP',
            'Polegadas' => '"',
            'Centímetros' => 'cm',
            'Quilograma' => 'kg',
            'Grama' => 'g',
            'Mililitro' => 'ml',
            'Litro' => 'L',
        ];

        foreach ($abbreviations as $full => $abbr) {
            $title = str_ireplace($full, $abbr, $title);
        }

        return $title;
    }

    /**
     * Modificadores comuns para variações
     */
    private function getCommonModifiers(): array
    {
        return [
            'Original',
            'Novo',
            'Lacrado',
            'Nacional',
            'Importado',
            'Premium',
            'Exclusivo',
            'Completo',
        ];
    }

    /**
     * Parse título em componentes
     */
    private function parseTitleIntoComponents(string $title): array
    {
        $words = explode(' ', $title);
        
        return [
            'brand' => $words[0] ?? '',
            'model' => $words[1] ?? '',
            'specs' => array_slice($words, 2),
            'keywords' => [$title],
            'modifiers' => $this->getCommonModifiers()
        ];
    }

    /**
     * Extrai atributo de item
     */
    private function extractAttribute(array $item, string $attributeId): string
    {
        foreach ($item['attributes'] ?? [] as $attr) {
            if (($attr['id'] ?? '') === $attributeId) {
                return $attr['value_name'] ?? '';
            }
        }
        return '';
    }
}
