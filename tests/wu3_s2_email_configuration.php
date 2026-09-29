<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\EmailConfigurationResolver;
use Copot\Core\EmailCredentialBoundary;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;

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

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-s2-' . bin2hex(random_bytes(5));
mkdir($storage, 0700, true);
$environmentPath = $storage . DIRECTORY_SEPARATOR . '.env';

$settings = static function (array $overrides) use ($basePath): SettingsService {
    $repository = new class(new Database(new Config($basePath . DIRECTORY_SEPARATOR . 'config')), $overrides) extends SettingsRepository {
        public function __construct(Database $database, private array $overrides)
        {
            parent::__construct($database);
        }

        public function findOverride(string $namespace, string $key): ?array
        {
            $value = $this->overrides[$namespace . '.' . $key] ?? null;
            if ($value === null) {
                return null;
            }

            return [
                'namespace' => $namespace,
                'setting_key' => $key,
                'setting_value' => is_int($value) ? (string) $value : (string) $value,
                'value_type' => is_int($value) ? 'integer' : 'string',
            ];
        }
    };

    return new SettingsService(SettingsRegistry::core(), $repository);
};

try {
    $credentials = new EmailCredentialBoundary($environmentPath);
    $defaults = (new EmailConfigurationResolver($settings([]), $credentials))->resolve();
    $assert($defaults->state() === 'incomplete', 'Default Email state must be incomplete.');
    $assert($defaults->transport()->port() === 587, 'Default SMTP port is incorrect.');
    $assert($defaults->transport()->security() === 'tls', 'Default SMTP security mode is incorrect.');
    $assert($defaults->transport()->username() === null, 'Empty SMTP username must resolve as null.');
    $assert($defaults->credentialConfigured() === false, 'Missing credential must be not configured.');
    $assert(!array_key_exists('secret', $defaults->toReadModel()), 'Email read model must not contain a secret field.');

    $registry = SettingsRegistry::core();
    $assert($registry->find('email', 'smtp_host')?->type() === 'string', 'SMTP host definition is not typed as string.');
    $assert($registry->find('email', 'smtp_port')?->type() === 'integer', 'SMTP port definition is not typed as integer.');
    $assert($registry->find('email', 'smtp_security')?->allowedValues() === ['none', 'ssl', 'tls'], 'SMTP security modes are not bounded.');
    $assert($registry->find('email', 'smtp_username')?->isInternal() === true, 'SMTP username must not be exposed through generic Settings discovery.');

    $validValues = [
        'email.smtp_host' => 'smtp.example.test',
        'email.smtp_port' => 2525,
        'email.smtp_security' => 'ssl',
        'email.smtp_username' => 'mailer@example.test',
        'email.sender_email' => 'no-reply@example.test',
        'email.sender_name' => 'COPOT Mailer',
    ];
    $validSettings = $settings($validValues);
    foreach ($validValues as $identifier => $value) {
        [$namespace, $key] = explode('.', $identifier, 2);
        $assert($validSettings->get($namespace, $key) === $value, "Settings override did not resolve for {$identifier}.");
    }

    $credentials->replace('runtime-generated-test-credential-' . bin2hex(random_bytes(8)));
    $configured = (new EmailConfigurationResolver($validSettings, $credentials))->resolve();
    $readModel = $configured->toReadModel();
    $assert($configured->state() === 'configured', 'Valid complete Email configuration did not become configured.');
    $assert($configured->transport()->host() === 'smtp.example.test', 'SMTP host override did not resolve.');
    $assert($configured->transport()->port() === 2525, 'SMTP port override did not resolve.');
    $assert($configured->transport()->security() === 'ssl', 'SMTP security override did not resolve.');
    $assert($configured->sender()->email() === 'no-reply@example.test', 'Sender email override did not resolve.');
    $assert($configured->sender()->name() === 'COPOT Mailer', 'Sender name override did not resolve.');
    $assert($configured->credentialConfigured() === true, 'Configured credential state did not resolve.');
    $assert($readModel['credential'] === ['configured' => true], 'Credential read model was not redacted.');
    $assert(!str_contains(serialize($readModel), 'runtime-generated-test-credential'), 'Credential plaintext leaked into the read model.');

    $invalidCases = [
        'email.smtp_host' => ['bad host', 'Invalid SMTP host was accepted.'],
        'email.smtp_port' => [70000, 'Invalid SMTP port was accepted.'],
        'email.smtp_security' => ['starttls', 'Invalid SMTP security mode was accepted.'],
        'email.sender_email' => ['not-an-email', 'Invalid sender email was accepted.'],
    ];
    foreach ($invalidCases as $identifier => [$value, $message]) {
        [$namespace, $key] = explode('.', $identifier, 2);
        try {
            $validSettings->validate($namespace, $key, $value);
            throw new RuntimeException($message);
        } catch (\Copot\Core\SettingsException) {
            $assert(true, $message);
        }
    }

    $incomplete = (new EmailConfigurationResolver($settings([
        'email.smtp_host' => 'smtp.example.test',
        'email.sender_email' => 'no-reply@example.test',
        'email.sender_name' => 'COPOT Mailer',
    ]), new EmailCredentialBoundary($storage . DIRECTORY_SEPARATOR . 'missing.env')))->resolve();
    $assert($incomplete->state() === 'incomplete', 'Missing credential must keep Email configuration incomplete.');
    $assert($incomplete->toReadModel()['credential'] === ['configured' => false], 'Missing credential state is not deterministic.');

    $missingRequired = (new EmailConfigurationResolver($settings([
        'email.smtp_port' => 2525,
        'email.sender_email' => 'no-reply@example.test',
        'email.sender_name' => 'COPOT Mailer',
    ]), $credentials))->resolve();
    $assert($missingRequired->state() === 'incomplete', 'Missing SMTP host must remain non-fatal and incomplete.');

    echo "WU3-S2 email configuration passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($storage);
}
