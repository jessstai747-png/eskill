<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Exception;
use Throwable;

/**
 * Clone ROI Analysis Service
 *
 * Análise comparativa de desempenho entre itens clonados e originais:
 * - Métricas de vendas e conversão
 * - Comparação de performance
 * - Cálculo de ROI
 * - Identificação de top performers
 */
class CloneROIAnalysisService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Obtém análise comparativa geral
     */
    public function getComparativeAnalysis(array $filters = []): array
    {
        $period = $filters['period'] ?? 30; // dias
        $startDate = date('Y-m-d', strtotime("-{$period} days"));

        // Buscar itens clonados com métricas
        $clonedItems = $this->getClonedItemsWithMetrics($startDate, $filters);

        // Calcular agregados
        $summary = $this->calculateSummary($clonedItems);

        // Top performers
        $topPerformers = $this->identifyTopPerformers($clonedItems);

        // Underperformers (candidatos a pausar)
        $underperformers = $this->identifyUnderperformers($clonedItems);

        return [
            'period_days' => $period,
            'start_date' => $startDate,
            'summary' => $summary,
            'top_performers' => $topPerformers,
            'underperformers' => $underperformers,
            'total_items_analyzed' => count($clonedItems),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Busca itens clonados com métricas
     */
    private function getClonedItemsWithMetrics(string $startDate, array $filters): array
    {
        $sql = "
            SELECT
                ci.id,
                ci.source_item_id,
                ci.target_item_id,
                ci.source_seller_id,
                ci.cloned_at,
                cim.visits,
                cim.sales,
                cim.revenue,
                cim.conversion_rate,
                cim.avg_position,
                cim.updated_at as metrics_updated,
                JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.title')) as title,
                JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.category_id')) as category_id,
                JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.brand')) as brand,
                JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.price')) as original_price
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics cim ON cim.cloned_item_id = ci.id
            WHERE ci.account_id = :account_id
            AND ci.cloned_at >= :start_date
        ";
        $params = [
            ':account_id' => $this->accountId,
            ':start_date' => $startDate,
        ];

        if (!empty($filters['category_id'])) {
            $sql .= " AND JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.category_id')) LIKE :category";
            $params[':category'] = $filters['category_id'] . '%';
        }

        if (!empty($filters['seller_id'])) {
            $sql .= " AND ci.source_seller_id = :seller_id";
            $params[':seller_id'] = $filters['seller_id'];
        }

        $sql .= " ORDER BY ci.cloned_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula sumário geral
     */
    private function calculateSummary(array $items): array
    {
        $totalVisits = 0;
        $totalSales = 0;
        $totalRevenue = 0;
        $conversionRates = [];
        $withMetrics = 0;

        foreach ($items as $item) {
            if ($item['visits'] !== null) {
                $withMetrics++;
                $totalVisits += intval($item['visits']);
                $totalSales += intval($item['sales']);
                $totalRevenue += floatval($item['revenue']);
                if (($item['conversion_rate'] ?? null) !== null) {
                    $conversionRates[] = floatval($item['conversion_rate']);
                }
            }
        }

        $avgConversion = count($conversionRates) > 0
            ? array_sum($conversionRates) / count($conversionRates)
            : 0;

        $overallConversion = $totalVisits > 0
            ? ($totalSales / $totalVisits) * 100
            : 0;

        return [
            'total_items' => count($items),
            'items_with_metrics' => $withMetrics,
            'total_visits' => $totalVisits,
            'total_sales' => $totalSales,
            'total_revenue' => round($totalRevenue, 2),
            'avg_conversion_rate' => round($avgConversion, 2),
            'overall_conversion_rate' => round($overallConversion, 2),
            'avg_revenue_per_item' => $withMetrics > 0
                ? round($totalRevenue / $withMetrics, 2)
                : 0,
            'avg_sales_per_item' => $withMetrics > 0
                ? round($totalSales / $withMetrics, 2)
                : 0,
        ];
    }

    /**
     * Identifica top performers
     */
    private function identifyTopPerformers(array $items, int $limit = 10): array
    {
        // Filtrar itens com métricas e ordenar por receita
        $withMetrics = array_filter($items, fn($i) => $i['revenue'] > 0);

        usort($withMetrics, fn($a, $b) => floatval($b['revenue']) <=> floatval($a['revenue']));

        $topPerformers = array_slice($withMetrics, 0, $limit);

        return array_map(function ($item) {
            return [
                'target_item_id' => $item['target_item_id'],
                'source_item_id' => $item['source_item_id'],
                'title' => $item['title'],
                'brand' => $item['brand'],
                'revenue' => floatval($item['revenue']),
                'sales' => intval($item['sales']),
                'visits' => intval($item['visits']),
                'conversion_rate' => floatval($item['conversion_rate']),
                'roi_indicator' => $this->calculateROIIndicator($item),
            ];
        }, $topPerformers);
    }

    /**
     * Identifica underperformers
     */
    private function identifyUnderperformers(array $items, int $limit = 10): array
    {
        // Itens com visitas mas sem vendas ou conversão muito baixa
        $candidates = array_filter($items, function ($item) {
            $visits = intval($item['visits'] ?? 0);
            $sales = intval($item['sales'] ?? 0);
            $conversion = floatval($item['conversion_rate'] ?? 0);

            // Teve pelo menos 50 visitas mas conversão < 0.5%
            return $visits >= 50 && ($conversion < 0.5 || $sales === 0);
        });

        usort($candidates, fn($a, $b) => intval($b['visits']) <=> intval($a['visits']));

        $underperformers = array_slice($candidates, 0, $limit);

        return array_map(function ($item) {
            return [
                'target_item_id' => $item['target_item_id'],
                'source_item_id' => $item['source_item_id'],
                'title' => $item['title'],
                'visits' => intval($item['visits']),
                'sales' => intval($item['sales']),
                'conversion_rate' => floatval($item['conversion_rate']),
                'recommendation' => $this->getUnderperformerRecommendation($item),
            ];
        }, $underperformers);
    }

    /**
     * Calcula indicador de ROI
     */
    private function calculateROIIndicator(array $item): string
    {
        $revenue = floatval($item['revenue'] ?? 0);
        $sales = intval($item['sales'] ?? 0);
        $conversion = floatval($item['conversion_rate'] ?? 0);

        if ($revenue >= 5000 && $conversion >= 3) {
            return 'excellent';
        } elseif ($revenue >= 1000 && $conversion >= 1.5) {
            return 'good';
        } elseif ($revenue >= 100 || $sales >= 1) {
            return 'moderate';
        }

        return 'low';
    }

    /**
     * Gera recomendação para underperformer
     */
    private function getUnderperformerRecommendation(array $item): array
    {
        $visits = intval($item['visits'] ?? 0);
        $conversion = floatval($item['conversion_rate'] ?? 0);

        if ($visits >= 100 && $conversion < 0.1) {
            return [
                'action' => 'pause',
                'reason' => 'Alta visibilidade sem conversão',
                'suggestion' => 'Pausar e revisar preço/título',
            ];
        } elseif ($visits >= 50 && $conversion < 0.5) {
            return [
                'action' => 'optimize',
                'reason' => 'Baixa conversão',
                'suggestion' => 'Otimizar título e imagens',
            ];
        }

        return [
            'action' => 'monitor',
            'reason' => 'Precisa mais dados',
            'suggestion' => 'Aguardar mais visitas',
        ];
    }

    /**
     * Obtém comparação direta entre original e clone
     */
    public function getItemComparison(string $targetItemId): array
    {
        // Buscar dados do clone
        $stmt = $this->db->prepare("
            SELECT
                ci.*,
                cim.visits, cim.sales, cim.revenue, cim.conversion_rate
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics cim ON cim.cloned_item_id = ci.id
            WHERE ci.target_item_id = :item_id
            AND ci.account_id = :account_id
        ");
        $stmt->execute([
            ':item_id' => $targetItemId,
            ':account_id' => $this->accountId,
        ]);
        $clone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$clone) {
            return ['error' => 'Item clonado não encontrado'];
        }

        $sourceSnapshot = json_decode($clone['source_snapshot'] ?? '{}', true);
        if (!is_array($sourceSnapshot)) {
            $sourceSnapshot = [];
        }

        return [
            'clone' => [
                'item_id' => $clone['target_item_id'],
                'cloned_at' => $clone['cloned_at'],
                'visits' => intval($clone['visits'] ?? 0),
                'sales' => intval($clone['sales'] ?? 0),
                'revenue' => floatval($clone['revenue'] ?? 0),
                'conversion_rate' => floatval($clone['conversion_rate'] ?? 0),
            ],
            'source' => [
                'item_id' => $clone['source_item_id'],
                'seller_id' => $clone['source_seller_id'],
                'title' => $sourceSnapshot['title'] ?? '',
                'price' => $sourceSnapshot['price'] ?? 0,
                'category_id' => $sourceSnapshot['category_id'] ?? '',
            ],
            'performance_delta' => $this->calculatePerformanceDelta($clone),
        ];
    }

    /**
     * Calcula delta de performance
     */
    private function calculatePerformanceDelta(array $clone): array
    {
        $sales = intval($clone['sales'] ?? 0);
        $conversion = floatval($clone['conversion_rate'] ?? 0);

        $benchmark = $this->getRealCategoryBenchmark($clone);
        $benchmarkConversion = floatval($benchmark['value'] ?? 0);

        $status = 'insufficient_data';
        if ($benchmarkConversion > 0) {
            $status = $conversion >= $benchmarkConversion ? 'above' : 'below';
        }

        return [
            'vs_benchmark' => [
                'conversion_diff' => round($conversion - $benchmarkConversion, 2),
                'benchmark_conversion' => round($benchmarkConversion, 2),
                'benchmark_source' => $benchmark['source'] ?? 'unavailable',
                'status' => $status,
            ],
            'sales' => $sales,
            'time_to_first_sale' => $this->estimateTimeToFirstSale($clone),
        ];
    }

    /**
     * Calcula benchmark real de conversão da categoria usando dados do banco
     */
    private function getRealCategoryBenchmark(array $clone): array
    {
        $categoryId = '';
        if (!empty($clone['source_snapshot'])) {
            $snapshot = json_decode((string)$clone['source_snapshot'], true);
            $categoryId = (string)($snapshot['category_id'] ?? '');
        }

        try {
            if ($categoryId !== '') {
                $stmt = $this->db->prepare("
                    SELECT AVG(cim.conversion_rate) as avg_conversion
                    FROM clone_item_metrics cim
                    JOIN cloned_items ci ON ci.id = cim.cloned_item_id
                    WHERE ci.account_id = :account_id
                      AND JSON_UNQUOTE(JSON_EXTRACT(ci.source_snapshot, '$.category_id')) = :category_id
                      AND cim.visits > 10
                      AND cim.updated_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                ");
                $stmt->execute([
                    ':account_id' => $this->accountId,
                    ':category_id' => $categoryId,
                ]);
                $avgConversion = floatval($stmt->fetchColumn() ?: 0);
                if ($avgConversion > 0) {
                    return ['value' => round($avgConversion, 2), 'source' => 'clone_metrics_category'];
                }
            }

            $stmt = $this->db->prepare("
                SELECT AVG(cim.conversion_rate) as avg_conversion
                FROM clone_item_metrics cim
                JOIN cloned_items ci ON ci.id = cim.cloned_item_id
                WHERE ci.account_id = :account_id
                  AND cim.visits > 10
                  AND cim.updated_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            ");
            $stmt->execute([':account_id' => $this->accountId]);
            $accountAvg = floatval($stmt->fetchColumn() ?: 0);
            if ($accountAvg > 0) {
                return ['value' => round($accountAvg, 2), 'source' => 'clone_metrics_account'];
            }

            if ($categoryId !== '') {
                try {
                    $stmtPerf = $this->db->prepare("
                        SELECT AVG(conversion_rate) as avg_conversion
                        FROM category_performance
                        WHERE category_id = :category_id
                          AND date >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                    ");
                    $stmtPerf->execute([':category_id' => $categoryId]);
                    $catPerfAvg = floatval($stmtPerf->fetchColumn() ?: 0);
                    if ($catPerfAvg > 0) {
                        return ['value' => round($catPerfAvg, 2), 'source' => 'category_performance'];
                    }
                } catch (Throwable $e) {
                    log_warning('CloneROIAnalysisService: category_performance indisponível', [
                        'service' => self::class,
                        'category_id' => $categoryId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['value' => 0.0, 'source' => 'unavailable'];
        } catch (Throwable $e) {
            log_warning('CloneROIAnalysisService: erro ao calcular benchmark de categoria', [
                'service' => self::class,
                'error' => $e->getMessage(),
            ]);
            return ['value' => 0.0, 'source' => 'error'];
        }
    }

    /**
     * Estima tempo até primeira venda
     */
    private function estimateTimeToFirstSale(array $clone): ?int
    {
        if (intval($clone['sales'] ?? 0) === 0) {
            return null;
        }

        // Calcular dias desde clonagem até agora
        $clonedAt = strtotime((string) ($clone['cloned_at'] ?? ''));
        if ($clonedAt === false) {
            return null;
        }
        $daysSinceClone = (time() - $clonedAt) / 86400;

        return intval($daysSinceClone);
    }

    /**
     * Registra métricas de um item
     */
    public function recordMetrics(string $targetItemId, array $metrics): bool
    {
        // Encontrar ID do clone
        $stmt = $this->db->prepare("
            SELECT id FROM cloned_items
            WHERE target_item_id = :item_id AND account_id = :account_id
        ");
        $stmt->execute([
            ':item_id' => $targetItemId,
            ':account_id' => $this->accountId,
        ]);
        $clone = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$clone) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO clone_item_metrics
            (cloned_item_id, visits, sales, revenue, conversion_rate, avg_position, updated_at)
            VALUES
            (:cloned_id, :visits, :sales, :revenue, :conversion, :position, NOW())
            ON DUPLICATE KEY UPDATE
            visits = :visits2,
            sales = :sales2,
            revenue = :revenue2,
            conversion_rate = :conversion2,
            avg_position = :position2,
            updated_at = NOW()
        ");

        $visits = intval($metrics['visits'] ?? 0);
        $sales = intval($metrics['sales'] ?? 0);
        $revenue = floatval($metrics['revenue'] ?? 0);
        $conversion = $visits > 0 ? ($sales / $visits) * 100 : 0;
        $position = $metrics['avg_position'] ?? null;

        return $stmt->execute([
            ':cloned_id' => $clone['id'],
            ':visits' => $visits,
            ':sales' => $sales,
            ':revenue' => $revenue,
            ':conversion' => $conversion,
            ':position' => $position,
            ':visits2' => $visits,
            ':sales2' => $sales,
            ':revenue2' => $revenue,
            ':conversion2' => $conversion,
            ':position2' => $position,
        ]);
    }

    /**
     * Sincroniza métricas da API ML
     */
    public function syncMetricsFromML(int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));

        // Buscar itens clonados recentes sem métricas atualizadas
        $stmt = $this->db->prepare("
            SELECT ci.target_item_id, ci.id
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics cim ON cim.cloned_item_id = ci.id
            WHERE ci.account_id = :account_id
            AND (cim.updated_at IS NULL OR cim.updated_at < DATE_SUB(NOW(), INTERVAL 24 HOUR))
            ORDER BY ci.cloned_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $synced = 0;
        $errors = 0;
        $client = $this->getMlClient();

        foreach ($items as $item) {
            try {
                $itemId = $item['target_item_id'] ?? '';
                if ($itemId === '') {
                    $errors++;
                    continue;
                }

                // 1. Obter visitas reais via ML API
                $visits = 0;
                try {
                    $visitData = $client->get("/items/{$itemId}/visits/time_window", [
                        'last' => 30,
                        'unit' => 'day',
                    ]);
                    // Somar visitas dos últimos 30 dias
                    foreach ($visitData['results'] ?? [] as $dayData) {
                        $visits += intval($dayData['total'] ?? $dayData['visits'] ?? 0);
                    }
                } catch (Throwable $e) {
                    // Fallback: endpoint alternativo
                    try {
                        $visitData = $client->get("/items/{$itemId}/visits", ['date_from' => date('Y-m-d', strtotime('-30 days'))]);
                        $visits = intval($visitData['total_visits'] ?? 0);
                    } catch (Throwable $e2) {
                        log_warning('CloneROIAnalysisService: falha ao obter visitas do item', [
                            'service' => self::class,
                            'item_id' => $itemId,
                            'error' => $e2->getMessage(),
                        ]);
                    }
                }

                // 2. Obter vendas reais via orders/search
                $sales = 0;
                $revenue = 0.0;
                $sellerId = $client->getSellerId();
                if ($sellerId) {
                    try {
                        $ordersData = $client->get('/orders/search', [
                            'seller' => $sellerId,
                            'q' => $itemId,
                            'order.date_created.from' => date('Y-m-d\TH:i:s.000-00:00', strtotime('-30 days')),
                            'sort' => 'date_desc',
                        ]);

                        if (
                            isset($ordersData['error'])
                            && $ordersData['error'] === 'orders_access_unavailable'
                            && ($ordersData['feature'] ?? null) === 'orders'
                            && ($ordersData['optional_feature'] ?? false) === true
                        ) {
                            log_info('CloneROIAnalysisService: orders capability unavailable — skipping sales metrics', [
                                'service' => self::class,
                                'item_id' => $itemId,
                            ]);
                        } else {
                            foreach ($ordersData['results'] ?? [] as $order) {
                                foreach ($order['order_items'] ?? [] as $orderItem) {
                                    if (($orderItem['item']['id'] ?? '') === $itemId) {
                                        $sales += intval($orderItem['quantity'] ?? 1);
                                        $revenue += floatval($orderItem['unit_price'] ?? 0) * intval($orderItem['quantity'] ?? 1);
                                    }
                                }
                            }
                        }
                    } catch (Throwable $e) {
                        log_warning('CloneROIAnalysisService: falha ao obter vendas do item', [
                            'service' => self::class,
                            'item_id' => $itemId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // 3. Obter posição no ranking (buscar na categoria)
                $avgPosition = null;
                try {
                    $itemDetails = $client->get("/items/{$itemId}", [], 600, true);
                    $categoryId = $itemDetails['category_id'] ?? '';
                    $title = $itemDetails['title'] ?? '';
                    if ($categoryId && $title) {
                        $firstWord = explode(' ', $title)[0] ?? '';
                        if ($firstWord !== '') {
                            $searchResults = $client->searchItems([
                                'category' => $categoryId,
                                'q' => $firstWord,
                                'limit' => 50,
                            ]);
                            $position = 1;
                            foreach ($searchResults['results'] ?? [] as $result) {
                                if (($result['id'] ?? '') === $itemId) {
                                    $avgPosition = $position;
                                    break;
                                }
                                $position++;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    log_warning('CloneROIAnalysisService: falha ao estimar posição do item', [
                        'service' => self::class,
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->recordMetrics($itemId, [
                    'visits' => $visits,
                    'sales' => $sales,
                    'revenue' => $revenue,
                    'avg_position' => $avgPosition,
                ]);
                $synced++;
            } catch (Throwable $e) {
                log_warning('CloneROIAnalysisService: erro ao sincronizar métricas', [
                    'service' => self::class,
                    'item_id' => $item['target_item_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }

            usleep(200000); // Rate limit
        }

        return [
            'total_items' => count($items),
            'synced' => $synced,
            'errors' => $errors,
        ];
    }

    /**
     * Obtém instância do MercadoLivreClient (lazy init)
     */
    private function getMlClient(): MercadoLivreClient
    {
        if ($this->mlClient === null) {
            $this->mlClient = new MercadoLivreClient($this->accountId);
        }
        return $this->mlClient;
    }

    /**
     * Obtém ROI por período
     */
    public function getROIByPeriod(int $days = 7): array
    {
        $daysSql = max(1, min(365, (int) $days));
        $stmt = $this->db->prepare("
            SELECT
                DATE(ci.cloned_at) as date,
                COUNT(*) as items_cloned,
                COALESCE(SUM(cim.sales), 0) as total_sales,
                COALESCE(SUM(cim.revenue), 0) as total_revenue,
                COALESCE(AVG(cim.conversion_rate), 0) as avg_conversion
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics cim ON cim.cloned_item_id = ci.id
            WHERE ci.account_id = :account_id
            AND ci.cloned_at >= DATE_SUB(NOW(), INTERVAL {$daysSql} DAY)
            GROUP BY DATE(ci.cloned_at)
            ORDER BY date ASC
        ");
        $stmt->execute([':account_id' => $this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Garante que as tabelas existem
     */
    private function ensureTablesExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_item_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cloned_item_id INT NOT NULL,
                visits INT DEFAULT 0,
                sales INT DEFAULT 0,
                revenue DECIMAL(12,2) DEFAULT 0,
                conversion_rate DECIMAL(5,2) DEFAULT 0,
                avg_position DECIMAL(5,2) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_cloned_item (cloned_item_id),
                INDEX idx_revenue (revenue DESC),
                INDEX idx_sales (sales DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $checked = true;
    }
}
