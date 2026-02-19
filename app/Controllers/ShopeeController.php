<?php

namespace App\Controllers;

use App\Services\ShopeeService;
use App\Database;

class ShopeeController extends BaseController
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new ShopeeService();
    }

    public function index(): void
    {
        $authUrl = $this->service->getAuthUrl();
        $items = $this->service->getItems();
        
        // Pass to View
        // We'll mimic the standard View loading
        require __DIR__ . '/../Views/dashboard/shopee/index.php';
    }

    public function sync(): void
    {
        header('Content-Type: application/json');
        try {
            // Real sync: fetch items from Shopee API and store in database
            $items = $this->service->getItems();
            
            if (empty($items)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Nenhum item encontrado na Shopee. Verifique a autenticação da conta.',
                    'count' => 0
                ]);
                return;
            }
            
            // Store items in database
            $db = Database::getInstance();
            $count = 0;
            
            foreach ($items as $item) {
                $stmt = $db->prepare("
                    INSERT INTO shopee_items (item_id, shop_id, name, status, price, stock, updated_at)
                    VALUES (:item_id, :shop_id, :name, :status, :price, :stock, NOW())
                    ON DUPLICATE KEY UPDATE
                        name = VALUES(name),
                        status = VALUES(status),
                        price = VALUES(price),
                        stock = VALUES(stock),
                        updated_at = NOW()
                ");
                
                $stmt->execute([
                    'item_id' => $item['item_id'] ?? 0,
                    'shop_id' => $item['shop_id'] ?? 0,
                    'name' => $item['item_name'] ?? '',
                    'status' => $item['item_status'] ?? 'NORMAL',
                    'price' => $item['price_info']['current_price'] ?? 0,
                    'stock' => $item['stock_info_v2']['summary_info']['total_available_stock'] ?? 0,
                ]);
                $count++;
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Sincronização concluída: {$count} itens atualizados.",
                'count' => $count
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
