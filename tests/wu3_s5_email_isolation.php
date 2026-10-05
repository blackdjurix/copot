<?php

declare(strict_types=1);

use Copot\Core\Auth;
use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\EmailConfigurationResolver;
use Copot\Core\EmailCredentialBoundary;
use Copot\Core\EmailMessage;
use Copot\Core\EmailSenderIdentity;
use Copot\Core\EmailTransport;
use Copot\Core\EmailTransportConfiguration;
use Copot\Core\InstallerGate;
use Copot\Core\InstallerRequirements;
use Copot\Core\InstallationState;
use Copot\Core\PasswordHasher;
use Copot\Core\PermissionChecker;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\Router;
use Copot\Core\Session;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SmtpTransportDriver;
use Copot\Core\User;
use Copot\Core\UserProvider;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

final class WU3EmailIsolationSettingsRepository extends SettingsRepository
{
    public function __construct(private array $overrides = [])
    {
    }

    public function findOverride(string $namespace, string $key): ?array
    {
        return $this->overrides[$namespace . '.' . $key] ?? null;
    }
}

final class WU3EmailIsolationPermissionChecker extends PermissionChecker
{
    public function __construct(private array $allowed)
    {
    }

    public function userCan(int $userId, string $permission): bool
    {
        return in_array($permission, $this->allowed, true);
    }
}

final class WU3EmailIsolationUserProvider extends UserProvider
{
    public function __construct(private User $user)
    {
    }

    public function findByEmail(string $email): ?User
    {
        return strtolower(trim($email)) === strtolower($this->user->email()) ? $this->user : null;
    }

    public function updateLastLogin(int $id): void
    {
    }
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $candidate = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($candidate) && !is_link($candidate)) {
            $removeDirectory($candidate);
        } else {
            @unlink($candidate);
        }
    }

    @rmdir($path);
};

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-s5-email-isolation-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
mkdir($root . DIRECTORY_SEPARATOR . 'config', 0700, true);
mkdir($storage . DIRECTORY_SEPARATOR . 'site-assets', 0700, true);
$databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'copot_wu3_s5_isolation',
            'username' => 'fixture',
            'password' => 'fixture',
            'charset' => 'utf8mb4',
        ],
    ],
];
PHP;
file_put_contents($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php', $databaseConfig);
$environmentPath = $root . DIRECTORY_SEPARATOR . '.env';
$sessionStarted = false;

$settingsFor = static function (array $values) use ($basePath): SettingsService {
    $overrides = [];
    foreach ($values as $identifier => $value) {
        [$namespace, $key] = explode('.', $identifier, 2);
        $overrides[$identifier] = [
            'namespace' => $namespace,
            'setting_key' => $key,
            'setting_value' => (string) $value,
            'value_type' => is_int($value) ? 'integer' : 'string',
        ];
    }

    return new SettingsService(
        SettingsRegistry::core(),
        new WU3EmailIsolationSettingsRepository($overrides)
    );
};

$emailValues = [
    'email.smtp_host' => 'smtp.example.test',
    'email.smtp_port' => 2525,
    'email.smtp_security' => 'tls',
    'email.smtp_username' => 'mailer@example.test',
    'email.sender_email' => 'no-reply@example.test',
    'email.sender_name' => 'COPOT Test Mailer',
];

$makeTransport = static function (SettingsService $settings, EmailCredentialBoundary $credentials, SmtpTransportDriver $driver): EmailTransport {
    return new EmailTransport(
        new EmailConfigurationResolver($settings, $credentials),
        $credentials,
        $driver
    );
};

try {
    $emptyState = new InstallationState($storage);
    $installerGate = new InstallerGate($emptyState);
    $assert(
        $installerGate->decide(new Request('GET', '/install')) === InstallerGate::INSTALLER,
        'Missing Email configuration must not prevent the installer boundary from being available.'
    );

    $application = new Application($root);
    $assert($application instanceof Application, 'Application bootstrap construction was blocked by absent Email configuration.');

    $requirements = (new InstallerRequirements($root))->check(true);
    $requirementNames = array_column($requirements, 'name');
    $assert(in_array('session', $requirementNames, true), 'Installer requirements did not execute with Email absent.');
    $assert(in_array('json', $requirementNames, true), 'Installer requirements lost JSON availability with Email absent.');
    $assert(in_array('filter', $requirementNames, true), 'Installer requirements lost Filter availability with Email absent.');

    $emptySettings = $settingsFor([]);
    $missingCredentials = new EmailCredentialBoundary($environmentPath);
    $missingConfigurationTransport = $makeTransport(
        $emptySettings,
        $missingCredentials,
        new class implements SmtpTransportDriver {
            public function deliver(EmailMessage $message, EmailSenderIdentity $sender, EmailTransportConfiguration $transport, string $credential): void
            {
                throw new RuntimeException('The driver must not run for absent Email configuration.');
            }
        }
    );
    $missingConfigurationResult = $missingConfigurationTransport->deliver(
        new EmailMessage('recipient@example.test', 'Isolation test', 'Email is not configured.')
    );
    $assert($missingConfigurationResult->code() === 'configuration_incomplete', 'Absent Email configuration did not fail locally and deterministically.');

    $missingCredentialTransport = $makeTransport(
        $settingsFor($emailValues),
        $missingCredentials,
        new class implements SmtpTransportDriver {
            public function deliver(EmailMessage $message, EmailSenderIdentity $sender, EmailTransportConfiguration $transport, string $credential): void
            {
                throw new RuntimeException('The driver must not run without an Email credential.');
            }
        }
    );
    $missingCredentialResult = $missingCredentialTransport->deliver(
        new EmailMessage('recipient@example.test', 'Isolation test', 'Email credential is unavailable.')
    );
    $assert($missingCredentialResult->code() === 'credential_unavailable', 'Unavailable Email credential did not fail locally and deterministically.');
    $assert((new Application($root)) instanceof Application, 'Application bootstrap construction was blocked by an unavailable Email credential.');

    $failingCredentialPath = $root . DIRECTORY_SEPARATOR . 'configured.env';
    file_put_contents($failingCredentialPath, "COPOT_EMAIL_CREDENTIAL=\"test-only-fake-credential\"\r\n");
    $failingCredentials = new EmailCredentialBoundary($failingCredentialPath);
    $driverCalls = 0;
    $failingTransport = $makeTransport(
        $settingsFor($emailValues),
        $failingCredentials,
        new class($driverCalls) implements SmtpTransportDriver {
            public function __construct(public int &$calls)
            {
            }

            public function deliver(EmailMessage $message, EmailSenderIdentity $sender, EmailTransportConfiguration $transport, string $credential): void
            {
                $this->calls++;
                throw new RuntimeException('Deterministic transport failure.');
            }
        }
    );
    $failure = $failingTransport->deliver(new EmailMessage('recipient@example.test', 'Isolation test', 'Transport fails.'));
    $assert($failure->code() === 'transport_failed', 'Deterministic transport failure was not converted to a bounded result.');
    $assert($driverCalls === 1, 'Deterministic failing transport was not exercised exactly once.');
    $assert((string) file_get_contents($failingCredentialPath) === "COPOT_EMAIL_CREDENTIAL=\"test-only-fake-credential\"\r\n", 'Transport failure mutated credential state.');

    $installedState = new InstallationState($storage);
    $installedState->createMarker('0.13.0');
    $assert(
        (new InstallerGate($installedState))->decide(new Request('GET', '/')) === InstallerGate::NORMAL_APPLICATION,
        'Zero-optional normal application routing remained unavailable after Email transport failure.'
    );

    $config = new Config($basePath . DIRECTORY_SEPARATOR . 'config');
    session_save_path(sys_get_temp_dir());
    session_id('copotwu3s5' . bin2hex(random_bytes(5)));
    $session = new Session($config);
    $session->start();
    $sessionStarted = true;
    $passwords = new PasswordHasher();
    $user = new User([
        'id' => 7001,
        'name' => 'Isolation User',
        'email' => 'isolation@example.test',
        'password_hash' => $passwords->make('isolation-password'),
        'status' => 'active',
    ], new WU3EmailIsolationPermissionChecker(['admin.access']));
    $auth = new Auth(
        $config,
        $session,
        new WU3EmailIsolationUserProvider($user),
        $passwords
    );
    $assert($auth->attempt('isolation@example.test', 'isolation-password'), 'Baseline login failed while Email was isolated and unavailable.');
    $assert($auth->check(), 'Authenticated state was not established while Email was unavailable.');

    $router = new Router();
    $router->get('/zero-optional-health', static fn (): Response => Response::html('healthy'));
    $health = $router->dispatch(new Request('GET', '/zero-optional-health'));
    $assert($health->statusCode() === 200 && $health->body() === 'healthy', 'Global application route was unavailable after Email failure.');

    echo "WU3-S5 Email isolation acceptance passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($sessionStarted && session_status() === PHP_SESSION_ACTIVE) {
        $session->destroy();
    }
    $removeDirectory($root);
}
