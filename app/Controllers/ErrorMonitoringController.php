<?php

namespace App\Controllers;

use App\Services\ErrorMonitoringService;

/**
 * Controller para Monitoramento de Erros
 */
class ErrorMonitoringController extends BaseController
{
    private ErrorMonitoringService $errorMonitoring;
    
    public function __construct()
    {
        parent::__construct();
        $this->errorMonitoring = new ErrorMonitoringService();
    }
    
    /**
     * GET /api/monitoring/errors/recent
     * Lista erros recentes
     */
    public function recent(): void
    {
        header('Content-Type: application/json');
        
        $limit = $this->request->getInt('limit', 50);
        $severity = $this->request->get('severity');
        
        $errors = $this->errorMonitoring->getRecentErrors($limit, $severity);
        
        echo json_encode([
            'success' => true,
            'count' => count($errors),
            'errors' => $errors
        ]);
    }
    
    /**
     * GET /api/monitoring/errors/stats
     * Estatísticas de erros
     */
    public function stats(): void
    {
        header('Content-Type: application/json');
        
        $hours = $this->request->getInt('hours', 24);
        
        $stats = $this->errorMonitoring->getErrorStats($hours);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
    }
    
    /**
     * GET /api/monitoring/errors/analyze-log
     * Analisa arquivo de log
     */
    public function analyzeLog(): void
    {
        header('Content-Type: application/json');
        
        $filename = $this->request->get('file', 'error.log');
        $lines = $this->request->getInt('lines', 100);
        
        $analysis = $this->errorMonitoring->analyzeLogFile($filename, $lines);
        
        echo json_encode([
            'success' => true,
            'analysis' => $analysis
        ]);
    }
    
    /**
     * POST /api/monitoring/errors/clean
     * Limpa erros antigos
     */
    public function clean(): void
    {
        header('Content-Type: application/json');
        
        $days = $this->request->postInt('days', 30);
        
        $deleted = $this->errorMonitoring->cleanOldErrors($days);
        
        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Removidos {$deleted} erros com mais de {$days} dias"
        ]);
    }
    
    /**
     * POST /api/monitoring/errors/log
     * Registra erro manualmente (para JavaScript, etc)
     */
    public function log(): void
    {
        header('Content-Type: application/json');
        
        $data = $this->request->json();
        
        if (!$data || !isset($data['message'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Dados inválidos'
            ]);
            return;
        }
        
        $this->errorMonitoring->logError($data);
        
        echo json_encode([
            'success' => true,
            'message' => 'Erro registrado'
        ]);
    }
}
