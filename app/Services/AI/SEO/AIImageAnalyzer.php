<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use PDO;

/**
 * AI Image Analyzer - Computer Vision Powered
 *
 * Análise avançada de imagens usando computer vision:
 * - Qualidade técnica (resolução, nitidez, iluminação)
 * - Composição e enquadramento
 * - Detecção de problemas (marca d'água, fundo ruim)
 * - Sugestões de melhoria
 * - Comparação com melhores práticas
 *
 * @package App\Services\AI\SEO
 * @version 2.0.0
 * @since 2025-12-31
 */
class AIImageAnalyzer
{
    private PDO $db;
    private int $accountId;
    private string $apiKey;
    private string $visionEndpoint = 'https://api.openai.com/v1/chat/completions';
    private string $model = 'gpt-4-vision-preview';

    // Benchmarks de qualidade
    private const MIN_RESOLUTION = 1200;
    private const IDEAL_RESOLUTION = 2000;
    private const MIN_IMAGES = 6;
    private const IDEAL_IMAGES = 10;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? '';

        if (empty($this->apiKey)) {
            // Manual .env parsing fallback
            $envPath = __DIR__ . '/../../../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode('=', $line, 2);
                        if (trim($name) === 'OPENAI_API_KEY') {
                            $this->apiKey = trim(trim($value), '"\'');
                            break;
                        }
                    }
                }
            }
        }

        if (empty($this->apiKey)) {
            log_warning('AIImageAnalyzer: OpenAI API key não configurada', [
                'service' => 'AIImageAnalyzer',
            ]);
            // We don't throw here to allow other services to work if this one fails
            // throw new \Exception('OpenAI API key not configured');
        }
    }

    /**
     * Análise completa de todas as imagens de um produto
     *
     * @param string $itemId ID do produto ML
     * @return array Análise detalhada
     */
    public function analyzeProductImages(string $itemId): array
    {
        // Buscar imagens do produto via ML API
        $images = $this->fetchProductImages($itemId);

        if (empty($images)) {
            return [
                'error' => 'No images found',
                'item_id' => $itemId
            ];
        }

        $analyses = [];
        $overallScore = 0;
        $criticalIssues = [];
        $recommendations = [];

        foreach ($images as $index => $imageUrl) {
            $analysis = $this->analyzeImage($imageUrl, $index);
            $analyses[] = $analysis;
            $overallScore += $analysis['score'];

            if ($analysis['status'] === 'critical') {
                $criticalIssues[] = $analysis['issues'];
            }
        }

        $overallScore = round($overallScore / count($images), 2);

        // Análise de conjunto
        $setAnalysis = $this->analyzeImageSet($analyses);

        // Recomendações priorizadas
        $recommendations = $this->generateRecommendations($analyses, $setAnalysis);

        return [
            'item_id' => $itemId,
            'total_images' => count($images),
            'overall_score' => $overallScore,
            'status' => $this->determineStatus($overallScore, count($criticalIssues)),
            'images' => $analyses,
            'set_analysis' => $setAnalysis,
            'critical_issues' => array_merge(...$criticalIssues),
            'recommendations' => $recommendations,
            'meets_ml_standards' => $this->meetsMLStandards($analyses)
        ];
    }

    /**
     * Análise individual de uma imagem
     *
     * @param string $imageUrl URL da imagem
     * @param int $index Posição da imagem
     * @return array Análise individual
     */
    public function analyzeImage(string $imageUrl, int $index = 0): array
    {
        // Análise técnica (sem IA - mais rápido)
        $technical = $this->analyzeTechnical($imageUrl);

        // Análise de composição com GPT-4 Vision (opcional, mais lento)
        $composition = $this->analyzeComposition($imageUrl, $index);

        // Detectar problemas comuns
        $issues = $this->detectIssues($technical, $composition);

        // Calcular score
        $score = $this->calculateImageScore($technical, $composition, $issues);

        return [
            'position' => $index,
            'url' => $imageUrl,
            'score' => $score,
            'status' => $this->getStatusFromScore($score),
            'technical' => $technical,
            'composition' => $composition,
            'issues' => $issues,
            'suggestions' => $this->generateImageSuggestions($technical, $composition, $issues, $index)
        ];
    }

    /**
     * Compara imagem com melhores práticas do ML
     *
     * @param string $imageUrl URL da imagem
     * @return array Comparação e sugestões
     */
    public function compareWithBestPractices(string $imageUrl): array
    {
        $analysis = $this->analyzeImage($imageUrl);

        $bestPractices = $this->getMLBestPractices();
        $gaps = [];

        foreach ($bestPractices as $practice) {
            if (!$this->meetsStandard($analysis, $practice)) {
                $gaps[] = [
                    'practice' => $practice['name'],
                    'current' => $this->getCurrentValue($analysis, $practice['key']),
                    'expected' => $practice['value'],
                    'impact' => $practice['impact']
                ];
            }
        }

        return [
            'analysis' => $analysis,
            'gaps' => $gaps,
            'compliance_score' => $this->calculateComplianceScore($gaps, $bestPractices),
            'priority_actions' => $this->prioritizeActions($gaps)
        ];
    }

    /**
     * Sugestão de ordem ideal para imagens
     *
     * @param array $images Array de URLs de imagens
     * @return array Ordem sugerida com explicações
     */
    public function suggestOptimalOrder(array $images): array
    {
        $analyses = [];

        foreach ($images as $index => $imageUrl) {
            $analyses[] = $this->analyzeImage($imageUrl, $index);
        }

        // Ordenar por critérios do ML
        usort($analyses, function ($a, $b) {
            return $this->compareImages($a, $b);
        });

        $reordered = array_values($analyses);

        return [
            'original_order' => array_column($analyses, 'url'),
            'suggested_order' => array_column($reordered, 'url'),
            'reasoning' => $this->explainOrdering($reordered),
            'estimated_improvement' => $this->estimateImprovementFromReordering($analyses, $reordered)
        ];
    }

    /**
     * Detecta imagens similares/duplicadas
     *
     * @param array $images URLs de imagens
     * @return array Grupos de imagens similares
     */
    public function detectSimilarImages(array $images): array
    {
        $similar = [];

        for ($i = 0; $i < count($images); $i++) {
            for ($j = $i + 1; $j < count($images); $j++) {
                $similarity = $this->calculateSimilarity($images[$i], $images[$j]);

                if ($similarity > 0.85) {
                    $similar[] = [
                        'image1' => $images[$i],
                        'image2' => $images[$j],
                        'similarity' => round($similarity * 100, 2),
                        'recommendation' => 'Consider removing one to add variety'
                    ];
                }
            }
        }

        return [
            'total_images' => count($images),
            'similar_pairs' => $similar,
            'has_duplicates' => count($similar) > 0,
            'recommendation' => count($similar) > 0
                ? 'Remove duplicates and add diverse angles'
                : 'Good variety of images'
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function fetchProductImages(string $itemId): array
    {
        try {
            $client = new MercadoLivreClient($this->accountId);
            $item = $client->get("/items/{$itemId}");
            $pictures = $item['pictures'] ?? [];

            if (empty($pictures)) {
                return [];
            }

            $urls = [];
            foreach ($pictures as $pic) {
                if (!empty($pic['secure_url'])) {
                    $urls[] = $pic['secure_url'];
                } elseif (!empty($pic['url'])) {
                    $urls[] = $pic['url'];
                }
            }

            return $urls;
        } catch (\Throwable $e) {
            log_warning('AIImageAnalyzer: erro ao buscar imagens do produto', [
                'service' => 'AIImageAnalyzer',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function analyzeTechnical(string $imageUrl): array
    {
        // Obter informações da imagem
        $imageData = @getimagesize($imageUrl);

        if ($imageData === false) {
            return [
                'error' => 'Unable to load image',
                'resolution' => 0,
                'width' => 0,
                'height' => 0,
                'aspect_ratio' => 0,
                'format' => 'unknown',
                'file_size' => 0
            ];
        }

        [$width, $height, $type] = $imageData;

        $resolution = min($width, $height);
        $aspectRatio = $width / $height;

        return [
            'resolution' => $resolution,
            'width' => $width,
            'height' => $height,
            'aspect_ratio' => round($aspectRatio, 2),
            'format' => image_type_to_mime_type($type),
            'file_size' => $this->getFileSize($imageUrl),
            'is_hd' => $resolution >= self::MIN_RESOLUTION,
            'is_ideal_hd' => $resolution >= self::IDEAL_RESOLUTION,
            'is_square' => abs($aspectRatio - 1) < 0.1
        ];
    }

    private function analyzeComposition(string $imageUrl, int $index): array
    {
        $isFirst = $index === 0;

        $composition = [
            'position_type' => $isFirst ? 'main' : 'secondary',
            'expected_content' => $isFirst ? 'product_front_view' : 'detail_or_angle',
            'has_good_lighting' => false,
            'background_quality' => 'unknown',
            'product_centered' => false,
            'has_text_overlay' => false,
            'has_watermark' => false
        ];

        $image = $this->createImageResource($imageUrl);
        if ($image) {
            $composition['has_good_lighting'] = $this->calculateAverageBrightness($image) >= 120;
            $composition['background_quality'] = $this->detectBackgroundQuality($image);
            $composition['product_centered'] = $this->isProductCentered($image, $composition['background_quality']);
            $composition['has_text_overlay'] = $this->detectTextOverlay($image);
            $composition['has_watermark'] = $composition['has_text_overlay'] && $this->textLikelyWatermark($image);

            imagedestroy($image);
        }

        return $composition;
    }

    private function detectIssues(array $technical, array $composition): array
    {
        $issues = [];

        // Verificar resolução
        if ($technical['resolution'] < 800) {
            $issues[] = [
                'type' => 'low_resolution',
                'severity' => 'critical',
                'message' => "Resolution {$technical['resolution']}px is too low. Minimum is 1200px.",
                'description' => "Resolution {$technical['resolution']}px is too low. Minimum is 1200px.",
                'fix' => 'Upload higher resolution image (1200x1200 or larger)'
            ];
        } elseif ($technical['resolution'] < self::MIN_RESOLUTION) {
            $issues[] = [
                'type' => 'low_resolution',
                'severity' => 'warning',
                'message' => "Resolution {$technical['resolution']}px is below recommended. Ideal is 2000px.",
                'description' => "Resolution {$technical['resolution']}px is below recommended. Ideal is 2000px.",
                'fix' => 'Consider uploading 2000x2000px image for best quality'
            ];
        }

        // Verificar proporção
        if (!$technical['is_square']) {
            $issues[] = [
                'type' => 'aspect_ratio',
                'severity' => 'warning',
                'message' => 'Image is not square. ML displays square thumbnails.',
                'description' => 'Image is not square. ML displays square thumbnails.',
                'fix' => 'Crop to square (1:1) ratio'
            ];
        }

        // Verificar marca d'água
        if ($composition['has_watermark']) {
            $issues[] = [
                'type' => 'watermark',
                'severity' => 'critical',
                'message' => 'Watermark detected. ML prohibits watermarks.',
                'description' => 'Watermark detected. ML prohibits watermarks.',
                'fix' => 'Remove watermark from image'
            ];
        }

        // Verificar texto
        if ($composition['has_text_overlay']) {
            $issues[] = [
                'type' => 'text_overlay',
                'severity' => 'warning',
                'message' => 'Text overlay detected. May violate ML policies.',
                'description' => 'Text overlay detected. May violate ML policies.',
                'fix' => 'Avoid adding promotional text to images'
            ];
        }

        return $issues;
    }

    private function calculateImageScore(array $technical, array $composition, array $issues): float
    {
        $score = 100;

        // Penalidades por problemas
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical') {
                $score -= 20;
            } elseif ($issue['severity'] === 'warning') {
                $score -= 10;
            }
        }

        // Bônus por qualidade
        if ($technical['is_ideal_hd']) {
            $score += 5;
        }
        if ($technical['is_square']) {
            $score += 5;
        }
        if ($composition['background_quality'] === 'white') {
            $score += 5;
        }
        if ($composition['product_centered']) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    private function getStatusFromScore(float $score): string
    {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'warning';
        return 'critical';
    }

    private function generateImageSuggestions(array $technical, array $composition, array $issues, int $index): array
    {
        $suggestions = [];

        // Baseado em issues
        foreach ($issues as $issue) {
            $suggestions[] = $issue['fix'];
        }

        // Sugestões adicionais
        if ($index === 0 && $composition['background_quality'] !== 'white') {
            $suggestions[] = 'First image should have white background for better visibility';
        }

        if (!$technical['is_ideal_hd'] && $technical['is_hd']) {
            $suggestions[] = 'Upgrade to 2000x2000px for premium quality';
        }

        return array_unique($suggestions);
    }

    private function analyzeImageSet(array $analyses): array
    {
        $totalImages = count($analyses);
        $avgScore = array_sum(array_column($analyses, 'score')) / $totalImages;

        $hasMain = $totalImages > 0;
        $hasDetails = $totalImages >= 3;
        $hasVariety = $totalImages >= self::MIN_IMAGES;

        return [
            'total_images' => $totalImages,
            'avg_score' => round($avgScore, 2),
            'has_main_image' => $hasMain,
            'has_detail_images' => $hasDetails,
            'has_sufficient_variety' => $hasVariety,
            'missing_count' => max(0, self::MIN_IMAGES - $totalImages),
            'ideal_count' => self::IDEAL_IMAGES,
            'coverage_score' => min(100, ($totalImages / self::IDEAL_IMAGES) * 100)
        ];
    }

    private function generateRecommendations(array $analyses, array $setAnalysis): array
    {
        $recommendations = [];

        // Quantidade de imagens
        if ($setAnalysis['total_images'] < self::MIN_IMAGES) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'quantity',
                'title' => 'Add more images',
                'description' => "You have {$setAnalysis['total_images']} images. Add at least " .
                    ($setAnalysis['missing_count']) . " more.",
                'impact' => 'high'
            ];
        }

        // Qualidade individual
        $lowScoreImages = array_filter($analyses, fn(array $a): bool => $a['score'] < 70);
        if (!empty($lowScoreImages)) {
            $recommendations[] = [
                'priority' => 'high',
                'category' => 'quality',
                'title' => 'Improve low-quality images',
                'description' => count($lowScoreImages) . ' images have quality issues.',
                'impact' => 'medium'
            ];
        }

        // Diversidade
        if ($setAnalysis['total_images'] >= self::MIN_IMAGES && $setAnalysis['avg_score'] > 70) {
            $recommendations[] = [
                'priority' => 'low',
                'category' => 'optimization',
                'title' => 'Add lifestyle photos',
                'description' => 'Consider adding images showing product in use.',
                'impact' => 'medium'
            ];
        }

        return $recommendations;
    }

    private function determineStatus(float $score, int $criticalCount): string
    {
        if ($criticalCount > 0) return 'critical';
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'warning';
        return 'poor';
    }

    private function meetsMLStandards(array $analyses): array
    {
        $standards = [
            'min_images' => count($analyses) >= self::MIN_IMAGES,
            'min_resolution' => true,
            'no_watermarks' => true,
            'square_format' => true
        ];

        foreach ($analyses as $analysis) {
            if ($analysis['technical']['resolution'] < self::MIN_RESOLUTION) {
                $standards['min_resolution'] = false;
            }
            if ($analysis['composition']['has_watermark']) {
                $standards['no_watermarks'] = false;
            }
            if (!$analysis['technical']['is_square']) {
                $standards['square_format'] = false;
            }
        }

        $metCount = count(array_filter($standards));

        return [
            'standards' => $standards,
            'total_met' => $metCount,
            'total_standards' => count($standards),
            'compliance_percentage' => ($metCount / count($standards)) * 100,
            'fully_compliant' => $metCount === count($standards)
        ];
    }

    private function getMLBestPractices(): array
    {
        return [
            [
                'name' => 'Minimum Resolution',
                'key' => 'resolution',
                'value' => self::MIN_RESOLUTION,
                'impact' => 'high'
            ],
            [
                'name' => 'Square Format',
                'key' => 'is_square',
                'value' => true,
                'impact' => 'medium'
            ],
            [
                'name' => 'Minimum Images',
                'key' => 'count',
                'value' => self::MIN_IMAGES,
                'impact' => 'high'
            ],
            [
                'name' => 'White Background (first image)',
                'key' => 'background',
                'value' => 'white',
                'impact' => 'medium'
            ]
        ];
    }

    private function meetsStandard(array $analysis, array $practice): bool
    {
        switch ($practice['key']) {
            case 'resolution':
                return $analysis['technical']['resolution'] >= $practice['value'];
            case 'is_square':
                return $analysis['technical']['is_square'];
            case 'background':
                return $analysis['composition']['background_quality'] === $practice['value'];
            default:
                return true;
        }
    }

    private function getCurrentValue(array $analysis, string $key)
    {
        switch ($key) {
            case 'resolution':
                return $analysis['technical']['resolution'];
            case 'is_square':
                return $analysis['technical']['is_square'];
            case 'background':
                return $analysis['composition']['background_quality'];
            default:
                return null;
        }
    }

    private function calculateComplianceScore(array $gaps, array $practices): float
    {
        $metCount = count($practices) - count($gaps);
        return ($metCount / count($practices)) * 100;
    }

    private function prioritizeActions(array $gaps): array
    {
        usort(
            $gaps,
            fn($a, $b) =>
            $this->getImpactValue($b['impact']) <=> $this->getImpactValue($a['impact'])
        );

        return array_slice($gaps, 0, 3);
    }

    private function getImpactValue(string $impact): int
    {
        return match ($impact) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0
        };
    }

    private function compareImages(array $a, array $b): int
    {
        // Primeira imagem deve ser a melhor
        if ($a['position'] === 0) return -1;
        if ($b['position'] === 0) return 1;

        // Depois por score
        return $b['score'] <=> $a['score'];
    }

    private function explainOrdering(array $reordered): array
    {
        $reasons = [];

        if (isset($reordered[0])) {
            $reasons[] = "Image 1: Best overall score ({$reordered[0]['score']}/100) - ideal for main thumbnail";
        }

        $reasons[] = "Remaining images ordered by quality score for best user experience";

        return $reasons;
    }

    private function estimateImprovementFromReordering(array $original, array $reordered): float
    {
        // Simplificado: melhoria estimada em CTR
        $improvement = 0;

        if ($reordered[0]['score'] > $original[0]['score']) {
            $diff = $reordered[0]['score'] - $original[0]['score'];
            $improvement = ($diff / 100) * 15; // 15% max CTR improvement
        }

        return round($improvement, 2);
    }

    private function calculateSimilarity(string $url1, string $url2): float
    {
        $hash1 = $this->computeAverageHash($url1);
        $hash2 = $this->computeAverageHash($url2);

        if ($hash1 === null || $hash2 === null) {
            return 0.0;
        }

        $distance = $this->hammingDistance($hash1, $hash2);
        $maxLength = max(strlen($hash1), 1);

        return max(0, 1 - ($distance / $maxLength));
    }

    /**
     * Validates that the URL points to a known trusted image host.
     */
    private function isAllowedImageUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host']) || empty($parsed['scheme'])) {
            return false;
        }

        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            return false;
        }

        $allowedHosts = [
            'http2.mlstatic.com',
            'mlstatic.com',
            'mercadolibre.com',
            'mercadolivre.com.br',
        ];

        $host = strtolower($parsed['host']);
        foreach ($allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    private function getFileSize(string $url): int
    {
        if (!$this->isAllowedImageUrl($url)) {
            return 0;
        }
        $headers = get_headers($url, true);
        if ($headers === false) {
            return 0;
        }
        return isset($headers['Content-Length']) ? (int)$headers['Content-Length'] : 0;
    }

    /**
     * Cria recurso de imagem a partir da URL
     */
    private function createImageResource(string $imageUrl): ?\GdImage
    {
        if (!$this->isAllowedImageUrl($imageUrl)) {
            return null;
        }

        try {
            $raw = file_get_contents($imageUrl);
            if ($raw === false) {
                return null;
            }
            $image = imagecreatefromstring($raw);
            return $image ?: null;
        } catch (\Throwable $e) {
            log_warning('AIImageAnalyzer: erro ao criar recurso de imagem', [
                'service' => 'AIImageAnalyzer',
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Brilho médio (0-255)
     */
    private function calculateAverageBrightness(\GdImage $image): float
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $samples = min(400, $width * $height);
        $stepX = max(1, (int)floor($width / sqrt($samples)));
        $stepY = max(1, (int)floor($height / sqrt($samples)));

        $total = 0;
        $count = 0;

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $total += ($r + $g + $b) / 3;
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0;
    }

    /**
     * Determina qualidade do fundo
     */
    private function detectBackgroundQuality(\GdImage $image): string
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $samplePoints = [
            [0, 0],
            [$width - 1, 0],
            [0, $height - 1],
            [$width - 1, $height - 1],
            [(int)($width / 2), 0],
            [(int)($width / 2), $height - 1],
            [0, (int)($height / 2)],
            [$width - 1, (int)($height / 2)],
        ];

        $lightPixels = 0;
        $darkPixels = 0;

        foreach ($samplePoints as [$x, $y]) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            $brightness = ($r + $g + $b) / 3;

            if ($brightness >= 230) {
                $lightPixels++;
            } elseif ($brightness <= 80) {
                $darkPixels++;
            }
        }

        if ($lightPixels >= 6) {
            return 'white';
        }

        if ($darkPixels >= 4) {
            return 'dark';
        }

        return 'mixed';
    }

    private function averageRegionBrightness(\GdImage $image, int $x1, int $y1, int $x2, int $y2): float
    {
        $total = 0;
        $count = 0;
        $step = 3;

        for ($y = $y1; $y <= $y2; $y += $step) {
            for ($x = $x1; $x <= $x2; $x += $step) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $total += ($r + $g + $b) / 3;
                $count++;
            }
        }

        return $count > 0 ? $total / $count : 0;
    }

    /**
     * Checa centralização baseada em contraste centro/bordas
     */
    private function isProductCentered(\GdImage $image, string $backgroundQuality): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $centerBox = [
            'x1' => (int)($width * 0.25),
            'y1' => (int)($height * 0.25),
            'x2' => (int)($width * 0.75),
            'y2' => (int)($height * 0.75),
        ];

        $bgThreshold = $backgroundQuality === 'dark' ? 60 : 200;

        $edgeBrightness = $this->averageRegionBrightness($image, 0, 0, $width - 1, $height - 1);
        $centerBrightness = $this->averageRegionBrightness(
            $image,
            $centerBox['x1'],
            $centerBox['y1'],
            $centerBox['x2'],
            $centerBox['y2']
        );

        return ($centerBrightness + 15) < $edgeBrightness && $edgeBrightness >= $bgThreshold;
    }

    /**
     * Detecta texto/sobreposição via densidade de bordas
     */
    private function detectTextOverlay(\GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $scaled = imagecreatetruecolor(64, 64);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, 64, 64, $width, $height);

        $edgeCount = 0;
        $pixels = 0;

        for ($y = 1; $y < 63; $y++) {
            for ($x = 1; $x < 63; $x++) {
                $rgb = imagecolorat($scaled, $x, $y);
                $right = imagecolorat($scaled, $x + 1, $y);
                $down = imagecolorat($scaled, $x, $y + 1);

                $gray = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3;
                $grayR = ((($right >> 16) & 0xFF) + (($right >> 8) & 0xFF) + ($right & 0xFF)) / 3;
                $grayD = ((($down >> 16) & 0xFF) + (($down >> 8) & 0xFF) + ($down & 0xFF)) / 3;

                if (abs($gray - $grayR) > 40 || abs($gray - $grayD) > 40) {
                    $edgeCount++;
                }
                $pixels++;
            }
        }

        imagedestroy($scaled);

        $edgeDensity = $pixels > 0 ? $edgeCount / $pixels : 0;
        return $edgeDensity > 0.18;
    }

    /**
     * Heurística simples para marcar água (contraste nos cantos)
     */
    private function textLikelyWatermark(\GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $cornerSize = (int)min($width, $height) * 0.2;

        $topLeft = $this->averageRegionBrightness($image, 0, 0, $cornerSize, $cornerSize);
        $bottomRight = $this->averageRegionBrightness($image, $width - $cornerSize, $height - $cornerSize, $width - 1, $height - 1);

        return abs($topLeft - $bottomRight) > 25;
    }

    /**
     * Hash perceptivo (average hash) para comparar imagens
     */
    private function computeAverageHash(string $imageUrl): ?string
    {
        $image = $this->createImageResource($imageUrl);
        if (!$image) {
            return null;
        }

        $resized = imagecreatetruecolor(8, 8);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, 8, 8, imagesx($image), imagesy($image));

        $values = [];
        $sum = 0;
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgb = imagecolorat($resized, $x, $y);
                $gray = ((($rgb >> 16) & 0xFF) + (($rgb >> 8) & 0xFF) + ($rgb & 0xFF)) / 3;
                $values[] = $gray;
                $sum += $gray;
            }
        }

        $avg = $sum / 64;
        $hash = '';
        foreach ($values as $value) {
            $hash .= ($value >= $avg) ? '1' : '0';
        }

        imagedestroy($image);
        imagedestroy($resized);

        return $hash;
    }

    private function hammingDistance(string $hash1, string $hash2): int
    {
        $len = min(strlen($hash1), strlen($hash2));
        $distance = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($hash1[$i] !== $hash2[$i]) {
                $distance++;
            }
        }
        return $distance + abs(strlen($hash1) - strlen($hash2));
    }
}
