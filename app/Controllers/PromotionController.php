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
        parent::__construct();
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
        $id = trim((string) (filter_input(INPUT_GET, 'id') ?? ''));
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            return;
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id)) {
            http_response_code(422);
            echo json_encode(['error' => 'ID inválido']);
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
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados inválidos: esperado objeto JSON']);
            return;
        }

        $promotionId = isset($data['promotion_id']) ? trim((string)$data['promotion_id']) : '';
        $items = $data['items'] ?? []; // [{item_id, price}]
        if (!is_array($items)) {
            $items = [];
        }

        if ($promotionId === '' || empty($items)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            return;
        }

        $sanitizedItems = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemId = trim((string)($item['item_id'] ?? ''));
            $price = isset($item['price']) ? (float)$item['price'] : 0.0;
            if ($itemId === '' || $price <= 0) {
                continue;
            }

            $sanitizedItems[] = [
                'item_id' => $itemId,
                'price' => round($price, 2),
            ];
        }

        if ($sanitizedItems === []) {
            http_response_code(422);
            echo json_encode(['error' => 'Itens inválidos para adesão à promoção']);
            return;
        }

        try {
            $result = $this->promotionService->joinPromotion($promotionId, $sanitizedItems);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: List coupons
     * GET /api/marketing/coupons
     */
    public function listCoupons(): void
    {
        header('Content-Type: application/json');
        try {
            $result = $this->promotionService->listCoupons();
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Create coupon
     * POST /api/marketing/coupons
     */
    public function createCoupon(): void
    {
        header('Content-Type: application/json');
        $data = $this->request->json();

        if (!is_array($data) || empty($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados do cupom são obrigatórios']);
            return;
        }

        if (count($data) > 30) {
            http_response_code(422);
            echo json_encode(['error' => 'Payload de cupom excede limite permitido']);
            return;
        }

        try {
            $result = $this->promotionService->createCoupon($data);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Update coupon status (pause/activate)
     * POST /api/marketing/coupons/{id}/status
     */
    public function updateCouponStatus(string $id): void
    {
        header('Content-Type: application/json');
        $data = $this->request->json();
        $status = is_array($data) ? (string) ($data['status'] ?? '') : '';

        if (!in_array($status, ['active', 'paused', 'finished'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Status inválido. Use: active, paused ou finished']);
            return;
        }

        try {
            $result = $this->promotionService->updateCouponStatus($id, $status);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get coupon performance metrics
     * GET /api/marketing/coupons/{id}/performance
     */
    public function couponPerformance(string $id): void
    {
        header('Content-Type: application/json');
        try {
            $result = $this->promotionService->getCouponPerformance($id);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Analyze promotions performance
     * GET /api/marketing/promotions/performance
     */
    public function performanceAnalysis(): void
    {
        header('Content-Type: application/json');
        $filters = [
            'period'     => (int) ($this->request->get('period') ?? 30),
            'account_id' => $this->request->get('account_id'),
        ];

        try {
            $result = $this->promotionService->analyzePromotionsPerformance($filters);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Get AI-suggested items for promotions
     * GET /api/marketing/promotions/suggested-items
     */
    public function suggestedItems(): void
    {
        header('Content-Type: application/json');
        $criteria = [
            'limit'       => max(1, min(100, (int) ($this->request->get('limit') ?? 20))),
            'min_stock'   => (int) ($this->request->get('min_stock') ?? 0),
            'category_id' => $this->request->get('category_id'),
        ];

        try {
            $result = $this->promotionService->getSuggestedItems($criteria);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * API: Simulate discount impact for an item
     * GET /api/marketing/promotions/simulate
     */
    public function simulateDiscount(): void
    {
        header('Content-Type: application/json');
        $itemId     = (string) ($this->request->get('item_id') ?? '');
        $discount   = (float) ($this->request->get('discount') ?? 0);

        if ($itemId === '' || $discount <= 0 || $discount >= 100) {
            http_response_code(400);
            echo json_encode(['error' => 'item_id e discount (0-100) são obrigatórios']);
            return;
        }

        try {
            $result = $this->promotionService->simulateDiscountImpact($itemId, $discount);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
