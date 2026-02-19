<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de detecção de duplicatas em clones de catálogo.
 *
 * Verifica se itens já foram clonados anteriormente, gerencia registros
 * de clones e fornece estatísticas de duplicação.
 */
class CloneDuplicateDetectionService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Verifica se um item já possui clones registrados.
     *
     * @param string $itemId   ID do item no Mercado Livre
     * @param int    $accountId ID da conta
     * @return array{is_duplicate: bool, existing_items: array, recommendation: string, severity?: string, options?: array}
     */
    public function checkDuplicate(string $itemId, int $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT target_item_id, created_at, status
            FROM catalog_clone_jobs
            WHERE source_item_id = :item_id
              AND account_id = :account_id
              AND status != 'inactive'
            ORDER BY created_at DESC
        ");
        $stmt->execute(['item_id' => $itemId, 'account_id' => $accountId]);
        $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($existing)) {
            return [
                'is_duplicate' => false,
                'existing_items' => [],
                'recommendation' => 'proceed',
            ];
        }

        $severity = count($existing) >= 3 ? 'high' : 'low';
        $result = [
            'is_duplicate' => true,
            'existing_items' => $existing,
            'clone_count' => count($existing),
            'recommendation' => $severity === 'high' ? 'review' : 'proceed_with_caution',
            'severity' => $severity,
        ];

        if ($severity === 'high') {
            $result['options'] = [
                'skip' => 'Pular clonagem deste item',
                'update' => 'Atualizar clone existente',
                'create_new' => 'Criar novo clone mesmo assim',
            ];
        }

        return $result;
    }

    /**
     * Verifica duplicatas para múltiplos itens em lote.
     *
     * @param array $itemIds   Lista de IDs de itens
     * @param int   $accountId ID da conta
     * @return array Mapa itemId => resultado de duplicata
     */
    public function batchCheckDuplicates(array $itemIds, int $accountId): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $results = [];
        foreach ($itemIds as $itemId) {
            $results[$itemId] = $this->checkDuplicate($itemId, $accountId);
        }

        return $results;
    }

    /**
     * Resolve uma situação de duplicata aplicando a ação escolhida.
     *
     * @param string $itemId    ID do item
     * @param int    $accountId ID da conta
     * @param string $action    Ação: 'skip', 'update' ou 'create_new'
     * @param array  $options   Opções adicionais para create_new
     * @return array Resultado da resolução
     */
    public function resolveDuplicate(
        string $itemId,
        int $accountId,
        string $action,
        array $options = []
    ): array {
        return match ($action) {
            'skip' => [
                'status' => 'skipped',
                'reason' => 'Item pulado pelo usuário devido a duplicata',
                'item_id' => $itemId,
            ],
            'update' => [
                'status' => 'update_required',
                'action' => 'update_existing_clone',
                'item_id' => $itemId,
            ],
            'create_new' => [
                'status' => 'proceed',
                'action' => 'create_new_clone',
                'item_id' => $itemId,
                'modifications' => [
                    'title_suffix' => $options['title_suffix'] ?? '',
                    'sku_suffix' => $options['sku_suffix'] ?? '',
                ],
            ],
            default => [
                'status' => 'error',
                'reason' => "Ação desconhecida: {$action}",
            ],
        };
    }

    /**
     * Verifica se um SKU já existe para a conta.
     *
     * @param string $sku       SKU a verificar
     * @param int    $accountId ID da conta
     * @return array Resultado com is_duplicate e opções
     */
    public function checkSkuDuplicate(string $sku, int $accountId): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as cnt
            FROM catalog_clone_jobs
            WHERE JSON_EXTRACT(options, '$.sku') = :sku
              AND account_id = :account_id
              AND status != 'inactive'
        ");

        try {
            $stmt->execute(['sku' => $sku, 'account_id' => $accountId]);
            $count = (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            $count = 0;
        }

        $isDuplicate = $count > 0;

        $result = [
            'is_duplicate' => $isDuplicate,
            'recommendation' => $isDuplicate ? 'modify_sku' : 'proceed',
        ];

        if ($isDuplicate) {
            $result['options'] = [
                'skip' => 'Pular item com SKU duplicado',
                'modify_sku' => 'Adicionar sufixo ao SKU',
            ];
        }

        return $result;
    }

    /**
     * Retorna estatísticas de duplicatas para uma conta.
     *
     * @param int $accountId ID da conta
     * @param int $days      Período em dias
     * @return array Estatísticas
     */
    public function getDuplicateStats(int $accountId, int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(DISTINCT source_item_id) as total_source_items,
                COUNT(*) as total_clones
            FROM catalog_clone_jobs
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['account_id' => $accountId, 'days' => $days]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Contar duplicatas (itens com mais de 1 clone)
        $stmt2 = $this->db->prepare("
            SELECT COUNT(*) as duplicate_clones
            FROM (
                SELECT source_item_id
                FROM catalog_clone_jobs
                WHERE account_id = :account_id
                  AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                GROUP BY source_item_id
                HAVING COUNT(*) > 1
            ) dup
        ");
        $stmt2->execute(['account_id' => $accountId, 'days' => $days]);
        $duplicateCount = (int) $stmt2->fetchColumn();

        // Top duplicatas
        $stmt3 = $this->db->prepare("
            SELECT source_item_id, COUNT(*) as clone_count
            FROM catalog_clone_jobs
            WHERE account_id = :account_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY source_item_id
            HAVING COUNT(*) > 1
            ORDER BY clone_count DESC
            LIMIT 10
        ");
        $stmt3->execute(['account_id' => $accountId, 'days' => $days]);
        $topDuplicates = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        return [
            'summary' => [
                'total_source_items' => (int) ($summary['total_source_items'] ?? 0),
                'total_clones' => (int) ($summary['total_clones'] ?? 0),
                'duplicate_clones' => $duplicateCount,
            ],
            'top_duplicates' => $topDuplicates,
            'period_days' => $days,
        ];
    }

    /**
     * Registra um novo clone no sistema.
     *
     * @param string      $sourceId  Item de origem
     * @param string      $targetId  Item clone criado
     * @param int         $accountId ID da conta
     * @param string|null $jobId     ID do job de clonagem
     * @return bool
     */
    public function registerClone(
        string $sourceId,
        string $targetId,
        int $accountId,
        ?string $jobId = null
    ): bool {
        $stmt = $this->db->prepare("
            INSERT INTO catalog_clone_jobs
            (source_item_id, target_item_id, account_id, job_id, status, created_at)
            VALUES (:source, :target, :account_id, :job_id, 'active', NOW())
        ");

        return $stmt->execute([
            'source' => $sourceId,
            'target' => $targetId,
            'account_id' => $accountId,
            'job_id' => $jobId,
        ]);
    }

    /**
     * Marca um clone como inativo.
     *
     * @param string $targetId ID do clone
     * @return bool
     */
    public function markCloneInactive(string $targetId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE catalog_clone_jobs
            SET status = 'inactive', updated_at = NOW()
            WHERE target_item_id = :target_id
        ");

        return $stmt->execute(['target_id' => $targetId]);
    }

    /**
     * Remove registros inativos mais antigos que X dias.
     *
     * @param int $days Número de dias
     * @return int Número de registros removidos
     */
    public function cleanupOldInactiveClones(int $days = 90): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM catalog_clone_jobs
            WHERE status = 'inactive'
              AND updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $days]);

        return $stmt->rowCount();
    }
}
