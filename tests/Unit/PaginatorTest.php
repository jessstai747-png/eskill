<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Paginator;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\Paginator
 */
class PaginatorTest extends TestCase
{
    // ─── Constructor ─────────────────────────────────────────────────────────

    public function testDefaultValues(): void
    {
        $p = new Paginator();
        $this->assertSame(1, $p->page());
        $this->assertSame(20, $p->perPage());
        $this->assertSame(0, $p->total());
    }

    public function testPageClampedToMinimumOne(): void
    {
        $p = new Paginator(page: -5);
        $this->assertSame(1, $p->page());
    }

    public function testPerPageClampedToMax500(): void
    {
        $p = new Paginator(perPage: 9999);
        $this->assertSame(500, $p->perPage());
    }

    public function testPerPageClampedToMin1(): void
    {
        $p = new Paginator(perPage: 0);
        $this->assertSame(1, $p->perPage());
    }

    public function testTotalClampedToMin0(): void
    {
        $p = new Paginator(total: -100);
        $this->assertSame(0, $p->total());
    }

    // ─── limit / offset ───────────────────────────────────────────────────────

    public function testOffsetForFirstPage(): void
    {
        $p = new Paginator(page: 1, perPage: 20);
        $this->assertSame(0, $p->offset());
    }

    public function testOffsetForSecondPage(): void
    {
        $p = new Paginator(page: 2, perPage: 20);
        $this->assertSame(20, $p->offset());
    }

    public function testOffsetForThirdPage(): void
    {
        $p = new Paginator(page: 3, perPage: 15);
        $this->assertSame(30, $p->offset());
    }

    public function testLimitEqualsPerPage(): void
    {
        $p = new Paginator(perPage: 50);
        $this->assertSame(50, $p->limit());
    }

    // ─── lastPage ─────────────────────────────────────────────────────────────

    public function testLastPageIsOneWhenTotalIsZero(): void
    {
        $p = new Paginator(total: 0);
        $this->assertSame(1, $p->lastPage());
    }

    public function testLastPageRoundsCeilCorrectly(): void
    {
        $p = new Paginator(perPage: 20, total: 41);
        $this->assertSame(3, $p->lastPage());
    }

    public function testLastPageExactDivision(): void
    {
        $p = new Paginator(perPage: 20, total: 40);
        $this->assertSame(2, $p->lastPage());
    }

    // ─── hasPrev / hasNext ────────────────────────────────────────────────────

    public function testHasPrevFalseOnFirstPage(): void
    {
        $p = new Paginator(page: 1, total: 100);
        $this->assertFalse($p->hasPrev());
    }

    public function testHasPrevTrueOnSecondPage(): void
    {
        $p = new Paginator(page: 2, total: 100);
        $this->assertTrue($p->hasPrev());
    }

    public function testHasNextTrueWhenMorePages(): void
    {
        $p = new Paginator(page: 1, perPage: 20, total: 50);
        $this->assertTrue($p->hasNext());
    }

    public function testHasNextFalseOnLastPage(): void
    {
        $p = new Paginator(page: 3, perPage: 20, total: 50);
        $this->assertFalse($p->hasNext());
    }

    // ─── setTotal ─────────────────────────────────────────────────────────────

    public function testSetTotalUpdatesTotal(): void
    {
        $p = new Paginator();
        $p->setTotal(200);
        $this->assertSame(200, $p->total());
    }

    public function testSetTotalReturnsSelf(): void
    {
        $p = new Paginator();
        $this->assertSame($p, $p->setTotal(10));
    }

    // ─── slice ────────────────────────────────────────────────────────────────

    public function testSliceReturnsCorrectWindow(): void
    {
        $items = range(1, 50);
        $p = new Paginator(page: 2, perPage: 10);
        $slice = $p->slice($items);

        $this->assertCount(10, $slice);
        $this->assertSame(11, array_values($slice)[0]);
        $this->assertSame(20, array_values($slice)[9]);
    }

    public function testSliceUpdatesTotalToFullCount(): void
    {
        $items = range(1, 37);
        $p = new Paginator(page: 1, perPage: 10);
        $p->slice($items);

        $this->assertSame(37, $p->total());
    }

    public function testSliceLastPageMayBePartial(): void
    {
        $items = range(1, 25);
        $p = new Paginator(page: 3, perPage: 10);
        $slice = $p->slice($items);

        $this->assertCount(5, $slice);
    }

    // ─── meta() ───────────────────────────────────────────────────────────────

    public function testMetaContainsAllKeys(): void
    {
        $p = new Paginator(page: 2, perPage: 20, total: 100);
        $meta = $p->meta();

        $this->assertArrayHasKey('page', $meta);
        $this->assertArrayHasKey('per_page', $meta);
        $this->assertArrayHasKey('total', $meta);
        $this->assertArrayHasKey('pages', $meta);
        $this->assertArrayHasKey('has_prev', $meta);
        $this->assertArrayHasKey('has_next', $meta);
    }

    public function testMetaValuesAreCorrect(): void
    {
        $p = new Paginator(page: 2, perPage: 20, total: 100);
        $meta = $p->meta();

        $this->assertSame(2, $meta['page']);
        $this->assertSame(20, $meta['per_page']);
        $this->assertSame(100, $meta['total']);
        $this->assertSame(5, $meta['pages']);
        $this->assertTrue($meta['has_prev']);
        $this->assertTrue($meta['has_next']);
    }

    // ─── fromRequest() ────────────────────────────────────────────────────────

    public function testFromRequestReadsPageAndPerPage(): void
    {
        $_GET['page']     = '3';
        $_GET['per_page'] = '50';

        $request = new Request();
        $p = Paginator::fromRequest($request, total: 200);

        $this->assertSame(3, $p->page());
        $this->assertSame(50, $p->perPage());

        unset($_GET['page'], $_GET['per_page']);
    }

    public function testFromRequestUsesDefaultPerPage(): void
    {
        $_GET['page'] = '1';
        unset($_GET['per_page'], $_GET['limit']);

        $request = new Request();
        $p = Paginator::fromRequest($request, total: 100, defaultPerPage: 25);

        $this->assertSame(25, $p->perPage());

        unset($_GET['page']);
    }

    public function testFromRequestClampsPerPageToMax(): void
    {
        $_GET['per_page'] = '9999';

        $request = new Request();
        $p = Paginator::fromRequest($request, total: 100, maxPerPage: 100);

        $this->assertSame(100, $p->perPage());

        unset($_GET['per_page']);
    }

    public function testFromRequestFallsBackToLimitParam(): void
    {
        unset($_GET['per_page']);
        $_GET['limit'] = '30';

        $request = new Request();
        $p = Paginator::fromRequest($request);

        $this->assertSame(30, $p->perPage());

        unset($_GET['limit']);
    }
}
