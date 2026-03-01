<?php

namespace App\Controllers;

use App\Database;
use App\Services\UserService;

class HealthController extends BaseController
{
    private $db;
    private UserService $userService;

    public function __construct(?UserService $userService = null)
    {
        parent::__construct();
        $this->userService = $userService ?? new UserService();
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            if ($this->canSendHeaders()) {
                header('Location: /login');
                exit;
            }
            return;
        }

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
        } catch (\Exception $e) {
            log_debug('DB size query failed', ['error' => $e->getMessage()]);
        }

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
            'errors' => ['label' => 'Log de Erros (Size)', 'value' => round($errorLogSize / 1024, 2) . ' KB', 'status' => ($errorLogSize > 1024 * 1024 ? 'warning' : 'success')]
        ];

        $pageTitle = 'Status do Sistema';
        $activePage = 'health';

        ob_start();
        require __DIR__ . '/../Views/dashboard/health/index.php'; // Reuse or create new view
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Liveness check - simple alive status
     * Used by Kubernetes/container orchestrators
     */
    public function live(): void
    {
        if ($this->canSendHeaders()) {
            header('Content-Type: application/json');
        }
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
        if ($this->canSendHeaders()) {
            header('Content-Type: application/json');
        }

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

        if ($this->canSendHeaders()) {
            http_response_code($allReady ? 200 : 503);
        }
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
        if ($this->canSendHeaders()) {
            header('Content-Type: application/json');
        }

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
            if ($this->canSendHeaders()) {
                http_response_code(503);
            }
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
        if ($this->canSendHeaders()) {
            header('Content-Type: application/json');
        }

        $config = \App\Core\Config::getInstance()->all();
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
    private function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function canSendHeaders(): bool
    {
        return PHP_SAPI !== 'cli' && !headers_sent();
    }

    /**
     * Check integrations health — verifies dependencies used by real service implementations.
     * Checks: Tesseract OCR, GD library, OpenAI API key, ML API, DB tables.
     */
    public function integrations(): void
    {
        if ($this->canSendHeaders()) {
            header('Content-Type: application/json');
        }

        $checks = [];

        // 1. GD Library (used by AIImageAnalyzerService)
        $checks['gd_library'] = [
            'status' => extension_loaded('gd') ? 'ok' : 'error',
            'message' => extension_loaded('gd') ? 'GD extension loaded' : 'GD extension not available',
        ];

        // 2. Tesseract OCR (used by AIImageAnalyzerService)
        $tesseractPath = trim(shell_exec('which tesseract 2>/dev/null') ?: '');
        $checks['tesseract_ocr'] = [
            'status' => !empty($tesseractPath) ? 'ok' : 'warning',
            'message' => !empty($tesseractPath) ? "Tesseract found at {$tesseractPath}" : 'Tesseract not found — OCR features will be limited',
        ];

        // 3. OpenAI API key (used by AIImageAnalyzerService, ListingBuilderService)
        $openaiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        $checks['openai_api'] = [
            'status' => !empty($openaiKey) ? 'ok' : 'warning',
            'message' => !empty($openaiKey) ? 'OpenAI API key configured' : 'OPENAI_API_KEY not set — AI features disabled',
        ];

        // 4. Critical DB tables existence
        $requiredTables = [
            'items',
            'ml_orders',
            'order_items',
            'ml_questions',
            'seo_performance_metrics',
            'competitor_watchlist',
        ];
        $missingTables = [];
        if ($this->db) {
            foreach ($requiredTables as $table) {
                try {
                    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                    $stmt = $this->db->query("SELECT 1 FROM `{$safeTable}` LIMIT 0");
                    $stmt->closeCursor();
                } catch (\Exception $e) {
                    $missingTables[] = $table;
                }
            }
        }
        $checks['database_tables'] = [
            'status' => empty($missingTables) ? 'ok' : 'warning',
            'message' => empty($missingTables)
                ? 'All ' . count($requiredTables) . ' required tables exist'
                : 'Missing tables: ' . implode(', ', $missingTables),
            'required' => $requiredTables,
            'missing' => $missingTables,
        ];

        // 5. Active ML accounts
        $activeAccounts = 0;
        if ($this->db) {
            try {
                $stmt = $this->db->query(
                    "SELECT COUNT(*) FROM ml_accounts
                    WHERE status = 'active'
                      AND (token_expires_at IS NULL OR token_expires_at > NOW())"
                );
                $activeAccounts = (int)$stmt->fetchColumn();
            } catch (\Throwable $e) {
                // table may not exist yet on fresh install
                $activeAccounts = -1;
            }
        }
        $checks['ml_active_accounts'] = [
            'status'  => $activeAccounts > 0 ? 'ok' : ($activeAccounts === -1 ? 'warning' : 'warning'),
            'message' => $activeAccounts > 0
                ? "{$activeAccounts} conta(s) ML ativa(s) com token válido"
                : ($activeAccounts === -1
                    ? 'Não foi possível verificar contas ML (tabela ausente)'
                    : 'Nenhuma conta ML ativa — funcionalidades de marketplace indisponíveis'),
            'count' => max($activeAccounts, 0),
        ];

        // 6. ML API connectivity
        // Note: a 403 PA_UNAUTHORIZED_RESULT_FROM_POLICIES response still means the API is
        // reachable — it is only blocked for requests that include a client_id not registered
        // for the public-sites policy. We treat it as reachable (ok) not as an error.
        $mlApiStatus = 'skipped';
        $mlApiMessage = 'ML API check skipped (api_url not configured)';
        try {
            $config = \App\Core\Config::getInstance()->all();
            $ml = $config['mercadolivre'] ?? [];
            if (!empty($ml['api_url'])) {
                $ctx = stream_context_create(['http' => [
                    'timeout' => 3,
                    'ignore_errors' => true,  // read body even on 4xx so we can inspect the reason
                ]]);
                $resp = @file_get_contents($ml['api_url'] . '/sites/MLB', false, $ctx);
                $httpLine = $http_response_header[0] ?? '';
                preg_match('/HTTP\/\S+\s+(\d+)/', $httpLine, $m);
                $httpCode = (int)($m[1] ?? 0);

                if ($resp !== false && $httpCode === 200) {
                    $mlApiStatus = 'ok';
                    $mlApiMessage = 'ML API reachable';
                } elseif ($httpCode === 403) {
                    // Policy blocks client_id, but connectivity is confirmed
                    $mlApiStatus = 'ok';
                    $mlApiMessage = 'ML API reachable (policy restricts client_id on public endpoint)';
                } elseif ($resp === false || $httpCode === 0) {
                    $mlApiStatus = 'error';
                    $mlApiMessage = 'ML API unreachable (connection failed or timeout)';
                } elseif ($httpCode >= 500) {
                    $mlApiStatus = 'error';
                    $mlApiMessage = "ML API server error (HTTP {$httpCode})";
                } else {
                    $mlApiStatus = 'warning';
                    $mlApiMessage = "ML API returned HTTP {$httpCode}";
                }
            }
        } catch (\Throwable $e) {
            $mlApiStatus = 'error';
            $mlApiMessage = 'ML API check failed: ' . $e->getMessage();
        }
        $checks['ml_api'] = [
            'status'  => $mlApiStatus,
            'message' => $mlApiMessage,
        ];

        // 6. Storage directories writable
        $storageDirs = ['storage/cache', 'storage/logs'];
        $notWritable = [];
        foreach ($storageDirs as $dir) {
            $path = __DIR__ . '/../../' . $dir;
            if (!is_dir($path) || !is_writable($path)) {
                $notWritable[] = $dir;
            }
        }
        $checks['storage'] = [
            'status' => empty($notWritable) ? 'ok' : 'warning',
            'message' => empty($notWritable) ? 'All storage directories writable' : 'Not writable: ' . implode(', ', $notWritable),
        ];

        // Overall status
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
            if ($this->canSendHeaders()) {
                http_response_code(503);
            }
        } elseif ($hasWarning) {
            $status = 'degraded';
        }

        echo json_encode([
            'status' => $status,
            'timestamp' => date('c'),
            'checks' => $checks,
        ]);
    }
}
