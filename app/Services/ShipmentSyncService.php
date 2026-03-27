<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Traits\ShipmentSyncHelpers;
use PDO;
use Throwable;

class ShipmentSyncService
{
    use ShipmentSyncHelpers;
    private const DEFAULT_DAYS = 30;
    private const MAX_DAYS = 365;
    private const DEFAULT_ORDER_LIMIT = 2000;
    private const DEFAULT_SLEEP_US = 100000;
    private const MAX_LIMIT = 5000;
    private const DELAY_KEYWORDS = ['delay', 'late', 'delayed', 'shipment_delayed'];

    private ?PDO $db;
    private ?int $accountId;
    private MercadoLivreClient $client;
    private StructuredLogService $logger;

    public function __construct(
        ?int $accountId = null,
        ?MercadoLivreClient $client = null,
        ?PDO $db = null,
        ?StructuredLogService $logger = null,
        bool $skipDbAutoConnect = false
    ) {
        $this->accountId = $accountId;
        $this->client = $this->resolveClient($client, $accountId);
        $this->logger = $this->resolveLogger($logger);
        $this->db = $this->resolveDb($db, $skipDbAutoConnect);
    }

    /**
     * Sincroniza envios (shipments) a partir de pedidos recentes.
     *
     * @param int $accountId ID da conta ML
     * @param int $days Intervalo de dias para buscar pedidos no cache local
     * @param array{
     *   limit?: int,
     *   order_limit?: int,
     *   sleep_us?: int
     * } $options
     * @return array{
     *   success: bool,
     *   account_id: int,
     *   found: int,
     *   synced: int,
     *   errors: int,
     *   orders_scanned: int,
     *   error_details?: array<int, array<string, mixed>>,
     *   error?: string
     * }
     */
    public function syncForAccount(int $accountId, int $days = self::DEFAULT_DAYS, array $options = []): array
    {
        $this->ensureAccountContext($accountId);
        $days = $this->sanitizeDays($days);

        if ($this->db === null) {
            return $this->buildDbUnavailableResult($accountId);
        }

        $syncOptions = $this->normalizeSyncOptions($options);

        $mapResult = $this->fetchShipmentMapFromOrders($accountId, $days, $syncOptions['order_limit']);
        if (!$this->isSuccessResult($mapResult)) {
            return $this->buildOrdersErrorResult($accountId, $mapResult);
        }

        $shipmentMap = $this->resolveShipmentMap($mapResult);
        $shipmentIds = $this->applyShipmentLimit(array_keys($shipmentMap), $syncOptions['shipment_limit']);
        $syncResult = $this->syncShipmentList($shipmentIds, $shipmentMap, $syncOptions['sleep_us']);

        return $this->buildSyncSuccessResult($accountId, $mapResult, $shipmentIds, $syncResult);
    }

    private function resolveClient(?MercadoLivreClient $client, ?int $accountId): MercadoLivreClient
    {
        if ($client !== null) {
            return $client;
        }

        return new MercadoLivreClient($accountId);
    }

    private function resolveLogger(?StructuredLogService $logger): StructuredLogService
    {
        if ($logger !== null) {
            return $logger;
        }

        return new StructuredLogService('shipment-sync-service');
    }

    private function resolveDb(?PDO $db, bool $skipDbAutoConnect): ?PDO
    {
        if ($db !== null) {
            return $db;
        }

        if ($skipDbAutoConnect) {
            return null;
        }

        try {
            return Database::getInstance();
        } catch (Throwable $e) {
            $this->logger->warning('ShipmentSyncService: DB indisponível', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function normalizeSyncOptions(array $options): array
    {
        return [
            'shipment_limit' => isset($options['limit'])
                ? $this->sanitizeLimit((int)$options['limit'], self::MAX_LIMIT)
                : null,
            'order_limit' => isset($options['order_limit'])
                ? $this->sanitizeLimit((int)$options['order_limit'], self::MAX_LIMIT)
                : self::DEFAULT_ORDER_LIMIT,
            'sleep_us' => isset($options['sleep_us'])
                ? max(0, min(1_000_000, (int)$options['sleep_us']))
                : self::DEFAULT_SLEEP_US,
        ];
    }

    private function buildDbUnavailableResult(int $accountId): array
    {
        return [
            'success' => false, 'account_id' => $accountId, 'found' => 0, 'synced' => 0,
            'errors' => 1, 'orders_scanned' => 0, 'error' => 'db_unavailable',
        ];
    }

    private function buildOrdersErrorResult(int $accountId, array $mapResult): array
    {
        return [
            'success' => false, 'account_id' => $accountId, 'found' => 0, 'synced' => 0,
            'errors' => 1, 'orders_scanned' => 0, 'error' => $mapResult['error'] ?? 'orders_not_found',
        ];
    }

    private function isSuccessResult(array $result): bool
    {
        return isset($result['success']) && $result['success'] === true;
    }

    private function resolveShipmentMap(array $mapResult): array
    {
        $value = $this->getArrayValueOrNull($mapResult, 'shipments');
        return $value ?? [];
    }

    private function getOrdersScanned(array $mapResult): int
    {
        $value = $this->getNumericValue($mapResult, 'orders_scanned');
        return $value ?? 0;
    }

    private function buildSyncSuccessResult(int $accountId, array $mapResult, array $shipmentIds, array $syncResult): array
    {
        return [
            'success' => true, 'account_id' => $accountId, 'found' => count($shipmentIds),
            'synced' => $syncResult['synced'], 'errors' => $syncResult['errors'],
            'orders_scanned' => $this->getOrdersScanned($mapResult), 'error_details' => $syncResult['error_details'],
        ];
    }

    private function applyShipmentLimit(array $shipmentIds, ?int $limit): array
    {
        if ($limit === null) {
            return $shipmentIds;
        }

        return array_slice($shipmentIds, 0, $limit);
    }

    private function syncShipmentList(array $shipmentIds, array $shipmentMap, int $sleepUs): array
    {
        $synced = 0;
        $errors = 0;
        $errorDetails = [];

        foreach ($shipmentIds as $shipmentId) {
            $context = $this->getShipmentContext($shipmentMap, $shipmentId);
            $result = $this->syncSingleShipment($shipmentId, $context);
            if ($this->isSuccessResult($result)) {
                $synced++;
            } else {
                $errors++;
                $this->appendErrorDetail($errorDetails, $shipmentId, $result);
            }

            $this->sleepBetween($sleepUs);
        }

        return ['synced' => $synced, 'errors' => $errors, 'error_details' => $errorDetails];
    }

    private function getShipmentContext(array $shipmentMap, string $shipmentId): array
    {
        $context = $this->getArrayValueOrNull($shipmentMap, $shipmentId);
        return $context ?? [];
    }

    private function appendErrorDetail(array &$errorDetails, string $shipmentId, array $result): void
    {
        if (count($errorDetails) >= 10) {
            return;
        }

        $errorDetails[] = $this->buildErrorDetail($shipmentId, $result);
    }

    private function buildErrorDetail(string $shipmentId, array $result): array
    {
        $error = $this->getStringValue($result, 'error') ?? 'unknown';
        $detail = [
            'shipment_id' => $shipmentId,
            'error' => $error,
        ];

        $message = $this->getStringValue($result, 'message');
        if ($message !== null) {
            $detail['message'] = $message;
        }

        return $detail;
    }

    private function sleepBetween(int $sleepUs): void
    {
        if ($sleepUs > 0) {
            usleep($sleepUs);
        }
    }

    private function syncSingleShipment(string $shipmentId, array $context): array
    {
        try {
            $response = $this->unwrapMlResponse($this->client->get("/shipments/{$shipmentId}"));

            if (isset($response['error'])) {
                return [
                    'success' => false,
                    'error' => is_string($response['error']) ? $response['error'] : 'ml_api_error',
                    'message' => $response['message'] ?? null,
                ];
            }

            $payload = $this->buildShipmentPayload($shipmentId, $response, $context);

            if ($this->upsertShipment($payload)) {
                return ['success' => true];
            }

            return ['success' => false, 'error' => 'db_insert_failed'];
        } catch (Throwable $e) {
            $this->logger->warning('ShipmentSyncService: falha ao sincronizar envio', [
                'shipment_id' => $shipmentId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'exception', 'message' => $e->getMessage()];
        }
    }

    private function ensureAccountContext(int $accountId): void
    {
        if ($this->accountId !== $accountId) {
            $this->accountId = $accountId;
            $this->client = new MercadoLivreClient($accountId);
        }
    }

    private function fetchShipmentMapFromOrders(int $accountId, int $days, int $orderLimit): array
    {
        if ($this->db === null) {
            return ['success' => false, 'orders_scanned' => 0, 'shipments' => [], 'error' => 'db_unavailable'];
        }

        try {
            $rows = $this->queryRecentOrders($accountId, $days, $orderLimit);
        } catch (Throwable $e) {
            $this->logger->warning('ShipmentSyncService: falha ao buscar pedidos locais', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'orders_scanned' => 0, 'shipments' => [], 'error' => 'orders_query_failed'];
        }

        $shipments = [];
        $ordersScanned = 0;

        foreach ($rows as $row) {
            $ordersScanned++;
            $context = $this->extractShipmentContext($row);
            if ($context === null) {
                continue;
            }

            $shipmentId = $context['shipment_id'];
            if (isset($shipments[$shipmentId])) {
                continue;
            }

            $shipments[$shipmentId] = [
                'order_id' => $context['order_id'],
                'order_date' => $context['order_date'],
            ];
        }

        return ['success' => true, 'orders_scanned' => $ordersScanned, 'shipments' => $shipments];
    }

    private function queryRecentOrders(int $accountId, int $days, int $orderLimit): array
    {
        if ($this->db === null) {
            return [];
        }

        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $limit = $orderLimit > 0 ? $orderLimit : self::DEFAULT_ORDER_LIMIT;

        $sql = 'SELECT ml_order_id, order_data, date_created FROM ml_orders WHERE ml_account_id = :account_id AND date_created >= :since ORDER BY date_created DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'account_id' => $accountId,
            'since' => $since,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    private function extractShipmentContext(array $row): ?array
    {
        $orderData = $this->decodeJsonArray($this->getStringValue($row, 'order_data'));
        if ($orderData === null) {
            return null;
        }

        $shipmentId = $this->resolveShippingId($orderData);
        if ($shipmentId === null) {
            return null;
        }

        return [
            'shipment_id' => $shipmentId,
            'order_id' => $this->resolveOrderId($orderData, $row),
            'order_date' => $this->resolveOrderDate($row),
        ];
    }

    private function decodeJsonArray(?string $payload): ?array
    {
        if ($payload === null || $payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function resolveShippingId(array $orderData): ?string
    {
        return $this->firstNonEmptyString([
            $this->getNestedStringValue($orderData, ['shipping', 'id']),
            $this->getNestedStringValue($orderData, ['shipping_id']),
        ]);
    }

    private function resolveOrderId(array $orderData, array $row): ?string
    {
        return $this->firstNonEmptyString([
            $this->getNestedStringValue($orderData, ['id']),
            $this->getStringValue($row, 'ml_order_id'),
        ]);
    }

    private function resolveOrderDate(array $row): ?string
    {
        return $this->getStringValue($row, 'date_created');
    }

    private function buildShipmentPayload(string $shipmentId, array $shipment, array $context): array
    {
        $orderId = $this->firstNonEmptyString([
            $this->getStringValue($shipment, 'order_id'),
            $this->getStringValue($context, 'order_id'),
        ]);
        $status = $this->getStringValue($shipment, 'status');
        $dates = $this->extractShipmentDates($shipment, $context);

        return [
            'account_id' => $this->accountId, 'shipment_id' => $shipmentId, 'order_id' => $orderId, 'status' => $status,
            'tracking_number' => $this->extractTrackingNumber($shipment), 'carrier' => $this->extractCarrier($shipment),
            'is_delayed' => $this->isShipmentDelayed($shipment) ? 1 : 0, 'created_at' => $dates['created_at'],
            'shipped_at' => $dates['shipped_at'], 'delivered_at' => $dates['delivered_at'],
            'data' => $this->encodeShipmentData($shipment),
        ];
    }

    private function extractShipmentDates(array $shipment, array $context): array
    {
        $createdAt = $this->firstNonEmptyString([
            $this->getNestedStringValue($shipment, ['date_created']),
            $this->getStringValue($context, 'order_date'),
        ]);
        $shippedAt = $this->firstNonEmptyString([
            $this->getNestedStringValue($shipment, ['date_shipped']),
            $this->getNestedStringValue($shipment, ['status_history', 'date_shipped']),
        ]);
        $deliveredAt = $this->firstNonEmptyString([
            $this->getNestedStringValue($shipment, ['date_delivered']),
            $this->getNestedStringValue($shipment, ['status_history', 'date_delivered']),
        ]);

        return [
            'created_at' => $this->toDbDate($createdAt), 'shipped_at' => $this->toDbDate($shippedAt),
            'delivered_at' => $this->toDbDate($deliveredAt),
        ];
    }

    private function upsertShipment(array $payload): bool
    {
        if ($this->db === null || $this->accountId === null) {
            return false;
        }

        $stmt = $this->db->prepare($this->getUpsertSql());

        return $stmt->execute([
            'account_id' => $payload['account_id'],
            'shipment_id' => $payload['shipment_id'],
            'order_id' => $payload['order_id'],
            'status' => $payload['status'],
            'tracking_number' => $payload['tracking_number'],
            'carrier' => $payload['carrier'],
            'is_delayed' => $payload['is_delayed'],
            'created_at' => $payload['created_at'],
            'shipped_at' => $payload['shipped_at'],
            'delivered_at' => $payload['delivered_at'],
            'data' => $payload['data'],
        ]);
    }

    private function getUpsertSql(): string
    {
        return 'INSERT INTO shipments (account_id, shipment_id, order_id, status, tracking_number, carrier, is_delayed, created_at, shipped_at, delivered_at, data) '
            . 'VALUES (:account_id, :shipment_id, :order_id, :status, :tracking_number, :carrier, :is_delayed, :created_at, :shipped_at, :delivered_at, :data) '
            . 'ON DUPLICATE KEY UPDATE order_id = VALUES(order_id), status = VALUES(status), tracking_number = VALUES(tracking_number), carrier = VALUES(carrier), '
            . 'is_delayed = VALUES(is_delayed), created_at = COALESCE(VALUES(created_at), created_at), shipped_at = COALESCE(VALUES(shipped_at), shipped_at), '
            . 'delivered_at = COALESCE(VALUES(delivered_at), delivered_at), data = VALUES(data)';
    }

    private function unwrapMlResponse(array $response): array
    {
        if (isset($response['error'])) {
            return $response;
        }

        if (isset($response['body']) && is_array($response['body'])) {
            return $response['body'];
        }

        return $response;
    }

    private function toDbDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function extractTrackingNumber(array $shipment): ?string
    {
        return $this->firstNonEmptyString([
            $this->getNestedStringValue($shipment, ['tracking_number']),
            $this->getNestedStringValue($shipment, ['tracking', 'number']),
            $this->getNestedStringValue($shipment, ['tracking', 'tracking_number']),
        ]);
    }

    private function extractCarrier(array $shipment): ?string
    {
        return $this->firstNonEmptyString([
            $this->getNestedStringValue($shipment, ['carrier', 'name']),
            $this->getNestedStringValue($shipment, ['carrier_info', 'carrier_name']),
            $this->getNestedStringValue($shipment, ['carrier_info', 'name']),
            $this->getNestedStringValue($shipment, ['shipping_option', 'name']),
            $this->getNestedStringValue($shipment, ['tracking_method']),
        ]);
    }
}
