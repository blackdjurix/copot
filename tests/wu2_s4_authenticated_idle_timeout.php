<?php

declare(strict_types=1);

use Copot\Core\AuthenticatedIdleTimeoutResolver;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
use Copot\Core\Session;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\Auth;
use Copot\Core\UserProvider;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu2_s4_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . $databaseName . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
(new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install([
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
]);
$_ENV['DB_DATABASE'] = $databaseName;
putenv('DB_DATABASE=' . $databaseName);
putenv('AUTHENTICATED_IDLE_TIMEOUT_MINUTES=180');

$config = new Config($basePath . '/config');
$database = new Database($config);
$connection = $database->connection();
$settings = new SettingsService(SettingsRegistry::core(), new SettingsRepository($database));
$resolver = new AuthenticatedIdleTimeoutResolver($settings, $config);

try {
    $assert($resolver->resolve() === 120, 'Readable settings with no override did not use the registry default.');
    $settings->set('security', 'authenticated_idle_timeout_minutes', 45);
    $assert($resolver->resolve() === 45, 'Valid runtime setting did not win.');
    $connection->prepare('UPDATE settings SET setting_value = :value WHERE namespace = :namespace AND setting_key = :key')
        ->execute(['value' => 'invalid', 'namespace' => 'security', 'key' => 'authenticated_idle_timeout_minutes']);
    $assert($resolver->resolve() === 120, 'Invalid readable runtime setting did not use the registry default.');

    $clockNow = 1000000;
    $session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
    $session->start();
    $carrierLifetime = (int) ini_get('session.gc_maxlifetime');
    $cookieLifetime = (int) (session_get_cookie_params()['lifetime'] ?? 0);
    $assert($carrierLifetime >= 120 * 60, 'PHP session carrier lifetime was shorter than the canonical timeout.');
    $assert($cookieLifetime >= 120 * 60, 'Session cookie lifetime was shorter than the canonical timeout.');
    $assert($carrierLifetime > 60 && $cookieLifetime > 60, 'The carrier did not remain longer than the server-side test timeout.');
    $userEmail = 's4-user-' . bin2hex(random_bytes(4)) . '@example.test';
    $hash = (new PasswordHasher())->make('S4 password');
    $insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
    $insert->execute(['name' => 'S4 User', 'email' => $userEmail, 'password_hash' => $hash, 'status' => 'active']);
    $auth = new Auth($config, $session, new UserProvider($database), new PasswordHasher());
    $assert($auth->attempt($userEmail, 'S4 password'), 'Successful login did not establish an authenticated session.');
    $assert($auth->check(), 'Authenticated check immediately after login failed.');
    $clockNow += 44 * 60;
    $assert($auth->check(), 'Authenticated check before the effective timeout failed.');
    $clockNow += 44 * 60;
    $assert($auth->check(), 'Activity refresh did not prevent premature expiry.');
    $settings->set('security', 'authenticated_idle_timeout_minutes', 1);
    $assert($session->has($config->get('auth.session_key', '_copot_user_id')), 'Changing the setting proactively invalidated the session.');
    $clockNow += 61;
    $assert(!$auth->check(), 'Changed timeout was not applied on the next authenticated evaluation.');
    $assert(!$session->has($config->get('auth.session_key', '_copot_user_id')), 'Expired idle session state was not cleared.');

    if (session_status() === PHP_SESSION_ACTIVE) {
        $session->destroy();
    }

    $connection->exec('DROP TABLE settings');
    $assert($resolver->resolve() === 180, 'Unreadable settings did not use the valid bootstrap value.');
    putenv('AUTHENTICATED_IDLE_TIMEOUT_MINUTES=invalid');
    $fallbackConfig = new Config($basePath . '/config');
    $fallbackResolver = new AuthenticatedIdleTimeoutResolver($settings, $fallbackConfig);
    $assert($fallbackResolver->resolve() === 120, 'Invalid bootstrap value did not use the registry default.');
    echo "WU2-S4 authenticated idle-timeout tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
    putenv('AUTHENTICATED_IDLE_TIMEOUT_MINUTES');
}
