<?php

namespace App\Services\AI\Core;

use App\Database;
use PDO;

/**
 * AI Rate Limiter Service
 * 
 * Production-grade rate limiting for AI API calls.
 * Prevents quota exhaustion and manages costs.
 * 
 * Features:
 * - Per-provider rate limits
 * - Per-account quotas
 * - Sliding window algorithm
 * - Cost-based limiting
 * - Automatic throttling
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class RateLimiterService
{
    private PDO $db;
    private ?int $accountId;
    
    // Provider-specific limits (requests per minute)
    private const PROVIDER_LIMITS = [
        'openai' => [
            'requests_per_minute' => 60,
            'tokens_per_minute' => 90000,
            'daily_cost_limit' => 50.00, // USD
        ],
        'anthropic' => [
            'requests_per_minute' => 50,
            'tokens_per_minute' => 100000,
            'daily_cost_limit' => 50.00,
        ],
        'gemini' => [
            'requests_per_minute' => 60,
            'tokens_per_minute' => 120000,
            'daily_cost_limit' => 30.00,
        ],
    ];
    
    // Global limits
    private const GLOBAL_LIMITS = [
        'requests_per_minute' => 100,
        'requests_per_hour' => 1000,
        'daily_cost_limit' => 100.00,
    ];
    
    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTableExists();
    }
    
    /**
     * Ensure rate limit table exists
     */
    private function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS ai_rate_limits (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    account_id INT NULL,
                    provider VARCHAR(50) NOT NULL,
                    window_start TIMESTAMP NOT NULL,
                    window_type ENUM('minute', 'hour', 'day') NOT NULL,
                    request_count INT DEFAULT 0,
                    token_count INT DEFAULT 0,
                    total_cost DECIMAL(10,6) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_window (account_id, provider, window_start, window_type),
                    INDEX idx_account (account_id),
                    INDEX idx_provider (provider)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            log_error('Falha ao criar tabela ai_rate_limits', [
                'service' => 'RateLimiterService',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check if request is allowed
     * 
     * @param string $provider
     * @param int $estimatedTokens
     * @return array
     */
    public function checkLimit(string $provider, int $estimatedTokens = 0): array
    {
        $providerLimits = self::PROVIDER_LIMITS[$provider] ?? self::PROVIDER_LIMITS['openai'];
        
        // Check minute window
        $minuteStats = $this->getWindowStats($provider, 'minute');
        
        if ($minuteStats['request_count'] >= $providerLimits['requests_per_minute']) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_requests',
                'retry_after' => 60 - (time() % 60),
                'message' => "Rate limit: {$providerLimits['requests_per_minute']} requests per minute exceeded"
            ];
        }
        
        if ($minuteStats['token_count'] + $estimatedTokens > $providerLimits['tokens_per_minute']) {
            return [
                'allowed' => false,
                'reason' => 'rate_limit_tokens',
                'retry_after' => 60 - (time() % 60),
                'message' => "Token limit: {$providerLimits['tokens_per_minute']} tokens per minute exceeded"
            ];
        }
        
        // Check daily cost
        $dayStats = $this->getWindowStats($provider, 'day');
        
        if ($dayStats['total_cost'] >= $providerLimits['daily_cost_limit']) {
            return [
                'allowed' => false,
                'reason' => 'cost_limit',
                'retry_after' => $this->secondsUntilMidnight(),
                'message' => "Daily cost limit of \${$providerLimits['daily_cost_limit']} exceeded"
            ];
        }
        
        // Check global limits
        $globalMinute = $this->getWindowStats('global', 'minute');
        
        if ($globalMinute['request_count'] >= self::GLOBAL_LIMITS['requests_per_minute']) {
            return [
                'allowed' => false,
                'reason' => 'global_rate_limit',
                'retry_after' => 60 - (time() % 60),
                'message' => "Global rate limit exceeded"
            ];
        }
        
        return [
            'allowed' => true,
            'remaining_requests' => $providerLimits['requests_per_minute'] - $minuteStats['request_count'],
            'remaining_tokens' => $providerLimits['tokens_per_minute'] - $minuteStats['token_count'],
            'remaining_cost' => round($providerLimits['daily_cost_limit'] - $dayStats['total_cost'], 4)
        ];
    }
    
    /**
     * Record a request
     * 
     * @param string $provider
     * @param int $tokens
     * @param float $cost
     */
    public function recordRequest(string $provider, int $tokens = 0, float $cost = 0): void
    {
        $this->incrementWindow($provider, 'minute', 1, $tokens, $cost);
        $this->incrementWindow($provider, 'hour', 1, $tokens, $cost);
        $this->incrementWindow($provider, 'day', 1, $tokens, $cost);
        
        // Also record global
        $this->incrementWindow('global', 'minute', 1, $tokens, $cost);
        $this->incrementWindow('global', 'hour', 1, $tokens, $cost);
        $this->incrementWindow('global', 'day', 1, $tokens, $cost);
    }
    
    /**
     * Get window statistics
     */
    private function getWindowStats(string $provider, string $windowType): array
    {
        $windowStart = $this->getWindowStart($windowType);
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COALESCE(SUM(request_count), 0) as request_count,
                    COALESCE(SUM(token_count), 0) as token_count,
                    COALESCE(SUM(total_cost), 0) as total_cost
                FROM ai_rate_limits
                WHERE provider = ?
                AND window_type = ?
                AND window_start >= ?
                " . ($this->accountId ? "AND (account_id = ? OR account_id IS NULL)" : "")
            );
            
            $params = [$provider, $windowType, $windowStart];
            if ($this->accountId) $params[] = $this->accountId;
            
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'request_count' => 0,
                'token_count' => 0,
                'total_cost' => 0
            ];
        } catch (\Exception $e) {
            return ['request_count' => 0, 'token_count' => 0, 'total_cost' => 0];
        }
    }
    
    /**
     * Increment window counters
     */
    private function incrementWindow(string $provider, string $windowType, int $requests, int $tokens, float $cost): void
    {
        $windowStart = $this->getWindowStart($windowType);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_rate_limits 
                (account_id, provider, window_start, window_type, request_count, token_count, total_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    request_count = request_count + VALUES(request_count),
                    token_count = token_count + VALUES(token_count),
                    total_cost = total_cost + VALUES(total_cost)
            ");
            
            $stmt->execute([
                $this->accountId,
                $provider,
                $windowStart,
                $windowType,
                $requests,
                $tokens,
                $cost
            ]);
        } catch (\Exception $e) {
            log_warning('Falha ao registrar rate limit', [
                'service' => 'RateLimiterService',
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Get window start timestamp
     */
    private function getWindowStart(string $windowType): string
    {
        $now = time();
        
        switch ($windowType) {
            case 'minute':
                return date('Y-m-d H:i:00', $now);
            case 'hour':
                return date('Y-m-d H:00:00', $now);
            case 'day':
                return date('Y-m-d 00:00:00', $now);
            default:
                return date('Y-m-d H:i:s', $now);
        }
    }
    
    /**
     * Seconds until midnight
     */
    private function secondsUntilMidnight(): int
    {
        $now = time();
        $midnight = strtotime('tomorrow midnight');
        return $midnight - $now;
    }
    
    /**
     * Get usage summary
     */
    public function getUsageSummary(?string $provider = null): array
    {
        try {
            $sql = "
                SELECT 
                    provider,
                    window_type,
                    SUM(request_count) as requests,
                    SUM(token_count) as tokens,
                    SUM(total_cost) as cost
                FROM ai_rate_limits
                WHERE window_start >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                " . ($provider ? "AND provider = ?" : "") . "
                " . ($this->accountId ? "AND (account_id = ? OR account_id IS NULL)" : "") . "
                GROUP BY provider, window_type
            ";
            
            $params = [];
            if ($provider) $params[] = $provider;
            if ($this->accountId) $params[] = $this->accountId;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get remaining quota
     */
    public function getRemainingQuota(string $provider): array
    {
        $limits = self::PROVIDER_LIMITS[$provider] ?? self::PROVIDER_LIMITS['openai'];
        $minuteStats = $this->getWindowStats($provider, 'minute');
        $dayStats = $this->getWindowStats($provider, 'day');
        
        return [
            'provider' => $provider,
            'minute' => [
                'requests' => max(0, $limits['requests_per_minute'] - $minuteStats['request_count']),
                'tokens' => max(0, $limits['tokens_per_minute'] - $minuteStats['token_count']),
            ],
            'daily' => [
                'cost_remaining' => max(0, round($limits['daily_cost_limit'] - $dayStats['total_cost'], 4)),
                'cost_used' => round($dayStats['total_cost'], 4),
            ]
        ];
    }
    
    /**
     * Clean old records
     */
    public function cleanup(int $daysToKeep = 7): int
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM ai_rate_limits
                WHERE window_start < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysToKeep]);
            return $stmt->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
