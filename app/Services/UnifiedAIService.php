<?php

namespace App\Services;

use App\Services\AIContentGeneratorService;
use App\Services\AIImageAnalyzerService;
use App\Services\AISEOOptimizerService;
use App\Services\AIRecommendationEngineService;
use App\Services\AIPredictiveAnalyticsService;
use App\Services\AI\ML\LearningEngine;
use App\Services\AI\ML\MarketTrendPredictor;
use App\Services\AI\ML\DeepDemandPredictor;
use App\Services\AI\Intelligence\CompetitorIntelligenceService;

/**
 * API de IA Unificada - Centralizador de todos os serviços de IA
 * Provides a unified interface for all AI services with intelligent routing
 */
class UnifiedAIService
{
    private $accountId;
    private $contentGenerator;
    private $imageAnalyzer;
    private $seoOptimizer;
    private $recommendationEngine;
    private $predictiveAnalytics;
    
    private $operationLog = [];
    private $performanceMetrics = [];

    public function __construct($accountId) {
        $this->accountId = $accountId;
        // Services will be initialized lazily
        $this->startMetricsCollection();
    }

    /**
     * Lazy load AI services
     */
    private function getService($serviceName) {
        if (!isset($this->$serviceName)) {
            switch ($serviceName) {
                case 'contentGenerator':
                    $this->contentGenerator = new AIContentGeneratorService($this->accountId);
                    break;
                case 'imageAnalyzer':
                    $this->imageAnalyzer = new AIImageAnalyzerService($this->accountId);
                    break;
                case 'seoOptimizer':
                    $this->seoOptimizer = new AISEOOptimizerService($this->accountId);
                    break;
                case 'recommendationEngine':
                    $this->recommendationEngine = new AIRecommendationEngineService($this->accountId);
                    break;
                case 'predictiveAnalytics':
                    $this->predictiveAnalytics = new AIPredictiveAnalyticsService($this->accountId);
                    break;
                case 'learningPipeline':
                    $this->learningPipeline = new LearningEngine($this->accountId);
                    break;
                case 'marketTrendPredictor':
                    $this->marketTrendPredictor = new MarketTrendPredictor();
                    break;
                case 'deepDemandPredictor':
                    $this->deepDemandPredictor = new DeepDemandPredictor();
                    break;
                case 'competitorIntelligence':
                    $this->competitorIntelligence = new CompetitorIntelligenceService();
                    break;
            }
        }
        return $this->$serviceName;
    }

    /**
     * Initialize all AI services
     * @deprecated Use lazy loading instead
     */
    private function initializeServices() {
        // Deprecated
    }

    /**
     * Unified AI Processing - Single entry point for all AI operations
     */
    public function processAIRequest($operation, $data = [], $options = []) {
        $startTime = microtime(true);
        
        try {
            // Route to appropriate AI service based on operation
            $result = $this->routeOperation($operation, $data, $options);
            
            // Log operation
            $this->logOperation($operation, $result, $startTime, 'success');
            
            // Update performance metrics
            $this->updatePerformanceMetrics($operation, microtime(true) - $startTime, true);
            
            return [
                'success' => true,
                'operation' => $operation,
                'result' => $result,
                'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            $this->logOperation($operation, ['error' => $e->getMessage()], $startTime, 'error');
            $this->updatePerformanceMetrics($operation, microtime(true) - $startTime, false);
            
            return [
                'success' => false,
                'operation' => $operation,
                'error' => $e->getMessage(),
                'processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Route operations to appropriate AI services
     */
    private function routeOperation($operation, $data, $options) {
        switch (strtolower($operation)) {
            // Content Generation Operations
            case 'generate_content':
            case 'generate_description':
            case 'generate_title':
            case 'generate_bullets':
                return $this->handleContentOperations($operation, $data, $options);
                
            // Image Analysis Operations
            case 'analyze_image':
            case 'analyze_images':
            case 'extract_colors':
            case 'check_quality':
                return $this->handleImageOperations($operation, $data, $options);
                
            // SEO Optimization Operations
            case 'analyze_seo':
            case 'optimize_seo':
            case 'keyword_analysis':
            case 'optimize_title':
                return $this->handleSEOOperations($operation, $data, $options);
                
            // Recommendation Operations
            case 'get_recommendations':
            case 'recommend_products':
            case 'analyze_market':
            case 'clone_suggestions':
                return $this->handleRecommendationOperations($operation, $data, $options);
                
            // Predictive Analytics Operations
            case 'predict_performance':
            case 'predict_demand':
            case 'predict_pricing':
            case 'forecast_trend':
                return $this->handlePredictiveOperations($operation, $data, $options);
                
            // Composite Operations (Multi-service)
            case 'full_analysis':
            case 'complete_optimization':
            case 'smart_listing':
                return $this->handleCompositeOperations($operation, $data, $options);
            
            // Learning Operations
            case 'learn_from_feedback':
            case 'get_learning_stats':
            case 'analyze_patterns':
                return $this->handleLearningOperations($operation, $data, $options);
            
            // Market & Intelligence Operations
            case 'analyze_market_trends':
            case 'forecast_demand':
            case 'analyze_competitor':
            case 'detect_competitor_strategy':
                return $this->handleIntelligenceOperations($operation, $data, $options);
                
            default:
                throw new Exception("Operação não reconhecida: $operation");
        }
    }

    /**
     * Handle Content Generation Operations
     */
    private function handleContentOperations($operation, $data, $options) {
        $generator = $this->getService('contentGenerator');
        switch ($operation) {
            case 'generate_content':
                return $generator->generateProductDescription($data['product_info'] ?? []);
                
            case 'generate_description':
                return $generator->generateProductDescription($data);
                
            case 'generate_title':
                return $generator->optimizeTitle($data['title'] ?? '', $data['keywords'] ?? []);
                
            case 'generate_bullets':
                return $generator->generateBulletPoints($data['product'] ?? []);
                
            default:
                throw new Exception("Operação de conteúdo não reconhecida");
        }
    }

    /**
     * Handle Image Analysis Operations
     */
    private function handleImageOperations($operation, $data, $options) {
        $analyzer = $this->getService('imageAnalyzer');
        switch ($operation) {
            case 'analyze_image':
                return $analyzer->analyzeProductImage($data['image_url'] ?? '');
                
            case 'analyze_images':
                return $analyzer->analyzeBulkImages($data['images'] ?? []);
                
            case 'extract_colors':
                return $analyzer->extractColorPalette($data['image_url'] ?? '');
                
            case 'check_quality':
                $analysis = $analyzer->analyzeProductImage($data['image_url'] ?? '');
                return ['quality_score' => $analysis['technical_analysis']['quality_score']];
                
            default:
                throw new Exception("Operação de imagem não reconhecida");
        }
    }

    /**
     * Handle SEO Optimization Operations
     */
    private function handleSEOOperations($operation, $data, $options) {
        $optimizer = $this->getService('seoOptimizer');
        switch ($operation) {
            case 'analyze_seo':
                return $optimizer->analyzeSEO($data);
                
            case 'optimize_seo':
                return $optimizer->optimizeProduct($data);
                
            case 'keyword_analysis':
                return $optimizer->analyzeKeywords($data['keywords'] ?? [], $data['category_id'] ?? '');
                
            case 'optimize_title':
                $seoResult = $optimizer->optimizeProduct($data);
                return ['optimized_title' => $seoResult['optimizations']['title']];
                
            default:
                throw new Exception("Operação de SEO não reconhecida");
        }
    }

    /**
     * Handle Recommendation Operations
     */
    private function handleRecommendationOperations($operation, $data, $options) {
        $engine = $this->getService('recommendationEngine');
        switch ($operation) {
            case 'get_recommendations':
                return $engine->getPersonalizedRecommendations(
                    $data['user_id'] ?? $this->accountId,
                    $data['limit'] ?? 10
                );
                
            case 'recommend_products':
                return $engine->recommendProductsToClone($data['category_id'] ?? '');
                
            case 'analyze_market':
                return $engine->analyzeMarketOpportunities($data['filters'] ?? []);
                
            case 'clone_suggestions':
                return $engine->recommendProductsToClone($data['category_id'] ?? '');
                
            default:
                throw new Exception("Operação de recomendação não reconhecida");
        }
    }

    /**
     * Handle Predictive Analytics Operations
     */
    private function handlePredictiveOperations($operation, $data, $options) {
        $analytics = $this->getService('predictiveAnalytics');
        switch ($operation) {
            case 'predict_performance':
                return $analytics->predictProductPerformance($data);
                
            case 'predict_demand':
                return $analytics->predictMarketDemand(
                    $data['category_id'] ?? '',
                    $data['timeframe'] ?? 30
                );
                
            case 'predict_pricing':
                $performance = $analytics->predictProductPerformance($data);
                return ['optimal_price' => $performance['price_optimization']['optimal_price']];
                
            case 'forecast_trend':
                return $analytics->predictMarketDemand(
                    $data['category_id'] ?? '',
                    $data['days'] ?? 90
                );
                
            default:
                throw new Exception("Operação preditiva não reconhecida");
        }
    }

    /**
     * Handle Learning Operations
     */
    private function handleLearningOperations($operation, $data, $options) {
        $engine = $this->getService('learningPipeline');
        switch ($operation) {
            case 'learn_from_feedback':
                return $engine->recordFeedback(
                    $data['item_id'] ?? '',
                    $data['type'] ?? 'general',
                    $data['feedback'] ?? [],
                    $data['optimization_id'] ?? null
                );

            case 'get_learning_stats':
                return $engine->getStats();

            case 'analyze_patterns':
                return $engine->analyzeSuccessPatterns($data['category_id'] ?? null);

            default:
                throw new Exception("Operação de aprendizado não reconhecida");
        }
    }

    /**
     * Handle Intelligence Operations (Trends, Demand, Competitors)
     */
    private function handleIntelligenceOperations($operation, $data, $options) {
        switch ($operation) {
            case 'analyze_market_trends':
                return $this->getService('marketTrendPredictor')->analyzeTrends($data['category_id']);
                
            case 'forecast_demand':
                return $this->getService('deepDemandPredictor')->forecastDemand($data['sku'], $data['days'] ?? 30);
                
            case 'analyze_competitor':
                return $this->getService('competitorIntelligence')->trackCompetitor($data['competitor_id']);
                
            case 'detect_competitor_strategy':
                return $this->getService('competitorIntelligence')->detectStrategy($data['competitor_id']);
                
            default:
                throw new Exception("Operação de inteligência não reconhecida");
        }
    }

    /**
     * Handle Composite Operations (Multi-service)
     */
    private function handleCompositeOperations($operation, $data, $options) {
        switch ($operation) {
            case 'full_analysis':
                return $this->performFullAnalysis($data);
                
            case 'complete_optimization':
                return $this->performCompleteOptimization($data);
                
            case 'smart_listing':
                return $this->createSmartListing($data);
                
            default:
                throw new Exception("Operação composta não reconhecida");
        }
    }

    /**
     * Perform full AI analysis using all services
     */
    private function performFullAnalysis($data) {
        $results = [];
        
        // SEO Analysis
        if (isset($data['title']) || isset($data['description'])) {
            $results['seo_analysis'] = $this->getService('seoOptimizer')->analyzeSEO($data);
        }
        
        // Image Analysis
        if (isset($data['images']) && !empty($data['images'])) {
            $results['image_analysis'] = $this->getService('imageAnalyzer')->analyzeBulkImages($data['images']);
        }
        
        // Content Quality Analysis
        if (isset($data['title']) || isset($data['description'])) {
            $results['content_quality'] = $this->getService('contentGenerator')->validateContentQuality([
                'title' => $data['title'] ?? '',
                'description' => $data['description'] ?? ''
            ]);
        }
        
        // Performance Prediction
        $results['performance_prediction'] = $this->getService('predictiveAnalytics')->predictProductPerformance($data);
        
        // Market Recommendations
        if (isset($data['category_id'])) {
            $results['market_insights'] = $this->getService('recommendationEngine')->analyzeMarketOpportunities([
                'category_id' => $data['category_id']
            ]);
        }
        
        // Overall Score
        $results['overall_score'] = $this->calculateOverallScore($results);
        
        return $results;
    }

    /**
     * Perform complete optimization using all services
     */
    private function performCompleteOptimization($data) {
        $optimizations = [];
        
        // SEO Optimization
        $optimizations['seo'] = $this->getService('seoOptimizer')->optimizeProduct($data);
        
        // Content Optimization
        if (isset($data['title'])) {
            $optimizations['title'] = $this->getService('contentGenerator')->optimizeTitle(
                $data['title'],
                $optimizations['seo']['keyword_analysis']['recommended_keywords'] ?? []
            );
        }
        
        if (isset($data['description']) || isset($data['product_info'])) {
            $optimizations['description'] = $this->getService('contentGenerator')->generateProductDescription(
                $data['product_info'] ?? []
            );
        }
        
        // Price Optimization
        $performance = $this->getService('predictiveAnalytics')->predictProductPerformance($data);
        $optimizations['pricing'] = $performance['price_optimization'];
        
        // Action Plan
        $optimizations['action_plan'] = $this->generateActionPlan($optimizations);
        
        return $optimizations;
    }

    /**
     * Create smart listing with AI assistance
     */
    private function createSmartListing($data) {
        $listing = [];
        
        // Generate optimized content
        $content = $this->getService('contentGenerator')->generateProductDescription($data);
        $listing['title'] = $content['title'];
        $listing['description'] = $content['description'];
        $listing['bullet_points'] = $content['bullet_points'];
        
        // SEO optimization
        $seo = $this->getService('seoOptimizer')->optimizeProduct($data);
        $listing['seo_keywords'] = $seo['keyword_analysis']['recommended_keywords'];
        
        // Price suggestion
        $prediction = $this->getService('predictiveAnalytics')->predictProductPerformance($data);
        $listing['suggested_price'] = $prediction['price_optimization']['optimal_price'];
        
        // Category recommendations
        if (isset($data['category_id'])) {
            $recommendations = $this->getService('recommendationEngine')->analyzeMarketOpportunities([
                'category_id' => $data['category_id']
            ]);
            $listing['market_insights'] = $recommendations;
        }
        
        // Image optimization suggestions
        if (isset($data['images'])) {
            $imageAnalysis = $this->getService('imageAnalyzer')->analyzeBulkImages($data['images']);
            $listing['image_suggestions'] = $imageAnalysis['improvement_suggestions'];
        }
        
        return $listing;
    }

    /**
     * Calculate overall score from multiple analyses
     */
    private function calculateOverallScore($results) {
        $scores = [];
        
        if (isset($results['seo_analysis'])) {
            $scores[] = $results['seo_analysis']['overall_score'];
        }
        
        if (isset($results['content_quality'])) {
            $scores[] = $results['content_quality']['overall_score'];
        }
        
        if (isset($results['performance_prediction'])) {
            $scores[] = $results['performance_prediction']['performance_score'];
        }
        
        if (isset($results['image_analysis'])) {
            $scores[] = $results['image_analysis']['average_quality_score'];
        }
        
        return !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0;
    }

    /**
     * Generate action plan from optimizations
     */
    private function generateActionPlan($optimizations) {
        $actions = [];
        
        // SEO Actions
        if (isset($optimizations['seo']['recommendations'])) {
            foreach ($optimizations['seo']['recommendations'] as $recommendation) {
                $actions[] = [
                    'category' => 'SEO',
                    'action' => $recommendation['action'],
                    'priority' => $recommendation['priority'],
                    'impact' => $recommendation['impact']
                ];
            }
        }
        
        // Content Actions
        if (isset($optimizations['title'])) {
            $actions[] = [
                'category' => 'Content',
                'action' => 'Atualizar título otimizado',
                'priority' => 'high',
                'impact' => 'high'
            ];
        }
        
        // Pricing Actions
        if (isset($optimizations['pricing'])) {
            $actions[] = [
                'category' => 'Pricing',
                'action' => 'Ajustar preço para valor otimizado',
                'priority' => 'medium',
                'impact' => 'high'
            ];
        }
        
        // Sort by priority
        usort($actions, function($a, $b) {
            $priority_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priority_order[$b['priority']] <=> $priority_order[$a['priority']];
        });
        
        return $actions;
    }

    /**
     * Batch Processing - Process multiple AI operations in sequence
     */
    public function processBatch($operations) {
        $results = [];
        $startTime = microtime(true);
        
        foreach ($operations as $operation) {
            $result = $this->processAIRequest(
                $operation['operation'],
                $operation['data'] ?? [],
                $operation['options'] ?? []
            );
            
            $results[] = $result;
            
            // Break on first error if specified
            if (!$result['success'] && ($operation['options']['break_on_error'] ?? false)) {
                break;
            }
        }
        
        return [
            'batch_success' => true,
            'total_operations' => count($operations),
            'successful_operations' => count(array_filter($results, fn($r) => $r['success'])),
            'failed_operations' => count(array_filter($results, fn($r) => !$r['success'])),
            'results' => $results,
            'total_processing_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms'
        ];
    }

    /**
     * Get AI Service Status
     */
    public function getServiceStatus() {
        return [
            'unified_ai_service' => 'active',
            'services' => [
                'content_generator' => isset($this->contentGenerator) ? 'active' : 'lazy',
                'image_analyzer' => isset($this->imageAnalyzer) ? 'active' : 'lazy',
                'seo_optimizer' => isset($this->seoOptimizer) ? 'active' : 'lazy',
                'recommendation_engine' => isset($this->recommendationEngine) ? 'active' : 'lazy',
                'predictive_analytics' => isset($this->predictiveAnalytics) ? 'active' : 'lazy',
                'learning_pipeline' => isset($this->learningPipeline) ? 'active' : 'lazy',
                'market_trend_predictor' => isset($this->marketTrendPredictor) ? 'active' : 'lazy',
                'deep_demand_predictor' => isset($this->deepDemandPredictor) ? 'active' : 'lazy',
                'competitor_intelligence' => isset($this->competitorIntelligence) ? 'active' : 'lazy'
            ],
            'total_operations' => count($this->operationLog),
            'success_rate' => $this->calculateSuccessRate(),
            'average_processing_time' => $this->calculateAverageProcessingTime(),
            'last_operation' => end($this->operationLog) ?: null
        ];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics() {
        return [
            'operation_counts' => $this->performanceMetrics['operations'] ?? [],
            'success_rates' => $this->performanceMetrics['success_rates'] ?? [],
            'processing_times' => $this->performanceMetrics['processing_times'] ?? [],
            'error_rates' => $this->performanceMetrics['error_rates'] ?? [],
            'daily_stats' => $this->getDailyStats()
        ];
    }

    /**
     * Start metrics collection
     */
    private function startMetricsCollection() {
        $this->performanceMetrics = [
            'operations' => [],
            'success_rates' => [],
            'processing_times' => [],
            'error_rates' => []
        ];
    }

    /**
     * Log operation
     */
    private function logOperation($operation, $result, $startTime, $status) {
        $this->operationLog[] = [
            'operation' => $operation,
            'status' => $status,
            'processing_time' => microtime(true) - $startTime,
            'timestamp' => date('Y-m-d H:i:s'),
            'result_size' => strlen(json_encode($result))
        ];
    }

    /**
     * Update performance metrics
     */
    private function updatePerformanceMetrics($operation, $processingTime, $success) {
        if (!isset($this->performanceMetrics['operations'][$operation])) {
            $this->performanceMetrics['operations'][$operation] = 0;
            $this->performanceMetrics['success_rates'][$operation] = [];
            $this->performanceMetrics['processing_times'][$operation] = [];
        }
        
        $this->performanceMetrics['operations'][$operation]++;
        $this->performanceMetrics['success_rates'][$operation][] = $success ? 1 : 0;
        $this->performanceMetrics['processing_times'][$operation][] = $processingTime;
    }

    /**
     * Calculate success rate
     */
    private function calculateSuccessRate() {
        if (empty($this->operationLog)) return 100;
        
        $successful = array_filter($this->operationLog, fn($log) => $log['status'] === 'success');
        return round((count($successful) / count($this->operationLog)) * 100, 1);
    }

    /**
     * Calculate average processing time
     */
    private function calculateAverageProcessingTime() {
        if (empty($this->operationLog)) return 0;
        
        $times = array_column($this->operationLog, 'processing_time');
        return round(array_sum($times) / count($times) * 1000, 2);
    }

    /**
     * Get daily statistics
     */
    private function getDailyStats() {
        $today = date('Y-m-d');
        $todayOps = array_filter($this->operationLog, fn($log) => 
            strpos($log['timestamp'], $today) === 0
        );
        
        return [
            'today_operations' => count($todayOps),
            'today_success_rate' => !empty($todayOps) ? 
                round((count(array_filter($todayOps, fn($log) => $log['status'] === 'success')) / count($todayOps)) * 100, 1) : 100,
            'hourly_distribution' => $this->getHourlyDistribution($todayOps)
        ];
    }

    /**
     * Get hourly operation distribution
     */
    private function getHourlyDistribution($operations) {
        $hours = array_fill(0, 24, 0);
        
        foreach ($operations as $op) {
            $hour = (int)date('H', strtotime($op['timestamp']));
            $hours[$hour]++;
        }
        
        return $hours;
    }
}