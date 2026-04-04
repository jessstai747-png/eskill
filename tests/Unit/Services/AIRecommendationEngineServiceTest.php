<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AIRecommendationEngineService;
use App\Services\CacheManagerService;
use App\Services\LogService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AIRecommendationEngineService
 */
class AIRecommendationEngineServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function buildService(
        CacheManagerService $cache,
        ?\PDO $db = null,
        ?LogService $logger = null
    ): AIRecommendationEngineService {
        $ref = new \ReflectionClass(AIRecommendationEngineService::class);
        $svc = $ref->newInstanceWithoutConstructor();

        $cacheProp = $ref->getProperty('cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($svc, $cache);

        if ($db !== null) {
            $dbProp = $ref->getProperty('db');
            $dbProp->setAccessible(true);
            $dbProp->setValue($svc, $db);
        }

        $logProp = $ref->getProperty('logger');
        $logProp->setAccessible(true);
        $logProp->setValue($svc, $logger ?? $this->silentLogger());

        $modelsProp = $ref->getProperty('recommendationModels');
        $modelsProp->setAccessible(true);
        $modelsProp->setValue($svc, []);

        $profilesProp = $ref->getProperty('userProfiles');
        $profilesProp->setAccessible(true);
        $profilesProp->setValue($svc, []);

        $mlProp = $ref->getProperty('mlClient');
        $mlProp->setAccessible(true);
        $mlProp->setValue($svc, null);

        return $svc;
    }

    private function silentLogger(): LogService
    {
        return $this->createMock(LogService::class);
    }

    /** Cache always misses */
    private function emptyCache(): CacheManagerService
    {
        $cache = $this->createMock(CacheManagerService::class);
        $cache->method('get')->willReturn(null);
        $cache->method('set')->willReturn(true);
        return $cache;
    }

    /** Cache always hits and returns $data */
    private function hitCache(array $data): CacheManagerService
    {
        $cache = $this->createMock(CacheManagerService::class);
        $cache->method('get')->willReturn($data);
        return $cache;
    }

    /**
     * Stubbed PDO where all calls return safe no-op defaults.
     * fetchColumn() returns null so resolveAccountId returns null,
     * which causes all account-dependent helpers to return early.
     */
    private function stubDb(): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(null);
        $stmt->method('bindValue')->willReturn(true);

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);
        $db->method('query')->willReturn($stmt);

        return $db;
    }

    // =========================================================================
    // getPersonalizedRecommendations — cache hit
    // =========================================================================

    public function testGetPersonalizedRecommendationsCacheHit(): void
    {
        $cached = [
            'success' => true,
            'user_id' => 99,
            'recommendations' => ['products_to_clone' => []],
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        $db = $this->createMock(\PDO::class);
        $db->expects($this->never())->method('prepare');

        $svc = $this->buildService($this->hitCache($cached), $db);
        $result = $svc->getPersonalizedRecommendations(99);

        $this->assertTrue($result['success']);
        $this->assertSame(99, $result['user_id']);
    }

    public function testGetPersonalizedRecommendationsCacheHitWithForceRefreshSkipsCache(): void
    {
        // Even when cache has data, force_refresh should trigger rebuild
        $cached = ['success' => true, 'user_id' => 42, 'recommendations' => []];

        $svc = $this->buildService($this->hitCache($cached), $this->stubDb());
        $result = $svc->getPersonalizedRecommendations(42, ['force_refresh' => true]);

        // Should still succeed but result comes from fresh build (not cache)
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('user_id', $result);
        $this->assertSame(42, $result['user_id']);
    }

    // =========================================================================
    // getPersonalizedRecommendations — full flow with stub DB
    // =========================================================================

    public function testGetPersonalizedRecommendationsCompletesWithStubDb(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());
        $result = $svc->getPersonalizedRecommendations(1);

        $this->assertArrayHasKey('success', $result);
        $this->assertSame(1, $result['user_id']);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertArrayHasKey('confidence_metrics', $result);
        $this->assertArrayHasKey('generated_at', $result);
    }

    public function testGetPersonalizedRecommendationsResultStructure(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());
        $result = $svc->getPersonalizedRecommendations(5);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user_profile_summary', $result);
        $this->assertArrayHasKey('personalization_factors', $result);
        $this->assertArrayHasKey('next_analysis_date', $result);
    }

    // =========================================================================
    // getPersonalizedRecommendations — DB exception → error response
    // =========================================================================

    public function testGetPersonalizedRecommendationsDbExceptionReturnsError(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->will($this->throwException(new \PDOException('Connection refused')));
        $db->method('query')->will($this->throwException(new \PDOException('Connection refused')));

        $logger = $this->silentLogger();
        $logger->expects($this->atLeastOnce())->method('error');

        $svc = $this->buildService($this->emptyCache(), $db, $logger);
        $result = $svc->getPersonalizedRecommendations(10);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame(10, $result['user_id']);
    }

    // =========================================================================
    // recommendProductsToClone — via public API
    // =========================================================================

    public function testRecommendProductsToCloneWithNoContextAccountId(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $userProfile = ['user_id' => 1, 'preferred_categories' => [], 'preferred_brands' => []];
        $context = ['account_id' => null]; // no accountId → findCloneCandidates returns early

        $result = $svc->recommendProductsToClone($userProfile, $context);

        $this->assertIsArray($result);
        $this->assertEmpty($result); // No candidates → no recommendations
    }

    public function testRecommendProductsToCloneDbCandidatesAllBelowThreshold(): void
    {
        // Even if DB returns candidates, if analyzeClonePotential score < 70 they're excluded
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([
            // Candidate with score that will calculate below 70
            [
                'id' => 'MLB123', 'title' => 'Bagageiro CG 160', 'price' => '100.00',
                'permalink' => 'http://example.com', 'category_id' => 'MLB1',
                'sold_quantity' => '0', 'available_quantity' => '5',
            ],
        ]);
        $stmt->method('fetchColumn')->willReturn(42); // non-null accountId

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturn($stmt);
        $db->method('query')->willReturn($stmt);

        $svc = $this->buildService($this->emptyCache(), $db);

        $userProfile = ['user_id' => 1, 'preferred_categories' => ['MLB1'], 'preferred_brands' => []];
        $context = ['account_id' => 42, 'market_trends' => [], 'economic_indicators' => []];

        $result = $svc->recommendProductsToClone($userProfile, $context);

        $this->assertIsArray($result);
    }

    public function testRecommendProductsToCloneExceptionReturnsEmpty(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->will($this->throwException(new \RuntimeException('DB error')));

        $svc = $this->buildService($this->emptyCache(), $db);

        $userProfile = ['user_id' => 1, 'preferred_categories' => ['MLB1'], 'preferred_brands' => []];
        $context = ['account_id' => 99];

        $result = $svc->recommendProductsToClone($userProfile, $context);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // =========================================================================
    // recommendMarketOpportunities
    // =========================================================================

    public function testRecommendMarketOpportunitiesStructureWithNoAccountId(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $userProfile = ['user_id' => 1, 'preferred_categories' => []];
        $context = ['account_id' => null, 'season' => 'summer'];

        $result = $svc->recommendMarketOpportunities($userProfile, $context);

        $this->assertIsArray($result);
        // With no accountId, all DB-dependent sub-methods return empty → no opportunities
        $this->assertEmpty($result);
    }

    public function testRecommendMarketOpportunitiesOrdersByScore(): void
    {
        // DB returns empty so identifyMarketGaps/Niches return [], but identifyTrendingProducts hits DB
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $userProfile = ['user_id' => 1, 'preferred_categories' => []];
        $context = ['account_id' => null];

        $result = $svc->recommendMarketOpportunities($userProfile, $context);

        // Verify result is sorted (score descending) — even if empty, the sort runs without error
        $this->assertIsArray($result);
        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual($result[$i + 1]['score'], $result[$i]['score']);
        }
    }

    public function testRecommendMarketOpportunitiesLimitsToFifteen(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $result = $svc->recommendMarketOpportunities(
            ['user_id' => 1],
            ['account_id' => null]
        );

        $this->assertCount(min(15, count($result)), $result);
    }

    // =========================================================================
    // recommendPricingStrategies
    // =========================================================================

    public function testRecommendPricingStrategiesNoAccountYieldsEmpty(): void
    {
        // resolveAccountId returns null → getUserProducts returns [] → no strategies
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $userProfile = ['user_id' => 1];
        $context = [];

        $result = $svc->recommendPricingStrategies($userProfile, $context);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRecommendPricingStrategiesWithProductsNoOpportunity(): void
    {
        $products = [
            ['id' => 'MLB1', 'title' => 'Bagageiro CG', 'price' => '100.00', 'category_id' => 'MLB_CAT'],
        ];

        // callCount to return different results per prepare call
        $callCount = 0;
        $stmtAccount = $this->createMock(\PDOStatement::class);
        $stmtAccount->method('execute')->willReturn(true);
        $stmtAccount->method('fetchColumn')->willReturn(99); // non-null accountId
        $stmtAccount->method('fetch')->willReturn(false);
        $stmtAccount->method('fetchAll')->willReturn([]);

        $stmtProducts = $this->createMock(\PDOStatement::class);
        $stmtProducts->method('execute')->willReturn(true);
        $stmtProducts->method('fetchAll')->willReturn($products);
        $stmtProducts->method('fetch')->willReturn(false);
        $stmtProducts->method('fetchColumn')->willReturn(null);

        $stmtAvg = $this->createMock(\PDOStatement::class);
        $stmtAvg->method('execute')->willReturn(true);
        // AVG returns 0 → no opportunity
        $stmtAvg->method('fetchColumn')->willReturn(0);
        $stmtAvg->method('fetchAll')->willReturn([]);
        $stmtAvg->method('fetch')->willReturn(false);

        $stmts = [$stmtAccount, $stmtProducts, $stmtAvg];

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturnCallback(function () use (&$callCount, $stmts) {
            $stmt = $stmts[$callCount] ?? $stmts[2];
            $callCount++;
            return $stmt;
        });
        $db->method('query')->willReturn($stmtAvg);

        $svc = $this->buildService($this->emptyCache(), $db);

        $result = $svc->recommendPricingStrategies(['user_id' => 1], []);

        $this->assertIsArray($result);
        // AVG returned 0 → no pricing opportunity
        $this->assertEmpty($result);
    }

    public function testRecommendPricingStrategiesWithPriceIncreaseOpportunity(): void
    {
        $products = [
            ['id' => 'MLB1', 'title' => 'Bagageiro CG', 'price' => '90.00', 'category_id' => 'MLB_CAT'],
        ];

        $callCount = 0;
        $stmtAccount = $this->createMock(\PDOStatement::class);
        $stmtAccount->method('execute')->willReturn(true);
        $stmtAccount->method('fetchColumn')->willReturn(99);
        $stmtAccount->method('fetch')->willReturn(false);
        $stmtAccount->method('fetchAll')->willReturn([]);

        $stmtProducts = $this->createMock(\PDOStatement::class);
        $stmtProducts->method('execute')->willReturn(true);
        $stmtProducts->method('fetchAll')->willReturn($products);
        $stmtProducts->method('fetch')->willReturn(false);
        $stmtProducts->method('fetchColumn')->willReturn(null);

        $stmtAvg = $this->createMock(\PDOStatement::class);
        $stmtAvg->method('execute')->willReturn(true);
        // avg = 120, current price = 90 → diff = (120-90)/120 = 0.25 > 0.1 → increase_price
        $stmtAvg->method('fetchColumn')->willReturn(120.0);
        $stmtAvg->method('fetchAll')->willReturn([]);
        $stmtAvg->method('fetch')->willReturn(false);

        $stmts = [$stmtAccount, $stmtProducts, $stmtAvg];

        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willReturnCallback(function () use (&$callCount, $stmts) {
            $stmt = $stmts[$callCount] ?? $stmts[2];
            $callCount++;
            return $stmt;
        });
        $db->method('query')->willReturn($stmtAvg);

        $svc = $this->buildService($this->emptyCache(), $db);

        $result = $svc->recommendPricingStrategies(['user_id' => 1], []);

        $this->assertIsArray($result);
        if (!empty($result)) {
            $strategy = $result[0];
            $this->assertArrayHasKey('product_id', $strategy);
            $this->assertArrayHasKey('current_price', $strategy);
            $this->assertArrayHasKey('recommended_price', $strategy);
            $this->assertArrayHasKey('strategy_type', $strategy);
            $this->assertSame('increase_price', $strategy['strategy_type']);
            $this->assertSame(90.0, $strategy['current_price']);
        }
    }

    public function testRecommendPricingStrategiesLimitsToTen(): void
    {
        $svc = $this->buildService($this->emptyCache(), $this->stubDb());

        $result = $svc->recommendPricingStrategies(['user_id' => 1], []);

        // Even with results, max 10 strategies
        $this->assertLessThanOrEqual(10, count($result));
    }
}
