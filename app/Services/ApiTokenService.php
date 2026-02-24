<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * ApiTokenService
 *
 * Gerenciamento de tokens de API para autenticação de terceiros
 * - Criar/revogar tokens
 * - Validar tokens
 * - Controlar escopos e permissões
 * - Rate limiting por token
 */
class ApiTokenService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                scopes JSON NULL,
                is_active TINYINT(1) DEFAULT 1,
                last_used_at TIMESTAMP NULL,
                expires_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_token (token),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Criar novo token de API
     */
    public function createToken(int $userId, string $name, array $scopes = [], ?int $expiresInDays = null): array
    {
        $token = $this->generateSecureToken();

        $expiresAt = null;
        if ($expiresInDays) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiresInDays} days"));
        }

        $stmt = $this->db->prepare("
            INSERT INTO api_tokens (user_id, token, name, scopes, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, TRUE)
        ");

        $scopesJson = json_encode($scopes);
        $stmt->execute([$userId, $token, $name, $scopesJson, $expiresAt]);

        return [
            'id' => $this->db->lastInsertId(),
            'token' => $token,
            'name' => $name,
            'scopes' => $scopes,
            'expires_at' => $expiresAt
        ];
    }

    /**
     * Validar token
     */
    public function validateToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.id as user_id, u.email, u.name
            FROM api_tokens t
            JOIN users u ON t.user_id = u.id
            WHERE t.token = ?
            AND t.is_active = 1
            AND (t.expires_at IS NULL OR t.expires_at > NOW())
        ");

        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            return null;
        }

        // Atualizar last_used_at
        $this->updateLastUsed($tokenData['id']);

        // Decodificar scopes
        $tokenData['scopes'] = json_decode($tokenData['scopes'] ?? '[]', true);

        return $tokenData;
    }

    /**
     * Verificar se token tem permissão para scope específico
     */
    public function hasScope(array $tokenData, string $scope): bool
    {
        $scopes = $tokenData['scopes'] ?? [];

        // Se tiver scope '*', tem permissão total
        if (in_array('*', $scopes)) {
            return true;
        }

        return in_array($scope, $scopes);
    }

    /**
     * Listar tokens do usuário
     */
    public function listTokens(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, scopes, last_used_at, expires_at, is_active, created_at,
                   SUBSTRING(token, 1, 8) as token_preview
            FROM api_tokens
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");

        $stmt->execute([$userId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decodificar scopes
        foreach ($tokens as &$token) {
            $token['scopes'] = json_decode($token['scopes'] ?? '[]', true);
            $token['token_preview'] .= '...';
        }

        return $tokens;
    }

    /**
     * Revogar token
     */
    public function revokeToken(int $tokenId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_tokens
            SET is_active = FALSE, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$tokenId, $userId]);
    }

    /**
     * Atualizar nome do token
     */
    public function updateTokenName(int $tokenId, int $userId, string $name): bool
    {
        $stmt = $this->db->prepare("
            UPDATE api_tokens
            SET name = ?, updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$name, $tokenId, $userId]);
    }

    /**
     * Atualizar último uso
     */
    private function updateLastUsed(int $tokenId): void
    {
        $stmt = $this->db->prepare("
            UPDATE api_tokens
            SET last_used_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$tokenId]);
    }

    /**
     * Gerar token seguro
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 caracteres
    }

    /**
     * Estatísticas de uso do token
     */
    public function getTokenStats(int $tokenId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.id,
                t.name,
                t.created_at,
                t.last_used_at,
                DATEDIFF(NOW(), t.created_at) as days_active,
                t.expires_at,
                t.is_active
            FROM api_tokens t
            WHERE t.id = ? AND t.user_id = ?
        ");

        $stmt->execute([$tokenId, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Limpar tokens expirados
     */
    public function cleanExpiredTokens(): int
    {
        $stmt = $this->db->prepare("
            UPDATE api_tokens
            SET is_active = FALSE
            WHERE expires_at IS NOT NULL
            AND expires_at < NOW()
            AND is_active = TRUE
        ");

        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Escopos disponíveis
     */
    public static function getAvailableScopes(): array
    {
        return [
            '*' => 'Acesso total (não recomendado)',
            'read' => 'Leitura de dados',
            'write' => 'Escrita de dados',
            'assistant:read' => 'Assistant Connector — leitura (sellers, status de actions)',
            'assistant:write' => 'Assistant Connector — escrita (eventos, criar actions)',
            'assistant:admin' => 'Assistant Connector — admin (leitura+escrita)',
            'openclaw:read' => 'OpenClaw — leitura (sellers, items, orders, analytics)',
            'openclaw:write' => 'OpenClaw — escrita (actions, events, webhooks)',
            'openclaw:admin' => 'OpenClaw — acesso total (leitura+escrita+admin)',
            'orders:read' => 'Ler pedidos',
            'orders:write' => 'Gerenciar pedidos',
            'items:read' => 'Ler anúncios',
            'items:write' => 'Gerenciar anúncios',
            'reports:read' => 'Gerar relatórios',
            'analytics:read' => 'Acessar análises',
            'webhooks:write' => 'Configurar webhooks'
        ];
    }
}
