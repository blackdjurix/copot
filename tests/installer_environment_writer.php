<?php

declare(strict_types=1);

use Copot\Core\InstallerEnvironmentWriter;

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

$configuration = static function (string $namespace): array {
    return [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'copot_test',
        'username' => 'root',
        'password' => '',
        'namespace' => $namespace,
    ];
};

$namespaceLines = static function (string $contents): array {
    return preg_grep('/^\s*DB_NAMESPACE=/', preg_split('/\r\n|\n|\r/', $contents) ?: []) ?: [];
};

$storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-installer-environment-' . bin2hex(random_bytes(5));
mkdir($storage, 0700, true);

try {
    $firstPath = $storage . DIRECTORY_SEPARATOR . 'first.env';
    $writer = new InstallerEnvironmentWriter($firstPath);

    $writer->persist($configuration('alpha'));
    $firstContents = (string) file_get_contents($firstPath);
    $assert(count($namespaceLines($firstContents)) === 1, 'First persistence must write exactly one DB_NAMESPACE entry.');
    $assert(str_contains($firstContents, 'DB_NAMESPACE="alpha"'), 'First persistence must write the requested namespace.');

    $writer->persist($configuration('alpha'));
    $repeatedContents = (string) file_get_contents($firstPath);
    $assert(count($namespaceLines($repeatedContents)) === 1, 'Repeated persistence must keep exactly one DB_NAMESPACE entry.');
    $assert(substr_count($repeatedContents, 'DB_NAMESPACE="alpha"') === 1, 'Repeated persistence must not duplicate the namespace value.');

    $writer->persist($configuration('beta'));
    $changedContents = (string) file_get_contents($firstPath);
    $assert(count($namespaceLines($changedContents)) === 1, 'Changing namespace must keep exactly one DB_NAMESPACE entry.');
    $assert(str_contains($changedContents, 'DB_NAMESPACE="beta"'), 'Changing namespace must write the new namespace.');
    $assert(!str_contains($changedContents, 'DB_NAMESPACE="alpha"'), 'Changing namespace must remove the old namespace value.');

    $existingPath = $storage . DIRECTORY_SEPARATOR . 'existing.env';
    file_put_contents($existingPath, implode(PHP_EOL, [
        'APP_ENV="testing"',
        'DB_HOST="old-host"',
        ' DB_NAMESPACE="old"',
        'DB_NAMESPACE="stale"',
        'DB_DATABASE="old-database"',
        'CUSTOM_KEY="preserve-me"',
        '',
    ]));
    $existingWriter = new InstallerEnvironmentWriter($existingPath);
    $existingWriter->persist($configuration('gamma'));
    $existingContents = (string) file_get_contents($existingPath);

    $assert(count($namespaceLines($existingContents)) === 1, 'Existing duplicate DB_NAMESPACE entries must collapse to one entry.');
    $assert(str_contains($existingContents, 'DB_NAMESPACE="gamma"'), 'Existing DB_NAMESPACE must be replaced with the new value.');
    $assert(!str_contains($existingContents, 'DB_NAMESPACE="old"'), 'The old DB_NAMESPACE value must not remain active.');
    $assert(str_contains($existingContents, 'DB_HOST="127.0.0.1"'), 'Existing DB_HOST persistence must continue to merge.');
    $assert(str_contains($existingContents, 'DB_PORT="3306"'), 'Existing DB_PORT persistence must continue to merge.');
    $assert(str_contains($existingContents, 'DB_DATABASE="copot_test"'), 'Existing DB_DATABASE persistence must continue to merge.');
    $assert(str_contains($existingContents, 'DB_USERNAME="root"'), 'Existing DB_USERNAME persistence must continue to merge.');
    $assert(str_contains($existingContents, 'DB_PASSWORD=""'), 'Existing DB_PASSWORD persistence must continue to merge.');
    $assert(str_contains($existingContents, 'CUSTOM_KEY="preserve-me"'), 'Unrelated existing environment keys must remain intact.');
} finally {
    foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
        @unlink($path);
    }
    @rmdir($storage);
}

echo "Installer environment writer regression passed ({$assertions} assertions)." . PHP_EOL;
