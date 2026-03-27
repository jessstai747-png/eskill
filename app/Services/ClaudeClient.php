<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Claude API Client
 *
 * Cliente para comunicação com a API da Anthropic Claude.
 * Implementa chamadas para o modelo Claude 3.5 Sonnet.
 */
class ClaudeClient
{
    private string $apiKey;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';
    private string $model = 'claude-3-5-sonnet-20241022';
    private int $maxTokens = 4096;
    private string $version = '2023-06-01';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? $_ENV['ANTHROPIC_API_KEY'] ?? '';

        if (empty($this->apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not configured');
        }
    }

    /**
     * Send a message to Claude and get response
     *
     * @param array $messages Array of messages [['role' => 'user', 'content' => '...']]
     * @param array $options Additional options (system, temperature, max_tokens)
     * @return array Response from Claude API
     * @throws \RuntimeException On API errors
     */
    public function complete(array $messages, array $options = []): array
    {
        $payload = [
            'model' => $options['model'] ?? $this->model,
            'max_tokens' => $options['max_tokens'] ?? $this->maxTokens,
            'messages' => $messages,
        ];

        // Add optional parameters
        if (isset($options['system'])) {
            $payload['system'] = $options['system'];
        }

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        if (isset($options['top_p'])) {
            $payload['top_p'] = $options['top_p'];
        }

        // Make API request
        $response = $this->makeRequest($payload);

        return $response;
    }

    /**
     * Generate feature list from requirements
     *
     * @param array $requirements High-level requirements
     * @param string $category Project category
     * @return array Expanded feature list
     */
    public function generateFeatureList(array $requirements, string $category): array
    {
        $prompt = $this->buildFeatureListPrompt($requirements, $category);

        $response = $this->complete([
            ['role' => 'user', 'content' => $prompt]
        ], [
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ]);

        return $this->parseFeatureListResponse($response);
    }

    /**
     * Implement a specific feature
     *
     * @param array $feature Feature details
     * @param string $projectPath Path to project
     * @param array $context Additional context
     * @return array Implementation result
     */
    public function implementFeature(array $feature, string $projectPath, array $context = []): array
    {
        $prompt = $this->buildImplementationPrompt($feature, $projectPath, $context);

        $response = $this->complete([
            ['role' => 'user', 'content' => $prompt]
        ], [
            'temperature' => 0.5,
            'max_tokens' => 4096,
        ]);

        return $this->parseImplementationResponse($response);
    }

    /**
     * Build prompt for feature list generation
     */
    private function buildFeatureListPrompt(array $requirements, string $category): string
    {
        $requirementsList = implode("\n", array_map(fn(string $r): string => "- {$r}", $requirements));

        return <<<PROMPT
You are an expert software architect. Given these high-level requirements for a {$category} project:

{$requirementsList}

Generate a comprehensive list of granular, testable features. Each feature should:
1. Be small and focused (one clear responsibility)
2. Be testable end-to-end from a user perspective
3. Have clear test steps
4. Be independent where possible

Output format (JSON array):
[
  {
    "id": "F1",
    "category": "functional|ui|performance|security",
    "description": "User can ...",
    "priority": "high|medium|low",
    "steps": [
      "Step 1 to test this feature",
      "Step 2 to verify behavior"
    ]
  }
]

Generate 100+ features. Be thorough and comprehensive. Return ONLY the JSON array, no other text.
PROMPT;
    }

    /**
     * Build prompt for feature implementation
     */
    private function buildImplementationPrompt(array $feature, string $projectPath, array $context): string
    {
        $featureDesc = $feature['description'] ?? 'Unknown feature';
        $featureId = $feature['id'] ?? 'FX';

        return <<<PROMPT
You are an expert software developer implementing features incrementally.

FEATURE TO IMPLEMENT: {$featureId} - {$featureDesc}

PROJECT PATH: {$projectPath}

            Your task:
            1. Implement the feature following best practices
            2. Add necessary service layer logic
            3. Add controller endpoints if needed
            4. Add tests

            Respond with JSON:
            {
              "files": [
                {
                  "path": "src/Service/Example.php",
                  "content": "<?php ... (FULL FILE CONTENT)"
                },
                {
                  "path": "tests/Feature/ExampleTest.php",
                  "content": "<?php ... (FULL FILE CONTENT)"
                }
              ],
              "summary": "What was implemented"
            }

            Return ONLY the JSON, no other text. Ensure ALL PHP files start with <?php.
PROMPT;
    }

    /**
     * Parse feature list response from Claude
     */
    private function parseFeatureListResponse(array $response): array
    {
        $content = $response['content'][0]['text'] ?? '';

        // Extract JSON from response
        if (preg_match('/\[[\s\S]*\]/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        throw new \RuntimeException('Failed to parse feature list from Claude response');
    }

    /**
     * Parse implementation response from Claude
     */
    private function parseImplementationResponse(array $response): array
    {
        $content = $response['content'][0]['text'] ?? '';

        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json) {
                return $json;
            }
        }

        throw new \RuntimeException('Failed to parse implementation from Claude response');
    }

    /**
     * Make HTTP request to Claude API
     */
    private function makeRequest(array $payload): array
    {
        $ch = curl_init($this->apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: ' . $this->version,
            ],
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Claude API error (HTTP {$httpCode}): {$response}");
        }

        $data = json_decode($response, true);
        if (!$data) {
            throw new \RuntimeException("Failed to decode Claude API response");
        }

        // Track API call for rate limiting
        $this->trackApiCall();

        return $data;
    }

    /**
     * Track API call with intelligent rate limiter
     */
    private function trackApiCall(): void
    {
        try {
            $tracker = new RateLimitTrackerService();
            $tracker->trackCall('anthropic');

            // Check if we should alert
            $alert = $tracker->shouldAlert('anthropic');
            if ($alert) {
                log_warning('Rate limit alert', ['service' => 'ClaudeClient', 'level' => $alert['level'], 'message' => $alert['message']]);
            }
        } catch (\Exception $e) {
            // Non-blocking
            log_warning('Failed to track rate limit', ['service' => 'ClaudeClient', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Test connection to Claude API
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->complete([
                ['role' => 'user', 'content' => 'Say "OK" if you can hear me.']
            ], [
                'max_tokens' => 10,
            ]);

            return !empty($response['content']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get usage statistics from last response
     */
    public function getUsageStats(array $response): array
    {
        return [
            'input_tokens' => $response['usage']['input_tokens'] ?? 0,
            'output_tokens' => $response['usage']['output_tokens'] ?? 0,
        ];
    }
}
