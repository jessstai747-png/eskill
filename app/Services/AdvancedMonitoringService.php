<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Advanced System Monitoring Service V8.1
 * Real-time system monitoring with intelligent alerts
 */
class AdvancedMonitoringService
{
    private $db;
    private $alertThresholds;
    private $metricsHistory = [];

    public function __construct()
    {
        $this->db = \App\Database::getInstance();
        $this->initializeTables();
        $this->setDefaultThresholds();
    }

    /**
     * Initialize monitoring tables
     */
    private function initializeTables(): void
    {
        // System metrics table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                metric_type VARCHAR(50) NOT NULL,
                metric_name VARCHAR(100) NOT NULL,
                value DECIMAL(10,4) NOT NULL,
                unit VARCHAR(20) NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                server_id VARCHAR(50) DEFAULT 'main',
                INDEX idx_type_time (metric_type, timestamp),
                INDEX idx_name_time (metric_name, timestamp)
            )
        ");

        // System alerts table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                alert_type ENUM('critical', 'warning', 'info') NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                metric_name VARCHAR(100),
                threshold_value DECIMAL(10,4),
                current_value DECIMAL(10,4),
                status ENUM('active', 'acknowledged', 'resolved') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                acknowledged_at TIMESTAMP NULL,
                resolved_at TIMESTAMP NULL,
                INDEX idx_status_type (status, alert_type),
                INDEX idx_created (created_at)
            )
        ");

        // Performance logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS performance_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                endpoint VARCHAR(200) NOT NULL,
                method VARCHAR(10) NOT NULL,
                response_time DECIMAL(8,3) NOT NULL,
                memory_usage INT NOT NULL,
                status_code INT NOT NULL,
                user_id INT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_endpoint_time (endpoint, timestamp),
                INDEX idx_response_time (response_time),
                INDEX idx_timestamp (timestamp)
            )
        ");
    }

    /**
     * Set default alert thresholds
     */
    private function setDefaultThresholds(): void
    {
        $this->alertThresholds = [
            'cpu_usage' => ['warning' => 70, 'critical' => 85],
            'memory_usage' => ['warning' => 80, 'critical' => 90],
            'disk_usage' => ['warning' => 85, 'critical' => 95],
            'response_time' => ['warning' => 1000, 'critical' => 2000], // ms
            'error_rate' => ['warning' => 5, 'critical' => 10], // %
            'active_connections' => ['warning' => 1000, 'critical' => 1500],
            'queue_size' => ['warning' => 100, 'critical' => 500]
        ];
    }

    /**
     * Collect comprehensive system metrics
     */
    public function collectSystemMetrics(): array
    {
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'server_id' => gethostname() ?: 'unknown'
        ];

        // CPU Usage
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $metrics['cpu_usage'] = round($load[0] * 100 / $this->getCpuCoreCount(), 2);
        } else {
            $metrics['cpu_usage'] = $this->getCpuUsageWindows();
        }

        // Memory Usage
        $memoryTotal = $this->getTotalMemory();
        $memoryUsed = memory_get_usage(true);
        $metrics['memory_usage'] = round(($memoryUsed / $memoryTotal) * 100, 2);
        $metrics['memory_used_mb'] = round($memoryUsed / 1024 / 1024, 2);
        $metrics['memory_total_mb'] = round($memoryTotal / 1024 / 1024, 2);

        // Disk Usage
        $diskTotal = disk_total_space('.');
        $diskFree = disk_free_space('.');
        $diskUsed = $diskTotal - $diskFree;
        $metrics['disk_usage'] = round(($diskUsed / $diskTotal) * 100, 2);
        $metrics['disk_used_gb'] = round($diskUsed / 1024 / 1024 / 1024, 2);
        $metrics['disk_total_gb'] = round($diskTotal / 1024 / 1024 / 1024, 2);

        // Database Metrics
        $metrics = array_merge($metrics, $this->getDatabaseMetrics());

        // Application Metrics
        $metrics = array_merge($metrics, $this->getApplicationMetrics());

        // Performance Metrics
        $metrics = array_merge($metrics, $this->getPerformanceMetrics());

        // Store metrics
        $this->storeMetrics($metrics);

        // Check for alerts
        $this->checkAlerts($metrics);

        return $metrics;
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $metrics = [];

            // Active connections
            $stmt = $this->db->query("SHOW STATUS LIKE 'Threads_connected'");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metrics['db_connections'] = (int)($result['Value'] ?? 0);

            // Queries per second
            $stmt = $this->db->query("SHOW STATUS LIKE 'Queries'");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metrics['db_queries_total'] = (int)($result['Value'] ?? 0);

            // Slow queries
            $stmt = $this->db->query("SHOW STATUS LIKE 'Slow_queries'");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metrics['db_slow_queries'] = (int)($result['Value'] ?? 0);

            // Database size
            $stmt = $this->db->query("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metrics['db_size_mb'] = (float)($result['db_size_mb'] ?? 0);

            return $metrics;
        } catch (\Exception $e) {
            return [
                'db_connections' => 0,
                'db_queries_total' => 0,
                'db_slow_queries' => 0,
                'db_size_mb' => 0
            ];
        }
    }

    /**
     * Get application-specific metrics
     */
    private function getApplicationMetrics(): array
    {
        $metrics = [];

        try {
            // AI Operations count (last hour)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as ai_operations_hour 
                FROM system_metrics 
                WHERE metric_type = 'ai_operation' 
                AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $metrics['ai_operations_hour'] = (int)($result['ai_operations_hour'] ?? 0);

            // Active sessions
            if (session_status() === PHP_SESSION_ACTIVE) {
                $sessionDir = session_save_path() ?: sys_get_temp_dir();
                $sessionFiles = glob($sessionDir . '/sess_*');
                $metrics['active_sessions'] = count($sessionFiles ?: []);
            } else {
                $metrics['active_sessions'] = 0;
            }

            // Cache hit rate (if using cache)
            $metrics['cache_hit_rate'] = $this->getCacheHitRate();

            // Queue size (if using queues)
            $metrics['queue_size'] = $this->getQueueSize();
        } catch (\Exception $e) {
            $metrics = [
                'ai_operations_hour' => 0,
                'active_sessions' => 0,
                'cache_hit_rate' => 0,
                'queue_size' => 0
            ];
        }

        return $metrics;
    }

    /**
     * Get performance metrics (last 5 minutes)
     */
    public function getPerformanceMetrics(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    AVG(response_time) as avg_response_time,
                    MAX(response_time) as max_response_time,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count,
                    AVG(memory_usage) as avg_memory_usage
                FROM performance_logs 
                WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalRequests = (int)($result['total_requests'] ?? 0);
            $errorCount = (int)($result['error_count'] ?? 0);

            return [
                'total_requests_5min' => $totalRequests,
                'avg_response_time' => round((float)($result['avg_response_time'] ?? 0), 2),
                'max_response_time' => round((float)($result['max_response_time'] ?? 0), 2),
                'error_rate' => $totalRequests > 0 ? round(($errorCount / $totalRequests) * 100, 2) : 0,
                'avg_memory_usage_mb' => round((float)($result['avg_memory_usage'] ?? 0) / 1024 / 1024, 2)
            ];
        } catch (\Exception $e) {
            return [
                'total_requests_5min' => 0,
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'error_rate' => 0,
                'avg_memory_usage_mb' => 0
            ];
        }
    }

    /**
     * Store metrics in database
     */
    private function storeMetrics(array $metrics): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_metrics (metric_type, metric_name, value, unit, server_id) 
            VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($metrics as $name => $value) {
            if (in_array($name, ['timestamp', 'server_id'])) continue;

            // Safety check for DECIMAL(10,4) - Max 999999.9999
            if (is_numeric($value) && $value > 999999) {
                // Skip huge cumulative counters to avoid crash, or cap them
                continue;
            }

            $type = $this->getMetricType($name);
            $unit = $this->getMetricUnit($name);

            $stmt->execute([$type, $name, $value, $unit, $metrics['server_id']]);
        }
    }

    /**
     * Check for alerts based on thresholds
     */
    private function checkAlerts(array $metrics): void
    {
        foreach ($this->alertThresholds as $metricName => $thresholds) {
            if (!isset($metrics[$metricName])) continue;

            $value = $metrics[$metricName];

            // Check critical threshold
            if ($value >= $thresholds['critical']) {
                $this->createAlert('critical', $metricName, $value, $thresholds['critical']);
            }
            // Check warning threshold
            elseif ($value >= $thresholds['warning']) {
                $this->createAlert('warning', $metricName, $value, $thresholds['warning']);
            }
        }
    }

    /**
     * Create system alert
     */
    private function createAlert(string $type, string $metricName, float $currentValue, float $threshold): void
    {
        // Check if similar alert exists in last 10 minutes
        $stmt = $this->db->prepare("
            SELECT id FROM system_alerts 
            WHERE metric_name = ? AND alert_type = ? AND status = 'active'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            LIMIT 1
        ");
        $stmt->execute([$metricName, $type]);

        if ($stmt->fetch()) {
            return; // Don't create duplicate alerts
        }

        $title = $this->getAlertTitle($type, $metricName);
        $message = $this->getAlertMessage($metricName, $currentValue, $threshold);

        $stmt = $this->db->prepare("
            INSERT INTO system_alerts (alert_type, title, message, metric_name, threshold_value, current_value) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$type, $title, $message, $metricName, $threshold, $currentValue]);

        // Send notification if critical
        if ($type === 'critical') {
            $this->sendCriticalAlert($title, $message);
        }
    }

    /**
     * Get system health status
     */
    public function getHealthStatus(): array
    {
        $metrics = $this->collectSystemMetrics();

        $healthScore = 100;
        $issues = [];

        // Check each metric against thresholds
        foreach ($this->alertThresholds as $metricName => $thresholds) {
            if (!isset($metrics[$metricName])) continue;

            $value = $metrics[$metricName];

            if ($value >= $thresholds['critical']) {
                $healthScore -= 20;
                $issues[] = [
                    'level' => 'critical',
                    'metric' => $metricName,
                    'value' => $value,
                    'message' => "Critical: {$metricName} is at {$value}% (threshold: {$thresholds['critical']}%)"
                ];
            } elseif ($value >= $thresholds['warning']) {
                $healthScore -= 10;
                $issues[] = [
                    'level' => 'warning',
                    'metric' => $metricName,
                    'value' => $value,
                    'message' => "Warning: {$metricName} is at {$value}% (threshold: {$thresholds['warning']}%)"
                ];
            }
        }

        $healthScore = max(0, $healthScore);

        return [
            'health_score' => $healthScore,
            'status' => $this->getHealthStatusLevel($healthScore),
            'issues' => $issues,
            'metrics' => $metrics,
            'active_alerts' => $this->getActiveAlerts(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get active alerts
     */
    public function getActiveAlerts(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM system_alerts 
            WHERE status = 'active' 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Log request performance
     */
    public function logRequestPerformance(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO performance_logs (endpoint, method, response_time, memory_usage, status_code, user_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['endpoint'] ?? '',
            $data['method'] ?? 'GET',
            $data['response_time'] ?? 0,
            $data['memory_usage'] ?? memory_get_usage(true),
            $data['status_code'] ?? 200,
            $data['user_id'] ?? null,
            $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
            $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }

    /**
     * Get performance analytics
     */
    public function getPerformanceAnalytics(string $timeframe = '1 hour'): array
    {
        // Parse timefram (e.g., '1 hour', '24 hour') or default to 1 HOUR
        // Safe mapping to prevent injection
        $intervalMap = [
            '1 hour' => '1 HOUR',
            '6 hour' => '6 HOUR',
            '12 hour' => '12 HOUR',
            '24 hour' => '24 HOUR',
            '7 day' => '7 DAY',
            '30 day' => '30 DAY'
        ];

        $interval = $intervalMap[$timeframe] ?? '1 HOUR';

        $sql = "SELECT 
                endpoint,
                COUNT(*) as request_count,
                AVG(response_time) as avg_response_time,
                MIN(response_time) as min_response_time,
                MAX(response_time) as max_response_time,
                SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count
            FROM performance_logs 
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL $interval)
            GROUP BY endpoint
            ORDER BY request_count DESC
            LIMIT 20";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Helper methods
     */
    private function getCpuCoreCount(): int
    {
        if (is_file('/proc/cpuinfo')) {
            return (int)shell_exec('nproc') ?: 1;
        }
        return 1;
    }

    private function getCpuUsageWindows(): float
    {
        // Try COM object if available, otherwise return 0 (better than random noise)
        if (class_exists('COM')) {
            try {
                $wmi = new \COM("winmgmts:\\\\.");
                $cpus = $wmi->InstancesOf("Win32_Processor");
                foreach ($cpus as $cpu) {
                    return (float)$cpu->LoadPercentage;
                }
            } catch (\Exception $e) {
                log_debug('WMI CPU query failed', ['error' => $e->getMessage()]);
            }
        }
        return 0.0;
    }

    private function getTotalMemory(): int
    {
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo && preg_match('/MemTotal:\s+(\d+)/', $memInfo, $matches)) {
            return (int)$matches[1] * 1024; // Convert KB to bytes
        }
        return 8 * 1024 * 1024 * 1024; // Default 8GB
    }

    private function getCacheHitRate(): float
    {
        // Calculate based on existing metrics since Redis stats might be unavailable
        // Assuming we track cache_hits and cache_misses counters somewhere, or return 0
        return 0.0;
    }

    private function getQueueSize(): int
    {
        // Check standard 'jobs' table for pending jobs
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM jobs 
                WHERE available_at <= UNIX_TIMESTAMP(NOW())
            ");
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getMetricType(string $name): string
    {
        $systemMetrics = ['cpu_usage', 'memory_usage', 'disk_usage'];
        $databaseMetrics = ['db_connections', 'db_queries_total', 'db_slow_queries', 'db_size_mb'];
        $performanceMetrics = ['avg_response_time', 'max_response_time', 'error_rate'];

        if (in_array($name, $systemMetrics)) return 'system';
        if (in_array($name, $databaseMetrics)) return 'database';
        if (in_array($name, $performanceMetrics)) return 'performance';

        return 'application';
    }

    private function getMetricUnit(string $name): string
    {
        $percentageMetrics = ['cpu_usage', 'memory_usage', 'disk_usage', 'error_rate', 'cache_hit_rate'];
        $timeMetrics = ['avg_response_time', 'max_response_time'];
        $mbMetrics = ['memory_used_mb', 'memory_total_mb', 'db_size_mb', 'avg_memory_usage_mb'];
        $gbMetrics = ['disk_used_gb', 'disk_total_gb'];

        if (in_array($name, $percentageMetrics)) return '%';
        if (in_array($name, $timeMetrics)) return 'ms';
        if (in_array($name, $mbMetrics)) return 'MB';
        if (in_array($name, $gbMetrics)) return 'GB';

        return 'count';
    }

    private function getAlertTitle(string $type, string $metricName): string
    {
        $titles = [
            'cpu_usage' => 'High CPU Usage',
            'memory_usage' => 'High Memory Usage',
            'disk_usage' => 'Low Disk Space',
            'response_time' => 'Slow Response Time',
            'error_rate' => 'High Error Rate'
        ];

        $prefix = ucfirst($type) . ': ';
        return $prefix . ($titles[$metricName] ?? ucwords(str_replace('_', ' ', $metricName)));
    }

    private function getAlertMessage(string $metricName, float $currentValue, float $threshold): string
    {
        $unit = $this->getMetricUnit($metricName);
        return "The {$metricName} has reached {$currentValue}{$unit}, which exceeds the threshold of {$threshold}{$unit}. Immediate attention required.";
    }

    private function getHealthStatusLevel(int $score): string
    {
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'fair';
        if ($score >= 60) return 'poor';
        return 'critical';
    }

    private function sendCriticalAlert(string $title, string $message): void
    {
        // Implement your notification system here
        // Could be email, Slack, webhook, etc.
        log_critical('Alerta crítico de monitoramento', [
            'alert_title' => $title,
            'alert_message' => $message,
        ]);
    }

    /**
     * Clean old metrics data
     */
    public function cleanOldMetrics(int $daysToKeep = 30): int
    {
        $totalDeleted = 0;
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));

        try {
            // Clean system_metrics
            $stmt = $this->db->prepare("DELETE FROM system_metrics WHERE timestamp < :cutoff");
            $stmt->execute(['cutoff' => $cutoffDate]);
            $totalDeleted += $stmt->rowCount();

            // Clean performance_logs
            $stmt = $this->db->prepare("DELETE FROM performance_logs WHERE timestamp < :cutoff");
            $stmt->execute(['cutoff' => $cutoffDate]);
            $totalDeleted += $stmt->rowCount();

            // Clean resolved/old alerts
            $stmt = $this->db->prepare("DELETE FROM system_alerts WHERE status = 'resolved' AND resolved_at < :cutoff");
            $stmt->execute(['cutoff' => $cutoffDate]);
            $totalDeleted += $stmt->rowCount();
        } catch (\Exception $e) {
            log_error('Falha ao limpar métricas antigas', [
                'days_to_keep' => $daysToKeep,
                'error' => $e->getMessage(),
            ]);
        }

        return $totalDeleted;
    }
}
