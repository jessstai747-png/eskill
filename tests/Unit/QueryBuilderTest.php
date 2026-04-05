<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\QueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Core\QueryBuilder
 * @covers ::db
 */
class QueryBuilderTest extends TestCase
{
    private static \PDO $pdo;

    // ─── Bootstrap SQLite in-memory ───────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new \PDO('sqlite::memory:');
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);

        self::$pdo->exec("
            CREATE TABLE users (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                name    TEXT    NOT NULL,
                age     INTEGER NOT NULL DEFAULT 0,
                email   TEXT,
                active  INTEGER NOT NULL DEFAULT 1
            )
        ");
    }

    protected function setUp(): void
    {
        // Clean slate before every test so inserts/updates/deletes don't bleed
        self::$pdo->exec('DELETE FROM users');
        self::$pdo->exec('DELETE FROM sqlite_sequence WHERE name="users"');

        self::$pdo->exec("INSERT INTO users (name, age, email, active) VALUES ('Alice', 30, 'alice@example.com', 1)");
        self::$pdo->exec("INSERT INTO users (name, age, email, active) VALUES ('Bob',   25, 'bob@example.com',   1)");
        self::$pdo->exec("INSERT INTO users (name, age, email, active) VALUES ('Carol', 40, NULL,                0)");
    }

    private function qb(): QueryBuilder
    {
        return new QueryBuilder(self::$pdo);
    }

    // ─── table() guard ────────────────────────────────────────────────────────

    public function testThrowsWhenNoTableSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->qb()->get();
    }

    // ─── get() ───────────────────────────────────────────────────────────────

    public function testGetReturnsAllRows(): void
    {
        $rows = $this->qb()->table('users')->get();
        $this->assertCount(3, $rows);
    }

    public function testGetReturnsAssocArray(): void
    {
        $row = $this->qb()->table('users')->get()[0];
        $this->assertArrayHasKey('name', $row);
        $this->assertArrayHasKey('age', $row);
    }

    // ─── first() ─────────────────────────────────────────────────────────────

    public function testFirstReturnsOneRow(): void
    {
        $row = $this->qb()->table('users')->first();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('name', $row);
    }

    public function testFirstReturnsNullForEmptyResult(): void
    {
        $row = $this->qb()->table('users')->where('name', 'Nobody')->first();
        $this->assertNull($row);
    }

    // ─── count() ─────────────────────────────────────────────────────────────

    public function testCountAllRows(): void
    {
        $this->assertSame(3, $this->qb()->table('users')->count());
    }

    public function testCountWithWhere(): void
    {
        $this->assertSame(2, $this->qb()->table('users')->where('active', 1)->count());
    }

    // ─── value() ─────────────────────────────────────────────────────────────

    public function testValueReturnsColumnFromFirstRow(): void
    {
        $name = $this->qb()->table('users')->orderBy('id')->value('name');
        $this->assertSame('Alice', $name);
    }

    // ─── select() ────────────────────────────────────────────────────────────

    public function testSelectWithStar(): void
    {
        $row = $this->qb()->table('users')->select('*')->first();
        $this->assertArrayHasKey('email', $row);
    }

    // ─── where() operators ───────────────────────────────────────────────────

    public function testWhereEquals(): void
    {
        $rows = $this->qb()->table('users')->where('name', 'Alice')->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    public function testWhereGreaterThan(): void
    {
        $rows = $this->qb()->table('users')->where('age', 25, '>')->get();
        $this->assertCount(2, $rows); // Alice (30) and Carol (40)
    }

    public function testWhereLessThanOrEqual(): void
    {
        $rows = $this->qb()->table('users')->where('age', 30, '<=')->get();
        $this->assertCount(2, $rows); // Alice (30) and Bob (25)
    }

    public function testWhereLike(): void
    {
        $rows = $this->qb()->table('users')->where('name', '%o%', 'LIKE')->get();
        $this->assertCount(2, $rows); // Bob, Carol
    }

    public function testWhereRejectsInvalidOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb()->table('users')->where('name', 'x', 'DROP');
    }

    // ─── whereRaw() ──────────────────────────────────────────────────────────

    public function testWhereRaw(): void
    {
        $rows = $this->qb()->table('users')
            ->whereRaw('age > :min', [':min' => 25])
            ->get();
        $this->assertCount(2, $rows);
    }

    // ─── whereIn() ───────────────────────────────────────────────────────────

    public function testWhereIn(): void
    {
        $rows = $this->qb()->table('users')
            ->whereIn('name', ['Alice', 'Bob'])
            ->get();
        $this->assertCount(2, $rows);
    }

    public function testWhereInEmptyListReturnsEmpty(): void
    {
        $rows = $this->qb()->table('users')->whereIn('name', [])->get();
        $this->assertCount(0, $rows);
    }

    // ─── whereNull() / whereNotNull() ────────────────────────────────────────

    public function testWhereNull(): void
    {
        $rows = $this->qb()->table('users')->whereNull('email')->get();
        $this->assertCount(1, $rows); // Carol has NULL email
        $this->assertSame('Carol', $rows[0]['name']);
    }

    public function testWhereNotNull(): void
    {
        $rows = $this->qb()->table('users')->whereNotNull('email')->get();
        $this->assertCount(2, $rows);
    }

    // ─── chained WHERE ───────────────────────────────────────────────────────

    public function testMultipleWheres(): void
    {
        $rows = $this->qb()->table('users')
            ->where('active', 1)
            ->where('age', 25, '>')
            ->get();
        $this->assertCount(1, $rows); // Only Alice (30, active)
        $this->assertSame('Alice', $rows[0]['name']);
    }

    // ─── orderBy() / latest() / oldest() ────────────────────────────────────

    public function testOrderByAsc(): void
    {
        $names = array_column($this->qb()->table('users')->orderBy('age')->get(), 'name');
        $this->assertSame(['Bob', 'Alice', 'Carol'], $names);
    }

    public function testOrderByDesc(): void
    {
        $names = array_column($this->qb()->table('users')->orderBy('age', 'DESC')->get(), 'name');
        $this->assertSame(['Carol', 'Alice', 'Bob'], $names);
    }

    public function testOrderByRejectsInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->qb()->table('users')->orderBy('age', 'SIDEWAYS');
    }

    public function testLatestOrdersByDesc(): void
    {
        $row = $this->qb()->table('users')->latest('age')->first();
        $this->assertSame('Carol', $row['name']); // highest age
    }

    // ─── limit() / offset() ──────────────────────────────────────────────────

    public function testLimitRestrictsRows(): void
    {
        $rows = $this->qb()->table('users')->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $rows);
    }

    public function testOffsetSkipsRows(): void
    {
        $rows = $this->qb()->table('users')->orderBy('id')->limit(10)->offset(2)->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Carol', $rows[0]['name']);
    }

    // ─── insert() ────────────────────────────────────────────────────────────

    public function testInsertReturnsLastInsertId(): void
    {
        $id = $this->qb()->table('users')->insert(['name' => 'Dave', 'age' => 22, 'active' => 1]);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertedRowCanBeRead(): void
    {
        $id  = $this->qb()->table('users')->insert(['name' => 'Eve', 'age' => 28, 'active' => 1]);
        $row = $this->qb()->table('users')->where('id', $id)->first();
        $this->assertSame('Eve', $row['name'] ?? null);
    }

    // ─── update() ────────────────────────────────────────────────────────────

    public function testUpdateReturnsAffectedRows(): void
    {
        $affected = $this->qb()->table('users')
            ->where('active', 1)
            ->update(['active' => 0]);
        $this->assertSame(2, $affected);
    }

    public function testUpdateChangesData(): void
    {
        $this->qb()->table('users')->where('name', 'Alice')->update(['age' => 99]);
        $row = $this->qb()->table('users')->where('name', 'Alice')->first();
        $this->assertSame(99, (int) ($row['age'] ?? 0));
    }

    // ─── delete() ────────────────────────────────────────────────────────────

    public function testDeleteReturnsAffectedRows(): void
    {
        $affected = $this->qb()->table('users')->where('name', 'Bob')->delete();
        $this->assertSame(1, $affected);
    }

    public function testDeleteRemovesRows(): void
    {
        $this->qb()->table('users')->where('active', 0)->delete();
        $this->assertSame(2, $this->qb()->table('users')->count());
    }

    // ─── Immutability (clone semantics) ──────────────────────────────────────

    public function testBuilderIsImmutable(): void
    {
        $base      = $this->qb()->table('users');
        $filtered  = $base->where('active', 1);

        // Base query should still return all 3 rows
        $this->assertSame(3, $base->count());
        // Filtered query should return only 2
        $this->assertSame(2, $filtered->count());
    }

    // ─── Complex chain ───────────────────────────────────────────────────────

    public function testComplexChainedQuery(): void
    {
        $rows = $this->qb()->table('users')
            ->select('*')
            ->where('active', 1)
            ->where('age', 20, '>')
            ->orderBy('age', 'DESC')
            ->limit(1)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('Alice', $rows[0]['name']); // highest age among active
    }
}
