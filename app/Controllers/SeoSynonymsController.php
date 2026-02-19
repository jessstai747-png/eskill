<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\SEO\SynonymExpansionService;
use App\Services\SEO\SemanticScoreService;

/**
 * @deprecated Functionality consolidated in SEOKillerController.
 * API endpoints remain functional for backward compatibility.
 */
class SeoSynonymsController
{
    private SynonymExpansionService $synonymExpansionService;
    private SemanticScoreService $semanticScoreService;
    private Request $request;

    public function __construct()
    {
        // In a real application, these services would be injected by a dependency injection container.
        $this->request = new Request();
        $this->synonymExpansionService = new SynonymExpansionService();
        $this->semanticScoreService = new SemanticScoreService();
    }

    public function getHierarchy(string $categoryId): void
    {
        // Implementation for getHierarchy
        $data = $this->synonymExpansionService->getHierarchy($categoryId);
        $this->jsonResponse($data);
    }

    public function expand(): void
    {
        // Implementation for expand
        $title = $this->request->post('title', '') ?? '';
        $categoryId = $this->request->post('categoryId', '') ?? '';
        $data = $this->synonymExpansionService->expand($title, $categoryId);
        $this->jsonResponse($data);
    }

    public function generateModel(): void
    {
        // Implementation for generateModel
        $title = $this->request->post('title', '') ?? '';
        $categoryId = $this->request->post('categoryId', '') ?? '';
        $data = $this->synonymExpansionService->generateOptimizedModel($title, $categoryId);
        $this->jsonResponse(is_array($data) ? $data : ['model' => $data]);
    }

    public function calculateScore(): void
    {
        // Implementation for calculateScore
        $word = $this->request->post('word', '') ?? '';
        $title = $this->request->post('title', '') ?? '';
        $categoryId = $this->request->post('categoryId', '') ?? '';
        $data = $this->semanticScoreService->calculateScore($word, $title, $categoryId);
        $this->jsonResponse(['score' => $data]);
    }

    public function getContexts(string $categoryId): void
    {
        // Implementation for getContexts
        $data = $this->semanticScoreService->getContexts($categoryId);
        $this->jsonResponse($data);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
    }
}