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
 * Reads blog data from SQLite and returns it as PSL collections of type-coerced
 * rows. Exercises generics via Type coercion + Vector/Map + Option + Dict.
 */
final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return Vector of coerced post rows (each an array), newest first. */
    public function recentPosts(int $limit = 20): Vector
    {
        $rows = $this->pdo->query(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             ORDER BY p.published_at DESC LIMIT ' . $limit,
        )->fetchAll();

        $post = Types::post();
        $coerced = Vec\map($rows, static fn(array $r): array => $post->coerce($r));

        return Vector::fromArray($coerced);
    }

    public function findBySlug(string $slug): Option\Option
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id WHERE p.slug = ?',
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        if ($row === false) {
            return Option\none();
        }

        return Option\some(Types::post()->coerce($row));
    }

    /** @return Vector of coerced comment rows for a post. */
    public function commentsFor(int $postId): Vector
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM comments WHERE post_id = ? ORDER BY published_at ASC',
        );
        $stmt->execute([$postId]);
        $comment = Types::comment();
        $rows = Vec\map($stmt->fetchAll(), static fn(array $r): array => $comment->coerce($r));

        return Vector::fromArray($rows);
    }

    /** @return Vector of coerced tag rows for a post. */
    public function tagsFor(int $postId): Vector
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id
             WHERE pt.post_id = ? ORDER BY t.name',
        );
        $stmt->execute([$postId]);
        $tag = Types::tag();
        $rows = Vec\map($stmt->fetchAll(), static fn(array $r): array => $tag->coerce($r));

        return Vector::fromArray($rows);
    }

    /** @return Vector of coerced post rows carrying a given tag. */
    public function postsByTag(string $tag): Vector
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
        $post = Types::post();
        $rows = Vec\map($stmt->fetchAll(), static fn(array $r): array => $post->coerce($r));

        return Vector::fromArray($rows);
    }

    /** @return Vector of coerced post rows matching a search term in title/summary. */
    public function search(string $term): Vector
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             WHERE p.title LIKE ? OR p.summary LIKE ?
             ORDER BY p.published_at DESC',
        );
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);
        $post = Types::post();
        $rows = Vec\map($stmt->fetchAll(), static fn(array $r): array => $post->coerce($r));

        return Vector::fromArray($rows);
    }

    /**
     * Tag cloud: tag name => post count, as a PSL Map. Uses Dict\group_by over
     * the join rows to count, exercising the keyed-collection generics.
     *
     * @return Map of tag-name => count
     */
    public function tagCloud(): Map
    {
        $rows = $this->pdo->query(
            'SELECT t.name AS name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id',
        )->fetchAll();

        $grouped = Dict\group_by($rows, static fn(array $r): string => $r['name']);
        $counts = Dict\map($grouped, static fn(array $group): int => \Psl\Iter\count($group));

        return Map::fromArray($counts);
    }
}
