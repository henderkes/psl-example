<?php

declare(strict_types=1);

namespace Blog;

use PDO;
use Psl\Collection\Map;
use Psl\Collection\Vector;
use Psl\Dict;
use Psl\Option;
use Psl\Vec;

/**
 * GENERIC variant of the repository. Return types are native generics
 * (`Vector<array>`, `Option<array>`, `Map<string,int>`, `Listing<array>`) and
 * collections are built with turbofish so the instances carry real type
 * arguments enforced by the engine (`new Vector::<array>(...)`).
 */
final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<array> $rows */
    private function coerceRows(array $rows, \Psl\Type\TypeInterface $type): Vector<array>
    {
        $coerced = Vec\map($rows, static fn(array $r): array => $type->coerce($r));

        // direct turbofish construction => a genuine Vector<array> (the static
        // fromArray() factory would hand back Vector<mixed>).
        return new Vector::<array>($coerced);
    }

    public function recentPosts(int $limit = 20): Vector<array>
    {
        $rows = $this->pdo->query(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             ORDER BY p.published_at DESC LIMIT ' . $limit,
        )->fetchAll();

        return $this->coerceRows($rows, Types::post());
    }

    /** @return Option\Option<array> (runtime is Option<mixed>: Option's ctor is private) */
    public function findBySlug(string $slug): Option\Option
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id WHERE p.slug = ?',
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        $value = $row === false ? null : Types::post()->coerce($row);

        return Option\from_nullable::<array>($value);
    }

    public function commentsFor(int $postId): Vector<array>
    {
        $stmt = $this->pdo->prepare('SELECT * FROM comments WHERE post_id = ? ORDER BY published_at ASC');
        $stmt->execute([$postId]);

        return $this->coerceRows($stmt->fetchAll(), Types::comment());
    }

    public function tagsFor(int $postId): Vector<array>
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name',
        );
        $stmt->execute([$postId]);

        return $this->coerceRows($stmt->fetchAll(), Types::tag());
    }

    public function postsByTag(string $tag): Listing<array>
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p
             JOIN authors a ON a.id = p.author_id
             JOIN post_tags pt ON pt.post_id = p.id
             JOIN tags t ON t.id = pt.tag_id
             WHERE t.name = ? ORDER BY p.published_at DESC',
        );
        $stmt->execute([$tag]);

        return new Listing::<array>($this->coerceRows($stmt->fetchAll(), Types::post()), 'Posts tagged #' . $tag);
    }

    public function search(string $term): Listing<array>
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             WHERE p.title LIKE ? OR p.summary LIKE ? ORDER BY p.published_at DESC',
        );
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);

        return new Listing::<array>($this->coerceRows($stmt->fetchAll(), Types::post()), 'Search: ' . $term);
    }

    public function tagCloud(): Map<string, int>
    {
        $rows = $this->pdo->query(
            'SELECT t.name AS name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id',
        )->fetchAll();

        $grouped = Dict\group_by($rows, static fn(array $r): string => $r['name']);
        $counts = Dict\map($grouped, static fn(array $group): int => \Psl\Iter\count($group));

        return new Map::<string, int>($counts);
    }
}
