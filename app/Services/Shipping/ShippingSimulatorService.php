<?php
declare(strict_types=1);

namespace App\Services\Shipping;

use App\Services\MercadoLivreClient;

/**
 * Shipping Simulator Service - Simula custos e prazos de envio
 * 
 * Calcula custos de envio para diferentes modalidades:
 * - Mercado Envios (ME1)
 * - Mercado Envios 2 (ME2)
 * - Flex
 * - Full (Fulfillment)
 * - Envio customizado
 * 
 * Usa a API oficial: /users/{user_id}/shipping_options e /items/{item_id}/shipping_options
 */
class ShippingSimulatorService
{
    private MercadoLivreClient $client;

    // Modalidades de envio disponíveis
    public const SHIPPING_MODES = [
        'not_specified' => 'Não especificado',
        'custom' => 'Envio customizado',
        'me1' => 'Mercado Envios 1',
        'me2' => 'Mercado Envios 2',
    ];

    // Tipos logísticos
    public const LOGISTIC_TYPES = [
        'default' => 'Padrão',
        'drop_off' => 'Drop Off (despacho em agência)',
        'cross_docking' => 'Cross Docking',
        'xd_drop_off' => 'Cross Docking Drop Off',
        'fulfillment' => 'Full (Mercado Envios Full)',
        'flex' => 'Flex',
    ];

    // Métodos de envio
    public const SHIPPING_METHODS = [
        'standard' => 'Padrão',
        'express' => 'Expresso',
        'next_day' => 'Entrega no dia seguinte',
        'same_day' => 'Entrega no mesmo dia',
    ];

    // Tags de frete
    public const SHIPPING_TAGS = [
        'mandatory_free_shipping' => 'Frete grátis obrigatório',
        'self_service_in' => 'Auto-atendimento de entrada',
        'self_service_out' => 'Auto-atendimento de saída',
        'fulfillment' => 'Fulfillment',
        'flex' => 'Flex',
    ];

    public function __construct(?int $accountId = null)
    {
        $this->client = new MercadoLivreClient($accountId);
    }

    /**
     * Simula custos de envio para um item específico
     */
    public function simulateForItem(string $itemId, array $options = []): array
    {
        try {
            $item = $this->client->get("/items/{$itemId}");

            if (isset($item['error'])) {
                return [
                    'success' => false,
                    'error' => 'Item não encontrado',
                ];
            }

            return $this->simulateShipping([
                'item_id' => $itemId,
                'category_id' => $item['category_id'] ?? null,
                'price' => $item['price'] ?? 0,
                'dimensions' => $this->extractDimensions($item),
                'zip_code' => $options['zip_code'] ?? null,
                'quantity' => $options['quantity'] ?? 1,
            ]);

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Simula custos de envio com dados customizados
     */
    public function simulateShipping(array $data): array
    {
        $itemId = $data['item_id'] ?? null;
        $zipCode = $data['zip_code'] ?? '01310-100'; // CEP padrão (Av. Paulista)
        $quantity = $data['quantity'] ?? 1;

        $simulation = [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'item_id' => $itemId,
            'destination_zip' => $zipCode,
            'quantity' => $quantity,
        ];

        // Tentar obter opções de envio da API
        if ($itemId) {
            $apiOptions = $this->getShippingOptionsFromAPI($itemId, $zipCode);
            if ($apiOptions) {
                $simulation['api_options'] = $apiOptions;
            }
        }

        // Calcular estimativas para cada modalidade
        $dimensions = $data['dimensions'] ?? [];
        $price = $data['price'] ?? 0;
        $categoryId = $data['category_id'] ?? null;

        $simulation['estimated_costs'] = [
            'custom' => $this->estimateCustomShipping($dimensions, $zipCode),
            'me2' => $this->estimateME2Shipping($dimensions, $price, $zipCode),
            'flex' => $this->estimateFlexShipping($dimensions, $price),
            'full' => $this->estimateFullShipping($dimensions, $price, $categoryId),
        ];

        // Adicionar análise de elegibilidade
        $simulation['eligibility'] = $this->checkEligibility($dimensions, $price, $categoryId);

        // Recomendar melhor opção
        $simulation['recommendation'] = $this->recommendBestOption($simulation['estimated_costs'], $simulation['eligibility']);

        return $simulation;
    }

    /**
     * Obtém opções de envio da API oficial do ML
     */
    private function getShippingOptionsFromAPI(string $itemId, string $zipCode): ?array
    {
        try {
            // Endpoint: GET /items/{item_id}/shipping_options?zip_code={zip_code}
            $options = $this->client->get("/items/{$itemId}/shipping_options", [
                'zip_code' => $zipCode,
            ]);

            if (isset($options['error'])) {
                return null;
            }

            return [
                'available' => true,
                'options' => $options['options'] ?? [],
                'free_shipping' => $options['free_shipping'] ?? null,
                'coverage' => $options['coverage'] ?? null,
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Estima custos para envio customizado
     */
    private function estimateCustomShipping(array $dimensions, string $zipCode): array
    {
        $weight = $dimensions['weight'] ?? 1000; // gramas
        $cubicWeight = $this->calculateCubicWeight($dimensions);
        $chargeableWeight = max($weight, $cubicWeight);

        // Estimativa baseada em Correios (valores aproximados)
        $baseCost = 15.00; // Custo base PAC
        $weightCost = ($chargeableWeight / 1000) * 5.00; // R$ 5 por kg
        $total = $baseCost + $weightCost;

        return [
            'mode' => 'custom',
            'available' => true,
            'cost' => round($total, 2),
            'free_shipping_possible' => false,
            'estimated_days' => '7-15 dias úteis',
            'details' => [
                'base_cost' => $baseCost,
                'weight_cost' => round($weightCost, 2),
                'chargeable_weight' => $chargeableWeight,
            ],
            'pros' => [
                'Controle total sobre envio',
                'Pode negociar melhores tarifas',
            ],
            'cons' => [
                'Sem proteção do ML',
                'Menor confiança do comprador',
                'Visibilidade reduzida nas buscas',
            ],
        ];
    }

    /**
     * Estima custos para Mercado Envios 2
     */
    private function estimateME2Shipping(array $dimensions, float $price, string $zipCode): array
    {
        $weight = $dimensions['weight'] ?? 1000;
        $cubicWeight = $this->calculateCubicWeight($dimensions);
        $chargeableWeight = max($weight, $cubicWeight);

        // ME2: custo varia por peso e região
        $baseCost = 12.00;
        $weightCost = ($chargeableWeight / 1000) * 6.50;
        $total = $baseCost + $weightCost;

        // Frete grátis elegível se preço > R$ 79
        $freeShippingEligible = $price >= 79.00;

        return [
            'mode' => 'me2',
            'available' => true,
            'cost' => round($total, 2),
            'seller_cost' => round($total * 0.85, 2), // ML subsidia parte
            'free_shipping_possible' => $freeShippingEligible,
            'free_shipping_seller_cost' => $freeShippingEligible ? round($total * 0.15, 2) : null,
            'estimated_days' => '3-7 dias úteis',
            'details' => [
                'base_cost' => $baseCost,
                'weight_cost' => round($weightCost, 2),
                'chargeable_weight' => $chargeableWeight,
                'ml_subsidy' => round($total * 0.15, 2),
            ],
            'pros' => [
                'Proteção do ML',
                'Melhor visibilidade que custom',
                'Frete grátis disponível',
            ],
            'cons' => [
                'Custo maior que custom',
                'Vendedor despacha o produto',
            ],
        ];
    }

    /**
     * Estima custos para Flex
     */
    private function estimateFlexShipping(array $dimensions, float $price): array
    {
        $weight = $dimensions['weight'] ?? 1000;
        $cubicWeight = $this->calculateCubicWeight($dimensions);
        $chargeableWeight = max($weight, $cubicWeight);

        // Flex: ML busca no vendedor
        $baseCost = 10.00;
        $weightCost = ($chargeableWeight / 1000) * 5.00;
        $total = $baseCost + $weightCost;

        return [
            'mode' => 'flex',
            'available' => $this->isFlexEligible($dimensions, $price),
            'cost' => round($total, 2),
            'seller_cost' => 0, // ML assume o custo
            'free_shipping_possible' => true,
            'free_shipping_seller_cost' => 0,
            'estimated_days' => '2-5 dias úteis',
            'details' => [
                'pickup' => 'ML coleta no vendedor',
                'storage' => 'Não requer estoque no ML',
                'chargeable_weight' => $chargeableWeight,
            ],
            'pros' => [
                'ML assume custo do frete',
                'Entrega mais rápida',
                'Melhor ranking nas buscas',
                'Frete grátis automático',
            ],
            'cons' => [
                'Elegibilidade limitada',
                'ML precisa coletar no vendedor',
                'Restrições de produto e localização',
            ],
        ];
    }

    /**
     * Estima custos para Full (Fulfillment)
     */
    private function estimateFullShipping(array $dimensions, float $price, ?string $categoryId): array
    {
        $weight = $dimensions['weight'] ?? 1000;
        $cubicWeight = $this->calculateCubicWeight($dimensions);
        $chargeableWeight = max($weight, $cubicWeight);

        // Full: custo de armazenagem + custo de envio (ML assume envio)
        $monthlyStorage = ($chargeableWeight / 1000) * 8.00; // R$ 8 por kg/mês
        $shippingCost = 0; // ML assume 100%

        return [
            'mode' => 'full',
            'available' => $this->isFullEligible($dimensions, $price, $categoryId),
            'cost' => 0, // Frete: R$ 0 para vendedor
            'seller_cost' => 0,
            'storage_cost' => round($monthlyStorage, 2),
            'free_shipping_possible' => true,
            'free_shipping_seller_cost' => 0,
            'estimated_days' => '1-3 dias úteis',
            'details' => [
                'storage' => 'Produtos estocados no ML',
                'monthly_storage_cost' => round($monthlyStorage, 2),
                'chargeable_weight' => $chargeableWeight,
                'shipping_handled_by' => 'Mercado Livre',
            ],
            'pros' => [
                'Entrega mais rápida (1-3 dias)',
                'Melhor ranking nas buscas',
                'Frete grátis automático',
                'ML cuida de tudo (picking, packing, shipping)',
                'Selo "Full" aumenta confiança',
                'Buy Box prioritário',
            ],
            'cons' => [
                'Custo de armazenagem mensal',
                'Precisa enviar estoque para o ML',
                'Restrições de categoria',
                'Processo de onboarding',
            ],
        ];
    }

    /**
     * Verifica elegibilidade para cada modalidade
     */
    private function checkEligibility(array $dimensions, float $price, ?string $categoryId): array
    {
        return [
            'custom' => [
                'eligible' => true,
                'reason' => 'Sempre disponível',
            ],
            'me2' => [
                'eligible' => true,
                'reason' => 'Disponível para a maioria dos produtos',
            ],
            'flex' => [
                'eligible' => $this->isFlexEligible($dimensions, $price),
                'reason' => $this->getFlexEligibilityReason($dimensions, $price),
            ],
            'full' => [
                'eligible' => $this->isFullEligible($dimensions, $price, $categoryId),
                'reason' => $this->getFullEligibilityReason($dimensions, $price, $categoryId),
            ],
        ];
    }

    /**
     * Verifica se é elegível para Flex
     */
    private function isFlexEligible(array $dimensions, float $price): bool
    {
        $weight = $dimensions['weight'] ?? 0;
        
        // Regras básicas do Flex
        if ($weight > 30000) return false; // Máximo 30kg
        if ($price < 50) return false; // Preço mínimo R$ 50

        $volume = $this->calculateVolume($dimensions);
        if ($volume > 100000) return false; // Máximo 100L

        return true;
    }

    /**
     * Motivo de elegibilidade Flex
     */
    private function getFlexEligibilityReason(array $dimensions, float $price): string
    {
        if (!$this->isFlexEligible($dimensions, $price)) {
            $weight = $dimensions['weight'] ?? 0;
            if ($weight > 30000) return 'Peso superior a 30kg';
            if ($price < 50) return 'Preço inferior a R$ 50';
            
            $volume = $this->calculateVolume($dimensions);
            if ($volume > 100000) return 'Volume superior a 100L';
            
            return 'Não elegível';
        }
        
        return 'Elegível para Flex';
    }

    /**
     * Verifica se é elegível para Full
     */
    private function isFullEligible(array $dimensions, float $price, ?string $categoryId): bool
    {
        $weight = $dimensions['weight'] ?? 0;
        
        // Regras básicas do Full
        if ($weight > 25000) return false; // Máximo 25kg
        if ($price < 30) return false; // Preço mínimo R$ 30

        $volume = $this->calculateVolume($dimensions);
        if ($volume > 80000) return false; // Máximo 80L

        // Algumas categorias não são elegíveis
        $restrictedCategories = ['MLB1540', 'MLB1148']; // Exemplo: Veículos, Imóveis
        if ($categoryId && in_array($categoryId, $restrictedCategories)) {
            return false;
        }

        return true;
    }

    /**
     * Motivo de elegibilidade Full
     */
    private function getFullEligibilityReason(array $dimensions, float $price, ?string $categoryId): string
    {
        if (!$this->isFullEligible($dimensions, $price, $categoryId)) {
            $weight = $dimensions['weight'] ?? 0;
            if ($weight > 25000) return 'Peso superior a 25kg';
            if ($price < 30) return 'Preço inferior a R$ 30';
            
            $volume = $this->calculateVolume($dimensions);
            if ($volume > 80000) return 'Volume superior a 80L';
            
            return 'Categoria não elegível ou outras restrições';
        }
        
        return 'Elegível para Full';
    }

    /**
     * Recomenda a melhor opção de envio
     */
    private function recommendBestOption(array $costs, array $eligibility): array
    {
        $recommendations = [];

        // Prioridade 1: Full (se elegível)
        if ($eligibility['full']['eligible']) {
            $recommendations[] = [
                'mode' => 'full',
                'priority' => 1,
                'reason' => 'Melhor ranking, entrega mais rápida, frete grátis',
                'impact' => 'Alto impacto em conversão (+50%)',
                'cost_benefit' => 'Excelente',
            ];
        }

        // Prioridade 2: Flex (se elegível)
        if ($eligibility['flex']['eligible']) {
            $recommendations[] = [
                'mode' => 'flex',
                'priority' => 2,
                'reason' => 'Frete grátis sem custo, boa entrega',
                'impact' => 'Alto impacto em conversão (+40%)',
                'cost_benefit' => 'Excelente',
            ];
        }

        // Prioridade 3: ME2 com frete grátis
        if ($costs['me2']['free_shipping_possible']) {
            $recommendations[] = [
                'mode' => 'me2',
                'priority' => 3,
                'reason' => 'Frete grátis disponível, boa visibilidade',
                'impact' => 'Médio impacto em conversão (+30%)',
                'cost_benefit' => 'Bom',
            ];
        }

        // Prioridade 4: ME2 sem frete grátis
        if (!$costs['me2']['free_shipping_possible']) {
            $recommendations[] = [
                'mode' => 'me2',
                'priority' => 4,
                'reason' => 'Proteção do ML, melhor que custom',
                'impact' => 'Baixo impacto em conversão (+10%)',
                'cost_benefit' => 'Médio',
            ];
        }

        // Prioridade 5: Custom (última opção)
        $recommendations[] = [
            'mode' => 'custom',
            'priority' => 5,
            'reason' => 'Última opção, menor visibilidade',
            'impact' => 'Negativo para conversão (-20%)',
            'cost_benefit' => 'Variável',
        ];

        return [
            'best' => $recommendations[0] ?? null,
            'alternatives' => array_slice($recommendations, 1, 2),
            'all_options' => $recommendations,
        ];
    }

    /**
     * Extrai dimensões de um item
     */
    private function extractDimensions(array $item): array
    {
        $shipping = $item['shipping'] ?? [];
        $dimensions = $shipping['dimensions'] ?? null;

        if (!$dimensions) {
            return [
                'length' => null,
                'width' => null,
                'height' => null,
                'weight' => null,
            ];
        }

        return [
            'length' => $dimensions['length'] ?? null,
            'width' => $dimensions['width'] ?? null,
            'height' => $dimensions['height'] ?? null,
            'weight' => $dimensions['weight'] ?? null,
        ];
    }

    /**
     * Calcula peso cubado (volumétrico)
     */
    private function calculateCubicWeight(array $dimensions): float
    {
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        if (!$length || !$width || !$height) {
            return 0;
        }

        // Fórmula: (L x W x H) / 6000 (para gramas)
        return ($length * $width * $height) / 6000;
    }

    /**
     * Calcula volume em cm³
     */
    private function calculateVolume(array $dimensions): float
    {
        $length = $dimensions['length'] ?? 0;
        $width = $dimensions['width'] ?? 0;
        $height = $dimensions['height'] ?? 0;

        return $length * $width * $height;
    }

    /**
     * Compara custos entre modalidades
     */
    public function compareShippingCosts(string $itemId, array $zipCodes): array
    {
        $comparisons = [];

        foreach ($zipCodes as $zipCode) {
            $simulation = $this->simulateForItem($itemId, ['zip_code' => $zipCode]);
            $comparisons[$zipCode] = $simulation;
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'comparisons' => $comparisons,
            'summary' => $this->generateComparisonSummary($comparisons),
        ];
    }

    /**
     * Gera resumo de comparação
     */
    private function generateComparisonSummary(array $comparisons): array
    {
        $summary = [
            'total_destinations' => count($comparisons),
            'avg_cost_by_mode' => [],
            'best_mode_overall' => null,
        ];

        // Calcular média de custo por modalidade
        $costsByMode = [];
        foreach ($comparisons as $comparison) {
            if (isset($comparison['estimated_costs'])) {
                foreach ($comparison['estimated_costs'] as $mode => $cost) {
                    if (!isset($costsByMode[$mode])) {
                        $costsByMode[$mode] = [];
                    }
                    $costsByMode[$mode][] = $cost['cost'];
                }
            }
        }

        foreach ($costsByMode as $mode => $costs) {
            $summary['avg_cost_by_mode'][$mode] = round(array_sum($costs) / count($costs), 2);
        }

        return $summary;
    }

    /**
     * Compara custos de envio com dimensões e peso customizados
     * 
     * @param array $dimensions ['width' => cm, 'height' => cm, 'length' => cm]
     * @param float $weight Peso em kg
     * @param array $zipCodes Lista de CEPs destino
     * @param string|null $originZip CEP origem (opcional)
     * @return array Comparação de custos por destino
     */
    public function compareCustomShipping(array $dimensions, float $weight, array $zipCodes, ?string $originZip = null): array
    {
        // Validar inputs
        $validation = $this->validateCustomShippingInput($dimensions, $weight, $zipCodes);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Se não forneceu origem, buscar do seller
        if (!$originZip) {
            try {
                $seller = $this->client->get('/users/me');
                $originZip = $seller['address']['zip_code'] ?? '01310-100'; // Fallback São Paulo
            } catch (\Exception $e) {
                $originZip = '01310-100'; // Fallback
            }
        }

        $comparisons = [];

        foreach ($zipCodes as $zipCode) {
            try {
                // Calcular custo por diferentes modalidades
                $estimates = $this->estimateCustomShippingCosts(
                    $dimensions,
                    $weight,
                    $originZip,
                    $zipCode
                );

                $comparisons[$zipCode] = [
                    'zip_code' => $zipCode,
                    'estimates' => $estimates,
                    'cheapest' => $this->findCheapest($estimates),
                    'fastest' => $this->findFastest($estimates)
                ];

            } catch (\Exception $e) {
                $comparisons[$zipCode] = [
                    'zip_code' => $zipCode,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => true,
            'dimensions' => $dimensions,
            'weight' => $weight,
            'origin_zip' => $originZip,
            'comparisons' => $comparisons,
            'summary' => $this->generateCustomShippingSummary($comparisons)
        ];
    }

    /**
     * Valida inputs de envio customizado
     */
    private function validateCustomShippingInput(array $dimensions, float $weight, array $zipCodes): array
    {
        // Validar dimensões
        $requiredDims = ['width', 'height', 'length'];
        foreach ($requiredDims as $dim) {
            if (!isset($dimensions[$dim]) || $dimensions[$dim] <= 0) {
                return [
                    'valid' => false,
                    'error' => "Dimensão '{$dim}' inválida ou não fornecida"
                ];
            }
        }

        // Validar peso
        if ($weight <= 0 || $weight > 30) { // Limite ML: 30kg
            return [
                'valid' => false,
                'error' => 'Peso deve estar entre 0 e 30kg'
            ];
        }

        // Validar dimensões máximas ML
        $maxDimension = 105; // cm
        $sumDimensions = $dimensions['width'] + $dimensions['height'] + $dimensions['length'];
        if ($sumDimensions > 200) { // Soma máxima ML
            return [
                'valid' => false,
                'error' => 'Soma das dimensões não pode exceder 200cm'
            ];
        }

        // Validar CEPs
        if (empty($zipCodes)) {
            return [
                'valid' => false,
                'error' => 'Nenhum CEP destino fornecido'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Estima custos de envio customizado por modalidade
     */
    private function estimateCustomShippingCosts(array $dimensions, float $weight, string $originZip, string $destZip): array
    {
        $estimates = [];

        // PAC (Padrão)
        $estimates['pac'] = $this->calculateShippingCost(
            $dimensions,
            $weight,
            $originZip,
            $destZip,
            'pac'
        );

        // SEDEX (Expresso)
        $estimates['sedex'] = $this->calculateShippingCost(
            $dimensions,
            $weight,
            $originZip,
            $destZip,
            'sedex'
        );

        // Mercado Envios (estimativa baseada em tabela)
        $estimates['me2'] = $this->calculateME2Cost($dimensions, $weight, $originZip, $destZip);

        return $estimates;
    }

    /**
     * Calcula custo de envio por modalidade
     * Usa fórmula simplificada baseada em peso, distância e dimensões
     */
    private function calculateShippingCost(array $dimensions, float $weight, string $originZip, string $destZip, string $mode): array
    {
        // Peso cubado = (largura * altura * comprimento) / 6000
        $cubedWeight = ($dimensions['width'] * $dimensions['height'] * $dimensions['length']) / 6000;
        $finalWeight = max($weight, $cubedWeight);

        // Calcular distância estimada entre CEPs (simplificado por região)
        $distance = $this->estimateDistance($originZip, $destZip);

        // Tabela base de custos (valores aproximados)
        $baseCosts = [
            'pac' => ['base' => 15.00, 'per_kg' => 3.50, 'per_km' => 0.01],
            'sedex' => ['base' => 25.00, 'per_kg' => 5.00, 'per_km' => 0.015],
        ];

        $config = $baseCosts[$mode] ?? $baseCosts['pac'];
        
        $cost = $config['base'] 
            + ($finalWeight * $config['per_kg'])
            + ($distance * $config['per_km']);

        // Prazo estimado (dias)
        $deliveryTime = $mode === 'sedex' 
            ? ceil($distance / 500) + 1  // ~500km por dia
            : ceil($distance / 300) + 2; // ~300km por dia

        return [
            'mode' => $mode,
            'cost' => round($cost, 2),
            'delivery_time_days' => $deliveryTime,
            'weight_used' => $finalWeight,
            'is_cubed' => $cubedWeight > $weight
        ];
    }

    /**
     * Calcula custo ME2 (Mercado Envios 2)
     */
    private function calculateME2Cost(array $dimensions, float $weight, string $originZip, string $destZip): array
    {
        $distance = $this->estimateDistance($originZip, $destZip);
        $cubedWeight = ($dimensions['width'] * $dimensions['height'] * $dimensions['length']) / 6000;
        $finalWeight = max($weight, $cubedWeight);

        // ME2 geralmente mais barato que SEDEX, mais caro que PAC
        $baseCost = 18.00;
        $perKg = 4.00;
        $perKm = 0.012;

        $cost = $baseCost + ($finalWeight * $perKg) + ($distance * $perKm);
        $deliveryTime = ceil($distance / 400) + 1;

        return [
            'mode' => 'me2',
            'cost' => round($cost, 2),
            'delivery_time_days' => $deliveryTime,
            'weight_used' => $finalWeight,
            'is_cubed' => $cubedWeight > $weight
        ];
    }

    /**
     * Estima distância entre CEPs (simplificado por região)
     */
    private function estimateDistance(string $originZip, string $destZip): int
    {
        // Remover formatação
        $origin = (int) preg_replace('/\D/', '', $originZip);
        $dest = (int) preg_replace('/\D/', '', $destZip);

        // Tabela simplificada de regiões (primeiros 2 dígitos)
        $originRegion = (int) substr($origin, 0, 2);
        $destRegion = (int) substr($dest, 0, 2);

        // Mesma região: 100km
        if ($originRegion === $destRegion) {
            return 100;
        }

        // Diferença de região * fator de distância
        $regionDiff = abs($originRegion - $destRegion);
        return min(100 + ($regionDiff * 200), 3000); // Max 3000km (Brasil)
    }

    /**
     * Encontra opção mais barata
     */
    private function findCheapest(array $estimates): ?array
    {
        $cheapest = null;
        $minCost = PHP_FLOAT_MAX;

        foreach ($estimates as $estimate) {
            if (isset($estimate['cost']) && $estimate['cost'] < $minCost) {
                $minCost = $estimate['cost'];
                $cheapest = $estimate;
            }
        }

        return $cheapest;
    }

    /**
     * Encontra opção mais rápida
     */
    private function findFastest(array $estimates): ?array
    {
        $fastest = null;
        $minDays = PHP_INT_MAX;

        foreach ($estimates as $estimate) {
            if (isset($estimate['delivery_time_days']) && $estimate['delivery_time_days'] < $minDays) {
                $minDays = $estimate['delivery_time_days'];
                $fastest = $estimate;
            }
        }

        return $fastest;
    }

    /**
     * Gera resumo de comparação customizada
     */
    private function generateCustomShippingSummary(array $comparisons): array
    {
        $totalDestinations = count($comparisons);
        $avgCostByMode = [];
        $successfulComparisons = 0;

        foreach ($comparisons as $comparison) {
            if (isset($comparison['estimates'])) {
                $successfulComparisons++;
                foreach ($comparison['estimates'] as $mode => $estimate) {
                    if (!isset($avgCostByMode[$mode])) {
                        $avgCostByMode[$mode] = [];
                    }
                    $avgCostByMode[$mode][] = $estimate['cost'];
                }
            }
        }

        // Calcular médias
        foreach ($avgCostByMode as $mode => $costs) {
            $avgCostByMode[$mode] = round(array_sum($costs) / count($costs), 2);
        }

        return [
            'total_destinations' => $totalDestinations,
            'successful_comparisons' => $successfulComparisons,
            'avg_cost_by_mode' => $avgCostByMode,
            'cheapest_mode_overall' => !empty($avgCostByMode) ? array_keys($avgCostByMode, min($avgCostByMode))[0] : null
        ];
    }
}
