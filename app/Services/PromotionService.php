<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * PromotionService - Gestão Avançada de Promoções
 * 
 * Expande funcionalidades de promoções do Mercado Livre com:
 * - Cupons personalizados
 * - Campanhas de co-participação
 * - Deals e Flash Sales
 * - Análise de performance de promoções
 * 
 * @link https://developers.mercadolivre.com.br/pt_br/promocoes
 */
class PromotionService extends MercadoLivreClient
{
    private PDO $db;

    public function __construct(?int $accountId = null)
    {
        parent::__construct($accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Lista todas promoções disponíveis
     * 
     * @param array $filters Filtros
     * @return array Lista de promoções
     */
    public function getPromotions(array $filters = []): array
    {
        try {
            $userId = $this->getSellerId();
            $params = array_merge(['seller_id' => $userId], $filters);
            
            $response = $this->get("/seller-promotions/promotions", $params);
            
            if (isset($response['error'])) {
                return $this->getEmptyPromotions();
            }
            
            return [
                'total' => count($response['results'] ?? []),
                'promotions' => $this->formatPromotions($response['results'] ?? []),
            ];
            
        } catch (\Exception $e) {
            log_error('Erro ao listar promoções', [
                'account_id' => $this->accountId ?? null,
                'error' => $e->getMessage(),
            ]);
            return $this->getEmptyPromotions();
        }
    }

    /**
     * Lista itens elegíveis para promoção
     * 
     * @param string $promotionId ID da promoção
     * @return array Lista de itens
     */
    public function getPromotionItems(string $promotionId): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/seller-promotions/promotions/{$promotionId}/items", [
                'seller_id' => $userId,
            ]);
             
            if (isset($response['error'])) {
                return ['total' => 0, 'items' => []];
            }

            return [
                'total' => count($response['results'] ?? []),
                'items' => $this->formatPromotionItems($response['results'] ?? [], $promotionId),
            ];
             
        } catch (\Exception $e) {
            log_error('Erro ao listar itens da promoção', [
                'promotion_id' => $promotionId,
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'items' => []];
        }
    }

    /**
     * Participa itens em promoção
     * 
     * @param string $promotionId ID da promoção
     * @param array $items Lista de itens
     * @return array Resultado
     */
    public function joinPromotion(string $promotionId, array $items): array
    {
        try {
            $payload = [
                'promotion_id' => $promotionId,
                'items' => array_map(function($item) {
                    return [
                        'item_id' => $item['item_id'],
                        'price' => $item['price'] ?? null,
                    ];
                }, $items),
            ];
            
            $response = $this->post("/seller-promotions/promotions/{$promotionId}/items", $payload);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao participar',
                ];
            }

            return [
                'success' => true,
                'items_added' => count($items),
                'promotion_id' => $promotionId,
                'message' => 'Itens adicionados à promoção',
            ];
        
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Cria cupom de desconto
     * 
     * @param array $couponData Dados do cupom
     * @return array Resultado
     */
    public function createCoupon(array $couponData): array
    {
        try {
            $userId = $this->getSellerId();
            
            $payload = $this->buildCouponPayload($couponData);
            $response = $this->post("/seller-promotions/coupons?seller_id={$userId}", $payload);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao criar cupom',
                ];
            }

            return [
                'success' => true,
                'coupon_id' => $response['id'] ?? null,
                'code' => $response['code'] ?? null,
                'discount' => $response['discount'] ?? null,
                'details' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista cupons ativos
     * 
     * @return array Lista de cupons
     */
    public function listCoupons(): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/seller-promotions/coupons", ['seller_id' => $userId]);

            if (isset($response['error'])) {
                return ['total' => 0, 'coupons' => []];
            }

            return [
                'total' => count($response['results'] ?? []),
                'coupons' => $this->formatCoupons($response['results'] ?? []),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao listar cupons', [
                'service' => 'PromotionService',
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'coupons' => []];
        }
    }

    /**
     * Atualiza status de cupom
     * 
     * @param string $couponId ID do cupom
     * @param string $status Novo status (active, paused)
     * @return array Resultado
     */
    public function updateCouponStatus(string $couponId, string $status): array
    {
        try {
            $userId = $this->getSellerId();
            $payload = ['status' => $status];
            
            $response = $this->put("/seller-promotions/coupons/{$couponId}?seller_id={$userId}", $payload);

            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'coupon_id' => $couponId,
                'status' => $status,
                'message' => 'Status atualizado',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtém performance de cupom
     * 
     * @param string $couponId ID do cupom
     * @return array Métricas
     */
    public function getCouponPerformance(string $couponId): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/seller-promotions/coupons/{$couponId}/performance", [
                'seller_id' => $userId,
            ]);

            if (isset($response['error'])) {
                return $this->getEmptyCouponMetrics();
            }

            return [
                'coupon_id' => $couponId,
                'redemptions' => $response['redemptions'] ?? 0,
                'revenue' => $response['revenue'] ?? 0,
                'discount_given' => $response['discount_given'] ?? 0,
                'orders' => $response['orders'] ?? 0,
                'conversion_rate' => $this->calculateConversionRate($response),
                'roi' => $this->calculateCouponROI($response),
            ];
        } catch (\Exception $e) {
            return $this->getEmptyCouponMetrics();
        }
    }

    /**
     * Cria campanha de co-participação
     * 
     * @param array $campaignData Dados da campanha
     * @return array Resultado
     */
    public function createCoParticipationCampaign(array $campaignData): array
    {
        try {
            $userId = $this->getSellerId();
            
            $payload = [
                'name' => $campaignData['name'],
                'start_date' => $campaignData['start_date'],
                'end_date' => $campaignData['end_date'],
                'discount_percentage' => $campaignData['discount_percentage'],
                'seller_participation' => $campaignData['seller_participation'] ?? 50, // % do vendedor
                'ml_participation' => $campaignData['ml_participation'] ?? 50, // % do ML
                'items' => $campaignData['items'] ?? [],
            ];

            $response = $this->post("/seller-promotions/co-participation?seller_id={$userId}", $payload);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao criar campanha',
                ];
            }

            return [
                'success' => true,
                'campaign_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? 'pending',
                'details' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista campanhas de co-participação
     * 
     * @return array Lista de campanhas
     */
    public function listCoParticipationCampaigns(): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/seller-promotions/co-participation", ['seller_id' => $userId]);

            if (isset($response['error'])) {
                return ['total' => 0, 'campaigns' => []];
            }

            return [
                'total' => count($response['results'] ?? []),
                'campaigns' => $this->formatCampaigns($response['results'] ?? []),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao listar campanhas de co-participação', [
                'service' => 'PromotionService',
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'campaigns' => []];
        }
    }

    /**
     * Simula impacto de desconto
     * 
     * @param string $itemId ID do item
     * @param float $discountPercentage Desconto em %
     * @return array Simulação
     */
    public function simulateDiscountImpact(string $itemId, float $discountPercentage): array
    {
        try {
            // Buscar dados do item
            $item = $this->get("/items/{$itemId}");

            if (isset($item['error'])) {
                return ['success' => false, 'error' => 'Item não encontrado'];
            }

            $currentPrice = $item['price'];
            $newPrice = $currentPrice * (1 - $discountPercentage / 100);
            
            // Estimativa de aumento de conversão (simplificada)
            $conversionIncrease = min($discountPercentage * 2, 100); // 2x o desconto, max 100%

            return [
                'success' => true,
                'item_id' => $itemId,
                'current_price' => $currentPrice,
                'new_price' => round($newPrice, 2),
                'discount_amount' => round($currentPrice - $newPrice, 2),
                'discount_percentage' => $discountPercentage,
                'estimated_conversion_increase' => round($conversionIncrease, 2) . '%',
                'estimated_sales_increase' => round($conversionIncrease * 1.5, 2) . '%', // Estimativa
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analisa performance geral de promoções
     * 
     * @param array $filters Filtros
     * @return array Métricas
     */
    public function analyzePromotionsPerformance(array $filters = []): array
    {
        try {
            $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $filters['end_date'] ?? date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT promotion_id) as total_promotions,
                    SUM(sales) as total_sales,
                    SUM(revenue) as total_revenue,
                    SUM(discount_given) as total_discount,
                    AVG(conversion_rate) as avg_conversion
                FROM promotion_performance
                WHERE account_id = :account_id
                AND date BETWEEN :start_date AND :end_date
            ");

            $stmt->execute([
                'account_id' => $this->getAccountId(),
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $revenue = $data['total_revenue'] ?? 0;
            $discount = $data['total_discount'] ?? 0;
            $netRevenue = $revenue - $discount;

            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_promotions' => $data['total_promotions'] ?? 0,
                'total_sales' => $data['total_sales'] ?? 0,
                'total_revenue' => round($revenue, 2),
                'total_discount' => round($discount, 2),
                'net_revenue' => round($netRevenue, 2),
                'avg_conversion_rate' => round($data['avg_conversion'] ?? 0, 2),
                'roi' => $discount > 0 ? round((($revenue - $discount) / $discount) * 100, 2) : 0,
                'discount_rate' => $revenue > 0 ? round(($discount / $revenue) * 100, 2) : 0,
            ];
        } catch (\Exception $e) {
            log_error('Erro ao analisar promoções', [
                'error' => $e->getMessage(),
            ]);
            return [
                'total_promotions' => 0,
                'total_sales' => 0,
                'total_revenue' => 0,
                'roi' => 0,
            ];
        }
    }

    /**
     * Obtém sugestões de itens para promoção
     * 
     * @param array $criteria Critérios
     * @return array Itens sugeridos
     */
    public function getSuggestedItems(array $criteria = []): array
    {
        try {
            $minVisits = $criteria['min_visits'] ?? 100;
            $maxConversion = $criteria['max_conversion'] ?? 5;

            $stmt = $this->db->prepare("
                SELECT 
                    item_id,
                    title,
                    price,
                    visits,
                    sales,
                    (sales / NULLIF(visits, 0)) * 100 as conversion_rate
                FROM item_metrics
                WHERE account_id = :account_id
                AND visits >= :min_visits
                AND (sales / NULLIF(visits, 0)) * 100 <= :max_conversion
                ORDER BY visits DESC
                LIMIT 20
            ");

            $stmt->execute([
                'account_id' => $this->getAccountId(),
                'min_visits' => $minVisits,
                'max_conversion' => $maxConversion,
            ]);

            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'total' => count($items),
                'items' => array_map(function($item) {
                    return [
                        'item_id' => $item['item_id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'visits' => $item['visits'],
                        'conversion_rate' => round($item['conversion_rate'] ?? 0, 2),
                        'suggested_discount' => $this->suggestDiscount($item),
                    ];
                }, $items),
                'criteria' => $criteria,
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'items' => []];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function buildCouponPayload(array $data): array
    {
        return [
            'code' => $data['code'] ?? $this->generateCouponCode(),
            'discount_type' => $data['discount_type'] ?? 'percentage', // percentage, fixed
            'discount_value' => $data['discount_value'],
            'min_purchase_amount' => $data['min_purchase_amount'] ?? 0,
            'max_uses' => $data['max_uses'] ?? 100,
            'start_date' => $data['start_date'] ?? date('Y-m-d'),
            'end_date' => $data['end_date'],
            'items' => $data['items'] ?? [], // IDs dos itens
        ];
    }

    private function generateCouponCode(): string
    {
        $seed = (string) microtime(true);
        return 'CUPOM' . strtoupper(substr(md5($seed), 0, 8));
    }

    private function formatPromotions(array $promotions): array
    {
        return array_map(function($p) {
            return [
                'id' => $p['id'],
                'type' => $p['type'],
                'name' => $p['name'] ?? $p['type'],
                'status' => $p['status'],
                'start_date' => $p['start_date'],
                'finish_date' => $p['finish_date'],
                'benefits' => $p['benefits'] ?? [],
            ];
        }, $promotions);
    }

    private function formatPromotionItems(array $items, string $promotionId): array
    {
        return array_map(function($item) use ($promotionId) {
            return [
                'id' => $item['id'],
                'title' => $item['name'] ?? $item['id'],
                'price' => $item['price'],
                'status' => $item['status'] ?? 'candidate',
                'suggested_price' => $item['suggested_price'] ?? ($item['price'] * 0.9),
                'promotion_id' => $promotionId,
            ];
        }, $items);
    }

    private function formatCoupons(array $coupons): array
    {
        return array_map(function($c) {
            return [
                'id' => $c['id'],
                'code' => $c['code'],
                'discount_type' => $c['discount_type'],
                'discount_value' => $c['discount_value'],
                'uses' => $c['uses'] ?? 0,
                'max_uses' => $c['max_uses'],
                'status' => $c['status'],
                'valid_from' => $c['start_date'],
                'valid_until' => $c['end_date'],
            ];
        }, $coupons);
    }

    private function formatCampaigns(array $campaigns): array
    {
        return array_map(function($c) {
            return [
                'id' => $c['id'],
                'name' => $c['name'],
                'discount_percentage' => $c['discount_percentage'],
                'seller_participation' => $c['seller_participation'],
                'ml_participation' => $c['ml_participation'],
                'status' => $c['status'],
                'start_date' => $c['start_date'],
                'end_date' => $c['end_date'],
                'items_count' => count($c['items'] ?? []),
            ];
        }, $campaigns);
    }

    private function calculateConversionRate(array $data): float
    {
        $redemptions = $data['redemptions'] ?? 0;
        $impressions = $data['impressions'] ?? 1;
        
        return $impressions > 0 ? round(($redemptions / $impressions) * 100, 2) : 0;
    }

    private function calculateCouponROI(array $data): float
    {
        $revenue = $data['revenue'] ?? 0;
        $discount = $data['discount_given'] ?? 1;
        
        return $discount > 0 ? round((($revenue - $discount) / $discount) * 100, 2) : 0;
    }

    private function suggestDiscount(array $item): float
    {
        $conversion = $item['conversion_rate'] ?? 0;
        
        // Sugestão baseada na conversão atual
        if ($conversion < 1) return 20; // Baixa conversão = desconto maior
        if ($conversion < 3) return 15;
        if ($conversion < 5) return 10;
        
        return 5; // Conversão boa = desconto menor
    }

    private function getEmptyPromotions(): array
    {
        return [
            'total' => 0,
            'promotions' => [],
        ];
    }

    private function getEmptyCouponMetrics(): array
    {
        return [
            'redemptions' => 0,
            'revenue' => 0,
            'discount_given' => 0,
            'orders' => 0,
            'conversion_rate' => 0,
            'roi' => 0,
        ];
    }
}
