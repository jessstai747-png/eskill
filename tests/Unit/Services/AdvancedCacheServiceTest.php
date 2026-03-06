<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\AdvancedCacheService;

/**
 * @covers \App\Services\AdvancedCacheService
 */
class AdvancedCacheServiceTest extends TestCase
{
    private AdvancedCacheService $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new AdvancedCacheService('memory');
    }

    public function testSetAndGet(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertSame('value1', $this->cache->get('key1'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertNull($this->cache->get('nonexistent'));
        $this->assertSame('default', $this->cache->get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('key1', 'value1');
        $this->assertTrue($this->cache->has('key1'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('nonexistent'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->delete('key1');
        $this->assertNull($this->cache->get('key1'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('key1', 'val1');
        $this->cache->set('key2', 'val2');
        $this->cache->clear();

        $this->assertNull($this->cache->get('key1'));
        $this->assertNull($this->cache->get('key2'));
    }

    public function testSetWithNullTTLNeverExpires(): void
    {
        $this->cache->set('permanent', 'forever', null);
        $this->assertSame('forever', $this->cache->get('permanent'));
    }

    public function testRememberReturnsCallbackResult(): void
    {
        $result = $this->cache->remember('computed', function () {
            return 'computed_value';
        });
        $this->assertSame('computed_value', $result);
    }

    public function testRememberReturnsCachedOnSecondCall(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;
            return 'result';
        };

        $this->cache->remember('key', $callback);
        $this->cache->remember('key', $callback);

        $this->assertSame(1, $calls);
    }

    public function testRememberWithTtlSignature(): void
    {
        $result = $this->cache->remember('key', 3600, function () {
            return 'ttl_result';
        });
        $this->assertSame('ttl_result', $result);
    }

    public function testGetStatsReturnsExpectedKeys(): void
    {
        $stats = $this->cache->getStats();

        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertArrayHasKey('deletes', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertArrayHasKey('memory_items', $stats);
    }

    public function testGetStatsCountsHitsAndMisses(): void
    {
        $this->cache->set('key', 'val');
        $this->cache->get('key');      // hit
        $this->cache->get('missing');   // miss

        $stats = $this->cache->getStats();
        $this->assertSame(1, $stats['hits']);
        $this->assertSame(1, $stats['misses']);
        $this->assertSame(1, $stats['writes']);
    }

    public function testCacheStoresAndRetrievesArrays(): void
    {
        $data = ['name' => 'AWA Motos', 'products' => [1, 2, 3]];
        $this->cache->set('array_key', $data);
        $this->assertSame($data, $this->cache->get('array_key'));
    }

    public function testCacheStoresAndRetrievesIntegers(): void
    {
        $this->cache->set('int_key', 42);
        $this->assertSame(42, $this->cache->get('int_key'));
    }

    public function testDeleteReturnsFalseForMissingKey(): void
    {
        $result = $this->cache->delete('nonexistent');
        $this->assertFalse($result);
    }

    public function testCacheStoresBoolean(): void
    {
        $this->cache->set('bool_key', true);
        $this->assertTrue($this->cache->get('bool_key'));
    }

    public function testHasAfterDelete(): void
    {
        $this->cache->set('key', 'val');
        $this->assertTrue($this->cache->has('key'));
        $this->cache->delete('key');
        $this->assertFalse($this->cache->has('key'));
    }

    public function testClearReturnsDeleteCount(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $count = $this->cache->clear();
        $this->assertIsInt($count);
    }
}
