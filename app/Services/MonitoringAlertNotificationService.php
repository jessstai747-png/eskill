<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use DateTimeImmutable;
use PDO;

/**
 * Dispara alertas operacionais de monitoramento com deduplicação/cooldown.
 */
class MonitoringAlertNotificationService
{
    private const DISPATCH_TYPE = 'ML_MONITOR_DISPATCH';
    private const DEFAULT_COOLDOWN_MINUTES = 30;
    private const DEFAULT_MAX_PER_RUN = 5;
    private const DEFAULT_MIN_SEVERITY = 'HIGH';
    private const DEFAULT_ENABLED = false;

    /**
     * @var array<string, int>
     */
    private const SEVERITY_RANK = [
        'LOW' => 10,
        'INFO' => 20,
        'MEDIUM' => 30,
        'WARNING' => 40,
        'HIGH' => 50,
        'CRITICAL' => 60,
    ];

    private PDO $db;
    private NotificationService $notificationService;
    private bool $enabled;
    private string $minSeverity;
    private int $cooldownMinutes;
    private int $maxPerRun;

    public function __construct(?PDO $db = null, ?NotificationService $notificationService = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->notificationService = $notificationService ?? new NotificationService();
        $this->enabled = $this->getBoolEnv('ML_MONITOR_ALERT_NOTIFY_ENABLED', self::DEFAULT_ENABLED);
        $this->minSeverity = $this->normalizeSeverity(
            $this->getStringEnv('ML_MONITOR_ALERT_NOTIFY_MIN_SEVERITY', self::DEFAULT_MIN_SEVERITY)
        );
        $this->cooldownMinutes = $this->getIntEnv(
            'ML_MONITOR_ALERT_NOTIFY_COOLDOWN_MINUTES',
            self::DEFAULT_COOLDOWN_MINUTES,
            1,
            1440
        );
        $this->maxPerRun = $this->getIntEnv('ML_MONITOR_ALERT_NOTIFY_MAX_PER_RUN', self::DEFAULT_MAX_PER_RUN, 1, 100);

        $this->ensureNotificationLogTableExists();
    }

    /**
     * @param array<int, array<string, mixed>> $alerts
     * @return array<string, mixed>
     */
    public function dispatchMlOperationalAlerts(array $alerts): array
    {
        $summary = [
            'enabled' => $this->enabled,
            'min_severity' => $this->minSeverity,
            'cooldown_minutes' => $this->cooldownMinutes,
            'max_per_run' => $this->maxPerRun,
            'checked' => 0,
            'eligible' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped_due_to_cooldown' => 0,
            'skipped_due_to_severity' => 0,
            'skipped_due_to_limit' => 0,
        ];

        if (!$this->enabled) {
            return $summary;
        }

        foreach ($alerts as $alert) {
            if (!is_array($alert)) {
                continue;
            }

            $summary['checked']++;
            $type = strtolower((string)($alert['type'] ?? ''));

            if (!$this->isMlOperationalAlert($type)) {
                continue;
            }

            $severity = $this->normalizeSeverity((string)($alert['severity'] ?? 'WARNING'));
            if (!$this->severityMeetsThreshold($severity, $this->minSeverity)) {
                $summary['skipped_due_to_severity']++;
                continue;
            }

            if ($summary['sent'] >= $this->maxPerRun) {
                $summary['skipped_due_to_limit']++;
                continue;
            }

            $summary['eligible']++;
            $subject = $this->buildAlertTitle($type, $severity);

            if ($this->hasRecentDispatch($subject)) {
                $summary['skipped_due_to_cooldown']++;
                continue;
            }

            $message = $this->buildAlertMessage($alert);

            try {
                $channels = $this->notificationService->sendAlert($subject, $message, $severity);
                $delivered = $this->wasDelivered($channels);
                $this->persistDispatchMarker($subject, $message, $severity, $delivered ? 'SENT' : 'FAILED');

                if ($delivered) {
                    $summary['sent']++;
                } else {
                    $summary['failed']++;
                }
            } catch (\Throwable $e) {
                $summary['failed']++;
                $this->persistDispatchMarker($subject, $message, $severity, 'FAILED', $e->getMessage());
            }
        }

        return $summary;
    }

    private function isMlOperationalAlert(string $type): bool
    {
        return str_starts_with($type, 'ml_');
    }

    private function wasDelivered(array $channels): bool
    {
        if ($channels === []) {
            return false;
        }

        foreach ($channels as $sent) {
            if ($sent === true) {
                return true;
            }
        }

        return false;
    }

    private function buildAlertTitle(string $type, string $severity): string
    {
        $label = strtoupper(str_replace('_', ' ', $type));
        return "[ML Monitor][$severity] $label";
    }

    /**
     * @param array<string, mixed> $alert
     */
    private function buildAlertMessage(array $alert): string
    {
        $message = (string)($alert['message'] ?? 'Alerta operacional ML detectado');
        $value = isset($alert['value']) ? (string)$alert['value'] : 'N/A';
        $threshold = isset($alert['threshold']) ? (string)$alert['threshold'] : 'N/A';
        $type = (string)($alert['type'] ?? 'unknown');
        $timestamp = date('Y-m-d H:i:s');

        return "Tipo: {$type}\nMensagem: {$message}\nValor: {$value}\nThreshold: {$threshold}\nTimestamp: {$timestamp}";
    }

    private function hasRecentDispatch(string $subject): bool
    {
        $cutoff = (new DateTimeImmutable('now'))
            ->modify('-' . $this->cooldownMinutes . ' minutes')
            ->format('Y-m-d H:i:s');

        try {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM notification_logs
                 WHERE type = :type
                   AND subject = :subject
                   AND status = :status
                   AND created_at >= :cutoff'
            );
            $stmt->execute([
                'type' => self::DISPATCH_TYPE,
                'subject' => $subject,
                'status' => 'SENT',
                'cutoff' => $cutoff,
            ]);

            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function persistDispatchMarker(string $subject, string $message, string $severity, string $status, ?string $errorMessage = null): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO notification_logs (type, recipient, subject, status, error_message, metadata, created_at)
                 VALUES (:type, :recipient, :subject, :status, :error_message, :metadata, :created_at)'
            );
            $stmt->execute([
                'type' => self::DISPATCH_TYPE,
                'recipient' => 'monitoring',
                'subject' => $subject,
                'status' => $status,
                'error_message' => $errorMessage,
                'metadata' => json_encode([
                    'message' => $message,
                    'severity' => $severity,
                ]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Best-effort: falha de persistência não deve quebrar endpoint de monitoramento.
        }
    }

    private function ensureNotificationLogTableExists(): void
    {
        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS notification_logs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    type VARCHAR(20) NOT NULL,
                    recipient VARCHAR(255) NOT NULL,
                    subject TEXT,
                    status VARCHAR(20) NOT NULL,
                    error_message TEXT,
                    metadata JSON,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type (type),
                    INDEX idx_status (status),
                    INDEX idx_created (created_at),
                    INDEX idx_recipient (recipient)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            // Best-effort: o fluxo segue mesmo sem criação automática.
        }
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtoupper(trim($severity));
        return isset(self::SEVERITY_RANK[$normalized]) ? $normalized : 'WARNING';
    }

    private function severityMeetsThreshold(string $candidate, string $threshold): bool
    {
        return (self::SEVERITY_RANK[$candidate] ?? 0) >= (self::SEVERITY_RANK[$threshold] ?? 0);
    }

    private function getStringEnv(string $key, string $default): string
    {
        $raw = $_ENV[$key] ?? getenv($key);
        return is_string($raw) && trim($raw) !== '' ? trim($raw) : $default;
    }

    private function getBoolEnv(string $key, bool $default): bool
    {
        $raw = $_ENV[$key] ?? getenv($key);
        if ($raw === false || $raw === null) {
            return $default;
        }

        $value = strtolower(trim((string)$raw));
        if (in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
            return false;
        }

        return $default;
    }

    private function getIntEnv(string $key, int $default, int $min, int $max): int
    {
        $raw = $_ENV[$key] ?? getenv($key);
        $value = is_numeric($raw) ? (int)$raw : $default;
        return max($min, min($max, $value));
    }
}
