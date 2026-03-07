<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de Monitoramento de Concorrentes
 *
 * Monitora preços de concorrentes em tempo real, analisa tendências
 * de mercado e gera alertas quando detecta movimentações importantes.
 */
class CompetitorMonitorService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->ensureTablesExist();
    }

    /**
     * Cria as tabelas necessárias se não existirem
     */
    private function ensureTablesExist(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_competitors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                competitor_id VARCHAR(50) NOT NULL,
                competitor_seller_id BIGINT,
                competitor_title VARCHAR(500),
                competitor_price DECIMAL(12,2),
                competitor_original_price DECIMAL(12,2),
                competitor_condition VARCHAR(20),
                competitor_shipping_free TINYINT(1) DEFAULT 0,
                competitor_seller_reputation VARCHAR(50),
                competitor_sold_quantity INT DEFAULT 0,
                competitor_available_quantity INT DEFAULT 0,
                position_in_search INT,
                last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_competitor (account_id, item_id, competitor_id),
                INDEX idx_account_item (account_id, item_id),
                INDEX idx_last_checked (last_checked)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_competitor_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                competitor_id INT NOT NULL,
                price DECIMAL(12,2) NOT NULL,
                original_price DECIMAL(12,2),
                sold_quantity INT DEFAULT 0,
                available_quantity INT DEFAULT 0,
                recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_competitor_date (competitor_id, recorded_at),
                FOREIGN KEY (competitor_id) REFERENCES pricing_competitors(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_market_alerts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                alert_type ENUM('price_drop', 'price_increase', 'new_competitor', 'competitor_out_of_stock', 'market_shift', 'opportunity') NOT NULL,
                severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                title VARCHAR(255) NOT NULL,
                message TEXT,
                data JSON,
                is_read TINYINT(1) DEFAULT 0,
                is_actioned TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account_read (account_id, is_read),
                INDEX idx_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_watchlist (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                item_id VARCHAR(50) NOT NULL,
                item_title VARCHAR(500),
                category_id VARCHAR(50),
                keywords TEXT,
                monitor_frequency ENUM('hourly', 'daily', 'weekly') DEFAULT 'daily',
                price_alert_threshold DECIMAL(5,2) DEFAULT 5.00,
                notify_email TINYINT(1) DEFAULT 1,
                notify_slack TINYINT(1) DEFAULT 0,
                is_active TINYINT(1) DEFAULT 1,
                last_monitored TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_watchlist (account_id, item_id),
                INDEX idx_account_active (account_id, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Obtém cliente ML (lazy loading)
     */
    private function getMlClient(): MercadoLivreClient
    {
        if ($this->mlClient === null) {
            $this->mlClient = new MercadoLivreClient($this->accountId);
        }
        return $this->mlClient;
    }

    /**
     * Adiciona item à watchlist de monitoramento
     */
    public function addToWatchlist(array $data): array
    {
        if (empty($data['item_id'])) {
            return ['success' => false, 'message' => 'Item ID é obrigatório'];
        }

        // Verificar se item existe
        try {
            $ml = $this->getMlClient();
            $item = $ml->get("/items/{$data['item_id']}");
            if (!$item || isset($item['error'])) {
                return ['success' => false, 'message' => 'Item não encontrado no Mercado Livre'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao verificar item: ' . $e->getMessage()];
        }

        $stmt = $this->db->prepare("
            INSERT INTO pricing_watchlist
            (account_id, item_id, item_title, category_id, keywords, monitor_frequency,
             price_alert_threshold, notify_email, notify_slack)
            VALUES
            (:account_id, :item_id, :item_title, :category_id, :keywords, :frequency,
             :threshold, :notify_email, :notify_slack)
            ON DUPLICATE KEY UPDATE
                item_title = VALUES(item_title),
                category_id = VALUES(category_id),
                keywords = VALUES(keywords),
                monitor_frequency = VALUES(monitor_frequency),
                price_alert_threshold = VALUES(price_alert_threshold),
                notify_email = VALUES(notify_email),
                notify_slack = VALUES(notify_slack),
                is_active = 1
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $data['item_id'],
            'item_title' => $item['title'] ?? '',
            'category_id' => $item['category_id'] ?? null,
            'keywords' => $data['keywords'] ?? null,
            'frequency' => $data['monitor_frequency'] ?? 'daily',
            'threshold' => $data['price_alert_threshold'] ?? 5.00,
            'notify_email' => $data['notify_email'] ?? true,
            'notify_slack' => $data['notify_slack'] ?? false
        ]);

        return [
            'success' => true,
            'message' => 'Item adicionado à watchlist',
            'item_title' => $item['title']
        ];
    }

    /**
     * Remove item da watchlist
     */
    public function removeFromWatchlist(string $itemId): array
    {
        $stmt = $this->db->prepare("
            UPDATE pricing_watchlist SET is_active = 0
            WHERE account_id = :account_id AND item_id = :item_id
        ");
        $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);

        return ['success' => true, 'message' => 'Item removido da watchlist'];
    }

    /**
     * Lista itens na watchlist
     */
    public function getWatchlist(): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*,
                   (SELECT COUNT(*) FROM pricing_competitors c WHERE c.account_id = w.account_id AND c.item_id = w.item_id) as competitor_count,
                   (SELECT MIN(c.competitor_price) FROM pricing_competitors c WHERE c.account_id = w.account_id AND c.item_id = w.item_id) as lowest_competitor_price
            FROM pricing_watchlist w
            WHERE w.account_id = :account_id AND w.is_active = 1
            ORDER BY w.created_at DESC
        ");
        $stmt->execute(['account_id' => $this->accountId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Escaneia concorrentes para um item específico
     */
    public function scanCompetitors(string $itemId, ?string $keywords = null): array
    {
        $ml = $this->getMlClient();

        // Obter informações do item original
        $item = $ml->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return ['success' => false, 'message' => 'Item não encontrado'];
        }

        $categoryId = $item['category_id'] ?? null;
        $searchTerms = $keywords ?: $this->extractSearchTerms($item['title'] ?? '');

        if (!$categoryId && !$searchTerms) {
            return ['success' => false, 'message' => 'Não foi possível determinar termos de busca'];
        }

        // Buscar produtos similares
        $competitors = [];

        try {
            $searchParams = [
                'category' => $categoryId,
                'limit' => 50,
                'sort' => 'relevance'
            ];

            if ($searchTerms) {
                $searchParams['q'] = $searchTerms;
            }

            $searchResults = $ml->searchItems($searchParams);
            $results = $searchResults['results'] ?? [];

            $position = 0;
            foreach ($results as $result) {
                $position++;

                // Ignorar o próprio item
                if ($result['id'] === $itemId) {
                    continue;
                }

                // Obter detalhes completos do item
                $competitorItem = $ml->get("/items/{$result['id']}");
                if (!$competitorItem || isset($competitorItem['error'])) {
                    continue;
                }

                $competitor = [
                    'competitor_id' => $result['id'],
                    'competitor_seller_id' => $competitorItem['seller_id'] ?? null,
                    'competitor_title' => $competitorItem['title'] ?? '',
                    'competitor_price' => (float) ($competitorItem['price'] ?? 0),
                    'competitor_original_price' => (float) ($competitorItem['original_price'] ?? $competitorItem['price'] ?? 0),
                    'competitor_condition' => $competitorItem['condition'] ?? 'new',
                    'competitor_shipping_free' => $this->hasFreeSHipping($competitorItem),
                    'competitor_seller_reputation' => $this->getSellerReputation($competitorItem),
                    'competitor_sold_quantity' => (int) ($competitorItem['sold_quantity'] ?? 0),
                    'competitor_available_quantity' => (int) ($competitorItem['available_quantity'] ?? 0),
                    'position_in_search' => $position
                ];

                $this->saveCompetitor($itemId, $competitor);
                $competitors[] = $competitor;
            }

            // Atualizar data de monitoramento
            $stmt = $this->db->prepare("
                UPDATE pricing_watchlist SET last_monitored = NOW()
                WHERE account_id = :account_id AND item_id = :item_id
            ");
            $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);

            // Analisar e gerar alertas
            $alerts = $this->analyzeCompetitors($itemId, $item, $competitors);

            return [
                'success' => true,
                'item_id' => $itemId,
                'item_price' => (float) ($item['price'] ?? 0),
                'competitors_found' => count($competitors),
                'competitors' => $competitors,
                'alerts_generated' => count($alerts),
                'alerts' => $alerts
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao buscar concorrentes: ' . $e->getMessage()];
        }
    }

    /**
     * Extrai termos de busca do título
     */
    private function extractSearchTerms(string $title): string
    {
        // Remover palavras comuns e caracteres especiais
        $stopWords = ['de', 'da', 'do', 'para', 'com', 'em', 'por', 'e', 'ou', 'a', 'o', 'as', 'os', 'um', 'uma'];
        $words = preg_split('/[\s\-\/\|\(\)]+/', strtolower($title));
        $words = array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords) && !is_numeric($word);
        });

        // Pegar as 5 primeiras palavras mais relevantes
        return implode(' ', array_slice($words, 0, 5));
    }

    /**
     * Verifica se tem frete grátis
     */
    private function hasFreeShipping(array $item): bool
    {
        $shipping = $item['shipping'] ?? [];
        return ($shipping['free_shipping'] ?? false) || ($shipping['logistic_type'] ?? '') === 'fulfillment';
    }

    /**
     * Obtém reputação do vendedor
     */
    private function getSellerReputation(array $item): string
    {
        $reputation = $item['seller']['seller_reputation']['level_id'] ?? null;
        $powerSeller = $item['seller']['seller_reputation']['power_seller_status'] ?? null;

        if ($powerSeller === 'platinum') {
            return 'MercadoLíder Platinum';
        } elseif ($powerSeller === 'gold') {
            return 'MercadoLíder Gold';
        } elseif ($powerSeller) {
            return 'MercadoLíder';
        } elseif ($reputation) {
            return ucfirst($reputation);
        }

        return 'Standard';
    }

    /**
     * Salva dados do concorrente
     */
    private function saveCompetitor(string $itemId, array $competitor): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_competitors
            (account_id, item_id, competitor_id, competitor_seller_id, competitor_title,
             competitor_price, competitor_original_price, competitor_condition,
             competitor_shipping_free, competitor_seller_reputation, competitor_sold_quantity,
             competitor_available_quantity, position_in_search, last_checked)
            VALUES
            (:account_id, :item_id, :competitor_id, :seller_id, :title,
             :price, :original_price, :condition, :shipping_free, :reputation,
             :sold_qty, :available_qty, :position, NOW())
            ON DUPLICATE KEY UPDATE
                competitor_price = VALUES(competitor_price),
                competitor_original_price = VALUES(competitor_original_price),
                competitor_sold_quantity = VALUES(competitor_sold_quantity),
                competitor_available_quantity = VALUES(competitor_available_quantity),
                position_in_search = VALUES(position_in_search),
                last_checked = NOW()
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $itemId,
            'competitor_id' => $competitor['competitor_id'],
            'seller_id' => $competitor['competitor_seller_id'],
            'title' => $competitor['competitor_title'],
            'price' => $competitor['competitor_price'],
            'original_price' => $competitor['competitor_original_price'],
            'condition' => $competitor['competitor_condition'],
            'shipping_free' => $competitor['competitor_shipping_free'] ? 1 : 0,
            'reputation' => $competitor['competitor_seller_reputation'],
            'sold_qty' => $competitor['competitor_sold_quantity'],
            'available_qty' => $competitor['competitor_available_quantity'],
            'position' => $competitor['position_in_search']
        ]);

        // Registrar no histórico
        $competitorDbId = $this->db->lastInsertId();
        if ($competitorDbId) {
            $this->recordCompetitorHistory((int) $competitorDbId, $competitor);
        }
    }

    /**
     * Registra histórico do concorrente
     */
    private function recordCompetitorHistory(int $competitorDbId, array $competitor): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_competitor_history
            (competitor_id, price, original_price, sold_quantity, available_quantity)
            VALUES
            (:competitor_id, :price, :original_price, :sold_qty, :available_qty)
        ");

        $stmt->execute([
            'competitor_id' => $competitorDbId,
            'price' => $competitor['competitor_price'],
            'original_price' => $competitor['competitor_original_price'],
            'sold_qty' => $competitor['competitor_sold_quantity'],
            'available_qty' => $competitor['competitor_available_quantity']
        ]);
    }

    /**
     * Analisa concorrentes e gera alertas
     */
    private function analyzeCompetitors(string $itemId, array $item, array $competitors): array
    {
        $alerts = [];
        $myPrice = (float) ($item['price'] ?? 0);

        if ($myPrice <= 0 || empty($competitors)) {
            return $alerts;
        }

        // Calcular estatísticas de mercado
        $prices = array_column($competitors, 'competitor_price');
        $prices = array_filter($prices, fn($p) => $p > 0);

        if (empty($prices)) {
            return $alerts;
        }

        $minPrice = min($prices);
        $maxPrice = max($prices);
        $avgPrice = array_sum($prices) / count($prices);
        $medianPrice = $this->calculateMedian($prices);

        // Obter preços anteriores para comparação
        $previousPrices = $this->getPreviousCompetitorPrices($itemId);

        // Alerta: Preço muito acima do mercado
        if ($myPrice > $avgPrice * 1.15) {
            $alerts[] = $this->createAlert(
                $itemId,
                'market_shift',
                'high',
                'Preço acima da média do mercado',
                sprintf(
                    'Seu preço (R$ %.2f) está %.1f%% acima da média do mercado (R$ %.2f)',
                    $myPrice,
                    (($myPrice / $avgPrice) - 1) * 100,
                    $avgPrice
                ),
                ['my_price' => $myPrice, 'avg_price' => $avgPrice, 'diff_percent' => (($myPrice / $avgPrice) - 1) * 100]
            );
        }

        // Alerta: Concorrente com preço muito abaixo
        $significantDrops = [];
        foreach ($competitors as $competitor) {
            $competitorId = $competitor['competitor_id'];
            $currentPrice = $competitor['competitor_price'];
            $previousPrice = $previousPrices[$competitorId] ?? $currentPrice;

            if ($previousPrice > 0 && $currentPrice < $previousPrice * 0.9) {
                $dropPercent = (1 - ($currentPrice / $previousPrice)) * 100;
                $significantDrops[] = [
                    'competitor_id' => $competitorId,
                    'title' => $competitor['competitor_title'],
                    'previous_price' => $previousPrice,
                    'current_price' => $currentPrice,
                    'drop_percent' => $dropPercent
                ];
            }
        }

        if (!empty($significantDrops)) {
            $alerts[] = $this->createAlert(
                $itemId,
                'price_drop',
                'high',
                'Queda significativa de preço detectada',
                sprintf('%d concorrente(s) reduziram preço em mais de 10%%', count($significantDrops)),
                ['drops' => $significantDrops]
            );
        }

        // Alerta: Oportunidade - você está bem posicionado
        if ($myPrice <= $minPrice * 1.05 && $myPrice >= $avgPrice * 0.85) {
            $alerts[] = $this->createAlert(
                $itemId,
                'opportunity',
                'low',
                'Boa posição competitiva',
                'Seu preço está competitivo - considere ajuste de margem para aumentar lucro',
                ['my_price' => $myPrice, 'min_price' => $minPrice, 'avg_price' => $avgPrice]
            );
        }

        // Alerta: Concorrentes sem estoque
        $outOfStock = array_filter($competitors, fn($c) => ($c['competitor_available_quantity'] ?? 0) === 0);
        if (count($outOfStock) >= 3) {
            $alerts[] = $this->createAlert(
                $itemId,
                'competitor_out_of_stock',
                'medium',
                'Concorrentes sem estoque',
                sprintf('%d concorrentes estão sem estoque - oportunidade de destaque', count($outOfStock)),
                ['out_of_stock_count' => count($outOfStock)]
            );
        }

        return $alerts;
    }

    /**
     * Obtém preços anteriores dos concorrentes
     */
    private function getPreviousCompetitorPrices(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.competitor_id, h.price
            FROM pricing_competitors c
            JOIN pricing_competitor_history h ON c.id = h.competitor_id
            WHERE c.account_id = :account_id AND c.item_id = :item_id
            AND h.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY h.recorded_at ASC
        ");
        $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);

        $prices = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $prices[$row['competitor_id']] = (float) $row['price'];
        }

        return $prices;
    }

    /**
     * Calcula mediana
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = (int) floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Cria um alerta
     */
    private function createAlert(
        string $itemId,
        string $type,
        string $severity,
        string $title,
        string $message,
        array $data
    ): array {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_market_alerts
            (account_id, item_id, alert_type, severity, title, message, data)
            VALUES
            (:account_id, :item_id, :type, :severity, :title, :message, :data)
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $itemId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'data' => json_encode($data)
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ];
    }

    /**
     * Obtém alertas de mercado
     */
    public function getAlerts(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (isset($filters['is_read'])) {
            $where[] = 'is_read = :is_read';
            $params['is_read'] = $filters['is_read'] ? 1 : 0;
        }

        if (!empty($filters['item_id'])) {
            $where[] = 'item_id = :item_id';
            $params['item_id'] = $filters['item_id'];
        }

        if (!empty($filters['severity'])) {
            $where[] = 'severity = :severity';
            $params['severity'] = $filters['severity'];
        }

        $limit = max(1, min(500, (int) ($filters['limit'] ?? 50)));

        $sql = "SELECT * FROM pricing_market_alerts WHERE " . implode(' AND ', $where)
            . " ORDER BY created_at DESC LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($alerts as &$alert) {
            $alert['data'] = json_decode($alert['data'], true);
        }

        return $alerts;
    }

    /**
     * Marca alertas como lidos
     */
    public function markAlertsAsRead(array $alertIds): array
    {
        if (empty($alertIds)) {
            return ['success' => false, 'message' => 'Nenhum alerta especificado'];
        }

        $placeholders = implode(',', array_fill(0, count($alertIds), '?'));
        $stmt = $this->db->prepare("
            UPDATE pricing_market_alerts
            SET is_read = 1
            WHERE account_id = ? AND id IN ($placeholders)
        ");

        $params = array_merge([$this->accountId], $alertIds);
        $stmt->execute($params);

        return ['success' => true, 'marked' => $stmt->rowCount()];
    }

    /**
     * Obtém análise de mercado para um item
     */
    public function getMarketAnalysis(string $itemId): array
    {
        // Obter concorrentes atuais
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_competitors
            WHERE account_id = :account_id AND item_id = :item_id
            ORDER BY competitor_price ASC
        ");
        $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);
        $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($competitors)) {
            return [
                'success' => false,
                'message' => 'Nenhum concorrente encontrado. Execute um scan primeiro.'
            ];
        }

        // Obter meu item
        $ml = $this->getMlClient();
        $myItem = $ml->get("/items/{$itemId}");
        $myPrice = (float) ($myItem['price'] ?? 0);

        // Calcular estatísticas
        $prices = array_filter(array_column($competitors, 'competitor_price'), fn($p) => $p > 0);
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $avgPrice = array_sum($prices) / count($prices);
        $medianPrice = $this->calculateMedian($prices);

        // Calcular posição de preço
        $pricesBelow = count(array_filter($prices, fn($p) => $p < $myPrice));
        $pricePosition = count($prices) > 0 ? round(($pricesBelow / count($prices)) * 100) : 0;

        // Analisar frete grátis
        $freeShippingCount = count(array_filter($competitors, fn($c) => $c['competitor_shipping_free']));
        $freeShippingPercent = count($competitors) > 0 ? round(($freeShippingCount / count($competitors)) * 100) : 0;

        // Analisar vendedores premium
        $premiumSellers = count(array_filter(
            $competitors,
            fn($c) =>
            str_contains(strtolower($c['competitor_seller_reputation'] ?? ''), 'mercadolíder')
        ));

        // Distribuição de preços por faixa
        $priceDistribution = $this->calculatePriceDistribution($prices, $myPrice);

        // Tendência de preços (últimos 7 dias)
        $priceTrend = $this->calculatePriceTrend($itemId);

        return [
            'success' => true,
            'item_id' => $itemId,
            'my_price' => $myPrice,
            'competitors_count' => count($competitors),
            'statistics' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'avg_price' => round($avgPrice, 2),
                'median_price' => round($medianPrice, 2),
                'price_range' => $maxPrice - $minPrice
            ],
            'position' => [
                'price_percentile' => $pricePosition,
                'description' => $this->getPositionDescription($pricePosition)
            ],
            'market_features' => [
                'free_shipping_percent' => $freeShippingPercent,
                'premium_sellers_count' => $premiumSellers,
                'premium_sellers_percent' => count($competitors) > 0 ? round(($premiumSellers / count($competitors)) * 100) : 0
            ],
            'price_distribution' => $priceDistribution,
            'price_trend' => $priceTrend,
            'competitors' => $competitors,
            'recommendations' => $this->generateRecommendations($myPrice, $minPrice, $avgPrice, $medianPrice, $pricePosition)
        ];
    }

    /**
     * Calcula distribuição de preços
     */
    private function calculatePriceDistribution(array $prices, float $myPrice): array
    {
        if (empty($prices)) {
            return [];
        }

        $min = min($prices);
        $max = max($prices);
        $range = $max - $min;

        if ($range <= 0) {
            return [['range' => sprintf('R$ %.2f', $min), 'count' => count($prices), 'has_my_price' => true]];
        }

        $bucketSize = $range / 5;
        $distribution = [];

        for ($i = 0; $i < 5; $i++) {
            $bucketMin = $min + ($i * $bucketSize);
            $bucketMax = $min + (($i + 1) * $bucketSize);

            $count = count(array_filter($prices, function ($p) use ($bucketMin, $bucketMax, $i) {
                return $i === 4 ? ($p >= $bucketMin && $p <= $bucketMax) : ($p >= $bucketMin && $p < $bucketMax);
            }));

            $distribution[] = [
                'range' => sprintf('R$ %.2f - R$ %.2f', $bucketMin, $bucketMax),
                'min' => round($bucketMin, 2),
                'max' => round($bucketMax, 2),
                'count' => $count,
                'has_my_price' => $myPrice >= $bucketMin && $myPrice <= $bucketMax
            ];
        }

        return $distribution;
    }

    /**
     * Calcula tendência de preços
     */
    private function calculatePriceTrend(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(h.recorded_at) as date, AVG(h.price) as avg_price, MIN(h.price) as min_price
            FROM pricing_competitor_history h
            JOIN pricing_competitors c ON h.competitor_id = c.id
            WHERE c.account_id = :account_id AND c.item_id = :item_id
            AND h.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(h.recorded_at)
            ORDER BY date ASC
        ");
        $stmt->execute(['account_id' => $this->accountId, 'item_id' => $itemId]);

        $trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($trend) < 2) {
            return ['direction' => 'stable', 'change_percent' => 0, 'data' => $trend];
        }

        $firstAvg = (float) $trend[0]['avg_price'];
        $lastAvg = (float) $trend[count($trend) - 1]['avg_price'];

        if ($firstAvg > 0) {
            $changePercent = (($lastAvg - $firstAvg) / $firstAvg) * 100;
            $direction = $changePercent > 2 ? 'up' : ($changePercent < -2 ? 'down' : 'stable');
        } else {
            $changePercent = 0;
            $direction = 'stable';
        }

        return [
            'direction' => $direction,
            'change_percent' => round($changePercent, 2),
            'data' => $trend
        ];
    }

    /**
     * Obtém descrição da posição de preço
     */
    private function getPositionDescription(int $percentile): string
    {
        if ($percentile <= 20) {
            return 'Muito competitivo - Entre os mais baratos';
        } elseif ($percentile <= 40) {
            return 'Competitivo - Abaixo da média';
        } elseif ($percentile <= 60) {
            return 'Moderado - Na média do mercado';
        } elseif ($percentile <= 80) {
            return 'Acima da média - Considere revisão';
        } else {
            return 'Pouco competitivo - Entre os mais caros';
        }
    }

    /**
     * Gera recomendações baseadas na análise
     */
    private function generateRecommendations(
        float $myPrice,
        float $minPrice,
        float $avgPrice,
        float $medianPrice,
        int $pricePosition
    ): array {
        $recommendations = [];

        if ($myPrice > $avgPrice * 1.1) {
            $suggestedPrice = round($avgPrice * 1.05, 2);
            $recommendations[] = [
                'type' => 'price_reduction',
                'priority' => 'high',
                'title' => 'Reduzir preço para aumentar competitividade',
                'description' => sprintf(
                    'Seu preço está %.1f%% acima da média. Considere reduzir para R$ %.2f',
                    (($myPrice / $avgPrice) - 1) * 100,
                    $suggestedPrice
                ),
                'suggested_price' => $suggestedPrice
            ];
        }

        if ($myPrice < $minPrice) {
            $recommendations[] = [
                'type' => 'price_increase',
                'priority' => 'medium',
                'title' => 'Oportunidade de aumento de preço',
                'description' => sprintf('Você tem o menor preço. Pode aumentar até R$ %.2f mantendo competitividade', $minPrice),
                'suggested_price' => round($minPrice * 0.99, 2)
            ];
        }

        if ($pricePosition >= 30 && $pricePosition <= 50) {
            $recommendations[] = [
                'type' => 'maintain',
                'priority' => 'low',
                'title' => 'Preço bem posicionado',
                'description' => 'Seu preço está em uma faixa competitiva. Foque em outros diferenciais.'
            ];
        }

        return $recommendations;
    }

    /**
     * Obtém estatísticas gerais do monitoramento
     */
    public function getStats(): array
    {
        // Total de itens monitorados
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pricing_watchlist
            WHERE account_id = :account_id AND is_active = 1
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $watchlistCount = (int) $stmt->fetchColumn();

        // Total de concorrentes rastreados
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT competitor_id) FROM pricing_competitors
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $competitorsCount = (int) $stmt->fetchColumn();

        // Alertas não lidos
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pricing_market_alerts
            WHERE account_id = :account_id AND is_read = 0
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $unreadAlerts = (int) $stmt->fetchColumn();

        // Alertas por tipo
        $stmt = $this->db->prepare("
            SELECT alert_type, COUNT(*) as count
            FROM pricing_market_alerts
            WHERE account_id = :account_id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY alert_type
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $alertsByType = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $alertsByType[$row['alert_type']] = (int) $row['count'];
        }

        return [
            'watchlist_items' => $watchlistCount,
            'competitors_tracked' => $competitorsCount,
            'unread_alerts' => $unreadAlerts,
            'alerts_last_7_days' => $alertsByType
        ];
    }
}
