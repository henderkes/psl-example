<?php

declare(strict_types=1);

namespace Blog;

use Psl\Option;
use Psl\Str;

/** A tiny request value object. */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $query = '',
    ) {
    }
}

/** A tiny response value object. */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly string $contentType = 'text/html; charset=utf-8',
    ) {
    }
}

/**
 * The application kernel. `handle()` is the single request pipeline shared by
 * the live HTTP server (bin/server.php) and the benchmark (bench/workload.php),
 * so the perf numbers reflect exactly the code that serves real traffic.
 */
final class Kernel
{
    private Repository $repo;

    public function __construct(\PDO $pdo)
    {
        $this->repo = new Repository($pdo);
    }

    public function handle(Request $request): Response
    {
        $path = $request->path;

        if ($path === '/') {
            $posts = $this->repo->recentPosts(20);
            $cloud = $this->repo->tagCloud();

            return new Response(200, View::home($posts, $cloud));
        }

        if ($path === '/api/posts') {
            $posts = $this->repo->recentPosts(50);

            return new Response(200, View::apiPosts($posts), 'application/json');
        }

        if (Str\starts_with($path, '/post/')) {
            $slug = Str\after($path, '/post/') ?? '';

            return $this->repo->findBySlug($slug)->proceed(
                function (array $post): Response {
                    $comments = $this->repo->commentsFor($post['id']);
                    $tags = $this->repo->tagsFor($post['id']);

                    return new Response(200, View::post($post, $comments, $tags));
                },
                static fn(): Response => new Response(404, View::notFound($path)),
            );
        }

        if (Str\starts_with($path, '/tag/')) {
            $tag = Str\after($path, '/tag/') ?? '';
            $posts = $this->repo->postsByTag($tag);

            return new Response(200, View::list('Posts tagged #' . $tag, $posts));
        }

        if ($path === '/search') {
            $term = '';
            if ($request->query !== '' && Str\contains($request->query, 'q=')) {
                $term = urldecode(Str\after($request->query, 'q=') ?? '');
            }
            $posts = $this->repo->search($term);

            return new Response(200, View::list('Search: ' . $term, $posts));
        }

        return new Response(404, View::notFound($path));
    }
}
