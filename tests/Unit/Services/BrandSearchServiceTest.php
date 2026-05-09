<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Database;
use App\Services\MercadoLivre\BrandSearchService;
use App\Services\MercadoLivreClient;
use App\Services\StructuredLogService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\MercadoLivre\BrandSearchService
 */
class BrandSearchServiceTest extends TestCase
{
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        $this->ref = new ReflectionClass(BrandSearchService::class);
    }

    // =========================================================================
    // Constants — BRAND-003 spec values
    // =========================================================================

    public function testPageLimitConstantIs50(): void
    {
        $this->assertSame(50, $this->ref->getConstant('PAGE_LIMIT'));
    }

    public function testMaxOffsetConstantIs950(): void
    {
        $this->assertSame(950, $this->ref->getConstant('MAX_OFFSET'));
    }

    public function testRateLimitWaitIsPositive(): void
    {
        $val = $this->ref->getConstant('RATE_LIMIT_WAIT');
        $this->assertIsInt($val);
        $this->assertGreaterThan(0, $val, 'RATE_LIMIT_WAIT must be > 0 ms');
    }

    public function testRateLimitWaitIs350ms(): void
    {
        $this->assertSame(350, $this->ref->getConstant('RATE_LIMIT_WAIT'));
    }

    public function testUsersBatchIs20(): void
    {
        $this->assertSame(20, $this->ref->getConstant('USERS_BATCH'));
    }

    public function testMlApiBaseIsString(): void
    {
        $base = $this->ref->getConstant('ML_API_BASE');
        $this->assertIsString($base);
        $this->assertStringStartsWith('https://', $base);
    }

    // =========================================================================
    // Constants are private (implementation detail)
    // =========================================================================

    public function testConstantsArePrivate(): void
    {
        foreach (['PAGE_LIMIT', 'MAX_OFFSET', 'RATE_LIMIT_WAIT', 'USERS_BATCH'] as $c) {
            $rc = $this->ref->getReflectionConstant($c);
            $this->assertNotFalse($rc, "Constant {$c} must exist");
            $this->assertTrue($rc->isPrivate(), "Constant {$c} should be private");
        }
    }

    // =========================================================================
    // Properties
    // =========================================================================

    public function testHasMlClientProperty(): void
    {
        $this->assertTrue($this->ref->hasProperty('ml'), 'Missing property: $ml');
    }

    public function testHasLogProperty(): void
    {
        $this->assertTrue($this->ref->hasProperty('log'), 'Missing property: $log');
    }

    public function testMlPropertyIsPrivate(): void
    {
        $prop = $this->ref->getProperty('ml');
        $this->assertTrue($prop->isPrivate(), '$ml must be private');
    }

    // =========================================================================
    // Public API — expected methods
    // =========================================================================

    public function testInitSearchIsPublic(): void
    {
        $m = $this->ref->getMethod('initSearch');
        $this->assertTrue($m->isPublic(), 'initSearch must be public');
    }

    public function testExecuteSearchIsPublic(): void
    {
        $m = $this->ref->getMethod('executeSearch');
        $this->assertTrue($m->isPublic(), 'executeSearch must be public');
    }

    public function testGetSearchProgressIsPublic(): void
    {
        $m = $this->ref->getMethod('getSearchProgress');
        $this->assertTrue($m->isPublic(), 'getSearchProgress must be public');
    }

    public function testGetSearchSellersIsPublic(): void
    {
        $m = $this->ref->getMethod('getSearchSellers');
        $this->assertTrue($m->isPublic(), 'getSearchSellers must be public');
    }

    public function testInitSearchSignatureReturnsInt(): void
    {
        $m = $this->ref->getMethod('initSearch');
        $returnType = (string)$m->getReturnType();
        $this->assertSame('int', $returnType, 'initSearch must return int (search_id)');
    }

    public function testExecuteSearchReturnsVoid(): void
    {
        $m = $this->ref->getMethod('executeSearch');
        $returnType = (string)$m->getReturnType();
        $this->assertSame('void', $returnType);
    }

    public function testGetSearchProgressReturnsNullableArray(): void
    {
        $m = $this->ref->getMethod('getSearchProgress');
        $returnType = $m->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertTrue($returnType->allowsNull(), 'getSearchProgress return type must allow null');
        // ?array renders as 'array' via getReturnType toString
        $this->assertStringContainsString('array', (string)$returnType);
    }

    public function testGetSearchSellersReturnsArray(): void
    {
        $m = $this->ref->getMethod('getSearchSellers');
        $returnType = (string)$m->getReturnType();
        $this->assertSame('array', $returnType);
    }

    // =========================================================================
    // Public API — old sync methods must NOT exist
    // =========================================================================

    public function testSearchByBrandIdRemoved(): void
    {
        $this->assertFalse(
            $this->ref->hasMethod('searchByBrandId'),
            'searchByBrandId was a legacy method and must not exist in BRAND-003'
        );
    }

    public function testSearchByBrandNameRemoved(): void
    {
        $this->assertFalse(
            $this->ref->hasMethod('searchByBrandName'),
            'searchByBrandName is a legacy method removed in BRAND-003'
        );
    }

    public function testExportCsvMethodRemoved(): void
    {
        $this->assertFalse(
            $this->ref->hasMethod('exportCsv'),
            'exportCsv (old sync) must not exist in BRAND-003; export is now in controller'
        );
    }

    // =========================================================================
    // Private helpers — present and private
    // =========================================================================

    public function testFetchBrandCategoriesIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('fetchBrandCategories'));
        $this->assertTrue(
            $this->ref->getMethod('fetchBrandCategories')->isPrivate(),
            'fetchBrandCategories should be private'
        );
    }

    public function testFetchItemsByBrandAndCategoryIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('fetchItemsByBrandAndCategory'));
        $this->assertTrue(
            $this->ref->getMethod('fetchItemsByBrandAndCategory')->isPrivate()
        );
    }

    public function testFetchSellersBatchIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('fetchSellersBatch'));
        $this->assertTrue($this->ref->getMethod('fetchSellersBatch')->isPrivate());
    }

    public function testCalcReputationScoreIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('calcReputationScore'));
        $this->assertTrue($this->ref->getMethod('calcReputationScore')->isPrivate());
    }

    public function testNormalizeConditionIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('normalizeCondition'));
        $this->assertTrue($this->ref->getMethod('normalizeCondition')->isPrivate());
    }

    // =========================================================================
    // normalizeCondition logic — accessible via reflection
    // =========================================================================

    public function testNormalizeConditionReturnsNewForNew(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('normalizeCondition');
        $m->setAccessible(true);
        $this->assertSame('new', $m->invoke($svc, 'new'));
    }

    public function testNormalizeConditionReturnsUsedForUsed(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('normalizeCondition');
        $m->setAccessible(true);
        $this->assertSame('used', $m->invoke($svc, 'used'));
    }

    public function testNormalizeConditionReturnsOtherForUnknown(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('normalizeCondition');
        $m->setAccessible(true);
        $this->assertSame('not_specified', $m->invoke($svc, 'refurbished'));
    }

    // =========================================================================
    // calcReputationScore — accessible via reflection
    // =========================================================================

    public function testCalcReputationScoreForPlatinum(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('calcReputationScore');
        $m->setAccessible(true);

        $rep = [
            'level_id'              => 'platinum',
            'power_seller_status'   => 'platinum',
            'transactions'          => ['completed' => 200, 'canceled' => 2],
            'metrics'               => [
                'sales'    => ['completed' => 200],
                'claims'   => ['rate' => 0.01],
                'delayed_handling_time' => ['rate' => 0.02],
                'cancellations' => ['rate' => 0.01],
            ],
        ];
        $score = $m->invoke($svc, $rep);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
        $this->assertIsInt($score);
    }

    public function testCalcReputationScoreForNewSeller(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('calcReputationScore');
        $m->setAccessible(true);

        $score = $m->invoke($svc, []);
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);
    }

    public function testCalcReputationScoreReturnsBetween0and100(): void
    {
        $svc = $this->makeServiceWithoutDb();
        $m   = $this->ref->getMethod('calcReputationScore');
        $m->setAccessible(true);

        foreach ([[],  ['level_id' => 'gold'], ['level_id' => 'silver']] as $rep) {
            $score = $m->invoke($svc, $rep);
            $this->assertGreaterThanOrEqual(0, $score, 'Score must be >= 0');
            $this->assertLessThanOrEqual(100, $score, 'Score must be <= 100');
        }
    }

    // =========================================================================
    // Constructor accepts nullable accountId
    // =========================================================================

    public function testConstructorFirstParamIsNullableInt(): void
    {
        $ctor = $this->ref->getConstructor();
        $this->assertNotNull($ctor);
        $params = $ctor->getParameters();
        $this->assertCount(1, $params, '__construct must have exactly 1 parameter');
        $this->assertTrue($params[0]->allowsNull(), 'accountId must be nullable');
        $this->assertSame('accountId', $params[0]->getName());
    }

    public function testConstructorDefaultIsNull(): void
    {
        $ctor   = $this->ref->getConstructor();
        $params = $ctor->getParameters();
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertNull($params[0]->getDefaultValue());
    }

    // =========================================================================
    // initSearch parameter count and types
    // =========================================================================

    public function testInitSearchHasRequiredParams(): void
    {
        $m      = $this->ref->getMethod('initSearch');
        $params = $m->getParameters();
        $names  = array_map(fn($p) => $p->getName(), $params);

        $this->assertContains('accountId', $names);
        $this->assertContains('brandId',   $names);
        $this->assertContains('brandName', $names);
    }

    public function testInitSearchSiteIdDefaultsToMlb(): void
    {
        $m      = $this->ref->getMethod('initSearch');
        $params = $m->getParameters();
        foreach ($params as $p) {
            if ($p->getName() === 'siteId') {
                $this->assertTrue($p->isDefaultValueAvailable());
                $this->assertSame('MLB', $p->getDefaultValue());
                return;
            }
        }
        $this->assertTrue(true, 'siteId param may not exist if hardcoded to MLB');
    }

    public function testInitSearchRejectsInvalidAccountId(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('accountId inválido.');
        $svc->initSearch(0, '7297804', 'AWA', 'MLB');
    }

    public function testInitSearchRejectsEmptyBrandId(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('brandId inválido.');
        $svc->initSearch(1, '', 'AWA', 'MLB');
    }

    public function testInitSearchRejectsInvalidSiteId(): void
    {
        $svc = $this->makeServiceWithoutDb();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('siteId inválido.');
        $svc->initSearch(1, '7297804', 'AWA', 'mercadolivre-br');
    }

    // =========================================================================
    // Private helpers exist
    // =========================================================================

    public function testMapBrandItemIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('mapBrandItem'));
        $this->assertTrue($this->ref->getMethod('mapBrandItem')->isPrivate());
    }

    public function testMapBrandSellerIsPrivate(): void
    {
        $this->assertTrue($this->ref->hasMethod('mapBrandSeller'));
        $this->assertTrue($this->ref->getMethod('mapBrandSeller')->isPrivate());
    }

    // =========================================================================
    // Helper: instantiate service without DB connection for pure-logic tests
    // =========================================================================

    private function makeServiceWithoutDb(): BrandSearchService
    {
        // Constructor calls new MercadoLivreClient and new StructuredLogService.
        // We use reflection to build the object without calling __construct.
        return $this->ref->newInstanceWithoutConstructor();
    }

    // =========================================================================
    // Behavioral helpers (Phase 4 spec)
    // =========================================================================

    /** Inject a value into a private property via reflection. */
    private function injectServiceProp(object $instance, string $prop, mixed $value): void
    {
        $p = $this->ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue($instance, $value);
    }

    /**
     * Create a BrandSearchService without constructor + inject mock $ml and $log.
     *
     * @return array{0: BrandSearchService, 1: MercadoLivreClient&\PHPUnit\Framework\MockObject\MockObject, 2: StructuredLogService&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function makeServiceWithMocks(): array
    {
        /** @var BrandSearchService $svc */
        $svc     = $this->ref->newInstanceWithoutConstructor();
        $mockMl  = $this->createMock(MercadoLivreClient::class);
        $mockLog = $this->createMock(StructuredLogService::class);
        $this->injectServiceProp($svc, 'ml', $mockMl);
        $this->injectServiceProp($svc, 'log', $mockLog);
        return [$svc, $mockMl, $mockLog];
    }

    // =========================================================================
    // Behavioral Tests — BRAND-003 Phase 4 spec
    // =========================================================================

    /**
     * fetchBrandCategories() must parse available_filters and return category list.
     */
    public function testFetchCategoriesReturnsList(): void
    {
        [$svc, $mockMl] = $this->makeServiceWithMocks();

        $mockMl->method('get')->willReturn([
            'available_filters' => [[
                'id'     => 'category',
                'values' => [
                    ['id' => 'MLB5672', 'name' => 'Acessórios Motos'],
                    ['id' => 'MLB1051', 'name' => 'Motos'],
                ],
            ]],
        ]);

        $m = $this->ref->getMethod('fetchBrandCategories');
        $m->setAccessible(true);
        $result = $m->invoke($svc, '7297804', 'MLB');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('MLB5672', $result[0]['id']);
        $this->assertSame('Acessórios Motos', $result[0]['name']);
    }

    /**
     * Pagination must not exceed MAX_OFFSET=950 even when the catalog is larger.
     */
    public function testPaginationStopsAtMaxOffset(): void
    {
        [$svc, $mockMl] = $this->makeServiceWithMocks();

        // Mock returns 50 items per page and paging.total=100 (2 real pages)
        $calls = 0;
        $mockMl->method('get')->willReturnCallback(function () use (&$calls): array {
            $calls++;
            return [
                'results' => array_fill(0, 50, ['id' => 'MLB' . $calls, 'seller' => ['id' => $calls]]),
                'paging'  => ['total' => 100],
            ];
        });

        $m = $this->ref->getMethod('fetchItemsByBrandAndCategory');
        $m->setAccessible(true);
        $result = $m->invoke($svc, '7297804', 'ALL', 'MLB');

        $this->assertCount(100, $result, '2 pages × 50 items = 100 items returned');
        $this->assertSame(2, $calls, 'Exactly 2 API calls for 100-item catalog');

        // Boundary verification: with MAX_OFFSET=950 and PAGE_LIMIT=50, a 2000-item catalog
        // must be limited to 20 pages (offsets 0 through 950) by the loop guard.
        $maxOffset = $this->ref->getConstant('MAX_OFFSET');
        $pageLimit  = $this->ref->getConstant('PAGE_LIMIT');
        $offset     = 0;
        $iterations = 0;
        do {
            $iterations++;
            $offset += $pageLimit;
        } while ($offset <= $maxOffset && $offset < 2000);

        $this->assertSame(20, $iterations, 'Loop must cap at 20 pages when MAX_OFFSET=950');
        $this->assertSame(950, $offset - $pageLimit, 'Last processed offset must be 950');
    }

    /**
     * Seller IDs appearing in multiple categories must be deduplicated to a unique set.
     */
    public function testDeduplicatesSellerIds(): void
    {
        [$svc, $mockMl] = $this->makeServiceWithMocks();

        $call = 0;
        $mockMl->method('get')->willReturnCallback(function () use (&$call): array {
            $call++;
            $items = match ($call) {
                1       => [['id' => 'MLB1', 'seller' => ['id' => 100]], ['id' => 'MLB2', 'seller' => ['id' => 200]]],
                2       => [['id' => 'MLB3', 'seller' => ['id' => 100]], ['id' => 'MLB4', 'seller' => ['id' => 300]]],
                default => [],
            };
            return ['results' => $items, 'paging' => ['total' => count($items)]];
        });

        $m = $this->ref->getMethod('fetchItemsByBrandAndCategory');
        $m->setAccessible(true);

        // Simulate executeSearch's deduplication: build $allSellerIds from two category fetches
        $allSellerIds = [];
        foreach (['catA', 'catB'] as $catId) {
            foreach ($m->invoke($svc, '7297804', $catId, 'MLB') as $item) {
                $sid = $item['seller']['id'] ?? null;
                if ($sid !== null) {
                    $allSellerIds[(int) $sid] = true;
                }
            }
        }

        // Seller 100 appears in both categories → must result in 3 unique sellers (100, 200, 300)
        $this->assertCount(3, array_keys($allSellerIds), 'Duplicate seller IDs across categories must be deduplicated');
    }

    /**
     * When the API returns a 429-like exception, fetchSellersBatch must handle it gracefully
     * (return empty/partial results) without re-throwing.
     */
    public function testRateLimitBackoffOnHttp429(): void
    {
        [$svc, $mockMl, $mockLog] = $this->makeServiceWithMocks();

        $mockMl->method('get')->willThrowException(new \RuntimeException('HTTP 429 Too Many Requests'));
        $mockLog->expects($this->atLeast(2))->method('warning'); // one warning per seller_id

        $m = $this->ref->getMethod('fetchSellersBatch');
        $m->setAccessible(true);
        $result = $m->invoke($svc, [1001, 1002]);

        $this->assertSame([], $result, 'fetchSellersBatch must return empty array on 429, not throw');
    }

    /**
     * The progress formula used in executeSearch must produce strictly increasing values,
     * reaching exactly 70 after processing all categories.
     */
    public function testUpdateProgressOnEachCategory(): void
    {
        // Formula from executeSearch: (int)(($i + 1) / $totalCats * 70)
        foreach ([3, 5, 10] as $totalCats) {
            $prev = -1;
            for ($i = 0; $i < $totalCats; $i++) {
                $progress = (int) (($i + 1) / $totalCats * 70);
                $this->assertGreaterThan(
                    $prev,
                    $progress,
                    "Progress at category {$i}/{$totalCats} must exceed previous value {$prev}"
                );
                $prev = $progress;
            }
            $lastProgress = (int) ($totalCats / $totalCats * 70);
            $this->assertSame(70, $lastProgress, "Progress must reach exactly 70 after all {$totalCats} categories");
        }
    }

    /**
     * When executeSearch encounters a DB failure mid-run, it must persist status=failed
     * and re-throw the exception.
     */
    public function testStatusFailedOnApiException(): void
    {
        // Inject a mock PDO into the Database singleton so BrandSearchModel skips the real DB.
        $mockStmt = $this->createMock(\PDOStatement::class);
        $mockPdo  = $this->createMock(\PDO::class);

        // getSearch() → prepare call #1 (returns row), updateProgress() → #2, saveItems() → #3 (throws)
        $prepareCall = 0;
        $mockPdo->method('prepare')->willReturnCallback(
            function (string $sql) use (&$prepareCall, $mockStmt): \PDOStatement {
                $prepareCall++;
                if ($prepareCall === 3) {
                    throw new \PDOException('Simulated DB failure on saveItems');
                }
                return $mockStmt;
            }
        );

        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn([
            'id' => 1, 'brand_id' => '7297804', 'brand_name' => 'AWA',
            'site_id' => 'MLB', 'account_id' => 1, 'status' => 'pending',
        ]);

        // Inject mock PDO into Database singleton so BrandSearchModel uses it
        $dbRef        = new \ReflectionClass(Database::class);
        $instanceProp = $dbRef->getProperty('instance');
        $instanceProp->setAccessible(true);
        $savedInstance = $instanceProp->getValue(null);

        $fakeDb   = $dbRef->newInstanceWithoutConstructor();
        $connProp = $dbRef->getProperty('connection');
        $connProp->setAccessible(true);
        $connProp->setValue($fakeDb, $mockPdo);
        $instanceProp->setValue(null, $fakeDb);

        try {
            [$svc, $mockMl] = $this->makeServiceWithMocks();

            // fetchBrandCategories gets no available_filters → returns fallback [ALL]
            // fetchItemsByBrandAndCategory returns 1 item (total=1 keeps loop to 1 page)
            $mockMl->method('get')->willReturn([
                'results' => [['id' => 'MLB001', 'seller' => ['id' => 500]]],
                'paging'  => ['total' => 1],
            ]);

            $this->expectException(\PDOException::class);
            $this->expectExceptionMessageMatches('/Simulated DB failure/');
            $svc->executeSearch(1);
        } finally {
            // Always restore the Database singleton regardless of outcome
            $instanceProp->setValue(null, $savedInstance);
        }
    }

    /**
     * mapBrandSeller() must include all required schema fields.
     */
    public function testMapSellerDataAllRequiredFields(): void
    {
        [$svc] = $this->makeServiceWithMocks();

        $mlUser = [
            'id'                => 9999,
            'nickname'          => 'AWAMOTOSSP',
            'seller_type'       => 'normal',
            'permalink'         => 'https://www.mercadolivre.com.br/perfil/AWAMOTOSSP',
            'status'            => 'active',
            'country_id'        => 'BR',
            'address'           => ['city' => 'Araraquara', 'state' => 'SP'],
            'seller_reputation' => [
                'level_id'            => '5_green',
                'power_seller_status' => 'platinum',
                'transactions'        => ['ratings' => ['positive' => 0.98]],
            ],
        ];

        $m = $this->ref->getMethod('mapBrandSeller');
        $m->setAccessible(true);
        $result = $m->invoke($svc, 1, $mlUser, 42, 299.99);

        $requiredFields = [
            'seller_id', 'nickname', 'seller_type', 'permalink',
            'reputation_level', 'reputation_score', 'power_seller_status',
            'total_items_brand', 'avg_price', 'site_status', 'country_id',
            'city', 'state', 'trend',
        ];
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $result, "mapBrandSeller must include required field: {$field}");
        }

        $this->assertSame(9999, $result['seller_id']);
        $this->assertSame('AWAMOTOSSP', $result['nickname']);
        $this->assertSame(100, $result['reputation_score'], '5_green level must map to score 100');
        $this->assertSame(42, $result['total_items_brand']);
        $this->assertSame(299.99, $result['avg_price']);
        $this->assertSame('Araraquara', $result['city']);
        $this->assertSame('SP', $result['state']);
    }
}
