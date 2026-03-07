<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\AI\Core\AIConfigService;
use App\Services\AI\Core\AIProviderManager;
use Exception;

class LLMService
{
    private AIProviderManager $providerManager;
    private LogService $logger;
    private AlertService $alertService;
    private bool $useRealAI;
    private ?string $preferredProvider = null;

    // Model configurations por provider
    private const MODELS = [
        'basic' => [
            'claude' => 'claude-3-haiku-20240307',
            'openai' => 'gpt-4o-mini',
            'gemini' => 'gemini-1.5-flash',
        ],
        'advanced' => [
            'claude' => 'claude-3-5-sonnet-20241022',
            'openai' => 'gpt-4o',
            'gemini' => 'gemini-1.5-pro',
        ],
    ];

    public function __construct()
    {
        $this->logger = new LogService();
        $this->alertService = new AlertService();

        $configService = new AIConfigService();
        $this->seedEnvIfMissing('ANTHROPIC_API_KEY', $configService->getApiKey('anthropic'));
        $this->seedEnvIfMissing('OPENAI_API_KEY', $configService->getApiKey('openai'));

        $this->preferredProvider = $this->normalizeProvider($_ENV['AI_PREFERRED_PROVIDER'] ?? null);
        $this->providerManager = new AIProviderManager();
        $this->useRealAI = $this->providerManager->getPrimaryProvider() !== null;
    }

    /**
     * Gera texto usando LLM
     */
    public function generate(string $prompt, string $system = '', string $complexity = 'basic'): array
    {
        if (!$this->useRealAI) {
            $this->logger->warning('LLM_UNAVAILABLE', 'Nenhuma chave de IA configurada');
            return [
                'success' => false,
                'error' => 'Nenhum provedor de IA configurado',
                'content' => '',
                'model' => null,
                'provider' => null
            ];
        }

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        if ($system !== '') {
            array_unshift($messages, ['role' => 'system', 'content' => $system]);
        }

        $fallbackEnabled = $this->isFallbackEnabled();
        $preferred = $this->resolvePreferredProvider($complexity);
        $providers = $this->resolveProviderOrder($preferred, $fallbackEnabled);

        $lastError = null;

        foreach ($providers as $providerName) {
            $provider = $this->providerManager->getProvider($providerName);
            if (!$provider) {
                continue;
            }

            $options = $this->buildProviderOptions($providerName, $complexity, $system);
            $start = microtime(true);

            $response = $provider->chat($messages, $options);

            if (!isset($response['error'])) {
                $duration = microtime(true) - $start;
                $usage = $response['usage'] ?? ['input_tokens' => 0, 'output_tokens' => 0, 'total_tokens' => 0];
                $model = $response['model'] ?? ($options['model'] ?? 'unknown');

                $this->logUsage($usage, $model, $duration, null, 'generation', $providerName);

                return [
                    'success' => true,
                    'content' => $response['content'] ?? '',
                    'usage' => $usage,
                    'model' => $model,
                    'provider' => $response['provider'] ?? $providerName,
                    'fallback_used' => $providerName !== $preferred,
                ];
            }

            $lastError = $response;
        }

        $errorMsg = $lastError['message'] ?? 'Falha ao gerar resposta no LLM';
        $this->logger->error('LLM_ERROR', ['error' => $errorMsg]);

        if (strpos($errorMsg, 'credit') !== false || strpos($errorMsg, '402') !== false) {
            $this->alertService->createAlert(
                $_SESSION['active_ml_account_id'] ?? null,
                'ai_billing_error',
                [
                    'provider' => $lastError['provider'] ?? ($preferred ?? 'unknown'),
                    'error' => $errorMsg,
                    'action_required' => 'Adicionar créditos na plataforma da IA'
                ]
            );
        }

        return [
            'success' => false,
            'error' => $errorMsg,
            'content' => '',
            'model' => null,
            'provider' => $lastError['provider'] ?? ($preferred ?? 'unknown')
        ];
    }

    private function logUsage(array $usage, string $model, float $duration, ?int $userId = null, string $context = 'generation', string $provider = 'anthropic'): void
    {
        try {
            $db = \App\Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO llm_usage_logs 
                (provider, model, input_tokens, output_tokens, total_tokens, duration_ms, context_type, user_id, created_at)
                VALUES 
                (:provider, :model, :input, :output, :total, :duration, :context, :user, NOW())
            ");
            
            $stmt->execute([
                'provider' => $provider,
                'model' => $model,
                'input' => $usage['input_tokens'] ?? 0,
                'output' => $usage['output_tokens'] ?? 0,
                'total' => ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
                'duration' => round($duration * 1000), // ms
                'context' => $context,
                'user' => $userId ?? ($_SESSION['user_id'] ?? null)
            ]);

            $this->logger->info('LLM_USAGE_RECORDED', "Usage persisted for {$model}");
            
        } catch (Exception $e) {
            // Fallback log if DB fails
            $this->logger->error('LLM_USAGE_DB_ERROR', $e->getMessage());
        }
    }

    private function seedEnvIfMissing(string $key, ?string $value): void
    {
        if (!empty($value) && empty($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
    }

    private function normalizeProvider(?string $provider): ?string
    {
        if (!$provider) {
            return null;
        }

        $provider = strtolower(trim($provider));

        return match ($provider) {
            'anthropic', 'claude' => 'claude',
            'openai' => 'openai',
            'google', 'gemini' => 'gemini',
            default => null,
        };
    }

    private function resolvePreferredProvider(string $complexity): ?string
    {
        if ($this->preferredProvider) {
            return $this->preferredProvider;
        }

        $default = array_key_first(self::MODELS[$complexity] ?? self::MODELS['basic']);
        return $default ?: null;
    }

    private function resolveProviderOrder(?string $preferred, bool $fallbackEnabled): array
    {
        $available = array_keys($this->providerManager->getAvailableProviders());
        if (empty($available)) {
            return [];
        }

        $preferred = $preferred && in_array($preferred, $available, true) ? $preferred : $available[0];
        $order = [$preferred];

        if ($fallbackEnabled) {
            foreach ($available as $provider) {
                if ($provider !== $preferred) {
                    $order[] = $provider;
                }
            }
        }

        return $order;
    }

    private function buildProviderOptions(string $provider, string $complexity, string $system): array
    {
        $models = self::MODELS[$complexity] ?? self::MODELS['basic'];
        $model = $models[$provider] ?? null;

        return array_filter([
            'model' => $model,
            'system' => $system ?: null,
            'max_tokens' => 2000,
            'temperature' => 0.7,
        ], fn($value) => $value !== null && $value !== '');
    }

    private function isFallbackEnabled(): bool
    {
        $value = $_ENV['AI_FALLBACK_ENABLED'] ?? true;
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
    /**
     * Analisa sentimento e intenção de uma pergunta
     */
    public function analyzeSentiment(string $text, string $context = ''): array
    {
        $systemPrompt = "Você é um especialista em análise de atendimento ao cliente para E-commerce. " .
                       "Analise a pergunta do cliente e retorne APENAS um JSON válido com os seguintes campos:\n" .
                       "- sentiment: 'positive', 'neutral', 'negative', ou 'angry'\n" .
                       "- intent: 'shipping', 'technical', 'price', 'stock', 'warranty', 'other'\n" .
                       "- urgency: número de 0 a 100 (onde 100 é gravíssimo/urgente)\n" .
                       "- reasoning: breve explicação (máx 10 palavras)\n\n" .
                       "Exemplo: {\"sentiment\": \"negative\", \"intent\": \"shipping\", \"urgency\": 80, \"reasoning\": \"Cliente reclamou de atraso na entrega\"}";

        $userPrompt = "Contexto do Produto: $context\n\nPergunta: $text";

        try {
            $result = $this->generate($userPrompt, $systemPrompt, 'basic');
            
            // Extrair JSON da resposta (caso venha com texto extra)
            $content = $result['content'];
            $start = strpos($content, '{');
            $end = strrpos($content, '}');
            
            if ($start !== false && $end !== false) {
                $jsonStr = substr($content, $start, $end - $start + 1);
                $data = json_decode($jsonStr, true);
                if ($data) return $data;
            }
            
            return [
                'sentiment' => null,
                'intent' => null,
                'urgency' => null,
                'reasoning' => 'Falha ao analisar JSON da IA',
                'error' => $result['error'] ?? 'Resposta inválida da IA'
            ];
            
        } catch (\Exception $e) {
            log_error('Falha na análise de sentimento via LLM', [
                'error' => $e->getMessage(),
            ]);
            return [
                'sentiment' => null,
                'intent' => null,
                'urgency' => null,
                'reasoning' => 'Erro na API',
                'error' => $e->getMessage()
            ];
        }
    }
}
