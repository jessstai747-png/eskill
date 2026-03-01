<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço oficial de checkpoint/status de sincronização por conta e recurso.
 *
 * Observação: não cria schema em runtime. A tabela `sync_status` deve existir via migration.
 */
class SyncStatusService
{
    public const RESOURCE_ORDERS = 'orders';
    public const RESOURCE_ITEMS = 'items';
    public const RESOURCE_QUESTIONS = 'questions';
    public const RESOURCE_MESSAGES = 'messages';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public function getStatus(string $resourceType, int $accountId): ?array
    {
        $resourceType = $this->normalizeResourceType($resourceType);
        $stmt = $this->db->prepare(
            "SELECT resource_type, account_id, last_sync_at, status, last_sync_id, items_count, error_message, created_at, updated_at
             FROM sync_status
             WHERE resource_type = :resource_type
               AND account_id = :account_id
             LIMIT 1"
        );
        $stmt->execute([
            ':resource_type' => $resourceType,
            ':account_id' => $accountId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'resource_type' => (string)$row['resource_type'],
            'account_id' => (int)$row['account_id'],
            'last_sync_at' => $row['last_sync_at'] !== null ? (string)$row['last_sync_at'] : null,
            'status' => (string)$row['status'],
            'last_sync_id' => $row['last_sync_id'] !== null ? (string)$row['last_sync_id'] : null,
            'items_count' => $row['items_count'] !== null ? (int)$row['items_count'] : null,
            'error_message' => $row['error_message'] !== null ? (string)$row['error_message'] : null,
            'created_at' => $row['created_at'] !== null ? (string)$row['created_at'] : null,
            'updated_at' => $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
        ];
    }

    public function markRunning(string $resourceType, int $accountId, ?string $lastSyncId = null): void
    {
        $this->upsertStatus(
            $resourceType,
            $accountId,
            'running',
            null,
            null,
            $lastSyncId,
            false
        );
    }

    public function markSuccess(
        string $resourceType,
        int $accountId,
        ?int $itemsCount = null,
        ?string $lastSyncId = null,
        ?string $lastSyncAt = null
    ): void {
        $this->upsertStatus(
            $resourceType,
            $accountId,
            'success',
            $itemsCount,
            null,
            $lastSyncId,
            true,
            $lastSyncAt
        );
    }

    public function markError(string $resourceType, int $accountId, string $errorMessage): void
    {
        $this->upsertStatus(
            $resourceType,
            $accountId,
            'error',
            null,
            mb_substr($errorMessage, 0, 1000)
        );
    }

    private function upsertStatus(
        string $resourceType,
        int $accountId,
        string $status,
        ?int $itemsCount = null,
        ?string $errorMessage = null,
        ?string $lastSyncId = null,
        bool $touchLastSyncAt = false,
        ?string $lastSyncAtOverride = null
    ): void {
        $resourceType = $this->normalizeResourceType($resourceType);
        $lastSyncId = $lastSyncId !== null ? mb_substr($lastSyncId, 0, 100) : null;
        $status = $this->normalizeStatus($status);
        $lastSyncAt = $touchLastSyncAt
            ? ($lastSyncAtOverride ?: date('Y-m-d H:i:s'))
            : null;

        $stmt = $this->db->prepare(
            "INSERT INTO sync_status (
                resource_type, account_id, last_sync_at, status, last_sync_id, items_count, error_message, created_at, updated_at
             ) VALUES (
                :resource_type, :account_id, :last_sync_at, :status, :last_sync_id, :items_count, :error_message, NOW(), NOW()
             )
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                error_message = VALUES(error_message),
                items_count = COALESCE(VALUES(items_count), sync_status.items_count),
                last_sync_id = COALESCE(VALUES(last_sync_id), sync_status.last_sync_id),
                last_sync_at = CASE
                    WHEN VALUES(last_sync_at) IS NOT NULL THEN VALUES(last_sync_at)
                    ELSE sync_status.last_sync_at
                END,
                updated_at = NOW()"
        );

        $stmt->execute([
            ':resource_type' => $resourceType,
            ':account_id' => $accountId,
            ':last_sync_at' => $lastSyncAt,
            ':status' => $status,
            ':last_sync_id' => $lastSyncId,
            ':items_count' => $itemsCount,
            ':error_message' => $errorMessage,
        ]);
    }

    private function normalizeResourceType(string $resourceType): string
    {
        $resourceType = trim(strtolower($resourceType));
        if ($resourceType === '') {
            throw new \InvalidArgumentException('resource_type é obrigatório');
        }

        return $resourceType;
    }

    private function normalizeStatus(string $status): string
    {
        $status = trim(strtolower($status));
        if (!in_array($status, ['success', 'error', 'running'], true)) {
            throw new \InvalidArgumentException('status inválido para sync_status');
        }

        return $status;
    }
}
