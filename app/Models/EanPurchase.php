<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Compras de EAN
 */
class EanPurchase
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Criar nova compra
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ean_purchases 
            (account_id, package_id, quantity, unit_price, total_amount, discount_applied, 
             payment_method, payment_status, notes)
            VALUES 
            (:account_id, :package_id, :quantity, :unit_price, :total_amount, :discount_applied,
             :payment_method, :payment_status, :notes)
        ");
        
        $stmt->execute([
            'account_id' => $data['account_id'],
            'package_id' => $data['package_id'] ?? null,
            'quantity' => $data['quantity'],
            'unit_price' => $data['unit_price'],
            'total_amount' => $data['total_amount'],
            'discount_applied' => $data['discount_applied'] ?? 0,
            'payment_method' => $data['payment_method'] ?? 'pix',
            'payment_status' => 'pending',
            'notes' => $data['notes'] ?? null,
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    /**
     * Atualizar dados de pagamento
     */
    public function updatePayment(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        
        $allowedFields = [
            'payment_id', 'payment_external_id', 'payment_url', 
            'payment_qr_code', 'payment_qr_code_base64', 'payment_expires_at',
            'payment_status', 'paid_at', 'payment_method'
        ];
        
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $fields[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE ean_purchases SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Marcar como pago
     */
    public function markAsPaid(int $id, ?string $paymentId = null): bool
    {
        $params = ['id' => $id];
        $sql = "UPDATE ean_purchases SET payment_status = 'paid', paid_at = NOW()";
        
        if ($paymentId) {
            $sql .= ", payment_id = :payment_id";
            $params['payment_id'] = $paymentId;
        }
        
        $sql .= " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Buscar por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, pk.name as package_name, pk.slug as package_slug
            FROM ean_purchases p
            LEFT JOIN ean_packages pk ON p.package_id = pk.id
            WHERE p.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Buscar por payment_id (do gateway)
     */
    public function getByPaymentId(string $paymentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ean_purchases 
            WHERE payment_id = :payment_id OR payment_external_id = :payment_id
        ");
        $stmt->execute(['payment_id' => $paymentId]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Listar compras de uma conta
     */
    public function getByAccount(int $accountId, int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT p.*, pk.name as package_name
            FROM ean_purchases p
            LEFT JOIN ean_packages pk ON p.package_id = pk.id
            WHERE p.account_id = :account_id
            ORDER BY p.created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Listar todas as compras (admin)
     */
    public function listAll(int $page = 1, int $perPage = 50, ?string $status = null): array
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(200, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $limitSql = $perPage;
        $offsetSql = max(0, min(1000000, (int)$offset));
        $where = '';
        $params = [];
        
        if ($status) {
            $where = "WHERE p.payment_status = :status";
            $params['status'] = $status;
        }
        
        // Total
        $countSql = "SELECT COUNT(*) as total FROM ean_purchases p {$where}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];
        
        // Dados
        $sql = "
            SELECT p.*, pk.name as package_name, a.nickname as account_name
            FROM ean_purchases p
            LEFT JOIN ean_packages pk ON p.package_id = pk.id
            LEFT JOIN ml_accounts a ON p.account_id = a.id
            {$where}
            ORDER BY p.created_at DESC
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
    
    /**
     * Compras pendentes expiradas
     */
    public function getExpiredPending(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM ean_purchases 
            WHERE payment_status = 'pending' 
            AND payment_expires_at IS NOT NULL 
            AND payment_expires_at < NOW()
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Cancelar compra
     */
    public function cancel(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ean_purchases 
            SET payment_status = 'cancelled' 
            WHERE id = :id AND payment_status IN ('pending', 'processing')
        ");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Estatísticas de vendas
     */
    public function getStats(?string $startDate = null, ?string $endDate = null): array
    {
        $where = "WHERE payment_status = 'paid'";
        $params = [];
        
        if ($startDate) {
            $where .= " AND DATE(paid_at) >= :start_date";
            $params['start_date'] = $startDate;
        }
        
        if ($endDate) {
            $where .= " AND DATE(paid_at) <= :end_date";
            $params['end_date'] = $endDate;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(quantity) as total_eans,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_order_value
            FROM ean_purchases
            {$where}
        ");
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
    
    /**
     * Vendas por período (para gráficos)
     */
    public function getSalesByPeriod(string $period = 'day', int $days = 30): array
    {
        $groupBy = match($period) {
            'day' => 'DATE(paid_at)',
            'week' => 'YEARWEEK(paid_at)',
            'month' => 'DATE_FORMAT(paid_at, "%Y-%m")',
            default => 'DATE(paid_at)'
        };
        
        $stmt = $this->db->prepare("
            SELECT 
                {$groupBy} as period,
                COUNT(*) as orders,
                SUM(quantity) as eans,
                SUM(total_amount) as revenue
            FROM ean_purchases
            WHERE payment_status = 'paid' 
            AND paid_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY {$groupBy}
            ORDER BY period ASC
        ");
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
