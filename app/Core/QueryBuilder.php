<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Fluent SQL query builder wrapping PDO.
 *
 * Usage:
 *   $rows = (new QueryBuilder($pdo))
 *       ->table('notifications')
 *       ->where('user_id', $userId)
 *       ->where('is_read', 0)
 *       ->orderBy('created_at', 'DESC')
 *       ->limit(20)
 *       ->get();
 */
class QueryBuilder
{
    private \PDO $pdo;

    private string $table = '';

    /** @var string|string[] */
    private string|array $selectColumns = '*';

    /** @var array<int, array{clause: string, bindings: array<int|string, mixed>}> */
    private array $wheres = [];

    /** @var array<int, string> */
    private array $orderBys = [];

    private ?int $limitVal = null;

    private ?int $offsetVal = null;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Flavour factory (forwards to new instance) ───────────────────────────

    public function table(string $table): static
    {
        $clone = clone $this;
        $clone->table = $table;
        return $clone;
    }

    // ─── SELECT projection ────────────────────────────────────────────────────

    /**
     * @param string|string[] $columns
     */
    public function select(string|array $columns): static
    {
        $clone = clone $this;
        $clone->selectColumns = $columns;
        return $clone;
    }

    // ─── WHERE clauses ────────────────────────────────────────────────────────

    public function where(string $column, mixed $value, string $operator = '='): static
    {
        $operator = strtoupper(trim($operator));
        $allowed  = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];
        if (!in_array($operator, $allowed, true)) {
            throw new \InvalidArgumentException("Unsupported WHERE operator: {$operator}");
        }

        $clone = clone $this;
        $placeholder = ':w_' . count($clone->wheres);
        $clone->wheres[] = [
            'clause'   => "`{$column}` {$operator} {$placeholder}",
            'bindings' => [$placeholder => $value],
        ];
        return $clone;
    }

    /** @param array<int|string, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = []): static
    {
        $clone = clone $this;
        $clone->wheres[] = ['clause' => $sql, 'bindings' => $bindings];
        return $clone;
    }

    /** @param array<int, mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        if ($values === []) {
            // impossible match: add clause that always evaluates false
            return $this->whereRaw('1=0');
        }

        $clone = clone $this;
        $placeholders = [];
        $bindings     = [];
        foreach ($values as $i => $val) {
            $key             = ':wi_' . count($clone->wheres) . '_' . $i;
            $placeholders[]  = $key;
            $bindings[$key]  = $val;
        }
        $clone->wheres[] = [
            'clause'   => "`{$column}` IN (" . implode(',', $placeholders) . ')',
            'bindings' => $bindings,
        ];
        return $clone;
    }

    public function whereNull(string $column): static
    {
        $clone = clone $this;
        $clone->wheres[] = ['clause' => "`{$column}` IS NULL", 'bindings' => []];
        return $clone;
    }

    public function whereNotNull(string $column): static
    {
        $clone = clone $this;
        $clone->wheres[] = ['clause' => "`{$column}` IS NOT NULL", 'bindings' => []];
        return $clone;
    }

    // ─── ORDER / LIMIT / OFFSET ───────────────────────────────────────────────

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Direction must be ASC or DESC, got: {$direction}");
        }

        $clone = clone $this;
        $clone->orderBys[] = "`{$column}` {$direction}";
        return $clone;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'ASC');
    }

    public function limit(int $n): static
    {
        $clone = clone $this;
        $clone->limitVal = $n;
        return $clone;
    }

    public function offset(int $n): static
    {
        $clone = clone $this;
        $clone->offsetVal = $n;
        return $clone;
    }

    // ─── Terminal READ operations ─────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        $stmt = $this->executeSelect($this->buildSelectSql());
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $row = $this->limit(1)->executeSelect($this->limit(1)->buildSelectSql())->fetch();
        return $row !== false ? $row : null;
    }

    public function count(): int
    {
        $clone          = clone $this;
        $clone->selectColumns = 'COUNT(*) AS _count';
        $clone->limitVal      = null;
        $clone->offsetVal     = null;
        $clone->orderBys      = [];

        $row = $clone->executeSelect($clone->buildSelectSql())->fetch();
        return $row ? (int) $row['_count'] : 0;
    }

    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        return $row ? array_values($row)[0] : null;
    }

    // ─── Terminal WRITE operations ────────────────────────────────────────────

    /**
     * @param array<string, mixed> $data
     * @return int Last insert ID
     */
    public function insert(array $data): int
    {
        $this->assertTable();

        $columns      = array_keys($data);
        $placeholders = array_map(fn(string $c) => ':ins_' . $c, $columns);
        $bindings     = array_combine($placeholders, array_values($data));

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(',', array_map(fn(string $c) => "`{$c}`", $columns)),
            implode(',', $placeholders),
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @return int Affected rows count
     */
    public function update(array $data): int
    {
        $this->assertTable();

        $sets     = [];
        $bindings = [];

        foreach ($data as $col => $val) {
            $key            = ':upd_' . $col;
            $sets[]         = "`{$col}` = {$key}";
            $bindings[$key] = $val;
        }

        foreach ($this->wheres as $w) {
            $bindings = array_merge($bindings, $w['bindings']);
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s%s',
            $this->table,
            implode(', ', $sets),
            $this->buildWhereSql(),
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    /** @return int Affected rows count */
    public function delete(): int
    {
        $this->assertTable();

        $bindings = [];
        foreach ($this->wheres as $w) {
            $bindings = array_merge($bindings, $w['bindings']);
        }

        $sql  = 'DELETE FROM `' . $this->table . '`' . $this->buildWhereSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt->rowCount();
    }

    // ─── SQL building helpers ─────────────────────────────────────────────────

    private function buildSelectSql(): string
    {
        $this->assertTable();

        $cols = is_array($this->selectColumns)
            ? implode(', ', array_map(fn(string $c) => "`{$c}`", $this->selectColumns))
            : (string) $this->selectColumns;

        $sql = "SELECT {$cols} FROM `{$this->table}`";
        $sql .= $this->buildWhereSql();
        $sql .= $this->buildOrderSql();
        $sql .= $this->buildLimitSql();
        return $sql;
    }

    private function buildWhereSql(): string
    {
        if ($this->wheres === []) {
            return '';
        }
        $clauses = array_map(fn(array $w) => $w['clause'], $this->wheres);
        return ' WHERE ' . implode(' AND ', $clauses);
    }

    private function buildOrderSql(): string
    {
        if ($this->orderBys === []) {
            return '';
        }
        return ' ORDER BY ' . implode(', ', $this->orderBys);
    }

    private function buildLimitSql(): string
    {
        $sql = '';
        if ($this->limitVal !== null) {
            $sql .= " LIMIT {$this->limitVal}";
        }
        if ($this->offsetVal !== null) {
            $sql .= " OFFSET {$this->offsetVal}";
        }
        return $sql;
    }

    private function executeSelect(string $sql): \PDOStatement
    {
        $bindings = [];
        foreach ($this->wheres as $w) {
            $bindings = array_merge($bindings, $w['bindings']);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        return $stmt;
    }

    private function assertTable(): void
    {
        if ($this->table === '') {
            throw new \LogicException('Call table() before executing a query.');
        }
    }
}
