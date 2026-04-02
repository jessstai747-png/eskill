<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * AwaSellerRegistryService
 *
 * Gerencia a persistência dos sellers AWA detectados no Mercado Livre.
 * Fornece CRUD sobre as tabelas: awa_scan_runs, awa_seller_registry,
 * awa_seller_items e awa_seller_identification.
 *
 * Todas as operações de escrita e de consulta filtram por account_id.
 */
class AwaSellerRegistryService
{
    private PDO $db;
    private ?int $accountId;

    public function __construct(?int $accountId = null)
    {
        $this->db        = Database::getInstance();
        $this->accountId = $accountId;
    }

    public function setAccountId(int $accountId): void
    {
        $this->accountId = $accountId;
    }

    private function requireAccountId(): int
    {
        if ($this->accountId === null || $this->accountId <= 0) {
            throw new \RuntimeException('AwaSellerRegistryService: account_id não definido');
        }

        return $this->accountId;
    }

    // -------------------------------------------------------------------------
    // SCAN RUNS
    // -------------------------------------------------------------------------

    /**
     * Cria um novo registro de execução de varredura.
     *
     * @param array<string, mixed> $scope
     * @return int ID do scan_run criado
     */
    public function createScanRun(array $scope = []): int
    {
        $accountId = $this->requireAccountId();

        $stmt = $this->db->prepare("
            INSERT INTO awa_scan_runs (account_id, scope_json, status, sellers_found, items_found)
            VALUES (:account_id, :scope_json, 'running', 0, 0)
        ");
        $stmt->execute([
            'account_id' => $accountId,
            'scope_json' => json_encode($scope),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza o status e contadores de um scan_run.
     */
    public function updateScanRun(
        int $scanId,
        string $status,
        int $sellersFound = 0,
        int $itemsFound = 0,
        ?string $error = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE awa_scan_runs
               SET status         = :status,
                   sellers_found  = :sellers_found,
                   items_found    = :items_found,
                   finished_at    = IF(:status2 IN ('completed','failed'), NOW(), NULL),
                   error_message  = :error_message
             WHERE id = :id
        ");
        $stmt->execute([
            'status'        => $status,
            'status2'       => $status,
            'sellers_found' => $sellersFound,
            'items_found'   => $itemsFound,
            'error_message' => $error,
            'id'            => $scanId,
        ]);
    }

    /**
     * Retorna o scan_run mais recente com status 'completed' para a conta.
     *
     * @return array<string, mixed>|null
     */
    public function getLastCompletedScanRun(): ?array
    {
        $accountId = $this->requireAccountId();

        $stmt = $this->db->prepare("
            SELECT * FROM awa_scan_runs
             WHERE account_id = :account_id AND status = 'completed'
             ORDER BY finished_at DESC
             LIMIT 1
        ");
        $stmt->execute(['account_id' => $accountId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Lista os scan_runs mais recentes da conta.
     *
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function listScanRuns(int $limit = 20): array
    {
        $accountId = $this->requireAccountId();
        $limit     = max(1, min(100, $limit));

        $stmt = $this->db->prepare("
            SELECT * FROM awa_scan_runs
             WHERE account_id = :account_id
             ORDER BY started_at DESC
             LIMIT :limit
        ");
        $stmt->bindValue(':account_id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // -------------------------------------------------------------------------
    // SELLER REGISTRY
    // -------------------------------------------------------------------------

    /**
     * Insere ou atualiza um seller no registry (UPSERT por account_id + seller_id).
     *
     * @param array<string, mixed> $sellerData  Dados do seller
     * @param int                  $scanId      ID do scan_run gerador
     * @return int                              ID do registro em awa_seller_registry
     */
    public function upsertSeller(array $sellerData, int $scanId): int
    {
        $accountId  = $this->requireAccountId();
        $sellerId   = (int) ($sellerData['id'] ?? 0);
        $nickname   = (string) ($sellerData['nickname'] ?? '');
        $permalink  = (string) ($sellerData['permalink'] ?? '');
        $city       = (string) ($sellerData['address']['city'] ?? '');
        $state      = (string) ($sellerData['address']['state'] ?? '');
        $userType   = (string) ($sellerData['user_type'] ?? '');
        $repLevel   = (string) ($sellerData['seller_reputation']['level_id'] ?? '');
        $psStatus   = (string) ($sellerData['seller_reputation']['power_seller_status'] ?? '');
        $itemsCount = (int) ($sellerData['items_count'] ?? 0);

        $categories = $sellerData['categories'] ?? null;
        $categoriesJson = $categories !== null ? json_encode($categories) : null;

        $stmt = $this->db->prepare("
            INSERT INTO awa_seller_registry
                (account_id, seller_id, nickname, permalink, city, state, user_type,
                 reputation_level, power_seller_status, items_count,
                 categories_json, first_seen_at, last_seen_at, last_scan_id, is_active)
            VALUES
                (:account_id, :seller_id, :nickname, :permalink, :city, :state, :user_type,
                 :reputation_level, :power_seller_status, :items_count,
                 :categories_json, NOW(), NOW(), :last_scan_id, 1)
            ON DUPLICATE KEY UPDATE
                nickname            = VALUES(nickname),
                permalink           = VALUES(permalink),
                city                = VALUES(city),
                state               = VALUES(state),
                user_type           = VALUES(user_type),
                reputation_level    = VALUES(reputation_level),
                power_seller_status = VALUES(power_seller_status),
                items_count         = VALUES(items_count),
                categories_json     = COALESCE(VALUES(categories_json), categories_json),
                last_seen_at        = NOW(),
                last_scan_id        = VALUES(last_scan_id),
                is_active           = 1
        ");

        $stmt->execute([
            'account_id'          => $accountId,
            'seller_id'           => $sellerId,
            'nickname'            => $nickname,
            'permalink'           => $permalink ?: null,
            'city'                => $city ?: null,
            'state'               => $state ?: null,
            'user_type'           => $userType ?: null,
            'reputation_level'    => $repLevel ?: null,
            'power_seller_status' => $psStatus ?: null,
            'items_count'         => $itemsCount,
            'categories_json'     => $categoriesJson,
            'last_scan_id'        => $scanId,
        ]);

        $lastId = (int) $this->db->lastInsertId();
        if ($lastId > 0) {
            return $lastId;
        }

        // ON DUPLICATE KEY UPDATE — busca pelo par (account_id, seller_id)
        $find = $this->db->prepare("
            SELECT id FROM awa_seller_registry
             WHERE account_id = :account_id AND seller_id = :seller_id
        ");
        $find->execute(['account_id' => $accountId, 'seller_id' => $sellerId]);
        $row = $find->fetch();

        return $row !== false ? (int) $row['id'] : 0;
    }

    /**
     * Insere ou atualiza um item anunciado por um seller (UPSERT por account_id + ml_item_id).
     *
     * @param int                  $registryId  ID em awa_seller_registry
     * @param array<string, mixed> $itemData    Dados do item
     */
    public function upsertItem(int $registryId, array $itemData): void
    {
        $accountId = $this->requireAccountId();
        $mlItemId  = (string) ($itemData['id'] ?? '');
        $title     = (string) ($itemData['title'] ?? '');
        $category  = (string) ($itemData['category_id'] ?? '');
        $price     = isset($itemData['price']) ? (float) $itemData['price'] : null;
        $status    = (string) ($itemData['status'] ?? '');

        $brandAnalysis = $itemData['brand_analysis'] ?? [];
        $hasBrand      = (bool) ($brandAnalysis['has_brand'] ?? false);
        $isCorrect     = (bool) ($brandAnalysis['is_correct'] ?? false);

        $hasTitleMatch = mb_stripos($title, 'AWA') !== false;
        $matchType = 'none';
        if ($hasBrand && $isCorrect && $hasTitleMatch) {
            $matchType = 'both';
        } elseif ($hasBrand && $isCorrect) {
            $matchType = 'attribute';
        } elseif ($hasTitleMatch) {
            $matchType = 'title';
        }

        $evidenceJson = json_encode([
            'brand_analysis' => $brandAnalysis,
            'condition'      => $itemData['condition'] ?? null,
            'permalink'      => $itemData['permalink'] ?? null,
            'thumbnail'      => $itemData['thumbnail'] ?? null,
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO awa_seller_items
                (account_id, seller_registry_id, ml_item_id, title, category_id, price, status,
                 brand_match_type, has_brand_attribute, evidence_json,
                 first_seen_at, last_seen_at)
            VALUES
                (:account_id, :reg_id, :ml_item_id, :title, :category_id, :price, :status,
                 :brand_match_type, :has_brand_attribute, :evidence_json,
                 NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                seller_registry_id  = VALUES(seller_registry_id),
                title               = VALUES(title),
                category_id         = VALUES(category_id),
                price               = VALUES(price),
                status              = VALUES(status),
                brand_match_type    = VALUES(brand_match_type),
                has_brand_attribute = VALUES(has_brand_attribute),
                evidence_json       = VALUES(evidence_json),
                last_seen_at        = NOW()
        ");

        $stmt->execute([
            'account_id'          => $accountId,
            'reg_id'              => $registryId,
            'ml_item_id'          => $mlItemId,
            'title'               => $title,
            'category_id'         => $category ?: null,
            'price'               => $price,
            'status'              => $status ?: null,
            'brand_match_type'    => $matchType,
            'has_brand_attribute' => (int) $hasBrand,
            'evidence_json'       => $evidenceJson,
        ]);
    }

    // -------------------------------------------------------------------------
    // CONSULTAS
    // -------------------------------------------------------------------------

    /**
     * Busca um seller pelo ML user id, filtrado por account_id.
     *
     * @return array<string, mixed>|null
     */
    public function getSellerByMlId(int $sellerId): ?array
    {
        $accountId = $this->requireAccountId();

        $stmt = $this->db->prepare("
            SELECT r.*, s.started_at AS last_scan_started_at
              FROM awa_seller_registry r
         LEFT JOIN awa_scan_runs s ON s.id = r.last_scan_id
             WHERE r.account_id = :account_id AND r.seller_id = :seller_id
        ");
        $stmt->execute(['account_id' => $accountId, 'seller_id' => $sellerId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Busca um seller pelo ID interno, filtrado por account_id.
     *
     * @return array<string, mixed>|null
     */
    public function getSellerById(int $id): ?array
    {
        $accountId = $this->requireAccountId();

        $stmt = $this->db->prepare("
            SELECT r.*, s.started_at AS last_scan_started_at
              FROM awa_seller_registry r
         LEFT JOIN awa_scan_runs s ON s.id = r.last_scan_id
             WHERE r.id = :id AND r.account_id = :account_id
        ");
        $stmt->execute(['id' => $id, 'account_id' => $accountId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Lista sellers com filtros opcionais e paginação.
     *
     * @param array<string, mixed> $filters  state, reputation_level, search, is_active
     * @param int                  $page     1-based
     * @param int                  $perPage
     * @return array<int, array<string, mixed>>
     */
    public function listSellers(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $binds] = $this->buildSellerFilters($filters);

        $sql = "
            SELECT r.*, s.started_at AS last_scan_started_at
              FROM awa_seller_registry r
         LEFT JOIN awa_scan_runs s ON s.id = r.last_scan_id
             {$where}
             ORDER BY r.items_count DESC, r.last_seen_at DESC
             LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($binds as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Conta sellers com filtros opcionais.
     *
     * @param array<string, mixed> $filters
     */
    public function countSellers(array $filters = []): int
    {
        [$where, $binds] = $this->buildSellerFilters($filters);

        $sql  = "SELECT COUNT(*) FROM awa_seller_registry r {$where}";
        $stmt = $this->db->prepare($sql);
        foreach ($binds as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Lista itens de um seller com paginação.
     *
     * @param int $registryId  ID interno em awa_seller_registry
     * @param int $page        1-based
     * @param int $perPage
     * @return array<int, array<string, mixed>>
     */
    public function getSellerItems(int $registryId, int $page = 1, int $perPage = 50): array
    {
        $perPage = max(1, min(200, $perPage));
        $offset  = ($page - 1) * $perPage;

        $stmt = $this->db->prepare("
            SELECT * FROM awa_seller_items
             WHERE seller_registry_id = :reg_id
             ORDER BY last_seen_at DESC
             LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':reg_id', $registryId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Conta itens de um seller.
     */
    public function countSellerItems(int $registryId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM awa_seller_items WHERE seller_registry_id = :reg_id
        ");
        $stmt->execute(['reg_id' => $registryId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Retorna os dados de identificação jurídica de um seller.
     *
     * @return array<string, mixed>|null
     */
    public function getSellerIdentification(int $registryId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM awa_seller_identification
             WHERE seller_registry_id = :reg_id
             ORDER BY confidence_score DESC
             LIMIT 1
        ");
        $stmt->execute(['reg_id' => $registryId]);
        $row = $stmt->fetch();

        return $row !== false ? $row : null;
    }

    /**
     * Upsert de dados de identificação jurídica.
     *
     * @param int                  $registryId
     * @param array<string, mixed> $data  cnpj, razao_social, source_type, confidence_score, notes
     */
    public function upsertIdentification(int $registryId, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO awa_seller_identification
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
                verified_at         = IF(VALUES(verification_status) = 'verified', NOW(), verified_at)
        ");

        $stmt->execute([
            'reg_id'              => $registryId,
            'cnpj'                => $data['cnpj'] ?? null,
            'razao_social'        => $data['razao_social'] ?? null,
            'source_type'         => $data['source_type'] ?? 'manual',
            'source_reference'    => $data['source_reference'] ?? null,
            'confidence_score'    => (int) ($data['confidence_score'] ?? 50),
            'verification_status' => $data['verification_status'] ?? 'pending',
            'notes'               => $data['notes'] ?? null,
            'created_by'          => $data['created_by'] ?? null,
        ]);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVADOS
    // -------------------------------------------------------------------------

    /**
     * Constrói cláusula WHERE e binds para filtros de seller (sempre escopado por account_id).
     *
     * @param  array<string, mixed>   $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildSellerFilters(array $filters): array
    {
        $accountId  = $this->requireAccountId();
        $conditions = ['r.account_id = :account_id'];
        $binds      = [':account_id' => $accountId];

        if (isset($filters['is_active'])) {
            $conditions[] = 'r.is_active = :is_active';
            $binds[':is_active'] = (int) (bool) $filters['is_active'];
        } else {
            $conditions[] = 'r.is_active = 1';
        }

        if (!empty($filters['state'])) {
            $conditions[] = 'r.state = :state';
            $binds[':state'] = (string) $filters['state'];
        }

        if (!empty($filters['reputation_level'])) {
            $conditions[] = 'r.reputation_level = :reputation_level';
            $binds[':reputation_level'] = (string) $filters['reputation_level'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = 'r.nickname LIKE :search';
            $binds[':search'] = '%' . str_replace(
                ['\\', '%', '_'],
                ['\\\\', '\\%', '\\_'],
                $filters['search']
            ) . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        return [$where, $binds];
    }
}
