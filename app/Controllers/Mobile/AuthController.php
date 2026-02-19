<?php

namespace App\Controllers\Mobile;

use App\Database;

class AuthController
{
    private $db;

    /** @var string Secret key for signing mobile tokens */
    private string $tokenSecret;

    /** @var int Token TTL in seconds (default: 30 days) */
    private int $tokenTtl;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->tokenSecret = $_ENV['MOBILE_TOKEN_SECRET'] ?? $_ENV['APP_KEY'] ?? '';
        $this->tokenTtl = (int) ($_ENV['MOBILE_TOKEN_TTL'] ?? 2592000);

        if (empty($this->tokenSecret) || strlen($this->tokenSecret) < 32) {
            throw new \RuntimeException('MOBILE_TOKEN_SECRET or APP_KEY must be set (min 32 chars)');
        }
    }

    public function login(): void
    {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        
        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Email e senha obrigatórios']);
            return;
        }

        $stmt = $this->db->prepare("SELECT id, name, password, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $token = $this->generateSignedToken($user['id']);
            
            // Register Device if provided
            if (!empty($data['device_token'])) {
                $this->registerDevice($user['id'], $data);
            }

            echo json_encode([
                'success' => true,
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Credenciais inválidas']);
        }
    }

    /**
     * Generate a cryptographically signed token.
     * Format: base64(payload).base64(hmac_signature)
     */
    private function generateSignedToken(int $userId): string
    {
        $payload = json_encode([
            'uid' => $userId,
            'iat' => time(),
            'exp' => time() + $this->tokenTtl,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        $encodedPayload = rtrim(base64_encode($payload), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $this->tokenSecret);

        return $encodedPayload . '.' . $signature;
    }

    /**
     * Validate a signed mobile token.
     * Returns the decoded payload array or null if invalid/expired.
     */
    public static function validateSignedToken(string $token): ?array
    {
        $secret = $_ENV['MOBILE_TOKEN_SECRET'] ?? $_ENV['APP_KEY'] ?? '';
        if (empty($secret)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(base64_decode($encodedPayload), true);
        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private function registerDevice($userId, $data): void
    {
        $stmt = $this->db->prepare("INSERT INTO mobile_devices (user_id, device_token, device_name, platform) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_active = NOW()");
        $stmt->execute([
            $userId,
            $data['device_token'],
            $data['device_name'] ?? 'Unknown',
            $data['platform'] ?? 'android'
        ]);
    }
}
