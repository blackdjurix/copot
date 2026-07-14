<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Application;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Csrf;
use Copot\Core\Env;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleLoader;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm33batch3sec' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

final class Batch3SecurityUser extends User
{
    public function __construct(private array $grants) {}
    public function id(): int { return 933003; }
    public function name(): string { return 'Batch 3 Security'; }
    public function email(): string { return 'batch3-security@example.test'; }
    public function status(): string { return 'active'; }
    public function isActive(): bool { return true; }
    public function can(string $permission): bool { return in_array($permission, $this->grants, true); }
}

final class Batch3SecurityAuth extends Auth
{
    public function __construct(public ?User $actor) {}
    public function check(): bool { return $this->actor instanceof User; }
    public function user(): ?User { return $this->actor; }
}

final class Batch3SecurityCsrf extends Csrf
{
    public int $calls = 0;
    public function __construct(public string $accepted = 'batch3-csrf') {}
    public function token(): string { return $this->accepted; }
    public function validateOrReject(Request $request, string $field = '_token'): ?Response
    {
        $this->calls++;
        return $request->post($field) === $this->accepted ? null : Response::html('RAW_CSRF_DETAIL', 419);
    }
}

final class Batch3ThrowingModules extends ModuleManager
{
    public int $calls = 0;
    public function __construct() {}
    public function install(string $name): void { $this->calls++; throw new RuntimeException('RAW_LIFECYCLE_EXCEPTION'); }
    public function enable(string $name): void { $this->calls++; throw new RuntimeException('RAW_LIFECYCLE_EXCEPTION'); }
    public function disable(string $name): void { $this->calls++; throw new RuntimeException('RAW_LIFECYCLE_EXCEPTION'); }
    public function uninstall(string $name): void { $this->calls++; throw new RuntimeException('RAW_LIFECYCLE_EXCEPTION'); }
}

final class Batch3SecurityApplication extends Application
{
    public function __construct(string $basePath, private ModuleManager $modulesOverride)
    {
        parent::__construct($basePath);
    }

    public function modules(): ModuleManager
    {
        return $this->modulesOverride;
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) { throw new RuntimeException($message); }
};
$value = static fn (Response $response, string $property): mixed =>
    (new ReflectionProperty(Response::class, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');

$fixtureName = 'batch3security' . bin2hex(random_bytes(5));
$fixtureDirectory = $basePath . '/modules/' . $fixtureName;
$setupApp = new Application($basePath);
$setupApp->session()->start();
$moduleManagerPresent = false;
$moduleManagerInitialStatus = null;
foreach ($setupApp->modules()->installed() as $module) {
    if (($module['name'] ?? null) === 'module-manager') {
        $moduleManagerPresent = true;
        $moduleManagerInitialStatus = (string) ($module['status'] ?? '');
        if (($module['status'] ?? null) !== 'enabled') { $setupApp->modules()->enable('module-manager'); }
    }
}
if (!$moduleManagerPresent) {
    $setupApp->modules()->install('module-manager');
    $setupApp->modules()->enable('module-manager');
}

$throwingModules = new Batch3ThrowingModules();
$app = new Batch3SecurityApplication($basePath, $throwingModules);
$app->session()->start();

try {
    mkdir($fixtureDirectory, 0777, true);
    file_put_contents($fixtureDirectory . '/module.json', json_encode([
        'name' => $fixtureName,
        'title' => 'Security <Fixture>',
        'version' => '1.0.0',
        'requires' => ['modules' => []],
        'permissions' => [],
    ], JSON_THROW_ON_ERROR));

    $auth = new Batch3SecurityAuth(new Batch3SecurityUser([]));
    $csrf = new Batch3SecurityCsrf();
    $adminUrl = $app->adminUrl();
    $navigation = new AdminNavigation();
    $router = new Router();
    $view = new View($basePath . '/resources/views');
    $pages = new AdminPageRenderer($view, $adminUrl, $navigation, 'Copot', 'copot', 'en');
    $errors = new AdminErrorRenderer($view, $pages, $adminUrl, $auth, $csrf, 'admin.access');
    $authProperty = new ReflectionProperty(Application::class, 'auth');
    $authProperty->setValue($app, $auth);
    $csrfProperty = new ReflectionProperty(Application::class, 'csrf');
    $csrfProperty->setValue($app, $csrf);
    (new ReflectionProperty(Application::class, 'adminNavigation'))->setValue($app, $navigation);
    (new ReflectionProperty(Application::class, 'adminPageRenderer'))->setValue($app, $pages);
    $errorProperty = new ReflectionProperty(Application::class, 'adminErrors');
    $errorProperty->setValue($app, new AdminErrorRenderer($view, $pages, $adminUrl, $auth, $csrf, 'admin.access'));
    $app->moduleLoader()->loadRoutes($app);

    foreach ([
        [[], 'guest'],
        [['admin.access'], 'admin-only'],
        [['modules.manage'], 'module-only'],
        [['content.update'], 'unrelated'],
    ] as [$grants, $label]) {
        $auth->actor = new Batch3SecurityUser($grants);
        $csrf->calls = 0;
        $get = $app->router()->dispatch(new Request('GET', '/admin/modules'));
        $assert($status($get) === 403 || $status($get) === 302, "{$label} inventory authorization was not enforced.");
        foreach (['install', 'enable', 'disable', 'uninstall'] as $action) {
            $response = $app->router()->dispatch(new Request('POST', '/admin/modules/' . $action, [], [
                '_token' => 'invalid', 'module' => $fixtureName,
            ]));
            $assert($status($response) === 403 || $status($response) === 302, "{$label} {$action} authorization was not enforced.");
        }
        $assert($csrf->calls === 0, "{$label} reached CSRF before authorization.");
        $visible = $navigation->itemsFor($auth->actor);
        $hasModules = array_filter($visible, static fn (array $item): bool => ($item['label'] ?? null) === 'Modules');
        $assert(($grants === ['modules.manage'] || $grants === ['admin.access', 'modules.manage'])
            ? $hasModules !== [] : $hasModules === [], "{$label} navigation visibility was incorrect.");
    }

    $auth->actor = new Batch3SecurityUser(['admin.access', 'modules.manage']);
    $csrf->calls = 0;
    $csrfFailure = $app->router()->dispatch(new Request('POST', '/admin/modules/install', [], [
        '_token' => 'invalid', 'module' => $fixtureName,
    ]));
    $assert($status($csrfFailure) === 419, 'Invalid CSRF was not rejected.');
    $assert($csrf->calls === 1, 'Mutation did not validate CSRF exactly once.');
    $assert(!str_contains($content($csrfFailure), 'RAW_CSRF_DETAIL'), 'Raw CSRF detail leaked.');
    $assert($status($app->router()->dispatch(new Request('GET', '/admin/modules/install'))) === 404, 'GET mutation route was registered.');

    $invalid = $app->router()->dispatch(new Request('POST', '/admin/modules/install', [], [
        '_token' => 'batch3-csrf', 'module' => '../unsafe',
    ]));
    $assert($status($invalid) === 422, 'Invalid module name was not rejected.');
    $assert(!str_contains($content($invalid), '..' . DIRECTORY_SEPARATOR), 'Unsafe module path leaked.');

    foreach (['disable', 'uninstall'] as $action) {
        $self = $app->router()->dispatch(new Request('POST', '/admin/modules/' . $action, [], [
            '_token' => 'batch3-csrf', 'module' => 'module-manager',
        ]));
        $assert($status($self) === 422, "Self-{$action} was not denied.");
        $assert(str_contains($content($self), 'Module Manager cannot disable or uninstall itself.'), "Self-{$action} denial reason was not rendered.");
    }

    $throwing = $app->router()->dispatch(new Request('POST', '/admin/modules/install', [], [
        '_token' => 'batch3-csrf', 'module' => $fixtureName,
    ]));
    $assert($status($throwing) === 503, 'Lifecycle exception was not sanitized as 503.');
    $throwingHtml = $content($throwing);
    $assert(!str_contains($throwingHtml, 'RAW_LIFECYCLE_EXCEPTION') && !str_contains($throwingHtml, 'RuntimeException'),
        'Lifecycle exception detail leaked.');
    $assert($throwingModules->calls === 1, 'Eligible lifecycle action did not reach the ModuleManager boundary.');

    echo "M3.3 Batch 3 Module Manager security passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (is_file($fixtureDirectory . '/module.json')) { unlink($fixtureDirectory . '/module.json'); }
    if (is_dir($fixtureDirectory)) { rmdir($fixtureDirectory); }
    if (!$moduleManagerPresent) {
        try {
            $setupApp->modules()->disable('module-manager');
            $setupApp->modules()->uninstall('module-manager');
        } catch (Throwable) {
        }
    } elseif ($moduleManagerInitialStatus === 'disabled') {
        try { $setupApp->modules()->disable('module-manager'); } catch (Throwable) { }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}
