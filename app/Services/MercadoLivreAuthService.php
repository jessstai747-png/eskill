<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * Serviço responsável pelo fluxo OAuth do Mercado Livre (autorização, troca de código e refresh)
 */
class MercadoLivreAuthService
{
    private array $config;
    private ?\PDO $db;

    public function __construct(?\PDO $db = null, ?array $config = null)
    {
        $this->config = $config ?? \App\Core\Config::getInstance()->all();
        $this->db = $db;
    }

    private function pdo(): \PDO
    {
        if ($this->db instanceof \PDO) {
            return $this->db;
        }

        try {
            $this->db = Database::getInstance();
            return $this->db;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Database unavailable for MercadoLivreAuthService', 0, $e);
        }
    }
    
    /**
     * Grava evento de auditoria na tabela token_refresh_audit
     */
    private function logAuditEvent(
        int $accountId,
        string $action,
        ?array $details = null,
        ?int $httpCode = null,
        ?string $errorMessage = null,
        ?string $expiresAtBefore = null,
        ?string $expiresAtAfter = null,
        ?int $executionTimeMs = null
    ): void {
        try {
            $stmt = $this->pdo()->prepare("
                INSERT INTO token_refresh_audit (
                    account_id, action, details, http_code, error_message,
                    expires_at_before, expires_at_after, execution_time_ms
                ) VALUES (
                    :account_id, :action, :details, :http_code, :error_message,
                    :expires_at_before, :expires_at_after, :execution_time_ms
                )
            ");
            
            $stmt->execute([
                'account_id' => $accountId,
                'action' => $action,
                'details' => $details ? json_encode($details) : null,
                'http_code' => $httpCode,
                'error_message' => $errorMessage,
                'expires_at_before' => $expiresAtBefore,
                'expires_at_after' => $expiresAtAfter,
                'execution_time_ms' => $executionTimeMs,
            ]);
        } catch (\Throwable $e) {
            // Não falhar por erro de auditoria, apenas logar
            log_warning('Falha ao gravar evento de auditoria de token', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gera a URL de autorização do Mercado Livre e grava um state seguro na sessão
     */
    public function getAuthUrl(int $userId): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ml = $this->config['mercadolivre'] ?? [];
        $clientId = $ml['app_id'] ?? '';
        $redirect = $ml['redirect_uri'] ?? '';
        $authBase = $ml['auth_url'] ?? 'https://auth.mercadolibre.com/authorization';

        $state = $userId . ':' . bin2hex(random_bytes(16));
        $_SESSION['ml_oauth_state'] = $state;

        $codeVerifier = $this->base64UrlEncode(random_bytes(32));
        $codeChallenge = $this->base64UrlEncode(hash('sha256', $codeVerifier, true));
        if (!isset($_SESSION['ml_oauth_pkce']) || !is_array($_SESSION['ml_oauth_pkce'])) {
            $_SESSION['ml_oauth_pkce'] = [];
        }
        $_SESSION['ml_oauth_pkce'][$state] = $codeVerifier;

        $params = [
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirect,
            'state' => $state,
            'scope' => 'read write',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $authBase . '?' . http_build_query($params);
    }

    /**
     * Troca código de autorização por tokens e persiste a conta em `ml_accounts`.
     * Retorna array com 'success' e 'user_info' (dados retornados por /users/me).
     */
    public function exchangeCodeForTokens(string $code, string $state): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $stored = $_SESSION['ml_oauth_state'] ?? null;
        if (!$stored || $stored !== $state) {
            throw new \Exception('Estado OAuth inválido ou expirado');
        }

        $codeVerifier = null;
        if (isset($_SESSION['ml_oauth_pkce']) && is_array($_SESSION['ml_oauth_pkce'])) {
            $codeVerifier = $_SESSION['ml_oauth_pkce'][$state] ?? null;
        }
        if (!is_string($codeVerifier) || $codeVerifier === '') {
            throw new \Exception('code_verifier ausente ou expirado');
        }

        // Extrair userId do state (formato: <userId>:<random>)
        $parts = explode(':', $state, 2);
        $userId = (int)($parts[0] ?? 0);

        $ml = $this->config['mercadolivre'] ?? [];
        $tokenUrl = $ml['token_url'] ?? 'https://api.mercadolibre.com/oauth/token';
        $clientId = $ml['app_id'] ?? '';
        $clientSecret = $ml['client_secret'] ?? '';
        $redirect = $ml['redirect_uri'] ?? '';

        $post = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirect,
            'code_verifier' => $codeVerifier,
        ];

        $ch = curl_init($tokenUrl);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'SEO-Optimizer/1.0',
        ];
        $this->applyCurlProxyOptions($curlOptions);
        curl_setopt_array($ch, $curlOptions);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Falha ao obter tokens do Mercado Livre: HTTP ' . $httpCode . ' - ' . $resp);
        }

        $data = json_decode($resp, true);
        if (!isset($data['access_token'])) {
            throw new \Exception('Resposta inválida do provedor de token: ' . $resp);
        }

        $accessToken = $data['access_token'];
        $refreshToken = $data['refresh_token'] ?? null;
        $expiresIn = (int)($data['expires_in'] ?? 21600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Buscar informações do usuário ML
        $userInfo = $this->fetchUserInfo($accessToken);

        $mlUserId = $userInfo['id'] ?? null;
        $nickname = $userInfo['nickname'] ?? null;
        $email = $userInfo['email'] ?? null;

        if (!$mlUserId) {
            throw new \Exception('Não foi possível obter o id do usuário Mercado Livre');
        }

        // Criptografar tokens - OBRIGATÓRIO em produção
        $storeAccess = $accessToken;
        $storeRefresh = $refreshToken;
        $tokensEncrypted = 0;
        try {
            $enc = new EncryptionService();
            $storeAccess = $enc->encrypt($accessToken);
            $storeRefresh = $enc->encrypt((string)$refreshToken);
            $tokensEncrypted = 1;
        } catch (\Throwable $e) {
            $appEnv = $_ENV['APP_ENV'] ?? 'production';
            if ($appEnv === 'production' || $appEnv === 'staging') {
                throw new \RuntimeException(
                    'Impossível armazenar tokens sem criptografia em produção. Configure APP_KEY no .env (mínimo 32 caracteres). Erro: ' . $e->getMessage()
                );
            }
            $logger = new StructuredLogService();
            $logger->warning('EncryptionService indisponível - tokens armazenados sem criptografia (apenas dev)', [
                'error' => $e->getMessage()
            ]);
        }

        // Inserir ou atualizar ml_accounts
        $stmt = $this->pdo()->prepare('SELECT id FROM ml_accounts WHERE ml_user_id = :ml_user_id LIMIT 1');
        $stmt->execute(['ml_user_id' => $mlUserId]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing) {
            $accountId = (int)$existing['id'];
            $update = $this->pdo()->prepare("UPDATE ml_accounts SET
                user_id = :user_id,
                nickname = :nickname,
                email = :email,
                access_token = :access_token,
                refresh_token = :refresh_token,
                token_expires_at = :expires_at,
                tokens_encrypted = :tokens_encrypted,
                status = 'active',
                updated_at = NOW()
                WHERE id = :id");

            $update->execute([
                'user_id' => $userId,
                'nickname' => $nickname,
                'email' => $email,
                'access_token' => $storeAccess,
                'refresh_token' => $storeRefresh,
                'expires_at' => $expiresAt,
                'tokens_encrypted' => $tokensEncrypted,
                'id' => $accountId
            ]);
        } else {
            $insert = $this->pdo()->prepare("INSERT INTO ml_accounts
                (user_id, ml_user_id, nickname, email, site_id, access_token, refresh_token, token_expires_at, status, tokens_encrypted, created_at, updated_at)
                VALUES
                (:user_id, :ml_user_id, :nickname, :email, :site_id, :access_token, :refresh_token, :expires_at, 'active', :tokens_encrypted, NOW(), NOW())");

            $insert->execute([
                'user_id' => $userId,
                'ml_user_id' => $mlUserId,
                'nickname' => $nickname,
                'email' => $email,
                'site_id' => $ml['site_id'] ?? 'MLB',
                'access_token' => $storeAccess,
                'refresh_token' => $storeRefresh,
                'expires_at' => $expiresAt,
                'tokens_encrypted' => $tokensEncrypted,
            ]);

            $accountId = (int)$this->pdo()->lastInsertId();
        }

        unset($_SESSION['ml_oauth_state']);
        if (isset($_SESSION['ml_oauth_pkce']) && is_array($_SESSION['ml_oauth_pkce'])) {
            unset($_SESSION['ml_oauth_pkce'][$state]);
        }
        
        // Logar autorização OAuth bem-sucedida
        $this->logAuditEvent(
            $accountId,
            'authorization_granted',
            [
                'ml_user_id' => $mlUserId,
                'nickname' => $nickname,
                'is_reconnection' => isset($existing)
            ],
            200,
            null,
            null,
            $expiresAt
        );
        
        // Atualizar last_oauth_connection_at
        $this->pdo()->prepare(
            'UPDATE ml_accounts SET last_oauth_connection_at = NOW() WHERE id = :id'
        )->execute(['id' => $accountId]);

        return ['success' => true, 'account_id' => $accountId, 'user_info' => $userInfo];
    }

    /**
     * Renova tokens usando refresh_token salvo no banco
     * Com suporte a retry e backoff exponencial
     */
    public function refreshToken(int $accountId, int $maxRetries = 3): bool
    {
        $startTime = microtime(true);
        
        // Recuperar refresh token e expirar atual
        $stmt = $this->pdo()->prepare('SELECT refresh_token, tokens_encrypted, token_expires_at FROM ml_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }
        
        $expiresAtBefore = $row['token_expires_at'];
        
        // Logar tentativa de refresh
        $this->logAuditEvent(
            $accountId,
            'refresh_attempt',
            ['max_retries' => $maxRetries],
            null,
            null,
            $expiresAtBefore
        );

        $refreshToken = $row['refresh_token'];
        if ($row['tokens_encrypted']) {
            try {
                $enc = new EncryptionService();
                $refreshToken = $enc->decrypt($refreshToken);
            } catch (\Throwable $e) {
                $logger = new StructuredLogService();
                $logger->error('Failed to decrypt refresh token', ['error' => $e->getMessage()]);
                return false;
            }
        }

        // Bail early: sem refresh token, não adianta tentar a API
        if (empty($refreshToken)) {
            $logger = new StructuredLogService();
            $logger->warning('Refresh token vazio - conta desconectada', [
                'account_id' => $accountId,
            ]);
            
            $this->logAuditEvent(
                $accountId,
                'refresh_failed',
                null,
                null,
                'Refresh token vazio - conta desconectada',
                $expiresAtBefore
            );
            
            return false;
        }

        $ml = $this->config['mercadolivre'] ?? [];
        $tokenUrl = $ml['token_url'] ?? 'https://api.mercadolibre.com/oauth/token';
        $clientId = $ml['app_id'] ?? '';
        $clientSecret = $ml['client_secret'] ?? '';

        $post = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken
        ];

        $attempt = 0;
        $success = false;
        $resp = null;
        $httpCode = 0;

        while ($attempt < $maxRetries && !$success) {
            $attempt++;
            
            $ch = curl_init($tokenUrl);
            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($post),
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'SEO-Optimizer/1.0',
            ];
            $this->applyCurlProxyOptions($curlOptions);
            curl_setopt_array($ch, $curlOptions);

            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Sucesso HTTP 200
            if ($httpCode === 200) {
                $success = true;
                break;
            }
            
            // Falha fatal (invalid_grant), não adianta tentar de novo
            if ($httpCode === 400 || $httpCode === 401) {
                $data = json_decode($resp, true);
                if (isset($data['error']) && $data['error'] === 'invalid_grant') {
                    break;
                }
            }

            // Se falhou por rede ou erro 5xx, espera antes de tentar de novo (backoff)
            if ($attempt < $maxRetries) {
                $sleepSeconds = pow(2, $attempt); // 2s, 4s, 8s...
                sleep($sleepSeconds);
            }
        }

        if (!$success) {
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            $logger = new StructuredLogService();
            $logger->error('Falha refresh token ML após tentativas', [
                'attempts' => $attempt,
                'status' => $httpCode, 
                'response' => $resp,
                'account_id' => $accountId
            ]);
            
            $this->logAuditEvent(
                $accountId,
                'refresh_failed',
                ['attempts' => $attempt, 'response_preview' => substr($resp ?? '', 0, 200)],
                $httpCode,
                "Falha após {$attempt} tentativas",
                $expiresAtBefore,
                null,
                $executionTime
            );
            
            // Atualizar contador de falhas e último erro
            $this->pdo()->prepare(
                'UPDATE ml_accounts 
                SET refresh_failure_count = refresh_failure_count + 1, 
                    last_refresh_error = :error,
                    updated_at = NOW()
                WHERE id = :id'
            )->execute([
                'error' => "HTTP {$httpCode}: " . substr($resp ?? '', 0, 500),
                'id' => $accountId
            ]);
            
            return false;
        }

        $data = json_decode($resp, true);
        if (!isset($data['access_token'])) {
            $logger = new StructuredLogService();
            $logger->error('Resposta inválida ao renovar token ML', ['response' => $resp]);
            return false;
        }

        $accessToken = $data['access_token'];
        $newRefresh = $data['refresh_token'] ?? $refreshToken;
        $expiresIn = (int)($data['expires_in'] ?? 21600);
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);

        // Criptografar tokens - OBRIGATÓRIO em produção
        $storeAccess = $accessToken;
        $storeRefresh = $newRefresh;
        $tokensEncrypted = $row['tokens_encrypted'] ? 1 : 0;
        try {
            $enc = new EncryptionService();
            $storeAccess = $enc->encrypt($accessToken);
            $storeRefresh = $enc->encrypt($newRefresh);
            $tokensEncrypted = 1;
        } catch (\Throwable $e) {
            $appEnv = $_ENV['APP_ENV'] ?? 'production';
            if ($appEnv === 'production' || $appEnv === 'staging') {
                $logger = new StructuredLogService();
                $logger->critical('Falha ao criptografar tokens no refresh', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage()
                ]);
                throw new \RuntimeException('Impossível renovar tokens sem criptografia em produção. Configure APP_KEY.');
            }
        }

        $upd = $this->pdo()->prepare('UPDATE ml_accounts SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :expires_at, tokens_encrypted = :tokens_encrypted, status = :status, last_refresh_at = NOW(), refresh_failure_count = 0, last_refresh_error = NULL, updated_at = NOW() WHERE id = :id');
        $upd->execute([
            'access_token' => $storeAccess,
            'refresh_token' => $storeRefresh,
            'expires_at' => $expiresAt,
            'tokens_encrypted' => $tokensEncrypted,
            'status' => 'active',
            'id' => $accountId
        ]);
        
        $executionTime = (int)((microtime(true) - $startTime) * 1000);

        $logger = new StructuredLogService();
        $logger->info('Token ML renovado com sucesso', [
            'account_id' => $accountId,
            'expires_at' => $expiresAt,
            'attempts' => $attempt
        ]);
        
        // Logar sucesso de refresh com tempos de expiração
        $this->logAuditEvent(
            $accountId,
            'refresh_success',
            ['attempts' => $attempt],
            200,
            null,
            $expiresAtBefore,
            $expiresAt,
            $executionTime
        );

        return (bool)$upd->rowCount();
    }

    /**
     * Garante token válido para uma conta. Renova se estiver perto de expirar.
     */
    public function ensureValidToken(int $accountId, int $bufferMinutes = 60): bool
    {
        $stmt = $this->pdo()->prepare('SELECT token_expires_at, status FROM ml_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $expiresAt = $row['token_expires_at'] ?? null;
        $status = $row['status'] ?? 'inactive';

        // Se não há expiração registrada, tenta seguir mesmo assim
        if (!$expiresAt) {
            return true;
        }

        $secondsLeft = strtotime($expiresAt) - time();
        if ($secondsLeft > ($bufferMinutes * 60) && $status === 'active') {
            return true;
        }

        $refreshed = $this->refreshToken($accountId);
        if ($refreshed) {
            try {
                $upd = $this->pdo()->prepare("UPDATE ml_accounts SET status = 'active', updated_at = NOW() WHERE id = :id");
                $upd->execute(['id' => $accountId]);
            } catch (\Throwable $e) {
                // não bloquear se não conseguir atualizar status
                try {
                    (new StructuredLogService())->warning('Falha ao atualizar status ml_accounts para active', [
                        'account_id' => $accountId,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {
                    // último recurso: não bloquear fluxo
                }
            }
            return true;
        }

        try {
            $upd = $this->pdo()->prepare("UPDATE ml_accounts SET status = 'expired', updated_at = NOW() WHERE id = :id");
            $upd->execute(['id' => $accountId]);
        } catch (\Throwable $e) {
            try {
                (new StructuredLogService())->warning('Falha ao atualizar status ml_accounts para expired', [
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $ignored) {
                // não bloquear fluxo
            }
        }

        return false;
    }

    /**
     * Busca informações do usuário usando o access_token
     */
    private function fetchUserInfo(string $accessToken): array
    {
        $url = ($this->config['mercadolivre']['api_url'] ?? 'https://api.mercadolibre.com') . '/users/me';

        $ch = curl_init($url);
        $curlOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'SEO-Optimizer/1.0',
        ];
        $this->applyCurlProxyOptions($curlOptions);
        curl_setopt_array($ch, $curlOptions);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception('Falha ao obter informações do usuário ML: HTTP ' . $httpCode . ' - ' . $resp);
        }

        $data = json_decode($resp, true);
        return is_array($data) ? $data : [];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Verifica se o token de uma conta precisa ser renovado
     * Retorna true se estiver expirando nos próximos 30 minutos
     */
    public function tokenNeedsRefresh(int $accountId, int $bufferMinutes = 30): bool
    {
        $stmt = $this->pdo()->prepare('SELECT token_expires_at FROM ml_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['token_expires_at'])) {
            return true; // Sem data de expiração, precisa refresh
        }

        $expiresAt = strtotime($row['token_expires_at']);
        $bufferSeconds = $bufferMinutes * 60;
        $threshold = time() + $bufferSeconds;

        return $expiresAt <= $threshold;
    }

    /**
     * Retorna status detalhado do token de uma conta
     */
    public function getTokenStatus(int $accountId): array
    {
        $stmt = $this->pdo()->prepare('
            SELECT id, ml_user_id, nickname, token_expires_at, status, last_token_refresh
            FROM ml_accounts WHERE id = :id LIMIT 1
        ');
        $stmt->execute(['id' => $accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return [
                'exists' => false,
                'status' => 'not_found',
                'expires_at' => null,
                'seconds_remaining' => 0,
            ];
        }

        $expiresAt = $row['token_expires_at'] ? strtotime($row['token_expires_at']) : null;
        $secondsRemaining = $expiresAt ? max(0, $expiresAt - time()) : 0;

        $tokenStatus = 'unknown';
        if ($secondsRemaining <= 0) {
            $tokenStatus = 'expired';
        } elseif ($secondsRemaining <= 1800) { // 30 min
            $tokenStatus = 'expiring_soon';
        } elseif ($secondsRemaining <= 3600) { // 1 hour
            $tokenStatus = 'expiring';
        } else {
            $tokenStatus = 'valid';
        }

        return [
            'exists' => true,
            'account_id' => (int)$row['id'],
            'ml_user_id' => $row['ml_user_id'],
            'nickname' => $row['nickname'],
            'account_status' => $row['status'],
            'expires_at' => $row['token_expires_at'],
            'seconds_remaining' => $secondsRemaining,
            'status' => $tokenStatus,
            'last_refresh' => $row['last_token_refresh'] ?? null,
        ];
    }

    private function applyCurlProxyOptions(array &$curlOptions): void
    {
        $enabledRaw = $_ENV['ML_PROXY_ENABLED'] ?? getenv('ML_PROXY_ENABLED') ?? null;
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }

        $type = (string)($_ENV['ML_PROXY_TYPE'] ?? getenv('ML_PROXY_TYPE') ?? 'http');
        $host = (string)($_ENV['ML_PROXY_HOST'] ?? getenv('ML_PROXY_HOST') ?? '');
        $port = (string)($_ENV['ML_PROXY_PORT'] ?? getenv('ML_PROXY_PORT') ?? '');
        $user = (string)($_ENV['ML_PROXY_USER'] ?? getenv('ML_PROXY_USER') ?? '');
        $pass = (string)($_ENV['ML_PROXY_PASS'] ?? getenv('ML_PROXY_PASS') ?? '');

        if ($host === '' || $port === '') {
            return;
        }

        $curlOptions[CURLOPT_PROXY] = $host . ':' . $port;

        $scheme = strtolower(trim($type));
        if ($scheme === 'socks5' || $scheme === 'socks5h') {
            $curlOptions[CURLOPT_PROXYTYPE] = $scheme === 'socks5h' ? CURLPROXY_SOCKS5_HOSTNAME : CURLPROXY_SOCKS5;
        } else {
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
        }

        if ($user !== '') {
            $curlOptions[CURLOPT_PROXYUSERPWD] = $user . ':' . $pass;
        }
    }
}
