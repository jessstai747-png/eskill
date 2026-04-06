<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * MercadoLivreProxyService
 *
 * Encapsula chamadas HTTP à API do Mercado Livre roteando-as via
 * Cloudflare Worker (ML_CF_PROXY_ENABLED=true) para contornar bloqueio de IP.
 *
 * Variáveis de ambiente:
 *   ML_CF_PROXY_ENABLED  — true|false (default: false)
 *   ML_CF_PROXY_URL      — URL do Cloudflare Worker
 *   ML_CF_PROXY_SECRET   — Secret configurado no Worker (X-Proxy-Secret)
 *
 * Uso direto (standalone):
 *   $proxy  = new MercadoLivreProxyService();
 *   $result = $proxy->testConnection();
 *
 * O MercadoLivreClient usa este serviço internamente quando proxy está ativo.
 */
class MercadoLivreProxyService
{
    private const ML_API_BASE     = 'https://api.mercadolibre.com';
    private const TIMEOUT         = 30;
    private const CONNECT_TIMEOUT = 10;

    private bool   $proxyEnabled;
    private string $proxyUrl;
    private string $proxySecret;

    private readonly Client $http;

    public function __construct(?Client $httpClient = null)
    {
        $this->proxyEnabled = filter_var(
            $_ENV['ML_CF_PROXY_ENABLED'] ?? getenv('ML_CF_PROXY_ENABLED') ?? 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $this->proxyUrl    = rtrim((string) ($_ENV['ML_CF_PROXY_URL']    ?? getenv('ML_CF_PROXY_URL')    ?? ''), '/');
        $this->proxySecret = (string) ($_ENV['ML_CF_PROXY_SECRET'] ?? getenv('ML_CF_PROXY_SECRET') ?? '');

        $this->http = $httpClient ?? new Client([
            'timeout'         => self::TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'headers'         => [
                'Accept'     => 'application/json',
                'User-Agent' => 'eskill-ml-proxy/1.0',
            ],
        ]);
    }

    /**
     * GET na API do ML — via proxy ou direto conforme configuração.
     *
     * @param  string      $path        Ex: /sites/MLB/search?BRAND=7297804&limit=50
     * @param  string|null $accessToken Bearer token OAuth (Authorization: Bearer …)
     * @return array<string,mixed>
     */
    public function get(string $path, ?string $accessToken = null): array
    {
        return $this->request('GET', $path, $accessToken);
    }

    /**
     * POST na API do ML.
     *
     * @param  array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    public function post(string $path, ?array $body = null, ?string $accessToken = null): array
    {
        return $this->request('POST', $path, $accessToken, $body);
    }

    /**
     * PUT na API do ML.
     *
     * @param  array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    public function put(string $path, ?array $body = null, ?string $accessToken = null): array
    {
        return $this->request('PUT', $path, $accessToken, $body);
    }

    /**
     * Testa a conexão com a API ML.
     * Retorna métricas de diagnóstico.
     *
     * @return array{success:bool, via_proxy:bool, response_ms:int, site_id?:string, site_name?:string, error?:string}
     */
    public function testConnection(): array
    {
        $start = microtime(true);

        try {
            $result = $this->get('/sites/MLB');
            $ms     = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'     => true,
                'via_proxy'   => $this->proxyEnabled,
                'response_ms' => $ms,
                'site_id'     => (string) ($result['id'] ?? ''),
                'site_name'   => (string) ($result['name'] ?? ''),
            ];
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $start) * 1000);

            return [
                'success'     => false,
                'via_proxy'   => $this->proxyEnabled,
                'response_ms' => $ms,
                'error'       => $e->getMessage(),
            ];
        }
    }

    public function isProxyEnabled(): bool
    {
        return $this->proxyEnabled;
    }

    public function getProxyUrl(): string
    {
        return $this->proxyUrl;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Executa a requisição — via Cloudflare Worker ou direto à API ML.
     *
     * @param  array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function request(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body = null
    ): array {
        if ($this->proxyEnabled) {
            return $this->requestViaProxy($method, $path, $accessToken, $body);
        }

        return $this->requestDirect($method, $path, $accessToken, $body);
    }

    /**
     * Rota a requisição pelo Cloudflare Worker.
     *
     * O Worker espera:
     *   X-Proxy-Secret  → autenticação entre servidor e Worker
     *   X-ML-Path       → path + query string da API ML destino
     *   Authorization   → Bearer token do ML (repassado ao ML)
     *
     * @param  array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function requestViaProxy(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body
    ): array {
        if ($this->proxyUrl === '') {
            throw new \RuntimeException('ML_CF_PROXY_URL não configurado. Defina no .env.');
        }

        if ($this->proxySecret === '') {
            throw new \RuntimeException('ML_CF_PROXY_SECRET não configurado. Defina no .env.');
        }

        $headers = [
            'X-Proxy-Secret' => $this->proxySecret,
            'X-ML-Path'      => $path,
            'Content-Type'   => 'application/json',
        ];

        if ($accessToken !== null && $accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $options = [
            'headers' => $headers,
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request($method, $this->proxyUrl, $options);
            $decoded  = json_decode((string) $response->getBody(), true);

            if (!is_array($decoded)) {
                throw new \RuntimeException('Resposta inválida do Cloudflare Worker (JSON esperado)');
            }

            return $decoded;
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;
            $detail = '';
            try {
                $detail = (string) $e->getResponse()?->getBody();
            } catch (\Throwable) {
            }

            log_error('MercadoLivreProxy: erro via Cloudflare Worker', [
                'path'   => $path,
                'status' => $status,
                'detail' => mb_substr($detail, 0, 500),
            ]);

            throw new \RuntimeException("ML Proxy error [{$status}]: " . $e->getMessage(), $status, $e);
        }
    }

    /**
     * Requisição direta à API ML (fallback quando proxy desabilitado).
     *
     * @param  array<string,mixed>|null $body
     * @return array<string,mixed>
     */
    private function requestDirect(
        string $method,
        string $path,
        ?string $accessToken,
        ?array $body
    ): array {
        $url     = self::ML_API_BASE . $path;
        $headers = ['Content-Type' => 'application/json'];

        if ($accessToken !== null && $accessToken !== '') {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $options = ['headers' => $headers];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->http->request($method, $url, $options);
            $decoded  = json_decode((string) $response->getBody(), true);

            if (!is_array($decoded)) {
                throw new \RuntimeException('Resposta inválida da API ML (JSON esperado)');
            }

            return $decoded;
        } catch (RequestException $e) {
            $status = $e->getResponse()?->getStatusCode() ?? 0;
            throw new \RuntimeException("ML API error [{$status}]: " . $e->getMessage(), $status, $e);
        }
    }
}
