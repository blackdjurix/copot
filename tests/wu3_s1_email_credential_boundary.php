<?php

declare(strict_types=1);

use Copot\Core\EmailCredentialBoundary;
use Copot\Core\EmailCredentialException;

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

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-s1-' . bin2hex(random_bytes(5));
mkdir($storage, 0700, true);
$path = $storage . DIRECTORY_SEPARATOR . '.env';
$secret = 'initial-' . bin2hex(random_bytes(8));
$replacement = 'replacement-' . bin2hex(random_bytes(8));

try {
    file_put_contents($path, "APP_ENV=testing\r\nCUSTOM_KEY=preserve-me\r\n");
    $boundary = new EmailCredentialBoundary($path);
    $assert($boundary->state() === ['configured' => false], 'Missing credential must be not configured.');

    $boundary->replace($secret);
    $contents = (string) file_get_contents($path);
    $assert(str_contains($contents, "APP_ENV=testing\r\nCUSTOM_KEY=preserve-me\r\n"), 'Unrelated environment formatting and values must remain intact.');
    $assert(substr_count($contents, 'COPOT_EMAIL_CREDENTIAL=') === 1, 'Initial write must create one credential entry.');
    $assert($boundary->state() === ['configured' => true], 'Successful write must report configured state.');
    $assert($boundary->state()['configured'] !== $secret, 'Public state must not expose the plaintext secret.');

    $boundary->replace($replacement);
    $replaced = (string) file_get_contents($path);
    $assert(substr_count($replaced, 'COPOT_EMAIL_CREDENTIAL=') === 1, 'Replacement must keep one credential entry.');
    $assert(str_contains($replaced, 'COPOT_EMAIL_CREDENTIAL="' . $replacement . '"'), 'Replacement must persist the new credential.');
    $assert(!str_contains($replaced, $secret), 'Replacement must remove the old credential value.');
    $assert(str_contains($replaced, 'CUSTOM_KEY=preserve-me'), 'Replacement must preserve unrelated keys.');

    $beforeFailure = $replaced;
    $failing = new EmailCredentialBoundary($path, static fn(string $temporary, string $target): bool => false);
    try {
        $failedSecret = 'failed-' . bin2hex(random_bytes(8));
        $failing->replace($failedSecret);
        throw new RuntimeException('Injected replacement failure unexpectedly succeeded.');
    } catch (EmailCredentialException $exception) {
        $assert($exception->getMessage() === 'Email credential could not be saved.', 'Failure must use a sanitized error.');
        $assert(!str_contains($exception->getMessage(), $failedSecret), 'Failure must not expose secret material.');
    }
    $assert((string) file_get_contents($path) === $beforeFailure, 'Pre-commit failure must preserve the original valid environment.');
    $assert(!str_contains((string) file_get_contents($path), $failedSecret), 'Failed secret must not become active state.');
    $assert(count(glob($storage . DIRECTORY_SEPARATOR . '.copot-email-credential-*') ?: []) === 0, 'Temporary credential artifacts must be cleaned up.');

    $invalid = new EmailCredentialBoundary($storage . DIRECTORY_SEPARATOR . 'missing.env');
    try {
        $invalidSecret = 'invalid-' . bin2hex(random_bytes(8));
        $invalid->replace($invalidSecret . "\nsecret");
        throw new RuntimeException('Invalid credential unexpectedly succeeded.');
    } catch (EmailCredentialException $exception) {
        $assert(!str_contains($exception->getMessage(), $invalidSecret), 'Validation errors must not expose secret material.');
    }
} finally {
    foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($storage);
}

echo "WU3-S1 email credential boundary passed ({$assertions} assertions)." . PHP_EOL;
