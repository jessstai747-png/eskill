<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class MonitoringService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureMonitoringTable();
    }

    /**
     * Cria tabela de monitoramento se não existir e garante que colunas existam
     */
    private function ensureMonitoringTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS system_monitoring (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    metric_name VARCHAR(100) NOT NULL,
                    metric_value DECIMAL(10,2) NOT NULL,
                    metric_unit VARCHAR(20) NULL,
                    status ENUM('ok', 'warning', 'critical') DEFAULT 'ok',
                    metadata JSON NULL,
                    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_metric_name (metric_name),
                    INDEX idx_status (status),
                    INDEX idx_recorded_at (recorded_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Fix schema drift: add 'status' column if table existed without it
            $columns = $this->db->query("SHOW COLUMNS FROM system_monitoring LIKE 'status'");
            if ($columns->rowCount() === 0) {
                $this->db->exec("
                    ALTER TABLE system_monitoring
                    ADD COLUMN status ENUM('ok', 'warning', 'critical') DEFAULT 'ok' AFTER metric_unit
                ");
                log_info('MonitoringService: added missing column status to system_monitoring');
            }
        } catch (\Exception $e) {
            log_error('Erro ao criar tabela de monitoramento', ['service' => 'MonitoringService', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Registra uma métrica do sistema
     */
    public function recordMetric(string $metricName, float $value, ?string $unit = null, string $status = 'ok', array $metadata = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO system_monitoring (metric_name, metric_value, metric_unit, status, metadata)
                VALUES (:metric_name, :metric_value, :metric_unit, :status, :metadata)
            ");

            $stmt->execute([
                ':metric_name' => $metricName,
                ':metric_value' => $value,
                ':metric_unit' => $unit,
                ':status' => $status,
                ':metadata' => json_encode($metadata),
            ]);
        } catch (\Exception $e) {
            log_error('Erro ao registrar métrica', ['service' => 'MonitoringService', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Monitora saúde do sistema
     */
    public function checkSystemHealth(): array
    {
        $health = [
            'status' => 'ok',
            'checks' => [],
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Verificar conexão com banco de dados
        try {
            $this->db->query("SELECT 1");
            $health['checks']['database'] = ['status' => 'ok', 'message' => 'Conexão OK'];
        } catch (\Exception $e) {
            $health['checks']['database'] = ['status' => 'critical', 'message' => $e->getMessage()];
            $health['status'] = 'critical';
        }

        // Verificar espaço em disco
        $diskFree = disk_free_space(__DIR__ . '/../../');
        $diskTotal = disk_total_space(__DIR__ . '/../../');

        if ($diskFree === false || $diskTotal === false || $diskTotal == 0) {
            $diskUsagePercent = 0.0;
            $diskFreeGb = 0.0;
            $diskTotalGb = 0.0;
        } else {
            $diskUsagePercent = (1 - ($diskFree / $diskTotal)) * 100;
            $diskFreeGb = round($diskFree / 1024 / 1024 / 1024, 2);
            $diskTotalGb = round($diskTotal / 1024 / 1024 / 1024, 2);
        }

        $health['checks']['disk'] = [
            'status' => $diskUsagePercent > 90 ? 'critical' : ($diskUsagePercent > 75 ? 'warning' : 'ok'),
            'usage_percent' => round($diskUsagePercent, 2),
            'free_gb' => $diskFreeGb,
            'total_gb' => $diskTotalGb,
        ];

        if ($health['checks']['disk']['status'] !== 'ok') {
            $health['status'] = $health['checks']['disk']['status'];
        }

        // Verificar contas ML ativas
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM ml_accounts WHERE status = 'active'");
            $activeAccounts = $stmt->fetch()['total'] ?? 0;

            $health['checks']['ml_accounts'] = [
                'status' => 'ok',
                'active_accounts' => (int)$activeAccounts,
            ];
        } catch (\Exception $e) {
            $health['checks']['ml_accounts'] = ['status' => 'warning', 'message' => $e->getMessage()];
        }

        // Verificar tokens expirando
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as total
                FROM ml_accounts
                WHERE status = 'active'
                AND token_expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
                AND token_expires_at > NOW()
            ");
            $expiringTokens = $stmt->fetch()['total'] ?? 0;

            $health['checks']['tokens'] = [
                'status' => $expiringTokens > 0 ? 'warning' : 'ok',
                'expiring_count' => (int)$expiringTokens,
            ];

            if ($expiringTokens > 0) {
                $health['status'] = 'warning';
            }
        } catch (\Exception $e) {
            $health['checks']['tokens'] = ['status' => 'warning', 'message' => $e->getMessage()];
        }

        // Verificar jobs pendentes
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM jobs WHERE status = 'pending'");
            $pendingJobs = $stmt->fetch()['total'] ?? 0;

            $health['checks']['jobs'] = [
                'status' => $pendingJobs > 100 ? 'warning' : 'ok',
                'pending_count' => (int)$pendingJobs,
            ];

            if ($pendingJobs > 100) {
                $health['status'] = 'warning';
            }
        } catch (\Exception $e) {
            // Tabela jobs pode não existir
            $health['checks']['jobs'] = ['status' => 'ok', 'message' => 'Tabela jobs não encontrada'];
        }

        // Registrar métricas
        $this->recordMetric('disk_usage_percent', $diskUsagePercent, '%', $health['checks']['disk']['status']);
        $this->recordMetric('active_accounts', $activeAccounts, 'count', 'ok');
        $this->recordMetric('expiring_tokens', $expiringTokens, 'count', $expiringTokens > 0 ? 'warning' : 'ok');

        return $health;
    }

    /**
     * Obtém métricas recentes
     */
    public function getRecentMetrics(string $metricName = null, int $hours = 24): array
    {
        $sql = "
            SELECT * FROM system_monitoring
            WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
        ";
        $params = [':hours' => $hours];

        if ($metricName) {
            $sql .= " AND metric_name = :metric_name";
            $params[':metric_name'] = $metricName;
        }

        $sql .= " ORDER BY recorded_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar JSON metadata
        foreach ($metrics as &$metric) {
            $metric['metadata'] = json_decode($metric['metadata'], true);
        }

        return $metrics;
    }

    /**
     * Obtém estatísticas de métricas
     */
    public function getMetricStats(string $metricName, int $hours = 24): array
    {
        $metrics = $this->getRecentMetrics($metricName, $hours);

        if (empty($metrics)) {
            return [
                'metric_name' => $metricName,
                'count' => 0,
                'avg' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        $values = array_column($metrics, 'metric_value');

        return [
            'metric_name' => $metricName,
            'count' => count($values),
            'avg' => round(array_sum($values) / count($values), 2),
            'min' => min($values),
            'max' => max($values),
            'latest' => $values[0] ?? 0,
        ];
    }

    /**
     * Limpa métricas antigas
     */
    public function cleanOldMetrics(int $days = 30): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM system_monitoring
            WHERE recorded_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");

        $stmt->execute([':days' => $days]);

        return $stmt->rowCount();
    }
}
