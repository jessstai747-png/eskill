<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

use App\Database;
use PDO;

/**
 * AI Logging Service
 * 
 * Production-grade structured logging for AI operations.
 * Tracks all API calls, errors, performance metrics.
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class LoggingService
{
    private PDO $db;
    private ?int $accountId;
    private string $requestId;
    
    // Log levels
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    
    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->requestId = $this->generateRequestId();
        $this->ensureTableExists();
    }
    
    /**
     * Ensure logging table exists
     */
    private function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_logs (
                    id BIGINT PRIMARY KEY AUTO_INCREMENT,
                    request_id VARCHAR(36) NOT NULL,
                    account_id INT NULL,
                    level VARCHAR(20) NOT NULL,
                    category VARCHAR(50) NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    message TEXT NOT NULL,
                    context JSON NULL,
                    duration_ms INT NULL,
                    ai_provider VARCHAR(50) NULL,
                    ai_model VARCHAR(50) NULL,
                    tokens_used INT NULL,
                    cost DECIMAL(10,6) NULL,
                    error_code VARCHAR(50) NULL,
                    stack_trace TEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_request (request_id),
                    INDEX idx_account (account_id),
                    INDEX idx_level (level),
                    INDEX idx_category (category),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            log_error('Falha ao criar tabela ai_logs', [
                'service' => 'AI\\Core\\LoggingService',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Generate unique request ID
     */
    private function generateRequestId(): string
    {
        return sprintf('%s-%s',
            date('Ymd-His'),
            bin2hex(random_bytes(4))
        );
    }
    
    /**
     * Log an entry
     * 
     * @param string $level
     * @param string $category
     * @param string $action
     * @param string $message
     * @param array $context
     */
    public function log(string $level, string $category, string $action, string $message, array $context = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_logs 
                (request_id, account_id, level, category, action, message, context,
                 duration_ms, ai_provider, ai_model, tokens_used, cost, error_code, 
                 stack_trace, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->requestId,
                $this->accountId,
                $level,
                $category,
                $action,
                $message,
                json_encode($context),
                $context['duration_ms'] ?? null,
                $context['provider'] ?? null,
                $context['model'] ?? null,
                $context['tokens'] ?? null,
                $context['cost'] ?? null,
                $context['error_code'] ?? null,
                $context['stack_trace'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (\Exception $e) {
            // Fallback to error_log
            error_log("[AI-{$level}] {$category}::{$action} - {$message}");
        }
    }
    
    /**
     * Log debug message
     */
    public function debug(string $category, string $action, string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $category, $action, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info(string $category, string $action, string $message, array $context = []): void
    {
        $this->log(self::INFO, $category, $action, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning(string $category, string $action, string $message, array $context = []): void
    {
        $this->log(self::WARNING, $category, $action, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error(string $category, string $action, string $message, array $context = []): void
    {
        // Add stack trace if exception provided
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $context['error_code'] = $e->getCode();
            $context['stack_trace'] = $e->getTraceAsString();
            unset($context['exception']);
        }
        
        $this->log(self::ERROR, $category, $action, $message, $context);
    }
    
    /**
     * Log critical message
     */
    public function critical(string $category, string $action, string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $category, $action, $message, $context);
        
        // Also send to error log for immediate visibility
        error_log("[CRITICAL AI] {$category}::{$action} - {$message}");
    }
    
    /**
     * Log AI API call
     */
    public function logApiCall(string $provider, string $action, array $result, float $duration): void
    {
        $level = isset($result['error']) ? self::ERROR : self::INFO;
        
        $context = [
            'provider' => $provider,
            'model' => $result['model'] ?? 'unknown',
            'tokens' => $result['usage']['total_tokens'] ?? null,
            'cost' => $result['cost'] ?? null,
            'duration_ms' => round($duration * 1000),
        ];
        
        if (isset($result['error'])) {
            $context['error_code'] = $result['error'];
        }
        
        $message = isset($result['error']) 
            ? "API call failed: {$result['message']}"
            : "API call successful";
        
        $this->log($level, 'AI_API', $action, $message, $context);
    }
    
    /**
     * Log optimization result
     */
    public function logOptimization(string $itemId, string $type, array $result): void
    {
        $level = ($result['success'] ?? false) ? self::INFO : self::WARNING;
        
        $context = [
            'item_id' => $itemId,
            'type' => $type,
            'score_before' => $result['score_before'] ?? null,
            'score_after' => $result['score_after'] ?? null,
            'improvement' => $result['improvement'] ?? null,
            'duration_ms' => round(($result['duration'] ?? 0) * 1000),
            'provider' => $result['ai_provider'] ?? null,
            'cost' => $result['cost'] ?? null,
        ];
        
        $message = ($result['success'] ?? false)
            ? "Optimization completed with +" . ($result['improvement'] ?? 0) . " points improvement"
            : "Optimization failed: " . ($result['error'] ?? 'Unknown error');
        
        $this->log($level, 'OPTIMIZATION', $type, $message, $context);
    }
    
    /**
     * Get recent logs
     */
    public function getRecentLogs(int $limit = 100, ?string $level = null, ?string $category = null): array
    {
        try {
            $limitSql = max(1, min(500, (int)$limit));
            $sql = "SELECT * FROM ai_logs WHERE 1=1";
            $params = [];
            
            if ($this->accountId) {
                $sql .= " AND (account_id = ? OR account_id IS NULL)";
                $params[] = $this->accountId;
            }
            
            if ($level) {
                $sql .= " AND level = ?";
                $params[] = $level;
            }
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get error summary
     */
    public function getErrorSummary(int $hours = 24): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    category,
                    error_code,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence
                FROM ai_logs
                WHERE level IN ('error', 'critical')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY category, error_code
                ORDER BY count DESC
            ");
            
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(int $hours = 24): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ai_provider,
                    COUNT(*) as total_calls,
                    AVG(duration_ms) as avg_duration_ms,
                    SUM(tokens_used) as total_tokens,
                    SUM(cost) as total_cost,
                    SUM(CASE WHEN level = 'error' THEN 1 ELSE 0 END) as error_count
                FROM ai_logs
                WHERE category = 'AI_API'
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY ai_provider
            ");
            
            $stmt->execute([$hours]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get request ID
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }
}
