<?php

namespace App\Controllers;

use App\Services\CloneSEOIntegrationService;
use Exception;

/**
 * Clone SEO Integration Controller
 * 
 * API para otimizações SEO durante clonagem
 */
class CloneSEOIntegrationController
{
    private int $accountId;

    public function __construct()
    {
        $this->accountId = $_SESSION['account_id'] ?? 0;

        if (!$this->accountId) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Otimiza item para clonagem
     * POST /api/clone/seo/optimize
     */
    public function optimize(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['item'])) {
                throw new Exception('Item é obrigatório');
            }

            $level = $input['level'] ?? CloneSEOIntegrationService::LEVEL_STANDARD;

            $service = new CloneSEOIntegrationService($this->accountId);
            $result = $service->optimizeForClone($input['item'], $level);

            // Log da otimização
            $originalScore = $service->calculateSEOScore($input['item'])['total'];
            $newScore = $result['seo_score']['total'];

            if ($originalScore !== $newScore) {
                $service->logOptimization(
                    $input['item']['id'] ?? 'preview',
                    $originalScore,
                    $newScore,
                    $level
                );
            }

            echo json_encode([
                'success' => true,
                'result' => $result,
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Calcula score SEO de um item
     * POST /api/clone/seo/score
     */
    public function getScore(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['item'])) {
                throw new Exception('Item é obrigatório');
            }

            $service = new CloneSEOIntegrationService($this->accountId);
            $score = $service->calculateSEOScore($input['item']);

            echo json_encode([
                'success' => true,
                'score' => $score,
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém nível de otimização do usuário
     * GET /api/clone/seo/settings
     */
    public function getSettings(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSEOIntegrationService($this->accountId);
            $level = $service->getUserOptimizationLevel();

            echo json_encode([
                'success' => true,
                'settings' => [
                    'optimization_level' => $level,
                    'available_levels' => [
                        [
                            'value' => CloneSEOIntegrationService::LEVEL_NONE,
                            'label' => 'Nenhuma',
                            'description' => 'Clona sem modificações',
                        ],
                        [
                            'value' => CloneSEOIntegrationService::LEVEL_BASIC,
                            'label' => 'Básica',
                            'description' => 'Limpa título e remove palavras proibidas',
                        ],
                        [
                            'value' => CloneSEOIntegrationService::LEVEL_STANDARD,
                            'label' => 'Padrão',
                            'description' => 'Otimiza título e descrição',
                        ],
                        [
                            'value' => CloneSEOIntegrationService::LEVEL_AGGRESSIVE,
                            'label' => 'Agressiva',
                            'description' => 'Reescrita completa para máximo SEO',
                        ],
                    ],
                ],
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Define nível de otimização
     * PUT /api/clone/seo/settings
     */
    public function updateSettings(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['optimization_level'])) {
                throw new Exception('Nível de otimização é obrigatório');
            }

            $service = new CloneSEOIntegrationService($this->accountId);
            $success = $service->setUserOptimizationLevel($input['optimization_level']);

            if (!$success) {
                throw new Exception('Nível de otimização inválido');
            }

            echo json_encode([
                'success' => true,
                'message' => 'Configurações atualizadas',
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Obtém estatísticas de otimizações
     * GET /api/clone/seo/stats
     */
    public function getStats(): void
    {
        header('Content-Type: application/json');

        try {
            $service = new CloneSEOIntegrationService($this->accountId);
            $stats = $service->getOptimizationStats();

            echo json_encode([
                'success' => true,
                'stats' => $stats,
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Preview de otimização em lote
     * POST /api/clone/seo/preview-batch
     */
    public function previewBatch(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['items']) || !is_array($input['items'])) {
                throw new Exception('Lista de itens é obrigatória');
            }

            $level = $input['level'] ?? CloneSEOIntegrationService::LEVEL_STANDARD;
            $service = new CloneSEOIntegrationService($this->accountId);

            $results = [];
            $totalBefore = 0;
            $totalAfter = 0;

            foreach (array_slice($input['items'], 0, 50) as $item) {
                $result = $service->optimizeForClone($item, $level);

                $scoreBefore = $service->calculateSEOScore($item)['total'];
                $scoreAfter = $result['seo_score']['total'];

                $totalBefore += $scoreBefore;
                $totalAfter += $scoreAfter;

                $results[] = [
                    'item_id' => $item['id'] ?? null,
                    'title_original' => $item['title'] ?? '',
                    'title_optimized' => $result['item']['title'] ?? '',
                    'score_before' => $scoreBefore,
                    'score_after' => $scoreAfter,
                    'improvement' => $scoreAfter - $scoreBefore,
                ];
            }

            $count = count($results);

            echo json_encode([
                'success' => true,
                'items' => $results,
                'summary' => [
                    'total_items' => $count,
                    'avg_score_before' => $count > 0 ? round($totalBefore / $count, 1) : 0,
                    'avg_score_after' => $count > 0 ? round($totalAfter / $count, 1) : 0,
                    'avg_improvement' => $count > 0 ? round(($totalAfter - $totalBefore) / $count, 1) : 0,
                ],
            ]);

        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
