<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * BrandCentralService - Gestão de Lojas Oficiais (Brand Central)
 * 
 * Gerencia lojas oficiais de marcas no Mercado Livre
 * - Customização de vitrine
 * - Análise de desempenho da marca
 * - Gestão de produtos da marca
 * - Configurações visuais
 * 
 * @link https://developers.mercadolivre.com.br/pt_br/brand-central
 */
class BrandCentralService extends MercadoLivreClient
{
    private ?PDO $db;

    public function __construct(?int $accountId = null)
    {
        parent::__construct($accountId);
        try {
            $this->db = Database::getInstance();
        } catch (\Throwable) {
            $this->db = null;
        }
    }

    /**
     * Obtém informações da loja oficial
     * 
     * @return array Dados da loja
     */
    public function getBrandStore(): array
    {
        try {
            $userId = $this->getSellerId();
            $response = $this->get("/users/{$userId}/brands_official_store");

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao obter loja oficial',
                    'data' => $this->getEmptyStore(),
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'id' => $response['id'] ?? null,
                    'name' => $response['name'] ?? '',
                    'brand_name' => $response['brand']['name'] ?? '',
                    'logo' => $response['brand']['logo'] ?? null,
                    'description' => $response['description'] ?? '',
                    'status' => $response['status'] ?? 'inactive',
                    'followers' => $response['followers'] ?? 0,
                    'customization' => [
                        'primary_color' => $response['customization']['primary_color'] ?? '#000000',
                        'banner_url' => $response['customization']['banner_url'] ?? null,
                        'layout' => $response['customization']['layout'] ?? 'default',
                    ],
                    'created_at' => $response['date_created'] ?? null,
                ],
            ];
        } catch (\Exception $e) {
            log_error('Erro ao obter loja oficial', ['service' => 'BrandCentralService', 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => $this->getEmptyStore(),
            ];
        }
    }

    /**
     * Atualiza customização da loja
     * 
     * @param array $customization Dados de customização
     * @return array Resultado
     */
    public function updateStoreCustomization(array $customization): array
    {
        try {
            $userId = $this->getSellerId();
            $storeId = $this->getStoreId();

            if (!$storeId) {
                return ['success' => false, 'error' => 'Loja oficial não encontrada'];
            }

            $payload = $this->buildCustomizationPayload($customization);
            $response = $this->put("/brands_official_store/{$storeId}/customization", $payload);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao atualizar customização',
                ];
            }

            return [
                'success' => true,
                'message' => 'Customização atualizada',
                'details' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista produtos da marca
     * 
     * @param array $filters Filtros
     * @return array Lista de produtos
     */
    public function getBrandProducts(array $filters = []): array
    {
        try {
            $userId = $this->getSellerId();
            $storeId = $this->getStoreId();

            if (!$storeId) {
                return [
                    'success' => false,
                    'error' => 'Loja oficial não encontrada',
                    'data' => ['total' => 0, 'products' => [], 'paging' => []],
                ];
            }

            $params = array_merge(['seller_id' => $userId], $filters);
            $response = $this->get("/brands_official_store/{$storeId}/items", $params);

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao listar produtos',
                    'data' => ['total' => 0, 'products' => [], 'paging' => []],
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'total' => $response['paging']['total'] ?? 0,
                    'products' => $this->formatProducts($response['results'] ?? []),
                    'paging' => $response['paging'] ?? [],
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => ['total' => 0, 'products' => [], 'paging' => []],
            ];
        }
    }

    /**
     * Adiciona produto à vitrine
     * 
     * @param string $itemId ID do item
     * @param array $options Opções de exibição
     * @return array Resultado
     */
    public function addToShowcase(string $itemId, array $options = []): array
    {
        try {
            $storeId = $this->getStoreId();

            if (!$storeId) {
                return ['success' => false, 'error' => 'Loja oficial não encontrada'];
            }

            $payload = [
                'item_id' => $itemId,
                'position' => $options['position'] ?? null,
                'featured' => $options['featured'] ?? false,
                'section' => $options['section'] ?? 'main',
            ];

            $response = $this->post("/brands_official_store/{$storeId}/showcase", $payload);

            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'item_id' => $itemId,
                'message' => 'Produto adicionado à vitrine',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remove produto da vitrine
     * 
     * @param string $itemId ID do item
     * @return array Resultado
     */
    public function removeFromShowcase(string $itemId): array
    {
        try {
            $storeId = $this->getStoreId();

            if (!$storeId) {
                return ['success' => false, 'error' => 'Loja oficial não encontrada'];
            }

            $response = $this->delete("/brands_official_store/{$storeId}/showcase/{$itemId}");

            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'item_id' => $itemId,
                'message' => 'Produto removido da vitrine',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analisa performance da marca
     * 
     * @param array $filters Filtros
     * @return array Métricas
     */
    public function analyzeBrandPerformance(array $filters = []): array
    {
        try {
            if ($this->db === null) {
                return [
                    'success' => false,
                    'error' => 'Banco de dados indisponivel',
                    'data' => $this->getEmptyPerformance($filters),
                ];
            }

            $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $filters['end_date'] ?? date('Y-m-d');

            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_ticket,
                    COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.buyer.id'))) as unique_buyers
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND status = 'paid'
                AND date_created BETWEEN :start_date AND :end_date
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Buscar seguidores
            $store = $this->getBrandStore();
            $followers = $store['data']['followers'] ?? 0;

            return [
                'success' => true,
                'data' => [
                    'period' => [
                        'start' => $startDate,
                        'end' => $endDate,
                    ],
                    'total_sales' => $data['total_sales'] ?? 0,
                    'total_revenue' => round((float) ($data['total_revenue'] ?? 0), 2),
                    'avg_ticket' => round((float) ($data['avg_ticket'] ?? 0), 2),
                    'unique_buyers' => $data['unique_buyers'] ?? 0,
                    'followers' => $followers,
                    'repeat_purchase_rate' => $this->calculateRepeatRate($data),
                    'brand_loyalty_score' => $this->calculateLoyaltyScore($data, (int) $followers),
                ],
            ];
        } catch (\Exception $e) {
            log_error('Erro ao analisar marca', ['service' => 'BrandCentralService', 'error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => $this->getEmptyPerformance($filters),
            ];
        }
    }

    /**
     * Gerencia seções da vitrine
     * 
     * @param array $sections Configuração das seções
     * @return array Resultado
     */
    public function manageShowcaseSections(array $sections): array
    {
        try {
            $storeId = $this->getStoreId();

            if (!$storeId) {
                return ['success' => false, 'error' => 'Loja oficial não encontrada'];
            }

            $payload = ['sections' => $sections];
            $response = $this->put("/brands_official_store/{$storeId}/showcase/sections", $payload);

            if (isset($response['error'])) {
                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'sections_count' => count($sections),
                'message' => 'Seções atualizadas',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function getStoreId(): ?string
    {
        $store = $this->getBrandStore();
        return $store['data']['id'] ?? null;
    }

    private function buildCustomizationPayload(array $customization): array
    {
        $payload = [];

        if (isset($customization['primary_color'])) {
            $payload['primary_color'] = $customization['primary_color'];
        }

        if (isset($customization['banner_url'])) {
            $payload['banner_url'] = $customization['banner_url'];
        }

        if (isset($customization['layout'])) {
            $payload['layout'] = $customization['layout'];
        }

        if (isset($customization['description'])) {
            $payload['description'] = $customization['description'];
        }

        if (isset($customization['logo'])) {
            $payload['logo'] = $customization['logo'];
        }

        return $payload;
    }

    private function formatProducts(array $products): array
    {
        return array_map(function($product) {
            return [
                'id' => $product['id'],
                'title' => $product['title'],
                'price' => $product['price'],
                'available_quantity' => $product['available_quantity'] ?? 0,
                'sold_quantity' => $product['sold_quantity'] ?? 0,
                'thumbnail' => $product['thumbnail'] ?? null,
                'permalink' => $product['permalink'] ?? null,
                'status' => $product['status'] ?? 'unknown',
            ];
        }, $products);
    }

    private function calculateRepeatRate(array $data): float
    {
        $totalSales = (int) ($data['total_sales'] ?? 0);
        $uniqueBuyers = (int) ($data['unique_buyers'] ?? 0);

        if ($totalSales <= 0 || $uniqueBuyers <= 0) {
            return 0.0;
        }

        $repeatPurchases = max(0, $totalSales - $uniqueBuyers);
        return round(($repeatPurchases / $totalSales) * 100, 2);
    }

    private function calculateLoyaltyScore(array $data, int $followers): int
    {
        $score = 50; // Base

        // Seguidores
        if ($followers > 1000) $score += 15;
        elseif ($followers > 500) $score += 10;
        elseif ($followers > 100) $score += 5;

        // Taxa de recompra
        $repeatRate = $this->calculateRepeatRate($data);
        if ($repeatRate > 30) $score += 20;
        elseif ($repeatRate > 20) $score += 15;
        elseif ($repeatRate > 10) $score += 10;

        // Ticket médio
        $avgTicket = $data['avg_ticket'] ?? 0;
        if ($avgTicket > 200) $score += 15;
        elseif ($avgTicket > 100) $score += 10;
        elseif ($avgTicket > 50) $score += 5;

        return min(100, $score);
    }

    private function getEmptyStore(): array
    {
        return [
            'id' => null,
            'name' => '',
            'brand_name' => '',
            'status' => 'inactive',
            'followers' => 0,
            'customization' => [
                'primary_color' => '#000000',
                'banner_url' => null,
                'layout' => 'default',
            ],
        ];
    }

    private function getEmptyPerformance(array $filters): array
    {
        return [
            'period' => [
                'start' => $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
                'end' => $filters['end_date'] ?? date('Y-m-d'),
            ],
            'total_sales' => 0,
            'total_revenue' => 0.0,
            'avg_ticket' => 0.0,
            'unique_buyers' => 0,
            'followers' => 0,
            'repeat_purchase_rate' => 0.0,
            'brand_loyalty_score' => 0,
        ];
    }
}
