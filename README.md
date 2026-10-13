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
HTTP verbs they accept. By default, the router allows `GET`, `HEAD`, `POST`, `PUT`,
`PATCH`, `DELETE`, and `OPTIONS`; other methods are rejected
before constructing a handler. Each method receives the regex
matches array, with the full matched path at index `0` and captures at `1` onward.
Handler return values are ignored; handlers manage their own response.

To allow additional methods, register them before dispatching:

```php
solder::addMethods('PROPFIND', 'MKCOL');
solder::dispatch($routes);
```

Registration adds to the defaults and applies to subsequent dispatches in the
current PHP process. Names are normalized to uppercase and duplicates are ignored.
Names must start with an ASCII letter and contain only letters, digits, or
underscores so they can map to PHP methods; magic method names are rejected.
If any name is invalid, none of the names in that call are added.
Each registered method still requires a public handler method. Register only
methods you intend to expose to HTTP requests.

Patterns are regular expressions, matched case-insensitively against the entire
path with an optional trailing slash. Escape regex metacharacters for literal
text (for example `/file\.json`). Query strings are excluded; encoded path
characters are preserved. Routes are tried in declaration order, and the first
matching route wins. Put specific routes before broad fallback patterns:

```php
solder::dispatch([
    '/page/special' => SpecialPage::class,
    '/page/.*' => Page::class,
]);
```

Here `/page/special` selects `SpecialPage`; other paths matching `/page/.*`
select `Page`. Alternation is grouped so that `/one|/two` matches only those
complete paths.

For CLI use or tests, supply a method and URI explicitly:

```php
solder::dispatch(['/page/(\d+)' => Page::class], 'GET', '/page/42?preview=1');
```

## Error handling

An unmatched path throws `RouteNotFoundException`. A missing or unusable handler
class throws `HandlerConfigurationException`: handlers must be instantiable with
no constructor arguments. Both exceptions extend `RuntimeException`.

For a matching path, unsupported or non-public HTTP methods throw
`MethodNotAllowedException`, which extends `BadMethodCallException`.
Its `getAllowedMethods()` accessor returns registered verbs implemented as public
methods on the selected handler, including inherited methods. HEAD and OPTIONS
are included only when explicitly implemented. Method support is checked before
constructing the handler, and rejection never falls through to later routes.
An unmatched path throws `RouteNotFoundException` even for an unsupported verb.

Invalid patterns, route mappings, or missing request information throw
`InvalidArgumentException`. Exceptions thrown by constructors and handler methods
propagate unchanged. The router does not set HTTP status codes or implement
HEAD/OPTIONS fallback; your entry point controls error responses:

```php
try {
    solder::dispatch($routes);
} catch (RouteNotFoundException $error) {
    http_response_code(404);
    echo 'Not found';
} catch (MethodNotAllowedException $error) {
    http_response_code(405);
    $allowed = $error->getAllowedMethods();
    header('Allow:' . ($allowed === [] ? '' : ' ' . implode(', ', $allowed)));
    echo 'Method not allowed';
} catch (HandlerConfigurationException $error) {
    error_log((string) $error);
    http_response_code(500);
    echo 'Internal server error';
}
```

## Development

Run `php tests/run.php` (or `composer test`). The tests need no external packages.

## License

BSD-3-Clause. See [LICENSE](LICENSE) for the original copyright and terms.
