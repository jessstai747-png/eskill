<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

/**
 * Interface for all Marketplace Adapters
 * Standardizes operations across Mercado Livre, Amazon, Shopee, etc.
 */
interface MarketplaceInterface
{
    /**
     * Get orders from the marketplace
     * 
     * @param array $params Filter parameters (date_from, status, etc.)
     * @return array List of standardized orders
     */
    public function getOrders(array $params = []): array;

    /**
     * Get a specific order details
     * 
     * @param string $orderId
     * @return array Standardized order details
     */
    public function getOrder(string $orderId): array;

    /**
     * Update inventory for a SKU
     * 
     * @param string $sku
     * @param int $quantity
     * @return bool Success status
     */
    public function updateStock(string $sku, int $quantity): bool;

    /**
     * Get listing details
     * 
     * @param string $listingId
     * @return array Standardized listing details
     */
    public function getListing(string $listingId): array;

    /**
     * Get marketplace name (e.g., 'amazon', 'shopee')
     */
    public function getPlatformName(): string;
}
