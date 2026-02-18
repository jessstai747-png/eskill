<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * InventoryService - Gestão Avançada de Estoque
 * 
 * Gerencia estoque multi-origem, reservas e sincronização
 * - Estoque multi-origem (warehouse, dropshipping, etc)
 * - Reservas automáticas
 * - Sincronização em lote
 * - Histórico de movimentação
 * 
 * @link https://developers.mercadolivre.com.br/pt_br/gestion-de-stock
 */
class InventoryService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(?int $accountId = null)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }



    /**
     * Obtém estoque multi-origem
     * 
     * @param string $sku SKU do produto
     * @return array Estoque por origem
     */
    public function getMultiOriginStock(string $sku): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    origin,
                    quantity,
                    reserved,
                    available,
                    location,
                    updated_at
                FROM inventory_origins
                WHERE sku = :sku
                AND account_id = :account_id
                ORDER BY origin
            ");

            $stmt->execute([
                'sku' => $sku,
                'account_id' => $this->accountId,
            ]);

            $origins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalQuantity = 0;
            $totalReserved = 0;
            $totalAvailable = 0;

            foreach ($origins as &$origin) {
                $origin['quantity'] = (int)$origin['quantity'];
                $origin['reserved'] = (int)$origin['reserved'];
                $origin['available'] = (int)$origin['available'];
                
                $totalQuantity += $origin['quantity'];
                $totalReserved += $origin['reserved'];
                $totalAvailable += $origin['available'];
            }

            return [
                'sku' => $sku,
                'origins' => $origins,
                'totals' => [
                    'quantity' => $totalQuantity,
                    'reserved' => $totalReserved,
                    'available' => $totalAvailable,
                ],
            ];
        } catch (\Exception $e) {
            log_error('Erro ao obter estoque multi-origem', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return [
                'sku' => $sku,
                'origins' => [],
                'totals' => ['quantity' => 0, 'reserved' => 0, 'available' => 0],
            ];
        }
    }

    /**
     * Atualiza estoque por origem
     * 
     * @param string $sku SKU
     * @param string $origin Origem (warehouse, dropshipping, store)
     * @param int $quantity Nova quantidade
     * @return array Resultado
     */
    public function updateOriginStock(string $sku, string $origin, int $quantity): array
    {
        try {
            // Atualizar origem
            $stmt = $this->db->prepare("
                INSERT INTO inventory_origins (
                    account_id, sku, origin, quantity, available, updated_at
                ) VALUES (
                    :account_id, :sku, :origin, :quantity, :quantity, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    quantity = :quantity,
                    available = quantity - reserved,
                    updated_at = NOW()
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'sku' => $sku,
                'origin' => $origin,
                'quantity' => $quantity,
            ]);

            // Sincronizar com ML
            $this->syncToMercadoLivre($sku);

            return [
                'success' => true,
                'sku' => $sku,
                'origin' => $origin,
                'quantity' => $quantity,
                'message' => 'Estoque atualizado',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cria reserva de estoque com transação e lock
     *
     * @param string $sku SKU
     * @param int $quantity Quantidade a reservar
     * @param array $metadata Metadados da reserva (order_id, expires_minutes, etc)
     * @return array Resultado
     */
    public function createReservation(string $sku, int $quantity, array $metadata = []): array
    {
        // TTL configurável (default 60 minutos)
        $expireMinutes = $metadata['expires_minutes'] ?? 60;
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expireMinutes} minutes"));

        try {
            // Iniciar transação para evitar race conditions
            $this->db->beginTransaction();

            // Lock na tabela de origens para este SKU (SELECT FOR UPDATE)
            $stmt = $this->db->prepare("
                SELECT
                    id, origin, quantity, reserved, available
                FROM inventory_origins
                WHERE sku = :sku AND account_id = :account_id
                FOR UPDATE
            ");
            $stmt->execute([
                'sku' => $sku,
                'account_id' => $this->accountId,
            ]);
            $origins = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular total disponível
            $totalAvailable = array_sum(array_column($origins, 'available'));

            if ($totalAvailable < $quantity) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'error' => 'Estoque insuficiente',
                    'available' => $totalAvailable,
                    'requested' => $quantity,
                ];
            }

            // Gerar ID único usando UUID v4 para evitar colisões
            $reservationId = sprintf('RSV_%s_%s',
                date('Ymd'),
                bin2hex(random_bytes(8))
            );

            // Criar reserva
            $stmt = $this->db->prepare("
                INSERT INTO inventory_reservations (
                    account_id, reservation_id, sku, quantity,
                    order_id, expires_at, metadata, status, created_at
                ) VALUES (
                    :account_id, :reservation_id, :sku, :quantity,
                    :order_id, :expires_at, :metadata, 'active', NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'reservation_id' => $reservationId,
                'sku' => $sku,
                'quantity' => $quantity,
                'order_id' => $metadata['order_id'] ?? null,
                'expires_at' => $expiresAt,
                'metadata' => json_encode($metadata),
            ]);

            // Alocar reserva usando estratégia FIFO por origem
            $remaining = $quantity;
            foreach ($origins as $origin) {
                if ($remaining <= 0) break;

                $toReserve = min($remaining, (int)$origin['available']);
                if ($toReserve > 0) {
                    $stmt = $this->db->prepare("
                        UPDATE inventory_origins
                        SET reserved = reserved + :reserve,
                            available = quantity - reserved - :reserve,
                            updated_at = NOW()
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        'reserve' => $toReserve,
                        'id' => $origin['id'],
                    ]);
                    $remaining -= $toReserve;
                }
            }

            // Commit da transação
            $this->db->commit();

            // Registrar movimento
            $this->logMovement($sku, 'reservation', -$quantity, $reservationId, $metadata);

            return [
                'success' => true,
                'reservation_id' => $reservationId,
                'sku' => $sku,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            log_error('Erro ao criar reserva de estoque', [
                'service' => 'InventoryService',
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Registra movimento de estoque
     */
    private function logMovement(string $sku, string $type, int $quantity, ?string $referenceId = null, array $metadata = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO inventory_movements (
                    account_id, sku, type, quantity, reference_id, notes, created_at
                ) VALUES (
                    :account_id, :sku, :type, :quantity, :reference_id, :notes, NOW()
                )
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'sku' => $sku,
                'type' => $type,
                'quantity' => $quantity,
                'reference_id' => $referenceId,
                'notes' => json_encode($metadata),
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao registrar movimento de estoque', [
                'service' => 'InventoryService',
                'sku' => $sku,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Libera reserva
     * 
     * @param string $reservationId ID da reserva
     * @return array Resultado
     */
    public function releaseReservation(string $reservationId): array
    {
        try {
            $this->db->beginTransaction();

            // Buscar reserva com lock para evitar double-release
            $stmt = $this->db->prepare("
                SELECT sku, quantity
                FROM inventory_reservations
                WHERE reservation_id = :reservation_id
                AND account_id = :account_id
                AND status = 'active'
                FOR UPDATE
            ");

            $stmt->execute([
                'reservation_id' => $reservationId,
                'account_id' => $this->accountId,
            ]);

            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $this->db->rollBack();
                return ['success' => false, 'error' => 'Reserva não encontrada'];
            }

            // Marcar como liberada
            $stmt = $this->db->prepare("
                UPDATE inventory_reservations
                SET status = 'released', updated_at = NOW()
                WHERE reservation_id = :reservation_id
            ");

            $stmt->execute(['reservation_id' => $reservationId]);

            // Atualizar quantidades
            $this->updateReservedQuantities($reservation['sku'], -$reservation['quantity']);

            $this->db->commit();

            return [
                'success' => true,
                'reservation_id' => $reservationId,
                'message' => 'Reserva liberada',
            ];
        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Limpa reservas expiradas
     * 
     * @return array Resultado
     */
    public function cleanExpiredReservations(): array
    {
        try {
            // Buscar expiradas
            $stmt = $this->db->prepare("
                SELECT reservation_id, sku, quantity
                FROM inventory_reservations
                WHERE account_id = :account_id
                AND status = 'active'
                AND expires_at < NOW()
            ");

            $stmt->execute(['account_id' => $this->accountId]);
            $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cleaned = 0;
            foreach ($expired as $reservation) {
                $result = $this->releaseReservation($reservation['reservation_id']);
                if ($result['success']) {
                    $cleaned++;
                }
            }

            return [
                'success' => true,
                'cleaned' => $cleaned,
                'message' => "{$cleaned} reservas expiradas limpas",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sincroniza estoque em lote com chunking e rate limiting
     *
     * @param array $items Array de [sku => quantity]
     * @param int $chunkSize Tamanho do chunk (default 50)
     * @param callable|null $progressCallback Callback para progresso
     * @return array Resultado
     */
    public function bulkSync(array $items, int $chunkSize = 50, ?callable $progressCallback = null): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'total' => count($items),
            'details' => [],
            'started_at' => date('Y-m-d H:i:s'),
        ];

        // Dividir em chunks
        $chunks = array_chunk($items, $chunkSize, true);
        $processedCount = 0;

        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $sku => $quantity) {
                $processedCount++;

                try {
                    // Validar quantidade
                    if (!is_numeric($quantity) || $quantity < 0) {
                        $results['skipped']++;
                        $results['details'][$sku] = ['error' => 'Quantidade inválida'];
                        continue;
                    }

                    $result = $this->syncStock($sku, (int)$quantity);

                    if ($result['updated'] > 0) {
                        $results['success']++;
                    } elseif ($result['errors'] > 0) {
                        $results['errors']++;
                    } else {
                        $results['skipped']++;
                    }

                    $results['details'][$sku] = $result;

                    // Callback de progresso
                    if ($progressCallback && is_callable($progressCallback)) {
                        $progressCallback([
                            'current' => $processedCount,
                            'total' => $results['total'],
                            'percent' => round(($processedCount / $results['total']) * 100, 1),
                            'sku' => $sku,
                        ]);
                    }

                } catch (\Exception $e) {
                    $results['errors']++;
                    $results['details'][$sku] = ['error' => $e->getMessage()];
                }
            }

            // Rate limiting entre chunks (100ms)
            if ($chunkIndex < count($chunks) - 1) {
                usleep(100000);
            }
        }

        $results['finished_at'] = date('Y-m-d H:i:s');
        $results['duration_seconds'] = strtotime($results['finished_at']) - strtotime($results['started_at']);

        return $results;
    }

    /**
     * Histórico de movimentação
     * 
     * @param string $sku SKU
     * @param array $filters Filtros
     * @return array Histórico
     */
    public function getMovementHistory(string $sku, array $filters = []): array
    {
        try {
            $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $filters['end_date'] ?? date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT 
                    type,
                    quantity,
                    origin,
                    reference_id,
                    notes,
                    created_at
                FROM inventory_movements
                WHERE sku = :sku
                AND account_id = :account_id
                AND created_at BETWEEN :start_date AND :end_date
                ORDER BY created_at DESC
                LIMIT 100
            ");

            $stmt->execute([
                'sku' => $sku,
                'account_id' => $this->accountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'sku' => $sku,
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total' => count($movements),
                'movements' => $movements,
            ];
        } catch (\Exception $e) {
            return [
                'sku' => $sku,
                'total' => 0,
                'movements' => [],
            ];
        }
    }

    /**
     * Decreases stock for all items sharing the same SKU
     * 
     * @param string $sku
     * @param int $quantitySold
     * @param string|null $excludeItemId
     * @return array Result summary
     */
    public function adjustStockForSale(string $sku, int $quantitySold, ?string $excludeItemId = null): array
    {
        if (empty($sku)) {
            return ['error' => 'SKU cannot be empty'];
        }

        $stmt = $this->db->prepare("
            SELECT id, ml_item_id, account_id, available_quantity, title 
            FROM items 
            WHERE sku = :sku 
            AND status = 'active'
        ");
        $stmt->execute(['sku' => $sku]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'sku' => $sku,
            'total_items_found' => count($items),
            'updated' => [],
            'errors' => []
        ];

        foreach ($items as $item) {
            $newQuantity = max(0, $item['available_quantity'] - $quantitySold);

            try {
                if ($item['available_quantity'] != $newQuantity) {
                    // Verificar se temos token válido para API
                    $sourceInfo = $this->client->getDataSourceInfo();

                    if ($sourceInfo['source'] === 'api') {
                        // Chamada real à API do ML
                        $update = $this->client->put("/items/{$item['ml_item_id']}", [
                            'available_quantity' => $newQuantity
                        ]);

                        if (!$update['success']) {
                            throw new \Exception($update['error'] ?? 'API Error');
                        }
                    } else {
                        // Sem token válido - apenas atualizar localmente
                        $update = ['id' => $item['ml_item_id'], 'available_quantity' => $newQuantity, '_local_only' => true];
                        $results['warnings'][] = "Item {$item['ml_item_id']}: Atualizado apenas localmente (sem token ML válido)";
                    }

                    // Sempre atualizar banco local
                    $updStmt = $this->db->prepare("UPDATE items SET available_quantity = ?, updated_at = NOW() WHERE id = ?");
                    $updStmt->execute([$newQuantity, $item['id']]);

                    // Registrar movimento
                    $this->logMovement($sku, 'sale_adjustment', -$quantitySold, $excludeItemId, [
                        'item_id' => $item['ml_item_id'],
                        'old_qty' => $item['available_quantity'],
                        'new_qty' => $newQuantity,
                    ]);

                    $results['updated'][] = [
                        'ml_item_id' => $item['ml_item_id'],
                        'old_qty' => $item['available_quantity'],
                        'new_qty' => $newQuantity,
                        'account_id' => $item['account_id'],
                        'api_synced' => ($sourceInfo['source'] === 'api'),
                    ];
                }
                
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'item' => $item['ml_item_id'],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Force sets quantity for all items with SKU
     */
    public function syncStock(string $sku, int $quantity): array
    {
        $stmt = $this->db->prepare("SELECT id, ml_item_id, account_id FROM items WHERE sku = :sku AND status = 'active'");
        $stmt->execute(['sku' => $sku]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = ['updated' => 0, 'errors' => 0];

        foreach ($items as $item) {
            try {
                $this->client->put("/items/{$item['ml_item_id']}", ['available_quantity' => $quantity]);
                
                $this->db->prepare("UPDATE items SET available_quantity = ? WHERE id = ?")
                         ->execute([$quantity, $item['id']]);
                         
                $results['updated']++;
            } catch (\Exception $e) {
                $results['errors']++;
            }
        }
        return $results;
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function syncToMercadoLivre(string $sku): void
    {
        try {
            // Calcular total disponível
            $stock = $this->getMultiOriginStock($sku);
            $totalAvailable = $stock['totals']['available'];

            // Atualizar todos os itens ML com este SKU
            $this->syncStock($sku, $totalAvailable);
        } catch (\Exception $e) {
            log_warning('Erro ao sincronizar estoque com ML', [
                'service' => 'InventoryService',
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateReservedQuantities(string $sku, int $quantityChange): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE inventory_origins
                SET reserved = reserved + :change,
                    available = quantity - reserved,
                    updated_at = NOW()
                WHERE sku = :sku
                AND account_id = :account_id
                AND quantity >= reserved + :change
                ORDER BY quantity DESC
                LIMIT 1
            ");

            $stmt->execute([
                'sku' => $sku,
                'account_id' => $this->accountId,
                'change' => $quantityChange,
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao atualizar reservas de estoque', [
                'service' => 'InventoryService',
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
