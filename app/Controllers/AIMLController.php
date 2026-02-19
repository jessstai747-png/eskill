<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\AI\ML\CategoryLearningService;
use App\Services\AI\ML\KeywordClassifierService;
use App\Services\AI\ML\TrendPredictorService;
use App\Services\UserService;

/**
 * 🧠 AI ML Controller
 * 
 * API para serviços de Machine Learning e IA:
 * - Category Learning (padrões por categoria)
 * - Keyword Classification (CORE/SUPPORT/LONG_TAIL)
 * - Trend Prediction (tendências e sazonalidade)
 */
class AIMLController
{
    private ?int $accountId;
    private UserService $userService;
    private Request $request;
    
    public function __construct()
    {
        $this->userService = new UserService();
        $this->request = new Request();
        
        if (!$this->userService->isAuthenticated()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }

        $this->accountId = $_SESSION['active_ml_account_id'] ?? null;
    }

    // ========================================
    // 📚 Category Learning
    // ========================================

    /**
     * 📚 Aprender padrões de uma categoria
     * POST /api/ai/ml/category/learn
     */
    public function learnCategory(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            
            if (empty($data['category_id'])) {
                return ['error' => 'category_id é obrigatório'];
            }

            $service = new CategoryLearningService($this->accountId);
            $sampleSize = (int) ($data['sample_size'] ?? 50);
            
            return $service->learnCategory($data['category_id'], $sampleSize);
        });
    }

    /**
     * 📖 Obter aprendizado de categoria
     * GET /api/ai/ml/category/learning/{categoryId}
     */
    public function getCategoryLearning(string $categoryId): void
    {
        $this->json(function () use ($categoryId) {
            $service = new CategoryLearningService($this->accountId);
            $learning = $service->getCategoryLearning($categoryId);
            
            if (!$learning) {
                return ['error' => 'Nenhum aprendizado encontrado para esta categoria'];
            }
            
            return $learning;
        });
    }

    /**
     * 🎯 Gerar template otimizado
     * GET /api/ai/ml/category/template/{categoryId}
     */
    public function getCategoryTemplate(string $categoryId): void
    {
        $this->json(function () use ($categoryId) {
            $service = new CategoryLearningService($this->accountId);
            return $service->generateOptimizedTemplate($categoryId);
        });
    }

    /**
     * 📋 Listar categorias aprendidas
     * GET /api/ai/ml/category/learned
     */
    public function listLearnedCategories(): void
    {
        $this->json(function () {
            $service = new CategoryLearningService($this->accountId);
            return [
                'success' => true,
                'categories' => $service->listLearnedCategories(),
            ];
        });
    }

    /**
     * 🔄 Atualizar aprendizado
     * POST /api/ai/ml/category/refresh/{categoryId}
     */
    public function refreshCategoryLearning(string $categoryId): void
    {
        $this->json(function () use ($categoryId) {
            $service = new CategoryLearningService($this->accountId);
            return $service->refreshCategoryLearning($categoryId);
        });
    }

    // ========================================
    // 🏷️ Keyword Classification
    // ========================================

    /**
     * 🏷️ Classificar keywords
     * POST /api/ai/ml/keywords/classify
     */
    public function classifyKeywords(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            
            if (empty($data['keywords']) || !is_array($data['keywords'])) {
                return ['error' => 'keywords (array) é obrigatório'];
            }

            $service = new KeywordClassifierService($this->accountId);
            $categoryContext = $data['category_context'] ?? null;
            
            return [
                'success' => true,
                'classifications' => $service->classifyKeywords($data['keywords'], $categoryContext),
            ];
        });
    }

    /**
     * 📊 Classificar e agrupar por tipo
     * POST /api/ai/ml/keywords/group
     */
    public function groupKeywords(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            
            if (empty($data['keywords']) || !is_array($data['keywords'])) {
                return ['error' => 'keywords (array) é obrigatório'];
            }

            $service = new KeywordClassifierService($this->accountId);
            $categoryContext = $data['category_context'] ?? null;
            
            return [
                'success' => true,
                'grouped' => $service->classifyAndGroup($data['keywords'], $categoryContext),
            ];
        });
    }

    /**
     * 🎯 Otimizar keywords para título
     * POST /api/ai/ml/keywords/optimize-title
     */
    public function optimizeKeywordsForTitle(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            
            if (empty($data['keywords']) || !is_array($data['keywords'])) {
                return ['error' => 'keywords (array) é obrigatório'];
            }

            $service = new KeywordClassifierService($this->accountId);
            $categoryContext = $data['category_context'] ?? null;
            $maxLength = (int) ($data['max_length'] ?? 60);
            
            return $service->getOptimizedForTitle($data['keywords'], $categoryContext, $maxLength);
        });
    }

    /**
     * 📈 Estatísticas de classificação
     * GET /api/ai/ml/keywords/stats
     */
    public function getKeywordStats(): void
    {
        $this->json(function () {
            $service = new KeywordClassifierService($this->accountId);
            return [
                'success' => true,
                'stats' => $service->getClassificationStats(),
            ];
        });
    }

    /**
     * 🔄 Reclassificar keywords com baixa confiança
     * POST /api/ai/ml/keywords/reclassify
     */
    public function reclassifyKeywords(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            $threshold = (float) ($data['threshold'] ?? 0.7);
            
            $service = new KeywordClassifierService($this->accountId);
            $count = $service->reclassifyLowConfidence($threshold);
            
            return [
                'success' => true,
                'reclassified_count' => $count,
            ];
        });
    }

    // ========================================
    // 📈 Trend Prediction
    // ========================================

    /**
     * 📊 Prever tendência para keyword
     * GET /api/ai/ml/trends/predict
     */
    public function predictTrend(): void
    {
        $this->json(function () {
            $keyword = $this->request->get('keyword', '') ?? '';
            $categoryId = $this->request->get('category_id');
            
            if (empty($keyword)) {
                return ['error' => 'keyword é obrigatório'];
            }

            $service = new TrendPredictorService($this->accountId);
            return $service->predictTrend($keyword, $categoryId);
        });
    }

    /**
     * 📅 Analisar sazonalidade
     * GET /api/ai/ml/trends/seasonality
     */
    public function analyzeSeasonality(): void
    {
        $this->json(function () {
            $keyword = $this->request->get('keyword', '') ?? '';
            $categoryId = $this->request->get('category_id');
            
            if (empty($keyword)) {
                return ['error' => 'keyword é obrigatório'];
            }

            $service = new TrendPredictorService($this->accountId);
            return [
                'success' => true,
                'keyword' => $keyword,
                'seasonality' => $service->analyzeSeasonality($keyword, $categoryId),
            ];
        });
    }

    /**
     * 🔥 Keywords em ascensão
     * GET /api/ai/ml/trends/rising/{categoryId}
     */
    public function findRisingKeywords(string $categoryId): void
    {
        $this->json(function () use ($categoryId) {
            $limit = $this->request->getInt('limit', 20);
            
            $service = new TrendPredictorService($this->accountId);
            return [
                'success' => true,
                'category_id' => $categoryId,
                'rising_keywords' => $service->findRisingKeywords($categoryId, $limit),
            ];
        });
    }

    /**
     * 📋 Relatório de tendências por categoria
     * GET /api/ai/ml/trends/report/{categoryId}
     */
    public function getCategoryTrendReport(string $categoryId): void
    {
        $this->json(function () use ($categoryId) {
            $service = new TrendPredictorService($this->accountId);
            return $service->getCategoryTrendReport($categoryId);
        });
    }

    /**
     * 📊 Tendências em lote
     * POST /api/ai/ml/trends/batch
     */
    public function batchTrendPrediction(): void
    {
        $this->json(function () {
            $data = $this->getJsonInput();
            
            if (empty($data['keywords']) || !is_array($data['keywords'])) {
                return ['error' => 'keywords (array) é obrigatório'];
            }

            $service = new TrendPredictorService($this->accountId);
            $categoryId = $data['category_id'] ?? null;
            
            $results = [];
            foreach (array_slice($data['keywords'], 0, 10) as $keyword) { // Limit to 10
                $results[] = $service->predictTrend($keyword, $categoryId);
            }
            
            return [
                'success' => true,
                'predictions' => $results,
            ];
        });
    }

    // ========================================
    // 🛠️ Helpers
    // ========================================

    /**
     * JSON response wrapper
     */
    private function json(callable $callback): void
    {
        header('Content-Type: application/json');
        
        try {
            $result = $callback();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            $isDebug = (($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?? 'false') === 'true');
            echo json_encode([
                'error' => $e->getMessage(),
                'trace' => $isDebug ? $e->getTraceAsString() : null,
            ]);
        }
    }

    /**
     * Get JSON input
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }
}
