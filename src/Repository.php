<?php

declare(strict_types=1);

namespace Blog;

use PDO;
use Psl\Collection\Map;
use Psl\Collection\Vector;
use Psl\Dict;
use Psl\Iter;
use Psl\Option;
use Psl\Type\TypeInterface;
use Psl\Vec;

/**
 * REGULAR variant: structural mirror of the generic repository, generics removed
 * as possible: every collection is built with turbofish, every PSL function call
 * carries explicit type arguments (`Vec\map::<int, array, array>`,
 * `Dict\group_by::<string, array>`, `Iter\count::<array>`), and row coercion runs
 * through the generic `Pipeline<T>` (whose own `map<Tu>` is a generic method).
 */
final class Repository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Coerce raw rows through a fully-typed generic pipeline:
     * `Pipeline::from::<array>` -> `->map::<array>` (a generic method turbofishing
     * `Vec\map::<int, array, array>` inside) -> `Vector<array>`.
     *
     * @param list<array> $rows
     */
    private function coerceRows(array $rows, TypeInterface $type): Vector
    {
        return Pipeline::from($rows)
            ->map(static fn(array $r): array => $type->coerce($r))
            ->toVector();
    }

    /**
     * Word-frequency tally built generically: flatten to words, then
     * `Dict\group_by::<string, string>` + `Dict\map::<string, array, int>` with an
     * `Iter\count::<string>` per bucket, wrapped in a native Counts<string>.
     *
     * @param list<array> $rows
     */
    public function digest(array $rows): Counts
    {
        $words = [];
        foreach ($rows as $r) {
            $text = strtolower((string) (($r['title'] ?? '') . ' ' . ($r['summary'] ?? '') . ' ' . ($r['content'] ?? '')));
            foreach (explode(' ', $text) as $w) {
                $w = trim($w, " \t\r\n.,;:!?\"'()-");
                if (strlen($w) >= 4) {
                    $words[] = $w;
                }
            }
        }

        $grouped = Dict\group_by($words, static fn(string $w): string => $w);
        $counts  = Dict\map($grouped, static fn(array $g): int => Iter\count($g));

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
        $counts  = Dict\map($grouped, static fn(array $group): int => Iter\count($group));

        return new Counts(Map::fromArray($counts));
    }
}
