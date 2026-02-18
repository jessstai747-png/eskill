<?php

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

/**
 * AI Insights Service - GPT-4 Powered Intelligence
 * 
 * Fornece análises avançadas em linguagem natural usando GPT-4:
 * - Insights estratégicos personalizados
 * - Recomendações de ações prioritárias
 * - Análise de tendências e padrões
 * - Sugestões de A/B tests
 * - Explicações de métricas complexas
 * 
 * @package App\Services\AI\SEO
 * @version 2.0.0
 * @since 2025-12-31
 */
class AIInsightsService
{
    private PDO $db;
    private int $accountId;
    private string $apiKey;
    private string $apiEndpoint = 'https://api.openai.com/v1/chat/completions';
    private string $model = 'gpt-4o';

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;

        $this->apiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? getenv('OPENAI_API_KEY') ?? '';

        if (empty($this->apiKey)) {
            // Manual .env parsing fallback
            $envPath = __DIR__ . '/../../../../.env';
            if (file_exists($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode('=', $line, 2);
                        if (trim($name) === 'OPENAI_API_KEY') {
                            $this->apiKey = trim(trim($value), '"\'');
                            break;
                        }
                    }
                }
            }
        }

        if (empty($this->apiKey)) {
            $logMsg = date('[Y-m-d H:i:s] ') . "AIInsightsService: OpenAI API key not configured. ENV keys: " . implode(',', array_keys($_ENV)) . "\n";
            file_put_contents(__DIR__ . '/../../../../storage/logs/ai_debug.log', $logMsg, FILE_APPEND);
            log_warning('AIInsightsService: OpenAI API key não configurada', [
                'service' => 'AIInsightsService',
            ]);
            // We don't throw here to allow other services to work if this one fails
            // throw new \Exception('OpenAI API key not configured');
        }
    }

    /**
     * Gera insights estratégicos completos para a conta
     * 
     * @param array $options Opções de personalização
     * @return array Insights estruturados
     */
    public function generateStrategicInsights(array $options = []): array
    {
        // Coletar dados da conta
        $accountData = $this->getAccountData();

        // Preparar contexto para GPT
        $context = $this->prepareContext($accountData);

        // Prompt engenheirado para insights estratégicos
        $prompt = $this->buildStrategicPrompt($context, $options);

        // Chamar GPT-4
        $response = $this->callGPT4($prompt);

        // Parsear e estruturar resposta
        $insights = $this->parseInsights($response);

        // Salvar no histórico
        $this->saveInsightHistory('strategic', $insights);

        return [
            'type' => 'strategic',
            'generated_at' => date('Y-m-d H:i:s'),
            'insights' => $insights,
            'confidence' => $this->calculateConfidence($accountData),
            'actionable_items' => $this->extractActionableItems($insights),
            'priority_score' => $this->calculatePriorityScore($insights)
        ];
    }

    /**
     * Recomenda testes A/B baseado em dados históricos
     * 
     * @param string $focusArea Área de foco: all|title|description|price|images
     * @return array Sugestões de testes A/B
     */
    public function suggestABTests(string $focusArea = 'all'): array
    {
        $focusArea = strtolower(trim($focusArea));
        $allowedFocusAreas = ['all', 'title', 'description', 'price', 'images'];
        if (!in_array($focusArea, $allowedFocusAreas, true)) {
            $focusArea = 'all';
        }

        // Análise de performance atual
        $performance = $this->getPerformanceMetrics();

        // Identificar oportunidades
        $opportunities = $this->identifyTestOpportunities($performance);

        // Filtrar oportunidades por área de foco (mantém 'all' como padrão)
        if ($focusArea !== 'all') {
            $opportunities = array_values(array_filter(
                $opportunities,
                static function (array $opportunity) use ($focusArea): bool {
                    $type = strtolower((string)($opportunity['type'] ?? ''));

                    $normalized = match (true) {
                        str_contains($type, 'title') => 'title',
                        str_contains($type, 'desc') => 'description',
                        str_contains($type, 'image'), str_contains($type, 'photo') => 'images',
                        str_contains($type, 'price'), str_contains($type, 'pricing') => 'price',
                        default => $type,
                    };

                    return $normalized === $focusArea;
                }
            ));
        }

        // Prompt para GPT sugerir testes
        $prompt = $this->buildABTestPrompt($performance, $opportunities, $focusArea);

        $response = $this->callGPT4($prompt);

        $tests = $this->parseABTestSuggestions($response);

        return [
            'suggested_tests' => $tests,
            'total_suggestions' => count($tests),
            'estimated_impact' => $this->estimateImpact($tests),
            'recommended_duration' => $this->calculateTestDuration($performance)
        ];
    }

    /**
     * Analisa tendências e padrões nos dados
     * 
     * @param int $days Período de análise
     * @return array Tendências identificadas
     */
    public function analyzeTrends(int $days = 30): array
    {
        // Coletar dados históricos
        $historicalData = $this->getHistoricalData($days);

        if (empty($historicalData)) {
            return $this->buildEmptyTrendsResponse($days);
        }

        // Detectar padrões
        $patterns = $this->detectPatterns($historicalData);

        // Prompt para análise de tendências
        $prompt = $this->buildTrendsPrompt($historicalData, $patterns);

        $response = $this->callGPT4($prompt);

        $trends = $this->parseTrends($response);

        return [
            'period_days' => $days,
            'trends' => $trends,
            'patterns_detected' => count($patterns),
            'forecast' => $this->generateForecast($trends, $historicalData),
            'anomalies' => $this->detectAnomalies($historicalData)
        ];
    }

    /**
     * Explica métricas complexas em linguagem simples
     * 
     * @param string $metric Nome da métrica
     * @param mixed $value Valor atual
     * @param array $context Contexto adicional
     * @return array Explicação detalhada
     */
    public function explainMetric(string $metric, $value, array $context = []): array
    {
        $prompt = $this->buildExplanationPrompt($metric, $value, $context);

        $response = $this->callGPT4($prompt);

        return [
            'metric' => $metric,
            'value' => $value,
            'explanation' => $response,
            'benchmark' => $this->getBenchmark($metric),
            'is_good' => $this->evaluateMetric($metric, $value),
            'improvement_tips' => $this->extractImprovementTips($response)
        ];
    }

    /**
     * Recomendações prioritizadas de ações
     * 
     * @param int $limit Número máximo de recomendações
     * @return array Lista de ações recomendadas
     */
    public function getPrioritizedRecommendations(int $limit = 10): array
    {
        // Análise completa do estado atual
        $analysis = $this->getComprehensiveAnalysis();

        // Prompt para recomendações
        $prompt = $this->buildRecommendationsPrompt($analysis);

        $response = $this->callGPT4($prompt);

        $recommendations = $this->parseRecommendations($response);

        // Priorizar baseado em impacto e esforço
        $prioritized = $this->prioritizeRecommendations($recommendations);

        return [
            'recommendations' => array_slice($prioritized, 0, $limit),
            'total_identified' => count($recommendations),
            'quick_wins' => $this->filterQuickWins($prioritized),
            'long_term' => $this->filterLongTerm($prioritized)
        ];
    }

    /**
     * Análise de sentimento do mercado
     * 
     * @return array Análise de sentimento
     */
    public function analyzeMarketSentiment(): array
    {
        // Dados de competidores
        $competitorData = $this->getCompetitorData();

        // Mudanças recentes
        $recentChanges = $this->getRecentMarketChanges();

        $prompt = $this->buildSentimentPrompt($competitorData, $recentChanges);

        $response = $this->callGPT4($prompt);

        return [
            'sentiment' => $this->extractSentiment($response),
            'confidence' => $this->calculateSentimentConfidence($response),
            'factors' => $this->extractFactors($response),
            'recommendation' => $this->extractRecommendation($response),
            'market_conditions' => $this->describeMarketConditions($competitorData)
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getAccountData(): array
    {
        // Query para métricas de otimização
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT item_id) as total_items,
                COUNT(id) as total_optimizations,
                AVG(score_before) as avg_score_before,
                AVG(score_after) as avg_score_after,
                AVG(score_improvement) as avg_improvement,
                SUM(views_increase) as total_views_increase,
                SUM(sales_increase) as total_sales_increase
            FROM seo_optimizations
            WHERE account_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$this->accountId]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_items' => 0,
            'total_optimizations' => 0,
            'avg_score_before' => 0,
            'avg_score_after' => 0,
            'avg_improvement' => 0,
            'total_views_increase' => 0,
            'total_sales_increase' => 0
        ];

        // Query separada para watchlist (usando tabela correta)
        try {
            $stmt2 = $this->db->prepare("SELECT COUNT(*) as cnt FROM competitor_tracking WHERE account_id = ?");
            $stmt2->execute([$this->accountId]);
            $metrics['watchlist_count'] = $stmt2->fetchColumn() ?: 0;
        } catch (\Exception $e) {
            $metrics['watchlist_count'] = 0;
        }

        // Query separada para alertas não lidos (usando tabela correta com join)
        try {
            $stmt3 = $this->db->prepare("
                SELECT COUNT(*) as cnt 
                FROM competitor_alerts ca
                JOIN competitor_tracking ct ON ct.id = ca.tracking_id
                WHERE ct.account_id = ? AND ca.is_read = 0
            ");
            $stmt3->execute([$this->accountId]);
            $metrics['unread_alerts'] = $stmt3->fetchColumn() ?: 0;
        } catch (\Exception $e) {
            $metrics['unread_alerts'] = 0;
        }

        return $metrics;
    }

    private function prepareContext(array $data): string
    {
        return json_encode([
            'account_id' => $this->accountId,
            'period' => 'last_30_days',
            'metrics' => $data,
            'timestamp' => time()
        ], JSON_PRETTY_PRINT);
    }

    private function buildStrategicPrompt(string $context, array $options): string
    {
        $focus = $options['focus'] ?? 'general';

        return <<<PROMPT
You are an expert Mercado Livre marketplace strategist. Analyze the following account data and provide strategic insights.

ACCOUNT DATA:
$context

TASK: Provide strategic insights in the following areas:
1. Overall performance assessment (strengths and weaknesses)
2. Top 3 opportunities for growth
3. Competitive positioning
4. Risk factors to monitor
5. Recommended next steps (prioritized by impact)

FOCUS: $focus

Respond in JSON format with the following structure:
{
  "overall_assessment": "string",
  "strengths": ["string"],
  "weaknesses": ["string"],
  "opportunities": [{"title": "string", "description": "string", "impact": "high|medium|low"}],
  "risks": ["string"],
  "next_steps": [{"action": "string", "priority": "high|medium|low", "effort": "low|medium|high"}]
}
PROMPT;
    }

    private function buildABTestPrompt(array $performance, array $opportunities, string $focusArea = 'all'): string
    {
        $perfJson = json_encode($performance, JSON_PRETTY_PRINT);
        $oppJson = json_encode($opportunities, JSON_PRETTY_PRINT);
        $focusArea = strtolower(trim($focusArea));

        return <<<PROMPT
You are an A/B testing expert for e-commerce. Based on the performance data and opportunities, suggest A/B tests.

FOCUS AREA:
$focusArea

IMPORTANT:
- If FOCUS AREA is not "all", ONLY propose tests within that area.
- If the OPPORTUNITIES list is empty for the focus area, still propose high-impact tests within the focus area using PERFORMANCE DATA.

PERFORMANCE DATA:
$perfJson

OPPORTUNITIES:
$oppJson

Suggest 3-5 A/B tests that would have the highest impact. For each test, provide:
1. Test name
2. What to test (variant A vs B)
3. Expected impact
4. Recommended duration
5. Success metrics

Respond in JSON format with array of test objects.
PROMPT;
    }

    private function buildTrendsPrompt(array $data, array $patterns): string
    {
        $dataJson = json_encode($data, JSON_PRETTY_PRINT);
        $patternsJson = json_encode($patterns, JSON_PRETTY_PRINT);

        return <<<PROMPT
Analyze the following historical data and identify key trends.

HISTORICAL DATA:
$dataJson

DETECTED PATTERNS:
$patternsJson

Identify:
1. Rising trends (opportunities)
2. Declining trends (risks)
3. Seasonal patterns
4. Anomalies
5. Forecast for next 30 days

Respond in JSON format.
PROMPT;
    }

    private function buildExplanationPrompt(string $metric, $value, array $context): string
    {
        $contextJson = json_encode($context, JSON_PRETTY_PRINT);

        return <<<PROMPT
Explain the metric "$metric" with value "$value" in simple, actionable terms for a Mercado Livre seller.

CONTEXT:
$contextJson

Provide:
1. What this metric means
2. Whether this value is good, average, or needs improvement
3. Why it matters
4. 3 specific actions to improve it

Be concise and practical. Use analogies if helpful.
PROMPT;
    }

    private function buildRecommendationsPrompt(array $analysis): string
    {
        $analysisJson = json_encode($analysis, JSON_PRETTY_PRINT);

        return <<<PROMPT
Based on comprehensive account analysis, provide prioritized recommendations.

ANALYSIS:
$analysisJson

Provide 10 actionable recommendations with:
1. Action title
2. Description
3. Expected impact (high/medium/low)
4. Effort required (low/medium/high)
5. Category (seo, pricing, inventory, marketing, etc)

Prioritize by impact vs effort ratio. Respond in JSON format.
PROMPT;
    }

    private function buildSentimentPrompt(array $competitorData, array $changes): string
    {
        $compJson = json_encode($competitorData, JSON_PRETTY_PRINT);
        $changesJson = json_encode($changes, JSON_PRETTY_PRINT);

        return <<<PROMPT
Analyze market sentiment based on competitor behavior and recent changes.

COMPETITOR DATA:
$compJson

RECENT CHANGES:
$changesJson

Determine:
1. Market sentiment (bullish/bearish/neutral)
2. Key factors influencing sentiment
3. Strategic recommendation
4. Risk level

Respond in JSON format.
PROMPT;
    }

    /**
     * Chama a API GPT-4 com retry
     */
    private function callGPT4(string $prompt): string
    {
        // Cache key baseada no hash do prompt
        $cacheKey = 'gpt4_' . md5($prompt);
        $cachePath = __DIR__ . '/../../../../storage/cache/' . $cacheKey . '.json';

        // Verificar cache (válido por 1 hora)
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
            $cached = file_get_contents($cachePath);
            if ($cached) {
                return $cached;
            }
        }

        // Cache miss: from here on we need a valid key (avoid calling OpenAI with empty key)
        if (empty(trim($this->apiKey))) {
            log_error('AIInsightsService: OPENAI_API_KEY ausente/ inválida - abortando chamada OpenAI', [
                'service' => 'AIInsightsService',
            ]);
            throw new \RuntimeException(
                'OpenAI API key is missing or invalid. Configure OPENAI_API_KEY to enable AI insights.',
                503
            );
        }

        $data = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert e-commerce analyst specializing in Mercado Livre marketplace optimization. Always respond in valid JSON format.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 2000
        ];

        $ch = curl_init($this->apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            log_error('AIInsightsService: erro na API GPT-4', [
                'service' => 'AIInsightsService',
                'http_code' => $httpCode,
                'response_preview' => substr((string)$response, 0, 300),
            ]);

            if (in_array((int)$httpCode, [401, 403], true)) {
                throw new \RuntimeException(
                    'OpenAI API key is missing or invalid. Configure OPENAI_API_KEY to enable AI insights.',
                    503
                );
            }

            throw new \Exception("GPT-4 API error: HTTP $httpCode");
        }

        $decoded = json_decode($response, true);

        if (!isset($decoded['choices'][0]['message']['content'])) {
            throw new \Exception('Invalid GPT-4 response format');
        }

        $content = $decoded['choices'][0]['message']['content'];

        // Salvar no cache
        @file_put_contents($cachePath, $content);

        return $content;
    }

    private function parseInsights(string $response): array
    {
        // Tentar parsear como JSON primeiro
        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Fallback: extrair insights como texto
        return [
            'raw_response' => $response,
            'parsed' => false
        ];
    }

    private function parseABTestSuggestions(string $response): array
    {
        $decoded = json_decode($response, true);
        return $decoded ?? ['tests' => []];
    }

    private function parseTrends(string $response): array
    {
        $decoded = json_decode($response, true);
        return $decoded ?? ['trends' => []];
    }

    private function parseRecommendations(string $response): array
    {
        $decoded = json_decode($response, true);
        return $decoded['recommendations'] ?? [];
    }

    private function extractActionableItems(array $insights): array
    {
        $items = [];

        if (isset($insights['next_steps']) && is_array($insights['next_steps'])) {
            foreach ($insights['next_steps'] as $step) {
                if (isset($step['action'])) {
                    $items[] = $step;
                }
            }
        }

        return $items;
    }

    private function calculateConfidence(array $data): float
    {
        // Confiança baseada na quantidade de dados
        $optimizations = $data['total_optimizations'] ?? 0;

        if ($optimizations >= 100) return 0.95;
        if ($optimizations >= 50) return 0.85;
        if ($optimizations >= 20) return 0.75;
        if ($optimizations >= 10) return 0.65;

        return 0.50;
    }

    private function calculatePriorityScore(array $insights): int
    {
        $score = 0;

        // Pontuação baseada em oportunidades de alto impacto
        if (isset($insights['opportunities'])) {
            foreach ($insights['opportunities'] as $opp) {
                if (($opp['impact'] ?? '') === 'high') {
                    $score += 10;
                } elseif (($opp['impact'] ?? '') === 'medium') {
                    $score += 5;
                }
            }
        }

        return min($score, 100);
    }

    private function saveInsightHistory(string $type, array $insights): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO ai_insights_history (account_id, type, insights, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $this->accountId,
            $type,
            json_encode($insights)
        ]);
    }

    private function getPerformanceMetrics(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                optimization_type,
                AVG(score_improvement) as avg_improvement,
                COUNT(*) as count
            FROM seo_optimizations
            WHERE account_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY optimization_type
        ");
        $stmt->execute([$this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function identifyTestOpportunities(array $performance): array
    {
        $opportunities = [];

        foreach ($performance as $metric) {
            if ($metric['avg_improvement'] < 15) {
                $opportunities[] = [
                    'type' => $metric['optimization_type'],
                    'current_performance' => $metric['avg_improvement'],
                    'reason' => 'Below expected improvement rate'
                ];
            }
        }

        return $opportunities;
    }

    private function estimateImpact(array $tests): array
    {
        $impact = ['high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($tests as $test) {
            $level = $test['expected_impact'] ?? 'medium';
            $impact[$level] = ($impact[$level] ?? 0) + 1;
        }

        return $impact;
    }

    private function calculateTestDuration(array $performance): int
    {
        // Baseado no volume de tráfego
        $totalCount = array_sum(array_column($performance, 'count'));

        if ($totalCount > 1000) return 7;  // 1 semana
        if ($totalCount > 500) return 14;   // 2 semanas

        return 30; // 1 mês
    }

    private function getHistoricalData(int $days): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as optimizations,
                AVG(score_improvement) as avg_improvement,
                SUM(views_increase) as views,
                SUM(sales_increase) as sales
            FROM seo_optimizations
            WHERE account_id = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$this->accountId, $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function detectPatterns(array $data): array
    {
        $patterns = [];

        // Detectar tendência de crescimento
        if (count($data) >= 7) {
            $first7 = array_slice($data, 0, 7);
            $last7 = array_slice($data, -7);

            $avgFirst = array_sum(array_column($first7, 'optimizations')) / 7;
            $avgLast = array_sum(array_column($last7, 'optimizations')) / 7;

            if ($avgLast > $avgFirst * 1.2) {
                $patterns[] = ['type' => 'growth', 'strength' => 'strong'];
            } elseif ($avgLast < $avgFirst * 0.8) {
                $patterns[] = ['type' => 'decline', 'strength' => 'strong'];
            }
        }

        return $patterns;
    }

    private function generateForecast(array $trends, array $historical): array
    {
        // Forecast simples baseado em média móvel
        $values = array_column($historical, 'optimizations');
        if (empty($values)) {
            return $this->buildZeroForecast('Forecast indisponível por falta de dados históricos suficientes.');
        }

        $avg = array_sum($values) / count($values);

        return [
            'next_7_days' => round($avg * 7),
            'next_30_days' => round($avg * 30),
            'confidence' => 0.70
        ];
    }

    private function buildZeroForecast(string $reason): array
    {
        return [
            'next_7_days' => 0,
            'next_30_days' => 0,
            'confidence' => 0.35,
            'note' => $reason
        ];
    }

    private function detectAnomalies(array $data): array
    {
        $anomalies = [];
        $values = array_column($data, 'optimizations');

        if (empty($values)) return $anomalies;

        $mean = array_sum($values) / count($values);
        $stdDev = $this->calculateStdDev($values, $mean);

        foreach ($data as $point) {
            $value = $point['optimizations'];
            if (abs($value - $mean) > 2 * $stdDev) {
                $anomalies[] = [
                    'date' => $point['date'],
                    'value' => $value,
                    'deviation' => round(($value - $mean) / $stdDev, 2)
                ];
            }
        }

        return $anomalies;
    }

    private function buildEmptyTrendsResponse(int $days): array
    {
        return [
            'period_days' => $days,
            'trends' => [
                'rising' => [],
                'declining' => [],
                'seasonal' => [],
                'message' => 'Nenhum dado histórico disponível para analisar os últimos ' . $days . ' dias.'
            ],
            'patterns_detected' => 0,
            'forecast' => $this->buildZeroForecast('Ainda não há volume suficiente de otimizações para gerar previsões confiáveis.'),
            'anomalies' => []
        ];
    }

    private function calculateStdDev(array $values, float $mean): float
    {
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        return sqrt($variance);
    }

    private function getBenchmark(string $metric): array
    {
        // Tentar carregar benchmarks calculados do banco
        try {
            $stmt = $this->db->prepare("
                SELECT metric_name, low_threshold, good_threshold, excellent_threshold
                FROM seo_benchmarks
                WHERE metric_name = :metric
                LIMIT 1
            ");
            $stmt->execute(['metric' => $metric]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'low' => (float) $row['low_threshold'],
                    'good' => (float) $row['good_threshold'],
                    'excellent' => (float) $row['excellent_threshold'],
                ];
            }
        } catch (\Exception $e) {
            // Tabela pode não existir — usar defaults
        }

        // Defaults baseados em médias reais de e-commerce BR (Mercado Livre)
        $defaults = [
            'score_improvement' => ['low' => 10, 'good' => 20, 'excellent' => 30],
            'conversion_rate' => ['low' => 1, 'good' => 3, 'excellent' => 5],
            'views_increase' => ['low' => 50, 'good' => 200, 'excellent' => 500],
            'click_through_rate' => ['low' => 1, 'good' => 5, 'excellent' => 10],
            'seo_score' => ['low' => 40, 'good' => 70, 'excellent' => 90],
            'title_score' => ['low' => 30, 'good' => 60, 'excellent' => 85],
            'image_score' => ['low' => 20, 'good' => 60, 'excellent' => 90],
        ];

        return $defaults[$metric] ?? ['low' => 0, 'good' => 50, 'excellent' => 100];
    }

    private function evaluateMetric(string $metric, $value): bool
    {
        $benchmark = $this->getBenchmark($metric);
        return $value >= $benchmark['good'];
    }

    private function extractImprovementTips(string $response): array
    {
        // Extrair dicas da resposta GPT
        preg_match_all('/\d+\.\s+(.+?)(?:\n|$)/s', $response, $matches);
        return $matches[1] ?? [];
    }

    private function getComprehensiveAnalysis(): array
    {
        return [
            'account_data' => $this->getAccountData(),
            'performance' => $this->getPerformanceMetrics(),
            'competitors' => $this->getCompetitorData(),
            'recent_changes' => $this->getRecentMarketChanges()
        ];
    }

    private function prioritizeRecommendations(array $recommendations): array
    {
        usort($recommendations, function ($a, $b) {
            $scoreA = $this->getRecommendationScore($a);
            $scoreB = $this->getRecommendationScore($b);
            return $scoreB <=> $scoreA;
        });

        return $recommendations;
    }

    private function getRecommendationScore(array $rec): int
    {
        $impactScores = ['high' => 10, 'medium' => 5, 'low' => 2];
        $effortScores = ['low' => 10, 'medium' => 5, 'high' => 2];

        $impact = $impactScores[$rec['impact'] ?? 'medium'] ?? 5;
        $effort = $effortScores[$rec['effort'] ?? 'medium'] ?? 5;

        return $impact * $effort;
    }

    private function filterQuickWins(array $recommendations): array
    {
        return array_filter($recommendations, function ($rec) {
            return ($rec['impact'] ?? '') === 'high' && ($rec['effort'] ?? '') === 'low';
        });
    }

    private function filterLongTerm(array $recommendations): array
    {
        return array_filter($recommendations, function ($rec) {
            return ($rec['effort'] ?? '') === 'high';
        });
    }

    private function getCompetitorData(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT competitor_item_id) as total_competitors,
                    COUNT(*) as total_tracked
                FROM competitor_tracking
                WHERE account_id = ? AND is_active = 1
            ");
            $stmt->execute([$this->accountId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Contar alertas recentes
            $stmt2 = $this->db->prepare("
                SELECT COUNT(*) as total_changes
                FROM competitor_alerts ca
                JOIN competitor_tracking ct ON ct.id = ca.tracking_id
                WHERE ct.account_id = ?
                  AND ca.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt2->execute([$this->accountId]);
            $data['total_changes'] = $stmt2->fetchColumn() ?: 0;
            $data['price_change_rate'] = 0;

            return $data;
        } catch (\Exception $e) {
            return ['total_competitors' => 0, 'total_changes' => 0, 'price_change_rate' => 0];
        }
    }

    private function getRecentMarketChanges(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    ca.type as field,
                    COUNT(*) as changes
                FROM competitor_alerts ca
                JOIN competitor_tracking ct ON ct.id = ca.tracking_id
                WHERE ct.account_id = ?
                  AND ca.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY ca.type
            ");
            $stmt->execute([$this->accountId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function extractSentiment(string $response): string
    {
        $decoded = json_decode($response, true);
        return $decoded['sentiment'] ?? 'neutral';
    }

    private function calculateSentimentConfidence(string $response): float
    {
        $decoded = json_decode($response, true);
        return $decoded['confidence'] ?? 0.7;
    }

    private function extractFactors(string $response): array
    {
        $decoded = json_decode($response, true);
        return $decoded['factors'] ?? [];
    }

    private function extractRecommendation(string $response): string
    {
        $decoded = json_decode($response, true);
        return $decoded['recommendation'] ?? 'Monitor market conditions';
    }

    private function describeMarketConditions(array $data): string
    {
        $competitors = $data['total_competitors'] ?? 0;
        $changes = $data['total_changes'] ?? 0;

        if ($changes > 50) return 'highly_dynamic';
        if ($changes > 20) return 'moderately_active';
        if ($competitors > 10) return 'competitive';

        return 'stable';
    }
}
