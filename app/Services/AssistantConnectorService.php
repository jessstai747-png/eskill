<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use PDOException;
use Throwable;

/**
 * AssistantConnectorService
 *
 * Camada de persistência e validação para integração "assistant-connector".
 *
 * Responsabilidades:
 * - Listar contas ML ("sellers") disponíveis para o usuário autenticado
 * - Ingestão de eventos com idempotência (source + external_event_id)
 * - Criação/consulta de execuções de ações (assistant_action_runs) com idempotência
 */
class AssistantConnectorService
{
    public const SCOPE_READ = 'assistant:read';
    public const SCOPE_WRITE = 'assistant:write';
    public const SCOPE_ADMIN = 'assistant:admin';

    public const ACTION_ANSWER_QUESTION = 'answer_question';
    public const ACTION_UPDATE_STOCK = 'update_stock';
    public const ACTION_RECONCILE_ORDER = 'reconcile_order';
    public const ACTION_REFRESH_ACCOUNT_TOKEN = 'refresh_account_token';

    /**
     * @var array<string, bool>
     */
    private const ALLOWED_ACTIONS = [
        self::ACTION_ANSWER_QUESTION => true,
        self::ACTION_UPDATE_STOCK => true,
        self::ACTION_RECONCILE_ORDER => true,
        self::ACTION_REFRESH_ACCOUNT_TOKEN => true,
    ];

    private ?PDO $db;

    public function __construct(?PDO $db = null, bool $skipDbAutoConnect = false)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        if ($skipDbAutoConnect) {
            $this->db = null;
            return;
        }

        try {
            $this->db = Database::getInstance();
        } catch (Throwable $e) {
            $this->db = null;
            log_warning('AssistantConnectorService: DB indisponível', [
                'service' => 'AssistantConnectorService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function isDbAvailable(): bool
    {
        return $this->db !== null;
    }

    public static function normalizeAction(string $action): ?string
    {
        $normalized = strtolower(trim($action));
        if ($normalized === '') {
            return null;
        }

        return isset(self::ALLOWED_ACTIONS[$normalized]) ? $normalized : null;
    }

    /**
     * Idempotency key determinística para ações.
     *
     * - Se o cliente enviar uma key (payload ou header), usamos essa key.
     * - Caso contrário, derivamos de (action + account_id + parameters).
     */
    public static function deriveIdempotencyKey(
        string $action,
        int $accountId,
        array $parameters,
        ?string $providedKey = null
    ): string {
        $providedKey = is_string($providedKey) ? trim($providedKey) : '';
        if ($providedKey !== '') {
            return $providedKey;
        }

        $payload = [
            'action' => $action,
            'account_id' => $accountId,
            'parameters' => $parameters,
        ];

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            // Fallback extremamente improvável; ainda garante determinismo.
            $encoded = $action . '|' . $accountId;
        }

        return hash('sha256', $encoded);
    }

    /**
     * @return array{success: bool, sellers?: array<int, array<string, mixed>>, error?: string, message?: string}
     */
    public function listSellersForUser(int $userId): array
    {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'db_unavailable',
                'message' => 'Banco de dados indisponível para listar sellers.',
            ];
        }

        $stmt = $this->db->prepare(
            'SELECT id, ml_user_id, nickname, email, site_id, status, last_synced_at, created_at '
                . 'FROM ml_accounts '
                . 'WHERE user_id = :user_id '
                . 'ORDER BY created_at ASC'
        );
        $stmt->execute([':user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'sellers' => is_array($rows) ? $rows : [],
        ];
    }

    /**
     * Resolve um account_id (ml_accounts.id) para um usuário, aceitando:
     * - account_id (id interno)
     * - seller_id (ml_user_id do ML)
     */
    public function resolveAccountIdForUser(int $userId, ?int $accountId, ?string $sellerId): ?int
    {
        if ($this->db === null) {
            return null;
        }

        $accountId = $accountId !== null ? (int)$accountId : null;
        $sellerId = is_string($sellerId) ? trim($sellerId) : null;

        if ($accountId !== null && $accountId > 0) {
            $stmt = $this->db->prepare(
                'SELECT id FROM ml_accounts WHERE id = :id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute([':id' => $accountId, ':user_id' => $userId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        }

        if ($sellerId !== null && $sellerId !== '' && ctype_digit($sellerId)) {
            $stmt = $this->db->prepare(
                'SELECT id FROM ml_accounts WHERE ml_user_id = :ml_user_id AND user_id = :user_id LIMIT 1'
            );
            $stmt->execute([':ml_user_id' => (int)$sellerId, ':user_id' => $userId]);
            $id = $stmt->fetchColumn();
            return $id ? (int)$id : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, event?: array<string, mixed>, created?: bool, error?: string, message?: string}
     */
    public function ingestEvent(int $userId, array $payload): array
    {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'db_unavailable',
                'message' => 'Banco de dados indisponível para ingestão de eventos.',
            ];
        }

        $source = isset($payload['source']) ? (string)$payload['source'] : 'assistant';
        $externalEventId = isset($payload['event_id']) ? trim((string)$payload['event_id']) : '';
        $eventType = isset($payload['type']) ? trim((string)$payload['type']) : '';
        $occurredAt = isset($payload['occurred_at']) ? trim((string)$payload['occurred_at']) : null;

        $accountIdInput = isset($payload['account_id']) ? (int)$payload['account_id'] : null;
        $sellerIdInput = isset($payload['seller_id']) ? (string)$payload['seller_id'] : null;
        $accountId = $this->resolveAccountIdForUser($userId, $accountIdInput, $sellerIdInput);

        $eventPayload = $payload['payload'] ?? $payload;
        if (!is_array($eventPayload)) {
            $eventPayload = ['value' => $eventPayload];
        }

        if ($externalEventId === '' || $eventType === '') {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Campos obrigatórios: event_id, type',
            ];
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO assistant_events (account_id, source, external_event_id, event_type, occurred_at, payload) '
                    . 'VALUES (:account_id, :source, :external_event_id, :event_type, :occurred_at, :payload)'
            );

            $stmt->execute([
                ':account_id' => $accountId,
                ':source' => $source,
                ':external_event_id' => $externalEventId,
                ':event_type' => $eventType,
                ':occurred_at' => $occurredAt !== null && $occurredAt !== '' ? $occurredAt : null,
                ':payload' => json_encode($eventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $eventId = (int)$this->db->lastInsertId();
            $event = $this->getEventById($eventId);

            return [
                'success' => true,
                'created' => true,
                'event' => $event ?? ['id' => $eventId],
            ];
        } catch (PDOException $e) {
            // Duplicate key -> idempotência
            if ($this->isDuplicateKeyException($e)) {
                $existing = $this->getEventByExternalId($source, $externalEventId);
                return [
                    'success' => true,
                    'created' => false,
                    'event' => $existing ?? [
                        'source' => $source,
                        'external_event_id' => $externalEventId,
                        'event_type' => $eventType,
                    ],
                ];
            }

            log_error('AssistantConnectorService: falha ao inserir event', [
                'service' => 'AssistantConnectorService',
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => 'db_error',
                'message' => 'Falha ao persistir evento.',
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, action_run?: array<string, mixed>, created?: bool, error?: string, message?: string}
     */
    public function createActionRun(
        int $userId,
        array $payload,
        ?int $apiTokenId = null,
        ?string $idempotencyKey = null
    ): array {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'db_unavailable',
                'message' => 'Banco de dados indisponível para criar ações.',
            ];
        }

        $rawAction = isset($payload['action']) ? (string)$payload['action'] : '';
        $action = self::normalizeAction($rawAction);
        if ($action === null) {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Ação inválida. Use uma action permitida.',
            ];
        }

        $accountIdInput = isset($payload['account_id']) ? (int)$payload['account_id'] : null;
        $sellerIdInput = isset($payload['seller_id']) ? (string)$payload['seller_id'] : null;
        $accountId = $this->resolveAccountIdForUser($userId, $accountIdInput, $sellerIdInput);

        if ($accountId === null) {
            return [
                'success' => false,
                'error' => 'validation_error',
                'message' => 'Conta inválida ou não autorizada. Informe account_id (interno) ou seller_id (ml_user_id).',
            ];
        }

        $parameters = $payload['parameters'] ?? $payload['params'] ?? [];
        if (!is_array($parameters)) {
            $parameters = ['value' => $parameters];
        }

        $idempotencyKey = self::deriveIdempotencyKey($action, $accountId, $parameters, $idempotencyKey);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO assistant_action_runs '
                    . '(account_id, user_id, api_token_id, action, idempotency_key, status, parameters) '
                    . 'VALUES (:account_id, :user_id, :api_token_id, :action, :idempotency_key, \'queued\', :parameters)'
            );

            $stmt->execute([
                ':account_id' => $accountId,
                ':user_id' => $userId,
                ':api_token_id' => $apiTokenId,
                ':action' => $action,
                ':idempotency_key' => $idempotencyKey,
                ':parameters' => json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $runId = (int)$this->db->lastInsertId();
            $run = $this->getActionRunForUser($userId, $runId);

            return [
                'success' => true,
                'created' => true,
                'action_run' => $run ?? ['id' => $runId],
            ];
        } catch (PDOException $e) {
            if ($this->isDuplicateKeyException($e)) {
                $existing = $this->getActionRunByAccountAndIdempotency($accountId, $idempotencyKey);
                return [
                    'success' => true,
                    'created' => false,
                    'action_run' => $existing ?? [
                        'account_id' => $accountId,
                        'action' => $action,
                        'idempotency_key' => $idempotencyKey,
                        'status' => 'queued',
                    ],
                ];
            }

            log_error('AssistantConnectorService: falha ao criar action_run', [
                'service' => 'AssistantConnectorService',
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => 'db_error',
                'message' => 'Falha ao persistir action_run.',
            ];
        }
    }

    public function attachJobToActionRun(int $actionRunId, int $jobId): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE assistant_action_runs SET job_id = :job_id, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':job_id' => $jobId, ':id' => $actionRunId]);
    }

    /**
     * Consulta action_run com autorização (via userId).
     *
     * @return array<string, mixed>|null
     */
    public function getActionRunForUser(int $userId, int $actionRunId): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT ar.* '
                . 'FROM assistant_action_runs ar '
                . 'JOIN ml_accounts a ON a.id = ar.account_id '
                . 'WHERE ar.id = :id AND a.user_id = :user_id '
                . 'LIMIT 1'
        );
        $stmt->execute([':id' => $actionRunId, ':user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->decodeActionRunRow($row);
    }

    /**
     * Consulta action_run sem filtro de usuário (uso interno no worker).
     *
     * @return array<string, mixed>|null
     */
    public function getActionRunById(int $actionRunId): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM assistant_action_runs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $actionRunId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->decodeActionRunRow($row);
    }

    public function markActionRunProcessing(int $actionRunId, int $jobId): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE assistant_action_runs '
                . 'SET status = \'processing\', job_id = :job_id, attempts = attempts + 1, '
                . '    started_at = IFNULL(started_at, NOW()), updated_at = NOW() '
                . 'WHERE id = :id AND status IN (\'queued\', \'processing\')'
        );
        $stmt->execute([':job_id' => $jobId, ':id' => $actionRunId]);
    }

    public function markActionRunRetry(int $actionRunId, string $errorMessage): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE assistant_action_runs '
                . 'SET status = \'queued\', error_message = :error_message, updated_at = NOW() '
                . 'WHERE id = :id AND status IN (\'queued\', \'processing\')'
        );
        $stmt->execute([':error_message' => $errorMessage, ':id' => $actionRunId]);
    }

    /**
     * Marca como failed (DLQ). Use isso quando o job atingir max_attempts.
     */
    public function markActionRunFailed(int $actionRunId, string $errorMessage): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE assistant_action_runs '
                . 'SET status = \'failed\', error_message = :error_message, completed_at = NOW(), updated_at = NOW() '
                . 'WHERE id = :id'
        );
        $stmt->execute([':error_message' => $errorMessage, ':id' => $actionRunId]);
    }

    public function markActionRunCompleted(int $actionRunId, array $result): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE assistant_action_runs '
                . 'SET status = \'completed\', result = :result, error_message = NULL, completed_at = NOW(), updated_at = NOW() '
                . 'WHERE id = :id'
        );
        $stmt->execute([
            ':result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => $actionRunId,
        ]);
    }

    // -------------------------
    // Internals
    // -------------------------

    private function isDuplicateKeyException(PDOException $e): bool
    {
        $sqlState = $e->getCode();
        $msg = $e->getMessage();

        // MySQL duplicate key: SQLSTATE 23000 (Integrity constraint violation)
        if ((string)$sqlState === '23000') {
            return true;
        }

        return stripos($msg, 'Duplicate entry') !== false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getEventById(int $eventId): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM assistant_events WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $eventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        if (!empty($row['payload'])) {
            $decoded = json_decode((string)$row['payload'], true);
            $row['payload'] = is_array($decoded) ? $decoded : $row['payload'];
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getEventByExternalId(string $source, string $externalEventId): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM assistant_events WHERE source = :source AND external_event_id = :external_event_id LIMIT 1'
        );
        $stmt->execute([':source' => $source, ':external_event_id' => $externalEventId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        if (!empty($row['payload'])) {
            $decoded = json_decode((string)$row['payload'], true);
            $row['payload'] = is_array($decoded) ? $decoded : $row['payload'];
        }

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getActionRunByAccountAndIdempotency(int $accountId, string $idempotencyKey): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT * FROM assistant_action_runs WHERE account_id = :account_id AND idempotency_key = :idempotency_key LIMIT 1'
        );
        $stmt->execute([':account_id' => $accountId, ':idempotency_key' => $idempotencyKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $this->decodeActionRunRow($row);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function decodeActionRunRow(array $row): array
    {
        if (!empty($row['parameters'])) {
            $decoded = json_decode((string)$row['parameters'], true);
            $row['parameters'] = is_array($decoded) ? $decoded : $row['parameters'];
        } else {
            $row['parameters'] = [];
        }

        if (!empty($row['result'])) {
            $decoded = json_decode((string)$row['result'], true);
            $row['result'] = is_array($decoded) ? $decoded : $row['result'];
        } else {
            $row['result'] = null;
        }

        return $row;
    }
}
