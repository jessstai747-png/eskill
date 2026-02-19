<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Sistema de logs estruturados para auditoria e monitoramento
 */
class LoggingService
{
    private \PDO $db;
    private string $sessionId;
    private array $context = [];

    // Log levels
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_CRITICAL = 'CRITICAL';

    // Event categories
    public const CATEGORY_CATALOG_CLONE = 'CATALOG_CLONE';
    public const CATEGORY_PRICING = 'PRICING';
    public const CATEGORY_ML_API = 'ML_API';
    public const CATEGORY_SYSTEM = 'SYSTEM';
    public const CATEGORY_AUTH = 'AUTH';
    public const CATEGORY_MONITORING = 'MONITORING';

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->sessionId = session_id() ?: uniqid('log_');
        $this->ensureTable();
    }

    /**
     * Cria tabela de logs se não existir
     */
    private function ensureTable(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS system_logs (
                    id BIGINT PRIMARY KEY AUTO_INCREMENT,
                    session_id VARCHAR(50) NOT NULL,
                    level ENUM('DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    message TEXT NOT NULL,
                    context JSON,
                    user_id INT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    request_uri TEXT,
                    execution_time DECIMAL(10,4),
                    memory_usage INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_level (level),
                    INDEX idx_category (category),
                    INDEX idx_session (session_id),
                    INDEX idx_created (created_at),
                    INDEX idx_level_category (level, category)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (\Exception $e) {
            // Fallback para error_log se DB falhar na criação da tabela
            error_log("Erro ao criar tabela system_logs: " . $e->getMessage());
        }
    }

    /**
     * Define contexto global para logs
     */
    public function setContext(array $context): void
    {
        $this->context = array_merge($this->context, $context);
    }

    /**
     * Log genérico
     */
    public function log(string $level, string $category, string $message, array $context = []): void
    {
        try {
            $fullContext = array_merge($this->context, $context);
            
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (
                    session_id, level, category, message, context,
                    user_id, ip_address, user_agent, request_uri,
                    execution_time, memory_usage
                ) VALUES (
                    :session_id, :level, :category, :message, :context,
                    :user_id, :ip_address, :user_agent, :request_uri,
                    :execution_time, :memory_usage
                )
            ");

            $stmt->execute([
                'session_id' => $this->sessionId,
                'level' => $level,
                'category' => $category,
                'message' => $message,
                'context' => json_encode($fullContext),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'execution_time' => $this->getExecutionTime(),
                'memory_usage' => memory_get_usage(true)
            ]);

            // Log crítico também vai para arquivo
            if ($level === self::LEVEL_CRITICAL || $level === self::LEVEL_ERROR) {
                $this->logToFile($level, $category, $message, $fullContext);
            }

        } catch (\Exception $e) {
            // Fallback para log de arquivo se DB falhar
            $this->logToFile($level, $category, $message, $context);
            // Usar error_log como último recurso (não pode usar log_* aqui - risco de loop)
            error_log("Erro ao salvar log no banco: " . $e->getMessage());
        }
    }

    /**
     * Log de debug
     */
    public function debug(string $category, string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $category, $message, $context);
    }

    /**
     * Log de info
     */
    public function info(string $category, string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $category, $message, $context);
    }

    /**
     * Log de warning
     */
    public function warning(string $category, string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $category, $message, $context);
    }

    /**
     * Log de error
     */
    public function error(string $category, string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $category, $message, $context);
    }

    /**
     * Log de critical
     */
    public function critical(string $category, string $message, array $context = []): void
    {
        $this->log(self::LEVEL_CRITICAL, $category, $message, $context);
    }

    /**
     * Log específico para clonagem de catálogo
     */
    public function catalogClone(string $level, string $message, array $context = []): void
    {
        $this->log($level, self::CATEGORY_CATALOG_CLONE, $message, $context);
    }

    /**
     * Log específico para pricing
     */
    public function pricing(string $level, string $message, array $context = []): void
    {
        $this->log($level, self::CATEGORY_PRICING, $message, $context);
    }

    /**
     * Log específico para ML API
     */
    public function mlApi(string $level, string $message, array $context = []): void
    {
        $this->log($level, self::CATEGORY_ML_API, $message, $context);
    }

    /**
     * Log para arquivo (fallback)
     */
    private function logToFile(string $level, string $category, string $message, array $context = []): void
    {
        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/system_' . date('Y-m-d') . '.log';
        $logEntry = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $category,
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Calcula tempo de execução desde o início da request
     */
    private function getExecutionTime(): float
    {
        if (defined('REQUEST_START_TIME')) {
            return microtime(true) - (float)constant('REQUEST_START_TIME');
        }
        return 0.0;
    }

    /**
     * Busca logs com filtros
     */
    public function searchLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        try {
            $where = ['1=1'];
            $params = [];

            $limitSql = max(1, min($limit, 500));
            $offsetSql = max(0, $offset);

            if (!empty($filters['level'])) {
                $where[] = 'level = :level';
                $params['level'] = $filters['level'];
            }

            if (!empty($filters['category'])) {
                $where[] = 'category = :category';
                $params['category'] = $filters['category'];
            }

            if (!empty($filters['date_from'])) {
                $where[] = 'created_at >= :date_from';
                $params['date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $where[] = 'created_at <= :date_to';
                $params['date_to'] = $filters['date_to'];
            }

            if (!empty($filters['message'])) {
                $where[] = 'message LIKE :message';
                $params['message'] = '%' . $filters['message'] . '%';
            }

            if (!empty($filters['user_id'])) {
                $where[] = 'user_id = :user_id';
                $params['user_id'] = $filters['user_id'];
            }

            $sql = "
                SELECT * FROM system_logs 
                WHERE " . implode(' AND ', $where) . "
                ORDER BY created_at DESC
                LIMIT {$limitSql} OFFSET {$offsetSql}
            ";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            log_error('Erro ao buscar logs', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Estatísticas de logs por período
     */
    public function getLogStats(string $dateFrom, string $dateTo): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    level,
                    category,
                    COUNT(*) as count,
                    DATE(created_at) as log_date
                FROM system_logs 
                WHERE created_at BETWEEN :date_from AND :date_to
                GROUP BY level, category, DATE(created_at)
                ORDER BY log_date DESC, count DESC
            ");

            $stmt->execute([
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            log_error('Erro ao gerar estatísticas de logs', [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Limpa logs antigos
     */
    public function cleanOldLogs(int $daysToKeep = 30): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM system_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $daysToKeep]);
            
            $deletedRows = $stmt->rowCount();
            $this->info(self::CATEGORY_SYSTEM, "Logs antigos removidos", [
                'deleted_rows' => $deletedRows,
                'days_kept' => $daysToKeep
            ]);

            return $deletedRows;

        } catch (\Exception $e) {
            log_error('Erro ao limpar logs antigos', [
                'days_to_keep' => $daysToKeep,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Exporta logs para arquivo
     */
    public function exportLogs(array $filters = [], string $format = 'json'): string
    {
        $logs = $this->searchLogs($filters, 10000);
        $exportDir = __DIR__ . '/../../storage/exports';
        
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = 'logs_export_' . date('Y-m-d_H-i-s') . '.' . $format;
        $filepath = $exportDir . '/' . $filename;

        if ($format === 'json') {
            file_put_contents($filepath, json_encode($logs, JSON_PRETTY_PRINT));
        } elseif ($format === 'csv') {
            $fp = fopen($filepath, 'w');
            if (!empty($logs)) {
                fputcsv($fp, array_keys($logs[0]));
                foreach ($logs as $log) {
                    fputcsv($fp, $log);
                }
            }
            fclose($fp);
        }

        return $filepath;
    }

    /**
     * Alertas baseados em logs
     */
    public function checkLogAlerts(): array
    {
        $alerts = [];

        // Muitos erros na última hora
        $errorCount = $this->getRecentErrorCount(3600); // 1 hora
        if ($errorCount > 10) {
            $alerts[] = [
                'type' => 'HIGH_ERROR_RATE',
                'message' => "Muitos erros detectados: {$errorCount} na última hora",
                'severity' => 'HIGH'
            ];
        }

        // Muitos logs críticos
        $criticalCount = $this->getRecentCriticalCount(1800); // 30 min
        if ($criticalCount > 2) {
            $alerts[] = [
                'type' => 'CRITICAL_ERRORS',
                'message' => "Erros críticos detectados: {$criticalCount} nos últimos 30 min",
                'severity' => 'CRITICAL'
            ];
        }

        return $alerts;
    }

    /**
     * Conta erros recentes
     */
    private function getRecentErrorCount(int $seconds): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM system_logs 
                WHERE level IN ('ERROR', 'CRITICAL') 
                AND created_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
            ");
            $stmt->execute(['seconds' => $seconds]);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Conta logs críticos recentes
     */
    private function getRecentCriticalCount(int $seconds): int
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM system_logs 
                WHERE level = 'CRITICAL' 
                AND created_at > DATE_SUB(NOW(), INTERVAL :seconds SECOND)
            ");
            $stmt->execute(['seconds' => $seconds]);
            return (int)$stmt->fetchColumn();
        } catch (\Exception $e) {
            return 0;
        }
    }
}