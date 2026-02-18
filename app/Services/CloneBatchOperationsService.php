<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneBatchOperationsService
 * 
 * Operações em lote avançadas para itens clonados.
 * Repricing, atualização massiva, ações automáticas.
 */
class CloneBatchOperationsService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    private function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }

    /**
     * Repricing em lote baseado em regras
     */
    public function batchRepricing(array $rules): array
    {
        $results = [
            'total' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        // Buscar itens elegíveis
        $items = $this->getItemsForRepricing($rules);
        $results['total'] = count($items);

        foreach ($items as $item) {
            try {
                $newPrice = $this->calculateNewPrice($item, $rules);

                if ($newPrice === null || $newPrice === (float) $item['price']) {
                    $results['skipped']++;
                    continue;
                }

                // Aplicar novo preço
                $client = $this->getClient();
                $client->put("/items/{$item['target_item_id']}", [
                    'price' => $newPrice
                ]);

                // Atualizar local
                $this->updateLocalPrice($item['target_item_id'], $newPrice, $item['price']);

                $results['updated']++;
                $results['details'][] = [
                    'item_id' => $item['target_item_id'],
                    'old_price' => $item['price'],
                    'new_price' => $newPrice,
                    'change_percent' => round((($newPrice - $item['price']) / $item['price']) * 100, 2)
                ];

                usleep(100000); // Rate limit

            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $item['target_item_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Registrar operação
        $this->logBatchOperation('repricing', $results);

        return $results;
    }

    /**
     * Atualização massiva de estoque
     */
    public function batchStockUpdate(array $updates): array
    {
        $results = [
            'total' => count($updates),
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];

        $client = $this->getClient();

        foreach ($updates as $update) {
            $itemId = $update['item_id'] ?? null;
            $quantity = $update['quantity'] ?? null;

            if (!$itemId || $quantity === null) {
                $results['errors']++;
                continue;
            }

            try {
                $client->put("/items/$itemId", [
                    'available_quantity' => (int) $quantity
                ]);

                $results['updated']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'quantity' => (int) $quantity,
                    'success' => true
                ];

                usleep(100000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        $this->logBatchOperation('stock_update', $results);

        return $results;
    }

    /**
     * Pausar/Ativar em lote
     */
    public function batchStatusChange(array $itemIds, string $newStatus): array
    {
        $validStatuses = ['active', 'paused'];
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException("Status inválido: $newStatus");
        }

        $results = [
            'total' => count($itemIds),
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];

        $client = $this->getClient();

        foreach ($itemIds as $itemId) {
            try {
                $client->put("/items/$itemId", [
                    'status' => $newStatus
                ]);

                $results['updated']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'new_status' => $newStatus,
                    'success' => true
                ];

                usleep(100000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        $this->logBatchOperation('status_change', $results);

        return $results;
    }

    /**
     * Atualização de títulos em lote
     */
    public function batchTitleUpdate(array $updates): array
    {
        $results = [
            'total' => count($updates),
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];

        $client = $this->getClient();

        foreach ($updates as $update) {
            $itemId = $update['item_id'] ?? null;
            $newTitle = $update['title'] ?? null;

            if (!$itemId || !$newTitle) {
                $results['errors']++;
                continue;
            }

            // Validar título
            if (mb_strlen($newTitle) > 60) {
                $newTitle = mb_substr($newTitle, 0, 57) . '...';
            }

            try {
                $client->put("/items/$itemId", [
                    'title' => $newTitle
                ]);

                // Atualizar local
                $stmt = $this->db->prepare("
                    UPDATE cloned_items SET title = :title 
                    WHERE target_item_id = :item_id
                ");
                $stmt->execute(['title' => $newTitle, 'item_id' => $itemId]);

                $results['updated']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'new_title' => $newTitle,
                    'success' => true
                ];

                usleep(100000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        $this->logBatchOperation('title_update', $results);

        return $results;
    }

    /**
     * Aplicar regra de preço a categoria
     */
    public function applyPriceRuleToCategory(string $categoryId, array $rule): array
    {
        $stmt = $this->db->prepare("
            SELECT target_item_id, price 
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND category_id = :category_id
            AND status = 'completed'
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'category_id' => $categoryId
        ]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updates = [];
        foreach ($items as $item) {
            $newPrice = $this->calculateNewPrice($item, $rule);
            if ($newPrice !== null && $newPrice !== (float) $item['price']) {
                $updates[] = [
                    'item_id' => $item['target_item_id'],
                    'price' => $newPrice
                ];
            }
        }

        return $this->batchPriceUpdate($updates);
    }

    /**
     * Atualização de preços em lote
     */
    public function batchPriceUpdate(array $updates): array
    {
        $results = [
            'total' => count($updates),
            'updated' => 0,
            'errors' => 0,
            'details' => []
        ];

        $client = $this->getClient();

        foreach ($updates as $update) {
            $itemId = $update['item_id'] ?? null;
            $price = $update['price'] ?? null;

            if (!$itemId || $price === null) {
                $results['errors']++;
                continue;
            }

            try {
                $client->put("/items/$itemId", [
                    'price' => (float) $price
                ]);

                $this->updateLocalPrice($itemId, (float) $price);

                $results['updated']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'price' => (float) $price,
                    'success' => true
                ];

                usleep(100000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                    'success' => false
                ];
            }
        }

        $this->logBatchOperation('price_update', $results);

        return $results;
    }

    /**
     * Sincronização em lote de métricas
     */
    public function batchSyncMetrics(array $itemIds = []): array
    {
        $results = [
            'total' => 0,
            'synced' => 0,
            'errors' => 0,
            'details' => []
        ];

        // Se não especificado, buscar todos ativos
        if (empty($itemIds)) {
            $stmt = $this->db->prepare("
                SELECT target_item_id FROM cloned_items
                WHERE target_account_id = :account_id
                AND status = 'completed'
                ORDER BY last_synced_at ASC NULLS FIRST
                LIMIT 100
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $itemIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'target_item_id');
        }

        $results['total'] = count($itemIds);
        $client = $this->getClient();

        foreach ($itemIds as $itemId) {
            try {
                // Buscar dados atuais
                $itemData = $client->get("/items/$itemId");

                // Buscar visitas
                $visits = 0;
                try {
                    $visitsData = $client->get("/items/$itemId/visits/time_window", [
                        'last' => 30,
                        'unit' => 'day'
                    ]);
                    $visits = (int) ($visitsData['total_visits'] ?? 0);
                } catch (\Exception $e) {
                    error_log('CloneBatchOperationsService: failed to fetch visits for ' . $itemId . ' - ' . $e->getMessage());
                }

                // Atualizar métricas
                $this->updateItemMetrics($itemId, [
                    'price' => $itemData['price'] ?? 0,
                    'available_quantity' => $itemData['available_quantity'] ?? 0,
                    'sold_quantity' => $itemData['sold_quantity'] ?? 0,
                    'visits' => $visits
                ]);

                $results['synced']++;

                usleep(150000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->logBatchOperation('sync_metrics', $results);

        return $results;
    }

    /**
     * Aplicar otimização SEO em lote
     */
    public function batchSeoOptimization(array $itemIds, string $level = 'standard'): array
    {
        $results = [
            'total' => count($itemIds),
            'optimized' => 0,
            'skipped' => 0,
            'errors' => 0,
            'details' => []
        ];

        $seoService = new CloneSeoOptimizationService($this->accountId);
        $client = $this->getClient();

        foreach ($itemIds as $itemId) {
            try {
                // Analisar item
                $analysis = $seoService->analyzeForClone($itemId, $level);

                if (!$analysis['success'] ?? true) {
                    $results['errors']++;
                    continue;
                }

                // Se score já bom, skip
                if (($analysis['current_score'] ?? 0) >= 80) {
                    $results['skipped']++;
                    continue;
                }

                // Buscar dados atuais
                $itemData = $client->get("/items/$itemId");

                // Aplicar otimizações
                $newTitle = $seoService->optimizeTitle($itemData['title'] ?? '', $level);

                if ($newTitle !== $itemData['title']) {
                    $client->put("/items/$itemId", ['title' => $newTitle]);
                }

                $results['optimized']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'old_score' => $analysis['current_score'],
                    'potential_score' => $analysis['potential_score'],
                    'title_changed' => $newTitle !== $itemData['title']
                ];

                usleep(200000);
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage()
                ];
            }
        }

        $this->logBatchOperation('seo_optimization', $results);

        return $results;
    }

    /**
     * Encerrar clones antigos sem vendas
     */
    public function closeStaleItems(int $daysWithoutSales = 60): array
    {
        $stmt = $this->db->prepare("
            SELECT ci.target_item_id, ci.title, ci.created_at,
                   COALESCE(m.sales, 0) as sales
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.status = 'completed'
            AND ci.created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND COALESCE(m.sales, 0) = 0
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'days' => $daysWithoutSales
        ]);

        $staleItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($staleItems)) {
            return [
                'total' => 0,
                'closed' => 0,
                'message' => 'Nenhum item elegível para encerramento'
            ];
        }

        $itemIds = array_column($staleItems, 'target_item_id');

        return $this->batchStatusChange($itemIds, 'paused');
    }

    /**
     * Busca itens para repricing
     */
    private function getItemsForRepricing(array $rules): array
    {
        $query = "
            SELECT ci.*, COALESCE(m.sales, 0) as sales, COALESCE(m.visits, 0) as visits
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.status = 'completed'
        ";

        $params = ['account_id' => $this->accountId];

        if (!empty($rules['category_id'])) {
            $query .= " AND ci.category_id = :category_id";
            $params['category_id'] = $rules['category_id'];
        }

        if (!empty($rules['min_price'])) {
            $query .= " AND ci.price >= :min_price";
            $params['min_price'] = $rules['min_price'];
        }

        if (!empty($rules['max_price'])) {
            $query .= " AND ci.price <= :max_price";
            $params['max_price'] = $rules['max_price'];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula novo preço baseado em regras
     */
    private function calculateNewPrice(array $item, array $rules): ?float
    {
        $currentPrice = (float) $item['price'];
        $newPrice = $currentPrice;

        $type = $rules['type'] ?? 'percentage';
        $value = (float) ($rules['value'] ?? 0);

        switch ($type) {
            case 'percentage':
                $newPrice = $currentPrice * (1 + ($value / 100));
                break;

            case 'fixed_increase':
                $newPrice = $currentPrice + $value;
                break;

            case 'fixed_decrease':
                $newPrice = $currentPrice - $value;
                break;

            case 'set_price':
                $newPrice = $value;
                break;

            case 'round':
                $newPrice = $this->roundPrice($currentPrice, $rules);
                break;
        }

        // Validar preço mínimo
        $minPrice = (float) ($rules['min_price'] ?? 1);
        if ($newPrice < $minPrice) {
            $newPrice = $minPrice;
        }

        // Arredondar para 2 casas
        return round($newPrice, 2);
    }

    /**
     * Arredonda preço conforme regras
     */
    private function roundPrice(float $price, array $rules): float
    {
        $strategy = $rules['round_strategy'] ?? 'nearest';
        $precision = (int) ($rules['round_precision'] ?? 0);

        switch ($strategy) {
            case 'up':
                return ceil($price / pow(10, $precision)) * pow(10, $precision);

            case 'down':
                return floor($price / pow(10, $precision)) * pow(10, $precision);

            case 'nearest':
            default:
                return round($price / pow(10, $precision)) * pow(10, $precision);
        }
    }

    /**
     * Atualiza preço local
     */
    private function updateLocalPrice(string $itemId, float $newPrice, ?float $oldPrice = null): void
    {
        $stmt = $this->db->prepare("
            UPDATE cloned_items SET price = :price WHERE target_item_id = :item_id
        ");
        $stmt->execute(['price' => $newPrice, 'item_id' => $itemId]);

        // Registrar histórico
        if ($oldPrice !== null) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO clone_price_history (
                        account_id, item_id, old_price, new_price, changed_at
                    ) VALUES (
                        :account_id, :item_id, :old_price, :new_price, NOW()
                    )
                ");
                $stmt->execute([
                    'account_id' => $this->accountId,
                    'item_id' => $itemId,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice
                ]);
            } catch (\Exception $e) {
                // Tabela pode não existir
            }
        }
    }

    /**
     * Atualiza métricas de item
     */
    private function updateItemMetrics(string $itemId, array $data): void
    {
        $revenue = ($data['sold_quantity'] ?? 0) * ($data['price'] ?? 0);
        $conversion = ($data['visits'] ?? 0) > 0
            ? (($data['sold_quantity'] ?? 0) / $data['visits']) * 100
            : 0;

        $stmt = $this->db->prepare("
            INSERT INTO clone_item_metrics (
                account_id, item_id, visits, sales, revenue, conversion_rate, synced_at
            ) VALUES (
                :account_id, :item_id, :visits, :sales, :revenue, :conversion, NOW()
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
            'visits' => $data['visits'] ?? 0,
            'sales' => $data['sold_quantity'] ?? 0,
            'revenue' => $revenue,
            'conversion' => $conversion
        ]);

        // Atualizar último sync
        $stmt = $this->db->prepare("
            UPDATE cloned_items SET last_synced_at = NOW() 
            WHERE target_item_id = :item_id
        ");
        $stmt->execute(['item_id' => $itemId]);
    }

    /**
     * Registra operação em lote
     */
    private function logBatchOperation(string $type, array $results): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_batch_operations (
                    account_id, operation_type, total_items, 
                    success_count, error_count, results, created_at
                ) VALUES (
                    :account_id, :type, :total, :success, :errors, :results, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'type' => $type,
                'total' => $results['total'] ?? 0,
                'success' => $results['updated'] ?? $results['synced'] ?? $results['optimized'] ?? 0,
                'errors' => $results['errors'] ?? 0,
                'results' => json_encode($results)
            ]);
        } catch (\Exception $e) {
            // Tabela pode não existir
        }
    }

    /**
     * Histórico de operações em lote
     */
    public function getOperationsHistory(int $limit = 50): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT * FROM clone_batch_operations
                WHERE account_id = :account_id
                ORDER BY created_at DESC
                LIMIT {$limitSql}
            ");
            $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
