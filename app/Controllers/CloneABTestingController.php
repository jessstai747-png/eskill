<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CloneABTestingService;

/**
 * Clone A/B Testing Controller
 * 
 * API para gerenciar testes A/B de variações de anúncios clonados
 */
class CloneABTestingController
{
    private int $accountId;
    private int $userId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->accountId = (int)($_SESSION['account_id'] ?? 0);
        $this->userId = (int)($_SESSION['user_id'] ?? 0);
        
        if (!$this->userId) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * GET /api/clone/ab-tests
     * Lista testes A/B
     */
    public function listTests(): void
    {
        header('Content-Type: application/json');
        
        try {
            $filters = [
                'status' => $this->request->get('status'),
                'limit' => $this->request->getInt('limit', 50),
            ];
            
            $service = new CloneABTestingService($this->accountId);
            $tests = $service->listTests($filters);
            
            echo json_encode([
                'status' => 'success',
                'tests' => $tests,
                'total' => count($tests),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/clone/ab-tests/{testId}
     * Detalhes de um teste
     */
    public function getTest(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $test = $service->getTest($testId);
            
            if (!$test) {
                http_response_code(404);
                echo json_encode(['error' => 'Teste não encontrado']);
                return;
            }
            
            echo json_encode([
                'status' => 'success',
                'test' => $test,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests
     * Cria um novo teste A/B
     */
    public function createTest(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id é obrigatório']);
            return;
        }
        
        if (empty($input['variations']) || count($input['variations']) < 2) {
            http_response_code(400);
            echo json_encode(['error' => 'Pelo menos 2 variações são necessárias']);
            return;
        }
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $testId = $service->createTest($input);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Teste A/B criado com sucesso',
                'test_id' => $testId,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/{testId}/start
     * Inicia um teste
     */
    public function startTest(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $test = $service->startTest($testId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Teste iniciado',
                'test' => $test,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/{testId}/pause
     * Pausa um teste
     */
    public function pauseTest(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $test = $service->pauseTest($testId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Teste pausado',
                'test' => $test,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/{testId}/complete
     * Finaliza um teste e determina vencedor
     */
    public function completeTest(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $result = $service->completeTest($testId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Teste finalizado',
                'test' => $result,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * DELETE /api/clone/ab-tests/{testId}
     * Cancela um teste
     */
    public function cancelTest(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $service->cancelTest($testId);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Teste cancelado',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/{testId}/apply-winner
     * Aplica variação vencedora
     */
    public function applyWinner(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $result = $service->applyWinner($testId);
            
            if ($result['status'] === 'success') {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/{testId}/sync-metrics
     * Sincroniza métricas do ML
     */
    public function syncMetrics(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $results = $service->syncMetricsFromML($testId);
            
            $success = count(array_filter($results, fn($r) => $r['success']));
            $total = count($results);
            
            echo json_encode([
                'status' => 'success',
                'message' => "Métricas sincronizadas: {$success}/{$total}",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * GET /api/clone/ab-tests/{testId}/winner
     * Obtém análise de vencedor
     */
    public function getWinner(int $testId): void
    {
        header('Content-Type: application/json');
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $winner = $service->determineWinner($testId);
            
            echo json_encode([
                'status' => 'success',
                'winner' => $winner,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/generate-variations
     * Gera variações automáticas de título
     */
    public function generateVariations(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['item_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id é obrigatório']);
            return;
        }
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $variations = $service->generateTitleVariations(
                $input['item_id'],
                $input['count'] ?? 3
            );
            
            echo json_encode([
                'status' => 'success',
                'variations' => $variations,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * POST /api/clone/ab-tests/variations/{variationId}/metrics
     * Registra métricas manualmente
     */
    public function recordMetrics(int $variationId): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $service = new CloneABTestingService($this->accountId);
            $service->recordMetrics($variationId, $input);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Métricas registradas',
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}
