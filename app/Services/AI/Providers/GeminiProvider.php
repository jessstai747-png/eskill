<?php

declare(strict_types=1);

namespace App\Services\AI\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Google Gemini AI Provider
 * Supports Gemini 1.5 Pro, Gemini 1.5 Flash, and Gemini 1.0 Pro
 */
class GeminiProvider extends AbstractAIProvider
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const DEFAULT_MODEL = 'gemini-1.5-pro';

    // Pricing per 1M tokens (USD)
    private const PRICING = [
        'gemini-1.5-pro' => ['input' => 1.25, 'output' => 5.00],
        'gemini-1.5-flash' => ['input' => 0.075, 'output' => 0.30],
        'gemini-1.0-pro' => ['input' => 0.50, 'output' => 1.50],
    ];

    private Client $httpClient;

    protected function initialize(): void
    {
        $this->apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
        $this->model = $this->getConfig('model', self::DEFAULT_MODEL);

        $this->httpClient = new Client([
            'timeout' => 60,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function complete(string $prompt, array $options = []): array
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt]
        ], $options);
    }

    public function chat(array $messages, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'error' => true,
                'message' => 'Google Gemini API key not configured',
            ];
        }

        $model = $options['model'] ?? $this->model;

        // Convert messages to Gemini format
        $contents = $this->formatMessagesForGemini($messages);

        $requestBody = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 4000,
                'topP' => $options['top_p'] ?? 0.95,
                'topK' => $options['top_k'] ?? 40,
            ],
        ];

        // Safety settings (optional, default to less restrictive)
        if (!isset($options['disable_safety'])) {
            $requestBody['safetySettings'] = [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ];
        }

        // System instruction (if provided in options or first message)
        if (isset($options['system'])) {
            $requestBody['systemInstruction'] = [
                'parts' => [['text' => $options['system']]]
            ];
        }

        $url = self::API_URL . "/models/{$model}:generateContent?key={$this->apiKey}";

        try {
            $response = $this->httpClient->post($url, [
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

    /**
     * Convert standard message format to Gemini format
     * 
     * @param array $messages Standard format: [['role' => 'user', 'content' => '...']]
     * @return array Gemini format: [['role' => 'user', 'parts' => [['text' => '...']]]]
     */
    private function formatMessagesForGemini(array $messages): array
    {
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'];

            // Map assistant to model (Gemini uses 'model' instead of 'assistant')
            if ($role === 'assistant') {
                $role = 'model';
            }

            // Skip system messages - handle separately
            if ($role === 'system') {
                continue;
            }

            $parts = [];

            if (is_string($message['content'])) {
                $parts[] = ['text' => $message['content']];
            } elseif (is_array($message['content'])) {
                // Multi-modal content (text + images)
                foreach ($message['content'] as $item) {
                    if (isset($item['text'])) {
                        $parts[] = ['text' => $item['text']];
                    } elseif (isset($item['image_url'])) {
                        // Handle image URL
                        $imageData = $this->fetchImageAsBase64($item['image_url']['url']);
                        if ($imageData) {
                            $parts[] = [
                                'inline_data' => [
                                    'mime_type' => $imageData['mime_type'],
                                    'data' => $imageData['data']
                                ]
                            ];
                        }
                    }
                }
            }

            $contents[] = [
                'role' => $role,
                'parts' => $parts
            ];
        }

        return $contents;
    }

    /**
     * Fetch image and convert to base64 for Gemini API
     */
    private function fetchImageAsBase64(string $url): ?array
    {
        try {
            // Validate URL protocol — only allow http/https
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? '';
            if (!in_array($scheme, ['http', 'https'], true)) {
                log_warning('GeminiProvider: protocolo não permitido para imagem', [
                    'service' => 'GeminiProvider',
                    'url' => $url,
                    'scheme' => $scheme,
                ]);
                return null;
            }
            
            // Block private/reserved IP ranges (SSRF protection)
            $host = $parsed['host'] ?? '';
            if (empty($host)) {
                return null;
            }
            $ip = gethostbyname($host);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                log_warning('GeminiProvider: IP privado/reservado bloqueado', [
                    'service' => 'GeminiProvider',
                    'url' => $url,
                    'resolved_ip' => $ip,
                ]);
                return null;
            }
            
            $imageContent = file_get_contents($url);
            if ($imageContent === false) {
                return null;
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($imageContent);

            return [
                'mime_type' => $mimeType,
                'data' => base64_encode($imageContent)
            ];
        } catch (\Exception $e) {
            log_warning('Falha ao buscar imagem para Gemini', [
                'service' => 'GeminiProvider',
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function getName(): string
    {
        return 'Gemini';
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
        // Check for errors in response
        if (isset($rawResponse['error'])) {
            return [
                'error' => true,
                'message' => $rawResponse['error']['message'] ?? 'Unknown Gemini error',
                'raw' => $rawResponse,
            ];
        }

        // Check for blocked content
        if (isset($rawResponse['promptFeedback']['blockReason'])) {
            return [
                'error' => true,
                'message' => 'Content blocked: ' . $rawResponse['promptFeedback']['blockReason'],
                'raw' => $rawResponse,
            ];
        }

        // Extract content from candidates
        if (!isset($rawResponse['candidates'][0]['content']['parts'][0]['text'])) {
            return [
                'error' => true,
                'message' => 'Invalid response format from Gemini',
                'raw' => $rawResponse,
            ];
        }

        $usageMetadata = $rawResponse['usageMetadata'] ?? [];
        $inputTokens = $usageMetadata['promptTokenCount'] ?? 0;
        $outputTokens = $usageMetadata['candidatesTokenCount'] ?? 0;

        return [
            'content' => $rawResponse['candidates'][0]['content']['parts'][0]['text'],
            'usage' => [
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
            ],
            'model' => $this->model,
            'provider' => $this->getName(),
            'cost' => $this->estimateCost($inputTokens, $outputTokens),
            'finish_reason' => $rawResponse['candidates'][0]['finishReason'] ?? 'unknown',
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
     * Count tokens using Gemini's count tokens API
     * 
     * @param string $text
     * @return int Token count
     */
    public function countTokens(string $text): int
    {
        if (!$this->isAvailable()) {
            // Fall back to rough estimate
            return (int) ceil(strlen($text) / 4);
        }

        $url = self::API_URL . "/models/{$this->model}:countTokens?key={$this->apiKey}";

        try {
            $response = $this->httpClient->post($url, [
                'json' => [
                    'contents' => [
                        ['parts' => [['text' => $text]]]
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            return $data['totalTokens'] ?? (int) ceil(strlen($text) / 4);
        } catch (\Exception $e) {
            // Fall back to rough estimate
            return (int) ceil(strlen($text) / 4);
        }
    }

    /**
     * Estimate tokens (rough approximation without API call)
     * 
     * @param string $text
     * @return int Estimated token count
     */
    public function estimateTokens(string $text): int
    {
        // Gemini token estimation: ~4 characters per token
        return (int) ceil(strlen($text) / 4);
    }
}
