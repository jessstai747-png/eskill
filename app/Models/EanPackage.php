<?php

namespace App\Models;

use App\Database;
use PDO;

/**
 * Model para Pacotes de EAN
 */
class EanPackage
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Buscar todos os pacotes ativos
     */
    public function getAllActive(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ean_packages 
            WHERE is_active = TRUE 
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Buscar pacote por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ean_packages WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Buscar pacote por slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ean_packages WHERE slug = :slug AND is_active = TRUE");
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Criar novo pacote
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO ean_packages 
            (name, slug, quantity, price, price_per_ean, discount_percent, description, badge, is_featured, is_active, sort_order)
            VALUES 
            (:name, :slug, :quantity, :price, :price_per_ean, :discount_percent, :description, :badge, :is_featured, :is_active, :sort_order)
        ");

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
            'price_per_ean' => $data['price'] / $data['quantity'],
            'discount_percent' => $data['discount_percent'] ?? 0,
            'description' => $data['description'] ?? null,
            'badge' => $data['badge'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualizar pacote
     */
    public function update(int $id, array $data): bool
    {
        $allowedFields = [
            'name',
            'slug',
            'quantity',
            'price',
            'discount_percent',
            'description',
            'badge',
            'is_featured',
            'is_active',
            'sort_order',
        ];

        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedFields, true)) {
                continue;
            }
            $fields[] = "{$key} = :{$key}";
            $params[$key] = $value;
        }

        if (empty($fields)) {
            return false;
        }

        if (isset($data['price']) && isset($data['quantity']) && (int)$data['quantity'] > 0) {
            $fields[] = "price_per_ean = :price_per_ean";
            $params['price_per_ean'] = $data['price'] / $data['quantity'];
        }

        $sql = "UPDATE ean_packages SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Desativar pacote
     */
    public function deactivate(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE ean_packages SET is_active = FALSE WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Obter pacotes em destaque
     */
    public function getFeatured(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ean_packages 
            WHERE is_active = TRUE AND is_featured = TRUE 
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
