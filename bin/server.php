<?php

declare(strict_types=1);

/**
 * A live HTTP/1.1 server for the PSL blog demo, built on PSL's async runtime and
 * TCP listener. Serves the exact same Kernel::handle() pipeline the benchmark
 * measures.
 *
 *   php bin/server.php [host] [port]
 *   curl http://127.0.0.1:8099/
 */

require __DIR__ . '/../src/bootstrap.php';

use Blog\Kernel;
use Blog\Request;
use Blog\Response;
use Psl\Async;
use Psl\Str;
use Psl\TCP;

$host = $argv[1] ?? '127.0.0.1';
$port = (int) ($argv[2] ?? 8099);

Async\main(static function () use ($host, $port): int {
    $kernel = new Kernel(blog_pdo());
    $listener = TCP\listen($host, $port);

    $address = $listener->getLocalAddress();
    fwrite(STDERR, "PSL blog demo listening on http://{$address->host}:{$address->port}\n");

    while (true) {
        $connection = $listener->accept();

        // Handle each connection concurrently on the event loop.
        Async\run(static function () use ($connection, $kernel): void {
            try {
                $raw = $connection->read();
                if ($raw === '') {
                    $connection->close();
                    return;
                }

                $request = parse_http_request($raw);
                $response = $kernel->handle($request);

                $connection->writeAll(render_http_response($response));
            } catch (\Throwable $e) {
                try {
                    $connection->writeAll(render_http_response(new Response(500, 'Internal Server Error', 'text/plain')));
                } catch (\Throwable) {
                    // connection already gone
                }
            } finally {
                $connection->close();
            }
        });
    }
});

function parse_http_request(string $raw): Request
{
    $headerBlock = Str\before($raw, "\r\n\r\n") ?? $raw;
    $lines = Str\split($headerBlock, "\r\n");
    $requestLine = $lines[0] ?? 'GET / HTTP/1.1';
    $parts = Str\split($requestLine, ' ');

    $method = $parts[0] ?? 'GET';
    $target = $parts[1] ?? '/';

    $path = $target;
    $query = '';
    if (Str\contains($target, '?')) {
        $path = Str\before($target, '?') ?? $target;
        $query = Str\after($target, '?') ?? '';
    }

    return new Request($method, $path, $query);
}

function render_http_response(Response $response): string
{
    $reason = match ($response->status) {
        200 => 'OK',
        404 => 'Not Found',
        500 => 'Internal Server Error',
        default => 'OK',
    };

    $body = $response->body;
    $headers = [
        "HTTP/1.1 {$response->status} {$reason}",
        "Content-Type: {$response->contentType}",
        'Content-Length: ' . strlen($body),
        'Connection: close',
        'Server: psl-blog-demo',
    ];

    return Str\join($headers, "\r\n") . "\r\n\r\n" . $body;
}
