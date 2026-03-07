<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\CacheService;
use App\Traits\NormalizesMLItems;
use Exception;

/**
 * SEO Audit Service
 * 
 * Analyzes listing quality and generates SEO scores with actionable recommendations
 */
class SEOAuditService
{
    private \PDO $db;
    private MercadoLivreClient $mlClient;
    private CacheService $cache;

    // Score weights for overall calculation
    private const WEIGHTS = [
        'title' => 0.25,
        'description' => 0.20,
        'attributes' => 0.25,
        'images' => 0.15,
        'pricing' => 0.10,
        'category' => 0.05,
    ];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->cache = new CacheService();
    }

    /**
     * Audit a listing and generate SEO score
     * 
     * @param string $itemId ML item ID
     * @param bool $forceRefresh Force new audit (skip cache)
     * @return array Audit results
     */
    public function auditListing(string $itemId, bool $forceRefresh = false): array
    {
        $startTime = microtime(true);

        // Check cache
        if (!$forceRefresh) {
            $cached = $this->getCachedAudit($itemId);
            if ($cached) {
                return $cached;
            }
        }

        // Get item data from ML API
        $item = $this->mlClient->getItemDetails($itemId);

        if (!$item) {
            throw new Exception("Item not found: {$itemId}");
        }

        // Get category attributes for completeness check
        $categoryAttributes = [];
        if (!empty($item['category_id'])) {
            $categoryAttributes = $this->mlClient->getCategoryAttributes($item['category_id']);
        }

        // Perform individual audits
        $titleScore = $this->auditTitle($item);
        $descriptionScore = $this->auditDescription($item);
        $attributesScore = $this->auditAttributes($item, $categoryAttributes);
        $imagesScore = $this->auditImages($item);
        $pricingScore = $this->auditPricing($item);
        $categoryScore = $this->auditCategory($item);

        // Calculate overall score
        $overallScore = $this->calculateOverallScore([
            'title' => $titleScore['score'],
            'description' => $descriptionScore['score'],
            'attributes' => $attributesScore['score'],
            'images' => $imagesScore['score'],
            'pricing' => $pricingScore['score'],
            'category' => $categoryScore['score'],
        ]);

        // Collect all recommendations
        $hiddenRecommendations = $this->buildHiddenRecommendations($itemId, $item);

        $recommendations = array_merge(
            $titleScore['recommendations'],
            $descriptionScore['recommendations'],
            $attributesScore['recommendations'],
            $imagesScore['recommendations'],
            $pricingScore['recommendations'],
            $categoryScore['recommendations'],
            $hiddenRecommendations
        );

        // Sort by priority
        usort($recommendations, function ($a, $b) {
            $priority = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($priority[$a['priority']] ?? 99) <=> ($priority[$b['priority']] ?? 99);
        });

        $processingTime = round((microtime(true) - $startTime) * 1000);

        $hiddenCompleteness = $this->calculateHiddenAttributesCompleteness($itemId, $item);

        $audit = [
            'item_id' => $itemId,
            'account_id' => $this->mlClient->getAccountId(),
            'audit_date' => date('Y-m-d H:i:s'),
            'overall_score' => $overallScore,
            'scores' => [
                'title' => $titleScore['score'],
                'description' => $descriptionScore['score'],
                'attributes' => $attributesScore['score'],
                'images' => $imagesScore['score'],
                'pricing' => $pricingScore['score'],
                'category' => $categoryScore['score'],
            ],
            'completeness' => [
                'required_attributes' => $attributesScore['required_pct'],
                'optional_attributes' => $attributesScore['optional_pct'],
                'hidden_attributes' => $hiddenCompleteness,
            ],
            'recommendations' => $recommendations,
            'processing_time_ms' => $processingTime,
        ];

        // Save to database
        $this->saveAudit($audit);

        return $audit;
    }

    /**
     * Audit title quality
     */
    private function auditTitle(array $item): array
    {
        $title = $item['title'] ?? '';
        $score = 100;
        $recommendations = [];

        $length = mb_strlen($title);

        // Length check (optimal: 40-60 chars)
        if ($length < 30) {
            $score -= 20;
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'high',
                'message' => 'Título muito curto. Adicione mais detalhes do produto.',
                'impact' => 'Títulos mais descritivos aumentam a visibilidade em até 30%',
            ];
        } elseif ($length < 40) {
            $score -= 10;
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => 'Título poderia ser mais descritivo.',
                'impact' => 'Adicionar 10-20 caracteres pode melhorar o ranking',
            ];
        } elseif ($length > 60) {
            $score -= 5;
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'low',
                'message' => 'Título muito longo pode ser truncado em dispositivos móveis.',
                'impact' => 'Considere reduzir para 50-60 caracteres',
            ];
        }

        // Check for brand (common brands in Brazil)
        $brands = ['Samsung', 'LG', 'Sony', 'Apple', 'Xiaomi', 'Motorola', 'Nike', 'Adidas'];
        $hasBrand = false;
        foreach ($brands as $brand) {
            if (stripos($title, $brand) !== false) {
                $hasBrand = true;
                break;
            }
        }

        if (!$hasBrand && !empty($item['attributes'])) {
            // Check if brand is in attributes
            foreach ($item['attributes'] as $attr) {
                if (($attr['id'] ?? '') === 'BRAND' && !empty($attr['value_name'])) {
                    $brandName = $attr['value_name'];
                    if (stripos($title, $brandName) === false) {
                        $score -= 15;
                        $recommendations[] = [
                            'type' => 'title',
                            'priority' => 'high',
                            'message' => "Adicione a marca '{$brandName}' no título.",
                            'impact' => 'Títulos com marca têm 25% mais cliques',
                        ];
                    }
                    break;
                }
            }
        }

        // Check for special characters that might hurt SEO
        if (preg_match('/[★☆♥♦►◄]/', $title)) {
            $score -= 10;
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => 'Evite caracteres especiais decorativos no título.',
                'impact' => 'Pode afetar negativamente o ranking de busca',
            ];
        }

        // Check for all caps
        if ($title === mb_strtoupper($title) && $length > 10) {
            $score -= 5;
            $recommendations[] = [
                'type' => 'title',
                'priority' => 'low',
                'message' => 'Evite escrever todo o título em MAIÚSCULAS.',
                'impact' => 'Dificulta a leitura e pode parecer spam',
            ];
        }

        return [
            'score' => max(0, $score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit description quality
     */
    private function auditDescription(array $item): array
    {
        // Safely extract description text — getItemDetails() may return array or string
        $description = NormalizesMLItems::extractDescriptionText($item['description'] ?? null);
        $plainText = strip_tags($description);
        $score = 100;
        $recommendations = [];

        $length = mb_strlen($plainText);

        // Length check
        if ($length < 100) {
            $score -= 30;
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'high',
                'message' => 'Descrição muito curta. Adicione mais detalhes sobre o produto.',
                'impact' => 'Descrições completas aumentam conversão em até 40%',
            ];
        } elseif ($length < 200) {
            $score -= 15;
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'medium',
                'message' => 'Descrição poderia ser mais detalhada.',
                'impact' => 'Adicione especificações técnicas e benefícios',
            ];
        }

        // Check for HTML formatting
        $hasHtml = $description !== $plainText;
        if (!$hasHtml && $length > 200) {
            $score -= 10;
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'medium',
                'message' => 'Use formatação HTML para melhorar a legibilidade.',
                'impact' => 'Listas e negrito aumentam o engajamento',
            ];
        }

        // Check for call-to-action
        $cta_keywords = ['compre', 'garanta', 'aproveite', 'oferta', 'promoção'];
        $hasCta = false;
        foreach ($cta_keywords as $keyword) {
            if (stripos($plainText, $keyword) !== false) {
                $hasCta = true;
                break;
            }
        }

        if (!$hasCta) {
            $score -= 5;
            $recommendations[] = [
                'type' => 'description',
                'priority' => 'low',
                'message' => 'Adicione um call-to-action na descrição.',
                'impact' => 'Incentiva a compra imediata',
            ];
        }

        return [
            'score' => max(0, $score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit attributes completeness
     */
    private function auditAttributes(array $item, array $categoryAttributes): array
    {
        $itemAttributes = $item['attributes'] ?? [];
        $score = 100;
        $recommendations = [];

        // Count filled attributes
        $filledCount = count($itemAttributes);

        // Analyze category attributes
        $requiredCount = 0;
        $optionalCount = 0;
        $filledRequired = 0;
        $filledOptional = 0;

        if (!empty($categoryAttributes)) {
            foreach ($categoryAttributes as $catAttr) {
                $isRequired = ($catAttr['tags']['required'] ?? false);

                if ($isRequired) {
                    $requiredCount++;
                } else {
                    $optionalCount++;
                }

                // Check if filled in item
                $isFilled = false;
                foreach ($itemAttributes as $itemAttr) {
                    if (($itemAttr['id'] ?? '') === ($catAttr['id'] ?? '')) {
                        $value = $itemAttr['value_name'] ?? $itemAttr['value_id'] ?? '';
                        // Check for placeholder values
                        if (!empty($value) && !in_array(mb_strtolower($value), ['n/a', 'não se aplica', 'outro'])) {
                            $isFilled = true;
                        }
                        break;
                    }
                }

                if ($isFilled) {
                    if ($isRequired) {
                        $filledRequired++;
                    } else {
                        $filledOptional++;
                    }
                }
            }
        }

        // Calculate percentages
        $requiredPct = $requiredCount > 0 ? round(($filledRequired / $requiredCount) * 100) : 100;
        $optionalPct = $optionalCount > 0 ? round(($filledOptional / $optionalCount) * 100) : 0;

        // Score based on required attributes
        if ($requiredPct < 100) {
            $score -= (100 - $requiredPct);
            $missing = $requiredCount - $filledRequired;
            $recommendations[] = [
                'type' => 'attributes',
                'priority' => 'high',
                'message' => "{$missing} atributo(s) obrigatório(s) não preenchido(s).",
                'impact' => 'Atributos obrigatórios são essenciais para visibilidade',
            ];
        }

        // Bonus for optional attributes
        if ($optionalPct < 50) {
            $score -= 10;
            $recommendations[] = [
                'type' => 'attributes',
                'priority' => 'medium',
                'message' => 'Preencha mais atributos opcionais para melhorar o SEO.',
                'impact' => 'Cada atributo adicional melhora a relevância',
            ];
        }

        return [
            'score' => max(0, $score),
            'required_pct' => $requiredPct,
            'optional_pct' => $optionalPct,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit images quality
     */
    private function auditImages(array $item): array
    {
        $pictures = $item['pictures'] ?? [];
        $score = 100;
        $recommendations = [];

        $count = count($pictures);

        // Image count check
        if ($count === 0) {
            $score = 0;
            $recommendations[] = [
                'type' => 'images',
                'priority' => 'high',
                'message' => 'Nenhuma imagem encontrada!',
                'impact' => 'Imagens são essenciais para vendas',
            ];
        } elseif ($count < 4) {
            $score -= 30;
            $recommendations[] = [
                'type' => 'images',
                'priority' => 'high',
                'message' => "Adicione mais imagens (atual: {$count}, recomendado: 6-8).",
                'impact' => 'Anúncios com 6+ imagens vendem 50% mais',
            ];
        } elseif ($count < 6) {
            $score -= 10;
            $recommendations[] = [
                'type' => 'images',
                'priority' => 'medium',
                'message' => "Adicione mais 2-3 imagens para melhor conversão.",
                'impact' => 'Mais ângulos aumentam a confiança do comprador',
            ];
        }

        // Check image quality (resolution)
        $lowQualityCount = 0;
        foreach ($pictures as $picture) {
            $url = $picture['url'] ?? '';
            // ML image URLs contain size info
            if (strpos($url, '-I.jpg') !== false || strpos($url, '-O.jpg') !== false) {
                // These are low quality
                $lowQualityCount++;
            }
        }

        if ($lowQualityCount > 0) {
            $score -= 15;
            $recommendations[] = [
                'type' => 'images',
                'priority' => 'medium',
                'message' => "{$lowQualityCount} imagem(ns) com baixa resolução.",
                'impact' => 'Use imagens de alta qualidade (mínimo 800x800px)',
            ];
        }

        return [
            'score' => max(0, $score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit pricing strategy
     */
    private function auditPricing(array $item): array
    {
        $score = 100;
        $recommendations = [];

        $price = $item['price'] ?? 0;
        $originalPrice = $item['original_price'] ?? null;
        $shipping = $item['shipping'] ?? [];

        // Check for free shipping
        $hasFreeShipping = ($shipping['free_shipping'] ?? false);
        if (!$hasFreeShipping && $price > 79) {
            $score -= 15;
            $recommendations[] = [
                'type' => 'pricing',
                'priority' => 'medium',
                'message' => 'Considere oferecer frete grátis para aumentar conversão.',
                'impact' => 'Frete grátis pode aumentar vendas em 30%',
            ];
        }

        // Check for discount
        $hasDiscount = !empty($originalPrice) && $originalPrice > $price;
        if (!$hasDiscount) {
            $score -= 5;
            $recommendations[] = [
                'type' => 'pricing',
                'priority' => 'low',
                'message' => 'Considere mostrar um preço promocional.',
                'impact' => 'Descontos visíveis atraem mais cliques',
            ];
        }

        return [
            'score' => max(0, $score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit category placement
     */
    private function auditCategory(array $item): array
    {
        $score = 100;
        $recommendations = [];

        $categoryId = $item['category_id'] ?? '';

        if (empty($categoryId)) {
            $score = 0;
            $recommendations[] = [
                'type' => 'category',
                'priority' => 'high',
                'message' => 'Categoria não definida!',
                'impact' => 'Categoria correta é essencial para visibilidade',
            ];
        }

        // Additional category-specific checks could be added here

        return [
            'score' => max(0, $score),
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Calculate overall score from component scores
     */
    private function calculateOverallScore(array $scores): int
    {
        $weighted = 0;

        foreach (self::WEIGHTS as $component => $weight) {
            $weighted += ($scores[$component] ?? 0) * $weight;
        }

        return (int) round($weighted);
    }

    private function calculateHiddenAttributesCompleteness(string $itemId, array $item): int
    {
        $targets = ['KEYWORDS', 'MPN', 'LINE'];
        $map = $this->mapItemAttributes($item);

        $stored = [];
        try {
            $detector = new HiddenAttributesDetector($this->mlClient->getAccountId());
            $stored = $detector->getStoredHiddenAttributes($itemId);
            if (empty($stored)) {
                $detector->detectHiddenAttributes($itemId, false);
                $stored = $detector->getStoredHiddenAttributes($itemId);
            }
        } catch (\Throwable $e) {
            $stored = [];
        }

        foreach ($stored as $hidden) {
            $attrId = $hidden['attribute_id'] ?? null;
            if (is_string($attrId) && $attrId !== '') {
                $targets[] = $attrId;
            }
        }

        $targets = array_values(array_unique(array_filter($targets)));

        $filled = 0;
        foreach ($targets as $target) {
            if (!empty($map[$target])) {
                $filled++;
            }
        }

        if ($filled === 0) {
            return 0;
        }

        return (int) round(($filled / count($targets)) * 100);
    }

    private function buildHiddenRecommendations(string $itemId, array $item): array
    {
        $recommendations = [];
        $map = $this->mapItemAttributes($item);

        $detector = new HiddenAttributesDetector($this->mlClient->getAccountId());
        $stored = [];
        try {
            $stored = $detector->getStoredHiddenAttributes($itemId);
            if (empty($stored)) {
                $detector->detectHiddenAttributes($itemId, false);
                $stored = $detector->getStoredHiddenAttributes($itemId);
            }
        } catch (\Throwable $e) {
            $stored = [];
        }

        foreach ($stored as $hidden) {
            $attrId = $hidden['attribute_id'] ?? null;
            if (!is_string($attrId) || $attrId === '') {
                continue;
            }
            if (!empty($map[$attrId])) {
                continue;
            }
            $impact = $hidden['impact'] ?? 'medium';
            $priority = $impact === 'high' ? 'high' : ($impact === 'low' ? 'low' : 'medium');
            $name = $hidden['attribute_name'] ?? $attrId;
            $recommendations[] = [
                'type' => 'hidden_attributes',
                'priority' => $priority,
                'message' => "Preencha atributo oculto {$name}.",
                'impact' => 'Atributo recorrente entre concorrentes bem ranqueados',
            ];
        }

        try {
            $keywordFields = $detector->detectKeywordFields($itemId);
            foreach ($keywordFields as $fieldId => $data) {
                if (!($data['detected'] ?? false)) {
                    continue;
                }
                $suggestion = $data['suggestion'] ?? '';
                if ($suggestion === '') {
                    continue;
                }
                $priority = $fieldId === 'MPN' ? 'high' : 'medium';
                $recommendations[] = [
                    'type' => 'hidden_fields',
                    'priority' => $priority,
                    'message' => "Preencha {$fieldId} com '{$suggestion}'.",
                    'impact' => 'Melhora a indexação em buscas específicas',
                ];
            }
        } catch (\Throwable $e) {
            error_log('[SEOAudit] Hidden fields analysis failed: ' . $e->getMessage());
        }

        return $recommendations;
    }

    private function mapItemAttributes(array $item): array
    {
        $attributes = $item['attributes'] ?? [];
        $map = [];
        foreach ($attributes as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            $id = $attr['id'] ?? null;
            if (!is_string($id) || $id === '') {
                continue;
            }
            $value = $attr['value_name'] ?? $attr['value_id'] ?? null;
            if (is_string($value) && $value !== '') {
                $map[$id] = $value;
            }
        }

        return $map;
    }

    /**
     * Save audit to database
     */
    private function saveAudit(array $audit): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO seo_audits (
                item_id, account_id, audit_date,
                overall_score, title_score, description_score, attributes_score,
                images_score, pricing_score, category_score,
                required_attributes_pct, optional_attributes_pct, hidden_attributes_pct,
                recommendations, processing_time_ms
            ) VALUES (
                :item_id, :account_id, :audit_date,
                :overall_score, :title_score, :description_score, :attributes_score,
                :images_score, :pricing_score, :category_score,
                :required_attributes_pct, :optional_attributes_pct, :hidden_attributes_pct,
                :recommendations, :processing_time_ms
            )
            ON DUPLICATE KEY UPDATE
                audit_date = VALUES(audit_date),
                overall_score = VALUES(overall_score),
                title_score = VALUES(title_score),
                description_score = VALUES(description_score),
                attributes_score = VALUES(attributes_score),
                images_score = VALUES(images_score),
                pricing_score = VALUES(pricing_score),
                category_score = VALUES(category_score),
                required_attributes_pct = VALUES(required_attributes_pct),
                optional_attributes_pct = VALUES(optional_attributes_pct),
                recommendations = VALUES(recommendations),
                processing_time_ms = VALUES(processing_time_ms)"
        );
        $stmt->execute([
            'item_id' => $audit['item_id'],
            'account_id' => $audit['account_id'],
            'audit_date' => $audit['audit_date'],
            'overall_score' => $audit['overall_score'],
            'title_score' => $audit['scores']['title'],
            'description_score' => $audit['scores']['description'],
            'attributes_score' => $audit['scores']['attributes'],
            'images_score' => $audit['scores']['images'],
            'pricing_score' => $audit['scores']['pricing'],
            'category_score' => $audit['scores']['category'],
            'required_attributes_pct' => $audit['completeness']['required_attributes'],
            'optional_attributes_pct' => $audit['completeness']['optional_attributes'],
            'hidden_attributes_pct' => $audit['completeness']['hidden_attributes'],
            'recommendations' => json_encode($audit['recommendations']),
            'processing_time_ms' => $audit['processing_time_ms'],
        ]);
    }

    /**
     * Get cached audit if available and fresh
     */
    private function getCachedAudit(string $itemId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM seo_audits 
             WHERE item_id = :item_id 
             AND audit_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             ORDER BY audit_date DESC
             LIMIT 1"
        );
        $stmt->execute(['item_id' => $itemId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        $audit = $result;

        return [
            'item_id' => $audit['item_id'],
            'account_id' => $audit['account_id'],
            'audit_date' => $audit['audit_date'],
            'overall_score' => (int)$audit['overall_score'],
            'scores' => [
                'title' => (int)$audit['title_score'],
                'description' => (int)$audit['description_score'],
                'attributes' => (int)$audit['attributes_score'],
                'images' => (int)$audit['images_score'],
                'pricing' => (int)$audit['pricing_score'],
                'category' => (int)$audit['category_score'],
            ],
            'completeness' => [
                'required_attributes' => (int)$audit['required_attributes_pct'],
                'optional_attributes' => (int)$audit['optional_attributes_pct'],
                'hidden_attributes' => (int)$audit['hidden_attributes_pct'],
            ],
            'recommendations' => json_decode($audit['recommendations'], true) ?? [],
            'processing_time_ms' => (int)$audit['processing_time_ms'],
            'cached' => true,
        ];
    }

    /**
     * Get audit history for an item
     */
    public function getAuditHistory(string $itemId, int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare(
            "SELECT audit_date, overall_score, 
                    title_score, description_score, attributes_score,
                    images_score, pricing_score, category_score
             FROM seo_audits 
             WHERE item_id = :item_id 
             ORDER BY audit_date DESC
             LIMIT {$limitSql}"
        );
        $stmt->bindValue(':item_id', $itemId);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
