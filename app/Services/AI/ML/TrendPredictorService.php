<?php

declare(strict_types=1);

namespace App\Services\AI\ML;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\AI\Core\AIProviderManager;
use PDO;

/**
 * 📈 Trend Predictor Service
 * 
 * Prediz tendências de busca e sazonalidade:
 * - Análise de tendências históricas do ML
 * - Previsão de demanda sazonal
 * - Detecção de keywords em ascensão
 * - Alertas de oportunidades
 */
class TrendPredictorService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient;
    private ?AIProviderManager $aiProvider;

    // Meses em português
    private const MONTHS_PT = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    // Eventos sazonais conhecidos
    private const SEASONAL_EVENTS = [
        ['name' => 'Carnaval', 'month' => 2, 'categories' => ['fantasia', 'roupa', 'acessório', 'bebida']],
        ['name' => 'Páscoa', 'month' => 4, 'categories' => ['chocolate', 'doce', 'decoração']],
        ['name' => 'Dia das Mães', 'month' => 5, 'categories' => ['presente', 'perfume', 'joia', 'roupa']],
        ['name' => 'Dia dos Namorados', 'month' => 6, 'categories' => ['presente', 'perfume', 'joia', 'lingerie']],
        ['name' => 'Dia dos Pais', 'month' => 8, 'categories' => ['ferramenta', 'eletrônico', 'roupa masculina']],
        ['name' => 'Dia das Crianças', 'month' => 10, 'categories' => ['brinquedo', 'jogo', 'roupa infantil']],
        ['name' => 'Black Friday', 'month' => 11, 'categories' => ['*']],
        ['name' => 'Natal', 'month' => 12, 'categories' => ['presente', 'decoração', 'roupa', 'brinquedo']],
        ['name' => 'Volta às Aulas', 'month' => 1, 'categories' => ['escolar', 'mochila', 'caderno', 'uniforme']],
        ['name' => 'Inverno', 'month' => 6, 'categories' => ['cobertor', 'aquecedor', 'roupa inverno', 'casaco']],
        ['name' => 'Verão', 'month' => 12, 'categories' => ['praia', 'piscina', 'biquini', 'ventilador', 'ar condicionado']],
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->aiProvider = new AIProviderManager();
    }

    /**
     * 📊 Prever tendência para keyword
     */
    public function predictTrend(string $keyword, ?string $categoryId = null): array
    {
        try {
            // 1. Buscar dados históricos (se disponíveis)
            $historical = $this->getHistoricalData($keyword);

            // 2. Buscar tendência atual do ML
            $currentTrend = $this->fetchMLTrend($keyword, $categoryId);

            // 3. Analisar sazonalidade
            $seasonality = $this->analyzeSeasonality($keyword, $categoryId);

            // 4. Calcular previsão
            $prediction = $this->calculatePrediction($keyword, $historical, $currentTrend, $seasonality);

            // 5. Salvar dados para histórico
            $this->saveHistoricalData($keyword, $currentTrend);

            return [
                'success' => true,
                'keyword' => $keyword,
                'current_trend' => $currentTrend,
                'seasonality' => $seasonality,
                'prediction' => $prediction,
                'historical_available' => !empty($historical),
                'analyzed_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔍 Buscar tendência atual do Mercado Livre
     */
    private function fetchMLTrend(string $keyword, ?string $categoryId = null): array
    {
        try {
            $params = ['q' => $keyword, 'limit' => 50];
            if ($categoryId) {
                $params['category'] = $categoryId;
            }

            $response = $this->mlClient->get('/sites/MLB/search', $params);

            $totalResults = $response['paging']['total'] ?? 0;
            $items = $response['results'] ?? [];

            // Analisar itens retornados
            $avgPrice = 0;
            $totalSold = 0;
            $newListings = 0;
            $fullListings = 0;

            foreach ($items as $item) {
                $avgPrice += $item['price'] ?? 0;
                $totalSold += $item['sold_quantity'] ?? 0;

                // Verificar se é listagem recente (últimos 7 dias)
                $dateCreated = $item['date_created'] ?? null;
                if ($dateCreated) {
                    $created = strtotime($dateCreated);
                    if ($created > strtotime('-7 days')) {
                        $newListings++;
                    }
                }

                // Verificar Full
                if (($item['shipping']['logistic_type'] ?? '') === 'fulfillment') {
                    $fullListings++;
                }
            }

            $itemCount = count($items);
            $avgPrice = $itemCount > 0 ? $avgPrice / $itemCount : 0;

            // Calcular indicadores de tendência
            $trendScore = $this->calculateTrendScore($totalResults, $totalSold, $newListings, $itemCount);

            return [
                'total_results' => $totalResults,
                'avg_price' => round($avgPrice, 2),
                'total_sold_sample' => $totalSold,
                'new_listings_7d' => $newListings,
                'full_percentage' => $itemCount > 0 ? round(($fullListings / $itemCount) * 100) : 0,
                'trend_score' => $trendScore,
                'trend_direction' => $trendScore > 0.6 ? 'up' : ($trendScore < 0.4 ? 'down' : 'stable'),
            ];
        } catch (\Exception $e) {
            return [
                'total_results' => 0,
                'trend_score' => 0.5,
                'trend_direction' => 'unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 📈 Calcular score de tendência
     */
    private function calculateTrendScore(int $totalResults, int $totalSold, int $newListings, int $sampleSize): float
    {
        $score = 0.5; // Base neutra

        // Fator: Volume de resultados
        if ($totalResults > 10000) {
            $score += 0.15;
        } elseif ($totalResults > 1000) {
            $score += 0.1;
        } elseif ($totalResults < 100) {
            $score -= 0.1;
        }

        // Fator: Vendas na amostra
        $avgSoldPerItem = $sampleSize > 0 ? $totalSold / $sampleSize : 0;
        if ($avgSoldPerItem > 100) {
            $score += 0.2;
        } elseif ($avgSoldPerItem > 50) {
            $score += 0.1;
        } elseif ($avgSoldPerItem < 10) {
            $score -= 0.1;
        }

        // Fator: Novas listagens (indica interesse de vendedores)
        $newPercentage = $sampleSize > 0 ? ($newListings / $sampleSize) * 100 : 0;
        if ($newPercentage > 20) {
            $score += 0.15;
        } elseif ($newPercentage > 10) {
            $score += 0.05;
        }

        return max(0, min(1, $score));
    }

    /**
     * 📅 Analisar sazonalidade
     */
    public function analyzeSeasonality(string $keyword, ?string $categoryId = null): array
    {
        $currentMonth = (int) date('n');
        $keyword = mb_strtolower($keyword);

        $seasonalFactors = [];
        $upcomingEvents = [];

        // Verificar eventos sazonais
        foreach (self::SEASONAL_EVENTS as $event) {
            $isRelevant = false;

            // Verificar se keyword é relevante para o evento
            foreach ($event['categories'] as $category) {
                if ($category === '*' || str_contains($keyword, $category)) {
                    $isRelevant = true;
                    break;
                }
            }

            if ($isRelevant) {
                $monthsUntil = $event['month'] - $currentMonth;
                if ($monthsUntil < 0) {
                    $monthsUntil += 12;
                }

                $seasonalFactors[] = [
                    'event' => $event['name'],
                    'month' => $event['month'],
                    'months_until' => $monthsUntil,
                    'relevance' => $monthsUntil <= 2 ? 'high' : ($monthsUntil <= 4 ? 'medium' : 'low'),
                ];

                if ($monthsUntil <= 3) {
                    $upcomingEvents[] = $event['name'];
                }
            }
        }

        // Calcular fator sazonal atual
        $seasonalBoost = 0;
        foreach ($seasonalFactors as $factor) {
            if ($factor['months_until'] === 0) {
                $seasonalBoost += 0.5;
            } elseif ($factor['months_until'] === 1) {
                $seasonalBoost += 0.3;
            } elseif ($factor['months_until'] === 2) {
                $seasonalBoost += 0.1;
            }
        }

        return [
            'current_month' => self::MONTHS_PT[$currentMonth],
            'seasonal_factors' => $seasonalFactors,
            'upcoming_events' => $upcomingEvents,
            'seasonal_boost' => min(1, $seasonalBoost),
            'is_seasonal_peak' => $seasonalBoost >= 0.3,
        ];
    }

    /**
     * 🔮 Calcular previsão
     */
    private function calculatePrediction(string $keyword, array $historical, array $currentTrend, array $seasonality): array
    {
        $trendScore = $currentTrend['trend_score'] ?? 0.5;
        $seasonalBoost = $seasonality['seasonal_boost'] ?? 0;
        $trendDirection = $currentTrend['trend_direction'] ?? 'stable';

        // Combinar fatores
        $combinedScore = ($trendScore * 0.7) + ($seasonalBoost * 0.3);

        // Determinar recomendação
        $recommendation = 'manter';
        $priority = 'normal';

        if ($combinedScore > 0.7) {
            $recommendation = 'investir_agora';
            $priority = 'alta';
        } elseif ($combinedScore > 0.55) {
            $recommendation = 'otimizar';
            $priority = 'media';
        } elseif ($combinedScore < 0.35) {
            $recommendation = 'reconsiderar';
            $priority = 'baixa';
        }

        // Prever próximos 3 meses
        $monthlyPrediction = [];
        for ($i = 1; $i <= 3; $i++) {
            $futureMonth = ((int) date('n') + $i - 1) % 12 + 1;
            $futureFactor = $this->getMonthSeasonalFactor($keyword, $futureMonth);
            
            $predictedScore = $trendScore;
            if ($trendDirection === 'up') {
                $predictedScore += 0.05 * $i;
            } elseif ($trendDirection === 'down') {
                $predictedScore -= 0.05 * $i;
            }

            $predictedScore = ($predictedScore * 0.7) + ($futureFactor * 0.3);

            $monthlyPrediction[] = [
                'month' => self::MONTHS_PT[$futureMonth],
                'predicted_score' => round(min(1, max(0, $predictedScore)), 2),
                'expected_demand' => $predictedScore > 0.6 ? 'alta' : ($predictedScore > 0.4 ? 'media' : 'baixa'),
            ];
        }

        return [
            'combined_score' => round($combinedScore, 2),
            'recommendation' => $recommendation,
            'priority' => $priority,
            'monthly_prediction' => $monthlyPrediction,
            'confidence' => empty($historical) ? 0.6 : 0.8,
            'insights' => $this->generateInsights($keyword, $currentTrend, $seasonality),
        ];
    }

    /**
     * 📅 Obter fator sazonal para mês específico
     */
    private function getMonthSeasonalFactor(string $keyword, int $month): float
    {
        $factor = 0;
        $keyword = mb_strtolower($keyword);

        foreach (self::SEASONAL_EVENTS as $event) {
            if ($event['month'] !== $month) {
                continue;
            }

            foreach ($event['categories'] as $category) {
                if ($category === '*' || str_contains($keyword, $category)) {
                    $factor += 0.3;
                    break;
                }
            }
        }

        return min(1, $factor);
    }

    /**
     * 💡 Gerar insights
     */
    private function generateInsights(string $keyword, array $currentTrend, array $seasonality): array
    {
        $insights = [];

        // Insight sobre volume
        $totalResults = $currentTrend['total_results'] ?? 0;
        if ($totalResults > 5000) {
            $insights[] = '🔥 Mercado muito competitivo com ' . number_format($totalResults) . ' anúncios';
        } elseif ($totalResults < 500) {
            $insights[] = '💎 Nicho com baixa concorrência - oportunidade!';
        }

        // Insight sobre tendência
        $trendDirection = $currentTrend['trend_direction'] ?? 'stable';
        if ($trendDirection === 'up') {
            $insights[] = '📈 Tendência de alta - bom momento para investir';
        } elseif ($trendDirection === 'down') {
            $insights[] = '📉 Tendência de queda - avaliar estratégia';
        }

        // Insight sobre sazonalidade
        if ($seasonality['is_seasonal_peak'] ?? false) {
            $events = implode(', ', $seasonality['upcoming_events'] ?? []);
            $insights[] = "🎯 Pico sazonal próximo: {$events}";
        }

        // Insight sobre Full
        $fullPercentage = $currentTrend['full_percentage'] ?? 0;
        if ($fullPercentage > 50) {
            $insights[] = '📦 Maioria dos top sellers usa Full - considere aderir';
        }

        return $insights;
    }

    /**
     * 💾 Salvar dados históricos
     */
    private function saveHistoricalData(string $keyword, array $trend): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO keyword_trends (
                    keyword_hash, keyword, total_results, avg_price, 
                    trend_score, trend_direction, recorded_at
                ) VALUES (
                    :keyword_hash, :keyword, :total_results, :avg_price,
                    :trend_score, :trend_direction, NOW()
                )
            ");

            $stmt->execute([
                'keyword_hash' => md5($keyword),
                'keyword' => $keyword,
                'total_results' => $trend['total_results'] ?? 0,
                'avg_price' => $trend['avg_price'] ?? 0,
                'trend_score' => $trend['trend_score'] ?? 0.5,
                'trend_direction' => $trend['trend_direction'] ?? 'stable',
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao salvar dados históricos de tendências', [
                'service' => 'TrendPredictorService',
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 📖 Obter dados históricos
     */
    private function getHistoricalData(string $keyword): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT total_results, avg_price, trend_score, trend_direction, recorded_at
                FROM keyword_trends
                WHERE keyword_hash = :keyword_hash
                ORDER BY recorded_at DESC
                LIMIT 30
            ");
            $stmt->execute(['keyword_hash' => md5($keyword)]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 🔥 Buscar keywords em ascensão
     */
    public function findRisingKeywords(string $categoryId, int $limit = 20): array
    {
        try {
            // Buscar trends do ML
            $response = $this->mlClient->get("/trends/MLB/{$categoryId}");
            $trends = $response ?? [];

            $rising = [];
            foreach ($trends as $trend) {
                $keyword = $trend['keyword'] ?? '';
                if (empty($keyword)) {
                    continue;
                }

                $prediction = $this->predictTrend($keyword, $categoryId);

                $rising[] = [
                    'keyword' => $keyword,
                    'trend_score' => $prediction['current_trend']['trend_score'] ?? 0.5,
                    'trend_direction' => $prediction['current_trend']['trend_direction'] ?? 'stable',
                    'total_results' => $prediction['current_trend']['total_results'] ?? 0,
                    'recommendation' => $prediction['prediction']['recommendation'] ?? 'manter',
                ];
            }

            // Ordenar por score
            usort($rising, fn($a, $b) => $b['trend_score'] <=> $a['trend_score']);

            return array_slice($rising, 0, $limit);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 📋 Relatório de tendências para categoria
     */
    public function getCategoryTrendReport(string $categoryId): array
    {
        $risingKeywords = $this->findRisingKeywords($categoryId, 10);
        $currentMonth = (int) date('n');

        // Verificar eventos sazonais relevantes
        $relevantEvents = [];
        foreach (self::SEASONAL_EVENTS as $event) {
            $monthsUntil = $event['month'] - $currentMonth;
            if ($monthsUntil < 0) {
                $monthsUntil += 12;
            }

            if ($monthsUntil <= 3) {
                $relevantEvents[] = [
                    'name' => $event['name'],
                    'month' => self::MONTHS_PT[$event['month']],
                    'months_until' => $monthsUntil,
                    'categories' => $event['categories'],
                ];
            }
        }

        return [
            'category_id' => $categoryId,
            'report_date' => date('Y-m-d'),
            'rising_keywords' => $risingKeywords,
            'upcoming_events' => $relevantEvents,
            'market_insight' => $this->getMarketInsight($risingKeywords, $relevantEvents),
        ];
    }

    /**
     * 💡 Gerar insight de mercado
     */
    private function getMarketInsight(array $risingKeywords, array $events): string
    {
        if (empty($risingKeywords) && empty($events)) {
            return 'Mercado estável, sem tendências significativas identificadas.';
        }

        $insights = [];

        if (!empty($risingKeywords)) {
            $topKeyword = $risingKeywords[0]['keyword'] ?? 'N/A';
            $insights[] = "Keyword em alta: '{$topKeyword}'";
        }

        if (!empty($events)) {
            $nextEvent = $events[0]['name'] ?? 'N/A';
            $insights[] = "Próximo evento: {$nextEvent}";
        }

        return implode('. ', $insights) . '.';
    }
}
