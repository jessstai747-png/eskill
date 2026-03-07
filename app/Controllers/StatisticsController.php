<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\StatisticsService;

class StatisticsController
{
    private StatisticsService $statisticsService;
    private Request $request;
    
    public function __construct()
    {
        $this->statisticsService = new StatisticsService();
        $this->request = new Request();
    }
    
    /**
     * Obtém estatísticas gerais
     * GET /api/statistics
     */
    public function index(): void
    {
        $stats = $this->statisticsService->getGeneralStats();
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Obtém estatísticas por período
     * GET /api/statistics/period
     */
    public function byPeriod(): void
    {
        $startDate = $this->request->get('start_date', date('Y-m-d', strtotime('-30 days'))) ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $this->request->get('end_date', date('Y-m-d')) ?? date('Y-m-d');
        
        // Security: validate date format to prevent unexpected input
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
            return;
        }
        
        $stats = $this->statisticsService->getStatsByPeriod($startDate, $endDate);
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
    
    /**
     * Obtém top produtos
     * GET /api/statistics/top-products
     */
    public function topProducts(): void
    {
        $limit = $this->request->getInt('limit', 10);
        
        $products = $this->statisticsService->getTopProducts($limit);
        
        header('Content-Type: application/json');
        echo json_encode($products);
    }
    
    /**
     * Obtém estatísticas por categoria
     * GET /api/statistics/by-category
     */
    public function byCategory(): void
    {
        $stats = $this->statisticsService->getStatsByCategory();
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    }
}
