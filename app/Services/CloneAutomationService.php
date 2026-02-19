<?php

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Clone Automation Service
 * 
 * Sistema de auto-clonagem programada por regras:
 * - Regras baseadas em triggers (novo item, preço, categoria)
 * - Agendamento por horário/frequência
 * - Filtros avançados (marca, categoria, preço)
 * - Templates de clonagem
 * - Log de execução
 */
class CloneAutomationService
{
    private PDO $db;
    private int $accountId;
    private ?CatalogCloneService $cloneService = null;

    // Status de regras
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_DISABLED = 'disabled';

    // Tipos de trigger
    public const TRIGGER_SCHEDULE = 'schedule';       // Agendado (diário, semanal, etc)
    public const TRIGGER_NEW_ITEM = 'new_item';       // Quando novo item é detectado
    public const TRIGGER_PRICE_DROP = 'price_drop';   // Quando preço cai X%
    public const TRIGGER_STOCK_LOW = 'stock_low';     // Quando estoque baixo
    public const TRIGGER_MANUAL = 'manual';           // Executado manualmente

    // Frequências
    public const FREQ_HOURLY = 'hourly';
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Cria uma nova regra de automação
     */
    public function createRule(array $data): int
    {
        $name = $data['name'] ?? 'Regra ' . date('Y-m-d H:i');
        $triggerType = $data['trigger_type'] ?? self::TRIGGER_SCHEDULE;
        $frequency = $data['frequency'] ?? self::FREQ_DAILY;
        $sourceType = $data['source_type'] ?? 'seller';
        $sourceId = $data['source_id'] ?? null;
        $targetAccountId = $data['target_account_id'] ?? $this->accountId;
        $templateId = $data['template_id'] ?? null;
        
        // Filtros
        $filters = [
            'categories' => $data['categories'] ?? [],
            'brands' => $data['brands'] ?? [],
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'exclude_keywords' => $data['exclude_keywords'] ?? [],
            'include_keywords' => $data['include_keywords'] ?? [],
            'only_catalog' => $data['only_catalog'] ?? false,
            'only_available' => $data['only_available'] ?? true,
        ];
        
        // Configurações de trigger
        $triggerConfig = [
            'frequency' => $frequency,
            'run_at' => $data['run_at'] ?? '03:00',
            'days_of_week' => $data['days_of_week'] ?? [1, 2, 3, 4, 5],
            'price_drop_percent' => $data['price_drop_percent'] ?? 10,
            'stock_threshold' => $data['stock_threshold'] ?? 5,
        ];
        
        // Limites
        $limits = [
            'max_items_per_run' => $data['max_items_per_run'] ?? 50,
            'max_items_per_day' => $data['max_items_per_day'] ?? 200,
            'cooldown_hours' => $data['cooldown_hours'] ?? 24,
        ];
        
        $stmt = $this->db->prepare("
            INSERT INTO clone_automation_rules 
            (account_id, name, trigger_type, source_type, source_id, target_account_id,
             template_id, filters, trigger_config, limits, status, created_at, updated_at)
            VALUES 
            (:account_id, :name, :trigger_type, :source_type, :source_id, :target_account_id,
             :template_id, :filters, :trigger_config, :limits, :status, NOW(), NOW())
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':name' => $name,
            ':trigger_type' => $triggerType,
            ':source_type' => $sourceType,
            ':source_id' => $sourceId,
            ':target_account_id' => $targetAccountId,
            ':template_id' => $templateId,
            ':filters' => json_encode($filters),
            ':trigger_config' => json_encode($triggerConfig),
            ':limits' => json_encode($limits),
            ':status' => self::STATUS_ACTIVE,
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza uma regra
     */
    public function updateRule(int $ruleId, array $data): bool
    {
        $updates = [];
        $params = [':id' => $ruleId, ':account_id' => $this->accountId];
        
        $allowedFields = ['name', 'trigger_type', 'source_type', 'source_id', 
                          'target_account_id', 'template_id', 'status'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }
        
        // Campos JSON
        if (isset($data['filters'])) {
            $updates[] = "filters = :filters";
            $params[':filters'] = json_encode($data['filters']);
        }
        if (isset($data['trigger_config'])) {
            $updates[] = "trigger_config = :trigger_config";
            $params[':trigger_config'] = json_encode($data['trigger_config']);
        }
        if (isset($data['limits'])) {
            $updates[] = "limits = :limits";
            $params[':limits'] = json_encode($data['limits']);
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $updates[] = "updated_at = NOW()";
        
        $sql = "UPDATE clone_automation_rules SET " . implode(', ', $updates) . 
               " WHERE id = :id AND account_id = :account_id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Obtém uma regra
     */
    public function getRule(int $ruleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, 
                   (SELECT COUNT(*) FROM clone_automation_logs WHERE rule_id = r.id) as total_runs,
                   (SELECT SUM(items_cloned) FROM clone_automation_logs WHERE rule_id = r.id) as total_cloned,
                   (SELECT MAX(executed_at) FROM clone_automation_logs WHERE rule_id = r.id) as last_run
            FROM clone_automation_rules r
            WHERE r.id = :id AND r.account_id = :account_id
        ");
        $stmt->execute([
            ':id' => $ruleId,
            ':account_id' => $this->accountId,
        ]);
        
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($rule) {
            $rule['filters'] = json_decode($rule['filters'], true) ?? [];
            $rule['trigger_config'] = json_decode($rule['trigger_config'], true) ?? [];
            $rule['limits'] = json_decode($rule['limits'], true) ?? [];
        }
        
        return $rule ?: null;
    }

    /**
     * Lista regras
     */
    public function listRules(array $filters = []): array
    {
        $sql = "
            SELECT r.*, 
                   (SELECT COUNT(*) FROM clone_automation_logs WHERE rule_id = r.id) as total_runs,
                   (SELECT SUM(items_cloned) FROM clone_automation_logs WHERE rule_id = r.id) as total_cloned,
                   (SELECT MAX(executed_at) FROM clone_automation_logs WHERE rule_id = r.id) as last_run
            FROM clone_automation_rules r
            WHERE r.account_id = :account_id
        ";
        $params = [':account_id' => $this->accountId];
        
        if (!empty($filters['status'])) {
            $sql .= " AND r.status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['trigger_type'])) {
            $sql .= " AND r.trigger_type = :trigger_type";
            $params[':trigger_type'] = $filters['trigger_type'];
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rules as &$rule) {
            $rule['filters'] = json_decode($rule['filters'], true) ?? [];
            $rule['trigger_config'] = json_decode($rule['trigger_config'], true) ?? [];
            $rule['limits'] = json_decode($rule['limits'], true) ?? [];
        }
        
        return $rules;
    }

    /**
     * Exclui uma regra
     */
    public function deleteRule(int $ruleId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM clone_automation_rules 
            WHERE id = :id AND account_id = :account_id
        ");
        
        return $stmt->execute([
            ':id' => $ruleId,
            ':account_id' => $this->accountId,
        ]);
    }

    /**
     * Ativa uma regra
     */
    public function enableRule(int $ruleId): bool
    {
        return $this->updateRule($ruleId, ['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Pausa uma regra
     */
    public function pauseRule(int $ruleId): bool
    {
        return $this->updateRule($ruleId, ['status' => self::STATUS_PAUSED]);
    }

    /**
     * Executa uma regra manualmente
     */
    public function executeRule(int $ruleId, bool $dryRun = false): array
    {
        $rule = $this->getRule($ruleId);
        
        if (!$rule) {
            throw new Exception('Regra não encontrada');
        }
        
        if ($rule['status'] !== self::STATUS_ACTIVE && !$dryRun) {
            throw new Exception('Regra não está ativa');
        }
        
        // Verificar cooldown
        if (!$dryRun && $rule['last_run']) {
            $cooldownHours = $rule['limits']['cooldown_hours'] ?? 24;
            $lastRunTime = strtotime($rule['last_run']);
            $cooldownEnd = $lastRunTime + ($cooldownHours * 3600);
            
            if (time() < $cooldownEnd) {
                $remaining = ceil(($cooldownEnd - time()) / 3600);
                throw new Exception("Cooldown ativo. Aguarde {$remaining}h.");
            }
        }
        
        // Verificar limite diário
        if (!$dryRun) {
            $dailyCloned = $this->getDailyClonedCount($ruleId);
            $maxDaily = $rule['limits']['max_items_per_day'] ?? 200;
            
            if ($dailyCloned >= $maxDaily) {
                throw new Exception("Limite diário atingido ({$dailyCloned}/{$maxDaily})");
            }
        }
        
        // Buscar itens para clonar
        $items = $this->findItemsToClone($rule);
        
        // Aplicar limite por execução
        $maxPerRun = $rule['limits']['max_items_per_run'] ?? 50;
        $items = array_slice($items, 0, $maxPerRun);
        
        if ($dryRun) {
            return [
                'status' => 'dry_run',
                'rule_id' => $ruleId,
                'items_found' => count($items),
                'items' => array_map(function($item) {
                    return [
                        'id' => $item['id'],
                        'title' => $item['title'] ?? '',
                        'price' => $item['price'] ?? 0,
                    ];
                }, $items),
            ];
        }
        
        // Executar clonagem
        $results = $this->cloneItems($rule, $items);
        
        // Log da execução
        $this->logExecution($ruleId, $results);
        
        return $results;
    }

    /**
     * Encontra itens para clonar baseado nos filtros da regra
     */
    private function findItemsToClone(array $rule): array
    {
        $cloneService = $this->getCloneService();
        $filters = $rule['filters'];
        
        $searchFilters = [];
        
        if (!empty($filters['categories'])) {
            $searchFilters['category'] = $filters['categories'][0];
        }
        
        if (!empty($filters['min_price'])) {
            $searchFilters['min_price'] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $searchFilters['max_price'] = $filters['max_price'];
        }
        
        $searchFilters['limit'] = 100;
        
        // Buscar itens da fonte
        try {
            if ($rule['source_type'] === 'seller' && $rule['source_id']) {
                $result = $cloneService->listSellerItems($rule['source_id'], $searchFilters);
                $items = $result['items'] ?? [];
            } else {
                $items = [];
            }
        } catch (Exception $e) {
            return [];
        }
        
        // Aplicar filtros adicionais
        $filteredItems = [];
        
        foreach ($items as $item) {
            // Filtro de marca
            if (!empty($filters['brands'])) {
                $itemBrand = $this->extractBrand($item);
                if (!in_array($itemBrand, $filters['brands'])) {
                    continue;
                }
            }
            
            // Filtro de keywords excludentes
            if (!empty($filters['exclude_keywords'])) {
                $title = strtolower($item['title'] ?? '');
                $excluded = false;
                foreach ($filters['exclude_keywords'] as $kw) {
                    if (strpos($title, strtolower($kw)) !== false) {
                        $excluded = true;
                        break;
                    }
                }
                if ($excluded) continue;
            }
            
            // Filtro de keywords obrigatórias
            if (!empty($filters['include_keywords'])) {
                $title = strtolower($item['title'] ?? '');
                $included = false;
                foreach ($filters['include_keywords'] as $kw) {
                    if (strpos($title, strtolower($kw)) !== false) {
                        $included = true;
                        break;
                    }
                }
                if (!$included) continue;
            }
            
            // Filtro de catálogo
            if ($filters['only_catalog'] && empty($item['catalog_product_id'])) {
                continue;
            }
            
            // Filtro de disponibilidade
            if ($filters['only_available']) {
                $status = $item['status'] ?? '';
                if ($status !== 'active') {
                    continue;
                }
            }
            
            // Verificar se já foi clonado
            if ($this->wasAlreadyCloned($item['id'], $rule['id'])) {
                continue;
            }
            
            $filteredItems[] = $item;
        }
        
        return $filteredItems;
    }

    /**
     * Extrai marca de um item
     */
    private function extractBrand(array $item): string
    {
        $attributes = $item['attributes'] ?? [];
        foreach ($attributes as $attr) {
            if ($attr['id'] === 'BRAND') {
                return $attr['value_name'] ?? '';
            }
        }
        return '';
    }

    /**
     * Verifica se item já foi clonado por esta regra
     */
    private function wasAlreadyCloned(string $itemId, int $ruleId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM clone_automation_cloned_items 
            WHERE rule_id = :rule_id AND source_item_id = :item_id
        ");
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':item_id' => $itemId,
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Clona os itens
     */
    private function cloneItems(array $rule, array $items): array
    {
        $cloneService = $this->getCloneService();
        $targetAccountId = $rule['target_account_id'];
        $templateId = $rule['template_id'];
        
        $results = [
            'status' => 'completed',
            'rule_id' => $rule['id'],
            'items_found' => count($items),
            'items_cloned' => 0,
            'items_failed' => 0,
            'errors' => [],
            'cloned' => [],
        ];
        
        foreach ($items as $item) {
            try {
                $cloneResult = $cloneService->cloneItem(
                    $item['id'],
                    $targetAccountId,
                    $templateId ? ['template_id' => $templateId] : []
                );
                
                if (!empty($cloneResult['new_item_id'])) {
                    $results['items_cloned']++;
                    $results['cloned'][] = [
                        'source_id' => $item['id'],
                        'new_id' => $cloneResult['new_item_id'],
                    ];
                    
                    // Registrar item clonado
                    $this->recordClonedItem($rule['id'], $item['id'], $cloneResult['new_item_id']);
                } else {
                    $results['items_failed']++;
                    $results['errors'][] = [
                        'item_id' => $item['id'],
                        'error' => $cloneResult['error'] ?? 'Unknown error',
                    ];
                }
                
            } catch (Exception $e) {
                $results['items_failed']++;
                $results['errors'][] = [
                    'item_id' => $item['id'],
                    'error' => $e->getMessage(),
                ];
            }
            
            // Rate limit
            usleep(500000); // 0.5s entre cada clone
        }
        
        return $results;
    }

    /**
     * Registra item clonado
     */
    private function recordClonedItem(int $ruleId, string $sourceItemId, string $newItemId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_automation_cloned_items 
            (rule_id, source_item_id, cloned_item_id, cloned_at)
            VALUES 
            (:rule_id, :source_id, :cloned_id, NOW())
        ");
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':source_id' => $sourceItemId,
            ':cloned_id' => $newItemId,
        ]);
    }

    /**
     * Registra log de execução
     */
    private function logExecution(int $ruleId, array $results): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_automation_logs 
            (rule_id, items_found, items_cloned, items_failed, 
             errors, executed_at)
            VALUES 
            (:rule_id, :found, :cloned, :failed, :errors, NOW())
        ");
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':found' => $results['items_found'] ?? 0,
            ':cloned' => $results['items_cloned'] ?? 0,
            ':failed' => $results['items_failed'] ?? 0,
            ':errors' => json_encode($results['errors'] ?? []),
        ]);
    }

    /**
     * Obtém contagem de clones do dia
     */
    private function getDailyClonedCount(int $ruleId): int
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(items_cloned), 0) 
            FROM clone_automation_logs 
            WHERE rule_id = :rule_id 
            AND DATE(executed_at) = CURDATE()
        ");
        $stmt->execute([':rule_id' => $ruleId]);
        
        return (int) $stmt->fetchColumn();
    }

    /**
     * Obtém regras que devem ser executadas
     */
    public function getRulesDueForExecution(): array
    {
        $now = date('Y-m-d H:i:s');
        $currentHour = date('H:i');
        $dayOfWeek = date('N'); // 1 = Monday, 7 = Sunday
        
        $stmt = $this->db->prepare("
            SELECT r.* FROM clone_automation_rules r
            WHERE r.status = 'active'
            AND r.trigger_type = 'schedule'
            AND (
                r.last_executed_at IS NULL 
                OR (
                    TIMESTAMPDIFF(HOUR, r.last_executed_at, NOW()) >= 
                    JSON_UNQUOTE(JSON_EXTRACT(r.limits, '$.cooldown_hours'))
                )
            )
        ");
        $stmt->execute();
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $duRules = [];
        
        foreach ($rules as $rule) {
            $config = json_decode($rule['trigger_config'], true) ?? [];
            $runAt = $config['run_at'] ?? '03:00';
            $daysOfWeek = $config['days_of_week'] ?? [1, 2, 3, 4, 5];
            $frequency = $config['frequency'] ?? self::FREQ_DAILY;
            
            // Verificar dia da semana
            if (!in_array((int)$dayOfWeek, $daysOfWeek)) {
                continue;
            }
            
            // Verificar hora (com margem de 30 minutos)
            $runAtTime = strtotime($runAt);
            $currentTime = strtotime($currentHour);
            $diff = abs($currentTime - $runAtTime);
            
            if ($diff <= 1800) { // 30 minutos de margem
                $rule['filters'] = json_decode($rule['filters'], true) ?? [];
                $rule['trigger_config'] = json_decode($rule['trigger_config'], true) ?? [];
                $rule['limits'] = json_decode($rule['limits'], true) ?? [];
                $duRules[] = $rule;
            }
        }
        
        return $duRules;
    }

    /**
     * Marca regra como executada
     */
    public function markAsExecuted(int $ruleId): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_automation_rules 
            SET last_executed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([':id' => $ruleId]);
    }

    /**
     * Obtém histórico de execuções
     */
    public function getExecutionHistory(int $ruleId, int $limit = 50): array
    {
        $limitSql = max(1, min(200, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM clone_automation_logs 
            WHERE rule_id = :rule_id 
            ORDER BY executed_at DESC 
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':rule_id', $ruleId, PDO::PARAM_INT);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logs as &$log) {
            $log['errors'] = json_decode($log['errors'], true) ?? [];
        }
        
        return $logs;
    }

    /**
     * Obtém estatísticas gerais
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_rules,
                SUM(status = 'active') as active_rules,
                (SELECT COUNT(*) FROM clone_automation_logs l 
                 JOIN clone_automation_rules r ON r.id = l.rule_id 
                 WHERE r.account_id = :account_id) as total_executions,
                (SELECT COALESCE(SUM(items_cloned), 0) FROM clone_automation_logs l 
                 JOIN clone_automation_rules r ON r.id = l.rule_id 
                 WHERE r.account_id = :account_id2) as total_items_cloned
            FROM clone_automation_rules
            WHERE account_id = :account_id3
        ");
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':account_id2' => $this->accountId,
            ':account_id3' => $this->accountId,
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtém clone service
     */
    private function getCloneService(): CatalogCloneService
    {
        if (!$this->cloneService) {
            $this->cloneService = new CatalogCloneService($this->accountId);
        }
        return $this->cloneService;
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
            CREATE TABLE IF NOT EXISTS clone_automation_rules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NOT NULL,
                name VARCHAR(200) NOT NULL,
                trigger_type ENUM('schedule', 'new_item', 'price_drop', 'stock_low', 'manual') DEFAULT 'schedule',
                source_type ENUM('seller', 'category', 'search') DEFAULT 'seller',
                source_id VARCHAR(100) NULL,
                target_account_id INT NOT NULL,
                template_id INT NULL,
                filters JSON,
                trigger_config JSON,
                limits JSON,
                status ENUM('active', 'paused', 'disabled') DEFAULT 'active',
                last_executed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_account (account_id),
                INDEX idx_status (status),
                INDEX idx_trigger (trigger_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_automation_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rule_id INT NOT NULL,
                items_found INT DEFAULT 0,
                items_cloned INT DEFAULT 0,
                items_failed INT DEFAULT 0,
                errors JSON,
                executed_at DATETIME NOT NULL,
                INDEX idx_rule (rule_id),
                INDEX idx_date (executed_at),
                FOREIGN KEY (rule_id) REFERENCES clone_automation_rules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_automation_cloned_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                rule_id INT NOT NULL,
                source_item_id VARCHAR(50) NOT NULL,
                cloned_item_id VARCHAR(50) NOT NULL,
                cloned_at DATETIME NOT NULL,
                INDEX idx_rule (rule_id),
                INDEX idx_source (source_item_id),
                UNIQUE KEY uk_rule_source (rule_id, source_item_id),
                FOREIGN KEY (rule_id) REFERENCES clone_automation_rules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $checked = true;
    }
}
