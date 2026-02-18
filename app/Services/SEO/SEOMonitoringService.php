<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use PDO;

class SEOMonitoringService
{
    public function __construct(?int $accountId = null)
    {
        // Initialization code if needed
    }

    /**
     * Coleta métricas reais de performance usando dados locais.
     * Fonte: item_metrics_history (visits, sold_quantity, price) e orders.
     */
    public function collectMetrics(string $itemId): array
    {
        try {
            $accountId = $this->resolveAccountId($itemId);
            $db = Database::getInstance();

            // Métrica mais recente do item
            $stmt = $db->prepare("
                SELECT visits, sold_quantity, conversion_rate, price, date
                FROM item_metrics_history
                WHERE item_id = :item_id
                ORDER BY date DESC
                LIMIT 1
            ");
            $stmt->execute(['item_id' => $itemId]);
            $latest = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
                'visits' => 0,
                'sold_quantity' => 0,
                'conversion_rate' => 0,
                'price' => 0,
                'date' => date('Y-m-d')
            ];

            // Cliques (aproximação) = visitas
            $views = (int) $latest['visits'];
            $clicks = $views;
            $sales = (int) $latest['sold_quantity'];
            $conversionRate = $views > 0 ? $sales / $views : 0;

            // Posição média (se houver histórico em seo_optimizations)
            $positionAvg = $this->fetchAveragePosition($itemId);

            return [
                'item_id' => $itemId,
                'views' => $views,
                'clicks' => $clicks,
                'sales' => $sales,
                'conversion_rate' => round($conversionRate, 4),
                'position_avg' => $positionAvg,
                'last_updated' => $latest['date'],
                'traffic_sources' => $this->estimateTrafficSources($accountId, $itemId, $views)
            ];
        } catch (\PDOException $e) {
            // Tabela não existe ou erro de banco - retornar métricas vazias
            log_warning('Erro ao coletar métricas SEO', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [
                'item_id' => $itemId,
                'views' => 0,
                'clicks' => 0,
                'sales' => 0,
                'conversion_rate' => 0,
                'position_avg' => 0,
                'last_updated' => date('Y-m-d'),
                'traffic_sources' => []
            ];
        }
    }

    /**
     * Compara com período anterior
     */
    public function compareWithPrevious(string $itemId, int $days = 7): array
    {
        $current = $this->collectMetrics($itemId);

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT visits, sold_quantity, conversion_rate, price, date
            FROM item_metrics_history
            WHERE item_id = :item_id AND date <= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            ORDER BY date DESC
            LIMIT 1
        ");
        $stmt->bindValue(':item_id', $itemId);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $prev = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'visits' => 0,
            'sold_quantity' => 0,
            'conversion_rate' => 0,
            'price' => 0,
            'date' => null
        ];

        $previousMetrics = [
            'views' => (int) $prev['visits'],
            'clicks' => (int) $prev['visits'],
            'sales' => (int) $prev['sold_quantity'],
            'conversion_rate' => (float) $prev['conversion_rate'],
            'position_avg' => $this->fetchAveragePosition($itemId, $prev['date'])
        ];

        $changes = [
            'views_change' => $current['views'] - $previousMetrics['views'],
            'clicks_change' => $current['clicks'] - $previousMetrics['clicks'],
            'sales_change' => $current['sales'] - $previousMetrics['sales'],
            'conversion_change' => round(($current['conversion_rate'] - $previousMetrics['conversion_rate']) * 100, 2),
            'position_change' => $current['position_avg'] - $previousMetrics['position_avg']
        ];

        return [
            'item_id' => $itemId,
            'period_comparison' => [
                'current' => $current,
                'previous' => $previousMetrics
            ],
            'changes' => $changes,
            'trend' => $this->determineTrend($changes)
        ];
    }

    /**
     * Identifica oportunidades de melhoria
     */
    public function identifyOpportunities(string $itemId): array
    {
        $metrics = $this->collectMetrics($itemId);
        $opportunities = [];
        
        // Identify opportunities based on metrics
        if ($metrics['position_avg'] > 10) {
            $opportunities[] = [
                'type' => 'position',
                'description' => 'Anúncio em posição inferior a 10. Oportunidade de melhoria de ranqueamento.',
                'priority' => 'high',
                'suggestion' => 'Executar otimização completa de SEO'
            ];
        }
        
        if ($metrics['conversion_rate'] < 0.02) { // Less than 2%
            $opportunities[] = [
                'type' => 'conversion',
                'description' => 'Taxa de conversão abaixo de 2%. Oportunidade de melhoria na conversão.',
                'priority' => 'medium',
                'suggestion' => 'Revisar título e descrição para melhor alinhamento com intenção de compra'
            ];
        }
        
        if ($metrics['views'] < 200) {
            $opportunities[] = [
                'type' => 'visibility',
                'description' => 'Número de visualizações abaixo de 200. Oportunidade de aumento de visibilidade.',
                'priority' => 'medium',
                'suggestion' => 'Expandir palavras-chave e otimizar para mais tipos de busca'
            ];
        }
        
        // Check for seasonal opportunities
        $seasonalOpportunities = $this->checkSeasonalOpportunities($itemId);
        $opportunities = array_merge($opportunities, $seasonalOpportunities);
        
        return $opportunities;
    }

    /**
     * Agenda verificação automática
     */
    public function scheduleCheck(string $itemId, int $intervalDays = 7): array
    {
        $db = Database::getInstance();
        $accountId = $this->resolveAccountId($itemId);

        $stmt = $db->prepare("
            INSERT INTO seo_monitoring_schedule (item_id, account_id, interval_days, next_check, status, priority, created_at, updated_at)
            VALUES (:item_id, :account_id, :interval_days, DATE_ADD(NOW(), INTERVAL :interval_days DAY), 'active', 'normal', NOW(), NOW())
            ON DUPLICATE KEY UPDATE interval_days = VALUES(interval_days), next_check = VALUES(next_check), status = 'active', updated_at = NOW()
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $accountId,
            'interval_days' => $intervalDays
        ]);

        return [
            'item_id' => $itemId,
            'account_id' => $accountId,
            'interval_days' => $intervalDays,
            'next_check' => date('Y-m-d H:i:s', strtotime("+{$intervalDays} days"))
        ];
    }

    /**
     * Executa otimização automática
     */
    public function runAutoOptimization(string $itemId): array
    {
        // Initialize the SEO engine to perform optimization
        $engine = new SEOStrategiesEngine();
        
        // Perform full optimization
        $optimizationResult = $engine->optimizeFull($itemId);
        
        // Apply the optimizations
        $applicationResult = $engine->applyOptimization($itemId, $optimizationResult);
        
        return [
            'optimization_result' => $optimizationResult,
            'application_result' => $applicationResult,
            'completed_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Gera alerta de queda de posição
     */
    public function generateAlert(string $itemId, string $alertType): void
    {
        $alertMessage = "Alert for item {$itemId}: {$alertType} at " . date('Y-m-d H:i:s');
        $this->storeAlert($itemId, $alertType, $alertMessage);
    }

    /**
     * Determines trend based on changes
     */
    private function determineTrend(array $changes): string
    {
        if (!$this->hasSignificantChange($changes)) {
            return 'stable';
        }

        [$positiveFactors, $negativeFactors] = $this->countTrendFactors($changes);

        if ($positiveFactors > $negativeFactors) {
            return 'improving';
        }

        if ($negativeFactors > $positiveFactors) {
            return 'declining';
        }

        return 'mixed';
    }

    private function hasSignificantChange(array $changes): bool
    {
        $thresholds = [
            'views_change' => 100,
            'clicks_change' => 10,
            'sales_change' => 5,
            'conversion_change' => 0.5,
            'position_change' => 3,
        ];

        foreach ($thresholds as $key => $limit) {
            $value = $changes[$key] ?? 0;
            if (abs($value) > $limit) {
                return true;
            }
        }

        return false;
    }

    private function countTrendFactors(array $changes): array
    {
        $positive = 0;
        $negative = 0;

        $pairs = [
            'views_change' => 1,
            'clicks_change' => 1,
            'sales_change' => 1,
            'conversion_change' => 1,
            'position_change' => -1,
        ];

        foreach ($pairs as $key => $direction) {
            $value = $changes[$key] ?? 0;
            $adjusted = $value * $direction;
            if ($adjusted > 0) {
                $positive++;
            } else {
                $negative++;
            }
        }

        return [$positive, $negative];
    }

    /**
     * Check for seasonal opportunities
     */
    private function checkSeasonalOpportunities(string $itemId): array
    {
        $opportunities = [];
        
        // Get current month
        $currentMonth = date('n');
        
        // Define seasonal patterns (example for moto accessories)
        $seasonalPatterns = [
            11 => [ // November - start of summer season in Brazil
                'type' => 'seasonal',
                'description' => 'Início da temporada de verão. Oportunidade para produtos de lazer.',
                'priority' => 'medium',
                'suggestion' => 'Adicionar palavras-chave relacionadas a verão, praia, viagem'
            ],
            12 => [ // December - peak summer
                'type' => 'seasonal',
                'description' => 'Mês de maior demanda de verão. Oportunidade de pico.',
                'priority' => 'high',
                'suggestion' => 'Maximizar palavras-chave sazonais e promocionais'
            ],
            6 => [ // June - winter season
                'type' => 'seasonal',
                'description' => 'Temporada de inverno. Oportunidade para equipamentos de proteção.',
                'priority' => 'medium',
                'suggestion' => 'Focar em palavras-chave de proteção, conforto, aquecimento'
            ]
        ];
        
        if (isset($seasonalPatterns[$currentMonth])) {
            $opportunity = $seasonalPatterns[$currentMonth];
            $opportunity['item_id'] = $itemId;
            $opportunities[] = $opportunity;
        }
        
        return $opportunities;
    }

    /**
     * Store alert in database or log system
     */
    private function storeAlert(string $itemId, string $alertType, string $message): void
    {
        $accountId = $this->resolveAccountId($itemId);
        if (!$accountId) {
            return;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO realtime_alerts (account_id, alert_type, message, data_json, sent, created_at)
                VALUES (:account_id, :alert_type, :message, :data_json, 0, NOW())
            ");
            $stmt->execute([
                'account_id' => $accountId,
                'alert_type' => 'score',
                'message' => $message,
                'data_json' => json_encode([
                    'item_id' => $itemId,
                    'type' => $alertType
                ])
            ]);
        } catch (\Throwable $e) {
            log_warning('Erro ao salvar alerta SEO', [
                'item_id' => $itemId,
                'alert_type' => $alertType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveAccountId(string $itemId): ?int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT account_id FROM items WHERE ml_item_id = :id LIMIT 1");
        $stmt->execute(['id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['account_id'] : null;
    }

    private function fetchAveragePosition(string $itemId, ?string $untilDate = null): float
    {
        $db = Database::getInstance();
        $sql = "
            SELECT AVG(position_avg) as pos
            FROM seo_performance_metrics
            WHERE item_id = :item_id
        ";
        if ($untilDate) {
            $sql .= " AND metric_date <= :until";
        }
        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':item_id', $itemId);
        if ($untilDate) {
            $stmt->bindValue(':until', $untilDate);
        }
        $stmt->execute();
        $pos = $stmt->fetchColumn();
        return $pos !== false ? round((float)$pos, 2) : 0.0;
    }

    private function estimateTrafficSources(?int $accountId, string $itemId, int $views): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT traffic_sources
            FROM seo_performance_metrics
            WHERE item_id = :item_id
            ORDER BY metric_date DESC
            LIMIT 1
        ");
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['traffic_sources'])) {
            $json = json_decode($row['traffic_sources'], true);
            if (is_array($json)) {
                return $json;
            }
        }

        // fallback determinístico
        return [
            'organic' => $views,
            'paid' => 0,
            'direct' => 0
        ];
    }
}
