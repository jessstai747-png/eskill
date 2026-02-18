<?php

declare(strict_types=1);

namespace App\Middleware;

/**
 * Security Headers Middleware
 * Adds security headers to all responses
 */
class SecurityHeadersMiddleware
{
    /**
     * Apply security headers
     */
    public function handle(): void
    {
        // Fonte de verdade dos headers é SecurityMiddleware (public/index.php).
        // Legacy middleware fica desativado por padrão para evitar drift/duplicação.
        if ((getenv('SECURITY_HEADERS_LEGACY_ENABLED') ?: 'false') !== 'true') {
            return;
        }

        // HSTS - Force HTTPS for 1 year
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

        // Prevent clickjacking
        header('X-Frame-Options: DENY');

        // Prevent MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // XSS Protection (legacy browsers)
        header('X-XSS-Protection: 1; mode=block');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions Policy (formerly Feature Policy)
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

        // Content Security Policy
        $csp = $this->getContentSecurityPolicy();
        header("Content-Security-Policy: {$csp}");
    }

    /**
     * Get Content Security Policy
     */
    private function getContentSecurityPolicy(): string
    {
        $isProduction = ($_ENV['APP_ENV'] ?? 'development') === 'production';
        $cspNonce = $_SESSION['csp_nonce'] ?? '';

        if ($isProduction) {
            // Strict CSP for production
            // Uses nonce + strict-dynamic for scripts (unsafe-inline ignored by CSP2+ browsers when nonce present)
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$cspNonce}' 'strict-dynamic' https://unpkg.com https://cdn.jsdelivr.net",
                "script-src-elem 'self' 'nonce-{$cspNonce}' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
                "style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com",
                "img-src 'self' data: https:",
                "font-src 'self' https://fonts.gstatic.com",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'"
            ]);
        } else {
            // Relaxed CSP for development
            return implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",
                "style-src 'self' 'unsafe-inline' https:",
                "img-src 'self' data: https:",
                "font-src 'self' https:",
                "connect-src 'self' ws: wss:",
                "frame-ancestors 'none'"
            ]);
        }
    }

    /**
     * Check if request is over HTTPS
     */
    public static function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $_SERVER['SERVER_PORT'] == 443
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    /**
     * Redirect to HTTPS if not secure
     */
    public static function forceHttps(): void
    {
        if (!self::isSecure() && ($_ENV['APP_ENV'] ?? 'development') === 'production') {
            // Validate host against trusted domain to prevent host header injection
            $trustedDomain = $_ENV['APP_DOMAIN'] ?? 'eskill.com.br';
            $host = $_SERVER['HTTP_HOST'] ?? $trustedDomain;
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

            $redirect = 'https://' . $host . $uri;
            header('HTTP/1.1 301 Moved Permanently');
            header('Location: ' . $redirect);
            exit;
        }
    }
}
