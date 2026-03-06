<?php

declare(strict_types=1);

namespace App\Services;

class SecurityService
{
    private string $encryptionKey;

    public function __construct()
    {
        $config = \App\Core\Config::getInstance()->all();
        // Load key from canonical config only. Do NOT fallback to other sources.
        $env = $config['env'] ?? ($_ENV['APP_ENV'] ?? 'development');

        $this->encryptionKey = $config['key'] ?? '';

        // In production, require a strong APP_KEY. Fail fast.
        if ($env === 'production') {
            if (empty($this->encryptionKey) || strlen($this->encryptionKey) < 32) {
                throw new \Exception("Chave de criptografia insegura ou não configurada. Configure APP_KEY forte no .env para produção");
            }
        } else {
            if (empty($this->encryptionKey)) {
                throw new \Exception("Chave de criptografia não configurada. Configure APP_KEY no .env");
            }
        }
    }

    /**
     * Criptografa dados sensíveis (tokens)
     */
    public function encrypt(string $data): string
    {
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        if (!is_int($ivLength) || $ivLength <= 0) {
            throw new \RuntimeException('Falha ao determinar o tamanho do IV para criptografia');
        }

        $iv = random_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $data,
            $method,
            hash('sha256', $this->encryptionKey),
            0,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Falha ao criptografar dados');
        }

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Descriptografa dados
     */
    public function decrypt(string $encryptedData): string
    {
        $method = 'AES-256-CBC';
        $decoded = base64_decode($encryptedData, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Dados criptografados inválidos (base64)');
        }

        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException('Dados criptografados inválidos (formato)');
        }

        [$encrypted, $iv] = $parts;

        $decrypted = openssl_decrypt(
            $encrypted,
            $method,
            hash('sha256', $this->encryptionKey),
            0,
            $iv
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Falha ao descriptografar dados');
        }

        return $decrypted;
    }

    /**
     * Retorna token CSRF atual ou gera um novo se necessário
     */
    public function getCsrfToken(): string
    {
        if (!$this->ensureSession()) {
            return bin2hex(random_bytes(32));
        }

        if (!empty($_SESSION['csrf_token']) && isset($_SESSION['csrf_token_time'])) {
            $isExpired = (time() - $_SESSION['csrf_token_time']) > 3600;
            if (!$isExpired) {
                return $_SESSION['csrf_token'];
            }
        }

        return $this->createCsrfToken();
    }

    /**
     * Gera e armazena um novo token CSRF
     */
    public function generateCsrfToken(): string
    {
        if (!$this->ensureSession()) {
            return bin2hex(random_bytes(32));
        }

        return $this->createCsrfToken();
    }

    /**
     * Cria token CSRF e persiste na sessão
     */
    private function createCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();

        return $token;
    }

    /**
     * Valida token CSRF
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!$this->ensureSession()) {
            return false;
        }

        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Token expira em 1 hora
        if (time() - $_SESSION['csrf_token_time'] > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Garante sessão disponível sem disparar warnings em CLI
     */
    private function ensureSession(): bool
    {
        if (PHP_SAPI === 'cli') {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            return true;
        }

        if (session_status() === PHP_SESSION_NONE) {
            if (headers_sent()) {
                return false;
            }
            session_start();
        }

        return session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Sanitiza entrada para prevenir XSS
     */
    public function sanitize(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Valida e sanitiza array de dados
     */
    public function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $key = $this->sanitize($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } else {
                $sanitized[$key] = $this->sanitize((string)$value);
            }
        }

        return $sanitized;
    }

    /**
     * Gera hash seguro para senhas
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verifica senha
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Gera token aleatório seguro
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
}
