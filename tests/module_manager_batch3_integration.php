<?php

declare(strict_types=1);

use Copot\Core\Admin\AdminErrorRenderer;
use Copot\Core\Admin\AdminPageRenderer;
use Copot\Core\Admin\AdminUrl;
use Copot\Core\AdminNavigation;
use Copot\Core\Application;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Env;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleLoader;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\User;
use Copot\Core\View;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm33batch3int' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

final class Batch3IntegrationUser extends User
{
    public function __construct() {}
    public function id(): int { return 933002; }
    public function name(): string { return 'Batch 3 Integration'; }
    public function email(): string { return 'batch3-integration@example.test'; }
    public function status(): string { return 'active'; }
    public function isActive(): bool { return true; }
    public function can(string $permission): bool { return in_array($permission, ['admin.access', 'modules.manage'], true); }
}

final class Batch3IntegrationAuth extends Auth
{
    public function __construct(private ?User $actor) {}
    public function check(): bool { return $this->actor instanceof User; }
    public function user(): ?User { return $this->actor; }
}

final class Batch3IntegrationDeniedUser extends User
{
    public function __construct() {}
    public function id(): int { return 933003; }
    public function name(): string { return 'Batch 3 Denied'; }
    public function email(): string { return 'batch3-denied@example.test'; }
    public function status(): string { return 'active'; }
    public function isActive(): bool { return true; }
    public function can(string $permission): bool { return false; }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$value = static fn (Response $response, string $property): mixed =>
    (new ReflectionProperty(Response::class, $property))->getValue($response);
$status = static fn (Response $response): int => (int) $value($response, 'status');
$content = static fn (Response $response): string => (string) $value($response, 'content');
$location = static fn (Response $response): ?string =>
    (($value($response, 'headers')['Location'] ?? null) ?: null);
$rowFor = static function (string $html, string $name): string {
    preg_match_all('/<tr>.*?<\/tr>/s', $html, $matches);

    foreach ($matches[0] as $row) {
        if (str_contains($row, 'href="/admin/modules/' . $name . '"')) {
            return $row;
        }
    }

    return '';
};

$app = new Application($basePath);
$app->session()->start();
$connection = $app->database()->connection();
$fakeAuth = new Batch3IntegrationAuth(new Batch3IntegrationUser());
$authProperty = new ReflectionProperty(Application::class, 'auth');
$authProperty->setValue($app, $fakeAuth);
$errorProperty = new ReflectionProperty(Application::class, 'adminErrors');
$errorProperty->setValue($app, new AdminErrorRenderer(
    new View($basePath . '/resources/views'),
    $app->adminPageRenderer(),
    $app->adminUrl(),
    $fakeAuth,
    $app->csrf(),
    'admin.access'
));

$suffix = bin2hex(random_bytes(5));
$fixtureName = 'batch3fixture' . $suffix;
$malformedName = 'batch3malformed' . $suffix;
$missingName = 'batch3missing' . $suffix;
$fixtureDirectory = $basePath . '/modules/' . $fixtureName;
$malformedDirectory = $basePath . '/modules/' . $malformedName;
$sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
$moduleManagerWasInstalled = $app->modules()->installed();
$moduleManagerInitiallyPresent = array_filter(
    $moduleManagerWasInstalled,
    static fn (array $module): bool => ($module['name'] ?? null) === 'module-manager'
) !== [];
$moduleManagerInitialStatus = null;
foreach ($moduleManagerWasInstalled as $module) {
    if (($module['name'] ?? null) === 'module-manager') {
        $moduleManagerInitialStatus = (string) ($module['status'] ?? '');
    }
}

try {
    if (!mkdir($fixtureDirectory, 0777, true) && !is_dir($fixtureDirectory)) {
        throw new RuntimeException('Unable to create lifecycle fixture directory.');
    }
    if (!mkdir($malformedDirectory, 0777, true) && !is_dir($malformedDirectory)) {
        throw new RuntimeException('Unable to create malformed fixture directory.');
    }
    file_put_contents($fixtureDirectory . '/module.json', json_encode([
        'name' => $fixtureName,
        'title' => 'Batch 3 <Fixture>',
        'description' => 'Integration fixture',
        'version' => '1.0.0',
        'author' => 'Batch 3',
        'requires' => ['modules' => []],
        'permissions' => [['slug' => 'batch3.fixture', 'name' => 'Permission <Fixture> &']],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($malformedDirectory . '/module.json', '{');

    if (!$moduleManagerInitiallyPresent) {
        $app->modules()->install('module-manager');
    }
    $managerRow = $app->modules()->installed();
    $managerEnabled = false;
    foreach ($managerRow as $row) {
        if (($row['name'] ?? null) === 'module-manager' && ($row['status'] ?? null) === 'enabled') {
            $managerEnabled = true;
        }
    }
    if (!$managerEnabled) {
        $app->modules()->enable('module-manager');
    }

    $connection->prepare(
        'INSERT INTO modules (name, title, version, path, status, installed_at, created_at, updated_at)
         VALUES (:name, :title, :version, :path, :status, NOW(), NOW(), NOW())'
    )->execute([
        'name' => $missingName,
        'title' => 'Missing <Installed>',
        'version' => '0.0.1',
        'path' => $basePath . '/modules/' . $missingName,
        'status' => 'disabled',
    ]);

    require $basePath . '/routes/admin.php';
    $app->moduleLoader()->loadRoutes($app);

    $get = $app->router()->dispatch(new Request('GET', '/admin/modules'));
    $html = $content($get);
    $assert($status($get) === 200, 'Authorized Module Manager inventory GET failed.');
    $assert(str_contains($html, 'admin-shell'), 'Inventory did not use the Admin shell.');
    $assert(str_contains($html, 'Batch 3 &lt;Fixture&gt;'), 'Manifest-origin title was not escaped.');
    $headerMatch = preg_match('/<thead>.*?<tr>(.*?)<\/tr>.*?<\/thead>/s', $html, $headerParts);
    preg_match_all('/<th scope="col">(.*?)<\/th>/', (string) ($headerParts[1] ?? ''), $headers);
    $assert($headerMatch === 1 && array_map('trim', $headers[1]) === ['Module', 'Version', 'Lifecycle', 'Discovery', 'Notes', 'Actions'],
        'Module inventory did not render the locked six-column compact table.');
    $assert(str_contains($html, 'href="/admin/modules/' . $fixtureName . '"'), 'Module inventory did not expose an Open detail link.');
    $assert(!str_contains($html, 'Stored permissions:'), 'Detailed permission evidence remained in the compact inventory.');
    $assert(!str_contains($html, 'Discovered permissions:'), 'Discovered permission evidence remained in the compact inventory.');
    $assert(str_contains($html, $malformedName), 'Malformed discovery item was not rendered.');
    $assert(!str_contains($html, 'Malformed discovery data'), 'Detailed discovery diagnostics remained in the compact inventory.');
    $assert(str_contains($html, 'Missing &lt;Installed&gt;'), 'Installed-but-missing item was not rendered safely.');
    $assert(str_contains($html, 'action="/admin/modules/install"'), 'Install action did not use the configured default path.');
    $assert(str_contains($html, 'Modules'), 'Module Manager navigation label was not rendered.');
    $selfRow = $rowFor($html, 'module-manager');
    $assert($selfRow !== '', 'Module Manager inventory row was not rendered.');
    $assert(substr_count($selfRow, 'Module Manager cannot disable or uninstall itself.') === 1,
        'Self-management denial message was not rendered for the visible protected action.');
    $assert(str_contains($selfRow, 'admin-module-notes') && !str_contains($selfRow, 'admin-module-action__reason'),
        'Module notes were not separated from the action controls.');
    $assert(!str_contains($selfRow, 'module_manager_self_management_denied'),
        'Raw self-management denial code leaked into inventory output.');
    $assert(substr_count($selfRow, ' disabled') >= 1,
        'Self-management disable control was not visibly disabled.');

    $fixtureRow = $rowFor($html, $fixtureName);
    $assert(str_contains($fixtureRow, '>Install<') && !str_contains($fixtureRow, '>Enable<')
        && !str_contains($fixtureRow, '>Disable<') && !str_contains($fixtureRow, '>Uninstall<'),
        'Not-installed action matrix was incorrect.');

    $detail = $app->router()->dispatch(new Request('GET', '/admin/modules/' . $fixtureName));
    $detailHtml = $content($detail);
    $assert($status($detail) === 200, 'Known Module Detail route did not render.');
    $assert(str_contains($detailHtml, 'admin-module-detail-layout')
        && str_contains($detailHtml, 'admin-module-detail-column--primary')
        && str_contains($detailHtml, 'admin-module-detail-column--secondary'),
        'Module Detail did not render the expected two-column layout hooks.');
    foreach (['Operational evidence', 'Stored permissions', 'Discovered permissions', 'Dependencies', 'Contribution files', 'Diagnostics', 'Denial reasons', 'Permission &lt;Fixture&gt; &amp;'] as $evidence) {
        $assert(str_contains($detailHtml, $evidence), "Module Detail omitted {$evidence} evidence.");
    }
    $assert($status($app->router()->dispatch(new Request('GET', '/admin/modules/Bad!'))) === 404,
        'Invalid Module Detail slug did not use shared 404.');
    $assert($status($app->router()->dispatch(new Request('GET', '/admin/modules/does-not-exist'))) === 404,
        'Missing Module Detail item did not use shared 404.');
    foreach (['install', 'enable', 'disable', 'uninstall'] as $reservedName) {
        $reservedResponse = $app->router()->dispatch(new Request('GET', '/admin/modules/' . $reservedName));
        $assert($status($reservedResponse) === 404,
            "Reserved Module Manager path [{$reservedName}] was not rejected as a detail route.");
    }
    $authProperty->setValue($app, new Batch3IntegrationAuth(new Batch3IntegrationDeniedUser()));
    $unauthorizedDetail = $app->router()->dispatch(new Request('GET', '/admin/modules/' . $fixtureName));
    $assert($status($unauthorizedDetail) === 403, 'Unauthorized Module Detail request was not denied.');
    $authProperty->setValue($app, $fakeAuth);
    $malformedDetail = $app->router()->dispatch(new Request('GET', '/admin/modules/' . $malformedName));
    $assert($status($malformedDetail) === 200 && str_contains($content($malformedDetail), 'Malformed discovery data'),
        'Module Detail did not expose malformed discovery evidence.');

    $csrf = $app->session()->csrfToken();
    $initialInventory = (new ModuleInventoryBuilder(
        new ModuleDiscovery($basePath . '/modules'),
        new ModuleRepository($app->database())
    ))->build();
    $initialFixture = null;
    foreach ($initialInventory as $inventoryItem) {
        if (($inventoryItem['name'] ?? null) === $fixtureName) {
            $initialFixture = $inventoryItem;
            break;
        }
    }
    $assert(($initialFixture['available_actions']['install']['enabled'] ?? false) === true,
        'Initial inventory did not mark the uninstalled fixture eligible for installation.');
    $app->modules()->install($fixtureName);
    $installedDisabled = $app->router()->dispatch(new Request('GET', '/admin/modules'));
    $installedDisabledRow = $rowFor($content($installedDisabled), $fixtureName);
    $assert(str_contains($installedDisabledRow, '>Enable<') && str_contains($installedDisabledRow, '>Uninstall<')
        && !str_contains($installedDisabledRow, '>Disable<'),
        'Installed-disabled action matrix was incorrect.');
    $staleInstall = $app->router()->dispatch(new Request('POST', '/admin/modules/install', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
    ]));
    $assert($status($staleInstall) === 422, 'Stale install was not denied with a controlled status.');
    $assert(str_contains($content($staleInstall), 'already installed'), 'Stale install denial did not expose the stable safe reason.');
    $staleRow = $connection->prepare('SELECT status, COUNT(*) FROM modules WHERE name = :name GROUP BY status');
    $staleRow->execute(['name' => $fixtureName]);
    $staleState = $staleRow->fetch(PDO::FETCH_NUM);
    $assert($staleState !== false && $staleState[0] === 'disabled' && (int) $staleState[1] === 1,
        'Stale install unexpectedly mutated persistent lifecycle state.');
    $app->modules()->uninstall($fixtureName);
    $install = $app->router()->dispatch(new Request('POST', '/admin/modules/install', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
        'return_context' => 'https://example.test/unsafe',
    ]));
    $assert($status($install) === 302, 'Install did not use PRG.');
    $assert($location($install) === '/admin/modules?notice=install_success', 'Install PRG location was incorrect.');
    $row = $connection->prepare('SELECT status FROM modules WHERE name = :name');
    $row->execute(['name' => $fixtureName]);
    $assert($row->fetchColumn() === 'disabled', 'Install did not create a disabled module row.');

    $noticeGet = $app->router()->dispatch(new Request('GET', '/admin/modules', ['notice' => 'install_success']));
    $assert(str_contains($content($noticeGet), 'Module installed successfully.'), 'Safe install notice was not rendered.');

    $enable = $app->router()->dispatch(new Request('POST', '/admin/modules/enable', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
        'return_context' => 'unexpected',
    ]));
    $assert($status($enable) === 302 && $location($enable) === '/admin/modules?notice=enable_success', 'Unknown return context did not fall back to the configured list.');

    $enableDetail = $app->router()->dispatch(new Request('POST', '/admin/modules/enable', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
        'return_context' => 'detail',
    ]));
    $assert($status($enableDetail) === 422, 'Repeated enable did not preserve the controlled lifecycle denial.');

    $installedEnabled = $app->router()->dispatch(new Request('GET', '/admin/modules'));
    $installedEnabledRow = $rowFor($content($installedEnabled), $fixtureName);
    $assert(str_contains($installedEnabledRow, '>Disable<') && !str_contains($installedEnabledRow, '>Enable<')
        && !str_contains($installedEnabledRow, '>Uninstall<'),
        'Installed-enabled action matrix was incorrect.');

    $disable = $app->router()->dispatch(new Request('POST', '/admin/modules/disable', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
    ]));
    $assert($status($disable) === 302 && $location($disable) === '/admin/modules?notice=disable_success', 'Disable did not use configured PRG.');

    $uninstall = $app->router()->dispatch(new Request('POST', '/admin/modules/uninstall', [], [
        '_token' => $csrf,
        'module' => $fixtureName,
    ]));
    $assert($status($uninstall) === 302 && $location($uninstall) === '/admin/modules?notice=uninstall_success', 'Uninstall did not use configured PRG.');
    $assert($connection->prepare('SELECT COUNT(*) FROM modules WHERE name = :name')->execute(['name' => $fixtureName]) !== false,
        'Lifecycle state query could not be executed after uninstall.');
    $check = $connection->prepare('SELECT COUNT(*) FROM modules WHERE name = :name');
    $check->execute(['name' => $fixtureName]);
    $assert((int) $check->fetchColumn() === 0, 'Uninstall did not remove the fixture row.');

    $configDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m33-admin-config-' . $suffix;
    mkdir($configDirectory, 0777, true);
    file_put_contents($configDirectory . '/admin.php', "<?php\nreturn ['path' => 'dapur', 'permission' => 'admin.access'];\n");
    $adminUrl = new AdminUrl(new Config($configDirectory));
    $navigation = new AdminNavigation();
    $view = new View($basePath . '/resources/views');
    $pages = new AdminPageRenderer($view, $adminUrl, $navigation, 'Copot', 'copot', 'en');
    $configuredApp = new Application($basePath);
    $configuredApp->session()->start();
    $authProperty->setValue($configuredApp, $fakeAuth);
    $configuredErrors = new AdminErrorRenderer($view, $pages, $adminUrl, $fakeAuth, $configuredApp->csrf(), 'admin.access');
    (new ReflectionProperty(Application::class, 'adminUrl'))->setValue($configuredApp, $adminUrl);
    (new ReflectionProperty(Application::class, 'adminNavigation'))->setValue($configuredApp, $navigation);
    (new ReflectionProperty(Application::class, 'adminPageRenderer'))->setValue($configuredApp, $pages);
    (new ReflectionProperty(Application::class, 'adminErrors'))->setValue($configuredApp, $configuredErrors);
    $enabledOnlyModuleRepository = new class extends ModuleRepository {
        public function __construct() {}
        public function enabled(): array { return [['name' => 'module-manager', 'status' => 'enabled']]; }
    };
    (new ModuleLoader(new ModuleDiscovery($basePath . '/modules'), $enabledOnlyModuleRepository))->loadRoutes($configuredApp);
    $configured = $configuredApp->router()->dispatch(new Request('GET', '/dapur/modules'));
    $configuredHtml = $content($configured);
    $assert($status($configured) === 200, 'Configured non-default Module Manager path did not execute.');
    $assert(str_contains($configuredHtml, 'action="/dapur/modules/install"'), 'Configured inventory action ignored the Admin path.');
    $configuredDetail = $configuredApp->router()->dispatch(new Request('GET', '/dapur/modules/' . $fixtureName));
    $assert($status($configuredDetail) === 200 && str_contains($content($configuredDetail), 'action="/dapur/modules/install"'),
        'Configured Module Detail route or action path failed.');
    $assert($status($configuredApp->router()->dispatch(new Request('GET', '/admin/modules'))) === 404, 'Default Module Manager path remained registered in configured fixture.');
    $configuredPost = $configuredApp->router()->dispatch(new Request('POST', '/dapur/modules/install', [], [
        '_token' => $configuredApp->session()->csrfToken(),
        'module' => $fixtureName,
        'return_context' => 'detail',
    ]));
    $assert($status($configuredPost) === 302, 'Configured-path mutation did not use PRG.');
    $assert($location($configuredPost) === '/dapur/modules/' . $fixtureName . '?notice=install_success',
        'Configured-path mutation redirected to an unexpected location.');
    $assert($location($configuredPost) !== '/admin/modules/' . $fixtureName . '?notice=install_success',
        'Configured-path mutation fell back to the default Admin path.');

    echo "M3.3 Batch 3 Module Manager integration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    try {
        $row = $connection->prepare('SELECT status FROM modules WHERE name = :name');
        $row->execute(['name' => $fixtureName]);
        if ($row->fetchColumn() === 'enabled') {
            $app->modules()->disable($fixtureName);
        }
        $app->modules()->uninstall($fixtureName);
    } catch (Throwable) {
    }
    $connection->prepare('DELETE FROM modules WHERE name = :name')->execute(['name' => $missingName]);
    foreach ([$fixtureDirectory, $malformedDirectory] as $directory) {
        $file = $directory . '/module.json';
        if (is_file($file)) { unlink($file); }
        if (is_dir($directory)) { rmdir($directory); }
    }
    if (isset($configDirectory) && is_file($configDirectory . '/admin.php')) { unlink($configDirectory . '/admin.php'); }
    if (isset($configDirectory) && is_dir($configDirectory)) { rmdir($configDirectory); }
    if (!$moduleManagerInitiallyPresent) {
        try {
            $row = $app->modules()->installed();
            foreach ($row as $module) {
                if (($module['name'] ?? null) === 'module-manager') {
                    if (($module['status'] ?? null) === 'enabled') { $app->modules()->disable('module-manager'); }
                    $app->modules()->uninstall('module-manager');
                }
            }
        } catch (Throwable) {
        }
    } elseif ($moduleManagerInitialStatus === 'disabled') {
        try { $app->modules()->disable('module-manager'); } catch (Throwable) { }
    }
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
}
