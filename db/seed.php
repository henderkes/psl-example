<?php

declare(strict_types=1);

/**
 * Builds a deterministic "Symfony-demo"-style blog SQLite database.
 * Content is generated from a fixed LCG seed so every run is identical.
 */

$dbPath = __DIR__ . '/blog.sqlite';
@unlink($dbPath);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec(<<<'SQL'
CREATE TABLE authors (
    id    INTEGER PRIMARY KEY,
    name  TEXT NOT NULL,
    email TEXT NOT NULL
);
CREATE TABLE tags (
    id   INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE
);
CREATE TABLE posts (
    id           INTEGER PRIMARY KEY,
    slug         TEXT NOT NULL UNIQUE,
    title        TEXT NOT NULL,
    summary      TEXT NOT NULL,
    content      TEXT NOT NULL,
    author_id    INTEGER NOT NULL REFERENCES authors(id),
    published_at TEXT NOT NULL
);
CREATE TABLE post_tags (
    post_id INTEGER NOT NULL REFERENCES posts(id),
    tag_id  INTEGER NOT NULL REFERENCES tags(id),
    PRIMARY KEY (post_id, tag_id)
);
CREATE TABLE comments (
    id           INTEGER PRIMARY KEY,
    post_id      INTEGER NOT NULL REFERENCES posts(id),
    author_name  TEXT NOT NULL,
    content      TEXT NOT NULL,
    published_at TEXT NOT NULL
);
CREATE INDEX idx_posts_author ON posts(author_id);
CREATE INDEX idx_posts_published ON posts(published_at);
CREATE INDEX idx_comments_post ON comments(post_id);
CREATE INDEX idx_post_tags_tag ON post_tags(tag_id);
SQL);

// --- deterministic PRNG (LCG) -------------------------------------------------
$state = 0x1234_5678;
$rand = static function (int $mod) use (&$state): int {
    $state = ($state * 1103515245 + 12345) & 0x7FFFFFFF;
    return $state % $mod;
};
$pick = static function (array $list) use ($rand): string {
    return $list[$rand(count($list))];
};

$adjectives = ['Modern', 'Practical', 'Hidden', 'Elegant', 'Robust', 'Reactive', 'Functional', 'Typed', 'Composable', 'Concurrent', 'Immutable', 'Lazy', 'Pragmatic', 'Scalable'];
$nouns = ['Collections', 'Generics', 'Closures', 'Iterators', 'Channels', 'Promises', 'Fibers', 'Sockets', 'Pipelines', 'Coroutines', 'Templates', 'Monads', 'Streams', 'Schemas'];
$verbs = ['Mastering', 'Understanding', 'Exploring', 'Refactoring', 'Benchmarking', 'Designing', 'Building', 'Profiling'];
$words = ['the', 'a', 'standard', 'library', 'provides', 'strongly', 'typed', 'data', 'structures', 'for', 'PHP', 'developers', 'who', 'value', 'correctness', 'performance', 'and', 'expressive', 'composable', 'APIs', 'across', 'collections', 'channels', 'and', 'async', 'runtimes', 'with', 'reified', 'generics', 'enforced', 'at', 'runtime'];
$firsts = ['Ada', 'Linus', 'Grace', 'Dennis', 'Margaret', 'Ken', 'Barbara', 'Alan', 'Edsger', 'Donald', 'Anita', 'Guido'];
$lasts = ['Lovelace', 'Torvalds', 'Hopper', 'Ritchie', 'Hamilton', 'Thompson', 'Liskov', 'Turing', 'Dijkstra', 'Knuth', 'Borg', 'van Rossum'];

$sentence = static function (int $n) use ($pick, $words): string {
    $parts = [];
    for ($i = 0; $i < $n; $i++) {
        $parts[] = $pick($words);
    }
    $s = implode(' ', $parts);
    return ucfirst($s) . '.';
};

// --- authors -----------------------------------------------------------------
$authorStmt = $pdo->prepare('INSERT INTO authors (id, name, email) VALUES (?, ?, ?)');
$authorCount = 12;
for ($i = 1; $i <= $authorCount; $i++) {
    $name = $firsts[($i - 1) % count($firsts)] . ' ' . $lasts[($i - 1) % count($lasts)];
    $email = strtolower(str_replace([' ', '.'], ['.', ''], $name)) . '@psl.example';
    $authorStmt->execute([$i, $name, $email]);
}

// --- tags --------------------------------------------------------------------
$tagStmt = $pdo->prepare('INSERT INTO tags (id, name) VALUES (?, ?)');
$tagNames = [];
foreach ($nouns as $n) {
    $tagNames[] = strtolower($n);
}
foreach (['php', 'async', 'types', 'performance', 'tutorial', 'architecture', 'testing', 'deep-dive'] as $extra) {
    $tagNames[] = $extra;
}
$tagNames = array_values(array_unique($tagNames));
foreach ($tagNames as $idx => $tn) {
    $tagStmt->execute([$idx + 1, $tn]);
}
$tagCount = count($tagNames);

// --- posts + tags + comments -------------------------------------------------
$postStmt = $pdo->prepare('INSERT INTO posts (id, slug, title, summary, content, author_id, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
$ptStmt = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (?, ?)');
$commentStmt = $pdo->prepare('INSERT INTO comments (id, post_id, author_name, content, published_at) VALUES (?, ?, ?, ?, ?)');

$pdo->beginTransaction();
$postCount = 120;
$commentId = 1;
$baseTime = strtotime('2024-01-01 00:00:00');
for ($p = 1; $p <= $postCount; $p++) {
    $title = $pick($verbs) . ' ' . $pick($adjectives) . ' ' . $pick($nouns);
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title)) . '-' . $p;
    $summary = $sentence(12);
    $paras = [];
    for ($b = 0; $b < 4; $b++) {
        $paras[] = $sentence(28 + $rand(20));
    }
    $content = implode("\n\n", $paras);
    $authorId = 1 + $rand($authorCount);
    $publishedAt = date('Y-m-d H:i:s', $baseTime + $p * 7200 + $rand(3600));
    $postStmt->execute([$p, $slug, $title, $summary, $content, $authorId, $publishedAt]);

    // 3..6 distinct tags
    $nTags = 3 + $rand(4);
    $used = [];
    for ($t = 0; $t < $nTags; $t++) {
        $tagId = 1 + $rand($tagCount);
        if (isset($used[$tagId])) {
            continue;
        }
        $used[$tagId] = true;
        $ptStmt->execute([$p, $tagId]);
    }

    // 4..11 comments
    $nComments = 4 + $rand(8);
    for ($c = 0; $c < $nComments; $c++) {
        $cn = $pick($firsts) . ' ' . $pick($lasts);
        $commentStmt->execute([$commentId++, $p, $cn, $sentence(10 + $rand(15)), date('Y-m-d H:i:s', strtotime($publishedAt) + ($c + 1) * 3600)]);
    }
}
$pdo->commit();

$counts = [];
foreach (['authors', 'tags', 'posts', 'post_tags', 'comments'] as $tbl) {
    $counts[$tbl] = (int) $pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
}
echo "Seeded {$dbPath}\n";
foreach ($counts as $tbl => $n) {
    printf("  %-12s %d\n", $tbl, $n);
}
