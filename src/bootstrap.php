<?php

declare(strict_types=1);

/**
 * REGULAR variant: plain PHP application code on the *docblock-generics* PSL
 * (the `next` branch, before the reified-generics conversion). No native
 * generic syntax anywhere — types live in docblocks only.
 */

const BLOG_VARIANT = 'regular';
const BLOG_VARIANT_LABEL = 'docblock PSL &middot; plain code';

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/Types.php';
require __DIR__ . '/Repository.php';
require __DIR__ . '/View.php';
require __DIR__ . '/Kernel.php';

function blog_pdo(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/../db/blog.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}
