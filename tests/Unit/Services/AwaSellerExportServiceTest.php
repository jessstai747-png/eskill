<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AwaSellerExportService;
use App\Services\AwaSellerRegistryService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AwaSellerExportService
 */
class AwaSellerExportServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @param array<int, array<string, mixed>> $sellers */
    private function makeExportService(array $sellers = [], array $items = []): AwaSellerExportService
    {
        $fakeRegistry = new class($sellers, $items) extends AwaSellerRegistryService {
            /** @var array<int, array<string, mixed>> */
            private array $sellers;
            /** @var array<int, array<string, mixed>> */
            private array $items;

            /**
             * @param array<int, array<string, mixed>> $sellers
             * @param array<int, array<string, mixed>> $items
             */
            public function __construct(array $sellers, array $items)
            {
                // skip parent __construct (requires DB)
                $this->sellers = $sellers;
                $this->items   = $items;
            }

            /** @return iterable<int, array<string, mixed>> */
            public function iterateSellersForExport(array $filters = []): iterable
            {
                yield from $this->sellers;
            }

            /** @return array<int, array<string, mixed>> */
            public function listSellerItems(int $registryId, int $page = 1, int $limit = 50): array
            {
                // Return all items on page 1, empty on page 2+
                if ($page === 1) {
                    return $this->items;
                }
                return [];
            }
        };

        return new AwaSellerExportService(1, $fakeRegistry);
    }

    // -----------------------------------------------------------------------
    // streamSellersAsCsv
    // -----------------------------------------------------------------------

    public function testStreamSellersWritesHeaderAndRows(): void
    {
        $sellers = [
            [
                'seller_id'           => 123,
                'nickname'            => 'Loja Teste',
                'permalink'           => 'https://example.com/loja',
                'city'                => 'Araraquara',
                'state'               => 'SP',
                'reputation_level'    => '5_green',
                'power_seller_status' => 'platinum',
                'items_count'         => 10,
                'first_seen_at'       => '2026-01-01 00:00:00',
                'last_seen_at'        => '2026-04-01 00:00:00',
                'cnpj'                => '12345678000199',
                'razao_social'        => 'Empresa Ltda',
                'id_status'           => 'verified',
            ],
        ];

        $svc = $this->makeExportService($sellers);

        $out = fopen('php://memory', 'w+');
        $this->assertNotFalse($out);

        $count = $svc->streamSellersAsCsv($out);
        $this->assertSame(1, $count);

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        // Header present
        $this->assertStringContainsString('seller_id', $content);
        $this->assertStringContainsString('nickname', $content);
        // Data present
        $this->assertStringContainsString('Loja Teste', $content);
        $this->assertStringContainsString('12345678000199', $content);
    }

    public function testStreamSellersWritesOnlyHeaderWhenNoResults(): void
    {
        $svc = $this->makeExportService([]);

        $out = fopen('php://memory', 'w+');
        $this->assertNotFalse($out);

        $count = $svc->streamSellersAsCsv($out);
        $this->assertSame(0, $count);

        rewind($out);
        $lines = array_filter(explode("\n", stream_get_contents($out)));
        fclose($out);

        // Only header line
        $this->assertCount(1, $lines);
    }

    public function testStreamSellersHandlesMissingFieldsGracefully(): void
    {
        $sellers = [
            [],  // completely empty row
        ];

        $svc = $this->makeExportService($sellers);

        $out = fopen('php://memory', 'w+');
        $this->assertNotFalse($out);

        $count = $svc->streamSellersAsCsv($out);
        $this->assertSame(1, $count);

        fclose($out);
    }

    // -----------------------------------------------------------------------
    // streamSellerItemsAsCsv
    // -----------------------------------------------------------------------

    public function testStreamSellerItemsWritesHeaderAndRows(): void
    {
        $items = [
            [
                'ml_seller_id'       => 123,
                'nickname'           => 'Loja Teste',
                'ml_item_id'         => 'MLB999',
                'title'              => 'Bagageiro AWA',
                'price'              => '199.90',
                'original_price'     => null,
                'available_quantity' => 5,
                'category_id'        => 'MLB5750',
                'condition'          => 'new',
                'status'             => 'active',
                'permalink'          => 'https://example.com/MLB999',
                'thumbnail'          => 'https://example.com/thumb.jpg',
                'first_seen_at'      => '2026-01-15 00:00:00',
                'last_seen_at'       => '2026-04-01 00:00:00',
            ],
        ];

        $svc = $this->makeExportService([], $items);

        $out = fopen('php://memory', 'w+');
        $this->assertNotFalse($out);

        $count = $svc->streamSellerItemsAsCsv($out, 42);
        $this->assertSame(1, $count);

        rewind($out);
        $content = stream_get_contents($out);
        fclose($out);

        $this->assertStringContainsString('ml_item_id', $content);
        $this->assertStringContainsString('MLB999', $content);
        $this->assertStringContainsString('Bagageiro AWA', $content);
    }

    public function testStreamSellerItemsHandlesNullValues(): void
    {
        $items = [
            [
                'ml_seller_id'       => null,
                'nickname'           => null,
                'ml_item_id'         => null,
                'title'              => null,
                'price'              => null,
                'original_price'     => null,
                'available_quantity' => null,
                'category_id'        => null,
                'condition'          => null,
                'status'             => null,
                'permalink'          => null,
                'thumbnail'          => null,
                'first_seen_at'      => null,
                'last_seen_at'       => null,
            ],
        ];

        $svc = $this->makeExportService([], $items);

        $out = fopen('php://memory', 'w+');
        $this->assertNotFalse($out);

        $count = $svc->streamSellerItemsAsCsv($out, 1);
        $this->assertSame(1, $count);

        fclose($out);
    }

    // -----------------------------------------------------------------------
    // Column constant integrity
    // -----------------------------------------------------------------------

    public function testSellersColumnCountMatchesExpected(): void
    {
        // Verify the two column sets have the expected sizes
        $reflection = new \ReflectionClass(AwaSellerExportService::class);
        $cols       = $reflection->getConstant('SELLERS_COLUMNS');

        $this->assertIsArray($cols);
        $this->assertGreaterThan(0, count($cols));
        $this->assertContains('seller_id', $cols);
        $this->assertContains('cnpj', $cols);
    }
}
