<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\LazyLoadService;

/**
 * @covers \App\Services\LazyLoadService
 */
class LazyLoadServiceTest extends TestCase
{
    private LazyLoadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LazyLoadService();
    }

    public function testPaginateFirstPage(): void
    {
        $loader = function (array $filters): array {
            return [
                "results" => array_slice(range(1, 50), $filters["offset"], $filters["limit"]),
                "paging" => ["total" => 50],
            ];
        };
        $result = $this->service->paginate($loader, 1, 10);
        $this->assertArrayHasKey("data", $result);
        $this->assertArrayHasKey("pagination", $result);
        $this->assertArrayHasKey("has_more", $result);
        $this->assertCount(10, $result["data"]);
        $this->assertSame(1, $result["pagination"]["current_page"]);
        $this->assertSame(10, $result["pagination"]["per_page"]);
        $this->assertSame(50, $result["pagination"]["total"]);
        $this->assertTrue($result["has_more"]);
    }

    public function testPaginateLastPage(): void
    {
        $loader = function (array $filters): array {
            return [
                "results" => array_slice(range(1, 25), $filters["offset"], $filters["limit"]),
                "paging" => ["total" => 25],
            ];
        };
        $result = $this->service->paginate($loader, 3, 10);
        $this->assertCount(5, $result["data"]);
        $this->assertSame(3, $result["pagination"]["current_page"]);
        $this->assertFalse($result["has_more"]);
    }

    public function testPaginateHandlesError(): void
    {
        $loader = function (array $filters): array {
            return ["error" => "API failure"];
        };
        $result = $this->service->paginate($loader, 1, 10);
        $this->assertArrayHasKey("error", $result);
        $this->assertSame("API failure", $result["error"]);
    }

    public function testPaginatePassesFilters(): void
    {
        $receivedFilters = null;
        $loader = function (array $filters) use (&$receivedFilters): array {
            $receivedFilters = $filters;
            return ["results" => [], "paging" => ["total" => 0]];
        };
        $this->service->paginate($loader, 2, 15, ["status" => "active"]);
        $this->assertSame(15, $receivedFilters["offset"]);
        $this->assertSame(15, $receivedFilters["limit"]);
        $this->assertSame("active", $receivedFilters["status"]);
    }

    public function testPaginateCalculatesLastPage(): void
    {
        $loader = function (array $filters): array {
            return ["results" => [1, 2, 3], "paging" => ["total" => 100]];
        };
        $result = $this->service->paginate($loader, 1, 10);
        $this->assertEquals(10, $result["pagination"]["last_page"]);
    }

    public function testPaginateFromTo(): void
    {
        $loader = function (array $filters): array {
            return ["results" => range(1, 10), "paging" => ["total" => 50]];
        };
        $result = $this->service->paginate($loader, 2, 10);
        $this->assertSame(11, $result["pagination"]["from"]);
        $this->assertSame(20, $result["pagination"]["to"]);
    }

    public function testLoadMoreBasic(): void
    {
        $loader = function (array $filters): array {
            return [
                "items" => array_slice(range(1, 100), $filters["offset"], $filters["limit"]),
                "paging" => ["total" => 100],
            ];
        };
        $result = $this->service->loadMore($loader, 0, 20);
        $this->assertArrayHasKey("items", $result);
        $this->assertCount(20, $result["items"]);
        $this->assertSame(0, $result["offset"]);
        $this->assertSame(20, $result["limit"]);
        $this->assertSame(100, $result["total"]);
        $this->assertTrue($result["has_more"]);
        $this->assertSame(20, $result["next_offset"]);
    }

    public function testLoadMoreAtEnd(): void
    {
        $loader = function (array $filters): array {
            return [
                "items" => [98, 99, 100],
                "paging" => ["total" => 100],
            ];
        };
        $result = $this->service->loadMore($loader, 97, 10);
        $this->assertFalse($result["has_more"]);
        $this->assertNull($result["next_offset"]);
    }

    public function testLoadMoreHandlesError(): void
    {
        $loader = function (array $filters): array {
            return ["error" => "timeout"];
        };
        $result = $this->service->loadMore($loader, 0, 20);
        $this->assertArrayHasKey("error", $result);
    }

    public function testChunkProcessesAllData(): void
    {
        $loader = function (array $filters): array {
            $all = range(1, 25);
            $items = array_slice($all, $filters["offset"], $filters["limit"]);
            return [
                "items" => $items,
                "paging" => ["total" => 25],
            ];
        };
        $result = $this->service->chunk($loader, 10);
        $this->assertArrayHasKey("total_processed", $result);
        $this->assertArrayHasKey("items", $result);
        $this->assertSame(25, $result["total_processed"]);
        $this->assertCount(25, $result["items"]);
    }

    public function testChunkWithProcessor(): void
    {
        $loader = function (array $filters): array {
            return [
                "items" => [1, 2, 3],
                "paging" => ["total" => 3],
            ];
        };
        $processor = function (array $items): array {
            return array_map(fn($i) => $i * 10, $items);
        };
        $result = $this->service->chunk($loader, 100, $processor);
        $this->assertSame([10, 20, 30], $result["items"]);
    }

    public function testChunkHandlesError(): void
    {
        $loader = function (array $filters): array {
            return ["error" => "connection lost"];
        };
        $result = $this->service->chunk($loader, 10);
        $this->assertArrayHasKey("error", $result);
    }

    public function testChunkEmptyData(): void
    {
        $loader = function (array $filters): array {
            return ["items" => [], "paging" => ["total" => 0]];
        };
        $result = $this->service->chunk($loader, 10);
        $this->assertSame(0, $result["total_processed"]);
        $this->assertEmpty($result["items"]);
    }
}
