<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;

class SemanticScoreService
{
    private SynonymExpansionService $synonymService;
    private array $useContexts = [];

    public function __construct(?int $accountId = null)
    {
        $this->synonymService = new SynonymExpansionService($accountId);
        $this->loadUseContexts();
    }

    /**
     * Calcula score de relevância semântica
     */
    public function calculateScore(string $word, string $title, string $categoryId): float
    {
        $components = $this->getScoreComponents($word, $title);
        
        // Calculate weighted sum of components
        $totalScore = 0;
        $totalWeight = 0;
        
        foreach ($components as $component => $data) {
            $totalScore += $data['score'] * $data['weight'];
            $totalWeight += $data['weight'];
        }
        
        // Normalize score to 0-100 range
        $normalizedScore = $totalWeight > 0 ? ($totalScore / $totalWeight) * 100 : 0;
        
        // Adjust score based on category-specific contexts
        if ($this->hasUseContext($word)) {
            $normalizedScore *= 1.2; // Boost for words with known context
        }
        
        // Ensure score doesn't exceed 100
        return min($normalizedScore, 100);
    }

    /**
     * Calcula score para lista de palavras
     */
    public function scoreWords(array $words, string $title, string $categoryId): array
    {
        $scores = [];
        
        foreach ($words as $word) {
            $scores[$word] = $this->calculateScore($word, $title, $categoryId);
        }
        
        return $scores;
    }

    /**
     * Rankeia palavras por score
     */
    public function rankByScore(array $words, string $title, string $categoryId): array
    {
        $scores = $this->scoreWords($words, $title, $categoryId);
        
        // Sort by score descending
        arsort($scores);
        
        return $scores;
    }

    /**
     * Verifica se palavra tem contexto de uso
     */
    public function hasUseContext(string $word): bool
    {
        $wordLower = strtolower($word);
        
        foreach ($this->useContexts as $contextGroup) {
            foreach ($contextGroup as $contextWord) {
                if (strtolower($contextWord) === $wordLower) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Retorna contextos disponíveis
     */
    public function getContexts(string $categoryId): array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT context_type, keyword
                FROM seo_use_contexts
                WHERE is_active = 1
                AND (category_id = :category_id OR category_id = '')
            ");
            $stmt->execute(['category_id' => $categoryId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $contexts = [];
            foreach ($rows as $row) {
                $context = $row['context_type'] ?? null;
                $keyword = $row['keyword'] ?? null;
                if (!$context || !$keyword) {
                    continue;
                }
                if (!isset($contexts[$context])) {
                    $contexts[$context] = [];
                }
                $contexts[$context][] = $keyword;
            }
            return $contexts;
        } catch (\Throwable $e) {
            return $this->useContexts;
        }
    }

    /**
     * Componentes do score
     */
    private function getScoreComponents(string $word, string $title): array
    {
        $wordLower = strtolower($word);
        $titleLower = strtolower($title);
        
        return [
            'relevance' => [
                'score' => $this->calculateRelevanceScore($wordLower, $titleLower),
                'weight' => 0.4
            ],
            'frequency_in_title' => [
                'score' => $this->calculateFrequencyScore($wordLower, $titleLower),
                'weight' => 0.2
            ],
            'semantic_similarity' => [
                'score' => $this->calculateSemanticSimilarityScore($wordLower, $titleLower),
                'weight' => 0.3
            ],
            'context_matching' => [
                'score' => $this->calculateContextMatchingScore($wordLower),
                'weight' => 0.1
            ]
        ];
    }

    /**
     * Calculates relevance score based on word importance
     */
    private function calculateRelevanceScore(string $word, string $title): float
    {
        // Higher score for words that appear in the title
        if (strpos($title, $word) !== false) {
            return 1.0;
        }
        
        // Lower score for words not in title
        return 0.3;
    }

    /**
     * Calculates frequency score
     */
    private function calculateFrequencyScore(string $word, string $title): float
    {
        $wordsInTitle = explode(' ', $title);
        $count = 0;
        
        foreach ($wordsInTitle as $titleWord) {
            if (strtolower($titleWord) === $word) {
                $count++;
            }
        }
        
        // Return normalized frequency (0-1 scale)
        return min($count / max(count($wordsInTitle), 1), 1);
    }

    /**
     * Calculates semantic similarity score
     */
    private function calculateSemanticSimilarityScore(string $word, string $title): float
    {
        $titleWords = explode(' ', $title);
        $maxSimilarity = 0;
        
        foreach ($titleWords as $titleWord) {
            similar_text($word, $titleWord, $percent);
            $similarity = $percent / 100;
            
            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
            }
        }
        
        return $maxSimilarity;
    }

    /**
     * Calculates context matching score
     */
    private function calculateContextMatchingScore(string $word): float
    {
        if ($this->hasUseContext($word)) {
            return 1.0;
        }
        
        return 0.1;
    }

    /**
     * Loads use contexts from database or configuration
     */
    private function loadUseContexts(): void
    {
        $this->useContexts = [];
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT context_type, keyword
                FROM seo_use_contexts
                WHERE is_active = 1
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $context = $row['context_type'] ?? null;
                $keyword = $row['keyword'] ?? null;
                if (!$context || !$keyword) {
                    continue;
                }
                if (!isset($this->useContexts[$context])) {
                    $this->useContexts[$context] = [];
                }
                $this->useContexts[$context][] = $keyword;
            }
        } catch (\Throwable $e) {
            $this->useContexts = [];
        }
    }
}
