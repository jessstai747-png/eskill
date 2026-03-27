<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Saldo de EANs dos Sellers
 */
class EanBalance
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Obter saldo de um seller
     */
    public function getByAccount(int $accountId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, account_id, total_purchased, total_used, available, last_purchase_at, created_at, updated_at
            FROM ean_balances WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $accountId]);
        $balance = $stmt->fetch();

        if (!$balance) {
            // Tentar criar registro se não existir
            $created = $this->create($accountId);

            if ($created > 0) {
                // Re-buscar o registro criado
                $stmt->execute(['account_id' => $accountId]);
                return $stmt->fetch() ?: null;
            }

            // Se não conseguiu criar, retornar valores padrão
            return [
                'account_id' => $accountId,
                'total_purchased' => 0,
                'available' => 0,
                'total_used' => 0,
                'last_purchase_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        return $balance;
    }

    /**
     * Criar registro de saldo
     */
    public function create(int $accountId): int
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO ean_balances (account_id) VALUES (:account_id)
        ");
        $stmt->execute(['account_id' => $accountId]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Creditar EANs (após compra paga)
     */
    public function credit(int $accountId, int $quantity): bool
    {
        // Garantir que o registro existe
        $this->create($accountId);

        $stmt = $this->db->prepare("
            UPDATE ean_balances
            SET
                total_purchased = total_purchased + :qty1,
                available = available + :qty2,
                last_purchase_at = NOW()
            WHERE account_id = :account_id
        ");

        return $stmt->execute([
            'account_id' => $accountId,
            'qty1' => $quantity,
            'qty2' => $quantity,
        ]);
    }

    /**
     * Debitar EANs (ao usar)
     */
    public function debit(int $accountId, int $quantity = 1): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_balances
            SET
                total_used = total_used + :qty1,
                available = available - :qty2
            WHERE account_id = :account_id AND available >= :qty3
        ");

        return $stmt->execute([
            'account_id' => $accountId,
            'qty1' => $quantity,
            'qty2' => $quantity,
            'qty3' => $quantity,
        ]);
    }

    /**
     * Desfazer uso de EAN (desvincular)
     */
    public function unuse(int $accountId, int $quantity = 1): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_balances
            SET
                total_used = total_used - :qty1,
                available = available + :qty2
            WHERE account_id = :account_id AND total_used >= :qty3
        ");

        return $stmt->execute([
            'account_id' => $accountId,
            'qty1' => $quantity,
            'qty2' => $quantity,
            'qty3' => $quantity,
        ]);
    }

    /**
     * Estornar crédito (reembolso)
     * Uses transaction + SELECT FOR UPDATE to prevent race conditions.
     * Prevents negative balance by verifying available >= quantity under lock.
     */
    public function refund(int $accountId, int $quantity): bool
    {
        $this->db->beginTransaction();
        try {
            // Lock the row to prevent concurrent refunds racing
            $lockStmt = $this->db->prepare("
                SELECT available, total_purchased
                FROM ean_balances
                WHERE account_id = :account_id
                FOR UPDATE
            ");
            $lockStmt->execute(['account_id' => $accountId]);
            $row = $lockStmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->db->rollBack();
                error_log("EanBalance::refund failed — no balance record for account_id={$accountId}");
                return false;
            }

            if ((int)$row['available'] < $quantity || (int)$row['total_purchased'] < $quantity) {
                $this->db->rollBack();
                error_log("EanBalance::refund failed — insufficient balance for account_id={$accountId}, qty={$quantity}, available={$row['available']}, total_purchased={$row['total_purchased']}");
                return false;
            }

            // Row is locked — safe to update without race condition
            $updateStmt = $this->db->prepare("
                UPDATE ean_balances
                SET
                    total_purchased = total_purchased - :qty1,
                    available = available - :qty2
                WHERE account_id = :account_id
            ");

            $updateStmt->execute([
                'account_id' => $accountId,
                'qty1' => $quantity,
                'qty2' => $quantity,
            ]);

            $this->db->commit();
            return true;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("EanBalance::refund exception for account_id={$accountId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ajustar saldo manualmente
     */
    public function adjust(int $accountId, int $newAvailable, int $newUsed): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_balances
            SET
                total_purchased = :total,
                total_used = :used,
                available = :available
            WHERE account_id = :account_id
        ");

        return $stmt->execute([
            'account_id' => $accountId,
            'total' => $newAvailable + $newUsed,
            'used' => $newUsed,
            'available' => $newAvailable,
        ]);
    }

    /**
     * Verificar se tem saldo disponível
     */
    public function hasAvailable(int $accountId, int $quantity = 1): bool
    {
        $balance = $this->getByAccount($accountId);
        return $balance && $balance['available'] >= $quantity;
    }

    /**
     * Recalcular saldo baseado nas atribuições
     */
    public function recalculate(int $accountId): bool
    {
        // Contar EANs atribuídos
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN ml_item_id IS NULL THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN ml_item_id IS NOT NULL THEN 1 ELSE 0 END) as used
            FROM ean_assignments
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $accountId]);
        $counts = $stmt->fetch();

        // Atualizar saldo
        $this->create($accountId);

        $updateStmt = $this->db->prepare("
            UPDATE ean_balances
            SET
                total_purchased = :total,
                total_used = :used,
                available = :available
            WHERE account_id = :account_id
        ");

        return $updateStmt->execute([
            'account_id' => $accountId,
            'total' => (int) $counts['total'],
            'used' => (int) $counts['used'],
            'available' => (int) $counts['available'],
        ]);
    }

    /**
     * Listar todos os saldos (admin)
     */
    public function listAll(): array
    {
        $stmt = $this->db->query("
            SELECT
                b.*,
                a.nickname as account_name
            FROM ean_balances b
            JOIN ml_accounts a ON b.account_id = a.id
            ORDER BY b.total_purchased DESC
        ");
        return $stmt->fetchAll();
    }
}
