<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * SecurityMiddleware - Middleware de Segurança Avançada
 *
 * Implementa verificações de segurança para todas as requisições:
 * - Headers de segurança
 * - Proteção contra ataques comuns
 * - Rate limiting por IP
 * - Bloqueio de IPs suspeitos
 * - Validação de requisições
 */
class SecurityMiddleware
{
    private array $config;
    private ?\PDO $db;

    // IPs whitelist (não bloqueados)
    private array $whitelist = ['127.0.0.1', '::1', '193.186.4.203'];

    // User agents suspeitos (bots maliciosos)
    private array $suspiciousAgents = [
        'nikto',
        'sqlmap',
        'nmap',
        'sqlninja',
        'paros',
        'w3af',
        'acunetix',
        'havij',
        'masscan',
        'dirbuster',
        'gobuster',
        'nuclei',
        'whatweb',
        'wpscan',
        'joomscan',
        'zgrab'
    ];

    // Padrões de ataque na URL
    private array $attackPatterns = [
        // SQL Injection
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/i',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|(;))/i',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/i',
        '/((\%27)|(\'))union/i',

        // XSS
        '/((\%3C)|<)((\%2F)|\/)*[a-z0-9\%]+((\%3E)|>)/i',
        '/((\%3C)|<)((\%69)|i|(\%49))((\%6D)|m|(\%4D))((\%67)|g|(\%47))[^\n]+((\%3E)|>)/i',

        // Path Traversal
        '/(\.|%2e){2}(\/|\\\\|%2f|%5c)/i',

        // Remote File Inclusion
        '/[a-zA-Z]+:\/\/[^\s]+(\.php|\.txt|\.sh)/i',
    ];

    public function __construct()
    {
        $securityMwRateLimitEnabled = getenv('SECURITY_MW_RATE_LIMIT_ENABLED');
        if ($securityMwRateLimitEnabled === false || $securityMwRateLimitEnabled === '') {
            $securityMwRateLimitEnabled = getenv('SECURITY_MW_RATE_LIMIT');
        }

        $this->config = [
            // Rate limit principal é aplicado em public/index.php via RateLimitMiddleware.
            // Flag separada evita dupla aplicação (429 duplicado/custo extra).
            'rate_limit_enabled' => ($securityMwRateLimitEnabled ?: 'false') === 'true',
            'rate_limit_max' => (int)(getenv('RATE_LIMIT_MAX') ?: 100),
            'rate_limit_window' => (int)(getenv('RATE_LIMIT_WINDOW') ?: 60),
            'block_suspicious_agents' => true,
            'check_attack_patterns' => true,
            'log_security_events' => true,
            'force_https' => filter_var($_ENV['FORCE_HTTPS'] ?? getenv('FORCE_HTTPS') ?: 'false', FILTER_VALIDATE_BOOLEAN)
        ];

        try {
            $this->db = \App\Database::getInstance();
        } catch (\Exception $e) {
            $this->db = null;
        }
    }

    /**
     * Processa a requisição
     */
    public function handle(): bool
    {
        $ip = $this->getClientIp();

        // 1. Verificar se IP está bloqueado
        if ($this->isIpBlocked($ip)) {
            $this->denyAccess('IP bloqueado', 403);
            return false;
        }

        // 2. Adicionar headers de segurança
        $this->addSecurityHeaders();

        // 3. Forçar HTTPS em produção (skip para localhost/dev server)
        if ($this->config['force_https'] && !$this->isHttps()) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $hostWithoutPort = explode(':', $host)[0];
            $isLocalhost = in_array($hostWithoutPort, ['localhost', '127.0.0.1', '::1'], true);
            if (!$isLocalhost) {
                $this->redirectToHttps();
                return false;
            }
        }

        // 4. Verificar user agent suspeito
        if ($this->config['block_suspicious_agents'] && $this->isSuspiciousAgent()) {
            $this->logSecurityEvent('suspicious_agent', $ip, 'warning');
            $this->denyAccess('User agent não permitido', 403);
            return false;
        }

        // 5. Verificar padrões de ataque
        if ($this->config['check_attack_patterns'] && $this->hasAttackPatterns()) {
            $this->logSecurityEvent('attack_pattern', $ip, 'critical');
            $this->blockIp($ip, 'Padrão de ataque detectado', 3600);
            $this->denyAccess('Requisição bloqueada', 403);
            return false;
        }

        // 6. Rate limiting
        if ($this->config['rate_limit_enabled'] && !$this->checkRateLimit($ip)) {
            $this->logSecurityEvent('rate_limit_exceeded', $ip, 'warning');
            $this->denyAccess('Muitas requisições. Tente novamente em alguns minutos.', 429);
            return false;
        }

        return true;
    }

    /**
     * Adiciona headers de segurança
     */
    private function addSecurityHeaders(): void
    {
        // Só definir headers se ainda não foram enviados
        if (headers_sent()) {
            return;
        }

        // HSTS - Força HTTPS
        if ($this->isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        // Prevenir clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // X-XSS-Protection removido: deprecated em browsers modernos (B2)
        // CSP com nonce já cobre proteção contra XSS

        // Prevenir MIME type sniffing
        header('X-Content-Type-Options: nosniff');

        // Política de referência
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Remover informações sensíveis
        header_remove('X-Powered-By');
        header_remove('Server');

        // Cache-Control: prevent proxies/CDNs from caching HTML with per-request CSP nonces.
        // Without this a cached response body could have an old nonce while the CSP header
        // is regenerated fresh → every inline script would be blocked.
        $requestPath = $_SERVER['REQUEST_URI'] ?? '/';
        $isApiRequest = str_starts_with($requestPath, '/api/') || str_starts_with($requestPath, '/webhook/');
        if (!$isApiRequest) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        // Content Security Policy
        // Nonces são gerados em public/index.php e armazenados como constante CSP_NONCE,
        // acessível de qualquer escopo sem depender de $GLOBALS ou sessão.
        $cspNonce = defined('CSP_NONCE') ? CSP_NONCE : (($GLOBALS['cspNonce'] ?: null) ?? ($_SESSION['csp_nonce'] ?? ''));
        $csp = "default-src 'self'; " .
            "script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
            "script-src-elem 'self' 'nonce-{$cspNonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://unpkg.com; " .
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; " .
            "connect-src 'self' https://api.mercadolibre.com https://api.mercadolivre.com.br https://cdn.jsdelivr.net https://cdnjs.cloudflare.com";
        header("Content-Security-Policy: $csp");

        // Permissions Policy (restrito)
        header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');

        // Cross-Origin isolation
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
    }

    /**
     * Obtém IP real do cliente
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',    // CloudFlare
            'HTTP_X_FORWARDED_FOR',     // Proxy
            'HTTP_X_REAL_IP',           // Nginx
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
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
     * Verifica se IP está bloqueado
     */
    private function isIpBlocked(string $ip): bool
    {
        if (in_array($ip, $this->whitelist)) {
            return false;
        }

        if (!$this->db) {
            return false;
        }

        try {
            $sql = "SELECT id FROM blocked_ips
                    WHERE ip_address = :ip
                    AND (blocked_until IS NULL OR blocked_until > NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['ip' => $ip]);

            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Bloqueia um IP
     */
    public function blockIp(string $ip, string $reason, int $durationSeconds = 0): bool
    {
        if (in_array($ip, $this->whitelist) || !$this->db) {
            return false;
        }

        try {
            $blockedUntil = $durationSeconds > 0
                ? date('Y-m-d H:i:s', time() + $durationSeconds)
                : null;

            $sql = "INSERT INTO blocked_ips (ip_address, reason, blocked_until, attempts)
                    VALUES (:ip, :reason, :blocked_until, 1)
                    ON DUPLICATE KEY UPDATE
                        reason = :reason2,
                        blocked_until = :blocked_until2,
                        attempts = attempts + 1,
                        updated_at = NOW()";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'ip' => $ip,
                'reason' => $reason,
                'blocked_until' => $blockedUntil,
                'reason2' => $reason,
                'blocked_until2' => $blockedUntil
            ]);
        } catch (\Exception $e) {
            log_error('Erro ao bloquear IP', ['service' => 'SecurityMiddleware', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Verifica user agent suspeito
     */
    private function isSuspiciousAgent(): bool
    {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Permitir user agents vazios de browsers modernos (Privacy features)
        // Apenas bloquear se contiver padrões suspeitos conhecidos
        if (empty($userAgent)) {
            return false; // Browsers modernos podem omitir UA por privacidade
        }

        foreach ($this->suspiciousAgents as $agent) {
            if (strpos($userAgent, $agent) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica padrões de ataque na requisição
     */
    private function hasAttackPatterns(): bool
    {
        $checkData = [
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['QUERY_STRING'] ?? '',
            file_get_contents('php://input') ?: ''
        ];

        $combined = implode(' ', $checkData);

        foreach ($this->attackPatterns as $pattern) {
            if (preg_match($pattern, $combined)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica rate limit
     */
    private function checkRateLimit(string $ip): bool
    {
        if (in_array($ip, $this->whitelist)) {
            return true;
        }

        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($ip);

        $data = ['count' => 0, 'reset' => time() + $this->config['rate_limit_window']];

        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);

            if ($cached && $cached['reset'] > time()) {
                $data = $cached;
            }
        }

        $data['count']++;

        if ($data['count'] > $this->config['rate_limit_max']) {
            return false;
        }

        file_put_contents($cacheFile, json_encode($data), LOCK_EX);

        return true;
    }

    /**
     * Verifica se está usando HTTPS
     */
    private function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) == 443
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Redireciona para HTTPS
     */
    private function redirectToHttps(): void
    {
        // Validate host against trusted domain to prevent host header injection
        $trustedDomain = $_ENV['APP_DOMAIN'] ?? 'eskill.com.br';
        $host = $_SERVER['HTTP_HOST'] ?? $trustedDomain;

        // Strip port for comparison
        $hostWithoutPort = strtolower(explode(':', $host)[0]);
        $trustedWithoutPort = strtolower(explode(':', $trustedDomain)[0]);

        if ($hostWithoutPort !== $trustedWithoutPort && $hostWithoutPort !== 'localhost') {
            $host = $trustedDomain;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Prevent protocol-relative redirect (//evil.com)
        if (!str_starts_with($uri, '/') || str_starts_with($uri, '//')) {
            $uri = '/';
        }

        header("Location: https://{$host}{$uri}", true, 301);
        exit;
    }

    /**
     * Nega acesso com resposta HTTP
     */
    private function denyAccess(string $message, int $code = 403): void
    {
        http_response_code($code);

        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'error' => true,
                'message' => $message,
                'code' => $code
            ]);
        } else {
            echo "<html><body><h1>Acesso Negado</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p></body></html>";
        }

        exit;
    }

    /**
     * Verifica se é requisição de API
     */
    private function isApiRequest(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return strpos($uri, '/api/') !== false
            || strpos($accept, 'application/json') !== false;
    }

    /**
     * Registra evento de segurança
     */
    public function logSecurityEvent(string $eventType, string $ip, string $severity = 'info', array $details = []): bool
    {
        if (!$this->config['log_security_events'] || !$this->db) {
            return false;
        }

        try {
            $sql = "INSERT INTO security_audit_log
                    (event_type, ip_address, user_agent, details, severity)
                    VALUES (:event_type, :ip, :user_agent, :details, :severity)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'event_type' => $eventType,
                'ip' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'details' => !empty($details) ? json_encode($details) : null,
                'severity' => $severity
            ]);
        } catch (\Exception $e) {
            log_error('Erro ao registrar evento de segurança', ['service' => 'SecurityMiddleware', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtém estatísticas de segurança
     */
    public function getSecurityStats(int $hours = 24): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $sql = "SELECT
                        event_type,
                        severity,
                        COUNT(*) as count
                    FROM security_audit_log
                    WHERE created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
                    GROUP BY event_type, severity";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['hours' => $hours]);

            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // IPs bloqueados
            $blockedSql = "SELECT COUNT(*) FROM blocked_ips WHERE blocked_until IS NULL OR blocked_until > NOW()";
            $blockedCount = $this->db->query($blockedSql)->fetchColumn();

            return [
                'events' => $events,
                'blocked_ips' => (int)$blockedCount,
                'period_hours' => $hours
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
