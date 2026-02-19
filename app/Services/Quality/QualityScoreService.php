<?php

namespace App\Services\Quality;

use App\Services\MercadoLivreClient;
use App\Services\CategoryService;
use App\Services\Quality\HealthCheckService;

/**
 * Quality Score Service - Calcula pontuação de qualidade de anúncios
 * 
 * Sistema de pontuação baseado nas melhores práticas do Mercado Livre:
 * - Qualidade do conteúdo (título, descrição, imagens)
 * - Completude de informações (atributos, especificações)
 * - Experiência do comprador (frete, preço, reputação)
 * - Performance (conversão, visitas, vendas)
 * - Conformidade (catálogo, moderações)
 * 
 * Score vai de 0 a 100, onde:
 * - 90-100: Excelente
 * - 75-89: Muito Bom
 * - 60-74: Bom
 * - 40-59: Regular
 * - 0-39: Ruim
 */
class QualityScoreService
{
    private MercadoLivreClient $client;
    private CategoryService $categoryService;
    private HealthCheckService $healthCheck;

    // Pesos dos componentes do score
    private const WEIGHTS = [
        'content' => 30,      // Qualidade do conteúdo
        'completeness' => 25, // Completude das informações
        'experience' => 20,   // Experiência do comprador
        'performance' => 15,  // Performance de vendas
        'compliance' => 10,   // Conformidade
    ];

    // Classificações de score
    public const RATING_LEVELS = [
        'excellent' => ['min' => 90, 'label' => 'Excelente', 'color' => 'success'],
        'very_good' => ['min' => 75, 'label' => 'Muito Bom', 'color' => 'info'],
        'good' => ['min' => 60, 'label' => 'Bom', 'color' => 'primary'],
        'regular' => ['min' => 40, 'label' => 'Regular', 'color' => 'warning'],
        'poor' => ['min' => 0, 'label' => 'Ruim', 'color' => 'danger'],
    ];

    public function __construct(?int $accountId = null)
    {
        $this->client = new MercadoLivreClient($accountId);
        $this->categoryService = new CategoryService($accountId);
        $this->healthCheck = new HealthCheckService($accountId);
    }

    /**
     * Calcula o quality score completo de um anúncio
     */
    public function calculateQualityScore(string $itemId): array
    {
        $item = $this->client->get("/items/{$itemId}");

        if (isset($item['error'])) {
            return [
                'success' => false,
                'error' => $item['message'] ?? 'Item não encontrado',
            ];
        }

        // Obter descrição completa
        $description = $this->client->get("/items/{$itemId}/description");

        // Calcular cada componente
        $contentScore = $this->calculateContentScore($item, $description);
        $completenessScore = $this->calculateCompletenessScore($item);
        $experienceScore = $this->calculateExperienceScore($item);
        $performanceScore = $this->calculatePerformanceScore($item);
        $complianceScore = $this->calculateComplianceScore($item);

        // Calcular score total ponderado
        $totalScore = round(
            ($contentScore['score'] * self::WEIGHTS['content'] / 100) +
            ($completenessScore['score'] * self::WEIGHTS['completeness'] / 100) +
            ($experienceScore['score'] * self::WEIGHTS['experience'] / 100) +
            ($performanceScore['score'] * self::WEIGHTS['performance'] / 100) +
            ($complianceScore['score'] * self::WEIGHTS['compliance'] / 100),
            1
        );

        $rating = $this->getRating($totalScore);

        return [
            'success' => true,
            'item_id' => $itemId,
            'title' => $item['title'] ?? '',
            'timestamp' => date('Y-m-d H:i:s'),
            'quality_score' => [
                'total' => $totalScore,
                'rating' => $rating,
                'components' => [
                    'content' => $contentScore,
                    'completeness' => $completenessScore,
                    'experience' => $experienceScore,
                    'performance' => $performanceScore,
                    'compliance' => $complianceScore,
                ],
            ],
            'strengths' => $this->identifyStrengths($contentScore, $completenessScore, $experienceScore, $performanceScore, $complianceScore),
            'weaknesses' => $this->identifyWeaknesses($contentScore, $completenessScore, $experienceScore, $performanceScore, $complianceScore),
            'improvement_potential' => $this->calculateImprovementPotential($totalScore, $contentScore, $completenessScore, $experienceScore),
        ];
    }

    /**
     * Score de qualidade do conteúdo (30%)
     */
    private function calculateContentScore(array $item, array $description): array
    {
        $score = 0;
        $maxScore = 100;
        $details = [];

        // 1. TÍTULO (40 pontos)
        $title = $item['title'] ?? '';
        $titleLength = mb_strlen($title);

        if ($titleLength >= 45 && $titleLength <= 60) {
            $score += 15;
            $details[] = '✓ Título com tamanho ideal (45-60 caracteres)';
        } else if ($titleLength >= 30 && $titleLength < 45) {
            $score += 10;
            $details[] = '~ Título poderia ser mais descritivo';
        } else if ($titleLength > 60) {
            $score += 8;
            $details[] = '~ Título muito longo';
        } else {
            $details[] = '✗ Título muito curto';
        }

        // Verificar palavras-chave no título
        if (preg_match('/\b(original|novo|lacrado|garantia)\b/i', $title)) {
            $score += 10;
            $details[] = '✓ Título contém palavras-chave de confiança';
        }

        // Verificar se tem marca no título
        $hasBrand = false;
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'BRAND' && !empty($attr['value_name'])) {
                $brand = $attr['value_name'];
                if (stripos($title, $brand) !== false) {
                    $score += 8;
                    $hasBrand = true;
                    $details[] = '✓ Marca presente no título';
                }
                break;
            }
        }
        if (!$hasBrand) {
            $details[] = '~ Marca não aparece no título';
        }

        // Verificar capitalização
        if (!preg_match('/[A-Z]{3,}/', $title)) {
            $score += 7;
            $details[] = '✓ Título sem CAPS LOCK excessivo';
        } else {
            $details[] = '✗ Evite CAPS LOCK no título';
        }

        // 2. DESCRIÇÃO (35 pontos)
        $descText = $description['plain_text'] ?? '';
        $descLength = mb_strlen($descText);

        if ($descLength >= 500) {
            $score += 15;
            $details[] = '✓ Descrição completa (500+ caracteres)';
        } else if ($descLength >= 300) {
            $score += 10;
            $details[] = '~ Descrição poderia ser mais detalhada';
        } else if ($descLength >= 100) {
            $score += 5;
            $details[] = '✗ Descrição muito curta';
        } else {
            $details[] = '✗ Descrição inexistente ou muito curta';
        }

        // Verificar estruturação da descrição
        if (strpos($descText, "\n") !== false || strpos($descText, '<li>') !== false) {
            $score += 10;
            $details[] = '✓ Descrição estruturada com parágrafos/listas';
        } else {
            $details[] = '~ Use parágrafos e listas na descrição';
        }

        // Verificar características técnicas na descrição
        if (preg_match('/\b(especificações|características|dimensões|peso|garantia)\b/i', $descText)) {
            $score += 10;
            $details[] = '✓ Descrição inclui especificações técnicas';
        }

        // 3. IMAGENS (25 pontos)
        $pictures = $item['pictures'] ?? [];
        $pictureCount = count($pictures);

        if ($pictureCount >= 8) {
            $score += 15;
            $details[] = '✓ Quantidade ideal de imagens (8+)';
        } else if ($pictureCount >= 6) {
            $score += 12;
            $details[] = '✓ Boa quantidade de imagens (6+)';
        } else if ($pictureCount >= 3) {
            $score += 7;
            $details[] = '~ Adicione mais imagens';
        } else if ($pictureCount >= 1) {
            $score += 3;
            $details[] = '✗ Poucas imagens';
        } else {
            $details[] = '✗ Sem imagens';
        }

        // Verificar qualidade das imagens
        $highQualityCount = 0;
        foreach ($pictures as $picture) {
            $size = $picture['size'] ?? '';
            if (!empty($size)) {
                list($width, $height) = explode('x', $size);
                if ($width >= 1200 && $height >= 1200) {
                    $highQualityCount++;
                }
            }
        }

        if ($highQualityCount >= 6) {
            $score += 10;
            $details[] = '✓ Imagens em alta resolução (1200x1200+)';
        } else if ($highQualityCount >= 3) {
            $score += 5;
            $details[] = '~ Algumas imagens poderiam ter melhor resolução';
        }

        return [
            'score' => min($score, $maxScore),
            'max_score' => $maxScore,
            'percentage' => round(min($score, $maxScore), 1),
            'details' => $details,
        ];
    }

    /**
     * Score de completude das informações (25%)
     */
    private function calculateCompletenessScore(array $item): array
    {
        $score = 0;
        $maxScore = 100;
        $details = [];

        $categoryId = $item['category_id'] ?? '';
        $attributes = $item['attributes'] ?? [];

        // 1. ATRIBUTOS OBRIGATÓRIOS (50 pontos)
        $categoryAttributes = $this->categoryService->getCategoryAttributes($categoryId);
        $requiredAttributes = array_filter($categoryAttributes, fn($attr) => 
            isset($attr['tags']['required']) && $attr['tags']['required']
        );

        $itemAttributeIds = array_column($attributes, 'id');
        $missingRequired = [];

        foreach ($requiredAttributes as $reqAttr) {
            if (!in_array($reqAttr['id'], $itemAttributeIds)) {
                $missingRequired[] = $reqAttr['name'];
            }
        }

        $requiredCount = count($requiredAttributes);
        $filledRequiredCount = $requiredCount - count($missingRequired);

        if ($requiredCount > 0) {
            $requiredPercentage = ($filledRequiredCount / $requiredCount) * 50;
            $score += $requiredPercentage;

            if (empty($missingRequired)) {
                $details[] = '✓ Todos os atributos obrigatórios preenchidos';
            } else {
                $details[] = "~ Faltam {" . count($missingRequired) . "} atributos obrigatórios";
            }
        } else {
            $score += 50;
            $details[] = '✓ Categoria sem atributos obrigatórios';
        }

        // 2. ATRIBUTOS RECOMENDADOS (30 pontos)
        $recommendedAttributes = array_filter($categoryAttributes, fn($attr) => 
            isset($attr['tags']['recommended']) && $attr['tags']['recommended']
        );

        $filledRecommendedCount = 0;
        foreach ($recommendedAttributes as $recAttr) {
            if (in_array($recAttr['id'], $itemAttributeIds)) {
                $filledRecommendedCount++;
            }
        }

        $recommendedCount = count($recommendedAttributes);
        if ($recommendedCount > 0) {
            $recommendedPercentage = ($filledRecommendedCount / $recommendedCount) * 30;
            $score += $recommendedPercentage;

            if ($filledRecommendedCount === $recommendedCount) {
                $details[] = '✓ Todos os atributos recomendados preenchidos';
            } else {
                $missingRecCount = $recommendedCount - $filledRecommendedCount;
                $details[] = "~ {$missingRecCount} atributos recomendados não preenchidos";
            }
        } else {
            $score += 30;
        }

        // 3. ATRIBUTOS IMPORTANTES (20 pontos)
        $hasBrand = in_array('BRAND', $itemAttributeIds);
        $hasModel = in_array('MODEL', $itemAttributeIds);
        $hasGtin = in_array('GTIN', $itemAttributeIds);

        if ($hasBrand) {
            $score += 8;
            $details[] = '✓ Marca (BRAND) informada';
        } else {
            $details[] = '✗ Marca (BRAND) não informada';
        }

        if ($hasModel) {
            $score += 6;
            $details[] = '✓ Modelo (MODEL) informado';
        }

        if ($hasGtin) {
            $score += 6;
            $details[] = '✓ GTIN (código de barras) informado';
        } else {
            $details[] = '~ GTIN não informado (ajuda no catálogo)';
        }

        return [
            'score' => min($score, $maxScore),
            'max_score' => $maxScore,
            'percentage' => round(min($score, $maxScore), 1),
            'details' => $details,
        ];
    }

    /**
     * Score de experiência do comprador (20%)
     */
    private function calculateExperienceScore(array $item): array
    {
        $score = 0;
        $maxScore = 100;
        $details = [];

        $shipping = $item['shipping'] ?? [];
        $price = $item['price'] ?? 0;

        // 1. FRETE (40 pontos)
        $freeShipping = $shipping['free_shipping'] ?? false;
        $logisticType = $shipping['logistic_type'] ?? '';
        $mode = $shipping['mode'] ?? '';

        if ($freeShipping) {
            $score += 20;
            $details[] = '✓ Frete grátis ativado';
        } else {
            $details[] = '✗ Sem frete grátis (impacta conversão)';
        }

        if ($logisticType === 'fulfillment') {
            $score += 20;
            $details[] = '✓ Mercado Envios Full (melhor ranking)';
        } else if ($mode === 'me2') {
            $score += 15;
            $details[] = '✓ Mercado Envios';
        } else if (!empty($mode) && $mode !== 'not_specified') {
            $score += 10;
            $details[] = '~ Considere usar Mercado Envios';
        } else {
            $details[] = '✗ Modo de envio não configurado';
        }

        // 2. TIPO DE ANÚNCIO (25 pontos)
        $listingType = $item['listing_type_id'] ?? '';

        if ($listingType === 'gold_premium' || $listingType === 'gold_pro') {
            $score += 25;
            $details[] = '✓ Anúncio Premium (máxima visibilidade)';
        } else if ($listingType === 'gold_special') {
            $score += 20;
            $details[] = '✓ Anúncio Clássico';
        } else if ($listingType === 'gold') {
            $score += 15;
            $details[] = '~ Anúncio Grátis (visibilidade limitada)';
        } else {
            $score += 10;
        }

        // 3. PREÇO COMPETITIVO (20 pontos)
        // Nota: análise completa requer comparação com concorrentes
        if ($price > 0) {
            $score += 10;
            $details[] = '✓ Preço definido';
        }

        $originalPrice = $item['original_price'] ?? null;
        if ($originalPrice && $originalPrice > $price) {
            $discount = round((($originalPrice - $price) / $originalPrice) * 100);
            $score += 10;
            $details[] = "✓ Desconto de {$discount}% (atrai compradores)";
        }

        // 4. DISPONIBILIDADE (15 pontos)
        $availableQuantity = $item['available_quantity'] ?? 0;
        $soldQuantity = $item['sold_quantity'] ?? 0;

        if ($availableQuantity > 0) {
            $score += 10;
            $details[] = '✓ Produto disponível em estoque';
        } else {
            $details[] = '✗ Sem estoque disponível';
        }

        if ($soldQuantity >= 10) {
            $score += 5;
            $details[] = '✓ Histórico de vendas (confiança)';
        }

        return [
            'score' => min($score, $maxScore),
            'max_score' => $maxScore,
            'percentage' => round(min($score, $maxScore), 1),
            'details' => $details,
        ];
    }

    /**
     * Score de performance (15%)
     */
    private function calculatePerformanceScore(array $item): array
    {
        $score = 50; // Score base (sem dados de performance)
        $maxScore = 100;
        $details = [];

        // 1. VENDAS (40 pontos)
        $soldQuantity = $item['sold_quantity'] ?? 0;

        if ($soldQuantity >= 100) {
            $score += 40;
            $details[] = '✓ Excelente histórico de vendas (100+)';
        } else if ($soldQuantity >= 50) {
            $score += 35;
            $details[] = '✓ Bom histórico de vendas (50+)';
        } else if ($soldQuantity >= 10) {
            $score += 25;
            $details[] = '✓ Vendas comprovadas (10+)';
        } else if ($soldQuantity > 0) {
            $score += 15;
            $details[] = '~ Primeiras vendas realizadas';
        } else {
            $details[] = '~ Sem histórico de vendas ainda';
        }

        // 2. CONVERSÃO (30 pontos)
        // Nota: taxa de conversão real requer API de métricas
        // Por hora, estimamos baseado em outros fatores
        $hasVideo = !empty($item['video_id']);
        if ($hasVideo) {
            $score += 10;
            $details[] = '✓ Tem vídeo (aumenta conversão)';
        }

        // 3. CATÁLOGO (30 pontos)
        $catalogProductId = $item['catalog_product_id'] ?? null;
        if (!empty($catalogProductId)) {
            $score += 30;
            $details[] = '✓ Vinculado ao catálogo (melhor performance)';
        } else {
            $details[] = '~ Não está no catálogo';
        }

        return [
            'score' => min($score, $maxScore),
            'max_score' => $maxScore,
            'percentage' => round(min($score, $maxScore), 1),
            'details' => $details,
            'note' => 'Análise parcial - requer dados de métricas para score completo',
        ];
    }

    /**
     * Score de conformidade (10%)
     */
    private function calculateComplianceScore(array $item): array
    {
        $score = 100;
        $maxScore = 100;
        $details = [];

        // 1. STATUS DO ANÚNCIO (40 pontos)
        $status = $item['status'] ?? '';

        if ($status === 'active') {
            $details[] = '✓ Anúncio ativo';
        } else if ($status === 'paused') {
            $score -= 40;
            $details[] = '✗ Anúncio pausado';
        } else if ($status === 'inactive') {
            $score -= 50;
            $details[] = '✗ Anúncio inativo';
        } else {
            $score -= 20;
            $details[] = "~ Status: {$status}";
        }

        // 2. SUB-STATUS (30 pontos)
        $subStatus = $item['sub_status'] ?? [];
        if (!empty($subStatus)) {
            $score -= 30;
            $details[] = '✗ Possui alertas/moderações: ' . implode(', ', $subStatus);
        }

        // 3. HEALTH (30 pontos)
        $health = $item['health'] ?? null;
        if ($health !== null && is_numeric($health)) {
            if ($health >= 0.8) {
                $details[] = '✓ Boa saúde do anúncio';
            } else if ($health >= 0.5) {
                $score -= 15;
                $details[] = '~ Saúde do anúncio pode melhorar';
            } else {
                $score -= 30;
                $details[] = '✗ Problemas de saúde detectados';
            }
        }

        if (empty($details) || count($details) === 1) {
            $details[] = '✓ Sem problemas de conformidade';
        }

        return [
            'score' => max(0, min($score, $maxScore)),
            'max_score' => $maxScore,
            'percentage' => round(max(0, min($score, $maxScore)), 1),
            'details' => $details,
        ];
    }

    /**
     * Obtém classificação baseada no score
     */
    private function getRating(float $score): array
    {
        foreach (self::RATING_LEVELS as $key => $level) {
            if ($score >= $level['min']) {
                return [
                    'key' => $key,
                    'label' => $level['label'],
                    'color' => $level['color'],
                    'range' => [$level['min'], 100],
                ];
            }
        }

        return self::RATING_LEVELS['poor'];
    }

    /**
     * Identifica pontos fortes
     */
    private function identifyStrengths(...$components): array
    {
        $strengths = [];

        foreach ($components as $component) {
            if ($component['percentage'] >= 80) {
                foreach ($component['details'] as $detail) {
                    if (strpos($detail, '✓') === 0) {
                        $strengths[] = trim(substr($detail, 2));
                    }
                }
            }
        }

        return array_slice($strengths, 0, 5);
    }

    /**
     * Identifica pontos fracos
     */
    private function identifyWeaknesses(...$components): array
    {
        $weaknesses = [];

        foreach ($components as $component) {
            if ($component['percentage'] < 60) {
                foreach ($component['details'] as $detail) {
                    if (strpos($detail, '✗') === 0 || strpos($detail, '~') === 0) {
                        $weaknesses[] = trim(substr($detail, 2));
                    }
                }
            }
        }

        return array_slice($weaknesses, 0, 5);
    }

    /**
     * Calcula potencial de melhoria
     */
    private function calculateImprovementPotential(float $currentScore, ...$components): array
    {
        $improvements = [];
        $totalPotential = 0;

        // Analisar componentes com maior potencial
        foreach ($components as $component) {
            if ($component['percentage'] < 80) {
                $potential = 100 - $component['percentage'];
                $totalPotential += $potential;

                foreach ($component['details'] as $detail) {
                    if (strpos($detail, '✗') === 0) {
                        $improvements[] = [
                            'action' => trim(substr($detail, 2)),
                            'impact' => 'high',
                            'potential_gain' => '+' . round($potential / 3, 1) . ' pontos',
                        ];
                    }
                }
            }
        }

        $maxPossibleScore = min(100, $currentScore + ($totalPotential / count($components)));

        return [
            'current_score' => $currentScore,
            'max_possible_score' => round($maxPossibleScore, 1),
            'potential_gain' => '+' . round($maxPossibleScore - $currentScore, 1) . ' pontos',
            'top_improvements' => array_slice($improvements, 0, 3),
        ];
    }

    /**
     * Compara quality score do item com concorrentes da mesma categoria via ML API
     */
    public function compareWithCategory(string $itemId, string $categoryId): array
    {
        $itemScore = $this->calculateQualityScore($itemId);

        if (!$itemScore['success']) {
            return $itemScore;
        }

        $myScore = $itemScore['quality_score']['total'] ?? 0;

        try {
            // Buscar top items da categoria para comparação
            $searchResults = $this->client->searchItems([
                'category' => $categoryId,
                'sort' => 'sold_quantity_desc',
                'limit' => 10,
            ], 3600);

            $results = $searchResults['results'] ?? [];
            $categoryScores = [];

            foreach ($results as $item) {
                $id = $item['id'] ?? '';
                if (empty($id) || $id === $itemId) {
                    continue;
                }

                // Calcular score simplificado para cada concorrente
                $score = 0;

                // Título (0-25pts)
                $title = $item['title'] ?? '';
                $titleLen = mb_strlen($title);
                if ($titleLen >= 45 && $titleLen <= 60) {
                    $score += 25;
                } elseif ($titleLen >= 30) {
                    $score += 15;
                } else {
                    $score += 5;
                }

                // Imagens (0-25pts)
                $imageCount = count($item['pictures'] ?? []);
                $score += min(25, $imageCount * 4);

                // Preço/condição (0-15pts)
                if (($item['condition'] ?? '') === 'new') {
                    $score += 10;
                }
                if (($item['shipping']['free_shipping'] ?? false) === true) {
                    $score += 5;
                }

                // Atributos (0-20pts)
                $attrCount = count($item['attributes'] ?? []);
                $filledAttrs = 0;
                foreach ($item['attributes'] ?? [] as $attr) {
                    if (!empty($attr['value_name'] ?? null)) {
                        $filledAttrs++;
                    }
                }
                $score += min(20, (int) ($filledAttrs * 2));

                // Vendas (0-15pts)
                $soldQty = $item['sold_quantity'] ?? 0;
                if ($soldQty >= 100) {
                    $score += 15;
                } elseif ($soldQty >= 10) {
                    $score += 10;
                } elseif ($soldQty > 0) {
                    $score += 5;
                }

                $categoryScores[] = $score;
            }

            if (empty($categoryScores)) {
                return [
                    'success' => true,
                    'item_score' => $myScore,
                    'category_average' => null,
                    'position' => null,
                    'sample_size' => 0,
                    'note' => 'Sem concorrentes encontrados na categoria para comparação',
                ];
            }

            $categoryAvg = round(array_sum($categoryScores) / count($categoryScores), 1);
            $allScores = array_merge($categoryScores, [$myScore]);
            rsort($allScores);
            $position = array_search($myScore, $allScores) + 1;

            return [
                'success' => true,
                'item_score' => $myScore,
                'category_average' => $categoryAvg,
                'position' => $position,
                'total_compared' => count($categoryScores) + 1,
                'sample_size' => count($categoryScores),
                'percentile' => round((1 - ($position / (count($allScores)))) * 100, 1),
                'above_average' => $myScore > $categoryAvg,
                'difference' => round($myScore - $categoryAvg, 1),
            ];

        } catch (\Exception $e) {
            return [
                'success' => true,
                'item_score' => $myScore,
                'category_average' => null,
                'position' => null,
                'note' => 'Comparação indisponível: ' . $e->getMessage(),
            ];
        }
    }
}
