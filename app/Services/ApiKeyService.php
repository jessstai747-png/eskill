<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class ApiKeyService
{
    private $db;
    private $accountId;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    public function createKey(string $name, array $permissions = ['read']): array
    {
        $clientId = bin2hex(random_bytes(16)); // 32 chars
        $clientSecret = bin2hex(random_bytes(32)); // 64 chars
        $secretHash = password_hash($clientSecret, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO user_api_keys (account_id, client_id, client_secret_hash, name, permissions) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->accountId,
            $clientId,
            $secretHash,
            $name,
            json_encode($permissions)
        ]);

        return [
            'client_id' => $clientId,
            'client_secret' => $clientSecret, // Returned only once
            'name' => $name
        ];
    }

    public function listKeys(): array
    {
        $stmt = $this->db->prepare("SELECT id, name, client_id, created_at, last_used_at, status FROM user_api_keys WHERE account_id = ? AND status = 'active'");
        $stmt->execute([$this->accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revokeKey(string $clientId): bool
    {
        $stmt = $this->db->prepare("UPDATE user_api_keys SET status = 'revoked' WHERE account_id = ? AND client_id = ?");
        return $stmt->execute([$this->accountId, $clientId]);
    }

    // Static method for Middleware validation
    public static function validateKey(string $clientId, string $clientSecret): ?int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT account_id, client_secret_hash, status FROM user_api_keys WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($key && $key['status'] === 'active' && password_verify($clientSecret, $key['client_secret_hash'])) {
            // Update last used
            $update = $db->prepare("UPDATE user_api_keys SET last_used_at = NOW() WHERE client_id = ?");
            $update->execute([$clientId]);
            
            return (int)$key['account_id'];
        }

        return null;
    }
}
