<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Inbox de eventos de webhook com idempotência persistente.
 */
class WebhookInboxService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
        $this->ensureColumnsExist();
    }

    /**
     * Registra evento recebido; retorna false se duplicado.
     */
    public function registerIncoming(string $provider, string $eventKey, array $payload, array $metadata = []): bool
    {
        $requestId = isset($metadata['request_id']) ? (string)$metadata['request_id'] : null;
        $deliveryId = isset($metadata['delivery_id']) ? trim((string)$metadata['delivery_id']) : null;
        $signatureTs = isset($metadata['signature_ts']) ? (int)$metadata['signature_ts'] : null;
        $signatureNonce = isset($metadata['signature_nonce']) ? trim((string)$metadata['signature_nonce']) : null;
        if ($deliveryId === '') {
            $deliveryId = null;
        }
        if ($signatureNonce === '') {
            $signatureNonce = null;
        }
        if ($signatureTs !== null && $signatureTs <= 0) {
            $signatureTs = null;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO webhook_event_inbox
             (provider, event_key, request_id, delivery_id, signature_ts, signature_nonce,
              payload_hash, payload_json, metadata_json, status, received_at)
             VALUES
             (:provider, :event_key, :request_id, :delivery_id, :signature_ts, :signature_nonce,
              :payload_hash, :payload_json, :metadata_json, 'received', NOW())"
        );

        try {
            $stmt->execute([
                ':provider' => $provider,
                ':event_key' => $eventKey,
                ':request_id' => $requestId,
                ':delivery_id' => $deliveryId,
                ':signature_ts' => $signatureTs,
                ':signature_nonce' => $signatureNonce,
                ':payload_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return true;
        } catch (\PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * Marca evento como processado.
     */
    public function markProcessed(string $provider, string $eventKey, array $result = []): void
    {
        $stmt = $this->db->prepare(
            "UPDATE webhook_event_inbox
             SET status = 'processed',
                 processed_at = NOW(),
                 result_json = :result_json,
                 error_message = NULL
             WHERE provider = :provider AND event_key = :event_key"
        );

        $stmt->execute([
            ':result_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':provider' => $provider,
            ':event_key' => $eventKey,
        ]);
    }

    /**
     * Marca evento como enfileirado para processamento assíncrono.
     */
    public function markQueued(string $provider, string $eventKey, int $jobId, array $queueMeta = []): void
    {
        $metaJson = json_encode(array_merge([
            'queue_status' => 'queued',
            'queued_at' => date('c'),
            'job_id' => $jobId,
        ], $queueMeta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $this->db->prepare(
            "UPDATE webhook_event_inbox
             SET status = 'queued',
                 job_id = :job_id,
                 result_json = :result_json,
                 updated_at = CURRENT_TIMESTAMP
             WHERE provider = :provider AND event_key = :event_key"
        );

        $stmt->execute([
            ':job_id' => $jobId,
            ':result_json' => $metaJson,
            ':provider' => $provider,
            ':event_key' => $eventKey,
        ]);
    }

    /**
     * Marca evento como falho.
     */
    public function markFailed(string $provider, string $eventKey, string $errorMessage): void
    {
        $stmt = $this->db->prepare(
            "UPDATE webhook_event_inbox
             SET status = 'failed',
                 processed_at = NOW(),
                 error_message = :error_message
             WHERE provider = :provider AND event_key = :event_key"
        );

        $stmt->execute([
            ':error_message' => mb_substr($errorMessage, 0, 1000),
            ':provider' => $provider,
            ':event_key' => $eventKey,
        ]);
    }

    /**
     * Retorna eventos falhos de um provider para tentativa de reprocessamento.
     */
    public function getFailedEvents(string $provider, int $limit = 100): array
    {
        $limitSql = max(1, min(1000, (int)$limit));
        $stmt = $this->db->prepare(
            "SELECT *
             FROM webhook_event_inbox
             WHERE provider = :provider
               AND status = 'failed'
             ORDER BY processed_at ASC, id ASC
                         LIMIT {$limitSql}"
        );
        $stmt->bindValue(':provider', $provider);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Verifica replay de assinatura por delivery_id e/ou nonce em janela recente.
     */
    public function hasSignatureReplay(
        string $provider,
        ?string $deliveryId = null,
        ?string $signatureNonce = null,
        ?int $signatureTs = null,
        int $windowSeconds = 300
    ): bool {
        $deliveryId = $deliveryId !== null ? trim($deliveryId) : null;
        $signatureNonce = $signatureNonce !== null ? trim($signatureNonce) : null;

        if ($deliveryId === '') {
            $deliveryId = null;
        }
        if ($signatureNonce === '') {
            $signatureNonce = null;
        }

        if ($deliveryId === null && $signatureNonce === null) {
            return false;
        }

        $windowSeconds = max(60, min(86400, $windowSeconds));
        $windowStart = date('Y-m-d H:i:s', time() - $windowSeconds);

        $clauses = [];
        $params = [
            ':provider' => $provider,
            ':window_start' => $windowStart,
        ];

        if ($deliveryId !== null) {
            $clauses[] = 'delivery_id = :delivery_id';
            $params[':delivery_id'] = $deliveryId;
        }

        if ($signatureNonce !== null) {
            $clauses[] = 'signature_nonce = :signature_nonce';
            $params[':signature_nonce'] = $signatureNonce;
        }

        if (empty($clauses)) {
            return false;
        }

        $sql = "SELECT 1
                FROM webhook_event_inbox
                WHERE provider = :provider
                  AND received_at >= :window_start
                  AND (" . implode(' OR ', $clauses) . ")
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    /**
     * Remove eventos antigos do inbox por provider.
     */
    public function cleanupOldEvents(string $provider, int $days = 30, array $statuses = ['processed', 'failed']): int
    {
        $days = max(1, min(365, $days));
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if (empty($statuses)) {
            $statuses = ['processed', 'failed'];
        }

        $placeholders = [];
        $params = [
            ':provider' => $provider,
            ':cutoff' => date('Y-m-d H:i:s', time() - ($days * 86400)),
        ];

        foreach ($statuses as $idx => $status) {
            $key = ':status_' . $idx;
            $placeholders[] = $key;
            $params[$key] = $status;
        }

        $sql = "DELETE FROM webhook_event_inbox
                WHERE provider = :provider
                  AND status IN (" . implode(',', $placeholders) . ")
                  AND received_at < :cutoff";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$stmt->rowCount();
    }

    /**
     * Retorna status de um evento por event_key ou request_id.
     */
    public function getEventStatus(string $provider, ?string $eventKey = null, ?string $requestId = null): ?array
    {
        if (($eventKey === null || $eventKey === '') && ($requestId === null || $requestId === '')) {
            return null;
        }

        if ($eventKey !== null && $eventKey !== '') {
            $stmt = $this->db->prepare(
                "SELECT *
                 FROM webhook_event_inbox
                 WHERE provider = :provider AND event_key = :event_key
                 LIMIT 1"
            );
            $stmt->execute([
                ':provider' => $provider,
                ':event_key' => $eventKey,
            ]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT *
                 FROM webhook_event_inbox
                 WHERE provider = :provider AND request_id = :request_id
                 ORDER BY id DESC
                 LIMIT 1"
            );
            $stmt->execute([
                ':provider' => $provider,
                ':request_id' => $requestId,
            ]);
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $result = json_decode((string)($row['result_json'] ?? ''), true);
        if (!is_array($result)) {
            $result = [];
        }

        $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return [
            'id' => (int)$row['id'],
            'provider' => (string)$row['provider'],
            'event_key' => (string)$row['event_key'],
            'request_id' => (string)($row['request_id'] ?? ''),
            'status' => (string)$row['status'],
            'job_id' => isset($row['job_id']) ? (int)$row['job_id'] : null,
            'received_at' => (string)$row['received_at'],
            'processed_at' => $row['processed_at'],
            'error_message' => $row['error_message'],
            'result' => $result,
            'metadata' => $metadata,
        ];
    }

    /**
     * Métricas de SLA para um provider de webhook.
     */
    public function getProviderSlaMetrics(string $provider, int $hoursBack = 24, int $recentLimit = 200): array
    {
        $hoursBack = max(1, min(720, $hoursBack));
        $recentLimit = max(10, min(2000, $recentLimit));

        $windowStart = date('Y-m-d H:i:s', time() - ($hoursBack * 3600));

        $summaryStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) AS received_count,
                SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) AS queued_count,
                SUM(CASE WHEN status = 'processed' THEN 1 ELSE 0 END) AS processed_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_count,
                AVG(CASE WHEN processed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, received_at, processed_at) END) AS avg_processing_seconds,
                MAX(CASE WHEN processed_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, received_at, processed_at) END) AS max_processing_seconds
             FROM webhook_event_inbox
             WHERE provider = :provider
               AND received_at >= :window_start"
        );
        $summaryStmt->execute([
            ':provider' => $provider,
            ':window_start' => $windowStart,
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $recentStmt = $this->db->prepare(
            "SELECT
                id,
                provider,
                event_key,
                request_id,
                job_id,
                status,
                received_at,
                processed_at,
                error_message,
                result_json,
                metadata_json,
                TIMESTAMPDIFF(SECOND, received_at, COALESCE(processed_at, NOW())) AS processing_seconds
             FROM webhook_event_inbox
             WHERE provider = :provider
               AND received_at >= :window_start
             ORDER BY id DESC
                         LIMIT {$recentLimit}"
        );
        $recentStmt->bindValue(':provider', $provider);
        $recentStmt->bindValue(':window_start', $windowStart);
        $recentStmt->execute();
        $recentRows = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $recent = [];
        foreach ($recentRows as $row) {
            $result = json_decode((string)($row['result_json'] ?? ''), true);
            if (!is_array($result)) {
                $result = [];
            }

            $metadata = json_decode((string)($row['metadata_json'] ?? ''), true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $recent[] = [
                'id' => (int)$row['id'],
                'event_key' => (string)$row['event_key'],
                'request_id' => (string)($row['request_id'] ?? ''),
                'job_id' => isset($row['job_id']) ? (int)$row['job_id'] : null,
                'status' => (string)$row['status'],
                'received_at' => (string)$row['received_at'],
                'processed_at' => $row['processed_at'],
                'processing_seconds' => (int)($row['processing_seconds'] ?? 0),
                'queue_status' => (string)($result['queue_status'] ?? ''),
                'error_message' => $row['error_message'],
                'metadata' => $metadata,
            ];
        }

        $total = (int)($summary['total'] ?? 0);
        $processed = (int)($summary['processed_count'] ?? 0);
        $failed = (int)($summary['failed_count'] ?? 0);

        return [
            'provider' => $provider,
            'window' => [
                'hours_back' => $hoursBack,
                'window_start' => $windowStart,
            ],
            'summary' => [
                'total' => $total,
                'received_count' => (int)($summary['received_count'] ?? 0),
                'queued_count' => (int)($summary['queued_count'] ?? 0),
                'processed_count' => $processed,
                'failed_count' => $failed,
                'success_rate_percent' => $total > 0 ? round(($processed / $total) * 100, 2) : 0.0,
                'failure_rate_percent' => $total > 0 ? round(($failed / $total) * 100, 2) : 0.0,
                'avg_processing_seconds' => isset($summary['avg_processing_seconds']) ? (float)$summary['avg_processing_seconds'] : 0.0,
                'max_processing_seconds' => (int)($summary['max_processing_seconds'] ?? 0),
            ],
            'recent_events' => $recent,
            'generated_at' => date('c'),
        ];
    }

    private function ensureTableExists(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS webhook_event_inbox (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                provider VARCHAR(60) NOT NULL,
                event_key VARCHAR(255) NOT NULL,
                request_id VARCHAR(64) NULL,
                delivery_id VARCHAR(128) NULL,
                signature_ts BIGINT NULL,
                signature_nonce VARCHAR(128) NULL,
                job_id INT NULL,
                payload_hash CHAR(64) NOT NULL,
                payload_json LONGTEXT NOT NULL,
                metadata_json JSON NULL,
                status ENUM('received','queued','processed','failed') NOT NULL DEFAULT 'received',
                error_message VARCHAR(1000) NULL,
                result_json JSON NULL,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_webhook_event_provider_key (provider, event_key),
                INDEX idx_webhook_event_provider_status (provider, status),
                INDEX idx_webhook_event_received_at (received_at),
                INDEX idx_webhook_event_request_id (provider, request_id),
                INDEX idx_webhook_event_delivery_id (provider, delivery_id),
                INDEX idx_webhook_event_signature_nonce (provider, signature_nonce),
                INDEX idx_webhook_event_signature_ts (provider, signature_ts),
                INDEX idx_webhook_event_job_id (job_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function ensureColumnsExist(): void
    {
        try {
            $stmt = $this->db->query("SELECT DATABASE() AS db_name");
            $dbName = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['db_name'] ?? null) : null;
            if (!$dbName) {
                return;
            }

            $colsStmt = $this->db->prepare(
                "SELECT COLUMN_NAME
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :db
                   AND TABLE_NAME = 'webhook_event_inbox'"
            );
            $colsStmt->execute([':db' => $dbName]);
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
            $map = array_fill_keys(is_array($cols) ? $cols : [], true);

            if (!isset($map['request_id'])) {
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD COLUMN request_id VARCHAR(64) NULL AFTER event_key");
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD INDEX idx_webhook_event_request_id (provider, request_id)");
            }

            if (!isset($map['job_id'])) {
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD COLUMN job_id INT NULL AFTER request_id");
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD INDEX idx_webhook_event_job_id (job_id)");
            }

            if (!isset($map['delivery_id'])) {
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD COLUMN delivery_id VARCHAR(128) NULL AFTER request_id");
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD INDEX idx_webhook_event_delivery_id (provider, delivery_id)");
            }

            if (!isset($map['signature_ts'])) {
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD COLUMN signature_ts BIGINT NULL AFTER delivery_id");
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD INDEX idx_webhook_event_signature_ts (provider, signature_ts)");
            }

            if (!isset($map['signature_nonce'])) {
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD COLUMN signature_nonce VARCHAR(128) NULL AFTER signature_ts");
                $this->db->exec("ALTER TABLE webhook_event_inbox ADD INDEX idx_webhook_event_signature_nonce (provider, signature_nonce)");
            }

            $statusColStmt = $this->db->prepare(
                "SELECT COLUMN_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :db
                   AND TABLE_NAME = 'webhook_event_inbox'
                   AND COLUMN_NAME = 'status'
                 LIMIT 1"
            );
            $statusColStmt->execute([':db' => $dbName]);
            $statusCol = (string)($statusColStmt->fetch(PDO::FETCH_ASSOC)['COLUMN_TYPE'] ?? '');
            if ($statusCol !== '' && stripos($statusCol, "'queued'") === false) {
                $this->db->exec(
                    "ALTER TABLE webhook_event_inbox
                     MODIFY COLUMN status ENUM('received','queued','processed','failed') NOT NULL DEFAULT 'received'"
                );
            }
        } catch (\Throwable $e) {
            // não interromper o fluxo de webhook por migração incremental
        }
    }
}
