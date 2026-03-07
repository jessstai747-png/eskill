<?php

declare(strict_types=1);

namespace App\Services\Marketplace\Shopee;

use App\Services\Marketplace\MarketplaceInterface;
use App\Services\LoggingService;
use App\Database;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ShopeeAdapter implements MarketplaceInterface
{
    private const API_ENDPOINT = 'https://partner.shopeemobile.com';

    private $accountId;
    private $credentials;
    private $httpClient;
    private $logger;
    private $db;

    public function __construct(?int $accountId)
    {
        $this->accountId = $accountId;
        $this->logger = new LoggingService();
        $this->db = Database::getInstance();

        // In a real scenario, fetch credentials securely from the database
        $this->credentials = [
            'partner_id' => (int)($_ENV['SHOPEE_PARTNER_ID'] ?? null),
            'partner_key' => $_ENV['SHOPEE_PARTNER_KEY'] ?? null,
            'shop_id' => (int)($_ENV['SHOPEE_SHOP_ID'] ?? null), // This should be fetched per account
        ];

        $this->httpClient = new Client([
            'base_uri' => self::API_ENDPOINT,
            'timeout'  => 30.0,
        ]);
    }

    /**
     * Generates the required authentication signature for Shopee API requests.
     */
    private function generateSignature(string $path, int $timestamp): string
    {
        $baseString = sprintf("%d%s%d", $this->credentials['partner_id'], $path, $timestamp);
        return hash_hmac('sha256', $baseString, $this->credentials['partner_key']);
    }

    private function makeApiRequest(string $method, string $path, array $options = []): array
    {
        try {
            $timestamp = time();
            $signature = $this->generateSignature($path, $timestamp);
            
            $defaultOptions = [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'query' => [
                    'partner_id' => $this->credentials['partner_id'],
                    'timestamp' => $timestamp,
                    'sign' => $signature,
                    'shop_id' => $this->credentials['shop_id']
                ]
            ];

            $response = $this->httpClient->request($method, '/api/v2' . $path, array_merge_recursive($defaultOptions, $options));
            
            $result = json_decode($response->getBody()->getContents(), true);

            if (!empty($result['error'])) {
                throw new Exception("Shopee API Error: {$result['error']} - {$result['message']}");
            }

            return $result['response'] ?? $result;

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('SHOPEE_API_ERROR', "Shopee API request failed", [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            throw new Exception("Shopee API Error: " . $e->getMessage());
        }
    }

    public function getOrders(array $params = []): array
    {
        $this->logger->info('SHOPEE_GET_ORDERS', 'Fetching orders from Shopee', ['params' => $params]);
        
        $queryParams = [
            'time_range_field' => 'create_time',
            'time_from' => strtotime($params['createdAfter'] ?? '-7 days'),
            'time_to' => time(),
            'page_size' => $params['limit'] ?? 50,
            'cursor' => $params['cursor'] ?? ''
        ];
        
        $response = $this->makeApiRequest('GET', '/order/get_order_list', [
            'query' => $queryParams
        ]);

        return $response['order_list'] ?? [];
    }

    public function getOrder(string $orderId): array
    {
        $this->logger->info('SHOPEE_GET_ORDER', 'Fetching single order from Shopee', ['orderId' => $orderId]);
        
        $response = $this->makeApiRequest('GET', '/order/get_order_detail', [
            'query' => [
                'order_sn_list' => $orderId
            ]
        ]);
        
        return $response['order_list'][0] ?? [];
    }

    public function updateStock(string $sku, int $quantity): bool
    {
        $this->logger->info('SHOPEE_UPDATE_STOCK', 'Updating stock on Shopee', ['sku' => $sku, 'qty' => $quantity]);
        $columnsStmt = $this->db->query("SHOW COLUMNS FROM shopee_items");
        $columns = $columnsStmt ? $columnsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $itemColumn = in_array('shopee_item_id', $columns, true) ? 'shopee_item_id' : 'item_id';

        $stmt = $this->db->prepare("SELECT {$itemColumn} as item_id FROM shopee_items WHERE sku = ? LIMIT 1");
        $stmt->execute([$sku]);
        $itemId = $stmt->fetchColumn();
        if (!$itemId) {
            throw new Exception("SKU não encontrado na Shopee: {$sku}");
        }

        $modelId = 0;
        $models = $this->makeApiRequest('GET', '/product/get_model_list', [
            'query' => [
                'item_id' => (int)$itemId
            ]
        ]);

        if (!empty($models['model'])) {
            $modelId = (int)$models['model'][0]['model_id'];
        }

        $body = [
            'item_id' => (int)$itemId,
            'stock_list' => [
                [
                    'model_id' => $modelId,
                    'seller_stock' => $quantity,
                ],
            ],
        ];

        $this->makeApiRequest('POST', '/product/update_stock', ['json' => $body]);

        return true;
    }

    public function getListing(string $listingId): array
    {
        $this->logger->info('SHOPEE_GET_LISTING', 'Fetching listing from Shopee', ['item_id' => $listingId]);
        
        $response = $this->makeApiRequest('GET', '/product/get_item_base_info', [
            'query' => ['item_id_list' => $listingId]
        ]);
        
        return $response['item_list'][0] ?? [];
    }

    public function getPlatformName(): string
    {
        return 'shopee';
    }
}
