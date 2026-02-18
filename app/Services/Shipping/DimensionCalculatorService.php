<?php
declare(strict_types=1);

namespace App\Services\Shipping;

/**
 * Dimension Calculator Service - Calcula dimensões e peso cubado
 * 
 * Funcionalidades:
 * - Calcula peso cubado (volumétrico)
 * - Valida dimensões para cada modalidade de envio
 * - Sugere embalagens adequadas
 * - Otimiza dimensões para reduzir frete
 * - Detecta produtos irregulares
 */
class DimensionCalculatorService
{
    // Limites por modalidade (em cm)
    private const DIMENSION_LIMITS = [
        'me2' => [
            'max_length' => 200,
            'max_width' => 200,
            'max_height' => 200,
            'max_weight_kg' => 30,
            'max_cubic_weight_kg' => 200,
            'max_sum' => 300, // Soma L+W+H
        ],
        'flex' => [
            'max_length' => 150,
            'max_width' => 100,
            'max_height' => 100,
            'max_weight_kg' => 25,
            'max_cubic_weight_kg' => 150,
            'max_sum' => 250,
        ],
        'full' => [
            'max_length' => 120,
            'max_width' => 80,
            'max_height' => 80,
            'max_weight_kg' => 20,
            'max_cubic_weight_kg' => 100,
            'max_sum' => 200,
        ],
    ];

    // Fator de peso cubado (divisor padrão ML)
    private const CUBIC_WEIGHT_DIVISOR = 6000; // cm³ / kg

    // Tamanhos de caixas padrão dos Correios (em cm)
    private const STANDARD_BOXES = [
        'pac_mini' => ['length' => 16, 'width' => 11, 'height' => 2],
        'pac_1' => ['length' => 16, 'width' => 11, 'height' => 6],
        'pac_2' => ['length' => 22, 'width' => 16, 'height' => 10],
        'pac_3' => ['length' => 28, 'width' => 22, 'height' => 15],
        'pac_4' => ['length' => 36, 'width' => 27, 'height' => 20],
        'pac_5' => ['length' => 41, 'width' => 32, 'height' => 25],
        'sedex_1' => ['length' => 16, 'width' => 11, 'height' => 6],
        'sedex_2' => ['length' => 22, 'width' => 16, 'height' => 10],
        'sedex_3' => ['length' => 28, 'width' => 22, 'height' => 15],
        'sedex_4' => ['length' => 36, 'width' => 27, 'height' => 20],
    ];

    /**
     * Calcula peso cubado
     */
    public function calculateCubicWeight(float $length, float $width, float $height): float
    {
        // Volume em cm³
        $volume = $length * $width * $height;
        
        // Peso cubado = volume / divisor
        $cubicWeight = $volume / self::CUBIC_WEIGHT_DIVISOR;
        
        return round($cubicWeight, 2);
    }

    /**
     * Calcula qual peso será cobrado (maior entre real e cubado)
     */
    public function calculateChargeableWeight(
        float $actualWeight,
        float $length,
        float $width,
        float $height
    ): array {
        $cubicWeight = $this->calculateCubicWeight($length, $width, $height);
        $chargeableWeight = max($actualWeight, $cubicWeight);

        return [
            'actual_weight_kg' => round($actualWeight, 2),
            'cubic_weight_kg' => round($cubicWeight, 2),
            'chargeable_weight_kg' => round($chargeableWeight, 2),
            'using' => $chargeableWeight === $cubicWeight ? 'cubic' : 'actual',
            'volume_cm3' => round($length * $width * $height, 2),
        ];
    }

    /**
     * Valida dimensões para uma modalidade
     */
    public function validateDimensions(
        float $length,
        float $width,
        float $height,
        float $weight,
        string $shippingMode
    ): array {
        $limits = self::DIMENSION_LIMITS[$shippingMode] ?? null;

        if (!$limits) {
            return [
                'valid' => false,
                'error' => 'Modalidade de envio inválida',
            ];
        }

        $issues = [];
        $warnings = [];

        // Validar comprimento
        if ($length > $limits['max_length']) {
            $issues[] = sprintf(
                'Comprimento %.1fcm excede limite de %.1fcm',
                $length,
                $limits['max_length']
            );
        }

        // Validar largura
        if ($width > $limits['max_width']) {
            $issues[] = sprintf(
                'Largura %.1fcm excede limite de %.1fcm',
                $width,
                $limits['max_width']
            );
        }

        // Validar altura
        if ($height > $limits['max_height']) {
            $issues[] = sprintf(
                'Altura %.1fcm excede limite de %.1fcm',
                $height,
                $limits['max_height']
            );
        }

        // Validar soma
        $sum = $length + $width + $height;
        if ($sum > $limits['max_sum']) {
            $issues[] = sprintf(
                'Soma das dimensões %.1fcm excede limite de %.1fcm',
                $sum,
                $limits['max_sum']
            );
        }

        // Validar peso real
        if ($weight > $limits['max_weight_kg']) {
            $issues[] = sprintf(
                'Peso %.2fkg excede limite de %.2fkg',
                $weight,
                $limits['max_weight_kg']
            );
        }

        // Validar peso cubado
        $cubicWeight = $this->calculateCubicWeight($length, $width, $height);
        if ($cubicWeight > $limits['max_cubic_weight_kg']) {
            $issues[] = sprintf(
                'Peso cubado %.2fkg excede limite de %.2fkg',
                $cubicWeight,
                $limits['max_cubic_weight_kg']
            );
        }

        // Warnings para dimensões próximas ao limite
        $threshold = 0.9; // 90% do limite
        if ($length > $limits['max_length'] * $threshold) {
            $warnings[] = 'Comprimento próximo ao limite máximo';
        }
        if ($sum > $limits['max_sum'] * $threshold) {
            $warnings[] = 'Soma das dimensões próxima ao limite';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'warnings' => $warnings,
            'dimensions' => [
                'length' => $length,
                'width' => $width,
                'height' => $height,
                'sum' => $sum,
            ],
            'weight' => [
                'actual' => $weight,
                'cubic' => $cubicWeight,
            ],
            'limits' => $limits,
        ];
    }

    /**
     * Valida para todas as modalidades
     */
    public function validateForAllModes(
        float $length,
        float $width,
        float $height,
        float $weight
    ): array {
        $results = [];

        foreach (array_keys(self::DIMENSION_LIMITS) as $mode) {
            $results[$mode] = $this->validateDimensions($length, $width, $height, $weight, $mode);
        }

        // Determinar modalidades compatíveis
        $compatible = array_filter($results, fn($r) => $r['valid']);

        return [
            'dimensions' => compact('length', 'width', 'height', 'weight'),
            'validation_results' => $results,
            'compatible_modes' => array_keys($compatible),
            'recommended_mode' => $this->recommendMode($results),
        ];
    }

    /**
     * Recomenda melhor modalidade baseado nas dimensões
     */
    private function recommendMode(array $validationResults): ?string
    {
        // Prioridade: full > flex > me2
        $priority = ['full', 'flex', 'me2'];

        foreach ($priority as $mode) {
            if (isset($validationResults[$mode]) && $validationResults[$mode]['valid']) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * Sugere caixa padrão adequada
     */
    public function suggestPackaging(float $length, float $width, float $height): array
    {
        $productVolume = $length * $width * $height;
        $suitable = [];

        foreach (self::STANDARD_BOXES as $name => $box) {
            // Verificar se produto cabe na caixa
            $dimensions = [$length, $width, $height];
            $boxDimensions = [$box['length'], $box['width'], $box['height']];
            
            // Ordenar ambas as dimensões
            sort($dimensions);
            sort($boxDimensions);

            // Verificar se cada dimensão do produto cabe na caixa
            $fits = $dimensions[0] <= $boxDimensions[0] &&
                    $dimensions[1] <= $boxDimensions[1] &&
                    $dimensions[2] <= $boxDimensions[2];

            if ($fits) {
                $boxVolume = $box['length'] * $box['width'] * $box['height'];
                $efficiency = ($productVolume / $boxVolume) * 100;

                $suitable[] = [
                    'name' => $name,
                    'dimensions' => $box,
                    'volume_cm3' => $boxVolume,
                    'efficiency' => round($efficiency, 1),
                    'cubic_weight_kg' => round($boxVolume / self::CUBIC_WEIGHT_DIVISOR, 2),
                ];
            }
        }

        // Ordenar por eficiência (quanto mais próximo de 100%, melhor)
        usort($suitable, fn($a, $b) => $b['efficiency'] <=> $a['efficiency']);

        return [
            'product_dimensions' => compact('length', 'width', 'height'),
            'product_volume_cm3' => round($productVolume, 2),
            'suitable_boxes' => $suitable,
            'recommended' => $suitable[0] ?? null,
        ];
    }

    /**
     * Otimiza dimensões para reduzir frete
     */
    public function optimizeDimensions(
        float $length,
        float $width,
        float $height,
        float $weight,
        string $targetMode = 'me2'
    ): array {
        $original = compact('length', 'width', 'height', 'weight');
        $originalCubic = $this->calculateCubicWeight($length, $width, $height);

        // Sugestões de otimização
        $suggestions = [];

        // 1. Verificar se pode usar caixa menor
        $packaging = $this->suggestPackaging($length, $width, $height);
        if ($packaging['recommended']) {
            $box = $packaging['recommended'];
            $newCubic = $box['cubic_weight_kg'];

            if ($newCubic < $originalCubic) {
                $savings = (($originalCubic - $newCubic) / $originalCubic) * 100;
                $suggestions[] = [
                    'type' => 'use_standard_box',
                    'description' => 'Usar caixa padrão ' . $box['name'],
                    'box' => $box,
                    'cubic_weight_reduction_kg' => round($originalCubic - $newCubic, 2),
                    'cost_savings_estimate' => round($savings, 1) . '%',
                    'impact' => $savings > 20 ? 'high' : 'medium',
                ];
            }
        }

        // 2. Reduzir altura se possível (dimensão que mais afeta peso cubado)
        if ($height > 5) {
            $optimizedHeight = max(2, $height * 0.8);
            $newCubic = $this->calculateCubicWeight($length, $width, $optimizedHeight);
            $savings = (($originalCubic - $newCubic) / $originalCubic) * 100;

            if ($savings > 10) {
                $suggestions[] = [
                    'type' => 'reduce_height',
                    'description' => 'Reduzir altura da embalagem',
                    'current_height' => $height,
                    'suggested_height' => round($optimizedHeight, 1),
                    'cubic_weight_reduction_kg' => round($originalCubic - $newCubic, 2),
                    'cost_savings_estimate' => round($savings, 1) . '%',
                    'impact' => $savings > 20 ? 'high' : 'medium',
                ];
            }
        }

        // 3. Verificar se está próximo do limite do modo desejado
        $validation = $this->validateDimensions($length, $width, $height, $weight, $targetMode);
        if (!$validation['valid']) {
            $suggestions[] = [
                'type' => 'exceeds_limits',
                'description' => 'Produto excede limites da modalidade ' . $targetMode,
                'issues' => $validation['issues'],
                'suggestion' => 'Considere dividir em múltiplos envios ou usar modalidade superior',
                'impact' => 'critical',
            ];
        }

        // 4. Sugerir modalidade alternativa se atual não for viável
        $allValidations = $this->validateForAllModes($length, $width, $height, $weight);
        if ($allValidations['recommended_mode'] !== $targetMode) {
            $suggestions[] = [
                'type' => 'alternative_mode',
                'description' => 'Modalidade alternativa recomendada',
                'current_target' => $targetMode,
                'recommended' => $allValidations['recommended_mode'],
                'compatible_modes' => $allValidations['compatible_modes'],
                'impact' => 'high',
            ];
        }

        return [
            'original_dimensions' => $original,
            'original_cubic_weight' => $originalCubic,
            'target_mode' => $targetMode,
            'validation' => $validation,
            'suggestions' => $suggestions,
            'total_potential_savings' => $this->calculateTotalSavings($suggestions),
        ];
    }

    /**
     * Calcula economia total das sugestões
     */
    private function calculateTotalSavings(array $suggestions): string
    {
        $totalSavings = 0;

        foreach ($suggestions as $suggestion) {
            if (isset($suggestion['cost_savings_estimate'])) {
                $savings = (float)str_replace('%', '', $suggestion['cost_savings_estimate']);
                $totalSavings += $savings;
            }
        }

        return round($totalSavings, 1) . '%';
    }

    /**
     * Detecta produtos com dimensões irregulares
     */
    public function detectIrregularDimensions(float $length, float $width, float $height): array
    {
        $dimensions = [$length, $width, $height];
        sort($dimensions);
        
        $smallest = $dimensions[0];
        $middle = $dimensions[1];
        $largest = $dimensions[2];

        $issues = [];

        // Muito fino (altura muito menor que outras dimensões)
        if ($smallest < 2 && ($middle > $smallest * 10 || $largest > $smallest * 10)) {
            $issues[] = [
                'type' => 'very_thin',
                'description' => 'Produto muito fino - alto risco de danos no transporte',
                'severity' => 'high',
                'recommendation' => 'Use embalagem reforçada ou adicione proteção',
            ];
        }

        // Muito desproporcional (uma dimensão muito maior que as outras)
        if ($largest > $middle * 3 || $largest > $smallest * 5) {
            $issues[] = [
                'type' => 'disproportionate',
                'description' => 'Produto muito desproporcional',
                'severity' => 'medium',
                'recommendation' => 'Verifique se há possibilidade de dobrar/desmontar',
            ];
        }

        // Muito grande em todas as dimensões
        $sumDimensions = $length + $width + $height;
        if ($sumDimensions > 250) {
            $issues[] = [
                'type' => 'oversized',
                'description' => 'Produto de grandes dimensões',
                'severity' => 'high',
                'recommendation' => 'Opções de envio podem ser limitadas - considere envio especial',
            ];
        }

        // Muito pequeno (pode se perder)
        if ($largest < 10) {
            $issues[] = [
                'type' => 'very_small',
                'description' => 'Produto muito pequeno',
                'severity' => 'medium',
                'recommendation' => 'Use embalagem maior para facilitar manuseio',
            ];
        }

        return [
            'has_irregular_dimensions' => !empty($issues),
            'dimensions' => compact('length', 'width', 'height'),
            'sorted_dimensions' => [
                'smallest' => $smallest,
                'middle' => $middle,
                'largest' => $largest,
            ],
            'issues' => $issues,
        ];
    }

    /**
     * Calcula custo estimado de embalagem
     */
    public function estimatePackagingCost(float $length, float $width, float $height): array
    {
        $volume = $length * $width * $height;
        
        // Custos estimados por tipo de embalagem (em R$)
        $packagingCosts = [
            'envelope' => ['min_volume' => 0, 'max_volume' => 500, 'cost' => 1.50],
            'box_small' => ['min_volume' => 500, 'max_volume' => 5000, 'cost' => 3.00],
            'box_medium' => ['min_volume' => 5000, 'max_volume' => 15000, 'cost' => 5.00],
            'box_large' => ['min_volume' => 15000, 'max_volume' => 50000, 'cost' => 8.00],
            'box_xlarge' => ['min_volume' => 50000, 'max_volume' => 999999, 'cost' => 15.00],
        ];

        $selectedType = null;
        $estimatedCost = 0;

        foreach ($packagingCosts as $type => $data) {
            if ($volume >= $data['min_volume'] && $volume <= $data['max_volume']) {
                $selectedType = $type;
                $estimatedCost = $data['cost'];
                break;
            }
        }

        return [
            'volume_cm3' => round($volume, 2),
            'packaging_type' => $selectedType ?? 'custom',
            'estimated_cost_brl' => $estimatedCost,
            'includes' => [
                'box' => true,
                'bubble_wrap' => $volume < 10000,
                'tape' => true,
            ],
        ];
    }

    /**
     * Análise completa de dimensões
     */
    public function analyzeComplete(
        float $length,
        float $width,
        float $height,
        float $weight
    ): array {
        return [
            'dimensions' => compact('length', 'width', 'height', 'weight'),
            'chargeable_weight' => $this->calculateChargeableWeight($weight, $length, $width, $height),
            'validation_all_modes' => $this->validateForAllModes($length, $width, $height, $weight),
            'packaging_suggestions' => $this->suggestPackaging($length, $width, $height),
            'optimization_opportunities' => $this->optimizeDimensions($length, $width, $height, $weight),
            'irregular_detection' => $this->detectIrregularDimensions($length, $width, $height),
            'packaging_cost' => $this->estimatePackagingCost($length, $width, $height),
        ];
    }
}
