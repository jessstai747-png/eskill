<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

final class CategoryNodeDTO
{
    public function __construct(
        public string $id,
        public string $name
    ) {}

    public static function fromArray(array $data): self
    {
        $id = trim((string)($data['id'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));

        if ($id === '' || $name === '') {
            throw new CategoriesApiException('Payload inválido para categoria (id/name ausentes)', 502, 'invalid_payload', [
                'payload' => $data,
            ]);
        }

        return new self($id, $name);
    }
}
