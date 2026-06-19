<?php

declare(strict_types=1);

namespace Blog;

use Psl\Collection\Map;
use Psl\Collection\Vector;
use Psl\Str;
use Psl\Vec;

/**
 * GENERIC variant of the view: method signatures use native generic types
 * (`Vector<array>`, `Map<string,int>`, `Listing<array>`) so the engine enforces
 * the element types of the collections handed to the renderer.
 */
final class View
{
    private const STYLE = <<<'CSS'
:root{--bg:#f4f5f7;--card:#fff;--ink:#2b3a4a;--muted:#7b8a9a;--accent:#2d8a6b;--accent2:#1f6f8b;--line:#e6e9ee}
*{box-sizing:border-box}
body{margin:0;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55}
header.nav{background:linear-gradient(95deg,#1f6f8b,#2d8a6b);color:#fff;padding:14px 0;box-shadow:0 2px 10px rgba(0,0,0,.12)}
header.nav .wrap{max-width:1040px;margin:0 auto;padding:0 22px;display:flex;align-items:center;gap:22px}
header.nav a{color:#fff;text-decoration:none}
header.nav .brand{font-size:21px;font-weight:700;letter-spacing:.3px}
header.nav .brand span{opacity:.8;font-weight:400}
header.nav nav{margin-left:auto;display:flex;gap:18px;font-size:14px}
header.nav nav a{opacity:.92}
.badge{margin-left:6px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.4);color:#fff;border-radius:999px;padding:3px 12px;font-size:12px;font-weight:600;letter-spacing:.3px}
.container{max-width:1040px;margin:26px auto;padding:0 22px;display:grid;grid-template-columns:1fr 290px;gap:26px}
.container.single{grid-template-columns:1fr}
main h1{font-size:30px;margin:.1em 0 .5em}
article.card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:22px 24px;margin-bottom:20px;box-shadow:0 1px 3px rgba(20,40,60,.05)}
article.card h2{margin:0 0 6px;font-size:21px}
article.card h2 a{color:var(--accent2);text-decoration:none}
article.card h2 a:hover{text-decoration:underline}
.meta{color:var(--muted);font-size:13px;margin:0 0 10px}
.meta b{color:var(--ink)}
.summary{margin:0;color:#41525f}
.tags a,.cloud a{display:inline-block;background:#eef6f2;color:var(--accent);border:1px solid #d7ebe1;border-radius:999px;padding:2px 11px;font-size:12.5px;text-decoration:none;margin:0 6px 6px 0}
.tags a:hover,.cloud a:hover{background:var(--accent);color:#fff}
aside{align-self:start}
aside .box{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px 20px;margin-bottom:20px}
aside h3{margin:0 0 12px;font-size:15px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
.post-body p{margin:0 0 14px;color:#3a4855}
.post h1{margin-bottom:.2em}
ul.list{list-style:none;padding:0;margin:0}
ul.list li{background:var(--card);border:1px solid var(--line);border-radius:10px;padding:12px 16px;margin-bottom:10px}
ul.list li a{color:var(--accent2);text-decoration:none;font-weight:600}
ul.list li span{color:var(--muted);font-size:13px}
.comments{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:20px 24px;margin-top:6px}
.comments ul{list-style:none;padding:0;margin:0}
.comments li{padding:12px 0;border-bottom:1px solid var(--line)}
.comments li:last-child{border:0}
.comments strong{color:var(--accent2)}
.comments span{color:var(--muted);font-size:12px;margin-left:8px}
footer{max-width:1040px;margin:10px auto 40px;padding:0 22px;color:var(--muted);font-size:13px;text-align:center}
CSS;

    private static function layout(string $title, string $body, string $sidebar = ''): string
    {
        $columns = $sidebar === '' ? 'container single' : 'container';

        return Str\format(
            "<!doctype html>\n<html lang=\"en\"><head><meta charset=\"utf-8\">"
            . "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">"
            . "<title>%s &middot; PSL Blog</title><style>%s</style></head>\n"
            . "<body>\n<header class=\"nav\"><div class=\"wrap\">"
            . "<a class=\"brand\" href=\"/\">PSL&nbsp;Blog<span> / demo</span></a>"
            . "<span class=\"badge\">%s</span>"
            . "<nav><a href=\"/\">Home</a><a href=\"/tag/generics\">#generics</a>"
            . "<a href=\"/search?q=Concurrent\">Search</a><a href=\"/api/posts\">API</a></nav>"
            . "</div></header>\n"
            . "<div class=\"%s\">\n<main>%s</main>\n%s</div>\n"
            . "<footer>php-standard-library demo &middot; %s &middot; PHP %s</footer>\n"
            . "</body></html>\n",
            self::escape($title),
            self::STYLE,
            BLOG_VARIANT_LABEL,
            $columns,
            $body,
            $sidebar,
            BLOG_VARIANT_LABEL,
            PHP_VERSION,
        );
    }

    private static function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function date(string $ts): string
    {
        return Str\slice($ts, 0, 16);
    }

    public static function home(Paginated<array> $page, Counts<string> $tagCloud): string
    {
        $items = Vec\map($page->rows(), static function (array $p): string {
            return Str\format(
                "<article class=\"card\"><h2><a href=\"/post/%s\">%s</a></h2>"
                . "<p class=\"meta\">by <b>%s</b> &middot; %s &middot; %d comments</p>"
                . "<p class=\"summary\">%s</p></article>",
                self::escape($p['slug']),
                self::escape($p['title']),
                self::escape($p['author_name']),
                self::escape(self::date($p['published_at'])),
                $p['comment_count'],
                self::escape($p['summary']),
            );
        });

        $cloudParts = [];
        foreach ($tagCloud->toArray() as $name => $count) {
            $cloudParts[] = Str\format('<a href="/tag/%s">%s &middot; %d</a>', self::escape($name), self::escape($name), $count);
        }

        $sidebar = '<aside><div class="box"><h3>About</h3>'
            . '<p style="margin:0;color:#41525f">A Symfony-demo-style blog rendered entirely with '
            . '<b>php-standard-library</b>. Every page flows through native generics: '
            . 'Paginated&lt;array&gt;, Listing&lt;array&gt;, Counts&lt;string&gt; and PSL Vector/Map/Option.</p></div>'
            . Str\format(
                '<div class="box"><h3>Tags &middot; %d uses</h3><div class="cloud">%s</div></div></aside>',
                $tagCloud->total(),
                Str\join($cloudParts, ' '),
            );

        $heading = Str\format(
            '<h1>Recent posts</h1><p class="meta">page %d of %d &middot; %d posts total</p>',
            $page->page,
            $page->pages(),
            $page->total,
        );

        return self::layout('Recent posts', $heading . Str\join($items, "\n"), $sidebar);
    }

    public static function post(array $post, Vector<array> $comments, Vector<array> $tags): string
    {
        $tagLinks = Vec\map($tags->toArray(), static fn(array $t): string => Str\format('<a href="/tag/%s">#%s</a>', self::escape($t['name']), self::escape($t['name'])));

        $paragraphs = Vec\map(
            Str\split($post['content'], "\n\n"),
            static fn(string $para): string => '<p>' . self::escape($para) . '</p>',
        );

        $commentHtml = Vec\map($comments->toArray(), static fn(array $c): string => Str\format(
            "<li><strong>%s</strong> <span>%s</span><br>%s</li>",
            self::escape($c['author_name']),
            self::escape($c['published_at']),
            self::escape($c['content']),
        ));

        $body = Str\format(
            "<article class=\"post\"><h1>%s</h1><p class=\"meta\">by <b>%s</b> &middot; %s</p>\n"
            . "<p class=\"tags\">%s</p>\n<div class=\"post-body\">%s</div>\n</article>\n"
            . "<section class=\"comments\"><h3>%d comments</h3><ul>%s</ul></section>",
            self::escape($post['title']),
            self::escape($post['author_name']),
            self::escape(self::date($post['published_at'])),
            Str\join($tagLinks, ' '),
            Str\join($paragraphs, "\n"),
            $comments->count(),
            Str\join($commentHtml, "\n"),
        );

        return self::layout($post['title'], $body);
    }

    public static function listing(Listing<array> $listing): string
    {
        $items = Vec\map($listing->rows(), static fn(array $p): string => Str\format(
            "<li><a href=\"/post/%s\">%s</a> <span>&middot; by %s &middot; %d comments</span></li>",
            self::escape($p['slug']),
            self::escape($p['title']),
            self::escape($p['author_name']),
            $p['comment_count'],
        ));

        $count = $listing->count();
        $body = Str\format(
            "<h1>%s</h1><p class=\"meta\">%d post%s</p><ul class=\"list\">%s</ul>",
            self::escape($listing->heading),
            $count,
            $count === 1 ? '' : 's',
            Str\join($items, "\n"),
        );

        return self::layout($listing->heading, $body);
    }

    public static function apiPosts(Vector<array> $posts): string
    {
        $data = Vec\map($posts->toArray(), static fn(array $p): array => [
            'slug'     => $p['slug'],
            'title'    => $p['title'],
            'author'   => $p['author_name'],
            'comments' => $p['comment_count'],
        ]);

        return \Psl\Json\encode(['posts' => $data, 'count' => $posts->count()]);
    }

    public static function notFound(string $path): string
    {
        return self::layout('Not found', Str\format('<h1>404</h1><p>No route for <code>%s</code>.</p>', self::escape($path)));
    }
}
