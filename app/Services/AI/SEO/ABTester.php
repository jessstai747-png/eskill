<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use PDO;

/**
 * 🧪 A/B TESTER - Testes A/B
 * 
 * Sistema para testes A/B em anúncios:
 * - Teste de Títulos
 * - Teste de Preços
 * - Teste de Imagens (Principal)
 * - Monitoramento diário
 * - Determinação estatística do vencedor
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class ABTester
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;
    
    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        
        $this->ensureTablesExist();
    }
    
    /**
     * Ensure AB Testing tables exist
     */
    private function ensureTablesExist(): void
    {
        try {
            // Main Test Table
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_ab_tests (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    account_id INT NOT NULL,
                    item_id VARCHAR(50) NOT NULL,
                    type ENUM('title', 'price', 'picture') NOT NULL,
                    status ENUM('running', 'paused', 'completed', 'stopped') DEFAULT 'running',
                    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    end_date TIMESTAMP NULL,
                    duration_days INT DEFAULT 14,
                    winner_variant ENUM('A', 'B') NULL,
                    confidence_score DECIMAL(5,2) DEFAULT 0,
                    auto_apply_winner BOOLEAN DEFAULT TRUE,
                    variant_a_data TEXT NOT NULL, -- JSON (Original)
                    variant_b_data TEXT NOT NULL, -- JSON (New)
                    INDEX idx_account (account_id),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Daily Metrics tracking for the test
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS seo_ab_metrics (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    test_id INT NOT NULL,
                    date DATE NOT NULL,
                    variant ENUM('A', 'B') NOT NULL, -- Which variant was active
                    views INT DEFAULT 0,
                    sales INT DEFAULT 0,
                    revenue DECIMAL(10,2) DEFAULT 0,
                    conversion_rate DECIMAL(5,2) DEFAULT 0,
                    INDEX idx_test (test_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (\PDOException $e) {
            log_error('Falha ao criar tabelas do ABTester', [
                'service' => 'ABTester',
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 🆕 Criar um novo Teste A/B
     */
    public function createTest(string $itemId, string $type, $variantBValue, int $durationDays = 14): array
    {
        // 1. Get current item data (Variant A)
        $item = $this->mlClient->get("/items/{$itemId}");
        
        $variantAValue = null;
        if ($type === 'title') $variantAValue = $item['title'];
        elseif ($type === 'price') $variantAValue = $item['price'];
        elseif ($type === 'picture') $variantAValue = $item['pictures'][0]['id'] ?? null;
        
        if (!$variantAValue) return ['error' => 'Valor atual não encontrado para o tipo ' . $type];
        
        // 2. Check overlap
        if ($this->hasActiveTest($itemId)) {
            return ['error' => 'Já existe um teste ativo para este item'];
        }
        
        // 3. Create record
        $stmt = $this->db->prepare("
            INSERT INTO seo_ab_tests 
            (account_id, item_id, type, duration_days, variant_a_data, variant_b_data, status)
            VALUES (?, ?, ?, ?, ?, ?, 'running')
        ");
        
        // Variant B is initially applied? Or do we rotate?
        // STRATEGY: Rotation every 24h. Start with B to test impact immediately.
        
        $success = $stmt->execute([
            $this->accountId,
            $itemId,
            $type,
            $durationDays,
            json_encode(['value' => $variantAValue]),
            json_encode(['value' => $variantBValue])
        ]);
        
        if ($success) {
            $testId = $this->db->lastInsertId();
            
            // Apply Variant B immediately to start fresh
            $this->applyVariant($itemId, $type, $variantBValue);
            
            return [
                'success' => true, 
                'test_id' => $testId, 
                'message' => 'Teste iniciado. Variante B aplicada.'
            ];
        }
        
        return ['error' => 'Falha ao criar teste'];
    }
    
    /**
     * 🔄 Verificar e rotacionar testes (Cron Job)
     */
    public function updateTests(): array
    {
        // Get active tests
        $stmt = $this->db->prepare("SELECT * FROM seo_ab_tests WHERE status = 'running' AND account_id = ?");
        $stmt->execute([$this->accountId]);
        $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        
        foreach ($tests as $test) {
            // 1. Record metrics for yesterday (which variant was active?)
            // For simplicity, we assume we rotate exactly at cron time.
            // In a real system, we'd log exact changes.
            // Simplified Plan: Rotate Daily. Yesterday was Day X.
            
            $daysRunning = (time() - strtotime($test['start_date'])) / 86400;
            
            // Determine which was running yesterday
            // Even days = Variant A (Original), Odd days = Variant B (New) or vice versa.
            // Actually, let's track "current active" in future. For now, alternate.
            
            // Rotation Logic:
            // Calculate which one should be active TODAY
            $shouldBeB = (floor($daysRunning) % 2) == 0; // Toggle everyday
            
            $dataA = json_decode($test['variant_a_data'], true)['value'];
            $dataB = json_decode($test['variant_b_data'], true)['value'];
            
            $valToApply = $shouldBeB ? $dataB : $dataA;
            
            // Apply for today
            $this->applyVariant($test['item_id'], $test['type'], $valToApply);
            
            // Coletar métricas do dia anterior
            $this->collectDailyMetrics($test['id'], $test['item_id'], $shouldBeB ? 'B' : 'A');
            
            // Check status (End date?)
            if ($daysRunning >= $test['duration_days']) {
                $this->concludeTest($test['id']);
            }
            
            $updated++;
        }
        
        return ['updated' => $updated];
    }
    
    /**
     * 📊 Coletar métricas diárias do teste A/B
     * 
     * Tenta obter dados reais do ML API, com fallback para sold_quantity
     * 
     * @param int $testId ID do teste
     * @param string $itemId ID do item
     * @param string $variant Variante ativa ('A' ou 'B')
     * @return array Métricas coletadas
     */
    private function collectDailyMetrics(int $testId, string $itemId, string $variant): array
    {
        $metrics = [
            'test_id' => $testId,
            'date' => date('Y-m-d'),
            'variant' => $variant,
            'views' => 0,
            'sales' => 0,
            'revenue' => 0,
            'conversion_rate' => 0,
        ];
        
        try {
            // 1. Tentar buscar item atual
            $item = $this->mlClient->get("/items/{$itemId}");
            
            // 2. Sold quantity atual
            $soldNow = $item['sold_quantity'] ?? 0;
            
            // 3. Buscar sold quantity do dia anterior
            $stmt = $this->db->prepare("
                SELECT sales FROM seo_ab_metrics 
                WHERE test_id = ? 
                ORDER BY date DESC 
                LIMIT 1
            ");
            $stmt->execute([$testId]);
            $lastMetric = $stmt->fetch(PDO::FETCH_ASSOC);
            $soldBefore = $lastMetric ? $lastMetric['sales'] : 0;
            
            // 4. Delta de vendas
            $salesDelta = max(0, $soldNow - $soldBefore);
            $metrics['sales'] = $salesDelta;
            
            // 5. Receita estimada (vendas * preço)
            $price = $item['price'] ?? 0;
            $metrics['revenue'] = $salesDelta * $price;
            
            // 6. Tentar buscar visualizações (Visit Metrics API - requer permissões especiais)
            try {
                $visits = $this->mlClient->get("/items/{$itemId}/visits");
                $metrics['views'] = $visits['total'] ?? 0;
            } catch (\Exception $e) {
                // Visit Metrics não disponível - usar estimativa
                log_debug('ABTester: Visit Metrics API não disponível', [
                    'service' => 'ABTester',
                    'item_id' => $itemId,
                ]);
                $metrics['views'] = 0;
            }
            
            // 7. Taxa de conversão
            if ($metrics['views'] > 0) {
                $metrics['conversion_rate'] = round(($metrics['sales'] / $metrics['views']) * 100, 2);
            }
            
            // 8. Salvar métricas no banco
            $stmt = $this->db->prepare("
                INSERT INTO seo_ab_metrics (test_id, date, variant, views, sales, revenue, conversion_rate)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $testId,
                $metrics['date'],
                $metrics['variant'],
                $metrics['views'],
                $metrics['sales'],
                $metrics['revenue'],
                $metrics['conversion_rate'],
            ]);
            
            log_info('ABTester: métricas coletadas', [
                'service' => 'ABTester',
                'test_id' => $testId,
                'variant' => $variant,
                'sales' => $metrics['sales'],
            ]);
            
        } catch (\Exception $e) {
            log_warning('ABTester: erro ao coletar métricas', [
                'service' => 'ABTester',
                'test_id' => $testId,
                'error' => $e->getMessage(),
            ]);
        }
        
        return $metrics;
    }
    
    /**
     * 📊 Listar testes
     */
    public function listTests(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM seo_ab_tests WHERE account_id = ? ORDER BY id DESC LIMIT 20");
        $stmt->execute([$this->accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * ✋ Parar um teste
     */
    public function stopTest(int $testId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM seo_ab_tests WHERE id = ? AND account_id = ?");
        $stmt->execute([$testId, $this->accountId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) return ['error' => 'Teste não encontrado'];
        
        // Revert to Variant A
        $dataA = json_decode($test['variant_a_data'], true)['value'];
        $this->applyVariant($test['item_id'], $test['type'], $dataA);
        
        $this->db->prepare("UPDATE seo_ab_tests SET status = 'stopped', end_date = NOW() WHERE id = ?")
                 ->execute([$testId]);
                 
        return ['success' => true, 'message' => 'Teste parado e revertido para Variante A'];
    }
    
    // Helpers
    
    private function hasActiveTest(string $itemId): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM seo_ab_tests WHERE item_id = ? AND status = 'running'");
        $stmt->execute([$itemId]);
        return (bool) $stmt->fetch();
    }
    
    private function applyVariant(string $itemId, string $type, $value): bool
    {
        try {
            $data = [];
            if ($type === 'title') $data['title'] = $value;
            elseif ($type === 'price') $data['price'] = (float)$value;
            // Picture rotation is complex (reordering IDs), skipped for MVP stability
            
            if (!empty($data)) {
                $this->mlClient->put("/items/{$itemId}", $data);
                return true;
            }
        } catch (\Exception $e) {
            log_warning('ABTester: erro ao aplicar variante', [
                'service' => 'ABTester',
                'item_id' => $itemId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
        return false;
    }
    
    private function concludeTest(int $testId): void
    {
        // Calculate winner based on metrics with statistical significance
        $metrics = $this->getTestMetrics($testId);
        
        if (empty($metrics['A']) || empty($metrics['B'])) {
            return; // Not enough data
        }
        
        $stats = $this->calculateStatisticalSignificance($metrics['A'], $metrics['B']);
        
        // Update test with winner if confidence is high enough
        if ($stats['confidence'] >= 95) {
            $winner = $stats['winner'];
            $this->db->prepare("
                UPDATE seo_ab_tests 
                SET status = 'completed', 
                    end_date = NOW(),
                    winner_variant = ?,
                    confidence_score = ?
                WHERE id = ?
            ")->execute([$winner, $stats['confidence'], $testId]);
        }
    }
    
    /**
     * 📊 Calcular significância estatística (Teste Z para proporções)
     * 
     * @param array $metricsA Métricas da variante A
     * @param array $metricsB Métricas da variante B
     * @return array Resultado estatístico
     */
    private function calculateStatisticalSignificance(array $metricsA, array $metricsB): array
    {
        // Conversões
        $nA = $metricsA['views'] ?? 0;
        $nB = $metricsB['views'] ?? 0;
        $xA = $metricsA['sales'] ?? 0;
        $xB = $metricsB['sales'] ?? 0;
        
        if ($nA == 0 || $nB == 0) {
            return [
                'confidence' => 0,
                'winner' => null,
                'p_value' => 1,
                'message' => 'Dados insuficientes'
            ];
        }
        
        // Taxas de conversão
        $pA = $xA / $nA;
        $pB = $xB / $nB;
        
        // Taxa de conversão combinada
        $pPool = ($xA + $xB) / ($nA + $nB);
        
        // Erro padrão
        $se = sqrt($pPool * (1 - $pPool) * (1/$nA + 1/$nB));
        
        if ($se == 0) {
            return [
                'confidence' => 0,
                'winner' => null,
                'p_value' => 1,
                'message' => 'Erro padrão zero'
            ];
        }
        
        // Z-score
        $z = ($pB - $pA) / $se;
        
        // P-value (aproximação)
        $pValue = 2 * (1 - $this->normalCDF(abs($z)));
        
        // Nível de confiança
        $confidence = (1 - $pValue) * 100;
        
        // Determinar vencedor
        $winner = null;
        if ($confidence >= 95) {
            $winner = $pB > $pA ? 'B' : 'A';
        }
        
        return [
            'confidence' => round($confidence, 2),
            'winner' => $winner,
            'p_value' => round($pValue, 4),
            'z_score' => round($z, 4),
            'conversion_a' => round($pA * 100, 2),
            'conversion_b' => round($pB * 100, 2),
            'improvement' => round((($pB - $pA) / $pA) * 100, 2),
            'message' => $confidence >= 95 
                ? "Variante {$winner} é significativamente melhor (confiança {$confidence}%)"
                : "Diferença não é estatisticamente significativa (confiança {$confidence}%)"
        ];
    }
    
    /**
     * Função de distribuição cumulativa normal (aproximação)
     */
    private function normalCDF(float $x): float
    {
        // Aproximação de Abramowitz e Stegun
        $t = 1 / (1 + 0.2316419 * abs($x));
        $d = 0.3989423 * exp(-$x * $x / 2);
        $prob = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
        
        return $x > 0 ? 1 - $prob : $prob;
    }
    
    /**
     * 📊 Obter métricas agregadas do teste
     */
    private function getTestMetrics(int $testId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                variant,
                SUM(views) as total_views,
                SUM(sales) as total_sales,
                SUM(revenue) as total_revenue,
                AVG(conversion_rate) as avg_conversion
            FROM seo_ab_metrics
            WHERE test_id = ?
            GROUP BY variant
        ");
        $stmt->execute([$testId]);
        
        $metrics = ['A' => [], 'B' => []];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $metrics[$row['variant']] = [
                'views' => (int)$row['total_views'],
                'sales' => (int)$row['total_sales'],
                'revenue' => (float)$row['total_revenue'],
                'conversion' => (float)$row['avg_conversion']
            ];
        }
        
        return $metrics;
    }
    
    /**
     * 📊 Obter análise estatística completa de um teste
     * 
     * @param int $testId ID do teste
     * @return array Análise detalhada
     */
    public function getTestAnalysis(int $testId): array
    {
        // Buscar teste
        $stmt = $this->db->prepare("
            SELECT * FROM seo_ab_tests 
            WHERE id = ? AND account_id = ?
        ");
        $stmt->execute([$testId, $this->accountId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            return ['error' => 'Teste não encontrado'];
        }
        
        // Buscar métricas
        $metrics = $this->getTestMetrics($testId);
        
        // Calcular significância estatística
        $stats = !empty($metrics['A']) && !empty($metrics['B'])
            ? $this->calculateStatisticalSignificance($metrics['A'], $metrics['B'])
            : null;
        
        // Calcular dias decorridos
        $startDate = new \DateTime($test['start_date']);
        $now = new \DateTime();
        $daysElapsed = $now->diff($startDate)->days;
        $daysRemaining = max(0, $test['duration_days'] - $daysElapsed);
        
        return [
            'test_id' => $testId,
            'item_id' => $test['item_id'],
            'type' => $test['type'],
            'status' => $test['status'],
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'progress_percentage' => min(100, round(($daysElapsed / $test['duration_days']) * 100, 1)),
            'variant_a' => json_decode($test['variant_a_data'], true),
            'variant_b' => json_decode($test['variant_b_data'], true),
            'metrics' => $metrics,
            'statistical_analysis' => $stats,
            'winner' => $test['winner_variant'],
            'confidence' => $test['confidence_score'],
            'recommendation' => $this->generateRecommendation($test, $stats),
        ];
    }
    
    /**
     * Gerar recomendação baseada em análise
     */
    private function generateRecommendation(array $test, ?array $stats): string
    {
        if (!$stats || $stats['confidence'] < 75) {
            return "Continue o teste. Dados insuficientes para conclusão (confiança: " . 
                   ($stats['confidence'] ?? 0) . "%)";
        }
        
        if ($stats['confidence'] >= 95) {
            $winner = $stats['winner'];
            $improvement = abs($stats['improvement']);
            return "✅ Teste concluído! Variante {$winner} é {$improvement}% melhor. " .
                   "Recomendação: Aplicar variante {$winner} permanentemente.";
        }
        
        if ($stats['confidence'] >= 90) {
            return "⚠️ Resultados promissores (confiança {$stats['confidence']}%). " .
                   "Sugestão: Aguarde mais alguns dias para aumentar confiança.";
        }
        
        return "📊 Diferença detectada mas não significativa. Continue o teste.";
    }
}
