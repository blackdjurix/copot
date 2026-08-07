<?php

declare(strict_types=1);

use Copot\Core\CanonicalSchemaBaselineCatalog;
use Copot\Core\CanonicalSchemaBaselineDescriptor;
use Copot\Core\CanonicalSchemaBaselineVerifier;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\ExistingInstallEvidence;
use Copot\Core\InstallationState;
use Copot\Core\InstalledStateInspector;
use Copot\Core\LegacyClassification;
use Copot\Core\LegacyRuntimeClassifier;
use Copot\Core\InstallerSchemaRunner;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$host = getenv('COPOT_BASELINE_TEST_HOST');
$port = getenv('COPOT_BASELINE_TEST_PORT');
$username = getenv('COPOT_BASELINE_TEST_USERNAME');
$password = getenv('COPOT_BASELINE_TEST_PASSWORD');
if (!is_string($host) || !is_string($port) || !is_string($username) || !is_string($password) || !ctype_digit($port)) {
    throw new RuntimeException('Historical baseline test requires an isolated disposable MariaDB connection.');
}

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$names = [];
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-historical-baseline-' . bin2hex(random_bytes(6));
mkdir($root . DIRECTORY_SEPARATOR . 'storage', 0700, true);
$catalog = CanonicalSchemaBaselineCatalog::forProject($base);
$verifier = new CanonicalSchemaBaselineVerifier();
$classifier = new LegacyRuntimeClassifier($verifier, $catalog);
$registry = new CoreMigrationRegistry('historical-baseline-test', []);

$createDatabase = static function (string $prefix) use ($server, &$names): PDO {
    $name = $prefix . bin2hex(random_bytes(6));
    $names[] = $name;
    $server->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    return new PDO("mysql:host=" . getenv('COPOT_BASELINE_TEST_HOST') . ";port=" . getenv('COPOT_BASELINE_TEST_PORT') . ";dbname={$name};charset=utf8mb4", getenv('COPOT_BASELINE_TEST_USERNAME'), getenv('COPOT_BASELINE_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
};

$install = static function (PDO $connection, string $schemaPath): void {
    $runner = new InstallerSchemaRunner($schemaPath);
    $runner->install([
        'host' => (string) getenv('COPOT_BASELINE_TEST_HOST'),
        'port' => (int) getenv('COPOT_BASELINE_TEST_PORT'),
        'database' => (string) $connection->query('SELECT DATABASE()')->fetchColumn(),
        'username' => (string) getenv('COPOT_BASELINE_TEST_USERNAME'),
        'password' => (string) getenv('COPOT_BASELINE_TEST_PASSWORD'),
    ]);
};

$classify = static function (PDO $connection, string $markerVersion) use ($root, $classifier, $registry): \Copot\Core\LegacyClassificationResult {
    $storage = $root . DIRECTORY_SEPARATOR . 'storage';
    $state = new InstallationState($storage);
    if ($state->readMarker() === null) {
        $state->createMarker($markerVersion);
    } else {
        $state->replaceMarker($markerVersion, gmdate(DATE_ATOM));
    }
    $installed = (new InstalledStateInspector())->inspect($state, new ExistingInstallEvidence(true, true));
    return $classifier->classify($installed, $connection, dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql', $registry);
};

try {
    $historical = $createDatabase('copot_baseline_');
    $install($historical, $base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baselines' . DIRECTORY_SEPARATOR . 'webcore-0.8.0.sql');
    $result = $classify($historical, '0.8.0');
    $assert($result->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE, 'Exact v0.8.0 baseline was not accepted.');
    $assert($result->sourceWebcoreVersion() === '0.8.0', 'Historical baseline version was inferred incorrectly.');
    $assert($result->sourceSchemaIdentity() === 'canonical-schema:86431406ec45bcce7f44dd34c4cb146c9b3566868452244db71e0208c007d0f3', 'Historical baseline identity was not immutable.');

    $invalidCatalog = new CanonicalSchemaBaselineCatalog([new CanonicalSchemaBaselineDescriptor('invalid', '0.8.0', $base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baselines' . DIRECTORY_SEPARATOR . 'webcore-0.8.0.sql', str_repeat('0', 64), false)]);
    $assert($invalidCatalog->verify($historical, $verifier) === null, 'Invalid baseline identity evidence was accepted.');

    $historical->exec('ALTER TABLE users ADD COLUMN unexpected TEXT');
    $assert($classify($historical, '0.8.0')->classification() === LegacyClassification::UNKNOWN_OR_UNPROVABLE, 'Altered v0.8.0 baseline was accepted.');
    $historical = null;

    $extra = $createDatabase('copot_baseline_');
    $install($extra, $base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'baselines' . DIRECTORY_SEPARATOR . 'webcore-0.8.0.sql');
    $extra->exec('CREATE TABLE extra_baseline_table (id INT NOT NULL) ENGINE=InnoDB');
    $assert($classify($extra, '0.8.0')->classification() === LegacyClassification::UNKNOWN_OR_UNPROVABLE, 'Extra-table v0.8.0 baseline was accepted.');
    $extra = null;

    $current = $createDatabase('copot_baseline_');
    $install($current, $base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
    $currentResult = $classify($current, '0.13.0');
    $assert($currentResult->classification() === LegacyClassification::CANONICAL_SCHEMA_BASELINE, 'Current canonical schema behavior regressed.');
    $assert($currentResult->sourceWebcoreVersion() === '0.13.0', 'Current canonical schema version changed.');

    echo "Historical canonical baseline: {$assertions} assertions passed\n";
} finally {
    foreach ($names as $name) {
        try { $server->exec("DROP DATABASE IF EXISTS `{$name}`"); } catch (Throwable) {}
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) { if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); } }
        @rmdir($path);
    };
    $remove($root);
}
