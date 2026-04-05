<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\Collection
 * @covers ::collect
 */
class CollectionTest extends TestCase
{
    // ─── Construction ────────────────────────────────────────────────────────

    public function testMakeCreatesCollection(): void
    {
        $c = Collection::make([1, 2, 3]);
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertSame([1, 2, 3], $c->all());
    }

    public function testCollectHelperReturnsCollection(): void
    {
        $c = collect([10, 20]);
        $this->assertInstanceOf(Collection::class, $c);
    }

    // ─── count / isEmpty / isNotEmpty ─────────────────────────────────────────

    public function testCountReturnsItemCount(): void
    {
        $this->assertSame(3, collect([1, 2, 3])->count());
    }

    public function testCountIsZeroForEmpty(): void
    {
        $this->assertSame(0, collect([])->count());
    }

    public function testIsEmptyTrueForEmpty(): void
    {
        $this->assertTrue(collect([])->isEmpty());
    }

    public function testIsEmptyFalseForNonEmpty(): void
    {
        $this->assertFalse(collect([1])->isEmpty());
    }

    public function testIsNotEmptyTrueForNonEmpty(): void
    {
        $this->assertTrue(collect([1])->isNotEmpty());
    }

    // ─── Countable / IteratorAggregate ────────────────────────────────────────

    public function testCountableInterface(): void
    {
        $this->assertCount(4, collect([1, 2, 3, 4]));
    }

    public function testIteratorAggregateInterface(): void
    {
        $sum = 0;
        foreach (collect([1, 2, 3]) as $v) {
            $sum += $v;
        }
        $this->assertSame(6, $sum);
    }

    // ─── first / last / get / has / contains ─────────────────────────────────

    public function testFirstReturnsFirstItem(): void
    {
        $this->assertSame(1, collect([1, 2, 3])->first());
    }

    public function testFirstWithCallbackReturnsMatch(): void
    {
        $result = collect([1, 2, 3, 4])->first(fn($n) => $n > 2);
        $this->assertSame(3, $result);
    }

    public function testFirstReturnsDefaultWhenEmpty(): void
    {
        $this->assertSame('default', collect([])->first(null, 'default'));
    }

    public function testLastReturnsLastItem(): void
    {
        $this->assertSame(3, collect([1, 2, 3])->last());
    }

    public function testLastWithCallback(): void
    {
        $result = collect([1, 2, 3, 4])->last(fn($n) => $n < 3);
        $this->assertSame(2, $result);
    }

    public function testGetByKey(): void
    {
        $c = collect(['a' => 1, 'b' => 2]);
        $this->assertSame(2, $c->get('b'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertSame(99, collect([])->get('x', 99));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->assertTrue(collect(['k' => 'v'])->has('k'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(collect([])->has('k'));
    }

    public function testContainsReturnsTrueWhenValuePresent(): void
    {
        $this->assertTrue(collect([1, 2, 3])->contains(2));
    }

    public function testContainsReturnsFalseWhenAbsent(): void
    {
        $this->assertFalse(collect([1, 2, 3])->contains(5));
    }

    // ─── map ─────────────────────────────────────────────────────────────────

    public function testMapTransformsItems(): void
    {
        $result = collect([1, 2, 3])->map(fn($n) => $n * 2)->all();
        $this->assertSame([2, 4, 6], $result);
    }

    public function testMapPassesKeyToCallback(): void
    {
        $result = collect(['a' => 1])->map(fn($v, $k) => $k . ':' . $v)->all();
        $this->assertSame(['a' => 'a:1'], $result);
    }

    // ─── filter ──────────────────────────────────────────────────────────────

    public function testFilterWithCallback(): void
    {
        $result = collect([1, 2, 3, 4, 5])->filter(fn($n) => $n % 2 === 0)->values()->all();
        $this->assertSame([2, 4], $result);
    }

    public function testFilterWithoutCallbackRemovesFalsy(): void
    {
        $result = collect([0, 1, '', 'a', null, false, true])->filter()->values()->all();
        $this->assertSame([1, 'a', true], $result);
    }

    // ─── reduce ──────────────────────────────────────────────────────────────

    public function testReduceComputesValue(): void
    {
        $sum = collect([1, 2, 3, 4])->reduce(fn($carry, $n) => $carry + $n, 0);
        $this->assertSame(10, $sum);
    }

    // ─── pluck ───────────────────────────────────────────────────────────────

    public function testPluckExtractsColumn(): void
    {
        $rows = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];
        $result = collect($rows)->pluck('name')->all();
        $this->assertSame(['A', 'B'], $result);
    }

    public function testPluckWithIndexBy(): void
    {
        $rows = [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']];
        $result = collect($rows)->pluck('name', 'id')->all();
        $this->assertSame([1 => 'A', 2 => 'B'], $result);
    }

    // ─── groupBy ─────────────────────────────────────────────────────────────

    public function testGroupByField(): void
    {
        $rows = [
            ['type' => 'A', 'v' => 1],
            ['type' => 'B', 'v' => 2],
            ['type' => 'A', 'v' => 3],
        ];
        $grouped = collect($rows)->groupBy('type')->all();
        $this->assertCount(2, $grouped['A']);
        $this->assertCount(1, $grouped['B']);
    }

    public function testGroupByCallback(): void
    {
        $result = collect([1, 2, 3, 4, 5, 6])
            ->groupBy(fn($n) => $n % 2 === 0 ? 'even' : 'odd')
            ->all();

        $this->assertCount(3, $result['odd']);
        $this->assertCount(3, $result['even']);
    }

    // ─── sortBy / sortByDesc ──────────────────────────────────────────────────

    public function testSortByFieldAscending(): void
    {
        $rows = [['n' => 3], ['n' => 1], ['n' => 2]];
        $result = collect($rows)->sortBy('n')->pluck('n')->all();
        $this->assertSame([1, 2, 3], $result);
    }

    public function testSortByDescending(): void
    {
        $result = collect([['p' => 10], ['p' => 30], ['p' => 20]])
            ->sortByDesc('p')->pluck('p')->all();
        $this->assertSame([30, 20, 10], $result);
    }

    public function testSortByCallback(): void
    {
        $result = collect(['banana', 'apple', 'cherry'])
            ->sortBy(fn($s) => $s)
            ->values()->all();
        $this->assertSame(['apple', 'banana', 'cherry'], $result);
    }

    // ─── unique ──────────────────────────────────────────────────────────────

    public function testUniqueDeduplicatesPrimitives(): void
    {
        $result = collect([1, 2, 2, 3, 1])->unique()->all();
        $this->assertSame([1, 2, 3], $result);
    }

    public function testUniqueWithCallback(): void
    {
        $rows = [['id' => 1, 'cat' => 'A'], ['id' => 2, 'cat' => 'B'], ['id' => 3, 'cat' => 'A']];
        $result = collect($rows)->unique(fn($r) => $r['cat'])->values()->all();
        $this->assertCount(2, $result);
    }

    // ─── chunk ───────────────────────────────────────────────────────────────

    public function testChunkSplitsArray(): void
    {
        $chunks = collect([1, 2, 3, 4, 5])->chunk(2)->all();
        $this->assertCount(3, $chunks);
        $this->assertSame([1, 2], $chunks[0]);
    }

    // ─── values / keys ───────────────────────────────────────────────────────

    public function testValuesReindexes(): void
    {
        $result = collect(['a' => 1, 'b' => 2])->values()->all();
        $this->assertSame([1, 2], $result);
    }

    public function testKeysReturnsKeyCollection(): void
    {
        $result = collect(['x' => 1, 'y' => 2])->keys()->all();
        $this->assertSame(['x', 'y'], $result);
    }

    // ─── merge ───────────────────────────────────────────────────────────────

    public function testMergeArrays(): void
    {
        $result = collect([1, 2])->merge([3, 4])->all();
        $this->assertSame([1, 2, 3, 4], $result);
    }

    public function testMergeCollections(): void
    {
        $result = collect([1, 2])->merge(collect([3, 4]))->all();
        $this->assertSame([1, 2, 3, 4], $result);
    }

    // ─── flatten ─────────────────────────────────────────────────────────────

    public function testFlattenFlattensNested(): void
    {
        $result = collect([[1, 2], [3, [4, 5]]])->flatten()->all();
        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    // ─── take / skip / slice ─────────────────────────────────────────────────

    public function testTakeReturnFirstN(): void
    {
        $result = collect([1, 2, 3, 4, 5])->take(3)->all();
        $this->assertSame([1, 2, 3], $result);
    }

    public function testSkipSkipsFirstN(): void
    {
        $result = collect([1, 2, 3, 4, 5])->skip(2)->all();
        $this->assertSame([3, 4, 5], $result);
    }

    public function testSliceWithOffsetAndLength(): void
    {
        $result = collect([1, 2, 3, 4, 5])->slice(1, 3)->all();
        $this->assertSame([2, 3, 4], $result);
    }

    // ─── only / except ───────────────────────────────────────────────────────

    public function testOnlyKeepsSpecifiedKeys(): void
    {
        $rows = [['id' => 1, 'name' => 'A', 'secret' => 'x']];
        $result = collect($rows)->only(['id', 'name'])->first();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayNotHasKey('secret', $result);
    }

    public function testExceptRemovesSpecifiedKeys(): void
    {
        $rows = [['id' => 1, 'secret' => 'x', 'name' => 'A']];
        $result = collect($rows)->except(['secret'])->first();
        $this->assertArrayNotHasKey('secret', $result);
        $this->assertArrayHasKey('name', $result);
    }

    // ─── sum / avg / min / max ────────────────────────────────────────────────

    public function testSumOfPrimitives(): void
    {
        $this->assertSame(15, collect([1, 2, 3, 4, 5])->sum());
    }

    public function testSumByField(): void
    {
        $rows = [['price' => 10.0], ['price' => 20.0], ['price' => 30.5]];
        $this->assertEqualsWithDelta(60.5, collect($rows)->sum('price'), 0.001);
    }

    public function testAvgOfPrimitives(): void
    {
        $this->assertEqualsWithDelta(3.0, collect([1, 2, 3, 4, 5])->avg(), 0.001);
    }

    public function testAvgOfEmptyCollectionIsZero(): void
    {
        $this->assertSame(0.0, collect([])->avg());
    }

    public function testMinAndMaxOfIntegers(): void
    {
        $c = collect([5, 2, 8, 1, 9]);
        $this->assertSame(1, $c->min());
        $this->assertSame(9, $c->max());
    }

    public function testMinAndMaxByField(): void
    {
        $rows = [['score' => 80], ['score' => 95], ['score' => 60]];
        $this->assertSame(60, collect($rows)->min('score'));
        $this->assertSame(95, collect($rows)->max('score'));
    }

    // ─── each ────────────────────────────────────────────────────────────────

    public function testEachIteratesAllItems(): void
    {
        $log = [];
        collect([10, 20, 30])->each(function ($v) use (&$log) {
            $log[] = $v;
        });
        $this->assertSame([10, 20, 30], $log);
    }

    public function testEachReturnsSelf(): void
    {
        $c = collect([1]);
        $this->assertSame($c, $c->each(fn($v) => null));
    }

    // ─── Chaining ────────────────────────────────────────────────────────────

    public function testComplexChain(): void
    {
        $items = [
            ['category' => 'A', 'price' => 100.0, 'active' => true],
            ['category' => 'B', 'price' => 200.0, 'active' => false],
            ['category' => 'A', 'price' => 150.0, 'active' => true],
            ['category' => 'A', 'price' => 50.0,  'active' => true],
        ];

        $result = collect($items)
            ->filter(fn($r) => $r['active'])
            ->filter(fn($r) => $r['category'] === 'A')
            ->sortBy('price')
            ->pluck('price')
            ->all();

        $this->assertSame([50.0, 100.0, 150.0], $result);
    }
}
