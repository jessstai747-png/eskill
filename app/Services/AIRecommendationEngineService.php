<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * Sistema de Recomendações Inteligentes por IA
 *
 * Engine avançada de recomendações para o ecossistema ML:
 * - Recomendações de produtos para clonagem
 * - Sugestões de preços dinâmicas
 * - Oportunidades de mercado
 * - Produtos complementares
 * - Estratégias de cross-selling
 * - Análise preditiva de tendências
 * - Personalização por perfil de vendedor
 *
 * @author Sistema ML Manager V8.0
 * @version 8.0.0
 */
class AIRecommendationEngineService
{
    private \PDO $db;
    private LogService $logger;
    private CacheManagerService $cache;
    private ?MercadoLivreClient $mlClient = null;
    private array $recommendationModels;
    private array $userProfiles;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new LogService();
        $this->cache = new CacheManagerService();
        try {
            $this->mlClient = new MercadoLivreClient();
        } catch (\Throwable $e) {
            // Ignore for now or log
        }
        $this->initializeRecommendationModels();
        $this->loadUserProfiles();
    }

    // ========== RECOMENDAÇÕES PRINCIPAIS ==========

    /**
     * Gera recomendações personalizadas para o usuário
     */
    public function getPersonalizedRecommendations(int $userId, array $options = []): array
    {
        try {
            $cacheKey = 'ai_recommendations_' . $userId . '_' . md5(json_encode($options));
            $cached = $this->cache->get($cacheKey, 'ai_recommendations');
            if ($cached && !($options['force_refresh'] ?? false)) {
                return $cached;
            }

            // Perfil do usuário
            $userProfile = $this->buildUserProfile($userId);

            // Contexto atual
            $context = $this->gatherContext($userId);

            // Diferentes tipos de recomendações
            $recommendations = [
                'products_to_clone' => $this->recommendProductsToClone($userProfile, $context),
                'pricing_opportunities' => $this->recommendPricingStrategies($userProfile, $context),
                'market_opportunities' => $this->recommendMarketOpportunities($userProfile, $context),
                'category_expansion' => $this->recommendCategoryExpansion($userProfile, $context),
                'competitive_strategies' => $this->recommendCompetitiveStrategies($userProfile, $context),
                'optimization_actions' => $this->recommendOptimizations($userProfile, $context),
                'trending_products' => $this->recommendTrendingProducts($userProfile, $context),
                'seasonal_opportunities' => $this->recommendSeasonalOpportunities($userProfile, $context)
            ];

            // Score e priorização
            $prioritized = $this->prioritizeRecommendations($recommendations, $userProfile);

            // Explicações e insights
            $explained = $this->addExplanations($prioritized, $userProfile, $context);

            // Métricas de confiança
            $confidence = $this->calculateRecommendationConfidence($explained, $userProfile);

            $result = [
                'success' => true,
                'user_id' => $userId,
                'user_profile_summary' => $this->summarizeUserProfile($userProfile),
                'recommendations' => $explained,
                'confidence_metrics' => $confidence,
                'market_context' => $context['market_summary'],
                'personalization_factors' => $this->getPersonalizationFactors($userProfile),
                'next_analysis_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // Cache por 6 horas
            $this->cache->set($cacheKey, $result, 'ai_recommendations', 21600);

            // Log das recomendações
            $this->logger->info('AI recommendations generated', [
                'user_id' => $userId,
                'total_recommendations' => array_sum(array_map('count', $recommendations)),
                'confidence_avg' => $confidence['average_confidence']
            ]);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('AI recommendation generation failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return $this->createErrorResponse($e->getMessage(), $userId);
        }
    }

    /**
     * Recomenda produtos específicos para clonagem
     */
    public function recommendProductsToClone(array $userProfile, array $context, int $limit = 10): array
    {
        try {
            // Análise de categorias do usuário
            $userCategories = $userProfile['preferred_categories'] ?? [];
            $userBrands = $userProfile['preferred_brands'] ?? [];

            // Buscar produtos com potencial
            $candidates = $this->findCloneCandidates($userCategories, $userBrands, $context);

            // Análise de cada candidato
            $analyzedProducts = [];
            foreach ($candidates as $product) {
                $analysis = $this->analyzeClonePotential($product, $userProfile, $context);
                if ($analysis['score'] > 70) {
                    $analyzedProducts[] = array_merge($product, $analysis);
                }
            }

            // Ordenar por score e limitar
            usort($analyzedProducts, function ($a, $b) {
                return $b['score'] <=> $a['score'];
            });

            $recommendations = array_slice($analyzedProducts, 0, $limit);

            // Adicionar insights específicos
            foreach ($recommendations as &$rec) {
                $rec['why_recommended'] = $this->explainCloneRecommendation($rec, $userProfile);
                $rec['expected_performance'] = $this->predictClonePerformance($rec, $userProfile);
                $rec['risk_factors'] = $this->identifyCloneRisks($rec, $userProfile);
                $rec['action_plan'] = $this->generateCloneActionPlan($rec);
            }

            return $recommendations;
        } catch (Exception $e) {
            $this->logger->error('Clone recommendations failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Recomenda oportunidades de mercado
     */
    public function recommendMarketOpportunities(array $userProfile, array $context): array
    {
        $opportunities = [];

        // Análise de gaps de mercado
        $gaps = $this->identifyMarketGaps($userProfile, $context);
        foreach ($gaps as $gap) {
            $opportunities[] = [
                'type' => 'market_gap',
                'category' => $gap['category'],
                'opportunity' => $gap['description'],
                'potential_revenue' => $gap['estimated_revenue'],
                'competition_level' => $gap['competition'],
                'entry_difficulty' => $gap['difficulty'],
                'score' => $gap['score'],
                'timeline' => $gap['recommended_timeline'],
                'requirements' => $gap['requirements']
            ];
        }

        // Produtos em alta demanda
        $trending = $this->identifyTrendingProducts($context);
        foreach ($trending as $trend) {
            $opportunities[] = [
                'type' => 'trending_product',
                'product_type' => $trend['type'],
                'trend_strength' => $trend['strength'],
                'growth_rate' => $trend['growth_rate'],
                'saturation_level' => $trend['saturation'],
                'score' => $trend['score'],
                'seasonal_factor' => $trend['seasonal'],
                'recommended_action' => $trend['action']
            ];
        }

        // Nichos subestimados
        $niches = $this->identifyUndersuppliedNiches($userProfile, $context);
        foreach ($niches as $niche) {
            $opportunities[] = [
                'type' => 'undersupplied_niche',
                'niche_name' => $niche['name'],
                'supply_demand_ratio' => $niche['ratio'],
                'average_price' => $niche['avg_price'],
                'profit_margin_potential' => $niche['margin'],
                'score' => $niche['score'],
                'target_audience' => $niche['audience'],
                'key_success_factors' => $niche['success_factors']
            ];
        }

        // Ordenar por score
        usort($opportunities, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($opportunities, 0, 15);
    }

    /**
     * Recomenda estratégias de preço
     */
    public function recommendPricingStrategies(array $userProfile, array $context): array
    {
        $strategies = [];

        // Análise de produtos do usuário
        $userProducts = $this->getUserProducts($userProfile['user_id']);

        foreach ($userProducts as $product) {
            $priceAnalysis = $this->analyzePricingOpportunity($product, $context);

            if ($priceAnalysis['has_opportunity']) {
                $strategies[] = [
                    'product_id' => $product['id'],
                    'product_title' => $product['title'],
                    'current_price' => $product['price'],
                    'recommended_price' => $priceAnalysis['recommended_price'],
                    'strategy_type' => $priceAnalysis['strategy'],
                    'expected_impact' => $priceAnalysis['expected_impact'],
                    'confidence' => $priceAnalysis['confidence'],
                    'reasoning' => $priceAnalysis['reasoning'],
                    'competitive_position' => $priceAnalysis['position'],
                    'market_conditions' => $priceAnalysis['market_conditions'],
                    'risk_level' => $priceAnalysis['risk'],
                    'implementation_priority' => $priceAnalysis['priority']
                ];
            }
        }

        // Ordenar por impacto esperado
        usort($strategies, function ($a, $b) {
            return $b['expected_impact'] <=> $a['expected_impact'];
        });

        return array_slice($strategies, 0, 10);
    }

    // ========== ANÁLISE DE PERFIL E CONTEXTO ==========

    /**
     * Constrói perfil detalhado do usuário
     */
    /**
     * Constrói perfil detalhado do usuário (Real Implementation)
     */
    private function buildUserProfile(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $createdAt = $user['created_at'] ?? date('Y-m-d H:i:s');
        $priceRange = $this->getPriceRangePreference($userId);
        $performanceMetrics = $this->getPerformanceMetrics($userId);

        return [
            'user_id' => $userId,
            'account_age' => $this->calculateAccountAgeFromDate($createdAt),
            'selling_experience' => $this->calculateSellingExperience($userId),
            'preferred_categories' => $this->getPreferredCategories($userId),
            'preferred_brands' => $this->getPreferredBrands($userId),
            'price_range_preference' => $priceRange,
            'performance_metrics' => $performanceMetrics,
            'behavioral_patterns' => [],
            'risk_tolerance' => $this->assessRiskTolerance($userId),
            'growth_stage' => $this->determineGrowthStage($userId)
        ];
    }

    private function calculateAccountAgeFromDate(string $date): int
    {
        $created = new \DateTime($date);
        $now = new \DateTime();
        return $now->diff($created)->m + ($now->diff($created)->y * 12);
    }

    private function resolveAccountId(int $userId): ?int
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM ml_accounts WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Coleta contexto de mercado atual
     */
    private function gatherContext(int $userId): array
    {
        $accountId = $this->resolveAccountId($userId);
        $context = [
            'account_id' => $accountId,
            'current_date' => date('Y-m-d'),
            'season' => $this->getCurrentSeason(),
            'market_trends' => $this->getCurrentMarketTrends(),
            'economic_indicators' => $this->getEconomicIndicators(),
            'competitive_landscape' => $this->getCompetitiveLandscape($userId),
            'platform_changes' => $this->getRecentPlatformChanges(),
            'user_location_trends' => $this->getLocationBasedTrends($userId),
            'market_summary' => []
        ];

        // Gerar resumo do contexto
        $context['market_summary'] = $this->generateMarketSummary($context);

        return $context;
    }

    // ========== ALGORITMOS DE RECOMENDAÇÃO ==========

    /**
     * Analisa potencial de clonagem de produto
     */
    private function analyzeClonePotential(array $product, array $userProfile, array $context): array
    {
        $score = 0;
        $factors = [];

        // Demanda vs Oferta
        $demandSupplyRatio = $this->calculateDemandSupplyRatio($product, $context);
        $score += min(25, $demandSupplyRatio * 25);
        $factors['demand_supply'] = $demandSupplyRatio;

        // Margem de lucro potencial
        $profitMargin = $this->calculatePotentialMargin($product, $userProfile);
        $score += min(20, $profitMargin * 20);
        $factors['profit_margin'] = $profitMargin;

        // Adequação ao perfil do usuário
        $profileFit = $this->calculateProfileFit($product, $userProfile);
        $score += min(20, $profileFit * 20);
        $factors['profile_fit'] = $profileFit;

        // Tendência de mercado
        $trendScore = $this->calculateTrendScore($product, $context);
        $score += min(15, $trendScore * 15);
        $factors['trend_score'] = $trendScore;

        // Competitividade
        $competitiveness = $this->calculateCompetitiveness($product);
        $score += min(10, (1 - $competitiveness) * 10);
        $factors['competitiveness'] = $competitiveness;

        // Facilidade de clonagem
        $cloneEase = $this->calculateCloneEase($product);
        $score += min(10, $cloneEase * 10);
        $factors['clone_ease'] = $cloneEase;

        return [
            'score' => round($score, 2),
            'factors' => $factors,
            'recommendation_strength' => $this->getRecommendationStrength($score)
        ];
    }

    /**
     * Prioriza recomendações por relevância
     */
    private function prioritizeRecommendations(array $recommendations, array $userProfile): array
    {
        $prioritized = [];

        foreach ($recommendations as $type => $items) {
            if (empty($items)) continue;

            $typeWeight = $this->getTypeWeight($type, $userProfile);

            foreach ($items as &$item) {
                $item['priority_score'] = ($item['score'] ?? 0) * $typeWeight;
                $item['recommendation_type'] = $type;
            }

            $prioritized[$type] = $items;
        }

        return $prioritized;
    }

    // ========== EXPLICAÇÕES E INSIGHTS ==========

    /**
     * Adiciona explicações às recomendações
     */
    private function addExplanations(array $recommendations, array $userProfile, array $context): array
    {
        $explained = [];

        foreach ($recommendations as $type => $items) {
            $explained[$type] = [];

            foreach ($items as $item) {
                $explanation = $this->generateExplanation($item, $type, $userProfile, $context);
                $item['explanation'] = $explanation;
                $item['confidence_factors'] = $this->getConfidenceFactors($item, $type);
                $item['implementation_difficulty'] = $this->assessImplementationDifficulty($item, $type);

                $explained[$type][] = $item;
            }
        }

        return $explained;
    }

    /**
     * Gera explicação para uma recomendação
     */
    private function generateExplanation(array $item, string $type, array $userProfile, array $context): array
    {
        $explanation = [
            'why_recommended' => '',
            'key_factors' => [],
            'expected_outcome' => '',
            'success_probability' => 0,
            'alternative_options' => []
        ];

        switch ($type) {
            case 'products_to_clone':
                $explanation['why_recommended'] = $this->explainCloneRecommendation($item, $userProfile);
                break;

            case 'pricing_opportunities':
                $explanation['why_recommended'] = $this->explainPricingRecommendation($item, $userProfile);
                break;

            case 'market_opportunities':
                $explanation['why_recommended'] = $this->explainMarketRecommendation($item, $userProfile);
                break;

            default:
                $explanation['why_recommended'] = 'Recomendação baseada em análise de IA';
        }

        return $explanation;
    }

    // ========== INICIALIZAÇÃO ==========

    /**
     * Inicializa modelos de recomendação
     */
    private function initializeRecommendationModels(): void
    {
        $this->recommendationModels = [
            'collaborative_filtering' => [
                'enabled' => true,
                'weight' => 0.3,
                'min_data_points' => 10
            ],
            'content_based' => [
                'enabled' => true,
                'weight' => 0.25,
                'similarity_threshold' => 0.7
            ],
            'market_analysis' => [
                'enabled' => true,
                'weight' => 0.25,
                'trend_window_days' => 30
            ],
            'behavior_prediction' => [
                'enabled' => true,
                'weight' => 0.2,
                'prediction_accuracy' => 0.75
            ]
        ];
    }

    /**
     * Carrega perfis de usuários
     */
    private function loadUserProfiles(): void
    {
        // Cache de perfis para otimização
        $this->userProfiles = [];
    }

    // ========== MÉTODOS AUXILIARES ==========

    private function createErrorResponse(string $message, int $userId): array
    {
        return [
            'success' => false,
            'error' => $message,
            'user_id' => $userId,
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    private function recommendCategoryExpansion($profile, $context): array
    {
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        $userCategories = $profile['preferred_categories'] ?? [];
        $stmt = $this->db->query("
            SELECT category_id, COUNT(*) as total_items
            FROM items
            WHERE category_id IS NOT NULL AND category_id != ''
            GROUP BY category_id
            ORDER BY total_items DESC
            LIMIT 20
        ");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (empty($rows)) {
            return [];
        }
        $max = max(array_column($rows, 'total_items'));
        $recommendations = [];
        foreach ($rows as $row) {
            if (in_array($row['category_id'], $userCategories, true)) {
                continue;
            }
            $score = $max > 0 ? ($row['total_items'] / $max) * 100 : 0;
            $recommendations[] = [
                'category' => $row['category_id'],
                'score' => round($score, 2),
                'total_items' => (int)$row['total_items']
            ];
        }
        return array_slice($recommendations, 0, 5);
    }
    private function recommendCompetitiveStrategies($profile, $context): array
    {
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        try {
            $stmt = $this->db->prepare("
                SELECT
                    SUM(CASE WHEN competitor_price < my_price THEN 1 ELSE 0 END) as cheaper_count,
                    COUNT(*) as total_count
                FROM competitor_tracking
                WHERE account_id = ?
            ");
            $stmt->execute([$accountId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $total = (int)($row['total_count'] ?? 0);
            if ($total === 0) {
                return [];
            }
            $cheaper = (int)($row['cheaper_count'] ?? 0);
            $ratio = $total > 0 ? $cheaper / $total : 0;
            $strategies = [];
            if ($ratio > 0.3) {
                $strategies[] = ['strategy' => 'price_optimization', 'score' => round($ratio * 100, 2)];
            }
            if ($ratio <= 0.1) {
                $strategies[] = ['strategy' => 'value_positioning', 'score' => round((1 - $ratio) * 100, 2)];
            }
            return $strategies;
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function recommendOptimizations($profile, $context): array
    {
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        try {
            $stmt = $this->db->prepare("
                SELECT item_id, overall_score
                FROM seo_scores
                WHERE account_id = ?
                ORDER BY overall_score ASC
                LIMIT 5
            ");
            $stmt->execute([$accountId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $actions = [];
            foreach ($rows as $row) {
                $score = (float)($row['overall_score'] ?? 0);
                $actions[] = [
                    'action' => 'improve_seo',
                    'item_id' => $row['item_id'],
                    'score' => round(100 - $score, 2)
                ];
            }
            return $actions;
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function recommendTrendingProducts($profile, $context): array
    {
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        try {
            $stmt = $this->db->prepare("
                SELECT imh.item_id, i.title, SUM(imh.sold_quantity) as total_sold
                FROM item_metrics_history imh
                JOIN items i ON i.ml_item_id = imh.item_id
                WHERE i.account_id = :account_id
                  AND imh.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY imh.item_id, i.title
                ORDER BY total_sold DESC
                LIMIT 5
            ");
            $stmt->execute(['account_id' => $accountId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($row) => [
                'product' => $row['title'],
                'item_id' => $row['item_id'],
                'score' => (float)($row['total_sold'] ?? 0)
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function recommendSeasonalOpportunities($profile, $context): array
    {
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        $season = $context['season'] ?? null;
        try {
            $stmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as total_orders
                FROM ml_orders
                WHERE ml_account_id = :account_id
                  AND date_created >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                GROUP BY category_id
                ORDER BY total_orders DESC
                LIMIT 5
            ");
            $stmt->execute(['account_id' => $accountId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return array_map(fn($row) => [
                'season' => $season,
                'category' => $row['category_id'],
                'score' => (int)($row['total_orders'] ?? 0)
            ], $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function calculateRecommendationConfidence($recommendations, $profile): array
    {
        $scores = [];
        foreach ($recommendations as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $item) {
                if (isset($item['score'])) {
                    $scores[] = (float)$item['score'];
                } elseif (isset($item['expected_impact'])) {
                    $scores[] = (float)$item['expected_impact'];
                }
            }
        }
        if (empty($scores)) {
            return ['average_confidence' => 0, 'min_confidence' => 0];
        }
        $avg = array_sum($scores) / count($scores);
        return ['average_confidence' => round($avg / 100, 2), 'min_confidence' => round(min($scores) / 100, 2)];
    }
    private function summarizeUserProfile($profile): array
    {
        return [
            'experience' => $profile['selling_experience'] ?? null,
            'categories' => $profile['preferred_categories'] ?? [],
            'price_range' => $profile['price_range_preference'] ?? []
        ];
    }
    private function getPersonalizationFactors($profile): array
    {
        $factors = [];
        if (!empty($profile['selling_experience'])) $factors[] = 'selling_experience';
        if (!empty($profile['preferred_categories'])) $factors[] = 'preferred_categories';
        if (!empty($profile['preferred_brands'])) $factors[] = 'preferred_brands';
        if (!empty($profile['price_range_preference'])) $factors[] = 'price_range_preference';
        if (!empty($profile['risk_tolerance'])) $factors[] = 'risk_tolerance';
        return $factors;
    }
    private function findCloneCandidates($categories, $brands, $context): array
    {
        // Fetch real competitor items
        $accountId = $context['account_id'] ?? null;
        if (!$accountId) {
            return [];
        }
        $conditions = ["account_id = :account_id"];
        $params = ['account_id' => $accountId];
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $conditions[] = "category_id IN ({$placeholders})";
            $params = array_merge($params, $categories);
        }
        $sql = "SELECT ml_item_id as id, title, price, permalink, category_id, sold_quantity, available_quantity
                FROM competitor_items
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY sold_quantity DESC
                LIMIT 10";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($params));
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!empty($brands)) {
            $items = array_filter($items, function ($item) use ($brands) {
                foreach ($brands as $brand) {
                    if (stripos($item['title'] ?? '', $brand) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }
        return array_values($items);
    }
    private function explainCloneRecommendation($item, $profile): string
    {
        $score = $item['score'] ?? 0;
        if ($score >= 85) {
            return 'Alta demanda e baixa concorrência identificadas';
        }
        if ($score >= 70) {
            return 'Potencial consistente com seu portfólio atual';
        }
        return 'Oportunidade moderada com necessidade de ajuste de preço';
    }
    private function predictClonePerformance($item, $profile): array
    {
        $expectedSales = (int)($item['sold_quantity'] ?? 0);
        $price = (float)($item['price'] ?? 0);
        $revenue = $expectedSales * $price;
        return [
            'expected_sales' => $expectedSales,
            'expected_revenue' => round($revenue, 2),
            'roi' => $revenue > 0 ? round(($revenue * 0.2), 2) : 0
        ];
    }
    private function identifyCloneRisks($item, $profile): array
    {
        $categoryId = $item['category_id'] ?? null;
        $competition = 'unknown';
        if ($categoryId) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM competitor_items WHERE category_id = ?");
            $stmt->execute([$categoryId]);
            $count = (int)$stmt->fetchColumn();
            $competition = $count > 100 ? 'high' : ($count > 30 ? 'medium' : 'low');
        }
        return [
            'competition' => $competition,
            'stock' => (int)($item['available_quantity'] ?? 0) > 0 ? 'available' : 'unknown'
        ];
    }
    private function generateCloneActionPlan($item): array
    {
        $steps = ['Validar margem e custo logístico', 'Comparar preços com concorrentes'];
        if (!empty($item['category_id'])) {
            $steps[] = 'Revisar atributos da categoria';
        }
        $steps[] = 'Preparar anúncio com SEO completo';
        return ['steps' => $steps];
    }
    private function identifyMarketGaps($profile, $context): array
    {
        try {
            $accountId = $context['account_id'] ?? null;
            if (!$accountId) {
                return [];
            }
            $stmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as demand
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY category_id
                ORDER BY demand DESC
                LIMIT 20
            ");
            $stmt->execute(['account_id' => $accountId]);
            $demandRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($demandRows)) {
                return [];
            }
            $categories = array_column($demandRows, 'category_id');
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $supplyStmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as supply
                FROM items
                WHERE category_id IN ({$placeholders})
                GROUP BY category_id
            ");
            $supplyStmt->execute($categories);
            $supplyRows = $supplyStmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $gaps = [];
            foreach ($demandRows as $row) {
                $categoryId = $row['category_id'];
                $demand = (int)$row['demand'];
                $supply = (int)($supplyRows[$categoryId] ?? 0);
                if ($supply === 0) {
                    continue;
                }
                $ratio = $supply > 0 ? $demand / $supply : 0;
                if ($ratio < 2) {
                    continue;
                }
                $gaps[] = [
                    'category' => $categoryId,
                    'description' => 'Demanda acima da oferta',
                    'estimated_revenue' => $demand * 10,
                    'competition' => $ratio > 5 ? 'low' : 'medium',
                    'difficulty' => $ratio > 5 ? 'low' : 'medium',
                    'score' => round(min(100, $ratio * 10), 2),
                    'recommended_timeline' => '30-60 dias',
                    'requirements' => ['Capital inicial', 'Pesquisa de fornecedores']
                ];
            }
            return $gaps;
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function identifyTrendingProducts($context): array
    {
        $stmt = $this->db->prepare("SELECT title, price, sold_quantity FROM competitor_items ORDER BY sold_quantity DESC LIMIT 5");
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $trends = [];
        foreach ($items as $item) {
            $trends[] = [
                'type' => substr($item['title'], 0, 30) . '...',
                'strength' => ($item['sold_quantity'] ?? 0) > 50 ? 'high' : 'medium',
                'growth_rate' => null,
                'saturation' => 'unknown',
                'score' => (float)($item['sold_quantity'] ?? 0),
                'seasonal' => null,
                'action' => 'Monitorar concorrência'
            ];
        }

        return $trends;
    }
    private function identifyUndersuppliedNiches($profile, $context): array
    {
        try {
            $accountId = $context['account_id'] ?? null;
            if (!$accountId) {
                return [];
            }
            $stmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as demand
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY category_id
                ORDER BY demand DESC
                LIMIT 20
            ");
            $stmt->execute(['account_id' => $accountId]);
            $demandRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (empty($demandRows)) {
                return [];
            }
            $categories = array_column($demandRows, 'category_id');
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $supplyStmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as supply, AVG(price) as avg_price
                FROM items
                WHERE category_id IN ({$placeholders})
                GROUP BY category_id
            ");
            $supplyStmt->execute($categories);
            $supplyRows = $supplyStmt->fetchAll(\PDO::FETCH_ASSOC);
            $supplyIndex = [];
            foreach ($supplyRows as $row) {
                $supplyIndex[$row['category_id']] = $row;
            }
            $niches = [];
            foreach ($demandRows as $row) {
                $categoryId = $row['category_id'];
                if (!isset($supplyIndex[$categoryId])) {
                    continue;
                }
                $supply = (int)($supplyIndex[$categoryId]['supply'] ?? 0);
                if ($supply <= 0) {
                    continue;
                }
                $ratio = $row['demand'] / $supply;
                if ($ratio < 1.5) {
                    continue;
                }
                $niches[] = [
                    'name' => $categoryId,
                    'ratio' => round($ratio, 2),
                    'avg_price' => round((float)($supplyIndex[$categoryId]['avg_price'] ?? 0), 2),
                    'margin' => null,
                    'score' => round(min(100, $ratio * 20), 2),
                    'audience' => null,
                    'success_factors' => ['Preço competitivo', 'SEO otimizado']
                ];
            }
            return $niches;
        } catch (\Throwable $e) {
            return [];
        }
    }
    private function getUserProducts($userId): array
    {
        $stmt = $this->db->prepare("
            SELECT ml_item_id as id, title, price, category_id
            FROM items
            WHERE account_id = :account_id
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $accountId = $this->resolveAccountId($userId);
        if (!$accountId) {
            return [];
        }
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    private function analyzePricingOpportunity($product, $context): array
    {
        $categoryId = $product['category_id'] ?? null;
        $price = (float)($product['price'] ?? 0);
        if (!$categoryId || $price <= 0) {
            return ['has_opportunity' => false];
        }
        $stmt = $this->db->prepare("SELECT AVG(price) FROM items WHERE category_id = ?");
        $stmt->execute([$categoryId]);
        $avg = (float)($stmt->fetchColumn() ?: 0);
        if ($avg <= 0) {
            return ['has_opportunity' => false];
        }
        $diff = ($avg - $price) / $avg;
        $strategy = $diff > 0.1 ? 'increase_price' : ($diff < -0.1 ? 'decrease_price' : 'hold_price');
        $impact = abs($diff) * 100;
        return [
            'has_opportunity' => $strategy !== 'hold_price',
            'recommended_price' => round($avg, 2),
            'strategy' => $strategy,
            'expected_impact' => round($impact, 2),
            'confidence' => round(min(1, $impact / 100), 2),
            'reasoning' => 'Baseado no preço médio da categoria',
            'position' => $diff > 0 ? 'underpriced' : 'overpriced',
            'market_conditions' => 'neutral',
            'risk' => $impact > 20 ? 'medium' : 'low',
            'priority' => $impact > 20 ? 'high' : 'medium'
        ];
    }

    // ========== MÉTODOS DE ANÁLISE DE PERFIL (IMPLEMENTAÇÃO REAL) ==========

    /**
     * Calcula nível de experiência do vendedor baseado em dados reais
     */
    private function calculateSellingExperience($userId): string
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return 'beginner';
            }
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_orders,
                    MIN(date_created) as first_order,
                    DATEDIFF(NOW(), MIN(date_created)) as days_active
                FROM ml_orders
                WHERE ml_account_id = :account_id AND status = 'completed'
            ");
            $stmt->execute(['account_id' => $accountId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalOrders = (int)($data['total_orders'] ?? 0);
            $daysActive = (int)($data['days_active'] ?? 0);

            // Lógica: experiência baseada em vendas e tempo ativo
            if ($totalOrders > 1000 && $daysActive > 365) {
                return 'advanced';
            } elseif ($totalOrders > 100 || $daysActive > 180) {
                return 'intermediate';
            } else {
                return 'beginner';
            }
        } catch (\Throwable $e) {
            return 'beginner';
        }
    }

    /**
     * Obtém categorias preferidas do vendedor baseado em histórico real
     */
    private function getPreferredCategories($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return [];
            }
            $stmt = $this->db->prepare("
                SELECT category_id, COUNT(*) as count
                FROM items
                WHERE account_id = :account_id AND status = 'active'
                GROUP BY category_id
                ORDER BY count DESC
                LIMIT 5
            ");
            $stmt->execute(['account_id' => $accountId]);
            $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            return !empty($results) ? $results : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Obtém marcas preferidas do vendedor baseado em histórico real
     */
    private function getPreferredBrands($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return [];
            }
            $stmt = $this->db->prepare("
                SELECT
                    JSON_UNQUOTE(JSON_EXTRACT(attributes, '$[*].value_name')) as brand
                FROM items
                WHERE account_id = :account_id
                AND JSON_SEARCH(attributes, 'one', 'BRAND') IS NOT NULL
                AND status = 'active'
                GROUP BY brand
                ORDER BY COUNT(*) DESC
                LIMIT 5
            ");
            $stmt->execute(['account_id' => $accountId]);
            $results = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            // Filtrar nulls e valores vazios
            $brands = array_filter($results, fn($b) => !empty($b) && $b !== 'null');

            return !empty($brands) ? array_values($brands) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Obtém faixa de preço preferida baseada em produtos ativos
     */
    private function getPriceRangePreference($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return ['min' => 0, 'max' => 0, 'average' => 0, 'typical_range' => ['low' => 0, 'high' => 0]];
            }
            $stmt = $this->db->prepare("
                SELECT
                    MIN(price) as min_price,
                    MAX(price) as max_price,
                    AVG(price) as avg_price,
                    STDDEV(price) as std_price
                FROM items
                WHERE account_id = :account_id AND status = 'active' AND price > 0
            ");
            $stmt->execute(['account_id' => $accountId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $min = (float)($data['min_price'] ?? 0);
            $max = (float)($data['max_price'] ?? 0);
            $avg = (float)($data['avg_price'] ?? 0);

            return [
                'min' => round($min, 2),
                'max' => round($max, 2),
                'average' => round($avg, 2),
                'typical_range' => [
                    'low' => round($avg * 0.5, 2),
                    'high' => round($avg * 1.5, 2)
                ]
            ];
        } catch (\Throwable $e) {
            return ['min' => 0, 'max' => 0, 'average' => 0, 'typical_range' => ['low' => 0, 'high' => 0]];
        }
    }

    /**
     * Obtém métricas de performance reais do vendedor
     */
    private function getPerformanceMetrics($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return ['total_sales_90d' => 0, 'total_revenue_90d' => 0, 'avg_order_value' => 0, 'avg_rating' => 0, 'total_ratings' => 0];
            }
            // Métricas de vendas
            $salesStmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND status = 'completed'
                AND date_created >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $salesStmt->execute(['account_id' => $accountId]);
            $sales = $salesStmt->fetch(\PDO::FETCH_ASSOC);

            // Métricas de reputação (se disponível)
            $repStmt = $this->db->prepare("
                SELECT
                    positive_ratings,
                    negative_ratings,
                    neutral_ratings
                FROM seller_reputation
                WHERE account_id = :account_id
                ORDER BY updated_at DESC
                LIMIT 1
            ");
            $repStmt->execute(['account_id' => $accountId]);
            $reputation = $repStmt->fetch(\PDO::FETCH_ASSOC);

            $totalRatings = 0;
            $avgRating = 0;
            if ($reputation) {
                $positive = (int)($reputation['positive_ratings'] ?? 0);
                $negative = (int)($reputation['negative_ratings'] ?? 0);
                $neutral = (int)($reputation['neutral_ratings'] ?? 0);
                $totalRatings = $positive + $negative + $neutral;

                if ($totalRatings > 0) {
                    // Calcular rating médio (positivo = 5, neutro = 3, negativo = 1)
                    $avgRating = (($positive * 5) + ($neutral * 3) + ($negative * 1)) / $totalRatings;
                }
            }

            return [
                'total_sales_90d' => (int)($sales['total_orders'] ?? 0),
                'total_revenue_90d' => round((float)($sales['total_revenue'] ?? 0), 2),
                'avg_order_value' => round((float)($sales['avg_order_value'] ?? 0), 2),
                'avg_rating' => round($avgRating, 2),
                'total_ratings' => $totalRatings
            ];
        } catch (\Throwable $e) {
            return ['total_sales_90d' => 0, 'total_revenue_90d' => 0, 'avg_order_value' => 0, 'avg_rating' => 0, 'total_ratings' => 0];
        }
    }

    /**
     * Analisa padrões comportamentais do vendedor
     */
    private function analyzeBehavioralPatterns($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return ['active_hours' => null, 'response_time' => 'unknown', 'avg_response_minutes' => 0];
            }
            $hoursStmt = $this->db->prepare("
                SELECT HOUR(updated_at) as hour, COUNT(*) as activity
                FROM items
                WHERE account_id = :account_id
                AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY HOUR(updated_at)
                ORDER BY activity DESC
                LIMIT 3
            ");
            $hoursStmt->execute(['account_id' => $accountId]);
            $hours = $hoursStmt->fetchAll(\PDO::FETCH_COLUMN);

            // Tempo médio de resposta a perguntas
            $responseStmt = $this->db->prepare("
                SELECT AVG(TIMESTAMPDIFF(MINUTE, date_created, answer_date)) as avg_response_minutes
                FROM ml_questions
                WHERE account_id = :account_id
                AND answer_date IS NOT NULL
                AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $responseStmt->execute(['account_id' => $accountId]);
            $avgResponse = (float)$responseStmt->fetchColumn();

            // Classificar tempo de resposta
            $responseSpeed = 'unknown';
            if ($avgResponse > 0) {
                if ($avgResponse < 60) {
                    $responseSpeed = 'very_fast';
                } elseif ($avgResponse < 240) {
                    $responseSpeed = 'fast';
                } elseif ($avgResponse < 720) {
                    $responseSpeed = 'moderate';
                } else {
                    $responseSpeed = 'slow';
                }
            }

            return [
                'active_hours' => !empty($hours) ? implode(', ', $hours) . 'h' : null,
                'response_time' => $responseSpeed,
                'avg_response_minutes' => round($avgResponse, 0)
            ];
        } catch (\Throwable $e) {
            return ['active_hours' => null, 'response_time' => 'unknown', 'avg_response_minutes' => 0];
        }
    }

    /**
     * Avalia tolerância a risco baseado em comportamento real
     */
    private function assessRiskTolerance($userId): string
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return 'unknown';
            }
            $diversityStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT category_id) as categories
                FROM items
                WHERE account_id = :account_id AND status = 'active'
            ");
            $diversityStmt->execute(['account_id' => $accountId]);
            $categories = (int)$diversityStmt->fetchColumn();

            // Vendedores com preços variados = maior tolerância
            $priceStmt = $this->db->prepare("
                SELECT
                    STDDEV(price) / AVG(price) as price_cv
                FROM items
                WHERE account_id = :account_id AND status = 'active' AND price > 0
            ");
            $priceStmt->execute(['account_id' => $accountId]);
            $priceVariation = (float)$priceStmt->fetchColumn();

            // Lógica de classificação
            if ($categories > 5 && $priceVariation > 0.5) {
                return 'high';
            } elseif ($categories > 2 || $priceVariation > 0.3) {
                return 'medium';
            } else {
                return 'low';
            }
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /**
     * Determina estágio de crescimento do vendedor
     */
    private function determineGrowthStage($userId): string
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return 'unknown';
            }
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as active_items,
                    DATEDIFF(NOW(), MIN(date_created)) as days_since_first_item
                FROM items
                WHERE account_id = :account_id AND status = 'active'
            ");
            $stmt->execute(['account_id' => $accountId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $activeItems = (int)($data['active_items'] ?? 0);
            $daysSinceFirst = (int)($data['days_since_first_item'] ?? 0);

            // Lógica: startup < 3 meses, growth = crescendo, mature = estável
            if ($daysSinceFirst < 90 || $activeItems < 10) {
                return 'startup';
            } elseif ($activeItems < 100 && $daysSinceFirst < 365) {
                return 'growth';
            } else {
                return 'mature';
            }
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    // ========== MÉTODOS DE CONTEXTO (IMPLEMENTAÇÃO REAL) ==========

    /**
     * Retorna estação atual baseado na data (hemisfério sul)
     */
    private function getCurrentSeason(): string
    {
        $month = (int)date('n');

        // Hemisfério Sul (Brasil)
        if ($month >= 12 || $month <= 2) {
            return 'summer';
        } elseif ($month >= 3 && $month <= 5) {
            return 'autumn';
        } elseif ($month >= 6 && $month <= 8) {
            return 'winter';
        } else {
            return 'spring';
        }
    }

    /**
     * Obtém tendências de mercado atuais baseado em dados reais
     */
    private function getCurrentMarketTrends(): array
    {
        try {
            // Categorias com maior crescimento de vendas
            $stmt = $this->db->query("
                SELECT
                    category_id,
                    COUNT(*) as recent_sales,
                    (SELECT COUNT(*) FROM ml_orders o2
                     WHERE o2.category_id = o.category_id
                     AND o2.date_created BETWEEN DATE_SUB(NOW(), INTERVAL 60 DAY) AND DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) as previous_sales
                FROM ml_orders o
                WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY category_id
                HAVING recent_sales > 10
                ORDER BY (recent_sales - previous_sales) / NULLIF(previous_sales, 0) DESC
                LIMIT 5
            ");
            $trends = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($trends)) {
                return array_column($trends, 'category_id');
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * Obtém indicadores econômicos (pode ser integrado com API externa)
     */
    private function getEconomicIndicators(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT indicator_name, indicator_value, updated_at
                FROM economic_indicators
                WHERE updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($results)) {
                $indicators = [];
                foreach ($results as $row) {
                    $indicators[$row['indicator_name']] = (float)$row['indicator_value'];
                }
                return $indicators;
            }
        } catch (\Throwable $e) {
            return [];
        }

        return [];
    }

    /**
     * Analisa paisagem competitiva do vendedor
     */
    private function getCompetitiveLandscape($userId): array
    {
        try {
            $accountId = $this->resolveAccountId($userId);
            if (!$accountId) {
                return ['competition_level' => 'unknown', 'competitor_count' => 0, 'categories_analyzed' => 0];
            }
            $catStmt = $this->db->prepare("
                SELECT DISTINCT category_id FROM items
                WHERE account_id = :account_id AND status = 'active'
                LIMIT 5
            ");
            $catStmt->execute(['account_id' => $accountId]);
            $categories = $catStmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($categories)) {
                return ['competition_level' => 'unknown', 'market_share' => 0];
            }

            // Contar vendedores nas mesmas categorias
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $compStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT account_id) as competitors
                FROM items
                WHERE category_id IN ({$placeholders})
                AND status = 'active'
                AND account_id != ?
            ");
            $params = array_merge($categories, [$accountId]);
            $compStmt->execute($params);
            $competitors = (int)$compStmt->fetchColumn();

            // Classificar nível de competição
            $level = 'low';
            if ($competitors > 1000) {
                $level = 'very_high';
            } elseif ($competitors > 500) {
                $level = 'high';
            } elseif ($competitors > 100) {
                $level = 'medium';
            }

            return [
                'competition_level' => $level,
                'competitor_count' => $competitors,
                'categories_analyzed' => count($categories)
            ];
        } catch (\Throwable $e) {
            return ['competition_level' => 'unknown', 'competitor_count' => 0, 'categories_analyzed' => 0];
        }
    }

    /**
     * Retorna mudanças recentes na plataforma (pode ser alimentado por webhook ou admin)
     */
    private function getRecentPlatformChanges(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT change_type, description, impact_level, effective_date
                FROM platform_changes
                WHERE effective_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY effective_date DESC
                LIMIT 5
            ");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Obtém tendências baseadas na localização do vendedor
     */
    private function getLocationBasedTrends($userId): array
    {
        try {
            // Obter região do vendedor
            $stmt = $this->db->prepare("
                SELECT site_id AS state, '' AS city FROM ml_accounts WHERE id = :user_id
            ");
            $stmt->execute(['user_id' => $userId]);
            $location = $stmt->fetch(\PDO::FETCH_ASSOC);

            $state = $location['state'] ?? null;

            // Mapear estado para região
            $regionMap = [
                'SP' => 'southeast',
                'RJ' => 'southeast',
                'MG' => 'southeast',
                'ES' => 'southeast',
                'PR' => 'south',
                'SC' => 'south',
                'RS' => 'south',
                'BA' => 'northeast',
                'PE' => 'northeast',
                'CE' => 'northeast',
                'MA' => 'northeast',
                'PA' => 'north',
                'AM' => 'north',
                'GO' => 'midwest',
                'MT' => 'midwest',
                'MS' => 'midwest',
                'DF' => 'midwest'
            ];

            $region = $state ? ($regionMap[$state] ?? null) : null;

            return [
                'region' => $region,
                'state' => $state,
                'city' => $location['city'] ?? null
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Gera resumo do mercado baseado no contexto
     */
    private function generateMarketSummary($context): array
    {
        $season = $context['season'] ?? $this->getCurrentSeason();
        $trends = $context['market_trends'] ?? [];
        $economic = $context['economic_indicators'] ?? [];

        // Determinar status do mercado
        $status = 'stable';
        $inflation = $economic['inflation_12m'] ?? 5;
        $confidence = $economic['consumer_confidence'] ?? 90;

        if ($confidence > 100 && $inflation < 5) {
            $status = 'favorable';
        } elseif ($confidence < 80 || $inflation > 8) {
            $status = 'challenging';
        }

        return [
            'status' => $status,
            'season' => $season,
            'key_trends' => array_slice($trends, 0, 5),
            'consumer_sentiment' => $confidence > 95 ? 'positive' : ($confidence > 85 ? 'neutral' : 'cautious'),
            'inflation_impact' => $inflation > 6 ? 'high' : ($inflation > 4 ? 'moderate' : 'low')
        ];
    }

    // ========== ALGORITMOS DE CÁLCULO (IMPLEMENTAÇÃO REAL) ==========

    /**
     * Calcula relação demanda/oferta real para um produto
     */
    private function calculateDemandSupplyRatio($product, array $context = []): float
    {
        try {
            $categoryId = $product['category_id'] ?? null;
            if (!$categoryId) {
                return 1.0;
            }
            $accountId = $context['account_id'] ?? null;

            // Oferta: quantidade de anúncios ativos na categoria
            $supplyStmt = $this->db->prepare("
                SELECT COUNT(*) FROM items
                WHERE category_id = :cat AND status = 'active'
            ");
            $supplyStmt->execute(['cat' => $categoryId]);
            $supply = (int)$supplyStmt->fetchColumn();

            // Demanda: vendas nos últimos 30 dias
            $demandSql = "
                SELECT COUNT(*) FROM ml_orders
                WHERE category_id = :cat
                AND date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";
            if ($accountId) {
                $demandSql .= " AND ml_account_id = :account_id";
            }
            $demandStmt = $this->db->prepare($demandSql);
            $demandParams = ['cat' => $categoryId];
            if ($accountId) {
                $demandParams['account_id'] = $accountId;
            }
            $demandStmt->execute($demandParams);
            $demand = (int)$demandStmt->fetchColumn();

            if ($supply == 0) {
                return 2.0; // Alta demanda, sem oferta
            }

            return round($demand / $supply, 2);
        } catch (\Throwable $e) {
            return 1.0;
        }
    }

    /**
     * Calcula margem potencial baseado em dados reais
     */
    private function calculatePotentialMargin($product, $profile): float
    {
        try {
            $price = (float)($product['price'] ?? 0);
            $categoryId = $product['category_id'] ?? null;

            if ($price <= 0 || !$categoryId) {
                return 0.3; // Default 30%
            }

            // Obter preço médio da categoria
            $stmt = $this->db->prepare("
                SELECT AVG(price) as avg_price, MIN(price) as min_price
                FROM items
                WHERE category_id = :cat AND status = 'active' AND price > 0
            ");
            $stmt->execute(['cat' => $categoryId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $avgPrice = (float)($data['avg_price'] ?? $price);
            $minPrice = (float)($data['min_price'] ?? $price * 0.5);

            // Estimar margem baseado na diferença do preço médio
            $potentialMarkup = ($avgPrice - $minPrice) / $avgPrice;

            return min(0.6, max(0.1, round($potentialMarkup, 2)));
        } catch (\Throwable $e) {
            return 0.3;
        }
    }

    /**
     * Calcula fit do produto com perfil do vendedor
     */
    private function calculateProfileFit($product, $profile): float
    {
        $score = 0.5; // Base score

        // Categoria preferida?
        $preferredCategories = $profile['preferred_categories'] ?? [];
        if (in_array($product['category_id'] ?? '', $preferredCategories)) {
            $score += 0.2;
        }

        // Faixa de preço compatível?
        $priceRange = $profile['price_range'] ?? ['min' => 0, 'max' => 10000];
        $productPrice = (float)($product['price'] ?? 0);
        if ($productPrice >= $priceRange['min'] && $productPrice <= $priceRange['max']) {
            $score += 0.15;
        }

        // Experiência adequada?
        $experience = $profile['experience_level'] ?? 'beginner';
        $productComplexity = $this->estimateProductComplexity($product);

        if ($experience === 'advanced' || $productComplexity === 'simple') {
            $score += 0.15;
        } elseif ($experience === 'intermediate' && $productComplexity !== 'complex') {
            $score += 0.1;
        }

        return min(1.0, round($score, 2));
    }

    /**
     * Estima complexidade do produto
     */
    private function estimateProductComplexity($product): string
    {
        $price = (float)($product['price'] ?? 0);
        $attributes = $product['attributes'] ?? [];

        if ($price > 2000 || count($attributes) > 15) {
            return 'complex';
        } elseif ($price > 500 || count($attributes) > 8) {
            return 'moderate';
        }
        return 'simple';
    }

    /**
     * Calcula score de tendência baseado em dados reais
     */
    private function calculateTrendScore($product, $context): float
    {
        try {
            $categoryId = $product['category_id'] ?? null;
            if (!$categoryId) {
                return 0.5;
            }

            // Comparar vendas recentes vs anteriores
            $stmt = $this->db->prepare("
                SELECT
                    (SELECT COUNT(*) FROM ml_orders WHERE category_id = :cat1
                     AND date_created >= DATE_SUB(NOW(), INTERVAL 15 DAY)) as recent,
                    (SELECT COUNT(*) FROM ml_orders WHERE category_id = :cat2
                     AND date_created BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND DATE_SUB(NOW(), INTERVAL 15 DAY)) as previous
            ");
            $stmt->execute(['cat1' => $categoryId, 'cat2' => $categoryId]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $recent = (int)($data['recent'] ?? 0);
            $previous = (int)($data['previous'] ?? 1);

            if ($previous == 0) {
                return $recent > 0 ? 0.8 : 0.5;
            }

            $growthRate = ($recent - $previous) / $previous;

            // Converter taxa de crescimento em score 0-1
            if ($growthRate > 0.5) return 0.95;
            if ($growthRate > 0.2) return 0.8;
            if ($growthRate > 0) return 0.65;
            if ($growthRate > -0.2) return 0.5;
            return 0.3;
        } catch (\Throwable $e) {
            return 0.5;
        }
    }

    /**
     * Calcula competitividade do produto
     */
    private function calculateCompetitiveness($product): float
    {
        try {
            $categoryId = $product['category_id'] ?? null;
            $price = (float)($product['price'] ?? 0);

            if (!$categoryId || $price <= 0) {
                return 0.5;
            }

            // Quantos produtos similares existem com preço menor?
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN price < :price THEN 1 ELSE 0 END) as cheaper
                FROM items
                WHERE category_id = :cat AND status = 'active' AND price > 0
            ");
            $stmt->execute(['cat' => $categoryId, 'price' => $price]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            $total = (int)($data['total'] ?? 1);
            $cheaper = (int)($data['cheaper'] ?? 0);

            // Score alto = menos concorrentes mais baratos
            return round(1 - ($cheaper / $total), 2);
        } catch (\Throwable $e) {
            return 0.5;
        }
    }

    /**
     * Calcula facilidade de clonar o produto
     */
    private function calculateCloneEase($product): float
    {
        $score = 0.9; // Começa alto

        // Penalizar por complexidade de atributos
        $attributes = $product['attributes'] ?? [];
        if (count($attributes) > 20) {
            $score -= 0.2;
        } elseif (count($attributes) > 10) {
            $score -= 0.1;
        }

        // Penalizar por descrição muito longa
        $description = $product['description'] ?? '';
        if (strlen($description) > 5000) {
            $score -= 0.1;
        }

        // Penalizar se precisa de variações complexas
        $variations = $product['variations'] ?? [];
        if (count($variations) > 5) {
            $score -= 0.15;
        }

        // Penalizar se precisa de certificações especiais
        $categoryId = $product['category_id'] ?? '';
        $restrictedCategories = ['MLB1182', 'MLB1574']; // Saúde, etc.
        if (in_array($categoryId, $restrictedCategories)) {
            $score -= 0.2;
        }

        return max(0.3, round($score, 2));
    }

    /**
     * Retorna força da recomendação
     */
    private function getRecommendationStrength($score): string
    {
        if ($score >= 85) return 'very_high';
        if ($score >= 70) return 'high';
        if ($score >= 55) return 'medium';
        if ($score >= 40) return 'low';
        return 'very_low';
    }

    /**
     * Retorna peso do tipo de recomendação baseado no perfil
     */
    private function getTypeWeight($type, $profile): float
    {
        $experience = $profile['experience_level'] ?? 'beginner';
        $growthStage = $profile['growth_stage'] ?? 'startup';

        $weights = [
            'products_to_clone' => [
                'beginner' => 1.2,
                'intermediate' => 1.0,
                'advanced' => 0.8
            ],
            'pricing_opportunities' => [
                'beginner' => 0.7,
                'intermediate' => 1.0,
                'advanced' => 1.2
            ],
            'market_opportunities' => [
                'startup' => 1.1,
                'growth' => 1.2,
                'mature' => 1.0
            ],
            'optimization_actions' => [
                'beginner' => 1.0,
                'intermediate' => 1.1,
                'advanced' => 1.2
            ]
        ];

        $typeWeights = $weights[$type] ?? [];

        return $typeWeights[$experience] ?? $typeWeights[$growthStage] ?? 1.0;
    }

    /**
     * Obtém fatores de confiança para um item
     */
    private function getConfidenceFactors($item, $type): array
    {
        $factors = [
            'data_quality' => 'medium',
            'sample_size' => 'adequate',
            'recency' => 'current'
        ];

        // Avaliar qualidade dos dados
        if (isset($item['data_points']) && $item['data_points'] > 100) {
            $factors['data_quality'] = 'high';
            $factors['sample_size'] = 'large';
        } elseif (isset($item['data_points']) && $item['data_points'] < 10) {
            $factors['data_quality'] = 'low';
            $factors['sample_size'] = 'small';
        }

        return $factors;
    }

    /**
     * Avalia dificuldade de implementação
     */
    private function assessImplementationDifficulty($item, $type): string
    {
        // Baseado no tipo de recomendação e complexidade do item
        switch ($type) {
            case 'products_to_clone':
                $ease = $item['clone_ease'] ?? 0.7;
                if ($ease > 0.8) return 'easy';
                if ($ease > 0.5) return 'medium';
                return 'hard';

            case 'pricing_opportunities':
                return 'easy'; // Mudar preço é simples

            case 'market_opportunities':
                return 'medium'; // Requer pesquisa

            case 'optimization_actions':
                $actionType = $item['action_type'] ?? '';
                if (in_array($actionType, ['update_title', 'update_price'])) return 'easy';
                if (in_array($actionType, ['improve_photos', 'add_variations'])) return 'medium';
                return 'hard';

            default:
                return 'medium';
        }
    }

    /**
     * Explica recomendação de preço
     */
    private function explainPricingRecommendation($item, $profile): string
    {
        $currentPrice = $item['current_price'] ?? 0;
        $recommendedPrice = $item['recommended_price'] ?? 0;
        $strategy = $item['strategy_type'] ?? 'optimize';

        if ($recommendedPrice > $currentPrice) {
            $increase = round((($recommendedPrice - $currentPrice) / $currentPrice) * 100, 1);
            return "Análise de mercado indica oportunidade de aumento de {$increase}%. " .
                "Produtos similares estão sendo vendidos a preços maiores com boa conversão.";
        } elseif ($recommendedPrice < $currentPrice) {
            $decrease = round((($currentPrice - $recommendedPrice) / $currentPrice) * 100, 1);
            return "Recomendamos redução de {$decrease}% para aumentar competitividade. " .
                "Concorrentes com preços menores estão capturando maior volume de vendas.";
        }

        return "Preço atual está adequado ao mercado. Mantenha e monitore a concorrência.";
    }

    /**
     * Explica recomendação de mercado
     */
    private function explainMarketRecommendation($item, $profile): string
    {
        $type = $item['type'] ?? 'opportunity';
        $score = $item['score'] ?? 0;

        switch ($type) {
            case 'market_gap':
                return "Identificamos uma lacuna de mercado com baixa concorrência e demanda consistente. " .
                    "Score de oportunidade: {$score}/100.";

            case 'trending_product':
                $growth = $item['growth_rate'] ?? 0;
                return "Produto em tendência com crescimento de {$growth}% nas últimas semanas. " .
                    "Boa oportunidade para entrada rápida no mercado.";

            case 'undersupplied_niche':
                $ratio = $item['supply_demand_ratio'] ?? 1;
                return "Nicho com demanda {$ratio}x maior que a oferta atual. " .
                    "Potencial de margens acima da média do mercado.";

            default:
                return "Oportunidade identificada através de análise de dados de mercado.";
        }
    }
}
