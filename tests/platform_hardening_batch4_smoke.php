<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Csrf;
use Copot\Core\PermissionChecker;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

ini_set('display_errors', '1');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;

    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . '.');
    }
};
$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty($response, $property))->getValue($response);
};

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = new Config($basePath . '/config');
$adminUrl = new AdminUrl($config);
$view = new View($basePath . '/resources/views');
$navigation = new AdminNavigation();
$navigation->add('Dashboard', $adminUrl->baseUrl());

$permissionChecker = new class() extends PermissionChecker {
    public function __construct()
    {
    }

    public function userHasRole(int $userId, string $role): bool
    {
        return false;
    }

    public function userCan(int $userId, string $permission): bool
    {
        return in_array($permission, ['admin.access', 'content.update'], true);
    }
};

$allowedUser = new User([
    'id' => 1,
    'name' => 'Admin Fixture',
    'email' => 'admin@example.test',
    'password_hash' => 'fixture',
    'status' => 'active',
], $permissionChecker);

$deniedPermissionChecker = new class() extends PermissionChecker {
    public function __construct()
    {
    }

    public function userHasRole(int $userId, string $role): bool
    {
        return false;
    }

    public function userCan(int $userId, string $permission): bool
    {
        return false;
    }
};

$deniedUser = new User([
    'id' => 2,
    'name' => 'Denied Fixture',
    'email' => 'denied@example.test',
    'password_hash' => 'fixture',
    'status' => 'active',
], $deniedPermissionChecker);

$authFor = static function (?User $user): Auth {
    return new class($user) extends Auth {
        public function __construct(private ?User $fixtureUser)
        {
        }

        public function check(): bool
        {
            return $this->fixtureUser instanceof User;
        }

        public function user(): ?User
        {
            return $this->fixtureUser;
        }
    };
};

$csrf = new class() extends Csrf {
    public function __construct()
    {
    }

    public function token(): string
    {
        return 'fixture-csrf-token';
    }
};

$pageRenderer = new AdminPageRenderer(
    $view,
    $adminUrl,
    $navigation,
    'Copot',
    'Fixture Site',
    'en_US'
);

$renderer = new AdminErrorRenderer(
    $view,
    $pageRenderer,
    $adminUrl,
    $authFor($allowedUser),
    $csrf,
    'admin.access'
);

$adminRequest = new Request('GET', $adminUrl->childUrl('missing'));
$reference = 'ERR-1234567890ABCDEF12345678';

foreach ([403, 404, 419, 500, 503] as $status) {
    $response = $renderer->response($adminRequest, $status, $status === 500 ? $reference : null);
    $assertSame($status, $responseValue($response, 'status'), "Admin error status {$status} must be preserved.");
    $body = (string) $responseValue($response, 'content');
    $assert(str_contains($body, 'admin-shell'), "Eligible Admin error {$status} must render inside the Admin shell.");
    $assert(!str_contains($body, 'RuntimeException'), "Admin error {$status} must not leak exception detail.");
}

$serverError = $renderer->response($adminRequest, 500, $reference);
$serverBody = (string) $responseValue($serverError, 'content');
$assert(str_contains($serverBody, $reference), 'Eligible unexpected Admin error must show the supplied safe reference.');

$deniedRenderer = new AdminErrorRenderer(
    $view,
    $pageRenderer,
    $adminUrl,
    $authFor($deniedUser),
    $csrf,
    'admin.access'
);
$deniedResponse = $deniedRenderer->response($adminRequest, 403);
$assertSame(403, $responseValue($deniedResponse, 'status'), 'Denied base Admin access must preserve 403.');
$assert(!str_contains((string) $responseValue($deniedResponse, 'content'), 'admin-shell'),
    'User without base Admin access must receive standalone 403.');

$guestRenderer = new AdminErrorRenderer(
    $view,
    $pageRenderer,
    $adminUrl,
    $authFor(null),
    $csrf,
    'admin.access'
);
$guestResponse = $guestRenderer->response($adminRequest, 404);
$assert(!str_contains((string) $responseValue($guestResponse, 'content'), 'admin-shell'),
    'Guest Admin errors must remain standalone.');

$publicResponse = $renderer->response(new Request('GET', '/missing'), 500, $reference);
$assert(!str_contains((string) $responseValue($publicResponse, 'content'), 'admin-shell'),
    'Public paths must never receive the Admin shell.');

$invalidReferenceResponse = $renderer->response($adminRequest, 500, 'ERR-not-valid');
$assert(!str_contains((string) $responseValue($invalidReferenceResponse, 'content'), 'ERR-not-valid'),
    'Invalid error references must not be rendered.');

$initialLevel = ob_get_level();
$bufferResponse = $renderer->response($adminRequest, 404);
$assertSame($initialLevel, ob_get_level(), 'Admin error rendering must restore the exact output-buffer level.');
$assertSame(404, $responseValue($bufferResponse, 'status'), 'Buffer test must preserve response status.');

$applicationSource = file_get_contents($basePath . '/app/Core/Application.php') ?: '';
$bootstrapSource = file_get_contents($basePath . '/bootstrap/app.php') ?: '';
$fallbackSource = file_get_contents($basePath . '/routes/admin_fallback.php') ?: '';
$adminRoutesSource = file_get_contents($basePath . '/routes/admin.php') ?: '';
$contentRoutesSource = file_get_contents($basePath . '/modules/content/routes.php') ?: '';
$taxonomyRoutesSource = file_get_contents($basePath . '/modules/taxonomy/routes.php') ?: '';

$assert(substr_count($applicationSource, "'application.dispatch.failure'") === 1,
    'Application dispatch failure must retain one diagnostics event call site.');
$assert(str_contains($applicationSource, '$this->adminErrors->response($request, 500, $reference)'),
    'Unexpected dispatch failures must attempt Admin-safe recovery with the original reference.');
$assert(strpos($bootstrapSource, 'loadRoutes($app)') < strpos($bootstrapSource, "routes/admin_fallback.php"),
    'Admin fallback routes must register after module routes.');
$assert(str_contains($fallbackSource, "childUrl('{path}')"),
    'Admin fallback route must remain scoped under the configured Admin path.');
$assert(substr_count($fallbackSource, 'adminErrors()->response($request, 404)') === 2,
    'Admin fallback must cover both GET and POST.');
$assert(!str_contains($adminRoutesSource, "Response::html('403 Forbidden', 403)"),
    'Core Admin feature denials must use the shared Admin error boundary.');
$assert(!str_contains($contentRoutesSource, "Response::html('404 Not Found', 404)"),
    'Content Admin missing resources must use the shared Admin error boundary.');
$assert(!str_contains($taxonomyRoutesSource, "Response::html('404 Not Found', 404)"),
    'Taxonomy Admin missing resources must use the shared Admin error boundary.');
$assert(!str_contains($applicationSource, 'set_exception_handler')
    && !str_contains($applicationSource, 'set_error_handler'),
    'Batch 4 must not add global PHP handlers.');

echo "M2.4 Batch 4 Admin in-shell error smoke tests passed ({$assertions} assertions)." . PHP_EOL;
