<?php

declare(strict_types=1);

namespace Blog;

use Closure;
use Psl\Collection\Map;
use Psl\Collection\Vector;
use Psl\Vec;

/**
 * User-defined reified generics used throughout the application. Each `<T>` is a
 * real type parameter the engine enforces at runtime, so these can only be built
 * from PSL collections carrying matching type arguments (turbofish:
 * `new Listing::<array>($vec, ...)`). The `regular` branch expresses the same
 * shapes only in docblock `@template`s.
 */

/** A typed, headed listing of rows. @template T */
final class Listing<T>
{
    public function __construct(
        public readonly Vector<T> $items,
        public readonly string $heading,
    ) {
    }

    public function count(): int
    {
        return $this->items->count();
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /** @return list<T> */
    public function rows(): array
    {
        return $this->items->toArray();
    }
}

/** A page of typed rows plus pagination metadata. @template T */
final class Paginated<T>
{
    public function __construct(
        public readonly Vector<T> $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function pages(): int
    {
        return $this->perPage > 0 ? (int) \ceil($this->total / $this->perPage) : 1;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /** @return list<T> */
    public function rows(): array
    {
        return $this->items->toArray();
    }
}

/**
 * A keyed tally (e.g. tag-name => post count). Two type parameters, the key one
 * carrying a native bound. @template TKey of array-key
 */
final class Counts<TKey : int|string>
{
    public function __construct(
        public readonly Map<TKey, int> $byKey,
    ) {
    }

    public function total(): int
    {
        $total = 0;
        foreach ($this->byKey->toArray() as $n) {
            $total += $n;
        }
        return $total;
    }

    /** @return array<TKey, int> */
    public function toArray(): array
    {
        return $this->byKey->toArray();
    }
}

/**
 * A fluent, fully-typed transformation pipeline over a Vector<T>. Its `map`
 * method is itself generic (`map<Tu>`) and turbofishes the underlying
 * `Vec\map::<int, T, Tu>`, so the element type is forwarded and reified at every
 * hop. `filter`/`sort` keep T; `map` rebinds it to Tu.
 *
 * @template T
 */
final class Pipeline<T>
{
    public function __construct(
        public readonly Vector<T> $items,
    ) {
    }

    /** @param list<Tf> $values */
    public static function from<Tf>(array $values): Pipeline<Tf>
    {
        return new self::<Tf>(new Vector::<Tf>($values));
    }

    public function map<Tu>(Closure $f): Pipeline<Tu>
    {
        return new self::<Tu>(new Vector::<Tu>(Vec\map::<int, T, Tu>($this->items->toArray(), $f)));
    }

    public function filter(Closure $predicate): Pipeline<T>
    {
        return new self::<T>(new Vector::<T>(Vec\filter::<T>($this->items->toArray(), $predicate)));
    }

    public function sort(Closure $comparator): Pipeline<T>
    {
        return new self::<T>(new Vector::<T>(Vec\sort::<T>($this->items->toArray(), $comparator)));
    }

    public function toVector(): Vector<T>
    {
        return $this->items;
    }

    /** @return list<T> */
    public function toArray(): array
    {
        return $this->items->toArray();
    }

    public function count(): int
    {
        return $this->items->count();
    }
}
