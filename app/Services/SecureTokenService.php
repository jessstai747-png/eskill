<?php

namespace App\Services;

/**
 * SecureTokenService - Gerenciamento Seguro de Tokens ML
 * 
 * Armazena e recupera tokens do Mercado Livre de forma criptografada.
 */
class SecureTokenService
{
    private EncryptionService $encryption;
    private \PDO $db;

    public function __construct()
    {
        $this->encryption = new EncryptionService();
        $this->db = \App\Database::getInstance();
    }

    /**
     * Armazena tokens de forma criptografada
     */
    public function storeTokens(int $accountId, array $tokens): bool
    {
        $encryptedAccessToken = $this->encryption->encrypt($tokens['access_token']);
        $encryptedRefreshToken = $this->encryption->encrypt($tokens['refresh_token']);

        $sql = "UPDATE ml_accounts SET 
                    access_token = :access_token,
                    refresh_token = :refresh_token,
                    token_expires_at = :expires_at,
                    tokens_encrypted = 1,
                    updated_at = NOW()
                WHERE id = :account_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'access_token' => $encryptedAccessToken,
                'refresh_token' => $encryptedRefreshToken,
                'expires_at' => date('Y-m-d H:i:s', $tokens['expires_at']),
                'account_id' => $accountId
            ]);

            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            log_error('Erro ao armazenar tokens', ['service' => 'SecureTokenService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Recupera tokens descriptografados
     */
    public function getTokens(int $accountId): ?array
    {
        $sql = "SELECT access_token, refresh_token, token_expires_at, tokens_encrypted 
                FROM ml_accounts 
                WHERE id = :account_id AND status = 'active'";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['account_id' => $accountId]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$account) {
                return null;
            }

            // Verificar se tokens estão criptografados
            if ($account['tokens_encrypted']) {
                return [
                    'access_token' => $this->encryption->decrypt($account['access_token']),
                    'refresh_token' => $this->encryption->decrypt($account['refresh_token']),
                    'expires_at' => strtotime($account['token_expires_at'])
                ];
            }

            // Tokens não criptografados (legado)
            return [
                'access_token' => $account['access_token'],
                'refresh_token' => $account['refresh_token'],
                'expires_at' => strtotime($account['token_expires_at'])
            ];
        } catch (\Exception $e) {
            log_error('Erro ao recuperar tokens', ['service' => 'SecureTokenService', 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verifica se o token está expirado
     */
    public function isTokenExpired(int $accountId, int $bufferSeconds = 300): bool
    {
        $tokens = $this->getTokens($accountId);

        if (!$tokens) {
            return true;
        }

        return ($tokens['expires_at'] - $bufferSeconds) < time();
    }

    /**
     * Migra tokens não criptografados para criptografados
     */
    public function migrateUnencryptedTokens(): array
    {
        $sql = "SELECT id, access_token, refresh_token, token_expires_at 
                FROM ml_accounts 
                WHERE tokens_encrypted = 0 OR tokens_encrypted IS NULL";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $migrated = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            try {
                // Verificar se já está criptografado
                if ($this->encryption->isEncrypted($account['access_token'])) {
                    // Já está criptografado, apenas marcar
                    $updateSql = "UPDATE ml_accounts SET tokens_encrypted = 1 WHERE id = :id";
                    $this->db->prepare($updateSql)->execute(['id' => $account['id']]);
                    $migrated++;
                    continue;
                }

                // Criptografar tokens
                $tokens = [
                    'access_token' => $account['access_token'],
                    'refresh_token' => $account['refresh_token'],
                    'expires_at' => strtotime($account['token_expires_at'])
                ];

                if ($this->storeTokens($account['id'], $tokens)) {
                    $migrated++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                log_error('Erro ao migrar conta', ['service' => 'SecureTokenService', 'account_id' => $account['id'], 'error' => $e->getMessage()]);
                $failed++;
            }
        }

        return [
            'total' => count($accounts),
            'migrated' => $migrated,
            'failed' => $failed
        ];
    }

    /**
     * Revoga tokens (invalida no banco)
     */
    public function revokeTokens(int $accountId): bool
    {
        $sql = "UPDATE ml_accounts SET 
                    access_token = NULL,
                    refresh_token = NULL,
                    token_expires_at = NULL,
                    tokens_encrypted = 0,
                    status = 'revoked',
                    updated_at = NOW()
                WHERE id = :account_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['account_id' => $accountId]);
            return $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            log_error('Erro ao revogar tokens', ['service' => 'SecureTokenService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Obtém access token válido, renovando se necessário
     */
    public function getValidAccessToken(int $accountId): ?string
    {
        $tokens = $this->getTokens($accountId);

        if (!$tokens) {
            return null;
        }

        // Verificar expiração (com buffer de 5 minutos)
        if ($this->isTokenExpired($accountId, 300)) {
            // Tentar renovar
            $newTokens = $this->refreshAccessToken($accountId, $tokens['refresh_token']);

            if ($newTokens) {
                return $newTokens['access_token'];
            }

            return null;
        }

        return $tokens['access_token'];
    }

    /**
     * Renova access token usando refresh token
     */
    private function refreshAccessToken(int $accountId, string $refreshToken): ?array
    {
        // Obter credenciais do ML
        $clientId = getenv('ML_CLIENT_ID');
        $clientSecret = getenv('ML_CLIENT_SECRET');

        if (empty($clientId) || empty($clientSecret)) {
            log_error('Credenciais ML não configuradas', ['service' => 'SecureTokenService']);
            return null;
        }

        $postData = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ];

        $ch = curl_init('https://api.mercadolibre.com/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            log_error('Falha ao renovar token', ['service' => 'SecureTokenService', 'http_code' => $httpCode, 'response' => $response]);
            return null;
        }

        $data = json_decode($response, true);

        if (!isset($data['access_token'])) {
            log_error('Resposta inválida ao renovar token', ['service' => 'SecureTokenService', 'response' => $response]);
            return null;
        }

        $tokens = [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => time() + ($data['expires_in'] ?? 21600)
        ];

        // Armazenar novos tokens
        $this->storeTokens($accountId, $tokens);

        return $tokens;
    }
}
