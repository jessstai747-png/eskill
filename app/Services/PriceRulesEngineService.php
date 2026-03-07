<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Price Rules Engine Service
 *
 * Motor de regras para automação de preços:
 * - Match Competitor: igualar ou ficar X% abaixo/acima do concorrente
 * - Floor/Ceiling: preço mínimo e máximo automático
 * - Time-Based: preços diferentes por horário/dia
 * - Margin-Based: ajustar preço para manter margem mínima
 * - Stock-Based: reduzir preço quando estoque alto
 * - Velocity-Based: ajustar baseado em velocidade de vendas
 *
 * @package App\Services
 */
class PriceRulesEngineService
{
    private int $accountId;
    private PDO $db;
    private MercadoLivreClient $mlClient;

    // Tipos de regras suportadas
    public const RULE_MATCH_COMPETITOR = 'match_competitor';
    public const RULE_FLOOR_CEILING = 'floor_ceiling';
    public const RULE_TIME_BASED = 'time_based';
    public const RULE_MARGIN_BASED = 'margin_based';
    public const RULE_STOCK_BASED = 'stock_based';
    public const RULE_VELOCITY_BASED = 'velocity_based';
    public const RULE_CATEGORY_POSITION = 'category_position';

    // Prioridades
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 5;
    public const PRIORITY_HIGH = 10;
    public const PRIORITY_CRITICAL = 20;

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Criar nova regra de preço
     */
    public function createRule(array $data): array
    {
        $required = ['name', 'rule_type', 'conditions', 'actions'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
            }
        }

        // Validar tipo de regra
        $validTypes = [
            self::RULE_MATCH_COMPETITOR,
            self::RULE_FLOOR_CEILING,
            self::RULE_TIME_BASED,
            self::RULE_MARGIN_BASED,
            self::RULE_STOCK_BASED,
            self::RULE_VELOCITY_BASED,
            self::RULE_CATEGORY_POSITION
        ];

        if (!in_array($data['rule_type'], $validTypes)) {
            return ['success' => false, 'message' => 'Tipo de regra inválido'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO pricing_rules
            (account_id, name, description, rule_type, conditions, actions,
             priority, is_active, applies_to, item_ids, category_ids,
             start_date, end_date, created_at)
            VALUES
            (:account_id, :name, :description, :rule_type, :conditions, :actions,
             :priority, :is_active, :applies_to, :item_ids, :category_ids,
             :start_date, :end_date, NOW())
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rule_type' => $data['rule_type'],
            'conditions' => json_encode($data['conditions']),
            'actions' => json_encode($data['actions']),
            'priority' => $data['priority'] ?? self::PRIORITY_MEDIUM,
            'is_active' => $data['is_active'] ?? true,
            'applies_to' => $data['applies_to'] ?? 'all', // all, items, categories
            'item_ids' => isset($data['item_ids']) ? json_encode($data['item_ids']) : null,
            'category_ids' => isset($data['category_ids']) ? json_encode($data['category_ids']) : null,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null
        ]);

        $ruleId = (int) $this->db->lastInsertId();

        $this->logRuleAction($ruleId, 'created', $data);

        return [
            'success' => true,
            'rule_id' => $ruleId,
            'message' => 'Regra criada com sucesso'
        ];
    }

    /**
     * Listar regras do account
     */
    public function listRules(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (isset($filters['rule_type'])) {
            $where[] = 'rule_type = :rule_type';
            $params['rule_type'] = $filters['rule_type'];
        }

        if (isset($filters['is_active'])) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = $filters['is_active'];
        }

        $whereClause = implode(' AND ', $where);

        // Whitelist ORDER BY to prevent SQL injection
        $allowedOrders = [
            'priority_desc' => 'priority DESC, created_at DESC',
            'priority_asc' => 'priority ASC, created_at DESC',
            'created_desc' => 'created_at DESC',
            'created_asc' => 'created_at ASC',
            'name_asc' => 'name ASC',
            'name_desc' => 'name DESC',
        ];
        $orderKey = $filters['order_by'] ?? 'priority_desc';
        $orderBy = $allowedOrders[$orderKey] ?? 'priority DESC, created_at DESC';

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);

        $stmt = $this->db->prepare("
            SELECT * FROM pricing_rules
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode JSON fields
        foreach ($rules as &$rule) {
            $rule['conditions'] = json_decode($rule['conditions'], true);
            $rule['actions'] = json_decode($rule['actions'], true);
            $rule['item_ids'] = $rule['item_ids'] ? json_decode($rule['item_ids'], true) : [];
            $rule['category_ids'] = $rule['category_ids'] ? json_decode($rule['category_ids'], true) : [];
        }

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM pricing_rules WHERE {$whereClause}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue(":{$key}", $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        return [
            'success' => true,
            'rules' => $rules,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Obter regra por ID
     */
    public function getRule(int $ruleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_rules
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $ruleId, 'account_id' => $this->accountId]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rule) {
            return null;
        }

        $rule['conditions'] = json_decode($rule['conditions'], true);
        $rule['actions'] = json_decode($rule['actions'], true);
        $rule['item_ids'] = $rule['item_ids'] ? json_decode($rule['item_ids'], true) : [];
        $rule['category_ids'] = $rule['category_ids'] ? json_decode($rule['category_ids'], true) : [];

        // Carregar histórico de execuções
        $execStmt = $this->db->prepare("
            SELECT * FROM pricing_rule_executions
            WHERE rule_id = :rule_id
            ORDER BY executed_at DESC
            LIMIT 10
        ");
        $execStmt->execute(['rule_id' => $ruleId]);
        $rule['recent_executions'] = $execStmt->fetchAll(PDO::FETCH_ASSOC);

        return $rule;
    }

    /**
     * Atualizar regra
     */
    public function updateRule(int $ruleId, array $data): array
    {
        $rule = $this->getRule($ruleId);
        if (!$rule) {
            return ['success' => false, 'message' => 'Regra não encontrada'];
        }

        $updates = [];
        $params = ['id' => $ruleId, 'account_id' => $this->accountId];

        $allowedFields = [
            'name',
            'description',
            'rule_type',
            'conditions',
            'actions',
            'priority',
            'is_active',
            'applies_to',
            'item_ids',
            'category_ids',
            'start_date',
            'end_date'
        ];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (in_array($field, ['conditions', 'actions', 'item_ids', 'category_ids'])) {
                    $value = json_encode($value);
                }
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => 'Nenhum campo para atualizar'];
        }

        $updates[] = "updated_at = NOW()";
        $updateClause = implode(', ', $updates);

        $stmt = $this->db->prepare("
            UPDATE pricing_rules
            SET {$updateClause}
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute($params);

        $this->logRuleAction($ruleId, 'updated', $data);

        return ['success' => true, 'message' => 'Regra atualizada com sucesso'];
    }

    /**
     * Deletar regra
     */
    public function deleteRule(int $ruleId): array
    {
        $rule = $this->getRule($ruleId);
        if (!$rule) {
            return ['success' => false, 'message' => 'Regra não encontrada'];
        }

        $stmt = $this->db->prepare("
            DELETE FROM pricing_rules
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $ruleId, 'account_id' => $this->accountId]);

        $this->logRuleAction($ruleId, 'deleted', ['rule_name' => $rule['name']]);

        return ['success' => true, 'message' => 'Regra excluída com sucesso'];
    }

    /**
     * Ativar/Desativar regra
     */
    public function toggleRule(int $ruleId, bool $active): array
    {
        $stmt = $this->db->prepare("
            UPDATE pricing_rules
            SET is_active = :is_active, updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            'is_active' => $active,
            'id' => $ruleId,
            'account_id' => $this->accountId
        ]);

        $action = $active ? 'activated' : 'deactivated';
        $this->logRuleAction($ruleId, $action, []);

        return [
            'success' => true,
            'message' => $active ? 'Regra ativada' : 'Regra desativada'
        ];
    }

    /**
     * Executar todas as regras ativas para um item
     */
    public function executeRulesForItem(string $itemId): array
    {
        // Obter item do ML
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return ['success' => false, 'message' => 'Item não encontrado'];
        }

        $currentPrice = (float)($item['price'] ?? 0);
        $categoryId = $item['category_id'] ?? null;

        // Buscar regras aplicáveis
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_rules
            WHERE account_id = :account_id
            AND is_active = 1
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
            AND (
                applies_to = 'all'
                OR (applies_to = 'items' AND JSON_CONTAINS(item_ids, :item_id_json))
                OR (applies_to = 'categories' AND JSON_CONTAINS(category_ids, :category_id_json))
            )
            ORDER BY priority DESC
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id_json' => json_encode($itemId),
            'category_id_json' => json_encode($categoryId)
        ]);

        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $appliedRules = [];
        $newPrice = $currentPrice;

        foreach ($rules as $rule) {
            $conditions = json_decode($rule['conditions'], true);
            $actions = json_decode($rule['actions'], true);

            // Verificar condições
            if (!$this->evaluateConditions($conditions, $item, $currentPrice)) {
                continue;
            }

            // Aplicar ação
            $result = $this->applyAction($rule['rule_type'], $actions, $item, $newPrice);

            if ($result['applied']) {
                $newPrice = $result['new_price'];
                $appliedRules[] = [
                    'rule_id' => $rule['id'],
                    'rule_name' => $rule['name'],
                    'rule_type' => $rule['rule_type'],
                    'previous_price' => $result['previous_price'],
                    'new_price' => $result['new_price'],
                    'reason' => $result['reason']
                ];

                // Registrar execução
                $this->recordExecution($rule['id'], $itemId, $result);
            }
        }

        // Aplicar preço se mudou
        $priceChanged = abs($newPrice - $currentPrice) > 0.01;
        if ($priceChanged && !empty($appliedRules)) {
            $applyResult = $this->mlClient->put("/items/{$itemId}", ['price' => $newPrice]);

            if (!$applyResult || isset($applyResult['error'])) {
                return [
                    'success' => false,
                    'message' => 'Erro ao aplicar novo preço no ML',
                    'applied_rules' => $appliedRules
                ];
            }
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'original_price' => $currentPrice,
            'final_price' => $newPrice,
            'price_changed' => $priceChanged,
            'applied_rules' => $appliedRules,
            'rules_evaluated' => count($rules)
        ];
    }

    /**
     * Executar regras para todos os itens (worker)
     */
    public function executeAllRules(): array
    {
        $results = [
            'total_items' => 0,
            'items_updated' => 0,
            'rules_applied' => 0,
            'errors' => [],
            'details' => []
        ];

        // Buscar todos os itens ativos
        $stmt = $this->db->prepare("
            SELECT DISTINCT item_id FROM item_costs
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($items as $itemId) {
            $results['total_items']++;

            try {
                $result = $this->executeRulesForItem($itemId);

                if ($result['success'] && $result['price_changed']) {
                    $results['items_updated']++;
                    $results['rules_applied'] += count($result['applied_rules']);
                    $results['details'][] = [
                        'item_id' => $itemId,
                        'old_price' => $result['original_price'],
                        'new_price' => $result['final_price'],
                        'rules' => array_column($result['applied_rules'], 'rule_name')
                    ];
                }
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'item_id' => $itemId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Simular execução de regras (sem aplicar)
     */
    public function simulateRules(string $itemId): array
    {
        $item = $this->mlClient->get("/items/{$itemId}");
        if (!$item || isset($item['error'])) {
            return ['success' => false, 'message' => 'Item não encontrado'];
        }

        $currentPrice = (float)($item['price'] ?? 0);
        $categoryId = $item['category_id'] ?? null;

        $stmt = $this->db->prepare("
            SELECT * FROM pricing_rules
            WHERE account_id = :account_id
            AND is_active = 1
            ORDER BY priority DESC
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $simulation = [];
        $simulatedPrice = $currentPrice;

        foreach ($rules as $rule) {
            $conditions = json_decode($rule['conditions'], true);
            $actions = json_decode($rule['actions'], true);

            $wouldApply = $this->evaluateConditions($conditions, $item, $simulatedPrice);

            $simResult = [
                'rule_id' => $rule['id'],
                'rule_name' => $rule['name'],
                'rule_type' => $rule['rule_type'],
                'priority' => $rule['priority'],
                'conditions_met' => $wouldApply,
                'would_apply' => false,
                'new_price' => null,
                'reason' => null
            ];

            if ($wouldApply) {
                $result = $this->applyAction($rule['rule_type'], $actions, $item, $simulatedPrice);
                $simResult['would_apply'] = $result['applied'];
                $simResult['new_price'] = $result['new_price'];
                $simResult['reason'] = $result['reason'];

                if ($result['applied']) {
                    $simulatedPrice = $result['new_price'];
                }
            }

            $simulation[] = $simResult;
        }

        return [
            'success' => true,
            'item_id' => $itemId,
            'item_title' => $item['title'] ?? '',
            'current_price' => $currentPrice,
            'simulated_price' => $simulatedPrice,
            'price_change' => $simulatedPrice - $currentPrice,
            'price_change_percent' => $currentPrice > 0
                ? round((($simulatedPrice - $currentPrice) / $currentPrice) * 100, 2)
                : 0,
            'simulation' => $simulation
        ];
    }

    /**
     * Avaliar condições da regra
     */
    private function evaluateConditions(array $conditions, array $item, float $currentPrice): bool
    {
        foreach ($conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '==';
            $value = $condition['value'] ?? null;

            $itemValue = $this->getFieldValue($field, $item, $currentPrice);

            if (!$this->compareValues($itemValue, $operator, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obter valor do campo para comparação
     */
    private function getFieldValue(string $field, array $item, float $currentPrice): mixed
    {
        return match ($field) {
            'price' => $currentPrice,
            'stock' => $item['available_quantity'] ?? 0,
            'sold_quantity' => $item['sold_quantity'] ?? 0,
            'condition' => $item['condition'] ?? 'new',
            'category_id' => $item['category_id'] ?? '',
            'listing_type' => $item['listing_type_id'] ?? '',
            'status' => $item['status'] ?? '',
            'free_shipping' => $this->hasFreeSHipping($item),
            'hour' => (int)date('H'),
            'day_of_week' => (int)date('w'),
            'day_of_month' => (int)date('j'),
            default => null
        };
    }

    /**
     * Comparar valores
     */
    private function compareValues(mixed $a, string $operator, mixed $b): bool
    {
        return match ($operator) {
            '==' => $a == $b,
            '!=' => $a != $b,
            '>' => $a > $b,
            '>=' => $a >= $b,
            '<' => $a < $b,
            '<=' => $a <= $b,
            'in' => is_array($b) && in_array($a, $b),
            'not_in' => is_array($b) && !in_array($a, $b),
            'contains' => is_string($a) && is_string($b) && str_contains($a, $b),
            'between' => is_array($b) && count($b) === 2 && $a >= $b[0] && $a <= $b[1],
            default => false
        };
    }

    /**
     * Aplicar ação da regra
     */
    private function applyAction(string $ruleType, array $actions, array $item, float $currentPrice): array
    {
        return match ($ruleType) {
            self::RULE_MATCH_COMPETITOR => $this->applyMatchCompetitor($actions, $item, $currentPrice),
            self::RULE_FLOOR_CEILING => $this->applyFloorCeiling($actions, $currentPrice),
            self::RULE_TIME_BASED => $this->applyTimeBased($actions, $currentPrice),
            self::RULE_MARGIN_BASED => $this->applyMarginBased($actions, $item, $currentPrice),
            self::RULE_STOCK_BASED => $this->applyStockBased($actions, $item, $currentPrice),
            self::RULE_VELOCITY_BASED => $this->applyVelocityBased($actions, $item, $currentPrice),
            self::RULE_CATEGORY_POSITION => $this->applyCategoryPosition($actions, $item, $currentPrice),
            default => ['applied' => false, 'new_price' => $currentPrice, 'previous_price' => $currentPrice, 'reason' => 'Tipo de regra desconhecido']
        };
    }

    /**
     * Match Competitor: igualar ou ficar X% abaixo/acima
     */
    private function applyMatchCompetitor(array $actions, array $item, float $currentPrice): array
    {
        $competitorPrice = $this->getCompetitorPrice($item);
        if (!$competitorPrice) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Preço de concorrente não encontrado'
            ];
        }

        $adjustment = $actions['adjustment'] ?? 0; // percentual
        $adjustmentType = $actions['adjustment_type'] ?? 'below'; // below, above, match

        $newPrice = match ($adjustmentType) {
            'below' => $competitorPrice * (1 - ($adjustment / 100)),
            'above' => $competitorPrice * (1 + ($adjustment / 100)),
            'match' => $competitorPrice,
            default => $competitorPrice
        };

        // Aplicar limites se definidos
        if (isset($actions['min_price']) && $newPrice < $actions['min_price']) {
            $newPrice = $actions['min_price'];
        }
        if (isset($actions['max_price']) && $newPrice > $actions['max_price']) {
            $newPrice = $actions['max_price'];
        }

        return [
            'applied' => true,
            'new_price' => round($newPrice, 2),
            'previous_price' => $currentPrice,
            'reason' => "Ajustado para {$adjustment}% {$adjustmentType} concorrente (R$ " . number_format($competitorPrice, 2, ',', '.') . ")"
        ];
    }

    /**
     * Floor/Ceiling: preço mínimo e máximo
     */
    private function applyFloorCeiling(array $actions, float $currentPrice): array
    {
        $floor = $actions['floor'] ?? null;
        $ceiling = $actions['ceiling'] ?? null;
        $newPrice = $currentPrice;
        $reason = '';

        if ($floor !== null && $currentPrice < $floor) {
            $newPrice = $floor;
            $reason = "Preço abaixo do mínimo (R$ " . number_format($floor, 2, ',', '.') . ")";
        } elseif ($ceiling !== null && $currentPrice > $ceiling) {
            $newPrice = $ceiling;
            $reason = "Preço acima do máximo (R$ " . number_format($ceiling, 2, ',', '.') . ")";
        }

        return [
            'applied' => $newPrice !== $currentPrice,
            'new_price' => round($newPrice, 2),
            'previous_price' => $currentPrice,
            'reason' => $reason ?: 'Preço dentro dos limites'
        ];
    }

    /**
     * Time-Based: preços por horário/dia
     */
    private function applyTimeBased(array $actions, float $currentPrice): array
    {
        $schedules = $actions['schedules'] ?? [];
        $currentHour = (int)date('H');
        $currentDay = (int)date('w'); // 0=domingo

        foreach ($schedules as $schedule) {
            $hours = $schedule['hours'] ?? [0, 23];
            $days = $schedule['days'] ?? [0, 1, 2, 3, 4, 5, 6];
            $priceModifier = $schedule['price_modifier'] ?? 0;
            $modifierType = $schedule['modifier_type'] ?? 'percent'; // percent, fixed

            if ($currentHour >= $hours[0] && $currentHour <= $hours[1] && in_array($currentDay, $days)) {
                $newPrice = $modifierType === 'percent'
                    ? $currentPrice * (1 + ($priceModifier / 100))
                    : $currentPrice + $priceModifier;

                return [
                    'applied' => true,
                    'new_price' => round($newPrice, 2),
                    'previous_price' => $currentPrice,
                    'reason' => "Preço ajustado por horário ({$currentHour}h): {$priceModifier}" . ($modifierType === 'percent' ? '%' : ' R$')
                ];
            }
        }

        return [
            'applied' => false,
            'new_price' => $currentPrice,
            'previous_price' => $currentPrice,
            'reason' => 'Nenhum horário programado ativo'
        ];
    }

    /**
     * Margin-Based: manter margem mínima
     */
    private function applyMarginBased(array $actions, array $item, float $currentPrice): array
    {
        $minMargin = $actions['min_margin'] ?? 10;
        $targetMargin = $actions['target_margin'] ?? 15;

        // Buscar custos do item
        $stmt = $this->db->prepare("
            SELECT * FROM item_costs
            WHERE account_id = :account_id AND item_id = :item_id
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $item['id']
        ]);
        $costs = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$costs) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Custos não cadastrados para o item'
            ];
        }

        $totalCost = (float)($costs['product_cost'] ?? 0)
            + (float)($costs['shipping_cost'] ?? 0)
            + (float)($costs['packaging_cost'] ?? 0);

        $commissionRate = (float)($costs['ml_commission'] ?? 16) / 100;
        $taxRate = (float)($costs['tax_rate'] ?? 9) / 100;

        // Calcular preço para margem alvo
        // price - (price * commission) - (price * tax) - cost = price * target_margin
        // price * (1 - commission - tax - target_margin) = cost
        // price = cost / (1 - commission - tax - target_margin)

        $targetMarginRate = $targetMargin / 100;
        $denominator = 1 - $commissionRate - $taxRate - $targetMarginRate;

        if ($denominator <= 0) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Margem alvo impossível com custos atuais'
            ];
        }

        $targetPrice = $totalCost / $denominator;

        // Verificar margem atual
        $currentMargin = (($currentPrice * (1 - $commissionRate - $taxRate)) - $totalCost) / $currentPrice * 100;

        if ($currentMargin < $minMargin) {
            return [
                'applied' => true,
                'new_price' => round($targetPrice, 2),
                'previous_price' => $currentPrice,
                'reason' => "Margem atual ({$currentMargin}%) abaixo do mínimo ({$minMargin}%)"
            ];
        }

        return [
            'applied' => false,
            'new_price' => $currentPrice,
            'previous_price' => $currentPrice,
            'reason' => "Margem atual ({$currentMargin}%) adequada"
        ];
    }

    /**
     * Stock-Based: ajustar baseado em estoque
     */
    private function applyStockBased(array $actions, array $item, float $currentPrice): array
    {
        $stock = (int)($item['available_quantity'] ?? 0);
        $thresholds = $actions['thresholds'] ?? [];

        // Ordenar thresholds do maior para o menor
        usort($thresholds, fn($a, $b) => $b['stock'] - $a['stock']);

        foreach ($thresholds as $threshold) {
            if ($stock >= $threshold['stock']) {
                $modifier = $threshold['price_modifier'] ?? 0;
                $newPrice = $currentPrice * (1 + ($modifier / 100));

                return [
                    'applied' => true,
                    'new_price' => round($newPrice, 2),
                    'previous_price' => $currentPrice,
                    'reason' => "Estoque alto ({$stock} unidades): {$modifier}% de ajuste"
                ];
            }
        }

        return [
            'applied' => false,
            'new_price' => $currentPrice,
            'previous_price' => $currentPrice,
            'reason' => "Estoque ({$stock}) não atingiu nenhum threshold"
        ];
    }

    /**
     * Velocity-Based: ajustar baseado em velocidade de vendas
     */
    private function applyVelocityBased(array $actions, array $item, float $currentPrice): array
    {
        $soldQty = (int)($item['sold_quantity'] ?? 0);
        $createdDate = $item['date_created'] ?? null;

        if (!$createdDate) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Data de criação não disponível'
            ];
        }

        $daysSinceCreation = max(1, (time() - strtotime($createdDate)) / 86400);
        $velocity = $soldQty / $daysSinceCreation; // vendas por dia

        $thresholds = $actions['velocity_thresholds'] ?? [];
        usort($thresholds, fn($a, $b) => $b['velocity'] - $a['velocity']);

        foreach ($thresholds as $threshold) {
            if ($velocity >= $threshold['velocity']) {
                $modifier = $threshold['price_modifier'] ?? 0;
                $newPrice = $currentPrice * (1 + ($modifier / 100));

                return [
                    'applied' => true,
                    'new_price' => round($newPrice, 2),
                    'previous_price' => $currentPrice,
                    'reason' => sprintf(
                        "Velocidade alta (%.2f/dia): %d%% de ajuste",
                        $velocity,
                        $modifier
                    )
                ];
            }
        }

        return [
            'applied' => false,
            'new_price' => $currentPrice,
            'previous_price' => $currentPrice,
            'reason' => sprintf("Velocidade (%.2f/dia) não atingiu thresholds", $velocity)
        ];
    }

    /**
     * Category Position: manter posição no ranking da categoria
     */
    private function applyCategoryPosition(array $actions, array $item, float $currentPrice): array
    {
        $targetPosition = $actions['target_position'] ?? 3;
        $maxAdjustment = $actions['max_adjustment'] ?? 15; // máximo % de ajuste

        $categoryId = $item['category_id'] ?? null;
        if (!$categoryId) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Categoria não disponível'
            ];
        }

        // Buscar preços na categoria
        $searchResult = $this->mlClient->get("/sites/MLB/search?category={$categoryId}&sort=price_asc&limit=20");
        $results = $searchResult['results'] ?? [];

        if (count($results) < $targetPosition) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Poucos resultados na categoria'
            ];
        }

        // Preço na posição alvo
        $targetPrice = (float)($results[$targetPosition - 1]['price'] ?? 0);

        if ($targetPrice <= 0) {
            return [
                'applied' => false,
                'new_price' => $currentPrice,
                'previous_price' => $currentPrice,
                'reason' => 'Preço alvo inválido'
            ];
        }

        // Calcular ajuste necessário
        $adjustment = (($targetPrice - $currentPrice) / $currentPrice) * 100;

        // Limitar ajuste máximo
        if (abs($adjustment) > $maxAdjustment) {
            $adjustment = $adjustment > 0 ? $maxAdjustment : -$maxAdjustment;
        }

        $newPrice = $currentPrice * (1 + ($adjustment / 100));

        return [
            'applied' => abs($adjustment) > 0.5, // só aplica se > 0.5%
            'new_price' => round($newPrice, 2),
            'previous_price' => $currentPrice,
            'reason' => sprintf(
                "Ajuste para posição %d (preço alvo: R$ %.2f)",
                $targetPosition,
                $targetPrice
            )
        ];
    }

    /**
     * Obter preço do concorrente principal
     */
    private function getCompetitorPrice(array $item): ?float
    {
        $categoryId = $item['category_id'] ?? null;
        $title = $item['title'] ?? '';

        if (!$categoryId) {
            return null;
        }

        // Buscar no cache primeiro
        $stmt = $this->db->prepare("
            SELECT competitor_price FROM competitor_prices_cache
            WHERE account_id = :account_id AND item_id = :item_id
            AND updated_at > DATE_SUB(NOW(), INTERVAL 6 HOUR)
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $item['id']
        ]);
        $cached = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cached) {
            return (float)$cached['competitor_price'];
        }

        // Buscar no ML
        $keywords = $this->extractKeywords($title);
        $searchResult = $this->mlClient->get(
            "/sites/MLB/search?category={$categoryId}&q=" . urlencode($keywords) . "&sort=price_asc&limit=10"
        );

        $results = $searchResult['results'] ?? [];

        // Encontrar menor preço que não seja o próprio item
        foreach ($results as $result) {
            if ($result['id'] !== $item['id']) {
                $competitorPrice = (float)($result['price'] ?? 0);

                // Cachear resultado
                $this->cacheCompetitorPrice($item['id'], $competitorPrice);

                return $competitorPrice;
            }
        }

        return null;
    }

    /**
     * Cachear preço do concorrente
     */
    private function cacheCompetitorPrice(string $itemId, float $price): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO competitor_prices_cache (account_id, item_id, competitor_price, updated_at)
            VALUES (:account_id, :item_id, :price, NOW())
            ON DUPLICATE KEY UPDATE competitor_price = :price2, updated_at = NOW()
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $itemId,
            'price' => $price,
            'price2' => $price
        ]);
    }

    /**
     * Extrair keywords do título
     */
    private function extractKeywords(string $title): string
    {
        // Remover palavras comuns
        $stopWords = ['de', 'da', 'do', 'para', 'com', 'sem', 'e', 'ou', 'a', 'o', 'um', 'uma'];
        $words = preg_split('/\s+/', strtolower($title));
        $keywords = array_filter($words, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stopWords));

        return implode(' ', array_slice($keywords, 0, 5));
    }

    /**
     * Verificar frete grátis
     */
    private function hasFreeShipping(array $item): bool
    {
        return ($item['shipping']['free_shipping'] ?? false) === true;
    }

    /**
     * Registrar execução de regra
     */
    private function recordExecution(int $ruleId, string $itemId, array $result): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_rule_executions
            (rule_id, item_id, previous_price, new_price, reason, executed_at)
            VALUES (:rule_id, :item_id, :previous_price, :new_price, :reason, NOW())
        ");
        $stmt->execute([
            'rule_id' => $ruleId,
            'item_id' => $itemId,
            'previous_price' => $result['previous_price'],
            'new_price' => $result['new_price'],
            'reason' => $result['reason']
        ]);
    }

    /**
     * Log de ação em regra
     */
    private function logRuleAction(int $ruleId, string $action, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_rule_logs (rule_id, action, data, created_at)
            VALUES (:rule_id, :action, :data, NOW())
        ");
        $stmt->execute([
            'rule_id' => $ruleId,
            'action' => $action,
            'data' => json_encode($data)
        ]);
    }

    /**
     * Obter templates de regras predefinidas
     */
    public function getRuleTemplates(): array
    {
        return [
            [
                'name' => 'Match Menor Preço',
                'rule_type' => self::RULE_MATCH_COMPETITOR,
                'description' => 'Iguala ao menor preço do concorrente',
                'conditions' => [],
                'actions' => [
                    'adjustment' => 0,
                    'adjustment_type' => 'match'
                ]
            ],
            [
                'name' => '5% Abaixo do Concorrente',
                'rule_type' => self::RULE_MATCH_COMPETITOR,
                'description' => 'Mantém preço 5% abaixo do menor concorrente',
                'conditions' => [],
                'actions' => [
                    'adjustment' => 5,
                    'adjustment_type' => 'below'
                ]
            ],
            [
                'name' => 'Preço Mínimo R$50',
                'rule_type' => self::RULE_FLOOR_CEILING,
                'description' => 'Não permite preço abaixo de R$50',
                'conditions' => [],
                'actions' => [
                    'floor' => 50,
                    'ceiling' => null
                ]
            ],
            [
                'name' => 'Promoção Noturna',
                'rule_type' => self::RULE_TIME_BASED,
                'description' => '10% de desconto entre 22h e 6h',
                'conditions' => [],
                'actions' => [
                    'schedules' => [
                        ['hours' => [22, 23], 'days' => [0, 1, 2, 3, 4, 5, 6], 'price_modifier' => -10, 'modifier_type' => 'percent'],
                        ['hours' => [0, 6], 'days' => [0, 1, 2, 3, 4, 5, 6], 'price_modifier' => -10, 'modifier_type' => 'percent']
                    ]
                ]
            ],
            [
                'name' => 'Margem Mínima 15%',
                'rule_type' => self::RULE_MARGIN_BASED,
                'description' => 'Garante margem mínima de 15%',
                'conditions' => [],
                'actions' => [
                    'min_margin' => 15,
                    'target_margin' => 20
                ]
            ],
            [
                'name' => 'Liquidação Estoque Alto',
                'rule_type' => self::RULE_STOCK_BASED,
                'description' => 'Desconto progressivo por estoque',
                'conditions' => [],
                'actions' => [
                    'thresholds' => [
                        ['stock' => 100, 'price_modifier' => -15],
                        ['stock' => 50, 'price_modifier' => -10],
                        ['stock' => 20, 'price_modifier' => -5]
                    ]
                ]
            ],
            [
                'name' => 'Aumento por Demanda',
                'rule_type' => self::RULE_VELOCITY_BASED,
                'description' => 'Aumenta preço quando vendendo bem',
                'conditions' => [],
                'actions' => [
                    'velocity_thresholds' => [
                        ['velocity' => 10, 'price_modifier' => 10],
                        ['velocity' => 5, 'price_modifier' => 5],
                        ['velocity' => 2, 'price_modifier' => 2]
                    ]
                ]
            ],
            [
                'name' => 'Top 3 da Categoria',
                'rule_type' => self::RULE_CATEGORY_POSITION,
                'description' => 'Mantém entre os 3 mais baratos',
                'conditions' => [],
                'actions' => [
                    'target_position' => 3,
                    'max_adjustment' => 15
                ]
            ]
        ];
    }
}
