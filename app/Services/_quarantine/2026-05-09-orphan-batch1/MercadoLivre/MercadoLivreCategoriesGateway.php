<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

use App\Services\MercadoLivreClient;

class MercadoLivreCategoriesGateway implements CategoriesApiGatewayInterface
{
    private MercadoLivreClient $client;

    public function __construct(?MercadoLivreClient $client = null)
    {
        $this->client = $client ?? new MercadoLivreClient(null);
    }

    public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array
    {
        return $this->client->get($endpoint, $params, $cacheTtlOrPublic, $public);
    }
}
