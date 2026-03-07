<?php

declare(strict_types=1);

namespace App\Services\AI\Testing;

use App\Database;

/**
 * A/B Testing Framework for Optimizations
 * Tests different optimization approaches and tracks performance
 */
class ABTestingService
{
    private \PDO $db;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->createTestsTable();
    }
    
    /**
     * Create A/B tests table
     */
    private function createTestsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS ai_ab_tests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            test_name VARCHAR(255) NOT NULL,
            item_id VARCHAR(50) NOT NULL,
            variant_a JSON NOT NULL,
            variant_b JSON NOT NULL,
            status ENUM('active', 'paused', 'completed') DEFAULT 'active',
            winner VARCHAR(1) NULL,
            confidence_level INT NULL,
            is_significant TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            ended_at TIMESTAMP NULL,
            INDEX idx_item_id (item_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
        
        // Create metrics tracking table
        $sqlMetrics = "CREATE TABLE IF NOT EXISTS ai_ab_test_metrics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            test_id INT NOT NULL,
            variant ENUM('a', 'b') NOT NULL,
            date DATE NOT NULL,
            views INT DEFAULT 0,
            visits INT DEFAULT 0,
            sales INT DEFAULT 0,
            revenue DECIMAL(10,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (test_id) REFERENCES ai_ab_tests(id) ON DELETE CASCADE,
            INDEX idx_test_variant (test_id, variant, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sqlMetrics);
    }
    
    /**
     * Create new A/B test
     * 
     * @param string $testName
     * @param string $itemId
     * @param array $variantA Original or control version
     * @param array $variantB Optimized or test version
     * @return int Test ID
     */
    public function createTest(string $testName, string $itemId, array $variantA, array $variantB): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_ab_tests 
            (test_name, item_id, variant_a, variant_b, status, started_at) 
            VALUES (?, ?, ?, ?, 'active', NOW())"
        );
        
        $stmt->execute([
            $testName,
            $itemId,
            json_encode($variantA),
            json_encode($variantB)
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Track metrics for a variant
     * 
     * @param int $testId
     * @param string $variant 'a' or 'b'
     * @param array $metrics
     */
    public function trackMetrics(int $testId, string $variant, array $metrics): void
    {
        $date = date('Y-m-d');
        
        // Check if metrics already exist for today
        $stmt = $this->db->prepare(
            "SELECT id FROM ai_ab_test_metrics 
            WHERE test_id = ? AND variant = ? AND date = ?"
        );
        $stmt->execute([$testId, $variant, $date]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update existing
            $stmt = $this->db->prepare(
                "UPDATE ai_ab_test_metrics 
                SET views = ?, visits = ?, sales = ?, revenue = ?
                WHERE id = ?"
            );
            $stmt->execute([
                $metrics['views'] ?? 0,
                $metrics['visits'] ?? 0,
                $metrics['sales'] ?? 0,
                $metrics['revenue'] ?? 0,
                $existing['id']
            ]);
        } else {
            // Insert new
            $stmt = $this->db->prepare(
                "INSERT INTO ai_ab_test_metrics 
                (test_id, variant, date, views, visits, sales, revenue) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $testId,
                $variant,
                $date,
                $metrics['views'] ?? 0,
                $metrics['visits'] ?? 0,
                $metrics['sales'] ?? 0,
                $metrics['revenue'] ?? 0
            ]);
        }
    }
    
    /**
     * Get test results with statistical analysis
     * 
     * @param int $testId
     * @return array
     */
    public function getTestResults(int $testId): array
    {
        // Get test info
        $stmt = $this->db->prepare("SELECT * FROM ai_ab_tests WHERE id = ?");
        $stmt->execute([$testId]);
        $test = $stmt->fetch();
        
        if (!$test) {
            return ['error' => 'Test not found'];
        }
        
        // Get aggregated metrics for each variant
        $stmt = $this->db->prepare(
            "SELECT 
                variant,
                SUM(views) as total_views,
                SUM(visits) as total_visits,
                SUM(sales) as total_sales,
                SUM(revenue) as total_revenue
            FROM ai_ab_test_metrics 
            WHERE test_id = ?
            GROUP BY variant"
        );
        $stmt->execute([$testId]);
        $metrics = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $metricsA = ['views' => 0, 'visits' => 0, 'sales' => 0, 'revenue' => 0];
        $metricsB = ['views' => 0, 'visits' => 0, 'sales' => 0, 'revenue' => 0];
        
        foreach ($metrics as $row) {
            if ($row['variant'] === 'a') {
                $metricsA = [
                    'views' => (int)$row['total_views'],
                    'visits' => (int)$row['total_visits'],
                    'sales' => (int)$row['total_sales'],
                    'revenue' => (float)$row['total_revenue'],
                ];
            } else {
                $metricsB = [
                    'views' => (int)$row['total_views'],
                    'visits' => (int)$row['total_visits'],
                    'sales' => (int)$row['total_sales'],
                    'revenue' => (float)$row['total_revenue'],
                ];
            }
        }
        
        // Calculate conversion rates
        $metricsA['ctr'] = $metricsA['views'] > 0 
            ? round(($metricsA['visits'] / $metricsA['views']) * 100, 2) 
            : 0;
        $metricsB['ctr'] = $metricsB['views'] > 0 
            ? round(($metricsB['visits'] / $metricsB['views']) * 100, 2) 
            : 0;
            
        $metricsA['conversion'] = $metricsA['visits'] > 0 
            ? round(($metricsA['sales'] / $metricsA['visits']) * 100, 2) 
            : 0;
        $metricsB['conversion'] = $metricsB['visits'] > 0 
            ? round(($metricsB['sales'] / $metricsB['visits']) * 100, 2) 
            : 0;
        
        // Statistical significance test (simple chi-square approximation)
        $significance = $this->calculateSignificance($metricsA, $metricsB);
        
        // Determine winner
        $winner = 'undecided';
        if ($significance['is_significant']) {
            if ($metricsB['conversion'] > $metricsA['conversion']) {
                $winner = 'b';
            } else if ($metricsA['conversion'] > $metricsB['conversion']) {
                $winner = 'a';
            }
        }
        
        return [
            'test_id' => $testId,
            'test_name' => $test['test_name'],
            'item_id' => $test['item_id'],
            'status' => $test['status'],
            'variant_a' => [
                'data' => json_decode($test['variant_a'], true),
                'metrics' => $metricsA,
            ],
            'variant_b' => [
                'data' => json_decode($test['variant_b'], true),
                'metrics' => $metricsB,
            ],
            'winner' => $winner,
            'confidence_level' => $significance['confidence'],
            'is_significant' => $significance['is_significant'],
            'improvement' => $this->calculateImprovement($metricsA, $metricsB),
        ];
    }
    
    /**
     * Calculate statistical significance
     * 
     * @param array $metricsA
     * @param array $metricsB
     * @return array
     */
    private function calculateSignificance(array $metricsA, array $metricsB): array
    {
        $visitsA = $metricsA['visits'];
        $visitsB = $metricsB['visits'];
        $salesA = $metricsA['sales'];
        $salesB = $metricsB['sales'];
        
        // Need minimum sample size
        if ($visitsA < 30 || $visitsB < 30) {
            return [
                'is_significant' => false,
                'confidence' => 0,
                'message' => 'Insufficient data (minimum 30 visits per variant)',
            ];
        }
        
        // Calculate proportions
        $pA = $visitsA > 0 ? $salesA / $visitsA : 0;
        $pB = $visitsB > 0 ? $salesB / $visitsB : 0;
        
        // Pooled proportion
        $pPooled = ($salesA + $salesB) / ($visitsA + $visitsB);
        
        // Standard error
        $se = sqrt($pPooled * (1 - $pPooled) * (1/$visitsA + 1/$visitsB));
        
        // Z-score
        $z = $se > 0 ? abs($pB - $pA) / $se : 0;
        
        // Confidence level (simplified)
        // z > 1.96 = 95% confidence
        // z > 1.64 = 90% confidence
        $confidence = 0;
        $isSignificant = false;
        
        if ($z >= 1.96) {
            $confidence = 95;
            $isSignificant = true;
        } else if ($z >= 1.64) {
            $confidence = 90;
            $isSignificant = true;
        } else if ($z >= 1.28) {
            $confidence = 80;
        }
        
        return [
            'is_significant' => $isSignificant,
            'confidence' => $confidence,
            'z_score' => round($z, 2),
        ];
    }
    
    /**
     * Calculate improvement percentage
     * 
     * @param array $metricsA
     * @param array $metricsB
     * @return array
     */
    private function calculateImprovement(array $metricsA, array $metricsB): array
    {
        $improvements = [];
        
        // CTR improvement
        if ($metricsA['ctr'] > 0) {
            $improvements['ctr'] = round((($metricsB['ctr'] - $metricsA['ctr']) / $metricsA['ctr']) * 100, 1);
        }
        
        // Conversion improvement
        if ($metricsA['conversion'] > 0) {
            $improvements['conversion'] = round((($metricsB['conversion'] - $metricsA['conversion']) / $metricsA['conversion']) * 100, 1);
        }
        
        // Revenue improvement
        if ($metricsA['revenue'] > 0) {
            $improvements['revenue'] = round((($metricsB['revenue'] - $metricsA['revenue']) / $metricsA['revenue']) * 100, 1);
        }
        
        return $improvements;
    }
    
    /**
     * End test and declare winner
     * 
     * @param int $testId
     * @return array
     */
    public function endTest(int $testId): array
    {
        $results = $this->getTestResults($testId);
        
        if (isset($results['error'])) {
            return $results;
        }
        
        // Update test with winner
        $stmt = $this->db->prepare(
            "UPDATE ai_ab_tests 
            SET status = 'completed',
                ended_at = NOW(),
                winner = ?,
                confidence_level = ?,
                is_significant = ?
            WHERE id = ?"
        );
        
        $stmt->execute([
            $results['winner'],
            (int)$results['confidence_level'], // Schema has INT
            $results['is_significant'] ? 1 : 0,
            $testId
        ]);
        
        return $results;
    }
    
    /**
     * Get all active tests
     * 
     * @return array
     */
    public function getActiveTests(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM ai_ab_tests 
            WHERE status = 'active'
            ORDER BY created_at DESC"
        );
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get database connection
     * 
     * @return \PDO
     */
    private function getDbConnection(): \PDO
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'eskill';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }
}
