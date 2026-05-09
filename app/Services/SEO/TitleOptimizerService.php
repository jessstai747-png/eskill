<?php

declare(strict_types=1);

namespace App\Services\SEO;

class TitleOptimizerService
{
    private const STOP_WORDS = [
        'de', 'da', 'do', 'das', 'dos', 'para', 'com', 'em', 'no', 'na', 'nos',
        'nas', 'por', 'e', 'a', 'o', 'os', 'as', 'ao', 'aos', 'um', 'uma',
    ];

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    public function analyzeTitle(string $title, array $competitors = []): array
    {
        $analysis   = $this->performTitleAnalysis($title, $competitors);
        $gaps       = $this->identifyTitleGaps($title, $competitors);
        $recs       = $this->generateTitleRecommendations($title, $analysis);
        $opportunity = $this->calculateOpportunityScore($title, $competitors);

        return [
            'title'            => $title,
            'analysis'         => $analysis,
            'gaps'             => $gaps,
            'recommendations'  => $recs,
            'opportunity_score' => $opportunity,
        ];
    }

    public function generateOptimizedTitles(string $title, array $keywords = [], array $options = []): array
    {
        $base     = trim($title);
        $variants = [$base];

        if (!empty($keywords)) {
            $topKeywords = array_slice($keywords, 0, 3);
            $variants[]  = $base . ' ' . implode(' ', $topKeywords);
        }

        return $variants;
    }

    public function generateModelAttribute(array $product, array $competitors = []): string
    {
        $title = (string) ($product['title'] ?? '');
        $attrs = $this->findMissingAttributes($title, $competitors);
        return implode(' ', array_slice($attrs, 0, 2));
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function extractKeywords(string $title): array
    {
        $lower = strtolower(trim($title));
        $words = preg_split('/\s+/', $lower, -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false) {
            return [];
        }

        $keywords = [];
        foreach ($words as $word) {
            $clean = preg_replace('/[^\p{L}\p{N}]/u', '', $word);
            if ($clean === null || $clean === '') {
                continue;
            }
            if (mb_strlen($clean) <= 2) {
                continue;
            }
            if (in_array($clean, self::STOP_WORDS, true)) {
                continue;
            }
            $keywords[] = $clean;
        }

        return array_values(array_unique($keywords));
    }

    private function findSemanticGaps(string $ourTitle, array $competitors): array
    {
        $myKeywords = $this->extractKeywords($ourTitle);
        $gaps       = [];

        foreach ($competitors as $competitorTitle) {
            $compKeywords = $this->extractKeywords((string) $competitorTitle);
            foreach ($compKeywords as $kw) {
                if (!in_array($kw, $myKeywords, true) && !in_array($kw, $gaps, true)) {
                    $gaps[] = $kw;
                }
            }
        }

        return $gaps;
    }

    private function analyzeSearchIntent(string $ourTitle, array $competitors): array
    {
        $intentSignals = [
            'original', 'novo', 'genuino', 'genuíno', 'oficial', 'premium',
            'top', 'melhor', 'garantido', 'lacrado', 'nacional', 'importado',
        ];

        $myKeywords = $this->extractKeywords($ourTitle);
        $found      = [];

        foreach ($competitors as $competitorTitle) {
            $compKeywords = $this->extractKeywords((string) $competitorTitle);
            foreach ($compKeywords as $kw) {
                if (
                    in_array($kw, $intentSignals, true)
                    && !in_array($kw, $myKeywords, true)
                    && !in_array($kw, $found, true)
                ) {
                    $found[] = $kw;
                }
            }
        }

        return $found;
    }

    private function findMissingAttributes(string $title, array $competitors): array
    {
        $titleLower = strtolower($title);
        $missing    = [];

        foreach ($competitors as $competitor) {
            $attributes = $competitor['attributes'] ?? [];
            foreach ($attributes as $attr) {
                $value = (string) ($attr['value_name'] ?? '');
                if ($value === '') {
                    continue;
                }
                if (
                    stripos($titleLower, strtolower($value)) === false
                    && !in_array($value, $missing, true)
                ) {
                    $missing[] = $value;
                }
                if (count($missing) >= 5) {
                    break 2;
                }
            }
        }

        return array_slice($missing, 0, 5);
    }

    private function calculateOpportunityScore(string $title, array $competitors): int
    {
        $score    = 50;
        $length   = strlen($title);
        $words    = count(array_filter(explode(' ', trim($title))));

        if ($length < 20) {
            $score += 20;
        } elseif ($length < 40) {
            $score += 10;
        }

        if ($words < 5) {
            $score += 15;
        } elseif ($words > 8) {
            $score -= 10;
        }

        if (!preg_match('/\d/', $title)) {
            $score += 10;
        }

        return min(100, max(0, $score));
    }

    private function identifyTitleGaps(string $title, array $competitors): array
    {
        $semanticGaps = $this->findSemanticGaps($title, $competitors);
        $intentGaps   = $this->analyzeSearchIntent($title, $competitors);
        $attrGaps     = $this->findMissingAttributes($title, $competitors);

        return [
            'semantic_gaps' => $semanticGaps,
            'intent_gaps'   => $intentGaps,
            'missing_attrs' => $attrGaps,
        ];
    }

    private function performTitleAnalysis(string $title, array $competitors): array
    {
        $keywords = $this->extractKeywords($title);
        $length   = strlen($title);

        return [
            'keyword_count'   => count($keywords),
            'character_count' => $length,
            'keywords'        => $keywords,
            'has_numbers'     => (bool) preg_match('/\d/', $title),
        ];
    }

    private function generateTitleRecommendations(string $title, array $analysis): array
    {
        $recs = [];

        if (($analysis['character_count'] ?? 0) < 30) {
            $recs[] = ['type' => 'length', 'message' => 'Título muito curto — adicione mais keywords relevantes'];
        }

        if (($analysis['keyword_count'] ?? 0) < 3) {
            $recs[] = ['type' => 'keywords', 'message' => 'Poucas keywords — inclua modelo, marca e especificações'];
        }

        if (empty($recs)) {
            $recs[] = ['type' => 'general', 'message' => 'Título adequado — monitore performance'];
        }

        return $recs;
    }
}
