<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Traits\DatabaseMigrationTrait;
use PDO;

class ItemSyncService
{
    use DatabaseMigrationTrait;

    private PDO $db;
    private ?MercadoLivreClient $mlClient = null;
    private LoggingService $logger;
    private bool $mlItemsHasLastSyncedAt = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new LoggingService();
        $this->ensureSchema();
    }

    /**
     * Sync all items for a given account.
     */
    public function syncForAccount(int $accountId): array
    {
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->logger->info('ITEM_SYNC_START', "Starting item sync for account {$accountId}");

        // Garante token válido antes de começar, evitando 401 em lotes subsequentes
        $this->mlClient->ensureValidAccessToken();

        $stats = ['total_found' => 0, 'total_synced' => 0, 'batches' => 0];

        try {
            // 1. Get the ML user ID for the account
            $account = $this->getAccountDetails($accountId);
            if (!$account || empty($account['ml_user_id'])) {
                throw new \Exception("Could not find Mercado Livre user ID for account {$accountId}");
            }
            $mlUserId = (string)$account['ml_user_id'];

            // 2. Fetch all item IDs
            $itemIds = $this->fetchAllItemIds($mlUserId);
            $stats['total_found'] = count($itemIds);
            $this->logger->info('ITEM_SYNC_FETCH_IDS', "Found {$stats['total_found']} items for user {$mlUserId}");

            if (empty($itemIds)) {
                $this->logger->info('ITEM_SYNC_COMPLETE', "No items to sync for account {$accountId}");
                return $stats;
            }

            // 3. Fetch full item details in batches
            $itemBatches = array_chunk($itemIds, 20); // ML API allows up to 20 IDs per multiget request

            foreach ($itemBatches as $batch) {
                $stats['batches']++;
                try {
                    $itemDetails = $this->fetchItemDetails($batch);

                    // 4. Salvar diretamente na tabela 'items' (ml_items é apenas uma VIEW)
                    $syncedCount = $this->syncToItemsTable($itemDetails, $accountId);

                    $stats['total_synced'] += $syncedCount;
                    $this->logger->info('ITEM_SYNC_BATCH', "Synced batch of {$syncedCount} items.");
                } catch (\Throwable $e) {
                    // Log error but continue with next batch
                    $this->logger->error('ITEM_SYNC_BATCH_ERROR', "Failed to sync batch", [
                        'batch_ids' => $batch,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->info('ITEM_SYNC_COMPLETE', "Item sync completed for account {$accountId}", $stats);
            return $stats;
        } catch (\Throwable $e) {
            $this->logger->error('ITEM_SYNC_ERROR', "Error during item sync for account {$accountId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function getAccountDetails(int $accountId): ?array
    {
        $stmt = $this->db->prepare("SELECT ml_user_id FROM ml_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function fetchAllItemIds(string $mlUserId): array
    {
        $itemIds = [];
        $offset = 0;
        $limit = 50;

        do {
            $response = $this->mlClient->get("/users/{$mlUserId}/items/search", [
                'offset' => $offset,
                'limit' => $limit,
                'status' => 'active,paused,closed'
            ]);

            if (isset($response['results']) && !empty($response['results'])) {
                $itemIds = array_merge($itemIds, $response['results']);
            }

            $offset += $limit;
            $total = $response['paging']['total'] ?? 0;
        } while ($offset < $total);

        return $itemIds;
    }

    private function fetchItemDetails(array $itemIds): array
    {
        $response = $this->mlClient->get('/items', [
            'ids' => implode(',', $itemIds)
        ]);

        if (isset($response['error'])) {
            $this->logger->warning('ITEM_SYNC_MULTIGET_ERROR', 'Erro ao buscar itens em lote', [
                'error' => $response
            ]);
            return [];
        }

        return is_array($response) ? $response : [];
    }

    private function saveItemsToDb(array $items, int $accountId): int
    {
        if (empty($items)) {
            return 0;
        }

        if ($this->mlItemsHasLastSyncedAt) {
            $sql = "
                INSERT INTO ml_items (
                    id, account_id, title, sku, category_id, price, currency_id,
                    available_quantity, sold_quantity, status, permalink, thumbnail, raw_data, last_synced_at
                ) VALUES (
                    :id, :account_id, :title, :sku, :category_id, :price, :currency_id,
                    :available_quantity, :sold_quantity, :status, :permalink, :thumbnail, :raw_data, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    sku = VALUES(sku),
                    category_id = VALUES(category_id),
                    price = VALUES(price),
                    available_quantity = VALUES(available_quantity),
                    sold_quantity = VALUES(sold_quantity),
                    status = VALUES(status),
                    permalink = VALUES(permalink),
                    thumbnail = VALUES(thumbnail),
                    raw_data = VALUES(raw_data),
                    last_synced_at = NOW(),
                    updated_at = CURRENT_TIMESTAMP
            ";
        } else {
            $sql = "
                INSERT INTO ml_items (
                    id, account_id, title, sku, category_id, price, currency_id,
                    available_quantity, sold_quantity, status, permalink, thumbnail, raw_data
                ) VALUES (
                    :id, :account_id, :title, :sku, :category_id, :price, :currency_id,
                    :available_quantity, :sold_quantity, :status, :permalink, :thumbnail, :raw_data
                )
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    sku = VALUES(sku),
                    category_id = VALUES(category_id),
                    price = VALUES(price),
                    available_quantity = VALUES(available_quantity),
                    sold_quantity = VALUES(sold_quantity),
                    status = VALUES(status),
                    permalink = VALUES(permalink),
                    thumbnail = VALUES(thumbnail),
                    raw_data = VALUES(raw_data),
                    updated_at = CURRENT_TIMESTAMP
            ";
        }

        $stmt = $this->db->prepare($sql);
        $count = 0;

        foreach ($items as $item) {
            if (isset($item['body'])) {
                $itemData = $item['body'];

                // Extract SKU from attributes
                $sku = null;
                if (isset($itemData['attributes'])) {
                    foreach ($itemData['attributes'] as $attr) {
                        if ($attr['id'] === 'SELLER_SKU') {
                            $sku = $attr['value_name'];
                            break;
                        }
                    }
                }

                $stmt->execute([
                    ':id' => $itemData['id'],
                    ':account_id' => $accountId,
                    ':title' => $itemData['title'],
                    ':sku' => $sku,
                    ':category_id' => $itemData['category_id'],
                    ':price' => $itemData['price'],
                    ':currency_id' => $itemData['currency_id'],
                    ':available_quantity' => $itemData['available_quantity'],
                    ':sold_quantity' => $itemData['sold_quantity'],
                    ':status' => $itemData['status'],
                    ':permalink' => $itemData['permalink'],
                    ':thumbnail' => $itemData['thumbnail'],
                    ':raw_data' => json_encode($itemData)
                ]);
                $count++;
            }
        }

        return $count;
    }

    private function ensureSchema(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ml_items (
                    id VARCHAR(20) PRIMARY KEY,
                    account_id INT NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    sku VARCHAR(100) NULL,
                    category_id VARCHAR(50) NULL,
                    price DECIMAL(12, 2) NOT NULL,
                    currency_id VARCHAR(3) NOT NULL,
                    available_quantity INT NOT NULL,
                    sold_quantity INT NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    permalink VARCHAR(255) NULL,
                    thumbnail VARCHAR(255) NULL,
                    raw_data JSON NULL,
                    last_synced_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_account_status (account_id, status),
                    INDEX idx_sku (sku),
                    INDEX idx_title (title)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $this->mlItemsHasLastSyncedAt = $this->columnExists($this->db, 'ml_items', 'last_synced_at');
            if (!$this->mlItemsHasLastSyncedAt) {
                $this->addColumnIfMissing($this->db, 'ml_items', 'last_synced_at', 'TIMESTAMP NULL');
                $this->mlItemsHasLastSyncedAt = $this->columnExists($this->db, 'ml_items', 'last_synced_at');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('ML_ITEMS_SCHEMA_WARNING', 'Falha ao validar/atualizar schema de ml_items', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sincroniza itens para a tabela 'items' (usada pelo TechSheet e outros módulos)
     */
    private function syncToItemsTable(array $items, int $accountId): int
    {
        if (empty($items)) {
            return 0;
        }

        $sql = "
            INSERT INTO items (
                ml_item_id, account_id, title, category_id, price,
                currency_id, available_quantity, status, condition_type,
                catalog_product_id, sku, data, created_at, updated_at
            ) VALUES (
                :ml_item_id, :account_id, :title, :category_id, :price,
                :currency_id, :available_quantity, :status, :condition_type,
                :catalog_product_id, :sku, :data, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                category_id = VALUES(category_id),
                price = VALUES(price),
                currency_id = VALUES(currency_id),
                available_quantity = VALUES(available_quantity),
                status = VALUES(status),
                condition_type = VALUES(condition_type),
                catalog_product_id = VALUES(catalog_product_id),
                sku = VALUES(sku),
                data = VALUES(data),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $count = 0;

        foreach ($items as $item) {
            if (isset($item['body']) && !isset($item['body']['error'])) {
                $itemData = $item['body'];

                // Extrair SKU dos atributos
                $sku = null;
                if (isset($itemData['attributes'])) {
                    foreach ($itemData['attributes'] as $attr) {
                        if ($attr['id'] === 'SELLER_SKU') {
                            $sku = $attr['value_name'];
                            break;
                        }
                    }
                }

                try {
                    $stmt->execute([
                        ':ml_item_id' => $itemData['id'],
                        ':account_id' => $accountId,
                        ':title' => $itemData['title'],
                        ':category_id' => $itemData['category_id'] ?? null,
                        ':price' => $itemData['price'] ?? 0,
                        ':currency_id' => $itemData['currency_id'] ?? 'BRL',
                        ':available_quantity' => $itemData['available_quantity'] ?? 0,
                        ':status' => $itemData['status'] ?? 'unknown',
                        ':condition_type' => $itemData['condition'] ?? null,
                        ':catalog_product_id' => $itemData['catalog_product_id'] ?? null,
                        ':sku' => $sku,
                        ':data' => json_encode($itemData),
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    $this->logger->warning('ITEM_SYNC_ITEMS_TABLE_ERROR', 'Erro ao salvar item na tabela items', [
                        'item_id' => $itemData['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return $count;
    }
}
