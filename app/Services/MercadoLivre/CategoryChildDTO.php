<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

final class CategoryChildDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public int $totalItemsInThisCategory
    ) {}

    public static function fromArray(array $data): self
    {
        $id = trim((string)($data['id'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));

        if ($id === '' || $name === '') {
            throw new CategoriesApiException('Payload inválido para categoria filha', 502, 'invalid_payload', [
                'payload' => $data,
            ]);
        }

        return new self(
            $id,
            $name,
            (int)($data['total_items_in_this_category'] ?? 0)
        );
    }
}
