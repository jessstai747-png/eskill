<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\AI\Providers\ClaudeProvider;
use App\Services\AI\Providers\OpenAIProvider;
use App\Services\AI\Providers\GeminiProvider;
use App\Services\CacheService;

/**
 * Cliente de IA para serviços SEO
 * Abstrai a comunicação com providers de IA (Claude, OpenAI, Gemini)
 * Com fallback automático entre providers
 *
 * @author Sistema SEO Profissional
 * @version 1.2.0
 */
class AIClient
{
    private $provider;
    private $fallbackProvider;
    private ?CacheService $cache;
    private string $defaultProvider;
    private array $config;
    private bool $useFallback = true;

    // Rate limiting
    private static array $requestCounts = [];
    private const MAX_REQUESTS_PER_MINUTE = 50;

    // Quota/billing error patterns
    private const QUOTA_ERROR_PATTERNS = [
        'quota', 'billing', 'credit', 'exceeded',
        'rate limit', 'too many requests', '429', '402'
    ];

    public function __construct(?string $provider = null, bool $enableFallback = true)
    {
        $this->defaultProvider = $provider ?? $_ENV['AI_DEFAULT_PROVIDER'] ?? 'claude';
        $this->useFallback = $enableFallback;
        $this->config = [
            'temperature' => (float)($_ENV['AI_TEMPERATURE'] ?? 0.7),
            'max_tokens' => (int)($_ENV['AI_MAX_TOKENS'] ?? 4000),
        ];

        $this->initializeProviders();

        try {
            $this->cache = new CacheService();
        } catch (\Exception $e) {
            $this->cache = null;
        }
    }

    /**
     * Inicializa providers de IA (principal e fallback)
     */
    private function initializeProviders(): void
    {
        switch ($this->defaultProvider) {
            case 'openai':
            case 'gpt':
                $this->provider = new OpenAIProvider();
                $this->fallbackProvider = new ClaudeProvider();
                break;
            case 'gemini':
            case 'google':
                $this->provider = new GeminiProvider();
                $this->fallbackProvider = new OpenAIProvider();
                break;
            case 'claude':
            case 'anthropic':
            default:
                $this->provider = new ClaudeProvider();
                $this->fallbackProvider = new OpenAIProvider();
                break;
        }
    }

    /**
     * Lista providers disponíveis
     */
    public static function getAvailableProviders(): array
    {
        return [
            'claude' => [
                'name' => 'Anthropic Claude',
                'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229'],
                'env_key' => 'ANTHROPIC_API_KEY',
            ],
            'openai' => [
                'name' => 'OpenAI',
                'models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo'],
                'env_key' => 'OPENAI_API_KEY',
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'models' => ['gemini-1.5-pro', 'gemini-1.5-flash', 'gemini-1.0-pro'],
                'env_key' => 'GEMINI_API_KEY',
            ],
        ];
    }

    /**
     * Verifica se o erro é relacionado a quota/billing
     */
    private function isQuotaError(string $errorMessage): bool
    {
        $errorLower = strtolower($errorMessage);
        foreach (self::QUOTA_ERROR_PATTERNS as $pattern) {
            if (strpos($errorLower, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Envia uma mensagem para a IA e retorna a resposta
     */
    public function chat(string $prompt, array $options = []): array
    {
        // Rate limiting check
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded. Please wait.',
                'content' => null
            ];
        }

        // Check cache
        $cacheKey = $this->generateCacheKey($prompt, $options);
        if ($this->cache && !($options['skip_cache'] ?? false)) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return [
                    'success' => true,
                    'content' => $cached,
                    'cached' => true,
                    'provider' => $this->defaultProvider
                ];
            }
        }

        // Prepare messages
        $messages = [];

        // Add system prompt if provided
        if (!empty($options['system'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $options['system']
            ];
        }

        // Add user message
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];

        // Make request
        $requestOptions = [
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
            'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
        ];

        // Try primary provider
        $response = $this->tryProvider($this->provider, $messages, $requestOptions);

        // If failed with quota error, try fallback
        if (!$response['success'] && $this->useFallback && $this->fallbackProvider) {
            $errorMsg = $response['error'] ?? '';
            if ($this->isQuotaError($errorMsg)) {
                log_warning('Provedor primário falhou por cota, tentando fallback', [
                    'provider' => $this->provider,
                    'fallback' => $this->fallbackProvider,
                ]);
                $response = $this->tryProvider($this->fallbackProvider, $messages, $requestOptions);
                if ($response['success']) {
                    $response['used_fallback'] = true;
                }
            }
        }

        if (!$response['success']) {
            return $response;
        }

        $content = $response['content'] ?? '';

        // Cache successful response
        if ($this->cache && !empty($content)) {
            $ttl = $options['cache_ttl'] ?? 3600; // 1 hour default
            $this->cache->set($cacheKey, $content, $ttl);
        }

        // Update rate limit counter
        $this->incrementRateLimit();

        return [
            'success' => true,
            'content' => $content,
            'cached' => false,
            'provider' => $response['provider'] ?? $this->defaultProvider,
            'usage' => $response['usage'] ?? null,
            'used_fallback' => $response['used_fallback'] ?? false
        ];
    }

    /**
     * Tenta fazer chamada a um provider específico
     */
    private function tryProvider($provider, array $messages, array $options): array
    {
        try {
            $response = $provider->chat($messages, $options);

            if (isset($response['error']) && $response['error']) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Unknown error',
                    'content' => null,
                    'provider' => $provider->getName()
                ];
            }

            return [
                'success' => true,
                'content' => $response['content'] ?? '',
                'usage' => $response['usage'] ?? null,
                'provider' => $provider->getName()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'content' => null,
                'provider' => $provider->getName()
            ];
        }
    }

    /**
     * Envia prompt e espera resposta JSON
     */
    public function chatJSON(string $prompt, array $options = []): array
    {
        // Add instruction for JSON response
        $jsonPrompt = $prompt . "\n\nIMPORTANTE: Responda APENAS com JSON válido, sem texto adicional, sem markdown, sem ```json.";

        $options['system'] = ($options['system'] ?? '') .
            "\nVocê é um assistente que SEMPRE responde em JSON válido. Nunca inclua texto fora do JSON.";

        $response = $this->chat($jsonPrompt, $options);

        if (!$response['success']) {
            return $response;
        }

        // Parse JSON
        $content = $response['content'];

        // Clean up common issues
        $content = trim($content);
        $content = preg_replace('/^```json\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from response
            if (preg_match('/\{[\s\S]*\}/m', $content, $matches)) {
                $decoded = json_decode($matches[0], true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Failed to parse JSON response: ' . json_last_error_msg(),
                    'content' => $content,
                    'raw_content' => $response['content']
                ];
            }
        }

        return [
            'success' => true,
            'content' => $content,
            'data' => $decoded,
            'cached' => $response['cached'] ?? false,
            'provider' => $response['provider']
        ];
    }

    /**
     * Chat com GPT-4 Vision para análise de imagens
     */
    public function visionChat(string $prompt, $imageInput, array $options = []): array
    {
        try {
            // Rate limiting check
            if (!$this->checkRateLimit()) {
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded. Please wait.',
                    'tokens_used' => 0
                ];
            }

            // Check cache
            $cacheKey = $this->generateCacheKey($prompt . '_vision_' . md5(is_array($imageInput) ? implode('', $imageInput) : $imageInput), $options);
            if ($this->cache && !($options['skip_cache'] ?? false)) {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return [
                        'success' => true,
                        'data' => $cached,
                        'cached' => true,
                        'provider' => $this->defaultProvider,
                        'tokens_used' => 0
                    ];
                }
            }

            // Prepara conteúdo de imagem
            $content = [
                [
                    'type' => 'text',
                    'text' => $prompt
                ]
            ];

            // Adiciona imagens ao conteúdo
            if (is_array($imageInput)) {
                // Múltiplas imagens
                foreach ($imageInput as $imageUrl) {
                    $imageData = $this->prepareImageData($imageUrl);
                    if ($imageData) {
                        $content[] = $imageData;
                    }
                }
            } else {
                // Imagem única
                $imageData = $this->prepareImageData($imageInput);
                if ($imageData) {
                    $content[] = $imageData;
                }
            }

            // Monta as mensagens
            $messages = [];
            if ($options['system']) {
                $messages[] = [
                    'role' => 'system',
                    'content' => $options['system']
                ];
            }
            
            $messages[] = [
                'role' => 'user',
                'content' => $content
            ];

            // Tenta usar OpenAI para Vision (único que suporta no momento)
            $openaiProvider = new OpenAIProvider();
            $response = $this->tryProvider($openaiProvider, $messages, [
                'temperature' => $options['temperature'] ?? $this->config['temperature'],
                'max_tokens' => $options['max_tokens'] ?? $this->config['max_tokens'],
                'model' => 'gpt-4-vision-preview'
            ]);

            if (!$response['success']) {
                return [
                    'success' => false,
                    'error' => $response['error'],
                    'tokens_used' => 0
                ];
            }

            $content = $response['content'] ?? '';

            // Try to parse as JSON if expected
            $data = null;
            if (strpos($prompt, 'JSON') !== false || strpos($prompt, 'json') !== false) {
                // Clean up common issues
                $content = trim($content);
                $content = preg_replace('/^```json\s*/i', '', $content);
                $content = preg_replace('/\s*```$/i', '', $content);
                $content = trim($content);

                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data = $decoded;
                }
            }

            // Se não for JSON, retorna como texto
            if (!$data) {
                $data = ['text_response' => $content];
            }

            // Cache successful response
            if ($this->cache && !empty($content)) {
                $ttl = $options['cache_ttl'] ?? 3600; // 1 hour default
                $this->cache->set($cacheKey, $data, $ttl);
            }

            // Update rate limit counter
            $this->incrementRateLimit();

            return [
                'success' => true,
                'data' => $data,
                'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                'model' => $response['model'] ?? 'gpt-4-vision-preview',
                'cached' => false
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tokens_used' => 0
            ];
        }
    }

    /**
     * Prepara dados da imagem para API Vision
     */
    private function prepareImageData(string $imageUrl): ?array
    {
        try {
            // Verifica se é URL ou base64
            if (filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                // URL - baixa a imagem
                $imageData = $this->downloadImage($imageUrl);
                if (!$imageData) {
                    return null;
                }
                $base64 = base64_encode($imageData);
            } elseif (preg_match('/^data:image\/(\w+);base64,/', $imageUrl)) {
                // Já está em base64
                $base64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageUrl);
            } else {
                // Assume que é base64 puro
                $base64 = $imageUrl;
            }

            // Detecta o tipo de imagem
            $imageInfo = getimagesizefromstring(base64_decode($base64));
            if (!$imageInfo) {
                return null;
            }

            $mimeType = $imageInfo['mime'];
            
            return [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64,{$base64}"
                ]
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Baixa imagem da URL
     */
    private function downloadImage(string $url): ?string
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageAnalyzer/1.0)');
            
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$data) {
                return null;
            }

            return $data;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Verifica se está disponível
     */
    public function isAvailable(): bool
    {
        return $this->provider->isAvailable();
    }

    /**
     * Retorna o nome do provider
     */
    public function getProviderName(): string
    {
        return $this->provider->getName();
    }

    /**
     * Gera cache key
     */
    private function generateCacheKey(string $prompt, array $options): string
    {
        $data = [
            'prompt' => $prompt,
            'provider' => $this->defaultProvider,
            'temperature' => $options['temperature'] ?? $this->config['temperature'],
        ];
        return 'ai_seo_' . md5(json_encode($data));
    }

    /**
     * Verifica rate limit
     */
    private function checkRateLimit(): bool
    {
        $key = $this->defaultProvider . '_' . date('Y-m-d-H-i');
        $count = self::$requestCounts[$key] ?? 0;
        return $count < self::MAX_REQUESTS_PER_MINUTE;
    }

    /**
     * Incrementa contador de rate limit
     */
    private function incrementRateLimit(): void
    {
        $key = $this->defaultProvider . '_' . date('Y-m-d-H-i');
        if (!isset(self::$requestCounts[$key])) {
            // Clean old entries
            $currentMinute = date('Y-m-d-H-i');
            foreach (self::$requestCounts as $k => $v) {
                if ($k !== $currentMinute) {
                    unset(self::$requestCounts[$k]);
                }
            }
            self::$requestCounts[$key] = 0;
        }
        self::$requestCounts[$key]++;
    }
}
