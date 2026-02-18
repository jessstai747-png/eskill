<?php
declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Inventário de EANs
 */
class EanInventory
{
    private PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
    }
    
    /**
     * Adicionar EANs ao inventário (em lote)
     */
    public function addBatch(array $eans, string $batch, float $cost = 0, string $supplier = ''): int
    {
        $added = 0;
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO ean_inventory (ean, purchase_batch, cost, supplier)
            VALUES (:ean, :batch, :cost, :supplier)
        ");
        
        foreach ($eans as $ean) {
            if ($this->validateEan($ean)) {
                $stmt->execute([
                    'ean' => $ean,
                    'batch' => $batch,
                    'cost' => $cost,
                    'supplier' => $supplier,
                ]);
                if ($stmt->rowCount() > 0) {
                    $added++;
                }
            }
        }
        
        return $added;
    }
    
    /**
     * Adicionar um único EAN
     */
    public function add(string $ean, string $batch = '', float $cost = 0, string $supplier = ''): ?int
    {
        if (!$this->validateEan($ean)) {
            return null;
        }
        
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO ean_inventory (ean, purchase_batch, cost, supplier)
            VALUES (:ean, :batch, :cost, :supplier)
        ");
        
        $stmt->execute([
            'ean' => $ean,
            'batch' => $batch,
            'cost' => $cost,
            'supplier' => $supplier,
        ]);
        
        return $stmt->rowCount() > 0 ? (int) $this->db->lastInsertId() : null;
    }
    
    /**
     * Buscar EANs disponíveis
     */
    public function getAvailable(int $limit = 100): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM ean_inventory 
            WHERE status = 'available' 
            ORDER BY id ASC 
            LIMIT {$limitSql}
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Reservar EANs para uma compra
     * Nota: Deve ser chamado dentro de uma transação existente
     */
    public function reserveEans(int $quantity): array
    {
        $quantitySql = max(1, min(1000, (int)$quantity));
        // Buscar EANs disponíveis com lock
        $stmt = $this->db->prepare("
            SELECT id, ean FROM ean_inventory 
            WHERE status = 'available' 
            ORDER BY id ASC 
            LIMIT {$quantitySql}
            FOR UPDATE
        ");
        $stmt->execute();
        $eans = $stmt->fetchAll();
        
        if (count($eans) < $quantitySql) {
            return [];
        }
        
        // Marcar como reservados
        $ids = array_column($eans, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $updateStmt = $this->db->prepare("
            UPDATE ean_inventory 
            SET status = 'reserved', reserved_at = NOW() 
            WHERE id IN ({$placeholders})
        ");
        $updateStmt->execute($ids);
        
        return $eans;
    }
    
    /**
     * Confirmar venda de EANs reservados
     */
    public function confirmSale(array $eanIds): bool
    {
        $placeholders = implode(',', array_fill(0, count($eanIds), '?'));
        $stmt = $this->db->prepare("
            UPDATE ean_inventory 
            SET status = 'sold', sold_at = NOW() 
            WHERE id IN ({$placeholders})
        ");
        return $stmt->execute($eanIds);
    }
    
    /**
     * Liberar EANs reservados (cancelamento)
     */
    public function releaseReserved(array $eanIds): bool
    {
        $placeholders = implode(',', array_fill(0, count($eanIds), '?'));
        $stmt = $this->db->prepare("
            UPDATE ean_inventory 
            SET status = 'available', reserved_at = NULL 
            WHERE id IN ({$placeholders}) AND status = 'reserved'
        ");
        return $stmt->execute($eanIds);
    }
    
    /**
     * Contar EANs por status
     */
    public function countByStatus(): array
    {
        $stmt = $this->db->query("
            SELECT status, COUNT(*) as total 
            FROM ean_inventory 
            GROUP BY status
        ");
        
        $result = ['available' => 0, 'reserved' => 0, 'sold' => 0, 'total' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['status']] = (int) $row['total'];
            $result['total'] += (int) $row['total'];
        }
        return $result;
    }
    
    /**
     * Buscar EAN por código
     */
    public function getByEan(string $ean): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ean_inventory WHERE ean = :ean");
        $stmt->execute(['ean' => $ean]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Buscar por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ean_inventory WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Validar formato EAN-13 ou EAN-8
     */
    public function validateEan(string $ean): bool
    {
        // Remover espaços
        $ean = trim($ean);
        
        // Verificar se é numérico e tem tamanho correto
        if (!preg_match('/^\d{8}$|^\d{13}$/', $ean)) {
            return false;
        }
        
        // Calcular dígito verificador
        $digits = str_split($ean);
        $checkDigit = array_pop($digits);
        
        $sum = 0;
        $length = strlen($ean);
        
        foreach ($digits as $i => $digit) {
            $multiplier = ($length == 13) 
                ? (($i % 2 == 0) ? 1 : 3)
                : (($i % 2 == 0) ? 3 : 1);
            $sum += (int)$digit * $multiplier;
        }
        
        $calculatedCheck = (10 - ($sum % 10)) % 10;
        
        return $calculatedCheck == (int)$checkDigit;
    }
    
    /**
     * Gerar EAN válido (para testes - NÃO usar em produção!)
     */
    public function generateTestEan(string $prefix = '789'): string
    {
        // Gerar código base com 12 dígitos
        $base = $prefix . str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
        
        // Calcular dígito verificador
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$base[$i] * (($i % 2 == 0) ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;
        
        return $base . $checkDigit;
    }
    
    /**
     * Listar EANs com paginação
     */
    public function list(int $page = 1, int $perPage = 50, ?string $status = null, ?string $batch = null): array
    {
        $page = max(1, (int)$page);
        $perPage = max(1, min(200, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $limitSql = $perPage;
        $offsetSql = max(0, min(1000000, (int)$offset));
        $where = [];
        $params = [];
        
        if ($status) {
            $where[] = "status = :status";
            $params['status'] = $status;
        }
        
        if ($batch) {
            $where[] = "purchase_batch = :batch";
            $params['batch'] = $batch;
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(' AND ', $where) : '';
        
        // Total
        $countStmt = $this->db->prepare("SELECT COUNT(*) as total FROM ean_inventory {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetch()['total'];
        
        // Dados
        $sql = "SELECT * FROM ean_inventory {$whereClause} ORDER BY id DESC LIMIT {$limitSql} OFFSET {$offsetSql}";
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
     * Listar lotes de compra
     */
    public function getBatches(): array
    {
        $stmt = $this->db->query("
            SELECT 
                purchase_batch,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold,
                MIN(created_at) as first_added,
                supplier,
                cost
            FROM ean_inventory
            WHERE purchase_batch IS NOT NULL AND purchase_batch != ''
            GROUP BY purchase_batch, supplier, cost
            ORDER BY first_added DESC
        ");
        return $stmt->fetchAll();
    }
}
