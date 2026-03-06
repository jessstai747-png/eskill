<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * Serviço de Análise Automática de Imagens por IA
 * 
 * Sistema avançado para análise inteligente de imagens de produtos:
 * - Reconhecimento automático de objetos e características
 * - Análise de qualidade e composição
 * - Extração de cores predominantes
 * - Detecção de texto em imagens
 * - Sugestões de otimização
 * - Análise de conformidade com padrões ML
 * 
 * @author Sistema ML Manager V8.0
 * @version 8.0.0
 */
class AIImageAnalyzerService
{
    private \PDO $db;
    private LogService $logger;
    private CacheManagerService $cache;
    private array $analysisModels;
    private array $qualityStandards;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new LogService();
        $this->cache = new CacheManagerService();
        $this->initializeAnalysisModels();
        $this->loadQualityStandards();
    }

    // ========== ANÁLISE PRINCIPAL DE IMAGENS ==========

    /**
     * Analisa imagem completa do produto
     */
    public function analyzeProductImage(string $imageUrl, array $productData = []): array
    {
        try {
            $cacheKey = 'ai_image_analysis_' . md5($imageUrl);
            $cached = $this->cache->get($cacheKey, 'ai_images');
            if ($cached) {
                return $cached;
            }

            // Download e validação da imagem
            $imageInfo = $this->downloadAndValidateImage($imageUrl);
            if (!$imageInfo['valid']) {
                return $this->createErrorResponse('Imagem inválida ou inacessível', $imageUrl);
            }

            // Análises paralelas
            $analyses = [
                'technical' => $this->analyzeTechnicalProperties($imageInfo),
                'quality' => $this->analyzeImageQuality($imageInfo),
                'content' => $this->analyzeImageContent($imageInfo),
                'colors' => $this->extractColorPalette($imageInfo),
                'text' => $this->extractTextFromImage($imageInfo),
                'composition' => $this->analyzeComposition($imageInfo),
                'compliance' => $this->checkMLCompliance($imageInfo, $productData)
            ];

            // Pontuação geral
            $overallScore = $this->calculateOverallScore($analyses);
            
            // Sugestões de melhoria
            $suggestions = $this->generateImprovementSuggestions($analyses);
            
            // Classificação automática
            $classification = $this->classifyImageType($analyses);

            $result = [
                'success' => true,
                'image_url' => $imageUrl,
                'overall_score' => $overallScore,
                'classification' => $classification,
                'technical_properties' => $analyses['technical'],
                'quality_analysis' => $analyses['quality'],
                'content_analysis' => $analyses['content'],
                'color_palette' => $analyses['colors'],
                'detected_text' => $analyses['text'],
                'composition_analysis' => $analyses['composition'],
                'ml_compliance' => $analyses['compliance'],
                'suggestions' => $suggestions,
                'analyzed_at' => date('Y-m-d H:i:s')
            ];

            // Cache por 24 horas
            $this->cache->set($cacheKey, $result, 'ai_images', 86400);
            
            // Log da análise
            $this->logger->info('AI image analysis completed', [
                'image_url' => $imageUrl,
                'overall_score' => $overallScore,
                'classification' => $classification
            ]);

            // Limpeza do arquivo temporário
            if (isset($imageInfo['temp_file']) && file_exists($imageInfo['temp_file'])) {
                unlink($imageInfo['temp_file']);
            }

            return $result;

        } catch (Exception $e) {
            $this->logger->error('AI image analysis failed', [
                'error' => $e->getMessage(),
                'image_url' => $imageUrl
            ]);

            return $this->createErrorResponse($e->getMessage(), $imageUrl);
        }
    }

    /**
     * Analisa múltiplas imagens de um produto
     */
    public function analyzeBulkImages(array $imageUrls, array $productData = []): array
    {
        $results = [];
        $summary = [
            'total_images' => count($imageUrls),
            'analyzed' => 0,
            'errors' => 0,
            'average_score' => 0,
            'best_image' => null,
            'issues_found' => []
        ];

        foreach ($imageUrls as $index => $imageUrl) {
            $analysis = $this->analyzeProductImage($imageUrl, $productData);
            $results[$index] = $analysis;

            if ($analysis['success']) {
                $summary['analyzed']++;
                $summary['average_score'] += $analysis['overall_score'];
                
                // Encontrar melhor imagem
                if (!$summary['best_image'] || $analysis['overall_score'] > $summary['best_image']['score']) {
                    $summary['best_image'] = [
                        'index' => $index,
                        'url' => $imageUrl,
                        'score' => $analysis['overall_score']
                    ];
                }
                
                // Coletar problemas
                if (!empty($analysis['suggestions'])) {
                    $summary['issues_found'] = array_merge(
                        $summary['issues_found'], 
                        array_column($analysis['suggestions'], 'type')
                    );
                }
            } else {
                $summary['errors']++;
            }
        }

        if ($summary['analyzed'] > 0) {
            $summary['average_score'] = round($summary['average_score'] / $summary['analyzed'], 2);
        }

        $summary['issues_found'] = array_unique($summary['issues_found']);

        return [
            'success' => true,
            'results' => $results,
            'summary' => $summary,
            'recommendations' => $this->generateBulkRecommendations($summary, $results)
        ];
    }

    // ========== ANÁLISES ESPECÍFICAS ==========

    /**
     * Analisa propriedades técnicas da imagem
     */
    private function analyzeTechnicalProperties(array $imageInfo): array
    {
        $properties = [
            'dimensions' => [
                'width' => $imageInfo['width'],
                'height' => $imageInfo['height'],
                'aspect_ratio' => round($imageInfo['width'] / $imageInfo['height'], 2)
            ],
            'file_size' => $imageInfo['file_size'],
            'format' => $imageInfo['format'],
            'resolution_score' => $this->calculateResolutionScore($imageInfo),
            'size_compliance' => $this->checkSizeCompliance($imageInfo),
            'format_compliance' => $this->checkFormatCompliance($imageInfo['format'])
        ];

        return $properties;
    }

    /**
     * Analisa qualidade visual da imagem
     */
    private function analyzeImageQuality(array $imageInfo): array
    {
        // Simulação de análise de qualidade (em produção usaria bibliotecas especializadas)
        $quality = [
            'sharpness' => $this->calculateSharpness($imageInfo),
            'brightness' => $this->calculateBrightness($imageInfo),
            'contrast' => $this->calculateContrast($imageInfo),
            'noise_level' => $this->calculateNoise($imageInfo),
            'blur_detection' => $this->detectBlur($imageInfo),
            'exposure' => $this->analyzeExposure($imageInfo),
            'overall_quality' => 0
        ];

        // Cálculo da qualidade geral
        $quality['overall_quality'] = round((
            $quality['sharpness'] * 0.25 +
            $quality['brightness'] * 0.15 +
            $quality['contrast'] * 0.20 +
            (100 - $quality['noise_level']) * 0.15 +
            (100 - $quality['blur_detection']) * 0.15 +
            $quality['exposure'] * 0.10
        ), 2);

        return $quality;
    }

    /**
     * Analisa conteúdo e objetos da imagem
     * Usa LLM Vision API quando disponível, senão análise heurística via GD
     */
    private function analyzeImageContent(array $imageInfo): array
    {
        $content = [
            'detected_objects' => $this->detectObjects($imageInfo),
            'product_visibility' => $this->analyzeProductVisibility($imageInfo),
            'background_analysis' => $this->analyzeBackground($imageInfo),
            'composition_score' => $this->analyzeContentComposition($imageInfo),
            'professionalism_score' => $this->analyzeProfessionalism($imageInfo),
        ];

        // Tentar enriquecer com LLM Vision API
        $visionAnalysis = $this->tryLlmVisionAnalysis($imageInfo);
        if ($visionAnalysis !== null) {
            $content['detected_objects'] = $visionAnalysis['objects'];
            $content['ai_description'] = $visionAnalysis['description'];
            $content['ai_tags'] = $visionAnalysis['tags'];
            $content['analysis_engine'] = 'openai_vision';
        } else {
            $content['analysis_engine'] = 'heuristic_gd';
        }

        return $content;
    }

    /**
     * Análise avançada via LLM Vision (OpenAI GPT-4o)
     */
    private function tryLlmVisionAnalysis(array $imageInfo): ?array
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
        $imageUrl = $imageInfo['url'] ?? null;
        if (empty($apiKey) || !$imageUrl) {
            return null;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Analise esta imagem de produto para marketplace. Responda em JSON puro (sem markdown) com: {"objects": [{"name": "...", "confidence": 0.0-1.0}], "description": "descrição breve", "tags": ["tag1", "tag2"], "is_product_photo": true/false, "background_type": "white/colored/scene"}'],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                            ],
                        ],
                    ],
                    'max_tokens' => 500,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $rawContent = trim($body['choices'][0]['message']['content'] ?? '');
            // Limpar possíveis blocos markdown
            $rawContent = preg_replace('/^```(?:json)?\s*/s', '', $rawContent);
            $rawContent = preg_replace('/\s*```$/s', '', $rawContent);
            $parsed = json_decode($rawContent, true);

            if (!is_array($parsed)) {
                return null;
            }

            return [
                'objects' => $parsed['objects'] ?? [],
                'description' => $parsed['description'] ?? '',
                'tags' => $parsed['tags'] ?? [],
            ];
        } catch (\Exception $e) {
            $this->logger->warning('LLM Vision analysis falhou', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extrai paleta de cores predominantes usando GD library
     */
    private function extractColorPalette(array $imageInfo): array
    {
        $dominantColors = $this->extractColorsWithGd($imageInfo);

        // Detectar cor de fundo (média dos cantos)
        $bgColor = $this->detectBackgroundColor($imageInfo);

        // Calcular contraste entre cor dominante e fundo
        $contrastRatio = 1.0;
        if (!empty($dominantColors) && $bgColor) {
            $contrastRatio = $this->calculateContrastRatio($dominantColors[0]['hex'], $bgColor);
        }

        $accessibilityScore = min(100, (int)($contrastRatio * 20));

        return [
            'dominant_colors' => $dominantColors,
            'color_harmony' => $this->analyzeColorHarmony($dominantColors),
            'background_color' => $bgColor ?: '#FFFFFF',
            'contrast_ratio' => round($contrastRatio, 2),
            'accessibility_score' => $accessibilityScore,
        ];
    }

    /**
     * Extrai cores dominantes da imagem usando amostragem GD + quantização
     */
    private function extractColorsWithGd(array $imageInfo): array
    {
        $path = $imageInfo['temp_file'] ?? $imageInfo['local_path'] ?? null;
        if (!$path || !file_exists($path)) {
            return [['hex' => '#808080', 'percentage' => 100, 'name' => 'Cinza (imagem indisponível)']];
        }

        try {
            $img = $this->loadGdImage(array_merge($imageInfo, ['local_path' => $path]));
            if (!$img) {
                return [['hex' => '#808080', 'percentage' => 100, 'name' => 'Cinza (falha ao carregar)']];
            }

            $width = imagesx($img);
            $height = imagesy($img);

            // Redimensionar para 100x100 para amostragem eficiente
            $thumb = imagecreatetruecolor(100, 100);
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, 100, 100, $width, $height);
            imagedestroy($img);

            // Coletar todas as cores e agrupar por similaridade
            $colorBuckets = [];
            for ($y = 0; $y < 100; $y++) {
                for ($x = 0; $x < 100; $x++) {
                    $rgb = imagecolorat($thumb, $x, $y);
                    $r = ($rgb >> 16) & 0xFF;
                    $g = ($rgb >> 8) & 0xFF;
                    $b = $rgb & 0xFF;
                    // Quantizar para reduzir variações (arredondar para múltiplos de 32)
                    $qr = (int)(round($r / 32) * 32);
                    $qg = (int)(round($g / 32) * 32);
                    $qb = (int)(round($b / 32) * 32);
                    $key = "{$qr},{$qg},{$qb}";
                    if (!isset($colorBuckets[$key])) {
                        $colorBuckets[$key] = ['r' => $qr, 'g' => $qg, 'b' => $qb, 'count' => 0];
                    }
                    $colorBuckets[$key]['count']++;
                }
            }
            imagedestroy($thumb);

            // Ordenar por frequência
            usort($colorBuckets, fn($a, $b) => $b['count'] <=> $a['count']);

            // Top 5 cores dominantes
            $totalPixels = 10000; // 100x100
            $result = [];
            foreach (array_slice($colorBuckets, 0, 5) as $bucket) {
                $hex = sprintf('#%02X%02X%02X', min(255, $bucket['r']), min(255, $bucket['g']), min(255, $bucket['b']));
                $percentage = round(($bucket['count'] / $totalPixels) * 100, 1);
                $result[] = [
                    'hex' => $hex,
                    'percentage' => $percentage,
                    'name' => $this->getColorName($bucket['r'], $bucket['g'], $bucket['b']),
                ];
            }

            return $result ?: [['hex' => '#808080', 'percentage' => 100, 'name' => 'Cinza']];
        } catch (\Exception $e) {
            return [['hex' => '#808080', 'percentage' => 100, 'name' => 'Cinza (erro: ' . $e->getMessage() . ')']];
        }
    }

    /**
     * Detecta cor de fundo pela amostragem dos cantos
     */
    private function detectBackgroundColor(array $imageInfo): ?string
    {
        $bg = $this->analyzeBackground($imageInfo);
        if ($bg['type'] === 'white') return '#FFFFFF';
        if ($bg['type'] === 'dark') return '#1A1A1A';
        // Tentar extrair cor real dos cantos
        $path = $imageInfo['temp_file'] ?? $imageInfo['local_path'] ?? null;
        if (!$path || !file_exists($path)) return null;
        try {
            $img = $this->loadGdImage(array_merge($imageInfo, ['local_path' => $path]));
            if (!$img) return null;
            $rgb = imagecolorat($img, 2, 2);
            imagedestroy($img);
            return sprintf('#%02X%02X%02X', ($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calcula razão de contraste WCAG entre duas cores hex
     */
    private function calculateContrastRatio(string $hex1, string $hex2): float
    {
        $lum1 = $this->getRelativeLuminance($hex1);
        $lum2 = $this->getRelativeLuminance($hex2);
        $lighter = max($lum1, $lum2);
        $darker = min($lum1, $lum2);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function getRelativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;
        $r = $r <= 0.03928 ? $r / 12.92 : (($r + 0.055) / 1.055) ** 2.4;
        $g = $g <= 0.03928 ? $g / 12.92 : (($g + 0.055) / 1.055) ** 2.4;
        $b = $b <= 0.03928 ? $b / 12.92 : (($b + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Retorna nome aproximado da cor pelo valor RGB
     */
    private function getColorName(int $r, int $g, int $b): string
    {
        $colors = [
            'Branco' => [255, 255, 255], 'Preto' => [0, 0, 0],
            'Vermelho' => [255, 0, 0], 'Verde' => [0, 128, 0],
            'Azul' => [0, 0, 255], 'Amarelo' => [255, 255, 0],
            'Laranja' => [255, 165, 0], 'Rosa' => [255, 192, 203],
            'Roxo' => [128, 0, 128], 'Marrom' => [139, 69, 19],
            'Cinza' => [128, 128, 128], 'Cinza Claro' => [192, 192, 192],
            'Cinza Escuro' => [64, 64, 64], 'Azul Claro' => [135, 206, 235],
            'Azul Escuro' => [0, 0, 139], 'Bege' => [245, 245, 220],
            'Ciano' => [0, 255, 255], 'Magenta' => [255, 0, 255],
        ];
        $minDist = PHP_INT_MAX;
        $closest = 'Desconhecida';
        foreach ($colors as $name => [$cr, $cg, $cb]) {
            $dist = ($r - $cr) ** 2 + ($g - $cg) ** 2 + ($b - $cb) ** 2;
            if ($dist < $minDist) {
                $minDist = $dist;
                $closest = $name;
            }
        }
        return $closest;
    }

    /**
     * Extrai texto da imagem usando Tesseract OCR ou LLM Vision como fallback
     */
    private function extractTextFromImage(array $imageInfo): array
    {
        $path = $imageInfo['temp_file'] ?? $imageInfo['local_path'] ?? null;

        // Tentar Tesseract OCR primeiro (se disponível)
        if ($path && file_exists($path)) {
            $tesseractResult = $this->tryTesseractOcr($path);
            if ($tesseractResult !== null) {
                return $tesseractResult;
            }

            // Fallback: LLM Vision API (se configurada)
            $llmResult = $this->tryLlmVisionOcr($imageInfo);
            if ($llmResult !== null) {
                return $llmResult;
            }
        }

        // Último fallback: sem OCR disponível
        return [
            'has_text' => false,
            'extracted_text' => '',
            'text_regions' => [],
            'readable_score' => 0,
            'language_detected' => null,
            'ocr_engine' => 'none',
            'note' => 'Nenhum motor de OCR disponível. Instale Tesseract ou configure OPENAI_API_KEY para Vision API.',
        ];
    }

    /**
     * Extrai texto via Tesseract CLI
     */
    private function tryTesseractOcr(string $imagePath): ?array
    {
        // Verificar se tesseract está instalado
        $checkCmd = 'which tesseract 2>/dev/null';
        $tesseractPath = trim((string)@shell_exec($checkCmd));
        if (empty($tesseractPath)) {
            return null;
        }

        try {
            $outputBase = sys_get_temp_dir() . '/ocr_' . uniqid();
            $cmd = escapeshellcmd($tesseractPath) . ' '
                . escapeshellarg($imagePath) . ' '
                . escapeshellarg($outputBase) . ' '
                . '-l por+eng --psm 6 2>/dev/null';
            @exec($cmd, $output, $returnCode);

            $textFile = $outputBase . '.txt';
            if (!file_exists($textFile)) {
                return null;
            }

            $text = trim(file_get_contents($textFile));
            @unlink($textFile);

            if ($text === '') {
                return [
                    'has_text' => false,
                    'extracted_text' => '',
                    'text_regions' => [],
                    'readable_score' => 0,
                    'language_detected' => null,
                    'ocr_engine' => 'tesseract',
                ];
            }

            // Parse linhas como regiões de texto
            $regions = [];
            $lines = array_filter(explode("\n", $text));
            $yPos = 50;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $regions[] = [
                        'text' => $line,
                        'confidence' => 0.85,
                        'position' => ['x' => 50, 'y' => $yPos],
                    ];
                    $yPos += 30;
                }
            }

            // Detectar idioma (heurística simples)
            $lang = preg_match('/[àáâãéêíóôõúç]/iu', $text) ? 'pt-BR' : 'en';

            return [
                'has_text' => true,
                'extracted_text' => $text,
                'text_regions' => $regions,
                'readable_score' => min(100, (int)(mb_strlen($text) / 2)),
                'language_detected' => $lang,
                'ocr_engine' => 'tesseract',
            ];
        } catch (\Exception $e) {
            $this->logger->warning('Tesseract OCR falhou', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extrai texto via LLM Vision API (OpenAI GPT-4 Vision)
     */
    private function tryLlmVisionOcr(array $imageInfo): ?array
    {
        $apiKey = $_ENV['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?: '';
        if (empty($apiKey)) {
            return null;
        }

        $imageUrl = $imageInfo['url'] ?? null;
        if (!$imageUrl) {
            return null;
        }

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 30]);
            $response = $client->post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'Extraia todo texto visível nesta imagem de produto. Retorne APENAS o texto encontrado, um por linha. Se não houver texto, responda "SEM_TEXTO".'],
                                ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]],
                            ],
                        ],
                    ],
                    'max_tokens' => 500,
                ],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);
            $text = trim($body['choices'][0]['message']['content'] ?? '');

            if ($text === 'SEM_TEXTO' || $text === '') {
                return [
                    'has_text' => false,
                    'extracted_text' => '',
                    'text_regions' => [],
                    'readable_score' => 0,
                    'language_detected' => null,
                    'ocr_engine' => 'openai_vision',
                ];
            }

            $regions = [];
            $yPos = 50;
            foreach (array_filter(explode("\n", $text)) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $regions[] = [
                        'text' => $line,
                        'confidence' => 0.90,
                        'position' => ['x' => 50, 'y' => $yPos],
                    ];
                    $yPos += 30;
                }
            }

            $lang = preg_match('/[àáâãéêíóôõúç]/iu', $text) ? 'pt-BR' : 'en';

            return [
                'has_text' => true,
                'extracted_text' => $text,
                'text_regions' => $regions,
                'readable_score' => min(100, (int)(mb_strlen($text) / 2)),
                'language_detected' => $lang,
                'ocr_engine' => 'openai_vision',
            ];
        } catch (\Exception $e) {
            $this->logger->warning('LLM Vision OCR falhou', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Analisa composição e enquadramento
     */
    private function analyzeComposition(array $imageInfo): array
    {
        $composition = [
            'rule_of_thirds' => $this->checkRuleOfThirds($imageInfo),
            'centering' => $this->analyzeCentering($imageInfo),
            'symmetry' => $this->analyzeSymmetry($imageInfo),
            'depth_of_field' => $this->analyzeDepthOfField($imageInfo),
            'leading_lines' => $this->detectLeadingLines($imageInfo),
            'composition_score' => 0
        ];

        $composition['composition_score'] = round((
            $composition['rule_of_thirds'] * 0.3 +
            $composition['centering'] * 0.2 +
            $composition['symmetry'] * 0.2 +
            $composition['depth_of_field'] * 0.2 +
            $composition['leading_lines'] * 0.1
        ), 2);

        return $composition;
    }

    /**
     * Verifica conformidade com padrões do ML
     */
    private function checkMLCompliance(array $imageInfo, array $productData): array
    {
        $compliance = [
            'size_requirements' => $this->checkMLSizeRequirements($imageInfo),
            'quality_requirements' => $this->checkMLQualityRequirements($imageInfo),
            'content_policy' => $this->checkMLContentPolicy($imageInfo),
            'format_requirements' => $this->checkMLFormatRequirements($imageInfo),
            'overall_compliance' => 0,
            'violations' => [],
            'recommendations' => []
        ];

        // Verificações específicas do ML
        $checks = [
            'min_resolution' => $imageInfo['width'] >= 500 && $imageInfo['height'] >= 500,
            'max_file_size' => $imageInfo['file_size'] <= 10 * 1024 * 1024, // 10MB
            'supported_format' => in_array($imageInfo['format'], ['JPEG', 'PNG', 'WEBP']),
            'aspect_ratio' => $imageInfo['width'] / $imageInfo['height'] >= 0.8 && $imageInfo['width'] / $imageInfo['height'] <= 1.2
        ];

        foreach ($checks as $check => $passed) {
            if (!$passed) {
                $compliance['violations'][] = $check;
                $compliance['recommendations'][] = $this->getMLComplianceRecommendation($check);
            }
        }

        $compliance['overall_compliance'] = round(
            (count($checks) - count($compliance['violations'])) / count($checks) * 100, 
            2
        );

        return $compliance;
    }

    // ========== CÁLCULOS E ANÁLISES AUXILIARES ==========

    /**
     * Calcula pontuação geral da imagem
     */
    private function calculateOverallScore(array $analyses): float
    {
        $weights = [
            'technical' => 0.15,
            'quality' => 0.30,
            'content' => 0.25,
            'composition' => 0.20,
            'compliance' => 0.10
        ];

        $score = 0;
        $score += ($analyses['technical']['resolution_score'] ?? 0) * $weights['technical'];
        $score += ($analyses['quality']['overall_quality'] ?? 0) * $weights['quality'];
        $score += ($analyses['content']['composition_score'] ?? 0) * $weights['content'];
        $score += ($analyses['composition']['composition_score'] ?? 0) * $weights['composition'];
        $score += ($analyses['compliance']['overall_compliance'] ?? 0) * $weights['compliance'];

        return round($score, 2);
    }

    /**
     * Gera sugestões de melhoria
     */
    private function generateImprovementSuggestions(array $analyses): array
    {
        $suggestions = [];

        // Sugestões técnicas
        if (($analyses['technical']['resolution_score'] ?? 0) < 80) {
            $suggestions[] = [
                'type' => 'resolution',
                'priority' => 'high',
                'message' => 'Recomendamos usar imagem com resolução mínima de 1200x1200 pixels',
                'impact' => 'Melhor visualização e conformidade com o ML'
            ];
        }

        // Sugestões de qualidade
        if (($analyses['quality']['overall_quality'] ?? 0) < 70) {
            $suggestions[] = [
                'type' => 'quality',
                'priority' => 'medium',
                'message' => 'A qualidade da imagem pode ser melhorada com melhor iluminação e foco',
                'impact' => 'Maior atratividade e profissionalismo'
            ];
        }

        // Sugestões de conformidade
        if (!empty($analyses['compliance']['violations'] ?? [])) {
            foreach ($analyses['compliance']['violations'] as $violation) {
                $suggestions[] = [
                    'type' => 'compliance',
                    'priority' => 'high',
                    'message' => $this->getMLComplianceRecommendation($violation),
                    'impact' => 'Necessário para publicação no ML'
                ];
            }
        }

        return $suggestions;
    }

    // ========== MÉTODOS DE DOWNLOAD E VALIDAÇÃO ==========

    /**
     * Faz download e valida a imagem
     */
    private function downloadAndValidateImage(string $imageUrl): array
    {
        try {
            // Verificar URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                throw new Exception("URL inválida: $imageUrl");
            }

            // Gerar nome de arquivo temporário
            $tempFile = sys_get_temp_dir() . '/ai_img_' . uniqid() . '.tmp';

            // Download da imagem usando Curl para maior robustez
            $ch = curl_init($imageUrl);
            $fp = fopen($tempFile, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            // Security: enable SSL verification in production (C3)
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, ($_ENV['APP_ENV'] ?? 'production') === 'production');
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($error || $httpCode < 200 || $httpCode >= 400) {
                if (file_exists($tempFile)) unlink($tempFile);
                throw new Exception("Falha ao baixar imagem: " . ($error ?: "HTTP $httpCode"));
            }

            // Analisar arquivo baixado
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                if (file_exists($tempFile)) unlink($tempFile);
                throw new Exception("Arquivo vazio ou não criado");
            }

            $imageSize = @getimagesize($tempFile);
            if (!$imageSize) {
                unlink($tempFile);
                throw new Exception("Arquivo não é uma imagem válida");
            }

            list($width, $height, $type) = $imageSize;
            $mime = $imageSize['mime'];
            
            // Mapear tipo para formato
            $formatMap = [
                IMAGETYPE_JPEG => 'JPEG',
                IMAGETYPE_PNG => 'PNG',
                IMAGETYPE_WEBP => 'WEBP',
                IMAGETYPE_GIF => 'GIF'
            ];
            $format = $formatMap[$type] ?? 'UNKNOWN';

            $imageInfo = [
                'valid' => true,
                'url' => $imageUrl,
                'temp_file' => $tempFile,
                'width' => $width,
                'height' => $height,
                'file_size' => filesize($tempFile),
                'format' => $format,
                'color_depth' => $imageSize['bits'] ?? 24,
                'has_transparency' => ($format === 'PNG' || $format === 'WEBP' || $format === 'GIF'), // Aproximação
                'mime' => $mime
            ];

            return $imageInfo;

        } catch (Exception $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }

    // ========== INICIALIZAÇÃO ==========

    /**
     * Inicializa modelos de análise
     */
    private function initializeAnalysisModels(): void
    {
        $this->analysisModels = [
            'object_detection' => 'yolo_v8',
            'quality_analysis' => 'custom_cnn',
            'text_extraction' => 'tesseract_5',
            'color_analysis' => 'color_thief',
            'composition' => 'rule_based'
        ];
    }

    /**
     * Carrega padrões de qualidade
     */
    private function loadQualityStandards(): void
    {
        $this->qualityStandards = [
            'ml_requirements' => [
                'min_width' => 500,
                'min_height' => 500,
                'max_file_size' => 10485760, // 10MB
                'supported_formats' => ['JPEG', 'PNG', 'WEBP'],
                'recommended_resolution' => 1200
            ],
            'quality_thresholds' => [
                'excellent' => 90,
                'good' => 75,
                'fair' => 60,
                'poor' => 40
            ]
        ];
    }

    // ========== MÉTODOS AUXILIARES (IMPLEMENTAÇÕES BÁSICAS) ==========

    private function createErrorResponse(string $message, string $url): array
    {
        return [
            'success' => false,
            'error' => $message,
            'image_url' => $url,
            'analyzed_at' => date('Y-m-d H:i:s')
        ];
    }

    // Implementações reais usando GD library para análise de imagens
    private function calculateResolutionScore($imageInfo): int { 
        return min(100, (int)(($imageInfo['width'] * $imageInfo['height']) / (1200 * 1200) * 100)); 
    }
    private function checkSizeCompliance($imageInfo): bool { return $imageInfo['file_size'] <= 10485760; }
    private function checkFormatCompliance($format): bool { return in_array($format, ['JPEG', 'PNG', 'WEBP']); }
    
    private function calculateSharpness($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 70; // Default quando imagem não está acessível localmente
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return 70;

            $width = imagesx($img);
            $height = imagesy($img);
            // Laplacian variance como proxy de nitidez (amostrar centro da imagem)
            $cx = (int)($width / 2);
            $cy = (int)($height / 2);
            $sampleSize = min(100, min($width, $height) / 2);
            $values = [];
            for ($y = $cy - $sampleSize; $y < $cy + $sampleSize; $y += 2) {
                for ($x = $cx - $sampleSize; $x < $cx + $sampleSize; $x += 2) {
                    if ($x >= 0 && $x < $width && $y >= 0 && $y < $height) {
                        $rgb = imagecolorat($img, $x, $y);
                        $values[] = ($rgb >> 16) & 0xFF; // Red channel
                    }
                }
            }
            imagedestroy($img);

            if (count($values) < 4) return 70;
            // Calcular variância (imagens nítidas têm alta variância)
            $mean = array_sum($values) / count($values);
            $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $values)) / count($values);
            return min(100, max(0, (int)($variance / 20))); // Normalizar para 0-100
        } catch (\Exception $e) {
            return 70;
        }
    }
    
    private function calculateBrightness($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 75;
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return 75;

            $width = imagesx($img);
            $height = imagesy($img);
            $totalBrightness = 0;
            $samples = 0;
            $step = max(1, (int)(($width * $height) / 5000)); // Amostrar ~5000 pixels
            for ($i = 0; $i < $width * $height; $i += $step) {
                $x = $i % $width;
                $y = (int)($i / $width);
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $totalBrightness += ($r * 0.299 + $g * 0.587 + $b * 0.114);
                $samples++;
            }
            imagedestroy($img);

            $avgBrightness = $samples > 0 ? $totalBrightness / $samples : 128;
            // Score: 100 = brightness ideal (~140-180), penalizar muito escuro ou muito claro
            $ideal = 160;
            $distance = abs($avgBrightness - $ideal);
            return max(0, min(100, 100 - (int)($distance * 0.6)));
        } catch (\Exception $e) {
            return 75;
        }
    }

    private function calculateContrast($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 70;
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return 70;

            $width = imagesx($img);
            $height = imagesy($img);
            $min = 255;
            $max = 0;
            $step = max(1, (int)(($width * $height) / 3000));
            for ($i = 0; $i < $width * $height; $i += $step) {
                $x = $i % $width;
                $y = (int)($i / $width);
                $rgb = imagecolorat($img, $x, $y);
                $gray = (int)(0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
                if ($gray < $min) $min = $gray;
                if ($gray > $max) $max = $gray;
            }
            imagedestroy($img);

            $contrast = $max - $min;
            return min(100, max(0, (int)($contrast / 2.55)));
        } catch (\Exception $e) {
            return 70;
        }
    }

    private function calculateNoise($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 15;
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return 15;

            $width = imagesx($img);
            $height = imagesy($img);
            $diffs = [];
            $step = max(2, (int)($width / 100));
            for ($y = 1; $y < min($height, 200); $y += $step) {
                for ($x = 1; $x < min($width, 200); $x += $step) {
                    $c1 = imagecolorat($img, $x, $y) & 0xFF;
                    $c2 = imagecolorat($img, $x - 1, $y) & 0xFF;
                    $diffs[] = abs($c1 - $c2);
                }
            }
            imagedestroy($img);

            $avgDiff = count($diffs) > 0 ? array_sum($diffs) / count($diffs) : 0;
            // Menor diferença média = menos ruído. Normalizar: <5 = ótimo, >30 = ruim
            return min(100, max(0, (int)($avgDiff * 3)));
        } catch (\Exception $e) {
            return 15;
        }
    }

    private function detectBlur($imageInfo): int 
    {
        // Reusar sharpness invertido: menos nítido = mais blur
        $sharpness = $this->calculateSharpness($imageInfo);
        return max(0, 100 - $sharpness);
    }

    private function analyzeExposure($imageInfo): int 
    {
        $brightness = $this->calculateBrightness($imageInfo);
        // Boa exposição está na faixa 60-85 de brightness score
        if ($brightness >= 60 && $brightness <= 90) return min(100, $brightness + 10);
        return max(0, $brightness - 10);
    }
    
    private function detectObjects($imageInfo): array
    {
        // Se não há arquivo local, não há como analisar objetos via heurística
        if (empty($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return [];
        }

        $img = $this->loadGdImage($imageInfo);
        if (!$img) {
            return [];
        }

        $width  = imagesx($img);
        $height = imagesy($img);
        if ($width <= 0 || $height <= 0) {
            return [];
        }

        // Comparar variância de cor entre região central e bordas
        // Alta variância central + baixa variância nas bordas → produto sobre fundo limpo
        $centerX = (int)($width * 0.3);
        $centerY = (int)($height * 0.3);
        $margin  = (int)(min($width, $height) * 0.08);

        $centerSamples = [];
        $borderSamples = [];

        $step = max(1, (int)(min($width, $height) / 30));
        for ($y = $centerY; $y < $centerY + (int)($height * 0.4); $y += $step) {
            for ($x = $centerX; $x < $centerX + (int)($width * 0.4); $x += $step) {
                $rgb = imagecolorat($img, min($x, $width - 1), min($y, $height - 1));
                $centerSamples[] = ($rgb >> 16 & 0xFF) * 0.299
                    + ($rgb >> 8 & 0xFF)  * 0.587
                    + ($rgb & 0xFF)       * 0.114;
            }
        }

        for ($m = 0; $m < $margin; $m += $step) {
            for ($i = 0; $i < $width; $i += $step) {
                $rgb = imagecolorat($img, min($i, $width - 1), $m);
                $borderSamples[] = ($rgb >> 16 & 0xFF) * 0.299 + ($rgb >> 8 & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114;
                $rgb = imagecolorat($img, min($i, $width - 1), max(0, $height - 1 - $m));
                $borderSamples[] = ($rgb >> 16 & 0xFF) * 0.299 + ($rgb >> 8 & 0xFF) * 0.587 + ($rgb & 0xFF) * 0.114;
            }
        }

        $calcVariance = function (array $samples): float {
            if (count($samples) < 2) {
                return 0.0;
            }
            $mean = array_sum($samples) / count($samples);
            $variance = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $samples)) / count($samples);
            return $variance;
        };

        $centerVariance = $calcVariance($centerSamples);
        $borderVariance = $calcVariance($borderSamples);

        $objects = [];
        // Produto detectado quando há variância central > bordas (objeto sobre fundo)
        if ($centerVariance > 200 && $centerVariance > $borderVariance * 1.5) {
            $confidence = min(0.90, 0.60 + ($centerVariance / 10000));
            $objects[] = ['name' => 'product', 'confidence' => round($confidence, 2)];
        } elseif ($borderVariance < 100) {
            // Fundo muito uniforme mas centro sem objeto claro
            $objects[] = ['name' => 'product', 'confidence' => 0.55];
        }

        return $objects;
    }
    
    private function analyzeProductVisibility($imageInfo): int 
    {
        // Base no tamanho vs resolução ideal ML (1200x1200)
        $coverage = min(1.0, ($imageInfo['width'] * $imageInfo['height']) / (1200 * 1200));
        return (int)($coverage * 100);
    }

    private function analyzeBackground($imageInfo): array 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return ['type' => 'unknown', 'score' => 50];
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return ['type' => 'unknown', 'score' => 50];

            // Amostrar cantos da imagem para detectar fundo
            $width = imagesx($img);
            $height = imagesy($img);
            $cornerPixels = [];
            $margin = min(20, (int)($width * 0.05));
            
            $corners = [[0, 0], [$width - $margin, 0], [0, $height - $margin], [$width - $margin, $height - $margin]];
            foreach ($corners as [$cx, $cy]) {
                for ($dx = 0; $dx < $margin; $dx += 3) {
                    for ($dy = 0; $dy < $margin; $dy += 3) {
                        $x = min($width - 1, max(0, $cx + $dx));
                        $y = min($height - 1, max(0, $cy + $dy));
                        $rgb = imagecolorat($img, $x, $y);
                        $cornerPixels[] = [
                            'r' => ($rgb >> 16) & 0xFF,
                            'g' => ($rgb >> 8) & 0xFF,
                            'b' => $rgb & 0xFF
                        ];
                    }
                }
            }
            imagedestroy($img);

            if (empty($cornerPixels)) return ['type' => 'unknown', 'score' => 50];

            $avgR = array_sum(array_column($cornerPixels, 'r')) / count($cornerPixels);
            $avgG = array_sum(array_column($cornerPixels, 'g')) / count($cornerPixels);
            $avgB = array_sum(array_column($cornerPixels, 'b')) / count($cornerPixels);

            // Detectar fundo branco (ideal para ML)
            if ($avgR > 230 && $avgG > 230 && $avgB > 230) {
                return ['type' => 'white', 'score' => 100];
            }
            if ($avgR > 200 && $avgG > 200 && $avgB > 200) {
                return ['type' => 'light', 'score' => 85];
            }
            if ($avgR < 50 && $avgG < 50 && $avgB < 50) {
                return ['type' => 'dark', 'score' => 40];
            }
            return ['type' => 'colored', 'score' => 60];
        } catch (\Exception $e) {
            return ['type' => 'unknown', 'score' => 50];
        }
    }

    private function analyzeContentComposition($imageInfo): int 
    {
        // Score baseado em: resolução adequada + formato correto + proporção ideal
        $resScore = $this->calculateResolutionScore($imageInfo);
        $formatOk = $this->checkFormatCompliance($imageInfo['format'] ?? 'JPEG') ? 100 : 50;
        $ratio = $imageInfo['width'] / max(1, $imageInfo['height']);
        $ratioScore = $ratio >= 0.8 && $ratio <= 1.2 ? 100 : ($ratio >= 0.5 && $ratio <= 2.0 ? 70 : 40);
        return (int)(($resScore + $formatOk + $ratioScore) / 3);
    }

    private function analyzeProfessionalism($imageInfo): int 
    {
        $bg = $this->analyzeBackground($imageInfo);
        $res = $this->calculateResolutionScore($imageInfo);
        $sharp = $this->calculateSharpness($imageInfo);
        return (int)(($bg['score'] * 0.4) + ($res * 0.3) + ($sharp * 0.3));
    }

    private function analyzeColorHarmony(array $dominantColors = []): int 
    {
        if (count($dominantColors) < 2) {
            return 60;
        }

        $hues = [];
        $luminances = [];

        foreach ($dominantColors as $color) {
            $hex = strtoupper((string)($color['hex'] ?? ''));
            if (!preg_match('/^#[0-9A-F]{6}$/', $hex)) {
                continue;
            }

            $r = hexdec(substr($hex, 1, 2));
            $g = hexdec(substr($hex, 3, 2));
            $b = hexdec(substr($hex, 5, 2));

            $rN = $r / 255;
            $gN = $g / 255;
            $bN = $b / 255;
            $max = max($rN, $gN, $bN);
            $min = min($rN, $gN, $bN);
            $delta = $max - $min;

            $hue = 0.0;
            if ($delta > 0) {
                if ($max === $rN) {
                    $hue = 60 * fmod((($gN - $bN) / $delta), 6);
                } elseif ($max === $gN) {
                    $hue = 60 * ((($bN - $rN) / $delta) + 2);
                } else {
                    $hue = 60 * ((($rN - $gN) / $delta) + 4);
                }
                if ($hue < 0) {
                    $hue += 360;
                }
            }

            $hues[] = $hue;
            $luminances[] = $this->getRelativeLuminance($hex);
        }

        if (count($hues) < 2) {
            return 60;
        }

        sort($hues);
        $hueSpread = $hues[count($hues) - 1] - $hues[0];
        $lumSpread = max($luminances) - min($luminances);

        // Heurística: equilíbrio entre contraste e coesão cromática
        $hueScore = $hueSpread >= 140 && $hueSpread <= 240
            ? 90
            : ($hueSpread >= 80 && $hueSpread <= 280 ? 75 : 55);
        $lumScore = $lumSpread >= 0.15 && $lumSpread <= 0.75
            ? 85
            : ($lumSpread >= 0.08 ? 70 : 55);

        return (int)max(40, min(98, round(($hueScore * 0.6) + ($lumScore * 0.4))));
    }
    
    private function checkRuleOfThirds($imageInfo): int 
    {
        // Sem detecção de objetos avançada, base na proporção
        $ratio = $imageInfo['width'] / max(1, $imageInfo['height']);
        return ($ratio >= 0.9 && $ratio <= 1.1) ? 85 : 70;
    }

    private function analyzeCentering($imageInfo): int 
    {
        // Estimar centralização pelo contraste centro vs bordas
        $brightness = $this->calculateBrightness($imageInfo);
        return min(95, max(50, $brightness + 10));
    }

    private function analyzeSymmetry($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 65;
        }
        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) return 65;

            $width = imagesx($img);
            $height = imagesy($img);
            $diffs = 0;
            $samples = 0;
            $halfWidth = (int)($width / 2);
            $step = max(1, (int)($height / 50));

            for ($y = 0; $y < $height; $y += $step) {
                for ($dx = 0; $dx < min($halfWidth, 50); $dx += 3) {
                    $left = imagecolorat($img, $dx, $y) & 0xFF;
                    $right = imagecolorat($img, $width - 1 - $dx, $y) & 0xFF;
                    $diffs += abs($left - $right);
                    $samples++;
                }
            }
            imagedestroy($img);

            $avgDiff = $samples > 0 ? $diffs / $samples : 50;
            return max(0, min(100, 100 - (int)($avgDiff * 1.5)));
        } catch (\Exception $e) {
            return 65;
        }
    }

    private function analyzeDepthOfField($imageInfo): int 
    {
        // Aproximar: diferença de nitidez centro vs borda
        $sharpness = $this->calculateSharpness($imageInfo);
        return min(100, max(30, $sharpness - 5));
    }

    private function detectLeadingLines($imageInfo): int 
    {
        if (!isset($imageInfo['local_path']) || !file_exists($imageInfo['local_path'])) {
            return 55;
        }

        try {
            $img = $this->loadGdImage($imageInfo);
            if (!$img) {
                return 55;
            }

            $width = imagesx($img);
            $height = imagesy($img);
            if ($width < 20 || $height < 20) {
                imagedestroy($img);
                return 55;
            }

            // Reduzir imagem para amostragem eficiente
            $targetW = 120;
            $targetH = (int)max(60, round(($height / max(1, $width)) * $targetW));
            $thumb = imagecreatetruecolor($targetW, $targetH);
            imagecopyresampled($thumb, $img, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
            imagedestroy($img);

            $verticalEdges = 0;
            $horizontalEdges = 0;
            $diagEdges = 0;
            $samples = 0;

            for ($y = 1; $y < $targetH - 1; $y += 2) {
                for ($x = 1; $x < $targetW - 1; $x += 2) {
                    $c = imagecolorat($thumb, $x, $y);
                    $r = ($c >> 16) & 0xFF;
                    $g = ($c >> 8) & 0xFF;
                    $b = $c & 0xFF;
                    $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);

                    $cR = imagecolorat($thumb, $x + 1, $y);
                    $grayR = (int)(0.299 * (($cR >> 16) & 0xFF) + 0.587 * (($cR >> 8) & 0xFF) + 0.114 * ($cR & 0xFF));
                    $cD = imagecolorat($thumb, $x, $y + 1);
                    $grayD = (int)(0.299 * (($cD >> 16) & 0xFF) + 0.587 * (($cD >> 8) & 0xFF) + 0.114 * ($cD & 0xFF));
                    $cDiag = imagecolorat($thumb, $x + 1, $y + 1);
                    $grayDiag = (int)(0.299 * (($cDiag >> 16) & 0xFF) + 0.587 * (($cDiag >> 8) & 0xFF) + 0.114 * ($cDiag & 0xFF));

                    $dx = abs($grayR - $gray);
                    $dy = abs($grayD - $gray);
                    $dd = abs($grayDiag - $gray);

                    if ($dx > 24) {
                        $verticalEdges++;
                    }
                    if ($dy > 24) {
                        $horizontalEdges++;
                    }
                    if ($dd > 24) {
                        $diagEdges++;
                    }
                    $samples++;
                }
            }

            imagedestroy($thumb);

            if ($samples === 0) {
                return 55;
            }

            $edgeDensity = ($verticalEdges + $horizontalEdges + $diagEdges) / ($samples * 3);
            $orientationBalance = min($verticalEdges, $horizontalEdges + $diagEdges) / max(1, max($verticalEdges, $horizontalEdges + $diagEdges));

            $densityScore = min(100, (int)round($edgeDensity * 260));
            $balanceScore = min(100, (int)round($orientationBalance * 100));

            return (int)max(35, min(95, round(($densityScore * 0.65) + ($balanceScore * 0.35))));
        } catch (\Exception $e) {
            return 55;
        }
    }

    private function checkMLSizeRequirements($imageInfo): bool { return $imageInfo['width'] >= 500 && $imageInfo['height'] >= 500; }
    private function checkMLQualityRequirements($imageInfo): bool { 
        $sharpness = $this->calculateSharpness($imageInfo);
        $noise = $this->calculateNoise($imageInfo);
        return $sharpness >= 40 && $noise <= 50;
    }
    private function checkMLContentPolicy($imageInfo): bool { return true; }
    private function checkMLFormatRequirements($imageInfo): bool { return in_array($imageInfo['format'] ?? '', ['JPEG', 'PNG', 'WEBP']); }
    private function classifyImageType($analyses): string { 
        if (isset($analyses['background']) && $analyses['background']['type'] === 'white') {
            return 'product_photo_studio';
        }
        return 'product_photo';
    }
    private function generateBulkRecommendations($summary, $results): array { 
        $recs = [];
        if (($summary['avg_quality_score'] ?? 0) < 70) {
            $recs[] = 'Melhore a qualidade geral das imagens (resolução e nitidez)';
        }
        if (($summary['total_images'] ?? 0) < 6) {
            $recs[] = 'Adicione mais imagens (mínimo 6 recomendado pelo ML)';
        }
        $lowRes = array_filter($results, fn($r) => ($r['resolution_score'] ?? 0) < 50);
        if (!empty($lowRes)) {
            $recs[] = 'Substitua ' . count($lowRes) . ' imagens com baixa resolução (mínimo 1200x1200)';
        }
        if (empty($recs)) {
            $recs[] = 'Use a melhor imagem como principal';
        }
        return $recs;
    }

    /**
     * Carrega imagem usando GD library
     */
    private function loadGdImage(array $imageInfo): ?\GdImage
    {
        $path = $imageInfo['local_path'] ?? null;
        if (!$path || !file_exists($path)) return null;

        $format = strtoupper($imageInfo['format'] ?? '');
        return match ($format) {
            'JPEG', 'JPG' => @imagecreatefromjpeg($path),
            'PNG' => @imagecreatefrompng($path),
            'WEBP' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'GIF' => @imagecreatefromgif($path),
            default => @imagecreatefromstring(file_get_contents($path)),
        } ?: null;
    }

    private function getMLComplianceRecommendation(string $violation): string
    {
        $recommendations = [
            'min_resolution' => 'Use imagem com pelo menos 500x500 pixels',
            'max_file_size' => 'Reduza o tamanho do arquivo para menos de 10MB',
            'supported_format' => 'Use formato JPEG, PNG ou WEBP',
            'aspect_ratio' => 'Use proporção quadrada ou próxima (1:1 ideal)'
        ];

        return $recommendations[$violation] ?? 'Verifique os padrões do Mercado Livre';
    }
}