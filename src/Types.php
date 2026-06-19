<?php

declare(strict_types=1);

namespace Blog;

use Psl\Type;

/**
 * Type shapes for the domain models.
 *
 * Every database row is run through a PSL `Type` coercer. The Type system is one
 * of the most generics-heavy parts of PSL — each `Type\shape`, `Type\vec`,
 * `Type\optional`, etc. is a generic `TypeInterface<T>` whose `coerce()` is
 * dispatched through the reified-generic class hierarchy. This is deliberate:
 * it makes the per-request work lean hard on generics so the benchmark has
 * something to measure.
 */
final class Types
{
    public static function author(): Type\TypeInterface
    {
        return Type\shape([
            'id'    => Type\int(),
            'name'  => Type\non_empty_string(),
            'email' => Type\non_empty_string(),
        ]);
    }

    public static function tag(): Type\TypeInterface
    {
        return Type\shape([
            'id'   => Type\int(),
            'name' => Type\non_empty_string(),
        ]);
    }

    public static function comment(): Type\TypeInterface
    {
        return Type\shape([
            'id'           => Type\int(),
            'post_id'      => Type\int(),
            'author_name'  => Type\non_empty_string(),
            'content'      => Type\non_empty_string(),
            'published_at' => Type\non_empty_string(),
        ]);
    }

    public static function post(): Type\TypeInterface
    {
        return Type\shape([
            'id'           => Type\int(),
            'slug'         => Type\non_empty_string(),
            'title'        => Type\non_empty_string(),
            'summary'      => Type\non_empty_string(),
            'content'      => Type\string(),
            'author_id'    => Type\int(),
            'published_at' => Type\non_empty_string(),
            'author_name'  => Type\non_empty_string(),
            'comment_count' => Type\int(),
        ]);
    }
}
