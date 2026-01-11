<?php

namespace App\Controllers;

use App\Database;

class HealthController extends BaseController
{
    private $db;

    public function __construct()
    {
        parent::__construct();
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    public function index(): void
    {
        // 1. Cron Status (general sync and refresh-token cleanup)
        $cronFiles = [
            'sync' => __DIR__ . '/../../storage/logs/cron_sync.log',
            'refresh_cleanup' => __DIR__ . '/../../storage/logs/cron_cleanup_refresh_tokens.log'
        ];

        $cronStatus = [];
        foreach ($cronFiles as $name => $cronLog) {
            $last = 'Never';
            $status = 'danger';
            if (file_exists($cronLog)) {
                $mtime = filemtime($cronLog);
                $last = date('d/m/Y H:i', $mtime);

                if (time() - $mtime < 3600) {
                    $status = 'success';
                } elseif (time() - $mtime < 86400) {
                    $status = 'warning';
                }
            }
            $cronStatus[$name] = ['last_run' => $last, 'status' => $status, 'path' => $cronLog];
        }

        // 2. DB Size (Approx)
        $dbSize = 'Unknown';
        try {
            // This query requires permissions, might fail on some hosts
            $stmt = $this->db->query("
                SELECT sum(data_length + index_length) / 1024 / 1024 
                FROM information_schema.TABLES
                WHERE table_schema = DATABASE()
            ");
            $size = $stmt->fetchColumn();
            $dbSize = number_format((float)$size, 2) . ' MB';
        } catch (\Exception $e) {}

        // 3. Error Rate (Last 24h)
        // Using audit logs or just a mock if we don't have comprehensive error logging in DB
        // Let's check audit_logs for failures if we had that, otherwise just check error.log size
        $errorLogSize = 0;
        $phpErrorLog = __DIR__ . '/../../storage/logs/error.log';
        if (file_exists($phpErrorLog)) $errorLogSize = filesize($phpErrorLog);
        
        $metrics = [
            'cron_sync' => ['label' => 'Última Execução Sync', 'value' => $cronStatus['sync']['last_run'], 'status' => $cronStatus['sync']['status']],
            'cron_refresh_cleanup' => ['label' => 'Última Execução Refresh Cleanup', 'value' => $cronStatus['refresh_cleanup']['last_run'], 'status' => $cronStatus['refresh_cleanup']['status']],
            'db_size' => ['label' => 'Tamanho do Banco', 'value' => $dbSize, 'status' => 'info'],
            'errors' => ['label' => 'Log de Erros (Size)', 'value' => round($errorLogSize/1024, 2) . ' KB', 'status' => ($errorLogSize > 1024*1024 ? 'warning' : 'success')]
        ];

        require __DIR__ . '/../Views/dashboard/health/index.php'; // Reuse or create new view
    }
    
    /**
     * Liveness check - simple alive status
     * Used by Kubernetes/container orchestrators
     */
    public function live(): void
    {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'alive',
            'timestamp' => date('c'),
        ]);
    }
    
    /**
     * Readiness check - checks if system can accept requests
     * Verifies database connectivity
     */
    public function ready(): void
    {
        header('Content-Type: application/json');
        
        $checks = [
            'database' => $this->checkDatabase(),
        ];
        
        $allReady = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'ok') {
                $allReady = false;
                break;
            }
        }
        
        http_response_code($allReady ? 200 : 503);
        echo json_encode([
            'status' => $allReady ? 'ready' : 'not_ready',
            'timestamp' => date('c'),
            'checks' => $checks,
        ]);
    }
    
    /**
     * Full health check - comprehensive system status
     */
    public function check(): void
    {
        header('Content-Type: application/json');
        
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk(),
        ];
        
        // Determine overall status
        $hasError = false;
        $hasWarning = false;
        
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $hasError = true;
            } elseif ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }
        
        $status = 'healthy';
        if ($hasError) {
            $status = 'unhealthy';
            http_response_code(503);
        } elseif ($hasWarning) {
            $status = 'degraded';
        }
        
        echo json_encode([
            'status' => $status,
            'timestamp' => date('c'),
            'checks' => $checks,
            'version' => '1.0.0',
            'environment' => $_ENV['APP_ENV'] ?? 'production',
        ]);
    }

    /**
     * Check Mercado Livre integration readiness (credentials present and optional ping)
     */
    public function mercadoLivre(): void
    {
        header('Content-Type: application/json');

        $config = require __DIR__ . '/../../config/app.php';
        $ml = $config['mercadolivre'] ?? [];

        $result = ['credentials' => 'missing', 'ping' => 'skipped'];

        if (!empty($ml['app_id']) && !empty($ml['client_secret'])) {
            $result['credentials'] = 'ok';

            // If monitoring enabled, try to ping ML API
            $monitoring = filter_var($_ENV['MONITORING_ENABLED'] ?? $config['monitoring']['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
            if ($monitoring) {
                try {
                    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
                    $resp = @file_get_contents($ml['api_url'] . '/sites/' . ($ml['site_id'] ?? 'MLB'), false, $ctx);
                    $result['ping'] = $resp ? 'ok' : 'failed';
                } catch (\Throwable $e) {
                    $result['ping'] = 'failed';
                }
            }
        }

        echo json_encode(['status' => 'ok', 'result' => $result]);
    }
    
    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            if (!$this->db) {
                return [
                    'status' => 'error',
                    'message' => 'Database not initialized',
                ];
            }
            
            $stmt = $this->db->query('SELECT 1');
            $stmt->fetch();
            
            return [
                'status' => 'ok',
                'message' => 'Connected',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Check cache directory
     */
    private function checkCache(): array
    {
        $cacheDir = __DIR__ . '/../../storage/cache';
        
        if (!is_dir($cacheDir)) {
            return [
                'status' => 'warning',
                'message' => 'Cache directory not found',
                'writable' => false,
            ];
        }
        
        $writable = is_writable($cacheDir);
        
        return [
            'status' => $writable ? 'ok' : 'warning',
            'message' => $writable ? 'Cache operational' : 'Cache not writable',
            'writable' => $writable,
        ];
    }
    
    /**
     * Check memory usage
     */
    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        $usagePercent = $limit > 0 ? ($usage / $limit) * 100 : 0;
        
        $status = 'ok';
        if ($usagePercent > 90) {
            $status = 'error';
        } elseif ($usagePercent > 75) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'usage' => $this->formatBytes($usage),
            'peak' => $this->formatBytes($peak),
            'limit' => ini_get('memory_limit'),
            'usage_percent' => round($usagePercent, 1),
        ];
    }
    
    /**
     * Check disk space
     */
    private function checkDisk(): array
    {
        $path = __DIR__ . '/../../';
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        
        $usedPercent = $total > 0 ? (($total - $free) / $total) * 100 : 0;
        
        $status = 'ok';
        if ($usedPercent > 95) {
            $status = 'error';
        } elseif ($usedPercent > 85) {
            $status = 'warning';
        }
        
        return [
            'status' => $status,
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used_percent' => round($usedPercent, 1),
        ];
    }
    
    /**
     * Parse memory limit to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
