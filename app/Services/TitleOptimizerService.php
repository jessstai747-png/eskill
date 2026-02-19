<?php

namespace App\Services;

use App\Services\KeywordResearchService;
use App\Services\CategoryService;
use App\Services\CacheService;
use App\Services\LLMService;
use App\Services\AI\Core\RetryService;

/**
 * Serviço de Otimização de Títulos para Mercado Livre
 *
 * Estratégias de SEO implementadas:
 * - Estrutura ideal: [Marca] + [Modelo] + [Características] + [Diferenciais]
 * - Limite de 60 caracteres respeitado
 * - Keywords de alto impacto priorizadas no início
 * - Remoção de termos proibidos
 * - Capitalização otimizada
 */
class TitleOptimizerService
{
    private KeywordResearchService $keywordService;
    private CategoryService $categoryService;
    private CacheService $cache;
    private LLMService $ai;
    private RetryService $retryService;
    private LogService $logger;

    // Limite máximo de caracteres do ML
    private const MAX_LENGTH = 60;
    private const OPTIMAL_LENGTH = 55;

    // Termos proibidos pelo ML
    private const FORBIDDEN_TERMS = [
        // Promoções (proibido)
        'promoção', 'oferta', 'desconto', 'liquidação', 'black friday',
        'cyber monday', 'queima', 'super oferta', 'mega oferta',
        // Call to action (proibido)
        'compre já', 'aproveite', 'não perca', 'últimas unidades',
        'corra', 'imperdível', 'oportunidade única',
        // Menção de frete (redundante)
        'frete grátis', 'frete gratuito', 'envio grátis',
        // Preço (proibido)
        'menor preço', 'melhor preço', 'preço baixo', 'barato',
        'mais barato', 'econômico',
        // Excesso de pontuação
        '!!!', '???', '***', '...', '+++',
        // Termos genéricos sem valor
        'confira', 'veja', 'clique', 'acesse',
    ];

    // Termos de alto valor SEO
    private const HIGH_VALUE_TERMS = [
        'original' => 10,
        'genuíno' => 9,
        'lacrado' => 9,
        'novo' => 8,
        'garantia' => 8,
        'nota fiscal' => 7,
        'pronta entrega' => 7,
        'envio imediato' => 6,
        'nacional' => 5,
        'importado' => 5,
    ];

    // Conectores permitidos
    private const CONNECTORS = ['para', 'com', 'de', 'e', 'c/', 'p/'];

    public function __construct(?int $accountId = null)
    {
        $this->keywordService = new KeywordResearchService($accountId);
        $this->categoryService = new CategoryService($accountId);
        $this->cache = new CacheService();
        $this->ai = new LLMService();
        $this->retryService = new RetryService();
        $this->logger = new LogService();
    }
    
    /**
     * Otimiza um título existente com IA
     */
    public function optimize(string $currentTitle, array $productInfo = []): array
    {
        $result = [
            'original_title' => $currentTitle,
            'optimized_title' => '',
            'alternative_titles' => [],
            'improvements' => [],
            'score_before' => 0,
            'score_after' => 0,
            'warnings' => [],
        ];

        // Avaliar título atual
        $currentAnalysis = $this->analyzeTitle($currentTitle);
        $result['score_before'] = $currentAnalysis['score'];
        $result['current_analysis'] = $currentAnalysis;

        // Extrair informações do produto
        $brand = $productInfo['brand'] ?? $this->extractBrand($currentTitle);
        $model = $productInfo['model'] ?? $this->extractModel($currentTitle);
        $categoryId = $productInfo['category_id'] ?? null;

        // Obter keywords relevantes
        $keywords = [];
        if ($categoryId) {
            $research = $this->keywordService->researchKeywords($categoryId, $currentTitle);
            $keywords = $research['primary_keywords'] ?? [];
        }

        // Limpar título atual
        $cleanedTitle = $this->cleanTitle($currentTitle);

        try {
            // Use AI to generate optimized title
            $optimizedTitle = $this->generateOptimizedTitleWithAI([
                'current_title' => $currentTitle,
                'brand' => $brand,
                'model' => $model,
                'keywords' => $keywords,
                'category_id' => $categoryId,
                'attributes' => $productInfo['attributes'] ?? [],
                'original_words' => $this->extractImportantWords($cleanedTitle),
            ]);

            $result['optimized_title'] = $optimizedTitle;

        } catch (\Exception $e) {
            $this->logger->error('AI title optimization failed', [
                'error' => $e->getMessage(),
                'current_title' => $currentTitle
            ]);

            // Fallback to traditional optimization
            $result['optimized_title'] = $this->buildOptimizedTitle([
                'brand' => $brand,
                'model' => $model,
                'keywords' => $keywords,
                'original_words' => $this->extractImportantWords($cleanedTitle),
                'category_id' => $categoryId,
                'attributes' => $productInfo['attributes'] ?? [],
            ]);
        }

        // Gerar alternativas com IA
        $result['alternative_titles'] = $this->generateAlternatives([
            'brand' => $brand,
            'model' => $model,
            'keywords' => $keywords,
            'original_words' => $this->extractImportantWords($cleanedTitle),
        ]);

        // Avaliar título otimizado
        $optimizedAnalysis = $this->analyzeTitle($result['optimized_title']);
        $result['score_after'] = $optimizedAnalysis['score'];
        $result['optimized_analysis'] = $optimizedAnalysis;

        // Listar melhorias feitas
        $result['improvements'] = $this->listImprovements($currentAnalysis, $optimizedAnalysis);

        return $result;
    }

    /**
     * Generate optimized title using AI
     */
    private function generateOptimizedTitleWithAI(array $context): string
    {
        $cacheKey = 'ai_optimized_title_' . md5(serialize($context));
        $cached = $this->cache->get($cacheKey, 'ai_titles');
        if ($cached) {
            return $cached;
        }

        $prompt = "Otimizar este título para SEO no marketplace Mercado Livre:

Título atual: {$context['current_title']}
Marca: {$context['brand']}
Modelo: {$context['model']}
Categoria: {$context['category_id']}
Atributos: " . json_encode($context['attributes']) . "
Keywords prioritárias: " . implode(', ', array_column($context['keywords'], 'keyword')) . "

Regras:
- Máximo de 60 caracteres
- Priorizar marca e modelo no início
- Incluir keywords relevantes
- Evitar termos proibidos: " . implode(', ', self::FORBIDDEN_TERMS) . "
- Manter capitalização apropriada
- Focar em conversão e atratividade

Retorne APENAS o título otimizado, sem explicações adicionais.";

        $result = $this->retryService->execute(
            fn() => $this->ai->generate($prompt, "Você é um especialista em SEO e otimização de títulos para marketplaces. Gere títulos otimizados que aumentem a visibilidade e conversão.", 'advanced'),
            'generate_optimized_title',
            ['timeout', 'rate limit', 'service unavailable']
        );

        if ($result['success']) {
            $optimizedTitle = trim($result['content']);

            // Ensure it meets requirements
            $optimizedTitle = $this->validateAndAdjustTitle($optimizedTitle);

            // Cache the result
            $this->cache->set($cacheKey, $optimizedTitle, 'ai_titles', 7200); // 2 hours

            return $optimizedTitle;
        }

        // Fallback to traditional method
        $fallbackTitle = $this->buildOptimizedTitle($context);

        // Cache the fallback
        $this->cache->set($cacheKey, $fallbackTitle, 'ai_titles', 7200); // 2 hours

        return $fallbackTitle;
    }

    /**
     * Validate and adjust title to meet requirements
     */
    private function validateAndAdjustTitle(string $title): string
    {
        // Ensure max length
        if (mb_strlen($title) > self::MAX_LENGTH) {
            $title = mb_substr($title, 0, self::MAX_LENGTH);
        }

        // Clean forbidden terms
        $title = $this->cleanTitle($title);

        // Ensure proper capitalization
        $title = $this->ensureProperCapitalization($title);

        return $title;
    }

    /**
     * Ensure proper capitalization
     */
    private function ensureProperCapitalization(string $title): string
    {
        $words = explode(' ', $title);
        $result = [];

        foreach ($words as $i => $word) {
            if ($i === 0) {
                // First word should be capitalized
                $result[] = ucfirst(strtolower($word));
            } else {
                // Check if it's a connector that should stay lowercase
                if (in_array(strtolower($word), self::CONNECTORS)) {
                    $result[] = strtolower($word);
                } else {
                    $result[] = ucfirst(strtolower($word));
                }
            }
        }

        return implode(' ', $result);
    }
    
    /**
     * Analisa qualidade de um título
     */
    public function analyzeTitle(string $title): array
    {
        $analysis = [
            'title' => $title,
            'length' => mb_strlen($title),
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'positives' => [],
        ];
        
        // 1. Comprimento (0-25 pontos)
        $length = $analysis['length'];
        if ($length >= 45 && $length <= 58) {
            $analysis['score'] += 25;
            $analysis['positives'][] = 'Comprimento ideal';
        } elseif ($length >= 35 && $length <= 60) {
            $analysis['score'] += 20;
        } elseif ($length > 60) {
            $analysis['score'] += 10;
            $analysis['issues'][] = 'Título muito longo (máx 60 caracteres)';
        } else {
            $analysis['score'] += 10;
            $analysis['issues'][] = 'Título muito curto';
        }
        
        // 2. Termos proibidos (0-20 pontos)
        $forbiddenFound = $this->findForbiddenTerms($title);
        if (empty($forbiddenFound)) {
            $analysis['score'] += 20;
            $analysis['positives'][] = 'Sem termos proibidos';
        } else {
            $analysis['issues'][] = 'Termos proibidos: ' . implode(', ', $forbiddenFound);
        }
        
        // 3. Termos de alto valor (0-20 pontos)
        $highValueFound = $this->findHighValueTerms($title);
        $highValueScore = min(20, count($highValueFound) * 5);
        $analysis['score'] += $highValueScore;
        if (!empty($highValueFound)) {
            $analysis['positives'][] = 'Termos de valor: ' . implode(', ', array_keys($highValueFound));
        }
        
        // 4. Estrutura (0-20 pontos)
        $structureScore = $this->evaluateStructure($title);
        $analysis['score'] += $structureScore;
        if ($structureScore >= 15) {
            $analysis['positives'][] = 'Boa estrutura';
        }
        
        // 5. Capitalização (0-10 pontos)
        if ($this->hasProperCapitalization($title)) {
            $analysis['score'] += 10;
            $analysis['positives'][] = 'Capitalização correta';
        } else {
            $analysis['score'] += 5;
            $analysis['issues'][] = 'Capitalização pode ser melhorada';
        }
        
        // 6. Palavras repetidas (0-5 pontos)
        if (!$this->hasRepeatedWords($title)) {
            $analysis['score'] += 5;
        } else {
            $analysis['issues'][] = 'Palavras repetidas detectadas';
        }
        
        $analysis['grade'] = $this->calculateGrade($analysis['score']);
        
        return $analysis;
    }
    
    /**
     * Constrói título otimizado
     */
    private function buildOptimizedTitle(array $components): string
    {
        $parts = [];
        
        // 1. Marca (sempre primeiro se disponível)
        if (!empty($components['brand'])) {
            $parts[] = $this->capitalize($components['brand']);
        }
        
        // 2. Modelo
        if (!empty($components['model'])) {
            $parts[] = $components['model'];
        }
        
        // 3. Palavras importantes do título original (que não são marca/modelo)
        $originalWords = $components['original_words'] ?? [];
        $usedWords = array_map('mb_strtolower', $parts);
        
        foreach ($originalWords as $word) {
            if (!in_array(mb_strtolower($word), $usedWords) && mb_strlen($word) > 2) {
                $parts[] = $this->capitalize($word);
                $usedWords[] = mb_strtolower($word);
            }
        }
        
        // 4. Keywords de alto impacto
        $keywords = $components['keywords'] ?? [];
        foreach (array_slice($keywords, 0, 3) as $kw) {
            $keyword = $kw['keyword'] ?? '';
            if (!empty($keyword) && !in_array(mb_strtolower($keyword), $usedWords)) {
                $parts[] = $this->capitalize($keyword);
                $usedWords[] = mb_strtolower($keyword);
            }
        }
        
        // 5. Termos de alto valor SEO se couber
        foreach (self::HIGH_VALUE_TERMS as $term => $score) {
            if (!in_array(mb_strtolower($term), $usedWords)) {
                $tempTitle = implode(' ', array_merge($parts, [$this->capitalize($term)]));
                if (mb_strlen($tempTitle) <= self::OPTIMAL_LENGTH) {
                    $parts[] = $this->capitalize($term);
                    break;
                }
            }
        }
        
        // Montar título respeitando limite
        $title = $this->assembleTitle($parts);
        
        return $title;
    }
    
    /**
     * Monta título respeitando limite de caracteres
     */
    private function assembleTitle(array $parts): string
    {
        $title = '';
        
        foreach ($parts as $part) {
            $testTitle = trim($title . ' ' . $part);
            if (mb_strlen($testTitle) <= self::MAX_LENGTH) {
                $title = $testTitle;
            } else {
                break;
            }
        }
        
        return trim($title);
    }
    
    /**
     * Gera títulos alternativos
     */
    private function generateAlternatives(array $components): array
    {
        $alternatives = [];
        $brand = $components['brand'] ?? '';
        $model = $components['model'] ?? '';
        $keywords = $components['keywords'] ?? [];
        $originalWords = $components['original_words'] ?? [];
        
        // Alternativa 1: Modelo + Marca
        if ($brand && $model) {
            $alt1Parts = [$model, $brand];
            $alt1Parts = array_merge($alt1Parts, array_slice($originalWords, 0, 3));
            $alternatives[] = [
                'title' => $this->assembleTitle($alt1Parts),
                'strategy' => 'Modelo primeiro (busca por modelo)',
            ];
        }
        
        // Alternativa 2: Foco em keywords
        if (!empty($keywords)) {
            $keywordFirst = array_column(array_slice($keywords, 0, 2), 'keyword');
            $alt2Parts = array_merge($keywordFirst, [$brand, $model]);
            $alt2Parts = array_filter($alt2Parts);
            $alternatives[] = [
                'title' => $this->assembleTitle($alt2Parts),
                'strategy' => 'Keywords primeiro (maior relevância)',
            ];
        }
        
        // Alternativa 3: Descritivo com benefício
        $benefitTerms = ['Original', 'Garantia', 'Novo'];
        $alt3Parts = [$brand, $model];
        $alt3Parts = array_merge($alt3Parts, $benefitTerms);
        $alternatives[] = [
            'title' => $this->assembleTitle(array_filter($alt3Parts)),
            'strategy' => 'Foco em benefícios/garantias',
        ];
        
        // Alternativa 4: Long-tail
        $alt4Parts = array_merge(
            [$brand, $model],
            array_slice($originalWords, 0, 5)
        );
        $alternatives[] = [
            'title' => $this->assembleTitle(array_filter($alt4Parts)),
            'strategy' => 'Long-tail (específico)',
        ];
        
        // Avaliar cada alternativa
        foreach ($alternatives as &$alt) {
            $analysis = $this->analyzeTitle($alt['title']);
            $alt['score'] = $analysis['score'];
            $alt['grade'] = $analysis['grade'];
        }
        
        // Ordenar por score
        usort($alternatives, fn($a, $b) => $b['score'] - $a['score']);
        
        return $alternatives;
    }
    
    /**
     * Limpa título removendo termos proibidos e normalizando
     */
    private function cleanTitle(string $title): string
    {
        $cleaned = $title;
        
        // Remover termos proibidos
        foreach (self::FORBIDDEN_TERMS as $term) {
            $pattern = '/\b' . preg_quote($term, '/') . '\b/iu';
            $cleaned = preg_replace($pattern, '', $cleaned);
        }
        
        // Remover pontuação excessiva
        $cleaned = preg_replace('/[!?*]{2,}/', '', $cleaned);
        $cleaned = preg_replace('/\.{3,}/', '', $cleaned);
        
        // Normalizar espaços
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        
        return trim($cleaned);
    }
    
    /**
     * Extrai marca do título
     */
    private function extractBrand(string $title): ?string
    {
        // Lista de marcas conhecidas (expandir conforme necessário)
        $knownBrands = [
            'samsung', 'apple', 'lg', 'sony', 'motorola', 'xiaomi', 'huawei',
            'asus', 'dell', 'hp', 'lenovo', 'acer', 'positivo', 'multilaser',
            'nike', 'adidas', 'puma', 'new balance', 'olympikus', 'fila',
            'tramontina', 'mondial', 'britânia', 'philco', 'electrolux',
            'brastemp', 'consul', 'intelbras', 'tp-link', 'd-link',
            'jbl', 'harman', 'bose', 'beats', 'edifier', 'philips'
        ];
        
        $titleLower = mb_strtolower($title);
        
        foreach ($knownBrands as $brand) {
            if (mb_strpos($titleLower, $brand) !== false) {
                return ucfirst($brand);
            }
        }
        
        // Tentar extrair primeira palavra capitalizada como marca
        $words = explode(' ', $title);
        if (!empty($words[0]) && preg_match('/^[A-Z]/', $words[0])) {
            return $words[0];
        }
        
        return null;
    }
    
    /**
     * Extrai modelo do título
     */
    private function extractModel(string $title): ?string
    {
        // Padrões comuns de modelo: letras+números, números+letras
        $patterns = [
            '/\b([A-Z]{1,3}\d{2,6}[A-Z]?)\b/i', // A12, XS11, GT2
            '/\b(\d{2,4}[A-Z]{1,3})\b/i', // 12Pro, 256GB
            '/\b(Pro|Max|Plus|Ultra|Lite|Mini|Air)\b/i', // Sufixos de modelo
            '/\b(Galaxy\s+[A-Z]\d{1,2})/i', // Galaxy S21
            '/\b(iPhone\s+\d{1,2}(\s+Pro)?)/i', // iPhone 13 Pro
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Extrai palavras importantes do título
     */
    private function extractImportantWords(string $title): array
    {
        // Stop words para remover
        $stopWords = ['a', 'o', 'de', 'da', 'do', 'em', 'um', 'uma', 'para', 'com', 'e', 'ou', 'que'];
        
        $words = preg_split('/\s+/', $title);
        $important = [];
        
        foreach ($words as $word) {
            $wordLower = mb_strtolower($word);
            if (mb_strlen($word) > 2 && !in_array($wordLower, $stopWords)) {
                $important[] = $word;
            }
        }
        
        return $important;
    }
    
    /**
     * Encontra termos proibidos no título
     */
    private function findForbiddenTerms(string $title): array
    {
        $found = [];
        $titleLower = mb_strtolower($title);
        
        foreach (self::FORBIDDEN_TERMS as $term) {
            if (mb_strpos($titleLower, mb_strtolower($term)) !== false) {
                $found[] = $term;
            }
        }
        
        return $found;
    }
    
    /**
     * Encontra termos de alto valor no título
     */
    private function findHighValueTerms(string $title): array
    {
        $found = [];
        $titleLower = mb_strtolower($title);
        
        foreach (self::HIGH_VALUE_TERMS as $term => $score) {
            if (mb_strpos($titleLower, mb_strtolower($term)) !== false) {
                $found[$term] = $score;
            }
        }
        
        return $found;
    }
    
    /**
     * Avalia estrutura do título
     */
    private function evaluateStructure(string $title): int
    {
        $score = 0;
        
        // Tem números (modelos)
        if (preg_match('/\d/', $title)) {
            $score += 5;
        }
        
        // Começa com maiúscula (marca)
        if (preg_match('/^[A-Z]/', $title)) {
            $score += 5;
        }
        
        // Tem variação de case (não é tudo maiúsculo ou minúsculo)
        if (preg_match('/[a-z]/', $title) && preg_match('/[A-Z]/', $title)) {
            $score += 5;
        }
        
        // Não tem excesso de maiúsculas
        $upperCount = preg_match_all('/[A-Z]/', $title);
        $totalChars = mb_strlen(preg_replace('/[^a-zA-Z]/', '', $title));
        if ($totalChars > 0 && ($upperCount / $totalChars) < 0.5) {
            $score += 5;
        }
        
        return $score;
    }
    
    /**
     * Verifica capitalização adequada
     */
    private function hasProperCapitalization(string $title): bool
    {
        // Primeira letra maiúscula
        if (!preg_match('/^[A-ZÀ-Ú]/', $title)) {
            return false;
        }
        
        // Não é tudo maiúsculo
        if ($title === mb_strtoupper($title)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verifica se tem palavras repetidas
     */
    private function hasRepeatedWords(string $title): bool
    {
        $words = preg_split('/\s+/', mb_strtolower($title));
        $wordCount = array_count_values($words);
        
        foreach ($wordCount as $word => $count) {
            if ($count > 1 && mb_strlen($word) > 2) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Capitaliza texto de forma inteligente
     */
    private function capitalize(string $text): string
    {
        // Palavras que devem ficar minúsculas (conectores)
        $lowercase = ['de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'e', 'ou', 'a', 'o'];
        
        $words = explode(' ', mb_strtolower($text));
        $result = [];
        
        foreach ($words as $i => $word) {
            if ($i === 0 || !in_array($word, $lowercase)) {
                $result[] = mb_strtoupper(mb_substr($word, 0, 1)) . mb_substr($word, 1);
            } else {
                $result[] = $word;
            }
        }
        
        return implode(' ', $result);
    }
    
    /**
     * Calcula nota baseada no score
     */
    private function calculateGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    /**
     * Lista melhorias realizadas
     */
    private function listImprovements(array $before, array $after): array
    {
        $improvements = [];
        
        if ($after['length'] !== $before['length']) {
            $improvements[] = "Comprimento ajustado: {$before['length']} → {$after['length']} caracteres";
        }
        
        // Problemas resolvidos
        foreach ($before['issues'] as $issue) {
            if (!in_array($issue, $after['issues'])) {
                $improvements[] = "Corrigido: {$issue}";
            }
        }
        
        // Novos positivos
        foreach ($after['positives'] as $positive) {
            if (!in_array($positive, $before['positives'])) {
                $improvements[] = "Adicionado: {$positive}";
            }
        }
        
        if ($after['score'] > $before['score']) {
            $diff = $after['score'] - $before['score'];
            $improvements[] = "Score melhorado em {$diff} pontos";
        }
        
        return $improvements;
    }
    
    /**
     * Sugere título baseado em categoria e atributos com IA
     */
    public function suggestTitle(string $categoryId, array $attributes): array
    {
        $cacheKey = 'title_suggestion_' . md5($categoryId . serialize($attributes));
        $cached = $this->cache->get($cacheKey, 'ai_titles');
        if ($cached) {
            return $cached;
        }

        // Extrair informações dos atributos
        $brand = '';
        $model = '';
        $specs = [];

        foreach ($attributes as $attr) {
            $attrId = $attr['id'] ?? '';
            $value = $attr['value_name'] ?? $attr['value'] ?? '';

            if ($attrId === 'BRAND') {
                $brand = $value;
            } elseif ($attrId === 'MODEL' || $attrId === 'LINE') {
                $model = $value;
            } elseif (!empty($value) && mb_strlen($value) < 20) {
                $specs[] = $value;
            }
        }

        // Pesquisar keywords da categoria
        $research = $this->keywordService->researchKeywords($categoryId);
        $topKeywords = array_slice($research['primary_keywords'] ?? [], 0, 3);

        try {
            // Use AI to suggest title
            $prompt = "Sugerir título otimizado para SEO no Mercado Livre com base nas seguintes informações:

Categoria: {$categoryId}
Marca: {$brand}
Modelo: {$model}
Especificações: " . implode(', ', $specs) . "
Keywords prioritárias: " . implode(', ', array_column($topKeywords, 'keyword')) . "

Regras:
- Máximo de 60 caracteres
- Priorizar marca e modelo no início
- Incluir keywords relevantes
- Evitar termos proibidos: " . implode(', ', self::FORBIDDEN_TERMS) . "
- Manter capitalização apropriada
- Focar em conversão e atratividade

Retorne APENAS o título sugerido, sem explicações adicionais.";

            $result = $this->retryService->execute(
                fn() => $this->ai->generate($prompt, "Você é um especialista em SEO e otimização de títulos para marketplaces. Gere títulos otimizados que aumentem a visibilidade e conversão.", 'advanced'),
                'suggest_title',
                ['timeout', 'rate limit', 'service unavailable']
            );

            if ($result['success']) {
                $suggestedTitle = trim($result['content']);

                // Validate and adjust title
                $suggestedTitle = $this->validateAndAdjustTitle($suggestedTitle);
            } else {
                // Fallback to traditional method
                $suggestedTitle = $this->buildTraditionalSuggestedTitle($categoryId, $brand, $model, $specs, $topKeywords);
            }

        } catch (\Exception $e) {
            $this->logger->error('AI title suggestion failed', [
                'error' => $e->getMessage(),
                'category_id' => $categoryId
            ]);

            // Fallback to traditional method
            $suggestedTitle = $this->buildTraditionalSuggestedTitle($categoryId, $brand, $model, $specs, $topKeywords);
        }

        // Gerar variações
        $alternatives = $this->generateAlternatives([
            'brand' => $brand,
            'model' => $model,
            'keywords' => $topKeywords,
            'original_words' => $specs,
        ]);

        $result = [
            'suggested_title' => $suggestedTitle,
            'alternatives' => $alternatives,
            'analysis' => $this->analyzeTitle($suggestedTitle),
            'keywords_used' => array_column($topKeywords, 'keyword'),
        ];

        // Cache the result
        $this->cache->set($cacheKey, $result, 'ai_titles', 7200); // 2 hours

        return $result;
    }

    /**
     * Build suggested title using traditional method (fallback)
     */
    private function buildTraditionalSuggestedTitle(string $categoryId, string $brand, string $model, array $specs, array $topKeywords): string
    {
        // Construir título
        $parts = array_filter([$brand, $model]);
        $parts = array_merge($parts, array_slice($specs, 0, 3));

        foreach ($topKeywords as $kw) {
            $keyword = $kw['keyword'] ?? '';
            if (!empty($keyword)) {
                $parts[] = $this->capitalize($keyword);
            }
        }

        return $this->assembleTitle($parts);
    }
}
