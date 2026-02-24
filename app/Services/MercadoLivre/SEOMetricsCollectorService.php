<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Database;
use App\Services\MercadoLivreClient;
use PDO;

/**
 * SEO Metrics Collector - Coleta metricas de performance da API do Mercado Livre
 *
 * Pipeline completo:
 * 1. Busca todos os itens ativos de uma conta (DB + API)
 * 2. Obtem visitas via /visits/items (batch de ate 50 IDs)
 * 3. Obtem detalhes (sold_quantity, price) via multi-get /items?ids=...
 * 4. Calcula revenue, conversion_rate
 * 5. Salva em seo_performance_metrics (UPSERT)
 * 6. Marca itens ja otimizados (JOIN seo_optimization_events)
 *
 * Rate limiting: ~1 req/s para endpoints autenticados
 *
 * @version 1.0.0
 */
class SEOMetricsCollectorService
{
    private PDO $db;
    private int $accountId;
    private MercadoLivreClient $mlClient;

    /** @var int Pausa em microsegundos entre chamadas (rate limiting) */
    private int $rateLimitDelayUs = 500000;

    /** @var int Maximo de itens processados por execucao */
    private int $maxItemsPerRun = 500;

    /** @var array<string, int> Estatisticas da execucao */
    private array $stats = [
        'items_found' => 0,
        'items_processed' => 0,
        'metrics_saved' => 0,
        'metrics_updated' => 0,
        'api_calls' => 0,
        'errors' => 0,
        'skipped' => 0,
        'duration_ms' => 0,
    ];

    public function __construct(int $accountId, ?MercadoLivreClient $mlClient = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = $mlClient ?? new MercadoLivreClient($accountId);
    }

    /**
     * Executa a coleta completa de metricas para a conta.
     *
     * @param int $days Periodo de visitas a coletar (max 30)
     * @param bool $verbose Exibir output no terminal
     * @return array<string, int> Estatisticas da execucao
     */
    public function collect(int $days = 1, bool $verbose = false): array
    {
        $startTime = microtime(true);
        $days = min(max(1, $days), 30);

        try {
            $this->ensureTableExists();

            $itemIds = $this->getActiveItemIds($verbose);
            $this->stats['items_found'] = count($itemIds);

            if (empty($itemIds)) {
                if ($verbose) {
                    echo "   [AVISO] Nenhum item ativo encontrado para conta #{$this->accountId}\n";
                }
                return $this->finalize($startTime);
            }

            $itemIds = array_slice($itemIds, 0, $this->maxItemsPerRun);

            if ($verbose) {
                echo "   [INFO] {$this->stats['items_found']} itens encontrados, processando " . count($itemIds) . "\n";
            }

            $allVisits = $this->fetchVisitsBatch($itemIds, $days, $verbose);
            $allDetails = $this->fetchDetailsBatch($itemIds, $verbose);
            $optimizedItems = $this->getOptimizedItemIds();
            $this->saveMetricsBatch($itemIds, $allVisits, $allDetails, $optimizedItems, $days, $verbose);
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            log_error('SEOMetricsCollector: falha na coleta', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if ($verbose) {
                echo "   [ERRO] Erro fatal: {$e->getMessage()}\n";
            }
        }

        return $this->finalize($startTime);
    }

    /**
     * Busca IDs de itens ativos, priorizando DB local, com fallback para API.
     *
     * @return array<string>
     */
    private function getActiveItemIds(bool $verbose = false): array
    {
        $itemIds = [];

        try {
            $stmt = $this->db->prepare("
                SELECT ml_item_id
                FROM items
                WHERE account_id = :account_id
                  AND status IN ('active', 'paused')
                  AND ml_item_id IS NOT NULL
                  AND ml_item_id != ''
                ORDER BY ml_item_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            // Tabela items pode nao existir ainda
        }

        if (empty($itemIds)) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id
                    FROM ml_items
                    WHERE account_id = :account_id
                      AND status IN ('active', 'paused')
                    ORDER BY id
                ");
                $stmt->execute(['account_id' => $this->accountId]);
                $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (\Throwable $e) {
                // Tabela ml_items pode nao existir
            }
        }

        if (empty($itemIds)) {
            if ($verbose) {
                echo "   [INFO] Sem itens no DB local - buscando na API ML...\n";
            }
            $itemIds = $this->fetchAllItemIdsFromApi();
        }

        return $itemIds;
    }

    /**
     * Busca todos os IDs de itens ativos via API ML (paginado).
     *
     * @return array<string>
     */
    private function fetchAllItemIdsFromApi(): array
    {
        $allIds = [];
        $offset = 0;
        $limit = 100;
        $total = 0;

        do {
            try {
                $data = $this->mlClient->getMyItems([
                    'status' => 'active',
                    'limit' => $limit,
                    'offset' => $offset,
                ]);
                $this->stats['api_calls']++;

                if (isset($data['error'])) {
                    log_warning('SEOMetricsCollector: erro ao listar itens da API', [
                        'error' => $data['error'],
                        'account_id' => $this->accountId,
                    ]);
                    break;
                }

                $ids = $data['results'] ?? [];
                $total = $data['paging']['total'] ?? 0;
                $allIds = array_merge($allIds, $ids);
                $offset += $limit;

                usleep($this->rateLimitDelayUs);
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                log_error('SEOMetricsCollector: falha na paginacao de itens', [
                    'offset' => $offset,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        } while ($offset < $total && count($allIds) < $this->maxItemsPerRun);

        return $allIds;
    }

    /**
     * Obtem visitas de todos os itens em batches de 50.
     *
     * @param array<string> $itemIds
     * @param int $days
     * @return array<string, array{total: int, visits: int, daily: array}>
     */
    private function fetchVisitsBatch(array $itemIds, int $days, bool $verbose = false): array
    {
        if ($verbose) {
            echo "   [INFO] Buscando visitas para " . count($itemIds) . " itens...\n";
        }

        $allVisits = $this->mlClient->getMultiItemVisits($itemIds, $days);
        $this->stats['api_calls'] += (int)ceil(count($itemIds) / 50);

        $withVisits = count(array_filter($allVisits, fn(array $v): bool => $v['total'] > 0));
        if ($verbose) {
            echo "   [OK] Visitas obtidas - {$withVisits} itens com trafego\n";
        }

        return $allVisits;
    }

    /**
     * Obtem detalhes de todos os itens em batches de 20.
     *
     * @param array<string> $itemIds
     * @return array<string, array>
     */
    private function fetchDetailsBatch(array $itemIds, bool $verbose = false): array
    {
        if ($verbose) {
            echo "   [INFO] Buscando detalhes para " . count($itemIds) . " itens...\n";
        }

        $allDetails = $this->mlClient->getMultiItemDetails($itemIds, [
            'id',
            'title',
            'price',
            'sold_quantity',
            'status',
            'category_id',
            'available_quantity',
            'thumbnail',
        ]);
        $this->stats['api_calls'] += (int)ceil(count($itemIds) / 20);

        if ($verbose) {
            echo "   [OK] Detalhes obtidos para " . count($allDetails) . " itens\n";
        }

        return $allDetails;
    }

    /**
     * Retorna set de item IDs que ja foram otimizados pelo SEO Killer.
     *
     * @return array<string, true>
     */
    private function getOptimizedItemIds(): array
    {
        $optimized = [];
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT item_id
                FROM seo_optimization_events
                WHERE account_id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                $optimized[$id] = true;
            }
        } catch (\Throwable $e) {
            // Tabela pode nao existir
        }
        return $optimized;
    }

    /**
     * Salva metricas em batch via INSERT ... ON DUPLICATE KEY UPDATE.
     *
     * @param array<string> $itemIds
     * @param array<string, array> $allVisits
     * @param array<string, array> $allDetails
     * @param array<string, true> $optimizedItems
     * @param int $days
     * @param bool $verbose
     */
    private function saveMetricsBatch(
        array $itemIds,
        array $allVisits,
        array $allDetails,
        array $optimizedItems,
        int $days,
        bool $verbose = false
    ): void {
        $today = date('Y-m-d');

        $stmt = $this->db->prepare("
            INSERT INTO seo_performance_metrics
                (account_id, item_id, metric_date, views, visits, sold_quantity, revenue, conversion_rate, was_optimized, optimization_date)
            VALUES
                (:account_id, :item_id, :metric_date, :views, :visits, :sold_quantity, :revenue, :conversion_rate, :was_optimized, :optimization_date)
            ON DUPLICATE KEY UPDATE
                views = VALUES(views),
                visits = VALUES(visits),
                sold_quantity = VALUES(sold_quantity),
                revenue = VALUES(revenue),
                conversion_rate = VALUES(conversion_rate),
                was_optimized = VALUES(was_optimized),
                optimization_date = COALESCE(VALUES(optimization_date), optimization_date)
        ");

        $saved = 0;

        foreach ($itemIds as $itemId) {
            try {
                $visits = $allVisits[$itemId] ?? ['total' => 0, 'visits' => 0, 'daily' => []];
                $detail = $allDetails[$itemId] ?? [];

                $soldQty = (int)($detail['sold_quantity'] ?? 0);
                $price = (float)($detail['price'] ?? 0);
                $revenue = $soldQty * $price;
                $viewCount = max($visits['total'], $visits['visits']);
                $conversionRate = $viewCount > 0 ? round(($soldQty / $viewCount) * 100, 2) : 0.0;
                $wasOptimized = isset($optimizedItems[$itemId]) ? 1 : 0;

                if ($days > 1 && !empty($visits['daily'])) {
                    foreach ($visits['daily'] as $date => $dailyViews) {
                        $stmt->execute([
                            'account_id' => $this->accountId,
                            'item_id' => $itemId,
                            'metric_date' => $date,
                            'views' => $dailyViews,
                            'visits' => $dailyViews,
                            'sold_quantity' => $soldQty,
                            'revenue' => $revenue,
                            'conversion_rate' => $conversionRate,
                            'was_optimized' => $wasOptimized,
                            'optimization_date' => $wasOptimized ? $today : null,
                        ]);
                        $saved++;
                    }
                } else {
                    $stmt->execute([
                        'account_id' => $this->accountId,
                        'item_id' => $itemId,
                        'metric_date' => $today,
                        'views' => $viewCount,
                        'visits' => $viewCount,
                        'sold_quantity' => $soldQty,
                        'revenue' => $revenue,
                        'conversion_rate' => $conversionRate,
                        'was_optimized' => $wasOptimized,
                        'optimization_date' => $wasOptimized ? $today : null,
                    ]);
                    $saved++;
                }

                $this->stats['items_processed']++;
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                log_error('SEOMetricsCollector: falha ao salvar metrica', [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->stats['metrics_saved'] = $saved;

        if ($verbose) {
            echo "   [DB] {$saved} registros salvos em seo_performance_metrics\n";
        }
    }

    /**
     * Garante que a tabela existe (sem conflitar com migrations).
     */
    private function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_performance_metrics (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    account_id INT NOT NULL,
                    item_id VARCHAR(50) NOT NULL,
                    metric_date DATE NOT NULL,
                    views INT DEFAULT 0,
                    visits INT DEFAULT 0,
                    sold_quantity INT DEFAULT 0,
                    revenue DECIMAL(12,2) DEFAULT 0,
                    conversion_rate DECIMAL(5,2) DEFAULT 0,
                    position_avg DECIMAL(5,2) DEFAULT 0,
                    was_optimized TINYINT(1) DEFAULT 0,
                    optimization_date DATE NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_item_date (item_id, metric_date),
                    INDEX idx_account (account_id),
                    INDEX idx_item (item_id),
                    INDEX idx_date (metric_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\PDOException $e) {
            // Tabela provavelmente ja existe
        }
    }

    /**
     * Finaliza execucao e retorna estatisticas.
     */
    private function finalize(float $startTime): array
    {
        $this->stats['duration_ms'] = (int)((microtime(true) - $startTime) * 1000);

        log_info('SEOMetricsCollector: coleta concluida', [
            'account_id' => $this->accountId,
            'stats' => $this->stats,
        ]);

        return $this->stats;
    }

    /**
     * Coleta metricas para TODAS as contas ativas.
     *
     * @param int $days Periodo de visitas
     * @param bool $verbose Exibir output
     * @return array<int, array> Estatisticas por conta
     */
    public static function collectAllAccounts(int $days = 1, bool $verbose = false): array
    {
        $db = Database::getInstance();
        $results = [];

        try {
            $stmt = $db->query("
                SELECT id, nickname
                FROM ml_accounts
                WHERE status = 'active'
                ORDER BY id
            ");
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            if ($verbose) {
                echo "[ERRO] Tabela ml_accounts inacessivel: {$e->getMessage()}\n";
            }
            return $results;
        }

        if (empty($accounts)) {
            if ($verbose) {
                echo "[AVISO] Nenhuma conta ML ativa encontrada.\n";
            }
            return $results;
        }

        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];
            $nickname = $account['nickname'] ?? "#{$accountId}";

            if ($verbose) {
                echo "\n-- [{$nickname}] Coletando metricas SEO --\n";
            }

            try {
                $collector = new self($accountId);
                $stats = $collector->collect($days, $verbose);
                $results[$accountId] = $stats;
            } catch (\Throwable $e) {
                $results[$accountId] = ['error' => $e->getMessage()];
                if ($verbose) {
                    echo "   [ERRO] Falha: {$e->getMessage()}\n";
                }
            }
        }

        return $results;
    }

    /**
     * Define o limite de itens por execucao.
     */
    public function setMaxItemsPerRun(int $max): self
    {
        $this->maxItemsPerRun = max(1, $max);
        return $this;
    }

    /**
     * Define o delay entre chamadas de API (rate limiting).
     *
     * @param int $microseconds Delay em microsegundos
     */
    public function setRateLimitDelay(int $microseconds): self
    {
        $this->rateLimitDelayUs = max(0, $microseconds);
        return $this;
    }

    /**
     * Retorna as estatisticas da ultima execucao.
     */
    public function getStats(): array
    {
        return $this->stats;
    }
}
