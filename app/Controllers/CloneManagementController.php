<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CloneSyncService;
use App\Services\CloneItemManagerService;

/**
 * CloneManagementController
 * 
 * API para gerenciamento e sincronização de itens clonados.
 */
class CloneManagementController extends BaseController
{
    private int $accountId;
    
    public function __construct()
    {
        parent::__construct();
        $this->accountId = $this->requireAccountId();
    }
    
    // ==========================================
    // GERENCIAMENTO DE ITENS
    // ==========================================
    
    /**
     * GET /api/clone/items
     * Lista itens clonados com filtros
     */
    public function listItems(): void
    {
        $filters = [
            'page' => $this->request->getInt('page', 1),
            'limit' => $this->request->getInt('limit', 20),
            'status' => $this->request->get('status'),
            'category_id' => $this->request->get('category_id'),
            'source_seller_id' => $this->request->get('seller_id'),
            'search' => $this->request->get('search'),
            'date_from' => $this->request->get('date_from'),
            'date_to' => $this->request->get('date_to'),
            'has_sales' => $this->request->get('has_sales') !== null,
            'sort' => $this->request->get('sort') ?? 'created_at',
            'order' => $this->request->get('order') ?? 'DESC'
        ];
        
        $manager = new CloneItemManagerService($this->accountId);
        $result = $manager->listItems(array_filter($filters));
        
        $this->json($result);
    }
    
    /**
     * GET /api/clone/items/{itemId}
     * Detalhes de um item clonado
     */
    public function getItem(string $itemId): void
    {
        $manager = new CloneItemManagerService($this->accountId);
        $item = $manager->getItem($itemId);
        
        if (!$item) {
            $this->jsonError('Item não encontrado', 404);
            return;
        }
        
        $this->json($item);
    }
    
    /**
     * PUT /api/clone/items/{itemId}
     * Atualiza um item clonado
     */
    public function updateItem(string $itemId): void
    {
        $data = $this->getJsonInput();
        
        if (empty($data)) {
            $this->jsonError('Dados inválidos', 400);
            return;
        }
        
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $result = $manager->updateItem($itemId, $data);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * POST /api/clone/items/{itemId}/pause
     * Pausa um item
     */
    public function pauseItem(string $itemId): void
    {
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $result = $manager->pauseItem($itemId);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * POST /api/clone/items/{itemId}/activate
     * Ativa um item
     */
    public function activateItem(string $itemId): void
    {
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $result = $manager->activateItem($itemId);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * POST /api/clone/items/{itemId}/close
     * Encerra um item
     */
    public function closeItem(string $itemId): void
    {
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $result = $manager->closeItem($itemId);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * POST /api/clone/items/batch
     * Operações em lote
     */
    public function batchOperation(): void
    {
        $data = $this->getJsonInput();
        
        $operation = $data['operation'] ?? null;
        $itemIds = $data['item_ids'] ?? [];
        
        if (!$operation || empty($itemIds)) {
            $this->jsonError('Parâmetros inválidos: operation e item_ids são obrigatórios', 400);
            return;
        }
        
        $validOperations = ['pause', 'activate', 'close', 'sync'];
        if (!in_array($operation, $validOperations)) {
            $this->jsonError("Operação inválida. Use: " . implode(', ', $validOperations), 400);
            return;
        }
        
        try {
            $manager = new CloneItemManagerService($this->accountId);
            $result = $manager->batchOperation($operation, $itemIds);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/clone/items/stats
     * Estatísticas gerais
     */
    public function getStats(): void
    {
        $manager = new CloneItemManagerService($this->accountId);
        $stats = $manager->getStats();
        $this->json($stats);
    }
    
    /**
     * GET /api/clone/items/top-sellers
     * Top itens por vendas
     */
    public function getTopSellers(): void
    {
        $limit = $this->request->getInt('limit', 10);
        
        $manager = new CloneItemManagerService($this->accountId);
        $items = $manager->getTopSellers($limit);
        $this->json(['items' => $items]);
    }
    
    /**
     * GET /api/clone/items/attention
     * Itens que precisam atenção
     */
    public function getItemsNeedingAttention(): void
    {
        $limit = $this->request->getInt('limit', 20);
        
        $manager = new CloneItemManagerService($this->accountId);
        $items = $manager->getItemsNeedingAttention($limit);
        $this->json(['items' => $items]);
    }
    
    /**
     * GET /api/clone/items/distribution/category
     * Distribuição por categoria
     */
    public function getCategoryDistribution(): void
    {
        $manager = new CloneItemManagerService($this->accountId);
        $distribution = $manager->getCategoryDistribution();
        $this->json(['categories' => $distribution]);
    }
    
    /**
     * GET /api/clone/items/distribution/seller
     * Distribuição por vendedor
     */
    public function getSellerDistribution(): void
    {
        $manager = new CloneItemManagerService($this->accountId);
        $distribution = $manager->getSellerDistribution();
        $this->json(['sellers' => $distribution]);
    }
    
    /**
     * GET /api/clone/items/export
     * Exporta dados para CSV
     */
    public function exportItems(): void
    {
        $filters = [
            'status' => $this->request->get('status'),
            'category_id' => $this->request->get('category_id'),
            'source_seller_id' => $this->request->get('seller_id'),
            'date_from' => $this->request->get('date_from'),
            'date_to' => $this->request->get('date_to')
        ];
        
        $manager = new CloneItemManagerService($this->accountId);
        $csv = $manager->exportToCsv(array_filter($filters));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cloned_items_' . date('Y-m-d') . '.csv"');
        echo $csv;
    }
    
    // ==========================================
    // SINCRONIZAÇÃO
    // ==========================================
    
    /**
     * POST /api/clone/sync/all
     * Sincroniza todos os itens
     */
    public function syncAll(): void
    {
        $data = $this->getJsonInput();
        
        $options = [
            'limit' => (int) ($data['limit'] ?? 100),
            'days' => (int) ($data['days'] ?? 30),
            'types' => $data['types'] ?? ['price', 'stock', 'status', 'metrics']
        ];
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->syncAll($options);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }
    
    /**
     * POST /api/clone/sync/item/{itemId}
     * Sincroniza um item específico
     */
    public function syncItem(string $itemId): void
    {
        $data = $this->getJsonInput();
        
        $options = [
            'types' => $data['types'] ?? ['price', 'stock', 'status', 'metrics'],
            'source_item_id' => $data['source_item_id'] ?? null
        ];
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->syncItem($itemId, $options);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }
    
    /**
     * PUT /api/clone/sync/price/{itemId}
     * Atualiza preço de um item
     */
    public function updatePrice(string $itemId): void
    {
        $data = $this->getJsonInput();
        
        if (!isset($data['price'])) {
            $this->jsonError('Preço é obrigatório', 400);
            return;
        }
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->updatePrice($itemId, (float) $data['price'], [
                'reason' => $data['reason'] ?? 'api_update'
            ]);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * PUT /api/clone/sync/stock/{itemId}
     * Atualiza estoque de um item
     */
    public function updateStock(string $itemId): void
    {
        $data = $this->getJsonInput();
        
        if (!isset($data['quantity'])) {
            $this->jsonError('Quantidade é obrigatória', 400);
            return;
        }
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->updateStock($itemId, (int) $data['quantity'], [
                'reason' => $data['reason'] ?? 'api_update'
            ]);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * PUT /api/clone/sync/status/{itemId}
     * Atualiza status de um item
     */
    public function updateStatus(string $itemId): void
    {
        $data = $this->getJsonInput();
        
        if (!isset($data['status'])) {
            $this->jsonError('Status é obrigatório', 400);
            return;
        }
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->updateStatus($itemId, $data['status']);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * POST /api/clone/sync/prices/batch
     * Atualização em lote de preços
     */
    public function batchUpdatePrices(): void
    {
        $data = $this->getJsonInput();
        
        if (empty($data['updates']) || !is_array($data['updates'])) {
            $this->jsonError('Lista de atualizações é obrigatória', 400);
            return;
        }
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $result = $sync->batchUpdatePrices($data['updates']);
            $this->json($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }
    
    /**
     * GET /api/clone/sync/history/{itemId}
     * Histórico de sincronização
     */
    public function getSyncHistory(string $itemId): void
    {
        $options = [
            'limit' => $this->request->getInt('limit', 50),
            'type' => $this->request->get('type')
        ];
        
        $sync = new CloneSyncService($this->accountId);
        $history = $sync->getSyncHistory($itemId, $options);
        $this->json(['history' => $history]);
    }
    
    /**
     * GET /api/clone/sync/settings
     * Obtém configurações de sincronização
     */
    public function getSyncSettings(): void
    {
        $sync = new CloneSyncService($this->accountId);
        $settings = $sync->getSyncSettings();
        $this->json(['settings' => $settings]);
    }
    
    /**
     * PUT /api/clone/sync/settings
     * Atualiza configurações de sincronização
     */
    public function updateSyncSettings(): void
    {
        $data = $this->getJsonInput();
        
        if (empty($data)) {
            $this->jsonError('Configurações são obrigatórias', 400);
            return;
        }
        
        try {
            $sync = new CloneSyncService($this->accountId);
            $settings = $sync->updateSyncSettings($data);
            $this->json(['settings' => $settings]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 400);
        }
    }
    
    /**
     * GET /api/clone/sync/alerts
     * Lista alertas pendentes
     */
    public function getAlerts(): void
    {
        $limit = $this->request->getInt('limit', 50);
        
        $sync = new CloneSyncService($this->accountId);
        $alerts = $sync->getPendingAlerts(['limit' => $limit]);
        $this->json(['alerts' => $alerts]);
    }
    
    /**
     * POST /api/clone/sync/alerts/{alertId}/resolve
     * Resolve um alerta
     */
    public function resolveAlert(int $alertId): void
    {
        $data = $this->getJsonInput();
        $resolution = $data['resolution'] ?? 'resolved';
        
        $sync = new CloneSyncService($this->accountId);
        $result = $sync->resolveAlert($alertId, $resolution);
        
        $this->json(['success' => $result]);
    }
    
    // ==========================================
    // HELPERS
    // ==========================================
    
    private function getJsonInput(): array
    {
        return $this->request->json() ?? [];
    }
}
