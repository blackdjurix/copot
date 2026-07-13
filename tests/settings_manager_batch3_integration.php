<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm32batch3' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

final class Batch3EffectiveLookupFailureSettingsService extends SettingsService
{
    public bool $failEffectiveLookup = false;

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        if ($this->failEffectiveLookup) {
            throw new PDOException('RAW_EFFECTIVE_LOOKUP_FAILURE');
        }

        return parent::get($namespace, $key, $default);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$responseValue = static fn (Response $response, string $property): mixed =>
    (new ReflectionProperty($response, $property))->getValue($response);
$statusOf = static fn (Response $response): int => (int) $responseValue($response, 'status');
$contentOf = static fn (Response $response): string => (string) $responseValue($response, 'content');
$locationOf = static fn (Response $response): ?string =>
    (($responseValue($response, 'headers')['Location'] ?? null) ?: null);

$app = new Application($basePath);
$app->session()->start();
$connection = $app->database()->connection();
$routeSettings = new Batch3EffectiveLookupFailureSettingsService(
    SettingsRegistry::core(),
    new SettingsRepository($app->database())
);
$authorizedId = $connection->query(
    "SELECT u.id
    FROM users u
    JOIN user_roles ur ON ur.user_id = u.id
    JOIN role_permissions rp ON rp.role_id = ur.role_id
    JOIN permissions p ON p.id = rp.permission_id
    WHERE u.status = 'active' AND p.slug IN ('admin.access', 'settings.update')
    GROUP BY u.id
    HAVING COUNT(DISTINCT p.slug) = 2
    ORDER BY u.id
    LIMIT 1"
)->fetchColumn();

if (!is_numeric($authorizedId)) {
    throw new RuntimeException('Configured-path fixture requires an active Settings administrator.');
}

$snapshot = [];
foreach ([
    'site.name', 'site.tagline', 'localization.timezone', 'localization.locale',
    'localization.date_format', 'localization.time_format',
] as $identifier) {
    [$namespace, $key] = explode('.', $identifier, 2);
    $statement = $connection->prepare(
        'SELECT * FROM settings WHERE namespace = :namespace AND setting_key = :setting_key LIMIT 1'
    );
    $statement->execute(['namespace' => $namespace, 'setting_key' => $key]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $snapshot[$identifier] = is_array($row) ? $row : null;
}

$fixtureDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m32-admin-path-' . bin2hex(random_bytes(6));
if (!mkdir($fixtureDirectory, 0777, true) && !is_dir($fixtureDirectory)) {
    throw new RuntimeException('Unable to create configured-path fixture directory.');
}
$fixtureConfig = $fixtureDirectory . DIRECTORY_SEPARATOR . 'admin.php';

try {
    if (file_put_contents($fixtureConfig, "<?php\nreturn ['path' => 'dapur', 'permission' => 'admin.access'];\n") === false) {
        throw new RuntimeException('Unable to create configured-path fixture config.');
    }

    $adminUrl = new AdminUrl(new Config($fixtureDirectory));
    $navigation = new AdminNavigation();
    $router = new Router();
    $view = new View($basePath . '/resources/views');
    $pages = new AdminPageRenderer($view, $adminUrl, $navigation, 'Copot', 'copot', 'en');
    $errors = new AdminErrorRenderer(
        $view, $pages, $adminUrl, $app->auth(), $app->csrf(), 'admin.access'
    );
    $alternateApp = new class($app, $routeSettings, $router, $navigation, $adminUrl, $pages, $errors) {
        public function __construct(
            private Application $app,
            private SettingsService $settingsValue,
            private Router $routerValue,
            private AdminNavigation $navigationValue,
            private AdminUrl $adminUrlValue,
            private AdminPageRenderer $pagesValue,
            private AdminErrorRenderer $errorsValue
        ) {}
        public function config() { return new Config(dirname(__DIR__) . '/config'); }
        public function database() { return $this->app->database(); }
        public function settings() { return $this->settingsValue; }
        public function siteAssets() { return $this->app->siteAssets(); }
        public function session() { return $this->app->session(); }
        public function csrf() { return $this->app->csrf(); }
        public function auth() { return $this->app->auth(); }
        public function router(): Router { return $this->routerValue; }
        public function adminNavigation(): AdminNavigation { return $this->navigationValue; }
        public function adminUrl(): AdminUrl { return $this->adminUrlValue; }
        public function adminPageRenderer(): AdminPageRenderer { return $this->pagesValue; }
        public function adminErrors(): AdminErrorRenderer { return $this->errorsValue; }
    };

    (static function ($app) use ($basePath): void {
        require $basePath . '/modules/settings-manager/routes.php';
    })($alternateApp);

    $sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
    $app->auth()->logout();
    $app->session()->set($sessionKey, (int) $authorizedId);

    $get = $router->dispatch(new Request('GET', '/dapur/settings'));
    $assert($statusOf($get) === 200, 'Configured Settings GET route did not execute.');
    $html = $contentOf($get);
    $assert(str_contains($html, 'admin-shell'), 'Configured Settings GET left the Admin shell.');
    $assert(str_contains($html, 'action="/dapur/settings"'), 'Scalar form ignored configured path.');

    $settingsState = static function () use ($app): array {
        return [
            'site.name' => $app->settings()->get('site', 'name'),
            'site.tagline' => $app->settings()->get('site', 'tagline'),
            'localization.timezone' => $app->settings()->get('localization', 'timezone'),
            'localization.locale' => $app->settings()->get('localization', 'locale'),
            'localization.date_format' => $app->settings()->get('localization', 'date_format'),
            'localization.time_format' => $app->settings()->get('localization', 'time_format'),
        ];
    };
    $validSettings = static fn (string $name, ?string $tagline = null): array => array_filter([
        'site.name' => $name,
        'site.tagline' => $tagline,
        'localization.timezone' => 'UTC',
        'localization.locale' => 'en_US',
        'localization.date_format' => 'Y-m-d',
        'localization.time_format' => 'H:i',
    ], static fn (mixed $value): bool => $value !== null);
    $csrfToken = $app->session()->csrfToken();

    $beforeNonArray = $settingsState();
    $nonArray = $router->dispatch(new Request('POST', '/dapur/settings', [], [
        '_token' => $csrfToken,
        'settings' => 'invalid-scalar',
    ]));
    $nonArrayHtml = $contentOf($nonArray);
    $assert($statusOf($nonArray) === 422, 'Non-array Settings payload did not return 422.');
    $assert(str_contains($nonArrayHtml, 'The submitted settings payload is invalid.'),
        'Non-array Settings payload lacked controlled form feedback.');
    $assert(!str_contains($nonArrayHtml, 'RuntimeException') && !str_contains($nonArrayHtml, 'Stack trace'),
        'Non-array Settings payload exposed internal exception detail.');
    $assert($settingsState() === $beforeNonArray, 'Non-array Settings payload mutated storage.');

    $storedMarker = 'stored-redisplay-' . bin2hex(random_bytes(4));
    $app->settings()->set('site', 'tagline', $storedMarker);
    $beforeValidation = $settingsState();
    $omitted = $validSettings('');
    $omittedResponse = $router->dispatch(new Request('POST', '/dapur/settings', [], [
        '_token' => $csrfToken,
        'settings' => $omitted,
    ]));
    $omittedHtml = $contentOf($omittedResponse);
    $assert($statusOf($omittedResponse) === 422, 'Omitted optional redisplay fixture did not return 422.');
    $assert(str_contains($omittedHtml, 'value="' . $storedMarker . '"'),
        'Omitted optional field did not redisplay its effective stored value.');
    $assert($settingsState() === $beforeValidation, 'Omitted optional validation failure mutated storage.');

    $explicitEmpty = $validSettings('', '');
    $emptyResponse = $router->dispatch(new Request('POST', '/dapur/settings', [], [
        '_token' => $csrfToken,
        'settings' => $explicitEmpty,
    ]));
    $emptyHtml = $contentOf($emptyResponse);
    $taglineId = 'setting-' . bin2hex('site.tagline');
    $assert($statusOf($emptyResponse) === 422, 'Explicit-empty redisplay fixture did not return 422.');
    $assert(preg_match('/id="' . preg_quote($taglineId, '/') . '"[^>]*value=""/s', $emptyHtml) === 1,
        'Explicit empty optional value did not take precedence during redisplay.');
    $assert(!str_contains($emptyHtml, 'value="' . $storedMarker . '"'),
        'Stored optional value overrode an explicit empty submitted value.');
    $assert($settingsState() === $beforeValidation, 'Explicit-empty validation failure mutated storage.');

    $submittedMarker = 'submitted-redisplay-' . bin2hex(random_bytes(4));
    $unknownAndInvalid = $validSettings('', $submittedMarker);
    $unknownAndInvalid['localization.locale'] = 'xx_XX';
    $unknownAndInvalid['unknown.setting'] = 'blocked';
    $aggregate = $router->dispatch(new Request('POST', '/dapur/settings', [], [
        '_token' => $csrfToken,
        'settings' => $unknownAndInvalid,
    ]));
    $aggregateHtml = $contentOf($aggregate);
    $assert($statusOf($aggregate) === 422, 'Aggregate route validation did not return 422.');
    $assert(str_contains($aggregateHtml, 'unknown or uneditable fields'),
        'Unknown nested identifier did not render a form-level error.');
    $assert(substr_count($aggregateHtml, 'The submitted value is invalid.') >= 2,
        'Multiple known field errors were not rendered together.');
    $assert(substr_count($aggregateHtml, 'aria-invalid="true"') >= 2,
        'Multiple invalid fields did not receive aria-invalid.');
    $assert(str_contains($aggregateHtml, 'admin-shell'), 'Aggregate validation left the Admin shell.');
    $taglineValuePattern = '/id="' . preg_quote($taglineId, '/') . '"[^>]*value="([^"]*)"/s';
    $assert(preg_match($taglineValuePattern, $aggregateHtml, $taglineValueMatch) === 1
        && ($taglineValueMatch[1] ?? null) === $submittedMarker,
        'Distinct known submitted value did not take precedence beside a form error.');
    $assert(($taglineValueMatch[1] ?? null) !== $storedMarker,
        'Effective stored value incorrectly won over the distinct submitted value.');
    $localeId = 'setting-' . bin2hex('localization.locale');
    $assert(preg_match(
        '/id="' . preg_quote($localeId, '/') . '".*?<option value="xx_XX" selected>xx_XX \(invalid\)<\/option>/s',
        $aggregateHtml
    ) === 1, 'Invalid submitted select value was not rendered as the selected invalid-current option.');
    $assert(str_contains($aggregateHtml, '<option value="en_US"'),
        'Approved select options disappeared beside the invalid-current option.');
    $localeOptions = null;
    foreach ((new SettingsFieldMapper(SettingsManagerPolicy::defaults()))->sections($routeSettings->definitions()) as $section) {
        foreach ($section->fields() as $field) {
            if ($field->identifier() === 'localization.locale') {
                $localeOptions = $field->options();
            }
        }
    }
    $assert(is_array($localeOptions) && !in_array('xx_XX', $localeOptions, true),
        'Invalid submitted value entered the approved field options contract.');
    $assert($settingsState() === $beforeValidation, 'Unknown/aggregate validation failure mutated storage.');
    $assert($app->settings()->get('site', 'tagline') === $storedMarker,
        'Distinct submitted marker replaced the stored marker during validation failure.');
    $assert($app->settings()->get('site', 'tagline') !== $submittedMarker,
        'Distinct submitted marker was persisted during validation failure.');

    $beforeLookupFailure = $settingsState();
    $routeSettings->failEffectiveLookup = true;
    try {
        $lookupFailure = $router->dispatch(new Request('POST', '/dapur/settings', [], [
            '_token' => $csrfToken,
            'settings' => $validSettings(''),
        ]));
    } finally {
        $routeSettings->failEffectiveLookup = false;
    }
    $lookupFailureHtml = $contentOf($lookupFailure);
    $assert($statusOf($lookupFailure) === 503,
        'Effective-value lookup failure after validation did not return controlled 503.');
    $assert(str_contains($lookupFailureHtml, 'admin-shell'),
        'Effective-value lookup failure did not use the Admin shell response contract.');
    $assert(!str_contains($lookupFailureHtml, 'RAW_EFFECTIVE_LOOKUP_FAILURE')
        && !str_contains($lookupFailureHtml, 'PDOException')
        && !str_contains($lookupFailureHtml, 'Stack trace'),
        'Effective-value lookup failure exposed raw internal detail.');
    $assert($settingsState() === $beforeLookupFailure,
        'Effective-value lookup failure after validation mutated storage.');

    foreach ([
        '/dapur/settings/site-assets/logo',
        '/dapur/settings/site-assets/favicon',
    ] as $action) {
        $assert(str_contains($html, 'action="' . $action . '"'), "Configured action [{$action}] was not rendered.");
    }
    foreach ([
        '/dapur/settings/site-assets/logo',
        '/dapur/settings/site-assets/logo/remove',
        '/dapur/settings/site-assets/favicon',
        '/dapur/settings/site-assets/favicon/remove',
    ] as $action) {
        $assert($statusOf($router->dispatch(new Request('POST', $action, [], [
            '_token' => 'invalid-configured-path-token',
        ]))) === 419, "Configured action [{$action}] did not execute its CSRF guard.");
        $defaultAction = '/admin' . substr($action, strlen('/dapur'));
        $assert($statusOf($router->dispatch(new Request('POST', $defaultAction, [], [
            '_token' => 'invalid-configured-path-token',
        ]))) === 404, "Default action [{$defaultAction}] remained registered.");
    }

    $savedTagline = 'Batch 3 configured ' . bin2hex(random_bytes(4));
    $post = $router->dispatch(new Request('POST', '/dapur/settings', [], [
        '_token' => $app->session()->csrfToken(),
        'settings' => [
            'site.name' => 'Copot configured path',
            'site.tagline' => $savedTagline,
            'localization.timezone' => 'UTC',
            'localization.locale' => 'en_US',
            'localization.date_format' => 'Y-m-d',
            'localization.time_format' => 'H:i',
        ],
    ]));
    $assert($statusOf($post) === 302, 'Configured Settings POST did not redirect.');
    $assert($locationOf($post) === '/dapur/settings?saved=1', 'Configured success redirect was incorrect.');
    $assert($app->settings()->get('site', 'tagline') === $savedTagline, 'Configured POST did not persist.');
    $assert($statusOf($router->dispatch(new Request('GET', '/admin/settings'))) === 404,
        'Default Settings GET remained registered.');
    $assert($statusOf($router->dispatch(new Request('POST', '/admin/settings', [], [
        '_token' => $app->session()->csrfToken(), 'settings' => [],
    ]))) === 404, 'Default Settings POST remained registered.');

    echo "M3.2 Batch 3 configured-path integration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $connection->beginTransaction();
    try {
        foreach ($snapshot as $identifier => $row) {
            [$namespace, $key] = explode('.', $identifier, 2);
            $connection->prepare('DELETE FROM settings WHERE namespace = :namespace AND setting_key = :setting_key')
                ->execute(['namespace' => $namespace, 'setting_key' => $key]);
            if (is_array($row)) {
                $columns = ['id', 'namespace', 'setting_key', 'setting_value', 'value_type', 'created_at', 'updated_at'];
                $connection->prepare(
                    'INSERT INTO settings (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
                )->execute(array_intersect_key($row, array_flip($columns)));
            }
        }
        $connection->commit();
    } catch (Throwable $failure) {
        if ($connection->inTransaction()) { $connection->rollBack(); }
        throw new RuntimeException('Batch 3 integration cleanup failed.', 0, $failure);
    }
    if (is_file($fixtureConfig)) { unlink($fixtureConfig); }
    if (is_dir($fixtureDirectory)) { rmdir($fixtureDirectory); }
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}
