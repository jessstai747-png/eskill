<?php

declare(strict_types=1);

namespace App\Services\AI\Optimizers;

use App\Services\AI\Core\AIProviderManager;
use App\Services\AI\Core\PromptBuilder;

/**
 * Title Optimization Service
 * Generates optimized titles for Mercado Livre listings using AI
 */
class TitleOptimizer
{
    private AIProviderManager $aiProvider;
    private PromptBuilder $promptBuilder;
    private array $config;
    
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? [
            'model' => $_ENV['AI_DEFAULT_MODEL'] ?? 'gpt-4o',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ];
        
        $this->aiProvider = new AIProviderManager($this->config);
        $this->promptBuilder = new PromptBuilder();
    }
    
    /**
     * Optimize an existing title
     * 
     * @param string $currentTitle Current title of the listing
     * @param array $context Additional context (brand, category, attributes, keywords)
     * @return array Optimization result
     */
    public function optimize(string $currentTitle, array $context = []): array
    {
        $startTime = microtime(true);
        
        // Build prompt
        $prompt = $this->promptBuilder->buildTitleOptimizationPrompt($currentTitle, $context);
        
        // Get AI response
        $response = $this->aiProvider->chat([
            [
                'role' => 'system',
                'content' => $this->promptBuilder->buildSystemMessage('optimizer')
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ], [
            'temperature' => $this->config['temperature'],
            'max_tokens' => $this->config['max_tokens'],
        ]);
        
        if (isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['message'] ?? 'AI request failed',
                'original_title' => $currentTitle,
            ];
        }
        
        // Parse AI response
        $aiData = $this->parseAIResponse($response['content']);
        
        if (!$aiData) {
            return [
                'success' => false,
                'error' => 'Failed to parse AI response',
                'original_title' => $currentTitle,
                'raw_response' => $response['content'],
            ];
        }
        
        $duration = microtime(true) - $startTime;
        
        return [
            'success' => true,
            'original_title' => $currentTitle,
            'optimized_title' => $aiData['optimized_title'],
            'score' => $aiData['score'],
            'improvements' => $aiData['improvements'] ?? [],
            'keywords_used' => $aiData['keywords_used'] ?? [],
            'char_count' => $aiData['char_count'] ?? mb_strlen($aiData['optimized_title']),
            'alternatives' => $aiData['alternatives'] ?? [],
            'ai_model' => $response['model'],
            'ai_provider' => $response['provider'],
            'usage' => $response['usage'],
            'cost' => $response['cost'] ?? 0,
            'duration' => round($duration, 3),
        ];
    }
    
    /**
     * Generate title from product data (when no current title exists)
     * 
     * @param array $productData Product information
     * @return array Generation result
     */
    public function generate(array $productData): array
    {
        // Create a basic title from product data to use as starting point
        $basicTitle = $this->buildBasicTitle($productData);
        
        // Use optimize method
        return $this->optimize($basicTitle, $productData);
    }
    
    /**
     * Compare multiple title versions
     * 
     * @param array $titles Array of titles to compare
     * @param array $context Context for comparison
     * @return array Comparison results
     */
    public function compareVersions(array $titles, array $context = []): array
    {
        $results = [];
        
        foreach ($titles as $index => $title) {
            $analysis = $this->analyze($title, $context);
            $results[] = [
                'title' => $title,
                'index' => $index,
                'score' => $analysis['score'] ?? 0,
                'analysis' => $analysis,
                'issues' => $analysis['issues'] ?? [],
                'strengths' => $analysis['strengths'] ?? [],
            ];
        }
        
        // Sort by score descending
        usort($results, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $results;
    }
    
    /**
     * Analyze title quality without optimizing
     * 
     * @param string $title Title to analyze
     * @param array $context Context for analysis
     * @return array Analysis result
     */
    public function analyze(string $title, array $context = []): array
    {
        $issues = [];
        $strengths = [];
        $score = 100;
        $missingKeywords = [];
        
        $length = mb_strlen($title);
        $keywords = $context['keywords'] ?? [];
        
        // Length analysis - severe penalties for very short titles
        if ($length < 10) {
            $issues[] = 'Título muito curto';
            $score -= 50;
        } elseif ($length < 30) {
            $issues[] = 'Título muito curto';
            $score -= 25;
        } elseif ($length > 60) {
            $issues[] = 'Título muito longo';
            $score -= 10;
        } elseif ($length >= 50 && $length <= 60) {
            $strengths[] = 'Comprimento ideal (50-60 caracteres)';
        }
        
        // Keyword analysis
        if (!empty($keywords)) {
            $titleLower = mb_strtolower($title);
            $foundKeywords = 0;
            
            foreach (array_slice($keywords, 0, 5) as $keyword) {
                if (mb_strpos($titleLower, mb_strtolower($keyword)) !== false) {
                    $foundKeywords++;
                } else {
                    $missingKeywords[] = $keyword;
                }
            }
            
            if ($foundKeywords === 0) {
                $issues[] = 'Nenhuma keyword relevante encontrada';
                $score -= 20;
            } elseif ($foundKeywords <= 2) {
                $issues[] = 'Poucas keywords relevantes';
                $score -= 10;
            } else {
                $strengths[] = "{$foundKeywords} keywords relevantes incluídas";
            }
        }
        
        // Brand/Model analysis
        if (!empty($context['brand'])) {
            $brand = $context['brand'];
            if (mb_stripos($title, $brand) === false) {
                $issues[] = 'Marca não mencionada no título';
                $score -= 15;
            } else {
                $strengths[] = 'Marca mencionada';
            }
        }
        
        // Special characters check
        if (preg_match('/[^a-zA-Z0-9À-ÿ\s\/\-\|]/', $title)) {
            $issues[] = 'Contém caracteres especiais que podem ser problemáticos';
            $score -= 5;
        }
        
        // All caps check
        if ($title === mb_strtoupper($title)) {
            $issues[] = 'Todo em maiúsculas (não recomendado)';
            $score -= 10;
        }
        
        // Word count
        $wordCount = str_word_count($title);
        if ($wordCount < 4) {
            $issues[] = 'Poucas palavras (menos de 4)';
            $score -= 10;
        } elseif ($wordCount >= 6 && $wordCount <= 10) {
            $strengths[] = 'Número adequado de palavras';
        }
        
        return [
            'title' => $title,
            'score' => max(0, min(100, $score)),
            'char_count' => $length,
            'word_count' => $wordCount,
            'issues' => $issues,
            'strengths' => $strengths,
            'missing_keywords' => $missingKeywords,
            'grade' => $this->getGrade($score),
        ];
    }
    
    /**
     * Validate title against ML rules
     * 
     * @param string $title Title to validate
     * @return array Validation result
     */
    public function validate(string $title): array
    {
        $errors = [];
        $warnings = [];
        
        $length = mb_strlen($title);
        
        // Check length
        if ($length > 60) {
            $errors[] = 'Título excede 60 caracteres (limite ML)';
        } elseif ($length < 10) {
            $errors[] = 'Título muito curto (mínimo 10 caracteres)';
        }
        
        // Check for forbidden characters
        $forbiddenPatterns = [
            '/!{2,}/' => 'Múltiplos pontos de exclamação',
            '/\?{2,}/' => 'Múltiplos pontos de interrogação',
            '/[<>{}]/' => 'Caracteres HTML/especiais',
            '/FREE|GRATIS|GRÁTIS/i' => 'Palavras proibidas (FREE/GRATIS)',
            '/MELHOR PREÇO|MENOR PREÇO/i' => 'Claims de preço',
            '/COMPRE JÁ|COMPRE AGORA/i' => 'Call to action proibido',
        ];
        
        foreach ($forbiddenPatterns as $pattern => $message) {
            if (preg_match($pattern, $title)) {
                $errors[] = $message;
            }
        }
        
        // Check for excessive repetition
        $words = str_word_count(mb_strtolower($title), 1);
        $wordCounts = array_count_values($words);
        foreach ($wordCounts as $word => $count) {
            if ($count > 3 && mb_strlen($word) > 3) {
                $warnings[] = "Palavra '{$word}' repetida {$count}x";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'title' => $title,
            'char_count' => $length,
        ];
    }
    
    /**
     * Calculate score based on analysis factors
     * 
     * @param array $analysis Analysis data
     * @return float Score between 0-100
     */
    public function calculateScore(array $analysis): float
    {
        $score = 100.0;
        
        // Length factor (15 points)
        $charCount = $analysis['char_count'] ?? 0;
        if ($charCount >= 45 && $charCount <= 60) {
            // Perfect length
        } elseif ($charCount >= 35 && $charCount < 45) {
            $score -= 5;
        } elseif ($charCount < 35) {
            $score -= 15;
        } elseif ($charCount > 60) {
            $score -= 10;
        }
        
        // Brand presence (15 points)
        if (!($analysis['brand_present'] ?? true)) {
            $score -= 15;
        }
        
        // Keywords (20 points)
        $keywordsFound = $analysis['keywords_found'] ?? [];
        $missingKeywords = $analysis['missing_keywords'] ?? [];
        $totalKeywords = count($keywordsFound) + count($missingKeywords);
        if ($totalKeywords > 0) {
            $keywordScore = (count($keywordsFound) / $totalKeywords) * 20;
            $score -= (20 - $keywordScore);
        }
        
        // Invalid characters (10 points)
        if ($analysis['has_invalid_chars'] ?? false) {
            $score -= 10;
        }
        
        // Numbers presence (5 points for product codes/models)
        if ($analysis['has_numbers'] ?? false) {
            $score += 2;
        }
        
        return max(0.0, min(100.0, $score));
    }
    
    /**
     * Build a basic title from product data
     * 
     * @param array $productData
     * @return string
     */
    private function buildBasicTitle(array $productData): string
    {
        $parts = [];
        
        if (!empty($productData['brand'])) {
            $parts[] = $productData['brand'];
        }
        
        if (!empty($productData['model'])) {
            $parts[] = $productData['model'];
        }
        
        if (!empty($productData['category'])) {
            $parts[] = $productData['category'];
        }
        
        // Add key attributes
        if (!empty($productData['attributes'])) {
            foreach (array_slice($productData['attributes'], 0, 2) as $attr) {
                $value = $attr['value_name'] ?? $attr['value'] ?? '';
                if ($value && mb_strlen($value) < 20) {
                    $parts[] = $value;
                }
            }
        }
        
        return implode(' ', $parts) ?: 'Produto';
    }
    
    /**
     * Parse AI JSON response
     * 
     * @param string $content
     * @return array|null
     */
    private function parseAIResponse(string $content): ?array
    {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }
        
        // Try direct JSON parse
        $data = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && isset($data['optimized_title'])) {
            return $data;
        }
        
        // Fallback: try to find JSON object in text
        if (preg_match('/\{[\s\S]*"optimized_title"[\s\S]*\}/m', $content, $matches)) {
            $data = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data;
            }
        }
        
        log_warning('Falha ao parsear resposta AI para título', [
            'service' => 'TitleOptimizer',
            'response_preview' => substr($content, 0, 200),
        ]);
        return null;
    }
    
    /**
     * Get letter grade from score
     * 
     * @param int $score
     * @return string
     */
    private function getGrade(int $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 85) return 'A';
        if ($score >= 80) return 'B+';
        if ($score >= 75) return 'B';
        if ($score >= 70) return 'C+';
        if ($score >= 65) return 'C';
        if ($score >= 60) return 'D';
        return 'F';
    }
}
