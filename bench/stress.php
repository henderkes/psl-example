<?php

declare(strict_types=1);

/**
 * Pure-PSL generics stress workload — no SQLite, no I/O. Hammers the parts of
 * PSL that lean hardest on generics (Type coercion, Collection\Vector/Map,
 * Option, Dict/Vec transforms) so the cost of native reified generics is
 * isolated from database and rendering work.
 *
 *   php bench/stress.php [iterations]
 */

require '/home/opc/php-standard-library/vendor/autoload.php';

use Psl\Collection\Map;
use Psl\Collection\Vector;
use Psl\Dict;
use Psl\Option;
use Psl\Type;
use Psl\Vec;

$iterations = (int) ($argv[1] ?? 20000);

$rowType = Type\shape([
    'id'     => Type\int(),
    'name'   => Type\non_empty_string(),
    'score'  => Type\int(),
    'active' => Type\bool(),
    'tags'   => Type\vec(Type\non_empty_string()),
]);

// fixed input rows (strings on purpose, to force coercion)
$rows = [];
for ($i = 0; $i < 16; $i++) {
    $rows[] = [
        'id'     => (string) $i,
        'name'   => 'item-' . $i,
        'score'  => (string) (($i * 37) % 100),
        'active' => $i % 2 === 0 ? '1' : '0',
        'tags'   => ['t' . ($i % 4), 't' . ($i % 3)],
    ];
}

$checksum = 0;
$start = hrtime(true);

for ($n = 0; $n < $iterations; $n++) {
    // coerce every row through the generic Type system
    $coerced = Vec\map($rows, static fn(array $r): array => $rowType->coerce($r));

    // build a generic Vector and transform it
    $vector = Vector::fromArray($coerced);
    $scores = Vec\map($vector->toArray(), static fn(array $r): int => $r['score']);
    $active = Vec\filter($vector->toArray(), static fn(array $r): bool => $r['active']);

    // group into a generic Map via Dict
    $byTag = Dict\group_by($coerced, static fn(array $r): string => $r['tags'][0]);
    $sizes = Map::fromArray(Dict\map($byTag, static fn(array $g): int => \Psl\Iter\count($g)));

    // Option chain
    $first = $vector->first();
    $opt = Option\from_nullable($first)
        ->map(static fn(array $r): int => $r['score'])
        ->filter(static fn(int $s): bool => $s > 10)
        ->unwrapOr(-1);

    $checksum = ($checksum + \Psl\Math\sum($scores) + count($active) + $sizes->count() + $opt) & 0x7FFFFFFF;
}

$elapsedNs = hrtime(true) - $start;
printf(
    "RESULT iterations=%d checksum=%d wall_ms=%.3f us_per_iter=%.3f peak_mb=%.1f\n",
    $iterations,
    $checksum,
    $elapsedNs / 1_000_000,
    ($elapsedNs / 1000) / $iterations,
    memory_get_peak_usage(true) / 1048576,
);
