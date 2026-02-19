<?php

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Clone A/B Testing Service
 * 
 * Sistema de A/B Testing para variações de anúncios clonados:
 * - Criação de variações (título, preço, imagens)
 * - Rastreamento de métricas (visualizações, cliques, vendas)
 * - Determinação automática de vencedores
 * - Aplicação de variante vencedora
 */
class CloneABTestingService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;

    // Status de testes
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RUNNING = 'running';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Tipos de variação
    public const VAR_TITLE = 'title';
    public const VAR_PRICE = 'price';
    public const VAR_DESCRIPTION = 'description';
    public const VAR_THUMBNAIL = 'thumbnail';
    public const VAR_MULTI = 'multi';

    // Métricas
    public const METRIC_VIEWS = 'views';
    public const METRIC_CLICKS = 'clicks';
    public const METRIC_SALES = 'sales';
    public const METRIC_CONVERSION = 'conversion';
    public const METRIC_REVENUE = 'revenue';

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Cria um novo teste A/B
     */
    public function createTest(array $data): int
    {
        $itemId = $data['item_id'] ?? null;
        $testName = $data['name'] ?? 'A/B Test ' . date('Y-m-d H:i');
        $variationType = $data['variation_type'] ?? self::VAR_TITLE;
        $targetMetric = $data['target_metric'] ?? self::METRIC_CONVERSION;
        $duration = $data['duration_days'] ?? 7;
        $minSampleSize = $data['min_sample_size'] ?? 100;
        $confidenceLevel = $data['confidence_level'] ?? 95;
        $variations = $data['variations'] ?? [];

        if (!$itemId) {
            throw new Exception('item_id é obrigatório');
        }

        if (count($variations) < 2) {
            throw new Exception('Pelo menos 2 variações são necessárias');
        }

        $this->db->beginTransaction();
        
        try {
            // Criar teste
            $stmt = $this->db->prepare("
                INSERT INTO clone_ab_tests 
                (account_id, original_item_id, name, variation_type, target_metric,
                 duration_days, min_sample_size, confidence_level, status, created_at, updated_at)
                VALUES 
                (:account_id, :item_id, :name, :var_type, :metric,
                 :duration, :min_sample, :confidence, :status, NOW(), NOW())
            ");
            $stmt->execute([
                ':account_id' => $this->accountId,
                ':item_id' => $itemId,
                ':name' => $testName,
                ':var_type' => $variationType,
                ':metric' => $targetMetric,
                ':duration' => $duration,
                ':min_sample' => $minSampleSize,
                ':confidence' => $confidenceLevel,
                ':status' => self::STATUS_DRAFT,
            ]);
            
            $testId = (int) $this->db->lastInsertId();
            
            // Criar variações
            foreach ($variations as $index => $variation) {
                $isControl = $index === 0;
                $variationName = $variation['name'] ?? ($isControl ? 'Controle (Original)' : 'Variação ' . $index);
                
                $stmt = $this->db->prepare("
                    INSERT INTO clone_ab_variations 
                    (test_id, name, is_control, item_id, variation_data, 
                     traffic_weight, status, created_at)
                    VALUES 
                    (:test_id, :name, :is_control, :item_id, :data, 
                     :weight, 'active', NOW())
                ");
                $stmt->execute([
                    ':test_id' => $testId,
                    ':name' => $variationName,
                    ':is_control' => $isControl ? 1 : 0,
                    ':item_id' => $variation['item_id'] ?? $itemId,
                    ':data' => json_encode($variation['data'] ?? []),
                    ':weight' => $variation['weight'] ?? round(100 / count($variations), 2),
                ]);
            }
            
            $this->db->commit();
            
            return $testId;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Inicia um teste A/B
     */
    public function startTest(int $testId): array
    {
        $test = $this->getTest($testId);
        
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        if ($test['status'] !== self::STATUS_DRAFT && $test['status'] !== self::STATUS_PAUSED) {
            throw new Exception('Teste só pode ser iniciado se estiver em rascunho ou pausado');
        }
        
        // Atualizar status
        $stmt = $this->db->prepare("
            UPDATE clone_ab_tests 
            SET status = :status, started_at = IFNULL(started_at, NOW()), updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            ':status' => self::STATUS_RUNNING,
            ':id' => $testId,
            ':account_id' => $this->accountId,
        ]);
        
        return $this->getTest($testId);
    }

    /**
     * Pausa um teste A/B
     */
    public function pauseTest(int $testId): array
    {
        $stmt = $this->db->prepare("
            UPDATE clone_ab_tests 
            SET status = :status, updated_at = NOW()
            WHERE id = :id AND account_id = :account_id AND status = 'running'
        ");
        $stmt->execute([
            ':status' => self::STATUS_PAUSED,
            ':id' => $testId,
            ':account_id' => $this->accountId,
        ]);
        
        return $this->getTest($testId);
    }

    /**
     * Finaliza um teste A/B e determina vencedor
     */
    public function completeTest(int $testId): array
    {
        $test = $this->getTest($testId);
        
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        // Calcular vencedor
        $winner = $this->determineWinner($testId);
        
        $stmt = $this->db->prepare("
            UPDATE clone_ab_tests 
            SET status = :status, 
                ended_at = NOW(), 
                winner_variation_id = :winner_id,
                updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            ':status' => self::STATUS_COMPLETED,
            ':winner_id' => $winner['variation_id'] ?? null,
            ':id' => $testId,
            ':account_id' => $this->accountId,
        ]);
        
        return array_merge($this->getTest($testId), ['winner' => $winner]);
    }

    /**
     * Cancela um teste A/B
     */
    public function cancelTest(int $testId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clone_ab_tests 
            SET status = :status, ended_at = NOW(), updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        
        return $stmt->execute([
            ':status' => self::STATUS_CANCELLED,
            ':id' => $testId,
            ':account_id' => $this->accountId,
        ]);
    }

    /**
     * Obtém detalhes de um teste
     */
    public function getTest(int $testId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM clone_ab_variations WHERE test_id = t.id) as variation_count
            FROM clone_ab_tests t
            WHERE t.id = :id AND t.account_id = :account_id
        ");
        $stmt->execute([
            ':id' => $testId,
            ':account_id' => $this->accountId,
        ]);
        
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$test) {
            return null;
        }
        
        // Buscar variações
        $stmt = $this->db->prepare("
            SELECT v.*, 
                   COALESCE(SUM(m.views), 0) as total_views,
                   COALESCE(SUM(m.clicks), 0) as total_clicks,
                   COALESCE(SUM(m.sales), 0) as total_sales,
                   COALESCE(SUM(m.revenue), 0) as total_revenue
            FROM clone_ab_variations v
            LEFT JOIN clone_ab_metrics m ON m.variation_id = v.id
            WHERE v.test_id = :test_id
            GROUP BY v.id
            ORDER BY v.is_control DESC, v.id ASC
        ");
        $stmt->execute([':test_id' => $testId]);
        $test['variations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calcular métricas derivadas
        foreach ($test['variations'] as &$var) {
            $var['conversion_rate'] = $var['total_views'] > 0 
                ? round(($var['total_sales'] / $var['total_views']) * 100, 2) 
                : 0;
            $var['ctr'] = $var['total_views'] > 0 
                ? round(($var['total_clicks'] / $var['total_views']) * 100, 2) 
                : 0;
            $var['avg_order_value'] = $var['total_sales'] > 0 
                ? round($var['total_revenue'] / $var['total_sales'], 2) 
                : 0;
        }
        
        return $test;
    }

    /**
     * Lista testes da conta
     */
    public function listTests(array $filters = []): array
    {
        $sql = "
            SELECT t.*, 
                   (SELECT COUNT(*) FROM clone_ab_variations WHERE test_id = t.id) as variation_count,
                   (SELECT SUM(views) FROM clone_ab_metrics m 
                    JOIN clone_ab_variations v ON v.id = m.variation_id 
                    WHERE v.test_id = t.id) as total_views
            FROM clone_ab_tests t
            WHERE t.account_id = :account_id
        ";
        $params = [':account_id' => $this->accountId];
        
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        $sql .= " ORDER BY t.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registra métricas de uma variação
     */
    public function recordMetrics(int $variationId, array $metrics): bool
    {
        $date = $metrics['date'] ?? date('Y-m-d');
        
        $stmt = $this->db->prepare("
            INSERT INTO clone_ab_metrics 
            (variation_id, date, views, clicks, sales, revenue, created_at)
            VALUES 
            (:variation_id, :date, :views, :clicks, :sales, :revenue, NOW())
            ON DUPLICATE KEY UPDATE
                views = views + VALUES(views),
                clicks = clicks + VALUES(clicks),
                sales = sales + VALUES(sales),
                revenue = revenue + VALUES(revenue)
        ");
        
        return $stmt->execute([
            ':variation_id' => $variationId,
            ':date' => $date,
            ':views' => $metrics['views'] ?? 0,
            ':clicks' => $metrics['clicks'] ?? 0,
            ':sales' => $metrics['sales'] ?? 0,
            ':revenue' => $metrics['revenue'] ?? 0,
        ]);
    }

    /**
     * Sincroniza métricas do Mercado Livre
     */
    public function syncMetricsFromML(int $testId): array
    {
        $test = $this->getTest($testId);
        
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        $client = $this->getClient();
        $results = [];
        
        foreach ($test['variations'] as $variation) {
            $itemId = $variation['item_id'];
            
            try {
                // Buscar visitas
                $visits = $client->get("/items/{$itemId}/visits/time_window", [
                    'last' => 7,
                    'unit' => 'day'
                ]);
                
                $totalViews = 0;
                if (!empty($visits['results'])) {
                    foreach ($visits['results'] as $day) {
                        $totalViews += $day['total'] ?? 0;
                    }
                }
                
                // Buscar vendas (se disponível)
                // Nota: Endpoint de vendas pode variar
                
                $this->recordMetrics($variation['id'], [
                    'views' => $totalViews,
                    'date' => date('Y-m-d'),
                ]);
                
                $results[] = [
                    'variation_id' => $variation['id'],
                    'item_id' => $itemId,
                    'views_synced' => $totalViews,
                    'success' => true,
                ];
                
            } catch (Exception $e) {
                $results[] = [
                    'variation_id' => $variation['id'],
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        }
        
        return $results;
    }

    /**
     * Determina o vencedor de um teste
     */
    public function determineWinner(int $testId): array
    {
        $test = $this->getTest($testId);
        
        if (!$test || empty($test['variations'])) {
            return ['variation_id' => null, 'confidence' => 0, 'reason' => 'Sem dados'];
        }
        
        $targetMetric = $test['target_metric'];
        $minSample = $test['min_sample_size'];
        $confidenceLevel = $test['confidence_level'];
        
        // Encontrar variação controle
        $control = null;
        $variations = [];
        
        foreach ($test['variations'] as $var) {
            if ($var['is_control']) {
                $control = $var;
            } else {
                $variations[] = $var;
            }
        }
        
        if (!$control) {
            return ['variation_id' => null, 'confidence' => 0, 'reason' => 'Controle não encontrado'];
        }
        
        // Verificar tamanho mínimo da amostra
        $totalSamples = array_sum(array_column($test['variations'], 'total_views'));
        if ($totalSamples < $minSample) {
            return [
                'variation_id' => null, 
                'confidence' => 0, 
                'reason' => "Amostra insuficiente ({$totalSamples}/{$minSample})"
            ];
        }
        
        // Calcular melhor variação
        $metricMap = [
            self::METRIC_VIEWS => 'total_views',
            self::METRIC_CLICKS => 'total_clicks',
            self::METRIC_SALES => 'total_sales',
            self::METRIC_CONVERSION => 'conversion_rate',
            self::METRIC_REVENUE => 'total_revenue',
        ];
        
        $metricField = $metricMap[$targetMetric] ?? 'conversion_rate';
        
        $best = $control;
        $bestValue = $control[$metricField] ?? 0;
        $improvement = 0;
        
        foreach ($variations as $var) {
            $value = $var[$metricField] ?? 0;
            if ($value > $bestValue) {
                $improvement = $bestValue > 0 ? (($value - $bestValue) / $bestValue) * 100 : 100;
                $best = $var;
                $bestValue = $value;
            }
        }
        
        // Calcular significância estatística (simplificado)
        $confidence = $this->calculateConfidence($control, $best, $metricField);
        
        $isSignificant = $confidence >= $confidenceLevel;
        
        return [
            'variation_id' => $isSignificant ? $best['id'] : null,
            'variation_name' => $best['name'],
            'metric_value' => $bestValue,
            'improvement' => round($improvement, 2),
            'confidence' => $confidence,
            'is_significant' => $isSignificant,
            'reason' => $isSignificant 
                ? "Vencedor com {$confidence}% de confiança" 
                : "Resultado inconclusivo (confiança: {$confidence}%)",
        ];
    }

    /**
     * Calcula confiança estatística (simplificado)
     */
    private function calculateConfidence(array $control, array $variation, string $metric): float
    {
        $nControl = max(1, $control['total_views'] ?? 1);
        $nVariation = max(1, $variation['total_views'] ?? 1);
        
        if ($metric === 'conversion_rate') {
            $pControl = ($control['total_sales'] ?? 0) / $nControl;
            $pVariation = ($variation['total_sales'] ?? 0) / $nVariation;
            
            // Pooled proportion
            $pPooled = (($control['total_sales'] ?? 0) + ($variation['total_sales'] ?? 0)) / ($nControl + $nVariation);
            
            if ($pPooled == 0 || $pPooled == 1) {
                return 50; // Não é possível calcular
            }
            
            // Standard error
            $se = sqrt($pPooled * (1 - $pPooled) * (1/$nControl + 1/$nVariation));
            
            if ($se == 0) {
                return 50;
            }
            
            // Z-score
            $z = abs($pVariation - $pControl) / $se;
            
            // Conversão aproximada para porcentagem de confiança
            // Z = 1.96 -> 95%, Z = 2.58 -> 99%, Z = 1.28 -> 80%
            if ($z >= 2.58) return 99;
            if ($z >= 2.33) return 98;
            if ($z >= 1.96) return 95;
            if ($z >= 1.65) return 90;
            if ($z >= 1.28) return 80;
            if ($z >= 0.84) return 60;
            return round(50 + ($z * 15), 0);
        }
        
        // Para outras métricas, usar comparação simples
        $controlValue = $control[$metric] ?? 0;
        $variationValue = $variation[$metric] ?? 0;
        
        if ($controlValue == 0 && $variationValue == 0) {
            return 50;
        }
        
        $diff = abs($variationValue - $controlValue);
        $max = max($controlValue, $variationValue);
        
        return min(99, round(50 + ($diff / $max) * 50, 0));
    }

    /**
     * Aplica a variação vencedora
     */
    public function applyWinner(int $testId): array
    {
        $test = $this->getTest($testId);
        
        if (!$test) {
            throw new Exception('Teste não encontrado');
        }
        
        if ($test['status'] !== self::STATUS_COMPLETED) {
            throw new Exception('Teste precisa estar completado para aplicar vencedor');
        }
        
        if (!$test['winner_variation_id']) {
            throw new Exception('Nenhum vencedor determinado');
        }
        
        // Buscar variação vencedora
        $stmt = $this->db->prepare("
            SELECT * FROM clone_ab_variations WHERE id = :id
        ");
        $stmt->execute([':id' => $test['winner_variation_id']]);
        $winner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$winner) {
            throw new Exception('Variação vencedora não encontrada');
        }
        
        // Se a variação vencedora é o controle, não precisa fazer nada
        if ($winner['is_control']) {
            return [
                'status' => 'success',
                'message' => 'Variação controle (original) é a vencedora. Nenhuma alteração necessária.',
                'applied' => false,
            ];
        }
        
        $variationData = json_decode($winner['variation_data'], true) ?? [];
        
        // Aplicar mudanças no item original
        $client = $this->getClient();
        $originalItemId = $test['original_item_id'];
        
        try {
            $updateData = [];
            
            if (!empty($variationData['title'])) {
                $updateData['title'] = $variationData['title'];
            }
            if (!empty($variationData['price'])) {
                $updateData['price'] = $variationData['price'];
            }
            if (!empty($variationData['description'])) {
                // Descrição é atualizada separadamente
                $client->put("/items/{$originalItemId}/description", [
                    'plain_text' => $variationData['description']
                ]);
            }
            
            if (!empty($updateData)) {
                $client->put("/items/{$originalItemId}", $updateData);
            }
            
            // Marcar como aplicado
            $stmt = $this->db->prepare("
                UPDATE clone_ab_tests 
                SET winner_applied = 1, winner_applied_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([':id' => $testId]);
            
            return [
                'status' => 'success',
                'message' => 'Variação vencedora aplicada ao anúncio original',
                'applied' => true,
                'changes' => $variationData,
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Erro ao aplicar variação: ' . $e->getMessage(),
                'applied' => false,
            ];
        }
    }

    /**
     * Cria variações de título automaticamente
     */
    public function generateTitleVariations(string $itemId, int $count = 3): array
    {
        $client = $this->getClient();
        $item = $client->get("/items/{$itemId}");
        
        if (isset($item['error'])) {
            throw new Exception('Item não encontrado: ' . ($item['message'] ?? 'Unknown'));
        }
        
        $originalTitle = $item['title'] ?? '';
        $categoryId = $item['category_id'] ?? '';
        
        // Gerar variações baseadas em padrões comuns
        $variations = [
            [
                'name' => 'Controle (Original)',
                'item_id' => $itemId,
                'data' => ['title' => $originalTitle],
            ],
        ];
        
        // Variação 1: Adicionar palavras de urgência
        $urgencyPrefixes = ['OFERTA!', 'PROMOÇÃO!', 'IMPERDÍVEL!'];
        if (strlen($originalTitle) < 50) {
            $prefixIndex = abs(crc32($originalTitle . 'urgency')) % count($urgencyPrefixes);
            $variations[] = [
                'name' => 'Com Urgência',
                'data' => ['title' => $urgencyPrefixes[$prefixIndex] . ' ' . $originalTitle],
            ];
        }
        
        // Variação 2: Palavras-chave no início
        $words = explode(' ', $originalTitle);
        if (count($words) > 3) {
            // Mover palavras importantes para o início
            $important = ['Original', 'Premium', 'Novo', 'Genuíno'];
            foreach ($words as $i => $word) {
                if (in_array($word, $important) && $i > 0) {
                    unset($words[$i]);
                    array_unshift($words, $word);
                    break;
                }
            }
            $variations[] = [
                'name' => 'Keywords no Início',
                'data' => ['title' => implode(' ', $words)],
            ];
        }
        
        // Variação 3: Adicionar benefício
        $benefits = ['Frete Grátis', 'Envio Rápido', 'Garantia', 'Qualidade'];
        if (strlen($originalTitle) < 45) {
            $benefitIndex = abs(crc32($originalTitle . 'benefit')) % count($benefits);
            $benefit = $benefits[$benefitIndex];
            $variations[] = [
                'name' => 'Com Benefício',
                'data' => ['title' => $originalTitle . ' - ' . $benefit],
            ];
        }
        
        return array_slice($variations, 0, $count + 1);
    }

    /**
     * Obtém cliente ML
     */
    private function getClient(): MercadoLivreClient
    {
        if (!$this->client) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }

    /**
     * Garante que as tabelas existem
     */
    private function ensureTablesExist(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_ab_tests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                original_item_id VARCHAR(50) NOT NULL,
                name VARCHAR(200) NOT NULL,
                variation_type ENUM('title', 'price', 'description', 'thumbnail', 'multi') DEFAULT 'title',
                target_metric ENUM('views', 'clicks', 'sales', 'conversion', 'revenue') DEFAULT 'conversion',
                duration_days INT DEFAULT 7,
                min_sample_size INT DEFAULT 100,
                confidence_level INT DEFAULT 95,
                status ENUM('draft', 'running', 'paused', 'completed', 'cancelled') DEFAULT 'draft',
                winner_variation_id INT NULL,
                winner_applied TINYINT(1) DEFAULT 0,
                winner_applied_at DATETIME NULL,
                started_at DATETIME NULL,
                ended_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_account (account_id),
                INDEX idx_status (status),
                INDEX idx_item (original_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_ab_variations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                test_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                is_control TINYINT(1) DEFAULT 0,
                item_id VARCHAR(50) NULL COMMENT 'ID do item clonado para esta variação',
                variation_data JSON COMMENT 'Dados da variação (título, preço, etc)',
                traffic_weight DECIMAL(5,2) DEFAULT 50.00,
                status ENUM('active', 'paused', 'stopped') DEFAULT 'active',
                created_at DATETIME NOT NULL,
                INDEX idx_test (test_id),
                FOREIGN KEY (test_id) REFERENCES clone_ab_tests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_ab_metrics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                variation_id INT NOT NULL,
                date DATE NOT NULL,
                views INT DEFAULT 0,
                clicks INT DEFAULT 0,
                sales INT DEFAULT 0,
                revenue DECIMAL(12,2) DEFAULT 0,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uk_variation_date (variation_id, date),
                INDEX idx_variation (variation_id),
                FOREIGN KEY (variation_id) REFERENCES clone_ab_variations(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $checked = true;
    }
}
