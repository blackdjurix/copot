<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\DeploymentContext;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\Admin\AdminUrl;

$basePath = dirname(__DIR__);
require_once $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$captureState = static function (DeploymentContext $deployment, string $uri): Request {
    $previous = [];
    foreach (['REQUEST_METHOD', 'REQUEST_URI', 'SCRIPT_NAME'] as $key) {
        $previous[$key] = array_key_exists($key, $_SERVER) ? $_SERVER[$key] : null;
    }
    $previousQuery = $_GET;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['SCRIPT_NAME'] = '/copot/index.php';
    $_GET = ['search' => 'one'];

    try {
        return Request::capture($deployment);
    } finally {
        foreach ($previous as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
        $_GET = $previousQuery;
    }
};

try {
    $root = DeploymentContext::forApplicationRoot($basePath, '/');
    $copot = DeploymentContext::forApplicationRoot($basePath, '/copot');

    $assert($root->url('/') === '/', 'Root URL generation changed.');
    $assert($root->url('/admin') === '/admin', 'Root internal URL generation failed.');
    $assert($copot->url('/') === '/copot', 'Base-path root URL generation failed.');
    $assert($copot->url('/admin') === '/copot/admin', 'Base-path URL generation failed.');
    $assert($copot->url('/admin?tab=one#top') === '/copot/admin?tab=one#top', 'Query or fragment handling failed.');
    $assert($copot->url('/copot/admin') === '/copot/admin', 'Double-prefix protection failed.');
    $assert($copot->url('/admin//settings') === '/copot/admin/settings', 'Duplicate slash normalization failed.');
    $assert($copot->url('https://example.test/admin') === 'https://example.test/admin', 'External URL handling failed.');
    $assert($copot->url('//cdn.example.test/app.css') === '//cdn.example.test/app.css', 'Protocol-relative URL handling failed.');

    try {
        $copot->url('/admin/../login');
        $assert(false, 'URL traversal was accepted.');
    } catch (InvalidArgumentException) {
        $assert(true, 'URL traversal rejection was not reached.');
    }

    $request = $captureState($copot, '/copot/admin/users?search=one');
    $assert($request->path() === '/admin/users', 'Configured base path was not stripped from request path.');
    $assert($request->input('search') === 'one', 'Request query data was not preserved.');
    $request = $captureState($root, '/admin/users');
    $assert($request->path() === '/admin/users', 'Root request path handling changed.');

    $router = new Router($copot);
    $router->get('/admin', static fn (): Response => Response::html('ok'));
    $adminResponse = $router->dispatch(new Request('GET', '/admin'));
    $assert($adminResponse->statusCode() === 200, 'Logical route did not dispatch under a base path.');

    $admin = new AdminUrl(new Config($basePath . '/config'), $copot);
    $assert($admin->baseUrl() === '/copot/admin', 'Admin base URL did not include deployment base path.');
    $assert($admin->childUrl('users/create') === '/copot/admin/users/create', 'Admin child URL did not include deployment base path.');
    $assert($admin->routeChildUrl('users/create') === '/admin/users/create', 'Admin logical route path changed.');

    $adminRouter = new Router($copot);
    $adminRouter->get($admin->childUrl('users'), static fn (): Response => Response::html('ok'));
    $assert($adminRouter->dispatch(new Request('GET', '/admin/users'))->statusCode() === 200, 'Generated Admin route was not normalized to its logical path.');
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo 'Portability WU3 tests passed (' . $assertions . ' assertions).' . PHP_EOL;
