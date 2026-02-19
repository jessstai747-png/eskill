<?php

namespace App\Services;

use App\Services\MercadoLivreClient;
use App\Services\CategoryService;
use App\Services\CacheService;
use App\Services\Quality\QualityScoreService;
use App\Services\Quality\HealthCheckService;

/**
 * Serviço de Análise SEO para Anúncios do Mercado Livre
 * 
 * Analisa e pontua anúncios baseado nas melhores práticas de SEO do ML:
 * - Título otimizado (60 caracteres, keywords relevantes)
 * - Descrição completa e estruturada
 * - Atributos obrigatórios e recomendados preenchidos
 * - Imagens de qualidade
 * - Ficha técnica completa
 * 
 * INTEGRADO COM:
 * - QualityScoreService (pontuação oficial de qualidade)
 * - HealthCheckService (verificação de saúde do anúncio)
 */
class SeoAnalyzerService
{
    private MercadoLivreClient $client;
    private CategoryService $categoryService;
    private CacheService $cache;
    private ?QualityScoreService $qualityScore;
    private ?HealthCheckService $healthCheck;

    // Pesos para cálculo do score SEO
    private const WEIGHTS = [
        'title' => 25,
        'description' => 20,
        'attributes' => 20,
        'images' => 15,
        'price' => 10,
        'shipping' => 10,
    ];

    // Configurações de título
    private const TITLE_MIN_LENGTH = 20;
    private const TITLE_MAX_LENGTH = 60;
    private const TITLE_OPTIMAL_LENGTH = 55;

    // Palavras proibidas/ruins no título
    private const TITLE_FORBIDDEN_WORDS = [
        'promoção',
        'oferta',
        'desconto',
        'barato',
        'liquidação',
        'imperdível',
        'oportunidade',
        'últimas unidades',
        'aproveite',
        'compre já',
        'melhor preço',
        'menor preço',
        'frete grátis',
        '!!!',
        '???',
        '***',
        'grátis',
        'brinde',
        'queima',
        'black friday'
    ];

    // Palavras de alto impacto SEO
    private const HIGH_IMPACT_WORDS = [
        'original',
        'novo',
        'lacrado',
        'garantia',
        'nota fiscal',
        'pronta entrega',
        'envio imediato',
        'nacional',
        'importado'
    ];

    public function __construct(?int $accountId = null)
    {
        $this->client = new MercadoLivreClient($accountId);
        $this->categoryService = new CategoryService($accountId);
        $this->cache = new CacheService();
        
        // Integração com Quality Services (opcional)
        try {
            $this->qualityScore = new QualityScoreService($accountId);
            $this->healthCheck = new HealthCheckService($accountId);
        } catch (\Exception $e) {
            // Se Quality services não estiverem disponíveis, continua sem eles
            $this->qualityScore = null;
            $this->healthCheck = null;
        }
    }

    /**
     * Análise SEO completa de um anúncio
     * Inclui integração com Quality Score e Health Check
     */
    public function analyzeItem(string $itemId, bool $includeQualityCheck = true): array
    {
        $item = $this->client->get("/items/{$itemId}");

        if (isset($item['error'])) {
            return $item;
        }

        $categoryAttributes = $this->categoryService->getCategoryAttributes($item['category_id'] ?? '');
        $analysis = $this->analyzeItemData($item, $categoryAttributes);

        // Expor metadados básicos do item para evitar chamadas duplicadas em consumidores (ex.: relatórios/diagnósticos)
        $analysis['item'] = [
            'id' => $item['id'] ?? $itemId,
            'title' => $item['title'] ?? null,
            'category_id' => $item['category_id'] ?? null,
            'status' => $item['status'] ?? null,
            'price' => $item['price'] ?? null,
        ];

        // Integrar com Quality Services se disponível e solicitado
        if ($includeQualityCheck && $this->qualityScore && $this->healthCheck) {
            try {
                $qualityResult = $this->qualityScore->calculateQualityScore($itemId);
                $healthResult = $this->healthCheck->checkItemHealth($itemId);

                if ($qualityResult['success'] && $healthResult['success']) {
                    $analysis['quality_check'] = [
                        'quality_score' => $qualityResult['quality_score']['total'],
                        'quality_rating' => $qualityResult['quality_score']['rating'],
                        'health_status' => $healthResult['health']['status'],
                        'health_score' => $healthResult['health']['score'],
                        'critical_issues' => $healthResult['summary']['critical_issues'] ?? 0,
                        'total_issues' => $healthResult['summary']['total_issues'] ?? 0,
                    ];
                    
                    // Adicionar link para relatório completo
                    $analysis['quality_check']['full_report_url'] = "/api/quality/report/{$itemId}";
                }
            } catch (\Exception $e) {
                // Falha silenciosa - não quebra a análise SEO
                log_warning('Falha na integração de quality check', [
                    'service' => 'SeoAnalyzerService',
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $analysis;
    }

    /**
     * Análise SEO de dados de anúncio (antes de publicar)
     */
    public function analyzeItemData(array $item, array $categoryAttributes = []): array
    {
        $analysis = [
            'score' => 0,
            'max_score' => 100,
            'grade' => 'F',
            'sections' => [],
            'suggestions' => [],
            'critical_issues' => [],
            'warnings' => [],
        ];

        // Analisar cada seção
        $titleAnalysis = $this->analyzeTitle($item['title'] ?? '', $item);
        $descriptionAnalysis = $this->analyzeDescription($item['description'] ?? '', $item);
        $attributesAnalysis = $this->analyzeAttributes($item['attributes'] ?? [], $categoryAttributes, $item['category_id'] ?? '');
        $imagesAnalysis = $this->analyzeImages($item['pictures'] ?? []);
        $priceAnalysis = $this->analyzePrice($item);
        $shippingAnalysis = $this->analyzeShipping($item);

        // Calcular scores
        $analysis['sections'] = [
            'title' => $titleAnalysis,
            'description' => $descriptionAnalysis,
            'attributes' => $attributesAnalysis,
            'images' => $imagesAnalysis,
            'price' => $priceAnalysis,
            'shipping' => $shippingAnalysis,
        ];

        // Calcular score total ponderado
        $totalScore = 0;
        foreach ($analysis['sections'] as $section => $data) {
            $weight = self::WEIGHTS[$section] ?? 0;
            $sectionScore = ($data['score'] / $data['max_score']) * $weight;
            $totalScore += $sectionScore;

            // Coletar sugestões e problemas
            $analysis['suggestions'] = array_merge($analysis['suggestions'], $data['suggestions'] ?? []);

            if (isset($data['critical']) && $data['critical']) {
                $analysis['critical_issues'] = array_merge($analysis['critical_issues'], $data['issues'] ?? []);
            } else {
                $analysis['warnings'] = array_merge($analysis['warnings'], $data['issues'] ?? []);
            }
        }

        $analysis['score'] = round($totalScore);
        $analysis['grade'] = $this->calculateGrade($analysis['score']);

        // Adicionar recomendações priorizadas
        $analysis['priority_actions'] = $this->getPriorityActions($analysis);

        return $analysis;
    }

    /**
     * Análise do título
     */
    private function analyzeTitle(string $title, array $item): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        $title = trim($title);
        $length = mb_strlen($title);

        // Verificar comprimento
        if ($length === 0) {
            $result['issues'][] = 'Título vazio - CRÍTICO';
            $result['critical'] = true;
            return $result;
        }

        $result['details']['length'] = $length;
        $result['details']['optimal_length'] = self::TITLE_OPTIMAL_LENGTH;

        // Score por comprimento (0-30 pontos)
        if ($length >= self::TITLE_MIN_LENGTH && $length <= self::TITLE_MAX_LENGTH) {
            $lengthScore = 30;
            if ($length >= 45 && $length <= 58) {
                $lengthScore = 30; // Comprimento ideal
            } elseif ($length < 45) {
                $lengthScore = 20;
                $result['suggestions'][] = "Adicione mais palavras-chave ao título. Atual: {$length} caracteres, ideal: 45-58";
            }
        } else {
            if ($length < self::TITLE_MIN_LENGTH) {
                $lengthScore = 10;
                $result['issues'][] = "Título muito curto ({$length} caracteres). Mínimo recomendado: " . self::TITLE_MIN_LENGTH;
            } else {
                $lengthScore = 15;
                $result['issues'][] = "Título muito longo ({$length} caracteres). Máximo: " . self::TITLE_MAX_LENGTH;
            }
        }
        $result['score'] += $lengthScore;

        // Verificar palavras proibidas (0-20 pontos)
        $forbiddenFound = [];
        $titleLower = mb_strtolower($title);
        foreach (self::TITLE_FORBIDDEN_WORDS as $word) {
            if (mb_strpos($titleLower, mb_strtolower($word)) !== false) {
                $forbiddenFound[] = $word;
            }
        }

        if (empty($forbiddenFound)) {
            $result['score'] += 20;
        } else {
            $result['issues'][] = 'Palavras não recomendadas encontradas: ' . implode(', ', $forbiddenFound);
            $result['score'] += max(0, 20 - (count($forbiddenFound) * 5));
        }

        // Verificar palavras de alto impacto (0-20 pontos)
        $impactFound = [];
        foreach (self::HIGH_IMPACT_WORDS as $word) {
            if (mb_strpos($titleLower, mb_strtolower($word)) !== false) {
                $impactFound[] = $word;
            }
        }

        $impactScore = min(20, count($impactFound) * 5);
        $result['score'] += $impactScore;
        $result['details']['high_impact_words'] = $impactFound;

        if (empty($impactFound)) {
            $result['suggestions'][] = 'Considere adicionar palavras de impacto: Original, Novo, Lacrado, Garantia, Nota Fiscal';
        }

        // Verificar estrutura do título (0-15 pontos)
        // Formato ideal: [Marca] + [Modelo] + [Características principais]
        $hasNumbers = preg_match('/\d/', $title);
        $hasUppercase = preg_match('/[A-Z]/', $title);
        $structureScore = 0;

        if ($hasNumbers) $structureScore += 5; // Modelos geralmente têm números
        if ($hasUppercase) $structureScore += 5; // Marcas são capitalizadas
        if (preg_match('/\b(para|com|de)\b/i', $title)) $structureScore += 5; // Conectores descritivos

        $result['score'] += $structureScore;

        // Verificar repetição de palavras (0-15 pontos)
        $words = preg_split('/\s+/', $titleLower);
        $wordCount = array_count_values($words);
        $repetitions = array_filter($wordCount, fn($count) => $count > 1);

        if (empty($repetitions)) {
            $result['score'] += 15;
        } else {
            $result['issues'][] = 'Palavras repetidas no título: ' . implode(', ', array_keys($repetitions));
            $result['score'] += 5;
        }

        return $result;
    }

    /**
     * Análise da descrição
     */
    private function analyzeDescription($description, array $item): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        // Descrição pode vir em diferentes formatos
        $descText = '';
        if (is_array($description)) {
            $descText = $description['plain_text'] ?? '';
        } else {
            $descText = (string)$description;
        }

        $length = mb_strlen($descText);
        $result['details']['length'] = $length;

        // Verificar se tem descrição
        if ($length === 0) {
            $result['issues'][] = 'Descrição vazia - CRÍTICO para SEO';
            $result['critical'] = true;
            $result['suggestions'][] = 'Adicione uma descrição detalhada com pelo menos 500 caracteres';
            return $result;
        }

        // Score por comprimento (0-30 pontos)
        if ($length >= 1000) {
            $result['score'] += 30;
        } elseif ($length >= 500) {
            $result['score'] += 25;
        } elseif ($length >= 200) {
            $result['score'] += 15;
            $result['suggestions'][] = 'Expanda a descrição para pelo menos 500 caracteres';
        } else {
            $result['score'] += 5;
            $result['issues'][] = 'Descrição muito curta. Mínimo recomendado: 500 caracteres';
        }

        // Verificar estrutura (0-25 pontos)
        $hasLineBreaks = strpos($descText, "\n") !== false;
        $hasBulletPoints = preg_match('/[•\-\*]\s/', $descText);
        $hasSections = preg_match('/\b(características|especificações|conteúdo|inclui|garantia)\b/i', $descText);

        $structureScore = 0;
        if ($hasLineBreaks) $structureScore += 10;
        if ($hasBulletPoints) $structureScore += 10;
        if ($hasSections) $structureScore += 5;

        $result['score'] += $structureScore;

        if (!$hasLineBreaks) {
            $result['suggestions'][] = 'Use quebras de linha para organizar a descrição';
        }
        if (!$hasBulletPoints) {
            $result['suggestions'][] = 'Use bullet points (•) para listar características';
        }

        // Verificar palavras-chave relevantes (0-25 pontos)
        $descLower = mb_strtolower($descText);
        $keywordsFound = 0;
        $relevantKeywords = [
            'garantia',
            'original',
            'qualidade',
            'especificações',
            'características',
            'envio',
            'entrega',
            'novo',
            'lacrado',
            'nota fiscal'
        ];

        foreach ($relevantKeywords as $keyword) {
            if (mb_strpos($descLower, $keyword) !== false) {
                $keywordsFound++;
            }
        }

        $result['score'] += min(25, $keywordsFound * 5);
        $result['details']['keywords_found'] = $keywordsFound;

        // Verificar informações de contato/links (penalização)
        if (preg_match('/(whatsapp|whats|zap|telefone|email|@|\.com|\.br|instagram|facebook)/i', $descText)) {
            $result['issues'][] = 'Possível informação de contato detectada - proibido pelo ML';
            $result['score'] = max(0, $result['score'] - 20);
        }

        // Verificar HTML malformado ou tags
        if (preg_match('/<[^>]+>/', $descText)) {
            $result['issues'][] = 'Tags HTML detectadas na descrição';
            $result['score'] = max(0, $result['score'] - 10);
        }

        // Legibilidade (0-20 pontos)
        $sentences = preg_split('/[.!?]+/', $descText);
        $avgSentenceLength = count($sentences) > 0 ? $length / count($sentences) : $length;

        if ($avgSentenceLength <= 150) {
            $result['score'] += 20;
        } elseif ($avgSentenceLength <= 250) {
            $result['score'] += 10;
        } else {
            $result['suggestions'][] = 'Divida sentenças muito longas para melhor legibilidade';
        }

        return $result;
    }

    /**
     * Análise dos atributos
     */
    private function analyzeAttributes(array $attributes, array $categoryAttributes, string $categoryId): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        // Se não temos atributos da categoria, buscar
        if (empty($categoryAttributes) && !empty($categoryId)) {
            $categoryAttributes = $this->categoryService->getCategoryAttributes($categoryId);
            if (isset($categoryAttributes['error'])) {
                $categoryAttributes = [];
            }
        }

        // Identificar atributos obrigatórios e recomendados
        $requiredAttrs = [];
        $recommendedAttrs = [];

        foreach ($categoryAttributes as $attr) {
            $tags = $attr['tags'] ?? [];
            if (in_array('required', $tags)) {
                $requiredAttrs[$attr['id']] = $attr;
            } elseif (in_array('recommended', $tags) || in_array('catalog_required', $tags)) {
                $recommendedAttrs[$attr['id']] = $attr;
            }
        }

        // Mapear atributos preenchidos
        $filledAttrs = [];
        foreach ($attributes as $attr) {
            $filledAttrs[$attr['id']] = $attr;
        }

        $result['details']['total_category_attributes'] = count($categoryAttributes);
        $result['details']['filled_attributes'] = count($attributes);
        $result['details']['required_count'] = count($requiredAttrs);
        $result['details']['recommended_count'] = count($recommendedAttrs);

        // Verificar atributos obrigatórios (0-50 pontos)
        $requiredFilled = 0;
        $requiredMissing = [];

        foreach ($requiredAttrs as $attrId => $attr) {
            if (isset($filledAttrs[$attrId]) && !empty($filledAttrs[$attrId]['value_name'] ?? $filledAttrs[$attrId]['value_id'] ?? null)) {
                $requiredFilled++;
            } else {
                $requiredMissing[] = $attr['name'] ?? $attrId;
            }
        }

        if (count($requiredAttrs) > 0) {
            $requiredScore = ($requiredFilled / count($requiredAttrs)) * 50;
        } else {
            $requiredScore = 50; // Se não há obrigatórios, score cheio
        }

        $result['score'] += $requiredScore;

        if (!empty($requiredMissing)) {
            $result['issues'][] = 'Atributos obrigatórios faltando: ' . implode(', ', array_slice($requiredMissing, 0, 5));
            $result['critical'] = true;
        }

        // Verificar atributos recomendados (0-30 pontos)
        $recommendedFilled = 0;
        $recommendedMissing = [];

        foreach ($recommendedAttrs as $attrId => $attr) {
            if (isset($filledAttrs[$attrId]) && !empty($filledAttrs[$attrId]['value_name'] ?? $filledAttrs[$attrId]['value_id'] ?? null)) {
                $recommendedFilled++;
            } else {
                $recommendedMissing[] = $attr['name'] ?? $attrId;
            }
        }

        if (count($recommendedAttrs) > 0) {
            $recommendedScore = ($recommendedFilled / count($recommendedAttrs)) * 30;
        } else {
            $recommendedScore = 30;
        }

        $result['score'] += $recommendedScore;

        if (!empty($recommendedMissing)) {
            $result['suggestions'][] = 'Preencha atributos recomendados: ' . implode(', ', array_slice($recommendedMissing, 0, 5));
        }

        // Verificar atributos importantes para SEO (0-20 pontos)
        $seoAttrs = ['BRAND', 'MODEL', 'MPN', 'GTIN', 'SELLER_SKU'];
        $seoFilled = 0;

        foreach ($seoAttrs as $attrId) {
            if (isset($filledAttrs[$attrId]) && !empty($filledAttrs[$attrId]['value_name'] ?? $filledAttrs[$attrId]['value_id'] ?? null)) {
                $seoFilled++;
            }
        }

        $result['score'] += ($seoFilled / count($seoAttrs)) * 20;
        $result['details']['seo_attributes_filled'] = $seoFilled;

        if ($seoFilled < 3) {
            $result['suggestions'][] = 'Preencha BRAND, MODEL e GTIN/MPN para melhor posicionamento';
        }

        return $result;
    }

    /**
     * Análise das imagens
     */
    private function analyzeImages(array $pictures): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        $count = count($pictures);
        $result['details']['count'] = $count;

        // Verificar quantidade mínima
        if ($count === 0) {
            $result['issues'][] = 'Nenhuma imagem - CRÍTICO';
            $result['critical'] = true;
            return $result;
        }

        // Score por quantidade (0-40 pontos)
        // ML recomenda mínimo 6 imagens
        if ($count >= 10) {
            $result['score'] += 40;
        } elseif ($count >= 6) {
            $result['score'] += 35;
        } elseif ($count >= 4) {
            $result['score'] += 25;
            $result['suggestions'][] = "Adicione mais imagens. Atual: {$count}, ideal: 6-10";
        } elseif ($count >= 2) {
            $result['score'] += 15;
            $result['issues'][] = "Poucas imagens ({$count}). Mínimo recomendado: 6";
        } else {
            $result['score'] += 5;
            $result['issues'][] = 'Apenas 1 imagem - adicione mais para melhor conversão';
        }

        // Verificar qualidade das imagens (0-30 pontos)
        $qualityScore = 0;
        $lowQualityCount = 0;

        foreach ($pictures as $pic) {
            $maxSize = $pic['max_size'] ?? '';
            // Formato: "1200x1200" - verificar se é alta resolução
            if (preg_match('/(\d+)x(\d+)/', $maxSize, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];

                if ($width >= 1200 && $height >= 1200) {
                    $qualityScore += 5;
                } elseif ($width >= 800 && $height >= 800) {
                    $qualityScore += 3;
                } else {
                    $lowQualityCount++;
                }
            }
        }

        $result['score'] += min(30, $qualityScore);

        if ($lowQualityCount > 0) {
            $result['suggestions'][] = "{$lowQualityCount} imagem(ns) com resolução baixa. Use imagens de 1200x1200 ou maior";
        }

        // Verificar se primeira imagem é de qualidade (importante para busca) (0-15 pontos)
        if (!empty($pictures[0])) {
            $mainPic = $pictures[0];
            $maxSize = $mainPic['max_size'] ?? '';
            if (preg_match('/(\d+)x(\d+)/', $maxSize, $matches)) {
                $width = (int)$matches[1];
                if ($width >= 1200) {
                    $result['score'] += 15;
                } elseif ($width >= 800) {
                    $result['score'] += 10;
                } else {
                    $result['issues'][] = 'Imagem principal com baixa resolução - afeta visibilidade na busca';
                    $result['score'] += 5;
                }
            }
        }

        // Verificar variedade (0-15 pontos)
        // Não temos acesso ao conteúdo da imagem, mas podemos verificar se são diferentes
        $uniqueUrls = count(array_unique(array_column($pictures, 'url')));
        if ($uniqueUrls === $count) {
            $result['score'] += 15;
        } else {
            $result['issues'][] = 'Imagens duplicadas detectadas';
            $result['score'] += 5;
        }

        return $result;
    }

    /**
     * Análise do preço
     */
    private function analyzePrice(array $item): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        $price = $item['price'] ?? 0;
        $originalPrice = $item['original_price'] ?? null;

        $result['details']['price'] = $price;
        $result['details']['original_price'] = $originalPrice;

        // Verificar se tem preço
        if ($price <= 0) {
            $result['issues'][] = 'Preço inválido';
            $result['critical'] = true;
            return $result;
        }

        // Score base por ter preço válido (40 pontos)
        $result['score'] += 40;

        // Verificar preço promocional (0-30 pontos)
        if ($originalPrice && $originalPrice > $price) {
            $discount = (($originalPrice - $price) / $originalPrice) * 100;
            $result['details']['discount_percentage'] = round($discount, 1);

            if ($discount >= 10 && $discount <= 50) {
                $result['score'] += 30;
                $result['details']['has_promotion'] = true;
            } elseif ($discount > 50) {
                $result['score'] += 20;
                $result['suggestions'][] = 'Desconto muito alto pode parecer suspeito';
            } else {
                $result['score'] += 15;
            }
        } else {
            $result['suggestions'][] = 'Considere usar preço original + desconto para destacar na busca';
        }

        // Verificar tipo de listagem (0-30 pontos)
        $listingType = $item['listing_type_id'] ?? '';
        $result['details']['listing_type'] = $listingType;

        switch ($listingType) {
            case 'gold_pro':
            case 'gold_premium':
                $result['score'] += 30;
                break;
            case 'gold_special':
            case 'gold':
                $result['score'] += 25;
                break;
            case 'silver':
                $result['score'] += 15;
                $result['suggestions'][] = 'Considere upgrade para anúncio Premium para mais visibilidade';
                break;
            case 'bronze':
            case 'free':
                $result['score'] += 5;
                $result['issues'][] = 'Tipo de anúncio com baixa visibilidade';
                break;
        }

        return $result;
    }

    /**
     * Análise do frete
     */
    private function analyzeShipping(array $item): array
    {
        $result = [
            'score' => 0,
            'max_score' => 100,
            'issues' => [],
            'suggestions' => [],
            'details' => [],
            'critical' => false,
        ];

        $shipping = $item['shipping'] ?? [];

        // Frete grátis (0-40 pontos)
        $freeShipping = $shipping['free_shipping'] ?? false;
        $result['details']['free_shipping'] = $freeShipping;

        if ($freeShipping) {
            $result['score'] += 40;
        } else {
            $result['suggestions'][] = 'Ative frete grátis para melhor posicionamento na busca';
            $result['score'] += 10;
        }

        // Full (Mercado Envios Full) (0-40 pontos)
        $logisticType = $shipping['logistic_type'] ?? '';
        $result['details']['logistic_type'] = $logisticType;

        if ($logisticType === 'fulfillment' || $logisticType === 'cross_docking') {
            $result['score'] += 40;
            $result['details']['is_full'] = true;
        } elseif ($logisticType === 'drop_off' || $logisticType === 'xd_drop_off') {
            $result['score'] += 30;
        } elseif (!empty($logisticType)) {
            $result['score'] += 20;
        } else {
            $result['suggestions'][] = 'Considere usar Mercado Envios Full para melhor ranking';
        }

        // Modo de envio (0-20 pontos)
        $mode = $shipping['mode'] ?? '';
        $result['details']['shipping_mode'] = $mode;

        if ($mode === 'me2') {
            $result['score'] += 20;
        } elseif ($mode === 'me1') {
            $result['score'] += 15;
        } elseif (!empty($mode)) {
            $result['score'] += 10;
        }

        return $result;
    }

    /**
     * Calcula nota (grade) baseada no score
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
     * Obtém ações prioritárias ordenadas por impacto
     */
    private function getPriorityActions(array $analysis): array
    {
        $actions = [];

        // Problemas críticos primeiro
        foreach ($analysis['critical_issues'] as $issue) {
            $actions[] = [
                'priority' => 'critical',
                'action' => $issue,
                'impact' => 'alto',
            ];
        }

        // Sugestões de alto impacto
        $highImpactSuggestions = array_filter($analysis['suggestions'], function ($s) {
            return stripos($s, 'frete') !== false ||
                stripos($s, 'imag') !== false ||
                stripos($s, 'atributo') !== false;
        });

        foreach ($highImpactSuggestions as $suggestion) {
            $actions[] = [
                'priority' => 'high',
                'action' => $suggestion,
                'impact' => 'médio-alto',
            ];
        }

        // Outras sugestões
        $otherSuggestions = array_diff($analysis['suggestions'], $highImpactSuggestions);
        foreach ($otherSuggestions as $suggestion) {
            $actions[] = [
                'priority' => 'medium',
                'action' => $suggestion,
                'impact' => 'médio',
            ];
        }

        return array_slice($actions, 0, 10); // Top 10 ações
    }

    /**
     * Análise em lote de múltiplos anúncios (Otimizado com Multiget)
     */
    public function analyzeBatch(array $itemIds): array
    {
        $results = [];
        $errors = [];

        // Remover duplicatas e limpar IDs
        $itemIds = array_unique(array_filter($itemIds));

        // Processar em chunks de 20 (limite seguro para multiget)
        $chunks = array_chunk($itemIds, 20);

        foreach ($chunks as $chunk) {
            $idsString = implode(',', $chunk);

            // Usar multiget para buscar itens em uma única requisição
            $response = $this->client->get("/items", ['ids' => $idsString]);

            if (isset($response['error'])) {
                // Se falhar o multiget, tentar individualmente como fallback
                foreach ($chunk as $id) {
                    $results[$id] = $this->analyzeItem($id);
                }
                continue;
            }

            // Processar resposta do multiget
            // A resposta é um array de objetos, cada um com 'code', 'body'
            foreach ($response as $itemResponse) {
                $itemId = $itemResponse['body']['id'] ?? null;

                if (!$itemId) continue;

                if (($itemResponse['code'] ?? 0) === 200) {
                    $item = $itemResponse['body'];

                    // Otimização: Agrupar chamadas de categoria
                    // (O CategoryService já tem cache, então não precisamos reimplementar aqui)
                    $categoryAttributes = $this->categoryService->getCategoryAttributes($item['category_id'] ?? '');

                    $results[$itemId] = $this->analyzeItemData($item, $categoryAttributes);
                } else {
                    $results[$itemId] = [
                        'error' => true,
                        'message' => 'Erro ao buscar item',
                        'details' => $itemResponse['body'] ?? []
                    ];
                }
            }
        }

        // Calcular estatísticas gerais
        $validResults = array_filter($results, fn($r) => !isset($r['error']));
        $scores = array_column($validResults, 'score');

        return [
            'items' => $results,
            'summary' => [
                'total_requested' => count($itemIds),
                'total_analyzed' => count($validResults),
                'average_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores)) : 0,
                'min_score' => count($scores) > 0 ? min($scores) : 0,
                'max_score' => count($scores) > 0 ? max($scores) : 0,
                'distribution' => [
                    'excellent' => count(array_filter($scores, fn($s) => $s >= 80)),
                    'good' => count(array_filter($scores, fn($s) => $s >= 60 && $s < 80)),
                    'needs_improvement' => count(array_filter($scores, fn($s) => $s < 60)),
                ],
            ],
        ];
    }
}
