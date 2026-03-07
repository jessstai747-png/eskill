<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CompetitorService;
use App\Services\UserService;
use App\Core\Flash;

class CompetitorAnalysisController extends BaseController
{
    private CompetitorService $service;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userService = new UserService();

        if (!$this->userService->isAuthenticated()) {
            // Check if headers have already been sent to prevent "headers already sent" error
            if (!headers_sent()) {
                header('Location: /login');
                exit;
            } else {
                // If headers are already sent, we can't redirect
                http_response_code(401);
                echo json_encode(['error' => 'Access denied. Please log in.']);
                exit;
            }
        }

        $this->service = new CompetitorService($_SESSION['account_id'] ?? null);
    }

    public function index()
    {
        try {
            // Get all items
            $db = \App\Database::getInstance();
            $stmt = $db->query("
                SELECT ci.*, 
                       (SELECT price FROM competitor_price_history cph WHERE cph.competitor_item_id = ci.id ORDER BY recorded_at ASC LIMIT 1) as first_price,
                       (SELECT recorded_at FROM competitor_price_history cph WHERE cph.competitor_item_id = ci.id ORDER BY recorded_at ASC LIMIT 1) as first_date
                FROM competitor_items ci 
                WHERE status != 'closed'
                ORDER BY updated_at DESC
            ");
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // If table doesn't exist, start with empty items
            $items = [];
        }
        
        // Alerts
        try {
            $alerts = $this->service->getRecentAlerts(5);
        } catch (\Exception $e) {
            $alerts = [];
        }

        $pageTitle = 'Análise de Concorrência';
        $activePage = 'competitors';

        // View::render replacement
        ob_start();
        extract([
            'items' => $items,
            'alerts' => $alerts
        ]);
        require __DIR__ . '/../Views/dashboard/competitors/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    public function details(string $id)
    {
        try {
            $db = \App\Database::getInstance();
            
            // Item Details
            $stmt = $db->prepare("SELECT * FROM competitor_items WHERE ml_item_id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$item) {
                Flash::error('Competidor não encontrado.');
                header('Location: /dashboard/competitors');
                exit;
            }

            // History
            $history = $this->service->getPriceHistory($id, 90); // 90 days
        } catch (\Exception $e) {
            Flash::error('Erro ao carregar detalhes do competidor: ' . $e->getMessage());
            header('Location: /dashboard/competitors');
            exit;
        }

        $pageTitle = 'Detalhes do Competidor';
        $activePage = 'competitors';

        // View::render replacement
        ob_start();
        extract([
            'item' => $item,
            'history' => $history
        ]);
        require __DIR__ . '/../Views/dashboard/competitors/details.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
             header('Location: /dashboard/competitors');
             exit;
        }

        $url = $this->request->post('url', '');
        
        // Extract ID from URL (MLB...)
        if (preg_match('/MLB-?(\d+)/', $url, $matches)) {
            $id = 'MLB' . $matches[1];
        } elseif (preg_match('/MLB(\d+)/', $url, $matches)) {
            $id = 'MLB' . $matches[1];
        } else {
            // Try raw ID if posted
            $id = $url;
        }

        if (empty($id)) {
            Flash::error('URL ou ID inválido.');
        } else {
            $res = $this->service->addItemToWatch($id);
            if ($res['success']) {
                Flash::success($res['message']);
            } else {
                Flash::error($res['error']);
            }
        }

        header('Location: /dashboard/competitors');
    }
}
