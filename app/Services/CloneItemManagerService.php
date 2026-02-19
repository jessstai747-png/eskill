<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneItemManagerService
 * 
 * Serviço completo para gerenciamento de itens clonados.
 * CRUD, busca, filtros, operações em lote.
 */
class CloneItemManagerService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
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
     * Lista itens clonados com filtros
     */
    public function listItems(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = min(100, max(10, (int) ($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        $where = ['ci.target_account_id = :account_id'];
        $params = ['account_id' => $this->accountId];
        
        // Filtros
        if (!empty($filters['status'])) {
            $where[] = 'ci.status = :status';
            $params['status'] = $filters['status'];
        }
        
        if (!empty($filters['category_id'])) {
            $where[] = 'ci.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }
        
        if (!empty($filters['source_seller_id'])) {
            $where[] = 'ci.source_seller_id = :seller_id';
            $params['seller_id'] = $filters['source_seller_id'];
        }
        
        if (!empty($filters['search'])) {
            $where[] = '(ci.title LIKE :search OR ci.target_item_id LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = 'ci.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = 'ci.created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        
        if (isset($filters['has_sales']) && $filters['has_sales']) {
            $where[] = 'COALESCE(m.sales, 0) > 0';
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Ordenação
        $orderBy = match ($filters['sort'] ?? 'created_at') {
            'title' => 'ci.title',
            'price' => 'ci.price',
            'sales' => 'm.sales',
            'visits' => 'm.visits',
            'created_at' => 'ci.created_at',
            default => 'ci.created_at'
        };
        $orderDir = strtoupper($filters['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        
        // Contar total
        $countQuery = "
            SELECT COUNT(*) as total
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE $whereClause
        ";
        $countStmt = $this->db->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);
        
        // Buscar itens
        $query = "
            SELECT 
                ci.id,
                ci.source_item_id,
                ci.target_item_id,
                ci.source_seller_id,
                ci.title,
                ci.price,
                ci.category_id,
                ci.status,
                ci.source_snapshot,
                ci.target_snapshot,
                ci.created_at,
                ci.last_synced_at,
                COALESCE(m.visits, 0) as visits,
                COALESCE(m.sales, 0) as sales,
                COALESCE(m.revenue, 0) as revenue,
                COALESCE(m.conversion_rate, 0) as conversion_rate
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE $whereClause
            ORDER BY $orderBy $orderDir
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ";
        
        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Processar snapshots
        foreach ($items as &$item) {
            if (!empty($item['source_snapshot'])) {
                $item['source_snapshot'] = json_decode($item['source_snapshot'], true);
            }
            if (!empty($item['target_snapshot'])) {
                $item['target_snapshot'] = json_decode($item['target_snapshot'], true);
            }
        }
        
        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limitSql,
                'total' => $total,
                'pages' => ceil($total / $limitSql)
            ]
        ];
    }
    
    /**
     * Obtém detalhes de um item clonado
     */
    public function getItem(string $itemId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ci.*, 
                   COALESCE(m.visits, 0) as visits,
                   COALESCE(m.sales, 0) as sales,
                   COALESCE(m.revenue, 0) as revenue,
                   COALESCE(m.conversion_rate, 0) as conversion_rate
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE (ci.target_item_id = :item_id OR ci.id = :id)
            AND ci.target_account_id = :account_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'id' => is_numeric($itemId) ? (int) $itemId : 0,
            'account_id' => $this->accountId
        ]);
        
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            return null;
        }
        
        // Decodificar snapshots
        if (!empty($item['source_snapshot'])) {
            $item['source_snapshot'] = json_decode($item['source_snapshot'], true);
        }
        if (!empty($item['target_snapshot'])) {
            $item['target_snapshot'] = json_decode($item['target_snapshot'], true);
        }
        
        // Buscar dados atuais do ML
        try {
            $client = $this->getClient();
            $item['current_data'] = $client->get("/items/{$item['target_item_id']}");
        } catch (\Exception $e) {
            $item['current_data'] = null;
            $item['api_error'] = $e->getMessage();
        }
        
        return $item;
    }
    
    /**
     * Atualiza dados de um item clonado no ML
     */
    public function updateItem(string $itemId, array $data): array
    {
        // Verificar se item pertence à conta
        $stmt = $this->db->prepare("
            SELECT * FROM cloned_items 
            WHERE target_item_id = :item_id 
            AND target_account_id = :account_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $this->accountId
        ]);
        
        $clone = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clone) {
            throw new \Exception("Item não encontrado ou não pertence à conta");
        }
        
        // Campos permitidos para atualização
        $allowed = ['price', 'available_quantity', 'status', 'title', 'description'];
        $updateData = array_intersect_key($data, array_flip($allowed));
        
        if (empty($updateData)) {
            throw new \InvalidArgumentException("Nenhum campo válido para atualização");
        }
        
        // Atualizar no ML
        $client = $this->getClient();
        $result = $client->put("/items/$itemId", $updateData);
        
        // Atualizar local se tiver preço
        if (isset($updateData['price'])) {
            $this->db->prepare("
                UPDATE cloned_items SET price = :price WHERE target_item_id = :item_id
            ")->execute([
                'price' => $updateData['price'],
                'item_id' => $itemId
            ]);
        }
        
        return [
            'success' => true,
            'item_id' => $itemId,
            'updated_fields' => array_keys($updateData),
            'result' => $result
        ];
    }
    
    /**
     * Pausa um item clonado
     */
    public function pauseItem(string $itemId): array
    {
        return $this->updateItem($itemId, ['status' => 'paused']);
    }
    
    /**
     * Ativa um item clonado
     */
    public function activateItem(string $itemId): array
    {
        return $this->updateItem($itemId, ['status' => 'active']);
    }
    
    /**
     * Encerra um item clonado
     */
    public function closeItem(string $itemId): array
    {
        $client = $this->getClient();
        
        // Encerrar no ML
        $result = $client->put("/items/$itemId", ['status' => 'closed']);
        
        // Atualizar status local
        $stmt = $this->db->prepare("
            UPDATE cloned_items 
            SET status = 'closed', closed_at = NOW()
            WHERE target_item_id = :item_id
            AND target_account_id = :account_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $this->accountId
        ]);
        
        return [
            'success' => true,
            'item_id' => $itemId,
            'status' => 'closed'
        ];
    }
    
    /**
     * Operações em lote
     */
    public function batchOperation(string $operation, array $itemIds): array
    {
        $results = [
            'total' => count($itemIds),
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];
        
        foreach ($itemIds as $itemId) {
            try {
                switch ($operation) {
                    case 'pause':
                        $this->pauseItem($itemId);
                        break;
                    case 'activate':
                        $this->activateItem($itemId);
                        break;
                    case 'close':
                        $this->closeItem($itemId);
                        break;
                    case 'sync':
                        $syncService = new CloneSyncService($this->accountId);
                        $syncService->syncItem($itemId);
                        break;
                    default:
                        throw new \InvalidArgumentException("Operação inválida: $operation");
                }
                
                $results['success']++;
                $results['details'][$itemId] = ['success' => true];
                
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$itemId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Rate limit
            usleep(100000);
        }
        
        return $results;
    }
    
    /**
     * Obtém estatísticas gerais dos clones
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_items,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as active_items,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_items,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed_items,
                COUNT(DISTINCT source_seller_id) as unique_sellers,
                COUNT(DISTINCT category_id) as unique_categories,
                AVG(price) as avg_price,
                MIN(created_at) as first_clone,
                MAX(created_at) as last_clone
            FROM cloned_items
            WHERE target_account_id = :account_id
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Métricas agregadas
        $metricsStmt = $this->db->prepare("
            SELECT 
                SUM(visits) as total_visits,
                SUM(sales) as total_sales,
                SUM(revenue) as total_revenue,
                AVG(conversion_rate) as avg_conversion
            FROM clone_item_metrics
            WHERE account_id = :account_id
        ");
        $metricsStmt->execute(['account_id' => $this->accountId]);
        $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($stats, [
            'total_visits' => (int) ($metrics['total_visits'] ?? 0),
            'total_sales' => (int) ($metrics['total_sales'] ?? 0),
            'total_revenue' => (float) ($metrics['total_revenue'] ?? 0),
            'avg_conversion' => (float) ($metrics['avg_conversion'] ?? 0)
        ]);
    }
    
    /**
     * Top itens por vendas
     */
    public function getTopSellers(int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT ci.*, m.visits, m.sales, m.revenue, m.conversion_rate
            FROM cloned_items ci
            INNER JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND m.sales > 0
            ORDER BY m.sales DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Itens que precisam atenção (sem vendas, baixa conversão, etc)
     */
    public function getItemsNeedingAttention(int $limit = 20): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT ci.*, 
                   COALESCE(m.visits, 0) as visits, 
                   COALESCE(m.sales, 0) as sales,
                   COALESCE(m.conversion_rate, 0) as conversion_rate,
                   CASE 
                       WHEN COALESCE(m.visits, 0) > 100 AND COALESCE(m.sales, 0) = 0 THEN 'no_sales_high_visits'
                       WHEN COALESCE(m.conversion_rate, 0) < 1 AND COALESCE(m.visits, 0) > 50 THEN 'low_conversion'
                       WHEN ci.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND COALESCE(m.sales, 0) = 0 THEN 'stale_no_sales'
                       ELSE 'other'
                   END as attention_reason
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.status = 'completed'
            AND (
                (COALESCE(m.visits, 0) > 100 AND COALESCE(m.sales, 0) = 0)
                OR (COALESCE(m.conversion_rate, 0) < 1 AND COALESCE(m.visits, 0) > 50)
                OR (ci.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND COALESCE(m.sales, 0) = 0)
            )
            ORDER BY m.visits DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Distribuição por categoria
     */
    public function getCategoryDistribution(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                category_id,
                COUNT(*) as item_count,
                SUM(COALESCE(m.sales, 0)) as total_sales,
                SUM(COALESCE(m.revenue, 0)) as total_revenue,
                AVG(COALESCE(m.conversion_rate, 0)) as avg_conversion
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            GROUP BY category_id
            ORDER BY item_count DESC
            LIMIT 20
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Distribuição por vendedor de origem
     */
    public function getSellerDistribution(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                source_seller_id,
                COUNT(*) as item_count,
                SUM(COALESCE(m.sales, 0)) as total_sales,
                SUM(COALESCE(m.revenue, 0)) as total_revenue,
                AVG(COALESCE(m.conversion_rate, 0)) as avg_conversion
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            GROUP BY source_seller_id
            ORDER BY total_sales DESC
            LIMIT 20
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Exporta dados para CSV
     */
    public function exportToCsv(array $filters = []): string
    {
        $result = $this->listItems(array_merge($filters, ['limit' => 10000]));
        
        $output = fopen('php://temp', 'r+');
        
        // Header
        fputcsv($output, [
            'ID',
            'Item ID',
            'Source Item ID',
            'Source Seller',
            'Title',
            'Price',
            'Category',
            'Status',
            'Visits',
            'Sales',
            'Revenue',
            'Conversion',
            'Created At',
            'Last Synced'
        ]);
        
        // Data
        foreach ($result['items'] as $item) {
            fputcsv($output, [
                $item['id'],
                $item['target_item_id'],
                $item['source_item_id'],
                $item['source_seller_id'],
                $item['title'],
                $item['price'],
                $item['category_id'],
                $item['status'],
                $item['visits'],
                $item['sales'],
                $item['revenue'],
                $item['conversion_rate'],
                $item['created_at'],
                $item['last_synced_at']
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}
