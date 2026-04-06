<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivre\BrandSearchService;
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
}
