# psl-example — a Symfony-demo-style blog on php-standard-library

A small blog (authors, posts, tags, comments) built entirely on
[php-standard-library](https://github.com/php-standard-library/php-standard-library),
used to compare **native reified generics** against the original **docblock
generics** — in both the library *and* the application code.

Two branches, same app, different stack:

| branch | PSL library (pinned commit) | application code |
|--------|-----------------------------|------------------|
| **`generic`** (default) | `dev-reified-generics` — native `<T>` generics | native generics: turbofish `new Vector::<array>()`, `Map<string,int>`, a user `Listing<T>` class |
| **`regular`** | `dev-next` (the commit *before* the conversion) — docblock generics | plain PHP, generics only in docblocks |

`composer.json` on each branch pins the library to an exact git commit, so the
two branches install genuinely different builds of PSL.

## This branch: `generic`

The application code uses **native reified generics**, which requires a PHP
build that implements the
[reified-generics RFC](https://wiki.php.net/rfc/reified-generics). On a stock PHP
the source won't parse — check out the `regular` branch for that. The pinned
library commit (`3410ae4e`) is php-standard-library fully converted to native
generic syntax.

## Run it

```bash
composer install                       # pulls PSL at the pinned commit
php db/seed.php                         # build db/blog.sqlite (deterministic)
php bin/server.php 127.0.0.1 8099       # PSL async HTTP server
curl http://127.0.0.1:8099/
```

## Layout

```
src/Types.php       PSL Type shapes for each model (Type coercion per row)
src/Listing.php     a user-defined reified generic: Listing<T>  (generic branch only)
src/Repository.php  SQLite reads -> Type coerce -> PSL Collections/Option
src/View.php        HTML/JSON rendering with Str/Vec
src/Kernel.php      handle(Request): Response — the one request pipeline
bin/server.php      live HTTP/1.1 server on PSL's async runtime + TCP listener
bench/              perf harness comparing the two PSL builds (instructions/cycles/wall)
```

Every request: SQLite query → `Psl\Type` coercion → `Psl\Collection\Vector`/`Map`
→ `Psl\Option` → `Vec`/`Dict`/`Str` rendering. `Kernel::handle()` is shared by the
live server and the benchmark, so the perf numbers reflect real request handling.

## Live

- generic: https://generic.pyc.ac
- regular: https://regular.pyc.ac
