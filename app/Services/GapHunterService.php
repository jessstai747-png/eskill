<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\StructuredLogService;
use PDO;

/**
 * Service para análise de lacunas (gaps) de mercado em categorias do Mercado Livre.
 *
 * Identifica oportunidades de nicho analisando oferta vs demanda (trends) em
 * uma categoria. Resultados são persistidos na tabela `gap_trend_snapshots`.
 */
class GapHunterService
{
    private PDO $db;
    private MercadoLivreClient $client;
    private string $siteId;
    private StructuredLogService $logger;

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->logger = new StructuredLogService();
        $this->ensureTableExists();

        $config = \App\Core\Config::getInstance()->all();
        $this->siteId = $config['mercadolivre']['site_id'] ?? 'MLB';
        $this->client = new MercadoLivreClient($accountId);
    }

    /**
     * Analisa gaps de mercado para uma categoria.
     *
     * @param string $categoryId  ID da categoria ML (ex: "MLB1234")
     * @param array  $options     Opções extras: limit, min_gap_score, keywords
     * @return array Resultado da análise com gaps encontrados
     */
    public function analyzeCategory(string $categoryId, array $options = []): array
    {
        $limit = (int) ($options['limit'] ?? 50);
        $minGapScore = (float) ($options['min_gap_score'] ?? 0.3);

        // 1. Obter trends/keywords da categoria
        $trends = $this->getCategoryTrends($categoryId);

        // 2. Buscar oferta atual
        $supply = $this->getSupplyData($categoryId, $limit);

        // 3. Calcular gaps
        $gaps = $this->calculateGaps($trends, $supply, $minGapScore);

        // 4. Persistir snapshot
        $this->saveSnapshot($categoryId, $gaps);

        return [
            'success' => true,
            'category_id' => $categoryId,
            'total_trends' => count($trends),
            'total_supply' => $supply['total'] ?? 0,
            'gaps_found' => count($gaps),
            'gaps' => $gaps,
            'analyzed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Retorna tendências/keywords populares para a categoria via API de trends.
     */
    private function getCategoryTrends(string $categoryId): array
    {
        try {
            $response = $this->client->get("/trends/{$this->siteId}/{$categoryId}");

            if (isset($response['error'])) {
                return [];
            }

            $trends = [];
            foreach ($response as $item) {
                if (isset($item['keyword'])) {
                    $trends[] = [
                        'keyword' => $item['keyword'],
                        'url' => $item['url'] ?? '',
                    ];
                }
            }

            return $trends;
        } catch (\Exception $e) {
            $this->logger->error('Erro ao buscar trends da categoria', [
                'service' => 'GapHunterService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Busca oferta atual na categoria (quantidade de itens, preço médio, etc.).
     */
    private function getSupplyData(string $categoryId, int $limit): array
    {
        try {
            $response = $this->client->get("/sites/{$this->siteId}/search", [
                'category' => $categoryId,
                'limit' => min($limit, 50),
                'offset' => 0,
            ]);

            if (isset($response['error'])) {
                return ['total' => 0, 'items' => [], 'prices' => []];
            }

            $prices = [];
            foreach (($response['results'] ?? []) as $item) {
                if (isset($item['price'])) {
                    $prices[] = (float) $item['price'];
                }
            }

            return [
                'total' => $response['paging']['total'] ?? 0,
                'items' => $response['results'] ?? [],
                'prices' => $prices,
                'avg_price' => !empty($prices) ? round(array_sum($prices) / count($prices), 2) : 0,
                'std_dev' => !empty($prices) ? round($this->stdDev($prices), 2) : 0,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Erro ao buscar oferta da categoria', [
                'service' => 'GapHunterService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'items' => [], 'prices' => []];
        }
    }

    /**
     * Calcula gaps comparando trends com oferta.
     *
     * Gap score alto = keyword tem demanda mas baixa oferta relativa.
     */
    private function calculateGaps(array $trends, array $supply, float $minGapScore): array
    {
        if (empty($trends)) {
            return [];
        }

        $supplyTotal = $supply['total'] ?? 0;
        $avgPrice = $supply['avg_price'] ?? 0;
        $stdDev = $supply['std_dev'] ?? 0;
        $gaps = [];

        foreach ($trends as $index => $trend) {
            $keyword = $trend['keyword'] ?? '';
            if (empty($keyword)) {
                continue;
            }

            // Buscar quantos itens existem para essa keyword específica
            $keywordSupply = $this->getKeywordSupplyCount($keyword);

            // Gap score: quanto menor a oferta relativa à posição no trend, maior o gap
            $trendPosition = $index + 1;
            $trendWeight = 1 / $trendPosition; // Keywords mais populares = peso maior

            if ($supplyTotal > 0 && $keywordSupply > 0) {
                $supplyRatio = $keywordSupply / $supplyTotal;
                $gapScore = round($trendWeight * (1 - $supplyRatio), 4);
            } else {
                $gapScore = round($trendWeight, 4);
            }

            if ($gapScore >= $minGapScore) {
                $gaps[] = [
                    'keyword' => $keyword,
                    'gap_score' => $gapScore,
                    'supply_count' => $keywordSupply,
                    'price_avg' => $avgPrice,
                    'price_std_dev' => $stdDev,
                    'trend_position' => $trendPosition,
                ];
            }
        }

        // Ordenar por gap_score decrescente
        usort($gaps, fn($a, $b) => $b['gap_score'] <=> $a['gap_score']);

        return $gaps;
    }

    /**
     * Retorna quantidade de itens ativos para uma keyword.
     */
    private function getKeywordSupplyCount(string $keyword): int
    {
        try {
            $response = $this->client->get("/sites/{$this->siteId}/search", [
                'q' => $keyword,
                'limit' => 1,
            ]);

            return $response['paging']['total'] ?? 0;
        } catch (\Exception $e) {
            $this->logger->warning('Erro ao buscar supply count para keyword', [
                'service' => 'GapHunterService',
                'keyword' => $keyword,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }

    /**
     * Persiste os gaps encontrados na tabela gap_trend_snapshots.
     */
    private function saveSnapshot(string $categoryId, array $gaps): void
    {
        if (empty($gaps)) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO gap_trend_snapshots
                (category_id, keyword, gap_score, price_avg, price_std_dev, supply_count, created_at)
            VALUES
                (:category_id, :keyword, :gap_score, :price_avg, :price_std_dev, :supply_count, NOW())
        ");

        foreach ($gaps as $gap) {
            $stmt->execute([
                'category_id' => $categoryId,
                'keyword' => $gap['keyword'],
                'gap_score' => $gap['gap_score'],
                'price_avg' => $gap['price_avg'] ?? 0,
                'price_std_dev' => $gap['price_std_dev'] ?? 0,
                'supply_count' => $gap['supply_count'] ?? 0,
            ]);
        }
    }

    /**
     * Retorna snapshots históricos de gap para uma categoria.
     */
    public function getHistory(string $categoryId, int $limit = 50): array
    {
        $limitSql = max(1, min(365, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT id, category_id, keyword, gap_score, price_avg, price_std_dev, supply_count, created_at
            FROM gap_trend_snapshots
            WHERE category_id = :category_id
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue('category_id', $categoryId);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula desvio padrão de um array de valores.
     */
    private function stdDev(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $n;
        $sumSquares = 0.0;

        foreach ($values as $v) {
            $sumSquares += ($v - $mean) ** 2;
        }

        return sqrt($sumSquares / ($n - 1));
    }

    /**
     * Garante que a tabela existe (idempotente).
     */
    private function ensureTableExists(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS gap_trend_snapshots (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category_id VARCHAR(50) NOT NULL,
                keyword VARCHAR(255) NOT NULL,
                gap_score FLOAT NOT NULL,
                price_avg DECIMAL(10,2) DEFAULT 0.00,
                price_std_dev DECIMAL(10,2) DEFAULT 0.00,
                supply_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cat_date (category_id, created_at),
                INDEX idx_keyword (keyword)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
