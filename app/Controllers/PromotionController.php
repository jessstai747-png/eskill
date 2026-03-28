<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PromotionService;
use App\Services\UserService;

class PromotionController extends BaseController
{
    private PromotionService $promotionService;
    private UserService $userService;

    public function __construct()
    {
        // parent::__construct();
        $this->userService = new UserService();
        
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $this->promotionService = new PromotionService($_SESSION['active_ml_account_id'] ?? null);
    }

    /**
     * Render Promotions Dashboard
     */
    public function index(): void
    {
        $pageTitle = 'Promoções';
        $activePage = 'promotions';

        ob_start();
        require __DIR__ . '/../Views/dashboard/marketing/promotions.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * API: List Promotions
     */
    public function listPromotions(): void
    {
        header('Content-Type: application/json');
        try {
            $promotions = $this->promotionService->getPromotions();
            echo json_encode([
                'success' => true,
                'promotions' => $promotions['promotions'] ?? [],
                'total' => $promotions['total'] ?? 0,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    /**
     * API: Get details (items) for a promotion
     */
    public function detail(): void
    {
        header('Content-Type: application/json');
        $id = filter_input(INPUT_GET, 'id');
        if (!$id) {
             http_response_code(400);
             echo json_encode(['error' => 'ID required']);
             return;
        }
        
        try {
            $items = $this->promotionService->getPromotionItems($id);
            echo json_encode(['success' => true, 'items' => $items]);
        } catch (\Exception $e) {
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Join Promotion
     */
    public function join(): void
    {
        header('Content-Type: application/json');
        $data = $this->request->json();
        
        $promotionId = $data['promotion_id'] ?? null;
        $items = $data['items'] ?? []; // [{item_id, price}]
        
        if (!$promotionId || empty($items)) {
             http_response_code(400);
             echo json_encode(['error' => 'Invalid parameters']);
             return;
        }
        
        try {
            $result = $this->promotionService->joinPromotion($promotionId, $items);
            echo json_encode($result);
        } catch (\Exception $e) {
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
