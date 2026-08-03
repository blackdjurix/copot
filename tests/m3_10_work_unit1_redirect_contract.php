<?php

require_once __DIR__ . '/../bootstrap/autoload.php';

use Copot\Core\Redirect\RedirectContract;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\UnresolvedRouteResolver;

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$responseValue = static function (Response $response, string $property): mixed {
    $reflection = new ReflectionProperty(Response::class, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($response);
};

$resolver = new class implements UnresolvedRouteResolver {
    public int $calls = 0;

    public function resolve(Request $request): ?Response
    {
        $this->calls++;

        return Response::redirect('/destination', 301);
    }
};

$router = new Router();
$router->setUnresolvedRouteResolver($resolver);
$router->get('/exact', static fn (): Response => Response::html('exact'));
$router->get('/content/{slug}', static fn (): Response => Response::html('handler 404', 404));

$exact = $router->dispatchResult(new Request('GET', '/exact'));
$assert($exact->routeMatched(), 'Exact route must be reported as matched.');
$assert($responseValue($exact->response(), 'content') === 'exact', 'Exact route response must be preserved.');
$assert($resolver->calls === 0, 'Fallback must not run after an exact match.');

$handler404 = $router->dispatchResult(new Request('GET', '/content/missing'));
$assert($handler404->routeMatched(), 'Pattern route must be reported as matched.');
$assert($responseValue($handler404->response(), 'status') === 404, 'Handler-generated 404 must remain final.');
$assert($resolver->calls === 0, 'Fallback must not reinterpret a handler-generated 404.');

$fallback = $router->dispatchResult(new Request('GET', '/legacy'));
$assert(!$fallback->routeMatched(), 'Unmatched request must be reported as unmatched.');
$assert($responseValue($fallback->response(), 'status') === 301, 'Eligible GET fallback response must be returned.');
$assert($responseValue($fallback->response(), 'headers')['Location'] === '/destination', 'Fallback target must be preserved.');
$assert($resolver->calls === 1, 'Fallback must run once for an unmatched GET.');

$postResolver = new class implements UnresolvedRouteResolver {
    public int $calls = 0;

    public function resolve(Request $request): ?Response
    {
        $this->calls++;

        return Response::redirect('/should-not-run');
    }
};
$postRouter = new Router();
$postRouter->setUnresolvedRouteResolver($postResolver);
$post = $postRouter->dispatchResult(new Request('POST', '/legacy'));
$assert(!$post->routeMatched(), 'Unmatched POST must remain unmatched.');
$assert($responseValue($post->response(), 'status') === 404, 'Fallback must be restricted to public GET resolution.');
$assert($postResolver->calls === 0, 'Fallback must not run for POST.');

$failedResolver = new class implements UnresolvedRouteResolver {
    public function resolve(Request $request): ?Response
    {
        throw new RuntimeException('resolver failure');
    }
};
$failedRouter = new Router();
$failedRouter->setUnresolvedRouteResolver($failedResolver);
$failed = $failedRouter->dispatchResult(new Request('GET', '/legacy'));
$assert($responseValue($failed->response(), 'status') === 404, 'Fallback failure must fail closed to the normal 404.');

$duplicateFailed = false;
try {
    $router->setUnresolvedRouteResolver($failedResolver);
} catch (RuntimeException) {
    $duplicateFailed = true;
}
$assert($duplicateFailed, 'Only one unresolved-route resolver may be registered.');

$stringRouter = new Router();
$stringRouter->get('/string', static fn (): string => 'body');
$stringResponse = $stringRouter->dispatch(new Request('GET', '/string'));
$assert($responseValue($stringResponse, 'content') === 'body', 'String route handler compatibility must remain intact.');

$patternRouter = new Router();
$patternRouter->get('/{first}', static fn (Request $request, array $params): string => $params['first']);
$patternRouter->get('/{second}', static fn (Request $request, array $params): string => 'second');
$patternResponse = $patternRouter->dispatch(new Request('GET', '/ordered'));
$assert($responseValue($patternResponse, 'content') === 'ordered', 'Existing pattern insertion order must remain intact.');

$duplicateRouteFailed = false;
try {
    $stringRouter->get('/string', static fn (): string => 'duplicate');
} catch (RuntimeException) {
    $duplicateRouteFailed = true;
}
$assert($duplicateRouteFailed, 'Existing duplicate exact-route protection must remain intact.');

$assert(RedirectContract::source('legacy-page') === '/legacy-page', 'Source leading slash normalization failed.');
$assert(RedirectContract::source('/Legacy/Page/') === '/Legacy/Page', 'Source trailing slash normalization failed.');
$assert(RedirectContract::source('/a%20b') === '/a%20b', 'Non-structural percent encoding must be preserved.');
$assert(RedirectContract::source('/' . str_repeat('a', 511)) !== '', '512-byte source boundary must be accepted.');

foreach (['/', '//host', '/a//b', '/a?x=1', '/a#frag', '/a\\b', "/a\nb", "/a\0b", '/a/./b', '/a/../b', '/a/%2F/b', '/a/%5c/b', '/a/%2e%2e/b', '/a/%00/b', '/a/%0A/b', '/a/%zz', 'https://example.test/a'] as $source) {
    $rejected = false;
    try {
        RedirectContract::source($source);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $assert($rejected, "Invalid source was accepted: {$source}");
}

foreach (['/install', '/install/setup', '/admin', '/admin/settings', '/admin-assets/app.js', '/theme-assets/default.css', '/site-assets/logo.svg', '/content/article', '/login', '/protected'] as $source) {
    $rejected = false;
    try {
        RedirectContract::source($source);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $assert($rejected, "Reserved source was accepted: {$source}");
}
$configuredAdminRejected = false;
try {
    RedirectContract::source('/dapur/old', '/dapur');
} catch (InvalidArgumentException) {
    $configuredAdminRejected = true;
}
$assert($configuredAdminRejected, 'Configured Admin namespace must be reserved.');

foreach (['/new/path', '/?tab=one#top', 'http://example.test/path', 'https://example.test'] as $target) {
    $assert(RedirectContract::target($target) === $target, "Valid target changed: {$target}");
}

foreach (['//example.test/path', 'javascript:alert(1)', 'data:text/plain,blocked', 'ftp://example.test', 'https://user:pass@example.test', 'http:///path', "https://example.test/\n", 'https://example.test/%zz'] as $target) {
    $rejected = false;
    try {
        RedirectContract::target($target);
    } catch (InvalidArgumentException) {
        $rejected = true;
    }
    $assert($rejected, "Invalid target was accepted: {$target}");
}

$assert(RedirectContract::status() === 302, 'Default redirect status must be 302.');
$assert(RedirectContract::status(301) === 301, '301 redirect status must be allowed.');
$assert(RedirectContract::status('302') === 302, 'Numeric status input must remain compatible.');
$statusRejected = false;
try {
    RedirectContract::status(307);
} catch (InvalidArgumentException) {
    $statusRejected = true;
}
$assert($statusRejected, 'Non-allowlisted redirect status was accepted.');

RedirectContract::assertNotSelfRedirect('/old', '/new');
$selfRejected = false;
try {
    RedirectContract::assertNotSelfRedirect('/old/', '/old?from=legacy');
} catch (InvalidArgumentException) {
    $selfRejected = true;
}
$assert($selfRejected, 'Self-redirect invariant was not enforced.');
RedirectContract::assertNotSelfRedirect('/old', 'https://example.test/old');

echo "M3.10 WU1 redirect contract passed ({$assertions} assertions).\n";
