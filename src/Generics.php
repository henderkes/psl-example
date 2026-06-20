<?php

declare(strict_types=1);

namespace Blog;

use Psl\Collection\Map;
use Psl\Collection\Vector;

/**
 * REGULAR variant: the exact shapes the generic branch declares with native
 * reified generics, here as plain classes whose type parameters live only in a
 * docblock `@template` (no runtime enforcement). Structurally identical to the
 * generic branch's Generics.php — the only difference is the generics.
 */

/** A typed, headed listing of rows. @template T */
final class Listing
{
    /** @param Vector<T> $items */
    public function __construct(
        public readonly Vector $items,
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
final class Paginated
{
    /** @param Vector<T> $items */
    public function __construct(
        public readonly Vector $items,
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
 * A keyed tally (e.g. tag-name => post count). @template TKey of array-key
 */
final class Counts
{
    /** @param Map<TKey, int> $byKey */
    public function __construct(
        public readonly Map $byKey,
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
