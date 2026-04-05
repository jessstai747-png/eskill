<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Collection — Wrapper imutável ao redor de um array que expõe operações
 * encadeáveis para substituir os 1600+ raw array_* espalhados nos serviços.
 *
 * Uso:
 *   $items = collect($rows)
 *       ->filter(fn($r) => $r['active'])
 *       ->sortBy('price')
 *       ->map(fn($r) => ['id' => $r['id'], 'price' => (float)$r['price']])
 *       ->values()
 *       ->all();
 *
 * Todos os métodos que retornam uma coleção devem retornar uma nova
 * instância (operações imutáveis), exceto when explicitly noted.
 *
 * Implementa Countable e IteratorAggregate para uso em foreach e count().
 *
 * @template T
 * @implements \IteratorAggregate<int|string, T>
 */
class Collection implements \Countable, \IteratorAggregate
{
    /** @var array<int|string, T> */
    private array $items;

    /**
     * @param array<int|string, T> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * Cria uma Collection a partir de um array.
     *
     * @template U
     * @param array<int|string, U> $items
     * @return self<U>
     */
    public static function make(array $items = []): self
    {
        return new self($items);
    }

    // ─── Read-only accessors ──────────────────────────────────────────────────

    /**
     * Retorna o array subjacente.
     * @return array<int|string, T>
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Retorna o número de itens.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Verdadeiro se a coleção não tiver itens.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Verdadeiro se a coleção tiver pelo menos um item.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->items);
    }

    /**
     * Retorna o primeiro item (ou o resultado do callback, se fornecido).
     * Retorna $default se a coleção estiver vazia.
     *
     * @param callable(T): bool|null $callback
     * @return T|null
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : reset($this->items);
        }

        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                return $item;
            }
        }

        return $default;
    }

    /**
     * Retorna o último item.
     * @return T|null
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($this->items) ? $default : end($this->items);
        }

        $found = $default;
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                $found = $item;
            }
        }

        return $found;
    }

    /**
     * Retorna um item pelo índice/chave, ou $default se não existir.
     * @return T|null
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    /**
     * Verifica se uma chave existe na coleção.
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     * Verifica se um valor existe na coleção (comparação estrita por padrão).
     * @param T $value
     */
    public function contains(mixed $value, bool $strict = true): bool
    {
        return in_array($value, $this->items, $strict);
    }

    // ─── Transformations (return new Collection) ──────────────────────────────

    /**
     * Aplica $callback a cada item e retorna nova coleção com os resultados.
     * Equivale a array_map mas mantém as chaves.
     *
     * @param callable(T, int|string): mixed $callback
     */
    public function map(callable $callback): self
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $callback($item, $key);
        }
        return new self($result);
    }

    /**
     * Filtra itens que passam no teste do callback.
     * Equivale a array_filter com preservação de chaves.
     *
     * @param callable(T, int|string): bool|null $callback
     *   Sem callback: remove valores falsy (null, false, '', 0, []).
     */
    public function filter(?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_filter($this->items));
        }

        $result = [];
        foreach ($this->items as $key => $item) {
            if ($callback($item, $key)) {
                $result[$key] = $item;
            }
        }
        return new self($result);
    }

    /**
     * Reduz a coleção a um único valor.
     * Equivale a array_reduce.
     *
     * @param callable(mixed, T, int|string): mixed $callback
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;
        foreach ($this->items as $key => $item) {
            $carry = $callback($carry, $item, $key);
        }
        return $carry;
    }

    /**
     * Extrai uma coluna de arrays/objetos aninhados.
     * Equivale a array_column.
     *
     * @param string|int $column  Nome da coluna a extrair
     * @param string|int|null $indexBy  Coluna a usar como índice do resultado
     */
    public function pluck(string|int $column, string|int|null $indexBy = null): self
    {
        $result = [];
        foreach ($this->items as $item) {
            $value = is_array($item) ? ($item[$column] ?? null) : (property_exists($item, (string)$column) ? $item->$column : null);

            if ($indexBy !== null) {
                $index = is_array($item) ? ($item[$indexBy] ?? null) : (property_exists($item, (string)$indexBy) ? $item->$indexBy : null);
                $result[$index] = $value;
            } else {
                $result[] = $value;
            }
        }
        return new self($result);
    }

    /**
     * Agrupa os itens pela chave retornada pelo callback ou por um campo.
     *
     * @param callable(T): string|int|string|int $groupBy
     */
    public function groupBy(callable|string|int $groupBy): self
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $group = is_callable($groupBy)
                ? $groupBy($item, $key)
                : (is_array($item) ? ($item[$groupBy] ?? '') : ($item->$groupBy ?? ''));

            $result[$group][] = $item;
        }
        return new self($result);
    }

    /**
     * Ordena itens por um campo ou pelo resultado de um callback.
     * Retorna nova coleção com chaves re-indexadas (array_values).
     *
     * @param callable(T): mixed|string|int $key
     * @param bool $descending  Se verdadeiro, ordem decrescente.
     */
    public function sortBy(callable|string|int $key, bool $descending = false): self
    {
        $items = $this->items;

        usort($items, function ($a, $b) use ($key, $descending) {
            $va = is_callable($key) ? $key($a) : (is_array($a) ? ($a[$key] ?? null) : ($a->$key ?? null));
            $vb = is_callable($key) ? $key($b) : (is_array($b) ? ($b[$key] ?? null) : ($b->$key ?? null));

            $cmp = $va <=> $vb;
            return $descending ? -$cmp : $cmp;
        });

        return new self(array_values($items));
    }

    /**
     * Ordena itens em ordem decrescente. Atalho para sortBy(..., descending: true).
     *
     * @param callable(T): mixed|string|int $key
     */
    public function sortByDesc(callable|string|int $key): self
    {
        return $this->sortBy($key, descending: true);
    }

    /**
     * Remove itens duplicados (comparação estrita).
     * Equivale a array_unique com SORT_REGULAR.
     */
    public function unique(?callable $callback = null): self
    {
        if ($callback === null) {
            return new self(array_values(array_unique($this->items, SORT_REGULAR)));
        }

        $seen = [];
        $result = [];
        foreach ($this->items as $item) {
            $key = $callback($item);
            $serialized = serialize($key);
            if (!isset($seen[$serialized])) {
                $seen[$serialized] = true;
                $result[] = $item;
            }
        }
        return new self($result);
    }

    /**
     * Divide a coleção em pedaços de tamanho $size.
     * Retorna coleção de arrays (não de Collections).
     */
    public function chunk(int $size): self
    {
        if ($size <= 0) {
            return new self([]);
        }
        return new self(array_chunk($this->items, $size));
    }

    /**
     * Retorna novos itens com chaves re-indexadas (0, 1, 2, …).
     * Equivale a array_values.
     */
    public function values(): self
    {
        return new self(array_values($this->items));
    }

    /**
     * Retorna coleção com apenas as chaves.
     */
    public function keys(): self
    {
        return new self(array_keys($this->items));
    }

    /**
     * Mescla outro array ou Collection com este.
     *
     * @param array<int|string, mixed>|self $items
     */
    public function merge(array|self $items): self
    {
        $other = $items instanceof self ? $items->all() : $items;
        return new self(array_merge($this->items, $other));
    }

    /**
     * Achata um nível de arrays aninhados.
     */
    public function flatten(): self
    {
        $result = [];
        array_walk_recursive($this->items, function ($item) use (&$result) {
            $result[] = $item;
        });
        return new self($result);
    }

    /**
     * Retorna apenas os primeiros $limit itens.
     */
    public function take(int $limit): self
    {
        if ($limit < 0) {
            return new self(array_slice($this->items, $limit));
        }
        return new self(array_slice($this->items, 0, $limit));
    }

    /**
     * Pula os primeiros $count itens e retorna o resto.
     */
    public function skip(int $count): self
    {
        return new self(array_slice($this->items, $count));
    }

    /**
     * Retorna apenas itens no intervalo (similar a array_slice com chave de offset).
     */
    public function slice(int $offset, ?int $length = null): self
    {
        return new self(array_slice($this->items, $offset, $length));
    }

    /**
     * Retorna apenas os campos especificados de cada item (array associativo).
     *
     * @param array<string|int> $keys
     */
    public function only(array $keys): self
    {
        return $this->map(function ($item) use ($keys) {
            if (!is_array($item)) {
                return $item;
            }
            return array_intersect_key($item, array_flip($keys));
        });
    }

    /**
     * Remove os campos especificados de cada item.
     *
     * @param array<string|int> $keys
     */
    public function except(array $keys): self
    {
        return $this->map(function ($item) use ($keys) {
            if (!is_array($item)) {
                return $item;
            }
            return array_diff_key($item, array_flip($keys));
        });
    }

    // ─── Aggregates ───────────────────────────────────────────────────────────

    /**
     * Soma dos valores ou de um campo específico.
     *
     * @param callable(T): numeric|string|int|null $key
     */
    public function sum(callable|string|int|null $key = null): int|float
    {
        return array_sum($this->resolveValues($key));
    }

    /**
     * Média aritmética dos valores ou de um campo.
     *
     * @param callable(T): numeric|string|int|null $key
     */
    public function avg(callable|string|int|null $key = null): float
    {
        $values = $this->resolveValues($key);
        return empty($values) ? 0.0 : array_sum($values) / count($values);
    }

    /**
     * Valor mínimo.
     *
     * @param callable(T): numeric|string|int|null $key
     */
    public function min(callable|string|int|null $key = null): mixed
    {
        $values = $this->resolveValues($key);
        return empty($values) ? null : min($values);
    }

    /**
     * Valor máximo.
     *
     * @param callable(T): numeric|string|int|null $key
     */
    public function max(callable|string|int|null $key = null): mixed
    {
        $values = $this->resolveValues($key);
        return empty($values) ? null : max($values);
    }

    // ─── Iteration ────────────────────────────────────────────────────────────

    /**
     * Executa $callback para cada item sem transformar a coleção.
     *
     * @param callable(T, int|string): void $callback
     */
    public function each(callable $callback): self
    {
        foreach ($this->items as $key => $item) {
            $callback($item, $key);
        }
        return $this;
    }

    /**
     * @return \ArrayIterator<int|string, T>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * @param callable(T): numeric|string|int|null $key
     * @return array<int|float>
     */
    private function resolveValues(callable|string|int|null $key): array
    {
        if ($key === null) {
            return array_values($this->items);
        }

        $values = [];
        foreach ($this->items as $item) {
            $values[] = is_callable($key)
                ? $key($item)
                : (is_array($item) ? ($item[$key] ?? 0) : ($item->$key ?? 0));
        }
        return $values;
    }
}
