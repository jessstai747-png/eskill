<?php

declare(strict_types=1);

namespace App\Services;

/**
 * SecureTokenService - Gerenciamento Seguro de Tokens ML
 *
 * Armazena e recupera tokens do Mercado Livre de forma criptografada.
 */
class SecureTokenService
{
    private const TOKEN_REFRESH_MAX_RETRIES = 3;

    private EncryptionService $encryption;
    private \PDO $db;
    private array $config;

    public function __construct()
    {
        $this->encryption = new EncryptionService();
        $this->db = \App\Database::getInstance();
        $this->config = \App\Core\Config::getInstance()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function getMercadoLivreConfig(): array
    {
        $config = $this->config['mercadolivre'] ?? [];
        return is_array($config) ? $config : [];
    }

    private function getMercadoLivreTokenUrl(): string
    {
        return (string)($this->getMercadoLivreConfig()['token_url'] ?? 'https://api.mercadolibre.com/oauth/token');
    }

    private function shouldRetryTokenRequest(int $httpCode, string $curlError): bool
    {
        if ($curlError !== '') {
            return true;
        }

        return $httpCode === 408 || $httpCode === 429 || ($httpCode >= 500 && $httpCode < 600);
    }

    private function calculateTokenRetryDelaySeconds(int $attempt): int
    {
        $base = max(1, min(30, (int)($_ENV['ML_TRANSIENT_RETRY_BASE_SECONDS'] ?? getenv('ML_TRANSIENT_RETRY_BASE_SECONDS') ?? 2)));
        $maxDelay = max($base, min(60, (int)($_ENV['ML_TRANSIENT_RETRY_MAX_SECONDS'] ?? getenv('ML_TRANSIENT_RETRY_MAX_SECONDS') ?? 30)));
        $jitterMax = max(0, min(5, (int)($_ENV['ML_TRANSIENT_RETRY_JITTER_SECONDS'] ?? getenv('ML_TRANSIENT_RETRY_JITTER_SECONDS') ?? 1)));

        $delay = $base * (int) pow(2, max(0, $attempt - 1));
        $delay = min($maxDelay, $delay);
        $jitter = $jitterMax > 0 ? random_int(0, $jitterMax) : 0;

        return $delay + $jitter;
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
     * Renova access token delegando para MercadoLivreAuthService (implementação canônica com lock, retry e audit log).
     * O parâmetro $refreshToken é mantido por compatibilidade de assinatura; o token é relido do banco pelo delegate.
     */
    private function refreshAccessToken(int $accountId, string $refreshToken): ?array
    {
        $authService = new MercadoLivreAuthService();
        if (!$authService->refreshToken($accountId)) {
            log_error('Falha ao renovar token ML', [
                'service'    => 'SecureTokenService',
                'account_id' => $accountId,
            ]);
            return null;
        }

        return $this->getTokens($accountId);
    }
}
