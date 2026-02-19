<?php

declare(strict_types=1);

namespace App\Services\MercadoLivre;

final class CategoryDetailDTO
{
    /** @param CategoryNodeDTO[] $pathFromRoot */
    /** @param CategoryChildDTO[] $childrenCategories */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $picture,
        public ?string $permalink,
        public int $totalItemsInThisCategory,
        public array $pathFromRoot,
        public array $childrenCategories
    ) {
    }

    public static function fromArray(array $data): self
    {
        $id = trim((string)($data['id'] ?? ''));
        $name = trim((string)($data['name'] ?? ''));

        if ($id === '' || $name === '') {
            throw new CategoriesApiException('Payload inválido para detalhe de categoria', 502, 'invalid_payload', [
                'payload' => $data,
            ]);
        }

        $path = [];
        foreach (($data['path_from_root'] ?? []) as $node) {
            if (is_array($node)) {
                $path[] = CategoryNodeDTO::fromArray($node);
            }
        }

        $children = [];
        foreach (($data['children_categories'] ?? []) as $child) {
            if (is_array($child)) {
                $children[] = CategoryChildDTO::fromArray($child);
            }
        }

        return new self(
            $id,
            $name,
            isset($data['picture']) ? (string)$data['picture'] : null,
            isset($data['permalink']) ? (string)$data['permalink'] : null,
            (int)($data['total_items_in_this_category'] ?? 0),
            $path,
            $children
        );
    }
}
