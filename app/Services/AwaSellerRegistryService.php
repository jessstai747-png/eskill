<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use RuntimeException;

class AwaSellerRegistryService
{
    private PDO $db;
    private int $accountId;

    public function __construct(?int $accountId = null, ?PDO $db = null)
    {
        if (($accountId ?? 0) <= 0) {
            throw new RuntimeException('Conta Mercado Livre inválida para o registry AWA Sellers.');
        }

        $this->accountId = (int) $accountId;
        $this->db = $db ?? Database::getInstance();

        AwaSellerSchemaService::ensureSchema($this->db);
    }

    public function createScanRun(array $scope): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO awa_scan_runs (
                account_id,
                scope_json,
                status,
                sellers_found,
                items_found,
                started_at,
                created_at,
                updated_at
            ) VALUES (
                :account_id,
                :scope_json,
                :status,
                0,
                0,
                NOW(),
                NOW(),
                NOW()
            )'
        );

        $stmt->execute([
            'account_id' => $this->accountId,
            'scope_json' => $this->encodeJson($scope),
            'status' => 'running',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markScanCompleted(int $scanRunId, int $sellersFound, int $itemsFound): void
    {
        $stmt = $this->db->prepare(
            'UPDATE awa_scan_runs
                SET status = :status,
                    sellers_found = :sellers_found,
                    items_found = :items_found,
                    finished_at = NOW(),
                    updated_at = NOW(),
                    error_message = NULL
              WHERE id = :scan_id
                AND account_id = :account_id'
        );

        $stmt->execute([
            'status' => 'completed',
            'sellers_found' => max(0, $sellersFound),
            'items_found' => max(0, $itemsFound),
            'scan_id' => $scanRunId,
            'account_id' => $this->accountId,
        ]);
    }

    public function markScanFailed(int $scanRunId, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            'UPDATE awa_scan_runs
                SET status = :status,
                    finished_at = NOW(),
                    updated_at = NOW(),
                    error_message = :error_message
              WHERE id = :scan_id
                AND account_id = :account_id'
        );

        $stmt->execute([
            'status' => 'failed',
            'error_message' => mb_substr($errorMessage, 0, 65535),
            'scan_id' => $scanRunId,
            'account_id' => $this->accountId,
        ]);
    }

    public function upsertSeller(int $scanRunId, array $sellerData): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO awa_seller_registry (
                account_id,
                seller_id,
                nickname,
                permalink,
                city,
                state,
                user_type,
                reputation_level,
                power_seller_status,
                items_count,
                categories_json,
                first_seen_at,
                last_seen_at,
                last_scan_id,
                is_active,
                created_at,
                updated_at
            ) VALUES (
                :account_id,
                :seller_id,
                :nickname,
                :permalink,
                :city,
                :state,
                :user_type,
                :reputation_level,
                :power_seller_status,
                :items_count,
                :categories_json,
                NOW(),
                NOW(),
                :last_scan_id,
                1,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                nickname = VALUES(nickname),
                permalink = VALUES(permalink),
                city = VALUES(city),
                state = VALUES(state),
                user_type = VALUES(user_type),
                reputation_level = VALUES(reputation_level),
                power_seller_status = VALUES(power_seller_status),
                items_count = VALUES(items_count),
                categories_json = VALUES(categories_json),
                last_seen_at = NOW(),
                last_scan_id = VALUES(last_scan_id),
                is_active = 1,
                updated_at = NOW()'
        );

        $stmt->execute([
            'account_id' => $this->accountId,
            'seller_id' => (int) ($sellerData['seller_id'] ?? 0),
            'nickname' => (string) ($sellerData['nickname'] ?? 'Desconhecido'),
            'permalink' => $this->nullableString($sellerData['permalink'] ?? null),
            'city' => $this->nullableString($sellerData['city'] ?? null),
            'state' => $this->nullableString($sellerData['state'] ?? null),
            'user_type' => $this->nullableString($sellerData['user_type'] ?? null),
            'reputation_level' => $this->nullableString($sellerData['reputation_level'] ?? null),
            'power_seller_status' => $this->nullableString($sellerData['power_seller_status'] ?? null),
            'items_count' => max(0, (int) ($sellerData['items_count'] ?? 0)),
            'categories_json' => $this->encodeJson($sellerData['categories'] ?? []),
            'last_scan_id' => $scanRunId,
        ]);

        return $this->findSellerRegistryId((int) ($sellerData['seller_id'] ?? 0));
    }

    public function upsertSellerItem(int $sellerRegistryId, array $itemData): int
    {
        $mlItemId = (string) ($itemData['ml_item_id'] ?? '');
        if ($mlItemId === '') {
            throw new RuntimeException('ml_item_id é obrigatório para persistir item AWA.');
        }

        $price = array_key_exists('price', $itemData) && $itemData['price'] !== null
            ? (float) $itemData['price']
            : null;

        $stmt = $this->db->prepare(
            'INSERT INTO awa_seller_items (
                account_id,
                seller_registry_id,
                ml_item_id,
                title,
                category_id,
                price,
                status,
                brand_match_type,
                has_brand_attribute,
                evidence_json,
                first_seen_at,
                last_seen_at,
                created_at,
                updated_at
            ) VALUES (
                :account_id,
                :seller_registry_id,
                :ml_item_id,
                :title,
                :category_id,
                :price,
                :status,
                :brand_match_type,
                :has_brand_attribute,
                :evidence_json,
                NOW(),
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                seller_registry_id = VALUES(seller_registry_id),
                title = VALUES(title),
                category_id = VALUES(category_id),
                price = VALUES(price),
                status = VALUES(status),
                brand_match_type = VALUES(brand_match_type),
                has_brand_attribute = VALUES(has_brand_attribute),
                evidence_json = VALUES(evidence_json),
                last_seen_at = NOW(),
                updated_at = NOW()'
        );

        $stmt->execute([
            'account_id' => $this->accountId,
            'seller_registry_id' => $sellerRegistryId,
            'ml_item_id' => $mlItemId,
            'title' => (string) ($itemData['title'] ?? ''),
            'category_id' => $this->nullableString($itemData['category_id'] ?? null),
            'price' => $price,
            'status' => $this->nullableString($itemData['status'] ?? null),
            'brand_match_type' => (string) ($itemData['brand_match_type'] ?? 'unclassified'),
            'has_brand_attribute' => ($itemData['has_brand_attribute'] ?? false) ? 1 : 0,
            'evidence_json' => $this->encodeJson($itemData['evidence'] ?? []),
        ]);

        return $this->findSellerItemId($mlItemId);
    }

    public function getScanRun(int $scanRunId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, account_id, scope_json, status, sellers_found, items_found, started_at, finished_at, error_message, created_at, updated_at
               FROM awa_scan_runs
              WHERE id = :scan_id
                AND account_id = :account_id
              LIMIT 1'
        );

        $stmt->execute([
            'scan_id' => $scanRunId,
            'account_id' => $this->accountId,
        ]);

        $scan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($scan)) {
            return null;
        }

        $summaryStmt = $this->db->prepare(
            'SELECT COUNT(*) AS sellers_count, COALESCE(SUM(items_count), 0) AS total_items
               FROM awa_seller_registry
              WHERE account_id = :account_id
                AND last_scan_id = :scan_id'
        );
        $summaryStmt->execute([
            'account_id' => $this->accountId,
            'scan_id' => $scanRunId,
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $topSellersStmt = $this->db->prepare(
            'SELECT seller_id, nickname, permalink, city, state, user_type, reputation_level, power_seller_status, items_count, last_seen_at
               FROM awa_seller_registry
              WHERE account_id = :account_id
                AND last_scan_id = :scan_id
              ORDER BY items_count DESC, seller_id ASC
              LIMIT 20'
        );
        $topSellersStmt->execute([
            'account_id' => $this->accountId,
            'scan_id' => $scanRunId,
        ]);

        $scan['scope'] = $this->decodeJson($scan['scope_json'] ?? null);
        unset($scan['scope_json']);

        return [
            'scan' => $scan,
            'summary' => [
                'sellers_count' => (int) ($summary['sellers_count'] ?? 0),
                'items_count' => (int) ($summary['total_items'] ?? 0),
            ],
            'top_sellers' => $topSellersStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getMetrics(): array
    {
        $totalsStmt = $this->db->prepare(
            'SELECT
                COUNT(*) AS total_sellers,
                COALESCE(SUM(items_count), 0) AS total_items,
                COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_sellers,
                MAX(last_seen_at) AS last_seen_at
               FROM awa_seller_registry
              WHERE account_id = :account_id'
        );
        $totalsStmt->execute(['account_id' => $this->accountId]);
        $totals = $totalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $brandMatchStmt = $this->db->prepare(
            'SELECT brand_match_type, COUNT(*) AS total
               FROM awa_seller_items
              WHERE account_id = :account_id
              GROUP BY brand_match_type'
        );
        $brandMatchStmt->execute(['account_id' => $this->accountId]);

        $matchTypes = [
            'attribute_match' => 0,
            'attribute_mismatch' => 0,
            'title_match_only' => 0,
            'unclassified' => 0,
        ];

        foreach ($brandMatchStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = (string) ($row['brand_match_type'] ?? 'unclassified');
            $matchTypes[$type] = (int) ($row['total'] ?? 0);
        }

        $attributeStmt = $this->db->prepare(
            'SELECT COALESCE(SUM(has_brand_attribute), 0) AS items_with_brand_attribute
               FROM awa_seller_items
              WHERE account_id = :account_id'
        );
        $attributeStmt->execute(['account_id' => $this->accountId]);
        $attributeStats = $attributeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $lastScanStmt = $this->db->prepare(
            'SELECT id, status, sellers_found, items_found, started_at, finished_at, error_message
               FROM awa_scan_runs
              WHERE account_id = :account_id
              ORDER BY id DESC
              LIMIT 1'
        );
        $lastScanStmt->execute(['account_id' => $this->accountId]);
        $lastScan = $lastScanStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'account_id' => $this->accountId,
            'total_sellers' => (int) ($totals['total_sellers'] ?? 0),
            'active_sellers' => (int) ($totals['active_sellers'] ?? 0),
            'total_items' => (int) ($totals['total_items'] ?? 0),
            'items_with_brand_attribute' => (int) ($attributeStats['items_with_brand_attribute'] ?? 0),
            'last_seen_at' => $totals['last_seen_at'] ?? null,
            'match_types' => $matchTypes,
            'last_scan' => is_array($lastScan) ? $lastScan : null,
        ];
    }

    // =========================================================================
    // FASE 2 — QUERY METHODS
    // =========================================================================

    /**
     * Retorna um seller pelo ID interno (escopado para a conta).
     *
     * @return array<string, mixed>|null
     */
    public function getSellerById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*,
                    i.cnpj, i.razao_social, i.source_type, i.source_reference,
                    i.confidence_score, i.verification_status AS id_status,
                    i.verified_at, i.created_by, i.notes AS id_notes
               FROM awa_seller_registry r
          LEFT JOIN awa_seller_identification i ON i.seller_registry_id = r.id
              WHERE r.id = :id AND r.account_id = :account_id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'account_id' => $this->accountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $row['categories'] = $this->decodeJson($row['categories_json'] ?? null);
        unset($row['categories_json']);

        return $row;
    }

    /**
     * Lista sellers com filtros e paginação.
     *
     * Filtros suportados: search (nickname), state, reputation_level, id_status, is_active
     *
     * @param  array<string, mixed>           $filters
     * @return array<int, array<string, mixed>>
     */
    public function listSellers(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        [$where, $binds] = $this->buildSellerFilters($filters);

        $stmt = $this->db->prepare(
            "SELECT r.id, r.seller_id, r.nickname, r.permalink, r.city, r.state,
                    r.user_type, r.reputation_level, r.power_seller_status,
                    r.items_count, r.is_active, r.first_seen_at, r.last_seen_at,
                    i.cnpj, i.razao_social, i.verification_status AS id_status
               FROM awa_seller_registry r
          LEFT JOIN awa_seller_identification i ON i.seller_registry_id = r.id
               {$where}
               ORDER BY r.items_count DESC, r.last_seen_at DESC
               LIMIT :limit OFFSET :offset"
        );

        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Conta sellers com os mesmos filtros de listSellers().
     *
     * @param array<string, mixed> $filters
     */
    public function countSellers(array $filters = []): int
    {
        [$where, $binds] = $this->buildSellerFilters($filters);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
               FROM awa_seller_registry r
          LEFT JOIN awa_seller_identification i ON i.seller_registry_id = r.id
               {$where}"
        );

        foreach ($binds as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista itens de um seller específico com paginação.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSellerItems(int $registryId, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(200, $perPage));
        $offset  = max(0, ($page - 1) * $perPage);

        $stmt = $this->db->prepare(
            'SELECT id, ml_item_id, title, category_id, price, status,
                    brand_match_type, has_brand_attribute, evidence_json,
                    first_seen_at, last_seen_at
               FROM awa_seller_items
              WHERE seller_registry_id = :reg_id AND account_id = :account_id
              ORDER BY last_seen_at DESC
              LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':reg_id',      $registryId,      PDO::PARAM_INT);
        $stmt->bindValue(':account_id',  $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue(':limit',       $perPage,         PDO::PARAM_INT);
        $stmt->bindValue(':offset',      $offset,          PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function (array $row): array {
            $row['evidence'] = $this->decodeJson($row['evidence_json'] ?? null);
            unset($row['evidence_json']);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Conta itens de um seller específico.
     */
    public function countSellerItems(int $registryId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM awa_seller_items
              WHERE seller_registry_id = :reg_id AND account_id = :account_id'
        );
        $stmt->execute(['reg_id' => $registryId, 'account_id' => $this->accountId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna valores distintos para filtros da UI.
     *
     * @return array<string, array<string>>
     */
    public function getFilterOptions(): array
    {
        $states = $this->db->prepare(
            'SELECT DISTINCT state FROM awa_seller_registry
              WHERE account_id = :account_id AND state IS NOT NULL
              ORDER BY state'
        );
        $states->execute(['account_id' => $this->accountId]);

        $cities = $this->db->prepare(
            'SELECT DISTINCT city FROM awa_seller_registry
              WHERE account_id = :account_id AND city IS NOT NULL
              ORDER BY city'
        );
        $cities->execute(['account_id' => $this->accountId]);

        $reps = $this->db->prepare(
            'SELECT DISTINCT reputation_level FROM awa_seller_registry
              WHERE account_id = :account_id AND reputation_level IS NOT NULL
              ORDER BY reputation_level'
        );
        $reps->execute(['account_id' => $this->accountId]);

                $categories = $this->db->prepare(
                        'SELECT DISTINCT category_id FROM awa_seller_items
                            WHERE account_id = :account_id AND category_id IS NOT NULL
                            ORDER BY category_id'
                );
                $categories->execute(['account_id' => $this->accountId]);

        $matchTypes = $this->db->prepare(
            'SELECT DISTINCT brand_match_type FROM awa_seller_items
              WHERE account_id = :account_id
              ORDER BY brand_match_type'
        );
        $matchTypes->execute(['account_id' => $this->accountId]);

        return [
            'states'            => array_column($states->fetchAll(PDO::FETCH_ASSOC), 'state'),
            'cities'            => array_column($cities->fetchAll(PDO::FETCH_ASSOC), 'city'),
            'categories'        => array_column($categories->fetchAll(PDO::FETCH_ASSOC), 'category_id'),
            'reputation_levels' => array_column($reps->fetchAll(PDO::FETCH_ASSOC), 'reputation_level'),
            'brand_match_types' => array_column($matchTypes->fetchAll(PDO::FETCH_ASSOC), 'brand_match_type'),
            'id_statuses'       => ['verified', 'pending', 'not_available', 'conflict'],
        ];
    }

    /**
     * Retorna todos os sellers ativos em formato CSV para export.
     * Retorna gerador para evitar OOM em listas grandes.
     *
     * @return iterable<array<string, mixed>>
     */
    public function iterateSellersForExport(array $filters = []): iterable
    {
        [$where, $binds] = $this->buildSellerFilters($filters);

        $stmt = $this->db->prepare(
            "SELECT r.seller_id, r.nickname, r.permalink, r.city, r.state,
                    r.reputation_level, r.power_seller_status, r.items_count,
                    r.first_seen_at, r.last_seen_at,
                    i.cnpj, i.razao_social, i.verification_status AS id_status
               FROM awa_seller_registry r
          LEFT JOIN awa_seller_identification i ON i.seller_registry_id = r.id
               {$where}
              ORDER BY r.items_count DESC, r.last_seen_at DESC"
        );

        foreach ($binds as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }

    /**
     * Insere ou atualiza identificação jurídica de um seller.
     *
     * @param array<string, mixed> $data
     */
    public function upsertIdentification(int $registryId, array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO awa_seller_identification
                (seller_registry_id, cnpj, razao_social, source_type, source_reference,
                 confidence_score, verification_status, notes, created_by)
             VALUES
                (:reg_id, :cnpj, :razao_social, :source_type, :source_reference,
                 :confidence_score, :verification_status, :notes, :created_by)
             ON DUPLICATE KEY UPDATE
                cnpj                = VALUES(cnpj),
                razao_social        = VALUES(razao_social),
                source_type         = VALUES(source_type),
                source_reference    = VALUES(source_reference),
                confidence_score    = VALUES(confidence_score),
                verification_status = VALUES(verification_status),
                notes               = VALUES(notes),
                verified_at         = IF(VALUES(verification_status) = \'verified\', NOW(), verified_at)'
        );

        $stmt->execute([
            'reg_id'              => $registryId,
            'cnpj'                => $data['cnpj'] ?? null,
            'razao_social'        => $data['razao_social'] ?? null,
            'source_type'         => $data['source_type'] ?? 'manual',
            'source_reference'    => $data['source_reference'] ?? null,
            'confidence_score'    => max(0, min(100, (int) ($data['confidence_score'] ?? 50))),
            'verification_status' => $data['verification_status'] ?? 'pending',
            'notes'               => $data['notes'] ?? null,
            'created_by'          => $data['created_by'] ?? null,
        ]);
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Constrói cláusula WHERE + binds para filtros de seller.
     *
     * @param  array<string, mixed>                         $filters
     * @return array{0: string, 1: array<string, scalar>}
     */
    private function buildSellerFilters(array $filters): array
    {
        $conditions = ['r.account_id = :account_id'];
        $binds      = [':account_id' => $this->accountId];

        // is_active — por padrão somente ativos
        if (array_key_exists('is_active', $filters)) {
            $conditions[]          = 'r.is_active = :is_active';
            $binds[':is_active']   = (int) (bool) $filters['is_active'];
        } else {
            $conditions[] = 'r.is_active = 1';
        }

        if (!empty($filters['state'])) {
            $conditions[]   = 'r.state = :state';
            $binds[':state'] = (string) $filters['state'];
        }

        if (!empty($filters['city'])) {
            $conditions[]  = 'r.city = :city';
            $binds[':city'] = (string) $filters['city'];
        }

        if (!empty($filters['category_id'])) {
            $conditions[] = 'EXISTS (
                SELECT 1
                  FROM awa_seller_items si_filter
                 WHERE si_filter.account_id = r.account_id
                   AND si_filter.seller_registry_id = r.id
                   AND si_filter.category_id = :category_id
            )';
            $binds[':category_id'] = (string) $filters['category_id'];
        }

        if (!empty($filters['reputation_level'])) {
            $conditions[]              = 'r.reputation_level = :reputation_level';
            $binds[':reputation_level'] = (string) $filters['reputation_level'];
        }

        if (!empty($filters['id_status'])) {
            $conditions[]        = 'i.verification_status = :id_status';
            $binds[':id_status'] = (string) $filters['id_status'];
        }

        if (!empty($filters['min_items'])) {
            $conditions[]        = 'r.items_count >= :min_items';
            $binds[':min_items'] = max(1, (int) $filters['min_items']);
        }

        if (!empty($filters['search'])) {
            $escapedSearch = '%' . str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                (string) $filters['search']
            ) . '%';

            $conditions[]          = '(r.nickname LIKE :search_nick OR r.city LIKE :search_city OR CAST(r.seller_id AS CHAR) LIKE :search_id)';
            $binds[':search_nick'] = $escapedSearch;
            $binds[':search_city'] = $escapedSearch;
            $binds[':search_id']   = $escapedSearch;
        }

        return ['WHERE ' . implode(' AND ', $conditions), $binds];
    }

    private function findSellerRegistryId(int $sellerId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id
               FROM awa_seller_registry
              WHERE account_id = :account_id
                AND seller_id = :seller_id
              LIMIT 1'
        );
        $stmt->execute([
            'account_id' => $this->accountId,
            'seller_id' => $sellerId,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Seller persistido, mas não localizado no registry AWA.');
        }

        return (int) $id;
    }

    private function findSellerItemId(string $mlItemId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id
               FROM awa_seller_items
              WHERE account_id = :account_id
                AND ml_item_id = :ml_item_id
              LIMIT 1'
        );
        $stmt->execute([
            'account_id' => $this->accountId,
            'ml_item_id' => $mlItemId,
        ]);

        $id = $stmt->fetchColumn();
        if ($id === false) {
            throw new RuntimeException('Item persistido, mas não localizado no registry AWA.');
        }

        return (int) $id;
    }

    private function encodeJson(array $payload): ?string
    {
        if ($payload === []) {
            return null;
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Falha ao serializar payload JSON do módulo AWA Sellers.');
        }

        return $json;
    }

    /**
     * @return array<int|string, mixed>
     */
    // -----------------------------------------------------------------------
    // History & Alert helpers
    // -----------------------------------------------------------------------

    /**
     * Returns a lightweight snapshot of seller item counts keyed by seller_id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSellerItemCountSnapshot(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id AS registry_id, seller_id, nickname, items_count
               FROM awa_seller_registry
              WHERE account_id = :aid
                AND is_active = 1'
        );
        $stmt->execute(['aid' => $this->accountId]);

        $snapshot = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $sellerId = (int) ($row['seller_id'] ?? 0);
            if ($sellerId <= 0) {
                continue;
            }

            $snapshot[$sellerId] = [
                'registry_id' => (int) ($row['registry_id'] ?? 0),
                'nickname' => (string) ($row['nickname'] ?? 'Desconhecido'),
                'items_count' => max(0, (int) ($row['items_count'] ?? 0)),
            ];
        }

        return $snapshot;
    }

    /**
     * Returns all ml_seller_ids currently registered for this account.
     *
     * @return int[]
     */
    public function getKnownSellerIds(): array
    {
        $stmt = $this->db->prepare(
            'SELECT seller_id
               FROM awa_seller_registry
              WHERE account_id = :aid
                AND seller_id IS NOT NULL'
        );
        $stmt->execute(['aid' => $this->accountId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Returns the most-recent scan runs for this account.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listScanRuns(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $stmt  = $this->db->prepare(
            "SELECT id, status, sellers_found, items_found, scope_json,
                    started_at, finished_at, error_message, created_at, updated_at
               FROM awa_scan_runs
              WHERE account_id = :aid
              ORDER BY id DESC
              LIMIT {$limit}"
        );
        $stmt->execute(['aid' => $this->accountId]);

        return array_map(function (array $row): array {
            $row['scope'] = $this->decodeJson($row['scope_json'] ?? null);
            unset($row['scope_json']);

            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Returns new sellers detected during the last N days (first seen in that window).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNewSellersHistory(int $days = 7): array
    {
        $days = max(1, min(90, $days));
        $stmt = $this->db->prepare(
                        sprintf(
                                'SELECT r.id, r.seller_id, r.nickname, r.permalink, r.city, r.state,
                                                r.reputation_level, r.items_count, r.first_seen_at
                                     FROM awa_seller_registry r
                                    WHERE r.account_id = :aid
                                        AND r.first_seen_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                                    ORDER BY r.first_seen_at DESC',
                                $days
                        )
        );
                $stmt->execute(['aid' => $this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function decodeJson(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function nullableString(null|string|int|float $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        return $stringValue === '' ? null : $stringValue;
    }
}
