<?php

declare(strict_types=1);

namespace App\Services;

/**
 * JwtService - Minimal JWT implementation using HMAC-SHA256
 */
class JwtService
{
    private string $secret;
    private string $issuer;

    public function __construct()
    {
        $config = \App\Core\Config::getInstance()->all();
        // Load secret from canonical config only. Do NOT fallback to other sources here.
        $env = $config['env'] ?? ($_ENV['APP_ENV'] ?? 'development');

        $this->secret = $config['key'] ?? '';

        // Enforce strong APP_KEY in production. Fail fast.
        if ($env === 'production') {
            if (empty($this->secret) || strlen($this->secret) < 32) {
                throw new \RuntimeException('APP_KEY is not configured or too weak for production - cannot create JWT');
            }
        } else {
            if (empty($this->secret)) {
                throw new \RuntimeException('APP_KEY is not configured - cannot create JWT');
            }
        }

        $this->issuer = $config['url'] ?? ($_ENV['APP_URL'] ?? '');
        if (empty($this->issuer)) {
            if ($env === 'production') {
                throw new \RuntimeException('APP_URL must be configured for JWT issuer in production');
            }
            // In development only, allow fallback
            if (!empty($_SERVER['HTTP_HOST'])) {
                $isHttpsRequest = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (($_SERVER['SERVER_PORT'] ?? null) == 443)
                    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
                $scheme = $isHttpsRequest ? 'https' : 'http';
                $this->issuer = $scheme . '://' . $_SERVER['HTTP_HOST'];
            } else {
                $this->issuer = 'http://localhost';
            }
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function generateToken(int $userId, int $ttlSeconds = 900): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $this->issuer,
            'sub' => (string)$userId,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'jti' => bin2hex(random_bytes(16))
        ];

        $headerJson = json_encode($header);
        if ($headerJson === false) {
            throw new \RuntimeException('Failed to encode JWT header to JSON');
        }

        $payloadJson = json_encode($payload);
        if ($payloadJson === false) {
            throw new \RuntimeException('Failed to encode JWT payload to JSON');
        }

        $head = $this->base64UrlEncode($headerJson);
        $body = $this->base64UrlEncode($payloadJson);

        $sig = hash_hmac('sha256', $head . '.' . $body, $this->secret, true);
        $signature = $this->base64UrlEncode($sig);

        return $head . '.' . $body . '.' . $signature;
    }

    /**
     * Validate token and return payload array or null if invalid/expired
     */
    public function validateToken(string $token): ?array
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) return null;

            [$headB, $bodyB, $sigB] = $parts;

            $expected = $this->base64UrlEncode(hash_hmac('sha256', $headB . '.' . $bodyB, $this->secret, true));
            if (!hash_equals($expected, $sigB)) return null;

            $payloadJson = $this->base64UrlDecode($bodyB);
            $payload = json_decode($payloadJson, true);
            if (!is_array($payload)) return null;

            if (isset($payload['exp']) && time() > (int)$payload['exp']) {
                return null;
            }

            return $payload;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convenience: get user id from token (or null)
     */
    public function getUserIdFromToken(string $token): ?int
    {
        $payload = $this->validateToken($token);
        if (!$payload || !isset($payload['sub'])) return null;
        return (int)$payload['sub'];
    }
}
