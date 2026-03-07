<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Services\MercadoLivreClient;
use App\Services\AI\SEO\KeywordKiller;
use App\Services\SEO\SEOOptimizerService;

/**
 * 📝 SEO Description Generator - Geração de Descrições Persuasivas
 * 
 * Cria descrições otimizadas para SEO e conversão:
 * - Keywords de alta conversão integradas naturalmente
 * - Estrutura persuasiva AIDA (Attention, Interest, Desire, Action)
 * - Bullet points para features técnicas
 * - Resposta a dúvidas comuns dos compradores
 * - Call-to-action estratégicos
 * 
 * @author SEO Development Team
 * @version 1.0.0
 */
class SEODescriptionGenerator
{
    private ?int $accountId;
    private ?MercadoLivreClient $mlClient;
    private KeywordKiller $keywordKiller;
    private SEOOptimizerService $seoOptimizer;
    
    // Templates de estrutura persuasiva
    private const TEMPLATES = [
        'benefits_first' => [
            'opening' => 'Descubra como {product} pode transformar seu dia a dia',
            'benefits' => 'Experimente os benefícios exclusivos que só {brand} oferece',
            'features' => 'Especificações Técnicas:',
            'social_proof' => 'Mais de {sold_count} clientes já confiam neste produto',
            'closing' => 'Aproveite esta oportunidade única. Garanta já o seu!'
        ],
        'problem_solution' => [
            'opening' => 'Cansado de {problem}? {product} é a solução definitiva',
            'solution' => 'Com tecnologia inovadora, este produto oferece',
            'features' => 'Características Principais:',
            'guarantee' => 'Com garantia {warranty} e suporte especializado',
            'closing' => 'Não perca mais tempo. Adquira agora e transforme sua experiência'
        ],
        'feature_focused' => [
            'opening' => 'Apresentamos {product} - o melhor em sua categoria',
            'highlight' => 'Diferencial exclusivo: {main_feature}',
            'features' => 'Especificações Completas:',
            'quality' => 'Qualidade {brand} reconhecida mundialmente',
            'closing' => 'Compare, confirme e surpreenda-se. Peça já o seu!'
        ]
    ];
    
    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->mlClient = $accountId ? new MercadoLivreClient($accountId) : null;
        $this->keywordKiller = new KeywordKiller($accountId);
        $this->seoOptimizer = new SEOOptimizerService();
    }
    
    /**
     * 🚀 Gera descrição completa otimizada
     */
    public function generateDescription(array $product): array
    {
        $result = [
            'success' => true,
            'product' => $product['title'] ?? '',
            'description' => '',
            'short_description' => '',
            'bullet_points' => [],
            'metadata' => []
        ];
        
        try {
            // 1. Pesquisar keywords do produto
            $keywords = $this->keywordKiller->researchKeywords($product);
            
            // 2. Analisar concorrentes para diferencial
            $competitorAnalysis = $this->analyzeCompetitorDescriptions($product);
            
            // 3. Determinar melhor template baseado no produto
            $template = $this->selectBestTemplate($product, $keywords);
            
            // 4. Gerar descrição completa
            $fullDescription = $this->buildFullDescription($product, $keywords, $template, $competitorAnalysis);
            
            // 5. Criar versão curta para mobile/previsualização
            $shortDescription = $this->buildShortDescription($product, $keywords);
            
            // 6. Gerar bullet points otimizados
            $bulletPoints = $this->generateBulletPoints($product, $keywords);
            
            $result['description'] = $fullDescription;
            $result['short_description'] = $shortDescription;
            $result['bullet_points'] = $bulletPoints;
            $result['metadata'] = [
                'word_count' => str_word_count($fullDescription),
                'keywords_integrated' => $this->countIntegratedKeywords($fullDescription, $keywords),
                'template_used' => $template,
                'seo_score' => $this->calculateSEOScore($fullDescription, $keywords),
                'readability_score' => $this->calculateReadability($fullDescription)
            ];
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * 📊 Constrói descrição completa usando template
     */
    private function buildFullDescription(array $product, array $keywords, string $template, array $competitorAnalysis): string
    {
        $templateData = self::TEMPLATES[$template] ?? self::TEMPLATES['benefits_first'];
        $brand = $product['brand'] ?? 'esta marca';
        
        $description = '';
        
        // Opening - Hook inicial
        $opening = $templateData['opening'] ?? '';
        $opening = str_replace(['{product}', '{brand}'], [$product['title'] ?? 'este produto', $brand], $opening);
        
        if ($template === 'problem_solution') {
            $problem = $this->identifyCustomerProblem($product, $keywords);
            $opening = str_replace('{problem}', $problem, $opening);
        }
        
        $description .= $opening . "\n\n";
        
        // Benefits section
        $benefits = $this->generateBenefitsSection($product, $keywords);
        $description .= $benefits . "\n\n";
        
        // Features section with keywords
        $featuresHeader = $templateData['features'] ?? 'Características Principais:';
        $description .= $featuresHeader . "\n";
        
        $features = $this->generateFeaturesSection($product, $keywords);
        foreach ($features as $feature) {
            $description .= "• {$feature}\n";
        }
        
        $description .= "\n";
        
        // Differentiators
        $differentiators = $this->generateUniqueSellingPoints($product, $competitorAnalysis);
        if (!empty($differentiators)) {
            $description .= "Diferenciais Exclusivos:\n";
            foreach ($differentiators as $diff) {
                $description .= "• {$diff}\n";
            }
            $description .= "\n";
        }
        
        // Social proof or quality
        if (isset($templateData['social_proof'])) {
            $social = $templateData['social_proof'];
            $soldCount = $product['sold_quantity'] ?? 'milhares de';
            $social = str_replace('{sold_count}', $soldCount, $social);
            $description .= $social . "\n\n";
        }
        
        if (isset($templateData['quality'])) {
            $quality = $templateData['quality'];
            $quality = str_replace('{brand}', $brand, $quality);
            $description .= $quality . "\n\n";
        }
        
        // Guarantee info
        $warranty = $this->extractAttribute($product, 'WARRANTY');
        if ($warranty && isset($templateData['guarantee'])) {
            $guarantee = $templateData['guarantee'];
            $guarantee = str_replace('{warranty}', $warranty, $guarantee);
            $description .= $guarantee . "\n\n";
        }
        
        // Closing/Call-to-action
        $closing = $templateData['closing'] ?? 'Adquira agora mesmo!';
        $description .= $closing;
        
        return $this->cleanDescription($description);
    }
    
    /**
     * 🎯 Gera seção de benefícios com keywords
     */
    private function generateBenefitsSection(array $product, array $keywords): string
    {
        $benefits = [];
        $primaryKeywords = $keywords['keywords']['primary'] ?? [];
        
        // Mapear keywords para benefícios
        $benefitMappings = [
            'praticidade' => 'Praticidade no dia a dia',
            'economia' => 'Economia de tempo e dinheiro',
            'qualidade' => 'Qualidade superior e durabilidade',
            'facilidade' => 'Fácil de usar e instalar',
            'performance' => 'Performance otimizada',
            'conforto' => 'Máximo conforto de uso',
            'segurança' => 'Segurança garantida',
            'moderno' => 'Design moderno e elegante'
        ];
        
        foreach ($primaryKeywords as $keyword) {
            $keyword = mb_strtolower($keyword);
            foreach ($benefitMappings as $key => $benefit) {
                if (mb_strpos($keyword, $key) !== false && !in_array($benefit, $benefits)) {
                    $benefits[] = $benefit;
                    break;
                }
            }
        }
        
        // Benefícios baseados no tipo de produto
        $category = $product['category_id'] ?? '';
        if ($category) {
            $categoryBenefits = $this->getCategoryBenefits($category);
            $benefits = array_merge($benefits, $categoryBenefits);
        }
        
        // Adicionar benefícios dos atributos
        $attributeBenefits = $this->extractBenefitsFromAttributes($product);
        $benefits = array_merge($benefits, $attributeBenefits);
        
        // Remover duplicados e limitar
        $benefits = array_unique($benefits);
        $benefits = array_slice($benefits, 0, 4);
        
        if (!empty($benefits)) {
            return implode('. ', $benefits) . '.';
        }
        
        return 'Experimente a qualidade e eficiência que tornam este produto indispensável para você.';
    }
    
    /**
     * 🔧 Gera seção de features com keywords técnicas
     */
    private function generateFeaturesSection(array $product, array $keywords): array
    {
        $features = [];
        $secondaryKeywords = $keywords['keywords']['secondary'] ?? [];
        
        // Features baseadas nos atributos do produto
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = $attr['value_name'] ?? '';
            if ($value && strlen($value) <= 60) {
                $features[] = $value;
            }
        }
        
        // Features baseadas em keywords secundárias
        foreach ($secondaryKeywords as $keyword) {
            if (mb_strlen($keyword) >= 4 && mb_strlen($keyword) <= 30) {
                $features[] = ucfirst($keyword);
            }
        }
        
        // Features padrão baseadas no tipo de produto
        $category = $product['category_id'] ?? '';
        if ($category) {
            $categoryFeatures = $this->getCategoryFeatures($category);
            $features = array_merge($features, $categoryFeatures);
        }
        
        // Adicionar features técnicas com números
        $this->addNumericFeatures($product, $features);
        
        // Limpar e limitar
        $features = array_filter($features);
        $features = array_unique($features);
        $features = array_slice($features, 0, 6);
        
        return array_values($features);
    }
    
    /**
     * 📋 Gera bullet points otimizados
     */
    private function generateBulletPoints(array $product, array $keywords): array
    {
        $bullets = [];
        $allKeywords = array_merge(
            $keywords['keywords']['primary'] ?? [],
            $keywords['keywords']['secondary'] ?? [],
            $keywords['keywords']['long_tail'] ?? []
        );
        
        // Bullet 1: Principais benefícios
        $mainBenefit = $this->generateBenefitsSection($product, $keywords);
        $bullets[] = mb_substr($mainBenefit, 0, 80) . (mb_strlen($mainBenefit) > 80 ? '...' : '');
        
        // Bullet 2: Atributo principal
        $mainAttribute = $this->getMainAttribute($product);
        if ($mainAttribute) {
            $bullets[] = $mainAttribute;
        }
        
        // Bullet 3: Qualidade/Durabilidade
        $bullets[] = 'Alta qualidade e durabilidade garantidas pela marca ' . ($product['brand'] ?? 'reconhecida');
        
        // Bullet 4: Especificação técnica
        $techSpec = $this->getTechnicalSpecification($product);
        if ($techSpec) {
            $bullets[] = $techSpec;
        }
        
        // Bullet 5: Garantia/Suporte
        $warranty = $this->extractAttribute($product, 'WARRANTY');
        if ($warranty) {
            $bullets[] = "Garantia de {$warranty} contra defeitos de fabricação";
        } else {
            $bullets[] = 'Suporte técnico e atendimento pós-venda';
        }
        
        // Bullet 6: Call-to-action suave
        $bullets[] = 'Ideal para presente ou uso pessoal - compre com confiança';
        
        // Integrar keywords naturais
        $bullets = $this->integrateKeywordsInBullets($bullets, $allKeywords);
        
        return array_slice($bullets, 0, 7);
    }
    
    /**
     * 📱 Gera descrição curta para mobile
     */
    private function buildShortDescription(array $product, array $keywords): string
    {
        $brand = $product['brand'] ?? '';
        $title = $product['title'] ?? '';
        $mainKeyword = ($keywords['keywords']['primary'][0] ?? $title);
        
        $short = "{$title} é a escolha perfeita quem busca qualidade e economia. ";
        
        if ($brand) {
            $short .= "Com a tradição e confiança da marca {$brand}, ";
        }
        
        $short .= "este produto oferece performance superior e durabilidade. ";
        
        $mainFeature = $this->getMainAttribute($product);
        if ($mainFeature) {
            $short .= "Destaque para {$mainFeature}. ";
        }
        
        $short .= "Aproveite as melhores condições e compre agora!";
        
        // Limitar a ~100 palavras
        $words = explode(' ', $short);
        if (count($words) > 100) {
            $short = implode(' ', array_slice($words, 0, 97)) . '...';
        }
        
        return $short;
    }
    
    /**
     * 🎨 Seleciona melhor template baseado no produto
     */
    private function selectBestTemplate(array $product, array $keywords): string
    {
        $category = $product['category_id'] ?? '';
        $price = (float)($product['price'] ?? 0);
        $hasWarranty = $this->extractAttribute($product, 'WARRANTY');
        
        // Produtos caros → feature_focused
        if ($price > 1000) {
            return 'feature_focused';
        }
        
        // Produtos com garantia → problem_solution
        if ($hasWarranty) {
            return 'problem_solution';
        }
        
        // Categorias específicas
        $problemCategories = ['MLB1648', 'MLB1074']; // Eletrônicos, Eletrodomésticos
        if (in_array($category, $problemCategories)) {
            return 'problem_solution';
        }
        
        // Default
        return 'benefits_first';
    }
    
    /**
     * 🔍 Analisa descrições de concorrentes
     */
    private function analyzeCompetitorDescriptions(array $product): array
    {
        if (!$this->mlClient) {
            return ['common_points' => [], 'gaps' => []];
        }
        
        try {
            $searchTerm = mb_substr($product['title'] ?? '', 0, 30);
            $results = $this->mlClient->get('/sites/MLB/search', [
                'q' => $searchTerm,
                'limit' => 3,
                'sort' => 'sold_quantity_desc'
            ]);
            
            $commonPoints = [];
            $allWords = [];
            
            foreach ($results['results'] ?? [] as $item) {
                // Buscar descrição do item
                try {
                    $desc = $this->mlClient->get("/items/{$item['id']}/description");
                    $text = $desc['plain_text'] ?? $desc['text'] ?? '';
                    
                    // Extrair palavras comuns
                    $words = preg_split('/[\s\.\,\;\:]+/', mb_strtolower($text));
                    foreach ($words as $word) {
                        $word = trim($word);
                        if (mb_strlen($word) >= 4 && !$this->isStopword($word)) {
                            $allWords[$word] = ($allWords[$word] ?? 0) + 1;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // Palavras mais comuns em concorrentes
            arsort($allWords);
            $commonPoints = array_slice(array_keys($allWords), 0, 10);
            
            return [
                'common_points' => $commonPoints,
                'gaps' => $this->identifyDescriptionGaps($product, $commonPoints)
            ];
            
        } catch (\Exception $e) {
            return ['common_points' => [], 'gaps' => []];
        }
    }
    
    /**
     * 📈 Métodos de análise e validação
     */
    private function calculateSEOScore(string $description, array $keywords): int
    {
        $score = 100;
        $desc = mb_strtolower($description);
        
        // Verificar keywords principais
        $primaryKeywords = $keywords['keywords']['primary'] ?? [];
        foreach ($primaryKeywords as $keyword) {
            if (mb_stripos($desc, mb_strtolower($keyword)) === false) {
                $score -= 10;
            }
        }
        
        // Verificar comprimento
        $wordCount = str_word_count($description);
        if ($wordCount < 50) {
            $score -= 20;
        } elseif ($wordCount > 500) {
            $score -= 10;
        }
        
        // Verificar estrutura
        if (mb_strpos($description, '•') === false && mb_strpos($description, '-') === false) {
            $score -= 15; // Sem bullet points
        }
        
        return max(0, $score);
    }
    
    private function calculateReadability(string $text): int
    {
        $sentences = preg_split('/[.!?]+/', $text);
        $sentenceCount = count(array_filter($sentences));
        $wordCount = str_word_count($text);
        
        if ($sentenceCount === 0) return 0;
        
        $avgWordsPerSentence = $wordCount / $sentenceCount;
        
        // Score baseado no comprimento médio das frases
        if ($avgWordsPerSentence <= 15) return 90;
        if ($avgWordsPerSentence <= 20) return 80;
        if ($avgWordsPerSentence <= 25) return 70;
        return 60;
    }
    
    private function countIntegratedKeywords(string $description, array $keywords): int
    {
        $count = 0;
        $desc = mb_strtolower($description);
        $allKeywords = array_merge(
            $keywords['keywords']['primary'] ?? [],
            $keywords['keywords']['secondary'] ?? []
        );
        
        foreach ($allKeywords as $keyword) {
            if (mb_stripos($desc, mb_strtolower($keyword)) !== false) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Helper methods auxiliares
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
    
    private function isStopword(string $word): bool
    {
        $stopwords = ['de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'por', 'que', 'uma', 'um', 'seu', 'sua'];
        return in_array($word, $stopwords);
    }
    
    private function cleanDescription(string $description): string
    {
        // Remove espaços extras e linhas em branco
        $description = preg_replace('/\s+/', ' ', $description);
        $description = preg_replace('/\n\s*\n/', "\n\n", $description);
        
        return trim($description);
    }
    
    private function identifyCustomerProblem(array $product, array $keywords): string
    {
        $category = $product['category_id'] ?? '';
        
        // Problemas comuns por categoria
        $problems = [
            'MLB1648' => 'aparelhos que falham e não duram',
            'MLB1074' => 'eletrodomésticos que consomem muita energia',
            'MLB1743' => 'roupas que amassam facilmente',
            'MLB1051' => 'calçados que não são confortáveis'
        ];
        
        return $problems[$category] ?? 'produtos de baixa qualidade';
    }
    
    private function getCategoryBenefits(string $category): array
    {
        $benefits = [
            'MLB1648' => ['Conexão rápida e estável', 'Bateria de longa duração', 'Design moderno e compacto'],
            'MLB1074' => ['Economia de energia', 'Instalação fácil e rápida', 'Operação silenciosa'],
            'MLB1743' => ['Conforto superior', 'Tecido resistente', 'Estilo versátil'],
        ];
        
        return $benefits[$category] ?? [];
    }
    
    private function getCategoryFeatures(string $category): array
    {
        $features = [
            'MLB1648' => ['Tela Full HD', 'Processador rápido', 'Memória expandível'],
            'MLB1074' => ['Alta eficiência energética', 'Multiple funções', 'Fácil limpeza'],
            'MLB1743' => ['Material premium', 'Cores variadas', 'Lavagem fácil'],
        ];
        
        return $features[$category] ?? [];
    }
    
    private function extractBenefitsFromAttributes(array $product): array
    {
        $benefits = [];
        
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = $attr['value_name'] ?? '';
            $id = $attr['id'] ?? '';
            
            if ($id === 'WARRANTY' && $value) {
                $benefits[] = "Segurança com garantia de {$value}";
            } elseif ($id === 'VOLTAGE' && $value) {
                $benefits[] = "Compatibilidade universal com voltagem {$value}";
            } elseif ($id === 'MATERIAL' && $value) {
                $benefits[] = "Qualidade premium em {$value}";
            }
        }
        
        return $benefits;
    }
    
    private function addNumericFeatures(array $product, array &$features): void
    {
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = $attr['value_name'] ?? '';
            if (preg_match('/\d+/', $value) && !in_array($value, $features)) {
                $features[] = $value;
            }
        }
    }
    
    private function getMainAttribute(array $product): ?string
    {
        $priorityAttrs = ['MODEL', 'COLOR', 'SIZE', 'MATERIAL'];
        
        foreach ($priorityAttrs as $attrId) {
            $value = $this->extractAttribute($product, $attrId);
            if ($value && strlen($value) <= 40) {
                return $value;
            }
        }
        
        return null;
    }
    
    private function getTechnicalSpecification(array $product): ?string
    {
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = $attr['value_name'] ?? '';
            $name = $attr['name'] ?? '';
            
            if (preg_match('/\d+/', $value) && strlen($value) <= 50) {
                return "{$name}: {$value}";
            }
        }
        
        return null;
    }
    
    private function integrateKeywordsInBullets(array $bullets, array $keywords): array
    {
        foreach ($bullets as $i => &$bullet) {
            $lowerBullet = mb_strtolower($bullet);
            
            foreach (array_slice($keywords, 0, 3) as $keyword) {
                if (mb_stripos($lowerBullet, mb_strtolower($keyword)) === false && strlen($bullet) < 70) {
                    $bullet .= " com {$keyword}";
                    break;
                }
            }
        }
        
        return $bullets;
    }
    
    private function identifyDescriptionGaps(array $product, array $competitorWords): array
    {
        $myFeatures = [];
        
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = mb_strtolower($attr['value_name'] ?? '');
            if ($value && strlen($value) >= 4) {
                $myFeatures[] = $value;
            }
        }
        
        $gaps = [];
        foreach ($competitorWords as $word) {
            if (!in_array($word, $myFeatures)) {
                $gaps[] = $word;
            }
        }
        
        return array_slice($gaps, 0, 5);
    }
    
    private function generateUniqueSellingPoints(array $product, array $competitorAnalysis): array
    {
        $points = [];
        $gaps = $competitorAnalysis['gaps'] ?? [];
        
        // Usar gaps como diferenciais
        foreach ($gaps as $gap) {
            if (strlen($gap) <= 40) {
                $points[] = ucfirst($gap);
            }
        }
        
        // Adicionar diferenciais baseados nos atributos únicos
        foreach ($product['attributes'] ?? [] as $attr) {
            $value = $attr['value_name'] ?? '';
            if ($value && !preg_match('/^[A-Z0-9]+$/', $value) && strlen($value) <= 50) {
                $points[] = $value;
            }
        }
        
        return array_unique(array_slice($points, 0, 3));
    }
}