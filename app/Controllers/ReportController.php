<?php

namespace App\Controllers;

use App\Services\ReportService;
use App\Services\UserService;

class ReportController extends BaseController
{
    private ReportService $reportService;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();

        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $this->reportService = new ReportService();
    }

    /**
     * Render Reports Dashboard
     */
    public function index(): void
    {
        $pageTitle = 'Relatórios';
        $activePage = 'reports';

        ob_start();
        require __DIR__ . '/../Views/dashboard/reports/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Generate PDF Report
     */
    public function generatePdf(): void
    {
        header('Content-Type: application/json');

        $type = $this->request->post('type', 'sales');
        $start = $this->request->post('start_date', date('Y-m-01'));
        $end = $this->request->post('end_date', date('Y-m-d'));

        try {
            if ($type === 'sales') {
                $url = $this->reportService->generateSalesReport($start, $end);
            } elseif ($type === 'inventory') {
                $url = $this->reportService->generateInventoryReport();
            } elseif ($type === 'customer') {
                $url = $this->reportService->generateCustomerReport();
            } else {
                throw new \Exception("Tipo de relatório desconhecido: $type");
            }

            echo json_encode(['success' => true, 'url' => $url]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Generate CSV Export
     */
    public function generateCsv(): void
    {
        header('Content-Type: application/json');

        $start = $this->request->post('start_date', date('Y-m-01'));
        $end = $this->request->post('end_date', date('Y-m-d'));

        try {
            $url = $this->reportService->generateCsvExport($start, $end);
            echo json_encode(['success' => true, 'url' => $url]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Report by ML Account
     */
    public function byAccount(string $accountId): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $days = $this->request->getInt('days', 30);
            $cutoff = date('Y-m-d', strtotime("-{$days} days"));
            $accountId = (int) $accountId;

            $stmtOrders = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_ticket,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                FROM ml_orders
                WHERE seller_id = :account_id
                AND DATE(date_created) >= :cutoff
            ");
            $stmtOrders->execute([':account_id' => $accountId, ':cutoff' => $cutoff]);
            $orderStats = $stmtOrders->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmtItems = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM items
                WHERE seller_id = :account_id
                GROUP BY status
            ");
            $stmtItems->execute([':account_id' => $accountId]);
            $itemsByStatus = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

            $stmtQuestions = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'UNANSWERED' THEN 1 ELSE 0 END) as unanswered
                FROM ml_questions
                WHERE seller_id = :account_id
                AND created_at >= :cutoff
            ");
            $stmtQuestions->execute([':account_id' => $accountId, ':cutoff' => $cutoff . ' 00:00:00']);
            $questionStats = $stmtQuestions->fetch(\PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'data' => [
                    'account_id' => $accountId,
                    'period_days' => $days,
                    'orders' => [
                        'total' => (int) ($orderStats['total_orders'] ?? 0),
                        'revenue' => round((float) ($orderStats['total_revenue'] ?? 0), 2),
                        'avg_ticket' => round((float) ($orderStats['avg_ticket'] ?? 0), 2),
                        'paid' => (int) ($orderStats['paid_orders'] ?? 0),
                        'cancelled' => (int) ($orderStats['cancelled_orders'] ?? 0),
                    ],
                    'items_by_status' => $itemsByStatus,
                    'questions' => [
                        'total' => (int) ($questionStats['total'] ?? 0),
                        'unanswered' => (int) ($questionStats['unanswered'] ?? 0),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate account report: ' . $e->getMessage()]);
        }
    }

    /**
     * Report by Category
     */
    public function byCategory(string $categoryId): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $categoryId = trim($categoryId);

            $stmtItems = $db->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                    AVG(price) as avg_price,
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    SUM(available_quantity) as total_stock
                FROM items
                WHERE category_id = :cat_id
            ");
            $stmtItems->execute([':cat_id' => $categoryId]);
            $summary = $stmtItems->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmtTop = $db->prepare("
                SELECT 
                    item_id, title, price, available_quantity, status
                FROM items
                WHERE category_id = :cat_id
                ORDER BY price DESC
                LIMIT 20
            ");
            $stmtTop->execute([':cat_id' => $categoryId]);
            $topItems = $stmtTop->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'category_id' => $categoryId,
                    'summary' => [
                        'total_items' => (int) ($summary['total_items'] ?? 0),
                        'active' => (int) ($summary['active'] ?? 0),
                        'paused' => (int) ($summary['paused'] ?? 0),
                        'avg_price' => round((float) ($summary['avg_price'] ?? 0), 2),
                        'min_price' => round((float) ($summary['min_price'] ?? 0), 2),
                        'max_price' => round((float) ($summary['max_price'] ?? 0), 2),
                        'total_stock' => (int) ($summary['total_stock'] ?? 0),
                    ],
                    'top_items' => $topItems,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate category report: ' . $e->getMessage()]);
        }
    }

    /**
     * Report by Brand
     */
    public function byBrand(string $brand): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $brand = trim(urldecode($brand));

            $stmtItems = $db->prepare("
                SELECT 
                    COUNT(*) as total_items,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    AVG(price) as avg_price,
                    SUM(available_quantity) as total_stock,
                    COUNT(DISTINCT category_id) as categories
                FROM items
                WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.attributes[0].value_name')) LIKE :brand
                OR title LIKE :brand_title
            ");
            $stmtItems->execute([
                ':brand' => '%' . $brand . '%',
                ':brand_title' => '%' . $brand . '%',
            ]);
            $summary = $stmtItems->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmtItems2 = $db->prepare("
                SELECT 
                    item_id, title, price, status, available_quantity, category_id
                FROM items
                WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.attributes[0].value_name')) LIKE :brand
                OR title LIKE :brand_title
                ORDER BY price DESC
                LIMIT 20
            ");
            $stmtItems2->execute([
                ':brand' => '%' . $brand . '%',
                ':brand_title' => '%' . $brand . '%',
            ]);
            $items = $stmtItems2->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'brand' => $brand,
                    'summary' => [
                        'total_items' => (int) ($summary['total_items'] ?? 0),
                        'active' => (int) ($summary['active'] ?? 0),
                        'avg_price' => round((float) ($summary['avg_price'] ?? 0), 2),
                        'total_stock' => (int) ($summary['total_stock'] ?? 0),
                        'categories' => (int) ($summary['categories'] ?? 0),
                    ],
                    'items' => $items,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate brand report: ' . $e->getMessage()]);
        }
    }

    /**
     * Consolidated Report (all accounts)
     */
    public function consolidated(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $days = $this->request->getInt('days', 30);
            $cutoff = date('Y-m-d', strtotime("-{$days} days"));

            $stmtRevenue = $db->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_ticket,
                    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_revenue
                FROM ml_orders
                WHERE DATE(date_created) >= :cutoff
            ");
            $stmtRevenue->execute([':cutoff' => $cutoff]);
            $revenue = $stmtRevenue->fetch(\PDO::FETCH_ASSOC) ?: [];

            $stmtItems = $db->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(price * available_quantity) as inventory_value
                FROM items
                GROUP BY status
            ");
            $itemsByStatus = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

            $stmtTopCat = $db->prepare("
                SELECT 
                    i.category_id,
                    COUNT(DISTINCT i.item_id) as items,
                    COALESCE(SUM(o.total_amount), 0) as revenue
                FROM items i
                LEFT JOIN ml_orders o ON o.item_id = i.item_id 
                    AND DATE(o.date_created) >= :cutoff AND o.status = 'paid'
                GROUP BY i.category_id
                ORDER BY revenue DESC
                LIMIT 10
            ");
            $stmtTopCat->execute([':cutoff' => $cutoff]);
            $topCategories = $stmtTopCat->fetchAll(\PDO::FETCH_ASSOC);

            $stmtQuestions = $db->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'ANSWERED' THEN 1 ELSE 0 END) as answered,
                    SUM(CASE WHEN status = 'UNANSWERED' THEN 1 ELSE 0 END) as unanswered
                FROM ml_questions
                WHERE created_at >= :cutoff
            ");
            $stmtQuestions->execute([':cutoff' => $cutoff . ' 00:00:00']);
            $questions = $stmtQuestions->fetch(\PDO::FETCH_ASSOC) ?: [];

            echo json_encode([
                'success' => true,
                'data' => [
                    'period_days' => $days,
                    'revenue' => [
                        'total_orders' => (int) ($revenue['total_orders'] ?? 0),
                        'total_revenue' => round((float) ($revenue['total_revenue'] ?? 0), 2),
                        'avg_ticket' => round((float) ($revenue['avg_ticket'] ?? 0), 2),
                        'paid_revenue' => round((float) ($revenue['paid_revenue'] ?? 0), 2),
                    ],
                    'inventory' => $itemsByStatus,
                    'top_categories' => $topCategories,
                    'questions' => [
                        'total' => (int) ($questions['total'] ?? 0),
                        'answered' => (int) ($questions['answered'] ?? 0),
                        'unanswered' => (int) ($questions['unanswered'] ?? 0),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to generate consolidated report: ' . $e->getMessage()]);
        }
    }
}
