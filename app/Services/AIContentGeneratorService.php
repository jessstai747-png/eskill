<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\SEO\KeywordSourceService;
use App\Services\MercadoLivreClient;
use Exception;

/**
 * Serviço de Geração de Conteúdo por IA
 *
 * Sistema avançado para geração automática de:
 * - Descrições de produtos otimizadas
 * - Títulos SEO-friendly
 * - Bullets points atrativos
 * - Conteúdo personalizado por categoria
 *
 * @author Sistema ML Manager V8.0
 * @version 8.0.0
 */
class AIContentGeneratorService
{
    private \PDO $db;
    private LogService $logger;
    private CacheManagerService $cache;
    private array $aiModels;
    private array $templates;

    private LLMService $llm;
    private MercadoLivreClient $mlClient;
    private KeywordSourceService $keywordSource;

    public function __construct()
    {
        $this->db = Database::getInstance();

        $this->logger = new LogService();
        $this->cache = new CacheManagerService();
        $this->llm = new LLMService();
        $accountId = $_SESSION['active_ml_account_id'] ?? null;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->keywordSource = new KeywordSourceService($accountId);
        $this->initializeAIModels();
        // $this->loadTemplates(); // Templates replaced by Prompts
    }

    // ... methods ...

    /**
     * Gera conteúdo usando IA (INTEGRAÇÃO REAL)
     */
    private function generateWithAI(string $model, array $params): string
    {
        $type = $params['type'];
        $product = $params['product'];

        // Build Prompt Context
        $context = $this->buildContextPrompt($product, $params);

        switch ($type) {
            case 'product_description':
                $system = "Você é o maior especialista em Neuro-Copywriting para Marketplaces do Brasil. " .
                    "Sua missão é transformar visitantes em compradores usando gatilhos mentais de Escassez, Autoridade e Prova Social. " .
                    "Escreva textos altamente persuasivos, estruturados para leitura rápida (escaneabilidade), destacando benefícios emocionais e especificações técnicas com clareza absoluta.";

                $prompt = "Crie uma descrição de ALTA CONVERSÃO para o produto abaixo. Use a estrutura:\n" .
                    "1. **Gancho Inicial Irresistível**: Uma frase que conecte a dor/desejo do cliente à solução.\n" .
                    "2. **Lista de Benefícios (Bullets)**: Use emojis e negrito para destacar o 'porquê' comprar.\n" .
                    "3. **Ficha Técnica Humanizada**: Explique as specs técnicas traduzindo para benefícios reais.\n" .
                    "4. **Garantia e Segurança**: Reforce a confiança na compra.\n" .
                    "5. **CTA (Chamada para Ação)**: Convite claro para a compra.\n\n" .
                    "Dados do Produto:\n{$context}\n\n" .
                    "Tom de voz: Profissional, Seguro e Empático.\n" .
                    "Formatação: Markdown limpo.";

                $result = $this->llm->generate($prompt, $system, 'advanced');
                if (empty($result['success'])) {
                    throw new Exception($result['error'] ?? 'Falha ao gerar descrição');
                }
                return $result['content'];

            case 'product_title':
                $system = "Especialista em SEO para Marketplaces (Mercado Livre). " .
                    "Crie títulos de alta conversão com até 60 caracteres. Sem enrolação.";

                $prompt = "Gere UM título otimizado para:\n{$context}\n" .
                    "Regra: Max 60 chars. Priorize: Produto + Marca + Modelo + Atributo chave.";

                $result = $this->llm->generate($prompt, $system, 'basic');
                if (empty($result['success'])) {
                    throw new Exception($result['error'] ?? 'Falha ao gerar título');
                }
                return trim($result['content'], '"');

            default:
                throw new Exception('Tipo de conteúdo não suportado');
        }
    }

    private function buildContextPrompt(array $product, array $params): string
    {
        $txt = "Produto: " . ($product['title'] ?? '') . "\n";
        $txt .= "Marca: " . ($product['brand'] ?? '') . "\n";
        $txt .= "Preço: R$ " . ($product['price'] ?? '') . "\n";
        $txt .= "Atributos: " . json_encode($product['attributes'] ?? []) . "\n";

        // Gap Keywords
        if (!empty($params['options']['gap_keywords'])) {
            $txt .= "PALAVRAS-CHAVE OBRIGATÓRIAS (SEO): " . implode(', ', $params['options']['gap_keywords']) . "\n";
            $txt .= "ATENÇÃO: O texto DEVE focar nessas palavras-chave para cobrir lacunas de mercado.";
        }

        return $txt;
    }

    // ========== GERAÇÃO DE DESCRIÇÕES PRINCIPAIS ==========

    /**
     * Gera descrição completa otimizada para produto
     */
    public function generateProductDescription(array $productData, array $options = []): array
    {
        try {
            $cacheKey = 'ai_description_' . md5(json_encode($productData));
            $cached = $this->cache->get($cacheKey, 'ai_content');
            if ($cached && !($options['force_regenerate'] ?? false)) {
                return $cached;
            }

            // Análise do produto
            $analysis = $this->analyzeProduct($productData);

            // Seleção do modelo de IA apropriado
            $model = $this->selectBestModel($productData, 'description');

            // Geração da descrição
            $description = $this->generateWithAI($model, [
                'type' => 'product_description',
                'product' => $productData,
                'analysis' => $analysis,
                'options' => array_merge($this->getDefaultOptions(), $options)
            ]);

            // Otimização SEO
            $optimized = $this->optimizeForSEO($description, $productData);

            // Validação de qualidade
            $quality = $this->validateContentQuality($optimized);

            $result = [
                'success' => true,
                'description' => $optimized,
                'quality_score' => $quality['score'],
                'metrics' => [
                    'word_count' => str_word_count($optimized),
                    'character_count' => mb_strlen($optimized),
                    'seo_score' => $quality['seo_score'],
                    'readability_score' => $quality['readability_score']
                ],
                'suggestions' => $quality['suggestions'],
                'model_used' => $model,
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // Cache por 1 hora
            $this->cache->set($cacheKey, $result, 'ai_content', 3600);

            // Log da geração
            $this->logger->info('AI description generated', [
                'product_id' => $productData['id'] ?? null,
                'quality_score' => $quality['score'],
                'model' => $model
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('AI content generation failed', [
                'error' => $e->getMessage(),
                'product' => $productData['id'] ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Dispatch AI generation to background queue
     */
    public function dispatchGeneration(array $productData, string $type = 'description'): string
    {
        $jobService = new JobService();
        $payload = [
            'prompt' => $this->buildContextPrompt($productData, ['type' => "product_{$type}", 'options' => ['gap_keywords' => []]]),
            'system' => 'Ecommerce Expert',
            'complexity' => 'advanced'
        ];

        $jobId = $jobService->dispatch('ai_generation', $payload);
        return (string)$jobId;
    }

    /**
     * Gera título otimizado para produto
     */
    public function generateOptimizedTitle(array $productData, array $options = []): array
    {
        try {
            $analysis = $this->analyzeProduct($productData);
            $keywords = $this->extractKeywords($productData, $analysis);

            $model = $this->selectBestModel($productData, 'title');

            $title = $this->generateWithAI($model, [
                'type' => 'product_title',
                'product' => $productData,
                'keywords' => $keywords,
                'max_length' => $options['max_length'] ?? 60,
                'style' => $options['style'] ?? 'professional'
            ]);

            // Validação de limites ML
            $validated = $this->validateMLTitle($title, $productData);

            return [
                'success' => true,
                'title' => $validated['title'],
                'original_title' => $title,
                'adjustments_made' => $validated['adjustments'],
                'character_count' => mb_strlen($validated['title']),
                'seo_keywords' => $keywords,
                'model_used' => $model
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Gera bullets points atrativos
     */
    public function generateBulletPoints(array $productData, int $count = 5): array
    {
        try {
            $analysis = $this->analyzeProduct($productData);
            $features = $this->extractFeatures($productData, $analysis);

            $model = $this->selectBestModel($productData, 'bullets');

            $bullets = $this->generateWithAI($model, [
                'type' => 'bullet_points',
                'product' => $productData,
                'features' => $features,
                'count' => $count,
                'style' => 'persuasive'
            ]);

            $list = is_array($bullets) ? $bullets : array_filter(array_map('trim', explode("\n", $bullets)));
            return [
                'success' => true,
                'bullets' => $list,
                'total_count' => count($list),
                'model_used' => $model
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // ========== ANÁLISE E INTELIGÊNCIA ==========

    /**
     * Analisa produto para extração de insights
     */
    private function analyzeProduct(array $productData): array
    {
        $analysis = [
            'category_insights' => $this->getCategoryInsights($productData['category_id'] ?? null),
            'brand_positioning' => $this->getBrandPositioning($productData['brand'] ?? null),
            'price_positioning' => $this->analyzePricePositioning($productData['price'] ?? 0),
            'attributes_analysis' => $this->analyzeAttributes($productData['attributes'] ?? []),
            'competitive_analysis' => $this->getCompetitiveInsights($productData),
            'seo_opportunities' => $this->findSEOOpportunities($productData)
        ];

        return $analysis;
    }

    /**
     * Extrai keywords estratégicas
     */
    private function extractKeywords(array $productData, array $analysis): array
    {
        $keywords = [];

        // Keywords da categoria
        if (isset($analysis['category_insights']['keywords'])) {
            $keywords = array_merge($keywords, $analysis['category_insights']['keywords']);
        }

        // Keywords da marca
        if (isset($productData['brand'])) {
            $keywords[] = $productData['brand'];
        }

        // Keywords do título
        if (isset($productData['title'])) {
            $titleWords = $this->extractImportantWords($productData['title']);
            $keywords = array_merge($keywords, $titleWords);
        }

        // Keywords dos atributos
        foreach ($productData['attributes'] ?? [] as $attr) {
            if (isset($attr['value'])) {
                $keywords[] = $attr['value'];
            }
        }

        return array_unique(array_filter($keywords));
    }

    // ========== MODELOS DE IA ==========

    /**
     * Seleciona o melhor modelo para o tipo de conteúdo
     */
    private function selectBestModel(array $productData, string $contentType): string
    {
        $category = $productData['category_id'] ?? 'default';
        $complexity = $this->assessComplexity($productData);

        // Lógica de seleção baseada em categoria e complexidade
        if ($complexity > 0.8) {
            return $this->aiModels['advanced'][$contentType] ?? $this->aiModels['default'];
        } elseif ($complexity > 0.5) {
            return $this->aiModels['intermediate'][$contentType] ?? $this->aiModels['default'];
        } else {
            return $this->aiModels['basic'][$contentType] ?? $this->aiModels['default'];
        }
    }

    // Old templates removed in favor of LLMService

    // ========== TEMPLATES DE GERAÇÃO ==========

    /**
     * Template avançado para descrições (GAP OPTIMIZED)
     */
    private function generateDescriptionTemplate(array $product, array $params): string
    {
        $brand = $product['brand'] ?? 'Produto';
        $title = $product['title'] ?? 'Item';
        $category = $params['analysis']['category_insights']['name'] ?? 'categoria';

        // Gap Keywords Integration
        $gapKeywords = $params['options']['gap_keywords'] ?? [];
        $gapPhrase = !empty($gapKeywords) ? implode(' ', array_slice($gapKeywords, 0, 2)) : '';

        $description = "🔥 **{$brand} {$title}** - {$gapPhrase} Ideal para {$category}!\n\n";

        if (!empty($gapPhrase)) {
            $description .= "🚀 **OPORTUNIDADE EXCLUSIVA**: Projetado especificamente para quem busca **" . mb_strtoupper($gapPhrase) . "** com qualidade superior.\n\n";
        }

        $description .= "✨ **CARACTERÍSTICAS PRINCIPAIS:**\n";
        foreach ($product['attributes'] ?? [] as $attr) {
            if (isset($attr['name']) && isset($attr['value'])) {
                $description .= "• **{$attr['name']}**: {$attr['value']}\n";
            }
        }

        $description .= "\n🎯 **POR QUE ESCOLHER ESTE PRODUTO:**\n";
        $description .= "• Qualidade premium garantida\n";
        $description .= "• Melhor custo-benefício do mercado\n";
        $description .= "• Entrega rápida e segura\n";

        // Add SEO keywords naturally
        if (!empty($gapKeywords)) {
            foreach (array_slice($gapKeywords, 2, 3) as $k) {
                $description .= "• Referência em {$k}\n";
            }
        }

        $description .= "• Suporte técnico especializado\n\n";

        $description .= "🛒 **COMPRE AGORA** e aproveite nossas condições especiais!\n";
        $description .= "💳 Aceitamos todas as formas de pagamento\n";
        $description .= "🚚 Frete GRÁTIS para todo o Brasil*\n\n";

        $description .= "*Consulte condições na página do produto.";

        return $description;
    }

    /**
     * Template para títulos otimizados
     */
    private function generateTitleTemplate(array $product, array $params): string
    {
        $brand = $product['brand'] ?? '';
        $mainTitle = $product['title'] ?? 'Produto';
        $keywords = $params['keywords'] ?? [];

        // Limpar título existente
        $cleanTitle = preg_replace('/\s+/', ' ', trim($mainTitle));

        // Adicionar palavras-chave importantes no início se não estiverem
        $importantKeywords = array_slice($keywords, 0, 2);
        $titleParts = [];

        if ($brand && stripos($cleanTitle, $brand) === false) {
            $titleParts[] = $brand;
        }

        $titleParts[] = $cleanTitle;

        foreach ($importantKeywords as $keyword) {
            if (stripos(implode(' ', $titleParts), $keyword) === false) {
                $titleParts[] = $keyword;
            }
        }

        $finalTitle = implode(' ', $titleParts);

        // Limitar a 60 caracteres
        if (mb_strlen($finalTitle) > 60) {
            $finalTitle = mb_substr($finalTitle, 0, 57) . '...';
        }

        return $finalTitle;
    }

    // ========== VALIDAÇÃO E OTIMIZAÇÃO ==========

    /**
     * Valida qualidade do conteúdo gerado
     */
    private function validateContentQuality(string $content): array
    {
        $wordCount = str_word_count($content);
        $charCount = mb_strlen($content);

        // Score baseado em múltiplos fatores
        $score = 0;

        // Tamanho apropriado (500-2000 caracteres)
        if ($charCount >= 500 && $charCount <= 2000) {
            $score += 25;
        } elseif ($charCount >= 300) {
            $score += 15;
        }

        // Presença de elementos estruturais
        if (strpos($content, '**') !== false) $score += 10; // Negrito
        if (strpos($content, '•') !== false || strpos($content, '-') !== false) $score += 10; // Bullets
        if (strpos($content, '\n') !== false) $score += 10; // Quebras de linha

        // Palavras-chave relevantes
        $keywordDensity = $this->calculateKeywordDensity($content);
        if ($keywordDensity > 0.02 && $keywordDensity < 0.08) {
            $score += 15;
        }

        // Legibilidade
        $readabilityScore = $this->calculateReadabilityScore($content);
        $score += min(20, $readabilityScore);

        // SEO Score
        $seoScore = $this->calculateSEOScore($content);
        $score += min(10, $seoScore);

        return [
            'score' => min(100, $score),
            'seo_score' => $seoScore,
            'readability_score' => $readabilityScore,
            'suggestions' => $this->generateSuggestions($score, $content)
        ];
    }

    // ========== MÉTODOS AUXILIARES ==========

    /**
     * Inicializa modelos de IA disponíveis
     */
    private function initializeAIModels(): void
    {
        // Now fully integrated with LLMService
        $this->aiModels = [
            'basic' => [
                'description' => 'basic', // Maps to Claude Haiku in LLMService
                'title' => 'basic',
                'bullets' => 'basic'
            ],
            'intermediate' => [
                'description' => 'advanced', // Maps to Claude Sonnet in LLMService
                'title' => 'basic',
                'bullets' => 'basic'
            ],
            'advanced' => [
                'description' => 'advanced',
                'title' => 'advanced',
                'bullets' => 'advanced'
            ],
            'default' => 'basic'
        ];
    }

    /**
     * Avalia complexidade do produto
     */
    private function assessComplexity(array $productData): float
    {
        $complexity = 0.5; // Base higher to favor better models

        // Atributos
        $attrCount = count($productData['attributes'] ?? []);
        $complexity += min(0.3, $attrCount * 0.05);

        // Categoria técnica
        $technicalCategories = ['MLB1648', 'MLB1000', 'MLB1051']; // Exemplos
        if (in_array($productData['category_id'] ?? '', $technicalCategories)) {
            $complexity += 0.3;
        }

        // Preço alto (produtos premium)
        $price = $productData['price'] ?? 0;
        if ($price > 200) { // Lower threshold for premium
            $complexity += 0.2;
        }

        return min(1.0, $complexity);
    }

    // Métodos auxiliares adicionais (implementações básicas)
    private function getCategoryInsights($categoryId): array
    {
        if (!$categoryId) {
            return ['keywords' => [], 'name' => null, 'source' => 'none'];
        }

        $name = null;
        try {
            $category = $this->mlClient->get("/categories/{$categoryId}");
            $name = $category['name'] ?? null;
        } catch (Exception $e) {
            log_warning('Falha ao obter insights de categoria', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            $name = null;
        }

        $payload = $this->keywordSource->getKeywords($categoryId, $name ?? '');
        $keywords = [];
        foreach ($payload['keywords'] ?? [] as $keyword) {
            if (is_string($keyword)) {
                $keywords[] = $keyword;
                continue;
            }
            if (is_array($keyword)) {
                $value = $keyword['keyword'] ?? $keyword['value'] ?? $keyword['term'] ?? null;
                if (is_string($value) && $value !== '') {
                    $keywords[] = $value;
                }
            }
        }

        return [
            'keywords' => array_values(array_unique($keywords)),
            'name' => $name,
            'source' => $payload['source'] ?? 'unknown'
        ];
    }

    private function getBrandPositioning($brand): array
    {
        if (!$brand) {
            return ['position' => 'unknown', 'count' => 0, 'avg_price' => null];
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) as total, AVG(price) as avg_price FROM items WHERE title LIKE :brand");
        $stmt->execute([':brand' => '%' . $brand . '%']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $total = (int)($row['total'] ?? 0);
        $avgPrice = $row['avg_price'] ?? null;

        $position = 'low';
        if ($total >= 50) {
            $position = 'high';
        } elseif ($total >= 15) {
            $position = 'medium';
        }

        return [
            'position' => $position,
            'count' => $total,
            'avg_price' => $avgPrice ? round((float)$avgPrice, 2) : null
        ];
    }

    private function analyzePricePositioning($price): array
    {
        $price = (float)$price;
        if ($price <= 0) {
            return ['segment' => 'unknown', 'reference_avg' => null];
        }

        $stmt = $this->db->query("SELECT AVG(price) as avg_price FROM items");
        $avgRow = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : null;
        $avgPrice = (float)($avgRow['avg_price'] ?? 0);

        if ($avgPrice <= 0) {
            $segment = $price > 500 ? 'premium' : 'standard';
            return ['segment' => $segment, 'reference_avg' => null];
        }

        if ($price >= $avgPrice * 1.2) {
            $segment = 'premium';
        } elseif ($price <= $avgPrice * 0.8) {
            $segment = 'budget';
        } else {
            $segment = 'standard';
        }

        return ['segment' => $segment, 'reference_avg' => round($avgPrice, 2)];
    }

    private function analyzeAttributes($attributes): array
    {
        $count = is_array($attributes) ? count($attributes) : 0;
        $filled = 0;
        $technical = 0;
        foreach ($attributes as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            $value = $attr['value_name'] ?? $attr['value'] ?? null;
            if (is_string($value) && $value !== '') {
                $filled++;
            }
            $type = $attr['value_type'] ?? '';
            if (in_array($type, ['number', 'number_unit', 'boolean'], true)) {
                $technical++;
            }
        }

        return [
            'count' => $count,
            'filled' => $filled,
            'missing' => max(0, $count - $filled),
            'technical_count' => $technical
        ];
    }

    private function getCompetitiveInsights($product): array
    {
        $title = $product['title'] ?? '';
        $category = $product['category_id'] ?? null;
        if ($title === '' || !$category) {
            return ['competition_level' => 'unknown', 'total' => 0, 'avg_price' => null];
        }

        $config = \App\Core\Config::getInstance()->all();
        $siteId = $config['mercadolivre']['site_id'] ?? 'MLB';
        $response = $this->mlClient->get("/sites/{$siteId}/search", [
            'q' => $title,
            'category' => $category,
            'limit' => 20
        ], true);
        $payload = (isset($response['body']) && is_array($response['body'])) ? $response['body'] : $response;

        if (isset($payload['error'])) {
            return ['competition_level' => 'unknown', 'total' => 0, 'avg_price' => null];
        }

        $total = (int)($payload['paging']['total'] ?? 0);
        $prices = [];
        foreach ($payload['results'] ?? [] as $item) {
            if (isset($item['price'])) {
                $prices[] = (float)$item['price'];
            }
        }
        $avg = null;
        if (!empty($prices)) {
            $avg = array_sum($prices) / count($prices);
        }

        if ($total > 1000) {
            $level = 'high';
        } elseif ($total > 200) {
            $level = 'medium';
        } else {
            $level = 'low';
        }

        return [
            'competition_level' => $level,
            'total' => $total,
            'avg_price' => $avg ? round($avg, 2) : null
        ];
    }

    private function findSEOOpportunities($product): array
    {
        $opportunities = [];
        $title = $product['title'] ?? '';
        $description = $product['description'] ?? '';
        $brand = $product['brand'] ?? '';
        $attributes = $product['attributes'] ?? [];

        if (mb_strlen($title) < 40) {
            $opportunities[] = 'Título com baixa densidade de informações';
        }
        if ($brand && stripos($title, $brand) === false) {
            $opportunities[] = 'Marca ausente no título';
        }
        if (mb_strlen($description) < 200) {
            $opportunities[] = 'Descrição curta para SEO';
        }
        if (is_array($attributes) && count($attributes) < 5) {
            $opportunities[] = 'Poucos atributos preenchidos';
        }

        return $opportunities;
    }
    private function extractImportantWords($text): array
    {
        $text = mb_strtolower((string)$text);
        $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text));
        $stopwords = ['de', 'da', 'do', 'para', 'com', 'sem', 'e', 'em', 'a', 'o', 'as', 'os', 'um', 'uma', 'por', 'na', 'no', 'dos', 'das'];
        $filtered = [];
        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '' || in_array($word, $stopwords, true)) {
                continue;
            }
            $filtered[] = $word;
        }
        $unique = array_values(array_unique($filtered));
        usort($unique, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        return array_slice($unique, 0, 8);
    }

    private function validateMLTitle($title, $product): array
    {
        $title = trim(preg_replace('/\s+/', ' ', (string)$title));
        $adjustments = [];
        $brand = $product['brand'] ?? '';
        if ($brand && stripos($title, $brand) === false) {
            $candidate = trim($brand . ' ' . $title);
            if (mb_strlen($candidate) <= 60) {
                $title = $candidate;
                $adjustments[] = 'brand_added';
            }
        }

        if (mb_strlen($title) > 60) {
            $title = mb_substr($title, 0, 60);
            $adjustments[] = 'truncated';
        }

        return ['title' => $title, 'adjustments' => $adjustments];
    }

    private function extractFeatures($product, $analysis): array
    {
        $features = [];
        foreach ($product['attributes'] ?? [] as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            $name = $attr['name'] ?? $attr['id'] ?? null;
            $value = $attr['value_name'] ?? $attr['value'] ?? null;
            if (is_string($name) && $name !== '' && is_string($value) && $value !== '') {
                $features[] = "{$name}: {$value}";
            }
        }
        return array_slice($features, 0, 8);
    }

    private function generateBulletsTemplate($product, $params): array
    {
        $features = $this->extractFeatures($product, []);
        if (empty($features)) {
            return [];
        }
        return array_slice($features, 0, $params['count'] ?? 5);
    }

    private function generateDefaultTemplate($product, $params): string
    {
        $title = $product['title'] ?? '';
        $brand = $product['brand'] ?? '';
        $features = $this->extractFeatures($product, []);
        $lines = array_filter([$brand ? "{$brand} {$title}" : $title]);
        foreach ($features as $feature) {
            $lines[] = $feature;
        }
        return trim(implode("\n", $lines));
    }

    private function optimizeForSEO($content, $product): string
    {
        return trim($content);
    }

    private function calculateKeywordDensity($content): float
    {
        $words = preg_split('/\s+/', mb_strtolower((string)$content));
        $words = array_filter($words);
        $total = count($words);
        if ($total === 0) {
            return 0.0;
        }
        $important = $this->extractImportantWords($content);
        if (empty($important)) {
            return 0.0;
        }
        $count = 0;
        foreach ($words as $word) {
            if (in_array($word, $important, true)) {
                $count++;
            }
        }
        return $count / $total;
    }

    private function calculateReadabilityScore($content): float
    {
        $sentences = preg_split('/[.!?]+/', (string)$content);
        $sentences = array_filter(array_map('trim', $sentences));
        $words = preg_split('/\s+/', trim((string)$content));
        $wordCount = count(array_filter($words));
        $sentenceCount = max(1, count($sentences));
        $avgWords = $wordCount / $sentenceCount;
        $score = 100 - min(100, $avgWords * 1.5);
        return max(0, $score);
    }

    private function calculateSEOScore($content): float
    {
        $length = mb_strlen((string)$content);
        $lengthScore = min(100, ($length / 600) * 100);
        $densityScore = min(100, $this->calculateKeywordDensity($content) * 300);
        $readabilityScore = $this->calculateReadabilityScore($content);
        return round(($lengthScore * 0.4) + ($densityScore * 0.3) + ($readabilityScore * 0.3), 2);
    }

    private function generateSuggestions($score, $content): array
    {
        $suggestions = [];
        $length = mb_strlen((string)$content);
        if ($length < 400) {
            $suggestions[] = 'Expandir descrição com mais benefícios e aplicações';
        }
        $density = $this->calculateKeywordDensity($content);
        if ($density < 0.02) {
            $suggestions[] = 'Reforçar palavras-chave principais';
        }
        return $suggestions;
    }

    private function getDefaultOptions(): array
    {
        return ['style' => 'professional', 'length' => 'medium'];
    }
}
