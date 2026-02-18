<?php

namespace App\Controllers;

use App\Services\QueryOptimizerService;

class OptimizationController
{
    private QueryOptimizerService $optimizerService;

    public function __construct()
    {
        $this->optimizerService = new QueryOptimizerService();
    }

    /**
     * Analisa queries e sugere otimizações
     * GET /api/optimization/analyze
     */
    public function analyze(): void
    {
        $analysis = $this->optimizerService->analyzeSlowQueries();

        header('Content-Type: application/json');
        echo json_encode($analysis);
    }

    /**
     * Executa ANALYZE TABLE em todas as tabelas
     * POST /api/optimization/analyze-tables
     */
    public function analyzeTables(): void
    {
        $results = $this->optimizerService->analyzeTables();

        header('Content-Type: application/json');
        echo json_encode($results);
    }

    /**
     * Otimiza uma tabela específica
     * POST /api/optimization/optimize-table
     */
    public function optimizeTable(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['table'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campo "table" é obrigatório']);
            return;
        }

        // Validate table name — only alphanumeric and underscores allowed
        $table = $data['table'];
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $table)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nome de tabela inválido']);
            return;
        }

        $result = $this->optimizerService->optimizeTable($table);

        echo json_encode($result);
    }

    /**
     * Obtém estatísticas de uso de índices
     * GET /api/optimization/index-stats
     */
    public function indexStats(): void
    {
        $stats = $this->optimizerService->getIndexUsageStats();

        header('Content-Type: application/json');
        echo json_encode($stats);
    }
}
