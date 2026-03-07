<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditLogService;
use App\Services\UserService;

class ActivityController extends BaseController
{
    private AuditLogService $auditService;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditLogService();
        $this->userService = new UserService();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Lista atividades do usuário
     */
    public function index(): void
    {
        $userId = $_SESSION['user_id'];
        $limit = $this->request->getInt('limit', 50);
        
        $filters = [
            'user_id' => $userId,
            'limit' => $limit
        ];
        
        $dateFrom = $this->request->get('date_from');
        if (!empty($dateFrom)) {
            $filters['date_from'] = $dateFrom . ' 00:00:00';
        }
        
        $dateTo = $this->request->get('date_to');
        if (!empty($dateTo)) {
            $filters['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $action = $this->request->get('action');
        if (!empty($action)) {
            $filters['action'] = $action;
        }

        $activities = $this->auditService->getLogs($filters);

        $this->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    /**
     * Lista todas as atividades (admin)
     */
    public function all(): void
    {
        $limit = $this->request->getInt('limit', 100);
        
        $filters = ['limit' => $limit];
        
        $userIdFilter = $this->request->getInt('user_id', 0);
        if ($userIdFilter > 0) {
            $filters['user_id'] = $userIdFilter;
        }

        $dateFrom = $this->request->get('date_from');
        if (!empty($dateFrom)) {
            $filters['date_from'] = $dateFrom . ' 00:00:00';
        }
        
        $dateTo = $this->request->get('date_to');
        if (!empty($dateTo)) {
            $filters['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $action = $this->request->get('action');
        if (!empty($action)) {
            $filters['action'] = $action;
        }

        $activities = $this->auditService->getLogs($filters);

        $this->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    /**
     * Exporta atividades em CSV
     */
    public function export(): void
    {
        $userId = $_SESSION['user_id'];
        $limit = $this->request->getInt('limit', 1000);
        
        $filters = [
            'user_id' => $userId,
            'limit' => $limit
        ];
        
        $dateFrom = $this->request->get('date_from');
        if (!empty($dateFrom)) {
            $filters['date_from'] = $dateFrom . ' 00:00:00';
        }
        
        $dateTo = $this->request->get('date_to');
        if (!empty($dateTo)) {
            $filters['date_to'] = $dateTo . ' 23:59:59';
        }
        
        $action = $this->request->get('action');
        if (!empty($action)) {
            $filters['action'] = $action;
        }

        $activities = $this->auditService->getLogs($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activities_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, ['Data', 'Ação', 'Recurso', 'IP', 'Detalhes']);

        // Dados
        foreach ($activities as $activity) {
            fputcsv($output, [
                $activity['created_at'],
                $activity['action'],
                $activity['resource'],
                $activity['ip_address'],
                json_encode($activity['data'] ?? [])
            ]);
        }

        fclose($output);
    }
}
