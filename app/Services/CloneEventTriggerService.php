<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneEventTriggerService
 * 
 * Monitora eventos em sellers/categorias e dispara clonagens automáticas
 * Eventos suportados: new_items, price_drop, stock_available, competitor_out
 */
class CloneEventTriggerService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;

    // Tipos de eventos suportados
    public const EVENT_NEW_ITEMS = 'new_items';
    public const EVENT_PRICE_DROP = 'price_drop';
    public const EVENT_STOCK_AVAILABLE = 'stock_available';
    public const EVENT_COMPETITOR_OUT = 'competitor_out';

    // Thresholds padrão
    private const DEFAULT_PRICE_DROP_THRESHOLD = 10; // 10% de queda
    private const DEFAULT_CHECK_INTERVAL_MINUTES = 30;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Cria um trigger de monitoramento
     */
    public function createTrigger(array $data): array
    {
        $required = ['name', 'event_type', 'source_type', 'source_value'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório: {$field}");
            }
        }

        $validEvents = [self::EVENT_NEW_ITEMS, self::EVENT_PRICE_DROP, 
                        self::EVENT_STOCK_AVAILABLE, self::EVENT_COMPETITOR_OUT];
        if (!in_array($data['event_type'], $validEvents)) {
            throw new \InvalidArgumentException("Tipo de evento inválido");
        }

        $triggerId = $this->generateTriggerId();
        
        $stmt = $this->db->prepare("
            INSERT INTO clone_event_triggers (
                trigger_id, account_id, name, description, event_type,
                source_type, source_value, conditions, actions,
                is_active, check_interval_minutes, created_at
            ) VALUES (
                :trigger_id, :account_id, :name, :description, :event_type,
                :source_type, :source_value, :conditions, :actions,
                1, :check_interval, NOW()
            )
        ");

        $stmt->execute([
            'trigger_id' => $triggerId,
            'account_id' => $this->accountId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'event_type' => $data['event_type'],
            'source_type' => $data['source_type'],
            'source_value' => $data['source_value'],
            'conditions' => json_encode($data['conditions'] ?? []),
            'actions' => json_encode($data['actions'] ?? $this->getDefaultActions($data['event_type'])),
            'check_interval' => $data['check_interval_minutes'] ?? self::DEFAULT_CHECK_INTERVAL_MINUTES,
        ]);

        return $this->getTrigger($triggerId);
    }

    /**
     * Obtém um trigger específico
     */
    public function getTrigger(string $triggerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clone_event_triggers 
            WHERE trigger_id = :trigger_id AND account_id = :account_id
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'account_id' => $this->accountId]);
        
        $trigger = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($trigger) {
            $trigger['conditions'] = json_decode($trigger['conditions'] ?? '[]', true);
            $trigger['actions'] = json_decode($trigger['actions'] ?? '[]', true);
        }
        
        return $trigger ?: null;
    }

    /**
     * Lista triggers ativos
     */
    public function listTriggers(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM clone_event_triggers WHERE account_id = :account_id";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['account_id' => $this->accountId]);
        
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($triggers as &$trigger) {
            $trigger['conditions'] = json_decode($trigger['conditions'] ?? '[]', true);
            $trigger['actions'] = json_decode($trigger['actions'] ?? '[]', true);
        }
        
        return $triggers;
    }

    /**
     * Atualiza um trigger
     */
    public function updateTrigger(string $triggerId, array $data): array
    {
        $updates = [];
        $params = ['trigger_id' => $triggerId, 'account_id' => $this->accountId];

        $allowedFields = ['name', 'description', 'conditions', 'actions', 
                          'is_active', 'check_interval_minutes'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
                if (in_array($field, ['conditions', 'actions'])) {
                    $value = json_encode($value);
                } elseif ($field === 'is_active') {
                    $value = $value ? 1 : 0;
                }
                $updates[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($updates)) {
            return $this->getTrigger($triggerId);
        }

        $sql = "UPDATE clone_event_triggers SET " . implode(', ', $updates) . ", updated_at = NOW()
                WHERE trigger_id = :trigger_id AND account_id = :account_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->getTrigger($triggerId);
    }

    /**
     * Remove um trigger
     */
    public function deleteTrigger(string $triggerId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM clone_event_triggers 
            WHERE trigger_id = :trigger_id AND account_id = :account_id
        ");
        return $stmt->execute(['trigger_id' => $triggerId, 'account_id' => $this->accountId]);
    }

    /**
     * Obtém triggers pendentes de verificação
     */
    public function getDueTriggers(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clone_event_triggers
            WHERE is_active = 1
            AND (
                last_check_at IS NULL 
                OR last_check_at < DATE_SUB(NOW(), INTERVAL check_interval_minutes MINUTE)
            )
            ORDER BY last_check_at ASC
            LIMIT 20
        ");
        $stmt->execute();
        
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($triggers as &$trigger) {
            $trigger['conditions'] = json_decode($trigger['conditions'] ?? '[]', true);
            $trigger['actions'] = json_decode($trigger['actions'] ?? '[]', true);
        }
        
        return $triggers;
    }

    /**
     * Processa um trigger - verifica eventos e executa ações
     */
    public function processTrigger(array $trigger): array
    {
        $this->accountId = (int) $trigger['account_id'];
        
        // Atualizar last_check_at
        $this->updateLastCheck($trigger['trigger_id']);

        // Detectar eventos baseado no tipo
        $events = $this->detectEvents($trigger);

        if (empty($events)) {
            return [
                'trigger_id' => $trigger['trigger_id'],
                'events_detected' => 0,
                'actions_executed' => 0,
            ];
        }

        // Executar ações para cada evento
        $actionsExecuted = 0;
        foreach ($events as $event) {
            $result = $this->executeActions($trigger, $event);
            if ($result['success']) {
                $actionsExecuted++;
            }
            
            // Registrar evento
            $this->logEvent($trigger['trigger_id'], $event, $result);
        }

        return [
            'trigger_id' => $trigger['trigger_id'],
            'events_detected' => count($events),
            'actions_executed' => $actionsExecuted,
            'events' => $events,
        ];
    }

    /**
     * Detecta eventos baseado no tipo de trigger
     */
    private function detectEvents(array $trigger): array
    {
        $events = [];

        switch ($trigger['event_type']) {
            case self::EVENT_NEW_ITEMS:
                $events = $this->detectNewItems($trigger);
                break;

            case self::EVENT_PRICE_DROP:
                $events = $this->detectPriceDrops($trigger);
                break;

            case self::EVENT_STOCK_AVAILABLE:
                $events = $this->detectStockAvailable($trigger);
                break;

            case self::EVENT_COMPETITOR_OUT:
                $events = $this->detectCompetitorOut($trigger);
                break;
        }

        return $events;
    }

    /**
     * Detecta novos itens de um seller
     */
    private function detectNewItems(array $trigger): array
    {
        $events = [];
        $client = $this->getMlClient();
        
        if (!$client) {
            return $events;
        }

        $sellerId = $trigger['source_value'];
        $conditions = $trigger['conditions'];
        
        try {
            // Usar endpoint de itens do seller (funciona sem permissão especial)
            $params = [
                'status' => 'active',
                'limit' => 50,
                'offset' => 0,
            ];
            
            $response = $client->get("/users/{$sellerId}/items/search", $params);
            $itemIds = $response['results'] ?? [];
            
            // Buscar detalhes dos itens
            $items = [];
            if (!empty($itemIds)) {
                $chunks = array_chunk($itemIds, 20);
                foreach ($chunks as $chunk) {
                    $itemsResponse = $client->get('/items', ['ids' => implode(',', $chunk)]);
                    foreach ($itemsResponse as $itemData) {
                        $item = $itemData['body'] ?? $itemData;
                        if (!empty($item['id'])) {
                            // Filtrar por categoria se especificado
                            if (!empty($conditions['category_id']) && ($item['category_id'] ?? '') !== $conditions['category_id']) {
                                continue;
                            }
                            $items[] = $item;
                        }
                    }
                }
            }

            // Filtrar apenas itens novos (não vistos antes)
            $knownItems = $this->getKnownItems($trigger['trigger_id']);
            
            foreach ($items as $item) {
                if (!in_array($item['id'], $knownItems)) {
                    // Verificar condições adicionais
                    if ($this->matchesConditions($item, $conditions)) {
                        $events[] = [
                            'type' => self::EVENT_NEW_ITEMS,
                            'item_id' => $item['id'],
                            'title' => $item['title'],
                            'price' => $item['price'],
                            'seller_id' => $sellerId,
                            'detected_at' => date('Y-m-d H:i:s'),
                        ];
                        
                        // Marcar como conhecido
                        $this->markItemAsKnown($trigger['trigger_id'], $item['id']);
                    }
                }
            }
        } catch (\Exception $e) {
            log_error('Erro ao buscar itens do seller para clone trigger', [
                'seller_id' => $sellerId,
                'trigger_id' => $trigger['trigger_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $events;
    }

    /**
     * Detecta quedas de preço
     */
    private function detectPriceDrops(array $trigger): array
    {
        $events = [];
        $client = $this->getMlClient();
        
        if (!$client) {
            return $events;
        }

        $conditions = $trigger['conditions'];
        $threshold = $conditions['price_drop_threshold'] ?? self::DEFAULT_PRICE_DROP_THRESHOLD;

        // Buscar itens monitorados
        $monitoredItems = $this->getMonitoredItemsWithPrices($trigger['trigger_id']);

        foreach ($monitoredItems as $monitored) {
            try {
                $response = $client->get("/items/{$monitored['item_id']}");
                $currentPrice = $response['price'] ?? 0;
                $previousPrice = $monitored['last_price'];

                if ($previousPrice > 0 && $currentPrice > 0) {
                    $dropPercent = (($previousPrice - $currentPrice) / $previousPrice) * 100;

                    if ($dropPercent >= $threshold) {
                        $events[] = [
                            'type' => self::EVENT_PRICE_DROP,
                            'item_id' => $monitored['item_id'],
                            'title' => $response['title'] ?? '',
                            'previous_price' => $previousPrice,
                            'current_price' => $currentPrice,
                            'drop_percent' => round($dropPercent, 2),
                            'detected_at' => date('Y-m-d H:i:s'),
                        ];
                    }
                }

                // Atualizar preço monitorado
                $this->updateMonitoredPrice($trigger['trigger_id'], $monitored['item_id'], $currentPrice);

            } catch (\Exception $e) {
                continue;
            }
        }

        return $events;
    }

    /**
     * Detecta itens com estoque disponível
     */
    private function detectStockAvailable(array $trigger): array
    {
        $events = [];
        $client = $this->getMlClient();
        
        if (!$client) {
            return $events;
        }

        // Buscar itens que estavam sem estoque
        $outOfStockItems = $this->getOutOfStockItems($trigger['trigger_id']);

        foreach ($outOfStockItems as $item) {
            try {
                $response = $client->get("/items/{$item['item_id']}");
                $quantity = $response['available_quantity'] ?? 0;

                if ($quantity > 0) {
                    $events[] = [
                        'type' => self::EVENT_STOCK_AVAILABLE,
                        'item_id' => $item['item_id'],
                        'title' => $response['title'] ?? '',
                        'available_quantity' => $quantity,
                        'price' => $response['price'] ?? 0,
                        'detected_at' => date('Y-m-d H:i:s'),
                    ];

                    // Atualizar status
                    $this->updateItemStockStatus($trigger['trigger_id'], $item['item_id'], true);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $events;
    }

    /**
     * Detecta concorrentes sem estoque
     */
    private function detectCompetitorOut(array $trigger): array
    {
        $events = [];
        $client = $this->getMlClient();
        
        if (!$client) {
            return $events;
        }

        $conditions = $trigger['conditions'];
        $searchQuery = $trigger['source_value'];

        try {
            // Buscar produtos usando highlights de categoria
            $categoryId = $conditions['category_id'] ?? null;
            $items = [];
            
            // Se temos categoria, usar highlights
            if ($categoryId) {
                $response = $client->get("/highlights/MLB/category/{$categoryId}");
                $itemIds = $response['content'] ?? [];
                
                if (!empty($itemIds)) {
                    $chunks = array_chunk($itemIds, 20);
                    foreach ($chunks as $chunk) {
                        $itemsResponse = $client->get('/items', ['ids' => implode(',', $chunk)]);
                        foreach ($itemsResponse as $itemData) {
                            $item = $itemData['body'] ?? $itemData;
                            if (!empty($item['id'])) {
                                $items[] = $item;
                            }
                        }
                    }
                }
            } else {
                // Sem categoria, retornar vazio (não podemos usar /sites/MLB/search)
                log_warning('detectCompetitorOut requer category_id nas condições', [
                    'trigger_id' => $trigger['trigger_id'] ?? null,
                ]);
                return $events;
            }

            foreach ($items as $item) {
                // Verificar se está sem estoque
                if (($item['available_quantity'] ?? 0) === 0) {
                    // Verificar se era um concorrente ativo
                    $wasActive = $this->wasCompetitorActive($trigger['trigger_id'], $item['id']);
                    
                    if ($wasActive) {
                        $events[] = [
                            'type' => self::EVENT_COMPETITOR_OUT,
                            'item_id' => $item['id'],
                            'title' => $item['title'],
                            'seller_id' => $item['seller']['id'] ?? null,
                            'detected_at' => date('Y-m-d H:i:s'),
                        ];

                        $this->markCompetitorInactive($trigger['trigger_id'], $item['id']);
                    }
                } else {
                    // Marcar como ativo
                    $this->markCompetitorActive($trigger['trigger_id'], $item['id']);
                }
            }
        } catch (\Exception $e) {
            log_error('Erro ao detectar concorrentes no clone trigger', [
                'trigger_id' => $trigger['trigger_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $events;
    }

    /**
     * Executa ações para um evento detectado
     */
    private function executeActions(array $trigger, array $event): array
    {
        $actions = $trigger['actions'];
        $results = ['success' => true, 'actions' => []];

        foreach ($actions as $action) {
            $actionResult = $this->executeAction($action, $event, $trigger);
            $results['actions'][] = $actionResult;
            
            if (!$actionResult['success']) {
                $results['success'] = false;
            }
        }

        return $results;
    }

    /**
     * Executa uma ação específica
     */
    private function executeAction(array $action, array $event, array $trigger): array
    {
        $actionType = $action['type'] ?? 'clone';

        switch ($actionType) {
            case 'clone':
                return $this->executeCloneAction($event, $action);

            case 'notify':
                return $this->executeNotifyAction($event, $action, $trigger);

            case 'schedule':
                return $this->executeScheduleAction($event, $action);

            case 'log':
                return $this->executeLogAction($event, $action);

            default:
                return ['success' => false, 'error' => "Ação desconhecida: {$actionType}"];
        }
    }

    /**
     * Executa ação de clonagem
     */
    private function executeCloneAction(array $event, array $action): array
    {
        try {
            $cloneService = new CatalogCloneService($this->accountId);
            
            $config = [
                'seo_optimization' => $action['seo_optimization'] ?? true,
                'seo_level' => $action['seo_level'] ?? 'basic',
                'template_id' => $action['template_id'] ?? null,
                'auto_publish' => $action['auto_publish'] ?? false,
            ];

            $result = $cloneService->cloneItem($event['item_id'], $config);

            return [
                'success' => $result['success'] ?? false,
                'action' => 'clone',
                'item_id' => $event['item_id'],
                'cloned_id' => $result['cloned_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'action' => 'clone',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Executa ação de notificação
     */
    private function executeNotifyAction(array $event, array $action, array $trigger): array
    {
        try {
            $notificationService = new CloneSlackDiscordNotificationService($this->accountId);
            
            $messageData = $this->buildNotificationMessage($event, $trigger);
            $title = (string)($messageData['title'] ?? 'Evento Detectado');
            $message = sprintf(
                "%s\nItem: %s\nID: %s\nDetectado em: %s",
                (string)($messageData['trigger_name'] ?? 'Trigger de evento'),
                (string)($messageData['item_title'] ?? 'N/A'),
                (string)($messageData['item_id'] ?? 'N/A'),
                (string)($messageData['detected_at'] ?? date('Y-m-d H:i:s'))
            );
            $fields = [];

            if (isset($messageData['previous_price'])) {
                $fields[] = ['name' => 'Preço Anterior', 'value' => (string)$messageData['previous_price'], 'inline' => true];
            }
            if (isset($messageData['current_price'])) {
                $fields[] = ['name' => 'Preço Atual', 'value' => (string)$messageData['current_price'], 'inline' => true];
            }
            if (isset($messageData['drop_percent'])) {
                $fields[] = ['name' => 'Queda', 'value' => (string)$messageData['drop_percent'], 'inline' => true];
            }
            if (isset($messageData['available_quantity'])) {
                $fields[] = ['name' => 'Quantidade', 'value' => (string)$messageData['available_quantity'], 'inline' => true];
            }
            if (isset($messageData['price'])) {
                $fields[] = ['name' => 'Preço', 'value' => (string)$messageData['price'], 'inline' => true];
            }
            
            $channels = $action['channels'] ?? ['slack'];
            
            foreach ($channels as $channel) {
                if ($channel === 'slack') {
                    $notificationService->sendToSlack(
                        CloneSlackDiscordNotificationService::ALERT_MILESTONE,
                        $title,
                        $message,
                        $fields,
                        CloneSlackDiscordNotificationService::SEVERITY_INFO
                    );
                } elseif ($channel === 'discord') {
                    $notificationService->sendToDiscord(
                        CloneSlackDiscordNotificationService::ALERT_MILESTONE,
                        $title,
                        $message,
                        $fields,
                        CloneSlackDiscordNotificationService::SEVERITY_INFO
                    );
                }
            }

            return ['success' => true, 'action' => 'notify', 'channels' => $channels];
        } catch (\Exception $e) {
            return ['success' => false, 'action' => 'notify', 'error' => $e->getMessage()];
        }
    }

    /**
     * Executa ação de agendamento
     */
    private function executeScheduleAction(array $event, array $action): array
    {
        try {
            $schedulerService = new CloneAutoSchedulerService($this->accountId);
            
            $scheduleData = [
                'name' => "Auto: {$event['type']} - {$event['item_id']}",
                'source_type' => 'item_list',
                'source_value' => $event['item_id'],
                'frequency' => 'once',
                'run_at_hour' => (int) date('H'),
                'run_at_minute' => (int) date('i') + 5,
                'seo_level' => $action['seo_level'] ?? 'basic',
            ];

            $result = $schedulerService->createSchedule($scheduleData);

            return [
                'success' => true,
                'action' => 'schedule',
                'schedule_id' => $result['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'action' => 'schedule', 'error' => $e->getMessage()];
        }
    }

    /**
     * Executa ação de log
     */
    private function executeLogAction(array $event, array $action): array
    {
        $logLevel = $action['level'] ?? 'info';
        $logMessage = json_encode($event);
        
        log_info('Clone event trigger action executed', [
            'level' => $logLevel,
            'event' => $event,
        ]);
        
        return ['success' => true, 'action' => 'log'];
    }

    /**
     * Constrói mensagem de notificação
     */
    private function buildNotificationMessage(array $event, array $trigger): array
    {
        $typeLabels = [
            self::EVENT_NEW_ITEMS => '🆕 Novo Item Detectado',
            self::EVENT_PRICE_DROP => '📉 Queda de Preço',
            self::EVENT_STOCK_AVAILABLE => '📦 Estoque Disponível',
            self::EVENT_COMPETITOR_OUT => '🎯 Concorrente Sem Estoque',
        ];

        $message = [
            'title' => $typeLabels[$event['type']] ?? 'Evento Detectado',
            'trigger_name' => $trigger['name'],
            'item_id' => $event['item_id'],
            'item_title' => $event['title'] ?? 'N/A',
            'detected_at' => $event['detected_at'],
        ];

        // Adicionar dados específicos do evento
        if ($event['type'] === self::EVENT_PRICE_DROP) {
            $message['previous_price'] = $event['previous_price'];
            $message['current_price'] = $event['current_price'];
            $message['drop_percent'] = $event['drop_percent'] . '%';
        } elseif ($event['type'] === self::EVENT_STOCK_AVAILABLE) {
            $message['available_quantity'] = $event['available_quantity'];
            $message['price'] = $event['price'];
        }

        return $message;
    }

    /**
     * Verifica se item atende às condições
     */
    private function matchesConditions(array $item, array $conditions): bool
    {
        // Preço mínimo
        if (!empty($conditions['min_price']) && $item['price'] < $conditions['min_price']) {
            return false;
        }

        // Preço máximo
        if (!empty($conditions['max_price']) && $item['price'] > $conditions['max_price']) {
            return false;
        }

        // Quantidade mínima
        if (!empty($conditions['min_quantity'])) {
            $qty = $item['available_quantity'] ?? 0;
            if ($qty < $conditions['min_quantity']) {
                return false;
            }
        }

        // Condição do item
        if (!empty($conditions['condition'])) {
            if ($item['condition'] !== $conditions['condition']) {
                return false;
            }
        }

        // Frete grátis
        if (!empty($conditions['free_shipping'])) {
            $hasFreeShipping = $item['shipping']['free_shipping'] ?? false;
            if (!$hasFreeShipping) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtém itens já conhecidos para um trigger
     */
    private function getKnownItems(string $triggerId): array
    {
        $stmt = $this->db->prepare("
            SELECT item_id FROM clone_event_trigger_items
            WHERE trigger_id = :trigger_id
        ");
        $stmt->execute(['trigger_id' => $triggerId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Marca item como conhecido
     */
    private function markItemAsKnown(string $triggerId, string $itemId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO clone_event_trigger_items (trigger_id, item_id, first_seen_at)
            VALUES (:trigger_id, :item_id, NOW())
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'item_id' => $itemId]);
    }

    /**
     * Obtém itens monitorados com preços
     */
    private function getMonitoredItemsWithPrices(string $triggerId): array
    {
        $stmt = $this->db->prepare("
            SELECT item_id, last_price FROM clone_event_trigger_items
            WHERE trigger_id = :trigger_id AND last_price IS NOT NULL
        ");
        $stmt->execute(['trigger_id' => $triggerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza preço monitorado
     */
    private function updateMonitoredPrice(string $triggerId, string $itemId, float $price): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_event_trigger_items 
            SET last_price = :price, last_check_at = NOW()
            WHERE trigger_id = :trigger_id AND item_id = :item_id
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'item_id' => $itemId, 'price' => $price]);
    }

    /**
     * Obtém itens sem estoque
     */
    private function getOutOfStockItems(string $triggerId): array
    {
        $stmt = $this->db->prepare("
            SELECT item_id FROM clone_event_trigger_items
            WHERE trigger_id = :trigger_id AND has_stock = 0
        ");
        $stmt->execute(['trigger_id' => $triggerId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza status de estoque
     */
    private function updateItemStockStatus(string $triggerId, string $itemId, bool $hasStock): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_event_trigger_items 
            SET has_stock = :has_stock, last_check_at = NOW()
            WHERE trigger_id = :trigger_id AND item_id = :item_id
        ");
        $stmt->execute([
            'trigger_id' => $triggerId,
            'item_id' => $itemId,
            'has_stock' => $hasStock ? 1 : 0,
        ]);
    }

    /**
     * Verifica se concorrente estava ativo
     */
    private function wasCompetitorActive(string $triggerId, string $itemId): bool
    {
        $stmt = $this->db->prepare("
            SELECT is_active FROM clone_event_trigger_competitors
            WHERE trigger_id = :trigger_id AND item_id = :item_id
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'item_id' => $itemId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result && $result['is_active'];
    }

    /**
     * Marca concorrente como inativo
     */
    private function markCompetitorInactive(string $triggerId, string $itemId): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_event_trigger_competitors 
            SET is_active = 0, inactive_since = NOW()
            WHERE trigger_id = :trigger_id AND item_id = :item_id
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'item_id' => $itemId]);
    }

    /**
     * Marca concorrente como ativo
     */
    private function markCompetitorActive(string $triggerId, string $itemId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_event_trigger_competitors (trigger_id, item_id, is_active, first_seen_at)
            VALUES (:trigger_id, :item_id, 1, NOW())
            ON DUPLICATE KEY UPDATE is_active = 1, inactive_since = NULL
        ");
        $stmt->execute(['trigger_id' => $triggerId, 'item_id' => $itemId]);
    }

    /**
     * Atualiza timestamp de última verificação
     */
    private function updateLastCheck(string $triggerId): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_event_triggers SET last_check_at = NOW() WHERE trigger_id = :trigger_id
        ");
        $stmt->execute(['trigger_id' => $triggerId]);
    }

    /**
     * Registra evento detectado
     */
    private function logEvent(string $triggerId, array $event, array $result): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_event_trigger_logs (
                trigger_id, event_type, item_id, event_data, action_result, created_at
            ) VALUES (
                :trigger_id, :event_type, :item_id, :event_data, :action_result, NOW()
            )
        ");
        $stmt->execute([
            'trigger_id' => $triggerId,
            'event_type' => $event['type'],
            'item_id' => $event['item_id'],
            'event_data' => json_encode($event),
            'action_result' => json_encode($result),
        ]);
    }

    /**
     * Obtém histórico de eventos
     */
    public function getEventHistory(string $triggerId, int $limit = 50): array
    {
        $limitSql = max(1, min(500, (int)$limit));
        $stmt = $this->db->prepare("
            SELECT * FROM clone_event_trigger_logs
            WHERE trigger_id = :trigger_id
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':trigger_id', $triggerId, PDO::PARAM_STR);
        $stmt->execute();
        
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($logs as &$log) {
            $log['event_data'] = json_decode($log['event_data'] ?? '[]', true);
            $log['action_result'] = json_decode($log['action_result'] ?? '[]', true);
        }
        
        return $logs;
    }

    /**
     * Obtém estatísticas de triggers
     */
    public function getTriggerStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_triggers,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_triggers,
                SUM(total_events_detected) as total_events,
                SUM(total_actions_executed) as total_actions
            FROM clone_event_triggers
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ações padrão por tipo de evento
     */
    private function getDefaultActions(string $eventType): array
    {
        $defaults = [
            self::EVENT_NEW_ITEMS => [
                ['type' => 'notify', 'channels' => ['slack']],
                ['type' => 'clone', 'seo_optimization' => true, 'seo_level' => 'basic'],
            ],
            self::EVENT_PRICE_DROP => [
                ['type' => 'notify', 'channels' => ['slack', 'discord']],
                ['type' => 'log'],
            ],
            self::EVENT_STOCK_AVAILABLE => [
                ['type' => 'notify', 'channels' => ['slack']],
                ['type' => 'clone', 'seo_optimization' => true],
            ],
            self::EVENT_COMPETITOR_OUT => [
                ['type' => 'notify', 'channels' => ['slack', 'discord']],
            ],
        ];

        return $defaults[$eventType] ?? [['type' => 'log']];
    }

    /**
     * Gera ID único para trigger
     */
    private function generateTriggerId(): string
    {
        return 'TRG' . strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Obtém cliente ML
     */
    private function getMlClient(): ?MercadoLivreClient
    {
        if ($this->mlClient === null) {
            try {
                $this->mlClient = new MercadoLivreClient($this->accountId);
            } catch (\Exception $e) {
                return null;
            }
        }
        return $this->mlClient;
    }
}
