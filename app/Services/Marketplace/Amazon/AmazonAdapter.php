<?php

namespace App\Services\Marketplace\Amazon;

use App\Services\Marketplace\MarketplaceInterface;
use App\Services\LoggingService;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class AmazonAdapter implements MarketplaceInterface
{
    private const SP_API_ENDPOINT = 'https://sellingpartnerapi-na.amazon.com'; // North America endpoint

    private $accountId;
    private $credentials;
    private $httpClient;
    private $logger;
    private ?string $accessToken = null;
    private ?int $accessTokenExpiresAt = null;

    public function __construct(?int $accountId)
    {
        $this->accountId = $accountId;
        $this->logger = new LoggingService();

        $this->credentials = [
            'client_id' => $_ENV['AMAZON_CLIENT_ID'] ?? null,
            'client_secret' => $_ENV['AMAZON_CLIENT_SECRET'] ?? null,
            'refresh_token' => $_ENV['AMAZON_REFRESH_TOKEN'] ?? null, // This should be fetched per account
            'seller_id' => $_ENV['AMAZON_SELLER_ID'] ?? null,
            'aws_access_key' => $_ENV['AMAZON_AWS_ACCESS_KEY'] ?? null,
            'aws_secret_key' => $_ENV['AMAZON_AWS_SECRET_KEY'] ?? null,
            'aws_region' => $_ENV['AMAZON_AWS_REGION'] ?? 'us-east-1',
            'marketplace_id' => $_ENV['AMAZON_MARKETPLACE_ID'] ?? null,
        ];

        $this->httpClient = new Client([
            'base_uri' => self::SP_API_ENDPOINT,
            'timeout'  => 30.0,
        ]);
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->accessTokenExpiresAt && $this->accessTokenExpiresAt > time() + 60) {
            return $this->accessToken;
        }

        if (empty($this->credentials['refresh_token']) || empty($this->credentials['client_id']) || empty($this->credentials['client_secret'])) {
            throw new Exception("Amazon credentials are not configured for this account.");
        }

        $client = new Client(['base_uri' => 'https://api.amazon.com', 'timeout' => 30.0]);
        $response = $client->post('/auth/o2/token', [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->credentials['refresh_token'],
                'client_id' => $this->credentials['client_id'],
                'client_secret' => $this->credentials['client_secret'],
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        if (empty($data['access_token'])) {
            throw new Exception('Amazon LWA token response invalid.');
        }

        $this->accessToken = $data['access_token'];
        $expiresIn = (int)($data['expires_in'] ?? 3600);
        $this->accessTokenExpiresAt = time() + $expiresIn;

        return $this->accessToken;
    }

    private function makeApiRequest(string $method, string $path, array $options = []): array
    {
        try {
            $accessToken = $this->getAccessToken();

            $defaultOptions = [
                'headers' => [
                    'x-amz-access-token' => $accessToken,
                    'Content-Type' => 'application/json',
                ],
            ];
            $requestOptions = array_merge_recursive($defaultOptions, $options);

            $signedHeaders = $this->signRequest($method, $path, $requestOptions);
            $requestOptions['headers'] = array_merge($requestOptions['headers'] ?? [], $signedHeaders);

            $response = $this->httpClient->request($method, $path, $requestOptions);
            
            return json_decode($response->getBody()->getContents(), true);

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response body';
            $this->logger->error('AMAZON_API_ERROR', "Amazon API request failed", [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
                'response' => $responseBody
            ]);
            throw new Exception("Amazon API Error: " . $e->getMessage());
        }
    }

    private function signRequest(string $method, string $path, array $options): array
    {
        if (empty($this->credentials['aws_access_key']) || empty($this->credentials['aws_secret_key'])) {
            throw new Exception('Amazon AWS credentials are not configured.');
        }

        $accessKey = $this->credentials['aws_access_key'];
        $secretKey = $this->credentials['aws_secret_key'];
        $region = $this->credentials['aws_region'] ?? 'us-east-1';
        $service = 'execute-api';

        $amzdate = gmdate('Ymd\THis\Z');
        $datestamp = gmdate('Ymd');
        $host = parse_url(self::SP_API_ENDPOINT, PHP_URL_HOST);

        $query = $options['query'] ?? [];
        ksort($query);
        $encodedQuery = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $encodedQuery[] = rawurlencode($key) . '=' . rawurlencode((string)$item);
                }
                continue;
            }
            $encodedQuery[] = rawurlencode($key) . '=' . rawurlencode((string)$value);
        }
        $canonicalQueryString = implode('&', $encodedQuery);

        $payload = '';
        if (isset($options['body'])) {
            $payload = (string)$options['body'];
        } elseif (isset($options['json'])) {
            $payload = json_encode($options['json']);
        }
        $payloadHash = hash('sha256', $payload);

        $canonicalHeaders = "host:{$host}\n" . "x-amz-date:{$amzdate}\n";
        $signedHeaders = 'host;x-amz-date';

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $path,
            $canonicalQueryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash
        ]);

        $credentialScope = "{$datestamp}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzdate,
            $credentialScope,
            hash('sha256', $canonicalRequest)
        ]);

        $signingKey = $this->getSignatureKey($secretKey, $datestamp, $region, $service);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        return [
            'Authorization' => $authorizationHeader,
            'x-amz-date' => $amzdate,
            'host' => $host,
        ];
    }

    private function getSignatureKey(string $secretKey, string $dateStamp, string $regionName, string $serviceName): string
    {
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    public function getOrders(array $params = []): array
    {
        $this->logger->info('AMAZON_GET_ORDERS', 'Fetching orders from Amazon', ['params' => $params]);
        
        $marketplaceId = $this->credentials['marketplace_id'] ?? null;
        if (!$marketplaceId) {
            throw new Exception('Amazon Marketplace ID is not configured.');
        }

        $queryParams = [
            'MarketplaceIds' => [$marketplaceId],
        ];

        if (!empty($params['createdAfter'])) {
            $queryParams['CreatedAfter'] = $params['createdAfter'];
        } else {
            $queryParams['CreatedAfter'] = date('Y-m-d\TH:i:s\Z', strtotime('-7 days'));
        }

        $response = $this->makeApiRequest('GET', '/orders/v0/orders', [
            'query' => $queryParams
        ]);

        return $response['payload']['Orders'] ?? [];
    }

    public function getOrder(string $orderId): array
    {
        $this->logger->info('AMAZON_GET_ORDER', 'Fetching single order from Amazon', ['orderId' => $orderId]);

        $response = $this->makeApiRequest('GET', "/orders/v0/orders/{$orderId}");

        return $response['payload'] ?? [];
    }

    public function updateStock(string $sku, int $quantity): bool
    {
        $this->logger->info('AMAZON_UPDATE_STOCK', 'Updating stock on Amazon', ['sku' => $sku, 'qty' => $quantity]);
        
        $sellerId = $this->credentials['seller_id'];
        $marketplaceId = $_ENV['AMAZON_MARKETPLACE_ID'] ?? 'ATVPDKIKX0DER';
        
        $path = "/listings/2021-08-01/items/{$sellerId}/{$sku}";

        $body = [
            "productType" => "STORE_PRODUCT",
            "patches" => [
                [
                    "op" => "replace",
                    "path" => "/attributes/fulfillment_availability",
                    "value" => [
                        [
                            "fulfillment_channel_code" => "DEFAULT",
                            "quantity" => $quantity
                        ]
                    ]
                ]
            ]
        ];

        $this->makeApiRequest('PATCH', $path, [
            'query' => ['marketplaceIds' => $marketplaceId],
            'json' => $body
        ]);

        return true; // If no exception is thrown, assume success
    }

    public function getListing(string $listingId): array
    {
        // The SP-API uses SKU for listings, not a separate listingId like Mercado Livre
        $this->logger->info('AMAZON_GET_LISTING', 'Fetching listing from Amazon', ['sku' => $listingId]);
        
        $sellerId = $this->credentials['seller_id'];
        $marketplaceId = $_ENV['AMAZON_MARKETPLACE_ID'] ?? 'ATVPDKIKX0DER';
        
        $path = "/listings/2021-08-01/items/{$sellerId}/{$listingId}";
        
        $response = $this->makeApiRequest('GET', $path, [
            'query' => ['marketplaceIds' => $marketplaceId]
        ]);

        return $response['attributes'] ?? [];
    }

    public function getPlatformName(): string
    {
        return 'amazon';
    }
}
