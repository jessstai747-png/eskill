<?php

declare(strict_types=1);

namespace App\Services\Integrations\Brevo;

use App\Services\AI\Core\RetryService;
use App\Services\LoggingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

class BrevoClient
{
    private Client $http;
    private RetryService $retry;
    private LoggingService $logger;
    private string $baseUrl;
    private string $apiKey;
    private int $timeoutSeconds;

    public function __construct(?Client $http = null, ?RetryService $retry = null, ?LoggingService $logger = null)
    {
        $this->baseUrl = rtrim($_ENV['BREVO_BASE_URL'] ?? 'https://api.brevo.com/v3', '/');
        $this->apiKey = (string)($_ENV['BREVO_API_KEY'] ?? '');
        $this->timeoutSeconds = (int)($_ENV['BREVO_TIMEOUT_SECONDS'] ?? 10);

        $this->http = $http ?? new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => $this->timeoutSeconds,
            'connect_timeout' => min(5, $this->timeoutSeconds),
            'http_errors' => false,
        ]);

        $this->retry = $retry ?? new RetryService();
        $this->logger = $logger ?? new LoggingService();
    }

    public function get(string $path, array $query = [], ?string $operationName = null): array
    {
        return $this->request('GET', $path, ['query' => $query], $operationName);
    }

    public function post(string $path, array $payload = [], ?string $operationName = null): array
    {
        return $this->request('POST', $path, ['json' => $payload], $operationName);
    }

    public function put(string $path, array $payload = [], ?string $operationName = null): array
    {
        return $this->request('PUT', $path, ['json' => $payload], $operationName);
    }

    public function delete(string $path, array $query = [], ?string $operationName = null): array
    {
        return $this->request('DELETE', $path, ['query' => $query], $operationName);
    }

    public function health(): array
    {
        return $this->get('account', [], 'brevo.health');
    }

    private function request(string $method, string $path, array $options, ?string $operationName): array
    {
        if ($this->apiKey === '') {
            throw new BrevoApiException('BREVO_API_KEY não configurada', 500, ['missing_env' => 'BREVO_API_KEY']);
        }

        $normalizedPath = ltrim($path, '/');
        $op = $operationName ?? $this->defaultOperationName($method, $normalizedPath);

        $headers = [
            'Accept' => 'application/json',
            'api-key' => $this->apiKey,
        ];

        $requestOptions = array_merge($options, [
            'headers' => $headers,
        ]);

        $startedAt = microtime(true);

        try {
            $result = $this->retry->execute(function () use ($method, $normalizedPath, $requestOptions, $op) {
                $response = $this->http->request($method, $normalizedPath, $requestOptions);
                return $this->handleResponse($response, $op);
            }, $op);

            $this->logger->info(LoggingService::CATEGORY_MONITORING, 'Brevo request ok', [
                'integration' => 'brevo',
                'operation' => $op,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);

            return $result;
        } catch (BrevoApiException $e) {
            $this->logger->error(LoggingService::CATEGORY_MONITORING, 'Brevo request failed', [
                'integration' => 'brevo',
                'operation' => $op,
                'status' => $e->getStatusCode(),
                'details' => $e->getDetails(),
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
            throw $e;
        } catch (RequestException $e) {
            $this->logger->error(LoggingService::CATEGORY_MONITORING, 'Brevo connection error', [
                'integration' => 'brevo',
                'operation' => $op,
                'message' => $e->getMessage(),
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
            throw new BrevoApiException('Falha de conexão com a Brevo', 503, ['error' => $e->getMessage()], $e);
        } catch (\Throwable $e) {
            $this->logger->error(LoggingService::CATEGORY_MONITORING, 'Brevo unexpected error', [
                'integration' => 'brevo',
                'operation' => $op,
                'message' => $e->getMessage(),
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ]);
            throw new BrevoApiException('Erro inesperado na integração Brevo', 500, [], $e);
        }
    }

    private function handleResponse(ResponseInterface $response, string $operationName): array
    {
        $status = $response->getStatusCode();
        $body = (string)$response->getBody();
        $headers = $response->getHeaders();

        $parsed = $this->parseBody($body, $headers);

        if ($status >= 200 && $status <= 299) {
            return [
                'success' => true,
                'status' => $status,
                'data' => $parsed,
                'raw' => $body,
                'headers' => $this->normalizeHeaders($headers),
            ];
        }

        $message = $this->extractErrorMessage($parsed, $body) ?: "Brevo HTTP {$status}";

        throw new BrevoApiException($message, $status, [
            'operation' => $operationName,
            'status' => $status,
            'error' => $parsed,
        ]);
    }

    private function parseBody(string $body, array $headers): array
    {
        $contentType = strtolower((string)($headers['Content-Type'][0] ?? ''));
        $trimmed = trim($body);

        if ($trimmed === '') {
            return [];
        }

        $looksLikeXml = str_contains($contentType, 'xml') || str_starts_with($trimmed, '<');
        if ($looksLikeXml) {
            return $this->parseXml($trimmed);
        }

        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        return ['raw' => $body];
    }

    private function parseXml(string $xml): array
    {
        libxml_use_internal_errors(true);
        $element = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($element === false) {
            $errors = array_map(fn($e) => trim($e->message), libxml_get_errors());
            libxml_clear_errors();
            return ['raw' => $xml, 'xml_errors' => $errors];
        }

        $json = json_encode($element);
        $array = json_decode($json, true);
        return is_array($array) ? $array : ['raw' => $xml];
    }

    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $values) {
            $out[strtolower($name)] = array_values($values);
        }
        return $out;
    }

    private function extractErrorMessage(array $parsed, string $raw): ?string
    {
        if (isset($parsed['message']) && is_string($parsed['message'])) {
            return $parsed['message'];
        }
        if (isset($parsed['error']) && is_string($parsed['error'])) {
            return $parsed['error'];
        }
        if (isset($parsed['errors']) && is_array($parsed['errors']) && isset($parsed['errors'][0]['message'])) {
            return (string)$parsed['errors'][0]['message'];
        }
        if (isset($parsed['code']) && isset($parsed['message'])) {
            return (string)$parsed['message'];
        }

        $rawTrimmed = trim($raw);
        return $rawTrimmed !== '' ? mb_substr($rawTrimmed, 0, 200) : null;
    }

    private function defaultOperationName(string $method, string $path): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $path) ?? $path);
        $normalized = trim($normalized, '.');
        return 'brevo.' . strtolower($method) . '.' . ($normalized ?: 'root');
    }
}

