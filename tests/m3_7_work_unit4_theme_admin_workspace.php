<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ThemeDefinition;
use Copot\Core\ThemeDiscovery;
use Copot\Core\ThemeLifecycle;
use Copot\Core\ThemeRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm37wu4admin' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$statusOf = static fn (Response $response): int => (int) (new ReflectionProperty(Response::class, 'status'))->getValue($response);
$contentOf = static fn (Response $response): string => (string) (new ReflectionProperty(Response::class, 'content'))->getValue($response);
$locationOf = static fn (Response $response): string => (string) ((new ReflectionProperty(Response::class, 'headers'))->getValue($response)['Location'] ?? '');
$headerOf = static fn (Response $response, string $name): ?string => (new ReflectionProperty(Response::class, 'headers'))->getValue($response)[$name] ?? null;
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) ? $removeDirectory($child) : unlink($child);
    }

    rmdir($path);
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_m37_wu4_admin_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = compact('host', 'port', 'username', 'password') + ['database' => $databaseName];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$fixtureRoot = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.m37-wu4-admin-' . bin2hex(random_bytes(5));

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->modules()->install('theme-manager');
    $app->modules()->enable('theme-manager');
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';
    $db = $app->database()->connection();
    $permissionIds = [];
    foreach (['admin.access', 'themes.manage'] as $permission) {
        $statement = $db->prepare('SELECT id FROM permissions WHERE slug = :slug LIMIT 1');
        $statement->execute(['slug' => $permission]);
        $permissionIds[$permission] = (int) $statement->fetchColumn();
    }
    $createActor = static function (string $label, array $permissions) use ($db, $permissionIds): int {
        $suffix = bin2hex(random_bytes(4));
        $db->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,"test","active",NOW(),NOW())')->execute(['name' => $label, 'email' => $label . '-' . $suffix . '@example.test']);
        $userId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO roles (name,slug,created_at,updated_at) VALUES (:name,:slug,NOW(),NOW())')->execute(['name' => $label, 'slug' => $label . '-' . $suffix]);
        $roleId = (int) $db->lastInsertId();
        $db->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (:user_id,:role_id)')->execute(['user_id' => $userId, 'role_id' => $roleId]);
        foreach ($permissions as $permission) {
            $db->prepare('INSERT INTO role_permissions (role_id,permission_id) VALUES (:role_id,:permission_id)')->execute(['role_id' => $roleId, 'permission_id' => $permissionIds[$permission]]);
        }
        return $userId;
    };
    $full = $createActor('wu4-full', ['admin.access', 'themes.manage']);
    $adminOnly = $createActor('wu4-admin-only', ['admin.access']);
    $themesOnly = $createActor('wu4-themes-only', ['themes.manage']);
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $url = $app->adminUrl()->childUrl('themes');
    $token = static fn (): string => $app->session()->csrfToken();

    $makeTheme = static function (string $id, string $name, bool $withScreenshot = false) use ($fixtureRoot): void {
        $themePath = $fixtureRoot . DIRECTORY_SEPARATOR . $id;
        mkdir($themePath . DIRECTORY_SEPARATOR . 'layouts', 0777, true);
        file_put_contents($themePath . DIRECTORY_SEPARATOR . 'layouts' . DIRECTORY_SEPARATOR . 'app.php', '<?php echo "fixture";');
        $manifest = [
            'id' => $id,
            'name' => $name,
            'version' => '1.0.0',
            'type' => 'frontend',
            'entry' => ['layout' => 'layouts/app.php'],
            'description' => str_repeat('Description ', 30),
            'author' => 'Validation',
        ];
        if ($withScreenshot) {
            $manifest['screenshot'] = 'screenshot.png';
            file_put_contents($themePath . DIRECTORY_SEPARATOR . 'screenshot.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true));
        }
        file_put_contents($themePath . DIRECTORY_SEPARATOR . 'theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    };
    mkdir($fixtureRoot, 0777, true);
    $makeTheme('alpha', 'Alpha <Theme>', true);
    $makeTheme('beta', 'Beta Theme');
    $makeTheme('gamma', str_repeat('<script>Hostile</script>', 20));
    mkdir($fixtureRoot . DIRECTORY_SEPARATOR . 'invalid', 0777, true);
    file_put_contents($fixtureRoot . DIRECTORY_SEPARATOR . 'invalid' . DIRECTORY_SEPARATOR . 'theme.json', '{');
    $lifecycleProperty = new ReflectionProperty(Application::class, 'themeLifecycle');
    $lifecycleProperty->setAccessible(true);
    $lifecycleProperty->setValue($app, new ThemeLifecycle(new ThemeDiscovery($fixtureRoot), $app->themes(), new ThemeRepository($app->database()), $app->database()));
    $app->themeLifecycle()->activate('alpha');
    $db->prepare('INSERT INTO themes (theme_id,name,version,type,path,is_active,metadata,created_at,updated_at) VALUES (?,?,?,?,?,0,?,NOW(),NOW())')->execute(['stale', 'Stale', '0.1.0', 'frontend', 'themes/stale', '{}']);

    $guest = $app->run(new Request('GET', $url));
    $assert($statusOf($guest) === 302 && $locationOf($guest) === $app->adminUrl()->baseUrl(), 'Unauthenticated Theme request did not redirect to configured Admin login.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], []))) === 302, 'Unauthenticated POST did not redirect before CSRF.');
    $switch($adminOnly);
    $assert($statusOf($app->run(new Request('GET', $url))) === 403, 'Admin-only user reached Theme workspace.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], []))) === 403, 'Admin-only POST did not fail before CSRF.');
    $switch($themesOnly);
    $assert($statusOf($app->run(new Request('GET', $url))) === 403, 'Theme-only user reached Theme workspace.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], []))) === 403, 'Theme-only POST did not fail before CSRF.');
    $switch($full);
    $navigation = $app->adminNavigation()->itemsFor($app->auth()->user());
    $assert(array_column($navigation, 'label') === ['Dashboard', 'Themes'], 'Theme navigation was not permission filtered or deterministically ordered.');

    $workspace = $app->run(new Request('GET', $url));
    $html = $contentOf($workspace);
    $assert($statusOf($workspace) === 200 && str_contains($html, 'Theme inventory') && str_contains($html, 'Alpha'), 'Theme workspace did not render the fixture inventory.');
    $assert(str_contains($html, 'No screenshot') && str_contains($html, 'Activate Beta'), 'Theme screenshot placeholder or inactive activation action was not rendered.');
    $assert(str_contains($html, 'Active') && str_contains($html, 'Discovered, not registered') && str_contains($html, 'Stale registration') && str_contains($html, 'Invalid'), 'Theme lifecycle states were not presented distinctly.');
    $assert(!str_contains($html, '<script>') && strlen($html) < 50000, 'Hostile metadata was not escaped and bounded.');
    $assert(str_contains($html, 'aria-current="page"'), 'Themes navigation item was not marked active.');
    $assert(!str_contains($html, $basePath) && !str_contains($html, 'CREATE TABLE') && !str_contains($html, 'Exception'), 'Theme workspace leaked internal diagnostics.');

    $before = (int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $getMutationProbe = $app->run(new Request('GET', $url));
    $assert($statusOf($getMutationProbe) === 200 && (int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn() === $before, 'GET Theme inventory mutated registry state.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], []))) === 419, 'Missing CSRF was not rejected.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], ['_token' => 'invalid', 'theme_id' => 'alpha']))) === 419, 'Invalid CSRF was not rejected.');
    $beforeMismatchCount = (int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn();
    $beforeMismatchActive = (string) $db->query("SELECT theme_id FROM themes WHERE is_active = 1 AND type = 'frontend' LIMIT 1")->fetchColumn();
    foreach ([
        ['path' => 'themes/alpha/activate', 'body' => ['_token' => $token(), 'theme_id' => ['alpha']]],
        ['path' => 'themes/alpha/activate', 'body' => ['_token' => $token(), 'theme_id' => 'bad!']],
        ['path' => 'themes/alpha/activate', 'body' => ['_token' => $token()]],
        ['path' => 'themes/not!safe/activate', 'body' => ['_token' => $token(), 'theme_id' => 'not!safe']],
        ['path' => 'themes/alpha/activate', 'body' => ['_token' => $token(), 'theme_id' => 'beta']],
    ] as $rejection) {
        $path = str_contains($rejection['path'], '!')
            ? $app->adminUrl()->baseUrl() . '/' . $rejection['path']
            : $app->adminUrl()->childUrl($rejection['path']);
        $assert($statusOf($app->run(new Request('POST', $path, [], $rejection['body']))) === 422, 'Malformed or mismatched activation input was not rejected.');
    }
    $assert((int) $db->query('SELECT COUNT(*) FROM themes')->fetchColumn() === $beforeMismatchCount && (string) $db->query("SELECT theme_id FROM themes WHERE is_active = 1 AND type = 'frontend' LIMIT 1")->fetchColumn() === $beforeMismatchActive, 'Rejected activation input mutated registry or active state.');
    $activated = $app->run(new Request('POST', $app->adminUrl()->childUrl('themes/alpha/activate'), [], ['_token' => $token(), 'theme_id' => 'alpha']));
    $assert($statusOf($activated) === 302 && str_contains($locationOf($activated), 'notice=activated'), 'Valid Theme activation did not use PRG.');
    $assert((string) $db->query("SELECT theme_id FROM themes WHERE is_active = 1 AND type = 'frontend' LIMIT 1")->fetchColumn() === 'alpha', 'Theme activation did not use the validated route target.');
    $failure = $app->run(new Request('POST', $app->adminUrl()->childUrl('themes/missing/activate'), [], ['_token' => $token(), 'theme_id' => 'missing']));
    $failureHtml = $contentOf($failure);
    $assert($statusOf($failure) === 422 && str_contains($failureHtml, 'Activation could not be completed') && !str_contains($failureHtml, $fixtureRoot) && !str_contains($failureHtml, 'Exception'), 'Lifecycle activation failure was not sanitized.');
    $screenshot = $app->run(new Request('GET', $app->adminUrl()->childUrl('themes/alpha/screenshot')));
    $assert($statusOf($screenshot) === 200 && $headerOf($screenshot, 'Content-Type') === 'image/png', 'Allowed screenshot was not served with its allowlisted MIME type.');
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->childUrl('themes/alpha/screenshot')))) === 200, 'Screenshot success was not repeatable.');
    $switch($adminOnly);
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->childUrl('themes/alpha/screenshot')))) === 403, 'Unauthorized screenshot request was not denied.');
    $switch($full);
    $assert($statusOf($app->run(new Request('GET', $app->adminUrl()->baseUrl() . '/themes/not!safe/screenshot'))) === 404, 'Malformed screenshot request was not contained.');
    $lifecycleProperty->setValue($app, new ThemeLifecycle(new ThemeDiscovery($fixtureRoot . '-missing'), $app->themes(), new ThemeRepository($app->database()), $app->database()));
    $unavailableHtml = $contentOf($app->run(new Request('GET', $url)));
    $assert(str_contains($unavailableHtml, 'Unavailable') && !str_contains($unavailableHtml, $fixtureRoot), 'Root-unavailable inventory was not safely presented.');
    $removeDirectory($fixtureRoot . '-empty');
    mkdir($fixtureRoot . '-empty', 0777, true);
    $db->exec('DELETE FROM themes');
    $lifecycleProperty->setValue($app, new ThemeLifecycle(new ThemeDiscovery($fixtureRoot . '-empty'), $app->themes(), new ThemeRepository($app->database()), $app->database()));
    $emptyHtml = $contentOf($app->run(new Request('GET', $url)));
    $assert(str_contains($emptyHtml, 'No Themes discovered'), 'Empty inventory state was not intentional.');

    echo "M3.7 Work Unit 4 Theme Admin workspace passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $removeDirectory($fixtureRoot);
    $removeDirectory($fixtureRoot . '-empty');
    $removeDirectory($fixtureRoot . '-missing');
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
