<?php

declare(strict_types=1);

namespace App\Services\Financial;

use App\Database;
use App\Services\MercadoLivreClient;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Trait compartilhado por todos os serviços financeiros.
 * Fornece acesso ao banco de dados, accountId e clientes de API.
 */
trait HasFinancialDependencies
{
    protected \PDO $db;
    protected ?int $accountId;
    private ?MercadoLivreClient $client = null;
    private ?object $mpClient = null;

    // Alíquota padrão de impostos (Simples Nacional - média)
    protected const DEFAULT_TAX_RATE = 0.0;

    // Cache TTL em segundos
    protected const CACHE_TTL_SHORT = 300;   // 5 minutos
    protected const CACHE_TTL_MEDIUM = 1800; // 30 minutos
    protected const CACHE_TTL_LONG = 3600;   // 1 hora

    public function __construct(?int $accountId = null)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
    }

    /**
     * Obtém instância do cliente ML (lazy loading)
     */
    protected function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }

    /**
     * Obtém cliente HTTP para Mercado Pago API (lazy loading)
     */
    protected function getMercadoPagoClient(): object
    {
        if ($this->mpClient !== null) {
            return $this->mpClient;
        }

        $mlClient = $this->getClient();
        $accessToken = $mlClient->getAccessToken();

        $guzzle = new GuzzleClient([
            'base_uri' => 'https://api.mercadopago.com',
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => 'Bearer ' . ($accessToken ?? ''),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);

        $this->mpClient = new class($guzzle) {
            private GuzzleClient $http;

            public function __construct(GuzzleClient $http)
            {
                $this->http = $http;
            }

            private function request(string $method, string $url, array $data = []): array
            {
                $options = !empty($data) ? ['json' => $data] : [];
                $response = $this->http->request($method, $url, $options);
                return json_decode($response->getBody()->getContents(), true) ?: [];
            }

            public function get(string $url, array $params = []): array
            {
                $options = !empty($params) ? ['query' => $params] : [];
                $response = $this->http->request('GET', $url, $options);
                return json_decode($response->getBody()->getContents(), true) ?: [];
            }

            public function post(string $url, array $data = []): array
            {
                return $this->request('POST', $url, $data);
            }

            public function put(string $url, array $data = []): array
            {
                return $this->request('PUT', $url, $data);
            }

            public function delete(string $url): array
            {
                return $this->request('DELETE', $url);
            }
        };

        return $this->mpClient;
    }

    /**
     * Obtém o seller ID da conta
     */
    protected function getSellerId(): ?string
    {
        $client = $this->getClient();
        return $client->getSellerId();
    }
}
