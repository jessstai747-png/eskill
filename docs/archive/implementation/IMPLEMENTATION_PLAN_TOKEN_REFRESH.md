# Mercado Livre Token Refresh - Comprehensive Implementation Plan
**Created**: 2026-02-08  
**Duration**: 30 Days  
**Priority**: Critical Production Issue

## Executive Summary

This plan addresses critical issues with Mercado Livre token refresh mechanisms through:
1. **IP Whitelisting Resolution** - ML DevCenter application eligibility and IP submission
2. **Token Refresh Consolidation** - Elimination of duplicate scripts and unified architecture
3. **Production-Ready Monitoring** - File locking, alerts, and comprehensive logging
4. **Zero-Downtime Deployment** - Phased rollout with rollback procedures

## Phase 1: ML DevCenter IP Whitelisting Preparation (Days 1-7)

### Day 1: Application Eligibility Assessment
**Objective**: Verify current application status for IP management

**Tasks**:
```bash
# Check current application credentials
grep -E "(APP_ID|CLIENT_ID)" .env

# Verify current server IP
curl -s ifconfig.me
# Expected: 72.62.14.91
```

**Deliverables**:
- Application eligibility report
- Current IP verification documentation
- DevCenter access confirmation

### Day 2-3: Technical Documentation Preparation
**Files to Create**:
```
/docs/ip_whitelisting_technical_specs.md
/docs/proxy_configuration_fallback.md
```

**Content Requirements**:
- IP range justification documentation
- Technical architecture diagrams
- Fallback proxy specifications
- Security compliance documentation

### Day 4-5: IP Range Submission Package
**IP Information**:
- **Primary IP**: 72.62.14.91/32
- **Purpose**: OAuth token refresh operations
- **Protocol**: HTTPS (443), HTTP (80)
- **Justification**: Production API access for token management

**Submission Format** (CSV):
```csv
72.62.14.91/32,Production OAuth Token Refresh,HTTPS/HTTP
```

### Day 6-7: Fallback Proxy Configuration
**Environment Variables to Add**:
```bash
# .env additions
ML_PROXY_ENABLED=false
ML_PROXY_TYPE=http
ML_PROXY_HOST=backup-proxy.example.com
ML_PROXY_PORT=8080
ML_PROXY_USER=
ML_PROXY_PASS=
```

**Proxy Service Options**:
- Primary: Direct IP access (72.62.14.91)
- Fallback: Cloud-based proxy service
- Emergency: Multi-region proxy rotation

## Phase 2: Token Refresh System Consolidation (Days 8-15)

### Day 8: Current System Analysis
**Identified Duplicate Scripts**:
1. `/scripts/renew_tokens.php` - Legacy script (100 lines)
2. `/scripts/refresh_ml_tokens.php` - CLI interface (89 lines)  
3. `/scripts/cron_refresh_tokens.php` - Cron wrapper (54 lines)
4. `/app/Jobs/TokenRefreshJob.php` - Main implementation (197 lines)

**Issues Found**:
- Multiple entry points causing race conditions
- No file locking mechanisms
- Different refresh intervals (2h vs 3h)
- Inconsistent error handling

### Day 9-10: UnifiedTokenRefreshService Design
**Architecture**:
```php
// New service structure
app/Services/UnifiedTokenRefreshService.php
├── File locking mechanism
├── Rate limiting
├── Centralized configuration
├── Monitoring integration
└── Error recovery
```

**Key Features**:
```php
class UnifiedTokenRefreshService {
    private const LOCK_FILE = '/storage/token_refresh.lock';
    private const LOCK_TIMEOUT = 300; // 5 minutes
    private const MAX_CONCURRENT = 1;
    
    // Methods
    public function executeRefresh(bool $forceAll = false): array;
    public function refreshAccount(int $accountId): bool;
    public function getRefreshStatus(): array;
    private function acquireLock(): bool;
    private function releaseLock(): void;
}
```

### Day 11-12: Database Schema Updates
**Migration File**: `database/migrations/2026_02_08_token_refresh_optimization.sql`

```sql
-- Add refresh tracking fields
ALTER TABLE ml_accounts 
ADD COLUMN IF NOT EXISTS last_refresh_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS refresh_failure_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS last_refresh_error TEXT NULL,
ADD COLUMN IF NOT EXISTS refresh_locked_until DATETIME NULL,
ADD INDEX IF NOT EXISTS idx_last_refresh (last_refresh_at),
ADD INDEX IF NOT EXISTS idx_refresh_locked (refresh_locked_until);

-- Create refresh audit table
CREATE TABLE IF NOT EXISTS token_refresh_audit (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    action ENUM('refresh_success', 'refresh_failed', 'lock_acquired', 'lock_released') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
    INDEX idx_account_action (account_id, action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Day 13-15: Service Implementation
**File**: `app/Services/UnifiedTokenRefreshService.php`

```php
<?php

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreAuthService;
use App\Services\StructuredLogService;
use PDO;

class UnifiedTokenRefreshService
{
    private PDO $db;
    private MercadoLivreAuthService $authService;
    private StructuredLogService $logger;
    private string $lockFile;
    
    // Configuration from environment
    private int $refreshMarginMinutes;
    private int $maxRetries;
    private int $rateLimitDelayMs;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->authService = new MercadoLivreAuthService();
        $this->logger = new StructuredLogService();
        $this->lockFile = storage_path('token_refresh.lock');
        
        // Load configuration
        $this->refreshMarginMinutes = (int)($_ENV['TOKEN_REFRESH_MARGIN_MINUTES'] ?? 120);
        $this->maxRetries = (int)($_ENV['TOKEN_REFRESH_MAX_RETRIES'] ?? 3);
        $this->rateLimitDelayMs = (int)($_ENV['ML_API_RATE_DELAY_MS'] ?? 500);
    }
    
    /**
     * Execute token refresh with file locking and comprehensive error handling
     */
    public function executeRefresh(bool $forceAll = false): array
    {
        // Prevent concurrent execution
        if (!$this->acquireLock()) {
            $this->logger->warning('Token refresh already running', [
                'lock_file' => $this->lockFile,
                'pid' => getmypid()
            ]);
            return [
                'status' => 'skipped',
                'reason' => 'Another refresh process is running',
                'lock_file' => $this->lockFile
            ];
        }
        
        try {
            $results = [
                'started_at' => date('Y-m-d H:i:s'),
                'mode' => $forceAll ? 'force_all' : 'expiring_only',
                'accounts_checked' => 0,
                'tokens_refreshed' => 0,
                'tokens_failed' => 0,
                'already_expired' => 0,
                'locked_accounts' => 0,
                'details' => [],
            ];
            
            // Get accounts needing refresh
            $accounts = $this->getAccountsNeedingRefresh($forceAll);
            $results['accounts_checked'] = count($accounts);
            
            foreach ($accounts as $account) {
                $result = $this->processAccountRefresh($account);
                $this->updateResults($results, $result);
                
                // Rate limiting between requests
                usleep($this->rateLimitDelayMs * 1000);
            }
            
            $results['finished_at'] = date('Y-m-d H:i:s');
            $this->logSummary($results);
            
            return $results;
            
        } finally {
            $this->releaseLock();
        }
    }
    
    /**
     * Get accounts that need token refresh
     */
    private function getAccountsNeedingRefresh(bool $forceAll): array
    {
        $bufferTime = date('Y-m-d H:i:s', time() + ($this->refreshMarginMinutes * 60));
        
        if ($forceAll) {
            $sql = "
                SELECT id, nickname, ml_user_id, token_expires_at, status,
                       last_refresh_at, refresh_failure_count
                FROM ml_accounts
                WHERE refresh_token IS NOT NULL
                AND refresh_token != ''
                AND (refresh_locked_until IS NULL OR refresh_locked_until < NOW())
                ORDER BY token_expires_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "
                SELECT id, nickname, ml_user_id, token_expires_at, status,
                       last_refresh_at, refresh_failure_count
                FROM ml_accounts
                WHERE (
                    token_expires_at <= :buffer_time
                    OR status = 'expired'
                )
                AND refresh_token IS NOT NULL
                AND refresh_token != ''
                AND (refresh_locked_until IS NULL OR refresh_locked_until < NOW())
                ORDER BY token_expires_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['buffer_time' => $bufferTime]);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process refresh for a single account
     */
    private function processAccountRefresh(array $account): array
    {
        $accountId = (int)$account['id'];
        $result = [
            'account_id' => $accountId,
            'nickname' => $account['nickname'],
            'status_before' => $account['status'],
        ];
        
        // Check if account is temporarily locked due to repeated failures
        if ($account['refresh_failure_count'] >= 5) {
            $result['result'] = 'locked';
            $result['reason'] = 'Account locked due to repeated failures';
            return $result;
        }
        
        // Skip if expired too long (refresh_token likely invalid)
        $secondsExpired = time() - strtotime($account['token_expires_at'] ?? 'now');
        if ($secondsExpired > (30 * 24 * 3600)) {
            $result['result'] = 'skipped';
            $result['reason'] = 'Token expired > 30 days - manual reconnection required';
            return $result;
        }
        
        // Attempt refresh
        try {
            $this->markAccountRefreshAttempt($accountId);
            
            if ($this->authService->refreshToken($accountId, $this->maxRetries)) {
                $this->markAccountRefreshSuccess($accountId);
                $result['result'] = 'success';
                $result['status_after'] = 'active';
            } else {
                $this->markAccountRefreshFailure($accountId, 'Refresh token invalid');
                $result['result'] = 'failed';
                $result['reason'] = 'Refresh token invalid or expired';
            }
        } catch (\Throwable $e) {
            $this->markAccountRefreshFailure($accountId, $e->getMessage());
            $result['result'] = 'error';
            $result['reason'] = $e->getMessage();
        }
        
        return $result;
    }
    
    /**
     * File locking mechanism to prevent concurrent execution
     */
    private function acquireLock(): bool
    {
        if (file_exists($this->lockFile)) {
            $lockTime = filemtime($this->lockFile);
            if (time() - $lockTime < 300) { // 5 minutes
                return false;
            }
        }
        
        $lockData = json_encode([
            'pid' => getmypid(),
            'started_at' => date('Y-m-d H:i:s'),
            'hostname' => gethostname()
        ]);
        
        return file_put_contents($this->lockFile, $lockData, LOCK_EX) !== false;
    }
    
    private function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
    
    /**
     * Mark account refresh attempt in database
     */
    private function markAccountRefreshAttempt(int $accountId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO token_refresh_audit (account_id, action, details)
            VALUES (?, 'lock_acquired', ?)
        ");
        $stmt->execute([$accountId, json_encode(['pid' => getmypid()])]);
    }
    
    /**
     * Mark successful refresh and reset failure count
     */
    private function markAccountRefreshSuccess(int $accountId): void
    {
        $upd = $this->db->prepare("
            UPDATE ml_accounts 
            SET last_refresh_at = NOW(),
                refresh_failure_count = 0,
                last_refresh_error = NULL,
                refresh_locked_until = NULL,
                status = 'active',
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$accountId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO token_refresh_audit (account_id, action, details)
            VALUES (?, 'refresh_success', NULL)
        ");
        $stmt->execute([$accountId]);
    }
    
    /**
     * Mark refresh failure and increment failure count
     */
    private function markAccountRefreshFailure(int $accountId, string $error): void
    {
        $upd = $this->db->prepare("
            UPDATE ml_accounts 
            SET refresh_failure_count = refresh_failure_count + 1,
                last_refresh_error = ?,
                status = CASE 
                    WHEN refresh_failure_count >= 4 THEN 'expired'
                    ELSE status 
                END,
                refresh_locked_until = CASE 
                    WHEN refresh_failure_count >= 4 THEN DATE_ADD(NOW(), INTERVAL 1 HOUR)
                    ELSE NULL 
                END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$error, $accountId]);
        
        $stmt = $this->db->prepare("
            INSERT INTO token_refresh_audit (account_id, action, details)
            VALUES (?, 'refresh_failed', ?)
        ");
        $stmt->execute([$accountId, json_encode(['error' => $error])]);
    }
    
    /**
     * Update results array with single account result
     */
    private function updateResults(array &$results, array $result): void
    {
        $results['details'][] = $result;
        
        switch ($result['result']) {
            case 'success':
                $results['tokens_refreshed']++;
                break;
            case 'failed':
            case 'error':
                $results['tokens_failed']++;
                break;
            case 'skipped':
                $results['already_expired']++;
                break;
            case 'locked':
                $results['locked_accounts']++;
                break;
        }
    }
    
    /**
     * Log execution summary
     */
    private function logSummary(array $results): void
    {
        $this->logger->info('TokenRefreshService execution completed', [
            'accounts_checked' => $results['accounts_checked'],
            'tokens_refreshed' => $results['tokens_refreshed'],
            'tokens_failed' => $results['tokens_failed'],
            'already_expired' => $results['already_expired'],
            'locked_accounts' => $results['locked_accounts'],
            'duration_seconds' => strtotime($results['finished_at']) - strtotime($results['started_at'])
        ]);
    }
    
    /**
     * Refresh a specific account
     */
    public function refreshAccount(int $accountId): bool
    {
        return $this->authService->refreshToken($accountId, $this->maxRetries);
    }
    
    /**
     * Get current refresh status and statistics
     */
    public function getRefreshStatus(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_accounts,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_accounts,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired_accounts,
                SUM(CASE WHEN token_expires_at <= NOW() THEN 1 ELSE 0 END) as tokens_expired_now,
                SUM(CASE WHEN token_expires_at <= DATE_ADD(NOW(), INTERVAL 2 HOUR) THEN 1 ELSE 0 END) as tokens_expiring_soon,
                MAX(refresh_failure_count) as max_failure_count
            FROM ml_accounts
            WHERE refresh_token IS NOT NULL AND refresh_token != ''
        ");
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}

## Phase 3: Implementation Sequence (Days 16-25)

### Day 16-18: Monitoring & Alerting System
**Files to Create**:
```php
// app/Services/TokenRefreshMonitoringService.php
// app/Services/AlertService.php
// config/token_refresh.php
```

**Monitoring Metrics**:
```php
'metrics' => [
    'refresh_success_rate' => 'percentage',
    'refresh_latency' => 'milliseconds',
    'api_errors' => 'count',
    'lock_contentions' => 'count',
    'account_health' => 'status_distribution'
]
```

**Alert Thresholds**:
```php
'alerts' => [
    'critical_failure_rate' => 0.1, // 10%
    'max_refresh_latency' => 30000, // 30 seconds
    'max_lock_wait_time' => 600, // 10 minutes
    'min_account_health' => 0.8 // 80%
]
```

### Day 19-21: Script Consolidation
**Files to Retire**:
- `scripts/renew_tokens.php` → Delete
- `scripts/cron_refresh_tokens.php` → Delete  
- `scripts/refresh_ml_tokens.php` → Replace with simplified version

**New Entry Point**:
```php
// scripts/unified_token_refresh.php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedTokenRefreshService;

$service = new UnifiedTokenRefreshService();

// Parse arguments
$options = getopt('', ['all', 'account::', 'status', 'help']);

if (isset($options['help'])) {
    echo "Unified Token Refresh System\n";
    echo "Usage: php scripts/unified_token_refresh.php [--all] [--account=N] [--status]\n";
    exit(0);
}

if (isset($options['account'])) {
    $success = $service->refreshAccount((int)$options['account']);
    exit($success ? 0 : 1);
}

$forceAll = isset($options['all']);
$results = $service->executeRefresh($forceAll);

if (isset($options['status'])) {
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n";
}

exit($results['tokens_failed'] > 0 ? 1 : 0);
```

### Day 22-23: Cron Job Updates
**New Crontab Configuration**:
```bash
# Replace all existing token refresh crons with:
# Every 30 minutes with locking protection
*/30 * * * * cd /home/eskill/htdocs/eskill.com.br && php scripts/unified_token_refresh.php >> storage/logs/token_refresh.log 2>&1

# Health check every 5 minutes
*/5 * * * * cd /home/eskill/htdocs/eskill.com.br && php scripts/token_health_check.php >> storage/logs/token_health.log 2>&1

# Daily summary report
0 8 * * * cd /home/eskill/htdocs/eskill.com.br && php scripts/token_daily_report.php
```

### Day 24-25: Testing & Validation
**Test Suite**: `tests/Unit/Services/UnifiedTokenRefreshServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\UnifiedTokenRefreshService;
use App\Database;
use PDO;

class UnifiedTokenRefreshServiceTest extends TestCase
{
    private UnifiedTokenRefreshService $service;
    private PDO $db;
    
    protected function setUp(): void
    {
        $this->service = new UnifiedTokenRefreshService();
        $this->db = Database::getInstance();
    }
    
    public function test_fileLocking_preventsConcurrentExecution(): void
    {
        // Create first lock
        $lockFile = storage_path('token_refresh.test.lock');
        file_put_contents($lockFile, json_encode(['pid' => 12345]), LOCK_EX);
        
        // Try to acquire second lock
        $result = $this->service->executeRefresh(false);
        
        $this->assertEquals('skipped', $result['status']);
        $this->assertStringContains('already running', $result['reason']);
        
        // Cleanup
        unlink($lockFile);
    }
    
    public function test_refreshExpiredToken_success(): void
    {
        // Create test account with expired token
        $accountId = $this->createTestAccount([
            'token_expires_at' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
            'status' => 'expired'
        ]);
        
        // Mock successful auth service response
        // This would require dependency injection in production
        
        $success = $this->service->refreshAccount($accountId);
        $this->assertTrue($success);
        
        // Cleanup
        $this->cleanupTestAccount($accountId);
    }
    
    public function test_refreshInvalidToken_handlesGracefully(): void
    {
        // Create test account with invalid refresh token
        $accountId = $this->createTestAccount([
            'refresh_token' => 'invalid_token',
            'token_expires_at' => date('Y-m-d H:i:s', time() - 3600)
        ]);
        
        $success = $this->service->refreshAccount($accountId);
        $this->assertFalse($success);
        
        // Check failure count incremented
        $stmt = $this->db->prepare("SELECT refresh_failure_count FROM ml_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $failureCount = $stmt->fetchColumn();
        
        $this->assertGreaterThan(0, $failureCount);
        
        // Cleanup
        $this->cleanupTestAccount($accountId);
    }
    
    public function test_rateLimiting_betweenRequests(): void
    {
        // This test would require mocking time/sleep functions
        // For now, we test the configuration is loaded
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('rateLimitDelayMs');
        $property->setAccessible(true);
        
        $delay = $property->getValue($this->service);
        $this->assertIsInt($delay);
        $this->assertGreaterThan(0, $delay);
    }
    
    private function createTestAccount(array $data): int
    {
        $defaults = [
            'user_id' => 1,
            'ml_user_id' => 'test_' . uniqid(),
            'nickname' => 'test_user',
            'email' => 'test@example.com',
            'access_token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'status' => 'active'
        ];
        
        $data = array_merge($defaults, $data);
        
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $this->db->prepare("INSERT INTO ml_accounts ({$fields}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));
        
        return (int)$this->db->lastInsertId();
    }
    
    private function cleanupTestAccount(int $accountId): void
    {
        $stmt = $this->db->prepare("DELETE FROM ml_accounts WHERE id = ?");
        $stmt->execute([$accountId]);
    }
}
```

## Phase 4: Quality Assurance (Days 26-30)

### Day 26-27: Performance Benchmarking
**Key Metrics to Monitor**:
```php
$benchmarks = [
    'max_concurrent_refreshes' => 1, // With locking
    'refresh_latency_p95' => '< 5000ms',
    'memory_usage_peak' => '< 128MB',
    'cpu_usage_average' => '< 10%',
    'api_rate_limit_compliance' => '100%'
];
```

**Performance Test Script**:
```php
// scripts/performance_test_token_refresh.php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\UnifiedTokenRefreshService;

$service = new UnifiedTokenRefreshService();

// Baseline performance
$startTime = microtime(true);
$startMemory = memory_get_usage(true);

// Execute refresh
$results = $service->executeRefresh(false);

$endTime = microtime(true);
$endMemory = memory_get_usage(true);

$duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
$memoryUsed = $endMemory - $startMemory;

echo "Performance Results:\n";
echo "Duration: {$duration}ms\n";
echo "Memory Used: " . ($memoryUsed / 1024 / 1024) . "MB\n";
echo "Accounts Processed: {$results['accounts_checked']}\n";
echo "Tokens Refreshed: {$results['tokens_refreshed']}\n";
echo "Failures: {$results['tokens_failed']}\n";

if ($results['accounts_checked'] > 0) {
    $avgTimePerAccount = $duration / $results['accounts_checked'];
    echo "Average Time per Account: {$avgTimePerAccount}ms\n";
}

// Performance assertions
assert($duration < 30000, 'Total duration should be under 30 seconds');
assert($memoryUsed < 128 * 1024 * 1024, 'Memory usage should be under 128MB');
assert($results['tokens_failed'] / $results['accounts_checked'] < 0.1, 'Failure rate should be under 10%');
```

### Day 28: Security Review
**Security Checklist**:
- [ ] Token encryption verification
- [ ] IP whitelisting confirmation
- [ ] Proxy authentication security
- [ ] Audit log integrity
- [ ] Error message sanitization
- [ ] Rate limiting effectiveness

**Security Test Script**:
```php
// scripts/security_test_token_refresh.php
#!/usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\MercadoLivreAuthService;
use App\Database;

$auth = new MercadoLivreAuthService();
$db = Database::getInstance();

// Test 1: Verify tokens are encrypted in database
$stmt = $db->prepare("SELECT access_token, refresh_token, tokens_encrypted FROM ml_accounts LIMIT 1");
$stmt->execute();
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if ($account) {
    $tokenEncrypted = $account['tokens_encrypted'];
    $accessToken = $account['access_token'];
    $refreshToken = $account['refresh_token'];
    
    echo "Token Encryption Status:\n";
    echo "Tokens Encrypted Flag: " . ($tokenEncrypted ? 'YES' : 'NO') . "\n";
    
    if ($tokenEncrypted) {
        // Attempt to decrypt to verify encryption works
        try {
            $enc = new \App\Services\EncryptionService();
            $decrypted = $enc->decrypt($accessToken);
            echo "Encryption Verification: PASSED (can decrypt)\n";
        } catch (Exception $e) {
            echo "Encryption Verification: FAILED (cannot decrypt)\n";
        }
    }
}

// Test 2: Verify IP-based restrictions (if applicable)
echo "\nIP Configuration:\n";
$proxyEnabled = $_ENV['ML_PROXY_ENABLED'] ?? 'false';
echo "Proxy Enabled: {$proxyEnabled}\n";

if ($proxyEnabled === 'true') {
    $proxyHost = $_ENV['ML_PROXY_HOST'] ?? 'not_set';
    $proxyPort = $_ENV['ML_PROXY_PORT'] ?? 'not_set';
    echo "Proxy Host: {$proxyHost}\n";
    echo "Proxy Port: {$proxyPort}\n";
}

// Test 3: Verify audit logging
$stmt = $db->prepare("SELECT COUNT(*) as count FROM token_refresh_audit WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute();
$auditCount = $stmt->fetchColumn();

echo "\nAudit Logging:\n";
echo "Audit records in last hour: {$auditCount}\n";

echo "\nSecurity Review Complete.\n";
```

### Day 29: Documentation Update
**Documentation Files to Update**:
```
/docs/API_TOKEN_MANAGEMENT.md
/AGENTS.md - Development guidelines
/config/token_refresh.php - Configuration reference
/storage/logs/README.md - Log analysis guide
```

**Configuration Reference**:
```php
// config/token_refresh.php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token Refresh Configuration
    |--------------------------------------------------------------------------
    */
    
    // How many minutes before expiration to refresh tokens
    'refresh_margin_minutes' => env('TOKEN_REFRESH_MARGIN_MINUTES', 120),
    
    // Maximum retry attempts for failed refresh
    'max_retries' => env('TOKEN_REFRESH_MAX_RETRIES', 3),
    
    // Delay between API calls in milliseconds (rate limiting)
    'api_rate_delay_ms' => env('ML_API_RATE_DELAY_MS', 500),
    
    // File lock timeout in seconds
    'lock_timeout_seconds' => env('TOKEN_REFRESH_LOCK_TIMEOUT', 300),
    
    // Maximum failure count before account lockout
    'max_failure_count' => env('TOKEN_REFRESH_MAX_FAILURE_COUNT', 5),
    
    // Account lockout duration in minutes
    'lockout_duration_minutes' => env('TOKEN_REFRESH_LOCKOUT_MINUTES', 60),
    
    // Monitoring and alerting thresholds
    'alert_thresholds' => [
        'failure_rate' => env('TOKEN_REFRESH_ALERT_FAILURE_RATE', 0.1), // 10%
        'max_latency_ms' => env('TOKEN_REFRESH_ALERT_MAX_LATENCY', 30000), // 30s
        'min_health_rate' => env('TOKEN_REFRESH_ALERT_MIN_HEALTH', 0.8), // 80%
    ],
    
    // Proxy configuration
    'proxy' => [
        'enabled' => env('ML_PROXY_ENABLED', false),
        'type' => env('ML_PROXY_TYPE', 'http'),
        'host' => env('ML_PROXY_HOST', ''),
        'port' => env('ML_PROXY_PORT', ''),
        'username' => env('ML_PROXY_USER', ''),
        'password' => env('ML_PROXY_PASS', ''),
    ],
];
```

### Day 30: Production Deployment
**Deployment Steps**:
1. **Backup Current State**
```bash
# Backup existing scripts
mkdir -p storage/backup/2026-02-08_token_refresh
cp scripts/*token*.php storage/backup/2026-02-08_token_refresh/
cp -r app/Jobs/TokenRefreshJob.php storage/backup/2026-02-08_token_refresh/

# Export current cron configuration
crontab -l > storage/backup/2026-02-08_token_refresh/crontab.backup
```

2. **Deploy New Files**
```bash
# Apply database migration
mysql -h localhost -u root -p mercadolivre_db < database/migrations/2026_02_08_token_refresh_optimization.sql

# Deploy new service
cp app/Services/UnifiedTokenRefreshService.php app/Services/

# Create new unified script
cp scripts/unified_token_refresh.php scripts/

# Update cron jobs
crontab < config/crontab.token_refresh.new

# Set permissions
chmod +x scripts/unified_token_refresh.php
chmod +x scripts/token_health_check.php
chmod +x scripts/token_daily_report.php
```

3. **Monitor Deployment**
```bash
# Watch logs in real-time
tail -f storage/logs/token_refresh.log &
tail -f storage/logs/token_health.log &

# Check lock file status
ls -la storage/token_refresh.lock

# Verify service health
php scripts/unified_token_refresh.php --status

# Test manual refresh
php scripts/unified_token_refresh.php --account=1
```

## Rollback Procedures

### Immediate Rollback (Minutes)
```bash
# Restore original scripts
cp storage/backup/2026-02-08_token_refresh/*token*.php scripts/
cp storage/backup/2026-02-08_token_refresh/TokenRefreshJob.php app/Jobs/

# Restore old crontab
crontab storage/backup/2026-02-08_token_refresh/crontab.backup

# Remove new service
rm app/Services/UnifiedTokenRefreshService.php

# Kill any running processes
pkill -f "unified_token_refresh.php"
```

### Database Rollback (If needed)
```sql
-- Remove added columns (safe operation)
ALTER TABLE ml_accounts 
DROP COLUMN IF EXISTS last_refresh_at,
DROP COLUMN IF EXISTS refresh_failure_count,
DROP COLUMN IF EXISTS last_refresh_error,
DROP COLUMN IF EXISTS refresh_locked_until;

-- Remove audit table
DROP TABLE IF EXISTS token_refresh_audit;

-- Remove indexes
DROP INDEX IF EXISTS idx_last_refresh ON ml_accounts;
DROP INDEX IF EXISTS idx_refresh_locked ON ml_accounts;
```

### Configuration Rollback
```bash
# Restore .env backup
cp storage/backup/2026-02-08_token_refresh/.env.backup .env

# Remove new config files
rm config/token_refresh.php
```

## Success Metrics

### Technical Metrics
- **Token Refresh Success Rate**: > 95%
- **Average Refresh Latency**: < 5 seconds
- **Zero Concurrent Execution Conflicts**: 100%
- **API Rate Limit Compliance**: 100%

### Business Metrics
- **Account Uptime**: > 99.5%
- **Manual Reconnection Reduction**: > 80%
- **Customer Support Tickets**: < 5 per month
- **API Quota Efficiency**: > 90%

### Monitoring Dashboard
```php
// Expected dashboard metrics
[
    'token_health' => [
        'total_accounts' => 150,
        'active_accounts' => 142,
        'expired_accounts' => 8,
        'health_percentage' => 94.7
    ],
    'refresh_performance' => [
        'last_24h_refreshes' => 312,
        'success_rate' => 96.8,
        'average_latency_ms' => 1200,
        'failure_rate' => 3.2
    ],
    'system_status' => [
        'last_refresh' => '2026-02-08 14:30:00',
        'lock_status' => 'unlocked',
        'queue_size' => 0,
        'api_rate_limit_remaining' => 85
    ]
]
```

## Emergency Contacts

| Role | Contact | Availability |
|------|---------|--------------|
| DevOps Lead | devops@eskill.com.br | 24/7 |
| ML API Support | ML DevCenter | Business hours |
| Infrastructure | hosting@eskill.com.br | 24/7 |

## Post-Implementation Checklist

### Technical Verification
- [ ] IP whitelisting confirmed in ML DevCenter
- [ ] All duplicate scripts removed and backed up
- [ ] UnifiedTokenRefreshService deployed and tested
- [ ] Database migration applied successfully
- [ ] New cron jobs active and running
- [ ] Monitoring alerts configured and tested
- [ ] Lock file mechanism verified
- [ ] Rate limiting confirmed working
- [ ] Error handling tested with invalid tokens

### Operational Verification
- [ ] Documentation updated and distributed
- [ ] Team trained on new procedures
- [ ] Rollback plan tested and documented
- [ ] Success metrics dashboard live
- [ ] Alert notifications working
- [ ] Log rotation configured
- [ ] Backup procedures verified
- [ ] Performance benchmarks met

### Business Verification
- [ ] Token refresh success rate > 95%
- [ ] Account downtime < 0.5%
- [ ] Manual reconnections reduced > 80%
- [ ] Support tickets related to tokens < 5/month
- [ ] API quota utilization optimized
- [ ] Customer satisfaction maintained/improved

---

## Appendix A: File Structure

```
/docs/
├── IMPLEMENTATION_PLAN_TOKEN_REFRESH.md
├── ip_whitelisting_technical_specs.md
└── proxy_configuration_fallback.md

/app/Services/
├── UnifiedTokenRefreshService.php (NEW)
├── TokenRefreshMonitoringService.php (NEW)
├── AlertService.php (NEW)
└── MercadoLivreAuthService.php (EXISTING - enhanced)

/scripts/
├── unified_token_refresh.php (NEW)
├── token_health_check.php (NEW)
├── token_daily_report.php (NEW)
├── renew_tokens.php (DEPRECATED - to be removed)
├── refresh_ml_tokens.php (DEPRECATED - to be removed)
└── cron_refresh_tokens.php (DEPRECATED - to be removed)

/database/migrations/
└── 2026_02_08_token_refresh_optimization.sql (NEW)

/config/
└── token_refresh.php (NEW)

/tests/Unit/Services/
└── UnifiedTokenRefreshServiceTest.php (NEW)

/storage/logs/
├── token_refresh.log (NEW)
├── token_health.log (NEW)
└── token_audit.log (NEW)
```

## Appendix B: Environment Variables

```bash
# Token Refresh Configuration
TOKEN_REFRESH_MARGIN_MINUTES=120
TOKEN_REFRESH_MAX_RETRIES=3
TOKEN_REFRESH_LOCK_TIMEOUT=300
TOKEN_REFRESH_MAX_FAILURE_COUNT=5
TOKEN_REFRESH_LOCKOUT_MINUTES=60

# API Rate Limiting
ML_API_RATE_DELAY_MS=500

# Proxy Configuration (Fallback)
ML_PROXY_ENABLED=false
ML_PROXY_TYPE=http
ML_PROXY_HOST=backup-proxy.example.com
ML_PROXY_PORT=8080
ML_PROXY_USER=
ML_PROXY_PASS=

# Alert Thresholds
TOKEN_REFRESH_ALERT_FAILURE_RATE=0.1
TOKEN_REFRESH_ALERT_MAX_LATENCY=30000
TOKEN_REFRESH_ALERT_MIN_HEALTH=0.8
```

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-08  
**Next Review**: 2026-03-08  
**Implementation Start**: 2026-02-09  
**Expected Completion**: 2026-03-10
