<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * PricingRulesService
 *
 * Gerencia regras automáticas de precificação que podem ser aplicadas
 * a produtos ou categorias inteiras.
 *
 * Tipos de regras suportados:
 * - margin_min: Mantém margem mínima
 * - margin_target: Busca margem alvo
 * - price_floor: Preço mínimo absoluto
 * - price_ceiling: Preço máximo absoluto
 * - competitor_match: Acompanha preço de concorrente
 * - competitor_undercut: Fica X% abaixo do concorrente
 * - ranking_maintain: Mantém posição no ranking
 * - promotional: Regras de promoção temporária
 */
class PricingRulesService
{
    private PDO $db;
    private int $accountId;

    // Tipos de regras disponíveis
    public const RULE_TYPES = [
        'margin_min' => [
            'label' => 'Margem Mínima',
            'description' => 'Nunca permite que a margem fique abaixo do valor configurado',
            'params' => ['min_margin']
        ],
        'margin_target' => [
            'label' => 'Margem Alvo',
            'description' => 'Ajusta preço automaticamente para atingir margem desejada',
            'params' => ['target_margin']
        ],
        'price_floor' => [
            'label' => 'Preço Mínimo',
            'description' => 'Define preço mínimo absoluto para o produto',
            'params' => ['min_price']
        ],
        'price_ceiling' => [
            'label' => 'Preço Máximo',
            'description' => 'Define preço máximo absoluto para o produto',
            'params' => ['max_price']
        ],
        'competitor_match' => [
            'label' => 'Igualar Concorrente',
            'description' => 'Iguala preço do concorrente mais barato',
            'params' => ['max_discount_percent']
        ],
        'competitor_undercut' => [
            'label' => 'Abaixo do Concorrente',
            'description' => 'Mantém preço X% abaixo do concorrente',
            'params' => ['undercut_percent', 'max_discount_percent']
        ],
        'ranking_maintain' => [
            'label' => 'Manter Ranking',
            'description' => 'Ajusta preço para manter posição no ranking',
            'params' => ['target_position_percent', 'max_adjustment_percent']
        ],
        'promotional' => [
            'label' => 'Promoção Temporária',
            'description' => 'Aplica desconto temporário com data de início e fim',
            'params' => ['discount_percent', 'start_date', 'end_date']
        ],
    ];

    // Prioridades de regra (maior = mais importante)
    public const RULE_PRIORITIES = [
        'price_floor' => 100,        // Nunca viola preço mínimo
        'margin_min' => 90,          // Nunca viola margem mínima
        'price_ceiling' => 80,       // Não ultrapassa teto
        'promotional' => 70,         // Promoções são importantes
        'ranking_maintain' => 60,    // Ranking é prioridade média
        'competitor_match' => 50,
        'competitor_undercut' => 50,
        'margin_target' => 40,       // Margem alvo é secundária
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Cria uma nova regra de precificação
     *
     * @param array $ruleData Dados da regra
     * @return array Resultado da criação
     */
    public function createRule(array $ruleData): array
    {
        // Validar tipo de regra
        if (!isset(self::RULE_TYPES[$ruleData['rule_type'] ?? ''])) {
            return [
                'success' => false,
                'error' => 'Tipo de regra inválido'
            ];
        }

        $ruleType = $ruleData['rule_type'];
        $requiredParams = self::RULE_TYPES[$ruleType]['params'];

        // Validar parâmetros obrigatórios
        $params = $ruleData['params'] ?? [];
        foreach ($requiredParams as $param) {
            if (!isset($params[$param])) {
                return [
                    'success' => false,
                    'error' => "Parâmetro obrigatório ausente: {$param}"
                ];
            }
        }

        // Determinar escopo (item, categoria ou global)
        $scope = 'global';
        $scopeId = null;

        if (!empty($ruleData['item_id'])) {
            $scope = 'item';
            $scopeId = $ruleData['item_id'];
        } elseif (!empty($ruleData['category_id'])) {
            $scope = 'category';
            $scopeId = $ruleData['category_id'];
        }

        // Definir prioridade
        $priority = self::RULE_PRIORITIES[$ruleType] ?? 50;
        if (isset($ruleData['priority'])) {
            $priority = (int)$ruleData['priority'];
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO pricing_rules
                (account_id, name, rule_type, scope, scope_id, params, priority, is_active, created_at)
                VALUES
                (:account_id, :name, :rule_type, :scope, :scope_id, :params, :priority, :is_active, NOW())
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'name' => $ruleData['name'] ?? self::RULE_TYPES[$ruleType]['label'],
                'rule_type' => $ruleType,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'params' => json_encode($params),
                'priority' => $priority,
                'is_active' => (int)($ruleData['is_active'] ?? 1)
            ]);

            $ruleId = $this->db->lastInsertId();

            return [
                'success' => true,
                'rule_id' => $ruleId,
                'message' => 'Regra criada com sucesso'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Erro ao criar regra: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza uma regra existente
     */
    public function updateRule(int $ruleId, array $ruleData): array
    {
        try {
            $updates = [];
            $params = ['id' => $ruleId, 'account_id' => $this->accountId];

            if (isset($ruleData['name'])) {
                $updates[] = 'name = :name';
                $params['name'] = $ruleData['name'];
            }

            if (isset($ruleData['params'])) {
                $updates[] = 'params = :params';
                $params['params'] = json_encode($ruleData['params']);
            }

            if (isset($ruleData['priority'])) {
                $updates[] = 'priority = :priority';
                $params['priority'] = (int)$ruleData['priority'];
            }

            if (isset($ruleData['is_active'])) {
                $updates[] = 'is_active = :is_active';
                $params['is_active'] = (int)$ruleData['is_active'];
            }

            if (empty($updates)) {
                return ['success' => false, 'error' => 'Nenhum campo para atualizar'];
            }

            $updates[] = 'updated_at = NOW()';

            $sql = "UPDATE pricing_rules SET " . implode(', ', $updates) .
                " WHERE id = :id AND account_id = :account_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return [
                'success' => true,
                'message' => 'Regra atualizada com sucesso'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Erro ao atualizar regra: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Deleta uma regra
     */
    public function deleteRule(int $ruleId): array
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM pricing_rules
                WHERE id = :id AND account_id = :account_id
            ");

            $stmt->execute([
                'id' => $ruleId,
                'account_id' => $this->accountId
            ]);

            return [
                'success' => true,
                'message' => 'Regra excluída com sucesso'
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Erro ao excluir regra: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lista todas as regras
     */
    public function listRules(?string $scope = null): array
    {
        try {
            $sql = "SELECT * FROM pricing_rules WHERE account_id = :account_id";
            $params = ['account_id' => $this->accountId];

            if ($scope) {
                $sql .= " AND scope = :scope";
                $params['scope'] = $scope;
            }

            $sql .= " ORDER BY priority DESC, created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar params JSON
            foreach ($rules as &$rule) {
                $rule['params'] = json_decode($rule['params'], true) ?? [];
                $rule['type_info'] = self::RULE_TYPES[$rule['rule_type']] ?? null;
            }

            return [
                'success' => true,
                'rules' => $rules,
                'count' => count($rules)
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => 'Erro ao listar regras: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém regras aplicáveis a um item específico
     */
    public function getApplicableRules(string $itemId, ?string $categoryId = null): array
    {
        try {
            $sql = "
                SELECT * FROM pricing_rules
                WHERE account_id = :account_id
                  AND is_active = 1
                  AND (
                      scope = 'global'
                      OR (scope = 'item' AND scope_id = :item_id)
                      " . ($categoryId ? "OR (scope = 'category' AND scope_id = :category_id)" : "") . "
                  )
                ORDER BY priority DESC
            ";

            $params = [
                'account_id' => $this->accountId,
                'item_id' => $itemId
            ];

            if ($categoryId) {
                $params['category_id'] = $categoryId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rules as &$rule) {
                $rule['params'] = json_decode($rule['params'], true) ?? [];
            }

            return $rules;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Aplica regras de precificação a um item e retorna preço recomendado
     *
     * @param string $itemId ID do item
     * @param float $currentPrice Preço atual
     * @param array $context Contexto adicional (custos, categoria, etc)
     * @return array Resultado com preço recomendado e regras aplicadas
     */
    public function applyRules(string $itemId, float $currentPrice, array $context = []): array
    {
        $categoryId = $context['category_id'] ?? null;
        $rules = $this->getApplicableRules($itemId, $categoryId);

        if (empty($rules)) {
            return [
                'success' => true,
                'original_price' => $currentPrice,
                'recommended_price' => $currentPrice,
                'rules_applied' => [],
                'message' => 'Nenhuma regra aplicável encontrada'
            ];
        }

        $recommendedPrice = $currentPrice;
        $appliedRules = [];
        $violations = [];

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule, $recommendedPrice, $context);

            if ($result['applies']) {
                $appliedRules[] = [
                    'rule_id' => $rule['id'],
                    'rule_type' => $rule['rule_type'],
                    'name' => $rule['name'],
                    'action' => $result['action'],
                    'price_before' => $recommendedPrice,
                    'price_after' => $result['price'],
                ];

                if ($result['violation']) {
                    $violations[] = [
                        'rule_type' => $rule['rule_type'],
                        'message' => $result['violation_message']
                    ];
                }

                $recommendedPrice = $result['price'];
            }
        }

        return [
            'success' => true,
            'original_price' => $currentPrice,
            'recommended_price' => round($recommendedPrice, 2),
            'price_change' => round($recommendedPrice - $currentPrice, 2),
            'price_change_percent' => $currentPrice > 0 ? round((($recommendedPrice - $currentPrice) / $currentPrice) * 100, 2) : 0,
            'rules_applied' => $appliedRules,
            'violations' => $violations,
            'total_rules_evaluated' => count($rules)
        ];
    }

    /**
     * Avalia uma regra específica
     */
    private function evaluateRule(array $rule, float $currentPrice, array $context): array
    {
        $params = $rule['params'];
        $ruleType = $rule['rule_type'];

        switch ($ruleType) {
            case 'margin_min':
                return $this->evaluateMarginMinRule($params, $currentPrice, $context);

            case 'margin_target':
                return $this->evaluateMarginTargetRule($params, $currentPrice, $context);

            case 'price_floor':
                return $this->evaluatePriceFloorRule($params, $currentPrice);

            case 'price_ceiling':
                return $this->evaluatePriceCeilingRule($params, $currentPrice);

            case 'competitor_match':
                return $this->evaluateCompetitorMatchRule($params, $currentPrice, $context);

            case 'competitor_undercut':
                return $this->evaluateCompetitorUndercutRule($params, $currentPrice, $context);

            case 'ranking_maintain':
                return $this->evaluateRankingMaintainRule($params, $currentPrice, $context);

            case 'promotional':
                return $this->evaluatePromotionalRule($params, $currentPrice);

            default:
                return ['applies' => false];
        }
    }

    /**
     * Regra: Margem Mínima
     */
    private function evaluateMarginMinRule(array $params, float $currentPrice, array $context): array
    {
        $minMargin = (float)($params['min_margin'] ?? 10);
        $custos = $context['custos'] ?? [];

        if (empty($custos)) {
            return ['applies' => false, 'reason' => 'Custos não informados'];
        }

        // Calcular margem atual
        $custoTotal = $this->calculateTotalCost($currentPrice, $custos);
        $margemAtual = (($currentPrice - $custoTotal) / $currentPrice) * 100;

        if ($margemAtual < $minMargin) {
            // Calcular preço mínimo para atingir margem
            $precoMinimo = $custoTotal / (1 - ($minMargin / 100));

            return [
                'applies' => true,
                'action' => "Ajustado para margem mínima de {$minMargin}%",
                'price' => $precoMinimo,
                'violation' => true,
                'violation_message' => "Margem atual ({$margemAtual}%) abaixo do mínimo ({$minMargin}%)"
            ];
        }

        return ['applies' => false];
    }

    /**
     * Regra: Margem Alvo
     */
    private function evaluateMarginTargetRule(array $params, float $currentPrice, array $context): array
    {
        $targetMargin = (float)($params['target_margin'] ?? 15);
        $custos = $context['custos'] ?? [];

        if (empty($custos)) {
            return ['applies' => false, 'reason' => 'Custos não informados'];
        }

        $custoTotal = $this->calculateTotalCost($currentPrice, $custos);
        $precoAlvo = $custoTotal / (1 - ($targetMargin / 100));

        // Só ajusta se diferença for maior que 1%
        if (abs($precoAlvo - $currentPrice) / $currentPrice > 0.01) {
            return [
                'applies' => true,
                'action' => "Ajustado para margem alvo de {$targetMargin}%",
                'price' => $precoAlvo,
                'violation' => false
            ];
        }

        return ['applies' => false];
    }

    /**
     * Regra: Preço Mínimo
     */
    private function evaluatePriceFloorRule(array $params, float $currentPrice): array
    {
        $minPrice = (float)($params['min_price'] ?? 0);

        if ($currentPrice < $minPrice) {
            return [
                'applies' => true,
                'action' => "Ajustado para preço mínimo de R$ {$minPrice}",
                'price' => $minPrice,
                'violation' => true,
                'violation_message' => "Preço abaixo do mínimo permitido"
            ];
        }

        return ['applies' => false];
    }

    /**
     * Regra: Preço Máximo
     */
    private function evaluatePriceCeilingRule(array $params, float $currentPrice): array
    {
        $maxPrice = (float)($params['max_price'] ?? PHP_FLOAT_MAX);

        if ($currentPrice > $maxPrice) {
            return [
                'applies' => true,
                'action' => "Ajustado para preço máximo de R$ {$maxPrice}",
                'price' => $maxPrice,
                'violation' => true,
                'violation_message' => "Preço acima do máximo permitido"
            ];
        }

        return ['applies' => false];
    }

    /**
     * Regra: Igualar Concorrente
     */
    private function evaluateCompetitorMatchRule(array $params, float $currentPrice, array $context): array
    {
        $competitorPrice = $context['competitor_lowest_price'] ?? null;
        $maxDiscount = (float)($params['max_discount_percent'] ?? 20);

        if (!$competitorPrice || $competitorPrice >= $currentPrice) {
            return ['applies' => false];
        }

        // Calcular desconto necessário
        $discountNeeded = (($currentPrice - $competitorPrice) / $currentPrice) * 100;

        if ($discountNeeded <= $maxDiscount) {
            return [
                'applies' => true,
                'action' => "Igualado ao preço do concorrente",
                'price' => $competitorPrice,
                'violation' => false
            ];
        }

        // Aplica desconto máximo permitido
        $maxDiscountPrice = $currentPrice * (1 - $maxDiscount / 100);
        return [
            'applies' => true,
            'action' => "Desconto máximo de {$maxDiscount}% aplicado (concorrente mais barato)",
            'price' => $maxDiscountPrice,
            'violation' => false
        ];
    }

    /**
     * Regra: Abaixo do Concorrente
     */
    private function evaluateCompetitorUndercutRule(array $params, float $currentPrice, array $context): array
    {
        $competitorPrice = $context['competitor_lowest_price'] ?? null;
        $undercutPercent = (float)($params['undercut_percent'] ?? 5);
        $maxDiscount = (float)($params['max_discount_percent'] ?? 20);

        if (!$competitorPrice) {
            return ['applies' => false];
        }

        $targetPrice = $competitorPrice * (1 - $undercutPercent / 100);
        $discountNeeded = (($currentPrice - $targetPrice) / $currentPrice) * 100;

        if ($discountNeeded <= $maxDiscount && $targetPrice < $currentPrice) {
            return [
                'applies' => true,
                'action' => "Preço {$undercutPercent}% abaixo do concorrente",
                'price' => $targetPrice,
                'violation' => false
            ];
        }

        return ['applies' => false];
    }

    /**
     * Regra: Manter Ranking
     */
    private function evaluateRankingMaintainRule(array $params, float $currentPrice, array $context): array
    {
        $targetPosition = (float)($params['target_position_percent'] ?? 10);
        $maxAdjustment = (float)($params['max_adjustment_percent'] ?? 15);
        $currentPosition = $context['current_ranking_position'] ?? null;
        $priceForTarget = $context['price_for_target_position'] ?? null;

        if (!$currentPosition || !$priceForTarget) {
            return ['applies' => false];
        }

        // Se já está no alvo, não precisa ajustar
        if ($currentPosition <= $targetPosition) {
            return ['applies' => false];
        }

        // Calcular ajuste necessário
        $adjustmentNeeded = $currentPrice > 0 ? (($currentPrice - $priceForTarget) / $currentPrice) * 100 : 0;

        if ($adjustmentNeeded <= $maxAdjustment) {
            return [
                'applies' => true,
                'action' => "Ajustado para atingir top {$targetPosition}% do ranking",
                'price' => $priceForTarget,
                'violation' => false
            ];
        }

        // Aplica ajuste máximo
        $maxAdjustmentPrice = $currentPrice * (1 - $maxAdjustment / 100);
        return [
            'applies' => true,
            'action' => "Ajuste máximo de {$maxAdjustment}% aplicado para melhorar ranking",
            'price' => $maxAdjustmentPrice,
            'violation' => false
        ];
    }

    /**
     * Regra: Promoção Temporária
     */
    private function evaluatePromotionalRule(array $params, float $currentPrice): array
    {
        $discountPercent = (float)($params['discount_percent'] ?? 0);
        $startDate = $params['start_date'] ?? null;
        $endDate = $params['end_date'] ?? null;

        if (!$discountPercent) {
            return ['applies' => false];
        }

        $now = date('Y-m-d H:i:s');

        // Verificar se está no período da promoção
        if ($startDate && $now < $startDate) {
            return ['applies' => false, 'reason' => 'Promoção ainda não iniciou'];
        }

        if ($endDate && $now > $endDate) {
            return ['applies' => false, 'reason' => 'Promoção já encerrou'];
        }

        $promoPrice = $currentPrice * (1 - $discountPercent / 100);

        return [
            'applies' => true,
            'action' => "Promoção de {$discountPercent}% aplicada",
            'price' => $promoPrice,
            'violation' => false
        ];
    }

    /**
     * Calcula custo total considerando taxas
     */
    private function calculateTotalCost(float $preco, array $custos): float
    {
        $custoBase = (float)($custos['custo_producao'] ?? 0);
        $custoEmbalagem = (float)($custos['custo_embalagem'] ?? 0);
        $custoFrete = (float)($custos['custo_frete_gratis'] ?? 0);
        $taxaComissao = (float)($custos['taxa_comissao_ml'] ?? 16) / 100;
        $taxaImposto = (float)($custos['taxa_imposto'] ?? 9) / 100;
        $acos = (float)($custos['acos_medio'] ?? 0) / 100;

        $comissao = $preco * $taxaComissao;
        $imposto = $preco * $taxaImposto;
        $ads = $preco * $acos;

        return $custoBase + $custoEmbalagem + $custoFrete + $comissao + $imposto + $ads;
    }

    /**
     * Obtém tipos de regras disponíveis
     */
    public static function getRuleTypes(): array
    {
        return self::RULE_TYPES;
    }
}
