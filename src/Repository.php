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
 * REGULAR variant of the repository - a structural mirror of the generic
 * (`Vector`, `Option<array>`, `Map`, `Listing`) and
 * collections are built with turbofish so the instances carry real type
 * arguments enforced by the engine (`Vector::fromArray(...)`).
 */
final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @param list<array> $rows */
    private function coerceRows(array $rows, \Psl\Type\TypeInterface $type): Vector
    {
        $coerced = Vec\map($rows, static fn(array $r): array => $type->coerce($r));

        // direct turbofish construction => a genuine Vector (the static
        // fromArray() factory would hand back Vector<mixed>).
        return Vector::fromArray($coerced);
    }

    /**
     * Word-frequency tally over the given rows' text — the same keyed-collection
     * work the tag cloud does, exposed per route so every page exercises the
     * scalar-checked generics. A native Map wrapped in Counts.
     *
     * @param list<array> $rows
     */
    public function digest(array $rows): Counts
    {
        $counts = [];
        foreach ($rows as $r) {
            $text = strtolower((string) (($r['title'] ?? '') . ' ' . ($r['summary'] ?? '') . ' ' . ($r['content'] ?? '')));
            foreach (explode(' ', $text) as $w) {
                $w = trim($w, " \t\r\n.,;:!?\"'()-");
                if (strlen($w) < 4) {
                    continue;
                }
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }

        return new Counts(Map::fromArray($counts));
    }

    public function recentPosts(int $limit = 20): Vector
    {
        $rows = $this->pdo->query(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             ORDER BY p.published_at DESC LIMIT ' . $limit,
        )->fetchAll();

        return $this->coerceRows($rows, Types::post());
    }

    public function recentPage(int $page = 1, int $perPage = 20): Paginated
    {
        $total = (int) $this->pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $rows = $this->pdo->query(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             ORDER BY p.published_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
        )->fetchAll();

        // turbofish so the page carries a genuine Vector
        $items = Vector::fromArray(Vec\map($rows, static fn(array $r): array => Types::post()->coerce($r)));

        return new Paginated($items, $total, $page, $perPage);
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

        return Option\from_nullable($value);
    }

    public function commentsFor(int $postId): Vector
    {
        $stmt = $this->pdo->prepare('SELECT * FROM comments WHERE post_id = ? ORDER BY published_at ASC');
        $stmt->execute([$postId]);

        return $this->coerceRows($stmt->fetchAll(), Types::comment());
    }

    public function tagsFor(int $postId): Vector
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM tags t JOIN post_tags pt ON pt.tag_id = t.id WHERE pt.post_id = ? ORDER BY t.name',
        );
        $stmt->execute([$postId]);

        return $this->coerceRows($stmt->fetchAll(), Types::tag());
    }

    public function postsByTag(string $tag): Listing
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

        return new Listing($this->coerceRows($stmt->fetchAll(), Types::post()), 'Posts tagged #' . $tag);
    }

    public function search(string $term): Listing
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, a.name AS author_name,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count
             FROM posts p JOIN authors a ON a.id = p.author_id
             WHERE p.title LIKE ? OR p.summary LIKE ? ORDER BY p.published_at DESC',
        );
        $like = '%' . $term . '%';
        $stmt->execute([$like, $like]);

        return new Listing($this->coerceRows($stmt->fetchAll(), Types::post()), 'Search: ' . $term);
    }

    public function tagCloud(): Counts
    {
        $rows = $this->pdo->query(
            'SELECT t.name AS name FROM tags t JOIN post_tags pt ON pt.tag_id = t.id',
        )->fetchAll();

        $grouped = Dict\group_by($rows, static fn(array $r): string => $r['name']);
        $counts = Dict\map($grouped, static fn(array $group): int => \Psl\Iter\count($group));

        // a native Map wrapped in the generic Counts
        return new Counts(Map::fromArray($counts));
    }
}
