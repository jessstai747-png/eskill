<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use PDO;

/**
 * RankingAlertService
 * 
 * Serviço dedicado ao monitoramento de posicionamento no ranking de preços
 * do Mercado Livre e geração de alertas quando produtos saem das faixas ideais.
 * 
 * Funcionalidades:
 * - Monitoramento contínuo de posição no ranking
 * - Alertas por faixas (0-8%, 8-12%, 12-15%, >15%)
 * - Histórico de alertas com análise de tendência
 * - Sugestões de ajuste de preço
 * - Integração com webhook para notificações
 */
class RankingAlertService
{
    private PDO $db;
    private int $accountId;
    private MercadoLivreClient $mlClient;
    
    // Configurações de faixas de ranking
    private const RANKING_THRESHOLDS = [
        'excellent' => ['min' => 0, 'max' => 8, 'status' => 'excellent', 'message' => 'Excelente posição'],
        'good' => ['min' => 8, 'max' => 12, 'status' => 'good', 'message' => 'Boa posição'],
        'warning' => ['min' => 12, 'max' => 15, 'status' => 'warning', 'message' => 'Posição de risco'],
        'danger' => ['min' => 15, 'max' => 100, 'status' => 'danger', 'message' => 'Fora do ranking competitivo'],
    ];
    
    // Severidade dos alertas
    private const ALERT_SEVERITY = [
        'excellent' => 1,
        'good' => 2,
        'warning' => 3,
        'danger' => 4,
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Analisa e gera alertas para um item específico
     * 
     * @param string $itemId ID do item no ML
     * @param array $itemData Dados do item (opcional - será buscado se não fornecido)
     * @return array Resultado da análise com alertas gerados
     */
    public function analyzeItem(string $itemId, ?array $itemData = null): array
    {
        // Buscar dados do item se não fornecidos
        if (!$itemData) {
            $itemData = $this->mlClient->get("/items/{$itemId}");
        }
        
        if (empty($itemData) || isset($itemData['error'])) {
            return [
                'success' => false,
                'error' => 'Item não encontrado no Mercado Livre'
            ];
        }
        
        // Buscar dados de ranking da categoria
        $categoryId = $itemData['category_id'] ?? null;
        $currentPrice = $itemData['price'] ?? 0;
        
        if (!$categoryId || !$currentPrice) {
            return [
                'success' => false,
                'error' => 'Dados insuficientes para análise'
            ];
        }
        
        // Buscar posição no ranking de preço
        $rankingData = $this->fetchPriceRanking($itemId, $categoryId, $currentPrice);
        
        // Determinar status atual
        $currentStatus = $this->determineRankingStatus($rankingData['position_percentage'] ?? 50);
        
        // Verificar se precisa gerar alerta
        $previousAlert = $this->getLastAlert($itemId);
        $shouldAlert = $this->shouldGenerateAlert($currentStatus, $previousAlert);
        
        $result = [
            'success' => true,
            'item_id' => $itemId,
            'title' => $itemData['title'] ?? '',
            'current_price' => $currentPrice,
            'ranking_data' => $rankingData,
            'status' => $currentStatus,
            'threshold' => self::RANKING_THRESHOLDS[$currentStatus['status']],
            'alert_generated' => false,
        ];
        
        // Gerar alerta se necessário
        if ($shouldAlert) {
            $alert = $this->generateAlert($itemId, $itemData, $rankingData, $currentStatus);
            $result['alert_generated'] = true;
            $result['alert'] = $alert;
        }
        
        // Calcular sugestões de ajuste
        $result['suggestions'] = $this->calculatePriceSuggestions(
            $currentPrice, 
            $rankingData, 
            $currentStatus
        );
        
        return $result;
    }

    /**
     * Busca posição de preço no ranking da categoria
     */
    private function fetchPriceRanking(string $itemId, string $categoryId, float $currentPrice): array
    {
        // Buscar itens similares na categoria para comparação
        $searchParams = [
            'category' => $categoryId,
            'limit' => 50,
            'sort' => 'price_asc'
        ];
        
        $results = $this->mlClient->get('/sites/MLB/search', $searchParams);
        
        $items = $results['results'] ?? [];
        $totalItems = count($items);
        
        if ($totalItems === 0) {
            return [
                'position' => 0,
                'total' => 0,
                'position_percentage' => 50,
                'lowest_price' => 0,
                'highest_price' => 0,
                'average_price' => 0,
                'median_price' => 0,
            ];
        }
        
        // Extrair preços e calcular estatísticas
        $prices = array_map(fn($item) => $item['price'] ?? 0, $items);
        sort($prices);
        
        $lowestPrice = min($prices);
        $highestPrice = max($prices);
        $averagePrice = array_sum($prices) / $totalItems;
        $medianPrice = $prices[(int)floor($totalItems / 2)];
        
        // Calcular posição do preço atual
        $position = 0;
        foreach ($prices as $price) {
            if ($currentPrice >= $price) {
                $position++;
            }
        }
        
        // Calcular percentual de posição (0 = mais barato, 100 = mais caro)
        $positionPercentage = $totalItems > 1 
            ? (($position - 1) / ($totalItems - 1)) * 100 
            : 50;
        
        return [
            'position' => $position,
            'total' => $totalItems,
            'position_percentage' => round($positionPercentage, 2),
            'lowest_price' => $lowestPrice,
            'highest_price' => $highestPrice,
            'average_price' => round($averagePrice, 2),
            'median_price' => $medianPrice,
            'price_range' => $highestPrice - $lowestPrice,
            'distance_from_lowest' => round((($currentPrice - $lowestPrice) / $lowestPrice) * 100, 2),
            'distance_from_average' => round((($currentPrice - $averagePrice) / $averagePrice) * 100, 2),
        ];
    }

    /**
     * Determina o status de ranking baseado na posição percentual
     */
    private function determineRankingStatus(float $positionPercentage): array
    {
        foreach (self::RANKING_THRESHOLDS as $key => $threshold) {
            if ($positionPercentage >= $threshold['min'] && $positionPercentage < $threshold['max']) {
                return [
                    'status' => $key,
                    'severity' => self::ALERT_SEVERITY[$key],
                    'position_percentage' => $positionPercentage,
                    'message' => $threshold['message'],
                    'threshold_range' => "{$threshold['min']}-{$threshold['max']}%"
                ];
            }
        }
        
        // Fallback para danger
        return [
            'status' => 'danger',
            'severity' => 4,
            'position_percentage' => $positionPercentage,
            'message' => self::RANKING_THRESHOLDS['danger']['message'],
            'threshold_range' => '15-100%'
        ];
    }

    /**
     * Obtém o último alerta registrado para um item
     */
    private function getLastAlert(string $itemId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_ranking_alerts 
            WHERE account_id = :account_id AND item_id = :item_id 
            ORDER BY criado_em DESC LIMIT 1
        ");
        
        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $itemId
        ]);
        
        $alert = $stmt->fetch(PDO::FETCH_ASSOC);
        return $alert ?: null;
    }

    /**
     * Determina se deve gerar um novo alerta
     */
    private function shouldGenerateAlert(array $currentStatus, ?array $previousAlert): bool
    {
        // Se não há alerta anterior, gerar para warning e danger
        if (!$previousAlert) {
            return $currentStatus['severity'] >= 3;
        }
        
        $currentNivel = match ($currentStatus['status'] ?? null) {
            'danger' => 'vermelho',
            'warning' => 'amarelo',
            default => 'verde',
        };

        // Verificar se mudou de faixa (comparando níveis persistidos)
        if (($previousAlert['nivel'] ?? null) !== $currentNivel) {
            return true;
        }
        
        // Se piorou dentro da mesma faixa, alertar após 24h
        $lastAlertTime = strtotime((string)($previousAlert['criado_em'] ?? ''));
        $hoursSinceLastAlert = (time() - $lastAlertTime) / 3600;
        
        if ($hoursSinceLastAlert >= 24 && $currentStatus['severity'] >= 3) {
            return true;
        }
        
        return false;
    }

    /**
     * Gera e persiste um novo alerta
     */
    private function generateAlert(string $itemId, array $itemData, array $rankingData, array $status): array
    {
        $message = $this->buildAlertMessage($itemData, $rankingData, $status);
        $suggestedPrice = $this->calculateSuggestedPrice($itemData['price'], $rankingData, $status);

        $nivel = match ($status['status'] ?? null) {
            'danger' => 'vermelho',
            'warning' => 'amarelo',
            default => 'verde',
        };

        // Por enquanto, este service é focado em perda de posição no ranking de preço.
        $tipoAlerta = 'perda_posicao';
        
        $alert = [
            'account_id' => $this->accountId,
            'item_id' => $itemId,
            'tipo_alerta' => $tipoAlerta,
            'nivel' => $nivel,
            'mensagem' => $message,
            'preco_atual' => (float)($itemData['price'] ?? 0),
            'preco_recomendado' => $suggestedPrice,
            // Armazenamos o percentual de posição como "variação detectada" para análise posterior.
            'variacao_detectada' => isset($status['position_percentage']) ? (float)$status['position_percentage'] : null,
            'lido' => 0,
            'resolvido' => 0,
        ];
        
        // Persistir no banco
        $stmt = $this->db->prepare("
            INSERT INTO pricing_ranking_alerts 
            (account_id, item_id, tipo_alerta, nivel, mensagem, preco_atual, preco_recomendado, variacao_detectada, lido, resolvido)
            VALUES 
            (:account_id, :item_id, :tipo_alerta, :nivel, :mensagem, :preco_atual, :preco_recomendado, :variacao_detectada, :lido, :resolvido)
        ");
        
        $stmt->execute($alert);
        $alert['id'] = (int)$this->db->lastInsertId();
        $alert['criado_em'] = date('Y-m-d H:i:s');
        
        return $alert;
    }

    /**
     * Constrói mensagem de alerta personalizada
     */
    private function buildAlertMessage(array $itemData, array $rankingData, array $status): string
    {
        $title = $itemData['title'] ?? 'Produto';
        $position = $rankingData['position'] ?? 0;
        $total = $rankingData['total'] ?? 0;
        $percentage = round($status['position_percentage'], 1);
        
        $messages = [
            'excellent' => "✅ {$title} está na posição {$position} de {$total} ({$percentage}% - Excelente)",
            'good' => "👍 {$title} está em boa posição: {$position} de {$total} ({$percentage}%)",
            'warning' => "⚠️ {$title} está perdendo competitividade: posição {$position} de {$total} ({$percentage}%)",
            'danger' => "🔴 URGENTE: {$title} está fora do ranking competitivo: posição {$position} de {$total} ({$percentage}%)",
        ];
        
        return $messages[$status['status']] ?? $messages['warning'];
    }

    /**
     * Calcula preço sugerido para melhorar posição
     */
    private function calculateSuggestedPrice(float $currentPrice, array $rankingData, array $status): ?float
    {
        // Para excellent e good, não sugerir mudança
        if (in_array($status['status'], ['excellent', 'good'])) {
            return null;
        }
        
        $targetPercentage = 10; // Alvo: top 10%
        $lowestPrice = $rankingData['lowest_price'] ?? $currentPrice;
        $averagePrice = $rankingData['average_price'] ?? $currentPrice;
        
        // Calcular preço para atingir a posição alvo
        // Se estamos na posição 20%, queremos ir para 10%
        $priceRange = $rankingData['price_range'] ?? ($currentPrice * 0.3);
        
        // Preço alvo = preço mais baixo + (range * target_percentage)
        $targetPrice = $lowestPrice + ($priceRange * ($targetPercentage / 100));
        
        // Garantir que não seja menor que o preço mínimo viável (assumindo 5% de margem mínima)
        $minViablePrice = $lowestPrice * 1.02; // 2% acima do mais barato
        
        $suggestedPrice = max($targetPrice, $minViablePrice);
        
        // Não sugerir aumento de preço
        if ($suggestedPrice >= $currentPrice) {
            return $currentPrice * 0.95; // Sugere 5% de desconto
        }
        
        return round($suggestedPrice, 2);
    }

    /**
     * Calcula sugestões de ajuste de preço
     */
    private function calculatePriceSuggestions(float $currentPrice, array $rankingData, array $status): array
    {
        $suggestions = [];
        
        // Sugestão para atingir top 5%
        $suggestions['top_5'] = $this->calculateTargetPrice($currentPrice, $rankingData, 5);
        
        // Sugestão para atingir top 10%
        $suggestions['top_10'] = $this->calculateTargetPrice($currentPrice, $rankingData, 10);
        
        // Sugestão para igualar média
        $suggestions['match_average'] = [
            'price' => round($rankingData['average_price'], 2),
            'discount_percentage' => round((($currentPrice - $rankingData['average_price']) / $currentPrice) * 100, 2),
            'description' => 'Igualar preço médio da categoria'
        ];
        
        // Sugestão baseada no status atual
        if ($status['status'] === 'danger') {
            $urgentPrice = $currentPrice * 0.90; // 10% de desconto
            $suggestions['urgent'] = [
                'price' => round($urgentPrice, 2),
                'discount_percentage' => 10,
                'description' => 'Desconto urgente para recuperar posição'
            ];
        }
        
        return $suggestions;
    }

    /**
     * Calcula preço para atingir posição alvo
     */
    private function calculateTargetPrice(float $currentPrice, array $rankingData, int $targetPercentage): array
    {
        $lowestPrice = $rankingData['lowest_price'] ?? $currentPrice;
        $priceRange = $rankingData['price_range'] ?? ($currentPrice * 0.3);
        
        $targetPrice = $lowestPrice + ($priceRange * ($targetPercentage / 100));
        $discountPercentage = (($currentPrice - $targetPrice) / $currentPrice) * 100;
        
        return [
            'price' => round(max($targetPrice, $lowestPrice * 1.01), 2),
            'discount_percentage' => round(max(0, $discountPercentage), 2),
            'description' => "Atingir top {$targetPercentage}% do ranking"
        ];
    }

    /**
     * Executa análise em lote de múltiplos itens
     * 
     * @param array $itemIds Lista de IDs de itens
     * @return array Resultados da análise em lote
     */
    public function analyzeBatch(array $itemIds): array
    {
        $results = [
            'analyzed' => 0,
            'alerts_generated' => 0,
            'items' => [],
            'summary' => [
                'excellent' => 0,
                'good' => 0,
                'warning' => 0,
                'danger' => 0,
            ]
        ];
        
        foreach ($itemIds as $itemId) {
            $analysis = $this->analyzeItem($itemId);
            
            if ($analysis['success']) {
                $results['analyzed']++;
                $status = $analysis['status']['status'] ?? 'unknown';
                
                if (isset($results['summary'][$status])) {
                    $results['summary'][$status]++;
                }
                
                if ($analysis['alert_generated']) {
                    $results['alerts_generated']++;
                }
                
                $results['items'][$itemId] = [
                    'status' => $status,
                    'position_percentage' => $analysis['status']['position_percentage'] ?? 0,
                    'current_price' => $analysis['current_price'],
                    'alert_generated' => $analysis['alert_generated'],
                    'suggested_price' => $analysis['suggestions']['top_10']['price'] ?? null,
                ];
            }
        }
        
        return $results;
    }

    /**
     * Obtém alertas não lidos/não resolvidos
     */
    public function getUnresolvedAlerts(int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_ranking_alerts 
            WHERE account_id = :account_id 
              AND resolvido = 0 
            ORDER BY 
                CASE nivel 
                    WHEN 'vermelho' THEN 1 
                    WHEN 'amarelo' THEN 2 
                    WHEN 'verde' THEN 3 
                END,
                criado_em DESC
            LIMIT {$limitSql}
        ");
        
        $stmt->bindValue('account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca alertas como lidos
     */
    public function markAlertsAsRead(array $alertIds): bool
    {
        if (empty($alertIds)) {
            return false;
        }
        
        $placeholders = implode(',', array_fill(0, count($alertIds), '?'));
        
        $stmt = $this->db->prepare("
            UPDATE pricing_ranking_alerts 
            SET lido = 1
            WHERE id IN ({$placeholders}) AND account_id = ?
        ");
        
        $params = array_merge($alertIds, [$this->accountId]);
        return $stmt->execute($params);
    }

    /**
     * Resolve um alerta (marca como tratado)
     */
    public function resolveAlert(int $alertId, ?string $resolution = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE pricing_ranking_alerts 
            SET resolvido = 1,
                acao_tomada = :resolution,
                resolvido_em = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        
        return $stmt->execute([
            'id' => $alertId,
            'account_id' => $this->accountId,
            'resolution' => $resolution
        ]);
    }

    /**
     * Obtém estatísticas de alertas
     */
    public function getAlertStats(int $days = 30): array
    {
        $days = max(1, min(365, $days));
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));

        $stmt = $this->db->prepare("
            SELECT 
                nivel,
                COUNT(*) as total,
                SUM(CASE WHEN resolvido = 1 THEN 1 ELSE 0 END) as resolved,
                SUM(CASE WHEN resolvido = 0 THEN 1 ELSE 0 END) as pending
            FROM pricing_ranking_alerts
            WHERE account_id = :account_id
              AND criado_em >= :cutoff
            GROUP BY nivel
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'cutoff' => $cutoff,
        ]);

        $byLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalsByType = [
            'excellent' => 0,
            'good' => 0,
            'warning' => 0,
            'danger' => 0,
        ];

        foreach ($byLevel as $row) {
            $nivel = $row['nivel'] ?? null;
            $total = isset($row['total']) ? (int)$row['total'] : 0;
            $type = match ($nivel) {
                'vermelho' => 'danger',
                'amarelo' => 'warning',
                'verde' => 'excellent',
                default => null,
            };
            if ($type !== null) {
                $totalsByType[$type] += $total;
            }
        }

        $byType = [];
        foreach ($totalsByType as $type => $total) {
            $byType[] = ['alert_type' => $type, 'total' => (int)$total];
        }

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_alerts,
                SUM(CASE WHEN resolvido = 0 THEN 1 ELSE 0 END) as pending_alerts,
                AVG(TIMESTAMPDIFF(HOUR, criado_em, COALESCE(resolvido_em, NOW()))) as avg_resolution_hours
            FROM pricing_ranking_alerts
            WHERE account_id = :account_id
              AND criado_em >= :cutoff
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'cutoff' => $cutoff,
        ]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'by_type' => $byType,
            'by_level' => $byLevel,
            'totals' => $totals,
            'period_days' => $days,
        ];
    }

    /**
     * Obtém histórico de alertas de um item
     */
    public function getItemAlertHistory(string $itemId, int $limit = 20): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_ranking_alerts 
            WHERE account_id = :account_id AND item_id = :item_id 
            ORDER BY criado_em DESC
            LIMIT {$limitSql}
        ");
        
        $stmt->bindValue('account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->bindValue('item_id', $itemId, PDO::PARAM_STR);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
