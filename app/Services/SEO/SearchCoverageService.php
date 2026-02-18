<?php
declare(strict_types=1);

namespace App\Services\SEO;

class SearchCoverageService
{
    private const SEARCH_TYPES = [
        'generica' => ['weight' => 30, 'field' => 'title'],
        'qualificada' => ['weight' => 25, 'field' => 'title_model'],
        'long_tail' => ['weight' => 20, 'field' => 'description'],
        'marca_modelo' => ['weight' => 15, 'field' => 'attributes_description'],
        'filtros' => ['weight' => 10, 'field' => 'attributes']
    ];

    public function __construct(?int $accountId = null)
    {
        // Initialization code if needed
    }

    /**
     * Analisa cobertura de tipos de busca
     */
    public function analyzeCoverage(array $item): array
    {
        $coverage = [];

        foreach (self::SEARCH_TYPES as $type => $config) {
            $covered = $this->isSearchTypeCovered($type, $item);
            $coverage[$type] = [
                'status' => $covered ? 'covered' : 'missing',
                'field' => $config['field'],
                'weight' => $config['weight'],
                'keywords_present' => $this->getKeywordsForSearchType($type, $item)
            ];
        }

        $score = $this->calculateCoverageScore($coverage);
        $gaps = $this->identifyGaps($coverage);

        return [
            'coverage' => $coverage,
            'score' => $score,
            'gaps' => $gaps,
            'suggestions' => $this->suggestImprovements($gaps)
        ];
    }

    /**
     * Calcula score de cobertura
     */
    public function calculateCoverageScore(array $coverage): int
    {
        if (isset($coverage['coverage']) && is_array($coverage['coverage'])) {
            $coverage = $coverage['coverage'];
        }

        $totalWeight = 0;
        $coveredWeight = 0;
        
        foreach ($coverage as $type => $data) {
            $totalWeight += $data['weight'];

            $covered = $data['covered'] ?? ($data['status'] ?? '') === 'covered';
            if ($covered) {
                $coveredWeight += $data['weight'];
            }
        }
        
        if ($totalWeight === 0) {
            return 0;
        }
        
        return (int)round(($coveredWeight / $totalWeight) * 100);
    }

    /**
     * Identifica gaps de cobertura
     */
    public function identifyGaps(array $coverage): array
    {
        $gaps = [];

        if (isset($coverage['coverage']) && is_array($coverage['coverage'])) {
            $coverage = $coverage['coverage'];
        }

        foreach ($coverage as $type => $data) {
            $covered = $data['covered'] ?? ($data['status'] ?? '') === 'covered';
            if ($covered) {
                continue;
            }

            $gaps[$type] = [
                'type' => $type,
                'field' => $data['field'],
                'weight' => $data['weight'],
                'suggestion' => $this->getSuggestionForGap($type)
            ];
        }

        return $gaps;
    }

    /**
     * Sugere melhorias para cobertura
     */
    public function suggestImprovements(array $gaps): array
    {
        $suggestions = [];

        foreach ($gaps as $gap) {
            $suggestions[] = [
                'type' => $gap['type'],
                'field_to_improve' => $gap['field'],
                'importance' => $gap['weight'],
                'action' => $gap['suggestion']
            ];
        }

        usort($suggestions, function($a, $b) {
            return $b['importance'] <=> $a['importance'];
        });

        return $suggestions;
    }

    /**
     * Check if a search type is covered in the item
     */
    private function isSearchTypeCovered(string $type, array $item): bool
    {
        $handlers = [
            'generica' => fn() => $this->isGenericCovered($item),
            'qualificada' => fn() => $this->isQualifiedCovered($item),
            'long_tail' => fn() => $this->isLongTailCovered($item),
            'marca_modelo' => fn() => $this->isBrandModelCovered($item),
            'filtros' => fn() => $this->isFilterCovered($item),
        ];

        if (!isset($handlers[$type])) {
            return false;
        }

        return $handlers[$type]();
    }

    /**
     * Get keywords relevant to a search type
     */
    private function getKeywordsForSearchType(string $type, array $item): array
    {
        $allKeywords = $this->collectItemKeywords($item);

        $filters = [
            'generica' => fn(array $keywords) => array_values(array_filter(
                $keywords,
                fn($keyword) => mb_strlen($keyword) >= 3 && mb_strlen($keyword) <= 8
            )),
            'long_tail' => fn(array $keywords) => $keywords,
            'default' => fn(array $keywords) => $keywords,
        ];

        $filter = $filters[$type] ?? $filters['default'];
        $relevantKeywords = $filter($allKeywords);

        return array_values(array_unique($relevantKeywords));
    }

    private function isGenericCovered(array $item): bool
    {
        $title = strtolower($this->normalizeTextField($item['title'] ?? ''));
        return $title !== '' && str_word_count($title) >= 2;
    }

    private function isQualifiedCovered(array $item): bool
    {
        $title = strtolower($this->normalizeTextField($item['title'] ?? ''));
        $model = strtolower($this->normalizeTextField($item['model'] ?? ''));
        return (str_word_count($title) >= 3 || str_word_count($model) >= 2);
    }

    private function isLongTailCovered(array $item): bool
    {
        $description = $this->normalizeTextField($item['description'] ?? '');
        return str_word_count($description) >= 80;
    }

    private function isBrandModelCovered(array $item): bool
    {
        $attributes = $item['attributes'] ?? [];
        return $this->hasBrandModelInfo($attributes);
    }

    private function isFilterCovered(array $item): bool
    {
        $attributes = $item['attributes'] ?? [];
        return is_array($attributes) && count($attributes) >= 3;
    }

    /**
     * Check if attributes contain brand/model info
     */
    private function hasBrandModelInfo(array $attributes): bool
    {
        $brandIds = ['BRAND', 'MODEL'];
        $brandNames = ['marca', 'modelo', 'fabricante', 'brand', 'model'];

        foreach ($attributes as $attr) {
            if (!is_array($attr)) {
                continue;
            }

            $id = strtoupper($attr['id'] ?? '');
            $name = strtolower($attr['name'] ?? '');
            $value = $attr['value_name'] ?? ($attr['value'] ?? null);

            if (!empty($value) && (in_array($id, $brandIds, true) || in_array($name, $brandNames, true))) {
                return true;
            }
        }

        return false;
    }

    private function collectItemKeywords(array $item): array
    {
        $keywords = [];

        foreach (['title', 'model', 'description'] as $field) {
            if (!empty($item[$field])) {
                $value = $item[$field];
                if (is_array($value)) {
                    $value = $value['plain_text'] ?? ($value['text'] ?? '');
                }
                $value = is_string($value) ? $value : (string) $value;
                $keywords = array_merge($keywords, preg_split('/\s+/', strtolower($value)));
            }
        }

        if (!empty($item['attributes']) && is_array($item['attributes'])) {
            foreach ($item['attributes'] as $attr) {
                if (is_string($attr)) {
                    $keywords = array_merge($keywords, preg_split('/\s+/', strtolower($attr)));
                    continue;
                }

                if (!is_array($attr)) {
                    continue;
                }

                foreach (['value_name', 'value', 'name'] as $key) {
                    if (!empty($attr[$key])) {
                        $keywords = array_merge($keywords, preg_split('/\s+/', strtolower((string)$attr[$key])));
                    }
                }
            }
        }

        $keywords = array_filter($keywords, fn($keyword) => $keyword !== '');
        return array_values($keywords);
    }

    /**
     * Get suggestion for a gap
     */
    private function getSuggestionForGap(string $type): string
    {
        switch ($type) {
            case 'generica':
                return 'Melhore o título com termos mais descritivos';
            case 'qualificada':
                return 'Adicione informações qualificadoras no título ou campo modelo';
            case 'long_tail':
                return 'Amplie a descrição com mais detalhes e variações de termos';
            case 'marca_modelo':
                return 'Inclua informações de marca e modelo nos atributos';
            case 'filtros':
                return 'Adicione mais atributos para melhorar a filtragem';
            default:
                return 'Considere adicionar conteúdo relevante para este tipo de busca';
        }
    }

    private function normalizeTextField($value): string
    {
        if (is_array($value)) {
            return (string)($value['plain_text'] ?? ($value['text'] ?? ''));
        }

        return is_string($value) ? $value : (string) $value;
    }
}