<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\InventoryService;

/**
 * Inventory Advanced Controller
 * 
 * REST API para gerenciamento avançado de estoque
 * 
 * Endpoints:
 * - GET    /api/inventory/:accountId/multi-origin/:sku
 * - PUT    /api/inventory/:accountId/origin
 * - POST   /api/inventory/:accountId/reservation
 * - DELETE /api/inventory/:accountId/reservation/:reservationId
 * - POST   /api/inventory/:accountId/cleanup-reservations
 * - POST   /api/inventory/:accountId/bulk-sync
 * - GET    /api/inventory/:accountId/movements/:sku
 */
class InventoryAdvancedController
{
    private InventoryService $inventoryService;
    private Request $request;

    public function __construct(int $accountId)
    {
        $this->inventoryService = new InventoryService($accountId);
        $this->request = new Request();
    }

    /**
     * GET /api/inventory/:accountId/multi-origin/:sku
     * Obtém estoque por origem (warehouse, dropshipping, store)
     */
    public function getMultiOrigin(string $sku): void
    {
        header('Content-Type: application/json');

        $result = $this->inventoryService->getMultiOriginStock($sku);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * PUT /api/inventory/:accountId/origin
     * Atualiza estoque de uma origem específica
     * 
     * Body: {
     *   "sku": "SKU123",
     *   "origin": "warehouse",
     *   "quantity": 50,
     *   "location": "Prateleira A-12"
     * }
     */
    public function updateOrigin(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['sku'], $data['origin'], $data['quantity'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'sku, origin, and quantity are required'
            ]);
            return;
        }

        $result = $this->inventoryService->updateOriginStock(
            $data['sku'],
            $data['origin'],
            (int)$data['quantity'],
            $data['location'] ?? null
        );
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/inventory/:accountId/reservation
     * Cria reserva de estoque (expira em 1 hora)
     * 
     * Body: {
     *   "sku": "SKU123",
     *   "quantity": 2,
     *   "order_id": "ORDER123",
     *   "metadata": {"customer": "João Silva"}
     * }
     */
    public function createReservation(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['sku'], $data['quantity'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'sku and quantity are required'
            ]);
            return;
        }

        $result = $this->inventoryService->createReservation(
            $data['sku'],
            (int)$data['quantity'],
            $data['order_id'] ?? null,
            $data['metadata'] ?? null
        );
        
        http_response_code($result['success'] ? 201 : 500);
        echo json_encode($result);
    }

    /**
     * DELETE /api/inventory/:accountId/reservation/:reservationId
     * Libera uma reserva de estoque
     */
    public function releaseReservation(string $reservationId): void
    {
        header('Content-Type: application/json');

        $result = $this->inventoryService->releaseReservation($reservationId);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/inventory/:accountId/cleanup-reservations
     * Limpa reservas expiradas (útil para CRON jobs)
     */
    public function cleanupReservations(): void
    {
        header('Content-Type: application/json');

        $result = $this->inventoryService->cleanExpiredReservations();
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * POST /api/inventory/:accountId/bulk-sync
     * Sincronização em lote de múltiplos SKUs
     * 
     * Body: {
     *   "items": [
     *     {"sku": "SKU123", "quantity": 50},
     *     {"sku": "SKU456", "quantity": 30}
     *   ]
     * }
     */
    public function bulkSync(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['items']) || !is_array($data['items'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'items array is required'
            ]);
            return;
        }

        $result = $this->inventoryService->bulkSync($data['items']);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }

    /**
     * GET /api/inventory/:accountId/movements/:sku?type=sale&limit=100
     * Obtém histórico de movimentações
     */
    public function getMovements(string $sku): void
    {
        header('Content-Type: application/json');

        $type = $this->request->get('type');
        $limit = $this->request->getInt('limit', 100);
        $filters = [
            'type' => $type,
            'limit' => $limit,
        ];

        $result = $this->inventoryService->getMovementHistory($sku, $filters);
        
        http_response_code($result['success'] ? 200 : 500);
        echo json_encode($result);
    }
}
