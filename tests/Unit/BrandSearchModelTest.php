<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Models\BrandSearchModel;
use ReflectionClass;
use PDO;
use PDOStatement;

/**
 * @covers \App\Models\BrandSearchModel
 */
class BrandSearchModelTest extends TestCase
{
    private ReflectionClass $ref;
    private PDO $mockPdo;
    private PDOStatement $mockStmt;
    private BrandSearchModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ref = new ReflectionClass(BrandSearchModel::class);
        $this->mockStmt = $this->createMock(PDOStatement::class);
        $this->mockPdo  = $this->createMock(PDO::class);
        $this->model = $this->ref->newInstanceWithoutConstructor();
        $dbProp = $this->ref->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($this->model, $this->mockPdo);
    }

    public function testCreateSearchReturnsInsertId(): void
    {
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->mockPdo->method('lastInsertId')->willReturn('42');
        $id = $this->model->createSearch(['account_id'=>1,'brand_id'=>'7297804','brand_name'=>'AWA','site_id'=>'MLB','category_id'=>null]);
        $this->assertSame(42, $id);
    }

    public function testGetSearchReturnsArrayOnHit(): void
    {
        $expected = ['id'=>7,'brand_name'=>'AWA','status'=>'completed'];
        $this->mockStmt->method('fetch')->willReturn($expected);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->assertSame($expected, $this->model->getSearch(7));
    }

    public function testGetSearchReturnsNullOnMiss(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->assertNull($this->model->getSearch(99));
    }

    public function testGetPendingSearchesReturnsArray(): void
    {
        $rows = [['id'=>1,'status'=>'pending'],['id'=>2,'status'=>'pending']];
        $this->mockStmt->method('fetchAll')->willReturn($rows);
        $this->mockPdo->method('query')->willReturn($this->mockStmt);
        $this->assertCount(2, $this->model->getPendingSearches());
    }

    public function testGetPendingSearchesReturnsEmptyWhenNone(): void
    {
        $this->mockStmt->method('fetchAll')->willReturn([]);
        $this->mockPdo->method('query')->willReturn($this->mockStmt);
        $this->assertSame([], $this->model->getPendingSearches());
    }

    public function testUpdateProgressExecutesUpdate(): void
    {
        $this->mockStmt->expects($this->once())->method('execute')->with($this->arrayHasKey('progress'));
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->model->updateProgress(3, 55, 'running');
    }

    public function testUpdateCompletedPassesTotals(): void
    {
        $params = null;
        $this->mockStmt->method('execute')->willReturnCallback(function(array $p) use (&$params): bool { $params = $p; return true; });
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->model->updateCompleted(10, totalItems: 800, totalSellers: 42);
        $this->assertSame(800, $params['total_items']);
        $this->assertSame(42,  $params['total_sellers']);
        $this->assertSame(10, $params['id']);
    }

    public function testUpdateFailedSavesErrorMessage(): void
    {
        $params = null;
        $this->mockStmt->method('execute')->willReturnCallback(function(array $p) use (&$params): bool { $params = $p; return true; });
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->model->updateFailed(5, 'Rate limit hit');
        $this->assertSame(5, $params['id']);
        
        $this->assertSame('Rate limit hit', $params['error']);
    }

    public function testSaveSellersSkipsEmptyArray(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');
        $this->model->saveSellers(1, []);
    }

    public function testSaveItemsSkipsEmptyArray(): void
    {
        $this->mockPdo->expects($this->never())->method('prepare');
        $this->model->saveItems(1, []);
    }

    public function testCountItemsReturnsInteger(): void
    {
        $this->mockStmt->method('fetchColumn')->willReturn('37');
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->assertSame(37, $this->model->countItems(1));
    }

    public function testCountSellersReturnsInteger(): void
    {
        $this->mockStmt->method('fetchColumn')->willReturn('5');
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $this->assertSame(5, $this->model->countSellersBySearchId(1, []));
    }

    public function testBuildSellerWhereAlwaysIncludesSearchId(): void
    {
        $method = $this->ref->getMethod('buildSellerWhere');
        $method->setAccessible(true);
        [$where, $params] = $method->invoke($this->model, 7, []);
        $this->assertStringContainsString('search_id', $where);
        $this->assertSame(7, $params['search_id']);
    }

    public function testBuildSellerWhereAddsReputationFilter(): void
    {
        $method = $this->ref->getMethod('buildSellerWhere');
        $method->setAccessible(true);
        [$where, $params] = $method->invoke($this->model, 1, ['reputation'=>'gold']);
        $this->assertStringContainsString('reputation_level', $where);
        $this->assertSame('gold', $params['rep']);
    }

    public function testBuildSellerWhereAddsMinItemsFilter(): void
    {
        $method = $this->ref->getMethod('buildSellerWhere');
        $method->setAccessible(true);
        [$where, $params] = $method->invoke($this->model, 1, ['min_items'=>10]);
        $this->assertStringContainsString('total_items_brand', $where);
        $this->assertSame(10, $params['min_items']);
    }

    public function testGetSellerStatsReturnsStructuredArray(): void
    {
        $row = ['total_items' => '15', 'avg_price' => '149.50'];
        $this->mockStmt->method('fetch')->willReturn($row);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $result = $this->model->getSellerStats(1, 3);
        $this->assertSame(15, $result['total_items']);
        $this->assertSame(149.5, $result['avg_price']);
    }

    public function testGetSellerStatsReturnsZeroTotalsOnMiss(): void
    {
        $this->mockStmt->method('fetch')->willReturn(false);
        $this->mockPdo->method('prepare')->willReturn($this->mockStmt);
        $result = $this->model->getSellerStats(1, 99);
        $this->assertSame(0, $result['total_items']);
        $this->assertSame(0.0, $result['avg_price']);
    }

    /**
     * saveSellers() uses INSERT IGNORE, so inserting the same seller twice must not throw.
     * Verifies that the SQL statement contains "INSERT IGNORE".
     */
    public function testSaveSellersIgnoresDuplicates(): void
    {
        $capturedSql = '';
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockPdo->method('prepare')
            ->willReturnCallback(function (string $sql) use (&$capturedSql): PDOStatement {
                $capturedSql = $sql;
                return $this->mockStmt;
            });

        $seller = [
            'seller_id'           => 7001,
            'nickname'            => 'AWA_SHOP',
            'seller_type'         => 'normal',
            'permalink'           => null,
            'reputation_level'    => '5_green',
            'reputation_score'    => 100,
            'power_seller_status' => 'platinum',
            'total_items_brand'   => 5,
            'avg_price'           => 199.90,
            'site_status'         => 'active',
            'country_id'          => 'BR',
            'city'                => 'Araraquara',
            'state'               => 'SP',
            'trend'               => 'stable',
        ];

        // Calling with the same seller twice simulates a duplicate scenario
        $this->model->saveSellers(1, [$seller, $seller]);

        $this->assertStringContainsString('INSERT IGNORE', $capturedSql, 'saveSellers must use INSERT IGNORE to handle duplicates');
    }

    /**
     * getPendingSearches() must filter by status = 'pending'.
     */
    public function testGetPendingSearchesOnlyPending(): void
    {
        $capturedSql = '';
        $this->mockStmt->method('fetchAll')->willReturn([]);
        $this->mockPdo->method('query')
            ->willReturnCallback(function (string $sql) use (&$capturedSql): PDOStatement {
                $capturedSql = $sql;
                return $this->mockStmt;
            });

        $this->model->getPendingSearches();

        $this->assertStringContainsString("status = 'pending'", $capturedSql, "getPendingSearches must filter by status = 'pending'");
    }
}
