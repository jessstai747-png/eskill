<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use App\Services\CacheService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

/**
 * Token Manager for Mercado Livre OAuth 2.0
 * 
 * Handles automatic token refresh with rotating refresh tokens
 * Provides thread-safe token management for multi-account scenarios
 */
class TokenManager
{
    private \PDO $db;
    private CacheService $cache;
    private array $config;
    
    // Token refresh threshold (5 minutes before expiration)
    private const REFRESH_THRESHOLD_SECONDS = 300;
    
    // Cache TTL for tokens (1 hour)
    private const CACHE_TTL = 3600;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cache = new CacheService();
        
        $this->config = [
            'client_id' => $_ENV['ML_CLIENT_ID'] ?? '',
            'client_secret' => $_ENV['ML_CLIENT_SECRET'] ?? '',
            'redirect_uri' => $_ENV['ML_REDIRECT_URI'] ?? '',
        ];
    }
    
    /**
     * Get a valid access token for an account
     * Automatically refreshes if needed
     * 
     * @param int $accountId
     * @return string Valid access token
     * @throws Exception If token cannot be obtained
     */
    public function getValidToken(int $accountId): string
    {
        // Try cache first
        $cacheKey = "ml_token_{$accountId}";
        $cachedToken = $this->cache->get($cacheKey);
        
        if ($cachedToken) {
            return $cachedToken;
        }
        
        // Get from database
        $account = $this->getAccountTokenData($accountId);
        
        if (!$account) {
            throw new Exception("Account not found: {$accountId}");
        }
        
        // Check if token needs refresh
        if ($this->needsRefresh($account)) {
            $this->refreshToken($accountId, $account['refresh_token']);
            // Re-fetch after refresh
            $account = $this->getAccountTokenData($accountId);
        }
        
        // Cache the token
        $this->cache->set($cacheKey, $account['access_token'], self::CACHE_TTL);
        
        return $account['access_token'];
    }
    
    /**
     * Check if token needs refresh
     * 
     * @param array $account Account data with expires_at
     * @return bool True if token should be refreshed
     */
    private function needsRefresh(array $account): bool
    {
        if (empty($account['expires_at'])) {
            return true;
        }
        
        $expiresAt = strtotime($account['expires_at']);
        $now = time();
        
        // Refresh if expires within threshold
        return ($expiresAt - $now) < self::REFRESH_THRESHOLD_SECONDS;
    }
    
    /**
     * Refresh access token using refresh token
     * 
     * @param int $accountId
     * @param string $refreshToken
     * @return array New token data
     * @throws Exception If refresh fails
     */
    public function refreshToken(int $accountId, string $refreshToken): array
    {
        $client = new Client([
            'base_uri' => 'https://api.mercadolibre.com',
            'timeout' => 30,
        ]);
        
        try {
            $response = $client->post('/oauth/token', [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'refresh_token' => $refreshToken,
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['access_token'])) {
                throw new Exception('Invalid token response from Mercado Livre');
            }
            
            // Update database with new tokens
            $this->updateTokens($accountId, $data);
            
            // Invalidate cache
            $this->cache->delete("ml_token_{$accountId}");
            
            // Log successful refresh
            $this->logTokenRefresh($accountId, 'success');
            
            return $data;
            
        } catch (RequestException $e) {
            $this->logTokenRefresh($accountId, 'failed', $e->getMessage());
            
            $response = $e->getResponse();
            if ($response) {
                $body = json_decode($response->getBody()->getContents(), true);
                $message = $body['message'] ?? $e->getMessage();
            } else {
                $message = $e->getMessage();
            }
            
            throw new Exception("Token refresh failed: {$message}");
        }
    }
    
    /**
     * Exchange authorization code for tokens
     * 
     * @param string $code Authorization code from OAuth callback
     * @param int $accountId Account ID to associate tokens with
     * @return array Token data
     * @throws Exception If exchange fails
     */
    public function exchangeCodeForTokens(string $code, int $accountId): array
    {
        $client = new Client([
            'base_uri' => 'https://api.mercadolibre.com',
            'timeout' => 30,
        ]);
        
        try {
            $response = $client->post('/oauth/token', [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->config['client_id'],
                    'client_secret' => $this->config['client_secret'],
                    'code' => $code,
                    'redirect_uri' => $this->config['redirect_uri'],
                ],
            ]);
            
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($data['access_token'])) {
                throw new Exception('Invalid token response from Mercado Livre');
            }
            
            // Update database with new tokens
            $this->updateTokens($accountId, $data);
            
            return $data;
            
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $body = json_decode($response->getBody()->getContents(), true);
                $message = $body['message'] ?? $e->getMessage();
            } else {
                $message = $e->getMessage();
            }
            
            throw new Exception("Token exchange failed: {$message}");
        }
    }
    
    /**
     * Update tokens in database
     * 
     * @param int $accountId
     * @param array $tokenData Token data from ML API
     */
    private function updateTokens(int $accountId, array $tokenData): void
    {
        $expiresAt = date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 21600));
        
        $stmt = $this->db->prepare(
            "UPDATE ml_accounts 
             SET access_token = :access_token,
                 refresh_token = :refresh_token,
                 expires_at = :expires_at,
                 updated_at = NOW()
             WHERE id = :account_id"
        );
        $stmt->execute([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'account_id' => $accountId,
        ]);
    }
    
    /**
     * Get account token data from database
     * 
     * @param int $accountId
     * @return array|null Account data or null if not found
     */
    private function getAccountTokenData(int $accountId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, access_token, refresh_token, expires_at 
             FROM ml_accounts 
             WHERE id = :account_id"
        );
        $stmt->execute(['account_id' => $accountId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }
    
    /**
     * Log token refresh attempt
     * 
     * @param int $accountId
     * @param string $status 'success' or 'failed'
     * @param string|null $errorMessage
     */
    private function logTokenRefresh(int $accountId, string $status, ?string $errorMessage = null): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO sync_logs (account_id, sync_type, status, message, created_at)
                 VALUES (:account_id, 'token_refresh', :status, :message, NOW())"
            );
            $stmt->execute([
                'account_id' => $accountId,
                'status' => $status,
                'message' => $errorMessage ?? "Token refresh {$status}",
            ]);
        } catch (Exception $e) {
            // Don't throw on logging errors
            log_warning('Falha ao registrar log de refresh de token', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Check token health for an account
     * 
     * @param int $accountId
     * @return array Health status
     */
    public function checkTokenHealth(int $accountId): array
    {
        $account = $this->getAccountTokenData($accountId);
        
        if (!$account) {
            return [
                'healthy' => false,
                'status' => 'not_found',
                'message' => 'Account not found',
            ];
        }
        
        if (empty($account['access_token'])) {
            return [
                'healthy' => false,
                'status' => 'no_token',
                'message' => 'No access token found',
            ];
        }
        
        if (empty($account['refresh_token'])) {
            return [
                'healthy' => false,
                'status' => 'no_refresh_token',
                'message' => 'No refresh token found - re-authorization required',
            ];
        }
        
        $expiresAt = strtotime($account['expires_at']);
        $now = time();
        $secondsUntilExpiry = $expiresAt - $now;
        
        if ($secondsUntilExpiry < 0) {
            return [
                'healthy' => false,
                'status' => 'expired',
                'message' => 'Token expired',
                'expired_ago' => abs($secondsUntilExpiry),
            ];
        }
        
        if ($secondsUntilExpiry < self::REFRESH_THRESHOLD_SECONDS) {
            return [
                'healthy' => true,
                'status' => 'needs_refresh',
                'message' => 'Token will be refreshed soon',
                'expires_in' => $secondsUntilExpiry,
            ];
        }
        
        return [
            'healthy' => true,
            'status' => 'valid',
            'message' => 'Token is valid',
            'expires_in' => $secondsUntilExpiry,
        ];
    }
    
    /**
     * Force token refresh for an account
     * Useful for testing or manual intervention
     * 
     * @param int $accountId
     * @return array New token data
     * @throws Exception If refresh fails
     */
    public function forceRefresh(int $accountId): array
    {
        $account = $this->getAccountTokenData($accountId);
        
        if (!$account || empty($account['refresh_token'])) {
            throw new Exception("Cannot refresh: no refresh token available");
        }
        
        return $this->refreshToken($accountId, $account['refresh_token']);
    }
    
    /**
     * Invalidate cached token
     * Forces next request to fetch from database
     * 
     * @param int $accountId
     */
    public function invalidateCache(int $accountId): void
    {
        $this->cache->delete("ml_token_{$accountId}");
    }
    
    /**
     * Get token statistics for monitoring
     * 
     * @return array Statistics
     */
    public function getTokenStatistics(): array
    {
        $stats = $this->db->query(
            "SELECT 
                COUNT(*) as total_accounts,
                SUM(CASE WHEN access_token IS NOT NULL THEN 1 ELSE 0 END) as accounts_with_token,
                SUM(CASE WHEN refresh_token IS NOT NULL THEN 1 ELSE 0 END) as accounts_with_refresh,
                SUM(CASE WHEN expires_at > NOW() THEN 1 ELSE 0 END) as valid_tokens,
                SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_tokens
             FROM ml_accounts"
        );
        
        return $stats[0] ?? [];
    }
}
