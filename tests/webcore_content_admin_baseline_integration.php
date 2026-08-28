<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\ContentRepository;
use Copot\Core\ContentService;
use Copot\Core\Env;
use Copot\Core\Application;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\Request;

$basePath = dirname(__DIR__);
chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copot-core-content-' . bin2hex(random_bytes(5)));
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) throw new RuntimeException($message);
};
$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', '3306');
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_core_admin_' . bin2hex(random_bytes(6));
$quoted = '`' . str_replace('`', '``', $databaseName) . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

try {
    (new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host'=>$host,'port'=>$port,'database'=>$databaseName,'username'=>$username,'password'=>$password]);
    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $db = new Copot\Core\Database(new Config($basePath . '/config'));
    $db->connection()->prepare('INSERT INTO users (name,email,password_hash,status,created_at,updated_at) VALUES (:name,:email,:hash,\'active\',NOW(),NOW())')->execute(['name'=>'Core Admin','email'=>'core-admin@example.test','hash'=>password_hash('core-password', PASSWORD_DEFAULT)]);
    $userId = (int) $db->connection()->lastInsertId();
    $db->connection()->prepare('INSERT INTO user_roles (user_id,role_id) SELECT :user_id,id FROM roles WHERE slug=\'admin\'')->execute(['user_id'=>$userId]);
    $content = new ContentService($db, new ContentRepository($db));
    $contentId = $content->create(['type'=>'page','title'=>'Core Admin Page','slug'=>'core-admin-page','body'=>'Plain body','status'=>'draft','author_id'=>$userId]);
    $app = new Application($basePath);
    $app->session()->start();
    require $basePath . '/routes/web.php';
    require $basePath . '/routes/auth.php';
    require $basePath . '/routes/admin.php';
    require $basePath . '/routes/content_admin.php';
    require $basePath . '/routes/system_manager.php';
    require $basePath . '/routes/admin_fallback.php';
    $assert($app->auth()->attempt('core-admin@example.test', 'core-password'), 'Core Admin test account could not authenticate.');
    $get = static fn (string $path) => $app->run(new Request('GET', $path));
    $assert($get('/admin/content')->statusCode() === 200, 'Core Content list was not available with Content Manager disabled.');
    $assert(str_contains($get('/admin/content')->body(), 'Core Admin Page'), 'Core Content list did not render Core data.');
    $assert($get('/admin/content/create')->statusCode() === 200, 'Core Content create form did not render.');
    $assert($get('/admin/content/' . $contentId . '/edit')->statusCode() === 200, 'Core Content edit form did not render.');
    $token = $app->session()->csrfToken();
    $publish = $app->run(new Request('POST', '/admin/content/' . $contentId . '/publish', [], ['_token'=>$token]));
    $assert($publish->statusCode() === 302 && (new ContentRepository($db))->findById($contentId)?->isPublished(), 'Core Content publish action failed: ' . $publish->statusCode() . ' ' . $publish->body());
    $archive = $app->run(new Request('POST', '/admin/content/' . $contentId . '/archive', [], ['_token'=>$token]));
    $assert($archive->statusCode() === 302 && (new ContentRepository($db))->findById($contentId)?->isArchived(), 'Core Content archive action failed.');
    $invalidCsrf = $app->run(new Request('POST', '/admin/content/' . $contentId . '/archive', [], ['_token'=>'invalid']));
    $assert($invalidCsrf->statusCode() === 419, 'Core Content archive did not reject invalid CSRF.');
    echo "Webcore Content Admin baseline integration tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    try { $server->exec('DROP DATABASE ' . $quoted); } catch (Throwable) { }
}
