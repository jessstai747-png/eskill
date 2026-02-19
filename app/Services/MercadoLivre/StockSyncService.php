<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Database;
use App\Services\CacheService;
use App\Services\MercadoLivreClient;

/**
 * StockSyncService — Sincronização automática de estoque entre contas do Mercado Livre
 *
 * Suporta:
 * - Sincronização completa e incremental
 * - Fila com prioridade
 * - Retry com exponential backoff (3 tentativas)
 * - Rate limiting configurável e compartilhado (via CacheService)
 * - Webhook de recebimento do ML
 * - Histórico completo de alterações
 */
class StockSyncService
{
    private const MAX_ATTEMPTS = 3;
    private const BASE_DELAY_SECONDS = 5;
    private const DEFAULT_RATE_LIMIT = 30;
    private const RATE_LIMIT_CACHE_PREFIX = 'stock_sync_rate:';
    private const CLIENT_CACHE_TTL = 1800; // 30 minutos

    private \PDO $db;
    private CacheService $cache;

    /** @var array<int, MercadoLivreClient> */
    private array $clientCache = [];

    /** @var array<int, int> Timestamp de criação de cada client cacheado */
    private array $clientCacheTime = [];

    private int $rateLimitPerMinute;

    public function __construct(?int $rateLimitPerMinute = null)
    {
        $this->db = Database::getInstance();
        $this->cache = new CacheService();
        $this->rateLimitPerMinute = $rateLimitPerMinute ?? self::DEFAULT_RATE_LIMIT;
    }

    // =========================================================================
    // CRUD — Regras de sincronização
    // =========================================================================

    /**
     * Cria uma regra de sincronização de estoque
     *
     * @param array{
     *   user_id: int,
     *   source_account_id: int,
     *   target_account_id: int,
     *   source_item_id: string,
     *   target_item_id: string,
     *   sync_mode?: string,
     *   offset_value?: int,
     *   percentage_value?: float,
     *   min_stock?: int,
     *   max_stock?: int|null,
     *   priority?: int
     * } $data
     */
    public function createRule(array $data): array
    {
        $this->validateRuleData($data);

        try {
            $stmt = $this->db->prepare("
                INSERT INTO stock_sync_rules
                    (user_id, source_account_id, target_account_id, source_item_id,
                     target_item_id, sync_mode, offset_value, percentage_value,
                     min_stock, max_stock, priority)
                VALUES
                    (:user_id, :source_account_id, :target_account_id, :source_item_id,
                     :target_item_id, :sync_mode, :offset_value, :percentage_value,
                     :min_stock, :max_stock, :priority)
            ");

            $stmt->execute([
                'user_id' => $data['user_id'],
                'source_account_id' => $data['source_account_id'],
                'target_account_id' => $data['target_account_id'],
                'source_item_id' => $data['source_item_id'],
                'target_item_id' => $data['target_item_id'],
                'sync_mode' => $data['sync_mode'] ?? 'mirror',
                'offset_value' => $data['offset_value'] ?? 0,
                'percentage_value' => $data['percentage_value'] ?? 100.00,
                'min_stock' => $data['min_stock'] ?? 0,
                'max_stock' => $data['max_stock'] ?? null,
                'priority' => $data['priority'] ?? 5,
            ]);

            $ruleId = (int) $this->db->lastInsertId();

            log_info('Stock sync rule created', [
                'rule_id' => $ruleId,
                'source_item' => $data['source_item_id'],
                'target_item' => $data['target_item_id'],
            ]);

            return ['data' => $this->getRuleById($ruleId), 'error' => null];
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                log_warning('Stock sync rule already exists', [
                    'source_item' => $data['source_item_id'],
                    'target_item' => $data['target_item_id'],
                ]);
                return ['data' => null, 'error' => 'Regra de sincronização já existe para esse par de itens'];
            }

            log_error('Failed to create stock sync rule', [
                'error' => $e->getMessage(),
                'source_item' => $data['source_item_id'] ?? 'unknown',
                'target_item' => $data['target_item_id'] ?? 'unknown',
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza uma regra existente
     *
     * @param array<string, mixed> $data
     */
    public function updateRule(int $ruleId, array $data): array
    {
        $allowedFields = [
            'sync_mode',
            'offset_value',
            'percentage_value',
            'min_stock',
            'max_stock',
            'priority',
            'is_active',
        ];

        $sets = [];
        $params = ['id' => $ruleId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return ['data' => null, 'error' => 'Nenhum campo para atualizar'];
        }

        try {
            $sql = "UPDATE stock_sync_rules SET " . implode(', ', $sets) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            log_info('Stock sync rule updated', ['rule_id' => $ruleId, 'fields' => array_keys($data)]);

            return ['data' => $this->getRuleById($ruleId), 'error' => null];
        } catch (\PDOException $e) {
            log_error('Failed to update stock sync rule', ['rule_id' => $ruleId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Deleta uma regra de sincronização
     */
    public function deleteRule(int $ruleId): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM stock_sync_rules WHERE id = :id");
            $stmt->execute(['id' => $ruleId]);
            $deleted = $stmt->rowCount() > 0;

            if ($deleted) {
                log_info('Stock sync rule deleted', ['rule_id' => $ruleId]);
            }

            return $deleted;
        } catch (\PDOException $e) {
            log_error('Failed to delete stock sync rule', ['rule_id' => $ruleId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Busca regra por ID
     */
    public function getRuleById(int $ruleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM stock_sync_rules WHERE id = :id");
        $stmt->execute(['id' => $ruleId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Lista regras de um usuário
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRulesByUser(int $userId, bool $activeOnly = false): array
    {
        $sql = "SELECT r.*, sa.nickname AS source_nickname, ta.nickname AS target_nickname
                FROM stock_sync_rules r
                LEFT JOIN ml_accounts sa ON sa.id = r.source_account_id
                LEFT JOIN ml_accounts ta ON ta.id = r.target_account_id
                WHERE r.user_id = :user_id";

        if ($activeOnly) {
            $sql .= " AND r.is_active = 1";
        }

        $sql .= " ORDER BY r.priority ASC, r.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Sincronização completa (full sync)
    // =========================================================================

    /**
     * Executa sincronização completa: busca estoque atual de TODAS as source_items
     * e enfileira atualizações quando há diferença.
     *
     * @return array{queued: int, skipped: int, errors: int}
     */
    public function fullSync(int $userId): array
    {
        $rules = $this->getRulesByUser($userId, activeOnly: true);
        $stats = ['queued' => 0, 'skipped' => 0, 'errors' => 0, 'rate_limited' => false];

        log_info('Full stock sync started', ['user_id' => $userId, 'rules_count' => count($rules)]);

        foreach ($rules as $rule) {
            // Respeitar rate limit entre iterações
            if (!$this->checkRateLimit()) {
                log_warning('Rate limit atingido durante full sync', [
                    'user_id' => $userId,
                    'processed' => $stats['queued'] + $stats['skipped'],
                    'remaining' => count($rules) - $stats['queued'] - $stats['skipped'] - $stats['errors'],
                ]);
                $stats['rate_limited'] = true;
                break;
            }

            try {
                $sourceQuantity = $this->fetchItemStock(
                    (int) $rule['source_account_id'],
                    $rule['source_item_id']
                );

                if ($sourceQuantity === null) {
                    $stats['errors']++;
                    continue;
                }

                if (!$this->checkRateLimit()) {
                    $stats['rate_limited'] = true;
                    break;
                }

                $targetQuantity = $this->calculateTargetQuantity($sourceQuantity, $rule);

                // Obter estoque atual do target para comparar
                $currentTargetQuantity = $this->fetchItemStock(
                    (int) $rule['target_account_id'],
                    $rule['target_item_id']
                );

                if ($currentTargetQuantity !== null && $currentTargetQuantity === $targetQuantity) {
                    $stats['skipped']++;
                    continue;
                }

                $this->enqueue(
                    ruleId: (int) $rule['id'],
                    sourceQuantity: $sourceQuantity,
                    targetQuantityBefore: $currentTargetQuantity,
                    targetQuantityCalculated: $targetQuantity,
                    triggerType: 'full_sync',
                    priority: (int) $rule['priority']
                );

                $stats['queued']++;
            } catch (\Throwable $e) {
                $stats['errors']++;
                log_error('Full sync error for rule', [
                    'rule_id' => $rule['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        log_info('Full stock sync completed', array_merge($stats, ['user_id' => $userId]));

        return $stats;
    }

    // =========================================================================
    // Sincronização incremental (a partir de webhook ou trigger)
    // =========================================================================

    /**
     * Processa alteração de estoque de um item source (geralmente vindo por webhook).
     * Localiza todas as regras associadas e enfileira atualizações.
     *
     * @return array{queued: int, rules_matched: int}
     */
    public function handleStockChange(string $sourceItemId, int $newQuantity, string $triggerType = 'webhook'): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM stock_sync_rules
            WHERE source_item_id = :source_item_id AND is_active = 1
            ORDER BY priority ASC
        ");
        $stmt->execute(['source_item_id' => $sourceItemId]);
        $rules = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stats = ['queued' => 0, 'rules_matched' => count($rules)];

        foreach ($rules as $rule) {
            try {
                $targetQuantity = $this->calculateTargetQuantity($newQuantity, $rule);

                $this->enqueue(
                    ruleId: (int) $rule['id'],
                    sourceQuantity: $newQuantity,
                    targetQuantityBefore: $rule['last_target_quantity'] !== null ? (int) $rule['last_target_quantity'] : null,
                    targetQuantityCalculated: $targetQuantity,
                    triggerType: $triggerType,
                    priority: (int) $rule['priority']
                );

                $stats['queued']++;
            } catch (\Throwable $e) {
                log_error('Stock change enqueue error', [
                    'source_item' => $sourceItemId,
                    'rule_id' => $rule['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($stats['rules_matched'] > 0) {
            log_info('Stock change processed', [
                'source_item' => $sourceItemId,
                'new_quantity' => $newQuantity,
                'trigger' => $triggerType,
                'queued' => $stats['queued'],
            ]);
        }

        return $stats;
    }

    // =========================================================================
    // Webhook handler — recebimento de notificações do Mercado Livre
    // =========================================================================

    /**
     * Processa notificação de webhook do Mercado Livre
     *
     * O ML envia: { "resource": "/items/MLB123", "topic": "items", "user_id": 123, ... }
     *
     * @param array{resource: string, topic: string, user_id?: int} $payload
     */
    public function processWebhook(array $payload): array
    {
        $resource = $payload['resource'] ?? '';
        $topic = $payload['topic'] ?? '';

        if ($topic !== 'items' || $resource === '') {
            return ['processed' => false, 'reason' => 'Not a relevant item notification'];
        }

        // Extrair item ID do resource (/items/MLB12345)
        if (!preg_match('#/items/(MLB\d+)#', $resource, $matches)) {
            return ['processed' => false, 'reason' => 'Could not extract item ID from resource'];
        }

        $itemId = $matches[1];

        // Buscar TODAS as regras ativas que rastreiam esse item como source
        $stmt = $this->db->prepare("
            SELECT DISTINCT r.source_account_id
            FROM stock_sync_rules r
            WHERE r.source_item_id = :item_id AND r.is_active = 1
            LIMIT 1
        ");
        $stmt->execute(['item_id' => $itemId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return ['processed' => false, 'reason' => 'Item not tracked by any sync rule'];
        }

        // Buscar estoque atual via API usando a conta source
        $currentStock = $this->fetchItemStock((int) $row['source_account_id'], $itemId);
        if ($currentStock === null) {
            return ['processed' => false, 'reason' => 'Could not fetch current stock from ML API'];
        }

        // Delegar para handleStockChange que processa TODAS as regras para esse item
        $result = $this->handleStockChange($itemId, $currentStock, 'webhook');

        if ($result['rules_matched'] === 0) {
            return ['processed' => false, 'reason' => 'No active rules matched'];
        }

        return [
            'processed' => true,
            'item_id' => $itemId,
            'stock' => $currentStock,
            'queued' => $result['queued'],
            'rules_matched' => $result['rules_matched'],
        ];
    }

    // =========================================================================
    // Fila de processamento
    // =========================================================================

    /**
     * Adiciona item à fila de sincronização
     */
    public function enqueue(
        int $ruleId,
        int $sourceQuantity,
        ?int $targetQuantityBefore,
        int $targetQuantityCalculated,
        string $triggerType,
        int $priority = 5
    ): int {
        // Verificar se já existe item pendente/em processamento para essa regra (deduplicação)
        $existingStmt = $this->db->prepare("
            SELECT id FROM stock_sync_queue
            WHERE rule_id = :rule_id AND status IN ('pending', 'processing')
            LIMIT 1
        ");
        $existingStmt->execute(['rule_id' => $ruleId]);

        if ($existingStmt->fetch()) {
            // Atualizar o item existente com os valores mais recentes
            $updateStmt = $this->db->prepare("
                UPDATE stock_sync_queue
                SET source_quantity = :source_quantity,
                    target_quantity_before = :target_quantity_before,
                    target_quantity_calculated = :target_quantity_calculated,
                    trigger_type = :trigger_type,
                    priority = :priority
                WHERE rule_id = :rule_id AND status = 'pending'
                LIMIT 1
            ");
            $updateStmt->execute([
                'rule_id' => $ruleId,
                'source_quantity' => $sourceQuantity,
                'target_quantity_before' => $targetQuantityBefore,
                'target_quantity_calculated' => $targetQuantityCalculated,
                'trigger_type' => $triggerType,
                'priority' => min(10, max(1, $priority)),
            ]);

            log_debug('Stock sync queue item updated (dedup)', ['rule_id' => $ruleId]);
            return 0;
        }

        $stmt = $this->db->prepare("
            INSERT INTO stock_sync_queue
                (rule_id, source_quantity, target_quantity_before, target_quantity_calculated,
                 trigger_type, priority)
            VALUES
                (:rule_id, :source_quantity, :target_quantity_before, :target_quantity_calculated,
                 :trigger_type, :priority)
        ");

        $stmt->execute([
            'rule_id' => $ruleId,
            'source_quantity' => $sourceQuantity,
            'target_quantity_before' => $targetQuantityBefore,
            'target_quantity_calculated' => $targetQuantityCalculated,
            'trigger_type' => $triggerType,
            'priority' => min(10, max(1, $priority)),
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Processa itens da fila.
     * Busca itens pendentes ordenados por prioridade e processa respeitando rate limit.
     *
     * @return array{processed: int, failed: int, skipped: int, retried: int}
     */
    /**
     * Processa itens da fila.
     * Busca itens pendentes ordenados por prioridade e processa respeitando rate limit.
     * Quando $userId é informado, processa apenas itens daquele usuário.
     *
     * @return array{processed: int, failed: int, skipped: int, retried: int}
     */
    public function processQueue(int $limit = 50, ?int $userId = null): array
    {
        $stats = ['processed' => 0, 'failed' => 0, 'skipped' => 0, 'retried' => 0];

        // Buscar itens: pending + retry elegíveis + itens stuck em 'processing' há mais de 5 minutos
        $userFilter = '';
        $params = [];

        if ($userId !== null) {
            $userFilter = 'AND r.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $stmt = $this->db->prepare("
            SELECT q.*, r.source_account_id, r.target_account_id,
                   r.source_item_id, r.target_item_id, r.sync_mode,
                   r.offset_value, r.percentage_value, r.min_stock, r.max_stock,
                   r.user_id
            FROM stock_sync_queue q
            JOIN stock_sync_rules r ON r.id = q.rule_id
            WHERE (
                (q.status = 'pending')
                OR (q.status = 'failed' AND q.attempts < q.max_attempts AND q.next_retry_at <= NOW())
                OR (q.status = 'processing' AND q.updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
            )
            {$userFilter}
            ORDER BY q.priority ASC, q.created_at ASC
            LIMIT :queue_limit
        ");
        $stmt->bindValue('queue_limit', $limit, \PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            if (!$this->checkRateLimit()) {
                log_warning('Stock sync rate limit reached, stopping queue processing');
                break;
            }

            $result = $this->processQueueItem($item);

            match ($result) {
                'completed' => $stats['processed']++,
                'failed' => $stats['failed']++,
                'skipped' => $stats['skipped']++,
                'retried' => $stats['retried']++,
                default => $stats['failed']++,
            };
        }

        if ($stats['processed'] > 0 || $stats['failed'] > 0) {
            log_info('Stock sync queue processed', $stats);
        }

        return $stats;
    }

    /**
     * Processa um item individual da fila
     */
    private function processQueueItem(array $item): string
    {
        $queueId = (int) $item['id'];
        $startTime = microtime(true);

        // Marcar como processing
        $this->updateQueueStatus($queueId, 'processing');

        try {
            $targetAccountId = (int) $item['target_account_id'];
            $targetItemId = $item['target_item_id'];
            $targetQuantity = (int) $item['target_quantity_calculated'];

            // Buscar estoque atual do target antes de atualizar
            $currentTargetStock = $this->fetchItemStock($targetAccountId, $targetItemId);

            // Se já está sincronizado, skip
            if ($currentTargetStock !== null && $currentTargetStock === $targetQuantity) {
                $this->updateQueueStatus($queueId, 'skipped');
                $this->recordHistory(
                    item: $item,
                    queueId: $queueId,
                    targetQuantityBefore: $currentTargetStock,
                    targetQuantityAfter: $targetQuantity,
                    status: 'skipped',
                    durationMs: $this->elapsedMs($startTime)
                );
                return 'skipped';
            }

            // Atualizar estoque via API do ML
            $apiResult = $this->updateItemStock($targetAccountId, $targetItemId, $targetQuantity);

            if ($apiResult['success']) {
                $this->updateQueueStatus($queueId, 'completed');

                // Atualizar a regra com valores atuais
                $this->updateRuleLastSync(
                    (int) $item['rule_id'],
                    (int) $item['source_quantity'],
                    $targetQuantity
                );

                $this->recordHistory(
                    item: $item,
                    queueId: $queueId,
                    targetQuantityBefore: $currentTargetStock,
                    targetQuantityAfter: $targetQuantity,
                    status: 'success',
                    durationMs: $this->elapsedMs($startTime),
                    apiResponse: $apiResult['response'] ?? null
                );

                return 'completed';
            }

            // Falha na API — retry se ainda tem tentativas
            return $this->handleQueueItemFailure(
                $queueId,
                $item,
                $apiResult['message'] ?? 'Unknown API error',
                $startTime,
                $currentTargetStock
            );
        } catch (\Throwable $e) {
            log_error('Queue item processing failed', [
                'queue_id' => $queueId,
                'error' => $e->getMessage(),
            ]);

            return $this->handleQueueItemFailure(
                $queueId,
                $item,
                $e->getMessage(),
                $startTime,
                null
            );
        }
    }

    /**
     * Lida com falha em item da fila — retry com exponential backoff
     */
    private function handleQueueItemFailure(
        int $queueId,
        array $item,
        string $errorMessage,
        float $startTime,
        ?int $currentTargetStock
    ): string {
        $attempts = (int) $item['attempts'] + 1;
        $maxAttempts = (int) ($item['max_attempts'] ?? self::MAX_ATTEMPTS);

        if ($attempts < $maxAttempts) {
            // Exponential backoff: 1s, 2s, 4s
            $delaySec = self::BASE_DELAY_SECONDS * (2 ** ($attempts - 1));
            $nextRetry = date('Y-m-d H:i:s', time() + $delaySec);

            $stmt = $this->db->prepare("
                UPDATE stock_sync_queue
                SET status = 'failed', attempts = :attempts,
                    last_error = :error, next_retry_at = :next_retry
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $queueId,
                'attempts' => $attempts,
                'error' => $errorMessage,
                'next_retry' => $nextRetry,
            ]);

            log_warning('Stock sync item will retry', [
                'queue_id' => $queueId,
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'next_retry' => $nextRetry,
            ]);

            return 'retried';
        }

        // Todas as tentativas esgotadas
        $this->updateQueueStatus($queueId, 'failed', $errorMessage, $attempts);

        $this->recordHistory(
            item: $item,
            queueId: $queueId,
            targetQuantityBefore: $currentTargetStock,
            targetQuantityAfter: (int) $item['target_quantity_calculated'],
            status: 'failed',
            durationMs: $this->elapsedMs($startTime),
            errorMessage: $errorMessage
        );

        log_error('Stock sync item permanently failed', [
            'queue_id' => $queueId,
            'attempts' => $attempts,
            'error' => $errorMessage,
        ]);

        return 'failed';
    }

    // =========================================================================
    // API Mercado Livre — Estoque
    // =========================================================================

    /**
     * Busca estoque atual de um item via API do ML
     */
    public function fetchItemStock(int $accountId, string $itemId): ?int
    {
        try {
            $client = $this->getClient($accountId);

            // Buscar apenas o item (sem description) — precisamos só de available_quantity
            $item = $client->get("/items/{$itemId}", [], 60, true);

            if (empty($item) || isset($item['error'])) {
                log_warning('Could not fetch item stock', [
                    'account_id' => $accountId,
                    'item_id' => $itemId,
                    'error' => $item['error'] ?? 'empty response',
                ]);
                return null;
            }

            $this->trackApiCall();

            return isset($item['available_quantity']) ? (int) $item['available_quantity'] : null;
        } catch (\Throwable $e) {
            log_error('Failed to fetch item stock', [
                'account_id' => $accountId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Atualiza estoque de um item via API do ML
     *
     * @return array{success: bool, message: string, response?: array<string, mixed>}
     */
    public function updateItemStock(int $accountId, string $itemId, int $quantity): array
    {
        try {
            $client = $this->getClient($accountId);
            $result = $client->updateItem($itemId, ['available_quantity' => $quantity]);

            $this->trackApiCall();

            return [
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? 'unknown',
                'response' => $result['response'] ?? [],
            ];
        } catch (\Throwable $e) {
            log_error('Failed to update item stock', [
                'account_id' => $accountId,
                'item_id' => $itemId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // Cálculo de estoque target
    // =========================================================================

    /**
     * Calcula a quantidade a definir no target com base na regra
     *
     * @param array<string, mixed> $rule
     */
    public function calculateTargetQuantity(int $sourceQuantity, array $rule): int
    {
        $mode = $rule['sync_mode'] ?? 'mirror';
        $minStock = (int) ($rule['min_stock'] ?? 0);
        $maxStock = $rule['max_stock'] !== null ? (int) $rule['max_stock'] : null;

        $calculated = match ($mode) {
            'mirror' => $sourceQuantity,
            'offset' => $sourceQuantity + (int) ($rule['offset_value'] ?? 0),
            'percentage' => (int) round($sourceQuantity * ((float) ($rule['percentage_value'] ?? 100)) / 100),
            'custom' => $sourceQuantity, // fallback — custom logic pode ser estendido
            default => $sourceQuantity,
        };

        // Aplicar limites min/max
        $calculated = max($minStock, $calculated);
        if ($maxStock !== null) {
            $calculated = min($maxStock, $calculated);
        }

        return max(0, $calculated);
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /**
     * Obtém configurações de sync de um usuário
     */
    public function getSettings(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM stock_sync_settings WHERE user_id = :user_id");
        $stmt->execute(['user_id' => $userId]);
        $settings = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$settings) {
            return [
                'user_id' => $userId,
                'is_enabled' => true,
                'rate_limit_per_minute' => self::DEFAULT_RATE_LIMIT,
                'full_sync_interval_minutes' => 60,
                'webhook_enabled' => true,
                'notify_on_error' => true,
                'notify_on_sync' => false,
            ];
        }

        return $settings;
    }

    /**
     * Atualiza configurações de sync
     *
     * @param array<string, mixed> $data
     */
    public function updateSettings(int $userId, array $data): array
    {
        $allowedFields = [
            'is_enabled',
            'rate_limit_per_minute',
            'full_sync_interval_minutes',
            'webhook_enabled',
            'notify_on_error',
            'notify_on_sync',
        ];

        // Validar ranges de campos numéricos
        if (isset($data['rate_limit_per_minute'])) {
            $data['rate_limit_per_minute'] = max(1, min(100, (int) $data['rate_limit_per_minute']));
        }
        if (isset($data['full_sync_interval_minutes'])) {
            $data['full_sync_interval_minutes'] = max(5, min(1440, (int) $data['full_sync_interval_minutes']));
        }

        // Sanitizar booleans
        foreach (['is_enabled', 'webhook_enabled', 'notify_on_error', 'notify_on_sync'] as $boolField) {
            if (isset($data[$boolField])) {
                $data[$boolField] = $data[$boolField] ? 1 : 0;
            }
        }

        $sets = [];
        $params = ['user_id' => $userId];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($sets)) {
            return $this->getSettings($userId);
        }

        try {
            // Upsert
            $stmt = $this->db->prepare(
                "
                INSERT INTO stock_sync_settings (user_id, " . implode(', ', array_keys(array_diff_key($params, ['user_id' => 1]))) . ")
                VALUES (:user_id, " . implode(', ', array_map(fn(string $f) => ":{$f}", array_keys(array_diff_key($params, ['user_id' => 1])))) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $sets)
            );
            $stmt->execute($params);

            log_info('Stock sync settings updated', ['user_id' => $userId]);

            return $this->getSettings($userId);
        } catch (\PDOException $e) {
            log_error('Failed to update stock sync settings', ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    // =========================================================================
    // Histórico
    // =========================================================================

    /**
     * Retorna histórico de sync com paginação
     *
     * @param array{
     *   rule_id?: int,
     *   item_id?: string,
     *   status?: string,
     *   trigger_type?: string,
     *   date_from?: string,
     *   date_to?: string,
     *   limit?: int,
     *   offset?: int
     * } $filters
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getHistory(int $userId, array $filters = []): array
    {
        $where = ["r.user_id = :user_id"];
        $params = ['user_id' => $userId];

        if (!empty($filters['rule_id'])) {
            $where[] = "h.rule_id = :rule_id";
            $params['rule_id'] = $filters['rule_id'];
        }
        if (!empty($filters['item_id'])) {
            $where[] = "(h.source_item_id = :item_id OR h.target_item_id = :item_id2)";
            $params['item_id'] = $filters['item_id'];
            $params['item_id2'] = $filters['item_id'];
        }
        if (!empty($filters['status'])) {
            $where[] = "h.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['trigger_type'])) {
            $where[] = "h.trigger_type = :trigger_type";
            $params['trigger_type'] = $filters['trigger_type'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "h.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "h.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        $limit = min(100, (int) ($filters['limit'] ?? 50));
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        // Total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM stock_sync_history h
            JOIN stock_sync_rules r ON r.id = h.rule_id
            WHERE {$whereClause}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Items — usar bind params para limit/offset
        $params['_limit'] = $limit;
        $params['_offset'] = $offset;

        $stmt = $this->db->prepare("
            SELECT h.* FROM stock_sync_history h
            JOIN stock_sync_rules r ON r.id = h.rule_id
            WHERE {$whereClause}
            ORDER BY h.created_at DESC
            LIMIT :_limit OFFSET :_offset
        ");

        foreach ($params as $key => $value) {
            if ($key === '_limit' || $key === '_offset') {
                $stmt->bindValue($key, $value, \PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return ['items' => $items, 'total' => $total];
    }

    /**
     * Estatísticas de sincronização
     */
    public function getStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_rules,
                SUM(is_active) AS active_rules,
                MAX(last_synced_at) AS last_sync
            FROM stock_sync_rules
            WHERE user_id = :user_id
        ");
        $stmt->execute(['user_id' => $userId]);
        $ruleStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) AS processing,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
            FROM stock_sync_queue q
            JOIN stock_sync_rules r ON r.id = q.rule_id
            WHERE r.user_id = :user_id
              AND q.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute(['user_id' => $userId]);
        $queueStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT
                SUM(CASE WHEN h.status = 'success' THEN 1 ELSE 0 END) AS success_count,
                SUM(CASE WHEN h.status = 'failed' THEN 1 ELSE 0 END) AS error_count,
                AVG(h.duration_ms) AS avg_duration_ms
            FROM stock_sync_history h
            JOIN stock_sync_rules r ON r.id = h.rule_id
            WHERE r.user_id = :user_id
              AND h.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute(['user_id' => $userId]);
        $historyStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'rules' => $ruleStats,
            'queue_24h' => $queueStats,
            'history_24h' => $historyStats,
        ];
    }

    // =========================================================================
    // Helpers internos
    // =========================================================================

    /**
     * Obtém ou cria MercadoLivreClient para uma conta
     */
    private function getClient(int $accountId): MercadoLivreClient
    {
        // Invalidar cache se expirado (TTL de 30 min para forçar refresh de token)
        if (
            isset($this->clientCache[$accountId])
            && (time() - ($this->clientCacheTime[$accountId] ?? 0)) > self::CLIENT_CACHE_TTL
        ) {
            unset($this->clientCache[$accountId], $this->clientCacheTime[$accountId]);
        }

        if (!isset($this->clientCache[$accountId])) {
            $client = new MercadoLivreClient($accountId);
            $client->ensureValidAccessToken();
            $this->clientCache[$accountId] = $client;
            $this->clientCacheTime[$accountId] = time();
        }

        return $this->clientCache[$accountId];
    }

    /**
     * Valida dados para criação de regra
     *
     * @param array<string, mixed> $data
     */
    private function validateRuleData(array $data): void
    {
        $required = ['user_id', 'source_account_id', 'target_account_id', 'source_item_id', 'target_item_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        if (($data['source_account_id'] ?? 0) === ($data['target_account_id'] ?? 0)
            && ($data['source_item_id'] ?? '') === ($data['target_item_id'] ?? '')
        ) {
            throw new \InvalidArgumentException("Source e target não podem ser o mesmo item na mesma conta");
        }

        $validModes = ['mirror', 'offset', 'percentage', 'custom'];
        if (isset($data['sync_mode']) && !in_array($data['sync_mode'], $validModes, true)) {
            throw new \InvalidArgumentException("Modo de sync inválido: {$data['sync_mode']}");
        }
    }

    private function updateQueueStatus(int $queueId, string $status, ?string $error = null, ?int $attempts = null): void
    {
        $sets = ['status = :status'];
        $params = ['id' => $queueId, 'status' => $status];

        if ($status === 'completed' || $status === 'skipped') {
            $sets[] = 'processed_at = NOW()';
        }
        if ($error !== null) {
            $sets[] = 'last_error = :error';
            $params['error'] = $error;
        }
        if ($attempts !== null) {
            $sets[] = 'attempts = :attempts';
            $params['attempts'] = $attempts;
        }

        $sql = "UPDATE stock_sync_queue SET " . implode(', ', $sets) . " WHERE id = :id";
        $this->db->prepare($sql)->execute($params);
    }

    private function updateRuleLastSync(int $ruleId, int $sourceQuantity, int $targetQuantity): void
    {
        $stmt = $this->db->prepare("
            UPDATE stock_sync_rules
            SET last_synced_at = NOW(), last_source_quantity = :source_qty, last_target_quantity = :target_qty
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $ruleId,
            'source_qty' => $sourceQuantity,
            'target_qty' => $targetQuantity,
        ]);
    }

    /**
     * Registra entrada no histórico
     *
     * @param array<string, mixed> $item  Queue item com dados da regra
     * @param array<string, mixed>|null $apiResponse
     */
    private function recordHistory(
        array $item,
        int $queueId,
        ?int $targetQuantityBefore,
        int $targetQuantityAfter,
        string $status,
        int $durationMs,
        ?string $errorMessage = null,
        ?array $apiResponse = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO stock_sync_history
                    (rule_id, queue_id, source_account_id, target_account_id,
                     source_item_id, target_item_id, source_quantity,
                     target_quantity_before, target_quantity_after,
                     sync_mode, trigger_type, status, error_message,
                     api_response, duration_ms)
                VALUES
                    (:rule_id, :queue_id, :source_account_id, :target_account_id,
                     :source_item_id, :target_item_id, :source_quantity,
                     :target_quantity_before, :target_quantity_after,
                     :sync_mode, :trigger_type, :status, :error_message,
                     :api_response, :duration_ms)
            ");

            $stmt->execute([
                'rule_id' => $item['rule_id'],
                'queue_id' => $queueId,
                'source_account_id' => $item['source_account_id'],
                'target_account_id' => $item['target_account_id'],
                'source_item_id' => $item['source_item_id'],
                'target_item_id' => $item['target_item_id'],
                'source_quantity' => $item['source_quantity'],
                'target_quantity_before' => $targetQuantityBefore,
                'target_quantity_after' => $targetQuantityAfter,
                'sync_mode' => $item['sync_mode'] ?? 'mirror',
                'trigger_type' => $item['trigger_type'] ?? 'manual',
                'status' => $status,
                'error_message' => $errorMessage,
                'api_response' => $apiResponse !== null ? json_encode($apiResponse) : null,
                'duration_ms' => $durationMs,
            ]);
        } catch (\PDOException $e) {
            log_error('Failed to record stock sync history', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verifica rate limit — retorna true se pode prosseguir.
     * Usa CacheService (Redis/file) para compartilhar counter entre processos.
     */
    private function checkRateLimit(): bool
    {
        $minute = date('Y-m-d-H-i');
        $cacheKey = self::RATE_LIMIT_CACHE_PREFIX . $minute;

        $count = (int) ($this->cache->get($cacheKey) ?? 0);

        return $count < $this->rateLimitPerMinute;
    }

    /**
     * Registra chamada de API para rate limiting.
     * Persiste o counter em cache compartilhado.
     */
    private function trackApiCall(): void
    {
        $minute = date('Y-m-d-H-i');
        $cacheKey = self::RATE_LIMIT_CACHE_PREFIX . $minute;

        $current = (int) ($this->cache->get($cacheKey) ?? 0);
        $this->cache->set($cacheKey, $current + 1, 120); // TTL 2 min como safety
    }

    /**
     * Calcula tempo decorrido em milissegundos
     */
    private function elapsedMs(float $startTime): int
    {
        return (int) round((microtime(true) - $startTime) * 1000);
    }

    /**
     * Limpa itens processados da fila (manutenção)
     */
    public function cleanupQueue(int $daysOld = 7): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM stock_sync_queue
            WHERE status IN ('completed', 'skipped')
              AND processed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $daysOld]);
        $deleted = $stmt->rowCount();

        if ($deleted > 0) {
            log_info('Stock sync queue cleanup', ['deleted' => $deleted, 'days_old' => $daysOld]);
        }

        return $deleted;
    }

    /**
     * Retorna contagem de itens pendentes na fila
     */
    public function getPendingCount(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM stock_sync_queue
            WHERE status IN ('pending', 'processing')
               OR (status = 'failed' AND attempts < max_attempts AND next_retry_at <= NOW())
        ");

        return (int) $stmt->fetchColumn();
    }
}
