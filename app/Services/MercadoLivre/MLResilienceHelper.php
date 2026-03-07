<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Services\CircuitBreakerService;
use App\Services\CacheService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\RequestException;

/**
 * 🚀 ML API Resilience Helper
 * 
 * Adiciona camada de resiliência robusta para chamadas à API do Mercado Livre:
 * - Rate limiting automático
 * - Retry com exponential backoff
 * - Circuit breaker
 * - Cache inteligente
 * - Logging estruturado
 * 
 * @package App\Services\MercadoLivre
 */
class MLResilienceHelper
{
    private CircuitBreakerService $circuitBreaker;
    private ?CacheService $cache;
    private Client $httpClient;
    
    // Rate Limiting (requests por segundo)
    private int $rateLimitPerSecond = 10;
    private array $requestTimestamps = [];
    
    // Retry Configuration
    private int $maxRetries = 3;
    private array $retryStatusCodes = [429, 500, 502, 503, 504];
    private array $retryDelays = [1, 2, 4]; // segundos (exponential backoff)
    
    public function __construct(?CircuitBreakerService $circuitBreaker = null, ?CacheService $cache = null)
    {
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreakerService('mercadolivre_api');
        $this->cache = $cache;
        $this->httpClient = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false, // Handle errors manually
        ]);
    }

    /**
     * Faz requisição resiliente à API do ML
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $url Full URL or path
     * @param array $options Guzzle request options
     * @param bool $cacheable Se a resposta pode ser cacheada (apenas GET)
     * @param int $cacheTTL Tempo de cache em segundos
     * @return array{success: bool, data: mixed, error: ?string, status_code: int}
     */
    public function request(
        string $method,
        string $url,
        array $options = [],
        bool $cacheable = false,
        int $cacheTTL = 300
    ): array {
        // Verificar circuit breaker
        if (!$this->circuitBreaker->canRequest()) {
            logger()->warning('ML API circuit breaker OPEN', [
                'url' => $url,
                'method' => $method
            ]);
            
            return [
                'success' => false,
                'data' => null,
                'error' => 'Mercado Livre API temporariamente indisponível (circuit breaker)',
                'status_code' => 503
            ];
        }

        // Verificar cache (apenas GET)
        if ($cacheable && $method === 'GET' && $this->cache !== null) {
            $cacheKey = $this->getCacheKey($url, $options);
            $cached = $this->cache->get($cacheKey);
            
            if ($cached !== null) {
                logger()->debug('ML API cache hit', ['url' => $url]);
                return [
                    'success' => true,
                    'data' => $cached,
                    'error' => null,
                    'status_code' => 200
                ];
            }
        }

        // Rate limiting
        $this->enforceRateLimit();

        // Tentar requisição com retry
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                logger()->debug('ML API request', [
                    'method' => $method,
                    'url' => $url,
                    'attempt' => $attempt + 1
                ]);

                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();
                $body = (string)$response->getBody();
                $data = json_decode($body, true);

                // Sucesso (2xx)
                if ($statusCode >= 200 && $statusCode < 300) {
                    $this->circuitBreaker->recordSuccess();

                    // Cachear se aplicável
                    if ($cacheable && $method === 'GET' && $this->cache !== null) {
                        $cacheKey = $this->getCacheKey($url, $options);
                        $this->cache->set($cacheKey, $data, $cacheTTL);
                    }

                    logger()->info('ML API success', [
                        'method' => $method,
                        'url' => $url,
                        'status_code' => $statusCode
                    ]);

                    return [
                        'success' => true,
                        'data' => $data,
                        'error' => null,
                        'status_code' => $statusCode
                    ];
                }

                // Erro retryable?
                if (in_array($statusCode, $this->retryStatusCodes, true) && $attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt] ?? 4;
                    
                    logger()->warning('ML API retrying', [
                        'method' => $method,
                        'url' => $url,
                        'status_code' => $statusCode,
                        'attempt' => $attempt + 1,
                        'retry_in' => $delay
                    ]);

                    sleep($delay);
                    $attempt++;
                    continue;
                }

                // Erro não retryable
                $this->circuitBreaker->recordFailure();

                logger()->error('ML API error', [
                    'method' => $method,
                    'url' => $url,
                    'status_code' => $statusCode,
                    'response' => $body
                ]);

                return [
                    'success' => false,
                    'data' => $data,
                    'error' => $data['message'] ?? "ML API error: HTTP {$statusCode}",
                    'status_code' => $statusCode
                ];

            } catch (RequestException $e) {
                $lastException = $e;
                
                // Retry em exceções de rede
                if ($attempt < $this->maxRetries) {
                    $delay = $this->retryDelays[$attempt] ?? 4;
                    
                    logger()->warning('ML API network error, retrying', [
                        'method' => $method,
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'attempt' => $attempt + 1,
                        'retry_in' => $delay
                    ]);

                    sleep($delay);
                    $attempt++;
                    continue;
                }

                break;
            }
        }

        // Todas as tentativas falharam
        $this->circuitBreaker->recordFailure();

        $errorMessage = $lastException ? $lastException->getMessage() : 'Unknown error';

        logger()->error('ML API failed after retries', [
            'method' => $method,
            'url' => $url,
            'attempts' => $attempt + 1,
            'error' => $errorMessage
        ]);

        return [
            'success' => false,
            'data' => null,
            'error' => "ML API falhou após {$attempt} tentativas: {$errorMessage}",
            'status_code' => 503
        ];
    }

    /**
     * Rate limiting - aguarda se necessário
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        
        // Remover timestamps antigos (mais de 1 segundo)
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            fn($ts) => ($now - $ts) < 1.0
        );

        // Se atingiu o limite, aguardar
        if (count($this->requestTimestamps) >= $this->rateLimitPerSecond) {
            $oldestTimestamp = min($this->requestTimestamps);
            $waitTime = 1.0 - ($now - $oldestTimestamp);
            
            if ($waitTime > 0) {
                logger()->debug('Rate limit wait', [
                    'wait_time' => $waitTime,
                    'requests_last_second' => count($this->requestTimestamps)
                ]);
                
                usleep((int)($waitTime * 1000000));
            }
        }

        // Registrar timestamp atual
        $this->requestTimestamps[] = microtime(true);
    }

    /**
     * Gera chave de cache
     */
    private function getCacheKey(string $url, array $options): string
    {
        $key = 'ml_api:' . md5($url . json_encode($options));
        return $key;
    }

    /**
     * Configura rate limit personalizado
     */
    public function setRateLimit(int $requestsPerSecond): self
    {
        $this->rateLimitPerSecond = max(1, $requestsPerSecond);
        return $this;
    }

    /**
     * Configura retry personalizado
     */
    public function setRetryConfig(int $maxRetries, array $delays = [1, 2, 4]): self
    {
        $this->maxRetries = max(0, $maxRetries);
        $this->retryDelays = $delays;
        return $this;
    }

    /**
     * Obtém estatísticas do circuit breaker
     */
    public function getCircuitBreakerStats(): array
    {
        return $this->circuitBreaker->getState();
    }
}
