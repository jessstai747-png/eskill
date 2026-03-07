<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * RefreshTokenService - stores rotatable refresh tokens (selector:validator)
 */
class RefreshTokenService
{
    private \PDO $db;
    private int $defaultDays = 30;

    private static bool $tableVerified = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        // Verificar tabela apenas uma vez por request (não em cada instanciação)
        if (!self::$tableVerified) {
            $this->ensureTable();
            self::$tableVerified = true;
        }
    }

    private function ensureTable(): void
    {
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                selector TEXT NOT NULL,
                hashed_validator TEXT NOT NULL,
                device_info TEXT,
                ip_address TEXT,
                expires_at DATETIME NOT NULL,
                revoked INTEGER DEFAULT 0,
                replaced_by INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );";
        } else {
            // MySQL / MariaDB
            $sql = "CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                selector VARCHAR(64) NOT NULL,
                hashed_validator VARCHAR(255) NOT NULL,
                device_info VARCHAR(255),
                ip_address VARCHAR(45),
                expires_at DATETIME NOT NULL,
                revoked TINYINT(1) DEFAULT 0,
                replaced_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_selector (selector),
                INDEX idx_user (user_id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        }

        $this->db->exec($sql);
    }

    /**
     * Create a new refresh token and return the raw token (selector:validator)
     */
    public function createToken(int $userId, ?string $deviceInfo = null, int $days = null): string
    {
        $days = $days ?? $this->defaultDays;
        $selector = bin2hex(random_bytes(9));
        $validator = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + ($days * 24 * 60 * 60));

        // Use parameterized created_at so it works on both MySQL and SQLite
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("INSERT INTO refresh_tokens
            (user_id, selector, hashed_validator, device_info, ip_address, expires_at, revoked, created_at)
            VALUES (:user_id, :selector, :hashed, :device_info, :ip_address, :expires_at, 0, :created_at)");

        $stmt->execute([
            'user_id' => $userId,
            'selector' => $selector,
            'hashed' => $hashed,
            'device_info' => $deviceInfo,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'expires_at' => $expiresAt,
            'created_at' => $createdAt
        ]);

        return $selector . ':' . $validator;
    }

    /**
     * Validate a provided refresh token and rotate it (invalidate old, create new).
     * Returns array with keys: user_id, new_refresh_token
     */
    public function validateAndRotate(string $providedToken): ?array
    {
        $parts = explode(':', $providedToken, 2);
        if (count($parts) !== 2) return null;
        [$selector, $validator] = $parts;

        $stmt = $this->db->prepare('SELECT * FROM refresh_tokens WHERE selector = :selector LIMIT 1');
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) return null;
        if ((int)$row['revoked'] === 1) return null;
        if (strtotime($row['expires_at']) < time()) return null;

        $hashed = hash('sha256', $validator);
        if (!hash_equals($row['hashed_validator'], $hashed)) {
            // Possible theft - revoke this selector to prevent repeated attempts
            $upd = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE id = :id');
            $upd->execute(['id' => $row['id']]);
            return null;
        }

        // Rotate: create new token
        $device = $row['device_info'] ?? null;
        $newToken = $this->createToken((int)$row['user_id'], $device, $this->defaultDays);

        // Mark old as revoked and link
        $newId = (int)$this->db->lastInsertId();
        $upd = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1, replaced_by = :new_id WHERE id = :id');
        $upd->execute(['new_id' => $newId, 'id' => $row['id']]);

        return ['user_id' => (int)$row['user_id'], 'refresh_token' => $newToken];
    }

    /**
     * Revoke a refresh token (by raw token) or by selector
     */
    public function revokeToken(string $providedToken): bool
    {
        $parts = explode(':', $providedToken, 2);
        if (count($parts) !== 2) return false;
        $selector = $parts[0];

        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Revoke all refresh tokens for a user (logout all sessions)
     */
    public function revokeAllForUser(int $userId): int
    {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Find a refresh token record by selector (internal)
     */
    public function findBySelector(string $selector): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM refresh_tokens WHERE selector = :selector LIMIT 1');
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Remove tokens expirados e registros revogados antigos.
     * Retorna número de linhas removidas.
     */
    public function cleanupExpiredAndPrune(int $olderThanDays = 30): int
    {
        $driver = $this->db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $cutoff = date('Y-m-d H:i:s', time() - ($olderThanDays * 24 * 60 * 60));

        if ($driver === 'sqlite') {
            // SQLite: remove tokens where (revoked = 1 AND created_at < cutoff) OR (expires_at < now AND created_at < cutoff)
            $sql = 'DELETE FROM refresh_tokens WHERE (revoked = 1 AND created_at < :cutoff) OR (expires_at < :now AND created_at < :cutoff)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['cutoff' => $cutoff, 'now' => date('Y-m-d H:i:s')]);
            return $stmt->rowCount();
        }

        // MySQL: use unique named placeholders to avoid PDO driver issues when emulation
        // of prepared statements is disabled (some drivers do not support repeated named params)
        $sql = 'DELETE FROM refresh_tokens WHERE (revoked = 1 AND created_at < :cutoff) OR (expires_at < :now AND created_at < :cutoff2)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cutoff' => $cutoff, 'cutoff2' => $cutoff, 'now' => date('Y-m-d H:i:s')]);
        return $stmt->rowCount();
    }
}
