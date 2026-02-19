<?php

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

/**
 * 📊 Competitive Intelligence Dashboard
 * 
 * Análise avançada de mercado e oportunidades:
 * - Market share analysis
 * - Price trends e gap analysis
 * - Opportunity detection
 * - Strategic recommendations
 * 
 * @version 1.0.0
 */
class CompetitiveIntelligence
{
    private PDO $db;
    private int $accountId;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }
    
    /**
     * Dashboard principal de inteligência competitiva
     * 
     * @param string $categoryId Categoria ML (opcional)
     * @param array $options Opções de filtro
     * @return array Dashboard data
     */
    public function getDashboard(string $categoryId = null, array $options = []): array
    {
        $data = [
            'overview' => $this->getOverview($categoryId),
            'market_trends' => $this->getMarketTrends($categoryId),
            'price_analysis' => $this->getPriceAnalysis($categoryId),
            'opportunities' => $this->detectOpportunities($categoryId),
            'top_competitors' => $this->getTopCompetitors($categoryId, 10),
            'recommendations' => $this->generateRecommendations($categoryId),
        ];
        
        return $data;
    }
    
    /**
     * Overview geral do mercado
     */
    private function getOverview(string $categoryId = null): array
    {
        $whereClause = $categoryId ? "AND category_id = :category_id" : "";
        
        // Total de concorrentes monitorados
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              {$whereClause}
        ");
        $params = ['account_id' => $this->accountId];
        if ($categoryId) $params['category_id'] = $categoryId;
        $stmt->execute($params);
        $totalCompetitors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Mudanças nas últimas 24h
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM competitor_history
            WHERE watchlist_id IN (
                SELECT id FROM competitor_watchlist 
                WHERE account_id = :account_id {$whereClause}
            )
            AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute($params);
        $recentChanges = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Alertas não lidos
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM competitor_alerts
            WHERE watchlist_id IN (
                SELECT id FROM competitor_watchlist 
                WHERE account_id = :account_id {$whereClause}
            )
            AND status = 'unread'
        ");
        $stmt->execute($params);
        $unreadAlerts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Preço médio do mercado
        $stmt = $this->db->prepare("
            SELECT AVG(CAST(current_price AS DECIMAL(10,2))) as avg_price
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              {$whereClause}
        ");
        $stmt->execute($params);
        $avgPrice = $stmt->fetch(PDO::FETCH_ASSOC)['avg_price'] ?? 0;
        
        return [
            'total_competitors' => $totalCompetitors,
            'recent_changes_24h' => $recentChanges,
            'unread_alerts' => $unreadAlerts,
            'market_avg_price' => round($avgPrice, 2),
        ];
    }
    
    /**
     * Tendências de mercado (últimos 30 dias)
     */
    private function getMarketTrends(string $categoryId = null): array
    {
        $whereClause = $categoryId ? "AND w.category_id = :category_id" : "";
        
        // Mudanças de preço por dia (últimos 30 dias)
        $stmt = $this->db->prepare("
            SELECT 
                DATE(h.created_at) as date,
                COUNT(*) as total_changes,
                AVG(CASE 
                    WHEN h.field = 'price' AND CAST(h.new_value AS DECIMAL(10,2)) < CAST(h.old_value AS DECIMAL(10,2))
                    THEN 1 ELSE 0 
                END) as price_decrease_ratio
            FROM competitor_history h
            JOIN competitor_watchlist w ON w.id = h.watchlist_id
            WHERE w.account_id = :account_id
              AND h.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              {$whereClause}
            GROUP BY DATE(h.created_at)
            ORDER BY date DESC
        ");
        $params = ['account_id' => $this->accountId];
        if ($categoryId) $params['category_id'] = $categoryId;
        $stmt->execute($params);
        $dailyChanges = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Tendência de preços (sobe/desce)
        $priceIncreases = 0;
        $priceDecreases = 0;
        
        $stmt = $this->db->prepare("
            SELECT 
                CASE 
                    WHEN CAST(new_value AS DECIMAL(10,2)) > CAST(old_value AS DECIMAL(10,2)) THEN 'increase'
                    WHEN CAST(new_value AS DECIMAL(10,2)) < CAST(old_value AS DECIMAL(10,2)) THEN 'decrease'
                    ELSE 'stable'
                END as trend,
                COUNT(*) as count
            FROM competitor_history h
            JOIN competitor_watchlist w ON w.id = h.watchlist_id
            WHERE w.account_id = :account_id
              AND h.field = 'price'
              AND h.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              {$whereClause}
            GROUP BY trend
        ");
        $stmt->execute($params);
        $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($trends as $trend) {
            if ($trend['trend'] === 'increase') {
                $priceIncreases = $trend['count'];
            } elseif ($trend['trend'] === 'decrease') {
                $priceDecreases = $trend['count'];
            }
        }
        
        return [
            'daily_changes' => $dailyChanges,
            'price_trend' => [
                'increases' => $priceIncreases,
                'decreases' => $priceDecreases,
                'trend_direction' => $priceDecreases > $priceIncreases ? 'down' : 'up',
            ],
        ];
    }
    
    /**
     * Análise de preços competitivos
     */
    private function getPriceAnalysis(string $categoryId = null): array
    {
        $whereClause = $categoryId ? "AND category_id = :category_id" : "";
        
        $stmt = $this->db->prepare("
            SELECT 
                MIN(CAST(current_price AS DECIMAL(10,2))) as min_price,
                MAX(CAST(current_price AS DECIMAL(10,2))) as max_price,
                AVG(CAST(current_price AS DECIMAL(10,2))) as avg_price,
                STDDEV(CAST(current_price AS DECIMAL(10,2))) as price_stddev
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              AND current_price IS NOT NULL
              {$whereClause}
        ");
        $params = ['account_id' => $this->accountId];
        if ($categoryId) $params['category_id'] = $categoryId;
        $stmt->execute($params);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Distribuição de preços (ranges)
        $ranges = [
            'low' => ['min' => 0, 'max' => $stats['avg_price'] * 0.7, 'count' => 0],
            'medium' => ['min' => $stats['avg_price'] * 0.7, 'max' => $stats['avg_price'] * 1.3, 'count' => 0],
            'high' => ['min' => $stats['avg_price'] * 1.3, 'max' => PHP_FLOAT_MAX, 'count' => 0],
        ];
        
        $stmt = $this->db->prepare("
            SELECT current_price
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              AND current_price IS NOT NULL
              {$whereClause}
        ");
        $stmt->execute($params);
        $prices = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($prices as $price) {
            $price = (float)$price;
            if ($price <= $ranges['low']['max']) {
                $ranges['low']['count']++;
            } elseif ($price <= $ranges['medium']['max']) {
                $ranges['medium']['count']++;
            } else {
                $ranges['high']['count']++;
            }
        }
        
        return [
            'min' => round($stats['min_price'], 2),
            'max' => round($stats['max_price'], 2),
            'avg' => round($stats['avg_price'], 2),
            'stddev' => round($stats['price_stddev'], 2),
            'distribution' => $ranges,
            'recommended_range' => [
                'min' => round($stats['avg_price'] * 0.85, 2),
                'max' => round($stats['avg_price'] * 1.15, 2),
            ],
        ];
    }
    
    /**
     * Detecta oportunidades de mercado
     */
    private function detectOpportunities(string $categoryId = null, int $limit = 5): array
    {
        $opportunities = [];
        $whereClause = $categoryId ? "AND category_id = :category_id" : "";
        $params = ['account_id' => $this->accountId];
        if ($categoryId) $params['category_id'] = $categoryId;
        
        // Oportunidade 1: Concorrentes com score SEO baixo (<50)
        $stmt = $this->db->prepare("
            SELECT item_id, current_title, current_seo_score, current_price
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              AND current_seo_score < 50
              {$whereClause}
            ORDER BY current_seo_score ASC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $lowSeoCompetitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($lowSeoCompetitors)) {
            $opportunities[] = [
                'type' => 'low_seo_competitors',
                'priority' => 'high',
                'title' => '🎯 Concorrentes com SEO Fraco',
                'description' => 'Encontramos ' . count($lowSeoCompetitors) . ' concorrentes com score SEO baixo. Oportunidade de rankear acima deles.',
                'items' => $lowSeoCompetitors,
                'action' => 'Otimize seu SEO para superar esses concorrentes',
            ];
        }
        
        // Oportunidade 2: Quedas de preço recentes (últimas 48h)
        $stmt = $this->db->prepare("
            SELECT 
                w.item_id, 
                w.current_title, 
                h.old_value as old_price,
                h.new_value as new_price,
                h.created_at,
                ((CAST(h.old_value AS DECIMAL(10,2)) - CAST(h.new_value AS DECIMAL(10,2))) / CAST(h.old_value AS DECIMAL(10,2)) * 100) as discount_percent
            FROM competitor_history h
            JOIN competitor_watchlist w ON w.id = h.watchlist_id
            WHERE w.account_id = :account_id
              AND h.field = 'price'
              AND h.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
              AND CAST(h.new_value AS DECIMAL(10,2)) < CAST(h.old_value AS DECIMAL(10,2))
              {$whereClause}
            ORDER BY discount_percent DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $priceDrops = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($priceDrops)) {
            $opportunities[] = [
                'type' => 'recent_price_drops',
                'priority' => 'high',
                'title' => '💰 Quedas de Preço Recentes',
                'description' => 'Concorrentes baixaram preços recentemente. Considere ajustar sua estratégia.',
                'items' => $priceDrops,
                'action' => 'Revisar sua precificação ou destacar diferenciais',
            ];
        }
        
        // Oportunidade 3: Concorrentes sem frete grátis
        $stmt = $this->db->prepare("
            SELECT item_id, current_title, current_price, current_seo_score
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              AND (current_shipping_free = 0 OR current_shipping_free IS NULL)
              {$whereClause}
            ORDER BY current_seo_score DESC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $noFreeShipping = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($noFreeShipping)) {
            $opportunities[] = [
                'type' => 'no_free_shipping',
                'priority' => 'medium',
                'title' => '📦 Vantagem de Frete Grátis',
                'description' => count($noFreeShipping) . ' concorrentes não oferecem frete grátis. Destaque isso!',
                'items' => $noFreeShipping,
                'action' => 'Ativar frete grátis para ganhar vantagem competitiva',
            ];
        }
        
        // Oportunidade 4: Gap de atributos
        $stmt = $this->db->prepare("
            SELECT item_id, current_title, current_attributes_filled, current_seo_score
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              AND current_attributes_filled < 10
              {$whereClause}
            ORDER BY current_seo_score ASC
            LIMIT {$limit}
        ");
        $stmt->execute($params);
        $missingAttributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($missingAttributes)) {
            $opportunities[] = [
                'type' => 'missing_attributes',
                'priority' => 'medium',
                'title' => '🏷️ Concorrentes com Poucos Atributos',
                'description' => 'Oportunidade de se destacar preenchendo mais atributos.',
                'items' => $missingAttributes,
                'action' => 'Preencher todos os atributos relevantes do seu produto',
            ];
        }
        
        return $opportunities;
    }
    
    /**
     * Top concorrentes por score SEO
     */
    private function getTopCompetitors(string $categoryId = null, int $limit = 10): array
    {
        $whereClause = $categoryId ? "AND category_id = :category_id" : "";
        
        $stmt = $this->db->prepare("
            SELECT 
                item_id,
                current_title,
                current_price,
                current_seo_score,
                current_pictures_count,
                current_sold_quantity,
                current_shipping_free,
                updated_at
            FROM competitor_watchlist
            WHERE account_id = :account_id 
              AND status = 'active'
              {$whereClause}
            ORDER BY current_seo_score DESC
            LIMIT {$limit}
        ");
        $params = ['account_id' => $this->accountId];
        if ($categoryId) $params['category_id'] = $categoryId;
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gera recomendações estratégicas
     */
    private function generateRecommendations(string $categoryId = null): array
    {
        $recommendations = [];
        
        // Análise de preços
        $priceAnalysis = $this->getPriceAnalysis($categoryId);
        
        $recommendations[] = [
            'category' => 'pricing',
            'title' => '💰 Estratégia de Preço',
            'priority' => 'high',
            'description' => sprintf(
                'O preço médio do mercado é R$ %.2f. Recomendamos posicionar entre R$ %.2f e R$ %.2f para competitividade.',
                $priceAnalysis['avg'],
                $priceAnalysis['recommended_range']['min'],
                $priceAnalysis['recommended_range']['max']
            ),
            'action_items' => [
                'Revisar margem de lucro vs. competitividade',
                'Considerar promoções sazonais',
                'Monitorar mudanças de preço dos top 3 concorrentes',
            ],
        ];
        
        // Análise de SEO
        $stmt = $this->db->prepare("
            SELECT AVG(current_seo_score) as avg_score
            FROM competitor_watchlist
            WHERE account_id = :account_id AND status = 'active'
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $avgScore = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'] ?? 0;
        
        $recommendations[] = [
            'category' => 'seo',
            'title' => '🔥 Otimização SEO',
            'priority' => 'high',
            'description' => sprintf(
                'Score SEO médio dos concorrentes: %.0f/100. Supere essa marca para melhor rankeamento.',
                $avgScore
            ),
            'action_items' => [
                'Otimizar títulos com keywords de alto volume',
                'Adicionar no mínimo 6 imagens de alta qualidade',
                'Preencher todos os atributos obrigatórios + BRAND + MODEL',
                'Descrição com mínimo 500 caracteres',
            ],
        ];
        
        // Análise de frete
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN current_shipping_free = 1 THEN 1 ELSE 0 END) as with_free,
                COUNT(*) as total
            FROM competitor_watchlist
            WHERE account_id = :account_id AND status = 'active'
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $shipping = $stmt->fetch(PDO::FETCH_ASSOC);
        $freeShippingPercent = ($shipping['with_free'] / $shipping['total']) * 100;
        
        if ($freeShippingPercent > 50) {
            $recommendations[] = [
                'category' => 'shipping',
                'title' => '📦 Frete Grátis é Essencial',
                'priority' => 'high',
                'description' => sprintf(
                    '%.0f%% dos concorrentes oferecem frete grátis. Isso é crítico para conversão.',
                    $freeShippingPercent
                ),
                'action_items' => [
                    'Ativar frete grátis (Mercado Envios Full)',
                    'Ajustar preço para absorver custo de frete',
                    'Usar como diferencial no título/descrição',
                ],
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Análise SWOT automatizada
     * 
     * @param string $itemId Seu produto
     * @param array $competitorIds IDs dos concorrentes
     * @return array SWOT analysis
     */
    public function swotAnalysis(string $itemId, array $competitorIds): array
    {
        // Buscar dados do seu produto
        $stmt = $this->db->prepare("
            SELECT * FROM items 
            WHERE item_id = :item_id AND account_id = :account_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $this->accountId,
        ]);
        $myProduct = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Buscar concorrentes
        $placeholders = implode(',', array_fill(0, count($competitorIds), '?'));
        $stmt = $this->db->prepare("
            SELECT * FROM competitor_watchlist
            WHERE account_id = ? AND item_id IN ({$placeholders})
        ");
        $stmt->execute(array_merge([$this->accountId], $competitorIds));
        $competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $strengths = [];
        $weaknesses = [];
        $opportunities = [];
        $threats = [];
        
        // Calcular métricas médias dos concorrentes
        $avgPrice = array_sum(array_column($competitors, 'current_price')) / count($competitors);
        $avgScore = array_sum(array_column($competitors, 'current_seo_score')) / count($competitors);
        $avgImages = array_sum(array_column($competitors, 'current_pictures_count')) / count($competitors);
        
        // STRENGTHS
        if (($myProduct['seo_score'] ?? 0) > $avgScore) {
            $strengths[] = 'SEO score acima da média do mercado';
        }
        if (($myProduct['price'] ?? 0) < $avgPrice) {
            $strengths[] = 'Preço competitivo (abaixo da média)';
        }
        
        // WEAKNESSES
        if (($myProduct['seo_score'] ?? 0) < $avgScore) {
            $weaknesses[] = 'SEO score abaixo da média do mercado';
        }
        if (($myProduct['pictures_count'] ?? 0) < $avgImages) {
            $weaknesses[] = 'Menos imagens que a média dos concorrentes';
        }
        
        // OPPORTUNITIES
        $lowScoreCompetitors = array_filter($competitors, fn($c) => $c['current_seo_score'] < 50);
        if (count($lowScoreCompetitors) > 0) {
            $opportunities[] = sprintf(
                '%d concorrentes com SEO fraco - fácil superação',
                count($lowScoreCompetitors)
            );
        }
        
        // THREATS
        $highScoreCompetitors = array_filter($competitors, fn($c) => $c['current_seo_score'] > 85);
        if (count($highScoreCompetitors) > 0) {
            $threats[] = sprintf(
                '%d concorrentes com SEO excelente - alta competição',
                count($highScoreCompetitors)
            );
        }
        
        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'opportunities' => $opportunities,
            'threats' => $threats,
        ];
    }
}
