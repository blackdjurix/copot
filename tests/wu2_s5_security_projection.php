<?php

declare(strict_types=1);

use Copot\Core\AuthenticatedIdleTimeoutResolver;
use Copot\Core\AuthenticatedSessionRepository;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
use Copot\Core\PasswordPolicy;
use Copot\Core\ReauthenticationRequiredException;
use Copot\Core\ReauthenticationService;
use Copot\Core\SecurityEventRepository;
use Copot\Core\SecurityEventService;
use Copot\Core\SecurityPolicyService;
use Copot\Core\Session;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\UserProvider;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$read = static fn (string $path): string => (string) file_get_contents($basePath . '/' . $path);
$routes = $read('routes/site_settings.php');
$view = $read('resources/views/admin/site-settings.php');
$policySource = $read('app/Core/SecurityPolicyService.php');

$assert(str_contains($view, "'security' => 'Security'") && strpos($view, "'system' => 'System'") < strpos($view, "'security' => 'Security'") && strpos($view, "'security' => 'Security'") < strpos($view, "'modules' => 'Modules'") && strpos($view, "'modules' => 'Modules'") < strpos($view, "'redirects' => 'Redirects'"), 'Security tab placement is not System, Security, Modules, Redirects.');
$assert(str_contains($view, '$canManageSecurity') && str_contains($view, "'security' => \$canManageSecurity"), 'Security tab visibility is not capability-driven.');
$assert(str_contains($routes, "can('security.manage')") && str_contains($routes, '!$user->can(\'security.manage\')') && str_contains($routes, '$user->can($adminPermission)'), 'Security read/mutation authority does not require admin-shell plus security.manage.');
$assert(str_contains($routes, '$app->router()->post($securityPath') && str_contains($routes, '$securityPolicy->update'), 'Security policy mutation route is not composed through the canonical policy service.');
$assert(str_contains($routes, '$app->selfSessions()->listOwn') && str_contains($view, "'/account/sessions/revoke-others'") && str_contains($view, "'/account/password'"), 'Security projection does not reuse canonical session and password authorities.');
$assert(str_contains($routes, '$requireSettingsUser') && str_contains($routes, '$app->settings()->set(\'site\''), 'Generic Site Settings mutation boundary was not preserved.');
$assert(!str_contains($view, "'email' => 'Email'"), 'Email was fabricated before the Email capability exists.');
$assert(str_contains($policySource, 'requireRecentProof') && str_contains($policySource, 'beginTransaction') && str_contains($policySource, 'recordSecurityPolicyChange'), 'Security policy service does not enforce proof, atomic persistence, and event evidence.');

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu2_s5_' . bin2hex(random_bytes(6));
$quotedDatabase = '`' . $databaseName . '`';
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
(new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install([
    'host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password,
]);
$_ENV['DB_DATABASE'] = $databaseName;
putenv('DB_DATABASE=' . $databaseName);

$config = new Config($basePath . '/config');
$database = new Database($config);
$settings = new SettingsService(SettingsRegistry::core(), new SettingsRepository($database));
$events = new SecurityEventService(new SecurityEventRepository($database));
$sessions = new AuthenticatedSessionRepository($database);
$clockNow = 7000000;
$clock = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$session = new Session($config, null, new AuthenticatedIdleTimeoutResolver($settings, $config), static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwords = new PasswordHasher();
$email = 's5-security-' . bin2hex(random_bytes(4)) . '@example.test';
$insert = $database->connection()->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$insert->execute(['name' => 'S5 Security', 'email' => $email, 'password_hash' => $passwords->make('S5 security password'), 'status' => 'active']);
$userId = (int) $database->connection()->lastInsertId();
$auth = new Auth($config, $session, new UserProvider($database), $passwords, null, null, $sessions, null, $clock);
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static function () use (&$clockNow): int { return $clockNow; }, $events);
$policy = new SecurityPolicyService($settings, $reauthentication, $events, $database);

try {
    $assert($auth->attempt($email, 'S5 security password'), 'Security projection operator login failed.');
    $initial = $policy->values();
    $assert($initial['password_min_length'] === PasswordPolicy::DEFAULT_MINIMUM_LENGTH && $initial['password_max_length'] === PasswordPolicy::DEFAULT_MAXIMUM_LENGTH && $initial['authenticated_idle_timeout_minutes'] === AuthenticatedIdleTimeoutResolver::DEFAULT_MINUTES, 'Security projection did not read canonical defaults.');

    try {
        $policy->update($userId, ['password_min_length' => 14, 'password_max_length' => 140, 'authenticated_idle_timeout_minutes' => 240]);
        $assert(false, 'Security policy mutation bypassed recent re-authentication.');
    } catch (ReauthenticationRequiredException) {
        $assert($policy->values() === $initial, 'Proofless policy mutation partially changed canonical settings.');
    }

    try {
        $policy->update($userId, ['password_min_length' => 141, 'password_max_length' => 140, 'authenticated_idle_timeout_minutes' => 240]);
        $assert(false, 'Invalid password policy combination was accepted.');
    } catch (\Copot\Core\SettingsException) {
        $assert($policy->values() === $initial, 'Invalid password policy combination partially changed settings.');
    }

    $assert($reauthentication->reauthenticate('S5 security password')->succeeded(), 'Canonical recent re-authentication failed.');
    $changed = $policy->update($userId, ['password_min_length' => 14, 'password_max_length' => 140, 'authenticated_idle_timeout_minutes' => 240]);
    $assert(count($changed) === 3, 'Authorized Security policy mutation did not persist all changed values.');
    $updated = $policy->values();
    $assert($updated === ['password_min_length' => 14, 'password_max_length' => 140, 'authenticated_idle_timeout_minutes' => 240], 'Canonical Security policy values were not persisted.');
    $assert((new PasswordPolicy($settings))->minimumLength() === 14 && (new PasswordPolicy($settings))->maximumLength() === 140, 'PasswordPolicy did not consume projected canonical values.');
    $assert((new AuthenticatedIdleTimeoutResolver($settings, $config))->resolve() === 240, 'Authenticated idle-timeout resolver did not consume projected canonical value.');
    $eventRows = $events->listRecent(50);
    $eventJson = json_encode($eventRows, JSON_THROW_ON_ERROR);
    $assert(count(array_filter($eventRows, static fn (array $row): bool => $row['action'] === 'policy_change')) === 3, 'Security policy-change events were not recorded per changed key.');
    $assert(!str_contains($eventJson, 'S5 security password'), 'Security evidence exposed the re-authentication password.');

    echo "WU2-S5 security projection tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
