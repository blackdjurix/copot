<?php

declare(strict_types=1);

use Copot\Core\AuthenticatedIdleTimeoutResolver;
use Copot\Core\AuthenticatedSessionRepository;
use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\DeviceDescriptor;
use Copot\Core\Env;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
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
$connection = $database->connection();
$settings = new SettingsService(SettingsRegistry::core(), new SettingsRepository($database));
$resolver = new AuthenticatedIdleTimeoutResolver($settings, $config);
$sessions = new AuthenticatedSessionRepository($database);
$selfSessions = new SelfSessionService($sessions);
$clockNow = 2000000;
$session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwordHasher = new PasswordHasher();
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$email = 's5-owner-' . bin2hex(random_bytes(4)) . '@example.test';
$otherEmail = 's5-other-' . bin2hex(random_bytes(4)) . '@example.test';
$insert->execute(['name' => 'S5 Owner', 'email' => $email, 'password_hash' => $passwordHasher->make('S5 password'), 'status' => 'active']);
$ownerId = (int) $connection->lastInsertId();
$insert->execute(['name' => 'S5 Other', 'email' => $otherEmail, 'password_hash' => $passwordHasher->make('S5 password'), 'status' => 'active']);
$otherId = (int) $connection->lastInsertId();
$auth = new Auth(
    $config,
    $session,
    new UserProvider($database),
    $passwordHasher,
    null,
    null,
    $sessions,
    static fn (): string => DeviceDescriptor::fromUserAgent('Mozilla/5.0 Chrome/120 Windows NT'),
    static function () use (&$clockNow): DateTimeImmutable {
        return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
    }
);
$now = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};

try {
    $assert($auth->attempt($email, 'S5 password'), 'Initial durable-session login failed.');
    $identity = $session->authenticatedSessionIdentity();
    $phpSessionId = session_id();
    $assert(is_string($identity) && AuthenticatedSessionRepository::validIdentity($identity), 'Durable identity was not opaque 64-character hex.');
    $assert($identity !== $phpSessionId && !str_contains($identity, $phpSessionId), 'Durable identity exposed or reused the PHP session ID.');
    $assert(count($sessions->forUser($ownerId)) === 1, 'Initial login did not create exactly one registry record.');
    $record = $sessions->find((string) $identity);
    $assert($record !== null && $record->userId() === $ownerId && $record->deviceDescriptor() === 'Chrome on Windows', 'Registry ownership or bounded device descriptor was incorrect.');
    $session->regenerate();
    $assert($session->authenticatedSessionIdentity() === $identity && count($sessions->forUser($ownerId)) === 1, 'PHP carrier regeneration created or lost the durable identity.');

    $clockNow += 100;
    $before = $sessions->find((string) $identity)?->lastActiveAt()->format('Y-m-d H:i:s');
    $assert($auth->check(), 'Valid durable session did not authenticate.');
    $assert($sessions->find((string) $identity)?->lastActiveAt()->format('Y-m-d H:i:s') === $before, 'Pre-threshold activity caused persistent write amplification.');
    $clockNow += 200;
    $assert($auth->check(), 'Durable session failed at the activity write threshold.');
    $assert($sessions->find((string) $identity)?->lastActiveAt()->format('Y-m-d H:i:s') !== $before, 'Activity at the five-minute threshold did not persist.');

    $auth->logout();
    $assert($sessions->find((string) $identity)?->isRevoked(), 'Canonical logout did not revoke the durable session.');
    $assert($session->authenticatedSessionIdentity() === null, 'Canonical logout did not clear the PHP durable-session reference.');
    $assert($auth->attempt($email, 'S5 password'), 'Second independent login failed.');
    $secondIdentity = (string) $session->authenticatedSessionIdentity();
    $assert($secondIdentity !== $identity && count($sessions->forUser($ownerId)) === 2, 'Second login did not create a distinct durable identity.');

    $otherRecord = $sessions->create($ownerId, 'Tablet', $now(), 120);
    $foreignRecord = $sessions->create($otherId, 'Other device', $now(), 120);
    $listed = $selfSessions->listOwn($ownerId, $secondIdentity);
    $assert(count($listed) === 3 && count(array_filter($listed, static fn (array $item): bool => $item['session']->userId() !== $ownerId)) === 0, 'Own-session listing exposed another user.');
    $assert(count(array_filter($listed, static fn (array $item): bool => $item['current'])) === 1, 'Own-session listing did not identify exactly the current session.');
    $assert(!$selfSessions->revokeOwn($ownerId, $foreignRecord->identity()) && !$sessions->find($foreignRecord->identity())?->isRevoked(), 'Cross-user session revocation was effective.');
    $assert($selfSessions->revokeOwn($ownerId, $otherRecord->identity()) && $sessions->find($otherRecord->identity())?->isRevoked(), 'Own-session revocation failed.');
    $remaining = $sessions->create($ownerId, 'Laptop', $now(), 120);
    $assert($selfSessions->revokeOtherSessions($ownerId, $secondIdentity) >= 1, 'Sign-out-other-sessions did not revoke other sessions.');
    $assert($sessions->find($secondIdentity)?->isRevoked() === false, 'Sign-out-other-sessions revoked the current session.');
    $assert($sessions->find($remaining->identity())?->isRevoked() === true, 'Sign-out-other-sessions did not revoke the remaining other session.');

    $auth->logout();
    $assert(!$auth->check(), 'Logged-out durable session remained authenticated.');
    $assert($auth->attempt($email, 'S5 password'), 'Third durable-session login failed.');
    $revokedIdentity = (string) $session->authenticatedSessionIdentity();
    $sessions->revoke($revokedIdentity, 'test_revocation');
    $freshAuth = new Auth($config, $session, new UserProvider($database), $passwordHasher, null, null, $sessions, null, static function () use (&$clockNow): DateTimeImmutable { return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC')); });
    $assert(!$freshAuth->check(), 'Revoked durable session authenticated on the next evaluation.');

    $assert($auth->attempt($email, 'S5 password'), 'Missing-row test login failed.');
    $missingIdentity = (string) $session->authenticatedSessionIdentity();
    $connection->prepare('DELETE FROM security_sessions WHERE session_identity = :identity')->execute(['identity' => $missingIdentity]);
    $assert(!$freshAuth->check(), 'Missing durable registry row authenticated on the next evaluation.');

    $assert($auth->attempt($email, 'S5 password'), 'Owner-mismatch test login failed.');
    $mismatchedIdentity = (string) $session->authenticatedSessionIdentity();
    $connection->prepare('UPDATE security_sessions SET user_id = :user_id WHERE session_identity = :identity')->execute(['user_id' => $otherId, 'identity' => $mismatchedIdentity]);
    $assert(!$freshAuth->check(), 'Owner-mismatched durable registry row authenticated on the next evaluation.');

    $assert($auth->attempt($email, 'S5 password'), 'Idle-expiry durable-session login failed.');
    $expiredIdentity = (string) $session->authenticatedSessionIdentity();
    $clockNow += 121 * 60;
    $assert(!$auth->check(), 'S4 idle expiry did not invalidate the durable session.');
    $assert($sessions->find($expiredIdentity)?->isRevoked(), 'S4 idle expiry did not revoke the durable registry record.');
    echo "WU2-S5 persistent-session tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
