<?php

namespace App\Services\AI\SEO;

use App\Services\MercadoLivreClient;
use App\Services\AIService;

class BacklinkAnalyzer
{
    private $accountId;
    private $mlClient;
    private $aiService;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        // Using existing AIService or a lightweight prompt handler
        $this->aiService = new AIService(); 
    }

    public function analyzeOpportunities(string $itemId): array
    {
        try {
            // 1. Get Item Data
            $item = $this->mlClient->get("/items/{$itemId}");
            $title = $item['title'];
            $category = $this->getCategoryName($item['category_id']);
            
            // 2. Generate Prompt for Backlinks
            $prompt = $this->buildBacklinkPrompt($title, $category);
            
            $analysis = $this->getAIAnalysis($prompt);
            
            return [
                'success' => true,
                'item_title' => $title,
                'category' => $category,
                'analysis' => $analysis
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getCategoryName(string $categoryId): string
    {
        try {
            $cat = $this->mlClient->get("/categories/{$categoryId}");
            return $cat['name'];
        } catch (\Exception $e) {
            return '';
        }
    }

    private function buildBacklinkPrompt(string $title, string $category): string
    {
        return "Act as an SEO Expert. I have a product titled '{$title}' in the '{$category}' category on Mercado Livre.
Identify 5 high-potential backlink opportunities to drive external traffic to this product.
Return a JSON object with:
{
  \"opportunities\": [{\"type\": \"\", \"niche\": \"\", \"pitch\": \"\"}],
  \"strategy_score\": number,
  \"difficulty\": \"low|medium|high\"
}
Focus on Brazilian market context. Return only JSON.";
    }

    private function getAIAnalysis(string $prompt): array
    {
        $response = $this->aiService->generate($prompt);
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new \Exception('Resposta inválida do serviço de IA para backlinks');
        }
        return $decoded;
    }
}
