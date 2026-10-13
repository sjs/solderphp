<?php

declare(strict_types=1);

/**
 * A small URL-to-class router derived from GluePHP by Joe Topjian.
 * See LICENSE for the original copyright and terms.
 */
class solder
{
    /**
     * Routes are case-insensitive regular expressions matched against the whole
     * path with an optional trailing slash. Reverse key sorting preserves the
     * original route precedence. Handlers receive a single regex matches array.
     *
     * @param array<string, class-string> $urls
     * @throws InvalidArgumentException For invalid requests or route patterns.
     * @throws RuntimeException When no route or handler class exists.
     * @throws BadMethodCallException When no public HTTP method handler exists.
     */
    public static function dispatch(array $urls, ?string $method = null, ?string $uri = null): void
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? null);
        $uri = $uri ?? ($_SERVER['REQUEST_URI'] ?? null);
        if (!is_string($method) || $method === '' || !is_string($uri) || $uri === '') {
            throw new InvalidArgumentException('A request method and URI are required.');
        }

        $method = strtoupper($method);
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
