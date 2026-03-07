<?php

declare(strict_types=1);

namespace App\Services\Marketplace;

use App\Services\Marketplace\Amazon\AmazonAdapter;
use App\Services\Marketplace\Shopee\ShopeeAdapter;
use Exception;

/**
 * Factory for creating Marketplace Adapters
 */
class MarketplaceFactory
{
    /**
     * Get adapter instance
     * 
     * @param string $platform 'amazon', 'shopee', 'mercadolivre'
     * @param int|null $accountId Internal account ID to fetch credentials for
     * @return MarketplaceInterface
     * @throws Exception
     */
    public static function getAdapter(string $platform, ?int $accountId = null): MarketplaceInterface
    {
        switch (strtolower($platform)) {
            case 'amazon':
                return new AmazonAdapter($accountId);
            case 'shopee':
                return new ShopeeAdapter($accountId);
            // Mercado Livre might be handled differently or migrated here later
            default:
                throw new Exception("Platform [$platform] not supported.");
        }
    }
}
