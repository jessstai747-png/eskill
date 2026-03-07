<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de métricas de performance - Implementação real
 *
 * Coleta métricas reais do sistema, banco de dados, cache e filas
 */
class PerformanceMetricsService
{
    private ?PDO $db = null;
    private ?object $redis = null;
    private string $metricsTable = 'performance_metrics';

    public function __construct()
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Throwable $e) {
            log_warning('DB connection failed', ['service' => 'PerformanceMetricsService', 'error' => $e->getMessage()]);
        }

        $this->initRedis();
    }

    private function initRedis(): void
    {
        try {
            if (!class_exists('Redis')) {
                $this->redis = null;
                return;
            }

            $host = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);

            $redisClass = 'Redis';
            $this->redis = new $redisClass();
            $connected = @$this->redis->connect($host, $port, 2.0);

            if (!$connected) {
                $this->redis = null;
            }
        } catch (\Throwable $e) {
            $this->redis = null;
        }
    }

    public function getMetrics(): array
    {
        $now = time();

        return [
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'llm' => $this->getLLMMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'system' => $this->getSystemMetrics(),
            'timestamp' => $now,
        ];
    }

    /**
     * Get real cache metrics from Redis
     */
    private function getCacheMetrics(): array
    {
        if (!$this->redis) {
            return [
                'hit_rate' => 0,
                'connection' => 'disconnected',
                'error' => 'Redis not available'
            ];
        }

        try {
            $info = $this->redis->info('stats');

            $hits = (int)($info['keyspace_hits'] ?? 0);
            $misses = (int)($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;

            $hitRate = $total > 0 ? round($hits / $total, 4) : 0;

            // Get memory info
            $memInfo = $this->redis->info('memory');

            return [
                'hit_rate' => $hitRate,
                'hits' => $hits,
                'misses' => $misses,
                'connection' => 'ok',
                'used_memory' => $memInfo['used_memory'] ?? 0,
                'used_memory_human' => $memInfo['used_memory_human'] ?? '0B',
                'connected_clients' => (int)($this->redis->info('clients')['connected_clients'] ?? 0),
                'total_keys' => $this->redis->dbSize(),
            ];
        } catch (\Throwable $e) {
            return [
                'hit_rate' => 0,
                'connection' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get real queue metrics from database
     */
    private function getQueueMetrics(): array
    {
        if (!$this->db) {
            return ['pending' => 0, 'completed' => 0, 'failed' => 0, 'error' => 'DB not available'];
        }

        try {
            // Check if jobs table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'jobs'");
            if ($tableCheck->rowCount() === 0) {
                return ['pending' => 0, 'completed' => 0, 'failed' => 0, 'note' => 'jobs table not found'];
            }

            // Get queue stats
            $stmt = $this->db->query("
                SELECT
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                FROM jobs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get jobs completed today
            $todayStmt = $this->db->query("
                SELECT COUNT(*) as count
                FROM jobs
                WHERE status = 'completed'
                AND completed_at >= CURDATE()
            ");
            $todayCompleted = $todayStmt->fetchColumn() ?: 0;

            return [
                'pending' => (int)($stats['pending'] ?? 0),
                'processing' => (int)($stats['processing'] ?? 0),
                'completed' => (int)($stats['completed'] ?? 0),
                'failed' => (int)($stats['failed'] ?? 0),
                'completed_today' => (int)$todayCompleted,
            ];
        } catch (\Throwable $e) {
            return ['pending' => 0, 'completed' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get real LLM API usage metrics
     */
    private function getLLMMetrics(): array
    {
        if (!$this->db) {
            return ['today' => 0, 'week' => 0, 'error' => 'DB not available'];
        }

        try {
            // Check if ai_usage_logs table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'ai_usage_logs'");
            if ($tableCheck->rowCount() === 0) {
                // Try alternate table name
                $tableCheck = $this->db->query("SHOW TABLES LIKE 'llm_requests'");
                if ($tableCheck->rowCount() === 0) {
                    return ['today' => 0, 'week' => 0, 'note' => 'LLM logs table not found'];
                }
                $table = 'llm_requests';
            } else {
                $table = 'ai_usage_logs';
            }

            // Get usage stats
            $stmt = $this->db->query("
                SELECT
                    SUM(CASE WHEN created_at >= CURDATE() THEN 1 ELSE 0 END) as today,
                    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as week,
                    SUM(CASE WHEN created_at >= CURDATE() THEN COALESCE(tokens_used, 0) ELSE 0 END) as tokens_today,
                    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN COALESCE(tokens_used, 0) ELSE 0 END) as tokens_week,
                    SUM(CASE WHEN created_at >= CURDATE() THEN COALESCE(cost, 0) ELSE 0 END) as cost_today,
                    SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN COALESCE(cost, 0) ELSE 0 END) as cost_week
                FROM {$table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'today' => (int)($stats['today'] ?? 0),
                'week' => (int)($stats['week'] ?? 0),
                'tokens_today' => (int)($stats['tokens_today'] ?? 0),
                'tokens_week' => (int)($stats['tokens_week'] ?? 0),
                'cost_today' => round((float)($stats['cost_today'] ?? 0), 4),
                'cost_week' => round((float)($stats['cost_week'] ?? 0), 4),
            ];
        } catch (\Throwable $e) {
            return ['today' => 0, 'week' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get real database performance metrics
     */
    private function getDatabaseMetrics(): array
    {
        if (!$this->db) {
            return ['queries_per_sec' => 0, 'connection' => 'disconnected'];
        }

        try {
            // Get MySQL status variables
            $stmt = $this->db->query("SHOW GLOBAL STATUS WHERE Variable_name IN (
                'Questions', 'Uptime', 'Threads_connected', 'Threads_running',
                'Slow_queries', 'Connections', 'Bytes_received', 'Bytes_sent'
            )");

            $status = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $status[$row['Variable_name']] = $row['Value'];
            }

            $uptime = (int)($status['Uptime'] ?? 1);
            $questions = (int)($status['Questions'] ?? 0);

            return [
                'queries_per_sec' => round($questions / $uptime, 2),
                'total_queries' => $questions,
                'uptime_seconds' => $uptime,
                'threads_connected' => (int)($status['Threads_connected'] ?? 0),
                'threads_running' => (int)($status['Threads_running'] ?? 0),
                'slow_queries' => (int)($status['Slow_queries'] ?? 0),
                'total_connections' => (int)($status['Connections'] ?? 0),
                'bytes_received' => (int)($status['Bytes_received'] ?? 0),
                'bytes_sent' => (int)($status['Bytes_sent'] ?? 0),
                'connection' => 'ok',
            ];
        } catch (\Throwable $e) {
            return ['queries_per_sec' => 0, 'connection' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get real system metrics
     */
    private function getSystemMetrics(): array
    {
        $metrics = [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
            'php_version' => PHP_VERSION,
        ];

        // Try to get system load average (Linux only)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics['load_average'] = [
                '1min' => round($load[0], 2),
                '5min' => round($load[1], 2),
                '15min' => round($load[2], 2),
            ];
        }

        // Disk usage
        $appRoot = dirname(__DIR__, 2);
        if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
            $metrics['disk'] = [
                'free' => @disk_free_space($appRoot) ?: 0,
                'total' => @disk_total_space($appRoot) ?: 0,
                'used_percent' => 0,
            ];
            if ($metrics['disk']['total'] > 0) {
                $metrics['disk']['used_percent'] = round(
                    (($metrics['disk']['total'] - $metrics['disk']['free']) / $metrics['disk']['total']) * 100,
                    2
                );
            }
        }

        // CPU cores
        if (is_readable('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            $metrics['cpu_cores'] = substr_count($cpuinfo, 'processor');
        }

        return $metrics;
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;

        switch ($last) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Get real historical metrics from database
     */
    public function getHistoricalMetrics(string $metricKey, int $hours = 24): array
    {
        if (!$this->db) {
            return $this->buildPlaceholderSeries($hours, $metricKey);
        }

        try {
            // Check if metrics table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($this->metricsTable));
            if ($tableCheck->rowCount() === 0) {
                // Create table if not exists
                $this->createMetricsTable();
            }

            $stmt = $this->db->prepare("
                SELECT
                    UNIX_TIMESTAMP(recorded_at) as timestamp,
                    metric_value as value
                FROM {$this->metricsTable}
                WHERE metric_key = :key
                AND recorded_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                ORDER BY recorded_at ASC
            ");
            $stmt->execute(['key' => $metricKey, 'hours' => $hours]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                return $this->buildPlaceholderSeries($hours, $metricKey);
            }

            return $rows;
        } catch (\Throwable $e) {
            log_warning('getHistoricalMetrics error', ['service' => 'PerformanceMetricsService', 'error' => $e->getMessage()]);
            return $this->buildPlaceholderSeries($hours, $metricKey);
        }
    }

    private function buildPlaceholderSeries(int $hours, ?string $metricKey = null): array
    {
        $baseline = 0.0;

        if ($metricKey !== null && $this->db) {
            try {
                $stmt = $this->db->prepare("\n                    SELECT metric_value\n                    FROM {$this->metricsTable}\n                    WHERE metric_key = :key\n                    ORDER BY recorded_at DESC\n                    LIMIT 1\n                ");
                $stmt->execute(['key' => $metricKey]);
                $latest = $stmt->fetchColumn();
                if ($latest !== false && $latest !== null) {
                    $baseline = (float) $latest;
                }
            } catch (\Throwable $e) {
                // mantém baseline padrão em 0.0
            }
        }

        $now = time();
        $series = [];
        for ($i = $hours; $i >= 0; $i--) {
            $series[] = [
                'timestamp' => $now - ($i * 3600),
                'value' => $baseline,
            ];
        }
        return $series;
    }

    /**
     * Record a metric value for historical tracking
     */
    public function recordMetric(string $key, float $value): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            // Ensure table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($this->metricsTable));
            if ($tableCheck->rowCount() === 0) {
                $this->createMetricsTable();
            }

            $stmt = $this->db->prepare("
                INSERT INTO {$this->metricsTable} (metric_key, metric_value, recorded_at)
                VALUES (:key, :value, NOW())
            ");
            return $stmt->execute(['key' => $key, 'value' => $value]);
        } catch (\Throwable $e) {
            log_warning('recordMetric error', ['service' => 'PerformanceMetricsService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Record current metrics snapshot
     */
    public function recordCurrentMetrics(): void
    {
        $metrics = $this->getMetrics();

        // Record key metrics
        if (isset($metrics['cache']['hit_rate'])) {
            $this->recordMetric('cache_hit_rate', $metrics['cache']['hit_rate']);
        }
        if (isset($metrics['database']['queries_per_sec'])) {
            $this->recordMetric('db_queries_per_sec', $metrics['database']['queries_per_sec']);
        }
        if (isset($metrics['queue']['pending'])) {
            $this->recordMetric('queue_pending', $metrics['queue']['pending']);
        }
        if (isset($metrics['system']['memory_usage'])) {
            $this->recordMetric('memory_usage', $metrics['system']['memory_usage']);
        }
        if (isset($metrics['system']['load_average']['1min'])) {
            $this->recordMetric('load_average_1m', $metrics['system']['load_average']['1min']);
        }
    }

    /**
     * Create metrics table if not exists
     */
    private function createMetricsTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS {$this->metricsTable} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                metric_key VARCHAR(100) NOT NULL,
                metric_value DECIMAL(20, 6) NOT NULL,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_key_time (metric_key, recorded_at),
                INDEX idx_recorded (recorded_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Cleanup old metrics (keep last 30 days)
     */
    public function cleanupOldMetrics(int $daysToKeep = 30): int
    {
        if (!$this->db) {
            return 0;
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM {$this->metricsTable}
                WHERE recorded_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $daysToKeep]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            log_warning('Cleanup error', ['service' => 'PerformanceMetricsService', 'error' => $e->getMessage()]);
            return 0;
        }
    }
}
