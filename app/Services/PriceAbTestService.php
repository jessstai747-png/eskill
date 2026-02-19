<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de Teste A/B de Preços
 * 
 * Permite criar experimentos para testar diferentes estratégias de precificação
 * e medir o impacto em vendas, conversões e margem.
 */
class PriceAbTestService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->ensureTablesExist();
    }

    /**
     * Cria as tabelas necessárias se não existirem
     */
    private function ensureTablesExist(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_ab_tests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                status ENUM('draft', 'running', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
                item_id VARCHAR(50) NOT NULL,
                control_price DECIMAL(12,2) NOT NULL,
                variant_price DECIMAL(12,2) NOT NULL,
                traffic_split INT DEFAULT 50,
                start_date DATETIME,
                end_date DATETIME,
                target_metric ENUM('revenue', 'units_sold', 'conversion_rate', 'profit') DEFAULT 'revenue',
                min_sample_size INT DEFAULT 100,
                confidence_level DECIMAL(5,2) DEFAULT 95.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_account_status (account_id, status),
                INDEX idx_item (item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_ab_test_results (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_id INT NOT NULL,
                variant ENUM('control', 'variant') NOT NULL,
                date DATE NOT NULL,
                impressions INT DEFAULT 0,
                visits INT DEFAULT 0,
                conversions INT DEFAULT 0,
                units_sold INT DEFAULT 0,
                revenue DECIMAL(12,2) DEFAULT 0,
                profit DECIMAL(12,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_test_variant_date (test_id, variant, date),
                INDEX idx_test (test_id),
                FOREIGN KEY (test_id) REFERENCES pricing_ab_tests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pricing_ab_test_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                details JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_test (test_id),
                FOREIGN KEY (test_id) REFERENCES pricing_ab_tests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Obtém cliente ML (lazy loading)
     */
    private function getMlClient(): MercadoLivreClient
    {
        if ($this->mlClient === null) {
            $this->mlClient = new MercadoLivreClient($this->accountId);
        }
        return $this->mlClient;
    }

    /**
     * Cria um novo teste A/B
     */
    public function createTest(array $data): array
    {
        $required = ['name', 'item_id', 'control_price', 'variant_price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Campo obrigatório: $field"];
            }
        }

        // Verificar se item existe
        try {
            $ml = $this->getMlClient();
            $item = $ml->get("/items/{$data['item_id']}");
            if (!$item || isset($item['error'])) {
                return ['success' => false, 'message' => 'Item não encontrado no Mercado Livre'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao verificar item: ' . $e->getMessage()];
        }

        // Verificar se já existe teste ativo para este item
        $stmt = $this->db->prepare("
            SELECT id FROM pricing_ab_tests 
            WHERE account_id = :account_id 
            AND item_id = :item_id 
            AND status IN ('draft', 'running', 'paused')
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $data['item_id']
        ]);

        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Já existe um teste ativo para este item'];
        }

        // Criar teste
        $stmt = $this->db->prepare("
            INSERT INTO pricing_ab_tests 
            (account_id, name, description, item_id, control_price, variant_price, 
             traffic_split, target_metric, min_sample_size, confidence_level)
            VALUES 
            (:account_id, :name, :description, :item_id, :control_price, :variant_price,
             :traffic_split, :target_metric, :min_sample_size, :confidence_level)
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'item_id' => $data['item_id'],
            'control_price' => $data['control_price'],
            'variant_price' => $data['variant_price'],
            'traffic_split' => $data['traffic_split'] ?? 50,
            'target_metric' => $data['target_metric'] ?? 'revenue',
            'min_sample_size' => $data['min_sample_size'] ?? 100,
            'confidence_level' => $data['confidence_level'] ?? 95.00
        ]);

        $testId = (int) $this->db->lastInsertId();

        $this->logAction($testId, 'created', [
            'control_price' => $data['control_price'],
            'variant_price' => $data['variant_price']
        ]);

        return [
            'success' => true,
            'test_id' => $testId,
            'message' => 'Teste A/B criado com sucesso'
        ];
    }

    /**
     * Inicia um teste A/B
     */
    public function startTest(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['success' => false, 'message' => 'Teste não encontrado'];
        }

        if ($test['status'] === 'running') {
            return ['success' => false, 'message' => 'Teste já está em execução'];
        }

        if (!in_array($test['status'], ['draft', 'paused'])) {
            return ['success' => false, 'message' => 'Teste não pode ser iniciado neste status'];
        }

        // Aplicar preço inicial (controle ou variante aleatório baseado no split)
        $useVariant = mt_rand(1, 100) <= $test['traffic_split'];
        $price = $useVariant ? $test['variant_price'] : $test['control_price'];

        try {
            $ml = $this->getMlClient();
            $result = $ml->put("/items/{$test['item_id']}", ['price' => (float) $price]);

            if (!$result || isset($result['error'])) {
                return ['success' => false, 'message' => 'Erro ao aplicar preço no ML'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao aplicar preço: ' . $e->getMessage()];
        }

        // Atualizar status
        $stmt = $this->db->prepare("
            UPDATE pricing_ab_tests 
            SET status = 'running', 
                start_date = COALESCE(start_date, NOW())
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $testId, 'account_id' => $this->accountId]);

        $this->logAction($testId, 'started', [
            'initial_variant' => $useVariant ? 'variant' : 'control',
            'price_applied' => $price
        ]);

        return [
            'success' => true,
            'message' => 'Teste iniciado com sucesso',
            'active_variant' => $useVariant ? 'variant' : 'control',
            'price_applied' => $price
        ];
    }

    /**
     * Pausa um teste A/B
     */
    public function pauseTest(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['success' => false, 'message' => 'Teste não encontrado'];
        }

        if ($test['status'] !== 'running') {
            return ['success' => false, 'message' => 'Apenas testes em execução podem ser pausados'];
        }

        // Restaurar preço de controle
        try {
            $ml = $this->getMlClient();
            $ml->put("/items/{$test['item_id']}", ['price' => (float) $test['control_price']]);
        } catch (\Exception $e) {
            // Log mas não falha
        }

        $stmt = $this->db->prepare("
            UPDATE pricing_ab_tests SET status = 'paused' 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $testId, 'account_id' => $this->accountId]);

        $this->logAction($testId, 'paused', []);

        return ['success' => true, 'message' => 'Teste pausado'];
    }

    /**
     * Finaliza um teste A/B
     */
    public function completeTest(int $testId, ?string $winner = null): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['success' => false, 'message' => 'Teste não encontrado'];
        }

        // Calcular vencedor se não especificado
        if ($winner === null) {
            $analysis = $this->analyzeTest($testId);
            $winner = $analysis['winner'] ?? 'control';
        }

        // Aplicar preço vencedor permanentemente
        $finalPrice = $winner === 'variant' ? $test['variant_price'] : $test['control_price'];

        try {
            $ml = $this->getMlClient();
            $ml->put("/items/{$test['item_id']}", ['price' => (float) $finalPrice]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao aplicar preço final: ' . $e->getMessage()];
        }

        $stmt = $this->db->prepare("
            UPDATE pricing_ab_tests 
            SET status = 'completed', end_date = NOW() 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $testId, 'account_id' => $this->accountId]);

        $this->logAction($testId, 'completed', [
            'winner' => $winner,
            'final_price' => $finalPrice
        ]);

        return [
            'success' => true,
            'message' => 'Teste finalizado',
            'winner' => $winner,
            'final_price' => $finalPrice
        ];
    }

    /**
     * Cancela um teste A/B
     */
    public function cancelTest(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['success' => false, 'message' => 'Teste não encontrado'];
        }

        if ($test['status'] === 'completed') {
            return ['success' => false, 'message' => 'Teste já finalizado não pode ser cancelado'];
        }

        // Restaurar preço de controle
        try {
            $ml = $this->getMlClient();
            $ml->put("/items/{$test['item_id']}", ['price' => (float) $test['control_price']]);
        } catch (\Exception $e) {
            // Log mas não falha
        }

        $stmt = $this->db->prepare("
            UPDATE pricing_ab_tests 
            SET status = 'cancelled', end_date = NOW() 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $testId, 'account_id' => $this->accountId]);

        $this->logAction($testId, 'cancelled', []);

        return ['success' => true, 'message' => 'Teste cancelado'];
    }

    /**
     * Obtém um teste específico
     */
    public function getTest(int $testId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_ab_tests 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $testId, 'account_id' => $this->accountId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $test ?: null;
    }

    /**
     * Lista todos os testes
     */
    public function listTests(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['item_id'])) {
            $where[] = 'item_id = :item_id';
            $params['item_id'] = $filters['item_id'];
        }

        $sql = "SELECT * FROM pricing_ab_tests WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int) $filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra resultados diários de um teste
     */
    public function recordResults(int $testId, string $variant, array $metrics): array
    {
        $date = $metrics['date'] ?? date('Y-m-d');

        $stmt = $this->db->prepare("
            INSERT INTO pricing_ab_test_results 
            (test_id, variant, date, impressions, visits, conversions, units_sold, revenue, profit)
            VALUES 
            (:test_id, :variant, :date, :impressions, :visits, :conversions, :units_sold, :revenue, :profit)
            ON DUPLICATE KEY UPDATE
                impressions = impressions + VALUES(impressions),
                visits = visits + VALUES(visits),
                conversions = conversions + VALUES(conversions),
                units_sold = units_sold + VALUES(units_sold),
                revenue = revenue + VALUES(revenue),
                profit = profit + VALUES(profit)
        ");

        $stmt->execute([
            'test_id' => $testId,
            'variant' => $variant,
            'date' => $date,
            'impressions' => $metrics['impressions'] ?? 0,
            'visits' => $metrics['visits'] ?? 0,
            'conversions' => $metrics['conversions'] ?? 0,
            'units_sold' => $metrics['units_sold'] ?? 0,
            'revenue' => $metrics['revenue'] ?? 0,
            'profit' => $metrics['profit'] ?? 0
        ]);

        return ['success' => true];
    }

    /**
     * Obtém resultados de um teste
     */
    public function getResults(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['control' => [], 'variant' => []];
        }

        $stmt = $this->db->prepare("
            SELECT variant, 
                   SUM(impressions) as total_impressions,
                   SUM(visits) as total_visits,
                   SUM(conversions) as total_conversions,
                   SUM(units_sold) as total_units,
                   SUM(revenue) as total_revenue,
                   SUM(profit) as total_profit
            FROM pricing_ab_test_results
            WHERE test_id = :test_id
            GROUP BY variant
        ");
        $stmt->execute(['test_id' => $testId]);
        
        $results = ['control' => [], 'variant' => []];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $variant = $row['variant'];
            unset($row['variant']);
            $results[$variant] = $row;
        }

        return $results;
    }

    /**
     * Obtém resultados diários
     */
    public function getDailyResults(int $testId): array
    {
        $stmt = $this->db->prepare("
            SELECT date, variant, impressions, visits, conversions, units_sold, revenue, profit
            FROM pricing_ab_test_results
            WHERE test_id = :test_id
            ORDER BY date ASC, variant
        ");
        $stmt->execute(['test_id' => $testId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Analisa estatisticamente um teste A/B
     */
    public function analyzeTest(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test) {
            return ['success' => false, 'message' => 'Teste não encontrado'];
        }

        $results = $this->getResults($testId);
        $control = $results['control'] ?? [];
        $variant = $results['variant'] ?? [];

        // Métricas básicas
        $controlVisits = (int) ($control['total_visits'] ?? 0);
        $variantVisits = (int) ($variant['total_visits'] ?? 0);
        $controlConversions = (int) ($control['total_conversions'] ?? 0);
        $variantConversions = (int) ($variant['total_conversions'] ?? 0);
        $controlRevenue = (float) ($control['total_revenue'] ?? 0);
        $variantRevenue = (float) ($variant['total_revenue'] ?? 0);

        // Taxas de conversão
        $controlCR = $controlVisits > 0 ? $controlConversions / $controlVisits : 0;
        $variantCR = $variantVisits > 0 ? $variantConversions / $variantVisits : 0;

        // Receita por visita
        $controlRPV = $controlVisits > 0 ? $controlRevenue / $controlVisits : 0;
        $variantRPV = $variantVisits > 0 ? $variantRevenue / $variantVisits : 0;

        // Calcular lift
        $crLift = $controlCR > 0 ? (($variantCR - $controlCR) / $controlCR) * 100 : 0;
        $revenueLift = $controlRevenue > 0 ? (($variantRevenue - $controlRevenue) / $controlRevenue) * 100 : 0;

        // Significância estatística (aproximação via proporções)
        $totalSample = $controlVisits + $variantVisits;
        $minSample = (int) $test['min_sample_size'];
        $hasMinSample = $totalSample >= $minSample;

        // Cálculo simplificado de p-value (Z-test para proporções)
        $pValue = 1.0;
        $isSignificant = false;
        
        if ($controlVisits > 0 && $variantVisits > 0) {
            $pooledP = ($controlConversions + $variantConversions) / ($controlVisits + $variantVisits);
            
            if ($pooledP > 0 && $pooledP < 1) {
                $se = sqrt($pooledP * (1 - $pooledP) * (1 / $controlVisits + 1 / $variantVisits));
                
                if ($se > 0) {
                    $z = ($variantCR - $controlCR) / $se;
                    // Aproximação do p-value (two-tailed)
                    $pValue = 2 * (1 - $this->normalCdf(abs($z)));
                    $isSignificant = $pValue < (1 - $test['confidence_level'] / 100);
                }
            }
        }

        // Determinar vencedor
        $winner = 'inconclusive';
        $targetMetric = $test['target_metric'];
        
        if ($hasMinSample && $isSignificant) {
            switch ($targetMetric) {
                case 'conversion_rate':
                    $winner = $variantCR > $controlCR ? 'variant' : 'control';
                    break;
                case 'revenue':
                    $winner = $variantRevenue > $controlRevenue ? 'variant' : 'control';
                    break;
                case 'units_sold':
                    $variantUnits = (int) ($variant['total_units'] ?? 0);
                    $controlUnits = (int) ($control['total_units'] ?? 0);
                    $winner = $variantUnits > $controlUnits ? 'variant' : 'control';
                    break;
                case 'profit':
                    $variantProfit = (float) ($variant['total_profit'] ?? 0);
                    $controlProfit = (float) ($control['total_profit'] ?? 0);
                    $winner = $variantProfit > $controlProfit ? 'variant' : 'control';
                    break;
            }
        }

        return [
            'success' => true,
            'test' => $test,
            'control' => [
                'price' => $test['control_price'],
                'visits' => $controlVisits,
                'conversions' => $controlConversions,
                'conversion_rate' => round($controlCR * 100, 2),
                'revenue' => round($controlRevenue, 2),
                'revenue_per_visit' => round($controlRPV, 2)
            ],
            'variant' => [
                'price' => $test['variant_price'],
                'visits' => $variantVisits,
                'conversions' => $variantConversions,
                'conversion_rate' => round($variantCR * 100, 2),
                'revenue' => round($variantRevenue, 2),
                'revenue_per_visit' => round($variantRPV, 2)
            ],
            'analysis' => [
                'conversion_rate_lift' => round($crLift, 2),
                'revenue_lift' => round($revenueLift, 2),
                'p_value' => round($pValue, 4),
                'is_significant' => $isSignificant,
                'has_min_sample' => $hasMinSample,
                'total_sample' => $totalSample,
                'min_sample_required' => $minSample,
                'confidence_level' => $test['confidence_level']
            ],
            'winner' => $winner,
            'recommendation' => $this->getRecommendation($winner, $isSignificant, $hasMinSample, $crLift, $revenueLift)
        ];
    }

    /**
     * Aproximação da CDF da distribuição normal
     */
    private function normalCdf(float $x): float
    {
        $t = 1 / (1 + 0.2316419 * abs($x));
        $d = 0.3989423 * exp(-$x * $x / 2);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));
        return $x > 0 ? 1 - $p : $p;
    }

    /**
     * Gera recomendação baseada na análise
     */
    private function getRecommendation(
        string $winner, 
        bool $isSignificant, 
        bool $hasMinSample,
        float $crLift,
        float $revenueLift
    ): string {
        if (!$hasMinSample) {
            return 'Continue o teste até atingir o tamanho mínimo de amostra para resultados confiáveis.';
        }

        if (!$isSignificant) {
            return 'Os resultados ainda não são estatisticamente significativos. Continue o teste ou considere diferenças de preço maiores.';
        }

        if ($winner === 'variant') {
            $lift = $revenueLift > $crLift ? "receita ($revenueLift%)" : "conversão ($crLift%)";
            return "O preço variante está ganhando com aumento de $lift. Considere finalizar o teste e adotar o novo preço.";
        }

        if ($winner === 'control') {
            return 'O preço original (controle) está performando melhor. Considere manter o preço atual ou testar outras variações.';
        }

        return 'Resultados inconclusivos. Continue coletando dados ou revise a estratégia do teste.';
    }

    /**
     * Alterna preço baseado no split de tráfego (para uso em cron)
     */
    public function rotatePrice(int $testId): array
    {
        $test = $this->getTest($testId);
        if (!$test || $test['status'] !== 'running') {
            return ['success' => false, 'message' => 'Teste não está em execução'];
        }

        // Determinar variante baseado no split
        $useVariant = mt_rand(1, 100) <= $test['traffic_split'];
        $price = $useVariant ? $test['variant_price'] : $test['control_price'];
        $variant = $useVariant ? 'variant' : 'control';

        try {
            $ml = $this->getMlClient();
            $ml->put("/items/{$test['item_id']}", ['price' => (float) $price]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Erro ao aplicar preço: ' . $e->getMessage()];
        }

        $this->logAction($testId, 'price_rotated', [
            'variant' => $variant,
            'price' => $price
        ]);

        return [
            'success' => true,
            'variant' => $variant,
            'price' => $price
        ];
    }

    /**
     * Registra ação no log
     */
    private function logAction(int $testId, string $action, array $details): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_ab_test_log (test_id, action, details)
            VALUES (:test_id, :action, :details)
        ");
        $stmt->execute([
            'test_id' => $testId,
            'action' => $action,
            'details' => json_encode($details)
        ]);
    }

    /**
     * Obtém log de ações de um teste
     */
    public function getTestLog(int $testId): array
    {
        $stmt = $this->db->prepare("
            SELECT action, details, created_at
            FROM pricing_ab_test_log
            WHERE test_id = :test_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['test_id' => $testId]);
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logs as &$log) {
            $log['details'] = json_decode($log['details'], true);
        }
        
        return $logs;
    }

    /**
     * Obtém estatísticas gerais de testes
     */
    public function getStats(): array
    {
        // Total de testes por status
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) as count
            FROM pricing_ab_tests
            WHERE account_id = :account_id
            GROUP BY status
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        
        $statusCounts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        // Média de duração dos testes finalizados
        $stmt = $this->db->prepare("
            SELECT AVG(DATEDIFF(end_date, start_date)) as avg_duration
            FROM pricing_ab_tests
            WHERE account_id = :account_id AND status = 'completed' AND start_date IS NOT NULL
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $avgDuration = $stmt->fetchColumn();

        // Taxa de sucesso (variante venceu)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pricing_ab_test_log l
            JOIN pricing_ab_tests t ON l.test_id = t.id
            WHERE t.account_id = :account_id 
            AND l.action = 'completed' 
            AND JSON_EXTRACT(l.details, '$.winner') = 'variant'
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $variantWins = (int) $stmt->fetchColumn();

        $totalCompleted = $statusCounts['completed'] ?? 0;
        $variantWinRate = $totalCompleted > 0 ? ($variantWins / $totalCompleted) * 100 : 0;

        return [
            'status_counts' => $statusCounts,
            'total_tests' => array_sum($statusCounts),
            'running_tests' => $statusCounts['running'] ?? 0,
            'completed_tests' => $totalCompleted,
            'avg_duration_days' => round((float) $avgDuration, 1),
            'variant_win_rate' => round($variantWinRate, 1)
        ];
    }
}
