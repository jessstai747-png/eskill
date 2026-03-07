<?php

declare(strict_types=1);

namespace App\Services\AI\SEO;

use App\Services\MercadoLivreClient;
use App\Database;
use PDO;

class SchemaGenerator
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient = null;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    public function generateProductSchema(string $itemId): array
    {
        try {
            // Fetch item details
            $item = $this->mlClient->get("/items/{$itemId}");
            
            // Basic Product Schema
            $schema = [
                '@context' => 'https://schema.org/',
                '@type' => 'Product',
                'name' => $item['title'],
                'image' => array_column($item['pictures'] ?? [], 'url'),
                'description' => $this->getItemDescription($itemId),
                'sku' => $item['id'],
                'mpn' => $this->getAttributeValue($item, 'MPN') ?? $item['id'],
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $this->getAttributeValue($item, 'BRAND') ?? 'Generic'
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'url' => $item['permalink'],
                    'priceCurrency' => $item['currency_id'],
                    'price' => $item['price'],
                    'priceValidUntil' => date('Y-m-d', strtotime('+1 year')),
                    'itemCondition' => $item['condition'] === 'new' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition',
                    'availability' => $item['status'] === 'active' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => 'Mercado Livre Seller' // Could pull seller nickname if available
                    ]
                ]
            ];
            
            // Parse reviews if we had them (ML doesn't expose reviews via public API easily)
            // But if we had an AggregateRating, we'd add it here.
            
            return [
                'success' => true,
                'schema' => $schema,
                'json_ld' => json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ];
            
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getItemDescription(string $itemId): string
    {
        try {
            $desc = $this->mlClient->get("/items/{$itemId}/description");
            return $desc['plain_text'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    private function getAttributeValue(array $item, string $attrId): ?string
    {
        foreach ($item['attributes'] ?? [] as $attr) {
            if ($attr['id'] === $attrId) {
                return $attr['value_name'];
            }
        }
        return null;
    }
}
