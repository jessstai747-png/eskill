<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\StructuredLogService;
use App\Services\UserService;

/**
 * Controller para visualização e gerenciamento de logs
 */
class LogController extends BaseController
{
    private StructuredLogService $logService;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();

        $this->userService = new UserService();
        if (!$this->userService->isAuthenticated()) {
             header('Location: /login');
             exit;
        }

        $this->logService = new StructuredLogService();
    }

    /**
     * Página principal de logs
     */
    public function index(): void
    {
        $filters = [
            'limit' => $this->request->getInt('limit', 100),
            'level' => $this->request->get('level'),
            'search' => $this->request->get('search'),
            'start_date' => $this->request->get('start_date'),
            'end_date' => $this->request->get('end_date')
        ];

        $logs = $this->logService->search($filters);
        $stats = $this->logService->getStatistics();

        $pageTitle = 'Logs do Sistema';
        $activePage = 'logs';

        ob_start();
        require __DIR__ . '/../Views/dashboard/logs/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * API: Buscar logs com filtros
     */
    public function search(): void
    {
        header('Content-Type: application/json');

        $filters = [
            'limit' => $this->request->getInt('limit', 100),
            'level' => $this->request->get('level'),
            'search' => $this->request->get('search'),
            'start_date' => $this->request->get('start_date'),
            'end_date' => $this->request->get('end_date')
        ];

        $logs = $this->logService->search($filters);

        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }

    /**
     * API: Estatísticas dos logs
     */
    public function statistics(): void
    {
        header('Content-Type: application/json');

        $period = $this->request->get('period', '24h');
        $stats = $this->logService->getStatistics($period);

        echo json_encode([
            'success' => true,
            'statistics' => $stats
        ]);
    }

    /**
     * API: Limpar logs antigos
     */
    public function cleanup(): void
    {
        header('Content-Type: application/json');

        if ($this->request->method() !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        $days = $this->request->postInt('days', 30);
        $deleted = $this->logService->cleanup((int) $days);

        echo json_encode([
            'success' => true,
            'deleted' => $deleted,
            'message' => "Removidos {$deleted} arquivos de log antigos"
        ]);
    }

    /**
     * API: Exportar logs em CSV
     */
    public function export(): void
    {
        $filters = [
            'limit' => $this->request->getInt('limit', 1000),
            'level' => $this->request->get('level'),
            'search' => $this->request->get('search')
        ];

        $logs = $this->logService->search($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="logs_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['Timestamp', 'Level', 'Message', 'Context', 'User ID', 'IP']);

        // Dados
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['datetime'] ?? '',
                $log['level_name'] ?? '',
                $log['message'] ?? '',
                json_encode($log['context'] ?? []),
                $log['extra']['user_id'] ?? '',
                $log['extra']['ip'] ?? ''
            ]);
        }

        fclose($output);
    }
}
