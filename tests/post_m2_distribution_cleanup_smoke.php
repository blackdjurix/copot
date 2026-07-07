<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

use Copot\Core\Version;

$assertions = 0;
$failures = [];

$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(Version::CURRENT === '0.12.0', 'Framework version source must be 0.12.0 for the M2 final release candidate.');

$finalizerSource = (string) file_get_contents($basePath . '/app/Core/InstallerFinalizer.php');
$assert(str_contains($finalizerSource, 'Version::CURRENT'), 'InstallerFinalizer must consume Version::CURRENT.');
$assert(!str_contains($finalizerSource, "private const VERSION = '0.8.0'"), 'InstallerFinalizer must not keep the stale 0.8.0 literal.');

$envExample = (string) file_get_contents($basePath . '/.env.example');
$assert(str_contains($envExample, 'The installer writes only the DB_* keys'), '.env.example must state its installer relationship.');
$assert(str_contains($envExample, 'SESSION_SECURE=false'), '.env.example must document the local HTTP Secure-cookie default.');

$gitignore = (string) file_get_contents($basePath . '/.gitignore');
foreach (['.env', '/storage/logs/*', '/storage/site-assets/', '/storage/installed.lock', '/dist/'] as $rule) {
    $assert(str_contains($gitignore, $rule), '.gitignore must contain rule: ' . $rule);
}
$assert(!str_contains($gitignore, 'Laravel 4 specific'), '.gitignore must not keep stale Laravel-specific sections.');
$assert(!str_contains($gitignore, 'Homestead.yaml'), '.gitignore must not keep unrelated Homestead rules.');

$distributionDoc = (string) file_get_contents($basePath . '/docs/15_distribution_and_packaging.md');
foreach (['Source Repository vs Release Package', 'Package Include Policy', 'Package Exclude Policy', 'Clean-Install Verification'] as $heading) {
    $assert(str_contains($distributionDoc, $heading), 'Distribution contract must contain: ' . $heading);
}

$installDoc = (string) file_get_contents($basePath . '/INSTALL.md');
$assert(str_contains($installDoc, 'document root points to `public/`'), 'INSTALL.md must state the public document-root requirement.');
$assert(str_contains($installDoc, 'Never ship or publish a local `.env` file.'), 'INSTALL.md must reject distributing local .env state.');

if ($failures !== []) {
    fwrite(STDERR, "Post-M2 distribution cleanup smoke tests failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    exit(1);
}

echo 'Post-M2 distribution cleanup smoke tests passed (' . $assertions . ' assertions).' . PHP_EOL;
