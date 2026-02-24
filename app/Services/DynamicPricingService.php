<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Dynamic Pricing Service
 *
 * Ajuste automático de preços baseado em:
 * - Preços da concorrência
 * - Elasticidade de demanda
 * - Níveis de estoque
 * - Sazonalidade
 * - Margem de lucro mínima
 *
 * Algoritmos:
 * 1. Competition-Based: Ajusta baseado em competidores
 * 2. Demand-Based: Ajusta baseado em demanda histórica
 * 3. Inventory-Based: Liquida estoque parado
 * 4. Time-Based: Promoções relâmpago
 */
class DynamicPricingService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(int $accountId)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Calcula preço ótimo usando estratégia competition-based
     *
     * Lógica:
     * - Se temos menor preço: não mexer ou aumentar levemente (5%)
     * - Se estamos no meio: ficar 2-3% abaixo do segundo menor
     * - Se temos maior preço: igualar ao segundo menor ou descer mais
     *
     * @param string $itemId
     * @param array $options [
     *   'min_margin' => 0.15, // Margem mínima 15%
     *   'max_discount' => 0.30, // Desconto máximo 30%
     *   'aggressive' => false // Modo agressivo
     * ]
     * @return array
     */
    public function calculateOptimalPrice(string $itemId, array $options = []): array
    {
        try {
            $minMargin = $options['min_margin'] ?? 0.15;
            $maxDiscount = $options['max_discount'] ?? 0.30;
            $aggressive = $options['aggressive'] ?? false;

            // Buscar item
            $item = $this->client->get("/items/{$itemId}");

            if (!$item) {
                return ['success' => false, 'error' => 'Item not found'];
            }

            $currentPrice = $item['price'];
            $categoryId = $item['category_id'];

            // Buscar custo do produto
            $cost = $this->getItemCost($itemId);
            $minPrice = $cost * (1 + $minMargin);

            // Buscar concorrentes
            $competitors = $this->searchCompetitors($item['title'], $categoryId);

            if (empty($competitors)) {
                return [
                    'success' => true,
                    'strategy' => 'no_competition',
                    'current_price' => $currentPrice,
                    'optimal_price' => $currentPrice,
                    'change' => 0,
                    'reason' => 'No competitors found'
                ];
            }

            // Ordenar por preço
            usort($competitors, fn($a, $b) => $a['price'] <=> $b['price']);

            $lowestPrice = $competitors[0]['price'];
            $secondLowest = $competitors[1]['price'] ?? $lowestPrice;
            $avgPrice = array_sum(array_column($competitors, 'price')) / count($competitors);

            // Calcular preço ótimo
            $optimalPrice = $currentPrice;
            $strategy = 'maintain';
            $reason = 'Price is competitive';

            if ($currentPrice <= $lowestPrice) {
                // Já somos o mais barato
                if (!$aggressive) {
                    $optimalPrice = min($currentPrice * 1.05, $secondLowest * 0.98);
                    $strategy = 'increase_slight';
                    $reason = 'We are cheapest, can increase slightly';
                }
            } elseif ($currentPrice <= $avgPrice) {
                // Estamos no meio
                $optimalPrice = $secondLowest * 0.97;
                $strategy = 'competitive';
                $reason = 'Position below second lowest';
            } else {
                // Estamos caros
                $optimalPrice = $aggressive ? $lowestPrice * 0.98 : $secondLowest * 0.99;
                $strategy = 'decrease';
                $reason = 'Price too high, decrease to compete';
            }

            // Aplicar restrições
            $optimalPrice = max($optimalPrice, $minPrice);
            $maxAllowedDiscount = $currentPrice * (1 - $maxDiscount);
            $optimalPrice = max($optimalPrice, $maxAllowedDiscount);

            // Arredondar para .99
            $optimalPrice = floor($optimalPrice) + 0.99;

            $change = (($optimalPrice - $currentPrice) / $currentPrice) * 100;

            return [
                'success' => true,
                'strategy' => $strategy,
                'current_price' => $currentPrice,
                'optimal_price' => $optimalPrice,
                'change_percent' => round($change, 2),
                'change_amount' => round($optimalPrice - $currentPrice, 2),
                'reason' => $reason,
                'market_data' => [
                    'lowest_price' => $lowestPrice,
                    'second_lowest' => $secondLowest,
                    'average_price' => round($avgPrice, 2),
                    'competitors_count' => count($competitors)
                ],
                'constraints' => [
                    'min_price' => $minPrice,
                    'min_margin' => $minMargin,
                    'max_discount' => $maxDiscount
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Estratégia baseada em demanda
     *
     * Elasticidade = (ΔQ / Q) / (ΔP / P)
     * Se elasticidade > 1: demanda elástica (sensível a preço)
     * Se elasticidade < 1: demanda inelástica (pode aumentar preço)
     *
     * @param string $itemId
     * @param int $days Dias para análise histórica
     * @return array
     */
    public function demandBasedPricing(string $itemId, int $days = 30): array
    {
        try {
            // Buscar histórico de vendas e preços
            $stmt = $this->db->prepare("
                SELECT price, sold_quantity, DATE(created_at) as date
                FROM item_metrics_history
                WHERE item_id = :item_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                ORDER BY created_at ASC
            ");
            $stmt->execute(['item_id' => $itemId, 'days' => $days]);
            $history = $stmt->fetchAll();

            if (count($history) < 10) {
                return [
                    'success' => false,
                    'error' => 'Insufficient data for demand analysis'
                ];
            }

            // Calcular elasticidade
            $elasticity = $this->calculatePriceElasticity($history);

            $item = $this->client->get("/items/{$itemId}");
            $currentPrice = $item['price'];

            // Estratégia baseada em elasticidade
            if ($elasticity > 1.5) {
                // Demanda muito elástica - reduzir preço
                $optimalPrice = $currentPrice * 0.95;
                $strategy = 'elastic_demand';
                $reason = 'High price sensitivity, decrease to boost sales';
            } elseif ($elasticity < 0.8) {
                // Demanda inelástica - pode aumentar
                $optimalPrice = $currentPrice * 1.08;
                $strategy = 'inelastic_demand';
                $reason = 'Low price sensitivity, safe to increase';
            } else {
                // Elasticidade normal
                $optimalPrice = $currentPrice;
                $strategy = 'maintain';
                $reason = 'Normal elasticity, maintain price';
            }

            $optimalPrice = floor($optimalPrice) + 0.99;

            return [
                'success' => true,
                'strategy' => $strategy,
                'current_price' => $currentPrice,
                'optimal_price' => $optimalPrice,
                'change_percent' => round((($optimalPrice - $currentPrice) / $currentPrice) * 100, 2),
                'elasticity' => round($elasticity, 2),
                'elasticity_type' => $elasticity > 1 ? 'elastic' : 'inelastic',
                'reason' => $reason,
                'analysis_days' => $days,
                'data_points' => count($history)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Estratégia para liquidar estoque parado
     *
     * @param string $sku
     * @param array $options [
     *   'days_in_stock' => 90, // Dias parado
     *   'target_days' => 30, // Prazo para liquidar
     *   'min_margin' => 0.05 // Margem mínima 5%
     * ]
     * @return array
     */
    public function inventoryLiquidation(string $sku, array $options = []): array
    {
        try {
            $daysInStock = $options['days_in_stock'] ?? 90;
            $targetDays = $options['target_days'] ?? 30;
            $minMargin = $options['min_margin'] ?? 0.05;

            // Buscar itens com o SKU
            $stmt = $this->db->prepare("
                SELECT item_id, price, available_quantity
                FROM items
                WHERE sku = :sku AND account_id = :account_id
            ");
            $stmt->execute(['sku' => $sku, 'account_id' => $this->accountId]);
            $items = $stmt->fetchAll();

            if (empty($items)) {
                return ['success' => false, 'error' => 'No items found with this SKU'];
            }

            $cost = $this->getItemCost($items[0]['item_id']);
            $minPrice = $cost * (1 + $minMargin);

            // Calcular desconto necessário
            $urgencyFactor = min($daysInStock / 180, 1); // 0 a 1
            $targetFactor = 1 - ($targetDays / 90); // Quanto menor o prazo, maior o desconto

            $discountPercent = 0.10 + (0.40 * $urgencyFactor * $targetFactor); // 10% a 50%

            $results = [];
            foreach ($items as $item) {
                $currentPrice = $item['price'];
                $optimalPrice = $currentPrice * (1 - $discountPercent);
                $optimalPrice = max($optimalPrice, $minPrice);
                $optimalPrice = floor($optimalPrice) + 0.99;

                $results[] = [
                    'item_id' => $item['item_id'],
                    'current_price' => $currentPrice,
                    'optimal_price' => $optimalPrice,
                    'discount_percent' => round($discountPercent * 100, 1),
                    'change_amount' => round($currentPrice - $optimalPrice, 2),
                    'available_quantity' => $item['available_quantity']
                ];
            }

            return [
                'success' => true,
                'strategy' => 'inventory_liquidation',
                'sku' => $sku,
                'days_in_stock' => $daysInStock,
                'urgency_level' => $urgencyFactor > 0.7 ? 'high' : ($urgencyFactor > 0.4 ? 'medium' : 'low'),
                'target_days' => $targetDays,
                'items' => $results,
                'total_items' => count($results),
                'total_inventory' => array_sum(array_column($results, 'available_quantity'))
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Aplica ajuste de preço automaticamente
     *
     * @param string $itemId
     * @param float $newPrice
     * @param string $strategy
     * @return array
     */
    public function applyPriceAdjustment(string $itemId, float $newPrice, string $strategy): array
    {
        try {
            $result = $this->client->put("/items/{$itemId}", [
                'price' => $newPrice
            ]);

            if (!$result) {
                return ['success' => false, 'error' => 'Failed to update price'];
            }

            // Registrar histórico
            $stmt = $this->db->prepare("
                INSERT INTO price_adjustments
                (account_id, item_id, old_price, new_price, strategy, applied_at)
                VALUES (:account_id, :item_id, :old_price, :new_price, :strategy, NOW())
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'old_price' => $result['price'], // preço antes da atualização
                'new_price' => $newPrice,
                'strategy' => $strategy
            ]);

            return [
                'success' => true,
                'item_id' => $itemId,
                'new_price' => $newPrice,
                'strategy' => $strategy,
                'applied_at' => date('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Análise batch de múltiplos itens
     *
     * @param array $itemIds
     * @param string $strategy 'competition'|'demand'|'inventory'
     * @param array $options
     * @return array
     */
    public function batchAnalysis(array $itemIds, string $strategy = 'competition', array $options = []): array
    {
        $results = [];
        $summary = [
            'total' => count($itemIds),
            'analyzed' => 0,
            'should_increase' => 0,
            'should_decrease' => 0,
            'maintain' => 0,
            'total_potential_revenue' => 0
        ];

        foreach ($itemIds as $itemId) {
            $result = null;

            switch ($strategy) {
                case 'demand':
                    $result = $this->demandBasedPricing($itemId, $options['days'] ?? 30);
                    break;
                case 'inventory':
                    // Precisa de SKU, pular por agora
                    continue 2;
                case 'competition':
                default:
                    $result = $this->calculateOptimalPrice($itemId, $options);
                    break;
            }

            if ($result['success']) {
                $results[] = $result;
                $summary['analyzed']++;

                if ($result['optimal_price'] > $result['current_price']) {
                    $summary['should_increase']++;
                } elseif ($result['optimal_price'] < $result['current_price']) {
                    $summary['should_decrease']++;
                } else {
                    $summary['maintain']++;
                }

                // Calcular impacto potencial (assumindo 10 vendas/mês)
                $priceDiff = $result['optimal_price'] - $result['current_price'];
                $summary['total_potential_revenue'] += $priceDiff * 10;
            }
        }

        return [
            'success' => true,
            'strategy' => $strategy,
            'summary' => $summary,
            'items' => $results
        ];
    }

    // ==================== PRIVATE HELPERS ====================

    /**
     * Busca custo do produto
     */
    private function getItemCost(string $itemId): float
    {
        $stmt = $this->db->prepare("
            SELECT cost_price AS cost FROM items WHERE ml_item_id = :item_id AND account_id = :account_id
        ");
        $stmt->execute(['item_id' => $itemId, 'account_id' => $this->accountId]);
        $result = $stmt->fetch();

        return $result ? (float)$result['cost'] : 0.0;
    }

    /**
     * Busca concorrentes
     */
    private function searchCompetitors(string $title, string $categoryId, int $limit = 10): array
    {
        // Extrair palavras-chave principais do título
        $keywords = $this->extractKeywords($title);
        $query = implode(' ', array_slice($keywords, 0, 3));

        $results = $this->client->get('/sites/MLB/search', [
            'q' => $query,
            'category' => $categoryId,
            'limit' => $limit
        ]);

        $competitors = [];
        foreach ($results['results'] ?? [] as $item) {
            $competitors[] = [
                'id' => $item['id'],
                'title' => $item['title'],
                'price' => $item['price'],
                'sold_quantity' => $item['sold_quantity'] ?? 0
            ];
        }

        return $competitors;
    }

    /**
     * Extrai keywords do título
     */
    private function extractKeywords(string $title): array
    {
        $stopWords = ['de', 'da', 'do', 'para', 'com', 'sem', 'em', 'a', 'o', 'e', 'ou'];
        $words = preg_split('/\s+/', strtolower($title));

        return array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }

    /**
     * Calcula elasticidade-preço da demanda
     *
     * Elasticidade = (ΔQ / Q_avg) / (ΔP / P_avg)
     */
    private function calculatePriceElasticity(array $history): float
    {
        if (count($history) < 2) {
            return 1.0;
        }

        $changes = [];
        for ($i = 1; $i < count($history); $i++) {
            $prev = $history[$i - 1];
            $curr = $history[$i];

            $deltaQ = $curr['sold_quantity'] - $prev['sold_quantity'];
            $deltaP = $curr['price'] - $prev['price'];

            if ($deltaP != 0 && $prev['sold_quantity'] > 0) {
                $percentQ = $deltaQ / $prev['sold_quantity'];
                $percentP = $deltaP / $prev['price'];

                $elasticity = $percentQ / $percentP;
                $changes[] = abs($elasticity);
            }
        }

        return empty($changes) ? 1.0 : array_sum($changes) / count($changes);
    }
}
