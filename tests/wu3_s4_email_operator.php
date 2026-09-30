<?php

declare(strict_types=1);

use Copot\Core\Auth;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\EmailConfigurationResolver;
use Copot\Core\EmailCredentialBoundary;
use Copot\Core\EmailMessage;
use Copot\Core\EmailOperatorService;
use Copot\Core\EmailSenderIdentity;
use Copot\Core\EmailTransport;
use Copot\Core\EmailTransportConfiguration;
use Copot\Core\PasswordHasher;
use Copot\Core\PermissionChecker;
use Copot\Core\ReauthenticationService;
use Copot\Core\Session;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SmtpTransportDriver;
use Copot\Core\User;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$config = new Config($basePath . DIRECTORY_SEPARATOR . 'config');
$database = new Database($config);
$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-s4-' . bin2hex(random_bytes(5));
mkdir($storage, 0700, true);
$environmentPath = $storage . DIRECTORY_SEPARATOR . '.env';
$secret = 'operator-secret-' . bin2hex(random_bytes(8));

$schema = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
$upgrade = (string) file_get_contents($basePath . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'upgrades' . DIRECTORY_SEPARATOR . 'wu3_s4_email_permission.sql');
$assert(str_contains($schema, "'Manage Email', 'email.manage'"), 'Fresh schema does not declare email.manage.');
$assert(str_contains($schema, "permissions.slug = 'email.manage'"), 'Fresh schema does not grant email.manage to Administrator.');
$assert(str_contains($upgrade, "'Manage Email', 'email.manage'"), 'Existing-install upgrade does not declare email.manage.');
$assert(str_contains($upgrade, 'LEFT JOIN role_permissions'), 'Existing-install upgrade is not idempotent for role grants.');

$executeSql = static function (PDO $connection, string $sql): void {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $connection->exec($statement);
    }
};

$settings = new class(SettingsRegistry::core(), new SettingsRepository($database)) extends SettingsService {
    public array $values = [];

    public function get(string $namespace, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($namespace . '.' . $key, $this->values)) {
            return $this->values[$namespace . '.' . $key];
        }

        return parent::get($namespace, $key, $default);
    }

    public function validate(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $definition = SettingsRegistry::core()->find($namespace, $key);
        if ($definition === null || ($type !== null && $definition->type() !== $type)) {
            throw new \Copot\Core\SettingsException('Invalid Email setting.');
        }
        $definition->validate($value);
    }

    public function set(string $namespace, string $key, mixed $value, ?string $type = null): void
    {
        $this->validate($namespace, $key, $value, $type);
        $this->values[$namespace . '.' . $key] = $value;
    }
};
$settings->values = [
    'email.smtp_host' => 'smtp.example.test',
    'email.smtp_port' => 2525,
    'email.smtp_security' => 'tls',
    'email.smtp_username' => 'mailer@example.test',
    'email.sender_email' => 'no-reply@example.test',
    'email.sender_name' => 'COPOT Mailer',
];

$credentials = new EmailCredentialBoundary($environmentPath);
$driver = new class implements SmtpTransportDriver {
    public bool $fail = false;
    public int $calls = 0;

    public function deliver(EmailMessage $message, EmailSenderIdentity $sender, EmailTransportConfiguration $transport, string $credential): void
    {
        $this->calls++;
        if ($this->fail) {
            throw new RuntimeException('driver failure: ' . $credential);
        }
    }
};
$transport = new EmailTransport(new EmailConfigurationResolver($settings, $credentials), $credentials, $driver);

$permissionChecker = new class($database) extends PermissionChecker {
    public array $allowed = [];

    public function userCan(int $userId, string $permission): bool
    {
        return in_array($permission, $this->allowed, true);
    }
};
$passwords = new PasswordHasher();
$operatorPassword = 'operator-password-' . bin2hex(random_bytes(4));
$operator = new User([
    'id' => 9001,
    'name' => 'Email Operator',
    'email' => 'email-operator@example.test',
    'password_hash' => $passwords->make($operatorPassword),
    'status' => 'active',
], $permissionChecker);

$session = new Session($config);
session_save_path(sys_get_temp_dir());
session_id('copotwu3s4' . bin2hex(random_bytes(5)));
$session->start();
$auth = new class($config, $session, $database, $operator) extends Auth {
    public function __construct(Config $config, Session $session, Database $database, private User $operator)
    {
        parent::__construct($config, $session, new \Copot\Core\UserProvider($database), new PasswordHasher());
    }

    public function check(): bool { return true; }
    public function user(): ?User { return $this->operator; }
    public function durableSessionIdentity(): ?string { return 'operator-session'; }
};
$reauthentication = new ReauthenticationService($auth, $session, $passwords, static fn (): int => time());
$service = new EmailOperatorService($settings, new EmailConfigurationResolver($settings, $credentials), $credentials, $transport, $reauthentication);

try {
    $permissionChecker->allowed = ['admin.access', 'email.manage'];
    $read = $service->read($operator);
    $assert($read->succeeded(), 'Authorized Email read was denied.');
    $assert(($read->toArray()['data']['credential'] ?? null) === ['configured' => false], 'Email read did not redact credential state.');
    $assert(!str_contains(serialize($read->toArray()), $secret), 'Email read exposed credential plaintext.');

    $validUpdate = $service->updateConfiguration($operator, [
        'email.smtp_host' => 'smtp.example.test',
        'email.smtp_port' => 2525,
        'email.smtp_security' => 'ssl',
        'email.smtp_username' => '',
        'email.sender_email' => 'no-reply@example.test',
        'email.sender_name' => 'COPOT Mailer',
    ]);
    $assert($validUpdate->succeeded(), 'Authorized non-secret Email mutation failed.');
    $assert($settings->values['email.smtp_security'] === 'ssl', 'Email mutation did not use Settings authority.');
    $assert($validUpdate->toArray()['data']['transport']['username'] === null, 'Optional username did not resolve to null.');

    $invalidUpdate = $service->updateConfiguration($operator, ['email.smtp_port' => 70000]);
    $assert($invalidUpdate->code() === 'invalid_configuration', 'Invalid Email configuration was accepted.');

    $beforeCredential = $credentials->state();
    $withoutProof = $service->replaceCredential($operator, $secret);
    $assert($withoutProof->code() === 'reauthentication_required', 'Credential replacement bypassed re-authentication.');
    $assert($credentials->state() === $beforeCredential, 'Rejected credential replacement changed state.');

    $assert($reauthentication->reauthenticate($operatorPassword)->succeeded(), 'Canonical re-authentication proof could not be established.');
    $credentialUpdate = $service->replaceCredential($operator, $secret);
    $assert($credentialUpdate->succeeded(), 'Credential replacement with valid proof failed.');
    $assert($credentialUpdate->toArray()['data']['credential'] === ['configured' => true], 'Credential replacement returned unsafe state.');
    $assert(!str_contains(serialize($credentialUpdate->toArray()), $secret), 'Credential replacement result exposed plaintext.');

    $testBefore = $settings->values;
    $delivery = $service->sendTest($operator, 'recipient@example.test');
    $assert($delivery->code() === 'test_delivery_succeeded', 'Controlled test delivery did not succeed.');
    $assert($driver->calls === 1, 'Controlled test delivery did not invoke the injected transport exactly once.');
    $assert($settings->values === $testBefore, 'Controlled test delivery mutated Email configuration.');

    $driver->fail = true;
    $failedDelivery = $service->sendTest($operator, 'recipient@example.test');
    $assert($failedDelivery->code() === 'test_delivery_failed', 'Transport failure was not sanitized for operators.');
    $assert(!str_contains(serialize($failedDelivery->toArray()), $secret), 'Delivery failure exposed credential plaintext.');

    $permissionChecker->allowed = ['admin.access'];
    $denied = $service->updateConfiguration($operator, ['email.smtp_port' => 2526]);
    $assert($denied->code() === 'forbidden', 'admin.access alone authorized Email mutation.');
    $permissionChecker->allowed = ['settings.update'];
    $settingsOnly = $service->sendTest($operator, 'recipient@example.test');
    $assert($settingsOnly->code() === 'forbidden', 'settings.update alone authorized Email test delivery.');

    echo "WU3-S4 email operator passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $session->destroy();
    }

    foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($storage);
}

$upgradeDatabaseName = 'copot_wu3_s4_' . bin2hex(random_bytes(5));
$host = (string) \Copot\Core\Env::get('DB_HOST', '127.0.0.1');
$port = (string) \Copot\Core\Env::get('DB_PORT', '3306');
$username = (string) \Copot\Core\Env::get('DB_USERNAME', 'root');
$password = (string) \Copot\Core\Env::get('DB_PASSWORD', '');
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$upgradeDatabase = null;
try {
    $server->exec("CREATE DATABASE `{$upgradeDatabaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $upgradeDatabase = new PDO("mysql:host={$host};port={$port};dbname={$upgradeDatabaseName};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $upgradeDatabase->exec('CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, slug VARCHAR(100) NOT NULL UNIQUE, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB');
    $upgradeDatabase->exec('CREATE TABLE permissions (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, slug VARCHAR(150) NOT NULL UNIQUE, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB');
    $upgradeDatabase->exec('CREATE TABLE role_permissions (role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (role_id, permission_id)) ENGINE=InnoDB');
    $upgradeDatabase->exec("INSERT INTO roles (name, slug, created_at, updated_at) VALUES ('Administrator', 'admin', NOW(), NOW())");
    $executeSql($upgradeDatabase, $upgrade);
    $assert((int) $upgradeDatabase->query("SELECT COUNT(*) FROM permissions WHERE slug = 'email.manage'")->fetchColumn() === 1, 'Existing-install upgrade did not create email.manage.');
    $assert((int) $upgradeDatabase->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='admin' AND p.slug='email.manage'")->fetchColumn() === 1, 'Existing-install upgrade did not grant email.manage.');
    $executeSql($upgradeDatabase, $upgrade);
    $assert((int) $upgradeDatabase->query("SELECT COUNT(*) FROM permissions WHERE slug = 'email.manage'")->fetchColumn() === 1, 'Repeated permission upgrade duplicated email.manage.');
    $assert((int) $upgradeDatabase->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='admin' AND p.slug='email.manage'")->fetchColumn() === 1, 'Repeated permission upgrade duplicated the Administrator grant.');
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$upgradeDatabaseName}`");
}

echo "WU3-S4 permission lifecycle passed ({$assertions} assertions)." . PHP_EOL;
