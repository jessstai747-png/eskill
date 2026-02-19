<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\TitleGenerator\TitleGeneratorService;
use App\Services\TitleGenerator\TitleAnalyzerService;
use App\Services\TitleGenerator\TitleVariationsService;

/**
 * Title Generator Controller - API para Geração de Títulos
 */
class TitleGeneratorController
{
    private TitleGeneratorService $generator;
    private TitleAnalyzerService $analyzer;
    private TitleVariationsService $variations;
    private ?int $accountId = null;
    private Request $request;

    public function __construct(?int $accountId = null)
    {
        $this->request = new Request();
        $this->accountId = $accountId;
        $this->generator = new TitleGeneratorService($accountId);
        $this->analyzer = new TitleAnalyzerService($accountId);
        $this->variations = new TitleVariationsService();
    }

    /**
     * Gera títulos otimizados a partir de dados do produto
     * POST /api/title-generator/generate
     * Body: {category_id, brand?, model?, attributes?, keywords?, count?, optimize_for?}
     */
    public function generate(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['category_id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'category_id é obrigatório',
                ]);
                return;
            }

            $result = $this->generator->generateTitles($input, $input['options'] ?? []);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Gera títulos melhorados a partir de anúncio existente
     * POST /api/title-generator/improve/{itemId}
     * Body: {count?, optimize_for?}
     */
    public function improveFromItem(string $itemId): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $options = $input ?? [];

            $result = $this->generator->generateFromItem($itemId, $options);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Analisa título existente
     * POST /api/title-generator/analyze
     * Body: {title, category_id?}
     */
    public function analyze(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['title'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'title é obrigatório',
                ]);
                return;
            }

            $categoryId = $input['category_id'] ?? '';
            $analysis = $this->analyzer->analyzeTitle($input['title'], $categoryId);

            echo json_encode([
                'success' => true,
                'analysis' => $analysis,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Gera variações de um título
     * POST /api/title-generator/variations
     * Body: {title, category_id?, count?, strategy?}
     */
    public function generateVariations(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['title'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'title é obrigatório',
                ]);
                return;
            }

            $result = $this->variations->generateVariations($input['title'], $input);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Gera variações para A/B testing
     * POST /api/title-generator/ab-testing
     * Body: {title, category_id?}
     */
    public function generateABTesting(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['title'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'title é obrigatório',
                ]);
                return;
            }

            $result = $this->variations->generateABTestingVariations($input['title'], $input);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Compara múltiplos títulos
     * POST /api/title-generator/compare
     * Body: {titles: [], category_id?}
     */
    public function compare(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['titles']) || !is_array($input['titles'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'titles (array) é obrigatório',
                ]);
                return;
            }

            $categoryId = $input['category_id'] ?? '';
            $comparisons = [];

            foreach ($input['titles'] as $title) {
                $analysis = $this->analyzer->analyzeTitle($title, $categoryId);
                $comparisons[] = [
                    'title' => $title,
                    'score' => $analysis['overall_score'],
                    'length' => $analysis['length'],
                    'status' => $analysis['status'],
                    'issues_count' => count($analysis['issues']),
                    'seo_score' => $analysis['seo_analysis']['score'],
                    'performance_score' => $analysis['performance_estimate']['performance_score'],
                ];
            }

            // Ordenar por score
            usort($comparisons, fn($a, $b) => $b['score'] <=> $a['score']);

            echo json_encode([
                'success' => true,
                'total_titles' => count($comparisons),
                'category_id' => $categoryId,
                'comparisons' => $comparisons,
                'best_title' => $comparisons[0] ?? null,
                'worst_title' => end($comparisons) ?: null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Otimiza título existente (análise + sugestões + variações)
     * POST /api/title-generator/optimize
     * Body: {title, category_id?, count?}
     */
    public function optimize(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['title'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'title é obrigatório',
                ]);
                return;
            }

            $categoryId = $input['category_id'] ?? '';
            $count = $input['count'] ?? 5;

            // 1. Analisar título atual
            $analysis = $this->analyzer->analyzeTitle($input['title'], $categoryId);

            // 2. Gerar variações otimizadas
            $variationsResult = $this->variations->generateVariations($input['title'], [
                'category_id' => $categoryId,
                'count' => $count,
                'strategy' => 'all',
                'min_score' => max($analysis['overall_score'], 70), // Só variações melhores
            ]);

            echo json_encode([
                'success' => true,
                'original_title' => $input['title'],
                'current_analysis' => $analysis,
                'optimized_variations' => $variationsResult['variations'] ?? [],
                'best_improvement' => [
                    'title' => $variationsResult['best_variation']['title'] ?? $input['title'],
                    'score_gain' => ($variationsResult['best_variation']['score'] ?? 0) - $analysis['overall_score'],
                    'new_score' => $variationsResult['best_variation']['score'] ?? 0,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Análise em lote de múltiplos anúncios
     * POST /api/title-generator/batch/analyze
     * Body: {item_ids: []}
     */
    public function batchAnalyze(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['item_ids']) || !is_array($input['item_ids'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'item_ids (array) é obrigatório',
                ]);
                return;
            }

            $results = [];
            $totalScore = 0;
            $successfulAnalyses = 0;
            $needsImprovement = [];

            $itemIds = array_values(array_unique(array_filter(
                array_map(fn($id) => trim((string)$id), $input['item_ids']),
                fn($id) => $id !== ''
            )));

            foreach ($itemIds as $itemId) {
                try {
                    $improvementResult = $this->generator->generateFromItem($itemId, [
                        'count' => 3,
                        'optimize_for' => 'both'
                    ]);

                    if ($improvementResult['success']) {
                        $originalScore = $improvementResult['improvement']['original_score'];
                        $totalScore += $originalScore;
                        $successfulAnalyses++;

                        $results[] = [
                            'item_id' => $itemId,
                            'original_title' => $improvementResult['original_title'] ?? '',
                            'current_score' => $originalScore,
                            'best_alternative' => $improvementResult['best_title'] ?? null,
                            'potential_improvement' => $improvementResult['improvement']['score_gain'] ?? 0,
                        ];

                        if ($originalScore < 70) {
                            $needsImprovement[] = $itemId;
                        }
                    } else {
                        $results[] = [
                            'item_id' => $itemId,
                            'error' => $improvementResult['error'] ?? 'Falha ao analisar item',
                        ];
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            $avgScore = $successfulAnalyses > 0 ? (int)round($totalScore / $successfulAnalyses) : 0;

            echo json_encode([
                'success' => true,
                'total_requested' => count($itemIds),
                'total_analyzed' => $successfulAnalyses,
                'total_errors' => count($results) - $successfulAnalyses,
                'average_score' => $avgScore,
                'needs_improvement_count' => count($needsImprovement),
                'needs_improvement_items' => $needsImprovement,
                'results' => $results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Sugestões rápidas para título
     * GET /api/title-generator/quick-tips?title={title}
     */
    public function quickTips(): void
    {
        header('Content-Type: application/json');

        try {
            $title = $this->request->get('title', '') ?? '';

            if (empty($title)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'title é obrigatório',
                ]);
                return;
            }

            $analysis = $this->analyzer->analyzeTitle($title);

            // Extrair apenas dicas rápidas
            $tips = [
                'score' => $analysis['overall_score'],
                'status' => $analysis['status'],
                'quick_fixes' => array_slice($analysis['suggestions'], 0, 3),
                'critical_issues' => array_filter($analysis['issues'], function ($issue) {
                    return str_contains($issue, 'proibid') || str_contains($issue, 'longo demais');
                }),
                'length_info' => [
                    'current' => $analysis['length'],
                    'optimal' => '45-58',
                    'available_chars' => max(0, 60 - $analysis['length']),
                ],
            ];

            echo json_encode([
                'success' => true,
                'tips' => $tips,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
