<?php

namespace App\Services;

use App\Database;
use Exception;

/**
 * CloneTemplateService
 * 
 * Gerencia templates de clonagem e aplica regras de negócio
 */
class CloneTemplateService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Lista todos os templates ativos
     * 
     * @param int|null $accountId ID da conta (opcional, para filtro futuro)
     * @param bool $includeInactive Se deve incluir templates inativos
     */
    public function listTemplates(?int $accountId = null, bool $includeInactive = false): array
    {
        $sql = "SELECT * FROM clone_templates";
        if (!$includeInactive) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY is_system DESC, usage_count DESC, name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtém um template por ID ou slug
     */
    public function getTemplate($idOrSlug): ?array
    {
        $column = is_numeric($idOrSlug) ? 'id' : 'slug';
        
        $stmt = $this->db->prepare("SELECT * FROM clone_templates WHERE {$column} = :value");
        $stmt->execute(['value' => $idOrSlug]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cria um novo template
     */
    public function createTemplate(array $data): array
    {
        $slug = $data['slug'] ?? $this->generateSlug($data['name']);

        // Verificar se slug já existe
        $existing = $this->getTemplate($slug);
        if ($existing) {
            throw new Exception("Template com slug '{$slug}' já existe");
        }

        $stmt = $this->db->prepare("
            INSERT INTO clone_templates (
                name, slug, description, icon, color,
                pricing_type, pricing_value, pricing_round_to,
                stock_type, stock_value,
                title_prefix, title_suffix, title_remove_patterns,
                initial_status, clone_description, clone_variations,
                skip_catalog_items, skip_non_catalog_items,
                post_clone_actions, is_system, is_active, created_by_user_id
            ) VALUES (
                :name, :slug, :description, :icon, :color,
                :pricing_type, :pricing_value, :pricing_round_to,
                :stock_type, :stock_value,
                :title_prefix, :title_suffix, :title_remove_patterns,
                :initial_status, :clone_description, :clone_variations,
                :skip_catalog_items, :skip_non_catalog_items,
                :post_clone_actions, 0, 1, :user_id
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? 'bi-files',
            'color' => $data['color'] ?? 'primary',
            'pricing_type' => $data['pricing_type'] ?? 'copy',
            'pricing_value' => $data['pricing_value'] ?? null,
            'pricing_round_to' => $data['pricing_round_to'] ?? null,
            'stock_type' => $data['stock_type'] ?? 'copy',
            'stock_value' => $data['stock_value'] ?? null,
            'title_prefix' => $data['title_prefix'] ?? null,
            'title_suffix' => $data['title_suffix'] ?? null,
            'title_remove_patterns' => isset($data['title_remove_patterns']) ? json_encode($data['title_remove_patterns']) : null,
            'initial_status' => $data['initial_status'] ?? 'paused',
            'clone_description' => $data['clone_description'] ?? 1,
            'clone_variations' => $data['clone_variations'] ?? 1,
            'skip_catalog_items' => $data['skip_catalog_items'] ?? 0,
            'skip_non_catalog_items' => $data['skip_non_catalog_items'] ?? 0,
            'post_clone_actions' => isset($data['post_clone_actions']) ? json_encode($data['post_clone_actions']) : null,
            'user_id' => $data['user_id'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();

        return $this->getTemplate($id);
    }

    /**
     * Atualiza um template existente
     */
    public function updateTemplate(int $id, array $data): array
    {
        $template = $this->getTemplate($id);
        if (!$template) {
            throw new Exception("Template não encontrado");
        }

        if ($template['is_system']) {
            throw new Exception("Templates de sistema não podem ser editados");
        }

        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'name', 'description', 'icon', 'color',
            'pricing_type', 'pricing_value', 'pricing_round_to',
            'stock_type', 'stock_value',
            'title_prefix', 'title_suffix',
            'initial_status', 'clone_description', 'clone_variations',
            'skip_catalog_items', 'skip_non_catalog_items', 'is_active',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        // Handle JSON fields
        if (isset($data['title_remove_patterns'])) {
            $fields[] = "title_remove_patterns = :title_remove_patterns";
            $params['title_remove_patterns'] = json_encode($data['title_remove_patterns']);
        }

        if (isset($data['post_clone_actions'])) {
            $fields[] = "post_clone_actions = :post_clone_actions";
            $params['post_clone_actions'] = json_encode($data['post_clone_actions']);
        }

        if (empty($fields)) {
            return $template;
        }

        $sql = "UPDATE clone_templates SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getTemplate($id);
    }

    /**
     * Exclui um template (soft delete via is_active)
     */
    public function deleteTemplate(int $id): bool
    {
        $template = $this->getTemplate($id);
        if (!$template) {
            throw new Exception("Template não encontrado");
        }

        if ($template['is_system']) {
            throw new Exception("Templates de sistema não podem ser excluídos");
        }

        $stmt = $this->db->prepare("UPDATE clone_templates SET is_active = 0 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        return true;
    }

    /**
     * Incrementa contador de uso do template
     */
    public function incrementUsage(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE clone_templates SET usage_count = usage_count + 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * Aplica regras do template a um item
     * 
     * @param array $template Template configurado
     * @param array $sourceItem Dados do item origem
     * @return array Payload modificado com regras aplicadas
     */
    public function applyTemplateRules(array $template, array $sourceItem): array
    {
        $result = [
            'pricing_strategy' => $this->buildPricingStrategy($template),
            'stock_strategy' => $this->buildStockStrategy($template),
            'options' => $this->buildOptions($template),
            'calculated' => [],
        ];

        // Calcular preço final
        $originalPrice = (float) ($sourceItem['price'] ?? 0);
        $result['calculated']['original_price'] = $originalPrice;
        $result['calculated']['final_price'] = $this->calculatePrice($originalPrice, $template);

        // Calcular estoque final
        $originalStock = (int) ($sourceItem['available_quantity'] ?? 0);
        $result['calculated']['original_stock'] = $originalStock;
        $result['calculated']['final_stock'] = $this->calculateStock($originalStock, $template);

        // Calcular título final
        $originalTitle = $sourceItem['title'] ?? '';
        $result['calculated']['original_title'] = $originalTitle;
        $result['calculated']['final_title'] = $this->applyTitleRules($originalTitle, $template);

        // Verificar se deve pular este item
        $isCatalog = !empty($sourceItem['catalog_product_id']);
        $result['should_skip'] = false;
        $result['skip_reason'] = null;

        if ($isCatalog && ($template['skip_catalog_items'] ?? false)) {
            $result['should_skip'] = true;
            $result['skip_reason'] = 'Template configurado para ignorar itens de catálogo';
        }

        if (!$isCatalog && ($template['skip_non_catalog_items'] ?? false)) {
            $result['should_skip'] = true;
            $result['skip_reason'] = 'Template configurado para ignorar itens não-catálogo';
        }

        // Ações pós-clone
        $result['post_clone_actions'] = $this->parsePostCloneActions($template);

        return $result;
    }

    /**
     * Constrói estratégia de preço a partir do template
     */
    private function buildPricingStrategy(array $template): array
    {
        $strategy = ['type' => $template['pricing_type'] ?? 'copy'];

        if (in_array($strategy['type'], ['markup_percent', 'markdown_percent', 'fixed'])) {
            $strategy['value'] = (float) ($template['pricing_value'] ?? 0);
        }

        if (!empty($template['pricing_round_to'])) {
            $strategy['round_to'] = (float) $template['pricing_round_to'];
        }

        return $strategy;
    }

    /**
     * Constrói estratégia de estoque a partir do template
     */
    private function buildStockStrategy(array $template): array
    {
        $strategy = ['type' => $template['stock_type'] ?? 'copy'];

        if (in_array($strategy['type'], ['fixed', 'percentage'])) {
            $strategy['value'] = (int) ($template['stock_value'] ?? 0);
        }

        return $strategy;
    }

    /**
     * Constrói opções gerais a partir do template
     */
    private function buildOptions(array $template): array
    {
        return [
            'start_paused' => ($template['initial_status'] ?? 'paused') === 'paused',
            'clone_description' => (bool) ($template['clone_description'] ?? true),
            'clone_variations' => (bool) ($template['clone_variations'] ?? true),
            'title_prefix' => $template['title_prefix'] ?? null,
            'title_suffix' => $template['title_suffix'] ?? null,
            'template_id' => $template['id'] ?? null,
            'template_slug' => $template['slug'] ?? null,
        ];
    }

    /**
     * Calcula preço final baseado nas regras do template
     */
    public function calculatePrice(float $originalPrice, array $template): float
    {
        $type = $template['pricing_type'] ?? 'copy';
        $value = (float) ($template['pricing_value'] ?? 0);
        $roundTo = $template['pricing_round_to'] ?? null;

        $price = match ($type) {
            'copy' => $originalPrice,
            'markup_percent' => $originalPrice * (1 + ($value / 100)),
            'markdown_percent' => $originalPrice * (1 - ($value / 100)),
            'fixed' => $value,
            default => $originalPrice,
        };

        // Aplicar arredondamento
        if ($roundTo && $roundTo > 0) {
            $price = $this->roundPrice($price, $roundTo);
        }

        return round($price, 2);
    }

    /**
     * Arredonda preço para o valor mais próximo com final específico
     * Ex: roundTo=0.99 → 99.00 vira 98.99, 101.50 vira 100.99
     */
    private function roundPrice(float $price, float $roundTo): float
    {
        $intPart = floor($price);
        $decimal = $price - $intPart;
        $targetDecimal = $roundTo - floor($roundTo);

        if ($decimal > $targetDecimal) {
            return $intPart + $targetDecimal;
        }
        
        return ($intPart - 1) + $targetDecimal;
    }

    /**
     * Calcula estoque final baseado nas regras do template
     */
    public function calculateStock(int $originalStock, array $template): int
    {
        $type = $template['stock_type'] ?? 'copy';
        $value = (int) ($template['stock_value'] ?? 0);

        return match ($type) {
            'copy' => $originalStock,
            'fixed' => $value,
            'zero' => 0,
            'percentage' => max(0, (int) round($originalStock * ($value / 100))),
            default => $originalStock,
        };
    }

    /**
     * Aplica regras de título do template
     */
    public function applyTitleRules(string $title, array $template): string
    {
        $maxLength = 60;

        // Aplicar padrões de remoção
        if (!empty($template['title_remove_patterns'])) {
            $patterns = is_string($template['title_remove_patterns']) 
                ? json_decode($template['title_remove_patterns'], true) 
                : $template['title_remove_patterns'];

            if (is_array($patterns)) {
                foreach ($patterns as $pattern) {
                    $title = preg_replace($pattern, '', $title);
                }
            }
        }

        // Aplicar prefixo
        if (!empty($template['title_prefix'])) {
            $title = trim($template['title_prefix']) . ' ' . $title;
        }

        // Aplicar sufixo
        if (!empty($template['title_suffix'])) {
            $title = $title . ' ' . trim($template['title_suffix']);
        }

        // Limpar espaços duplicados
        $title = preg_replace('/\s+/', ' ', trim($title));

        // Truncar se necessário
        if (mb_strlen($title) > $maxLength) {
            $title = mb_substr($title, 0, $maxLength - 3) . '...';
        }

        return $title;
    }

    /**
     * Parseia ações pós-clone do template
     */
    private function parsePostCloneActions(array $template): array
    {
        $actions = $template['post_clone_actions'] ?? null;

        if (empty($actions)) {
            return [];
        }

        if (is_string($actions)) {
            $actions = json_decode($actions, true);
        }

        return is_array($actions) ? $actions : [];
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug ?: 'template-' . time();
    }
}
