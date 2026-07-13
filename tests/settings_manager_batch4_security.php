<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Auth;
use Copot\Core\Csrf;
use Copot\Core\Env;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\User;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm32b4sec' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

final class Batch4PermissionUser extends User
{
    public function __construct(private array $grants)
    {
    }

    public function id(): int { return 900004; }
    public function name(): string { return 'Batch 4 Security'; }
    public function email(): string { return 'batch4-security@example.test'; }
    public function status(): string { return 'active'; }
    public function isActive(): bool { return true; }
    public function can(string $permission): bool { return in_array($permission, $this->grants, true); }
}

final class Batch4AuthSpy extends Auth
{
    public function __construct(public ?User $actor) {}
    public function check(): bool { return $this->actor !== null; }
    public function user(): ?User { return $this->actor; }
}

final class Batch4CsrfSpy extends Csrf
{
    public int $validations = 0;
    public array $receivedTokens = [];
    public function __construct(public string $acceptedToken = 'batch4-csrf') {}
    public function token(): string { return 'batch4-csrf'; }
    public function validateOrReject(Request $request, string $field = '_token'): ?Response
    {
        $this->validations++;
        $token = $request->post($field);
        $this->receivedTokens[] = $token;
        return is_string($token) && hash_equals($this->acceptedToken, $token)
            ? null
            : Response::html('RAW_CSRF_REJECT', 419);
    }
    public function resetCalls(): void { $this->validations = 0; $this->receivedTokens = []; }
}

final class Batch4SecuritySettingsSpy extends SettingsService
{
    public int $definitionsCalls = 0;
    public int $getCalls = 0;
    public int $validateCalls = 0;
    public int $setCalls = 0;

    public function definitions(?string $namespace = null): array
    {
        $this->definitionsCalls++;
        return parent::definitions($namespace);
    }
    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        $this->getCalls++;
        return [
            'site.name' => 'copot', 'site.tagline' => '', 'localization.timezone' => 'UTC',
            'localization.locale' => 'en_US', 'localization.date_format' => 'Y-m-d',
            'localization.time_format' => 'H:i',
        ][$namespace . '.' . $key] ?? $default;
    }
    public function validate(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $this->validateCalls++;
        parent::validate($namespace, $key, $value, $type);
    }
    public function set(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $this->setCalls++;
        parent::set($namespace, $key, $value, $type);
    }
    public function resetCalls(): void
    {
        $this->definitionsCalls = $this->getCalls = $this->validateCalls = $this->setCalls = 0;
    }
}

final class Batch4AssetSpy
{
    public int $urlCalls = 0;
    public int $storeCalls = 0;
    public int $removeCalls = 0;
    public array $state = ['logo' => 'logo-before', 'favicon' => 'favicon-before'];
    public function url(string $slot): ?string { $this->urlCalls++; return null; }
    public function store(string $slot, string $path): void { $this->storeCalls++; $this->state[$slot] = $path; }
    public function remove(string $slot): void { $this->removeCalls++; $this->state[$slot] = null; }
    public function resetCalls(): void { $this->urlCalls = $this->storeCalls = $this->removeCalls = 0; }
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

$app = new Application($basePath);
$app->session()->start();
$settings = new Batch4SecuritySettingsSpy(SettingsRegistry::core(), new SettingsRepository($app->database()));
$auth = new Batch4AuthSpy(null);
$csrf = new Batch4CsrfSpy();
$assets = new Batch4AssetSpy();
$alternate = new class($app, $settings, $auth, $csrf, $assets) {
    public function __construct(private Application $app, private $settingsValue, private $authValue, private $csrfValue, private $assetsValue) {}
    public function config() { return $this->app->config(); }
    public function database() { return $this->app->database(); }
    public function settings() { return $this->settingsValue; }
    public function siteAssets() { return $this->assetsValue; }
    public function session() { return $this->app->session(); }
    public function csrf() { return $this->csrfValue; }
    public function auth() { return $this->authValue; }
    public function router() { return $this->app->router(); }
    public function adminNavigation() { return $this->app->adminNavigation(); }
    public function adminUrl() { return $this->app->adminUrl(); }
    public function adminPageRenderer() { return $this->app->adminPageRenderer(); }
    public function adminErrors() {
        return new Copot\Core\Admin\AdminErrorRenderer(
            new Copot\Core\View(dirname(__DIR__) . '/resources/views'),
            $this->app->adminPageRenderer(), $this->app->adminUrl(), $this->authValue, $this->csrfValue, 'admin.access'
        );
    }
};
(static function ($app) use ($basePath): void { require $basePath . '/modules/settings-manager/routes.php'; })($alternate);

$valid = static fn (): array => [
    'site.name' => 'Batch 4', 'site.tagline' => 'Security', 'localization.timezone' => 'UTC',
    'localization.locale' => 'en_US', 'localization.date_format' => 'Y-m-d', 'localization.time_format' => 'H:i',
];
$paths = [
    ['POST', '/admin/settings'],
    ['POST', '/admin/settings/site-assets/logo'],
    ['POST', '/admin/settings/site-assets/logo/remove'],
    ['POST', '/admin/settings/site-assets/favicon'],
    ['POST', '/admin/settings/site-assets/favicon/remove'],
];

try {
    foreach ([
        'admin-only' => ['admin.access'],
        'update-only' => ['settings.update'],
        'unrelated' => ['content.update'],
    ] as $label => $grants) {
        $auth->actor = new Batch4PermissionUser($grants);
        $settings->resetCalls(); $assets->resetCalls(); $csrf->resetCalls();
        $get = $app->router()->dispatch(new Request('GET', '/admin/settings'));
        $assert($status($get) === 403, "{$label} actor was not denied Settings GET.");
        $assert($settings->definitionsCalls === 0 && $settings->getCalls === 0 && $assets->urlCalls === 0,
            "{$label} denial occurred after definition/effective/asset lookup.");

        foreach ($paths as [$method, $path]) {
            $settings->resetCalls(); $assets->resetCalls(); $csrf->resetCalls();
            $post = ['_token' => 'invalid', 'settings' => $valid()];
            $response = $app->router()->dispatch(new Request($method, $path, [], $post));
            $assert($status($response) === 403, "{$label} actor was not denied [{$path}].");
            $assert($csrf->validations === 0, "{$label} actor reached CSRF for [{$path}].");
            $assert($settings->definitionsCalls === 0 && $settings->getCalls === 0
                && $settings->validateCalls === 0 && $settings->setCalls === 0
                && $assets->urlCalls === 0 && $assets->storeCalls === 0 && $assets->removeCalls === 0,
                "{$label} actor reached downstream work for [{$path}].");
        }
    }

    $auth->actor = new Batch4PermissionUser(['admin.access', 'settings.update']);
    $settings->resetCalls(); $assets->resetCalls();
    $allowed = $app->router()->dispatch(new Request('GET', '/admin/settings'));
    $assert($status($allowed) === 200, 'Actor with both permissions could not access Settings.');
    $assert($settings->definitionsCalls > 0 && $settings->getCalls > 0,
        'Authorized GET did not reach definition and effective-value lookup.');

    $before = $settings->get('site', 'tagline');
    foreach ([null, 'invalid'] as $token) {
        $settings->resetCalls(); $assets->resetCalls(); $csrf->resetCalls();
        $post = ['settings' => $valid()];
        if ($token !== null) { $post['_token'] = $token; }
        $response = $app->router()->dispatch(new Request('POST', '/admin/settings', [], $post));
        $html = $content($response);
        $assert($status($response) === 419 && str_contains($html, 'admin-shell'), 'Scalar CSRF failure was not a controlled Admin error.');
        $assert(!str_contains($html, 'RAW_CSRF_REJECT'), 'Scalar CSRF failure exposed raw detail.');
        $assert($csrf->validations === 1 && $csrf->receivedTokens === [$token],
            'Scalar CSRF fixture did not validate the actual missing/invalid request token.');
        $assert($settings->definitionsCalls === 0 && $settings->getCalls === 0
            && $settings->validateCalls === 0 && $settings->setCalls === 0,
            'Scalar CSRF failure reached discovery, validation, or persistence.');
    }
    $assert($settings->get('site', 'tagline') === $before, 'Scalar CSRF failure changed stored state.');

    foreach (array_slice($paths, 1) as [, $path]) {
        foreach ([null, 'invalid'] as $token) {
            $settings->resetCalls(); $assets->resetCalls(); $csrf->resetCalls();
            $assetState = $assets->state;
            $post = [];
            if ($token !== null) { $post['_token'] = $token; }
            $response = $app->router()->dispatch(new Request('POST', $path, [], $post));
            $html = $content($response);
            $assert($status($response) === 419 && str_contains($html, 'admin-shell'), "Asset CSRF failure [{$path}] was uncontrolled.");
            $assert(!str_contains($html, 'RAW_CSRF_REJECT'), "Asset CSRF failure [{$path}] exposed raw detail.");
            $assert($csrf->validations === 1 && $csrf->receivedTokens === [$token],
                "Asset CSRF fixture [{$path}] did not validate the actual missing/invalid request token.");
            $assert($assets->storeCalls === 0 && $assets->removeCalls === 0 && $assets->urlCalls === 0
                && $settings->definitionsCalls === 0, "Asset CSRF failure [{$path}] reached lookup or mutation.");
            $assert($assets->state === $assetState, "Asset CSRF failure [{$path}] changed asset state.");
        }
    }

    $csrf->resetCalls();
    foreach (['database.password', 'app.secret', 'env.APP_KEY', 'site.logo', 'site.favicon', 'unknown.setting'] as $identifier) {
        $settings->resetCalls(); $csrf->resetCalls();
        $submitted = $valid(); $submitted[$identifier] = 'must-not-persist';
        $response = $app->router()->dispatch(new Request('POST', '/admin/settings', [], [
            '_token' => 'batch4-csrf', 'settings' => $submitted,
        ]));
        $assert($csrf->validations === 1 && $csrf->receivedTokens === ['batch4-csrf'],
            "Valid CSRF token did not pass request-sensitive validation for [{$identifier}].");
        $assert($status($response) === 422, "Uneditable identifier [{$identifier}] was not rejected.");
        $assert(str_contains($content($response), 'unknown or uneditable fields'), "Uneditable identifier [{$identifier}] lacked controlled feedback.");
        $assert($settings->setCalls === 0, "Uneditable identifier [{$identifier}] allowed persistence.");
    }

    echo "M3.2 Batch 4 security hardening passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}
