<?php

declare(strict_types=1);

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\PackageRemovalSet;
use Copot\Core\PackageV3Identity;
use Copot\Core\StrictJsonDuplicateKeyGuard;
use Copot\Core\StagedFile;
use Copot\Core\StagedPayload;
use Copot\Core\StagingSession;
use Copot\Core\PackageManifestReader;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void { $assertions++; if (!$condition) { throw new RuntimeException($message); } };
$throws = static function (callable $callback, string $message) use (&$assertions): void { $assertions++; try { $callback(); } catch (Throwable) { return; } throw new RuntimeException($message . ' did not throw.'); };

$assert(hash('sha256', CanonicalTargetInventory::serializeEntries([])) === '77171ef0d605ac0f272046e606b204d74c330842b8113b7246f46fa2de46030b', 'Empty target inventory vector changed.');
$entry = new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', 'app/a.txt', 3, hash('sha256', 'abc'));
$assert((new CanonicalTargetInventory([$entry]))->fingerprint() === '5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3', 'Single target inventory vector changed.');
$actions = [['kind' => 1, 'reason' => 1, 'source_root' => 'APP_ROOT', 'source_path' => 'app/old.txt', 'target_root' => '', 'target_path' => '', 'expected_source_byte_size' => 3, 'expected_source_sha256' => hash('sha256', 'abc')]];
$assert(PackageRemovalSet::digest('plan-v1:example', $actions) === '619db7c1495f73674fb81f4603b4e486efc2042ded6a26dfe6f3b1272003272d', 'Removal-set vector changed.');
$throws(static function (): void { StrictJsonDuplicateKeyGuard::validate('{"a":1,"a":2}'); }, 'Top-level duplicate key');
$throws(static function (): void { StrictJsonDuplicateKeyGuard::validate('{"a":{"b":1,"b":2}}'); }, 'Nested duplicate key');
$throws(static function (): void { new CanonicalTargetInventoryEntry('APP_ROOT', 'package-owned', '../escape', 1, str_repeat('a', 64)); }, 'Unsafe target path');
$throws(static function (): void { new CanonicalTargetInventoryEntry('APP_ROOT', 'package_owned', 'app/a', 1, str_repeat('a', 64)); }, 'Noncanonical target ownership');
$throws(static function (): void { PackageRemovalSet::serialize('plan-v1:example', [['kind' => 1, 'reason' => 1, 'source_root' => 'APP_ROOT', 'source_path' => 'app/a', 'target_root' => '', 'target_path' => '', 'expected_source_byte_size' => -1, 'expected_source_sha256' => str_repeat('a', 64)]]); }, 'Negative removal integer');
$throws(static function (): void { PackageV3Identity::assertIdentity(str_repeat('a', 64)); }, 'Bare V3 identity');
$assert(PackageV3Identity::identity(str_repeat('0', 64), str_repeat('1', 64)) === 'v3-sha256:' . PackageV3Identity::digest(str_repeat('0', 64), str_repeat('1', 64)), 'V3 identity scheme binding changed.');

$live = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-live-' . bin2hex(random_bytes(4));
$stagingRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-v3-stage-' . bin2hex(random_bytes(4));
mkdir($live, 0700, true); mkdir($stagingRoot, 0700, true);
$session = StagingSession::create($live, $stagingRoot);
mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . 'app', 0700, true);
$payloadBytes = 'abc'; file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'a.txt', $payloadBytes);
$manifest = [
    'package_type' => 'copot-webcore', 'manifest_contract_version' => 3, 'target_webcore_version' => '1.0.0', 'release_identity' => 'release-1', 'source_tree_identity' => null,
    'source_compatibility' => ['minimum_source_version' => '1.0.0', 'maximum_source_version' => null],
    'runtime_compatibility' => ['minimum_php_version' => '8.2.0', 'minimum_database_versions' => ['mysql' => '8.0.0'], 'required_extensions' => ['json']],
    'inventory' => [['path' => 'app/a.txt', 'byte_size' => 3, 'sha256' => hash('sha256', $payloadBytes), 'ownership' => 'package_owned']],
    'migration_declaration' => ['declares_core_migrations' => false, 'declaration_identity' => null], 'target_requirements' => [], 'coverage' => 'full',
    'target_inventory' => ['serialization_version' => 1, 'fingerprint' => '5efefeef395b4c3eb55989b08995504edb78b7c35114d3c0cd31c43084f00aa3', 'entries' => [['root' => 'APP_ROOT', 'ownership' => 'package-owned', 'path' => 'app/a.txt', 'byte_size' => 3, 'sha256' => hash('sha256', $payloadBytes)]]],
    'source_state_requirements' => ['allowed_inventory_fingerprints' => []], 'removals' => [], 'renames' => [],
];
$raw = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
mkdir($session->payloadPath() . DIRECTORY_SEPARATOR . '.copot', 0700, true);
file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json', $raw);
$files = [new StagedFile('.copot/package.json', strlen($raw), hash('sha256', $raw)), new StagedFile('app/a.txt', 3, hash('sha256', $payloadBytes))];
$read = (new PackageManifestReader())->read(new StagedPayload($session, str_repeat('0', 64), $files));
$assert($read->contract()->manifestContractVersion() === 3 && $read->packageEvidenceIdentity() === PackageV3Identity::identity($read->rawManifestSha256(), $read->contract()->integrityIdentity()), 'Valid V3 manifest was not accepted or bound.');
$protectedCases = [
    'HTA-N02 removal' => ['removals' => [['root' => 'PUBLIC_ROOT', 'path' => '.htaccess', 'ownership' => 'package-owned', 'expected_source_byte_size' => 7, 'expected_source_sha256' => hash('sha256', 'routing'), 'reason' => 'obsolete']], 'renames' => []],
    'HTA-N03 rename source' => ['removals' => [], 'renames' => [['source_root' => 'PUBLIC_ROOT', 'source_path' => '.htaccess', 'target_root' => 'APP_ROOT', 'target_path' => 'app/a.txt']]],
    'HTA-N04 rename destination' => ['removals' => [], 'renames' => [['source_root' => 'APP_ROOT', 'source_path' => 'app/a.txt', 'target_root' => 'PUBLIC_ROOT', 'target_path' => '.htaccess']]],
];
foreach ($protectedCases as $label => $case) {
    $negativeManifest = [...$manifest, 'removals' => $case['removals'], 'renames' => $case['renames']];
    $negativeRaw = json_encode($negativeManifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json', $negativeRaw);
    $negativeFiles = [new StagedFile('.copot/package.json', strlen($negativeRaw), hash('sha256', $negativeRaw)), ...array_slice($files, 1)];
    $throws(static function () use ($session, $negativeFiles): void { (new PackageManifestReader())->read(new StagedPayload($session, str_repeat('0', 64), $negativeFiles)); }, $label . ' parser admission');
}
$duplicateRaw = str_replace('"release_identity":"release-1"', '"release_identity":"release-1","release_identity":"release-1"', $raw);
file_put_contents($session->payloadPath() . DIRECTORY_SEPARATOR . '.copot' . DIRECTORY_SEPARATOR . 'package.json', $duplicateRaw);
$duplicateFiles = [new StagedFile('.copot/package.json', strlen($duplicateRaw), hash('sha256', $duplicateRaw)), ...array_slice($files, 1)];
$throws(static function () use ($session, $duplicateFiles): void { (new PackageManifestReader())->read(new StagedPayload($session, str_repeat('0', 64), $duplicateFiles)); }, 'V3 reader duplicate key');
$session->cleanup(); @rmdir($stagingRoot); @rmdir($live);

echo "Manifest V3 WU1 focused tests passed ({$assertions} assertions)." . PHP_EOL;
