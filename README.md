# solderphp

A small PHP router that maps URL patterns to classes. Derived from
[GluePHP](https://github.com/jtopjian/gluephp), originally written by Joe Topjian.
Requires PHP 8.2 or newer; CI covers PHP 8.2–8.5. No runtime dependencies.

## Usage

Copy `solder.php` into your project and require it. Composer installations
can use `vendor/autoload.php` instead; this repository includes package metadata
and classmap autoloading.

```php
<?php

require __DIR__ . '/solder.php';

class Home
{
    public function GET(array $matches): void
    {
        echo 'Hello from solderphp!';
    }
}

class Page
{
    public function GET(array $matches): void
    {
        echo 'Requested page ' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
    }
}

solder::dispatch([
    '/' => Home::class,
    '/page/(\d+)' => Page::class,
]);
```

Configure your web server to forward requests to your PHP entry point.
Handlers must have a no-argument constructor and public methods named for the
HTTP verbs they accept (`GET`, `POST`, etc.). Each method receives the regex
matches array, with the full matched path at index `0` and captures at `1` onward.
Handler return values are ignored; handlers manage their own response.

Patterns are regular expressions, matched case-insensitively against the entire
path with an optional trailing slash. Escape regex metacharacters for literal
text (for example `/file\.json`). Query strings are excluded; encoded path
characters are preserved. As in GluePHP, routes are sorted by key in reverse
order before matching, and the first matching route wins. Alternation is grouped
so that `/one|/two` matches only those complete paths.

For CLI use or tests, supply a method and URI explicitly:

```php
solder::dispatch(['/page/(\d+)' => Page::class], 'GET', '/page/42?preview=1');
```

Missing routes or classes throw `RuntimeException`; unsupported or non-public
HTTP methods throw `BadMethodCallException`. Invalid patterns, route mappings,
or missing request information throw `InvalidArgumentException`. The router
does not automatically set HTTP status codes, implement HEAD/OPTIONS fallback,
or catch handler exceptions; your entry point controls error responses.

## Development

Run `php tests/run.php` (or `composer test`). The tests need no external packages.

## License

BSD-3-Clause. See [LICENSE](LICENSE) for the original copyright and terms.
