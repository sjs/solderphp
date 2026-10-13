<?php

declare(strict_types=1);

/**
 * A small URL-to-class router derived from GluePHP by Joe Topjian.
 * See LICENSE for the original copyright and terms.
 */
class solder
{
    /** @var list<string> */
    private static array $allowedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Allow additional HTTP methods for subsequent dispatches.
     * Names must also be safe PHP handler method names; magic methods are excluded.
     *
     * @throws InvalidArgumentException For invalid method names.
     */
    public static function addMethods(string ...$methods): void
    {
        $normalized = [];
        foreach ($methods as $method) {
            if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $method) !== 1) {
                throw new InvalidArgumentException('HTTP methods must start with an ASCII letter and contain only letters, digits, or underscores.');
            }
            $normalized[] = strtoupper($method);
        }

        // Validate every name before changing the allowlist.
        self::$allowedMethods = array_values(array_unique([...self::$allowedMethods, ...$normalized]));
    }

    /**
     * Routes are case-insensitive regular expressions matched against the whole
     * path with an optional trailing slash. Reverse key sorting preserves the
     * original route precedence. Handlers receive a single regex matches array.
     *
     * @param array<string, class-string> $urls
     * @throws InvalidArgumentException For invalid requests or route patterns.
     * @throws RuntimeException When no route or handler class exists.
     * @throws BadMethodCallException For unsupported verbs or missing public handlers.
     */
    public static function dispatch(array $urls, ?string $method = null, ?string $uri = null): void
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? null);
        $uri = $uri ?? ($_SERVER['REQUEST_URI'] ?? null);
        if (!is_string($method) || $method === '' || !is_string($uri) || $uri === '') {
            throw new InvalidArgumentException('A request method and URI are required.');
        }

        $method = strtoupper($method);
        if (!in_array($method, self::$allowedMethods, true)) {
            throw new BadMethodCallException("Method, $method, not supported.");
        }

        // Preserve encoded characters and leading double slashes in the path.
        $path = explode('?', $uri, 2)[0];
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('The request URI must start with /.');
        }

        krsort($urls);
        foreach ($urls as $route => $class) {
            if (!is_string($route) || !is_string($class) || $class === '') {
                throw new InvalidArgumentException('Routes must map string patterns to class names.');
            }

            // Avoid changing existing escapes and regex character classes.
            $delimiter = null;
            foreach (['~', '#', '%', '!', ';', '@', '`'] as $candidate) {
                if (!str_contains($route, $candidate)) {
                    $delimiter = $candidate;
                    break;
                }
            }
            if ($delimiter === null) {
                throw new InvalidArgumentException('The route uses every supported regex delimiter.');
            }

            $pattern = $delimiter . '\\A(?:' . $route . ')/?\\z' . $delimiter . 'i';
            $matched = @preg_match($pattern, $path, $matches);
            if ($matched === false) {
                throw new InvalidArgumentException("Invalid route pattern, $route: " . preg_last_error_msg());
            }
            if ($matched === 0) {
                continue;
            }

            if (!class_exists($class)) {
                throw new RuntimeException("Class, $class, not found.");
            }
            $handler = new $class();
            if (!method_exists($handler, $method) || !(new ReflectionMethod($handler, $method))->isPublic()) {
                throw new BadMethodCallException("Method, $method, not supported.");
            }

            $handler->$method($matches);
            return;
        }

        throw new RuntimeException("URL, $path, not found.");
    }
}
