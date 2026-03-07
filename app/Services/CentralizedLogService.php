<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use Exception;
use PDO;

/**
 * Centralized Logging Service V8.1
 * Advanced logging system with categorization, filtering and analytics
 */
class CentralizedLogService
{
    private PDO $db;
    private string $logPath;
    private array $config;
    private array $logLevels = [
        'emergency' => 0,
        'alert' => 1,
        'critical' => 2,
        'error' => 3,
        'warning' => 4,
        'notice' => 5,
        'info' => 6,
        'debug' => 7
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->logPath = __DIR__ . '/../../storage/logs';
        $this->config = [
            'max_file_size' => $_ENV['LOG_MAX_FILE_SIZE'] ?? 10485760, // 10MB
            'retention_days' => $_ENV['LOG_RETENTION_DAYS'] ?? 30,
            'rotation_enabled' => $_ENV['LOG_ROTATION_ENABLED'] ?? true,
            'database_logging' => $_ENV['LOG_DATABASE_ENABLED'] ?? true,
            'real_time_alerts' => $_ENV['LOG_REALTIME_ALERTS'] ?? true
        ];

        // Ensure log directory exists
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }

        $this->ensureLogTable();
    }

    /**
     * Log message with advanced categorization
     */
    public function log(string $level, string $message, array $context = []): bool {
        try {
            $logEntry = [
                'level' => $level,
                'message' => $message,
                'context' => $context,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'user_id' => $_SESSION['user_id'] ?? null,
                'request_id' => $this->getRequestId(),
                'memory_usage' => memory_get_usage(true),
                'execution_time' => $this->getExecutionTime()
            ];

            // Categorize log entry
            $category = $this->categorizeLog($message, $context);
            $logEntry['category'] = $category;

            // Add stack trace for errors
            if (in_array($level, ['error', 'critical', 'alert', 'emergency'])) {
                $logEntry['stack_trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            }

            // Write to file
            $this->writeToFile($level, $logEntry);

            // Write to database if enabled
            if ($this->config['database_logging']) {
                $this->writeToDatabase($logEntry);
            }

            // Send real-time alerts for critical logs
            if ($this->config['real_time_alerts'] && $this->shouldAlert($level, $category)) {
                $this->sendRealTimeAlert($logEntry);
            }

            return true;

        } catch (Exception $e) {
            error_log("Logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log API requests
     */
    public function logApiRequest(array $request, array $response, float $duration): void {
        $this->log('info', 'API Request', [
            'type' => 'api_request',
            'endpoint' => $request['endpoint'] ?? '',
            'method' => $request['method'] ?? 'GET',
            'parameters' => $request['parameters'] ?? [],
            'response_code' => $response['code'] ?? 200,
            'response_size' => $response['size'] ?? 0,
            'duration' => $duration,
            'success' => ($response['code'] ?? 200) < 400
        ]);
    }

    /**
     * Log user actions
     */
    public function logUserAction(string $action, array $details = []): void {
        $this->log('info', "User Action: {$action}", [
            'type' => 'user_action',
            'action' => $action,
            'details' => $details,
            'session_id' => session_id(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    }

    /**
     * Log system events
     */
    public function logSystemEvent(string $event, array $data = []): void {
        $this->log('notice', "System Event: {$event}", [
            'type' => 'system_event',
            'event' => $event,
            'data' => $data,
            'server_load' => sys_getloadavg(),
            'disk_usage' => $this->getDiskUsage()
        ]);
    }

    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, string $level = 'warning', array $details = []): void {
        $this->log($level, "Security Event: {$event}", [
            'type' => 'security',
            'event' => $event,
            'details' => $details,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'country' => $this->getCountryFromIp($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'headers' => $this->getSecurityHeaders()
        ]);
    }

    /**
     * Get logs with advanced filtering
     */
    public function getLogs(array $filters = [], int $limit = 100, int $offset = 0): array {
        try {
            $sql = "SELECT * FROM system_logs WHERE 1=1";
            $params = [];

            $limitSql = max(1, min($limit, 500));
            $offsetSql = max(0, $offset);

            // Apply filters
            if (isset($filters['level'])) {
                $sql .= " AND level = :level";
                $params['level'] = $filters['level'];
            }

            if (isset($filters['category'])) {
                $sql .= " AND category = :category";
                $params['category'] = $filters['category'];
            }

            if (isset($filters['user_id'])) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $filters['user_id'];
            }

            if (isset($filters['start_date'])) {
                $sql .= " AND created_at >= :start_date";
                $params['start_date'] = $filters['start_date'];
            }

            if (isset($filters['end_date'])) {
                $sql .= " AND created_at <= :end_date";
                $params['end_date'] = $filters['end_date'];
            }

            if (isset($filters['search'])) {
                $sql .= " AND (message LIKE :search OR context LIKE :search)";
                $params['search'] = '%' . $filters['search'] . '%';
            }

            $sql .= " ORDER BY created_at DESC LIMIT {$limitSql} OFFSET {$offsetSql}";

            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Parse context JSON
            foreach ($logs as &$log) {
                $log['context'] = json_decode($log['context'], true) ?? [];
            }

            return $logs;

        } catch (Exception $e) {
            error_log("Get logs failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get log analytics
     */
    public function getAnalytics(string $period = '24h'): array {
        try {
            $intervals = [
                '1h' => '1 HOUR',
                '24h' => '24 HOUR',
                '7d' => '7 DAY',
                '30d' => '30 DAY'
            ];

            $interval = $intervals[$period] ?? '24 HOUR';

            // Log levels distribution
            $stmt = $this->db->prepare("
                SELECT level, COUNT(*) as count 
                FROM system_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                GROUP BY level
                ORDER BY count DESC
            ");
            $stmt->execute();
            $levelStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Categories distribution
            $stmt = $this->db->prepare("
                SELECT category, COUNT(*) as count 
                FROM system_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                GROUP BY category
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Hourly distribution
            $stmt = $this->db->prepare("
                SELECT HOUR(created_at) as hour, COUNT(*) as count
                FROM system_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                GROUP BY HOUR(created_at)
                ORDER BY hour
            ");
            $stmt->execute();
            $hourlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Top error messages
            $stmt = $this->db->prepare("
                SELECT message, COUNT(*) as count
                FROM system_logs 
                WHERE level IN ('error', 'critical', 'alert', 'emergency')
                AND created_at >= DATE_SUB(NOW(), INTERVAL {$interval})
                GROUP BY message
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $errorStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'period' => $period,
                'levels' => $levelStats,
                'categories' => $categoryStats,
                'hourly' => $hourlyStats,
                'top_errors' => $errorStats,
                'total_logs' => array_sum(array_column($levelStats, 'count'))
            ];

        } catch (Exception $e) {
            error_log("Log analytics failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cleanup old logs
     */
    public function cleanup(): array {
        $result = [
            'files_cleaned' => 0,
            'db_records_cleaned' => 0,
            'space_freed' => 0
        ];

        try {
            // Cleanup database logs
            if ($this->config['database_logging']) {
                $stmt = $this->db->prepare("
                    DELETE FROM system_logs 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL :retention_days DAY)
                ");
                $stmt->bindValue(':retention_days', $this->config['retention_days'], PDO::PARAM_INT);
                $stmt->execute();
                $result['db_records_cleaned'] = $stmt->rowCount();
            }

            // Cleanup log files
            $cutoffTime = time() - ($this->config['retention_days'] * 24 * 3600);
            $files = glob($this->logPath . '/*.log');

            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    $size = filesize($file);
                    if (unlink($file)) {
                        $result['files_cleaned']++;
                        $result['space_freed'] += $size;
                    }
                }
            }

            // Rotate large files if enabled
            if ($this->config['rotation_enabled']) {
                $this->rotateLargeFiles();
            }

        } catch (Exception $e) {
            error_log("Log cleanup failed: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Categorize log entry
     */
    private function categorizeLog(string $message, array $context): string {
        $type = $context['type'] ?? null;
        
        if ($type) {
            return $type;
        }

        // Automatic categorization based on message content
        if (str_contains($message, 'SQL') || str_contains($message, 'database')) {
            return 'database';
        }
        
        if (str_contains($message, 'API') || str_contains($message, 'HTTP')) {
            return 'api';
        }
        
        if (str_contains($message, 'Auth') || str_contains($message, 'Login')) {
            return 'auth';
        }
        
        if (str_contains($message, 'Security') || str_contains($message, 'Suspicious')) {
            return 'security';
        }

        if (str_contains($message, 'Performance') || str_contains($message, 'Slow')) {
            return 'performance';
        }

        return 'general';
    }

    /**
     * Write log to file
     */
    private function writeToFile(string $level, array $logEntry): void {
        $filename = $this->logPath . DIRECTORY_SEPARATOR . "{$level}.log";
        
        // Check file size for rotation
        if ($this->config['rotation_enabled'] && file_exists($filename) && 
            filesize($filename) > $this->config['max_file_size']) {
            $this->rotateFile($filename);
        }

        $logLine = sprintf(
            "[%s] [%s] %s %s\n",
            $logEntry['timestamp'],
            strtoupper($level),
            $logEntry['message'],
            json_encode($logEntry['context'])
        );

        file_put_contents($filename, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Write log to database
     */
    private function writeToDatabase(array $logEntry): void {
        $stmt = $this->db->prepare("
            INSERT INTO system_logs (
                level, message, context, category, request_id,
                user_id, ip, user_agent, memory_usage, execution_time,
                created_at
            ) VALUES (
                :level, :message, :context, :category, :request_id,
                :user_id, :ip, :user_agent, :memory_usage, :execution_time,
                :created_at
            )
        ");

        $stmt->execute([
            'level' => $logEntry['level'],
            'message' => $logEntry['message'],
            'context' => json_encode($logEntry['context']),
            'category' => $logEntry['category'],
            'request_id' => $logEntry['request_id'],
            'user_id' => $logEntry['user_id'],
            'ip' => $logEntry['ip'],
            'user_agent' => $logEntry['user_agent'],
            'memory_usage' => $logEntry['memory_usage'],
            'execution_time' => $logEntry['execution_time'],
            'created_at' => $logEntry['timestamp']
        ]);
    }

    /**
     * Ensure log table exists
     */
    private function ensureLogTable(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS system_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) NOT NULL,
                message TEXT NOT NULL,
                context JSON,
                category VARCHAR(50) NOT NULL,
                request_id VARCHAR(50),
                user_id INT,
                ip VARCHAR(45),
                user_agent TEXT,
                memory_usage BIGINT,
                execution_time FLOAT,
                created_at DATETIME NOT NULL,
                INDEX idx_level (level),
                INDEX idx_category (category),
                INDEX idx_user_id (user_id),
                INDEX idx_created_at (created_at)
            )
        ");
    }

    /**
     * Get unique request ID
     */
    private function getRequestId(): string {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = uniqid('req_', true);
        }
        
        return $requestId;
    }

    /**
     * Get execution time
     */
    private function getExecutionTime(): float {
        static $startTime = null;
        
        if ($startTime === null) {
            $startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        }
        
        return microtime(true) - $startTime;
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        return [
            'total' => $total,
            'free' => $free,
            'used' => $total - $free,
            'usage_percent' => round((($total - $free) / $total) * 100, 2)
        ];
    }

    /**
     * Decide se um log deve gerar alerta em tempo real
     */
    private function shouldAlert(string $level, string $category): bool
    {
        if (in_array($level, ['emergency', 'alert', 'critical'], true)) {
            return true;
        }

        return $level === 'error' && $category === 'security';
    }

    /**
     * Envia alerta em tempo real (fallback: error_log)
     */
    private function sendRealTimeAlert(array $logEntry): void
    {
        $alert = sprintf(
            '[REALTIME_ALERT] [%s] %s | category=%s | request_id=%s',
            strtoupper((string)($logEntry['level'] ?? 'unknown')),
            (string)($logEntry['message'] ?? ''),
            (string)($logEntry['category'] ?? 'general'),
            (string)($logEntry['request_id'] ?? 'n/a')
        );

        error_log($alert);
    }

    /**
     * Resolve país por IP (placeholder seguro)
     */
    private function getCountryFromIp(string $ip): string
    {
        if ($ip === '' || $ip === 'unknown') {
            return 'unknown';
        }

        if (str_starts_with($ip, '127.') || $ip === '::1') {
            return 'local';
        }

        return 'unknown';
    }

    /**
     * Coleta subset de headers de segurança
     */
    private function getSecurityHeaders(): array
    {
        $headers = [];
        $server = $_SERVER;

        $tracked = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_PROTO',
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CF_RAY',
            'HTTP_SEC_FETCH_SITE',
            'HTTP_SEC_FETCH_MODE',
        ];

        foreach ($tracked as $key) {
            if (isset($server[$key])) {
                $headers[$key] = (string)$server[$key];
            }
        }

        return $headers;
    }

    /**
     * Rotaciona arquivos de log que ultrapassaram limite
     */
    private function rotateLargeFiles(): void
    {
        $files = glob($this->logPath . '/*.log');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $size = filesize($file);
            if ($size !== false && $size > (int)$this->config['max_file_size']) {
                $this->rotateFile($file);
            }
        }
    }

    /**
     * Rotaciona arquivo único de log
     */
    private function rotateFile(string $filename): void
    {
        if (!file_exists($filename)) {
            return;
        }

        $rotated = sprintf('%s.%s', $filename, date('Ymd_His'));
        @rename($filename, $rotated);
        @touch($filename);
    }
}