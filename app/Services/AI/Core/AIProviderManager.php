<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

use App\Services\AI\Providers\AbstractAIProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\AlertService;

/**
 * AI Provider Manager
 * Manages multiple AI providers with fallback logic and circuit breaker.
 *
 * Circuit breaker prevents hammering providers with known-bad API keys
 * or expired credits. When a provider returns an auth/key/credit error,
 * it is "tripped" and skipped for CIRCUIT_BREAKER_TTL seconds.
 */
class AIProviderManager
{
    private array $providers = [];
    private array $config;
    private ?string $preferredProvider = null;

    /** @var array<string, int> In-memory circuit breaker state: providerName => expiresAt */
    private static array $circuitBreaker = [];

    /** Seconds to skip a provider after an auth/key/credit failure */
    private const CIRCUIT_BREAKER_TTL = 300;

    /** File path for cross-request circuit breaker persistence */
    private const CIRCUIT_BREAKER_FILE = __DIR__ . '/../../../../storage/cache/ai_provider_circuit_breaker.json';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->preferredProvider = $_ENV['AI_PREFERRED_PROVIDER'] ?? 'openai';

        $this->loadCircuitState();
        $this->initializeProviders();
    }

    /**
     * Initialize all available providers
     */
    private function initializeProviders(): void
    {
        // OpenAI
        $openai = new OpenAIProvider($this->config);
        if ($openai->isAvailable()) {
            $this->providers['openai'] = $openai;
        }

        // Claude
        $claude = new ClaudeProvider($this->config);
        if ($claude->isAvailable()) {
            $this->providers['claude'] = $claude;
        }

        // Gemini
        $gemini = new GeminiProvider($this->config);
        if ($gemini->isAvailable()) {
            $this->providers['gemini'] = $gemini;
        }
    }

    /**
     * Get primary provider
     * 
     * @return AbstractAIProvider|null
     */
    public function getPrimaryProvider(): ?AbstractAIProvider
    {
        if (isset($this->providers[$this->preferredProvider])) {
            return $this->providers[$this->preferredProvider];
        }

        // Fallback to first available
        return !empty($this->providers) ? reset($this->providers) : null;
    }

    /**
     * Get provider by name
     * 
     * @param string $name
     * @return AbstractAIProvider|null
     */
    public function getProvider(string $name): ?AbstractAIProvider
    {
        return $this->providers[$name] ?? null;
    }

    /**
     * Execute request with fallback logic and circuit breaker.
     *
     * Skips providers whose circuit is open (recent auth/key/credit failure).
     * When a provider fails with an auth error, its circuit is tripped.
     *
     * @param string $method Method name (chat, complete)
     * @param array $args Method arguments
     * @param array $options Options including provider preference
     * @return array
     */
    public function executeWithFallback(string $method, array $args, array $options = []): array
    {
        $preferredProvider = $options['provider'] ?? $this->preferredProvider;
        $enabledFallback = $options['fallback'] ?? ($_ENV['AI_FALLBACK_ENABLED'] ?? true);
        $attempted = [];
        $result = null;

        // Try preferred provider first (if circuit is closed)
        if (isset($this->providers[$preferredProvider])) {
            if ($this->isCircuitOpen($preferredProvider)) {
                log_info('AI provider circuit open, skipping', [
                    'service' => 'AIProviderManager',
                    'provider' => $preferredProvider,
                ]);
            } else {
                $result = $this->providers[$preferredProvider]->$method(...$args);
                $attempted[] = $preferredProvider;

                if (!isset($result['error'])) {
                    return $result;
                }

                $errorMessage = $result['message'] ?? 'Unknown error';
                $this->tripCircuitIfAuthError($preferredProvider, $errorMessage);
                log_warning('AI provider failed', [
                    'service' => 'AIProviderManager',
                    'provider' => $preferredProvider,
                    'error' => $errorMessage,
                ]);
            }
        }

        // Try fallback providers if enabled (skip those with open circuits)
        if ($enabledFallback) {
            foreach ($this->providers as $name => $provider) {
                if ($name === $preferredProvider) {
                    continue;
                }

                if ($this->isCircuitOpen($name)) {
                    log_info('AI fallback provider circuit open, skipping', [
                        'service' => 'AIProviderManager',
                        'provider' => $name,
                    ]);
                    continue;
                }

                log_info('Trying fallback AI provider', [
                    'service' => 'AIProviderManager',
                    'fallback_provider' => $name,
                ]);

                $result = $provider->$method(...$args);
                $attempted[] = $name;

                if (!isset($result['error'])) {
                    $result['fallback_used'] = true;
                    $result['fallback_provider'] = $name;
                    return $result;
                }

                $errorMessage = $result['message'] ?? 'Unknown error';
                $this->tripCircuitIfAuthError($name, $errorMessage);
            }
        }

        // All providers failed or were circuit-broken
        $lastError = $result['message'] ?? 'All providers circuit-broken or unavailable';
        if (!empty($attempted)) {
            $this->triggerFailureAlert($lastError);
        }

        return [
            'error' => true,
            'message' => 'All AI providers failed',
            'attempted_providers' => $attempted,
            'circuit_broken_providers' => $this->getCircuitBrokenProviders(),
        ];
    }

    /**
     * Trigger alert on AI failure
     */
    private function triggerFailureAlert(string $lastError): void
    {
        try {
            $alertService = new AlertService();
            $alertService->createAlert(null, 'ai_provider_error', [
                'last_error' => $lastError,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } catch (\Throwable $e) {
            log_warning('Failed to trigger AI alert', ['service' => 'AIProviderManager', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Chat with automatic fallback
     * 
     * @param array $messages
     * @param array $options
     * @return array
     */
    public function chat(array $messages, array $options = []): array
    {
        return $this->executeWithFallback('chat', [$messages, $options], $options);
    }

    /**
     * Complete with automatic fallback
     * 
     * @param string $prompt
     * @param array $options
     * @return array
     */
    public function complete(string $prompt, array $options = []): array
    {
        return $this->executeWithFallback('complete', [$prompt, $options], $options);
    }

    /**
     * Get all available providers
     * 
     * @return array
     */
    public function getAvailableProviders(): array
    {
        $available = [];

        foreach ($this->providers as $name => $provider) {
            $available[$name] = [
                'name' => $provider->getName(),
                'model' => $provider->getDefaultModel(),
                'available' => $provider->isAvailable(),
            ];
        }

        return $available;
    }

    /**
     * Get provider statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'total_providers' => count($this->providers),
            'preferred_provider' => $this->preferredProvider,
            'available_providers' => array_keys($this->providers),
            'fallback_enabled' => $_ENV['AI_FALLBACK_ENABLED'] ?? true,
        ];
    }

    /**
     * Compare providers for same prompt
     * Useful for testing and choosing best provider
     * 
     * @param string $prompt
     * @param array $options
     * @return array Results from all providers
     */
    public function compareProviders(string $prompt, array $options = []): array
    {
        $results = [];

        foreach ($this->providers as $name => $provider) {
            $startTime = microtime(true);

            $result = $provider->complete($prompt, $options);

            $duration = microtime(true) - $startTime;

            $results[$name] = [
                'provider' => $provider->getName(),
                'result' => $result,
                'duration' => round($duration, 3),
                'cost' => $result['cost'] ?? 0,
                'success' => !isset($result['error']),
            ];
        }

        return $results;
    }

    /**
     * Select best provider based on criteria
     * 
     * @param string $criteria (cost, speed, quality)
     * @return string|null Provider name
     */
    public function selectBestProvider(string $criteria = 'cost'): ?string
    {
        if (empty($this->providers)) {
            return null;
        }

        $rankings = [
            'cost' => ['gemini' => 1, 'openai' => 2, 'claude' => 3], // Gemini cheapest
            'speed' => ['gemini' => 1, 'openai' => 2, 'claude' => 3],
            'quality' => ['claude' => 1, 'openai' => 2, 'gemini' => 3], // Claude best quality
        ];

        $ranking = $rankings[$criteria] ?? $rankings['cost'];

        foreach ($ranking as $providerName => $rank) {
            if (isset($this->providers[$providerName])) {
                return $providerName;
            }
        }

        return array_key_first($this->providers);
    }

    /**
     * Get the preferred provider instance
     * 
     * @return AbstractAIProvider|null
     */
    public function getPreferredProvider(): ?AbstractAIProvider
    {
        return $this->getPrimaryProvider();
    }

    /**
     * Get provider with fallback if the specified one is not available
     * 
     * @param string $preferredName
     * @return AbstractAIProvider|null
     */
    public function getProviderWithFallback(string $preferredName): ?AbstractAIProvider
    {
        // Try preferred first
        if (isset($this->providers[$preferredName])) {
            return $this->providers[$preferredName];
        }

        // Return first available as fallback
        return !empty($this->providers) ? reset($this->providers) : null;
    }

    /**
     * Get provider statistics with counts
     * 
     * @return array
     */
    public function getProviderStats(): array
    {
        $availableCount = count(array_filter($this->providers, function ($provider) {
            return $provider->isAvailable();
        }));

        return [
            'total_providers' => count($this->providers),
            'available_count' => $availableCount,
            'preferred_provider' => $this->preferredProvider,
            'available_providers' => array_keys($this->providers),
            'fallback_enabled' => $_ENV['AI_FALLBACK_ENABLED'] ?? true,
        ];
    }

    /**
     * Check if a specific provider is available
     * 
     * @param string $name Provider name
     * @return bool
     */
    public function isProviderAvailable(string $name): bool
    {
        return isset($this->providers[$name]) && $this->providers[$name]->isAvailable();
    }

    /**
     * Get the cheapest available provider
     * 
     * @return AbstractAIProvider|null
     */
    public function getCheapestProvider(): ?AbstractAIProvider
    {
        $providerName = $this->selectBestProvider('cost');
        return $providerName ? ($this->providers[$providerName] ?? null) : null;
    }

    /**
     * Get the fastest available provider
     * 
     * @return AbstractAIProvider|null
     */
    public function getFastestProvider(): ?AbstractAIProvider
    {
        $providerName = $this->selectBestProvider('speed');
        return $providerName ? ($this->providers[$providerName] ?? null) : null;
    }

    /**
     * Get provider based on strategy
     * 
     * @param string $strategy (cost, speed, quality)
     * @return AbstractAIProvider|null
     */
    public function getProviderByStrategy(string $strategy): ?AbstractAIProvider
    {
        $providerName = $this->selectBestProvider($strategy);
        return $providerName ? ($this->providers[$providerName] ?? null) : null;
    }

    // ─── Circuit Breaker ────────────────────────────────────────────────

    /**
     * Check if circuit is open (provider should be skipped).
     */
    private function isCircuitOpen(string $providerName): bool
    {
        if (!isset(self::$circuitBreaker[$providerName])) {
            return false;
        }

        if (time() >= self::$circuitBreaker[$providerName]) {
            unset(self::$circuitBreaker[$providerName]);
            $this->persistCircuitState();
            return false;
        }

        return true;
    }

    /**
     * Trip circuit breaker if the error is auth/key/credit related.
     */
    private function tripCircuitIfAuthError(string $providerName, string $errorMessage): void
    {
        if (!$this->isAuthError($errorMessage)) {
            return;
        }

        $expiresAt = time() + self::CIRCUIT_BREAKER_TTL;
        self::$circuitBreaker[$providerName] = $expiresAt;
        $this->persistCircuitState();

        if (function_exists('log_warning')) {
            log_warning('AI provider circuit breaker tripped', [
                'service' => 'AIProviderManager',
                'provider' => $providerName,
                'ttl_seconds' => self::CIRCUIT_BREAKER_TTL,
                'reason' => substr($errorMessage, 0, 200),
            ]);
        }
    }

    /**
     * Detect auth/key/credit errors that justify tripping the circuit.
     */
    private function isAuthError(string $message): bool
    {
        $patterns = [
            'Unauthorized',
            'API key',
            'api key',
            'Incorrect API key',
            'invalid_api_key',
            'credit balance',
            'insufficient_quota',
            'billing',
            'invalid_grant',
            'API key not valid',
        ];

        foreach ($patterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get list of currently circuit-broken providers.
     *
     * @return array<string, int> providerName => expiresAt
     */
    private function getCircuitBrokenProviders(): array
    {
        $now = time();
        $broken = [];

        foreach (self::$circuitBreaker as $name => $expiresAt) {
            if ($now < $expiresAt) {
                $broken[$name] = $expiresAt;
            }
        }

        return $broken;
    }

    /**
     * Reset circuit breaker for a specific provider (e.g. after key update).
     */
    public function resetCircuit(string $providerName): void
    {
        unset(self::$circuitBreaker[$providerName]);
        $this->persistCircuitState();
    }

    /**
     * Reset all circuit breakers.
     */
    public function resetAllCircuits(): void
    {
        self::$circuitBreaker = [];
        $this->persistCircuitState();
    }

    /**
     * Load circuit breaker state from file cache (cross-request persistence).
     */
    private function loadCircuitState(): void
    {
        if (!file_exists(self::CIRCUIT_BREAKER_FILE)) {
            return;
        }

        try {
            $json = file_get_contents(self::CIRCUIT_BREAKER_FILE);
            if ($json === false) {
                return;
            }

            $state = json_decode($json, true);
            if (!is_array($state)) {
                return;
            }

            $now = time();
            foreach ($state as $name => $expiresAt) {
                if (is_int($expiresAt) && $expiresAt > $now) {
                    self::$circuitBreaker[$name] = $expiresAt;
                }
            }
        } catch (\Throwable $e) {
            // Silently ignore corrupt cache file
        }
    }

    /**
     * Persist circuit breaker state to file for cross-request use.
     */
    private function persistCircuitState(): void
    {
        try {
            $now = time();
            $active = [];

            foreach (self::$circuitBreaker as $name => $expiresAt) {
                if ($expiresAt > $now) {
                    $active[$name] = $expiresAt;
                }
            }

            file_put_contents(
                self::CIRCUIT_BREAKER_FILE,
                json_encode($active, JSON_PRETTY_PRINT),
                LOCK_EX
            );
        } catch (\Throwable $e) {
            // Non-critical — best effort persistence
        }
    }
}
