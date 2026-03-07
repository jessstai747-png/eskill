<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

/**
 * 📊 SEO Score Calculator v2
 * 
 * Calcula o score SEO completo de produtos
 * com fatores ponderados e benchmarks
 * 
 * @author AI Development Team
 * @version 2.0.0
 */
class SEOScoreCalculator
{
    private int $accountId;
    private PDO $db;
    
    // Weight factors (total 100%)
    const WEIGHTS = [
        'title' => 25,
        'description' => 20,
        'images' => 15,
        'attributes' => 15,
        'pricing' => 10,
        'keywords' => 10,
        'engagement' => 5,
    ];
    
    private ?CompetitorSpy $competitorSpy = null;
    private ?KeywordKiller $keywordKiller = null;
    
    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->ensureTablesExist();
    }
    
    // Lazy load dependencies to avoid overhead
    private function getCompetitorSpy(): CompetitorSpy
    {
        if (!$this->competitorSpy) {
            $this->competitorSpy = new CompetitorSpy($this->accountId);
        }
        return $this->competitorSpy;
    }
    
    private function getKeywordKiller(): KeywordKiller
    {
        if (!$this->keywordKiller) {
            $this->keywordKiller = new KeywordKiller($this->accountId);
        }
        return $this->keywordKiller;
    }
    
    /**
     * 🎯 Calcular score completo de um item
     */
    public function calculateScore(string $itemId, array $itemData = []): array
    {
        $scores = [
            'item_id' => $itemId,
            'overall_score' => 0,
            'breakdown' => [],
            'recommendations' => [],
            'benchmarks' => [],
        ];
        
        try {
            // Get item data if not provided
            if (empty($itemData)) {
                $mlClient = new \App\Services\MercadoLivreClient($this->accountId);
                $itemData = $mlClient->get("/items/{$itemId}");
            }

            if (is_array($itemData) && isset($itemData['error'], $itemData['status']) && (int)$itemData['status'] >= 400) {
                throw new \RuntimeException((string)($itemData['message'] ?? $itemData['error']), (int)$itemData['status']);
            }
            
            // Calculate each component
            $scores['breakdown']['title'] = $this->scoreTitleQuality($itemData);
            $scores['breakdown']['description'] = $this->scoreDescriptionQuality($itemData);
            $scores['breakdown']['images'] = $this->scoreImagesQuality($itemData);
            $scores['breakdown']['attributes'] = $this->scoreAttributesCompleteness($itemData);
            // Use new real scoring methods
            $scores['breakdown']['pricing'] = $this->scorePricingCompetitiveness($itemData);
            $scores['breakdown']['keywords'] = $this->scoreKeywordRelevance($itemData);
            $scores['breakdown']['engagement'] = $this->scoreEngagement($itemData);
            
            // Calculate weighted overall score
            $totalScore = 0;
            foreach ($scores['breakdown'] as $component => $data) {
                $weight = self::WEIGHTS[$component] ?? 0;
                $componentScore = $data['score'] ?? 0;
                $totalScore += ($componentScore * $weight / 100);
            }
            
            $scores['overall_score'] = round($totalScore, 1);
            $scores['grade'] = $this->getGrade($scores['overall_score']);
            
            // Generate recommendations
            $scores['recommendations'] = $this->generateRecommendations($scores['breakdown']);
            
            // Add benchmarks
            $scores['benchmarks'] = $this->getBenchmarks($itemData['category_id'] ?? null);
            
            // Save to database for historical tracking
            $this->saveScoreToDatabase($itemId, $scores);
            
        } catch (\Throwable $e) {
            $scores['error'] = $e->getMessage();
            $scores['status'] = $e->getCode() > 0 ? $e->getCode() : 500;
        }
        
        return $scores;
    }
    
    /**
     * 📝 Score de qualidade do título
     */
    private function scoreTitleQuality(array $item): array
    {
        $score = 100;
        $issues = [];
        $title = $item['title'] ?? '';
        $titleLen = mb_strlen($title);
        
        // Length check (optimal: 50-60 chars)
        if ($titleLen < 30) {
            $score -= 30;
            $issues[] = 'Título muito curto (ideal: 50-60 caracteres)';
        } elseif ($titleLen > 70) {
            $score -= 10;
            $issues[] = 'Título muito longo (pode ser cortado)';
        }
        
        // Brand check
        $brand = '';
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'BRAND') {
                $brand = $attr['value_name'] ?? '';
                break;
            }
        }
        
        if ($brand && stripos($title, $brand) === false) {
            $score -= 15;
            $issues[] = 'Marca não aparece no título';
        }
        
        // Model check
        $model = '';
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === 'MODEL') {
                $model = $attr['value_name'] ?? '';
                break;
            }
        }
        
        if ($model && stripos($title, $model) === false) {
            $score -= 10;
            $issues[] = 'Modelo não aparece no título';
        }
        
        // Keyword stuffing check
        if (preg_match('/(\b\w+\b).*\1/i', $title)) {
            $score -= 10;
            $issues[] = 'Possível repetição excessiva de palavras';
        }
        
        // Caps lock abuse
        if (preg_match('/[A-Z]{5,}/', $title)) {
            $score -= 5;
            $issues[] = 'Evite CAPS LOCK excessivo';
        }
        
        return [
            'score' => max(0, $score),
            'title' => $title,
            'length' => $titleLen,
            'issues' => $issues,
        ];
    }
    
    /**
     * 📄 Score de qualidade da descrição
     */
    private function scoreDescriptionQuality(array $item): array
    {
        $score = 100;
        $issues = [];
        $description = '';
        
        // Fetch real description from ML API
        try {
            $itemId = $item['id'] ?? '';
            if ($itemId) {
                $mlClient = new \App\Services\MercadoLivreClient($this->accountId);
                $descData = $mlClient->get("/items/{$itemId}/description");
                $description = $descData['plain_text'] ?? $descData['text'] ?? '';
            }
        } catch (\Exception $e) {
            // If we can't fetch description, use a minimal score
            $issues[] = 'Não foi possível analisar a descrição';
            return [
                'score' => 50,
                'length' => 0,
                'issues' => $issues,
            ];
        }
        
        $descLen = mb_strlen($description);
        
        // Length scoring
        if ($descLen < 100) {
            $score -= 40;
            $issues[] = 'Descrição muito curta (mínimo: 300 caracteres)';
        } elseif ($descLen < 300) {
            $score -= 20;
            $issues[] = 'Descrição poderia ser mais detalhada (ideal: 500+ caracteres)';
        }
        
        if ($descLen > 5000) {
            $score -= 10;
            $issues[] = 'Descrição muito longa (pode perder atenção do cliente)';
        }
        
        // Check for keyword presence
        $title = $item['title'] ?? '';
        $titleWords = array_filter(explode(' ', strtolower($title)), function($word) {
            return strlen($word) > 3; // Only significant words
        });
        
        $keywordCount = 0;
        foreach ($titleWords as $word) {
            if (stripos($description, $word) !== false) {
                $keywordCount++;
            }
        }
        
        $keywordDensity = count($titleWords) > 0 ? ($keywordCount / count($titleWords)) * 100 : 0;
        if ($keywordDensity < 30) {
            $score -= 15;
            $issues[] = 'Descrição não menciona palavras-chave importantes do título';
        }
        
        // Check for structured content (bullet points, paragraphs)
        $hasStructure = (substr_count($description, "\n") > 2) || 
                       (substr_count($description, '•') > 0) ||
                       (substr_count($description, '-') > 2);
        
        if (!$hasStructure && $descLen > 200) {
            $score -= 10;
            $issues[] = 'Use formatação (parágrafos, listas) para melhor legibilidade';
        }
        
        return [
            'score' => max(0, $score),
            'length' => $descLen,
            'keyword_density' => round($keywordDensity, 1),
            'has_structure' => $hasStructure,
            'issues' => $issues,
        ];
    }
    
    /**
     * 🖼️ Score de qualidade das imagens
     */
    private function scoreImagesQuality(array $item): array
    {
        $score = 100;
        $issues = [];
        $pictures = $item['pictures'] ?? [];
        $pictureCount = count($pictures);
        
        if ($pictureCount < 3) {
            $score -= 40;
            $issues[] = 'Adicione mais imagens (mínimo 3)';
        } elseif ($pictureCount < 5) {
            $score -= 20;
            $issues[] = 'Ideal ter 5+ imagens';
        }
        
        if ($pictureCount === 0) {
            $score = 0;
            $issues[] = 'CRÍTICO: Produto sem imagens';
        }
        
        return [
            'score' => max(0, $score),
            'count' => $pictureCount,
            'issues' => $issues,
        ];
    }
    
    /**
     * 🔧 Score de completude dos atributos
     */
    private function scoreAttributesCompleteness(array $item): array
    {
        $score = 100;
        $issues = [];
        
        $attributeCount = count($item['attributes'] ?? []);
        
        if ($attributeCount < 5) {
            $score -= 50;
            $issues[] = 'Preencha mais atributos (ideal: 10+)';
        } elseif ($attributeCount < 10) {
            $score -= 25;
            $issues[] = 'Bom, mas pode adicionar mais atributos';
        }
        
        return [
            'score' => max(0, $score),
            'filled' => $attributeCount,
            'issues' => $issues,
        ];
    }
    
    /**
     * 💰 Score de competitividade do preço (Real Analysis)
     */
    private function scorePricingCompetitiveness(array $item): array
    {
        return $this->getCompetitorSpy()->analyzePriceCompetitiveness($item);
    }
    
    /**
     * 🔍 Score de relevância de keywords (Real Analysis)
     */
    private function scoreKeywordRelevance(array $item): array
    {
        return $this->getKeywordKiller()->analyzeKeywordUsage($item);
    }
    
    /**
     * 📈 Score de engajamento
     */
    private function scoreEngagement(array $item): array
    {
        $score = 50; // Base score
        $sold_quantity = $item['sold_quantity'] ?? 0;
        
        if ($sold_quantity > 100) $score = 100;
        elseif ($sold_quantity > 50) $score = 90;
        elseif ($sold_quantity > 20) $score = 80;
        elseif ($sold_quantity > 10) $score = 70;
        elseif ($sold_quantity > 5) $score = 60;
        
        return [
            'score' => $score,
            'sold_quantity' => $sold_quantity,
            'issues' => $sold_quantity < 5 ? ['Produto com poucas vendas'] : [],
        ];
    }
    
    /**
     * 🏆 Get grade from score
     */
    private function getGrade(float $score): string
    {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }
    
    /**
     * 💡 Generate recommendations
     */
    private function generateRecommendations(array $breakdown): array
    {
        $recommendations = [];
        
        foreach ($breakdown as $component => $data) {
            if (!empty($data['issues'])) {
                foreach ($data['issues'] as $issue) {
                    $recommendations[] = [
                        'component' => $component,
                        'priority' => $data['score'] < 50 ? 'high' : 'medium',
                        'issue' => $issue,
                    ];
                }
            }
        }
        
        // Sort by priority
        usort($recommendations, fn($a, $b) => 
            ($a['priority'] === 'high' ? 0 : 1) <=> ($b['priority'] === 'high' ? 0 : 1)
        );
        
        return array_slice($recommendations, 0, 5); // Top 5
    }
    
    /**
     * 📊 Get benchmarks
     */
    public function getBenchmarks(?string $categoryId): array
    {
        if (!$categoryId) {
            return [
                'category_average' => null,
                'top_10_percent' => null,
                'your_rank' => 'N/A',
            ];
        }
        
        try {
            // Check if we have cached benchmarks
            $stmt = $this->db->prepare("
                SELECT average_score, top_10_percent_score, sample_size, last_updated
                FROM seo_category_benchmarks
                WHERE account_id = ? AND category_id = ?
                AND last_updated > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$this->accountId, $categoryId]);
            $cached = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cached) {
                return [
                    'category_average' => (float)$cached['average_score'],
                    'top_10_percent' => (float)$cached['top_10_percent_score'],
                    'sample_size' => (int)$cached['sample_size'],
                    'last_updated' => $cached['last_updated'],
                    'your_rank' => 'calculating...',
                ];
            }
            
            // Calculate fresh benchmarks from recent scores
            $stmt = $this->db->prepare("
                SELECT 
                    AVG(overall_score) as avg_score,
                    COUNT(*) as sample_size
                FROM seo_scores_history h
                INNER JOIN (
                    SELECT item_id, MAX(created_at) as latest
                    FROM seo_scores_history
                    WHERE account_id = ?
                    GROUP BY item_id
                ) latest ON h.item_id = latest.item_id AND h.created_at = latest.latest
                WHERE h.account_id = ?
            ");
            $stmt->execute([$this->accountId, $this->accountId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $sampleSize = (int)($stats['sample_size'] ?? 0);
            $top10Offset = max(0, floor($sampleSize * 0.1) - 1);
            $offsetSql = max(0, min(100000, (int)$top10Offset));
            
            // Get top 10% threshold
            $stmt = $this->db->prepare("
                SELECT overall_score
                FROM seo_scores_history h
                INNER JOIN (
                    SELECT item_id, MAX(created_at) as latest
                    FROM seo_scores_history
                    WHERE account_id = ?
                    GROUP BY item_id
                ) latest ON h.item_id = latest.item_id AND h.created_at = latest.latest
                WHERE h.account_id = ?
                ORDER BY overall_score DESC
                LIMIT 1 OFFSET {$offsetSql}
            ");
            $stmt->execute([$this->accountId, $this->accountId]);
            $top10 = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $avgScore = (float)($stats['avg_score'] ?? 72.5);
            $top10Score = (float)($top10['overall_score'] ?? 88.0);
            
            // Cache the results
            $stmt = $this->db->prepare("
                INSERT INTO seo_category_benchmarks 
                (account_id, category_id, average_score, top_10_percent_score, sample_size, last_updated)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    average_score = VALUES(average_score),
                    top_10_percent_score = VALUES(top_10_percent_score),
                    sample_size = VALUES(sample_size),
                    last_updated = NOW()
            ");
            $stmt->execute([$this->accountId, $categoryId, $avgScore, $top10Score, $sampleSize]);
            
            return [
                'category_average' => $avgScore,
                'top_10_percent' => $top10Score,
                'sample_size' => $sampleSize,
                'last_updated' => date('Y-m-d H:i:s'),
                'your_rank' => 'calculating...',
            ];
            
        } catch (\Exception $e) {
            log_warning('Erro no cálculo de benchmark SEO', [
                'service' => 'SEOScoreCalculator',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [
                'category_average' => 72.5,
                'top_10_percent' => 88.0,
                'your_rank' => 'N/A',
            ];
        }
    }
    
    /**
     * 🗄️ Ensure database tables exist
     */
    private function ensureTablesExist(): void
    {
        try {
            // Score history table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_scores_history (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    item_id VARCHAR(50) NOT NULL,
                    overall_score DECIMAL(5,2) NOT NULL,
                    breakdown_json TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_item_date (item_id, created_at),
                    INDEX idx_account (account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Category benchmarks table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_category_benchmarks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    category_id VARCHAR(50) NOT NULL,
                    average_score DECIMAL(5,2),
                    top_10_percent_score DECIMAL(5,2),
                    sample_size INT,
                    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_category (account_id, category_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Score alerts table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_score_alerts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    account_id INT NOT NULL,
                    item_id VARCHAR(50) NOT NULL,
                    alert_type VARCHAR(50),
                    message TEXT,
                    severity ENUM('low', 'medium', 'high'),
                    is_read BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_account_unread (account_id, is_read)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Exception $e) {
            log_error('Erro ao criar tabelas de score SEO', [
                'service' => 'SEOScoreCalculator',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 💾 Save score to database for historical tracking
     */
    private function saveScoreToDatabase(string $itemId, array $scores): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO seo_scores_history 
                (account_id, item_id, overall_score, breakdown_json, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->accountId,
                $itemId,
                $scores['overall_score'],
                json_encode($scores['breakdown'])
            ]);
            
            // Check for score degradation
            $alert = $this->checkForDegradation($itemId, $scores['overall_score']);
            if ($alert) {
                $this->saveAlert($itemId, $alert);
            }
        } catch (\Exception $e) {
            log_warning('Erro ao salvar histórico de score SEO', [
                'service' => 'SEOScoreCalculator',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 📊 Get historical scores for an item
     */
    public function getHistoricalScores(string $itemId, int $days = 30): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT overall_score, breakdown_json, created_at
                FROM seo_scores_history
                WHERE account_id = ? AND item_id = ?
                AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at ASC
            ");
            $stmt->execute([$this->accountId, $itemId, $days]);
            
            $history = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $history[] = [
                    'score' => (float)$row['overall_score'],
                    'breakdown' => json_decode($row['breakdown_json'], true),
                    'date' => $row['created_at']
                ];
            }
            
            return [
                'success' => true,
                'item_id' => $itemId,
                'period_days' => $days,
                'history' => $history,
                'trend' => $this->calculateTrend($history)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 📈 Calculate score trend
     */
    private function calculateTrend(array $history): array
    {
        if (count($history) < 2) {
            return [
                'direction' => 'stable',
                'change' => 0,
                'change_percent' => 0
            ];
        }
        
        $firstScore = $history[0]['score'];
        $lastScore = $history[count($history) - 1]['score'];
        $change = $lastScore - $firstScore;
        $changePercent = $firstScore > 0 ? ($change / $firstScore) * 100 : 0;
        
        $direction = 'stable';
        if ($change > 2) $direction = 'improving';
        elseif ($change < -2) $direction = 'declining';
        
        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'change_percent' => round($changePercent, 2),
            'first_score' => $firstScore,
            'last_score' => $lastScore
        ];
    }
    
    /**
     * ⚠️ Check for score degradation
     */
    private function checkForDegradation(string $itemId, float $currentScore): ?array
    {
        try {
            // Get last score
            $stmt = $this->db->prepare("
                SELECT overall_score
                FROM seo_scores_history
                WHERE account_id = ? AND item_id = ?
                ORDER BY created_at DESC
                LIMIT 1 OFFSET 1
            ");
            $stmt->execute([$this->accountId, $itemId]);
            $last = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$last) {
                return null; // First score, no comparison
            }
            
            $lastScore = (float)$last['overall_score'];
            $drop = $lastScore - $currentScore;
            
            // Alert if dropped more than 10 points
            if ($drop > 10) {
                return [
                    'type' => 'score_degradation',
                    'message' => "Score caiu de {$lastScore} para {$currentScore} (-{$drop} pontos)",
                    'severity' => $drop > 20 ? 'high' : 'medium'
                ];
            }
            
            return null;
        } catch (\Exception $e) {
            log_warning('Erro ao verificar degradação de score', [
                'service' => 'SEOScoreCalculator',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    /**
     * 🚨 Save alert to database
     */
    private function saveAlert(string $itemId, array $alert): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO seo_score_alerts 
                (account_id, item_id, alert_type, message, severity, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $this->accountId,
                $itemId,
                $alert['type'],
                $alert['message'],
                $alert['severity']
            ]);
        } catch (\Exception $e) {
            log_warning('Erro ao salvar alerta de score SEO', [
                'service' => 'SEOScoreCalculator',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 🔔 Get unread alerts
     */
    public function getUnreadAlerts(int $limit = 10): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT id, item_id, alert_type, message, severity, created_at
                FROM seo_score_alerts
                WHERE account_id = ? AND is_read = FALSE
                ORDER BY created_at DESC
                LIMIT {$limitSql}
            ");
            $stmt->execute([$this->accountId]);
            
            return [
                'success' => true,
                'alerts' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 🆚 Compare item score with category average
     */
    public function compareWithCategoryAverage(string $itemId, string $categoryId): array
    {
        try {
            // Get current item score
            $itemScore = $this->calculateScore($itemId);
            
            // Get category benchmarks
            $benchmarks = $this->getBenchmarks($categoryId);
            
            $currentScore = $itemScore['overall_score'];
            $categoryAvg = $benchmarks['category_average'];
            $top10 = $benchmarks['top_10_percent'];
            
            $vsAverage = $currentScore - $categoryAvg;
            $vsTop10 = $currentScore - $top10;
            
            return [
                'success' => true,
                'your_score' => $currentScore,
                'category_average' => $categoryAvg,
                'top_10_percent' => $top10,
                'vs_average' => round($vsAverage, 2),
                'vs_top_10' => round($vsTop10, 2),
                'rank_estimate' => $this->estimateRank($currentScore, $categoryAvg, $top10)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 🏅 Estimate rank based on score
     */
    private function estimateRank(float $score, float $avg, float $top10): string
    {
        if ($score >= $top10) return 'Top 10%';
        if ($score >= $avg + 5) return 'Above Average';
        if ($score >= $avg - 5) return 'Average';
        return 'Below Average';
    }
}
