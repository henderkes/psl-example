<?php

declare(strict_types=1);

/**
 * Deterministic benchmark workload. Drives Kernel::handle() over a fixed set of
 * requests covering every route, N times, accumulating a checksum so the JIT /
 * optimizer cannot elide the work. Prints a machine-readable RESULT line.
 *
 *   php bench/workload.php [iterations]
 *
 * The SAME source runs against both PSL git states (native-generics vs
 * docblock-generics); only the PSL library underneath changes.
 */

require __DIR__ . '/../src/bootstrap.php';

use Blog\Kernel;
use Blog\Request;

$iterations = (int) ($argv[1] ?? 200);

$pdo = blog_pdo();
$kernel = new Kernel($pdo);

// Build a deterministic, representative request mix from real slugs/tags.
$slugs = $pdo->query('SELECT slug FROM posts ORDER BY id LIMIT 12')->fetchAll(PDO::FETCH_COLUMN);
$tags = $pdo->query('SELECT name FROM tags ORDER BY id LIMIT 8')->fetchAll(PDO::FETCH_COLUMN);

$requests = [];
$requests[] = new Request('GET', '/');
$requests[] = new Request('GET', '/api/posts');
foreach ($slugs as $slug) {
    $requests[] = new Request('GET', '/post/' . $slug);
}
foreach ($tags as $tag) {
    $requests[] = new Request('GET', '/tag/' . $tag);
}
$requests[] = new Request('GET', '/search', 'q=Generics');
$requests[] = new Request('GET', '/search', 'q=Concurrent');
$requests[] = new Request('GET', '/post/does-not-exist');

$requestCount = count($requests);

// Warm caches (statement prep, autoload) before timing.
foreach ($requests as $r) {
    $kernel->handle($r);
}

$checksum = 0;
$bytes = 0;
$served = 0;

$start = hrtime(true);
for ($i = 0; $i < $iterations; $i++) {
    foreach ($requests as $r) {
        $res = $kernel->handle($r);
        $len = strlen($res->body);
        $bytes += $len;
        // cheap rolling checksum over status + length + a sampled byte
        $checksum = ($checksum + $res->status * 31 + $len + ord($res->body[$len >> 1] ?? "\0")) & 0x7FFFFFFF;
        $served++;
    }
}
$elapsedNs = hrtime(true) - $start;

$wallMs = $elapsedNs / 1_000_000;
printf(
    "RESULT iterations=%d requests=%d served=%d bytes=%d checksum=%d wall_ms=%.3f us_per_req=%.2f peak_mb=%.1f\n",
    $iterations,
    $requestCount,
    $served,
    $bytes,
    $checksum,
    $wallMs,
    ($elapsedNs / 1000) / $served,
    memory_get_peak_usage(true) / 1048576,
);
