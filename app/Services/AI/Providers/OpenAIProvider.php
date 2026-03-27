<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * OpenAI API Provider (GPT-4o, GPT-4, GPT-3.5)
 */
class OpenAIProvider extends AbstractAIProvider
{
    private const API_URL = 'https://api.openai.com/';
    private const DEFAULT_MODEL = 'gpt-4o';

    // Pricing per 1M tokens (USD)
    private const PRICING = [
        'gpt-4.1'       => ['input' => 2.00, 'output' => 8.00],
        'gpt-4.1-mini'  => ['input' => 0.40, 'output' => 1.60],
        'gpt-4o'        => ['input' => 2.50, 'output' => 10.00],
        'gpt-4o-mini'   => ['input' => 0.15, 'output' => 0.60],
        'o4-mini'       => ['input' => 1.10, 'output' => 4.40],
        'gpt-4-turbo'   => ['input' => 10.00, 'output' => 30.00],
        'gpt-3.5-turbo' => ['input' => 0.50, 'output' => 1.50],
    ];

    private Client $httpClient;

    protected function initialize(): void
    {
        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? '';
        $this->model = $this->getConfig('model', self::DEFAULT_MODEL);

        $this->httpClient = new Client([
            'base_uri' => $_ENV['OPENAI_API_BASE_URL'] ?? self::API_URL,
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        // Use chat endpoint with single user message
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'error' => true,
                'message' => 'OpenAI API key not configured',
            ];
        }

        $requestBody = [
            'model' => $options['model'] ?? $this->model,
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 4000,
        ];

        // Optional parameters
        if (isset($options['top_p'])) {
            $requestBody['top_p'] = $options['top_p'];
        }
        if (isset($options['frequency_penalty'])) {
            $requestBody['frequency_penalty'] = $options['frequency_penalty'];
        }
        if (isset($options['presence_penalty'])) {
            $requestBody['presence_penalty'] = $options['presence_penalty'];
        }

        try {
            $response = $this->httpClient->post('v1/chat/completions', [
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
        return 'OpenAI';
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
        if (!isset($rawResponse['choices'][0]['message']['content'])) {
            return [
                'error' => true,
                'message' => 'Invalid response format from OpenAI',
                'raw' => $rawResponse,
            ];
        }

        $usage = $rawResponse['usage'] ?? [];

        return [
            'content' => $rawResponse['choices'][0]['message']['content'],
            'usage' => [
                'input_tokens' => $usage['prompt_tokens'] ?? 0,
                'output_tokens' => $usage['completion_tokens'] ?? 0,
                'total_tokens' => $usage['total_tokens'] ?? 0,
            ],
            'model' => $rawResponse['model'] ?? $this->model,
            'provider' => $this->getName(),
            'cost' => $this->estimateCost(
                $usage['prompt_tokens'] ?? 0,
                $usage['completion_tokens'] ?? 0
            ),
            'finish_reason' => $rawResponse['choices'][0]['finish_reason'] ?? 'unknown',
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
     * Count tokens (rough estimate, not exact)
     * OpenAI uses tiktoken, but this is a simple approximation
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
