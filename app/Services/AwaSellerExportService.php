<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * AwaSellerExportService
 *
 * Handles CSV export of AWA sellers and their items.
 * Writes directly to a PHP stream resource to support large datasets
 * without loading the full result into memory.
 */
class AwaSellerExportService
{
    private AwaSellerRegistryService $registry;

    private const SELLERS_COLUMNS = [
        'seller_id', 'nickname', 'permalink', 'city', 'state',
        'reputation_level', 'power_seller_status', 'items_count',
        'first_seen_at', 'last_seen_at',
        'cnpj', 'razao_social', 'id_status',
    ];

    private const ITEMS_COLUMNS = [
        'seller_id', 'nickname', 'ml_item_id', 'title',
        'price', 'original_price', 'available_quantity',
        'category_id', 'condition', 'status',
        'permalink', 'thumbnail', 'first_seen_at', 'last_seen_at',
    ];

    public function __construct(int $accountId, ?AwaSellerRegistryService $registry = null)
    {
        $this->registry = $registry ?? new AwaSellerRegistryService($accountId);
    }

    /**
     * Streams sellers as CSV into $output.
     *
     * @param resource             $output  PHP stream (e.g. fopen('php://output', 'w'))
     * @param array<string, mixed> $filters Filters accepted by AwaSellerRegistryService
     * @return int                          Number of rows written (excluding header)
     */
    public function streamSellersAsCsv($output, array $filters = []): int
    {
        fputcsv($output, self::SELLERS_COLUMNS);

        $rowCount = 0;
        foreach ($this->registry->iterateSellersForExport($filters) as $row) {
            fputcsv($output, [
                $row['seller_id']          ?? '',
                $row['nickname']           ?? '',
                $row['permalink']          ?? '',
                $row['city']               ?? '',
                $row['state']              ?? '',
                $row['reputation_level']   ?? '',
                $row['power_seller_status'] ?? '',
                $row['items_count']        ?? 0,
                $row['first_seen_at']      ?? '',
                $row['last_seen_at']       ?? '',
                $row['cnpj']               ?? '',
                $row['razao_social']       ?? '',
                $row['id_status']          ?? '',
            ]);
            $rowCount++;
        }

        return $rowCount;
    }

    /**
     * Streams items of a given seller as CSV into $output.
     *
     * @param resource $output     PHP stream
     * @param int      $registryId awa_sellers.id (not ml_seller_id)
     * @return int                 Number of rows written (excluding header)
     */
    public function streamSellerItemsAsCsv($output, int $registryId): int
    {
        fputcsv($output, self::ITEMS_COLUMNS);

        $page  = 1;
        $limit = 200;
        $rowCount = 0;

        do {
            $items = $this->registry->listSellerItems($registryId, $page, $limit);
            foreach ($items as $item) {
                fputcsv($output, [
                    $item['ml_seller_id']       ?? '',
                    $item['nickname']           ?? '',
                    $item['ml_item_id']         ?? '',
                    $item['title']              ?? '',
                    $item['price']              ?? '',
                    $item['original_price']     ?? '',
                    $item['available_quantity'] ?? '',
                    $item['category_id']        ?? '',
                    $item['condition']          ?? '',
                    $item['status']             ?? '',
                    $item['permalink']          ?? '',
                    $item['thumbnail']          ?? '',
                    $item['first_seen_at']      ?? '',
                    $item['last_seen_at']       ?? '',
                ]);
                $rowCount++;
            }
            $page++;
        } while (count($items) === $limit);

        return $rowCount;
    }

    /**
     * Sends headers and streams a sellers CSV as an HTTP download.
     *
     * @param array<string, mixed> $filters
     */
    public function downloadSellersAsCsv(array $filters = []): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="awa-sellers-' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        $this->streamSellersAsCsv($out, $filters);
        fclose($out);
    }

    /**
     * Sends headers and streams items of one seller as an HTTP download.
     */
    public function downloadSellerItemsAsCsv(int $registryId): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="awa-seller-' . $registryId . '-items-' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            return;
        }

        $this->streamSellerItemsAsCsv($out, $registryId);
        fclose($out);
    }
}
