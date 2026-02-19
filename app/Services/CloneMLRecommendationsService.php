<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneMLRecommendationsService
 * 
 * Serviço de recomendações inteligentes baseadas em análise de dados.
 * Sugere sellers, produtos e estratégias de clonagem com maior
 * probabilidade de sucesso.
 */
class CloneMLRecommendationsService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;
    
    // Pesos para score de recomendação
    private const WEIGHT_SALES = 0.30;
    private const WEIGHT_CONVERSION = 0.25;
    private const WEIGHT_PRICE_MARGIN = 0.20;
    private const WEIGHT_COMPETITION = 0.15;
    private const WEIGHT_RECENCY = 0.10;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }
    
    private function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }
    
    /**
     * Obtém recomendações de sellers para clonar
     */
    public function getSellerRecommendations(array $options = []): array
    {
        $limit = $options['limit'] ?? 10;
        $categoryId = $options['category_id'] ?? null;
        
        // Buscar histórico de clones bem sucedidos
        $successfulClones = $this->getSuccessfulCloneHistory($categoryId);
        
        // Analisar sellers com melhor performance
        $sellerScores = $this->analyzeSellerPerformance($successfulClones);
        
        // Buscar sellers novos com potencial
        $newSellers = $this->discoverNewSellers($categoryId, $limit);
        
        // Combinar e rankear
        $recommendations = [];
        
        foreach ($sellerScores as $sellerId => $data) {
            $score = $this->calculateSellerScore($data);
            $recommendations[] = [
                'seller_id' => $sellerId,
                'seller_name' => $data['seller_name'] ?? $sellerId,
                'score' => round($score, 2),
                'reason' => $this->getRecommendationReason($data),
                'metrics' => [
                    'total_cloned' => $data['total_cloned'],
                    'success_rate' => round($data['success_rate'] * 100, 1),
                    'avg_sales' => round($data['avg_sales'], 1),
                    'avg_margin' => round($data['avg_margin'], 1),
                ],
                'source' => 'historical',
            ];
        }
        
        // Adicionar novos sellers descobertos
        foreach ($newSellers as $seller) {
            $recommendations[] = [
                'seller_id' => $seller['seller_id'],
                'seller_name' => $seller['seller_name'] ?? $seller['seller_id'],
                'score' => $seller['score'],
                'reason' => 'Novo seller com alto potencial na categoria',
                'metrics' => $seller['metrics'] ?? [],
                'source' => 'discovery',
            ];
        }
        
        // Ordenar por score
        usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($recommendations, 0, $limit);
    }
    
    /**
     * Obtém recomendações de produtos específicos
     */
    public function getProductRecommendations(array $options = []): array
    {
        $limit = $options['limit'] ?? 20;
        $categoryId = $options['category_id'] ?? null;
        $minPrice = $options['min_price'] ?? 50;
        $maxPrice = $options['max_price'] ?? 5000;
        
        $recommendations = [];
        
        // 1. Produtos com alta demanda não atendida
        $gapProducts = $this->findMarketGaps($categoryId, $minPrice, $maxPrice);
        
        foreach ($gapProducts as $product) {
            $recommendations[] = [
                'item_id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'seller_id' => $product['seller']['id'] ?? null,
                'score' => $product['score'],
                'reason' => $product['reason'],
                'opportunity' => [
                    'demand_index' => $product['demand_index'] ?? 0,
                    'competition_level' => $product['competition_level'] ?? 'medium',
                    'suggested_price' => $product['suggested_price'] ?? null,
                    'estimated_margin' => $product['estimated_margin'] ?? null,
                ],
                'category' => $product['category_id'] ?? $categoryId,
            ];
        }
        
        // 2. Produtos bem sucedidos de outros vendedores
        $topProducts = $this->findTopPerformingProducts($categoryId, $limit);
        
        foreach ($topProducts as $product) {
            // Evitar duplicatas
            if (in_array($product['id'], array_column($recommendations, 'item_id'))) {
                continue;
            }
            
            $recommendations[] = [
                'item_id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'seller_id' => $product['seller']['id'] ?? null,
                'score' => $product['score'],
                'reason' => 'Produto com alta performance de vendas',
                'opportunity' => [
                    'sold_quantity' => $product['sold_quantity'] ?? 0,
                    'competition_level' => $product['competition_level'] ?? 'medium',
                ],
                'category' => $product['category_id'] ?? $categoryId,
            ];
        }
        
        // Ordenar e limitar
        usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($recommendations, 0, $limit);
    }
    
    /**
     * Obtém sugestões baseadas em performance histórica
     * Nota: Tabela cloned_items não tem category_id, usando catalog_product_id
     */
    public function getCategoryRecommendations(int $limit = 10): array
    {
        $fetchLimitSql = max(1, min(200, (int)$limit * 2));
        // Analisar por catalog_product_id ao invés de category_id
        $stmt = $this->db->prepare("
            SELECT 
                COALESCE(ci.catalog_product_id, 'unknown') as category_id,
                COUNT(*) as total_cloned,
                AVG(COALESCE(m.sales, 0)) as avg_sales,
                AVG(COALESCE(m.conversion_rate, 0)) as avg_conversion,
                SUM(CASE WHEN ci.status = 'created' THEN 1 ELSE 0 END) / COUNT(*) as success_rate
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.catalog_product_id IS NOT NULL
            GROUP BY ci.catalog_product_id
            HAVING total_cloned >= 2
            ORDER BY avg_sales DESC
            LIMIT {$fetchLimitSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $recommendations = [];
        $client = $this->getClient();
        
        foreach ($categories as $cat) {
            // Enriquecer com dados da API
            try {
                $catInfo = $client->get("/categories/{$cat['category_id']}");
                $catName = $catInfo['name'] ?? $cat['category_id'];
            } catch (\Exception $e) {
                $catName = $cat['category_id'];
            }
            
            $score = ($cat['avg_sales'] * 0.4) + 
                     ($cat['avg_conversion'] * 0.3) + 
                     ($cat['success_rate'] * 100 * 0.3);
            
            $recommendations[] = [
                'category_id' => $cat['category_id'],
                'category_name' => $catName,
                'score' => round($score, 2),
                'metrics' => [
                    'total_cloned' => (int) $cat['total_cloned'],
                    'avg_sales' => round((float) $cat['avg_sales'], 1),
                    'avg_conversion' => round((float) $cat['avg_conversion'], 2),
                    'success_rate' => round((float) $cat['success_rate'] * 100, 1),
                ],
                'recommendation' => $this->getCategoryRecommendationText($cat),
            ];
        }
        
        usort($recommendations, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($recommendations, 0, $limit);
    }
    
    /**
     * Análise de tendências para timing de clonagem
     */
    public function getTrendAnalysis(string $categoryId = null): array
    {
        // Análise de sazonalidade
        $stmt = $this->db->prepare("
            SELECT 
                DAYOFWEEK(ci.created_at) as day_of_week,
                HOUR(ci.created_at) as hour_of_day,
                COUNT(*) as clones_count,
                AVG(COALESCE(m.sales, 0)) as avg_sales
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY DAYOFWEEK(ci.created_at), HOUR(ci.created_at)
            ORDER BY avg_sales DESC
            LIMIT 10
        ");
        
        $stmt->execute(['account_id' => $this->accountId]);
        
        $timeAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determinar melhor horário
        $bestTimes = [];
        $dayNames = ['', 'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        
        foreach ($timeAnalysis as $row) {
            $bestTimes[] = [
                'day' => $dayNames[$row['day_of_week']] ?? '',
                'hour' => sprintf('%02d:00', $row['hour_of_day']),
                'performance_score' => round((float)($row['avg_sales'] ?? 0), 2),
            ];
        }
        
        return [
            'best_times' => array_slice($bestTimes, 0, 5),
            'recommendation' => $this->getBestTimeRecommendation($bestTimes),
            'insights' => $this->generateTrendInsights($categoryId),
        ];
    }
    
    /**
     * Previsão de performance para um item
     */
    public function predictPerformance(string $itemId): array
    {
        try {
            $client = $this->getClient();
            $item = $client->get("/items/$itemId");
            
            // Buscar items similares já clonados por faixa de preço
            $stmt = $this->db->prepare("
                SELECT 
                    ci.*,
                    m.sales, m.conversion_rate
                FROM cloned_items ci
                LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
                WHERE ci.target_account_id = :account_id
                AND ci.final_price BETWEEN :min_price AND :max_price
                AND m.sales IS NOT NULL
                ORDER BY ci.created_at DESC
                LIMIT 20
            ");
            
            $price = $item['price'] ?? 0;
            $stmt->execute([
                'account_id' => $this->accountId,
                'min_price' => $price * 0.7,
                'max_price' => $price * 1.3,
            ]);
            
            $similarItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($similarItems)) {
                return [
                    'item_id' => $itemId,
                    'prediction' => null,
                    'confidence' => 'low',
                    'message' => 'Dados insuficientes para previsão',
                ];
            }
            
            // Calcular médias
            $avgSales = array_sum(array_column($similarItems, 'sales')) / count($similarItems);
            $avgConversion = array_sum(array_column($similarItems, 'conversion_rate')) / count($similarItems);
            
            $confidence = count($similarItems) >= 10 ? 'high' : 
                         (count($similarItems) >= 5 ? 'medium' : 'low');
            
            return [
                'item_id' => $itemId,
                'title' => $item['title'] ?? '',
                'prediction' => [
                    'expected_sales_30d' => round($avgSales, 1),
                    'expected_conversion' => round($avgConversion, 2),
                    'expected_revenue_30d' => round($avgSales * $price, 2),
                ],
                'confidence' => $confidence,
                'based_on' => count($similarItems) . ' itens similares',
                'factors' => $this->getPerformanceFactors($item, $similarItems),
            ];
            
        } catch (\Exception $e) {
            return [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
                'prediction' => null,
            ];
        }
    }
    
    /**
     * Busca histórico de clones bem sucedidos
     * Nota: A tabela cloned_items tem estrutura simplificada,
     * busca dados básicos e enriquece via API quando possível
     */
    private function getSuccessfulCloneHistory(?string $categoryId = null): array
    {
        $query = "
            SELECT 
                ci.source_account_id,
                ci.source_item_id,
                ci.target_item_id,
                ci.final_price as price,
                COALESCE(m.sales, 0) as sales,
                COALESCE(m.conversion_rate, 0) as conversion_rate
            FROM cloned_items ci
            LEFT JOIN clone_item_metrics m ON m.item_id = ci.target_item_id
            WHERE ci.target_account_id = :account_id
            AND ci.status = 'created'
        ";
        
        $params = ['account_id' => $this->accountId];
        
        // Categoria não está na tabela, remover filtro
        
        $query .= " ORDER BY m.sales DESC LIMIT 100";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Enriquecer com seller_id buscando da API (cache interno)
        foreach ($items as &$item) {
            $item['source_seller_id'] = $item['source_account_id']; // Usar account como proxy
            $item['category_id'] = $categoryId;
            $item['title'] = 'Clone de ' . $item['source_item_id'];
        }
        
        return $items;
    }
    
    /**
     * Analisa performance por seller
     */
    private function analyzeSellerPerformance(array $clones): array
    {
        $sellerData = [];
        
        foreach ($clones as $clone) {
            $sellerId = $clone['source_seller_id'];
            if (!$sellerId) continue;
            
            if (!isset($sellerData[$sellerId])) {
                $sellerData[$sellerId] = [
                    'total_cloned' => 0,
                    'successful' => 0,
                    'total_sales' => 0,
                    'total_margin' => 0,
                ];
            }
            
            $sellerData[$sellerId]['total_cloned']++;
            if ($clone['sales'] > 0) {
                $sellerData[$sellerId]['successful']++;
            }
            $sellerData[$sellerId]['total_sales'] += $clone['sales'];
        }
        
        // Calcular métricas
        foreach ($sellerData as $sellerId => &$data) {
            $data['success_rate'] = $data['total_cloned'] > 0 
                ? $data['successful'] / $data['total_cloned'] 
                : 0;
            $data['avg_sales'] = $data['total_cloned'] > 0 
                ? $data['total_sales'] / $data['total_cloned'] 
                : 0;
            // Calcular margem média real baseada em preço de venda vs custo
            try {
                $db = \App\Database::getInstance();
                $marginStmt = $db->prepare(
                    "SELECT AVG(CASE WHEN cost > 0 THEN ((price - cost) / price) * 100 ELSE 0 END) as avg_margin
                     FROM items WHERE seller_id = ? AND status = 'active' AND cost > 0"
                );
                $marginStmt->execute([$sellerId]);
                $data['avg_margin'] = round((float)$marginStmt->fetchColumn(), 2);
            } catch (\Exception $e) {
                $data['avg_margin'] = 0;
            }
        }
        
        return $sellerData;
    }
    
    /**
     * Descobre novos sellers com potencial
     * Usa /highlights que não requer permissões especiais
     */
    private function discoverNewSellers(?string $categoryId, int $limit): array
    {
        if (!$categoryId) {
            return [];
        }
        
        try {
            $client = $this->getClient();
            
            // Usar highlights ao invés de search (não requer permissão especial)
            $response = $client->get("/highlights/MLB/category/{$categoryId}");
            $itemIds = $response['content'] ?? [];
            
            if (empty($itemIds)) {
                return [];
            }
            
            // Buscar detalhes dos itens
            $itemsResponse = $client->get('/items', ['ids' => implode(',', array_slice($itemIds, 0, 20))]);
            
            $sellers = [];
            $seenSellers = [];
            
            foreach ($itemsResponse as $itemData) {
                $item = $itemData['body'] ?? $itemData;
                $sellerId = $item['seller_id'] ?? null;
                if (!$sellerId || isset($seenSellers[$sellerId])) continue;
                
                $seenSellers[$sellerId] = true;
                
                // Verificar se já clonamos deste seller
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM cloned_items 
                    WHERE target_account_id = :account_id 
                    AND source_account_id = :seller_id
                ");
                $stmt->execute([
                    'account_id' => $this->accountId,
                    'seller_id' => $sellerId,
                ]);
                
                if ($stmt->fetchColumn() == 0) {
                    // Buscar info do seller
                    try {
                        $sellerInfo = $client->get("/users/{$sellerId}");
                        $sellerRep = $sellerInfo['seller_reputation']['transactions']['completed'] ?? 0;
                        $sellerLevel = $sellerInfo['seller_reputation']['level_id'] ?? '';
                        $sellerName = $sellerInfo['nickname'] ?? (string)$sellerId;
                    } catch (\Exception $e) {
                        $sellerRep = 0;
                        $sellerLevel = '';
                        $sellerName = (string)$sellerId;
                    }
                    
                    $soldQty = $item['sold_quantity'] ?? 0;
                    
                    // Score baseado em métricas reais
                    $score = 0;
                    $score += min(40, $soldQty / 5);
                    $score += min(30, $sellerRep / 100);
                    $score += match($sellerLevel) {
                        '5_green' => 30,
                        '4_light_green' => 20,
                        '3_yellow' => 10,
                        default => 5,
                    };
                    
                    $sellers[] = [
                        'seller_id' => $sellerId,
                        'seller_name' => $sellerName,
                        'score' => min(100, round($score, 2)),
                        'metrics' => [
                            'sample_item' => $item['id'] ?? '',
                            'sample_sold' => $soldQty,
                            'seller_transactions' => $sellerRep,
                            'seller_level' => $sellerLevel,
                        ],
                    ];
                }
                
                if (count($sellers) >= $limit) break;
            }
            
            return $sellers;
            
        } catch (\Exception $e) {
            log_error('Erro ao descobrir novos sellers para clone', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Encontra gaps de mercado usando highlights
     */
    private function findMarketGaps(?string $categoryId, float $minPrice, float $maxPrice): array
    {
        if (!$categoryId) {
            return [];
        }
        
        try {
            $client = $this->getClient();
            
            // Usar highlights que funciona sem permissões especiais
            $response = $client->get("/highlights/MLB/category/{$categoryId}");
            $itemIds = $response['content'] ?? [];
            
            if (empty($itemIds)) {
                return [];
            }
            
            // Buscar detalhes dos itens
            $itemsResponse = $client->get('/items', ['ids' => implode(',', array_slice($itemIds, 0, 20))]);
            
            $products = [];
            
            foreach ($itemsResponse as $itemData) {
                $item = $itemData['body'] ?? $itemData;
                
                $soldQty = $item['sold_quantity'] ?? 0;
                $price = $item['price'] ?? 0;
                
                // Filtrar por faixa de preço
                if ($price < $minPrice || $price > $maxPrice) {
                    continue;
                }
                
                if ($soldQty > 5) { // Itens em highlights geralmente vendem bem
                    $score = min(100, 60 + ($soldQty / 5));
                    
                    $products[] = [
                        'id' => $item['id'] ?? '',
                        'title' => $item['title'] ?? '',
                        'price' => $price,
                        'seller' => ['id' => $item['seller_id'] ?? null],
                        'score' => round($score, 2),
                        'reason' => $soldQty > 50 
                            ? 'Alta demanda comprovada' 
                            : 'Produto em destaque',
                        'demand_index' => $soldQty,
                        'competition_level' => 'medium',
                        'category_id' => $categoryId,
                    ];
                }
            }
            
            // Ordenar por score
            usort($products, fn($a, $b) => $b['score'] <=> $a['score']);
            
            return $products;
            
        } catch (\Exception $e) {
            log_error('Erro ao encontrar gaps de mercado para clone', [
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Encontra produtos com melhor performance
     */
    private function findTopPerformingProducts(?string $categoryId, int $limit): array
    {
        // Similar ao findMarketGaps mas com critérios diferentes
        return $this->findMarketGaps($categoryId, 0, 99999);
    }
    
    /**
     * Calcula score de um seller
     */
    private function calculateSellerScore(array $data): float
    {
        $score = 0;
        
        // Vendas médias (0-40 pontos)
        $score += min(40, $data['avg_sales'] * 4);
        
        // Taxa de sucesso (0-30 pontos)
        $score += $data['success_rate'] * 30;
        
        // Volume clonado (0-20 pontos)
        $score += min(20, $data['total_cloned'] * 2);
        
        // Margem (0-10 pontos)
        $score += min(10, $data['avg_margin'] / 10);
        
        return min(100, $score);
    }
    
    /**
     * Gera razão da recomendação
     */
    private function getRecommendationReason(array $data): string
    {
        if ($data['avg_sales'] > 10) {
            return 'Alto volume de vendas em clones anteriores';
        }
        if ($data['success_rate'] > 0.8) {
            return 'Excelente taxa de sucesso (>80%)';
        }
        if ($data['total_cloned'] > 20) {
            return 'Histórico consistente de clonagem';
        }
        return 'Boa performance geral';
    }
    
    /**
     * Texto de recomendação para categoria
     */
    private function getCategoryRecommendationText(array $cat): string
    {
        $avgSales = (float) $cat['avg_sales'];
        $successRate = (float) $cat['success_rate'] * 100;
        
        if ($avgSales > 5 && $successRate > 70) {
            return 'Alta prioridade - Excelente performance histórica';
        }
        if ($avgSales > 2) {
            return 'Boa oportunidade - Vendas consistentes';
        }
        return 'Potencial - Categoria em crescimento';
    }
    
    /**
     * Recomendação de melhor horário
     */
    private function getBestTimeRecommendation(array $bestTimes): string
    {
        if (empty($bestTimes)) {
            return 'Dados insuficientes para recomendação';
        }
        
        $best = $bestTimes[0];
        return "Melhor momento: {$best['day']} às {$best['hour']}";
    }
    
    /**
     * Gera insights de tendência
     */
    private function generateTrendInsights(?string $categoryId): array
    {
        return [
            'tip' => 'Clones feitos nas primeiras horas do dia tendem a ter melhor indexação',
            'category_note' => $categoryId 
                ? 'Análise específica para a categoria selecionada'
                : 'Análise geral de todas as categorias',
        ];
    }
    
    /**
     * Fatores que influenciam performance
     */
    private function getPerformanceFactors(array $item, array $similarItems): array
    {
        return [
            ['factor' => 'Preço competitivo', 'impact' => 'positivo'],
            ['factor' => 'Categoria com demanda', 'impact' => 'positivo'],
            ['factor' => 'Frete grátis', 'impact' => ($item['shipping']['free_shipping'] ?? false) ? 'positivo' : 'neutro'],
        ];
    }
}
