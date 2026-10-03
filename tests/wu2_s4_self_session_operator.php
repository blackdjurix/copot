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
use Copot\Core\ReauthenticationRequiredException;
use Copot\Core\ReauthenticationService;
use Copot\Core\SecurityEventRepository;
use Copot\Core\SecurityEventService;
use Copot\Core\SelfSessionService;
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
$databaseName = 'copot_wu2_s4_' . bin2hex(random_bytes(6));
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
$clockNow = 5000000;
$now = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwords = new PasswordHasher();
$ownerEmail = 's4-owner-' . bin2hex(random_bytes(4)) . '@example.test';
$foreignEmail = 's4-foreign-' . bin2hex(random_bytes(4)) . '@example.test';
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$insert->execute(['name' => 'S4 Owner', 'email' => $ownerEmail, 'password_hash' => $passwords->make('S4 password'), 'status' => 'active']);
$ownerId = (int) $connection->lastInsertId();
$insert->execute(['name' => 'S4 Foreign', 'email' => $foreignEmail, 'password_hash' => $passwords->make('S4 password'), 'status' => 'active']);
$foreignId = (int) $connection->lastInsertId();
$authClock = static function () use (&$clockNow): DateTimeImmutable { return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC')); };
$auth = new Auth($config, $session, new UserProvider($database), $passwords, null, null, $sessions, null, $authClock);
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static function () use (&$clockNow): int { return $clockNow; }, $events);
$selfSessions = new SelfSessionService($sessions, $reauthentication, $events);

try {
    $assert($selfSessions->listOwn($ownerId, str_repeat('a', 64)) === [], 'Unauthenticated service caller unexpectedly accessed sessions without route authentication context.');
    $assert($auth->attempt($ownerEmail, 'S4 password'), 'S4 owner login failed.');
    $currentIdentity = (string) $session->authenticatedSessionIdentity();
    $otherOwn = $sessions->create($ownerId, 'Other owner device', $now(), 120);
    $foreign = $sessions->create($foreignId, 'Foreign device', $now(), 120);

    $listed = $selfSessions->listOwn($ownerId, $currentIdentity);
    $assert(count($listed) === 2, 'Own-session listing did not return exactly the owner sessions.');
    $assert(count(array_filter($listed, static fn (array $item): bool => $item['session']->userId() === $foreignId)) === 0, 'Own-session listing exposed another user.');
    $assert(count(array_filter($listed, static fn (array $item): bool => $item['current'])) === 1, 'Own-session listing did not identify exactly one current session.');
    $assert((array_values(array_filter($listed, static fn (array $item): bool => $item['current']))[0]['session']->identity() ?? null) === $currentIdentity, 'Current-session marker used the wrong durable identity.');

    $assert($reauthentication->reauthenticate('S4 password')->succeeded(), 'Valid re-authentication failed before session mutation.');
    $assert(!$selfSessions->revokeOwn($ownerId, $foreign->identity()), 'Cross-user session revocation succeeded.');
    $assert(!$sessions->find($foreign->identity())?->isRevoked(), 'Foreign session was revoked by an owner-scoped request.');
    $assert($selfSessions->revokeOwn($ownerId, $otherOwn->identity()), 'Specific own-session revocation failed.');
    $assert($sessions->find($otherOwn->identity())?->isRevoked(), 'Specific own-session revocation did not persist server-side state.');

    $remaining = $sessions->create($ownerId, 'Remaining owner device', $now(), 120);
    $reauthentication->clearProof();
    try {
        $selfSessions->revokeOtherSessions($ownerId, $currentIdentity);
        $assert(false, 'Sign-out-all-others succeeded without recent proof.');
    } catch (ReauthenticationRequiredException) {
        $assert(!$sessions->find($remaining->identity())?->isRevoked(), 'Proofless bulk revocation changed session state.');
    }

    $assert($reauthentication->reauthenticate('S4 password')->succeeded(), 'Valid re-authentication failed before bulk revocation.');
    $assert($selfSessions->revokeOtherSessions($ownerId, $currentIdentity) >= 1, 'Sign-out-all-others did not revoke another own session.');
    $assert($sessions->find($currentIdentity)?->isRevoked() === false, 'Sign-out-all-others revoked the current durable session.');
    $assert($sessions->find($remaining->identity())?->isRevoked() === true, 'Sign-out-all-others did not revoke the remaining own session.');

    $assert($auth->check(), 'Current durable session did not remain authenticated after bulk revocation.');

    $freshAuth = new Auth($config, $session, new UserProvider($database), $passwords, null, null, $sessions, null, $authClock);
    $session->setAuthenticatedSessionIdentity($remaining->identity());
    $assert(!$freshAuth->check(), 'Revoked durable session remained authenticated during canonical evaluation.');
    $session->set($config->get('auth.session_key', '_copot_user_id'), $ownerId);
    $session->setAuthenticatedSessionIdentity($currentIdentity);
    $session->beginAuthenticatedActivity($clockNow);
    $assert($auth->check(), 'Current durable session did not remain authenticated after bulk revocation.');

    $auth->logout();
    $assert(!$auth->check() && $sessions->find($currentIdentity)?->isRevoked(), 'Canonical logout did not terminate and revoke the current session.');
    $eventRows = $events->listRecent(100);
    $eventJson = json_encode($eventRows, JSON_THROW_ON_ERROR);
    $assert(count(array_filter($eventRows, static fn (array $row): bool => $row['action'] === 'session_revoked')) >= 1, 'Specific session revocation event was missing.');
    $assert(count(array_filter($eventRows, static fn (array $row): bool => $row['action'] === 'sign_out_other_sessions')) === 1, 'Bulk revocation event count was incorrect.');
    $assert(str_contains($eventJson, 'revoked_count') && !str_contains($eventJson, $currentIdentity) && !str_contains($eventJson, $foreign->identity()), 'Session events exposed raw durable identities.');

    $routeSource = (string) file_get_contents($basePath . '/routes/auth.php');
    $assert(str_contains($routeSource, "get('/account/sessions'"), 'Self-session listing route was not registered.');
    $assert(str_contains($routeSource, "post('/account/sessions/revoke-others'"), 'Bulk self-session route was not registered.');
    $assert(str_contains($routeSource, "post('/account/sessions/{identity}/revoke'"), 'Specific self-session route was not registered.');
    $assert(str_contains($routeSource, 'selfSessions()->listOwn'), 'Listing route did not use SelfSessionService.');
    $assert(str_contains($routeSource, 'selfSessions()->revokeOtherSessions'), 'Bulk route did not use SelfSessionService.');
    $assert(str_contains($routeSource, "post('/logout'") && str_contains($routeSource, '$app->auth()->logout()'), 'Canonical logout route was replaced or removed.');

    echo "WU2-S4 self-session operator tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
