<?php

declare(strict_types=1);

use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\FailedLoginAttemptRepository;
use Copot\Core\FailedLoginThrottle;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\PasswordHasher;
use Copot\Core\Session;
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
(new InstallerSchemaRunner($basePath . '/database/schema.sql'))->install(['host' => $host, 'port' => $port, 'database' => $databaseName, 'username' => $username, 'password' => $password]);
$_ENV['DB_DATABASE'] = $databaseName;
putenv('DB_DATABASE=' . $databaseName);
$config = new Config($basePath . '/config');
$database = new Database($config);
$connection = $database->connection();
$connection->beginTransaction();
$session = new Session($config);
$session->start();

$now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
$clock = static function () use (&$now): DateTimeImmutable { return $now; };
$delays = [];
$throttle = new FailedLoginThrottle(new FailedLoginAttemptRepository($database), $clock);
$auth = new Auth(
    $config,
    $session,
    new UserProvider($database),
    new PasswordHasher(),
    $throttle,
    static function (int $milliseconds) use (&$delays): void { $delays[] = $milliseconds; }
);

$suffix = bin2hex(random_bytes(6));
$activeEmail = 's3-active-' . $suffix . '@example.test';
$inactiveEmail = 's3-inactive-' . $suffix . '@example.test';
$activePassword = 'S3 active password ' . $suffix;
$hash = (new PasswordHasher())->make($activePassword);
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$insert->execute(['name' => 'S3 Active', 'email' => $activeEmail, 'password_hash' => $hash, 'status' => 'active']);
$activeId = (int) $connection->lastInsertId();
$insert->execute(['name' => 'S3 Inactive', 'email' => $inactiveEmail, 'password_hash' => $hash, 'status' => 'inactive']);
$inactiveId = (int) $connection->lastInsertId();

try {
    $unknownEmail = 's3-unknown-' . $suffix . '@example.test';
    for ($attempt = 1; $attempt <= 4; $attempt++) {
        $assert(!$auth->attempt($unknownEmail, 'wrong'), 'Attempts 1-4 unexpectedly authenticated.');
    }
    $assert($delays === [], 'Attempts 1-4 introduced artificial delay.');
    for ($attempt = 5; $attempt <= 7; $attempt++) {
        $assert(!$auth->attempt($unknownEmail, 'wrong'), 'Attempts 5-7 unexpectedly authenticated.');
    }
    $assert($delays === [250, 250, 250], 'Attempts 5-7 did not use the first bounded delay tier.');
    for ($attempt = 8; $attempt <= 9; $attempt++) {
        $assert(!$auth->attempt($unknownEmail, 'wrong'), 'Attempts 8-9 unexpectedly authenticated.');
    }
    $assert($delays === [250, 250, 250, 1000, 1000], 'Attempts 8-9 did not use the stronger bounded delay tier.');
    $assert(!$auth->attempt($unknownEmail, 'wrong'), 'Attempt 10 unexpectedly authenticated.');
    $assert(count($delays) === 5, 'Attempt 10 applied an unbounded or unexpected delay.');

    $state = $connection->prepare('SELECT failure_count, locked_until FROM security_login_attempts WHERE target_hash = :target_hash');
    $state->execute(['target_hash' => FailedLoginThrottle::targetHash($unknownEmail)]);
    $locked = $state->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($locked) && (int) $locked['failure_count'] === 10 && $locked['locked_until'] !== null, 'Attempt 10 did not persist temporary lockout state.');
    $assert(!$auth->attempt($unknownEmail, 'wrong'), 'Active lockout did not reject authentication.');

    $auth->logout();
    $knownDelays = [];
    $knownThrottle = new FailedLoginThrottle(new FailedLoginAttemptRepository($database), $clock);
    $knownAuth = new Auth($config, $session, new UserProvider($database), new PasswordHasher(), $knownThrottle, static function (int $milliseconds) use (&$knownDelays): void { $knownDelays[] = $milliseconds; });
    $assert(!$knownAuth->attempt($activeEmail, 'wrong'), 'Known-account invalid password unexpectedly authenticated.');
    $assert(!$knownAuth->attempt($unknownEmail . '-fresh', 'wrong'), 'Unknown-account invalid password unexpectedly authenticated.');
    $assert($knownDelays === [], 'First known-account failure introduced delay.');
    $assert(count($knownDelays) === 0, 'Unknown-account and known-account failure handling diverged at the first attempt.');

    $knownAuth->logout();
    $assert($knownAuth->attempt($activeEmail, $activePassword), 'Valid active-user authentication failed after throttling integration.');
    $assert($knownAuth->id() === $activeId && $knownAuth->check(), 'Successful authentication did not establish the canonical session.');
    $state->execute(['target_hash' => FailedLoginThrottle::targetHash($activeEmail)]);
    $assert($state->fetch(PDO::FETCH_ASSOC) === false, 'Successful authentication did not clear active failure state.');
    $lastLogin = $connection->prepare('SELECT last_login_at FROM users WHERE id = :id');
    $lastLogin->execute(['id' => $activeId]);
    $lastLoginValue = $lastLogin->fetchColumn();
    $assert($lastLoginValue !== false && $lastLoginValue !== null, 'Successful authentication did not preserve last-login update behavior.');

    $knownAuth->logout();
    $activeStatus = $connection->prepare('SELECT status FROM users WHERE id = :id');
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $knownAuth->attempt($activeEmail, 'wrong');
    }
    $assert(!$knownAuth->attempt($activeEmail, $activePassword), 'Active-account lockout did not reject a valid password.');
    $activeStatus->execute(['id' => $activeId]);
    $assert($activeStatus->fetchColumn() === 'active', 'Temporary lockout changed permanent account status.');
    $now = $now->modify('+901 seconds');
    $assert($knownAuth->attempt($activeEmail, $activePassword), 'Temporary lockout did not expire automatically.');
    $knownAuth->logout();
    $now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));

    $knownAuth->logout();
    $inactiveAuth = new Auth($config, $session, new UserProvider($database), new PasswordHasher(), new FailedLoginThrottle(new FailedLoginAttemptRepository($database), $clock), static function (): void {});
    $assert(!$inactiveAuth->attempt($inactiveEmail, $activePassword), 'Inactive user authentication was accepted.');
    $inactiveStatus = $connection->prepare('SELECT status FROM users WHERE id = :id');
    $inactiveStatus->execute(['id' => $inactiveId]);
    $assert($inactiveStatus->fetchColumn() === 'inactive' && !$inactiveAuth->check(), 'Inactive-user semantics changed during throttling.');

    $windowEmail = 's3-window-' . $suffix . '@example.test';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $inactiveAuth->attempt($windowEmail, 'wrong');
    }
    $now = $now->modify('+901 seconds');
    $inactiveAuth->attempt($windowEmail, 'wrong');
    $state->execute(['target_hash' => FailedLoginThrottle::targetHash($windowEmail)]);
    $windowState = $state->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($windowState) && (int) $windowState['failure_count'] === 1, 'Expired failure-window state continued contributing to the active attempt count.');

    $now = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
    $expiryEmail = 's3-expiry-' . $suffix . '@example.test';
    for ($attempt = 1; $attempt <= 10; $attempt++) {
        $inactiveAuth->attempt($expiryEmail, 'wrong');
    }
    $now = $now->modify('+901 seconds');
    $assert(!$inactiveAuth->attempt($expiryEmail, 'wrong'), 'Expired lockout attempt unexpectedly authenticated an unknown account.');
    $state->execute(['target_hash' => FailedLoginThrottle::targetHash($expiryEmail)]);
    $expiredState = $state->fetch(PDO::FETCH_ASSOC);
    $assert(is_array($expiredState) && (int) $expiredState['failure_count'] === 1 && $expiredState['locked_until'] === null, 'Temporary lockout did not expire and reset progression.');

    echo "WU2-S3 failed-login throttling tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $session->destroy();
    }
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
