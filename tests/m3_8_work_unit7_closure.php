<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotm38wu7' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$statusOf = static function (Response $response): int {
    $property = new ReflectionProperty($response, 'status');
    $property->setAccessible(true);
    return (int) $property->getValue($response);
};
$runSql = static function (PDO $connection, string $sql): void {
    foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\R|$)/', $sql) ?: [])) as $statement) $connection->exec($statement);
};
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
$upgradeDatabase = 'copot_m38_wu7_upgrade_' . bin2hex(random_bytes(5));
$authorizationDatabase = 'copot_m38_wu7_auth_' . bin2hex(random_bytes(5));

try {
    $server->exec("CREATE DATABASE `{$upgradeDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $upgrade = new PDO("mysql:host={$host};port={$port};dbname={$upgradeDatabase};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $runSql($upgrade, (string) file_get_contents($basePath . '/tests/fixtures/m3_8_pre_media_install.sql'));
    $assert((int) $upgrade->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('media', 'media_variants', 'media_usages')")->fetchColumn() === 0, 'Upgrade fixture is not pre-M3.8 Media state.');
    $assert((int) $upgrade->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'content' AND column_name = 'featured_media_id'")->fetchColumn() === 0, 'Upgrade fixture already contains Content Media integration.');

    $upgradeSql = (string) file_get_contents($basePath . '/database/upgrades/m3_8_media_library.sql');
    $runSql($upgrade, $upgradeSql);
    foreach (['media', 'media_variants', 'media_usages'] as $table) {
        $statement = $upgrade->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $statement->execute([$table]);
        $assert((int) $statement->fetchColumn() === 1, "Existing-install upgrade did not create {$table}.");
    }
    $assert((int) $upgrade->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'content' AND column_name = 'featured_media_id'")->fetchColumn() === 1, 'Existing-install upgrade did not add featured_media_id.');
    $assert((int) $upgrade->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'content' AND index_name = 'idx_content_featured_media'")->fetchColumn() === 1, 'Existing-install upgrade did not add the Content Media index.');
    $mediaPermissions = ['media.view', 'media.upload', 'media.use', 'media.edit', 'media.delete'];
    $placeholders = implode(',', array_fill(0, count($mediaPermissions), '?'));
    $permissionCount = $upgrade->prepare("SELECT COUNT(*) FROM permissions WHERE slug IN ({$placeholders})");
    $permissionCount->execute($mediaPermissions);
    $assert((int) $permissionCount->fetchColumn() === 5, 'Existing-install upgrade did not provision all Media permissions.');
    $grantCount = $upgrade->prepare("SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id INNER JOIN permissions p ON p.id = rp.permission_id WHERE r.slug = 'admin' AND p.slug IN ({$placeholders})");
    $grantCount->execute($mediaPermissions);
    $assert((int) $grantCount->fetchColumn() === 5, 'Existing-install upgrade did not seed all administrator Media grants.');
    $runSql($upgrade, $upgradeSql);
    $permissionCount->execute($mediaPermissions);
    $assert((int) $permissionCount->fetchColumn() === 5, 'Existing-install upgrade duplicated Media permissions on reapplication.');
    $grantCount->execute($mediaPermissions);
    $assert((int) $grantCount->fetchColumn() === 5, 'Existing-install upgrade duplicated Media administrator grants on reapplication.');

    $config = new Config($basePath . '/config');
    $reflection = new ReflectionClass($config);
    $property = $reflection->getProperty('items');
    $property->setAccessible(true);
    $items = $property->getValue($config);
    foreach (['host' => $host, 'port' => $port, 'database' => $upgradeDatabase, 'username' => $username, 'password' => $password] as $key => $value) $items['database']['connections']['mysql'][$key] = $value;
    $property->setValue($config, $items);
    $manager = new ModuleManager(new ModuleDiscovery($basePath . '/modules'), new ModuleRepository(new Database($config)));
    $manager->install('media');
    $manager->enable('media');
    $assert((string) $upgrade->query("SELECT status FROM modules WHERE name = 'media'")->fetchColumn() === 'enabled', 'Normal ModuleManager continuation did not enable Media.');
    $manifestCount = $upgrade->query("SELECT COUNT(*) FROM module_permissions WHERE module_name = 'media'")->fetchColumn();
    $assert((int) $manifestCount === 5, 'Media manifest permissions were not registered through ModuleManager.');
    $manifestSlugs = $upgrade->query("SELECT permission_slug FROM module_permissions WHERE module_name = 'media' ORDER BY permission_slug")->fetchAll(PDO::FETCH_COLUMN);
    $expectedSlugs = $mediaPermissions;
    sort($expectedSlugs);
    $assert($manifestSlugs === $expectedSlugs, 'Media manifest permissions differ from the five provisioned actions.');

    $server->exec("CREATE DATABASE `{$authorizationDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host' => $host, 'port' => $port, 'database' => $authorizationDatabase, 'username' => $username, 'password' => $password]);
    $_ENV['DB_DATABASE'] = $authorizationDatabase;
    putenv('DB_DATABASE=' . $authorizationDatabase);
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    $app->modules()->install('media');
    $app->modules()->enable('media');
    $app->moduleLoader()->loadRoutes($app);
    require $basePath . '/routes/admin_fallback.php';
    $connection = $app->database()->connection();
    $ids = [];
    foreach (array_merge(['admin.access'], $mediaPermissions) as $slug) $ids[$slug] = (int) $connection->query("SELECT id FROM permissions WHERE slug = " . $connection->quote($slug))->fetchColumn();
    $actor = static function (string $label, array $permissions) use ($connection, $ids): int {
        $suffix = bin2hex(random_bytes(4));
        $connection->prepare("INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (?, ?, 'test', 'active', NOW(), NOW())")->execute([$label, $label . '-' . $suffix . '@example.test']);
        $userId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO roles (name, slug, created_at, updated_at) VALUES (?, ?, NOW(), NOW())')->execute([$label, $label . '-' . $suffix]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)')->execute([$userId, $roleId]);
        foreach ($permissions as $permission) $connection->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)')->execute([$roleId, $ids[$permission]]);
        return $userId;
    };
    $switch = static function (int $userId) use ($app): void {
        $app->auth()->logout();
        $app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), $userId);
    };
    $onlyAdmin = $actor('wu7-admin', ['admin.access']);
    $onlyUse = $actor('wu7-use', ['admin.access', 'media.use']);
    $full = $actor('wu7-full', array_merge(['admin.access'], $mediaPermissions));
    $mediaPath = $app->adminUrl()->childUrl('media');
    $uploadPath = $app->adminUrl()->childUrl('media/upload');
    $pickerPath = $app->adminUrl()->childUrl('media/context-picker');
    $switch($onlyAdmin);
    $assert($statusOf($app->run(new Request('GET', $mediaPath))) === 403, 'media.view is not required for the Media workspace.');
    $assert($statusOf($app->run(new Request('POST', $uploadPath))) === 403, 'media.upload authorization was not enforced before CSRF.');
    $assert($statusOf($app->run(new Request('GET', $pickerPath, ['consumer' => 'content']))) === 403, 'media.use is not required for picker access.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/1/title')))) === 403, 'media.edit authorization was not enforced before CSRF.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/1/delete')))) === 403, 'media.delete authorization was not enforced before CSRF.');
    $switch($onlyUse);
    $assert($statusOf($app->run(new Request('GET', $pickerPath, ['consumer' => 'content']))) === 200, 'media.use does not allow picker access.');
    $assert($statusOf($app->run(new Request('POST', $pickerPath . '/upload'))) === 403, 'Picker inline upload did not require media.upload in addition to media.use.');
    $switch($full);
    $assert($statusOf($app->run(new Request('GET', $mediaPath))) === 200, 'media.view does not allow the Media workspace.');
    $assert($statusOf($app->run(new Request('GET', $uploadPath))) === 200, 'media.upload does not allow the upload workspace.');
    $assert($statusOf($app->run(new Request('POST', $pickerPath . '/upload'))) === 419, 'Authorized picker inline upload did not reach CSRF validation.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/1/title')))) === 419, 'Authorized title update did not reach CSRF validation.');
    $assert($statusOf($app->run(new Request('POST', $app->adminUrl()->childUrl('media/1/delete')))) === 419, 'Authorized deletion did not reach CSRF validation.');

    echo "M3.8 Work Unit 7 closure passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$upgradeDatabase}`");
    $server->exec("DROP DATABASE IF EXISTS `{$authorizationDatabase}`");
}
