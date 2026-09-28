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
$databaseName = 'copot_wu2_s6_' . bin2hex(random_bytes(6));
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
$clockNow = 3000000;
$session = new Session($config, null, $resolver, static function () use (&$clockNow): int { return $clockNow; });
$session->start();
$passwords = new PasswordHasher();
$insert = $connection->prepare('INSERT INTO users (name, email, password_hash, status, created_at, updated_at) VALUES (:name, :email, :password_hash, :status, NOW(), NOW())');
$email = 's6-owner-' . bin2hex(random_bytes(4)) . '@example.test';
$insert->execute(['name' => 'S6 Owner', 'email' => $email, 'password_hash' => $passwords->make('S6 password'), 'status' => 'active']);
$ownerId = (int) $connection->lastInsertId();
$authClock = static function () use (&$clockNow): DateTimeImmutable {
    return (new DateTimeImmutable('@' . $clockNow))->setTimezone(new DateTimeZone('UTC'));
};
$auth = new Auth($config, $session, new UserProvider($database), $passwords, null, null, $sessions, null, $authClock);
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static function () use (&$clockNow): int { return $clockNow; });
$selfSessions = new SelfSessionService($sessions, $reauthentication);

try {
    $assert($auth->attempt($email, 'S6 password'), 'Authenticated S6 fixture login failed.');
    $identity = (string) $session->authenticatedSessionIdentity();
    $assert(!$reauthentication->reauthenticate('wrong')->succeeded(), 'Invalid current password issued a proof.');
    $assert($auth->check() && !$reauthentication->hasValidProof(), 'Failed re-authentication logged out the valid session or left proof state.');
    $assert($reauthentication->reauthenticate('S6 password')->succeeded(), 'Valid current password did not issue proof.');
    $assert(count($sessions->forUser($ownerId)) === 1, 'Re-authentication created a second durable session.');
    $session->regenerate();
    $assert($reauthentication->hasValidProof(), 'Proof did not survive PHP carrier regeneration within the same durable session.');

    $clockNow += 299;
    $assert($reauthentication->hasValidProof(), 'Proof expired before the five-minute boundary.');
    $assert($reauthentication->reauthenticate('wrong')->outcome() === 'invalid_password' && $reauthentication->hasValidProof(), 'Failed re-authentication refreshed or removed a still-valid proof.');
    $clockNow++;
    $assert(!$reauthentication->hasValidProof() && $auth->check(), 'Proof was not expired at five minutes without logging out the user.');
    $assert($reauthentication->reauthenticate('S6 password')->succeeded(), 'Successful re-authentication did not replace expired proof.');
    $clockNow += 299;
    $assert($reauthentication->hasValidProof(), 'Replacement proof did not refresh its validity window.');

    $second = $sessions->create($ownerId, 'Second session', $authClock(), 120);
    $session->setAuthenticatedSessionIdentity($second->identity());
    $assert(!$reauthentication->hasValidProof(), 'Proof crossed durable session identities.');
    $session->setAuthenticatedSessionIdentity($identity);
    $assert($reauthentication->reauthenticate('S6 password')->succeeded() && $reauthentication->hasValidProof(), 'Re-authentication did not issue a new proof after durable-session identity restoration.');

    $other = $sessions->create($ownerId, 'Other session', $authClock(), 120);
    $reauthentication->clearProof();
    try {
        $selfSessions->revokeOtherSessions($ownerId, $identity);
        $assert(false, 'Sign out other sessions was accepted without recent proof.');
    } catch (ReauthenticationRequiredException) {
        $assert(!$sessions->find($other->identity())?->isRevoked(), 'Proofless sensitive action mutated session state.');
    }
    $assert($reauthentication->reauthenticate('S6 password')->succeeded(), 'Re-authentication before sensitive session action failed.');
    $assert($selfSessions->revokeOtherSessions($ownerId, $identity) >= 1, 'Sign out other sessions failed with valid proof.');
    $assert($sessions->find($identity)?->isRevoked() === false && $sessions->find($other->identity())?->isRevoked() === true, 'Sign out other sessions did not preserve current or revoke other sessions.');

    $auth->logout();
    $assert(!$reauthentication->hasValidProof() && $reauthentication->reauthenticate('S6 password')->outcome() === 'unauthenticated', 'Logout did not invalidate re-authentication proof.');
    $assert($auth->attempt($email, 'S6 password'), 'Revocation test login failed.');
    $revokedIdentity = (string) $session->authenticatedSessionIdentity();
    $reauthentication->reauthenticate('S6 password');
    $sessions->revoke($revokedIdentity, 'test_revocation');
    $assert(!$reauthentication->hasValidProof(), 'Revoked durable session retained usable proof.');

    $assert($auth->attempt($email, 'S6 password'), 'Idle invalidation test login failed.');
    $idleIdentity = (string) $session->authenticatedSessionIdentity();
    $reauthentication->reauthenticate('S6 password');
    $clockNow += 121 * 60;
    $assert(!$reauthentication->hasValidProof() && $sessions->find($idleIdentity)?->isRevoked(), 'Idle invalidation did not prevent proof use or revoke the durable session.');
    echo "WU2-S6 re-authentication tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    try { $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase); } catch (Throwable) {}
}
