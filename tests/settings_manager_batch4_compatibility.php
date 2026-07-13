<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm32b4compat' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

final class Batch4FailingPersistenceSettings extends SettingsService
{
    public int $writes = 0;
    public ?int $failAt = null;
    public array $writeIdentifiers = [];
    public function set(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $this->writes++;
        $this->writeIdentifiers[] = $namespace . '.' . $key;
        if ($this->failAt === $this->writes) { throw new PDOException('RAW PDO password=secret SQLSTATE[HY000]'); }
        parent::set($namespace, $key, $value, $type);
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++; if (!$condition) { throw new RuntimeException($message); }
};
$value = static fn (Response $response, string $property): mixed =>
    (new ReflectionProperty(Response::class, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');

$app = new Application($basePath);
$app->session()->start();
$connection = $app->database()->connection();
$service = new Batch4FailingPersistenceSettings(SettingsRegistry::core(), new SettingsRepository($app->database()));
$authorizedId = $connection->query(
    "SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN role_permissions rp ON rp.role_id=ur.role_id
     JOIN permissions p ON p.id=rp.permission_id WHERE u.status='active' AND p.slug IN ('admin.access','settings.update')
     GROUP BY u.id HAVING COUNT(DISTINCT p.slug)=2 ORDER BY u.id LIMIT 1"
)->fetchColumn();
if (!is_numeric($authorizedId)) { throw new RuntimeException('Batch 4 compatibility requires an active Settings administrator.'); }

$snapshot = [];
foreach (['site.name', 'site.tagline', 'localization.timezone', 'localization.locale', 'localization.date_format', 'localization.time_format'] as $identifier) {
    [$namespace, $key] = explode('.', $identifier, 2);
    $statement = $connection->prepare('SELECT * FROM settings WHERE namespace=:namespace AND setting_key=:setting_key LIMIT 1');
    $statement->execute(['namespace' => $namespace, 'setting_key' => $key]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);
    $snapshot[$identifier] = is_array($row) ? $row : null;
}

$alternate = new class($app, $service) {
    public function __construct(private Application $app, private SettingsService $settingsValue) {}
    public function config() { return $this->app->config(); } public function database() { return $this->app->database(); }
    public function settings() { return $this->settingsValue; } public function siteAssets() { return $this->app->siteAssets(); }
    public function session() { return $this->app->session(); } public function csrf() { return $this->app->csrf(); }
    public function auth() { return $this->app->auth(); } public function router() { return $this->app->router(); }
    public function adminNavigation() { return $this->app->adminNavigation(); } public function adminUrl() { return $this->app->adminUrl(); }
    public function adminPageRenderer() { return $this->app->adminPageRenderer(); } public function adminErrors() { return $this->app->adminErrors(); }
};
(static function ($app) use ($basePath): void { require $basePath . '/modules/settings-manager/routes.php'; })($alternate);

try {
    $sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
    $app->auth()->logout(); $app->session()->set($sessionKey, (int) $authorizedId);
    $before = [
        'site.name' => $service->get('site', 'name'),
        'site.tagline' => $service->get('site', 'tagline'),
        'localization.timezone' => $service->get('localization', 'timezone'),
        'localization.locale' => $service->get('localization', 'locale'),
        'localization.date_format' => $service->get('localization', 'date_format'),
        'localization.time_format' => $service->get('localization', 'time_format'),
    ];
    $firstWriteMarker = 'batch4-first-' . bin2hex(random_bytes(8));
    $secondWriteMarker = 'batch4-second-' . bin2hex(random_bytes(8));
    $assert($before['site.name'] !== $firstWriteMarker, 'Unique first-write marker collided with prior state.');
    $assert($before['site.tagline'] !== $secondWriteMarker, 'Unique second-write marker collided with prior state.');
    $service->writes = 0; $service->writeIdentifiers = []; $service->failAt = 2;
    $response = $app->router()->dispatch(new Request('POST', '/admin/settings', [], [
        '_token' => $app->session()->csrfToken(),
        'settings' => [
            'site.name' => $firstWriteMarker, 'site.tagline' => $secondWriteMarker,
            'localization.timezone' => 'UTC', 'localization.locale' => 'en_US',
            'localization.date_format' => 'Y-m-d', 'localization.time_format' => 'H:i',
        ],
    ]));
    $service->failAt = null;
    $html = $content($response);
    $assert($service->writes === 2, 'Storage fixture did not fail exactly at the second set invocation.');
    $assert($service->writeIdentifiers === ['site.name', 'site.tagline'],
        'Storage fixture did not reach the expected first and second writes in policy order.');
    $assert($status($response) === 503, 'Scalar persistence failure did not return 503.');
    $assert(str_contains($html, 'admin-shell'), 'Scalar persistence failure left the Admin shell.');
    foreach (['RAW PDO', 'password=secret', 'SQLSTATE', 'PDOException', 'Stack trace'] as $raw) {
        $assert(!str_contains($html, $raw), "Scalar persistence failure leaked [{$raw}].");
    }
    $after = [
        'site.name' => $service->get('site', 'name'), 'site.tagline' => $service->get('site', 'tagline'),
        'localization.timezone' => $service->get('localization', 'timezone'),
        'localization.locale' => $service->get('localization', 'locale'),
        'localization.date_format' => $service->get('localization', 'date_format'),
        'localization.time_format' => $service->get('localization', 'time_format'),
    ];
    $assert($after === $before, 'Scalar persistence failure changed prior values or left a partial write.');
    $assert(!in_array($firstWriteMarker, $after, true), 'Rolled-back first-write marker remained effective.');
    $assert(!in_array($secondWriteMarker, $after, true), 'Failed second-write marker remained effective.');
    $assert(!$connection->inTransaction(), 'Scalar persistence failure left a transaction active.');

    $view = (string) file_get_contents($basePath . '/modules/settings-manager/views/admin/settings.php');
    $routes = (string) file_get_contents($basePath . '/modules/settings-manager/routes.php');
    $schema = (string) file_get_contents($basePath . '/database/schema.sql');
    $assert(str_contains($view, 'site-assets/logo') === false && str_contains($view, 'name="settings[site.logo]"') === false,
        'Generic scalar view accepted a Logo descriptor.');
    $assert(str_contains($view, 'name="settings[site.favicon]"') === false,
        'Generic scalar view accepted a Favicon descriptor.');
    $assert(substr_count($routes, "'settings.update'") >= 2 && str_contains($routes, '$adminPermission'),
        'Established permission pair is no longer used by navigation/routes.');
    $assert(!str_contains($schema, 'settings.read'), 'A new Settings read permission entered the schema.');

    echo "M3.2 Batch 4 compatibility hardening passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $service->failAt = null;
    if ($connection->inTransaction()) { $connection->rollBack(); }
    $connection->beginTransaction();
    try {
        foreach ($snapshot as $identifier => $row) {
            [$namespace, $key] = explode('.', $identifier, 2);
            $connection->prepare('DELETE FROM settings WHERE namespace=:namespace AND setting_key=:setting_key')
                ->execute(['namespace' => $namespace, 'setting_key' => $key]);
            if (is_array($row)) {
                $columns = ['id','namespace','setting_key','setting_value','value_type','created_at','updated_at'];
                $connection->prepare('INSERT INTO settings (' . implode(',', $columns) . ') VALUES (:' . implode(',:', $columns) . ')')
                    ->execute(array_intersect_key($row, array_flip($columns)));
            }
        }
        $connection->commit();
    } catch (Throwable $failure) {
        if ($connection->inTransaction()) { $connection->rollBack(); }
        throw new RuntimeException('Batch 4 compatibility cleanup failed.', 0, $failure);
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}
