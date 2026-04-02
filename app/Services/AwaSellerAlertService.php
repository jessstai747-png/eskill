<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use App\Database;
use RuntimeException;

/**
 * Detects and fires AWA Sellers alerts:
 *  - awa_new_seller        : new sellers found in a scan run
 *  - awa_volume_spike      : seller whose item count grew suddenly
 *  - awa_unidentified_seller: sellers with no identification after N days
 */
class AwaSellerAlertService
{
    /** @var list<string> */
    private const AWA_ALERT_TYPES = [
        'awa_new_seller',
        'awa_volume_spike',
        'awa_unidentified_seller',
    ];

    private PDO $db;
    private int $accountId;
    private AlertService $alertService;

    public function __construct(
        int $accountId,
        ?PDO $db = null,
        ?AlertService $alertService = null
    ) {
        if ($accountId <= 0) {
            throw new RuntimeException('Conta Mercado Livre inválida para alertas AWA Sellers.');
        }

        $this->accountId    = $accountId;
        $this->db           = $db ?? Database::getInstance();
        $this->alertService = $alertService ?? new AlertService();
    }

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Detect sellers that have been in the registry for more than $days days
     * without any identification record and fire a single consolidated alert.
     */
    public function checkUnidentifiedSellers(int $days = 7): int
    {
        $days = max(1, $days);
        $cutoff = (new DateTimeImmutable(sprintf('-%d days', $days)))->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total
               FROM awa_seller_registry r
          LEFT JOIN awa_seller_identification i ON i.seller_registry_id = r.id
              WHERE r.account_id = :aid
                AND r.is_active = 1
                AND r.first_seen_at <= :cutoff
                AND (i.id IS NULL OR i.verification_status = :pending_status)'
        );
        $stmt->execute([
            'aid' => $this->accountId,
            'cutoff' => $cutoff,
            'pending_status' => 'pending',
        ]);
        $total = (int) ($stmt->fetchColumn() ?: 0);

        if ($total > 0) {
            $this->alertService->createAlert($this->accountId, 'awa_unidentified_seller', [
                'unidentified_count' => $total,
                'days'               => $days,
                'account_id'         => $this->accountId,
            ]);
        }

        return $total;
    }

    /**
     * Compare seller-count snapshots before/after a scan and create alerts for
     * any seller whose item count grew above the configured threshold.
     *
     * @param array<int, array<string, mixed>> $beforeSnapshot keyed by seller_id
     * @param array<int, array<string, mixed>> $afterSnapshot keyed by seller_id
     */
    public function createVolumeSpikeAlerts(
        array $beforeSnapshot,
        array $afterSnapshot,
        float $threshold = 0.5,
        ?int $scanId = null
    ): int
    {
        $threshold = max(0.01, $threshold);

        $spikes = 0;

        foreach ($afterSnapshot as $sellerId => $afterRow) {
            $beforeRow = $beforeSnapshot[(int) $sellerId] ?? null;
            if (!is_array($beforeRow)) {
                continue;
            }

            $itemsBefore = max(0, (int) ($beforeRow['items_count'] ?? 0));
            $itemsAfter  = max(0, (int) ($afterRow['items_count'] ?? 0));

            if ($itemsBefore <= 0 || $itemsAfter <= $itemsBefore) {
                continue;
            }

            $growthRatio = ($itemsAfter - $itemsBefore) / $itemsBefore;
            if ($growthRatio < $threshold) {
                continue;
            }

            $payload = [
                'registry_id' => (int) ($afterRow['registry_id'] ?? $beforeRow['registry_id'] ?? 0),
                'seller_id' => (int) $sellerId,
                'nickname' => (string) ($afterRow['nickname'] ?? $beforeRow['nickname'] ?? 'Desconhecido'),
                'items_before' => $itemsBefore,
                'items_after' => $itemsAfter,
                'growth_ratio' => round($growthRatio, 4),
            ];

            if ($scanId !== null && $scanId > 0) {
                $payload['scan_id'] = $scanId;
            }

            $this->alertService->createAlert($this->accountId, 'awa_volume_spike', $payload);
            $spikes++;
        }

        return $spikes;
    }

    /**
     * Queries the DB to build before/after snapshots from the two most recent
     * completed scans and delegates to createVolumeSpikeAlerts.
     *
     * @return int number of spike alerts fired
     */
    public function checkVolumeSpikesSinceLastScan(float $threshold = 0.5): int
    {
        // Fetch the two most recent completed scan IDs
        $stmt = $this->db->prepare(
            "SELECT id FROM awa_scan_runs
              WHERE account_id = :aid AND status = 'completed'
              ORDER BY id DESC LIMIT 2"
        );
        $stmt->execute(['aid' => $this->accountId]);
        $scanIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($scanIds) < 2) {
            return 0;
        }

        [$latestScanId, $previousScanId] = [(int) $scanIds[0], (int) $scanIds[1]];

        // Build per-seller item counts for each scan
        $stmt = $this->db->prepare(
            "SELECT r.id AS registry_id, r.ml_seller_id AS seller_id, r.nickname,
                    COUNT(CASE WHEN si.scan_run_id = :prev THEN 1 END) AS items_before,
                    COUNT(CASE WHEN si.scan_run_id = :curr THEN 1 END) AS items_after
               FROM awa_seller_registry r
               JOIN awa_seller_items si ON si.registry_id = r.id
              WHERE r.account_id = :aid
                AND si.scan_run_id IN (:prev2, :curr2)
              GROUP BY r.id, r.ml_seller_id, r.nickname
             HAVING items_before > 0"
        );
        $stmt->execute([
            'aid'   => $this->accountId,
            'prev'  => $previousScanId,
            'curr'  => $latestScanId,
            'prev2' => $previousScanId,
            'curr2' => $latestScanId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $before = [];
        $after  = [];
        foreach ($rows as $row) {
            $sid = (int) $row['seller_id'];
            $before[$sid] = ['registry_id' => $row['registry_id'], 'nickname' => $row['nickname'], 'items_count' => (int) $row['items_before']];
            $after[$sid]  = ['registry_id' => $row['registry_id'], 'nickname' => $row['nickname'], 'items_count' => (int) $row['items_after']];
        }

        return $this->createVolumeSpikeAlerts($before, $after, $threshold, $latestScanId);
    }

    /**
     * Returns AWA-type alerts for this account (from the shared alerts table).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAwaAlerts(int $limit = 50, bool $unreadOnly = false): array
    {
        $limit = max(1, min(200, $limit));

        $unreadClause = $unreadOnly ? "AND read_at IS NULL" : '';
                $types = implode("', '", self::AWA_ALERT_TYPES);

        $stmt = $this->db->prepare(
                        "SELECT id, type, severity, message, data, read_at, created_at
                             FROM alerts
                            WHERE ml_account_id = :aid
                                AND type IN ('{$types}')
                {$unreadClause}
                            ORDER BY created_at DESC, id DESC
              LIMIT {$limit}"
        );
        $stmt->execute(['aid' => $this->accountId]);

        return array_map(
            static function (array $row): array {
                $row['data'] = json_decode((string) ($row['data'] ?? '{}'), true) ?: [];
                return $row;
            },
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }
}
