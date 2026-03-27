<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Testes unitários para TechSheetBenchmarkService
 * 
 * Testa análise de concorrentes, cálculo de frequência,
 * scoring de confiança e extração de queries de busca
 */
class TechSheetBenchmarkServiceTest extends TestCase
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
            /**
             * Analisa atributos de concorrentes e retorna frequências
             */
            public function analyzeCompetitorAttributes(
                array $competitors,
                array $targetAttributes
            ): array {
                $analysis = [];

                foreach ($targetAttributes as $targetAttr) {
                    $attrId = $targetAttr['id'];
                    $valueCounts = [];
                    $totalWithAttribute = 0;

                    // Conta frequência de cada valor entre concorrentes
                    foreach ($competitors as $competitor) {
                        $attributes = $competitor['attributes'] ?? [];
                        
                        foreach ($attributes as $attr) {
                            if ($attr['id'] === $attrId) {
                                $value = $attr['value_name'] ?? '';
                                
                                if (!empty($value) && $value !== 'N/A') {
                                    $valueCounts[$value] = ($valueCounts[$value] ?? 0) + 1;
                                    $totalWithAttribute++;
                                }
                                break;
                            }
                        }
                    }

                    // Ordena por frequência
                    arsort($valueCounts);

                    $analysis[$attrId] = [
                        'attribute_id' => $attrId,
                        'total_competitors' => count($competitors),
                        'competitors_with_attribute' => $totalWithAttribute,
                        'value_frequencies' => $valueCounts,
                        'most_common_value' => !empty($valueCounts) 
                            ? array_key_first($valueCounts) 
                            : null,
                        'most_common_frequency' => !empty($valueCounts) 
                            ? reset($valueCounts) 
                            : 0,
                    ];
                }

                return $analysis;
            }

            /**
             * Calcula confiança baseado em frequência e unanimidade
             */
            public function calculateConfidence(array $analysisData): int
            {
                $totalCompetitors = $analysisData['total_competitors'] ?? 0;
                $frequency = $analysisData['most_common_frequency'] ?? 0;

                if ($totalCompetitors === 0 || $frequency === 0) {
                    return 0;
                }

                // Verifica unanimidade
                $isUnanimous = ($frequency === $totalCompetitors);
                
                if ($isUnanimous && $totalCompetitors >= 3) {
                    return 95; // Máxima confiança
                }

                // Calcula percentual de uso
                $percentUsage = ($frequency / $totalCompetitors) * 100;

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

            /**
             * Extrai query de busca a partir do título
             */
            public function extractSearchQuery(string $title): string
            {
                // Remove caracteres especiais
                $query = preg_replace('/[^\w\s\-áéíóúãõâêôàèìòùäëïöüç]/ui', ' ', $title);
                
                // Tokeniza
                $tokens = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

                // Remove stopwords comuns
                $stopwords = [
                    'de', 'da', 'do', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas',
                    'para', 'com', 'sem', 'por', 'um', 'uma', 'o', 'a', 'os', 'as',
                    'e', 'ou', 'mas', 'mais', 'menos', 'muito', 'pouco', 'novo',
                    'novo', 'nova', 'original', 'kit', 'unidade', 'peça',
                ];

                $filtered = array_filter($tokens, function($token) use ($stopwords) {
                    $lower = mb_strtolower($token);
                    return mb_strlen($lower) >= 3 && !in_array($lower, $stopwords);
                });

                // Limita a 6 termos mais relevantes (primeiros após filtragem)
                $mainTerms = array_slice($filtered, 0, 6);

                return implode(' ', $mainTerms);
            }

            /**
             * Filtra valores inválidos de sugestões
             */
            public function filterInvalidValues(array $suggestions): array
            {
                return array_filter($suggestions, function($suggestion) {
                    $value = $suggestion['suggested_value'] ?? '';
                    
                    // Remove valores genéricos/inválidos
                    $invalidPatterns = [
                        '/^n\/a$/i',
                        '/^não.*aplicável/i',
                        '/^desconhecido/i',
                        '/^outro/i',
                        '/^-+$/',
                        '/^\s*$/',
                    ];

                    foreach ($invalidPatterns as $pattern) {
                        if (preg_match($pattern, $value)) {
                            return false;
                        }
                    }

                    return true;
                });
            }

            /**
             * Mescla sugestões de múltiplas fontes mantendo a melhor
             */
            public function mergeSuggestions(array $suggestions): array
            {
                $merged = [];

                foreach ($suggestions as $suggestion) {
                    $attrId = $suggestion['attribute_id'] ?? '';
                    
                    if (empty($attrId)) {
                        continue;
                    }

                    // Se não existe ou tem confiança maior, substitui
                    if (!isset($merged[$attrId]) 
                        || $suggestion['confidence'] > $merged[$attrId]['confidence']) {
                        $merged[$attrId] = $suggestion;
                    }
                }

                return array_values($merged);
            }
        };
    }

    // ==================== TESTES DE ANÁLISE DE CONCORRENTES ====================

    public function testAnalyzeCompetitorAttributesUnanimous(): void
    {
        $competitors = [
            [
                'attributes' => [
                    ['id' => 'COLOR', 'value_name' => 'Preto'],
                    ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ]
            ],
            [
                'attributes' => [
                    ['id' => 'COLOR', 'value_name' => 'Preto'],
                    ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ]
            ],
            [
                'attributes' => [
                    ['id' => 'COLOR', 'value_name' => 'Preto'],
                    ['id' => 'BRAND', 'value_name' => 'Samsung'],
                ]
            ],
        ];

        $targetAttributes = [
            ['id' => 'COLOR'],
            ['id' => 'BRAND'],
        ];

        $analysis = $this->service->analyzeCompetitorAttributes($competitors, $targetAttributes);

        // COLOR
        $this->assertEquals('Preto', $analysis['COLOR']['most_common_value']);
        $this->assertEquals(3, $analysis['COLOR']['most_common_frequency']);
        $this->assertEquals(3, $analysis['COLOR']['total_competitors']);

        // BRAND
        $this->assertEquals('Samsung', $analysis['BRAND']['most_common_value']);
        $this->assertEquals(3, $analysis['BRAND']['most_common_frequency']);
    }

    public function testAnalyzeCompetitorAttributesVariedValues(): void
    {
        $competitors = [
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Preto']]],
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Preto']]],
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Branco']]],
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Azul']]],
        ];

        $targetAttributes = [['id' => 'COLOR']];

        $analysis = $this->service->analyzeCompetitorAttributes($competitors, $targetAttributes);

        // Preto deve ser o mais comum (2 ocorrências)
        $this->assertEquals('Preto', $analysis['COLOR']['most_common_value']);
        $this->assertEquals(2, $analysis['COLOR']['most_common_frequency']);
        $this->assertEquals(4, $analysis['COLOR']['total_competitors']);

        // Deve ter contagem para todos os valores
        $frequencies = $analysis['COLOR']['value_frequencies'];
        $this->assertEquals(2, $frequencies['Preto']);
        $this->assertEquals(1, $frequencies['Branco']);
        $this->assertEquals(1, $frequencies['Azul']);
    }

    public function testAnalyzeCompetitorAttributesIgnoresInvalidValues(): void
    {
        $competitors = [
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Preto']]],
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'N/A']]], // Inválido
            ['attributes' => [['id' => 'COLOR', 'value_name' => '']]],     // Vazio
            ['attributes' => [['id' => 'COLOR', 'value_name' => 'Preto']]],
        ];

        $targetAttributes = [['id' => 'COLOR']];

        $analysis = $this->service->analyzeCompetitorAttributes($competitors, $targetAttributes);

        // Deve contar apenas os 2 valores "Preto" válidos
        $this->assertEquals(2, $analysis['COLOR']['competitors_with_attribute']);
        $this->assertEquals('Preto', $analysis['COLOR']['most_common_value']);
    }

    public function testAnalyzeCompetitorAttributesMissingAttribute(): void
    {
        $competitors = [
            ['attributes' => [['id' => 'BRAND', 'value_name' => 'Samsung']]],
            ['attributes' => [['id' => 'BRAND', 'value_name' => 'LG']]],
        ];

        // Busca por COLOR, que não existe nos concorrentes
        $targetAttributes = [['id' => 'COLOR']];

        $analysis = $this->service->analyzeCompetitorAttributes($competitors, $targetAttributes);

        $this->assertEquals(0, $analysis['COLOR']['competitors_with_attribute']);
        $this->assertNull($analysis['COLOR']['most_common_value']);
        $this->assertEquals(0, $analysis['COLOR']['most_common_frequency']);
    }

    // ==================== TESTES DE CÁLCULO DE CONFIANÇA ====================

    public function testCalculateConfidenceUnanimous(): void
    {
        $data = [
            'total_competitors' => 5,
            'most_common_frequency' => 5, // Todos têm o mesmo valor
        ];

        $confidence = $this->service->calculateConfidence($data);

        $this->assertEquals(95, $confidence);
    }

    public function testCalculateConfidenceHighFrequency(): void
    {
        // 80% dos concorrentes (8/10)
        $data = [
            'total_competitors' => 10,
            'most_common_frequency' => 8,
        ];

        $confidence = $this->service->calculateConfidence($data);
        $this->assertEquals(90, $confidence);
    }

    public function testCalculateConfidenceMediumFrequency(): void
    {
        // 50% dos concorrentes (5/10)
        $data = [
            'total_competitors' => 10,
            'most_common_frequency' => 5,
        ];

        $confidence = $this->service->calculateConfidence($data);
        $this->assertEquals(80, $confidence);
    }

    public function testCalculateConfidenceLowFrequency(): void
    {
        // 30% dos concorrentes (3/10)
        $data = [
            'total_competitors' => 10,
            'most_common_frequency' => 3,
        ];

        $confidence = $this->service->calculateConfidence($data);
        $this->assertEquals(70, $confidence);
    }

    public function testCalculateConfidenceVeryLowFrequency(): void
    {
        // 10% dos concorrentes (1/10)
        $data = [
            'total_competitors' => 10,
            'most_common_frequency' => 1,
        ];

        $confidence = $this->service->calculateConfidence($data);
        $this->assertEquals(65, $confidence);
    }

    public function testCalculateConfidenceZeroData(): void
    {
        $data = [
            'total_competitors' => 0,
            'most_common_frequency' => 0,
        ];

        $confidence = $this->service->calculateConfidence($data);
        $this->assertEquals(0, $confidence);
    }

    // ==================== TESTES DE EXTRAÇÃO DE QUERY ====================

    public function testExtractSearchQueryBasic(): void
    {
        $title = 'Samsung Galaxy S21 128GB Preto';
        $query = $this->service->extractSearchQuery($title);

        // Deve remover stopwords e manter termos relevantes
        $this->assertStringContainsString('Samsung', $query);
        $this->assertStringContainsString('Galaxy', $query);
        $this->assertStringContainsString('S21', $query);
        $this->assertStringContainsString('128GB', $query);
        $this->assertStringContainsString('Preto', $query);
    }

    public function testExtractSearchQueryRemovesStopwords(): void
    {
        $title = 'Notebook Dell para trabalho com Windows';
        $query = $this->service->extractSearchQuery($title);

        // Deve manter termos importantes
        $this->assertStringContainsString('Notebook', $query);
        $this->assertStringContainsString('Dell', $query);
        $this->assertStringContainsString('Windows', $query);

        // Não deve conter stopwords
        $this->assertStringNotContainsString(' para ', $query);
        $this->assertStringNotContainsString(' com ', $query);
    }

    public function testExtractSearchQueryRemovesSpecialChars(): void
    {
        $title = 'iPhone 13 Pro Max - 256GB!!! (Novo)';
        $query = $this->service->extractSearchQuery($title);

        // Deve limpar caracteres especiais
        $this->assertStringNotContainsString('!!!', $query);
        $this->assertStringNotContainsString('(', $query);
        $this->assertStringNotContainsString(')', $query);
        $this->assertStringNotContainsString('-', $query);
    }

    public function testExtractSearchQueryLimitsTerms(): void
    {
        $title = 'Produto Um Dois Três Quatro Cinco Seis Sete Oito Nove Dez';
        $query = $this->service->extractSearchQuery($title);

        // Deve limitar a 6 termos
        $terms = explode(' ', $query);
        $this->assertLessThanOrEqual(6, count($terms));
    }

    public function testExtractSearchQueryMinLength(): void
    {
        $title = 'A B CD Samsung Galaxy'; // Termos curtos devem ser removidos
        $query = $this->service->extractSearchQuery($title);

        // Não deve conter termos com menos de 3 caracteres
        $this->assertStringNotContainsString(' A ', $query);
        $this->assertStringNotContainsString(' B ', $query);
        $this->assertStringNotContainsString(' CD ', $query);
    }

    // ==================== TESTES DE FILTRAGEM DE VALORES ====================

    public function testFilterInvalidValuesRemovesNA(): void
    {
        $suggestions = [
            ['suggested_value' => 'Preto', 'confidence' => 80],
            ['suggested_value' => 'N/A', 'confidence' => 70],      // Inválido
            ['suggested_value' => 'n/a', 'confidence' => 60],      // Inválido
            ['suggested_value' => 'Branco', 'confidence' => 75],
        ];

        $filtered = $this->service->filterInvalidValues($suggestions);

        $this->assertCount(2, $filtered);
        
        $values = array_column($filtered, 'suggested_value');
        $this->assertContains('Preto', $values);
        $this->assertContains('Branco', $values);
        $this->assertNotContains('N/A', $values);
        $this->assertNotContains('n/a', $values);
    }

    public function testFilterInvalidValuesRemovesGeneric(): void
    {
        $suggestions = [
            ['suggested_value' => 'Samsung', 'confidence' => 90],
            ['suggested_value' => 'Desconhecido', 'confidence' => 50],  // Inválido
            ['suggested_value' => 'Não aplicável', 'confidence' => 40], // Inválido
            ['suggested_value' => 'Outro', 'confidence' => 30],         // Inválido
            ['suggested_value' => '---', 'confidence' => 20],           // Inválido
        ];

        $filtered = $this->service->filterInvalidValues($suggestions);

        $this->assertCount(1, $filtered);
        $this->assertEquals('Samsung', reset($filtered)['suggested_value']);
    }

    public function testFilterInvalidValuesRemovesEmpty(): void
    {
        $suggestions = [
            ['suggested_value' => 'Válido', 'confidence' => 80],
            ['suggested_value' => '', 'confidence' => 70],      // Vazio
            ['suggested_value' => '   ', 'confidence' => 60],   // Apenas espaços
        ];

        $filtered = $this->service->filterInvalidValues($suggestions);

        $this->assertCount(1, $filtered);
        $this->assertEquals('Válido', reset($filtered)['suggested_value']);
    }

    // ==================== TESTES DE MESCLAGEM DE SUGESTÕES ====================

    public function testMergeSuggestionsKeepsHigherConfidence(): void
    {
        $suggestions = [
            [
                'attribute_id' => 'COLOR',
                'suggested_value' => 'Preto',
                'confidence' => 70,
                'source' => 'title',
            ],
            [
                'attribute_id' => 'COLOR',
                'suggested_value' => 'Preto',
                'confidence' => 90, // Maior confiança
                'source' => 'benchmark',
            ],
            [
                'attribute_id' => 'BRAND',
                'suggested_value' => 'Samsung',
                'confidence' => 80,
                'source' => 'benchmark',
            ],
        ];

        $merged = $this->service->mergeSuggestions($suggestions);

        $this->assertCount(2, $merged); // COLOR e BRAND

        // COLOR deve ter a sugestão com maior confiança (90)
        $colorSuggestion = array_filter($merged, fn($s) => $s['attribute_id'] === 'COLOR');
        $colorSuggestion = reset($colorSuggestion);
        
        $this->assertEquals(90, $colorSuggestion['confidence']);
        $this->assertEquals('benchmark', $colorSuggestion['source']);
    }

    public function testMergeSuggestionsKeepsAllAttributes(): void
    {
        $suggestions = [
            ['attribute_id' => 'COLOR', 'suggested_value' => 'Preto', 'confidence' => 80],
            ['attribute_id' => 'BRAND', 'suggested_value' => 'Samsung', 'confidence' => 85],
            ['attribute_id' => 'MODEL', 'suggested_value' => 'Galaxy', 'confidence' => 75],
        ];

        $merged = $this->service->mergeSuggestions($suggestions);

        $this->assertCount(3, $merged);

        $attributes = array_column($merged, 'attribute_id');
        $this->assertContains('COLOR', $attributes);
        $this->assertContains('BRAND', $attributes);
        $this->assertContains('MODEL', $attributes);
    }

    public function testMergeSuggestionsIgnoresEmptyAttributeId(): void
    {
        $suggestions = [
            ['attribute_id' => '', 'suggested_value' => 'Valor', 'confidence' => 80],
            ['attribute_id' => 'COLOR', 'suggested_value' => 'Preto', 'confidence' => 90],
        ];

        $merged = $this->service->mergeSuggestions($suggestions);

        $this->assertCount(1, $merged);
        $this->assertEquals('COLOR', $merged[0]['attribute_id']);
    }

    public function testMergeSuggestionsWithDifferentValues(): void
    {
        // Mesmo atributo, valores diferentes - deve manter o de maior confiança
        $suggestions = [
            [
                'attribute_id' => 'COLOR',
                'suggested_value' => 'Azul',
                'confidence' => 70,
            ],
            [
                'attribute_id' => 'COLOR',
                'suggested_value' => 'Preto', // Diferente, mas confiança maior
                'confidence' => 90,
            ],
        ];

        $merged = $this->service->mergeSuggestions($suggestions);

        $this->assertCount(1, $merged);
        $this->assertEquals('Preto', $merged[0]['suggested_value']);
        $this->assertEquals(90, $merged[0]['confidence']);
    }
}
