<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Database;

class RateLimitMiddleware
{
    private int $maxRequests;
    private int $windowSeconds;
    private int $burstLimit;
    private static bool $tableEnsured = false;

    public function __construct(?int $maxRequests = null, ?int $windowSeconds = null, ?int $burstLimit = null)
    {
        // Use environment values if available, otherwise defaults
        $this->maxRequests = $maxRequests ?? (int)($_ENV['API_RATE_LIMIT'] ?? 100);
        $this->windowSeconds = $windowSeconds ?? 60;

        // Burst limit: allow reasonable spikes for dashboard initial load
        // Dashboard loads typically need 8-12 concurrent requests
        $resolvedBurst = $burstLimit ?? (int)($_ENV['API_BURST_LIMIT'] ?? 20);
        $this->burstLimit = max(1, (int)$resolvedBurst);
    }

    /**
     * Aplica rate limiting por IP
     */
    public function handle(): void
    {
        // In E2E/CI environments, Playwright and readiness probes can generate
        // rapid bursts of requests. Rate limiting is a production concern, so
        // we disable it for testing to keep suites deterministic.

        // Guard 1: APP_ENV-based bypass.
        // Prioritise getenv() (reads from the OS process environment, which is
        // set by the Playwright web-server command: APP_ENV=testing php -S ...)
        // over $_ENV, which can be overwritten by Dotenv loading .env with
        // APP_ENV=production before our code runs.
        $appEnv = strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
        if (in_array($appEnv, ['testing', 'test'], true)) {
            return;
        }

        // Guard 2: explicit opt-out flag (useful for staging with no DB).
        $disable = getenv('DISABLE_RATE_LIMIT') ?: ($_ENV['DISABLE_RATE_LIMIT'] ?? null);
        if (filter_var($disable, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        // Security: use real client IP behind proxy/CDN (A1)
        $ip = $this->getClientIp();

        // Guard 3: unconditional loopback bypass.
        // Requests from 127.x / ::1 are always local (Playwright tests, health
        // probes, internal CLI tooling). Rate-limiting loopback is meaningless
        // and harmful regardless of APP_ENV. This is defence-in-depth: even if
        // both Guard 1 and Guard 2 fail to fire, loopback is still never blocked.
        $isLoopback = in_array($ip, ['127.0.0.1', '::1', '0.0.0.0'], true)
            || str_starts_with($ip, '127.')
            || $ip === 'localhost';
        if ($isLoopback) {
            return;
        }

        // Prefer DB-backed rate limit, but do NOT hard-fail the whole request
        // if the DB is unavailable (e.g. E2E sandbox/CI). Fallback to filesystem.
        try {
            $db = Database::getInstance();
            $this->handleWithDatabase($ip, $db);
            return;
        } catch (\Throwable $e) {
            // Avoid 500 loops when DB is down. Fallback is best-effort.
            try {
                $this->handleWithFilesystem($ip);
            } catch (\Throwable $inner) {
                // Fail-open: if even fallback fails (read-only FS), proceed without rate limiting.
                // Logging here is intentionally conservative to avoid recursive failures.
                if (function_exists('log_warning')) {
                    log_warning('RateLimitMiddleware: fallback failed, rate limit disabled for this request', [
                        'service' => 'RateLimitMiddleware',
                        'error' => $inner->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * DB-backed implementation.
     *
     * @param string $ip
     * @param \PDO $db
     */
    private function handleWithDatabase(string $ip, \PDO $db): void
    {
        $now = time();
        $burstCutoff = date('Y-m-d H:i:s', $now - 5);
        $windowCutoff = date('Y-m-d H:i:s', $now - $this->windowSeconds);
        $cleanupCutoff = date('Y-m-d H:i:s', $now - ($this->windowSeconds * 2));

        $this->ensureRateLimitTable($db);

        // Check burst limit (last 5 seconds - reduced window for faster recovery)
        $stmtBurst = $db->prepare("
            SELECT COUNT(*) as count
            FROM rate_limits
            WHERE ip_address = :ip
            AND created_at > :cutoff
        ");

        $stmtBurst->execute([
            'ip' => $ip,
            'cutoff' => $burstCutoff,
        ]);
        $burstCount = (int)($stmtBurst->fetch()['count'] ?? 0);

        if ($burstCount >= $this->burstLimit) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: 5');
            echo json_encode([
                'error' => 'Burst limit exceeded. Please wait before making more requests.',
                'code' => 'BURST_LIMIT_EXCEEDED',
                'retry_after' => 5,
            ]);
            exit;
        }

        // Obter requisições na janela atual
        $stmt = $db->prepare("
            SELECT COUNT(*) as count
            FROM rate_limits
            WHERE ip_address = :ip
            AND created_at > :cutoff
        ");

        $stmt->execute([
            'ip' => $ip,
            'cutoff' => $windowCutoff,
        ]);

        $result = $stmt->fetch();
        $count = (int)($result['count'] ?? 0);

        if ($count >= $this->maxRequests) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: ' . $this->windowSeconds);
            echo json_encode([
                'error' => 'Muitas requisições. Tente novamente mais tarde.',
                'code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $this->windowSeconds,
            ]);
            exit;
        }

        // Registrar requisição
        $stmt = $db->prepare("
            INSERT INTO rate_limits (ip_address, created_at)
            VALUES (:ip, NOW())
        ");
        $stmt->execute(['ip' => $ip]);

        // Limpar registros antigos (manutenção)
        if (rand(1, 100) === 1) { // 1% de chance
            $stmt = $db->prepare("
                DELETE FROM rate_limits
                WHERE created_at < :cutoff
            ");
            $stmt->execute(['cutoff' => $cleanupCutoff]);
        }
    }

    /**
     * Filesystem fallback implementation for DB-less environments.
     * Stores timestamps per IP in a JSON file.
     */
    private function handleWithFilesystem(string $ip): void
    {
        $dir = $this->getFallbackDir();
        if ($dir === null) {
            return;
        }

        $file = rtrim($dir, '/') . '/' . md5($ip) . '.json';
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($fp);
            $data = [];
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            $now = time();
            $windowCutoff = $now - $this->windowSeconds;
            $burstCutoff = $now - 5;

            $timestamps = $data['timestamps'] ?? [];
            if (!is_array($timestamps)) {
                $timestamps = [];
            }

            // Keep only timestamps within the main window.
            $filtered = [];
            foreach ($timestamps as $ts) {
                if (!is_int($ts)) {
                    continue;
                }
                if ($ts > $windowCutoff) {
                    $filtered[] = $ts;
                }
            }

            $burstCount = 0;
            foreach ($filtered as $ts) {
                if ($ts > $burstCutoff) {
                    $burstCount++;
                }
            }

            $count = count($filtered);

            if ($burstCount >= $this->burstLimit) {
                http_response_code(429);
                header('Content-Type: application/json');
                header('Retry-After: 5');
                echo json_encode([
                    'error' => 'Burst limit exceeded. Please wait before making more requests.',
                    'code' => 'BURST_LIMIT_EXCEEDED',
                    'retry_after' => 5,
                ]);
                exit;
            }

            if ($count >= $this->maxRequests) {
                http_response_code(429);
                header('Content-Type: application/json');
                header('Retry-After: ' . $this->windowSeconds);
                echo json_encode([
                    'error' => 'Muitas requisições. Tente novamente mais tarde.',
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'retry_after' => $this->windowSeconds,
                ]);
                exit;
            }

            $filtered[] = $now;
            $payload = json_encode([
                'timestamps' => $filtered,
                'updated_at' => $now,
            ]);
            if (!is_string($payload)) {
                $payload = '{"timestamps":[]}';
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    /**
     * Obtém IP real do cliente (mesmo padrão do SecurityMiddleware)
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Garante que tabela existe
     */
    private function ensureRateLimitTable(\PDO $db): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        self::$tableEnsured = true;
    }

    /**
     * Resolve a writable directory for filesystem-based rate limit.
     */
    private function getFallbackDir(): ?string
    {
        $candidates = [];

        if (defined('STORAGE_PATH')) {
            $candidates[] = rtrim((string)STORAGE_PATH, '/') . '/cache/rate_limits';
        }
        if (defined('ROOT_PATH')) {
            $candidates[] = rtrim((string)ROOT_PATH, '/') . '/.tmp/rate_limits';
        }

        $candidates[] = sys_get_temp_dir() . '/eskill_rate_limits';

        foreach ($candidates as $dir) {
            if ($dir === '') {
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }

        return null;
    }
}
