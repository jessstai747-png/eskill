<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AI\Core;

use App\Services\AI\Core\CacheStrategy;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AI\Core\CacheStrategy
 */
class CacheStrategyTest extends TestCase
{
    // ─── getTTL ───────────────────────────────────────────

    public function testGetTTLKnownType(): void
    {
        $this->assertSame(7200, CacheStrategy::getTTL("ai_seo_analysis"));
    }

    public function testGetTTLCategoryAttributes(): void
    {
        $this->assertSame(604800, CacheStrategy::getTTL("category_attributes"));
    }

    public function testGetTTLUnknownTypeFallsBackToDefault(): void
    {
        $this->assertSame(3600, CacheStrategy::getTTL("unknown_type"));
    }

    public function testGetTTLPriceData(): void
    {
        $this->assertSame(1800, CacheStrategy::getTTL("price_data"));
    }

    // ─── getNamespace ─────────────────────────────────────

    public function testGetNamespaceAI(): void
    {
        $this->assertSame("ai_seo", CacheStrategy::getNamespace("ai"));
    }

    public function testGetNamespaceCompetitors(): void
    {
        $this->assertSame("seo_competition", CacheStrategy::getNamespace("competitors"));
    }

    public function testGetNamespaceConfig(): void
    {
        $this->assertSame("seo_config", CacheStrategy::getNamespace("config"));
    }

    public function testGetNamespaceUnknownReturnsDefault(): void
    {
        $this->assertSame("default", CacheStrategy::getNamespace("nonexistent"));
    }

    // ─── generateKey ──────────────────────────────────────

    public function testGenerateKeyProducesConsistentHash(): void
    {
        $key1 = CacheStrategy::generateKey("test", ["a" => 1, "b" => 2]);
        $key2 = CacheStrategy::generateKey("test", ["a" => 1, "b" => 2]);
        $this->assertSame($key1, $key2);
    }

    public function testGenerateKeyIgnoresInsertionOrder(): void
    {
        $key1 = CacheStrategy::generateKey("prefix", ["z" => 1, "a" => 2]);
        $key2 = CacheStrategy::generateKey("prefix", ["a" => 2, "z" => 1]);
        $this->assertSame($key1, $key2, "Keys should be the same regardless of insertion order (ksort)");
    }

    public function testGenerateKeyDifferentDataProducesDifferentKeys(): void
    {
        $key1 = CacheStrategy::generateKey("prefix", ["a" => 1]);
        $key2 = CacheStrategy::generateKey("prefix", ["a" => 2]);
        $this->assertNotSame($key1, $key2);
    }

    public function testGenerateKeyDifferentPrefixProducesDifferentKeys(): void
    {
        $key1 = CacheStrategy::generateKey("foo", ["a" => 1]);
        $key2 = CacheStrategy::generateKey("bar", ["a" => 1]);
        $this->assertNotSame($key1, $key2);
    }

    public function testGenerateKeyFormat(): void
    {
        $key = CacheStrategy::generateKey("myprefix", ["x" => 1]);
        $this->assertStringStartsWith("myprefix_", $key);
        $this->assertMatchesRegularExpression("/^myprefix_[a-f0-9]{32}$/", $key);
    }

    // ─── generateTaggedKey ────────────────────────────────

    public function testGenerateTaggedKeyFormat(): void
    {
        $key = CacheStrategy::generateTaggedKey("seo", "product_", "123", ["x" => 1]);
        $this->assertStringStartsWith("seo_product_123_", $key);
        $this->assertMatchesRegularExpression("/^seo_product_123_[a-f0-9]{32}$/", $key);
    }

    public function testGenerateTaggedKeyDeterministic(): void
    {
        $key1 = CacheStrategy::generateTaggedKey("p", "t_", "id", ["a" => 1]);
        $key2 = CacheStrategy::generateTaggedKey("p", "t_", "id", ["a" => 1]);
        $this->assertSame($key1, $key2);
    }

    // ─── shouldInvalidate ─────────────────────────────────

    public function testShouldInvalidateSameDataReturnsFalse(): void
    {
        $data = ["title" => "foo", "price" => 10];
        $this->assertFalse(CacheStrategy::shouldInvalidate($data, $data));
    }

    public function testShouldInvalidateDifferentDataReturnsTrue(): void
    {
        $old = ["title" => "foo"];
        $new = ["title" => "bar"];
        $this->assertTrue(CacheStrategy::shouldInvalidate($old, $new));
    }

    public function testShouldInvalidateWatchedFieldChanged(): void
    {
        $old = ["title" => "foo", "price" => 10];
        $new = ["title" => "foo", "price" => 20];
        $this->assertTrue(CacheStrategy::shouldInvalidate($old, $new, ["price"]));
    }

    public function testShouldInvalidateWatchedFieldUnchanged(): void
    {
        $old = ["title" => "foo", "price" => 10];
        $new = ["title" => "bar", "price" => 10];
        $this->assertFalse(
            CacheStrategy::shouldInvalidate($old, $new, ["price"]),
            "Only watched field (price) should matter"
        );
    }

    public function testShouldInvalidateNestedDotNotation(): void
    {
        $old = ["meta" => ["score" => 80]];
        $new = ["meta" => ["score" => 90]];
        $this->assertTrue(CacheStrategy::shouldInvalidate($old, $new, ["meta.score"]));
    }

    public function testShouldInvalidateNestedDotNotationUnchanged(): void
    {
        $old = ["meta" => ["score" => 80, "rank" => 1]];
        $new = ["meta" => ["score" => 80, "rank" => 5]];
        $this->assertFalse(CacheStrategy::shouldInvalidate($old, $new, ["meta.score"]));
    }

    public function testShouldInvalidateMissingFieldBothSides(): void
    {
        $old = ["a" => 1];
        $new = ["a" => 2];
        $this->assertFalse(
            CacheStrategy::shouldInvalidate($old, $new, ["nonexistent"]),
            "Both missing = both null = not changed"
        );
    }

    public function testShouldInvalidateFieldAddedToNew(): void
    {
        $old = [];
        $new = ["title" => "new"];
        $this->assertTrue(CacheStrategy::shouldInvalidate($old, $new, ["title"]));
    }

    // ─── getWarmingPolicy ─────────────────────────────────

    public function testGetWarmingPolicyKnownType(): void
    {
        $policy = CacheStrategy::getWarmingPolicy("category_keywords");
        $this->assertTrue($policy["enabled"]);
        $this->assertSame("high", $policy["priority"]);
        $this->assertArrayHasKey("schedule", $policy);
        $this->assertArrayHasKey("batch_size", $policy);
    }

    public function testGetWarmingPolicyUnknownTypeDisabled(): void
    {
        $policy = CacheStrategy::getWarmingPolicy("nonexistent");
        $this->assertFalse($policy["enabled"]);
        $this->assertSame("low", $policy["priority"]);
    }

    public function testGetWarmingPolicyCompetitorData(): void
    {
        $policy = CacheStrategy::getWarmingPolicy("competitor_data");
        $this->assertTrue($policy["enabled"]);
        $this->assertSame("medium", $policy["priority"]);
        $this->assertSame(30, $policy["batch_size"]);
    }

    // ─── getCompressionStrategy ───────────────────────────

    public function testGetCompressionStrategyCompressible(): void
    {
        $strategy = CacheStrategy::getCompressionStrategy("ai_seo_analysis");
        $this->assertTrue($strategy["enabled"]);
        $this->assertSame("gzip", $strategy["algorithm"]);
        $this->assertSame(6, $strategy["level"]);
    }

    public function testGetCompressionStrategyNonCompressible(): void
    {
        $strategy = CacheStrategy::getCompressionStrategy("price_data");
        $this->assertFalse($strategy["enabled"]);
        $this->assertSame("gzip", $strategy["algorithm"]);
    }

    // ─── getEvictionPriority ──────────────────────────────

    public function testGetEvictionPriorityHighPriority(): void
    {
        $this->assertSame(1, CacheStrategy::getEvictionPriority("category_attributes"));
    }

    public function testGetEvictionPriorityLowPriority(): void
    {
        $this->assertSame(10, CacheStrategy::getEvictionPriority("price_data"));
    }

    public function testGetEvictionPriorityUnknownDefaultsMedium(): void
    {
        $this->assertSame(5, CacheStrategy::getEvictionPriority("unknown"));
    }

    // ─── getConfig ────────────────────────────────────────

    public function testGetConfigReturnsAllKeys(): void
    {
        $config = CacheStrategy::getConfig("ai_seo_analysis");
        $this->assertArrayHasKey("ttl", $config);
        $this->assertArrayHasKey("namespace", $config);
        $this->assertArrayHasKey("warming_policy", $config);
        $this->assertArrayHasKey("compression", $config);
        $this->assertArrayHasKey("eviction_priority", $config);
        $this->assertArrayHasKey("versioning_enabled", $config);
    }

    public function testGetConfigTTLMatchesDirectCall(): void
    {
        $config = CacheStrategy::getConfig("competitor_data");
        $this->assertSame(CacheStrategy::getTTL("competitor_data"), $config["ttl"]);
    }

    public function testGetConfigNamespaceAIType(): void
    {
        $config = CacheStrategy::getConfig("ai_seo_analysis");
        $this->assertSame("ai_seo", $config["namespace"]);
    }

    public function testGetConfigNamespaceKeywordType(): void
    {
        $config = CacheStrategy::getConfig("category_keywords");
        $this->assertSame("keywords", $config["namespace"]);
    }

    public function testGetConfigNamespaceCompetitorType(): void
    {
        $config = CacheStrategy::getConfig("competitor_data");
        $this->assertSame("seo_competition", $config["namespace"]);
    }

    public function testGetConfigNamespaceMarketType(): void
    {
        $config = CacheStrategy::getConfig("market_trends");
        $this->assertSame("market", $config["namespace"]);
    }

    public function testGetConfigNamespaceOptimizationType(): void
    {
        $config = CacheStrategy::getConfig("optimization_results");
        $this->assertSame("optimization", $config["namespace"]);
    }

    public function testGetConfigNamespaceAnalyticsType(): void
    {
        $config = CacheStrategy::getConfig("performance_metrics");
        $this->assertSame("analytics", $config["namespace"]);
    }

    public function testGetConfigNamespaceStaticFallback(): void
    {
        $config = CacheStrategy::getConfig("category_attributes");
        $this->assertSame("static", $config["namespace"]);
    }

    public function testGetConfigVersioningEnabled(): void
    {
        $config = CacheStrategy::getConfig("ai_seo_analysis");
        $this->assertTrue($config["versioning_enabled"]);
    }

    public function testGetConfigVersioningDisabled(): void
    {
        $config = CacheStrategy::getConfig("price_data");
        $this->assertFalse($config["versioning_enabled"]);
    }
}
