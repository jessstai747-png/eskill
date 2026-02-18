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
        // Security: use real client IP behind proxy/CDN (A1)
        $ip = $this->getClientIp();
        $db = Database::getInstance();
        $now = time();
        $burstCutoff = date('Y-m-d H:i:s', $now - 5);
        $windowCutoff = date('Y-m-d H:i:s', $now - $this->windowSeconds);
        $cleanupCutoff = date('Y-m-d H:i:s', $now - ($this->windowSeconds * 2));

        $this->ensureRateLimitTable();

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
        $burstCount = (int)$stmtBurst->fetch()['count'];

        if ($burstCount >= $this->burstLimit) {
            http_response_code(429);
            header('Content-Type: application/json');
            header('Retry-After: 5');
            echo json_encode([
                'error' => 'Burst limit exceeded. Please wait before making more requests.',
                'code' => 'BURST_LIMIT_EXCEEDED',
                'retry_after' => 5
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
        $count = (int)$result['count'];

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
    private function ensureRateLimitTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }

        $db = Database::getInstance();

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
}
