<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Transações de EAN (log de movimentações)
 */
class EanTransaction
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Registrar transação
     */
    public function log(
        int $accountId,
        string $type,
        int $quantity,
        int $balanceBefore,
        int $balanceAfter,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $description = null,
        ?int $createdBy = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO ean_transactions 
            (account_id, type, quantity, balance_before, balance_after, 
             reference_type, reference_id, description, created_by)
            VALUES 
            (:account_id, :type, :quantity, :balance_before, :balance_after,
             :reference_type, :reference_id, :description, :created_by)
        ");
        
        $stmt->execute([
            'account_id' => $accountId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => $description,
            'created_by' => $createdBy,
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Registrar crédito (compra)
     */
    public function logCredit(int $accountId, int $quantity, int $balanceBefore, int $purchaseId, ?string $description = null): int
    {
        return $this->log(
            $accountId,
            'credit',
            $quantity,
            $balanceBefore,
            $balanceBefore + $quantity,
            'purchase',
            $purchaseId,
            $description ?? "Compra de {$quantity} EANs"
        );
    }
    
    /**
     * Registrar débito (uso)
     */
    public function logDebit(int $accountId, int $quantity, int $balanceBefore, ?int $assignmentId = null, ?string $description = null): int
    {
        return $this->log(
            $accountId,
            'debit',
            $quantity,
            $balanceBefore,
            $balanceBefore - $quantity,
            'assignment',
            $assignmentId,
            $description ?? "Uso de {$quantity} EAN(s)"
        );
    }
    
    /**
     * Registrar reembolso
     */
    public function logRefund(int $accountId, int $quantity, int $balanceBefore, int $purchaseId, ?string $description = null): int
    {
        return $this->log(
            $accountId,
            'refund',
            $quantity,
            $balanceBefore,
            $balanceBefore - $quantity,
            'purchase',
            $purchaseId,
            $description ?? "Reembolso de {$quantity} EANs"
        );
    }
    
    /**
     * Registrar ajuste manual
     */
    public function logAdjustment(int $accountId, int $quantity, int $balanceBefore, int $balanceAfter, string $description, int $createdBy): int
    {
        return $this->log(
            $accountId,
            'adjustment',
            $quantity,
            $balanceBefore,
            $balanceAfter,
            'manual',
            null,
            $description,
            $createdBy
        );
    }
    
    /**
     * Histórico de um seller
     */
    public function getByAccount(int $accountId, int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM ean_transactions 
            WHERE account_id = :account_id 
            ORDER BY created_at DESC 
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Histórico geral (admin)
     */
    public function listAll(int $page = 1, int $perPage = 50, ?string $type = null): array
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(200, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $limitSql = $perPage;
        $offsetSql = max(0, min(1000000, (int)$offset));
        $where = '';
        $params = [];
        
        if ($type) {
            $where = "WHERE t.type = :type";
            $params['type'] = $type;
        }
        
        $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM ean_transactions t {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];
        
        $sql = "
            SELECT t.*, a.nickname as account_name
            FROM ean_transactions t
            JOIN ml_accounts a ON t.account_id = a.id
            {$where}
            ORDER BY t.created_at DESC
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ";
        
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        
        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }
}
