<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\EmailConfigurationResolver;
use Copot\Core\EmailCredentialBoundary;
use Copot\Core\EmailDeliveryResult;
use Copot\Core\EmailMessage;
use Copot\Core\EmailMessageException;
use Copot\Core\EmailSenderIdentity;
use Copot\Core\EmailTransport;
use Copot\Core\EmailTransportConfiguration;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\SmtpTransportDriver;

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

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-s3-' . bin2hex(random_bytes(5));
mkdir($storage, 0700, true);
$environmentPath = $storage . DIRECTORY_SEPARATOR . '.env';
$credential = 'transport-runtime-' . bin2hex(random_bytes(8));

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
                'setting_value' => (string) $value,
                'value_type' => is_int($value) ? 'integer' : 'string',
            ];
        }
    };

    return new SettingsService(SettingsRegistry::core(), $repository);
};

$values = [
    'email.smtp_host' => 'smtp.example.test',
    'email.smtp_port' => 2525,
    'email.smtp_security' => 'tls',
    'email.smtp_username' => 'mailer@example.test',
    'email.sender_email' => 'no-reply@example.test',
    'email.sender_name' => 'COPOT Mailer',
];
$message = new EmailMessage('recipient@example.test', 'Test subject', "Hello\nWorld");

try {
    $credentials = new EmailCredentialBoundary($environmentPath);
    $credentials->replace($credential);
    $configuration = new EmailConfigurationResolver($settings($values), $credentials);

    $driver = new class implements SmtpTransportDriver {
        public array $calls = [];
        public bool $fail = false;

        public function deliver(
            EmailMessage $message,
            EmailSenderIdentity $sender,
            EmailTransportConfiguration $transport,
            string $credential
        ): void {
            $this->calls[] = [
                'recipient' => $message->recipient(),
                'subject' => $message->subject(),
                'body' => $message->body(),
                'sender_email' => $sender->email(),
                'sender_name' => $sender->name(),
                'host' => $transport->host(),
                'port' => $transport->port(),
                'security' => $transport->security(),
                'username' => $transport->username(),
                'credential' => $credential,
            ];

            if ($this->fail) {
                throw new RuntimeException('low-level protocol failure: ' . $credential);
            }
        }
    };

    $transport = new EmailTransport($configuration, $credentials, $driver);
    $success = $transport->deliver($message);
    $assert($success->isSuccessful(), 'Deterministic SMTP driver did not return success.');
    $assert($success->toArray() === ['status' => 'succeeded', 'code' => 'delivered'], 'Success result shape is incorrect.');
    $assert($driver->calls[0]['recipient'] === 'recipient@example.test', 'Recipient was not propagated.');
    $assert($driver->calls[0]['subject'] === 'Test subject', 'Subject was not propagated.');
    $assert($driver->calls[0]['body'] === "Hello\nWorld", 'Body was not propagated.');
    $assert($driver->calls[0]['sender_email'] === 'no-reply@example.test', 'Sender email was not propagated.');
    $assert($driver->calls[0]['sender_name'] === 'COPOT Mailer', 'Sender name was not propagated.');
    $assert($driver->calls[0]['credential'] === $credential, 'Credential was not consumed through the runtime boundary.');
    $assert(!str_contains(serialize($success->toArray()), $credential), 'Success result exposed credential plaintext.');

    foreach (['none', 'ssl', 'tls'] as $security) {
        $modeValues = $values;
        $modeValues['email.smtp_security'] = $security;
        $modeTransport = new EmailTransport(new EmailConfigurationResolver($settings($modeValues), $credentials), $credentials, $driver);
        $modeResult = $modeTransport->deliver($message);
        $assert($modeResult->isSuccessful(), "Supported security mode [{$security}] did not succeed through the deterministic driver.");
        $assert($driver->calls[array_key_last($driver->calls)]['security'] === $security, "Security mode [{$security}] was not propagated.");
    }

    $driver->fail = true;
    $failure = $transport->deliver($message);
    $assert($failure->status() === EmailDeliveryResult::FAILED, 'Driver failure did not produce failed status.');
    $assert($failure->code() === 'transport_failed', 'Driver failure did not produce sanitized transport code.');
    $assert($failure->toArray() === ['status' => 'failed', 'code' => 'transport_failed'], 'Failure result shape is incorrect.');
    $assert(!str_contains(serialize($failure->toArray()), $credential), 'Failure result exposed credential plaintext.');

    $missingCredentialPath = $storage . DIRECTORY_SEPARATOR . 'missing.env';
    $missingDriver = new class implements SmtpTransportDriver {
        public int $calls = 0;
        public function deliver(EmailMessage $message, EmailSenderIdentity $sender, EmailTransportConfiguration $transport, string $credential): void
        {
            $this->calls++;
        }
    };
    $missing = new EmailTransport(
        new EmailConfigurationResolver($settings($values), new EmailCredentialBoundary($missingCredentialPath)),
        new EmailCredentialBoundary($missingCredentialPath),
        $missingDriver
    );
    $missingResult = $missing->deliver($message);
    $assert($missingResult->code() === 'credential_unavailable', 'Missing credential did not produce controlled failure.');
    $assert($missingDriver->calls === 0, 'Missing credential invoked the SMTP driver.');

    $incomplete = new EmailTransport(
        new EmailConfigurationResolver($settings(['email.sender_email' => 'no-reply@example.test']), $credentials),
        $credentials,
        $missingDriver
    );
    $incompleteResult = $incomplete->deliver($message);
    $assert($incompleteResult->code() === 'configuration_incomplete', 'Incomplete configuration did not produce controlled failure.');

    try {
        new EmailMessage("bad\nrecipient@example.test", 'subject', 'body');
        throw new RuntimeException('Invalid recipient was accepted.');
    } catch (EmailMessageException $exception) {
        $assert(!str_contains($exception->getMessage(), 'bad'), 'Invalid message error exposed input material.');
    }

    echo "WU3-S3 email transport passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($storage);
}
