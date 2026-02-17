<?php

declare(strict_types=1);

namespace App\Traits;

/**
 * Trait NormalizesMLItems
 *
 * Shared normalization logic for Mercado Livre API item responses.
 *
 * Used by:
 *  - MercadoLivreAIIntegrationService
 *  - AIOptimizationEngine
 *
 * Why this exists:
 *  MercadoLivreClient::getItemDetails() returns `description` as an array
 *  (the raw /items/{id}/description response: {plain_text, text, date_created, ...}).
 *  This trait provides safe normalization that extracts the text and avoids
 *  silent data corruption when downstream code expects a string.
 */
trait NormalizesMLItems
{
    /**
     * Normalize ML API item response to internal format.
     *
     * Converts raw ML API fields to a consistent, typed structure:
     *  - description is always a string (plain text)
     *  - description_data preserves the full API response array
     *  - brand/model extracted from attributes
     *  - images normalized from pictures[]
     *  - attributes simplified to [{id, name, value}]
     *
     * @param array $mlItem Raw item from ML API (getItemDetails() or similar)
     * @return array Normalized item data
     */
    protected function normalizeMLItem(array $mlItem): array
    {
        $descriptionRaw = $mlItem['description'] ?? null;

        return [
            'id' => $mlItem['id'] ?? '',
            'title' => $mlItem['title'] ?? '',
            'description' => self::extractDescriptionText($descriptionRaw),
            'description_data' => is_array($descriptionRaw) ? $descriptionRaw : [],
            'category_id' => $mlItem['category_id'] ?? '',
            'category' => $mlItem['category_id'] ?? '',
            'brand' => self::extractMLAttribute($mlItem, 'BRAND') ?? '',
            'model' => self::extractMLAttribute($mlItem, 'MODEL') ?? '',
            'price' => (float) ($mlItem['price'] ?? 0),
            'original_price' => (float) ($mlItem['original_price'] ?? $mlItem['price'] ?? 0),
            'currency_id' => $mlItem['currency_id'] ?? 'BRL',
            'available_quantity' => (int) ($mlItem['available_quantity'] ?? 0),
            'sold_quantity' => (int) ($mlItem['sold_quantity'] ?? 0),
            'images' => array_map(
                static fn(array $img): array => [
                    'url' => $img['url'] ?? $img['secure_url'] ?? '',
                    'id' => $img['id'] ?? '',
                ],
                $mlItem['pictures'] ?? []
            ),
            'attributes' => array_map(
                static fn(array $attr): array => [
                    'id' => $attr['id'] ?? '',
                    'name' => $attr['name'] ?? '',
                    'value' => $attr['value_name'] ?? '',
                ],
                $mlItem['attributes'] ?? []
            ),
            'free_shipping' => $mlItem['shipping']['free_shipping'] ?? false,
            'status' => $mlItem['status'] ?? 'unknown',
            'permalink' => $mlItem['permalink'] ?? '',
            'health' => $mlItem['health'] ?? null,
        ];
    }

    /**
     * Extract description text from ML API response.
     *
     * Handles 3 possible formats:
     *  1. string — already plain text (e.g. from local DB or ItemService fallback)
     *  2. array  — raw API response with {plain_text, text, ...}
     *  3. null   — description not fetched
     *
     * @param mixed $description Raw description value from ML API
     * @return string Plain text description
     */
    public static function extractDescriptionText(mixed $description): string
    {
        if (is_string($description)) {
            return $description;
        }

        if (is_array($description)) {
            return $description['plain_text'] ?? $description['text'] ?? '';
        }

        return '';
    }

    /**
     * Extract a specific attribute value from an ML item by attribute ID.
     *
     * Common attribute IDs:
     *  - BRAND: Product brand
     *  - MODEL: Product model
     *  - GTIN: EAN/UPC barcode
     *  - SELLER_SKU: Seller's internal SKU
     *
     * @param array  $mlItem Raw ML API item
     * @param string $attrId Attribute ID to search for (e.g. 'BRAND')
     * @return string|null Attribute value or null if not found
     */
    public static function extractMLAttribute(array $mlItem, string $attrId): ?string
    {
        foreach ($mlItem['attributes'] ?? [] as $attr) {
            if (($attr['id'] ?? '') === $attrId) {
                return $attr['value_name'] ?? null;
            }
        }
        return null;
    }
}
