<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneSyncService
 * 
 * Serviço de sincronização bidirecional de dados entre itens originais e clones.
 * Gerencia atualizações de preços, estoque, métricas e status.
 */
class CloneSyncService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;
    
    // Configurações de sincronização
    private array $syncConfig = [
        'price' => ['enabled' => true, 'mode' => 'manual'], // manual, auto, scheduled
        'stock' => ['enabled' => true, 'mode' => 'auto'],
        'status' => ['enabled' => true, 'mode' => 'auto'],
        'metrics' => ['enabled' => true, 'mode' => 'scheduled'],
        'description' => ['enabled' => false, 'mode' => 'manual'],
        'images' => ['enabled' => false, 'mode' => 'manual'],
    ];
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->loadSyncConfig();
    }
    
    /**
     * Carrega configurações de sincronização do usuário
     */
    private function loadSyncConfig(): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT sync_config FROM clone_sync_settings 
                WHERE account_id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && !empty($row['sync_config'])) {
                $this->syncConfig = array_merge(
                    $this->syncConfig,
                    json_decode($row['sync_config'], true) ?? []
                );
            }
        } catch (\Exception $e) {
            // Use defaults
        }
    }
    
    /**
     * Retorna cliente ML (lazy loading)
     */
    private function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }
    
    /**
     * Sincroniza todos os itens clonados recentes
     */
    public function syncAll(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 100);
        $days = (int) ($options['days'] ?? 30);
        $types = $options['types'] ?? ['price', 'stock', 'status', 'metrics'];

        $limitSql = max(1, min((int)$limit, 500));
        
        $results = [
            'total' => 0,
            'synced' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => []
        ];
        
        // Buscar clones recentes
        $stmt = $this->db->prepare("
            SELECT id, source_item_id, target_item_id, source_snapshot, target_snapshot
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            AND status = 'completed'
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        $clones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results['total'] = count($clones);
        
        foreach ($clones as $clone) {
            try {
                $syncResult = $this->syncItem($clone['target_item_id'], [
                    'source_item_id' => $clone['source_item_id'],
                    'types' => $types
                ]);
                
                if ($syncResult['success']) {
                    $results['synced']++;
                } else {
                    $results['errors']++;
                }
                
                $results['details'][$clone['target_item_id']] = $syncResult;
                
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$clone['target_item_id']] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Sincroniza um item específico
     */
    public function syncItem(string $itemId, array $options = []): array
    {
        $types = $options['types'] ?? ['price', 'stock', 'status', 'metrics'];
        $sourceItemId = $options['source_item_id'] ?? null;
        
        $result = [
            'success' => true,
            'item_id' => $itemId,
            'synced' => [],
            'errors' => []
        ];
        
        try {
            $client = $this->getClient();
            
            // Buscar dados atuais do clone
            $cloneData = $client->get("/items/$itemId");
            
            if (!$cloneData || !isset($cloneData['id'])) {
                throw new \Exception("Item $itemId não encontrado");
            }
            
            // Sincronizar cada tipo
            foreach ($types as $type) {
                if (!$this->isSyncEnabled($type)) {
                    continue;
                }
                
                try {
                    $syncMethod = "sync" . ucfirst($type);
                    if (method_exists($this, $syncMethod)) {
                        $typeResult = $this->$syncMethod($itemId, $cloneData, $sourceItemId);
                        $result['synced'][$type] = $typeResult;
                    }
                } catch (\Exception $e) {
                    $result['errors'][$type] = $e->getMessage();
                }
            }
            
            // Atualizar timestamp de sincronização
            $this->updateSyncTimestamp($itemId);
            
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * Sincroniza preço do clone
     */
    private function syncPrice(string $itemId, array $cloneData, ?string $sourceItemId): array
    {
        $result = ['updated' => false, 'old_price' => null, 'new_price' => null];
        
        // Se temos item de origem, podemos comparar
        if ($sourceItemId) {
            try {
                $client = $this->getClient();
                $sourceData = $client->get("/items/$sourceItemId");
                
                if ($sourceData && isset($sourceData['price'])) {
                    $result['source_price'] = $sourceData['price'];
                }
            } catch (\Exception $e) {
                // Origem pode não estar acessível
            }
        }
        
        $result['current_price'] = $cloneData['price'] ?? 0;
        
        // Registrar para histórico
        $this->logSyncEvent($itemId, 'price', [
            'price' => $result['current_price'],
            'source_price' => $result['source_price'] ?? null
        ]);
        
        return $result;
    }
    
    /**
     * Sincroniza estoque do clone
     */
    private function syncStock(string $itemId, array $cloneData, ?string $sourceItemId): array
    {
        $result = [
            'available_quantity' => $cloneData['available_quantity'] ?? 0,
            'sold_quantity' => $cloneData['sold_quantity'] ?? 0
        ];
        
        // Registrar para histórico
        $this->logSyncEvent($itemId, 'stock', $result);
        
        return $result;
    }
    
    /**
     * Sincroniza status do clone
     */
    private function syncStatus(string $itemId, array $cloneData, ?string $sourceItemId): array
    {
        $status = $cloneData['status'] ?? 'unknown';
        $substatus = $cloneData['sub_status'] ?? [];
        
        $result = [
            'status' => $status,
            'sub_status' => $substatus,
            'is_active' => $status === 'active'
        ];
        
        // Verificar se precisa alerta
        if ($status !== 'active') {
            $this->createAlert($itemId, 'status_change', [
                'status' => $status,
                'sub_status' => $substatus
            ]);
        }
        
        $this->logSyncEvent($itemId, 'status', $result);
        
        return $result;
    }
    
    /**
     * Sincroniza métricas de performance
     */
    private function syncMetrics(string $itemId, array $cloneData, ?string $sourceItemId): array
    {
        $client = $this->getClient();
        
        // Buscar visitas
        $visits = 0;
        try {
            $visitsData = $client->get("/items/$itemId/visits/time_window", [
                'last' => 30,
                'unit' => 'day'
            ]);
            $visits = (int) ($visitsData['total_visits'] ?? 0);
        } catch (\Exception $e) {
            // Ignore
        }
        
        $result = [
            'visits_30d' => $visits,
            'sold_quantity' => $cloneData['sold_quantity'] ?? 0,
            'available_quantity' => $cloneData['available_quantity'] ?? 0,
            'price' => $cloneData['price'] ?? 0,
            'conversion_rate' => $visits > 0 
                ? round(($cloneData['sold_quantity'] ?? 0) / $visits * 100, 2) 
                : 0
        ];
        
        // Salvar métricas no banco
        $this->saveMetrics($itemId, $result);
        
        return $result;
    }
    
    /**
     * Salva métricas no banco
     */
    private function saveMetrics(string $itemId, array $metrics): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_item_metrics (
                    account_id, item_id, visits, sales, revenue, 
                    conversion_rate, synced_at
                ) VALUES (
                    :account_id, :item_id, :visits, :sales, :revenue,
                    :conversion_rate, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    visits = VALUES(visits),
                    sales = VALUES(sales),
                    revenue = VALUES(revenue),
                    conversion_rate = VALUES(conversion_rate),
                    synced_at = NOW()
            ");
            
            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'visits' => $metrics['visits_30d'],
                'sales' => $metrics['sold_quantity'],
                'revenue' => $metrics['sold_quantity'] * $metrics['price'],
                'conversion_rate' => $metrics['conversion_rate']
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail
        }
    }
    
    /**
     * Atualiza preço de um clone
     */
    public function updatePrice(string $itemId, float $newPrice, array $options = []): array
    {
        $client = $this->getClient();
        
        // Buscar preço atual
        $itemData = $client->get("/items/$itemId");
        $oldPrice = $itemData['price'] ?? 0;
        
        // Atualizar no ML
        $result = $client->put("/items/$itemId", [
            'price' => $newPrice
        ]);
        
        // Registrar mudança
        $this->logSyncEvent($itemId, 'price_update', [
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'reason' => $options['reason'] ?? 'manual'
        ]);
        
        return [
            'success' => true,
            'item_id' => $itemId,
            'old_price' => $oldPrice,
            'new_price' => $newPrice
        ];
    }
    
    /**
     * Atualiza estoque de um clone
     */
    public function updateStock(string $itemId, int $quantity, array $options = []): array
    {
        $client = $this->getClient();
        
        // Buscar quantidade atual
        $itemData = $client->get("/items/$itemId");
        $oldQuantity = $itemData['available_quantity'] ?? 0;
        
        // Atualizar no ML
        $result = $client->put("/items/$itemId", [
            'available_quantity' => $quantity
        ]);
        
        $this->logSyncEvent($itemId, 'stock_update', [
            'old_quantity' => $oldQuantity,
            'new_quantity' => $quantity,
            'reason' => $options['reason'] ?? 'manual'
        ]);
        
        return [
            'success' => true,
            'item_id' => $itemId,
            'old_quantity' => $oldQuantity,
            'new_quantity' => $quantity
        ];
    }
    
    /**
     * Pausa/ativa um clone
     */
    public function updateStatus(string $itemId, string $status): array
    {
        $client = $this->getClient();
        
        $validStatuses = ['active', 'paused'];
        if (!in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Status inválido: $status");
        }
        
        $result = $client->put("/items/$itemId", [
            'status' => $status
        ]);
        
        $this->logSyncEvent($itemId, 'status_update', [
            'new_status' => $status
        ]);
        
        return [
            'success' => true,
            'item_id' => $itemId,
            'status' => $status
        ];
    }
    
    /**
     * Sincronização em lote de preços
     */
    public function batchUpdatePrices(array $updates): array
    {
        $results = [
            'total' => count($updates),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($updates as $update) {
            $itemId = $update['item_id'] ?? null;
            $price = $update['price'] ?? null;
            
            if (!$itemId || $price === null) {
                $results['errors']++;
                continue;
            }
            
            try {
                $result = $this->updatePrice($itemId, (float) $price, [
                    'reason' => $update['reason'] ?? 'batch_update'
                ]);
                
                $results['success']++;
                $results['details'][$itemId] = $result;
                
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$itemId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Rate limit
            usleep(100000); // 100ms
        }
        
        return $results;
    }
    
    /**
     * Obtém histórico de sincronização de um item
     */
    public function getSyncHistory(string $itemId, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 50);
        $type = $options['type'] ?? null;

        $limitSql = max(1, min((int)$limit, 500));
        
        $query = "
            SELECT * FROM clone_sync_logs
            WHERE account_id = :account_id
            AND item_id = :item_id
        ";
        
        if ($type) {
            $query .= " AND sync_type = :type";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT {$limitSql}";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':item_id', $itemId, PDO::PARAM_STR);
        if ($type) {
            $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtém configurações de sincronização
     */
    public function getSyncSettings(): array
    {
        return $this->syncConfig;
    }
    
    /**
     * Atualiza configurações de sincronização
     */
    public function updateSyncSettings(array $settings): array
    {
        $this->syncConfig = array_merge($this->syncConfig, $settings);
        
        $stmt = $this->db->prepare("
            INSERT INTO clone_sync_settings (account_id, sync_config, updated_at)
            VALUES (:account_id, :config, NOW())
            ON DUPLICATE KEY UPDATE
                sync_config = VALUES(sync_config),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            'account_id' => $this->accountId,
            'config' => json_encode($this->syncConfig)
        ]);
        
        return $this->syncConfig;
    }
    
    /**
     * Verifica se um tipo de sincronização está habilitado
     */
    private function isSyncEnabled(string $type): bool
    {
        return ($this->syncConfig[$type]['enabled'] ?? false) === true;
    }
    
    /**
     * Atualiza timestamp de sincronização
     */
    private function updateSyncTimestamp(string $itemId): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE cloned_items 
                SET last_synced_at = NOW()
                WHERE target_item_id = :item_id
                AND target_account_id = :account_id
            ");
            $stmt->execute([
                'item_id' => $itemId,
                'account_id' => $this->accountId
            ]);
        } catch (\Exception $e) {
            // Ignore
        }
    }
    
    /**
     * Registra evento de sincronização
     */
    private function logSyncEvent(string $itemId, string $type, array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_sync_logs (
                    account_id, item_id, sync_type, sync_data, created_at
                ) VALUES (
                    :account_id, :item_id, :type, :data, NOW()
                )
            ");
            
            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'type' => $type,
                'data' => json_encode($data)
            ]);
        } catch (\Exception $e) {
            // Table may not exist yet
        }
    }
    
    /**
     * Cria alerta de sincronização
     */
    private function createAlert(string $itemId, string $alertType, array $data): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_sync_alerts (
                    account_id, item_id, alert_type, alert_data, status, created_at
                ) VALUES (
                    :account_id, :item_id, :type, :data, 'pending', NOW()
                )
            ");
            
            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'type' => $alertType,
                'data' => json_encode($data)
            ]);
        } catch (\Exception $e) {
            // Ignore
        }
    }
    
    /**
     * Obtém alertas pendentes
     */
    public function getPendingAlerts(array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 50);

        $limitSql = max(1, min((int)$limit, 500));
        
        $stmt = $this->db->prepare("
            SELECT * FROM clone_sync_alerts
            WHERE account_id = :account_id
            AND status = 'pending'
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Resolve um alerta
     */
    public function resolveAlert(int $alertId, string $resolution = 'resolved'): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clone_sync_alerts
            SET status = :status, resolved_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        
        return $stmt->execute([
            'status' => $resolution,
            'id' => $alertId,
            'account_id' => $this->accountId
        ]);
    }
}
