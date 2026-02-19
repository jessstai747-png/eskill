<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

interface CategoriesApiGatewayInterface
{
    public function get(string $endpoint, array $params = [], int|bool|null $cacheTtlOrPublic = null, ?bool $public = null): array;
}
