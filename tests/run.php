<?php

declare(strict_types=1);

require dirname(__DIR__) . '/solder.php';

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

class TestHandler
{
    public static array $calls = [];
    public function GET(array $matches): void { self::$calls[] = ['GET', $matches]; }
    public function POST(array $matches): void { self::$calls[] = ['POST', $matches]; }
    protected function DELETE(array $matches): void {}
    private function PATCH(array $matches): void {}
}
class SpecificHandler extends TestHandler
{
    public function GET(array $matches): void { self::$calls[] = ['specific', $matches]; }
}

$passed = 0;
function check(bool $condition, string $label): void
{
    global $passed;
    if (!$condition) {
        throw new RuntimeException("FAIL: $label");
    }
    ++$passed;
}
function rejects(string $exception, callable $action, string $label): void
{
    try {
        $action();
    } catch (Throwable $error) {
        check($error instanceof $exception, $label . ': ' . $error::class);
        return;
    }
    throw new RuntimeException("FAIL: $label did not throw");
}
function dispatch(array $routes, string $method, string $uri): array
{
    TestHandler::$calls = [];
    solder::dispatch($routes, $method, $uri);
    check(count(TestHandler::$calls) === 1, "one dispatch for $uri");
    return TestHandler::$calls[0];
}

$routes = ['/' => TestHandler::class, '/page/(\d+)' => TestHandler::class];
check(dispatch($routes, 'GET', '/')[1] === ['/'], 'root');
check(dispatch($routes, 'get', '/PAGE/42/?preview=1') === ['GET', ['/PAGE/42/', '42']], 'captures, case, slash, query');
check(dispatch($routes, 'POST', '/page/7')[0] === 'POST', 'POST');
check(dispatch(['/one|/two' => TestHandler::class], 'GET', '/two')[1][0] === '/two', 'alternation');
rejects(RuntimeException::class, fn() => solder::dispatch(['/one|/two' => TestHandler::class], 'GET', '/one/extra'), 'anchored alternation');
rejects(RuntimeException::class, fn() => solder::dispatch($routes, 'GET', "/page/7\n"), 'strict end anchor');
check(dispatch(['/page/.*' => TestHandler::class, '/page/special' => SpecificHandler::class], 'GET', '/page/special')[0] === 'specific', 'reverse key precedence');
check(dispatch(['/file\.json' => TestHandler::class], 'GET', '/file.json')[1][0] === '/file.json', 'literal dot');
check(dispatch(['/a\/b' => TestHandler::class], 'GET', '/a/b')[1][0] === '/a/b', 'escaped slash');
check(dispatch(['/[^/]+/item' => TestHandler::class], 'GET', '/abc/item')[1][0] === '/abc/item', 'slash in character class');
check(dispatch(['/a~b#c' => TestHandler::class], 'GET', '/a~b#c')[1][0] === '/a~b#c', 'delimiter selection');
check(dispatch(['/encoded/(.+)' => TestHandler::class], 'GET', '/encoded/a%2Fb')[1][1] === 'a%2Fb', 'encoded path retained');
check(dispatch(['//host/path' => TestHandler::class], 'GET', '//host/path')[1][0] === '//host/path', 'leading double slash retained');
foreach (['PUT', 'DELETE', 'PATCH', '__missing'] as $method) {
    rejects(BadMethodCallException::class, fn() => solder::dispatch($routes, $method, '/'), "unsupported $method");
}
rejects(RuntimeException::class, fn() => solder::dispatch($routes, 'GET', '/missing'), 'missing route');
rejects(RuntimeException::class, fn() => solder::dispatch(['/' => 'NonexistentHandler'], 'GET', '/'), 'missing class');
rejects(InvalidArgumentException::class, fn() => solder::dispatch(['/[broken' => TestHandler::class], 'GET', '/'), 'invalid regex');
rejects(InvalidArgumentException::class, fn() => solder::dispatch([TestHandler::class], 'GET', '/'), 'invalid key');
rejects(InvalidArgumentException::class, fn() => solder::dispatch(['/' => []], 'GET', '/'), 'invalid class value');
rejects(InvalidArgumentException::class, fn() => solder::dispatch($routes, '', '/'), 'empty method');
rejects(InvalidArgumentException::class, fn() => solder::dispatch($routes, 'GET', 'relative'), 'relative URI');
unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
rejects(InvalidArgumentException::class, fn() => solder::dispatch($routes), 'missing server request');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/page/99?x=1';
TestHandler::$calls = [];
solder::dispatch($routes);
check(TestHandler::$calls === [['GET', ['/page/99', '99']]], 'server request');
echo "Passed $passed checks.\n";
