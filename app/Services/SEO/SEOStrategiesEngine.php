<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\MercadoLivreClient;

class SEOStrategiesEngine
{
    private const SCORE_WEIGHTS = [
        'synonym_expansion' => 15,
        'keyword_distribution' => 20,
        'description_building' => 25,
        'coverage_analysis' => 20,
        'semantic_scoring' => 10,
        'hidden_fields' => 10,
    ];

    private $synonymService;
    private KeywordDistributionService $distributionService;
    private DescriptionBuilderService $descriptionService;
    private HiddenAttributesDetector $hiddenFieldsService;
    private SearchCoverageService $coverageService;
    private SemanticScoreService $scoreService;
    private MercadoLivreClient $mlClient;

    public function __construct(?int $accountId = null)
    {
        $this->synonymService = $this->resolveSynonymService($accountId);
        $this->distributionService = new KeywordDistributionService($accountId);
        $this->descriptionService = new DescriptionBuilderService($accountId);
        $this->hiddenFieldsService = new HiddenAttributesDetector($accountId);
        $this->coverageService = new SearchCoverageService($accountId);
        $this->scoreService = new SemanticScoreService($accountId);
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Executa otimização completa (12 estratégias)
     */
    public function optimizeFull(string $itemId): array
    {
        $item = $this->getItemData($itemId);
        $categoryId = (string)($item['category_id'] ?? '');
        $title = (string)($item['title'] ?? '');

        $synonyms = ($title !== '' && $categoryId !== '' && $this->synonymService)
            ? $this->synonymService->expand($title, $categoryId)
            : [];
        $distribution = $categoryId !== ''
            ? $this->distributionService->distribute($item, $categoryId)
            : [];

        $results = [
            'synonym_expansion' => $synonyms,
            'keyword_distribution' => $distribution,
            'description_building' => $this->descriptionService->build($item, $distribution),
            'coverage_analysis' => $this->coverageService->analyzeCoverage($item),
            'semantic_scoring' => $this->scoreItem($item),
            'hidden_fields' => $this->optimizeHiddenFields($itemId, $item),
        ];

        $results['overall_score'] = $this->calculateOverallScore($results);
        $results['report'] = $this->generateReport($itemId, $results);

        return $results;
    }

    /**
     * Executa otimização parcial (estratégias selecionadas)
     */
    public function optimizePartial(string $itemId, array $strategies): array
    {
        $item = $this->getItemData($itemId);
        $results = [];
        $categoryId = (string)($item['category_id'] ?? '');
        $title = (string)($item['title'] ?? '');

        foreach ($strategies as $strategy) {
            switch ($strategy) {
                case 'synonyms':
                    $results['synonym_expansion'] = ($title !== '' && $categoryId !== '' && $this->synonymService)
                        ? $this->synonymService->expand($title, $categoryId)
                        : [];
                    break;
                case 'keywords':
                    $results['keyword_distribution'] = $categoryId !== ''
                        ? $this->distributionService->distribute($item, $categoryId)
                        : [];
                    break;
                case 'description':
                    $distribution = $results['keyword_distribution'] ?? ($categoryId !== ''
                        ? $this->distributionService->distribute($item, $categoryId)
                        : []);
                    $results['description_building'] = $this->descriptionService->build($item, $distribution);
                    break;
                case 'coverage':
                    $results['coverage_analysis'] = $this->coverageService->analyzeCoverage($item);
                    break;
                case 'scoring':
                    $results['semantic_scoring'] = $this->scoreItem($item);
                    break;
                case 'hidden_fields':
                    $results['hidden_fields'] = $this->optimizeHiddenFields($itemId, $item);
                    break;
            }
        }

        $results['overall_score'] = $this->calculatePartialScore($results);

        return $results;
    }

    /**
     * Gera preview de otimização (sem aplicar)
     */
    public function previewOptimization(string $itemId): array
    {
        return $this->optimizeFull($itemId);
    }

    /**
     * Aplica otimizações via API do ML
     */
    public function applyOptimization(string $itemId, array $optimizations, ?int $accountId = null): array
    {
        $payload = $this->buildUpdatePayload($optimizations);

        if (empty($payload)) {
            return [
                'success' => false,
                'changes_applied' => [],
                'item_id' => $itemId,
                'message' => 'Nenhuma atualização válida encontrada.'
            ];
        }

        $response = $this->mlClient->updateItem($itemId, $payload);

        return [
            'success' => (bool)($response['success'] ?? true),
            'changes_applied' => array_keys($payload),
            'item_id' => $itemId,
            'response' => $response
        ];
    }

    /**
     * Calcula score SEO geral
     */
    public function calculateOverallScore(array $analysis): int
    {
        $componentScores = $this->calculateComponentScores($analysis);
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach (self::SCORE_WEIGHTS as $key => $weight) {
            if (!isset($componentScores[$key])) {
                continue;
            }
            $weightedSum += $componentScores[$key] * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal === 0.0) {
            return 0;
        }

        return (int)round($weightedSum / $weightTotal);
    }

    /**
     * Gera relatório de otimização
     */
    public function generateReport(string $itemId, array $results): array
    {
        return [
            'item_id' => $itemId,
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_score' => $results['overall_score'] ?? 0,
            'executed_strategies' => array_keys(array_diff_key($results, ['overall_score' => true, 'report' => true])),
            'improvement_potential' => $this->calculateImprovementPotential($results),
            'recommendations' => $this->generateRecommendations($results)
        ];
    }

    /**
     * Builds description using the description service
     */
    private function buildDescription(array $item): array
    {
        $distribution = $this->distributionService->distribute($item, (string)($item['category_id'] ?? ''));
        return $this->descriptionService->build($item, $distribution);
    }

    /**
     * Scores an item using semantic scoring
     */
    private function scoreItem(array $item): array
    {
        $title = $item['title'] ?? '';
        $categoryId = $item['category_id'] ?? '';
        
        // Get keywords to score against
        $keywords = [];
        if (isset($item['title'])) {
            $keywords = explode(' ', $item['title']);
        }
        
        $scores = $this->scoreService->scoreWords($keywords, $title, $categoryId);
        
        return [
            'individual_scores' => $scores,
            'average_score' => count($scores) > 0 ? array_sum($scores) / count($scores) : 0
        ];
    }

    /**
     * Optimizes hidden fields
     */
    private function optimizeHiddenFields(string $itemId, array $item): array
    {
        $fields = $this->hiddenFieldsService->detectKeywordFields($itemId);
        $score = $this->calculateHiddenFieldsScore($fields);

        return [
            'fields' => $fields,
            'score' => $score
        ];
    }

    /**
     * Gets item data (would typically fetch from DB or API)
     */
    private function getItemData(string $itemId): array
    {
        $item = $this->mlClient->getItemDetails($itemId);
        if (!is_array($item)) {
            $item = [];
        }

        return array_merge([
            'id' => $itemId,
            'title' => '',
            'description' => '',
            'category_id' => '',
            'model' => '',
            'attributes' => []
        ], $item);
    }

    /**
     * Calculates score for partial optimization
     */
    private function calculatePartialScore(array $results): int
    {
        return $this->calculateOverallScore($results);
    }

    /**
     * Calculates improvement potential
     */
    private function calculateImprovementPotential(array $results): float
    {
        // Calculate how much improvement is possible based on current scores
        $currentScore = $results['overall_score'] ?? 0;
        return 100 - $currentScore; // Potential improvement
    }

    /**
     * Generates recommendations based on analysis
     */
    private function generateRecommendations(array $results): array
    {
        $recommendations = [];
        $gaps = $results['coverage_analysis']['gaps'] ?? [];

        foreach ($gaps as $gap) {
            $recommendations[] = "Melhorar cobertura para busca tipo: {$gap['type']} - {$gap['suggestion']}";
        }

        return $recommendations;
    }

    private function calculateComponentScores(array $analysis): array
    {
        $scores = [];

        if (isset($analysis['synonym_expansion'])) {
            $scores['synonym_expansion'] = $this->calculateSynonymScore($analysis['synonym_expansion']);
        }

        if (isset($analysis['keyword_distribution'])) {
            $scores['keyword_distribution'] = $this->calculateDistributionScore($analysis['keyword_distribution']);
        }

        if (isset($analysis['description_building']['score'])) {
            $scores['description_building'] = $this->normalizeScore($analysis['description_building']['score']);
        }

        if (isset($analysis['coverage_analysis']['score'])) {
            $scores['coverage_analysis'] = $this->normalizeScore($analysis['coverage_analysis']['score']);
        }

        if (isset($analysis['semantic_scoring'])) {
            $scores['semantic_scoring'] = $this->extractSemanticScore($analysis['semantic_scoring']);
        }

        if (isset($analysis['hidden_fields'])) {
            $scores['hidden_fields'] = $this->calculateHiddenFieldsScore($analysis['hidden_fields']);
        }

        return $scores;
    }

    private function calculateSynonymScore(array $synonyms): int
    {
        $totalWords = 0;

        foreach ($synonyms as $data) {
            if (is_array($data) && isset($data['words']) && is_array($data['words'])) {
                $totalWords += count($data['words']);
            } elseif (is_array($data)) {
                $totalWords += count($data);
            }
        }

        $target = 12;
        if ($target === 0) {
            return 0;
        }

        return $this->normalizeScore(($totalWords / $target) * 100);
    }

    private function calculateDistributionScore(array $distribution): int
    {
        $scores = [];

        foreach ($distribution as $fieldData) {
            if (!is_array($fieldData)) {
                continue;
            }

            $keywords = $fieldData['keywords'] ?? $fieldData;
            $count = is_array($keywords) ? count($keywords) : 0;
            $limits = $fieldData['limits'] ?? ['min' => 0, 'max' => 0];
            $max = (int)($limits['max'] ?? 0);
            $min = (int)($limits['min'] ?? 0);

            if ($max <= 0) {
                continue;
            }

            $ratio = min(1, $count / $max);
            $fieldScore = $ratio * 100;

            if ($min > 0 && $count < $min) {
                $fieldScore *= 0.7;
            }

            $scores[] = $fieldScore;
        }

        if (empty($scores)) {
            return 0;
        }

        return $this->normalizeScore(array_sum($scores) / count($scores));
    }

    private function extractSemanticScore(array $semantic): int
    {
        if (isset($semantic['average_score'])) {
            return $this->normalizeScore($semantic['average_score']);
        }

        if (isset($semantic['score'])) {
            return $this->normalizeScore($semantic['score']);
        }

        return 0;
    }

    private function calculateHiddenFieldsScore(array $hiddenFields): int
    {
        $fields = $hiddenFields['fields'] ?? $hiddenFields;
        if (!is_array($fields) || empty($fields)) {
            return 0;
        }

        $total = 0;
        $missing = 0;

        foreach ($fields as $fieldData) {
            if (!is_array($fieldData)) {
                continue;
            }
            $total++;
            if (!empty($fieldData['detected'])) {
                $missing++;
            }
        }

        if ($total === 0) {
            return 0;
        }

        return $this->normalizeScore(((1 - ($missing / $total)) * 100));
    }

    private function normalizeScore($score): int
    {
        $value = (float)$score;
        if ($value < 0) {
            return 0;
        }
        if ($value > 100) {
            return 100;
        }

        return (int)round($value);
    }

    private function buildUpdatePayload(array $optimizations): array
    {
        $payload = [];

        if (!empty($optimizations['title'])) {
            $payload['title'] = $optimizations['title'];
        }

        if (!empty($optimizations['description_building']['full_description'])) {
            $payload['description'] = $optimizations['description_building']['full_description'];
        } elseif (!empty($optimizations['description'])) {
            $payload['description'] = $optimizations['description'];
        }

        $fields = $this->extractHiddenFieldsForUpdate($optimizations);
        if (!empty($fields)) {
            $payload['attributes'] = $this->mapHiddenFieldsToAttributes($fields);
        }

        return $payload;
    }

    private function extractHiddenFieldsForUpdate(array $optimizations): array
    {
        if (!empty($optimizations['hidden_fields']['fields'])) {
            return $this->pluckFieldSuggestions($optimizations['hidden_fields']['fields']);
        }

        if (!empty($optimizations['hidden_fields'])) {
            return $this->pluckFieldSuggestions($optimizations['hidden_fields']);
        }

        return [];
    }

    private function pluckFieldSuggestions(array $fields): array
    {
        $values = [];
        foreach ($fields as $fieldId => $data) {
            if (is_array($data)) {
                $value = $data['suggestion'] ?? $data['value'] ?? null;
                if ($value !== null && $value !== '') {
                    $values[$fieldId] = $value;
                }
            } elseif (is_string($data) && $data !== '') {
                $values[$fieldId] = $data;
            }
        }

        return $values;
    }

    private function mapHiddenFieldsToAttributes(array $fields): array
    {
        $attributes = [];
        foreach ($fields as $fieldId => $value) {
            $attributes[] = [
                'id' => $fieldId,
                'value_name' => $value,
            ];
        }

        return $attributes;
    }

    private function resolveSynonymService(?int $accountId)
    {
        $class = '\\App\\Services\\SEO\\SynonymExpansionService';
        if (class_exists($class)) {
            return new $class($accountId);
        }

        return null;
    }
}
