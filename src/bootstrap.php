<?php

declare(strict_types=1);

/**
 * GENERIC variant: application code written with native reified generics
 * (turbofish construction `new Vector::<array>(...)`, native generic type
 * hints `Vector<array>`/`Map<string,int>`/`Option<array>`, and a user-defined
 * generic class Listing<T>), running on the *native-generics* PSL
 * (the reified-generics build).
 */

const BLOG_VARIANT = 'generic';
const BLOG_VARIANT_LABEL = 'native generics &middot; reified code';

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/Listing.php';
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
