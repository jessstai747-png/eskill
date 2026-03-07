<?php

declare(strict_types=1);

namespace App\Services;

/**
 * AI Service for SEO strategy enhancement
 * This service integrates with AI/LLM providers (OpenAI/Claude) as mentioned in the architecture
 */
class AIService
{
    private string $provider;
    private string $apiKey;
    private \GuzzleHttp\Client $httpClient;

    public function __construct(string $provider = 'openai', ?string $apiKey = null)
    {
        $this->provider = $provider;
        $this->apiKey = $apiKey ?: $_ENV['AI_API_KEY'] ?? getenv('AI_API_KEY');

        if (!$this->apiKey) {
            throw new \Exception('AI API key not configured. Please set AI_API_KEY environment variable.');
        }

        // Initialize HTTP client
        $this->httpClient = new \GuzzleHttp\Client([
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * Generate content using AI
     */
    public function generate(string $prompt): string
    {
        try {
            $url = 'https://api.openai.com/v1/chat/completions';

            $payload = [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 500
            ];

            $response = $this->httpClient->post($url, [
                'json' => $payload
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            }

            throw new \Exception('Invalid response from AI service');
        } catch (\Exception $e) {
            log_error('Erro no AI Service', [
                'service' => 'AIService',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Expand synonyms using AI
     */
    public function expandSynonyms(string $keyword, string $categoryId): array
    {
        $prompt = "Generate a comprehensive list of synonyms and related terms for the keyword '{$keyword}' in the category {$categoryId}. Focus on Brazilian Portuguese terms. Return only as a JSON array.";
        $response = $this->generate($prompt);

        // Try to parse as JSON first
        $synonyms = json_decode($response, true);
        if (is_array($synonyms)) {
            return $synonyms;
        }

        // If not valid JSON, try to extract from plain text
        $lines = explode("\n", $response);
        $extracted = [];

        foreach ($lines as $line) {
            $cleanLine = trim($line, " \t\n\r\0\x0B-.\"");
            if (!empty($cleanLine) && !preg_match('/^[0-9]+\.?\s*$/', $cleanLine)) {
                $extracted[] = $cleanLine;
            }
        }

        return $extracted;
    }

    /**
     * Generate context-aware content
     */
    public function generateContextContent(string $baseContent, array $contexts): string
    {
        $contextStr = implode(', ', $contexts);
        $prompt = "Enhance and expand this content considering these usage contexts: {$contextStr}. Original content: {$baseContent}. Provide in Brazilian Portuguese.";

        return $this->generate($prompt);
    }

    /**
     * Classify keywords using AI
     */
    public function classifyKeywords(array $keywords, string $categoryId): array
    {
        $keywordsStr = implode(', ', $keywords);
        $prompt = "Classify these keywords for category {$categoryId} in Brazilian Portuguese market into four types: core (main product terms), support (auxiliary terms), technical (specifications), and context (usage situations). Return as a JSON object with arrays for each type. Keywords: {$keywordsStr}";

        try {
            $response = $this->generate($prompt);

            // Try to parse as JSON first
            $classification = json_decode($response, true);

            if (is_array($classification) &&
                isset($classification['core']) &&
                isset($classification['suporte']) &&
                isset($classification['tecnica']) &&
                isset($classification['contexto'])) {
                return $classification;
            }

            // If not properly formatted, return default classification
            return [
                'core' => [],
                'suporte' => [],
                'tecnica' => [],
                'contexto' => []
            ];
        } catch (\Exception $e) {
            log_warning('Erro na classificação de keywords por IA', [
                'service' => 'AIService',
                'error' => $e->getMessage(),
            ]);
            return [
                'core' => [],
                'suporte' => [],
                'tecnica' => [],
                'contexto' => []
            ];
        }
    }

    /**
     * Generate SEO-optimized description using AI
     */
    public function generateSeoDescription(string $title, string $features, string $targetKeywords): string
    {
        $prompt = "Create an SEO-optimized product description in Brazilian Portuguese for: '{$title}'. Features: {$features}. Target keywords: {$targetKeywords}. The description should be 400-600 words, include natural keyword placement, highlight benefits, and be compelling for customers.";

        return $this->generate($prompt);
    }

    /**
     * Generate FAQ content using AI
     */
    public function generateFAQ(array $keywords, string $productType): array
    {
        $keywordsStr = implode(', ', $keywords);
        $prompt = "Generate 5 common questions and answers in Brazilian Portuguese for a {$productType} focusing on these keywords: {$keywordsStr}. Return as a JSON array of objects with 'question' and 'answer' properties.";

        $response = $this->generate($prompt);
        $faq = json_decode($response, true);

        return is_array($faq) ? $faq : [];
    }
}
