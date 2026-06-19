<?php

declare(strict_types=1);

namespace Blog;

use Psl\Type;
use Psl\Type\TypeInterface;

/**
 * GENERIC variant: the shape builders are annotated with the native generic
 * `TypeInterface<array>` return type.
 */
final class Types
{
    public static function author(): TypeInterface
    {
        return Type\shape([
            'id'    => Type\int(),
            'name'  => Type\non_empty_string(),
            'email' => Type\non_empty_string(),
        ]);
    }

    public static function tag(): TypeInterface
    {
        return Type\shape([
            'id'   => Type\int(),
            'name' => Type\non_empty_string(),
        ]);
    }

    public static function comment(): TypeInterface
    {
        return Type\shape([
            'id'           => Type\int(),
            'post_id'      => Type\int(),
            'author_name'  => Type\non_empty_string(),
            'content'      => Type\non_empty_string(),
            'published_at' => Type\non_empty_string(),
        ]);
    }

    public static function post(): TypeInterface
    {
        return Type\shape([
            'id'            => Type\int(),
            'slug'          => Type\non_empty_string(),
            'title'         => Type\non_empty_string(),
            'summary'       => Type\non_empty_string(),
            'content'       => Type\string(),
            'author_id'     => Type\int(),
            'published_at'  => Type\non_empty_string(),
            'author_name'   => Type\non_empty_string(),
            'comment_count' => Type\int(),
        ]);
    }
}
