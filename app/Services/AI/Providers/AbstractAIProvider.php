<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use App\Services\StructuredLogService;

/**
 * Abstract base class for AI providers
 * All AI model integrations should extend this class
 */
abstract class AbstractAIProvider
{
    protected string $apiKey;
    protected string $model;
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->initialize();
    }

    /**
     * Initialize provider-specific configuration
     */
    abstract protected function initialize(): void;

    /**
     * Generate completion from prompt
     * 
     * @param string $prompt The prompt to send to the AI
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return array Response with 'content', 'usage', 'model'
     */
    abstract public function complete(string $prompt, array $options = []): array;

    /**
     * Generate chat completion (multi-turn conversation)
     * 
     * @param array $messages Array of messages [['role' => 'user', 'content' => '...']]
     * @param array $options Additional options
     * @return array Response with 'content', 'usage', 'model'
     */
    abstract public function chat(array $messages, array $options = []): array;

    /**
     * Check if provider is available and properly configured
     * 
     * @return bool
     */
    abstract public function isAvailable(): bool;

    /**
     * Get provider name
     * 
     * @return string
     */
    abstract public function getName(): string;

    /**
     * Get default model for this provider
     * 
     * @return string
     */
    abstract public function getDefaultModel(): string;

    /**
     * Estimate cost for a request
     * 
     * @param int $inputTokens
     * @param int $outputTokens
     * @return float Cost in USD
     */
    abstract public function estimateCost(int $inputTokens, int $outputTokens): float;

    /**
     * Parse and normalize response from provider
     * 
     * @param mixed $rawResponse
     * @return array Normalized response
     */
    protected function normalizeResponse($rawResponse): array
    {
        return [
            'content' => '',
            'usage' => [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
            ],
            'model' => $this->model,
            'provider' => $this->getName(),
            'raw' => $rawResponse,
        ];
    }

    /**
     * Handle API errors
     * 
     * @param \Exception $e
     * @return array Error response
     */
    protected function handleError(\Exception $e): array
    {
        $context = [
            'service' => 'AbstractAIProvider',
            'provider' => $this->getName(),
            'error' => $e->getMessage(),
        ];

        try {
            if (function_exists('log_error')) {
                \log_error('Erro no provedor AI', $context);
            } else {
                (new StructuredLogService())->error('Erro no provedor AI', $context);
            }
        } catch (\Throwable $t) {
            error_log('Erro no provedor AI: ' . $e->getMessage());
        }

        return [
            'error' => true,
            'message' => $e->getMessage(),
            'provider' => $this->getName(),
        ];
    }

    /**
     * Get configuration value
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Set configuration value
     * 
     * @param string $key
     * @param mixed $value
     */
    protected function setConfig(string $key, $value): void
    {
        $this->config[$key] = $value;
    }
}
