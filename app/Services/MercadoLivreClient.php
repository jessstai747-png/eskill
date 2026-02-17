<?php

namespace App\Services;

use App\Database;

/**
 * Client for Mercado Livre API integration
 * This service handles communication with Mercado Livre's API as mentioned in the architecture
 */
class MercadoLivreClient
{
    protected string $accessToken = '';
    protected string $refreshToken = '';
    protected ?string $tokenExpiresAt = null;
    protected string $baseUrl = 'https://api.mercadolibre.com';
    protected \GuzzleHttp\Client $httpClient;
    protected ?int $accountId = null;
    protected ?string $sellerId = null;
    protected bool $hasAccessToken = false;
    protected ?\GuzzleHttp\Client $publicHttpClient = null;
    protected static bool $missingTokenLogged = false;
    protected ?MercadoLivreAuthService $authService = null;
    private ?CacheService $cacheService = null;
    private ?CircuitBreakerService $circuitBreaker = null;
    private static bool $circuitBreakerEnabled = true;

    public function __construct(?int $accountId = null, ?MercadoLivreAuthService $authService = null)
    {
        $this->accountId = $accountId;
        $this->authService = $authService;

        // Se não foi informado accountId, tentar inferir a conta ativa da sessão (fluxo web).
        // Não inicia sessão automaticamente (para manter compatibilidade com CLI/tests).
        if ($this->accountId === null) {
            try {
                if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['active_ml_account_id'])) {
                    $candidate = (int)$_SESSION['active_ml_account_id'];
                    if ($candidate > 0) {
                        $this->accountId = $candidate;
                    }
                }
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        // Tokens
        // - Quando accountId está definido (multi-account), preferimos sempre os tokens vinculados no banco.
        // - ML_ACCESS_TOKEN (ambiente) fica como fallback para modo simples/single-account ou quando a conta não existe.
        $envAccessToken = (string)($_ENV['ML_ACCESS_TOKEN'] ?? getenv('ML_ACCESS_TOKEN') ?? '');

        $accountLoaded = false;
        if ($this->accountId !== null) {
            try {
                $accountLoaded = $this->loadAccount();
            } catch (\Throwable $e) {
                // best-effort; se falhar, tenta token do ambiente abaixo
            }
        }

        // Fallback: token do ambiente (somente quando NÃO há accountId explícito)
        // Se accountId foi informado mas loadAccount() falhou (tokens vazios/expirados),
        // NÃO devemos usar o token do ambiente — isso mascararia a conta desconectada.
        if (!$accountLoaded && $this->accountId === null) {
            $this->accessToken = $envAccessToken;
            $this->hasAccessToken = $this->accessToken !== '';
        }

        // 3) Aviso único quando não há token disponível de nenhuma fonte
        if (!$this->hasAccessToken && !self::$missingTokenLogged) {
            log_warning('Sem token configurado para ML API', [
                'account_id' => $this->accountId,
                'hint' => 'ML_ACCESS_TOKEN no ambiente ou conta vinculada em ml_accounts',
            ]);
            self::$missingTokenLogged = true;
        }

        $this->httpClient = $this->createHttpClient();
    }

    private function createHttpClient(bool $includeAuth = true): \GuzzleHttp\Client
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => 'SEO-Optimizer/1.0'
        ];

        if ($includeAuth && $this->hasAccessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        $options = [
            'timeout' => 30,
            'headers' => $headers,
        ];

        $proxy = $this->buildProxyOption();
        if ($proxy !== null) {
            $options['proxy'] = $proxy;
        }

        return new \GuzzleHttp\Client($options);
    }

    private function getPublicHttpClient(): \GuzzleHttp\Client
    {
        if ($this->publicHttpClient) {
            return $this->publicHttpClient;
        }

        $this->publicHttpClient = $this->createHttpClient(false);
        return $this->publicHttpClient;
    }

    /**
     * Monta a opção de proxy do Guzzle a partir de variáveis de ambiente.
     *
     * Suporta:
     * - ML_PROXY_ENABLED=true
     * - ML_PROXY_TYPE=http|socks5
     * - ML_PROXY_HOST, ML_PROXY_PORT
     * - ML_PROXY_USER, ML_PROXY_PASS (opcionais)
     */
    private function buildProxyOption(): ?string
    {
        $enabledRaw = $_ENV['ML_PROXY_ENABLED'] ?? getenv('ML_PROXY_ENABLED') ?? null;
        $enabled = filter_var($enabledRaw, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return null;
        }

        $type = (string)($_ENV['ML_PROXY_TYPE'] ?? getenv('ML_PROXY_TYPE') ?? 'http');
        $host = (string)($_ENV['ML_PROXY_HOST'] ?? getenv('ML_PROXY_HOST') ?? '');
        $port = (string)($_ENV['ML_PROXY_PORT'] ?? getenv('ML_PROXY_PORT') ?? '');
        $user = (string)($_ENV['ML_PROXY_USER'] ?? getenv('ML_PROXY_USER') ?? '');
        $pass = (string)($_ENV['ML_PROXY_PASS'] ?? getenv('ML_PROXY_PASS') ?? '');

        if ($host === '' || $port === '') {
            return null;
        }

        $scheme = strtolower(trim($type));
        if (!in_array($scheme, ['http', 'https', 'socks5', 'socks5h'], true)) {
            $scheme = 'http';
        }

        $auth = '';
        if ($user !== '') {
            $auth = rawurlencode($user);
            if ($pass !== '') {
                $auth .= ':' . rawurlencode($pass);
            }
            $auth .= '@';
        }

        return $scheme . '://' . $auth . $host . ':' . $port;
    }

    private function refreshHttpClient(): void
    {
        $this->httpClient = $this->createHttpClient(true);
        $this->publicHttpClient = null;
    }

    /**
     * Carrega dados da conta e descriptografa tokens quando necessário
     */
    public function loadAccount(): bool
    {
        if ($this->accountId === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT access_token, refresh_token, token_expires_at, tokens_encrypted FROM ml_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $this->accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $accessToken = $row['access_token'] ?? '';
        $refreshToken = $row['refresh_token'] ?? '';

        if (!empty($row['tokens_encrypted'])) {
            try {
                $enc = new EncryptionService();
                $accessToken = $accessToken !== '' ? $enc->decrypt($accessToken) : '';
                $refreshToken = $refreshToken !== '' ? $enc->decrypt($refreshToken) : '';
            } catch (\Throwable $e) {
                log_error('Falha ao descriptografar tokens ML', [
                    'account_id' => $this->accountId,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }
        }

        $this->accessToken = (string) $accessToken;
        $this->refreshToken = (string) $refreshToken;
        $this->tokenExpiresAt = $row['token_expires_at'] ?? null;
        $this->hasAccessToken = $this->accessToken !== '';
        $this->refreshHttpClient();

        return $this->hasAccessToken;
    }

    /**
     * Garante que o access token está válido (renova se necessário)
     */
    public function ensureValidAccessToken(int $bufferMinutes = 60): bool
    {
        if ($this->accountId === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT token_expires_at FROM ml_accounts WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $this->accountId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return false;
        }

        $expiresAt = $row['token_expires_at'] ?? null;
        if (!$expiresAt) {
            return $this->loadAccount();
        }

        $secondsLeft = strtotime($expiresAt) - time();
        if ($secondsLeft > ($bufferMinutes * 60)) {
            return $this->loadAccount();
        }

        $authService = $this->authService ?? new MercadoLivreAuthService();
        $refreshed = $authService->refreshToken($this->accountId);
        if (!$refreshed) {
            return false;
        }

        return $this->loadAccount();
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    protected function isConfigured(): bool
    {
        return $this->hasAccessToken;
    }

    protected function missingTokenError(string $endpoint): array
    {
        return [
            'error' => 'missing_access_token',
            'message' => 'Mercado Livre access token not configured.',
            'endpoint' => $endpoint,
            'status' => 401
        ];
    }

    private function getCacheService(): CacheService
    {
        if ($this->cacheService === null) {
            $this->cacheService = new CacheService();
        }

        return $this->cacheService;
    }

    /**
     * Obtém instância do Circuit Breaker (lazy load)
     * Protege o sistema quando a API do ML está instável
     */
    private function getCircuitBreaker(): ?CircuitBreakerService
    {
        if (!self::$circuitBreakerEnabled) {
            return null;
        }

        if ($this->circuitBreaker === null) {
            try {
                $this->circuitBreaker = new CircuitBreakerService('mercadolivre_api');
                $this->circuitBreaker->configure([
                    'failure_threshold' => 5,    // 5 falhas para abrir
                    'success_threshold' => 3,    // 3 sucessos para fechar
                    'open_timeout' => 60,        // 60s antes de testar novamente
                    'half_open_max_requests' => 3,
                ]);
            } catch (\Exception $e) {
                // Se falhar ao criar circuit breaker, continua sem ele
                log_warning('Falha ao inicializar CircuitBreaker', [
                    'error' => $e->getMessage(),
                ]);
                self::$circuitBreakerEnabled = false;
                return null;
            }
        }

        return $this->circuitBreaker;
    }

    /**
     * Desabilita circuit breaker (útil para testes)
     */
    public static function disableCircuitBreaker(): void
    {
        self::$circuitBreakerEnabled = false;
    }

    /**
     * Habilita circuit breaker
     */
    public static function enableCircuitBreaker(): void
    {
        self::$circuitBreakerEnabled = true;
    }

    /**
     * Obtém estatísticas do Circuit Breaker
     */
    public function getCircuitBreakerStats(): ?array
    {
        $cb = $this->getCircuitBreaker();
        return $cb ? $cb->getStats() : null;
    }

    /**
     * Em ambientes de teste, bloqueia rede por padrão para manter testes determinísticos.
     * Habilite explicitamente com ML_ALLOW_NETWORK=true.
     */
    private function isNetworkAllowed(): bool
    {
        $env = (string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'production');
        if (strtolower($env) !== 'testing') {
            return true;
        }

        $allow = $_ENV['ML_ALLOW_NETWORK'] ?? getenv('ML_ALLOW_NETWORK') ?? null;
        return filter_var($allow, FILTER_VALIDATE_BOOLEAN);
    }

    private function networkDisabledError(string $method, string $endpoint): array
    {
        return [
            'error' => 'network_disabled',
            'message' => 'External network calls are disabled in testing by default. Set ML_ALLOW_NETWORK=true to enable.',
            'method' => $method,
            'endpoint' => $endpoint,
            'status' => 503,
        ];
    }

    private function normalizeHttpError(string $method, string $endpoint, ?int $status, ?string $body, string $fallbackMessage): array
    {
        $payload = [];

        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (!isset($payload['error'])) {
            $payload['error'] = 'http_error';
        }

        if (!isset($payload['message'])) {
            $payload['message'] = $fallbackMessage;
        }

        $payload['status'] = $status;
        $payload['method'] = $method;
        $payload['endpoint'] = $endpoint;

        return $payload;
    }

    private function shouldCacheResponse(array $response): bool
    {
        if (isset($response['error'])) {
            return false;
        }

        return true;
    }

    private function buildCacheKey(string $method, string $endpoint, array $params, bool $public): string
    {
        $identity = [
            'method' => $method,
            'endpoint' => $endpoint,
            'params' => $params,
            'public' => $public,
            'account_id' => $this->accountId,
        ];

        if ($this->hasAccessToken) {
            $identity['token_hash'] = substr(hash('sha256', $this->accessToken), 0, 16);
        }

        return 'ml_http:' . md5(json_encode($identity));
    }

    private function requiresAuthForEndpoint(string $method, string $endpoint, ?bool $explicitPublic): bool
    {
        if ($explicitPublic !== null) {
            return !$explicitPublic;
        }

        if ($method !== 'GET') {
            return true;
        }

        $patterns = [
            '#^/users/me#',
            '#^/users/[^/]+/items/search#',
            '#^/users/[^/]+/products#',
            '#^/users/[^/]+/listings_quality#',
            '#^/users/[^/]+/shipping_preferences#',
            '#^/users/[^/]+/fulfillment#',
            '#^/users/[^/]+/brands_official_store#',
            '#^/orders#',
            '#^/questions#',
            '#^/shipments#',
            '#^/payments#',
            '#^/billing#',
            '#^/messages#',
            '#^/items/[^/]+/visits#',
            '#^/items/[^/]+/health#',
            '#^/items/[^/]+/shipping#',
            '#^/items/[^/]+/fulfillment#',
            '#^/answers#',
            '#^/my/#',
            '#^/v1/claims#',
            // Search é endpoint público - usar searchItems() para forçar public
            // '#^/sites/[^/]+/search#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Executa requisição com retry opcional (ex.: token expirado).
     * Retorna sempre array decodificado.
     * Inclui proteção via Circuit Breaker para resiliência.
     */
    private function requestWithRetry(string $method, string $endpoint, array $options = [], bool $allowRetry = true, bool $requiresAuth = true): array
    {
        if (!$this->isNetworkAllowed()) {
            return $this->networkDisabledError($method, $endpoint);
        }

        if ($requiresAuth && !$this->isConfigured()) {
            return $this->missingTokenError($endpoint);
        }

        // CIRCUIT BREAKER: Verifica se API está em estado de falha
        $circuitBreaker = $this->getCircuitBreaker();
        if ($circuitBreaker && !$circuitBreaker->canRequest()) {
            return [
                'error' => 'circuit_breaker_open',
                'message' => 'API do Mercado Livre temporariamente indisponível. Tente novamente em alguns minutos.',
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => 503,
            ];
        }

        // PROACTIVE TOKEN REFRESH: Verifica e renova token ANTES de fazer a requisição
        // Isso evita erros 401 e garante fluxo sem interrupções
        if ($requiresAuth && $this->accountId !== null) {
            $this->ensureValidAccessToken(120); // 2 horas de margem
        }

        // Se for requisição pública, adicionar client_id para evitar rate limits agressivos
        $clientId = (string)($_ENV['ML_APP_ID'] ?? getenv('ML_APP_ID') ?? '');
        if (!$requiresAuth && $clientId !== '') {
            if (!isset($options['query'])) {
                $options['query'] = [];
            }
            if (is_array($options['query']) && !isset($options['query']['client_id'])) {
                $options['query']['client_id'] = $clientId;
            }
        }

        $url = $this->baseUrl . $endpoint;

        try {
            // Para endpoints públicos, algumas políticas podem bloquear respostas sem Authorization.
            // Então usamos o client autenticado quando houver token; e só usamos client público quando não há token.
            $client = ($requiresAuth || $this->hasAccessToken) ? $this->httpClient : $this->getPublicHttpClient();
            $response = $client->request($method, $url, $options);
            $result = json_decode($response->getBody(), true) ?: [];

            // CIRCUIT BREAKER: Registra sucesso
            if ($circuitBreaker) {
                $circuitBreaker->recordSuccess();
            }

            return $result;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $status = $e->getResponse()?->getStatusCode();

            // Se o endpoint exige auth e não há token, padroniza erro.
            if ($status === 401 && $requiresAuth && !$this->hasAccessToken) {
                return $this->missingTokenError($endpoint);
            }

            // Token pode ter expirado / sido revogado; tenta refresh e reenvia 1x.
            if ($allowRetry && $status === 401 && $this->accountId !== null) {
                try {
                    $ok = $this->ensureValidAccessToken();
                    if ($ok) {
                        // ensureValidAccessToken() recarrega e atualiza headers
                        return $this->requestWithRetry($method, $endpoint, $options, false, $requiresAuth);
                    }
                } catch (\Throwable $t) {
                    // segue para log abaixo
                }
            }

            // RATE LIMIT (429) - Respeitar Retry-After
            if ($allowRetry && $status === 429) {
                $retryAfter = (int)($e->getResponse()?->getHeaderLine('Retry-After') ?? 0);
                // Se Retry-After for muito longo (> 60s), melhor falhar do que segurar o processo
                if ($retryAfter > 0 && $retryAfter <= 60) {
                    log_warning('ML API Rate Limit atingido', [
                        'endpoint' => $endpoint,
                        'retry_after_seconds' => $retryAfter,
                    ]);
                    sleep($retryAfter + 1); // +1s de margem
                    return $this->requestWithRetry($method, $endpoint, $options, false, $requiresAuth);
                }
            }

            // CIRCUIT BREAKER: Registra falha em erros de servidor (5xx)
            if ($circuitBreaker && $status >= 500) {
                $circuitBreaker->recordFailure("HTTP {$status} on {$method} {$endpoint}");
            }

            log_error('ML API HTTP Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            $body = null;
            try {
                $body = (string) $e->getResponse()?->getBody();
            } catch (\Throwable $t) {
                $body = null;
            }

            return $this->normalizeHttpError($method, $endpoint, $status, $body, $e->getMessage());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            // Erros 5xx - sempre contam para circuit breaker
            $status = $e->getResponse()?->getStatusCode() ?? 500;

            if ($circuitBreaker) {
                $circuitBreaker->recordFailure("HTTP {$status} on {$method} {$endpoint}");
            }

            log_error('ML API Server Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
            $body = null;
            try {
                $body = (string) $e->getResponse()?->getBody();
            } catch (\Throwable $t) {
                $body = null;
            }

            return $this->normalizeHttpError($method, $endpoint, $status, $body, $e->getMessage());
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            // Erros de conexão (timeout, DNS, etc) - contam para circuit breaker
            if ($circuitBreaker) {
                $circuitBreaker->recordFailure("Connection error on {$method} {$endpoint}: " . $e->getMessage());
            }

            log_error('ML API Connection Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return [
                'error' => 'connection_error',
                'message' => 'Não foi possível conectar à API do Mercado Livre. Verifique sua conexão.',
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => 0,
            ];
        } catch (\Exception $e) {
            // Outros erros de rede
            if ($circuitBreaker) {
                $circuitBreaker->recordFailure("Network error on {$method} {$endpoint}: " . $e->getMessage());
            }

            log_error('ML API Network Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            return [
                'error' => 'network_error',
                'message' => $e->getMessage(),
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => 0,
            ];
        }
    }

    /**
     * Generic GET request
     */
    public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
    {
        $cacheTtl = null;
        $explicitPublic = null;

        if (is_int($cacheTtlOrPublic)) {
            $cacheTtl = $cacheTtlOrPublic;
            if (is_bool($public)) {
                $explicitPublic = $public;
            }
        } elseif (is_bool($cacheTtlOrPublic)) {
            $explicitPublic = $cacheTtlOrPublic;
        } elseif (is_bool($public)) {
            $explicitPublic = $public;
        }

        $requiresAuth = $this->requiresAuthForEndpoint('GET', $endpoint, $explicitPublic);
        $isPublic = $explicitPublic ?? !$requiresAuth;

        $query = [];
        if (!empty($params)) {
            $query['query'] = $params;
        }

        if ($cacheTtl !== null && $cacheTtl > 0) {
            $cache = $this->getCacheService();
            $cacheKey = $this->buildCacheKey('GET', $endpoint, $params, $isPublic);

            if ($cache->has($cacheKey)) {
                $cached = $cache->get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $fresh = $this->requestWithRetry('GET', $endpoint, $query, true, $requiresAuth);
            if ($this->shouldCacheResponse($fresh)) {
                $cache->set($cacheKey, $fresh, $cacheTtl);
            }
            return $fresh;
        }

        return $this->requestWithRetry('GET', $endpoint, $query, true, $requiresAuth);
    }

    /**
     * Generic POST request
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->requestWithRetry('POST', $endpoint, ['json' => $data], true, true);
    }

    /**
     * Generic PUT request
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->requestWithRetry('PUT', $endpoint, ['json' => $data], true, true);
    }

    /**
     * Generic DELETE request
     */
    public function delete(string $endpoint): array
    {
        return $this->requestWithRetry('DELETE', $endpoint, [], true, true);
    }

    /**
     * Busca itens usando o endpoint público /sites/{siteId}/search.
     * Retorna a resposta bruta da API (ex.: results, paging, available_filters).
     */
    public function searchItems(array $params = [], int $cacheTtl = 300): array
    {
        $siteId = (string)($params['site_id'] ?? $params['siteId'] ?? ($_ENV['ML_SITE_ID'] ?? (getenv('ML_SITE_ID') ?: null) ?? 'MLB'));
        unset($params['site_id'], $params['siteId']);

        return $this->get("/sites/{$siteId}/search", $params, $cacheTtl, true);
    }

    /**
     * Get seller ID
     */
    public function getSellerId(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        if ($this->sellerId) {
            return $this->sellerId;
        }

        try {
            $user = $this->get('/users/me');
            $this->sellerId = $user['id'] ?? null;
            return $this->sellerId;
        } catch (\Exception $e) {
            log_error('Erro ao obter seller ID da ML API', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get account ID
     */
    public function getAccountId(): ?int
    {
        return $this->accountId;
    }

    /**
     * Get my items (seller's items)
     * 
     * Usa o endpoint correto /users/{user_id}/items/search
     * O endpoint /users/me/items/search NÃO existe na API do ML
     * 
     * @param array $params Filtros: status, limit, offset, search_type, etc
     * @return array Lista de itens ou erro
     */
    public function getMyItems(array $params = []): array
    {
        $sellerId = $this->getSellerId();

        if (!$sellerId) {
            return [
                'error' => 'seller_not_found',
                'message' => 'Não foi possível obter o ID do vendedor. Verifique a autenticação.',
                'results' => [],
                'paging' => ['total' => 0, 'offset' => 0, 'limit' => 50]
            ];
        }

        // Parâmetros padrão
        $defaults = [
            'status' => 'active',
            'limit' => 50,
            'offset' => 0
        ];

        $params = array_merge($defaults, $params);

        return $this->get("/users/{$sellerId}/items/search", $params);
    }

    /**
     * Get trends for a category
     */
    public function getTrends(string $categoryId): array
    {
        try {
            $siteId = $_ENV['ML_SITE_ID'] ?? (getenv('ML_SITE_ID') ?: null) ?? 'MLB';
            $data = $this->get("/trends/{$siteId}/{$categoryId}", [], 600, true);

            if (isset($data['error'])) {
                return [];
            }

            $keywords = $data['keywords'] ?? [];
            if (!is_array($keywords)) {
                return [];
            }

            $terms = [];
            foreach ($keywords as $kw) {
                if (is_string($kw) && $kw !== '') {
                    $terms[] = $kw;
                    continue;
                }
                if (is_array($kw)) {
                    $term = (string)($kw['keyword'] ?? $kw['term'] ?? $kw['q'] ?? '');
                    if ($term !== '') {
                        $terms[] = $term;
                    }
                }
            }

            return array_values(array_unique($terms));
        } catch (\Exception $e) {
            log_warning('Erro ao obter tendências da ML API', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get autocomplete suggestions
     */
    public function getAutocompleteSuggestions(string $keyword, ?string $categoryId = null): array
    {
        try {
            $siteId = $_ENV['ML_SITE_ID'] ?? (getenv('ML_SITE_ID') ?: null) ?? 'MLB';
            $params = [
                'q' => $keyword,
                'limit' => 20,
            ];

            // Alguns consumidores passam categoria; se a API ignorar, ok.
            if ($categoryId !== null && $categoryId !== '') {
                $params['category'] = $categoryId;
            }

            $data = $this->get("/sites/{$siteId}/autosuggest", $params, 600, true);

            if (isset($data['error'])) {
                return [];
            }

            $terms = [];

            // Formato comum: suggested_queries => [{q: "..."}, ...]
            foreach (($data['suggested_queries'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $q = (string)($row['q'] ?? '');
                if ($q !== '') {
                    $terms[] = $q;
                }
            }

            // Formato alternativo: suggestions => [{term: "..."}, ...]
            foreach (($data['suggestions'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $term = (string)($row['term'] ?? '');
                if ($term !== '') {
                    $terms[] = $term;
                }
            }

            return array_values(array_unique($terms));
        } catch (\Exception $e) {
            log_warning('Erro ao obter autocomplete da ML API', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get category information
     */
    public function getCategory(string $categoryId): array
    {
        try {
            $data = $this->get("/categories/{$categoryId}", [], 86400, true);
            if (isset($data['error']) || !is_array($data)) {
                return [];
            }
            return $data;
        } catch (\Exception $e) {
            log_warning('Erro ao obter categoria da ML API', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get category attributes
     */
    public function getCategoryAttributes(string $categoryId): array
    {
        try {
            $data = $this->get("/categories/{$categoryId}/attributes", [], 43200, true);
            if (isset($data['error']) || !is_array($data)) {
                return [];
            }

            // A API retorna uma lista de atributos (arrays). Mantemos estrutura completa.
            return array_values(array_filter($data, fn($row) => is_array($row) && isset($row['id'])));
        } catch (\Exception $e) {
            log_warning('Erro ao obter atributos da ML API', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get item details
     *
     * Returns the item with description as both:
     *  - 'description' (string): plain text for most consumers
     *  - 'description_data' (array): full API response for services needing HTML/metadata
     */
    public function getItemDetails(string $itemId): array
    {
        try {
            $item = $this->get("/items/{$itemId}", [], 300, true);
            if (isset($item['error']) || !is_array($item) || empty($item)) {
                return [];
            }

            $desc = $this->get("/items/{$itemId}/description", [], 300, true);
            if (!isset($desc['error']) && is_array($desc) && !empty($desc)) {
                // Store plain text for most consumers (title audit, AI prompts, etc.)
                $item['description'] = $desc['plain_text'] ?? $desc['text'] ?? '';
                // Preserve full response for services needing HTML or metadata
                $item['description_data'] = $desc;
            }

            return $item;
        } catch (\Exception $e) {
            log_error('Erro ao obter detalhes do item na ML API', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Update item — usa requestWithRetry para circuit breaker, retry em 401/429, e proteção de rede
     */
    public function updateItem(string $itemId, array $updates): array
    {
        $result = $this->requestWithRetry('PUT', "/items/{$itemId}", [
            'json' => $updates,
        ]);

        $hasError = isset($result['error']);

        return [
            'success' => !$hasError,
            'item_id' => $itemId,
            'updated_fields' => array_keys($updates),
            'response' => $result,
            'message' => $hasError
                ? ($result['message'] ?? 'Erro ao atualizar item')
                : 'Item atualizado com sucesso',
        ];
    }

    /**
     * Get search results for a keyword in a category
     */
    public function searchByKeyword(string $keyword, string $categoryId, int $limit = 20): array
    {
        try {
            $siteId = (string)($_ENV['ML_SITE_ID'] ?? (getenv('ML_SITE_ID') ?: null) ?? 'MLB');
            $data = $this->get("/sites/{$siteId}/search", [
                'q' => $keyword,
                'category' => $categoryId,
                'limit' => $limit,
            ], 300, true);

            if (isset($data['error'])) {
                return [];
            }

            $results = [];
            if (isset($data['results'])) {
                foreach ($data['results'] as $item) {
                    $results[] = [
                        'id' => $item['id'] ?? '',
                        'title' => $item['title'] ?? '',
                        'price' => $item['price'] ?? 0,
                        'sold_quantity' => $item['sold_quantity'] ?? 0,
                        'permalink' => $item['permalink'] ?? ''
                    ];
                }
            }

            return $results;
        } catch (\Exception $e) {
            log_error('Erro ao buscar por keyword na ML API', [
                'keyword' => $keyword,
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get competitor analysis
     */
    public function getCompetitorAnalysis(string $keyword, string $categoryId): array
    {
        try {
            // Get search results to analyze competitors
            $results = $this->searchByKeyword($keyword, $categoryId, 50);

            // Analyze pricing and performance
            $prices = array_column($results, 'price');
            $avgPrice = count($prices) > 0 ? array_sum($prices) / count($prices) : 0;
            $minPrice = count($prices) > 0 ? min($prices) : 0;
            $maxPrice = count($prices) > 0 ? max($prices) : 0;

            // Get top performing items based on sales
            usort($results, function ($a, $b) {
                return $b['sold_quantity'] <=> $a['sold_quantity'];
            });

            $topPerformers = array_slice($results, 0, 5);

            return [
                'top_performers' => $topPerformers,
                'price_analysis' => [
                    'min' => $minPrice,
                    'max' => $maxPrice,
                    'avg' => $avgPrice
                ],
                'market_insights' => [
                    'total_results' => count($results),
                    'competitor_density' => count($results) > 20 ? 'high' : (count($results) > 5 ? 'medium' : 'low')
                ]
            ];
        } catch (\Exception $e) {
            log_error('Erro ao obter análise de concorrência da ML API', [
                'error' => $e->getMessage(),
            ]);
            return [
                'top_performers' => [],
                'price_analysis' => ['min' => 0, 'max' => 0, 'avg' => 0],
                'market_insights' => ['total_results' => 0, 'competitor_density' => 'unknown']
            ];
        }
    }

    /**
     * Diagnose connection status
     * 
     * Verifica se a conexão com a API está funcionando corretamente
     * 
     * @return array Diagnóstico completo
     */
    public function diagnose(): array
    {
        $result = [
            'connected' => false,
            'has_token' => $this->hasAccessToken,
            'account_id' => $this->accountId,
            'seller_id' => null,
            'user_info' => null,
            'token_status' => 'unknown',
            'api_accessible' => false,
            'items_count' => 0,
            'error' => null,
            'checks' => []
        ];

        // Check 1: Token disponível
        $result['checks']['token'] = $this->hasAccessToken ? 'ok' : 'missing';

        // Check 2: API pública acessível
        try {
            $site = $this->get('/sites/MLB', [], 60, true);
            $result['api_accessible'] = isset($site['id']);
            $result['checks']['public_api'] = isset($site['id']) ? 'ok' : 'failed';
        } catch (\Throwable $e) {
            $result['checks']['public_api'] = 'error: ' . $e->getMessage();
        }

        // Check 3: Autenticação
        if ($this->hasAccessToken) {
            try {
                $me = $this->get('/users/me');
                if (isset($me['id'])) {
                    $result['connected'] = true;
                    $result['seller_id'] = $me['id'];
                    $result['user_info'] = [
                        'id' => $me['id'],
                        'nickname' => $me['nickname'] ?? null,
                        'email' => $me['email'] ?? null,
                    ];
                    $result['token_status'] = 'valid';
                    $result['checks']['auth'] = 'ok';
                } elseif (isset($me['error'])) {
                    $result['token_status'] = 'invalid';
                    $result['error'] = $me['message'] ?? $me['error'];
                    $result['checks']['auth'] = 'failed: ' . ($me['message'] ?? $me['error']);
                }
            } catch (\Throwable $e) {
                $result['token_status'] = 'error';
                $result['error'] = $e->getMessage();
                $result['checks']['auth'] = 'error: ' . $e->getMessage();
            }
        } else {
            $result['checks']['auth'] = 'skipped (no token)';
        }

        // Check 4: Listagem de itens (se autenticado)
        if ($result['connected']) {
            try {
                $items = $this->getMyItems(['limit' => 1]);
                if (isset($items['paging']['total'])) {
                    $result['items_count'] = $items['paging']['total'];
                    $result['checks']['items'] = 'ok (' . $items['paging']['total'] . ' items)';
                } elseif (isset($items['error'])) {
                    $result['checks']['items'] = 'failed: ' . ($items['message'] ?? $items['error']);
                }
            } catch (\Throwable $e) {
                $result['checks']['items'] = 'error: ' . $e->getMessage();
            }
        }

        return $result;
    }
}
