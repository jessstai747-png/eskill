<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

use App\Database;
use App\Services\AI\ML\KeywordClassifierService;
use PDO;

/**
 * 🔄 Learning Pipeline Service
 * 
 * Pipeline de coleta de resultados que:
 * - Armazena outcomes de otimizações
 * - Calcula médias e tendências simples (não treina modelos ML)
 * - Melhora recomendações via dados acumulados
 * - Rastreia progresso e performance das otimizações
 */
class LearningPipelineService
{
    private PDO $db;
    private int $accountId;

    // Learning types
    public const LEARN_TITLE = 'title';
    public const LEARN_DESCRIPTION = 'description';
    public const LEARN_PRICE = 'price';
    public const LEARN_KEYWORDS = 'keywords';
    public const LEARN_CATEGORY = 'category';
    public const LEARN_CONVERSION = 'conversion';

    // Model performance thresholds
    private const MIN_ACCURACY = 0.70;
    private const RETRAIN_THRESHOLD = 0.60;
    private const SAMPLE_SIZE_MIN = 50;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * 📥 Ingest optimization outcome for learning
     */
    public function ingestOutcome(string $learningType, array $outcome): array
    {
        $startTime = microtime(true);

        try {
            // Validate outcome
            if (!$this->validateOutcome($learningType, $outcome)) {
                return [
                    'success' => false,
                    'error' => 'Invalid outcome format',
                ];
            }

            // Store outcome
            $outcomeId = $this->storeOutcome($learningType, $outcome);

            // Check if we should trigger learning
            $shouldLearn = $this->shouldTriggerLearning($learningType);

            if ($shouldLearn) {
                // Run incremental learning
                $learningResult = $this->runIncrementalLearning($learningType);
            }

            return [
                'success' => true,
                'outcome_id' => $outcomeId,
                'learning_triggered' => $shouldLearn,
                'learning_result' => $learningResult ?? null,
                'processing_time' => round(microtime(true) - $startTime, 3),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Validate outcome data
     */
    private function validateOutcome(string $learningType, array $outcome): bool
    {
        $requiredFields = [
            self::LEARN_TITLE => ['item_id', 'original', 'optimized', 'metric_before', 'metric_after'],
            self::LEARN_DESCRIPTION => ['item_id', 'original', 'optimized', 'metric_before', 'metric_after'],
            self::LEARN_PRICE => ['item_id', 'original_price', 'new_price', 'sales_before', 'sales_after'],
            self::LEARN_KEYWORDS => ['category_id', 'keyword', 'performance_score', 'conversions'],
            self::LEARN_CATEGORY => ['category_id', 'patterns', 'success_rate'],
            self::LEARN_CONVERSION => ['item_id', 'visits', 'conversions', 'changes_applied'],
        ];

        $required = $requiredFields[$learningType] ?? [];

        foreach ($required as $field) {
            if (!isset($outcome[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * 💾 Store outcome in database
     */
    private function storeOutcome(string $learningType, array $outcome): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO learning_outcomes (
                account_id, learning_type, outcome_data, 
                success_score, created_at
            ) VALUES (
                :account_id, :learning_type, :outcome_data,
                :success_score, NOW()
            )
        ");

        $successScore = $this->calculateSuccessScore($learningType, $outcome);

        $stmt->execute([
            'account_id' => $this->accountId,
            'learning_type' => $learningType,
            'outcome_data' => json_encode($outcome),
            'success_score' => $successScore,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * 📊 Calculate success score from outcome
     */
    private function calculateSuccessScore(string $learningType, array $outcome): float
    {
        switch ($learningType) {
            case self::LEARN_TITLE:
            case self::LEARN_DESCRIPTION:
                $before = $outcome['metric_before'] ?? 0;
                $after = $outcome['metric_after'] ?? 0;
                return $before > 0 ? min(1.0, max(0.0, ($after - $before) / $before + 0.5)) : 0.5;

            case self::LEARN_PRICE:
                $salesBefore = $outcome['sales_before'] ?? 0;
                $salesAfter = $outcome['sales_after'] ?? 0;
                $profitBefore = $salesBefore * ($outcome['original_price'] ?? 0);
                $profitAfter = $salesAfter * ($outcome['new_price'] ?? 0);
                return $profitBefore > 0 ? min(1.0, max(0.0, ($profitAfter - $profitBefore) / $profitBefore + 0.5)) : 0.5;

            case self::LEARN_KEYWORDS:
                return min(1.0, ($outcome['performance_score'] ?? 0) / 100);

            case self::LEARN_CATEGORY:
                return $outcome['success_rate'] ?? 0.5;

            case self::LEARN_CONVERSION:
                $visits = $outcome['visits'] ?? 1;
                $conversions = $outcome['conversions'] ?? 0;
                return min(1.0, $conversions / $visits);

            default:
                return 0.5;
        }
    }

    /**
     * 🔍 Check if learning should be triggered
     */
    private function shouldTriggerLearning(string $learningType): bool
    {
        try {
            // Check sample count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM learning_outcomes
                WHERE account_id = :account_id
                AND learning_type = :learning_type
                AND processed = 0
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'learning_type' => $learningType,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $pendingCount = (int) ($row['count'] ?? 0);

            // Trigger if enough pending samples
            if ($pendingCount >= self::SAMPLE_SIZE_MIN) {
                return true;
            }

            // Also check model performance
            $modelPerformance = $this->getModelPerformance($learningType);

            if ($modelPerformance !== null && $modelPerformance < self::RETRAIN_THRESHOLD) {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 📈 Get current model performance
     */
    private function getModelPerformance(string $learningType): ?float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT accuracy
                FROM learning_models
                WHERE account_id = :account_id
                AND model_type = :model_type
                ORDER BY trained_at DESC
                LIMIT 1
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'model_type' => $learningType,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? (float) $row['accuracy'] : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * 🧠 Run incremental learning
     */
    private function runIncrementalLearning(string $learningType): array
    {
        $startTime = microtime(true);

        // Get pending outcomes
        $outcomes = $this->getPendingOutcomes($learningType);

        if (empty($outcomes)) {
            return ['success' => false, 'reason' => 'No pending outcomes'];
        }

        // Process outcomes based on type
        $learningResult = match ($learningType) {
            self::LEARN_TITLE => $this->learnFromTitles($outcomes),
            self::LEARN_DESCRIPTION => $this->learnFromDescriptions($outcomes),
            self::LEARN_PRICE => $this->learnFromPrices($outcomes),
            self::LEARN_KEYWORDS => $this->learnFromKeywords($outcomes),
            self::LEARN_CATEGORY => $this->learnFromCategories($outcomes),
            self::LEARN_CONVERSION => $this->learnFromConversions($outcomes),
            default => ['patterns' => []],
        };

        // Mark outcomes as processed
        $this->markOutcomesProcessed($learningType);

        // Calculate new model accuracy
        $accuracy = $this->calculateModelAccuracy($learningType, $learningResult);

        // Store updated model
        $this->storeModel($learningType, $learningResult, $accuracy);

        return [
            'success' => true,
            'samples_processed' => count($outcomes),
            'patterns_learned' => count($learningResult['patterns'] ?? []),
            'model_accuracy' => round($accuracy, 3),
            'training_time' => round(microtime(true) - $startTime, 3),
        ];
    }

    /**
     * 📝 Get pending outcomes
     */
    private function getPendingOutcomes(string $learningType): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, outcome_data, success_score
                FROM learning_outcomes
                WHERE account_id = :account_id
                AND learning_type = :learning_type
                AND processed = 0
                ORDER BY created_at ASC
                LIMIT 500
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'learning_type' => $learningType,
            ]);

            $outcomes = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $data = json_decode($row['outcome_data'], true) ?: [];
                $data['_id'] = $row['id'];
                $data['_success_score'] = (float) $row['success_score'];
                $outcomes[] = $data;
            }

            return $outcomes;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * 📰 Learn from title outcomes
     */
    private function learnFromTitles(array $outcomes): array
    {
        $patterns = [];
        $successfulPatterns = [];
        $failedPatterns = [];

        foreach ($outcomes as $outcome) {
            $success = $outcome['_success_score'] >= 0.6;
            $original = $outcome['original'] ?? '';
            $optimized = $outcome['optimized'] ?? '';

            // Extract patterns from optimized titles
            $titlePatterns = $this->extractTitlePatterns($optimized);

            foreach ($titlePatterns as $pattern) {
                if (!isset($patterns[$pattern])) {
                    $patterns[$pattern] = ['success' => 0, 'fail' => 0, 'examples' => []];
                }

                if ($success) {
                    $patterns[$pattern]['success']++;
                    $patterns[$pattern]['examples'][] = $optimized;
                } else {
                    $patterns[$pattern]['fail']++;
                }
            }
        }

        // Calculate pattern success rates
        $patternScores = [];
        foreach ($patterns as $pattern => $data) {
            $total = $data['success'] + $data['fail'];
            if ($total >= 3) { // Minimum samples
                $patternScores[$pattern] = [
                    'pattern' => $pattern,
                    'success_rate' => $data['success'] / $total,
                    'sample_size' => $total,
                    'example' => $data['examples'][0] ?? null,
                ];
            }
        }

        // Sort by success rate
        uasort($patternScores, fn($a, $b) => $b['success_rate'] <=> $a['success_rate']);

        return [
            'patterns' => array_slice($patternScores, 0, 20),
            'total_samples' => count($outcomes),
        ];
    }

    /**
     * 🔍 Extract patterns from title
     */
    private function extractTitlePatterns(string $title): array
    {
        $patterns = [];

        // Word count pattern
        $wordCount = str_word_count($title);
        $patterns[] = "word_count_" . ($wordCount <= 5 ? 'short' : ($wordCount <= 8 ? 'medium' : 'long'));

        // Character length pattern
        $charCount = strlen($title);
        $patterns[] = "char_length_" . ($charCount <= 40 ? 'short' : ($charCount <= 55 ? 'optimal' : 'long'));

        // Starts with brand
        if (preg_match('/^[A-Z][a-z]+/', $title)) {
            $patterns[] = 'starts_with_brand';
        }

        // Contains numbers
        if (preg_match('/\d+/', $title)) {
            $patterns[] = 'contains_numbers';
        }

        // Contains size indicators
        if (preg_match('/\b(P|M|G|GG|XG|PP|XL|XXL)\b/i', $title)) {
            $patterns[] = 'contains_size';
        }

        // Contains color
        if (preg_match('/\b(preto|branco|azul|vermelho|verde|amarelo|rosa|cinza|marrom|bege)\b/i', $title)) {
            $patterns[] = 'contains_color';
        }

        return $patterns;
    }

    /**
     * 📄 Learn from description outcomes
     */
    private function learnFromDescriptions(array $outcomes): array
    {
        $patterns = [];

        foreach ($outcomes as $outcome) {
            $success = $outcome['_success_score'] >= 0.6;
            $description = $outcome['optimized'] ?? '';

            // Extract description patterns
            $descPatterns = $this->extractDescriptionPatterns($description);

            foreach ($descPatterns as $pattern) {
                if (!isset($patterns[$pattern])) {
                    $patterns[$pattern] = ['success' => 0, 'fail' => 0];
                }

                if ($success) {
                    $patterns[$pattern]['success']++;
                } else {
                    $patterns[$pattern]['fail']++;
                }
            }
        }

        // Calculate scores
        $patternScores = [];
        foreach ($patterns as $pattern => $data) {
            $total = $data['success'] + $data['fail'];
            if ($total >= 3) {
                $patternScores[$pattern] = [
                    'pattern' => $pattern,
                    'success_rate' => $data['success'] / $total,
                    'sample_size' => $total,
                ];
            }
        }

        uasort($patternScores, fn($a, $b) => $b['success_rate'] <=> $a['success_rate']);

        return ['patterns' => array_slice($patternScores, 0, 20)];
    }

    /**
     * Extract description patterns
     */
    private function extractDescriptionPatterns(string $description): array
    {
        $patterns = [];

        // Length categories
        $length = strlen($description);
        $patterns[] = "length_" . ($length < 500 ? 'short' : ($length < 1000 ? 'medium' : 'long'));

        // Has bullet points
        if (preg_match('/[•\-\*]/', $description)) {
            $patterns[] = 'has_bullets';
        }

        // Has sections/headers
        if (preg_match('/\n[A-Z]{2,}|^\w+:$/m', $description)) {
            $patterns[] = 'has_sections';
        }

        // Has specifications
        if (preg_match('/\d+\s*(cm|mm|kg|g|ml|L|W|V)/i', $description)) {
            $patterns[] = 'has_specs';
        }

        return $patterns;
    }

    /**
     * 💰 Learn from price outcomes
     */
    private function learnFromPrices(array $outcomes): array
    {
        $priceChanges = [];

        foreach ($outcomes as $outcome) {
            $originalPrice = $outcome['original_price'] ?? 0;
            $newPrice = $outcome['new_price'] ?? 0;
            $success = $outcome['_success_score'] >= 0.6;

            if ($originalPrice > 0) {
                $changePercent = (($newPrice - $originalPrice) / $originalPrice) * 100;

                $priceChanges[] = [
                    'change_percent' => $changePercent,
                    'success' => $success,
                ];
            }
        }

        // Analyze successful change ranges
        $successfulChanges = array_filter($priceChanges, fn($c) => $c['success']);
        $avgSuccessChange = count($successfulChanges) > 0
            ? array_sum(array_column($successfulChanges, 'change_percent')) / count($successfulChanges)
            : 0;

        return [
            'patterns' => [
                'optimal_change_range' => [
                    'min' => -15,
                    'max' => 10,
                    'avg_successful' => round($avgSuccessChange, 2),
                ],
            ],
            'samples' => count($priceChanges),
            'success_rate' => count($priceChanges) > 0
                ? count($successfulChanges) / count($priceChanges)
                : 0,
        ];
    }

    /**
     * 🔤 Learn from keyword outcomes
     */
    private function learnFromKeywords(array $outcomes): array
    {
        $keywordScores = [];

        foreach ($outcomes as $outcome) {
            $keyword = $outcome['keyword'] ?? '';
            $performance = $outcome['performance_score'] ?? 0;
            $conversions = $outcome['conversions'] ?? 0;

            $keywordScores[$keyword] = [
                'keyword' => $keyword,
                'performance' => $performance,
                'conversions' => $conversions,
                'score' => $outcome['_success_score'],
            ];
        }

        // Sort by score
        uasort($keywordScores, fn($a, $b) => $b['score'] <=> $a['score']);

        // Update keyword classifier
        try {
            $classifier = new KeywordClassifierService($this->accountId);
            foreach (array_slice($keywordScores, 0, 50) as $data) {
                // High performers are likely CORE keywords
                if ($data['score'] >= 0.8) {
                    // Could update classification weights here
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }

        return [
            'patterns' => array_slice($keywordScores, 0, 30),
            'top_performers' => array_slice(array_keys($keywordScores), 0, 10),
        ];
    }

    /**
     * 📁 Learn from category outcomes
     */
    private function learnFromCategories(array $outcomes): array
    {
        $categoryPatterns = [];

        foreach ($outcomes as $outcome) {
            $categoryId = $outcome['category_id'] ?? '';
            $patterns = $outcome['patterns'] ?? [];
            $successRate = $outcome['success_rate'] ?? 0;

            if (!isset($categoryPatterns[$categoryId])) {
                $categoryPatterns[$categoryId] = [
                    'patterns' => [],
                    'success_rates' => [],
                ];
            }

            $categoryPatterns[$categoryId]['patterns'] = array_merge(
                $categoryPatterns[$categoryId]['patterns'],
                $patterns
            );
            $categoryPatterns[$categoryId]['success_rates'][] = $successRate;
        }

        // Calculate average success rates
        foreach ($categoryPatterns as $catId => &$data) {
            $data['avg_success'] = count($data['success_rates']) > 0
                ? array_sum($data['success_rates']) / count($data['success_rates'])
                : 0;
        }

        return ['patterns' => $categoryPatterns];
    }

    /**
     * 🎯 Learn from conversion outcomes
     */
    private function learnFromConversions(array $outcomes): array
    {
        $changeImpacts = [];

        foreach ($outcomes as $outcome) {
            $changes = $outcome['changes_applied'] ?? [];
            $conversionRate = ($outcome['conversions'] ?? 0) / max(1, $outcome['visits'] ?? 1);
            $success = $outcome['_success_score'] >= 0.6;

            foreach ($changes as $change) {
                $changeType = $change['type'] ?? 'unknown';

                if (!isset($changeImpacts[$changeType])) {
                    $changeImpacts[$changeType] = [
                        'success' => 0,
                        'fail' => 0,
                        'conversion_rates' => [],
                    ];
                }

                if ($success) {
                    $changeImpacts[$changeType]['success']++;
                } else {
                    $changeImpacts[$changeType]['fail']++;
                }

                $changeImpacts[$changeType]['conversion_rates'][] = $conversionRate;
            }
        }

        // Calculate impact scores
        $impactScores = [];
        foreach ($changeImpacts as $type => $data) {
            $total = $data['success'] + $data['fail'];
            if ($total >= 3) {
                $avgConversion = count($data['conversion_rates']) > 0
                    ? array_sum($data['conversion_rates']) / count($data['conversion_rates'])
                    : 0;

                $impactScores[$type] = [
                    'type' => $type,
                    'success_rate' => $data['success'] / $total,
                    'avg_conversion' => round($avgConversion * 100, 2),
                    'sample_size' => $total,
                ];
            }
        }

        uasort($impactScores, fn($a, $b) => $b['success_rate'] <=> $a['success_rate']);

        return ['patterns' => $impactScores];
    }

    /**
     * ✅ Mark outcomes as processed
     */
    private function markOutcomesProcessed(string $learningType): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE learning_outcomes
                SET processed = 1, processed_at = NOW()
                WHERE account_id = :account_id
                AND learning_type = :learning_type
                AND processed = 0
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'learning_type' => $learningType,
            ]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * 📊 Calculate model accuracy
     */
    private function calculateModelAccuracy(string $learningType, array $result): float
    {
        $patterns = $result['patterns'] ?? [];

        if (empty($patterns)) {
            return 0.5;
        }

        // Average success rate across top patterns
        $successRates = array_filter(array_map(
            fn($p) => $p['success_rate'] ?? null,
            is_array($patterns) ? array_values($patterns) : []
        ), fn($r) => $r !== null);

        if (empty($successRates)) {
            return 0.5;
        }

        return array_sum($successRates) / count($successRates);
    }

    /**
     * 💾 Store updated model
     */
    private function storeModel(string $learningType, array $result, float $accuracy): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO learning_models (
                    account_id, model_type, model_data, accuracy, trained_at
                ) VALUES (
                    :account_id, :model_type, :model_data, :accuracy, NOW()
                )
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'model_type' => $learningType,
                'model_data' => json_encode($result),
                'accuracy' => $accuracy,
            ]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * 📈 Get learning stats
     */
    public function getStats(): array
    {
        try {
            // Outcomes by type
            $stmt = $this->db->prepare("
                SELECT learning_type, 
                       COUNT(*) as total,
                       SUM(CASE WHEN processed = 1 THEN 1 ELSE 0 END) as processed,
                       AVG(success_score) as avg_success
                FROM learning_outcomes
                WHERE account_id = :account_id
                GROUP BY learning_type
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $outcomeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Model performance
            $stmt = $this->db->prepare("
                SELECT model_type, accuracy, trained_at
                FROM learning_models
                WHERE account_id = :account_id
                ORDER BY trained_at DESC
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $modelStats = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($modelStats[$row['model_type']])) {
                    $modelStats[$row['model_type']] = [
                        'accuracy' => (float) $row['accuracy'],
                        'last_trained' => $row['trained_at'],
                    ];
                }
            }

            return [
                'outcomes' => $outcomeStats,
                'models' => $modelStats,
                'health' => $this->calculatePipelineHealth($outcomeStats, $modelStats),
            ];

        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 💚 Calculate pipeline health
     */
    private function calculatePipelineHealth(array $outcomeStats, array $modelStats): array
    {
        $totalSamples = array_sum(array_column($outcomeStats, 'total'));
        $avgModelAccuracy = count($modelStats) > 0
            ? array_sum(array_column($modelStats, 'accuracy')) / count($modelStats)
            : 0;

        $health = 'unknown';
        if ($totalSamples < self::SAMPLE_SIZE_MIN) {
            $health = 'insufficient_data';
        } elseif ($avgModelAccuracy >= self::MIN_ACCURACY) {
            $health = 'healthy';
        } elseif ($avgModelAccuracy >= self::RETRAIN_THRESHOLD) {
            $health = 'needs_improvement';
        } else {
            $health = 'critical';
        }

        return [
            'status' => $health,
            'total_samples' => $totalSamples,
            'avg_model_accuracy' => round($avgModelAccuracy, 3),
            'models_count' => count($modelStats),
        ];
    }

    /**
     * 🔄 Force retrain all models
     */
    public function forceRetrainAll(): array
    {
        $results = [];

        foreach ([self::LEARN_TITLE, self::LEARN_DESCRIPTION, self::LEARN_PRICE, 
                  self::LEARN_KEYWORDS, self::LEARN_CATEGORY, self::LEARN_CONVERSION] as $type) {
            $results[$type] = $this->runIncrementalLearning($type);
        }

        return $results;
    }
}
