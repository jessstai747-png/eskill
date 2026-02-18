<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Atribuições de EAN aos Sellers
 */
class EanAssignment
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Atribuir EAN a um seller
     */
    public function assign(int $eanId, int $accountId, ?int $purchaseId = null, array $productData = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ean_assignments 
            (ean_id, account_id, purchase_id, ml_item_id, product_title, product_sku, category_id)
            VALUES 
            (:ean_id, :account_id, :purchase_id, :ml_item_id, :product_title, :product_sku, :category_id)
        ");
        
        $stmt->execute([
            'ean_id' => $eanId,
            'account_id' => $accountId,
            'purchase_id' => $purchaseId,
            'ml_item_id' => $productData['ml_item_id'] ?? null,
            'product_title' => $productData['product_title'] ?? null,
            'product_sku' => $productData['product_sku'] ?? null,
            'category_id' => $productData['category_id'] ?? null,
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Atribuir múltiplos EANs
     */
    public function assignBatch(array $eanIds, int $accountId, int $purchaseId): int
    {
        $assigned = 0;
        $stmt = $this->db->prepare("
            INSERT INTO ean_assignments (ean_id, account_id, purchase_id)
            VALUES (:ean_id, :account_id, :purchase_id)
        ");
        
        foreach ($eanIds as $eanId) {
            try {
                $stmt->execute([
                    'ean_id' => $eanId,
                    'account_id' => $accountId,
                    'purchase_id' => $purchaseId,
                ]);
                $assigned++;
            } catch (\PDOException $e) {
                // EAN já atribuído ou outro erro de constraint
                error_log("EanAssignment::assignBatch failed for ean_id={$eanId}, account_id={$accountId}: " . $e->getMessage());
                continue;
            }
        }
        
        return $assigned;
    }
    
    /**
     * Buscar EANs de um seller
     */
    public function getByAccount(int $accountId, bool $onlyAvailable = false, int $limit = 100): array
    {
        $where = "WHERE a.account_id = :account_id";

        $limitSql = max(1, min(500, (int)$limit));
        
        if ($onlyAvailable) {
            $where .= " AND a.ml_item_id IS NULL";
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                i.ean,
                i.status as inventory_status
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            {$where}
            ORDER BY a.assigned_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar EANs disponíveis (não usados) de um seller
     */
    public function getAvailableByAccount(int $accountId, int $limit = 100): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT 
                a.id as assignment_id,
                i.id as ean_id,
                i.ean
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            WHERE a.account_id = :account_id 
            AND a.ml_item_id IS NULL
            ORDER BY a.assigned_at ASC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Vincular EAN a um anúncio do ML
     */
    public function linkToItem(int $assignmentId, string $mlItemId, ?string $title = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_assignments 
            SET ml_item_id = :ml_item_id, product_title = :title
            WHERE id = :id AND ml_item_id IS NULL
        ");
        
        return $stmt->execute([
            'id' => $assignmentId,
            'ml_item_id' => $mlItemId,
            'title' => $title,
        ]);
    }
    
    /**
     * Desvincular EAN de um anúncio
     */
    public function unlinkFromItem(int $assignmentId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_assignments 
            SET ml_item_id = NULL 
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $assignmentId]);
    }
    
    /**
     * Buscar atribuição por EAN
     */
    public function getByEan(string $ean): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, i.ean
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            WHERE i.ean = :ean
        ");
        $stmt->execute(['ean' => $ean]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Buscar por item do ML
     */
    public function getByMlItem(string $mlItemId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, i.ean
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            WHERE a.ml_item_id = :ml_item_id
        ");
        $stmt->execute(['ml_item_id' => $mlItemId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Contar EANs de um seller
     */
    public function countByAccount(int $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN ml_item_id IS NULL THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN ml_item_id IS NOT NULL THEN 1 ELSE 0 END) as used
            FROM ean_assignments
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetch();
    }
    
    /**
     * Próximo EAN disponível para um seller
     */
    public function getNextAvailable(int $accountId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                a.id as assignment_id,
                i.id as ean_id,
                i.ean
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            WHERE a.account_id = :account_id 
            AND a.ml_item_id IS NULL
            ORDER BY a.assigned_at ASC
            LIMIT 1
        ");
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Buscar atribuição por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT a.*, i.ean
            FROM ean_assignments a
            JOIN ean_inventory i ON a.ean_id = i.id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Marcar como disponível novamente
     */
    public function markAsAvailable(int $assignmentId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_assignments 
            SET ml_item_id = NULL, product_title = NULL, used_at = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $assignmentId]);
    }
}
