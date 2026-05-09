<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use PDO;

/**
 * BrandSearchModel
 *
 * Acesso PDO às tabelas do módulo Brand Search (BRAND-003):
 *   - brand_searches  → registros de busca com status e progresso
 *   - brand_sellers   → vendedores únicos por busca
 *   - brand_items     → anúncios coletados por busca
 */
class BrandSearchModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // -------------------------------------------------------------------------
    // brand_searches
    // -------------------------------------------------------------------------

    /**
     * Cria registro inicial de busca com status=pending.
     */
    public function createSearch(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO brand_searches
                (account_id, brand_id, brand_name, site_id, category_id, status)
            VALUES
                (:account_id, :brand_id, :brand_name, :site_id, :category_id, 'pending')
        ");
        $stmt->execute([
            'account_id'  => $data['account_id'],
            'brand_id'    => $data['brand_id'],
            'brand_name'  => $data['brand_name'],
            'site_id'     => $data['site_id'] ?? 'MLB',
            'category_id' => $data['category_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Retorna dados de uma busca por ID.
     */
    public function getSearch(int $searchId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM brand_searches WHERE id = :id');
        $stmt->execute(['id' => $searchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Retorna buscas com status=pending.
     */
    public function getPendingSearches(): array
    {
        $stmt = $this->db->query("
            SELECT * FROM brand_searches
            WHERE status = 'pending'
            ORDER BY created_at ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza progresso durante a coleta (0-100).
     */
    public function updateProgress(int $searchId, int $progress, string $status): void
    {
        $this->db->prepare("
            UPDATE brand_searches
            SET progress = :progress, status = :status,
                started_at = CASE WHEN started_at IS NULL AND :s2 = 'running' THEN NOW() ELSE started_at END
            WHERE id = :id
        ")->execute([
            'id'       => $searchId,
            'progress' => $progress,
            'status'   => $status,
            's2'       => $status,
        ]);
    }

    /**
     * Marca busca como concluída com totais finais.
     */
    public function updateCompleted(int $searchId, int $totalItems, int $totalSellers): void
    {
        $this->db->prepare("
            UPDATE brand_searches
            SET status = 'completed', progress = 100,
                total_items = :total_items, total_sellers = :total_sellers,
                completed_at = NOW()
            WHERE id = :id
        ")->execute([
            'id'            => $searchId,
            'total_items'   => $totalItems,
            'total_sellers' => $totalSellers,
        ]);
    }

    /**
     * Marca busca como falha com mensagem de erro.
     */
    public function updateFailed(int $searchId, string $errorMessage): void
    {
        $this->db->prepare("
            UPDATE brand_searches
            SET status = 'failed', error_message = :error
            WHERE id = :id
        ")->execute([
            'id'    => $searchId,
            'error' => mb_substr($errorMessage, 0, 65535),
        ]);
    }

    // -------------------------------------------------------------------------
    // brand_sellers
    // -------------------------------------------------------------------------

    /**
     * INSERT em lote com INSERT IGNORE para brand_sellers.
     *
     * @param int     $searchId
     * @param array[] $sellers
     */
    public function saveSellers(int $searchId, array $sellers): void
    {
        if (empty($sellers)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($sellers), '(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'));
        $sql = "INSERT IGNORE INTO brand_sellers
            (search_id, seller_id, nickname, seller_type, permalink,
             reputation_level, reputation_score, power_seller_status,
             total_items_brand, avg_price, site_status, country_id, city, state, trend)
            VALUES {$placeholders}";

        $values = [];
        foreach ($sellers as $s) {
            $values[] = $searchId;
            $values[] = (int) ($s['seller_id'] ?? 0);
            $values[] = (string) ($s['nickname'] ?? '');
            $values[] = $s['seller_type'] ?? null;
            $values[] = $s['permalink'] ?? null;
            $values[] = $s['reputation_level'] ?? null;
            $values[] = isset($s['reputation_score']) ? (int) $s['reputation_score'] : null;
            $values[] = $s['power_seller_status'] ?? null;
            $values[] = (int) ($s['total_items_brand'] ?? 0);
            $values[] = isset($s['avg_price']) ? (float) $s['avg_price'] : null;
            $values[] = $s['site_status'] ?? null;
            $values[] = $s['country_id'] ?? 'BR';
            $values[] = $s['city'] ?? null;
            $values[] = $s['state'] ?? null;
            $values[] = $s['trend'] ?? 'stable';
        }

        $this->db->prepare($sql)->execute($values);
    }

    /**
     * Lista sellers com filtros e paginação.
     */
    public function getSellersBySearchId(
        int $searchId,
        array $filters,
        string $sort,
        string $order,
        int $limit,
        int $offset
    ): array {
        $allowed   = ['total_items_brand', 'reputation_score', 'avg_price', 'nickname', 'trend'];
        $sortCol   = in_array($sort, $allowed, true) ? $sort : 'total_items_brand';
        $sortDir   = strtolower($order) === 'asc' ? 'ASC' : 'DESC';

        [$where, $params] = $this->buildSellerWhere($searchId, $filters);

        $stmt = $this->db->prepare("
            SELECT * FROM brand_sellers
            WHERE {$where}
            ORDER BY {$sortCol} {$sortDir}
            LIMIT :lim OFFSET :off
        ");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta total de sellers para paginação.
     */
    public function countSellersBySearchId(int $searchId, array $filters): int
    {
        [$where, $params] = $this->buildSellerWhere($searchId, $filters);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM brand_sellers WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Estatísticas de um seller em uma busca (total_items, avg_price).
     */
    public function getSellerStats(int $searchId, int $sellerId): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total_items, AVG(price) AS avg_price
            FROM brand_items
            WHERE search_id = :search_id AND seller_id = :seller_id
        ");
        $stmt->execute(['search_id' => $searchId, 'seller_id' => $sellerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_items' => (int) ($row['total_items'] ?? 0),
            'avg_price'   => round((float) ($row['avg_price'] ?? 0.0), 2),
        ];
    }

    // -------------------------------------------------------------------------
    // brand_items
    // -------------------------------------------------------------------------

    /**
     * INSERT em lote com INSERT IGNORE para brand_items.
     *
     * @param int     $searchId
     * @param array[] $items
     */
    public function saveItems(int $searchId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($items), '(?,?,?,?,?,?,?,?,?,?,?,?)'));
        $sql = "INSERT IGNORE INTO brand_items
            (search_id, seller_id, item_id, title, category_id, category_name,
             price, currency_id, `condition`, listing_type, available_qty, status)
            VALUES {$placeholders}";

        $values = [];
        foreach ($items as $i) {
            $values[] = $searchId;
            $values[] = (int) ($i['seller_id'] ?? 0);
            $values[] = (string) ($i['item_id'] ?? '');
            $values[] = mb_substr((string) ($i['title'] ?? ''), 0, 255);
            $values[] = $i['category_id'] ?? null;
            $values[] = isset($i['category_name']) ? mb_substr((string) $i['category_name'], 0, 100) : null;
            $values[] = isset($i['price']) ? (float) $i['price'] : null;
            $values[] = $i['currency_id'] ?? 'BRL';
            $values[] = $i['condition'] ?? 'new';
            $values[] = $i['listing_type'] ?? null;
            $values[] = isset($i['available_qty']) ? (int) $i['available_qty'] : null;
            $values[] = $i['status'] ?? 'active';
        }

        $this->db->prepare($sql)->execute($values);
    }

    /**
     * Conta total de itens de uma busca.
     */
    public function countItems(int $searchId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM brand_items WHERE search_id = :id');
        $stmt->execute(['id' => $searchId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna anúncios de um seller específico em uma busca.
     */
    public function getItemsBySeller(int $searchId, int $sellerId, int $limit, int $offset): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM brand_items
            WHERE search_id = :search_id AND seller_id = :seller_id
            ORDER BY price DESC
            LIMIT :lim OFFSET :off
        ");
        $stmt->bindValue(':search_id', $searchId, PDO::PARAM_INT);
        $stmt->bindValue(':seller_id', $sellerId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Monta cláusula WHERE e parâmetros para queries de sellers.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSellerWhere(int $searchId, array $filters): array
    {
        $where  = 'search_id = :search_id';
        $params = ['search_id' => $searchId];

        if (!empty($filters['reputation'])) {
            $where           .= ' AND reputation_level = :rep';
            $params['rep']    = (string) $filters['reputation'];
        }

        if (!empty($filters['min_items']) && (int) $filters['min_items'] > 0) {
            $where                 .= ' AND total_items_brand >= :min_items';
            $params['min_items']    = (int) $filters['min_items'];
        }

        return [$where, $params];
    }
}
