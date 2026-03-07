<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\UserService;

class AuditController extends BaseController
{
    private AuditService $auditService;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
        
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        // Admin permission check - only admins and managers can view audit logs
        if (!$this->userService->hasPermission('audit')) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Acesso negado. Permissão de auditoria necessária.',
                'required_role' => 'admin ou manager'
            ]);
            exit;
        }
        
        $this->auditService = new AuditService();
    }

    public function index(): void
    {
        $logs = $this->auditService->getLogs(100);
        
        $pageTitle = 'Audit logs';
        $activePage = 'audit';

        // Pass to view
        ob_start();
        require __DIR__ . '/../Views/dashboard/audit/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }
}
