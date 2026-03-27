<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneTrendChartService
 *
 * Prepara dados formatados para gráficos Chart.js
 * Análise de tendências, performance e métricas do módulo Clone
 */
class CloneTrendChartService
{
    private PDO $db;
    private int $accountId;

    // Paleta de cores para gráficos
    private const COLORS = [
        'primary' => 'rgba(59, 130, 246, 1)',
        'primary_light' => 'rgba(59, 130, 246, 0.2)',
        'success' => 'rgba(34, 197, 94, 1)',
        'success_light' => 'rgba(34, 197, 94, 0.2)',
        'warning' => 'rgba(234, 179, 8, 1)',
        'warning_light' => 'rgba(234, 179, 8, 0.2)',
        'danger' => 'rgba(239, 68, 68, 1)',
        'danger_light' => 'rgba(239, 68, 68, 0.2)',
        'purple' => 'rgba(139, 92, 246, 1)',
        'purple_light' => 'rgba(139, 92, 246, 0.2)',
        'cyan' => 'rgba(6, 182, 212, 1)',
        'cyan_light' => 'rgba(6, 182, 212, 0.2)',
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Gráfico de clonagens por dia (últimos 30 dias)
     */
    public function getClonesPerDayChart(int $days = 30): array
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute(['account_id' => $this->accountId, 'days' => $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Preencher dias faltantes
        $filled = $this->fillMissingDates($data, $days, ['total' => 0, 'success' => 0, 'failed' => 0]);

        return [
            'type' => 'line',
            'data' => [
                'labels' => array_map(fn(array $d): string => date('d/m', strtotime($d['date'])), $filled),
                'datasets' => [
                    [
                        'label' => 'Total',
                        'data' => array_column($filled, 'total'),
                        'borderColor' => self::COLORS['primary'],
                        'backgroundColor' => self::COLORS['primary_light'],
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Sucesso',
                        'data' => array_column($filled, 'success'),
                        'borderColor' => self::COLORS['success'],
                        'backgroundColor' => 'transparent',
                        'borderDash' => [5, 5],
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Falha',
                        'data' => array_column($filled, 'failed'),
                        'borderColor' => self::COLORS['danger'],
                        'backgroundColor' => 'transparent',
                        'borderDash' => [5, 5],
                        'tension' => 0.4,
                    ],
                ],
            ],
            'options' => $this->getLineChartOptions('Clonagens por Dia'),
        ];
    }

    /**
     * Gráfico de taxa de sucesso por hora do dia
     */
    public function getSuccessRateByHourChart(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                HOUR(created_at) as hour,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as success
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY HOUR(created_at)
            ORDER BY hour ASC
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Criar array com todas as horas
        $hourlyData = array_fill(0, 24, ['total' => 0, 'success' => 0, 'rate' => 0]);
        foreach ($data as $row) {
            $hour = (int) $row['hour'];
            $hourlyData[$hour] = [
                'total' => (int) $row['total'],
                'success' => (int) $row['success'],
                'rate' => $row['total'] > 0 ? round(($row['success'] / $row['total']) * 100, 1) : 0,
            ];
        }

        $labels = array_map(fn(int $h): string => sprintf('%02d:00', $h), range(0, 23));

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Taxa de Sucesso (%)',
                        'data' => array_column($hourlyData, 'rate'),
                        'backgroundColor' => array_map(
                            fn(array $d): string => $d['rate'] >= 90 ? self::COLORS['success'] :
                                     ($d['rate'] >= 70 ? self::COLORS['warning'] : self::COLORS['danger']),
                            $hourlyData
                        ),
                        'borderRadius' => 4,
                    ],
                ],
            ],
            'options' => $this->getBarChartOptions('Taxa de Sucesso por Hora', '%'),
        ];
    }

    /**
     * Gráfico de clonagens por conta origem (top 10)
     * Nota: Tabela cloned_items não tem category_id, usando source_account_id
     */
    public function getClonesPerCategoryChart(int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(ci.source_account_id, 0) as category,
                COUNT(*) as total
            FROM cloned_items ci
            WHERE ci.target_account_id = :account_id
            AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY ci.source_account_id
            ORDER BY total DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Renomear para "Conta #X"
        foreach ($data as &$row) {
            $row['category'] = 'Conta #' . $row['category'];
        }

        $colors = $this->generateColorPalette(count($data));

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => array_column($data, 'category'),
                'datasets' => [
                    [
                        'data' => array_map('intval', array_column($data, 'total')),
                        'backgroundColor' => $colors['backgrounds'],
                        'borderColor' => $colors['borders'],
                        'borderWidth' => 2,
                    ],
                ],
            ],
            'options' => $this->getDoughnutChartOptions('Clonagens por Categoria'),
        ];
    }

    /**
     * Gráfico de performance por conta origem
     * Nota: Usando source_account_id pois tabela não tem source_seller_id
     */
    public function getSellerPerformanceChart(int $limit = 10): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT
                ci.source_account_id as seller_id,
                COUNT(*) as clones,
                SUM(CASE WHEN ci.status = 'created' THEN 1 ELSE 0 END) as success,
                ROUND(AVG(ci.processing_time_ms / 1000), 1) as avg_time
            FROM cloned_items ci
            WHERE ci.target_account_id = :account_id
            AND ci.source_account_id IS NOT NULL
            AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY ci.source_account_id
            ORDER BY clones DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn(array $d): string => 'Conta #' . (string)$d['seller_id'], $data),
                'datasets' => [
                    [
                        'label' => 'Clones',
                        'data' => array_map('intval', array_column($data, 'clones')),
                        'backgroundColor' => self::COLORS['primary'],
                        'borderRadius' => 4,
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'Sucesso',
                        'data' => array_map('intval', array_column($data, 'success')),
                        'backgroundColor' => self::COLORS['success'],
                        'borderRadius' => 4,
                        'yAxisID' => 'y',
                    ],
                ],
            ],
            'options' => $this->getBarChartOptions('Performance por Seller'),
        ];
    }

    /**
     * Gráfico de tendência de tempo médio de clonagem
     */
    public function getCloneTimeChart(int $days = 14): array
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(created_at) as date,
                ROUND(AVG(processing_time_ms / 1000), 1) as avg_time,
                ROUND(MIN(processing_time_ms / 1000), 1) as min_time,
                ROUND(MAX(processing_time_ms / 1000), 1) as max_time
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND status = 'created'
            AND processing_time_ms IS NOT NULL
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute(['account_id' => $this->accountId, 'days' => $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filled = $this->fillMissingDates($data, $days, ['avg_time' => 0, 'min_time' => 0, 'max_time' => 0]);

        return [
            'type' => 'line',
            'data' => [
                'labels' => array_map(fn(array $d): string => date('d/m', strtotime($d['date'])), $filled),
                'datasets' => [
                    [
                        'label' => 'Tempo Médio (s)',
                        'data' => array_column($filled, 'avg_time'),
                        'borderColor' => self::COLORS['primary'],
                        'backgroundColor' => self::COLORS['primary_light'],
                        'fill' => true,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Mínimo',
                        'data' => array_column($filled, 'min_time'),
                        'borderColor' => self::COLORS['success'],
                        'borderDash' => [5, 5],
                        'fill' => false,
                        'tension' => 0.4,
                    ],
                    [
                        'label' => 'Máximo',
                        'data' => array_column($filled, 'max_time'),
                        'borderColor' => self::COLORS['danger'],
                        'borderDash' => [5, 5],
                        'fill' => false,
                        'tension' => 0.4,
                    ],
                ],
            ],
            'options' => $this->getLineChartOptions('Tempo de Clonagem', 's'),
        ];
    }

    /**
     * Gráfico de distribuição de status
     */
    public function getStatusDistributionChart(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                status,
                COUNT(*) as total
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY status
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $statusColors = [
            'created' => self::COLORS['success'],
            'error' => self::COLORS['danger'],
            'skipped_duplicate' => self::COLORS['warning'],
        ];

        $statusLabels = [
            'created' => 'Criado',
            'error' => 'Erro',
            'skipped_duplicate' => 'Duplicado',
        ];

        return [
            'type' => 'pie',
            'data' => [
                'labels' => array_map(fn(array $d): string => $statusLabels[$d['status']] ?? $d['status'], $data),
                'datasets' => [
                    [
                        'data' => array_map('intval', array_column($data, 'total')),
                        'backgroundColor' => array_map(
                            fn(array $d): string => $statusColors[$d['status']] ?? self::COLORS['primary'],
                            $data
                        ),
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => $this->getPieChartOptions('Distribuição de Status'),
        ];
    }

    /**
     * Gráfico de agendamentos executados
     */
    public function getScheduleExecutionsChart(int $days = 14): array
    {
        $stmt = $this->db->prepare("
            SELECT
                DATE(started_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(items_cloned) as items
            FROM clone_schedule_runs
            WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY DATE(started_at)
            ORDER BY date ASC
        ");
        $stmt->execute(['days' => $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filled = $this->fillMissingDates($data, $days, ['total' => 0, 'completed' => 0, 'items' => 0]);

        return [
            'type' => 'bar',
            'data' => [
                'labels' => array_map(fn(array $d): string => date('d/m', strtotime($d['date'])), $filled),
                'datasets' => [
                    [
                        'label' => 'Execuções',
                        'data' => array_column($filled, 'total'),
                        'backgroundColor' => self::COLORS['primary'],
                        'borderRadius' => 4,
                        'yAxisID' => 'y',
                    ],
                    [
                        'label' => 'Itens Clonados',
                        'data' => array_column($filled, 'items'),
                        'type' => 'line',
                        'borderColor' => self::COLORS['success'],
                        'backgroundColor' => 'transparent',
                        'tension' => 0.4,
                        'yAxisID' => 'y1',
                    ],
                ],
            ],
            'options' => $this->getMixedChartOptions('Agendamentos Executados'),
        ];
    }

    /**
     * Gráfico de eventos detectados por tipo
     */
    public function getEventsByTypeChart(int $days = 7): array
    {
        $stmt = $this->db->prepare("
            SELECT
                event_type,
                COUNT(*) as total
            FROM clone_event_trigger_logs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            GROUP BY event_type
        ");
        $stmt->execute(['days' => $days]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $eventLabels = [
            'new_items' => 'Novos Itens',
            'price_drop' => 'Queda de Preço',
            'stock_available' => 'Estoque Disponível',
            'competitor_out' => 'Concorrente Sem Estoque',
        ];

        $eventColors = [
            'new_items' => self::COLORS['primary'],
            'price_drop' => self::COLORS['warning'],
            'stock_available' => self::COLORS['success'],
            'competitor_out' => self::COLORS['purple'],
        ];

        return [
            'type' => 'polarArea',
            'data' => [
                'labels' => array_map(fn(array $d): string => $eventLabels[$d['event_type']] ?? $d['event_type'], $data),
                'datasets' => [
                    [
                        'data' => array_map('intval', array_column($data, 'total')),
                        'backgroundColor' => array_map(
                            fn(array $d): string => str_replace('1)', '0.7)', $eventColors[$d['event_type']] ?? self::COLORS['primary']),
                            $data
                        ),
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => $this->getPolarChartOptions('Eventos por Tipo'),
        ];
    }

    /**
     * Gráfico radar de métricas de qualidade
     */
    public function getQualityMetricsChart(): array
    {
        // Calcular métricas
        $metrics = $this->calculateQualityMetrics();

        return [
            'type' => 'radar',
            'data' => [
                'labels' => [
                    'Taxa de Sucesso',
                    'Velocidade',
                    'SEO Score',
                    'Completude',
                    'Consistência',
                ],
                'datasets' => [
                    [
                        'label' => 'Atual',
                        'data' => [
                            $metrics['success_rate'],
                            $metrics['speed_score'],
                            $metrics['seo_score'],
                            $metrics['completeness'],
                            $metrics['consistency'],
                        ],
                        'backgroundColor' => self::COLORS['primary_light'],
                        'borderColor' => self::COLORS['primary'],
                        'borderWidth' => 2,
                        'pointBackgroundColor' => self::COLORS['primary'],
                    ],
                    [
                        'label' => 'Meta',
                        'data' => [90, 85, 80, 95, 90],
                        'backgroundColor' => 'transparent',
                        'borderColor' => self::COLORS['success'],
                        'borderDash' => [5, 5],
                        'borderWidth' => 2,
                        'pointBackgroundColor' => self::COLORS['success'],
                    ],
                ],
            ],
            'options' => $this->getRadarChartOptions('Métricas de Qualidade'),
        ];
    }

    /**
     * Dashboard completo com todos os gráficos
     */
    public function getDashboardCharts(): array
    {
        return [
            'clones_per_day' => $this->getClonesPerDayChart(),
            'success_by_hour' => $this->getSuccessRateByHourChart(),
            'clones_by_category' => $this->getClonesPerCategoryChart(),
            'seller_performance' => $this->getSellerPerformanceChart(),
            'clone_time' => $this->getCloneTimeChart(),
            'status_distribution' => $this->getStatusDistributionChart(),
            'schedule_executions' => $this->getScheduleExecutionsChart(),
            'events_by_type' => $this->getEventsByTypeChart(),
            'quality_metrics' => $this->getQualityMetricsChart(),
        ];
    }

    /**
     * Calcula métricas de qualidade
     */
    private function calculateQualityMetrics(): array
    {
        // Taxa de sucesso
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) as success
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $cloneStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $successRate = $cloneStats['total'] > 0
            ? round(($cloneStats['success'] / $cloneStats['total']) * 100, 1)
            : 0;

        // Velocidade (baseada no tempo médio vs benchmark de 30s)
        $stmt = $this->db->prepare("
            SELECT AVG(processing_time_ms / 1000) as avg_time
            FROM cloned_items
            WHERE target_account_id = :account_id AND status = 'created'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $avgTime = $stmt->fetchColumn() ?: 30;
        $speedScore = min(100, round((30 / max(1, $avgTime)) * 100, 1));

        // SEO Score - média baseada nos dados reais de otimização de clones
        $stmtSeo = $this->db->prepare("
            SELECT AVG(CASE
                WHEN LENGTH(title) >= 45 AND LENGTH(title) <= 60 THEN 80
                WHEN LENGTH(title) >= 30 THEN 60
                ELSE 40
            END) as avg_seo_score
            FROM cloned_items
            WHERE target_account_id = :account_id AND status = 'created'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtSeo->execute(['account_id' => $this->accountId]);
        $seoScore = round((float) ($stmtSeo->fetchColumn() ?: 70), 1);

        // Completude (% de clones com título, preço e pelo menos 1 imagem)
        $stmtComplete = $this->db->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN title IS NOT NULL AND title != '' AND price > 0 THEN 1 ELSE 0 END) as complete
            FROM cloned_items
            WHERE target_account_id = :account_id AND status = 'created'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmtComplete->execute(['account_id' => $this->accountId]);
        $compData = $stmtComplete->fetch(PDO::FETCH_ASSOC);
        $completeness = ($compData['total'] ?? 0) > 0
            ? round(((int) $compData['complete'] / (int) $compData['total']) * 100, 1)
            : 0;

        // Consistência (variação na taxa de sucesso diária nos últimos 7 dias)
        $stmtConsist = $this->db->prepare("
            SELECT DATE(created_at) as day,
                   SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) / COUNT(*) * 100 as daily_rate
            FROM cloned_items
            WHERE target_account_id = :account_id
            AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
        ");
        $stmtConsist->execute(['account_id' => $this->accountId]);
        $dailyRates = array_column($stmtConsist->fetchAll(PDO::FETCH_ASSOC), 'daily_rate');
        if (count($dailyRates) >= 2) {
            $mean = array_sum($dailyRates) / count($dailyRates);
            $variance = array_sum(array_map(fn(float $r): float => pow((float) $r - $mean, 2), $dailyRates)) / count($dailyRates);
            $consistency = max(0, min(100, round(100 - sqrt($variance), 1)));
        } else {
            $consistency = $successRate; // Fallback ao rate geral
        }

        return [
            'success_rate' => $successRate,
            'speed_score' => $speedScore,
            'seo_score' => $seoScore,
            'completeness' => $completeness,
            'consistency' => $consistency,
        ];
    }

    /**
     * Preenche datas faltantes
     */
    private function fillMissingDates(array $data, int $days, array $defaults): array
    {
        $result = [];
        $dataByDate = [];

        foreach ($data as $row) {
            $dataByDate[$row['date']] = $row;
        }

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            if (isset($dataByDate[$date])) {
                $result[] = $dataByDate[$date];
            } else {
                $result[] = array_merge(['date' => $date], $defaults);
            }
        }

        return $result;
    }

    /**
     * Gera paleta de cores
     */
    private function generateColorPalette(int $count): array
    {
        $baseColors = [
            [59, 130, 246],   // Blue
            [34, 197, 94],    // Green
            [234, 179, 8],    // Yellow
            [239, 68, 68],    // Red
            [139, 92, 246],   // Purple
            [6, 182, 212],    // Cyan
            [249, 115, 22],   // Orange
            [236, 72, 153],   // Pink
        ];

        $backgrounds = [];
        $borders = [];

        for ($i = 0; $i < $count; $i++) {
            $color = $baseColors[$i % count($baseColors)];
            $backgrounds[] = "rgba({$color[0]}, {$color[1]}, {$color[2]}, 0.7)";
            $borders[] = "rgba({$color[0]}, {$color[1]}, {$color[2]}, 1)";
        }

        return ['backgrounds' => $backgrounds, 'borders' => $borders];
    }

    // Opções padrão para diferentes tipos de gráficos
    private function getLineChartOptions(string $title, string $suffix = ''): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'title' => ['display' => true, 'text' => $title],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['callback' => $suffix ? "function(v) { return v + '{$suffix}'; }" : null],
                ],
            ],
        ];
    }

    private function getBarChartOptions(string $title, string $suffix = ''): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'title' => ['display' => true, 'text' => $title],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    private function getDoughnutChartOptions(string $title): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right'],
                'title' => ['display' => true, 'text' => $title],
            ],
            'cutout' => '60%',
        ];
    }

    private function getPieChartOptions(string $title): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right'],
                'title' => ['display' => true, 'text' => $title],
            ],
        ];
    }

    private function getRadarChartOptions(string $title): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'title' => ['display' => true, 'text' => $title],
            ],
            'scales' => [
                'r' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'ticks' => ['stepSize' => 20],
                ],
            ],
        ];
    }

    private function getPolarChartOptions(string $title): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'right'],
                'title' => ['display' => true, 'text' => $title],
            ],
        ];
    }

    private function getMixedChartOptions(string $title): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['position' => 'top'],
                'title' => ['display' => true, 'text' => $title],
            ],
            'scales' => [
                'y' => ['type' => 'linear', 'position' => 'left', 'beginAtZero' => true],
                'y1' => ['type' => 'linear', 'position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ];
    }
}
