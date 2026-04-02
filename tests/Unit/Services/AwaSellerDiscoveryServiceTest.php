<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AwaSellerDiscoveryService;
use App\Services\AwaSellerRegistryService;
use App\Services\BrandAnalyzerService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AwaSellerDiscoveryService
 */
class AwaSellerDiscoveryServiceTest extends TestCase
{
    public function testRunScanPersistsSellersAndItemsWithNormalizedMatchTypes(): void
    {
        $fakeAnalyzer = new class extends BrandAnalyzerService {
            public function __construct()
            {
            }

            public function analyzeAwaBrand(array $options = []): array
            {
                return [
                    'analysis_date' => '2026-04-02 16:10:00',
                    'execution_time' => '1.2s',
                    'brand_consistency_score' => 91.4,
                    'items' => [
                        [
                            'id' => 'MLB1',
                            'title' => 'Bagageiro AWA Titan 160',
                            'category_id' => 'MLB214858',
                            'price' => 199.9,
                            'status' => 'active',
                            'seller_id' => 123,
                            'permalink' => 'https://example.com/MLB1',
                            'thumbnail' => 'https://example.com/MLB1.jpg',
                            'shipping' => ['free_shipping' => true],
                            'brand_analysis' => [
                                'has_brand' => true,
                                'is_correct' => true,
                            ],
                        ],
                        [
                            'id' => 'MLB2',
                            'title' => 'Suporte A.W.A para Bros',
                            'category_id' => 'MLB5750',
                            'price' => 89.9,
                            'status' => 'paused',
                            'seller_id' => 123,
                            'permalink' => 'https://example.com/MLB2',
                            'thumbnail' => 'https://example.com/MLB2.jpg',
                            'shipping' => ['free_shipping' => false],
                            'brand_analysis' => [
                                'has_brand' => false,
                                'is_correct' => false,
                            ],
                        ],
                    ],
                    'sellers' => [
                        123 => [
                            'id' => 123,
                            'nickname' => 'Loja AWA Parceira',
                            'permalink' => 'https://perfil.mercadolivre.com.br/LOJAAWA',
                            'user_type' => 'normal',
                            'address' => [
                                'city' => ['name' => 'Araraquara'],
                                'state' => ['name' => 'SP'],
                            ],
                            'seller_reputation' => [
                                'level_id' => '5_green',
                                'power_seller_status' => 'platinum',
                            ],
                        ],
                    ],
                ];
            }
        };

        $fakeRegistry = new class extends AwaSellerRegistryService {
            public array $createdScopes = [];
            public array $sellerCalls = [];
            public array $itemCalls = [];
            public ?array $completed = null;
            public ?array $failed = null;

            public function __construct()
            {
            }

            public function createScanRun(array $scope): int
            {
                $this->createdScopes[] = $scope;
                return 77;
            }

            public function upsertSeller(int $scanRunId, array $sellerData): int
            {
                $this->sellerCalls[] = [
                    'scan_id' => $scanRunId,
                    'seller' => $sellerData,
                ];

                return 501;
            }

            public function upsertSellerItem(int $sellerRegistryId, array $itemData): int
            {
                $this->itemCalls[] = [
                    'seller_registry_id' => $sellerRegistryId,
                    'item' => $itemData,
                ];

                return count($this->itemCalls);
            }

            public function markScanCompleted(int $scanRunId, int $sellersFound, int $itemsFound): void
            {
                $this->completed = [
                    'scan_id' => $scanRunId,
                    'sellers_found' => $sellersFound,
                    'items_found' => $itemsFound,
                ];
            }

            public function markScanFailed(int $scanRunId, string $errorMessage): void
            {
                $this->failed = [
                    'scan_id' => $scanRunId,
                    'error' => $errorMessage,
                ];
            }
        };

        $service = new AwaSellerDiscoveryService(1, $fakeAnalyzer, $fakeRegistry);

        $result = $service->runScan([
            'categories' => ['MLB214858', 'MLB5750'],
            'max_results' => 250,
        ]);

        $this->assertSame(77, $result['scan_id']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame(1, $result['sellers_found']);
        $this->assertSame(2, $result['items_found']);
        $this->assertSame(['MLB214858', 'MLB5750'], $result['categories']);
        $this->assertCount(1, $fakeRegistry->sellerCalls);
        $this->assertCount(2, $fakeRegistry->itemCalls);
        $this->assertSame([
            'scan_id' => 77,
            'sellers_found' => 1,
            'items_found' => 2,
        ], $fakeRegistry->completed);
        $this->assertNull($fakeRegistry->failed);

        $sellerPayload = $fakeRegistry->sellerCalls[0]['seller'];
        $this->assertSame('Loja AWA Parceira', $sellerPayload['nickname']);
        $this->assertSame('Araraquara', $sellerPayload['city']);
        $this->assertSame('SP', $sellerPayload['state']);
        $this->assertSame(['MLB214858', 'MLB5750'], $sellerPayload['categories']);
        $this->assertSame(2, $sellerPayload['items_count']);

        $this->assertSame('attribute_match', $fakeRegistry->itemCalls[0]['item']['brand_match_type']);
        $this->assertTrue($fakeRegistry->itemCalls[0]['item']['has_brand_attribute']);
        $this->assertSame('title_match_only', $fakeRegistry->itemCalls[1]['item']['brand_match_type']);
        $this->assertFalse($fakeRegistry->itemCalls[1]['item']['has_brand_attribute']);
        $this->assertSame('https://example.com/MLB2', $fakeRegistry->itemCalls[1]['item']['evidence']['permalink']);
    }

    public function testRunScanMarksRunAsFailedWhenAnalyzerThrows(): void
    {
        $fakeAnalyzer = new class extends BrandAnalyzerService {
            public function __construct()
            {
            }

            public function analyzeAwaBrand(array $options = []): array
            {
                throw new \RuntimeException('Falha simulada na análise');
            }
        };

        $fakeRegistry = new class extends AwaSellerRegistryService {
            public ?array $failed = null;

            public function __construct()
            {
            }

            public function createScanRun(array $scope): int
            {
                return 12;
            }

            public function markScanFailed(int $scanRunId, string $errorMessage): void
            {
                $this->failed = [
                    'scan_id' => $scanRunId,
                    'error' => $errorMessage,
                ];
            }
        };

        $service = new AwaSellerDiscoveryService(1, $fakeAnalyzer, $fakeRegistry);

        try {
            $service->runScan();
            $this->fail('Era esperada uma exceção ao falhar a análise.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Falha simulada na análise', $exception->getMessage());
        }

        $this->assertSame([
            'scan_id' => 12,
            'error' => 'Falha simulada na análise',
        ], $fakeRegistry->failed);
    }
}
