<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SEO\HiddenAttributesDetector;
use App\Services\SEO\SearchCoverageService;
use App\Services\SEO\CompatibilityService;
use App\Services\SEO\SynonymExpansionService;

/**
 * @deprecated Functionality consolidated in SEOKillerController.
 * API endpoints remain functional for backward compatibility.
 */
class SeoCoverageController extends BaseController
{
    private HiddenAttributesDetector $hiddenDetector;
    private SearchCoverageService $coverageService;
    private CompatibilityService $compatibilityService;

    public function __construct()
    {
        $this->hiddenDetector = new HiddenAttributesDetector();
        $this->coverageService = new SearchCoverageService();
        $this->compatibilityService = new CompatibilityService();
    }
    
    /**
     * Helper para obter input JSON
     */
    protected function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        return json_decode($input, true) ?? [];
    }

    /**
     * Helper para respostas JSON
     */
    protected function jsonResponse(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * GET /api/seo/hidden-fields/{itemId}
     */
    public function detectHiddenFields(string $itemId): void
    {
        try {
            $fields = $this->hiddenDetector->detectKeywordFields($itemId);
            $this->jsonResponse(['success' => true, 'data' => $fields]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/seo/hidden-fields/generate
     */
    public function generateHiddenFieldValues(): void
    {
        try {
            $input = $this->getJsonInput();
            $this->ensureHiddenFieldInput($input);

            $results = $this->buildHiddenFieldResults($input);

            $this->jsonResponse(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/seo/hidden-fields/apply
     */
    public function applyHiddenFields(): void
    {
        try {
            $input = $this->getJsonInput();
            if (empty($input['item_id']) || empty($input['fields'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Item ID and Fields required'], 400);
            }

            $userId = $_SESSION['user_id'] ?? null;
            $result = $this->hiddenDetector->applyHiddenFields($input['item_id'], $input['fields'], $userId);
            $this->jsonResponse(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/seo/coverage/{itemId}
     */
    public function analyzeCoverage(string $itemId): void
    {
        try {
            $client = new \App\Services\MercadoLivreClient(); 
            // Fix: Use generic get method since getItem does not exist
            $item = $client->get("/items/{$itemId}");
            
            if (!$item || isset($item['error'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Item not found'], 404);
            }

            $analysis = $this->coverageService->analyzeCoverage($item);
            $this->jsonResponse(['success' => true, 'data' => $analysis]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/seo/coverage/gaps/{itemId}
     */
    public function getCoverageGaps(string $itemId): void
    {
        try {
            $client = new \App\Services\MercadoLivreClient(); 
            // Fix: Use generic get method
            $item = $client->get("/items/{$itemId}");
            
            if (!$item || isset($item['error'])) {
                $this->jsonResponse(['success' => false, 'error' => 'Item not found'], 404);
            }

            $analysis = $this->coverageService->analyzeCoverage($item);
            $this->jsonResponse(['success' => true, 'data' => $analysis['gaps']]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/seo/compatibility/{categoryId}
     */
    public function listCompatibility(string $categoryId): void
    {
        try {
            $list = $this->compatibilityService->getCompatibilityList($categoryId);
            $this->jsonResponse(['success' => true, 'data' => $list]);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function ensureHiddenFieldInput(array $input): void
    {
        if (empty($input['title']) && empty($input['item'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Title or Item required'], 400);
        }
    }

    private function buildHiddenFieldResults(array $input): array
    {
        $results = [];

        if (!empty($input['generate_keywords'])) {
            $synonyms = $this->resolveSynonymsForHiddenFields($input);
            $results['keywords_value'] = $this->hiddenDetector->generateKeywordsFieldValue(
                $input['title'] ?? '',
                $synonyms
            );
        }

        if (!empty($input['item'])) {
            $results['mpn_value'] = $this->hiddenDetector->generateMPNValue($input['item']);
            $results['line_value'] = $this->hiddenDetector->generateLineValue($input['item']);
        }

        return $results;
    }

    private function resolveSynonymsForHiddenFields(array $input): array
    {
        if (!empty($input['synonyms'])) {
            return $input['synonyms'];
        }

        if (!empty($input['category_id']) && !empty($input['title'])) {
            $synonymService = new SynonymExpansionService();
            $synonymData = $synonymService->expand($input['title'], $input['category_id']);
            return $synonymData['synonyms'] ?? $synonymData;
        }

        return [];
    }
}
