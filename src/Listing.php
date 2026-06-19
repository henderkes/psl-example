<?php

declare(strict_types=1);

namespace Blog;

use Psl\Collection\Vector;

/**
 * A user-defined reified generic in application code: a typed, headed listing
 * of rows. `T` is a real type parameter — the `Vector<T>` property is enforced
 * at runtime by the engine, so it can only be constructed with a `Vector`
 * carrying matching type arguments (e.g. `new Listing::<array>($vec, ...)`).
 *
 * This is the kind of native generic the `regular` variant expresses only in a
 * docblock `@template`.
 */
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

    /** @return list<T> */
    public function rows(): array
    {
        return $this->items->toArray();
    }
}
