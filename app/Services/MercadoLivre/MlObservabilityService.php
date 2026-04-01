<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

/**
 * Aggregates operational observability metrics for Mercado Livre accounts.
 *
 * Queries ml_accounts and webhook_event_inbox to build a structured summary
 * that allows quickly identifying disconnected accounts, webhook backlog, and
 * error spikes.
 *
 * "Pronto quando: for possível identificar rapidamente conta desconectada,
 *  backlog ou spike de erro." (ML-BLG-051)
 */
final class MlObservabilityService
{
    /** Webhook backlog alert threshold (number of pending events). */
    private const WEBHOOK_BACKLOG_COUNT_THRESHOLD = 100;

    /** Webhook backlog alert threshold (age of oldest pending event, in seconds). */
    private const WEBHOOK_BACKLOG_AGE_THRESHOLD_SECONDS = 1800; // 30 minutes

    public function __construct(private readonly \PDO $db) {}

    /**
     * Returns an observability summary for Mercado Livre accounts.
     *
     * @param int|null $userId    Scope to a specific authenticated user (null = all users).
     * @param int|null $accountId Scope to a specific account (takes precedence over userId).
     *
     * @return array{
     *   generated_at: string,
     *   accounts: array{total: int, active: int, disconnected: int, refresh_failures_gt0: int, items: list<array>},
     *   webhooks: array{pending: int, failed: int, processed_last_hour: int, oldest_pending_age_seconds: int|null},
     *   alerts: list<array{level: string, code: string, message: string}>,
     *   health: string
     * }
     */
    public function getSummary(?int $userId = null, ?int $accountId = null): array
    {
        $alerts = [];

        $accountsData = $this->queryAccounts($userId, $accountId, $alerts);
        $webhooksData = $this->queryWebhooks($alerts);
        $health       = $this->deriveHealth($alerts);

        return [
            'generated_at' => date('c'),
            'accounts'     => $accountsData,
            'webhooks'     => $webhooksData,
            'alerts'       => $alerts,
            'health'       => $health,
        ];
    }

    /**
     * Queries ml_accounts and populates account metrics + alerts.
     *
     * @param list<array{level: string, code: string, message: string, account_id?: int}> &$alerts
     * @return array{total: int, active: int, disconnected: int, refresh_failures_gt0: int, items: list<array>}
     */
    private function queryAccounts(?int $userId, ?int $accountId, array &$alerts): array
    {
        [$whereClause, $params] = $this->buildAccountWhereClause($userId, $accountId);

        $sql = "
            SELECT id, nickname, status, token_expires_at,
                   refresh_failure_count, last_refresh_error, last_refresh_at
            FROM ml_accounts
            {$whereClause}
            ORDER BY id ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $total              = count($rows);
        $active             = 0;
        $disconnected       = 0;
        $refreshFailuresGt0 = 0;
        $items              = [];

        foreach ($rows as $row) {
            $norm = $this->normalizeAccountRow($row);

            if ($norm['status'] === 'active') {
                $active++;
            }
            if ($norm['status'] === 'inactive') {
                $disconnected++;
            }
            if ($norm['refresh_failure_count'] > 0) {
                $refreshFailuresGt0++;
            }

            $this->addAccountAlerts($norm, $alerts);
            $items[] = $this->mapAccountItem($norm);
        }

        return [
            'total'                => $total,
            'active'               => $active,
            'disconnected'         => $disconnected,
            'refresh_failures_gt0' => $refreshFailuresGt0,
            'items'                => $items,
        ];
    }

    /**
     * Builds the WHERE clause and bind params for the ml_accounts query.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildAccountWhereClause(?int $userId, ?int $accountId): array
    {
        $where  = [];
        $params = [];

        if ($accountId !== null) {
            $where[]               = 'id = :account_id';
            $params[':account_id'] = $accountId;
        } elseif ($userId !== null) {
            $where[]            = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        return [$whereClause, $params];
    }

    /**
     * Appends ACCOUNT_DISCONNECTED and/or REFRESH_FAILURES alerts for a single normalized account row.
     *
     * @param array{id: int, nickname: string, status: string, refresh_failure_count: int, last_refresh_error: string, token_expires_at: mixed, last_refresh_at: mixed} $norm
     * @param list<array{level: string, code: string, message: string, account_id?: int}> &$alerts
     */
    private function addAccountAlerts(array $norm, array &$alerts): void
    {
        $id       = $norm['id'];
        $nickname = $norm['nickname'];
        $status   = $norm['status'];
        $failures = $norm['refresh_failure_count'];

        if ($status === 'inactive') {
            $alerts[] = [
                'level'      => 'error',
                'code'       => 'ACCOUNT_DISCONNECTED',
                'message'    => sprintf(
                    'Conta #%d (%s) está desconectada (status: %s)',
                    $id,
                    $nickname,
                    $status
                ),
                'account_id' => $id,
            ];
        }

        if ($failures > 0) {
            $alerts[] = [
                'level'      => 'warning',
                'code'       => 'REFRESH_FAILURES',
                'message'    => sprintf(
                    'Conta #%d (%s) tem %d falha(s) de refresh de token. Último erro: %s',
                    $id,
                    $nickname,
                    $failures,
                    $norm['last_refresh_error']
                ),
                'account_id' => $id,
            ];
        }
    }

    /**
     * Maps a normalized ml_accounts row to the public item shape.
     *
     * @param array{id: int, nickname: string, status: string, refresh_failure_count: int, token_expires_at: mixed, last_refresh_at: mixed} $norm
     * @return array{id: int, nickname: string, status: string, token_expires_at: mixed, refresh_failure_count: int, last_refresh_at: mixed}
     */
    private function mapAccountItem(array $norm): array
    {
        return [
            'id'                    => $norm['id'],
            'nickname'              => $norm['nickname'],
            'status'                => $norm['status'],
            'token_expires_at'      => $norm['token_expires_at'],
            'refresh_failure_count' => $norm['refresh_failure_count'],
            'last_refresh_at'       => $norm['last_refresh_at'],
        ];
    }

    /**
     * Normalizes a raw ml_accounts DB row to typed values, resolving null/missing keys.
     *
     * Centralizes all null-coalescing for account rows so callers stay branch-free.
     *
     * @param array<string, mixed> $row
     * @return array{id: int, nickname: string, status: string, refresh_failure_count: int, last_refresh_error: string, token_expires_at: mixed, last_refresh_at: mixed}
     */
    private function normalizeAccountRow(array $row): array
    {
        return [
            'id'                    => (int)$row['id'],
            'nickname'              => (string)$row['nickname'],
            'status'                => (string)$row['status'],
            'refresh_failure_count' => (int)$row['refresh_failure_count'],
            'last_refresh_error'    => (string)$row['last_refresh_error'],
            'token_expires_at'      => $row['token_expires_at'],
            'last_refresh_at'       => $row['last_refresh_at'],
        ];
    }

    /**
     * Queries webhook_event_inbox for the mercadolivre provider and populates webhook metrics + alerts.
     *
     * @param list<array{level: string, code: string, message: string}> &$alerts
     * @return array{pending: int, failed: int, processed_last_hour: int, oldest_pending_age_seconds: int|null}
     */
    private function queryWebhooks(array &$alerts): array
    {
        $stats             = $this->fetchWebhookStats();
        $pending           = $stats['pending'];
        $failed            = $stats['failed'];
        $processedLastHour = $stats['processed_last_hour'];
        $oldestAgeSec      = $this->calculateOldestAge($stats['oldest_pending_at']);

        $this->addWebhookAlerts($pending, $failed, $oldestAgeSec, $alerts);

        return [
            'pending'                    => $pending,
            'failed'                     => $failed,
            'processed_last_hour'        => $processedLastHour,
            'oldest_pending_age_seconds' => $oldestAgeSec,
        ];
    }

    /**
     * Executes the webhook aggregate SQL and returns typed stats.
     *
     * Centralizes all null-coalescing for webhook rows so queryWebhooks stays branch-free.
     *
     * @return array{pending: int, failed: int, processed_last_hour: int, oldest_pending_at: string|null}
     */
    private function fetchWebhookStats(): array
    {
        $sql = "
            SELECT
                SUM(CASE WHEN status IN ('received', 'queued') THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)                AS failed,
                SUM(CASE WHEN status = 'processed'
                         AND processed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                         THEN 1 ELSE 0 END)                                        AS processed_last_hour,
                MIN(CASE WHEN status IN ('received', 'queued') THEN received_at ELSE NULL END) AS oldest_pending_at
            FROM webhook_event_inbox
            WHERE provider = 'mercadolivre'
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return ['pending' => 0, 'failed' => 0, 'processed_last_hour' => 0, 'oldest_pending_at' => null];
        }

        return [
            'pending'             => (int)$row['pending'],
            'failed'              => (int)$row['failed'],
            'processed_last_hour' => (int)$row['processed_last_hour'],
            'oldest_pending_at'   => $row['oldest_pending_at'],
        ];
    }

    /**
     * Calculates age in seconds of the oldest pending webhook event.
     *
     * @param string|null $oldestPendingAt MySQL DATETIME string or null.
     */
    private function calculateOldestAge(?string $oldestPendingAt): ?int
    {
        if ($oldestPendingAt === null) {
            return null;
        }
        $ts = strtotime($oldestPendingAt);
        return $ts !== false ? max(0, time() - $ts) : null;
    }

    /**
     * Appends WEBHOOK_BACKLOG and/or WEBHOOK_FAILURES alerts when thresholds are breached.
     *
     * @param list<array{level: string, code: string, message: string}> &$alerts
     */
    private function addWebhookAlerts(int $pending, int $failed, ?int $oldestAgeSec, array &$alerts): void
    {
        $backlogByCount = $pending > self::WEBHOOK_BACKLOG_COUNT_THRESHOLD;
        $backlogByAge   = $oldestAgeSec !== null && $oldestAgeSec > self::WEBHOOK_BACKLOG_AGE_THRESHOLD_SECONDS;

        if ($backlogByCount || $backlogByAge) {
            $alerts[] = [
                'level'   => 'warning',
                'code'    => 'WEBHOOK_BACKLOG',
                'message' => sprintf(
                    'Backlog de webhooks ML: %d evento(s) pendente(s), mais antigo há %d segundo(s)',
                    $pending,
                    $oldestAgeSec ?? 0
                ),
            ];
        }

        if ($failed > 0) {
            $alerts[] = [
                'level'   => 'error',
                'code'    => 'WEBHOOK_FAILURES',
                'message' => sprintf('%d webhook(s) em status "failed" para Mercado Livre', $failed),
            ];
        }
    }

    /**
     * Derives an overall health string from the collected alerts.
     *
     * @param list<array{level: string, code: string, message: string}> $alerts
     */
    private function deriveHealth(array $alerts): string
    {
        $hasError   = false;
        $hasWarning = false;

        foreach ($alerts as $alert) {
            if ($alert['level'] === 'error') {
                $hasError = true;
            } elseif ($alert['level'] === 'warning') {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            return 'critical';
        }
        if ($hasWarning) {
            return 'degraded';
        }

        return 'ok';
    }
}
