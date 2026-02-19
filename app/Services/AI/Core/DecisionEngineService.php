<?php

declare(strict_types=1);

namespace App\Services\AI\Core;

use App\Database;
use App\Services\AI\ML\CategoryLearningService;
use App\Services\AI\ML\TrendPredictorService;
use App\Services\AI\SEO\Strategies\SEOStrategiesEngine;
use PDO;

/**
 * 🧠 Decision Engine Service
 * 
 * Motor de decisão que:
 * - Toma decisões baseadas em scoring heurístico (não usa ML)
 * - Analisa múltiplas fontes de dados
 * - Fornece recomendações de ação com pesos configuráveis
 * - Rastreia outcomes para melhoria iterativa
 */
class DecisionEngineService
{
    private PDO $db;
    private int $accountId;

    // Decision types
    public const DECISION_PRICE = 'price';
    public const DECISION_SEO = 'seo';
    public const DECISION_INVENTORY = 'inventory';
    public const DECISION_CAMPAIGN = 'campaign';
    public const DECISION_LISTING = 'listing';

    // Confidence thresholds
    private const CONFIDENCE_AUTO_EXECUTE = 0.85;
    private const CONFIDENCE_RECOMMEND = 0.60;
    private const CONFIDENCE_REVIEW = 0.40;

    // Decision status
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * 🎯 Make a decision
     */
    public function makeDecision(string $decisionType, array $context): array
    {
        $startTime = microtime(true);

        try {
            // Gather intelligence
            $intelligence = $this->gatherIntelligence($decisionType, $context);

            // Analyze with ML models
            $analysis = $this->analyzeWithML($decisionType, $context, $intelligence);

            // Generate decision
            $decision = $this->generateDecision($decisionType, $context, $analysis);

            // Calculate confidence
            $confidence = $this->calculateConfidence($decision, $analysis);

            // Determine action
            $action = $this->determineAction($confidence);

            // Build result
            $result = [
                'success' => true,
                'decision_id' => $this->saveDecision($decisionType, $context, $decision, $confidence),
                'type' => $decisionType,
                'decision' => $decision,
                'confidence' => round($confidence, 3),
                'action' => $action,
                'analysis' => $analysis,
                'reasoning' => $this->generateReasoning($decision, $analysis),
                'execution_time' => round(microtime(true) - $startTime, 3),
            ];

            // Auto-execute if high confidence
            if ($action === 'auto_execute' && ($context['auto_execute'] ?? true)) {
                $result['executed'] = $this->executeDecision($result['decision_id'], $decision);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'type' => $decisionType,
            ];
        }
    }

    /**
     * 📊 Gather intelligence from multiple sources
     */
    private function gatherIntelligence(string $decisionType, array $context): array
    {
        $intelligence = [];

        switch ($decisionType) {
            case self::DECISION_SEO:
                $intelligence = $this->gatherSEOIntelligence($context);
                break;

            case self::DECISION_PRICE:
                $intelligence = $this->gatherPriceIntelligence($context);
                break;

            case self::DECISION_INVENTORY:
                $intelligence = $this->gatherInventoryIntelligence($context);
                break;

            case self::DECISION_LISTING:
                $intelligence = $this->gatherListingIntelligence($context);
                break;

            case self::DECISION_CAMPAIGN:
                $intelligence = $this->gatherCampaignIntelligence($context);
                break;
        }

        return $intelligence;
    }

    /**
     * 🔍 Gather SEO intelligence
     */
    private function gatherSEOIntelligence(array $context): array
    {
        $itemId = $context['item_id'] ?? null;
        $categoryId = $context['category_id'] ?? null;

        $intelligence = [
            'current_score' => null,
            'category_patterns' => null,
            'trending_keywords' => [],
            'competitor_analysis' => null,
        ];

        // Get current SEO score
        if ($itemId) {
            try {
                $engine = new SEOStrategiesEngine($this->accountId);
                $analysis = $engine->analyzeItem($itemId);
                $intelligence['current_score'] = $analysis['overall_score'] ?? null;
                $intelligence['strategies'] = $analysis['strategies'] ?? [];
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Get category patterns
        if ($categoryId) {
            try {
                $categoryService = new CategoryLearningService($this->accountId);
                $learning = $categoryService->getCategoryLearning($categoryId);
                $intelligence['category_patterns'] = $learning['patterns'] ?? null;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Get trending keywords
        if ($categoryId) {
            try {
                $trendService = new TrendPredictorService($this->accountId);
                $rising = $trendService->findRisingKeywords($categoryId, 10);
                $intelligence['trending_keywords'] = $rising;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return $intelligence;
    }

    /**
     * 💰 Gather price intelligence
     */
    private function gatherPriceIntelligence(array $context): array
    {
        $itemId = $context['item_id'] ?? null;
        $categoryId = $context['category_id'] ?? null;

        $intelligence = [
            'current_price' => $context['current_price'] ?? null,
            'cost' => $context['cost'] ?? null,
            'market_avg' => null,
            'competitor_prices' => [],
            'demand_forecast' => null,
        ];

        // Get market data
        if ($categoryId) {
            try {
                $categoryService = new CategoryLearningService($this->accountId);
                $learning = $categoryService->getCategoryLearning($categoryId);
                $priceRanges = $learning['patterns']['price_ranges'] ?? null;
                if ($priceRanges) {
                    $intelligence['market_avg'] = $priceRanges['avg'] ?? null;
                    $intelligence['market_min'] = $priceRanges['min'] ?? null;
                    $intelligence['market_max'] = $priceRanges['max'] ?? null;
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return $intelligence;
    }

    /**
     * 📦 Gather inventory intelligence
     */
    private function gatherInventoryIntelligence(array $context): array
    {
        return [
            'current_stock' => $context['current_stock'] ?? null,
            'sales_velocity' => $context['sales_velocity'] ?? null,
            'days_of_stock' => null,
            'reorder_point' => null,
        ];
    }

    /**
     * 📋 Gather listing intelligence
     */
    private function gatherListingIntelligence(array $context): array
    {
        $categoryId = $context['category_id'] ?? null;

        $intelligence = [
            'category_template' => null,
            'required_attributes' => [],
            'top_keywords' => [],
        ];

        if ($categoryId) {
            try {
                $categoryService = new CategoryLearningService($this->accountId);
                $template = $categoryService->generateOptimizedTemplate($categoryId);
                $intelligence['category_template'] = $template['template'] ?? null;
            } catch (\Exception $e) {
                // Ignore
            }
        }

        return $intelligence;
    }

    /**
     * 📢 Gather campaign intelligence
     */
    private function gatherCampaignIntelligence(array $context): array
    {
        return [
            'budget' => $context['budget'] ?? null,
            'current_roi' => $context['current_roi'] ?? null,
            'market_trends' => [],
        ];
    }

    /**
     * 🤖 Analyze with ML models
     */
    private function analyzeWithML(string $decisionType, array $context, array $intelligence): array
    {
        $analysis = [
            'signals' => [],
            'weights' => [],
            'prediction' => null,
        ];

        switch ($decisionType) {
            case self::DECISION_SEO:
                $analysis = $this->analyzeSEO($context, $intelligence);
                break;

            case self::DECISION_PRICE:
                $analysis = $this->analyzePrice($context, $intelligence);
                break;

            case self::DECISION_INVENTORY:
                $analysis = $this->analyzeInventory($context, $intelligence);
                break;

            case self::DECISION_LISTING:
                $analysis = $this->analyzeListing($context, $intelligence);
                break;
        }

        return $analysis;
    }

    /**
     * 🔍 Analyze SEO decision
     */
    private function analyzeSEO(array $context, array $intelligence): array
    {
        $signals = [];
        $weights = [];

        $currentScore = $intelligence['current_score'] ?? 0;

        // Signal: Current score needs improvement
        if ($currentScore < 70) {
            $signals['low_score'] = [
                'value' => $currentScore,
                'impact' => 'high',
                'action' => 'optimize',
            ];
            $weights['low_score'] = 0.4;
        }

        // Signal: Missing strategy scores
        $strategies = $intelligence['strategies'] ?? [];
        $lowStrategies = array_filter($strategies, fn($s) => ($s['score'] ?? 0) < 50);
        if (!empty($lowStrategies)) {
            $signals['weak_strategies'] = [
                'count' => count($lowStrategies),
                'strategies' => array_keys($lowStrategies),
                'impact' => 'medium',
            ];
            $weights['weak_strategies'] = 0.3;
        }

        // Signal: Trending keywords available
        $trending = $intelligence['trending_keywords'] ?? [];
        if (!empty($trending)) {
            $signals['trending_keywords'] = [
                'count' => count($trending),
                'top' => $trending[0]['keyword'] ?? null,
                'impact' => 'medium',
            ];
            $weights['trending_keywords'] = 0.2;
        }

        // Signal: Category patterns available
        if ($intelligence['category_patterns'] ?? null) {
            $signals['category_patterns'] = [
                'available' => true,
                'impact' => 'low',
            ];
            $weights['category_patterns'] = 0.1;
        }

        return [
            'signals' => $signals,
            'weights' => $weights,
            'prediction' => [
                'potential_improvement' => max(0, 100 - $currentScore) * 0.7,
                'optimization_priority' => $currentScore < 50 ? 'urgent' : ($currentScore < 70 ? 'high' : 'normal'),
            ],
        ];
    }

    /**
     * 💰 Analyze price decision
     */
    private function analyzePrice(array $context, array $intelligence): array
    {
        $signals = [];
        $weights = [];

        $currentPrice = $intelligence['current_price'] ?? 0;
        $marketAvg = $intelligence['market_avg'] ?? 0;
        $cost = $intelligence['cost'] ?? 0;

        // Signal: Price vs market
        if ($marketAvg > 0 && $currentPrice > 0) {
            $priceDiff = (($currentPrice - $marketAvg) / $marketAvg) * 100;
            $signals['market_position'] = [
                'current_vs_avg' => round($priceDiff, 1),
                'position' => $priceDiff > 10 ? 'above' : ($priceDiff < -10 ? 'below' : 'competitive'),
                'impact' => abs($priceDiff) > 20 ? 'high' : 'medium',
            ];
            $weights['market_position'] = 0.4;
        }

        // Signal: Margin analysis
        if ($cost > 0 && $currentPrice > 0) {
            $margin = (($currentPrice - $cost) / $currentPrice) * 100;
            $signals['margin'] = [
                'percentage' => round($margin, 1),
                'healthy' => $margin > 20,
                'impact' => $margin < 15 ? 'high' : 'medium',
            ];
            $weights['margin'] = 0.3;
        }

        return [
            'signals' => $signals,
            'weights' => $weights,
            'prediction' => [
                'suggested_price' => $this->calculateOptimalPrice($intelligence),
                'expected_margin' => $cost > 0 ? round(((($marketAvg ?: $currentPrice) - $cost) / ($marketAvg ?: $currentPrice)) * 100, 1) : null,
            ],
        ];
    }

    /**
     * 📦 Analyze inventory decision
     */
    private function analyzeInventory(array $context, array $intelligence): array
    {
        $signals = [];
        $weights = [];

        $stock = $intelligence['current_stock'] ?? 0;
        $velocity = $intelligence['sales_velocity'] ?? 0;

        if ($velocity > 0) {
            $daysOfStock = $stock / $velocity;
            $signals['stock_days'] = [
                'days' => round($daysOfStock, 1),
                'status' => $daysOfStock < 7 ? 'critical' : ($daysOfStock < 14 ? 'low' : 'healthy'),
                'impact' => $daysOfStock < 7 ? 'high' : 'low',
            ];
            $weights['stock_days'] = 0.5;
        }

        return [
            'signals' => $signals,
            'weights' => $weights,
            'prediction' => [
                'reorder_needed' => ($stock / max(1, $velocity)) < 14,
                'suggested_quantity' => max(0, (int) ceil(30 * $velocity - $stock)),
            ],
        ];
    }

    /**
     * 📋 Analyze listing decision
     */
    private function analyzeListing(array $context, array $intelligence): array
    {
        $signals = [];

        $template = $intelligence['category_template'] ?? null;
        if ($template) {
            $signals['template_available'] = [
                'has_title_template' => isset($template['title']),
                'has_description_template' => isset($template['description']),
                'impact' => 'high',
            ];
        }

        return [
            'signals' => $signals,
            'weights' => ['template_available' => 0.5],
            'prediction' => [
                'optimization_available' => !empty($template),
            ],
        ];
    }

    /**
     * 📐 Calculate optimal price
     */
    private function calculateOptimalPrice(array $intelligence): ?float
    {
        $marketAvg = $intelligence['market_avg'] ?? 0;
        $cost = $intelligence['cost'] ?? 0;
        $current = $intelligence['current_price'] ?? 0;

        if ($marketAvg <= 0) {
            return null;
        }

        // Target: Competitive price with healthy margin
        $targetMargin = 0.25; // 25% margin
        $minPrice = $cost > 0 ? $cost / (1 - $targetMargin) : $marketAvg * 0.8;
        $maxPrice = $marketAvg * 1.1;

        // Optimal is between min and market avg
        $optimal = max($minPrice, min($maxPrice, $marketAvg));

        return round($optimal, 2);
    }

    /**
     * 🎯 Generate decision
     */
    private function generateDecision(string $decisionType, array $context, array $analysis): array
    {
        $decision = [
            'action' => 'no_action',
            'changes' => [],
            'priority' => 'normal',
        ];

        $signals = $analysis['signals'] ?? [];
        $prediction = $analysis['prediction'] ?? [];

        switch ($decisionType) {
            case self::DECISION_SEO:
                if (isset($signals['low_score']) || isset($signals['weak_strategies'])) {
                    $decision['action'] = 'optimize';
                    $decision['priority'] = $prediction['optimization_priority'] ?? 'normal';
                    $decision['changes']['run_full_optimization'] = true;

                    if (isset($signals['weak_strategies'])) {
                        $decision['changes']['focus_strategies'] = $signals['weak_strategies']['strategies'];
                    }
                }
                break;

            case self::DECISION_PRICE:
                $suggestedPrice = $prediction['suggested_price'] ?? null;
                $currentPrice = $context['current_price'] ?? 0;

                if ($suggestedPrice && abs($suggestedPrice - $currentPrice) / max(1, $currentPrice) > 0.05) {
                    $decision['action'] = 'adjust_price';
                    $decision['changes']['new_price'] = $suggestedPrice;
                    $decision['changes']['price_change'] = round($suggestedPrice - $currentPrice, 2);
                    $decision['priority'] = abs($suggestedPrice - $currentPrice) / max(1, $currentPrice) > 0.15 ? 'high' : 'normal';
                }
                break;

            case self::DECISION_INVENTORY:
                if ($prediction['reorder_needed'] ?? false) {
                    $decision['action'] = 'reorder';
                    $decision['changes']['quantity'] = $prediction['suggested_quantity'] ?? 0;
                    $decision['priority'] = ($signals['stock_days']['status'] ?? '') === 'critical' ? 'urgent' : 'high';
                }
                break;

            case self::DECISION_LISTING:
                if ($prediction['optimization_available'] ?? false) {
                    $decision['action'] = 'create_optimized';
                    $decision['changes']['use_template'] = true;
                    $decision['priority'] = 'normal';
                }
                break;
        }

        return $decision;
    }

    /**
     * 📊 Calculate confidence score
     */
    private function calculateConfidence(array $decision, array $analysis): float
    {
        $baseConfidence = 0.5;

        // Increase confidence based on signal count
        $signalCount = count($analysis['signals'] ?? []);
        $baseConfidence += min(0.2, $signalCount * 0.05);

        // Increase confidence based on weight agreement
        $weights = $analysis['weights'] ?? [];
        if (!empty($weights)) {
            $avgWeight = array_sum($weights) / count($weights);
            $baseConfidence += $avgWeight * 0.2;
        }

        // Decrease confidence if action is no_action (uncertain)
        if ($decision['action'] === 'no_action') {
            $baseConfidence *= 0.7;
        }

        return min(1.0, max(0.0, $baseConfidence));
    }

    /**
     * 🎬 Determine action based on confidence
     */
    private function determineAction(float $confidence): string
    {
        if ($confidence >= self::CONFIDENCE_AUTO_EXECUTE) {
            return 'auto_execute';
        }
        if ($confidence >= self::CONFIDENCE_RECOMMEND) {
            return 'recommend';
        }
        if ($confidence >= self::CONFIDENCE_REVIEW) {
            return 'review';
        }
        return 'insufficient_data';
    }

    /**
     * 💡 Generate reasoning explanation
     */
    private function generateReasoning(array $decision, array $analysis): array
    {
        $reasons = [];

        foreach ($analysis['signals'] ?? [] as $signal => $data) {
            $impact = $data['impact'] ?? 'low';
            $reasons[] = [
                'signal' => $signal,
                'impact' => $impact,
                'description' => $this->getSignalDescription($signal, $data),
            ];
        }

        return $reasons;
    }

    /**
     * 📝 Get signal description
     */
    private function getSignalDescription(string $signal, array $data): string
    {
        $descriptions = [
            'low_score' => "SEO score atual ({$data['value']}) precisa de melhoria",
            'weak_strategies' => count($data['strategies'] ?? []) . " estratégias com pontuação baixa",
            'trending_keywords' => "Keywords em alta disponíveis para otimização",
            'category_patterns' => "Padrões da categoria disponíveis para aplicar",
            'market_position' => "Preço está " . ($data['position'] ?? 'na média') . " do mercado",
            'margin' => "Margem atual: " . ($data['percentage'] ?? 0) . "%",
            'stock_days' => "Estoque para " . ($data['days'] ?? 0) . " dias",
        ];

        return $descriptions[$signal] ?? "Sinal: {$signal}";
    }

    /**
     * 💾 Save decision to database
     */
    private function saveDecision(string $type, array $context, array $decision, float $confidence): int
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_decisions (
                    account_id, decision_type, context_json, decision_json,
                    confidence, status, created_at
                ) VALUES (
                    :account_id, :decision_type, :context_json, :decision_json,
                    :confidence, :status, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'decision_type' => $type,
                'context_json' => json_encode($context),
                'decision_json' => json_encode($decision),
                'confidence' => $confidence,
                'status' => self::STATUS_PENDING,
            ]);

            return (int) $this->db->lastInsertId();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * ▶️ Execute a decision
     */
    private function executeDecision(int $decisionId, array $decision): bool
    {
        try {
            // Update status
            $this->updateDecisionStatus($decisionId, self::STATUS_EXECUTED);

            // Execute based on action
            $action = $decision['action'] ?? 'no_action';

            // Here would be the actual execution logic
            // For now, we just mark it as executed

            return true;
        } catch (\Exception $e) {
            $this->updateDecisionStatus($decisionId, self::STATUS_FAILED);
            return false;
        }
    }

    /**
     * Update decision status
     */
    private function updateDecisionStatus(int $decisionId, string $status): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE ai_decisions SET status = :status, executed_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute(['status' => $status, 'id' => $decisionId]);
        } catch (\Exception $e) {
            // Ignore
        }
    }

    /**
     * 📋 Get pending decisions
     */
    public function getPendingDecisions(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, decision_type, context_json, decision_json, confidence, created_at
                FROM ai_decisions
                WHERE account_id = :account_id AND status = :status
                ORDER BY confidence DESC, created_at ASC
            ");
            $stmt->execute(['account_id' => $this->accountId, 'status' => self::STATUS_PENDING]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * ✅ Approve a decision
     */
    public function approveDecision(int $decisionId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT decision_json FROM ai_decisions
                WHERE id = :id AND account_id = :account_id
            ");
            $stmt->execute(['id' => $decisionId, 'account_id' => $this->accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            $decision = json_decode($row['decision_json'], true) ?: [];
            $this->updateDecisionStatus($decisionId, self::STATUS_APPROVED);

            return $this->executeDecision($decisionId, $decision);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * ❌ Reject a decision
     */
    public function rejectDecision(int $decisionId, ?string $reason = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE ai_decisions
                SET status = :status, rejection_reason = :reason, executed_at = NOW()
                WHERE id = :id AND account_id = :account_id
            ");

            return $stmt->execute([
                'status' => self::STATUS_REJECTED,
                'reason' => $reason,
                'id' => $decisionId,
                'account_id' => $this->accountId,
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * 📊 Get decision stats
     */
    public function getStats(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    decision_type,
                    status,
                    COUNT(*) as count,
                    AVG(confidence) as avg_confidence
                FROM ai_decisions
                WHERE account_id = :account_id
                AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY decision_type, status
            ");
            $stmt->execute(['account_id' => $this->accountId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
}
