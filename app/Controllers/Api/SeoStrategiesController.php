<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Services\SEO\SEOStrategiesEngine;
use App\Services\SEO\SEOMonitoringService;
use App\Services\SEO\SynonymExpansionService;
use App\Services\SEO\SemanticScoreService;
use App\Services\SEO\KeywordDistributionService;
use App\Services\SEO\KeywordSourceService;
use App\Services\SEO\DescriptionBuilderService;
use App\Services\SEO\ContextInjectorService;
use App\Services\SEO\LongTailGeneratorService;
use App\Services\SEO\SearchCoverageService;
use App\Services\SEO\CompatibilityService;
use App\Services\SEO\HiddenAttributesDetector;
use App\Services\KeywordResearchService;
use App\Services\MercadoLivreClient;
use App\Services\AI\SEO\SEOScoreCalculator;
use App\Database;
use PDO;

class SeoStrategiesController
{
    private SEOStrategiesEngine $engine;
    private SEOMonitoringService $monitoring;
    private SynonymExpansionService $synonymService;
    private SemanticScoreService $semanticService;
    private KeywordDistributionService $keywordDistService;
    private KeywordSourceService $keywordSourceService;
    private DescriptionBuilderService $descBuilderService;
    private ContextInjectorService $contextService;
    private LongTailGeneratorService $longTailService;
    private SearchCoverageService $coverageService;
    private CompatibilityService $compatibilityService;
    private HiddenAttributesDetector $hiddenFieldsService;
    private KeywordResearchService $keywordResearchService;
    private MercadoLivreClient $mlClient;
    private ?int $accountId;
    private PDO $db;

    public function __construct()
    {
        $this->accountId = $_SESSION['active_ml_account_id'] ?? $_SESSION['account_id'] ?? null;
        $this->db = Database::getInstance();
        $this->engine = new SEOStrategiesEngine($this->accountId);
        $this->monitoring = new SEOMonitoringService($this->accountId);
        $this->synonymService = new SynonymExpansionService($this->accountId);
        $this->semanticService = new SemanticScoreService($this->accountId);
        $this->keywordDistService = new KeywordDistributionService($this->accountId);
        $this->keywordSourceService = new KeywordSourceService($this->accountId);
        $this->descBuilderService = new DescriptionBuilderService($this->accountId);
        $this->contextService = new ContextInjectorService($this->accountId);
        $this->longTailService = new LongTailGeneratorService($this->accountId);
        $this->coverageService = new SearchCoverageService($this->accountId);
        $this->compatibilityService = new CompatibilityService($this->accountId);
        $this->hiddenFieldsService = new HiddenAttributesDetector($this->accountId);
        $this->keywordResearchService = new KeywordResearchService($this->accountId);
        $this->mlClient = new MercadoLivreClient($this->accountId);
    }

    // Phase 1: Synonym Expansion and Semantic Scoring

    public function getSynonyms(string $categoryId): void
    {
        try {
            $hierarchy = $this->synonymService->getHierarchy($categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $hierarchy,
                'message' => 'Hierarquia de sinônimos retornada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function expandSynonyms(): void
    {
        try {
            $input = $this->getRequestData();
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';

            if (empty($title) || empty($categoryId)) {
                throw new \Exception('Título e ID da categoria são obrigatórios');
            }

            $synonyms = $this->synonymService->expand($title, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $synonyms,
                'message' => 'Sinônimos expandidos com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateModel(): void
    {
        try {
            $input = $this->getRequestData();
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';

            if (empty($title) || empty($categoryId)) {
                throw new \Exception('Título e ID da categoria são obrigatórios');
            }

            $model = $this->synonymService->generateOptimizedModel($title, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $model,
                'message' => 'Campo modelo otimizado gerado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function calculateScore(): void
    {
        try {
            $input = $this->getRequestData();
            $word = $input['word'] ?? '';
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';

            if (empty($word) || empty($title) || empty($categoryId)) {
                throw new \Exception('Palavra, título e ID da categoria são obrigatórios');
            }

            $score = $this->semanticService->calculateScore($word, $title, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => ['word' => $word, 'score' => $score],
                'message' => 'Score semântico calculado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function getContexts(string $categoryId): void
    {
        try {
            $contexts = $this->contextService->detectApplicableContexts(['category_id' => $categoryId]);

            $this->sendResponse([
                'success' => true,
                'data' => $contexts,
                'message' => 'Contextos retornados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Phase 2: Keyword Distribution

    public function distributeKeywords(): void
    {
        try {
            $input = $this->getRequestData();
            $item = $input['item'] ?? [];
            $categoryId = $input['category_id'] ?? '';

            if (empty($item) || empty($categoryId)) {
                throw new \Exception('Item e ID da categoria são obrigatórios');
            }

            $distribution = $this->keywordDistService->distribute($item, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $distribution,
                'message' => 'Distribuição de keywords realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function classifyKeywords(): void
    {
        try {
            $input = $this->getRequestData();
            $keywords = $input['keywords'] ?? [];
            $categoryId = $input['category_id'] ?? '';

            if (empty($keywords) || empty($categoryId)) {
                throw new \Exception('Lista de keywords e ID da categoria são obrigatórios');
            }

            $classification = $this->keywordDistService->classifyKeywords($keywords, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $classification,
                'message' => 'Classificação de keywords realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function fetchKeywords(string $categoryId): void
    {
        try {
            $keywords = $this->keywordSourceService->getKeywords($categoryId, '');

            $this->sendResponse([
                'success' => true,
                'data' => $keywords,
                'message' => 'Keywords retornadas com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateKeywords(string $categoryId): void
    {
        try {
            $input = $this->getRequestData();
            $baseKeyword = $input['base_keyword'] ?? '';

            if (empty($baseKeyword)) {
                throw new \Exception('Keyword base é obrigatória');
            }

            $keywords = $this->keywordSourceService->getKeywords($categoryId, $baseKeyword);

            $this->sendResponse([
                'success' => true,
                'data' => $keywords,
                'message' => 'Keywords geradas com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function validateDensity(): void
    {
        try {
            $input = $this->getRequestData();
            $text = $input['text'] ?? '';
            $keywords = $input['keywords'] ?? [];

            if (empty($text) || empty($keywords)) {
                throw new \Exception('Texto e keywords são obrigatórios');
            }

            $validation = $this->keywordDistService->validateDensity($text, $keywords);

            $this->sendResponse([
                'success' => true,
                'data' => $validation,
                'message' => 'Validação de densidade realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function calculateDensity(): void
    {
        try {
            $input = $this->getRequestData();
            $text = $input['text'] ?? '';
            $keyword = $input['keyword'] ?? '';

            if (empty($text) || empty($keyword)) {
                throw new \Exception('Texto e keyword são obrigatórios');
            }

            $density = $this->keywordDistService->calculateDensity($text, $keyword);

            $this->sendResponse([
                'success' => true,
                'data' => ['keyword' => $keyword, 'density' => $density],
                'message' => 'Densidade calculada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function getWeights(): void
    {
        try {
            $weights = $this->keywordDistService->getFieldWeights();

            $this->sendResponse([
                'success' => true,
                'data' => $weights,
                'message' => 'Pesos de campos retornados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function invalidateCache(string $categoryId): void
    {
        try {
            $this->keywordSourceService->invalidateCache($categoryId);

            $this->sendResponse([
                'success' => true,
                'message' => 'Cache invalidado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Phase 3: Description Building

    public function buildDescription(): void
    {
        try {
            $input = $this->getRequestData();
            $item = $input['item'] ?? [];
            $distribution = $input['distribution'] ?? [];

            if (empty($item)) {
                throw new \Exception('Item é obrigatório');
            }

            $description = $this->descBuilderService->build($item, $distribution);

            $this->sendResponse([
                'success' => true,
                'data' => $description,
                'message' => 'Descrição construída com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateBlock(): void
    {
        try {
            $input = $this->getRequestData();
            $blockType = $input['block_type'] ?? '';
            $item = $input['item'] ?? [];
            $keywords = $input['keywords'] ?? [];

            if (empty($blockType) || empty($item)) {
                throw new \Exception('Tipo de bloco e item são obrigatórios');
            }

            $block = $this->descBuilderService->generateBlock($blockType, $item, $keywords);

            $this->sendResponse([
                'success' => true,
                'data' => $block,
                'message' => 'Bloco gerado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateFAQ(): void
    {
        try {
            $input = $this->getRequestData();
            $item = $input['item'] ?? [];
            $keywords = $input['keywords'] ?? [];

            if (empty($item)) {
                throw new \Exception('Item é obrigatório');
            }

            $faqBlock = $this->descBuilderService->generateBlock('faq', $item, $keywords);

            $this->sendResponse([
                'success' => true,
                'data' => $faqBlock,
                'message' => 'FAQ gerado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function validateDescription(): void
    {
        try {
            $input = $this->getRequestData();
            $description = $input['description'] ?? '';

            if (empty($description)) {
                throw new \Exception('Descrição é obrigatória');
            }

            $validation = $this->descBuilderService->validateDescription($description);

            $this->sendResponse([
                'success' => true,
                'data' => $validation,
                'message' => 'Validação de descrição realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateLongTail(): void
    {
        try {
            $input = $this->getRequestData();
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';

            if (empty($title) || empty($categoryId)) {
                throw new \Exception('Título e ID da categoria são obrigatórios');
            }

            $longTailKeywords = $this->longTailService->generate($title, $categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $longTailKeywords,
                'message' => 'Keywords long tail geradas com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Phase 4: Hidden Fields and Coverage

    public function getHiddenFields(string $itemId): void
    {
        try {
            $hiddenFields = $this->hiddenFieldsService->detectKeywordFields($itemId);

            $this->sendResponse([
                'success' => true,
                'data' => $hiddenFields,
                'message' => 'Campos ocultos detectados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function generateHiddenFields(): void
    {
        try {
            $input = $this->getRequestData();
            $title = $input['title'] ?? '';
            $categoryId = $input['category_id'] ?? '';
            $synonyms = $input['synonyms'] ?? [];

            if (empty($title)) {
                throw new \Exception('Título é obrigatório');
            }

            if (empty($synonyms) && !empty($categoryId)) {
                $synonymData = $this->synonymService->expand($title, $categoryId);
                $synonyms = $this->flattenSynonymWords($synonymData);
            }

            $generatedValues = [
                'KEYWORDS' => $this->hiddenFieldsService->generateKeywordsFieldValue($title, $synonyms)
            ];

            if (!empty($input['item'])) {
                $generatedValues['MPN'] = $this->hiddenFieldsService->generateMPNValue($input['item']);
                $generatedValues['LINE'] = $this->hiddenFieldsService->generateLineValue($input['item']);
            }

            $this->sendResponse([
                'success' => true,
                'data' => $generatedValues,
                'message' => 'Valores para campos ocultos gerados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function applyHiddenFields(): void
    {
        try {
            $input = $this->getRequestData();
            $itemId = $input['item_id'] ?? '';
            $fields = $input['fields'] ?? [];

            if (empty($itemId) || empty($fields)) {
                throw new \Exception('ID do item e campos são obrigatórios');
            }

            $userId = $_SESSION['user_id'] ?? null;
            $result = $this->hiddenFieldsService->applyHiddenFields($itemId, $fields, $userId);

            $this->sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Campos ocultos aplicados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function getCoverage(string $itemId): void
    {
        try {
            $item = $this->mlClient->getItemDetails($itemId);
            $coverage = $this->coverageService->analyzeCoverage($item);

            $this->sendResponse([
                'success' => true,
                'data' => $coverage,
                'message' => 'Análise de cobertura realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function getGaps(string $itemId): void
    {
        try {
            $item = $this->mlClient->getItemDetails($itemId);
            $coverage = $this->coverageService->analyzeCoverage($item);
            $gaps = $coverage['gaps'] ?? [];

            $this->sendResponse([
                'success' => true,
                'data' => $gaps,
                'message' => 'Gaps de cobertura identificados com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    public function getCompatibility(string $categoryId): void
    {
        try {
            $compatibilityList = $this->compatibilityService->getCompatibilityList($categoryId);

            $this->sendResponse([
                'success' => true,
                'data' => $compatibilityList,
                'message' => 'Lista de compatibilidade retornada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Phase 5: Full SEO Engine (already implemented above)

    // Otimização completa
    public function optimizeFull(string $itemId): void
    {
        try {
            $result = $this->engine->optimizeFull($itemId);

            $this->sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Otimização completa realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Otimização parcial
    public function optimizePartial(string $itemId): void
    {
        try {
            // Get strategies from request body
            $input = $this->getRequestData();
            $strategies = $input['strategies'] ?? [];

            $result = $this->engine->optimizePartial($itemId, $strategies);

            $this->sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Otimização parcial realizada com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Preview sem aplicar
    public function preview(string $itemId): void
    {
        try {
            $result = $this->engine->previewOptimization($itemId);

            $this->sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Preview de otimização gerado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Aplicar otimizações
    public function apply(string $itemId): void
    {
        try {
            // Get optimizations from request body
            $input = $this->getRequestData();
            $optimizations = $input['optimizations'] ?? [];

            $result = $this->engine->applyOptimization($itemId, $optimizations);

            $this->sendResponse([
                'success' => true,
                'data' => $result,
                'message' => 'Otimizações aplicadas com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Dashboard de métricas
    public function getMetrics(string $itemId): void
    {
        try {
            $metrics = $this->monitoring->collectMetrics($itemId);

            $this->sendResponse([
                'success' => true,
                'data' => $metrics,
                'message' => 'Métricas coletadas com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Histórico de otimizações
    public function history(string $itemId): void
    {
        try {
            if ($itemId === '') {
                $this->sendErrorResponse('Item ID é obrigatório', 400);
                return;
            }

            if (empty($this->accountId)) {
                $this->sendErrorResponse('Conta não autenticada', 401);
                return;
            }

            $historyRows = $this->fetchOptimizationHistory($itemId);
            $history = $this->formatOptimizationHistory($historyRows);

            $this->sendResponse([
                'success' => true,
                'data' => [
                    'item_id' => $itemId,
                    'history' => $history
                ],
                'message' => 'Histórico de otimizações carregado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    // Agendar monitoramento
    public function scheduleMonitoring(string $itemId): void
    {
        try {
            // Get interval from request body
            $input = $this->getRequestData();
            $intervalDays = $input['interval_days'] ?? 7;

            $this->monitoring->scheduleCheck($itemId, $intervalDays);

            $this->sendResponse([
                'success' => true,
                'data' => [
                    'item_id' => $itemId,
                    'scheduled_interval' => $intervalDays
                ],
                'message' => 'Monitoramento agendado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage());
        }
    }

    // Get SEO score for an item
    public function getScore(string $itemId): void
    {
        try {
            if ($itemId === '') {
                $this->sendErrorResponse('Item ID é obrigatório', 400);
                return;
            }

            if (empty($this->accountId)) {
                $this->sendErrorResponse('Conta não autenticada', 401);
                return;
            }

            $calculator = new SEOScoreCalculator($this->accountId);
            $scoreData = $calculator->calculateScore($itemId);

            if (!empty($scoreData['error'])) {
                $status = (int)($scoreData['status'] ?? 500);
                $this->sendErrorResponse($scoreData['error'], $status ?: 500);
                return;
            }

            $this->sendResponse([
                'success' => true,
                'data' => [
                    'item_id' => $itemId,
                    'score' => $scoreData['overall_score'] ?? 0,
                    'grade' => $scoreData['grade'] ?? null,
                    'breakdown' => $scoreData['breakdown'] ?? [],
                    'recommendations' => $scoreData['recommendations'] ?? [],
                    'benchmarks' => $scoreData['benchmarks'] ?? [],
                    'last_updated' => date('Y-m-d H:i:s')
                ],
                'message' => 'Score SEO retornado com sucesso'
            ]);
        } catch (\Exception $e) {
            $this->sendErrorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Helper method to get request data
     */
    private function getRequestData(): array
    {
        $data = filter_input_array(INPUT_POST) ?? [];
        $input = file_get_contents('php://input');
        if (is_string($input) && $input !== '') {
            $json = json_decode($input, true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }

        return $data;
    }

    private function flattenSynonymWords(array $synonymData): array
    {
        $words = [];

        foreach ($synonymData as $entry) {
            if (is_array($entry)) {
                if (isset($entry['words']) && is_array($entry['words'])) {
                    $words = array_merge($words, $entry['words']);
                } elseif (isset($entry['word'])) {
                    $words[] = $entry['word'];
                }
            } elseif (is_string($entry)) {
                $words[] = $entry;
            }
        }

        $unique = [];
        foreach ($words as $word) {
            $value = trim($word);
            if ($value === '') {
                continue;
            }
            $key = mb_strtolower($value);
            if (!isset($unique[$key])) {
                $unique[$key] = $value;
            }
        }

        return array_values($unique);
    }

    private function fetchOptimizationHistory(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                id,
                optimization_type,
                score_before,
                score_after,
                views_before,
                views_after,
                sales_before,
                sales_after,
                status,
                applied_at,
                created_at
            FROM seo_optimizations
            WHERE item_id = :item_id
            ORDER BY COALESCE(applied_at, created_at) DESC, id DESC
            LIMIT 50
        ");

        $stmt->execute(['item_id' => $itemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function formatOptimizationHistory(array $rows): array
    {
        return array_map(fn ($row) => $this->formatHistoryRow($row), $rows);
    }

    private function formatHistoryRow(array $row): array
    {
        $values = $this->applyDefaults($row, [
            'score_before' => 0,
            'score_after' => 0,
            'views_before' => 0,
            'views_after' => 0,
            'sales_before' => 0,
            'sales_after' => 0,
            'applied_at' => $row['created_at'] ?? null,
        ]);

        $date = $values['applied_at'] ?? $values['created_at'];
        $scoreBefore = (float)$values['score_before'];
        $scoreAfter = (float)$values['score_after'];
        $viewsBefore = (int)$values['views_before'];
        $viewsAfter = (int)$values['views_after'];
        $salesBefore = (int)$values['sales_before'];
        $salesAfter = (int)$values['sales_after'];

        return [
            'id' => (int)$row['id'],
            'date' => $date,
            'optimization_type' => $row['optimization_type'],
            'status' => $row['status'],
            'score_before' => $scoreBefore,
            'score_after' => $scoreAfter,
            'score_improvement' => $scoreAfter - $scoreBefore,
            'views_before' => $viewsBefore,
            'views_after' => $viewsAfter,
            'views_increase' => $viewsAfter - $viewsBefore,
            'sales_before' => $salesBefore,
            'sales_after' => $salesAfter,
            'sales_increase' => $salesAfter - $salesBefore,
        ];
    }

    private function applyDefaults(array $data, array $defaults): array
    {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    /**
     * Helper method to send success response
     */
    private function sendResponse(array $data): void
    {
        header('Content-Type: application/json');
        http_response_code($data['success'] ? 200 : 400);
        echo json_encode($data);
    }

    /**
     * Helper method to send error response
     */
    private function sendErrorResponse(string $message, int $status = 400): void
    {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode([
            'success' => false,
            'error' => $message
        ]);
    }
}
