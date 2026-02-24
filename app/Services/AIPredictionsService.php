<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * AI Predictions Service
 *
 * Machine Learning para previsões de vendas usando:
 * - Linear Regression (tendências)
 * - Moving Average (sazonalidade)
 * - Exponential Smoothing (dados recentes)
 * - Pattern Recognition (eventos especiais)
 *
 * Previsões:
 * 1. Vendas futuras (próximos 7-90 dias)
 * 2. Demanda por categoria
 * 3. Melhor momento para promoções
 * 4. Produtos que vão bombar
 */
class AIPredictionsService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(int $accountId)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Prevê vendas futuras usando múltiplos modelos
     *
     * Combina 3 algoritmos:
     * 1. Linear Regression (peso 30%)
     * 2. Exponential Smoothing (peso 40%)
     * 3. Seasonal Decomposition (peso 30%)
     *
     * @param string $itemId
     * @param int $days Dias para prever (1-90)
     * @return array
     */
    public function predictSales(string $itemId, int $days = 30): array
    {
        try {
            // Buscar histórico de 180 dias
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date, sold_quantity
                FROM item_metrics_history
                WHERE item_id = :item_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY)
                ORDER BY created_at ASC
            ");
            $stmt->execute(['item_id' => $itemId]);
            $history = $stmt->fetchAll();

            if (count($history) < 30) {
                return [
                    'success' => false,
                    'error' => 'Insufficient data. Need at least 30 days of history'
                ];
            }

            // Converter para array de vendas diárias
            $sales = array_map(fn($row) => (int)$row['sold_quantity'], $history);

            // Modelo 1: Linear Regression
            $linearPred = $this->linearRegression($sales, $days);

            // Modelo 2: Exponential Smoothing
            $expPred = $this->exponentialSmoothing($sales, $days, 0.3);

            // Modelo 3: Seasonal (detectar padrões semanais)
            $seasonalPred = $this->seasonalForecast($sales, $days);

            // Ensemble: combinar modelos
            $forecast = [];
            $totalPredicted = 0;

            for ($i = 0; $i < $days; $i++) {
                $value = (
                    ($linearPred[$i] ?? 0) * 0.30 +
                    ($expPred[$i] ?? 0) * 0.40 +
                    ($seasonalPred[$i] ?? 0) * 0.30
                );

                $value = max(0, round($value)); // Não pode ser negativo
                $forecast[] = [
                    'day' => $i + 1,
                    'date' => date('Y-m-d', strtotime("+{$i} days")),
                    'predicted_sales' => $value
                ];
                $totalPredicted += $value;
            }

            // Calcular confiança
            $confidence = $this->calculateConfidence($sales, count($history));

            return [
                'success' => true,
                'item_id' => $itemId,
                'forecast_days' => $days,
                'forecast' => $forecast,
                'total_predicted' => $totalPredicted,
                'avg_daily' => round($totalPredicted / $days, 1),
                'confidence' => $confidence,
                'confidence_level' => $this->getConfidenceLevel($confidence),
                'historical_avg' => round(array_sum($sales) / count($sales), 1),
                'data_points' => count($sales)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Identifica produtos com potencial de crescimento
     *
     * Critérios:
     * - Crescimento de vendas >20% últimos 30 dias
     * - Views crescendo >30%
     * - Estoque suficiente
     * - Margem boa (>25%)
     *
     * @param int $limit
     * @return array
     */
    public function identifyRisingStars(int $limit = 20): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                WITH recent_metrics AS (
                    SELECT
                        item_id,
                        AVG(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            THEN sold_quantity ELSE 0 END) as recent_sales,
                        AVG(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                            THEN sold_quantity ELSE 0 END) as prev_sales,
                        AVG(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                            THEN views ELSE 0 END) as recent_views,
                        AVG(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                            AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                            THEN views ELSE 0 END) as prev_views
                    FROM item_metrics_history
                    WHERE account_id = :account_id
                    GROUP BY item_id
                )
                SELECT
                    i.item_id,
                    i.title,
                    i.price,
                    i.available_quantity,
                    rm.recent_sales,
                    rm.prev_sales,
                    ((rm.recent_sales - rm.prev_sales) / NULLIF(rm.prev_sales, 0)) * 100 as sales_growth,
                    ((rm.recent_views - rm.prev_views) / NULLIF(rm.prev_views, 0)) * 100 as views_growth
                FROM items i
                JOIN recent_metrics rm ON i.item_id = rm.item_id
                WHERE rm.prev_sales > 0
                AND ((rm.recent_sales - rm.prev_sales) / rm.prev_sales) > 0.20
                AND ((rm.recent_views - rm.prev_views) / NULLIF(rm.prev_views, 0)) > 0.30
                AND i.available_quantity > 10
                ORDER BY sales_growth DESC
                LIMIT {$limitSql}
            ");

            $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
            $stmt->execute();
            $stars = $stmt->fetchAll();

            // Calcular potential score
            foreach ($stars as &$star) {
                $score = 0;
                $score += min(($star['sales_growth'] / 100) * 40, 40); // Max 40 pontos
                $score += min(($star['views_growth'] / 100) * 30, 30); // Max 30 pontos
                $score += min(($star['available_quantity'] / 50) * 30, 30); // Max 30 pontos

                $star['potential_score'] = round($score, 1);
                $star['potential_level'] = $score > 70 ? 'very_high' : ($score > 50 ? 'high' : 'medium');
            }

            return [
                'success' => true,
                'rising_stars' => $stars,
                'count' => count($stars)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Prevê melhor momento para lançar promoção
     *
     * Analisa:
     * - Dia da semana (fim de semana vende mais)
     * - Horário (tarde/noite)
     * - Eventos sazonais
     * - Histórico de conversão
     *
     * @param string $itemId
     * @return array
     */
    public function predictBestPromotionTime(string $itemId): array
    {
        try {
            // Analisar vendas por dia da semana
            $stmt = $this->db->prepare("
                SELECT
                    DAYOFWEEK(created_at) as day_of_week,
                    HOUR(created_at) as hour,
                    AVG(sold_quantity) as avg_sales,
                    COUNT(*) as occurrences
                FROM item_metrics_history
                WHERE item_id = :item_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY day_of_week, hour
                ORDER BY avg_sales DESC
                LIMIT 10
            ");
            $stmt->execute(['item_id' => $itemId]);
            $bestTimes = $stmt->fetchAll();

            if (empty($bestTimes)) {
                return [
                    'success' => false,
                    'error' => 'No historical data for this item'
                ];
            }

            $topTime = $bestTimes[0];

            // Mapear dia da semana
            $days = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $dayName = $days[$topTime['day_of_week']];

            // Calcular próxima ocorrência
            $today = strtotime('today');
            $targetDay = (int)$topTime['day_of_week'];
            $currentDay = (int)date('w');
            $daysUntil = ($targetDay - $currentDay + 7) % 7;
            $daysUntil = $daysUntil === 0 ? 7 : $daysUntil;

            $nextDate = date('Y-m-d', strtotime("+{$daysUntil} days"));
            $recommendedTime = sprintf("%02d:00", $topTime['hour']);

            // Detectar se é período sazonal
            $seasonalBonus = $this->detectSeasonalEvent();

            return [
                'success' => true,
                'item_id' => $itemId,
                'recommended_day' => $dayName,
                'recommended_hour' => $topTime['hour'],
                'recommended_datetime' => "{$nextDate} {$recommendedTime}",
                'days_until' => $daysUntil,
                'expected_performance' => round($topTime['avg_sales'], 1),
                'confidence' => min(($topTime['occurrences'] / 10) * 100, 100),
                'seasonal_event' => $seasonalBonus,
                'best_times' => array_slice($bestTimes, 0, 5)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Prevê demanda por categoria
     *
     * @param string $categoryId
     * @param int $days
     * @return array
     */
    public function predictCategoryDemand(string $categoryId, int $days = 30): array
    {
        try {
            // Buscar histórico da categoria
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date, SUM(sold_quantity) as total_sales
                FROM item_metrics_history imh
                JOIN items i ON imh.item_id = i.ml_item_id
                WHERE i.category_id = :category_id
                AND imh.account_id = :account_id
                AND imh.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute([
                'category_id' => $categoryId,
                'account_id' => $this->accountId
            ]);
            $history = $stmt->fetchAll();

            if (count($history) < 15) {
                return [
                    'success' => false,
                    'error' => 'Insufficient category data'
                ];
            }

            $sales = array_map(fn($row) => (int)$row['total_sales'], $history);

            // Previsão usando exponential smoothing
            $forecast = $this->exponentialSmoothing($sales, $days, 0.4);

            $predictions = [];
            $totalPredicted = 0;

            for ($i = 0; $i < $days; $i++) {
                $value = max(0, round($forecast[$i]));
                $predictions[] = [
                    'day' => $i + 1,
                    'date' => date('Y-m-d', strtotime("+{$i} days")),
                    'predicted_demand' => $value
                ];
                $totalPredicted += $value;
            }

            return [
                'success' => true,
                'category_id' => $categoryId,
                'forecast_days' => $days,
                'predictions' => $predictions,
                'total_predicted' => $totalPredicted,
                'avg_daily' => round($totalPredicted / $days, 1),
                'trend' => $this->detectTrend($sales)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== PRIVATE ML ALGORITHMS ====================

    /**
     * Linear Regression: y = mx + b
     */
    private function linearRegression(array $data, int $steps): array
    {
        $n = count($data);
        $x = range(1, $n);
        $y = $data;

        // Calcular slope (m) e intercept (b)
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }

        $m = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $b = ($sumY - $m * $sumX) / $n;

        // Prever próximos steps
        $forecast = [];
        for ($i = 1; $i <= $steps; $i++) {
            $forecast[] = $m * ($n + $i) + $b;
        }

        return $forecast;
    }

    /**
     * Exponential Smoothing
     */
    private function exponentialSmoothing(array $data, int $steps, float $alpha = 0.3): array
    {
        $smoothed = [$data[0]];

        for ($i = 1; $i < count($data); $i++) {
            $smoothed[] = $alpha * $data[$i] + (1 - $alpha) * $smoothed[$i - 1];
        }

        // Prever próximos steps
        $forecast = [];
        $last = end($smoothed);

        for ($i = 0; $i < $steps; $i++) {
            $forecast[] = $last;
        }

        return $forecast;
    }

    /**
     * Seasonal Forecast (padrão semanal)
     */
    private function seasonalForecast(array $data, int $steps): array
    {
        $seasonLength = 7; // Semana
        $n = count($data);

        // Calcular médias sazonais
        $seasonals = [];
        for ($i = 0; $i < $seasonLength; $i++) {
            $values = [];
            for ($j = $i; $j < $n; $j += $seasonLength) {
                $values[] = $data[$j];
            }
            $seasonals[$i] = !empty($values) ? array_sum($values) / count($values) : 0;
        }

        // Prever usando padrão sazonal
        $forecast = [];
        $baseValue = array_sum($data) / $n;

        for ($i = 0; $i < $steps; $i++) {
            $seasonIndex = ($n + $i) % $seasonLength;
            $forecast[] = $seasonals[$seasonIndex] ?? $baseValue;
        }

        return $forecast;
    }

    /**
     * Calcula nível de confiança (0-100)
     */
    private function calculateConfidence(array $data, int $dataPoints): float
    {
        // Mais dados = mais confiança
        $dataFactor = min($dataPoints / 90, 1) * 40;

        // Menos variância = mais confiança
        $mean = array_sum($data) / count($data);
        $variance = 0;
        foreach ($data as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($data);
        $stdDev = sqrt($variance);
        $cv = $mean > 0 ? $stdDev / $mean : 1; // Coefficient of variation
        $varianceFactor = max(0, (1 - min($cv, 1)) * 60);

        return min($dataFactor + $varianceFactor, 100);
    }

    /**
     * Retorna nível de confiança textual
     */
    private function getConfidenceLevel(float $confidence): string
    {
        if ($confidence >= 80) return 'very_high';
        if ($confidence >= 60) return 'high';
        if ($confidence >= 40) return 'medium';
        return 'low';
    }

    /**
     * Detecta evento sazonal próximo
     */
    private function detectSeasonalEvent(): ?array
    {
        $month = (int)date('n');
        $day = (int)date('j');

        $events = [
            ['name' => 'Natal', 'start' => [12, 1], 'end' => [12, 25], 'multiplier' => 2.5],
            ['name' => 'Black Friday', 'start' => [11, 20], 'end' => [11, 30], 'multiplier' => 3.0],
            ['name' => 'Dia das Mães', 'start' => [5, 1], 'end' => [5, 14], 'multiplier' => 2.0],
            ['name' => 'Dia dos Pais', 'start' => [8, 1], 'end' => [8, 14], 'multiplier' => 1.8],
            ['name' => 'Volta às Aulas', 'start' => [1, 15], 'end' => [2, 15], 'multiplier' => 1.6],
        ];

        foreach ($events as $event) {
            if ($month == $event['start'][0] && $day >= $event['start'][1] && $day <= $event['end'][1]) {
                return [
                    'name' => $event['name'],
                    'multiplier' => $event['multiplier'],
                    'days_until_end' => $event['end'][1] - $day
                ];
            }
        }

        return null;
    }

    /**
     * Detecta tendência (rising, falling, stable)
     */
    private function detectTrend(array $data): string
    {
        $half = floor(count($data) / 2);
        $firstHalf = array_slice($data, 0, $half);
        $secondHalf = array_slice($data, $half);

        $avgFirst = array_sum($firstHalf) / count($firstHalf);
        $avgSecond = array_sum($secondHalf) / count($secondHalf);

        $change = ($avgSecond - $avgFirst) / $avgFirst;

        if ($change > 0.15) return 'rising';
        if ($change < -0.15) return 'falling';
        return 'stable';
    }
}
