<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * ShippingService - Gestão Avançada de Envios
 *
 * Expande funcionalidades de envios do Mercado Livre com:
 * - Preferências avançadas de shipping
 * - Gestão de dimensões e pesos
 * - Configuração de free shipping
 * - Picking lists e etiquetas
 *
 * @link https://developers.mercadolivre.com.br/pt_br/envios
 */
class ShippingService
{
    private MercadoLivreClient $client;
    private ?PDO $db;
    private ?int $accountId;

    public function __construct(
        ?int $accountId = null,
        ?MercadoLivreClient $client = null,
        ?PDO $db = null,
        bool $skipDbAutoConnect = false
    ) {
        $this->accountId = $accountId;
        $this->client = $client ?? new MercadoLivreClient($accountId);

        if ($db !== null) {
            $this->db = $db;
        } elseif ($skipDbAutoConnect) {
            $this->db = null;
        } else {
            $this->db = Database::getInstance();
        }
    }

    /**
     * Obtém preferências de envio do vendedor
     *
     * @return array Preferências configuradas
     */
    public function getShippingPreferences(): array
    {
        try {
            $userId = $this->client->getSellerId();
            $response = $this->client->get("/users/{$userId}/shipping_preferences");

            if (isset($response['error'])) {
                if (($response['error'] ?? '') === 'shipping_preferences_unavailable') {
                    return array_merge($this->getDefaultPreferences(), [
                        'available' => false,
                        'source' => 'feature_unavailable',
                        'message' => $response['message'] ?? 'Preferências de envio indisponíveis para esta conta.',
                    ]);
                }

                return $this->getDefaultPreferences();
            }

            return [
                'free_methods' => $response['free_methods'] ?? [],
                'cost_rule' => $response['cost_rule'] ?? 'default',
                'free_shipping_rules' => $response['free_shipping_rules'] ?? [],
                'handling_time' => [
                    'value' => $response['handling_time']['value'] ?? 24,
                    'unit' => $response['handling_time']['unit'] ?? 'hours',
                ],
                'local_pickup' => $response['local_pick_up'] ?? false,
                'dimensions' => [
                    'default_width' => $response['dimensions']['default_width'] ?? null,
                    'default_height' => $response['dimensions']['default_height'] ?? null,
                    'default_length' => $response['dimensions']['default_length'] ?? null,
                    'default_weight' => $response['dimensions']['default_weight'] ?? null,
                ],
                'available' => true,
                'source' => 'mercado_livre',
                'message' => null,
            ];
        } catch (\Exception $e) {
            log_error('Erro ao obter preferências de envio', [
                'error' => $e->getMessage(),
            ]);
            return $this->getDefaultPreferences();
        }
    }

    /**
     * Atualiza preferências de envio
     *
     * @param array $preferences Novas preferências
     * @return array Resultado
     */
    public function updateShippingPreferences(array $preferences): array
    {
        try {
            $userId = $this->client->getSellerId();
            $payload = $this->buildPreferencesPayload($preferences);

            $response = $this->client->put("/users/{$userId}/shipping_preferences", $payload);

            if (isset($response['error'])) {
                if (($response['error'] ?? '') === 'shipping_preferences_unavailable') {
                    return [
                        'success' => false,
                        'feature_unavailable' => true,
                        'feature' => 'shipping_preferences',
                        'error' => $response['message'] ?? 'Preferências de envio indisponíveis para esta conta.',
                    ];
                }

                return [
                    'success' => false,
                    'error' => $response['message'] ?? 'Erro ao atualizar preferências',
                ];
            }

            return [
                'success' => true,
                'message' => 'Preferências atualizadas com sucesso',
                'details' => $response,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Configura frete grátis por categoria/valor
     *
     * @param array $rules Regras de frete grátis
     * @return array Resultado
     */
    public function configureFreeShipping(array $rules): array
    {
        try {
            $userId = $this->client->getSellerId();

            $payload = [
                'free_shipping_rules' => $rules,
            ];

            $response = $this->client->put("/users/{$userId}/shipping_preferences", $payload);

            if (isset($response['error'])) {
                if (($response['error'] ?? '') === 'shipping_preferences_unavailable') {
                    return [
                        'success' => false,
                        'feature_unavailable' => true,
                        'feature' => 'shipping_preferences',
                        'error' => $response['message'] ?? 'Preferências de envio indisponíveis para esta conta.',
                    ];
                }

                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'rules_count' => count($rules),
                'message' => 'Frete grátis configurado',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Simula custo de envio para item
     *
     * @param array $itemData Dados do item
     * @param string $zipcode CEP de destino
     * @return array Simulação
     */
    public function simulateShippingCost(array $itemData, string $zipcode): array
    {
        try {
            $payload = [
                'dimensions' => [
                    'width' => $itemData['width'] ?? 10,
                    'height' => $itemData['height'] ?? 10,
                    'length' => $itemData['length'] ?? 10,
                    'weight' => $itemData['weight'] ?? 500,
                ],
                'from' => [
                    'zip_code' => $itemData['origin_zipcode'] ?? '',
                ],
                'to' => [
                    'zip_code' => $zipcode,
                ],
                'item_price' => $itemData['price'] ?? 0,
                'free_shipping' => $itemData['free_shipping'] ?? false,
            ];

            $response = $this->client->post("/shipping_options/simulate", $payload);

            if (isset($response['error'])) {
                return $this->getDefaultSimulation();
            }

            return [
                'options' => $this->formatShippingOptions($response['options'] ?? []),
                'cheapest' => $this->findCheapest($response['options'] ?? []),
                'fastest' => $this->findFastest($response['options'] ?? []),
            ];
        } catch (\Exception $e) {
            log_warning('Erro ao simular frete', [
                'error' => $e->getMessage(),
            ]);
            return $this->getDefaultSimulation();
        }
    }

    /**
     * Obtém dimensões recomendadas por categoria
     *
     * @param string $categoryId ID da categoria
     * @return array Dimensões sugeridas
     */
    public function getCategoryDimensions(string $categoryId): array
    {
        try {
            $response = $this->client->get("/categories/{$categoryId}/shipping_preferences");

            if (isset($response['error'])) {
                return $this->getDefaultDimensions();
            }

            return [
                'recommended_width' => $response['dimensions']['width'] ?? 10,
                'recommended_height' => $response['dimensions']['height'] ?? 10,
                'recommended_length' => $response['dimensions']['length'] ?? 10,
                'recommended_weight' => $response['dimensions']['weight'] ?? 500,
                'max_weight' => $response['dimensions']['max_weight'] ?? 30000,
            ];
        } catch (\Exception $e) {
            return $this->getDefaultDimensions();
        }
    }

    /**
     * Valida dimensões de pacote
     *
     * @param array $dimensions Dimensões
     * @return array Resultado validação
     */
    public function validateDimensions(array $dimensions): array
    {
        $errors = [];

        // Limites dos Correios/ML
        $maxSum = 200; // soma das 3 dimensões (cm)
        $maxSingle = 105; // dimensão individual máxima (cm)
        $minSum = 26; // soma mínima (cm)
        $maxWeight = 30000; // 30kg (gramas)

        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;
        $length = $dimensions['length'] ?? 0;
        $weight = $dimensions['weight'] ?? 0;

        $sum = $width + $height + $length;

        if ($sum > $maxSum) {
            $errors[] = "Soma das dimensões ({$sum}cm) excede limite de {$maxSum}cm";
        }

        if ($sum < $minSum) {
            $errors[] = "Soma das dimensões ({$sum}cm) abaixo do mínimo de {$minSum}cm";
        }

        if ($width > $maxSingle || $height > $maxSingle || $length > $maxSingle) {
            $errors[] = "Nenhuma dimensão pode exceder {$maxSingle}cm";
        }

        if ($weight > $maxWeight) {
            $errors[] = "Peso ({$weight}g) excede limite de {$maxWeight}g (30kg)";
        }

        if ($weight < 1) {
            $errors[] = "Peso deve ser maior que 0";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'dimensions_ok' => $sum >= $minSum && $sum <= $maxSum,
            'weight_ok' => $weight > 0 && $weight <= $maxWeight,
        ];
    }

    /**
     * Obtém etiquetas de envio (labels)
     *
     * @param array $shipmentIds IDs dos envios
     * @param string $format Formato (pdf, zpl)
     * @return array URLs das etiquetas
     */
    public function getShippingLabels(array $shipmentIds, string $format = 'pdf'): array
    {
        try {
            $labels = [];

            foreach ($shipmentIds as $shipmentId) {
                $response = $this->client->get("/shipments/{$shipmentId}/labels", [
                    'response_type' => $format,
                ]);

                if (!isset($response['error'])) {
                    $labels[] = [
                        'shipment_id' => $shipmentId,
                        'url' => $response['url'] ?? null,
                        'format' => $format,
                    ];
                }
            }

            return [
                'total' => count($labels),
                'labels' => $labels,
            ];
        } catch (\Exception $e) {
            log_error('Erro ao obter etiquetas de envio', [
                'error' => $e->getMessage(),
            ]);
            return ['total' => 0, 'labels' => []];
        }
    }

    /**
     * Configura handling time (tempo de preparação)
     *
     * @param int $value Valor
     * @param string $unit Unidade (hours, days)
     * @return array Resultado
     */
    public function setHandlingTime(int $value, string $unit = 'hours'): array
    {
        try {
            $userId = $this->client->getSellerId();

            $payload = [
                'handling_time' => [
                    'value' => $value,
                    'unit' => $unit,
                ],
            ];

            $response = $this->client->put("/users/{$userId}/shipping_preferences", $payload);

            if (isset($response['error'])) {
                if (($response['error'] ?? '') === 'shipping_preferences_unavailable') {
                    return [
                        'success' => false,
                        'feature_unavailable' => true,
                        'feature' => 'shipping_preferences',
                        'error' => $response['message'] ?? 'Preferências de envio indisponíveis para esta conta.',
                    ];
                }

                return ['success' => false, 'error' => $response['message'] ?? 'Erro'];
            }

            return [
                'success' => true,
                'handling_time' => "{$value} {$unit}",
                'message' => 'Tempo de preparação atualizado',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Analisa performance de envios
     *
     * @param array $filters Filtros
     * @return array Métricas
     */
    public function analyzeShippingPerformance(array $filters = []): array
    {
        try {
            if ($this->db === null) {
                return [
                    'total_shipments' => 0,
                    'delivery_rate' => 0,
                    'delay_rate' => 0,
                    'score' => 0,
                    'error' => 'db_unavailable',
                ];
            }

            $startDate = $filters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $endDate = $filters['end_date'] ?? date('Y-m-d');

            // Query local database
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_shipments,
                    AVG(TIMESTAMPDIFF(HOUR, created_at, shipped_at)) as avg_handling_hours,
                    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
                    COUNT(CASE WHEN delayed = 1 THEN 1 END) as delayed
                FROM shipments
                WHERE account_id = :account_id
                AND created_at BETWEEN :start_date AND :end_date
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = $data['total_shipments'] ?? 0;
            $delivered = $data['delivered'] ?? 0;
            $cancelled = $data['cancelled'] ?? 0;
            $delayed = $data['delayed'] ?? 0;

            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                ],
                'total_shipments' => $total,
                'delivered' => $delivered,
                'cancelled' => $cancelled,
                'delayed' => $delayed,
                'delivery_rate' => $total > 0 ? round(($delivered / $total) * 100, 2) : 0,
                'delay_rate' => $total > 0 ? round(($delayed / $total) * 100, 2) : 0,
                'avg_handling_hours' => round($data['avg_handling_hours'] ?? 0, 2),
                'score' => $this->calculateShippingScore($data),
            ];
        } catch (\Exception $e) {
            log_error('Erro ao analisar performance de envios', [
                'error' => $e->getMessage(),
            ]);
            return [
                'total_shipments' => 0,
                'delivery_rate' => 0,
                'delay_rate' => 0,
                'score' => 0,
            ];
        }
    }

    /**
     * Gera uma lista de separação (Picking List) para os pedidos selecionados
     * Agrupa por SKU/Título para facilitar a coleta no estoque
     */
    public function generatePickList(array $orderIds): array
    {
        if (empty($orderIds) || $this->db === null) {
            return [];
        }

        $normalizedIds = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $orderIds), static function (string $value): bool {
            return $value !== '';
        }));

        if (empty($normalizedIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));

        $sql = "SELECT id, ml_order_id, order_data FROM ml_orders
                WHERE (id IN ($placeholders) OR ml_order_id IN ($placeholders))";
        $params = array_merge($normalizedIds, $normalizedIds);
        if ($this->accountId !== null && $this->accountId > 0) {
            $sql .= " AND ml_account_id = ?";
            $params[] = $this->accountId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pickList = [];

        foreach ($orders as $order) {
            $orderData = json_decode((string) ($order['order_data'] ?? '{}'), true);
            if (!is_array($orderData)) {
                continue;
            }

            $items = $orderData['order_items'] ?? $orderData['items'] ?? [];
            if (!is_array($items)) {
                continue;
            }

            $orderIdentifier = (string) ($order['ml_order_id'] ?? $order['id'] ?? '');
            if ($orderIdentifier === '') {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $quantity = (int) ($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $title = (string) ($item['item']['title'] ?? $item['title'] ?? 'Item sem titulo');
                $sku = (string) (
                    $item['item']['seller_sku']
                    ?? $item['seller_sku']
                    ?? $item['sku']
                    ?? $item['item']['id']
                    ?? $item['item_id']
                    ?? $item['id']
                    ?? 'N/A'
                );

                if (!isset($pickList[$sku])) {
                    $pickList[$sku] = [
                        'sku' => $sku,
                        'title' => $title,
                        'total_quantity' => 0,
                        'orders' => []
                    ];
                }

                $pickList[$sku]['total_quantity'] += $quantity;
                $pickList[$sku]['orders'][] = $orderIdentifier;
            }
        }

        foreach ($pickList as &$entry) {
            $entry['orders'] = array_values(array_unique($entry['orders']));
        }
        unset($entry);

        $pickList = array_values($pickList);

        usort($pickList, function ($a, $b) {
            return strcmp((string) $a['title'], (string) $b['title']);
        });

        return $pickList;
    }

    /**
     * Gera PDF da Picking List
     */
    public function generatePickListPDF(array $orderIds): string
    {
        $items = $this->generatePickList($orderIds);

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);

        $html = '<html><body>';
        $html .= '<h1>Picking List (Lista de Separacao)</h1>';
        $html .= '<p>Data: ' . date('d/m/Y H:i') . '</p>';
        $html .= '<table width="100%" border="1" cellspacing="0" cellpadding="5">';
        $html .= '<thead><tr style="background:#eee;"><th>SKU</th><th>Produto</th><th>Qtd</th><th>Pedidos</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars((string) $item['sku']) . '</td>';
            $html .= '<td>' . htmlspecialchars((string) $item['title']) . '</td>';
            $html .= '<td style="text-align:center; font-weight:bold; font-size:1.2em;">' . (int) $item['total_quantity'] . '</td>';
            $html .= '<td>' . implode(', ', $item['orders']) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></body></html>';

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Obtém IDs de envio dos pedidos
     */
    public function getShippingIds(array $orderIds): array
    {
        if (empty($orderIds) || $this->db === null) {
            return [];
        }

        $normalizedIds = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $orderIds), static function (string $value): bool {
            return $value !== '';
        }));

        if (empty($normalizedIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
        $sql = "SELECT order_data FROM ml_orders
                WHERE (id IN ($placeholders) OR ml_order_id IN ($placeholders))";
        $params = array_merge($normalizedIds, $normalizedIds);
        if ($this->accountId !== null && $this->accountId > 0) {
            $sql .= " AND ml_account_id = ?";
            $params[] = $this->accountId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $shippingIds = [];
        foreach ($rows as $row) {
            $orderData = json_decode((string) ($row['order_data'] ?? '{}'), true);
            if (!is_array($orderData)) {
                continue;
            }

            $shippingId = $orderData['shipping']['id'] ?? $orderData['shipping_id'] ?? null;
            if ($shippingId !== null && $shippingId !== '') {
                $shippingIds[] = (string) $shippingId;
            }
        }

        return array_values(array_unique($shippingIds));
    }

    // ==================== MÉTODOS PRIVADOS ====================

    private function buildPreferencesPayload(array $preferences): array
    {
        $payload = [];

        if (isset($preferences['handling_time'])) {
            $payload['handling_time'] = $preferences['handling_time'];
        }

        if (isset($preferences['free_methods'])) {
            $payload['free_methods'] = $preferences['free_methods'];
        }

        if (isset($preferences['cost_rule'])) {
            $payload['cost_rule'] = $preferences['cost_rule'];
        }

        if (isset($preferences['local_pickup'])) {
            $payload['local_pick_up'] = $preferences['local_pickup'];
        }

        if (isset($preferences['dimensions'])) {
            $payload['dimensions'] = $preferences['dimensions'];
        }

        return $payload;
    }

    private function formatShippingOptions(array $options): array
    {
        return array_map(function ($opt) {
            return [
                'id' => $opt['shipping_method_id'] ?? null,
                'name' => $opt['name'] ?? 'N/A',
                'cost' => $opt['cost'] ?? 0,
                'currency_id' => $opt['currency_id'] ?? 'BRL',
                'estimated_delivery' => [
                    'date' => $opt['estimated_delivery_time']['date'] ?? null,
                    'time_from' => $opt['estimated_delivery_time']['time_from'] ?? 0,
                    'time_to' => $opt['estimated_delivery_time']['time_to'] ?? 0,
                    'unit' => $opt['estimated_delivery_time']['unit'] ?? 'hours',
                ],
            ];
        }, $options);
    }

    private function findCheapest(array $options): ?array
    {
        if (empty($options)) return null;

        $cheapest = null;
        $minCost = PHP_FLOAT_MAX;

        foreach ($options as $opt) {
            $cost = $opt['cost'] ?? PHP_FLOAT_MAX;
            if ($cost < $minCost) {
                $minCost = $cost;
                $cheapest = $opt;
            }
        }

        return $cheapest;
    }

    private function findFastest(array $options): ?array
    {
        if (empty($options)) return null;

        $fastest = null;
        $minTime = PHP_INT_MAX;

        foreach ($options as $opt) {
            $time = $opt['estimated_delivery_time']['time_to'] ?? PHP_INT_MAX;
            if ($time < $minTime) {
                $minTime = $time;
                $fastest = $opt;
            }
        }

        return $fastest;
    }

    private function calculateShippingScore(array $data): int
    {
        $score = 100;

        $total = $data['total_shipments'] ?? 0;
        if ($total === 0) return 0;

        // Penalidades
        $deliveryRate = ($data['delivered'] ?? 0) / $total;
        $delayRate = ($data['delayed'] ?? 0) / $total;
        $cancelRate = ($data['cancelled'] ?? 0) / $total;

        if ($deliveryRate < 0.95) $score -= 20; // < 95% entrega
        if ($delayRate > 0.05) $score -= 15; // > 5% atrasos
        if ($cancelRate > 0.03) $score -= 15; // > 3% cancelamentos

        $avgHandling = $data['avg_handling_hours'] ?? 0;
        if ($avgHandling > 48) $score -= 10; // > 2 dias preparação

        return max(0, $score);
    }

    private function getDefaultPreferences(): array
    {
        return [
            'free_methods' => [],
            'cost_rule' => 'default',
            'free_shipping_rules' => [],
            'handling_time' => ['value' => 24, 'unit' => 'hours'],
            'local_pickup' => false,
            'dimensions' => [
                'default_width' => 10,
                'default_height' => 10,
                'default_length' => 10,
                'default_weight' => 500,
            ],
            'available' => false,
            'source' => 'default',
            'message' => null,
        ];
    }

    private function getDefaultDimensions(): array
    {
        return [
            'recommended_width' => 10,
            'recommended_height' => 10,
            'recommended_length' => 10,
            'recommended_weight' => 500,
            'max_weight' => 30000,
        ];
    }

    private function getDefaultSimulation(): array
    {
        return [
            'options' => [],
            'cheapest' => null,
            'fastest' => null,
        ];
    }
}
