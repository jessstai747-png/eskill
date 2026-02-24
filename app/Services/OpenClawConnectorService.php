<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use PDOException;
use Throwable;

/**
 * OpenClawConnectorService
 *
 * Camada de integração para o sistema OpenClaw se conectar ao eskill.com.br.
 *
 * Responsabilidades:
 * - Listar contas ML ("sellers") disponíveis para o usuário autenticado
 * - Consultar e gerenciar itens (anúncios) do Mercado Livre
 * - Consultar pedidos
 * - Disparar ações assíncronas (reutiliza infra do AssistantConnector)
 * - Registrar/gerenciar webhooks outbound para notificações proativas
 * - Consultar analytics e dados de mercado
 */
class OpenClawConnectorService
{
    public const SCOPE_READ = 'openclaw:read';
    public const SCOPE_WRITE = 'openclaw:write';
    public const SCOPE_ADMIN = 'openclaw:admin';

    /**
     * Ações permitidas: herda do AssistantConnector + ações específicas do OpenClaw.
     *
     * @var array<string, bool>
     */
    private const ALLOWED_ACTIONS = [
        'answer_question' => true,
        'update_stock' => true,
        'update_price' => true,
        'reconcile_order' => true,
        'refresh_account_token' => true,
        'sync_item' => true,
        'pause_item' => true,
        'activate_item' => true,
    ];

    private ?PDO $db;
    private AssistantConnectorService $assistantConnector;

    public function __construct(?PDO $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $this->db = Database::getInstance();
            } catch (Throwable $e) {
                $this->db = null;
                log_warning('OpenClawConnectorService: DB indisponível', [
                    'service' => 'OpenClawConnectorService',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->assistantConnector = new AssistantConnectorService($this->db);
        $this->ensureWebhookTable();
    }

    public function isDbAvailable(): bool
    {
        return $this->db !== null;
    }

    // ========================================
    // Sellers (contas ML)
    // ========================================

    /**
     * @return array{success: bool, sellers?: list<array<string, mixed>>, error?: string, message?: string}
     */
    public function listSellers(int $userId): array
    {
        return $this->assistantConnector->listSellersForUser($userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSeller(int $userId, int $accountId): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT id, ml_user_id, nickname, email, site_id, status, last_synced_at, created_at '
                . 'FROM ml_accounts '
                . 'WHERE id = :id AND user_id = :user_id '
                . 'LIMIT 1'
        );
        $stmt->execute([':id' => $accountId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    // ========================================
    // Items (Anúncios ML)
    // ========================================

    /**
     * Lista itens de uma conta ML do usuário.
     *
     * @param array<string, mixed> $filters {status?: string, category_id?: string, search?: string, page?: int, per_page?: int}
     * @return array{success: bool, items?: list<array<string, mixed>>, pagination?: array<string, mixed>, error?: string}
     */
    public function listItems(int $userId, int $accountId, array $filters = []): array
    {
        if (!$this->userOwnsAccount($userId, $accountId)) {
            return ['success' => false, 'error' => 'Conta não autorizada'];
        }

        try {
            $itemService = new ItemService($accountId);
            $normalized = $this->normalizeConnectorItemFilters($filters);
            $page = $normalized['page'];
            $perPage = $normalized['per_page'];

            unset($normalized['page'], $normalized['per_page']);

            return $this->listItemsWithMlPaginationBridge(
                itemService: $itemService,
                filters: $normalized,
                page: $page,
                perPage: $perPage
            );
        } catch (Throwable $e) {
            log_error('OpenClawConnectorService::listItems failed', [
                'service' => 'OpenClawConnectorService',
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao listar itens'];
        }
    }

    /**
     * Obtém detalhes de um item específico.
     *
     * @return array{success: bool, item?: array<string, mixed>, error?: string}
     */
    public function getItem(int $userId, int $accountId, string $itemId): array
    {
        if (!$this->userOwnsAccount($userId, $accountId)) {
            return ['success' => false, 'error' => 'Conta não autorizada'];
        }

        try {
            $itemService = new ItemService($accountId);
            $result = $itemService->getItem($itemId);
            if (empty($result) || isset($result['error'])) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Item não encontrado',
                ];
            }

            return ['success' => true, 'item' => $result];
        } catch (Throwable $e) {
            log_error('OpenClawConnectorService::getItem failed', [
                'service' => 'OpenClawConnectorService',
                'account_id' => $accountId,
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao obter item'];
        }
    }

    /**
     * Estatísticas dos itens de uma conta.
     *
     * @return array{success: bool, stats?: array<string, mixed>, error?: string}
     */
    public function getItemsStats(int $userId, int $accountId): array
    {
        if (!$this->userOwnsAccount($userId, $accountId)) {
            return ['success' => false, 'error' => 'Conta não autorizada'];
        }

        try {
            $itemService = new ItemService($accountId);
            $stats = $itemService->getItemsStats();
            return ['success' => true, 'stats' => $stats];
        } catch (Throwable $e) {
            log_error('OpenClawConnectorService::getItemsStats failed', [
                'service' => 'OpenClawConnectorService',
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao obter estatísticas'];
        }
    }

    // ========================================
    // Orders (Pedidos)
    // ========================================

    /**
     * Lista pedidos de uma conta ML.
     *
     * @param array<string, mixed> $filters
     * @return array{success: bool, orders?: list<array<string, mixed>>, error?: string}
     */
    public function listOrders(int $userId, int $accountId, array $filters = []): array
    {
        if (!$this->userOwnsAccount($userId, $accountId)) {
            return ['success' => false, 'error' => 'Conta não autorizada'];
        }

        try {
            $orderService = new OrderService($accountId);
            $normalized = $this->normalizeConnectorOrderFilters($filters);
            return $orderService->listOrders($normalized);
        } catch (Throwable $e) {
            log_error('OpenClawConnectorService::listOrders failed', [
                'service' => 'OpenClawConnectorService',
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao listar pedidos'];
        }
    }

    /**
     * Obtém um pedido específico.
     *
     * @return array{success: bool, order?: array<string, mixed>, error?: string}
     */
    public function getOrder(int $userId, int $accountId, string $orderId): array
    {
        if (!$this->userOwnsAccount($userId, $accountId)) {
            return ['success' => false, 'error' => 'Conta não autorizada'];
        }

        try {
            $orderService = new OrderService($accountId);
            $result = $orderService->getOrder($orderId);
            if (!($result['success'] ?? false)) {
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Pedido não encontrado',
                ];
            }

            return $result;
        } catch (Throwable $e) {
            log_error('OpenClawConnectorService::getOrder failed', [
                'service' => 'OpenClawConnectorService',
                'account_id' => $accountId,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao obter pedido'];
        }
    }

    // ========================================
    // Actions (assíncronas — reutiliza AssistantConnector)
    // ========================================

    /**
     * Normaliza e valida uma ação. Retorna null se inválida.
     */
    public static function normalizeAction(string $action): ?string
    {
        $normalized = strtolower(trim($action));
        return isset(self::ALLOWED_ACTIONS[$normalized]) ? $normalized : null;
    }

    /**
     * Cria um action_run assíncrono.
     *
     * @param array<string, mixed> $payload {action, account_id|seller_id, parameters}
     * @return array{success: bool, action_run?: array<string, mixed>, created?: bool, error?: string, message?: string}
     */
    public function createAction(
        int $userId,
        array $payload,
        ?int $apiTokenId = null,
        ?string $idempotencyKey = null
    ): array {
        $rawAction = isset($payload['action']) ? (string)$payload['action'] : '';
        $action = self::normalizeAction($rawAction);
        if ($action === null) {
            $allowed = implode(', ', array_keys(self::ALLOWED_ACTIONS));
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => "Ação inválida: '{$rawAction}'. Permitidas: {$allowed}",
            ];
        }

        // Delegar para o AssistantConnector (infra compartilhada)
        return $this->assistantConnector->createActionRun(
            userId: $userId,
            payload: $payload,
            apiTokenId: $apiTokenId,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Consulta status de um action_run.
     *
     * @return array<string, mixed>|null
     */
    public function getAction(int $userId, int $actionRunId): ?array
    {
        return $this->assistantConnector->getActionRunForUser($userId, $actionRunId);
    }

    // ========================================
    // Webhooks Outbound (enviar notificações ao OpenClaw)
    // ========================================

    private function ensureWebhookTable(): void
    {
        if ($this->db === null) {
            return;
        }

        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS openclaw_webhooks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    url VARCHAR(2048) NOT NULL,
                    secret VARCHAR(255) NULL COMMENT 'HMAC secret for signature verification',
                    events JSON NOT NULL COMMENT 'Array of event types to subscribe',
                    is_active TINYINT(1) DEFAULT 1,
                    last_triggered_at TIMESTAMP NULL,
                    failure_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_active (is_active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            log_warning('OpenClawConnectorService: falha ao criar tabela openclaw_webhooks', [
                'service' => 'OpenClawConnectorService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lista webhooks registrados pelo usuário.
     *
     * @return array{success: bool, webhooks?: list<array<string, mixed>>, error?: string}
     */
    public function listWebhooks(int $userId): array
    {
        if ($this->db === null) {
            return ['success' => false, 'error' => 'DB indisponível'];
        }

        $stmt = $this->db->prepare(
            'SELECT id, name, url, events, is_active, last_triggered_at, failure_count, created_at '
                . 'FROM openclaw_webhooks '
                . 'WHERE user_id = :user_id '
                . 'ORDER BY created_at DESC'
        );
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $webhooks = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $row['events'] = $this->decodeJsonField($row['events'] ?? '[]');
            $webhooks[] = $row;
        }

        return ['success' => true, 'webhooks' => $webhooks];
    }

    /**
     * Registra um novo webhook outbound.
     *
     * @param list<string> $events Tipos de evento (ex: order.created, item.updated, stock.changed)
     * @return array{success: bool, webhook?: array<string, mixed>, error?: string, message?: string}
     */
    public function createWebhook(int $userId, string $name, string $url, array $events, ?string $secret = null): array
    {
        if ($this->db === null) {
            return ['success' => false, 'error' => 'DB indisponível'];
        }

        $name = trim($name);
        $url = trim($url);

        if ($name === '' || $url === '') {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Campos obrigatórios: name, url',
            ];
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'URL inválida',
            ];
        }

        $validEvents = self::getAvailableWebhookEvents();
        $invalidEvents = array_diff($events, $validEvents);
        if (!empty($invalidEvents)) {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Eventos inválidos: ' . implode(', ', $invalidEvents),
            ];
        }

        if ($secret === null) {
            $secret = bin2hex(random_bytes(32));
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO openclaw_webhooks (user_id, name, url, secret, events, is_active) '
                    . 'VALUES (:user_id, :name, :url, :secret, :events, 1)'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $name,
                ':url' => $url,
                ':secret' => $secret,
                ':events' => json_encode($events, JSON_UNESCAPED_UNICODE),
            ]);

            $webhookId = (int)$this->db->lastInsertId();

            return [
                'success' => true,
                'webhook' => [
                    'id' => $webhookId,
                    'name' => $name,
                    'url' => $url,
                    'secret' => $secret,
                    'events' => $events,
                    'is_active' => true,
                ],
            ];
        } catch (PDOException $e) {
            log_error('OpenClawConnectorService::createWebhook failed', [
                'service' => 'OpenClawConnectorService',
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Falha ao criar webhook'];
        }
    }

    /**
     * Remove um webhook do usuário.
     *
     * @return array{success: bool, error?: string}
     */
    public function deleteWebhook(int $userId, int $webhookId): array
    {
        if ($this->db === null) {
            return ['success' => false, 'error' => 'DB indisponível'];
        }

        $stmt = $this->db->prepare(
            'DELETE FROM openclaw_webhooks WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $webhookId, ':user_id' => $userId]);

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Webhook não encontrado'];
        }

        return ['success' => true];
    }

    /**
     * Testa um webhook enviando um payload de teste.
     *
     * @return array{success: bool, status_code?: int, error?: string}
     */
    public function testWebhook(int $userId, int $webhookId): array
    {
        if ($this->db === null) {
            return ['success' => false, 'error' => 'DB indisponível'];
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM openclaw_webhooks WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute([':id' => $webhookId, ':user_id' => $userId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($webhook)) {
            return ['success' => false, 'error' => 'Webhook não encontrado'];
        }

        $testPayload = [
            'event' => 'webhook.test',
            'timestamp' => date('c'),
            'data' => [
                'message' => 'Este é um teste de webhook do eskill.com.br para OpenClaw',
                'webhook_id' => $webhookId,
            ],
        ];

        return $this->deliverWebhook($webhook, $testPayload);
    }

    /**
     * Entrega um payload para um webhook registrado.
     *
     * @param array<string, mixed> $webhook
     * @param array<string, mixed> $payload
     * @return array{success: bool, status_code?: int, error?: string}
     */
    public function deliverWebhook(array $webhook, array $payload): array
    {
        $url = (string)($webhook['url'] ?? '');
        $secret = (string)($webhook['secret'] ?? '');

        if ($url === '') {
            return ['success' => false, 'error' => 'URL do webhook vazia'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['success' => false, 'error' => 'Falha ao serializar payload'];
        }

        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10, 'connect_timeout' => 5]);
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Signature' => $signature,
                    'X-Webhook-Source' => 'eskill.com.br',
                    'User-Agent' => 'eskill-openclaw-webhook/1.0',
                ],
                'body' => $body,
            ]);

            $statusCode = $response->getStatusCode();
            $webhookId = (int)($webhook['id'] ?? 0);

            if ($webhookId > 0 && $this->db !== null) {
                $this->db->prepare(
                    'UPDATE openclaw_webhooks SET last_triggered_at = NOW(), failure_count = 0, updated_at = NOW() WHERE id = :id'
                )->execute([':id' => $webhookId]);
            }

            return ['success' => true, 'status_code' => $statusCode];
        } catch (Throwable $e) {
            $webhookId = (int)($webhook['id'] ?? 0);
            if ($webhookId > 0 && $this->db !== null) {
                $this->db->prepare(
                    'UPDATE openclaw_webhooks SET failure_count = failure_count + 1, updated_at = NOW() WHERE id = :id'
                )->execute([':id' => $webhookId]);
            }

            log_error('OpenClawConnectorService::deliverWebhook failed', [
                'service' => 'OpenClawConnectorService',
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'Falha ao entregar webhook: ' . $e->getMessage()];
        }
    }

    /**
     * Tipos de evento disponíveis para webhook.
     *
     * @return list<string>
     */
    public static function getAvailableWebhookEvents(): array
    {
        return [
            'order.created',
            'order.updated',
            'order.shipped',
            'item.updated',
            'item.paused',
            'item.activated',
            'stock.changed',
            'price.changed',
            'question.received',
            'question.answered',
            'message.received',
            'account.token_refreshed',
            'webhook.test',
        ];
    }

    // ========================================
    // Internals
    // ========================================

    /**
     * Verifica se o userId é dono da conta ML.
     */
    private function userOwnsAccount(int $userId, int $accountId): bool
    {
        if ($this->db === null) {
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ml_accounts WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute([':id' => $accountId, ':user_id' => $userId]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Resolve account_id para um userId (delega para AssistantConnector).
     */
    public function resolveAccountId(int $userId, ?int $accountId, ?string $sellerId): ?int
    {
        return $this->assistantConnector->resolveAccountIdForUser($userId, $accountId, $sellerId);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeConnectorItemFilters(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPageRaw = $filters['per_page'] ?? $filters['limit'] ?? 50;
        $perPage = max(1, min(200, (int)$perPageRaw));

        $normalized = [
            'status' => $filters['status'] ?? null,
            'search' => $filters['search'] ?? null,
            'q' => $filters['q'] ?? null,
            'category' => $filters['category'] ?? $filters['category_id'] ?? null,
            'order' => $filters['order'] ?? null,
            'allow_local_cache' => $filters['allow_local_cache'] ?? null,
            'source' => $filters['source'] ?? null,
            'page' => $page,
            'per_page' => $perPage,
        ];

        return array_filter($normalized, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Converte paginação OpenClaw (até 200 por página) para o limite da API de itens do ML (50).
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function listItemsWithMlPaginationBridge(ItemService $itemService, array $filters, int $page, int $perPage): array
    {
        $items = [];
        $warnings = [];
        $total = 0;
        $aggregate = null;

        $remaining = $perPage;
        $offset = ($page - 1) * $perPage;
        $maxCalls = max(1, (int)ceil($perPage / 50));
        $calls = 0;

        while ($remaining > 0 && $calls < $maxCalls) {
            $chunkLimit = min(50, $remaining);
            $chunkFilters = $filters;
            $chunkFilters['limit'] = $chunkLimit;
            $chunkFilters['offset'] = $offset;

            $result = $itemService->listItems($chunkFilters);
            if (!($result['success'] ?? false)) {
                if ($calls === 0) {
                    return $result;
                }

                $warnings[] = 'Resposta parcial: uma das páginas da API do Mercado Livre falhou durante paginação ampliada.';
                break;
            }

            if (!is_array($aggregate)) {
                $aggregate = $result;
            }

            $chunkItems = $result['items'] ?? [];
            if (!is_array($chunkItems)) {
                $chunkItems = [];
            }

            $items = array_merge($items, $chunkItems);
            $received = count($chunkItems);
            $total = (int)($result['total'] ?? $total);

            if (isset($result['warning']) && is_string($result['warning']) && $result['warning'] !== '') {
                $warnings[] = $result['warning'];
            }

            $calls++;
            $remaining -= $received;

            if ($received === 0 || !($result['has_more'] ?? false)) {
                break;
            }

            $offset += $received;
        }

        if (!is_array($aggregate)) {
            return [
                'success' => false,
                'error' => 'ml_api_error',
                'message' => 'Falha ao buscar itens na API do Mercado Livre',
                'items' => [],
                'page' => $page,
                'pages' => 1,
                'limit' => $perPage,
                'total' => 0,
                'has_more' => false,
            ];
        }

        $items = array_slice($items, 0, $perPage);

        if ($total <= 0) {
            $total = (int)($aggregate['total'] ?? count($items));
        }

        $pages = max(1, (int)ceil($total / $perPage));
        $aggregate['items'] = $items;
        $aggregate['results'] = array_values(array_filter(
            array_map(
                static fn(array $item): ?string => isset($item['id']) && is_string($item['id']) ? $item['id'] : null,
                $items
            )
        ));
        $aggregate['page'] = $page;
        $aggregate['pages'] = $pages;
        $aggregate['limit'] = $perPage;
        $aggregate['total'] = $total;
        $aggregate['has_more'] = ($page * $perPage) < $total;

        if (!empty($warnings)) {
            $aggregate['warning'] = implode(' | ', array_values(array_unique($warnings)));
        }

        return $aggregate;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function normalizeConnectorOrderFilters(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPageRaw = $filters['per_page'] ?? $filters['limit'] ?? 50;
        $perPage = max(1, min(200, (int)$perPageRaw));

        $normalized = [
            'status' => $filters['status'] ?? null,
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'search' => $filters['search'] ?? null,
            'sort' => $filters['sort'] ?? null,
            'order' => $filters['order'] ?? null,
            'allow_local_cache' => $filters['allow_local_cache'] ?? null,
            'source' => $filters['source'] ?? null,
            'page' => $page,
            'limit' => $perPage,
        ];

        return array_filter($normalized, fn($value) => $value !== null && $value !== '');
    }

    /**
     * @return mixed
     */
    private function decodeJsonField(string $json): mixed
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
