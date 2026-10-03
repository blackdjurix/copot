<?php

declare(strict_types=1);

use Copot\Core\AuthenticatedIdleTimeoutResolver;
use Copot\Core\AuthenticatedSessionRepository;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\CurrentUserPasswordService;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
use Copot\Core\PasswordPolicy;
use Copot\Core\ReauthenticationService;
use Copot\Core\SecurityEventRepository;
use Copot\Core\SecurityEventService;
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
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) Env::get('DB_HOST', '127.0.0.1');
$port = (int) Env::get('DB_PORT', 3306);
$username = (string) Env::get('DB_USERNAME', 'root');
$password = (string) Env::get('DB_PASSWORD', '');
$databaseName = 'copot_wu2_s3_' . bin2hex(random_bytes(6));
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
$connection = $database->connection();
$settings = new SettingsService(SettingsRegistry::core(), new SettingsRepository($database));
$resolver = new AuthenticatedIdleTimeoutResolver($settings, $config);
$sessions = new AuthenticatedSessionRepository($database);
$events = new SecurityEventService(new SecurityEventRepository($database));
$clockNow = 4000000;
$session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwords = new PasswordHasher();
$oldPassword = 'S3 current password';
$newPassword = 'S3 replacement password';
$email = 's3-owner-' . bin2hex(random_bytes(4)) . '@example.test';
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$insert->execute(['name' => 'S3 Owner', 'email' => $email, 'password_hash' => $passwords->make($oldPassword), 'status' => 'active']);
$ownerId = (int) $connection->lastInsertId();
$authClock = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$auth = new Auth($config, $session, new UserProvider($database), $passwords, null, null, $sessions, null, $authClock);
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static function () use (&$clockNow): int { return $clockNow; }, $events);
$service = new CurrentUserPasswordService($auth, new UserProvider($database), $passwords, new PasswordPolicy($settings), $reauthentication, $events);
$storedHash = static function () use ($connection, $ownerId): string {
    $statement = $connection->prepare('SELECT password_hash FROM users WHERE id = :id');
    $statement->execute(['id' => $ownerId]);

    return (string) $statement->fetchColumn();
};

try {
    $unauthenticated = $service->change($newPassword, $newPassword);
    $assert($unauthenticated->code() === 'unauthenticated', 'Unauthenticated mutation was not rejected.');
    $assert($passwords->verify($oldPassword, $storedHash()), 'Unauthenticated mutation changed the password.');

    $assert($auth->attempt($email, $oldPassword), 'S3 fixture login failed.');
    $identity = (string) $session->authenticatedSessionIdentity();
    $withoutProof = $service->change($newPassword, $newPassword);
    $assert($withoutProof->code() === 'reauthentication_required', 'Password mutation bypassed recent re-authentication.');
    $assert($passwords->verify($oldPassword, $storedHash()), 'Proofless password mutation changed the password.');

    $assert(!$reauthentication->reauthenticate('wrong current password')->succeeded(), 'Incorrect current password issued re-authentication proof.');
    $failedReauth = $service->change($newPassword, $newPassword);
    $assert($failedReauth->code() === 'reauthentication_required', 'Failed re-authentication authorized password mutation.');
    $assert($passwords->verify($oldPassword, $storedHash()), 'Failed re-authentication changed the password.');

    $assert($reauthentication->reauthenticate($oldPassword)->succeeded(), 'Valid current password did not issue proof.');
    $invalid = $service->change('short', 'short');
    $assert($invalid->code() === 'invalid_password' && isset($invalid->errors()['password']), 'Invalid new password was not rejected by PasswordPolicy.');
    $assert($passwords->verify($oldPassword, $storedHash()), 'Invalid new password changed the password.');

    $assert($reauthentication->hasValidProof(), 'Policy rejection unexpectedly cleared valid re-authentication proof.');
    $changed = $service->change($newPassword, $newPassword);
    $assert($changed->succeeded(), 'Valid current-user password mutation failed.');
    $assert(!$passwords->verify($oldPassword, $storedHash()), 'Old password still verifies after successful mutation.');
    $assert($passwords->verify($newPassword, $storedHash()), 'New password does not verify through PasswordHasher.');
    $assert(count($sessions->forUser($ownerId)) === 1, 'Password mutation created a duplicate authenticated session.');
    $assert(!$reauthentication->hasValidProof(), 'Successful password mutation did not clear the consumed proof.');
    $assert($auth->check() && $auth->user()?->id() === $ownerId, 'Successful password mutation disrupted the current authenticated session.');

    $eventsRows = array_values(array_filter($events->listRecent(100), static fn (array $row): bool => $row['action'] === 'password_change'));
    $eventJson = json_encode($eventsRows, JSON_THROW_ON_ERROR);
    $assert(count($eventsRows) >= 2, 'Credential mutation evidence did not record rejection and success.');
    $assert(str_contains($eventJson, 'success') && str_contains($eventJson, 'rejected'), 'Credential mutation event outcomes were incomplete.');
    $assert(!str_contains($eventJson, $oldPassword) && !str_contains($eventJson, $newPassword), 'Credential mutation event stored password material.');
    $assert($identity !== '', 'Authenticated session identity was unavailable for credential evidence.');

    $routeSource = (string) file_get_contents($basePath . '/routes/auth.php');
    $assert(str_contains($routeSource, "post('/account/password'"), 'Current-user password route was not registered.');
    $assert(str_contains($routeSource, 'currentUserPassword()->change'), 'Current-user route did not use the canonical mutation service.');
    $assert(str_contains($routeSource, 'validateCsrf'), 'Current-user password route omitted CSRF validation.');

    echo "WU2-S3 current-user password tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
