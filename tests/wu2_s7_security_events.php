<?php

declare(strict_types=1);

use Copot\Core\AuthenticatedIdleTimeoutResolver;
use Copot\Core\AuthenticatedSessionRepository;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\DeviceDescriptor;
use Copot\Core\Env;
use Copot\Core\FailedLoginAttemptRepository;
use Copot\Core\FailedLoginThrottle;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
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
$databaseName = 'copot_wu2_s7_' . bin2hex(random_bytes(6));
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
$eventRepository = new SecurityEventRepository($database);
$clockNow = 4000000;
$clock = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$events = new SecurityEventService($eventRepository, $clock);
$sessions = new AuthenticatedSessionRepository($database);
$session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwords = new PasswordHasher();
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$email = 's7-owner-' . bin2hex(random_bytes(4)) . '@example.test';
$insert->execute(['name' => 'S7 Owner', 'email' => $email, 'password_hash' => $passwords->make('S7 secret password'), 'status' => 'active']);
$userId = (int) $connection->lastInsertId();
$authClock = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$auth = new Auth(
    $config,
    $session,
    new UserProvider($database),
    $passwords,
    new FailedLoginThrottle(new FailedLoginAttemptRepository($database), $authClock),
    static function (): void {},
    $sessions,
    static fn (): string => DeviceDescriptor::fromUserAgent('Mozilla/5.0 Chrome/120 Windows NT'),
    $authClock,
    $events
);
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static function () use (&$clockNow): int { return $clockNow; }, $events);
$selfSessions = new SelfSessionService($sessions, $reauthentication, $events);

try {
    $assert($auth->attempt($email, 'S7 secret password'), 'S7 successful login failed.');
    $identity = (string) $session->authenticatedSessionIdentity();
    $phpSessionId = session_id();
    $rows = $events->listRecent(20);
    $assert(count(array_filter($rows, static fn (array $row): bool => $row['action'] === 'session_created')) === 1, 'Successful login did not emit one session-created event.');
    $assert(count(array_filter($rows, static fn (array $row): bool => $row['action'] === 'login' && $row['result'] === 'success')) === 1, 'Successful login did not emit one login-success event.');
    $serialized = json_encode($rows, JSON_THROW_ON_ERROR);
    $assert(!str_contains($serialized, 'S7 secret password') && !str_contains($serialized, $phpSessionId), 'Security events stored plaintext credentials or the raw PHP session ID.');
    $assert(!str_contains($serialized, $identity) && str_contains($serialized, substr($identity, 0, 12)), 'Session event evidence was not bounded opaque identity evidence.');
    $assert(($rows[0]['category'] ?? '') === 'authentication' || ($rows[0]['category'] ?? '') === 'session', 'Event taxonomy category was not stable.');

    $auth->logout();
    $assert(count(array_filter($events->listRecent(50), static fn (array $row): bool => $row['action'] === 'logout' && $row['result'] === 'revoked')) === 1, 'Logout did not emit one revocation event.');
    $assert(!$auth->attempt('s7-unknown@example.test', 'wrong secret'), 'Unknown S7 login unexpectedly succeeded.');
    $failureRows = array_filter($events->listRecent(50), static fn (array $row): bool => $row['action'] === 'login' && $row['result'] === 'failure');
    $assert(count($failureRows) === 1 && !str_contains(json_encode($failureRows, JSON_THROW_ON_ERROR), 's7-unknown@example.test'), 'Login failure event was missing or exposed the account identity.');

    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $auth->attempt($email, 'wrong secret');
    }
    $lockoutRows = array_filter($events->listRecent(100), static fn (array $row): bool => $row['action'] === 'login' && $row['result'] === 'locked');
    $assert(count($lockoutRows) >= 1 && count($lockoutRows) <= 2, 'Temporary lockout evidence was missing or duplicated ambiguously.');
    $assert(str_contains(json_encode($lockoutRows, JSON_THROW_ON_ERROR), 'attempt_count'), 'Lockout event omitted bounded attempt evidence.');

    $clockNow += 901;
    $assert($auth->attempt($email, 'S7 secret password'), 'Post-lockout login failed.');
    $assert($reauthentication->reauthenticate('S7 secret password')->succeeded(), 'S7 re-authentication setup failed.');
    $assert($reauthentication->reauthenticate('wrong secret')->outcome() === 'invalid_password', 'Invalid re-authentication outcome was not stable.');
    $reauthRows = array_filter($events->listRecent(150), static fn (array $row): bool => $row['action'] === 'reauthenticate');
    $reauthJson = json_encode($reauthRows, JSON_THROW_ON_ERROR);
    $assert(count(array_filter($reauthRows, static fn (array $row): bool => $row['result'] === 'success')) === 1 && count(array_filter($reauthRows, static fn (array $row): bool => $row['result'] === 'invalid_password')) === 1, 'Re-authentication producer outcomes were incomplete.');
    $assert(!str_contains($reauthJson, 'S7 secret password') && !str_contains($reauthJson, 'wrong secret'), 'Re-authentication event stored submitted password material.');

    $other = $sessions->create($userId, 'Other', $clock(), 120);
    $currentIdentity = (string) $session->authenticatedSessionIdentity();
    $assert($selfSessions->revokeOtherSessions($userId, $currentIdentity) >= 1, 'Sign-out-other-sessions event producer did not revoke another session.');
    $signoutRows = array_filter($events->listRecent(200), static fn (array $row): bool => $row['action'] === 'sign_out_other_sessions');
    $assert(count($signoutRows) === 1 && str_contains((string) $signoutRows[array_key_first($signoutRows)]['context_json'], 'revoked_count'), 'Sign-out-other-sessions evidence was missing or unbounded.');

    $auth->logout();
    $assert($auth->attempt($email, 'S7 secret password'), 'Idle-expiry setup login failed.');
    $idleIdentity = (string) $session->authenticatedSessionIdentity();
    $clockNow += 121 * 60;
    $assert(!$auth->check(), 'Idle expiry did not invalidate the authenticated session.');
    $idleRows = array_filter($events->listRecent(250), static fn (array $row): bool => $row['action'] === 'idle_expiry');
    $assert(count($idleRows) === 1 && $sessions->find($idleIdentity)?->isRevoked(), 'Idle expiry event or durable revocation was missing.');

    $pruneNow = $clock();
    $old = $pruneNow->modify('-' . (SecurityEventService::RETENTION_SECONDS + 1) . ' seconds');
    $inside = $pruneNow->modify('-' . (SecurityEventService::RETENTION_SECONDS - 1) . ' seconds');
    $events->record('test', 'info', null, null, null, 'retention_old', 'success', ['attempt_count' => 1], $old);
    $events->record('test', 'info', null, null, null, 'retention_inside', 'success', ['attempt_count' => 2], $inside);
    $beforePrune = $events->listRecent(500);
    $assert($events->prune($pruneNow) === 1, 'Retention pruning did not remove exactly the expired event.');
    $afterPrune = $events->listRecent(500);
    $assert(count(array_filter($afterPrune, static fn (array $row): bool => $row['action'] === 'retention_old')) === 0, 'Expired event survived retention pruning.');
    $survivor = array_values(array_filter($afterPrune, static fn (array $row): bool => $row['action'] === 'retention_inside'))[0] ?? null;
    $beforeSurvivor = array_values(array_filter($beforePrune, static fn (array $row): bool => $row['action'] === 'retention_inside'))[0] ?? null;
    $assert(is_array($survivor) && is_array($beforeSurvivor) && $survivor['context_json'] === $beforeSurvivor['context_json'], 'Retention pruning mutated a surviving event.');
    $bounded = str_repeat('x', 500);
    $events->record('test', 'info', null, 'test', 'bounded', 'sanitized_context', 'success', ['device_descriptor' => $bounded, 'password' => 'do-not-store', 'raw_header' => 'do-not-store']);
    $sanitized = array_values(array_filter($events->listRecent(500), static fn (array $row): bool => $row['action'] === 'sanitized_context'))[0] ?? null;
    $assert(is_array($sanitized) && strlen((string) $sanitized['context_json']) < 300 && !str_contains((string) $sanitized['context_json'], 'do-not-store'), 'Event context allow-listing or bounds failed.');
    $assert(SecurityEventService::PRUNE_EVERY_APPENDS === 100, 'Retention trigger is not the documented bounded lower-write strategy.');

    echo "WU2-S7 security-event tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
