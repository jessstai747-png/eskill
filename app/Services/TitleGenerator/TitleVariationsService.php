<?php

declare(strict_types=1);

namespace App\Services\TitleGenerator;

/**
 * Title Variations Service - Geração de Variações de Títulos
 * 
 * Cria variações criativas e otimizadas de títulos existentes usando:
 * - Reordenação de componentes
 * - Substituição de sinônimos
 * - Adição/remoção de modificadores
 * - Variações de especificações
 * - A/B testing variations
 */
class TitleVariationsService
{
    private TitleAnalyzerService $analyzer;

    // Sinônimos comuns
    private const SYNONYMS = [
        'Celular' => ['Smartphone', 'Telefone', 'Cell'],
        'Notebook' => ['Laptop', 'Portátil'],
        'TV' => ['Televisão', 'Smart TV', 'Televisor'],
        'Fone' => ['Headphone', 'Headset', 'Auricular'],
        'Câmera' => ['Camera', 'Filmadora'],
        'Relógio' => ['Watch', 'Smartwatch'],
        'Tênis' => ['Sneaker', 'Calçado Esportivo'],
        'Camiseta' => ['Camisa', 'T-Shirt', 'Blusa'],
    ];

    // Modificadores de qualidade/versão
    private const MODIFIERS = [
        'quality' => ['Premium', 'Pro', 'Professional', 'Advanced', 'Superior'],
        'size' => ['Plus', 'Max', 'Mini', 'Compact', 'XL'],
        'version' => ['Edition', 'Version', 'Model', 'Series'],
        'condition' => ['Novo', 'Lacrado', 'Original', 'Nacional', 'Importado'],
        'feature' => ['Ultra', 'Super', 'Mega', 'Turbo', 'Extra'],
    ];

    // Conectores úteis
    private const CONNECTORS = ['-', '|', '/', '·'];

    // Abreviações comuns
    private const ABBREVIATIONS = [
        'Gigabyte' => 'GB',
        'Terabyte' => 'TB',
        'Megapixel' => 'MP',
        'Polegadas' => '"',
        'Centímetros' => 'cm',
        'Quilograma' => 'kg',
        'Mililitro' => 'ml',
    ];

    public function __construct()
    {
        $this->analyzer = new TitleAnalyzerService();
    }

    /**
     * Gera múltiplas variações de um título
     */
    public function generateVariations(string $title, array $options = []): array
    {
        $count = $options['count'] ?? 10;
        $categoryId = $options['category_id'] ?? '';
        $strategy = $options['strategy'] ?? 'all'; // all, conservative, aggressive
        $minScore = $options['min_score'] ?? 60;

        // Parse título em componentes
        $components = $this->parseTitle($title);

        // Gerar variações
        $variations = [];

        // Estratégia 1: Reordenar componentes
        $variations = array_merge($variations, $this->reorderComponents($components));

        // Estratégia 2: Substituir sinônimos
        $variations = array_merge($variations, $this->applySynonyms($title, $components));

        // Estratégia 3: Adicionar modificadores
        $variations = array_merge($variations, $this->addModifiers($title, $components));

        // Estratégia 4: Remover palavras desnecessárias
        $variations = array_merge($variations, $this->removeUnnecessaryWords($title, $components));

        // Estratégia 5: Expandir abreviações
        $variations = array_merge($variations, $this->expandAbbreviations($title));

        // Estratégia 6: Comprimir (abreviar)
        $variations = array_merge($variations, $this->compressTitle($title));

        // Estratégia 7: Variações A/B
        $variations = array_merge($variations, $this->generateABVariations($title, $components));

        // Remover duplicatas e título original
        $variations = array_unique($variations);
        $variations = array_filter($variations, fn($v) => $v !== $title);

        // Avaliar todas variações
        $evaluated = [];
        foreach ($variations as $variation) {
            if (mb_strlen($variation) > 60) continue; // Skip títulos muito longos

            $analysis = $this->analyzer->analyzeTitle($variation, $categoryId);
            
            if ($analysis['overall_score'] >= $minScore) {
                $evaluated[] = [
                    'title' => $variation,
                    'score' => $analysis['overall_score'],
                    'length' => mb_strlen($variation),
                    'strategy' => $this->identifyStrategy($title, $variation),
                    'improvements' => $this->compareToOriginal($title, $variation, $categoryId),
                ];
            }
        }

        // Ordenar por score
        usort($evaluated, fn($a, $b) => $b['score'] <=> $a['score']);

        // Aplicar filtro de estratégia
        if ($strategy !== 'all') {
            $evaluated = $this->filterByStrategy($evaluated, $strategy);
        }

        // Retornar top N
        $topVariations = array_slice($evaluated, 0, $count);

        return [
            'success' => true,
            'original_title' => $title,
            'original_score' => $this->analyzer->analyzeTitle($title, $categoryId)['overall_score'],
            'variations_generated' => count($variations),
            'variations_suitable' => count($evaluated),
            'variations' => $topVariations,
            'best_variation' => $topVariations[0] ?? null,
        ];
    }

    /**
     * Gera variações específicas para A/B testing
     */
    public function generateABTestingVariations(string $title, array $options = []): array
    {
        $categoryId = $options['category_id'] ?? '';
        
        $components = $this->parseTitle($title);

        // Criar 3 variações distintas para A/B testing
        $variations = [];

        // Variação A: Keyword-first (otimizado para SEO)
        if (!empty($components['specs'])) {
            $keywordFirst = implode(' ', array_slice($components['specs'], 0, 2));
            if (!empty($components['brand'])) {
                $keywordFirst .= ' ' . $components['brand'];
            }
            if (!empty($components['model'])) {
                $keywordFirst .= ' ' . $components['model'];
            }
            if (mb_strlen($keywordFirst) <= 60) {
                $variations[] = [
                    'type' => 'A',
                    'title' => $keywordFirst,
                    'focus' => 'SEO / Ranking',
                    'description' => 'Keywords no início para melhor posicionamento em buscas'
                ];
            }
        }

        // Variação B: Brand-first (otimizado para conversão)
        $brandFirst = '';
        if (!empty($components['brand'])) {
            $brandFirst = $components['brand'];
            if (!empty($components['model'])) {
                $brandFirst .= ' ' . $components['model'];
            }
            foreach ($components['specs'] as $spec) {
                if (mb_strlen($brandFirst . ' ' . $spec) <= 60) {
                    $brandFirst .= ' ' . $spec;
                }
            }
            $variations[] = [
                'type' => 'B',
                'title' => $brandFirst,
                'focus' => 'Conversão / Confiança',
                'description' => 'Marca em destaque para aumentar confiança do comprador'
            ];
        }

        // Variação C: Specs-heavy (otimizado para usuários técnicos)
        if (!empty($components['specs'])) {
            $specsHeavy = implode(' ', $components['specs']);
            if (!empty($components['brand'])) {
                $specsHeavy .= ' ' . $components['brand'];
            }
            if (mb_strlen($specsHeavy) <= 60) {
                $variations[] = [
                    'type' => 'C',
                    'title' => $specsHeavy,
                    'focus' => 'Especificações / Clareza',
                    'description' => 'Máximo de informações técnicas para compradores informados'
                ];
            }
        }

        // Avaliar cada variação
        foreach ($variations as &$variation) {
            $analysis = $this->analyzer->analyzeTitle($variation['title'], $categoryId);
            $variation['score'] = $analysis['overall_score'];
            $variation['length'] = mb_strlen($variation['title']);
            $variation['estimated_ctr'] = $analysis['performance_estimate']['click_through_rate_estimate'];
            $variation['ranking_potential'] = $analysis['performance_estimate']['ranking_potential'];
        }

        return [
            'success' => true,
            'original_title' => $title,
            'ab_variations' => $variations,
            'recommendation' => $this->recommendABVariation($variations),
        ];
    }

    /**
     * Parse título em componentes
     */
    private function parseTitle(string $title): array
    {
        $words = explode(' ', $title);
        
        $components = [
            'brand' => '',
            'model' => '',
            'specs' => [],
            'modifiers' => [],
            'all_words' => $words,
        ];

        // Identificar marca (primeira palavra capitalizada)
        if (!empty($words[0]) && preg_match('/^[A-Z]/', $words[0])) {
            $components['brand'] = $words[0];
        }

        // Identificar modelo (segunda palavra alfanumérica)
        if (!empty($words[1]) && preg_match('/[A-Za-z]\d+|[A-Z]+/', $words[1])) {
            $components['model'] = $words[1];
        }

        // Resto são specs/modifiers
        $remaining = array_slice($words, 2);
        foreach ($remaining as $word) {
            // Verificar se é modificador conhecido
            $isModifier = false;
            foreach (self::MODIFIERS as $modList) {
                if (in_array($word, $modList)) {
                    $components['modifiers'][] = $word;
                    $isModifier = true;
                    break;
                }
            }
            
            if (!$isModifier) {
                $components['specs'][] = $word;
            }
        }

        return $components;
    }

    /**
     * Reordena componentes
     */
    private function reorderComponents(array $components): array
    {
        $variations = [];
        $brand = $components['brand'] ?? '';
        $model = $components['model'] ?? '';
        $specs = $components['specs'] ?? [];

        // Specs → Brand → Model
        if (!empty($specs) && !empty($brand)) {
            $reordered = implode(' ', array_slice($specs, 0, 2)) . " $brand";
            if (!empty($model)) $reordered .= " $model";
            $variations[] = trim($reordered);
        }

        // Model → Brand → Specs
        if (!empty($model) && !empty($brand)) {
            $reordered = "$model $brand";
            if (!empty($specs)) {
                $reordered .= ' ' . implode(' ', array_slice($specs, 0, 3));
            }
            $variations[] = trim($reordered);
        }

        // Last spec first
        if (!empty($specs) && count($specs) >= 2) {
            $lastSpec = array_pop($specs);
            $reordered = $lastSpec . ' ' . implode(' ', $specs);
            if (!empty($brand)) $reordered .= " $brand";
            $variations[] = trim($reordered);
        }

        return array_filter($variations);
    }

    /**
     * Aplica sinônimos
     */
    private function applySynonyms(string $title, array $components): array
    {
        $variations = [];

        foreach (self::SYNONYMS as $word => $synonyms) {
            if (stripos($title, $word) !== false) {
                foreach ($synonyms as $synonym) {
                    $variation = str_ireplace($word, $synonym, $title);
                    if ($variation !== $title) {
                        $variations[] = $variation;
                    }
                }
            }
        }

        return $variations;
    }

    /**
     * Adiciona modificadores
     */
    private function addModifiers(string $title, array $components): array
    {
        $variations = [];

        // Só adicionar se tiver espaço (< 55 caracteres)
        if (mb_strlen($title) >= 55) {
            return $variations;
        }

        foreach (self::MODIFIERS as $category => $modList) {
            foreach ($modList as $modifier) {
                // Adicionar no final
                $withModifier = "$title $modifier";
                if (mb_strlen($withModifier) <= 60) {
                    $variations[] = $withModifier;
                }

                // Adicionar após marca
                if (!empty($components['brand'])) {
                    $withModifierAfterBrand = str_replace(
                        $components['brand'],
                        $components['brand'] . ' ' . $modifier,
                        $title
                    );
                    if (mb_strlen($withModifierAfterBrand) <= 60) {
                        $variations[] = $withModifierAfterBrand;
                    }
                }
            }
        }

        return array_slice($variations, 0, 20); // Limitar variações
    }

    /**
     * Remove palavras desnecessárias
     */
    private function removeUnnecessaryWords(string $title, array $components): array
    {
        $variations = [];
        
        // Palavras que podem ser removidas
        $unnecessary = ['com', 'para', 'de', 'em', 'da', 'do', 'e'];

        foreach ($unnecessary as $word) {
            $variation = preg_replace('/\b' . $word . '\b/i', '', $title);
            $variation = preg_replace('/\s+/', ' ', $variation); // Limpar espaços duplos
            $variation = trim($variation);
            
            if ($variation !== $title && mb_strlen($variation) >= 30) {
                $variations[] = $variation;
            }
        }

        return $variations;
    }

    /**
     * Expande abreviações
     */
    private function expandAbbreviations(string $title): array
    {
        $variations = [];

        foreach (self::ABBREVIATIONS as $full => $abbr) {
            if (str_contains($title, $abbr)) {
                $expanded = str_replace($abbr, $full, $title);
                if (mb_strlen($expanded) <= 60) {
                    $variations[] = $expanded;
                }
            }
        }

        return $variations;
    }

    /**
     * Comprime título (abrevia)
     */
    private function compressTitle(string $title): array
    {
        $compressed = $title;

        foreach (self::ABBREVIATIONS as $full => $abbr) {
            $compressed = str_ireplace($full, $abbr, $compressed);
        }

        return $compressed !== $title ? [$compressed] : [];
    }

    /**
     * Gera variações A/B
     */
    private function generateABVariations(string $title, array $components): array
    {
        $variations = [];

        // Versão com conectores
        foreach (self::CONNECTORS as $connector) {
            if (!empty($components['brand']) && !empty($components['model'])) {
                $withConnector = $components['brand'] . ' ' . $connector . ' ' . $components['model'];
                if (!empty($components['specs'])) {
                    $withConnector .= ' ' . implode(' ', array_slice($components['specs'], 0, 2));
                }
                if (mb_strlen($withConnector) <= 60) {
                    $variations[] = trim($withConnector);
                }
            }
        }

        return $variations;
    }

    /**
     * Identifica estratégia usada
     */
    private function identifyStrategy(string $original, string $variation): string
    {
        if (mb_strlen($variation) < mb_strlen($original) - 5) {
            return 'compression';
        } elseif (mb_strlen($variation) > mb_strlen($original) + 5) {
            return 'expansion';
        } elseif ($this->wordsReordered($original, $variation)) {
            return 'reorder';
        } else {
            return 'modification';
        }
    }

    /**
     * Verifica se palavras foram reordenadas
     */
    private function wordsReordered(string $str1, string $str2): bool
    {
        $words1 = explode(' ', mb_strtolower($str1));
        $words2 = explode(' ', mb_strtolower($str2));
        
        sort($words1);
        sort($words2);
        
        return $words1 === $words2;
    }

    /**
     * Compara com original
     */
    private function compareToOriginal(string $original, string $variation, string $categoryId): array
    {
        $originalAnalysis = $this->analyzer->analyzeTitle($original, $categoryId);
        $variationAnalysis = $this->analyzer->analyzeTitle($variation, $categoryId);

        return [
            'score_change' => $variationAnalysis['overall_score'] - $originalAnalysis['overall_score'],
            'length_change' => mb_strlen($variation) - mb_strlen($original),
            'improvements' => array_diff(
                $variationAnalysis['seo_analysis']['factors'] ?? [],
                $originalAnalysis['seo_analysis']['factors'] ?? []
            ),
        ];
    }

    /**
     * Filtra por estratégia
     */
    private function filterByStrategy(array $variations, string $strategy): array
    {
        if ($strategy === 'conservative') {
            // Apenas pequenas mudanças
            return array_filter($variations, function ($v) {
                return abs($v['improvements']['score_change']) <= 10;
            });
        } elseif ($strategy === 'aggressive') {
            // Mudanças significativas
            return array_filter($variations, function ($v) {
                return $v['improvements']['score_change'] > 10;
            });
        }

        return $variations;
    }

    /**
     * Recomenda melhor variação A/B
     */
    private function recommendABVariation(array $variations): array
    {
        if (empty($variations)) {
            return ['recommendation' => 'none', 'reason' => 'Nenhuma variação disponível'];
        }

        // Ordenar por score
        usort($variations, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $best = $variations[0];

        return [
            'recommended_type' => $best['type'],
            'recommended_title' => $best['title'],
            'reason' => "Melhor score ({$best['score']}) e {$best['focus']}",
            'all_scores' => array_map(fn($v) => [
                'type' => $v['type'],
                'score' => $v['score'],
                'focus' => $v['focus']
            ], $variations),
        ];
    }
}
