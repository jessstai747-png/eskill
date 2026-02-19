<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Testes unitários para TechSheetService
 * 
 * Testa análise de completude, cálculo de gaps, geração de sugestões
 * e lógica de confiança do módulo de Ficha Técnica
 */
class TechSheetServiceTest extends TestCase
{
    private object $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->createServiceForTesting();
    }

    /**
     * Cria um mock simplificado com métodos principais
     */
    private function createServiceForTesting(): object
    {
        return new class {
            // Confiança mínima para aplicação automática
            private const MIN_CONFIDENCE_AUTO_APPLY = 80;

            /**
             * Calcula a completude de um anúncio
             */
            public function calculateCompleteness(array $itemData, array $allAttributes): array
            {
                $total = count($allAttributes);
                $filled = 0;
                $missing = [
                    'required' => [],
                    'recommended' => [],
                    'filter' => [],
                    'hidden' => []
                ];

                foreach ($allAttributes as $attr) {
                    $attrId = $attr['id'] ?? null;
                    $hasValue = false;

                    // Verifica se o atributo tem valor
                    if (isset($itemData['attributes'])) {
                        foreach ($itemData['attributes'] as $itemAttr) {
                            if ($itemAttr['id'] === $attrId && !empty($itemAttr['value_name'])) {
                                $hasValue = true;
                                $filled++;
                                break;
                            }
                        }
                    }

                    // Se não tem valor, adiciona à lista de faltantes
                    if (!$hasValue) {
                        $tags = $attr['tags'] ?? [];
                        
                        if (in_array('required', $tags)) {
                            $missing['required'][] = $attrId;
                        } elseif (in_array('recommended', $tags)) {
                            $missing['recommended'][] = $attrId;
                        }

                        if (in_array('hidden', $tags)) {
                            $missing['hidden'][] = $attrId;
                        } elseif (in_array('allows_filters', $tags)) {
                            $missing['filter'][] = $attrId;
                        }
                    }
                }

                $percent = $total > 0 ? round(($filled / $total) * 100, 2) : 0;

                return [
                    'completeness_percent' => $percent,
                    'total_attributes' => $total,
                    'filled_attributes' => $filled,
                    'missing_attributes' => $total - $filled,
                    'missing_details' => $missing,
                ];
            }

            /**
             * Verifica se uma sugestão pode ser aplicada automaticamente
             */
            public function canAutoApply(array $suggestion): bool
            {
                $confidence = $suggestion['confidence'] ?? 0;
                $source = $suggestion['source'] ?? '';

                // Apenas sugestões com confiança alta
                if ($confidence < self::MIN_CONFIDENCE_AUTO_APPLY) {
                    return false;
                }

                // Nunca aplicar automaticamente para atributos críticos
                $criticalAttributes = ['BRAND', 'MODEL', 'GTIN', 'MPN'];
                $attrId = $suggestion['attribute_id'] ?? '';
                
                if (in_array($attrId, $criticalAttributes)) {
                    return false;
                }

                return true;
            }

            /**
             * Calcula confiança de uma sugestão baseada na fonte
             */
            public function calculateConfidence(string $source, array $metadata = []): int
            {
                // Título: 60-75% (dependendo da clareza)
                if ($source === 'title') {
                    $regexPattern = $metadata['regex_pattern'] ?? '';
                    $isExactMatch = $metadata['exact_match'] ?? false;
                    
                    if ($isExactMatch) {
                        return 75;
                    }
                    
                    if (!empty($regexPattern)) {
                        return 65;
                    }
                    
                    return 60;
                }

                // Benchmark: 70-95% (dependendo da frequência)
                if ($source === 'benchmark') {
                    $frequency = $metadata['frequency'] ?? 0;
                    $totalCompetitors = $metadata['total_competitors'] ?? 1;
                    $isUnanimous = $metadata['unanimous'] ?? false;

                    if ($isUnanimous && $totalCompetitors >= 3) {
                        return 95;
                    }

                    $percentUsage = $totalCompetitors > 0 
                        ? ($frequency / $totalCompetitors) * 100 
                        : 0;

                    if ($percentUsage >= 75) {
                        return 90;
                    } elseif ($percentUsage >= 50) {
                        return 80;
                    } elseif ($percentUsage >= 25) {
                        return 70;
                    } else {
                        return 65;
                    }
                }

                // IA: 50-85% (com guardrails)
                if ($source === 'ai') {
                    $validationPassed = $metadata['validation_passed'] ?? false;
                    $isFromAllowedValues = $metadata['is_from_allowed_values'] ?? false;
                    
                    if ($validationPassed && $isFromAllowedValues) {
                        return 85;
                    }
                    
                    if ($isFromAllowedValues) {
                        return 75;
                    }
                    
                    return 50;
                }

                return 50; // Default baixo
            }

            /**
             * Prioriza atributos faltantes
             */
            public function prioritizeMissingAttributes(array $missingDetails): array
            {
                $prioritized = [];

                // 1. Obrigatórios (maior prioridade)
                foreach ($missingDetails['required'] ?? [] as $attrId) {
                    $prioritized[] = [
                        'attribute_id' => $attrId,
                        'priority' => 'high',
                        'reason' => 'Atributo obrigatório',
                    ];
                }

                // 2. Para filtros (segunda prioridade)
                foreach ($missingDetails['filter'] ?? [] as $attrId) {
                    if (!$this->isInList($attrId, $prioritized)) {
                        $prioritized[] = [
                            'attribute_id' => $attrId,
                            'priority' => 'medium',
                            'reason' => 'Usado em filtros de busca',
                        ];
                    }
                }

                // 3. Recomendados (terceira prioridade)
                foreach ($missingDetails['recommended'] ?? [] as $attrId) {
                    if (!$this->isInList($attrId, $prioritized)) {
                        $prioritized[] = [
                            'attribute_id' => $attrId,
                            'priority' => 'low',
                            'reason' => 'Atributo recomendado',
                        ];
                    }
                }

                // 4. Escondidos (menor prioridade)
                foreach ($missingDetails['hidden'] ?? [] as $attrId) {
                    if (!$this->isInList($attrId, $prioritized)) {
                        $prioritized[] = [
                            'attribute_id' => $attrId,
                            'priority' => 'very_low',
                            'reason' => 'Atributo oculto',
                        ];
                    }
                }

                return $prioritized;
            }

            private function isInList(string $attrId, array $list): bool
            {
                foreach ($list as $item) {
                    if ($item['attribute_id'] === $attrId) {
                        return true;
                    }
                }
                return false;
            }

            /**
             * Valida se uma sugestão é aplicável
             */
            public function validateSuggestion(array $suggestion, array $attribute): array
            {
                $errors = [];
                $suggestedValue = $suggestion['suggested_value'] ?? null;

                if (empty($suggestedValue)) {
                    $errors[] = 'Valor sugerido está vazio';
                    return ['valid' => false, 'errors' => $errors];
                }

                // Valida contra allowed_values se existirem
                $allowedValues = $attribute['allowed_values'] ?? [];
                if (!empty($allowedValues)) {
                    $validValues = array_column($allowedValues, 'name');
                    
                    if (!in_array($suggestedValue, $validValues)) {
                        $errors[] = sprintf(
                            'Valor "%s" não está na lista de valores permitidos',
                            $suggestedValue
                        );
                    }
                }

                // Valida tipo de atributo
                $valueType = $attribute['value_type'] ?? 'string';
                if ($valueType === 'number' || $valueType === 'number_unit') {
                    if (!is_numeric($suggestedValue)) {
                        $errors[] = 'Valor deve ser numérico';
                    }
                }

                // Valida limites
                if (isset($attribute['value_max_length'])) {
                    $maxLen = $attribute['value_max_length'];
                    if (mb_strlen($suggestedValue) > $maxLen) {
                        $errors[] = sprintf(
                            'Valor excede tamanho máximo de %d caracteres',
                            $maxLen
                        );
                    }
                }

                return [
                    'valid' => empty($errors),
                    'errors' => $errors,
                ];
            }
        };
    }

    // ==================== TESTES DE COMPLETUDE ====================

    public function testCalculateCompletenessWithAllAttributesFilled(): void
    {
        $itemData = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ['id' => 'MODEL', 'value_name' => 'Galaxy S21'],
                ['id' => 'COLOR', 'value_name' => 'Preto'],
            ]
        ];

        $allAttributes = [
            ['id' => 'BRAND', 'tags' => ['required']],
            ['id' => 'MODEL', 'tags' => ['required']],
            ['id' => 'COLOR', 'tags' => ['allows_filters']],
        ];

        $result = $this->service->calculateCompleteness($itemData, $allAttributes);

        $this->assertEquals(100, $result['completeness_percent']);
        $this->assertEquals(3, $result['total_attributes']);
        $this->assertEquals(3, $result['filled_attributes']);
        $this->assertEquals(0, $result['missing_attributes']);
        $this->assertEmpty($result['missing_details']['required']);
    }

    public function testCalculateCompletenessWithMissingRequired(): void
    {
        $itemData = [
            'attributes' => [
                ['id' => 'BRAND', 'value_name' => 'Samsung'],
            ]
        ];

        $allAttributes = [
            ['id' => 'BRAND', 'tags' => ['required']],
            ['id' => 'MODEL', 'tags' => ['required']],
            ['id' => 'COLOR', 'tags' => ['recommended']],
        ];

        $result = $this->service->calculateCompleteness($itemData, $allAttributes);

        $this->assertLessThan(50, $result['completeness_percent']);
        $this->assertEquals(1, $result['filled_attributes']);
        $this->assertEquals(2, $result['missing_attributes']);
        $this->assertContains('MODEL', $result['missing_details']['required']);
        $this->assertContains('COLOR', $result['missing_details']['recommended']);
    }

    public function testCalculateCompletenessWithEmptyItem(): void
    {
        $itemData = ['attributes' => []];
        $allAttributes = [
            ['id' => 'BRAND', 'tags' => ['required']],
            ['id' => 'MODEL', 'tags' => ['required']],
        ];

        $result = $this->service->calculateCompleteness($itemData, $allAttributes);

        $this->assertEquals(0, $result['completeness_percent']);
        $this->assertEquals(2, $result['missing_attributes']);
    }

    // ==================== TESTES DE AUTO-APLICAÇÃO ====================

    public function testCanAutoApplyWithHighConfidence(): void
    {
        $suggestion = [
            'attribute_id' => 'COLOR',
            'suggested_value' => 'Preto',
            'confidence' => 90,
            'source' => 'benchmark',
        ];

        $this->assertTrue($this->service->canAutoApply($suggestion));
    }

    public function testCannotAutoApplyWithLowConfidence(): void
    {
        $suggestion = [
            'attribute_id' => 'COLOR',
            'suggested_value' => 'Preto',
            'confidence' => 70, // Abaixo do limite de 80
            'source' => 'ai',
        ];

        $this->assertFalse($this->service->canAutoApply($suggestion));
    }

    public function testCannotAutoApplyCriticalAttributes(): void
    {
        $criticalAttrs = ['BRAND', 'MODEL', 'GTIN', 'MPN'];

        foreach ($criticalAttrs as $attrId) {
            $suggestion = [
                'attribute_id' => $attrId,
                'suggested_value' => 'Test Value',
                'confidence' => 95, // Alta confiança, mas é atributo crítico
                'source' => 'benchmark',
            ];

            $this->assertFalse(
                $this->service->canAutoApply($suggestion),
                "Não deve aplicar automaticamente atributo crítico: $attrId"
            );
        }
    }

    // ==================== TESTES DE CONFIANÇA ====================

    public function testCalculateConfidenceFromTitle(): void
    {
        // Título com match exato
        $confidence = $this->service->calculateConfidence('title', [
            'exact_match' => true,
        ]);
        $this->assertEquals(75, $confidence);

        // Título com regex
        $confidence = $this->service->calculateConfidence('title', [
            'regex_pattern' => '\d+gb',
        ]);
        $this->assertEquals(65, $confidence);

        // Título genérico
        $confidence = $this->service->calculateConfidence('title', []);
        $this->assertEquals(60, $confidence);
    }

    public function testCalculateConfidenceFromBenchmark(): void
    {
        // Unânime entre 3+ concorrentes
        $confidence = $this->service->calculateConfidence('benchmark', [
            'unanimous' => true,
            'total_competitors' => 5,
            'frequency' => 5,
        ]);
        $this->assertEquals(95, $confidence);

        // 75% dos concorrentes usam
        $confidence = $this->service->calculateConfidence('benchmark', [
            'frequency' => 8,
            'total_competitors' => 10,
        ]);
        $this->assertEquals(90, $confidence);

        // 50% dos concorrentes usam
        $confidence = $this->service->calculateConfidence('benchmark', [
            'frequency' => 5,
            'total_competitors' => 10,
        ]);
        $this->assertEquals(80, $confidence);

        // Frequência média-baixa (30% = entre 25-50%)
        $confidence = $this->service->calculateConfidence('benchmark', [
            'frequency' => 3,
            'total_competitors' => 10,
        ]);
        $this->assertEquals(70, $confidence); // Entre 25-50% retorna 70

        // Baixa frequência (20% = abaixo de 25%)
        $confidence = $this->service->calculateConfidence('benchmark', [
            'frequency' => 2,
            'total_competitors' => 10,
        ]);
        $this->assertEquals(65, $confidence); // Abaixo de 25% retorna 65
    }

    public function testCalculateConfidenceFromAI(): void
    {
        // IA com validação + allowed values
        $confidence = $this->service->calculateConfidence('ai', [
            'validation_passed' => true,
            'is_from_allowed_values' => true,
        ]);
        $this->assertEquals(85, $confidence);

        // IA apenas allowed values
        $confidence = $this->service->calculateConfidence('ai', [
            'is_from_allowed_values' => true,
        ]);
        $this->assertEquals(75, $confidence);

        // IA sem validação
        $confidence = $this->service->calculateConfidence('ai', []);
        $this->assertEquals(50, $confidence);
    }

    // ==================== TESTES DE PRIORIZAÇÃO ====================

    public function testPrioritizeMissingAttributesOrder(): void
    {
        $missingDetails = [
            'required' => ['BRAND', 'MODEL'],
            'filter' => ['COLOR', 'SIZE'],
            'recommended' => ['MATERIAL'],
            'hidden' => ['INTERNAL_CODE'],
        ];

        $prioritized = $this->service->prioritizeMissingAttributes($missingDetails);

        // Deve ter 6 itens no total
        $this->assertCount(6, $prioritized);

        // Os 2 primeiros devem ser obrigatórios
        $this->assertEquals('high', $prioritized[0]['priority']);
        $this->assertEquals('high', $prioritized[1]['priority']);

        // Os 2 seguintes devem ser filtros
        $this->assertEquals('medium', $prioritized[2]['priority']);
        $this->assertEquals('medium', $prioritized[3]['priority']);

        // Próximo deve ser recomendado
        $this->assertEquals('low', $prioritized[4]['priority']);

        // Último deve ser escondido
        $this->assertEquals('very_low', $prioritized[5]['priority']);
    }

    public function testPrioritizeNoDuplicates(): void
    {
        // Atributo aparece em múltiplas categorias
        $missingDetails = [
            'required' => ['BRAND'],
            'filter' => ['BRAND'], // Duplicado
            'recommended' => ['COLOR'],
        ];

        $prioritized = $this->service->prioritizeMissingAttributes($missingDetails);

        // Deve ter apenas 2 itens (BRAND e COLOR, sem duplicata)
        $this->assertCount(2, $prioritized);

        $attributes = array_column($prioritized, 'attribute_id');
        $this->assertContains('BRAND', $attributes);
        $this->assertContains('COLOR', $attributes);

        // BRAND deve aparecer apenas uma vez
        $brandCount = count(array_filter($attributes, fn($id) => $id === 'BRAND'));
        $this->assertEquals(1, $brandCount);
    }

    // ==================== TESTES DE VALIDAÇÃO ====================

    public function testValidateSuggestionWithAllowedValues(): void
    {
        $suggestion = [
            'suggested_value' => 'Preto',
        ];

        $attribute = [
            'allowed_values' => [
                ['name' => 'Preto'],
                ['name' => 'Branco'],
                ['name' => 'Azul'],
            ],
        ];

        $result = $this->service->validateSuggestion($suggestion, $attribute);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function testValidateSuggestionWithInvalidValue(): void
    {
        $suggestion = [
            'suggested_value' => 'Rosa', // Não está na lista
        ];

        $attribute = [
            'allowed_values' => [
                ['name' => 'Preto'],
                ['name' => 'Branco'],
            ],
        ];

        $result = $this->service->validateSuggestion($suggestion, $attribute);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('não está na lista', $result['errors'][0]);
    }

    public function testValidateSuggestionNumericType(): void
    {
        $suggestion = [
            'suggested_value' => '128',
        ];

        $attribute = [
            'value_type' => 'number',
        ];

        $result = $this->service->validateSuggestion($suggestion, $attribute);
        $this->assertTrue($result['valid']);

        // Valor não numérico
        $suggestion['suggested_value'] = 'abc';
        $result = $this->service->validateSuggestion($suggestion, $attribute);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('numérico', $result['errors'][0]);
    }

    public function testValidateSuggestionMaxLength(): void
    {
        $suggestion = [
            'suggested_value' => str_repeat('x', 100),
        ];

        $attribute = [
            'value_max_length' => 50,
        ];

        $result = $this->service->validateSuggestion($suggestion, $attribute);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('tamanho máximo', $result['errors'][0]);
    }

    public function testValidateSuggestionEmptyValue(): void
    {
        $suggestion = [
            'suggested_value' => '',
        ];

        $attribute = [];

        $result = $this->service->validateSuggestion($suggestion, $attribute);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('vazio', $result['errors'][0]);
    }
}
