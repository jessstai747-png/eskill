<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Anthropic Claude AI Provider
 */
class ClaudeProvider extends AbstractAIProvider
{
    private const API_URL = 'https://api.anthropic.com/';
    private const DEFAULT_MODEL = 'claude-3-5-sonnet-20241022';

    // Pricing per 1M tokens (USD)
    private const PRICING = [
        'claude-3-5-sonnet-20241022' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-haiku-20241022' => ['input' => 0.80, 'output' => 4.00],
        'claude-3-opus-20240229' => ['input' => 15.00, 'output' => 75.00],
    ];

    private Client $httpClient;

    protected function initialize(): void
    {
        $this->apiKey = $_ENV['ANTHROPIC_API_KEY'] ?? '';
        $this->model = $this->getConfig('model', self::DEFAULT_MODEL);

        $this->httpClient = new Client([
            'base_uri' => self::API_URL,
            'timeout' => 60,
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        // Claude uses chat format, convert prompt to message
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'error' => true,
                'message' => 'Anthropic API key not configured',
            ];
        }

        // Extract system message if present
        $systemMessage = '';
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemMessage = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $requestBody = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? 4000,
            'temperature' => $options['temperature'] ?? 0.7,
        ];

        // Add system message if present
        if ($systemMessage) {
            $requestBody['system'] = $systemMessage;
        }

        // Optional parameters
        if (isset($options['top_p'])) {
            $requestBody['top_p'] = $options['top_p'];
        }
        if (isset($options['top_k'])) {
            $requestBody['top_k'] = $options['top_k'];
        }

        try {
            $response = $this->httpClient->post('v1/messages', [
                'json' => $requestBody,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return $this->normalizeResponse($data);
        } catch (RequestException $e) {
            return $this->handleError($e);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'Anthropic Claude';
    }

    public function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $pricing = self::PRICING[$this->model] ?? self::PRICING[self::DEFAULT_MODEL];

        $inputCost = ($inputTokens / 1000000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000000) * $pricing['output'];

        return $inputCost + $outputCost;
    }

    protected function normalizeResponse($rawResponse): array
    {
        if (!isset($rawResponse['content'][0]['text'])) {
            return [
                'error' => true,
                'message' => 'Invalid response format from Claude',
                'raw' => $rawResponse,
            ];
        }

        $usage = $rawResponse['usage'] ?? [];

        return [
            'content' => $rawResponse['content'][0]['text'],
            'usage' => [
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'total_tokens' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
            ],
            'model' => $rawResponse['model'] ?? $this->model,
            'provider' => $this->getName(),
            'cost' => $this->estimateCost(
                $usage['input_tokens'] ?? 0,
                $usage['output_tokens'] ?? 0
            ),
            'stop_reason' => $rawResponse['stop_reason'] ?? 'unknown',
            'raw' => $rawResponse,
        ];
    }

    /**
     * Get available models
     * 
     * @return array
     */
    public function getAvailableModels(): array
    {
        return array_keys(self::PRICING);
    }

    /**
     * Count tokens (rough estimate)
     * Claude uses similar tokenization to GPT models
     * 
     * @param string $text
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        // Rough estimate: 1 token ≈ 4 characters
        return (int) ceil(strlen($text) / 4);
    }
}
