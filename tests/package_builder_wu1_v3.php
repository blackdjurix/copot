<?php

declare(strict_types=1);

use Copot\Core\CanonicalTargetInventory;
use Copot\Core\CanonicalTargetInventoryEntry;
use Copot\Core\PackageContract;
use Copot\Core\PackageManifestReader;
use Copot\Core\PackageV3Identity;
use Copot\Core\ZipIntakeService;

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if (!extension_loaded('zip')) {
    echo "WU1 builder test skipped: ext-zip unavailable.\n";
    exit(0);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-builder-wu1-' . bin2hex(random_bytes(6));
mkdir($root, 0700, true);
$run = static function (string $output, ?int $version = null) use ($base): string {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($base . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'package.php');
    if ($version !== null) {
        $command .= ' --manifest-version=' . $version;
    }
    $command .= ' --output=' . escapeshellarg($output);
    $pipes = [];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $base);
    if (!is_resource($process)) {
        throw new RuntimeException('Builder process could not start.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== 0) {
        throw new RuntimeException("Builder failed: {$stderr}\n{$stdout}");
    }
    return $stdout;
};

$v3 = $root . DIRECTORY_SEPARATOR . 'v3.zip';
$v3Second = $root . DIRECTORY_SEPARATOR . 'v3-second.zip';
$v2 = $root . DIRECTORY_SEPARATOR . 'v2.zip';
$payload = null;
try {
    $firstOutput = $run($v3, 3);
    $secondOutput = $run($v3Second, 3);
    $assert(hash_file('sha256', $v3) === hash_file('sha256', $v3Second), 'V3 package output is not deterministic.');

    $staging = $root . DIRECTORY_SEPARATOR . 'staging';
    mkdir($staging, 0700, true);
    $payload = (new ZipIntakeService($base, $staging))->intake($v3);
    $manifest = (new PackageManifestReader())->read($payload);
    $contract = $manifest->contract();
    $assert($contract->manifestContractVersion() === PackageContract::V3_MANIFEST_CONTRACT_VERSION, 'Generated package is not V3.');
    $assert($contract->coverage() === 'full', 'Generated V3 package is not full coverage.');

    $zip = new ZipArchive();
    $assert($zip->open($v3) === true, 'Generated V3 ZIP could not be opened.');
    $raw = $zip->getFromName(PackageManifestReader::PATH);
    $zip->close();
    $assert(is_string($raw), 'Generated V3 manifest is missing.');
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    $assert(array_keys($data) === ['package_type', 'manifest_contract_version', 'target_webcore_version', 'release_identity', 'source_tree_identity', 'source_compatibility', 'runtime_compatibility', 'inventory', 'migration_declaration', 'target_requirements', 'coverage', 'target_inventory', 'source_state_requirements', 'removals', 'renames'], 'V3 manifest key ordering is incorrect.');
    $assert(count($data['inventory']) === count($data['target_inventory']['entries']), 'Target inventory is incomplete.');
    $assert($data['source_state_requirements']['allowed_inventory_fingerprints'] === [], 'Initial V3 source allowlist is not empty.');
    $assert($data['removals'] === [] && $data['renames'] === [], 'Initial V3 package declares destructive changes.');

    $targetEntries = array_map(static fn (array $entry): CanonicalTargetInventoryEntry => new CanonicalTargetInventoryEntry($entry['root'], $entry['ownership'], $entry['path'], $entry['byte_size'], $entry['sha256']), $data['target_inventory']['entries']);
    $targetInventory = new CanonicalTargetInventory($targetEntries);
    $assert(hash_equals($data['target_inventory']['fingerprint'], $targetInventory->fingerprint()), 'Target inventory fingerprint does not round-trip.');
    $paths = array_column($data['inventory'], 'path');
    foreach (['.env', '.copot/package.json', 'storage/.install.lock', 'storage/installed.lock', 'storage/.copot-lifecycle/committed-state.json', 'storage/.copot-lifecycle/installation-identity.json'] as $forbidden) {
        $assert(!in_array($forbidden, $paths, true), 'Runtime-owned path leaked into package inventory: ' . $forbidden);
    }
    $assert(!in_array('public/.htaccess', $paths, true) && in_array('routes/site_settings.php', $paths, true), 'Deployment-owned .htaccess leaked into package inventory or required policy path was omitted.');

    $manifestDigest = PackageV3Identity::manifestDigest($raw);
    $packageIdentity = PackageV3Identity::identity($manifestDigest, $contract->integrityIdentity());
    $assert(str_contains($firstOutput, 'Manifest SHA-256: ' . $manifestDigest), 'Manifest digest diagnostic is incorrect.');
    $assert(str_contains($firstOutput, 'Delivered payload integrity identity: ' . $contract->integrityIdentity()), 'Payload integrity diagnostic is incorrect.');
    $assert(str_contains($firstOutput, 'Package identity: ' . $packageIdentity), 'Package identity diagnostic is incorrect.');

    foreach (['../escape', '/absolute', 'C:/absolute', 'a//b', 'a/../b'] as $invalid) {
        try {
            new CanonicalTargetInventoryEntry(CanonicalTargetInventoryEntry::APP_ROOT, CanonicalTargetInventoryEntry::PACKAGE_OWNED, $invalid, 1, str_repeat('a', 64));
            throw new RuntimeException('Invalid target path was accepted: ' . $invalid);
        } catch (InvalidArgumentException) {
            $assertions++;
        }
    }

    $v2Output = $run($v2);
    $payload->cleanup();
    $payload = null;
    $v2Zip = new ZipArchive();
    $assert($v2Zip->open($v2) === true, 'V2 compatibility package could not be opened.');
    $v2Raw = $v2Zip->getFromName(PackageManifestReader::PATH);
    $v2Zip->close();
    $v2Data = json_decode((string) $v2Raw, true, 64, JSON_THROW_ON_ERROR);
    $assert($v2Data['manifest_contract_version'] === PackageContract::CURRENT_MANIFEST_CONTRACT_VERSION, 'Default builder behavior did not remain V2.');
    $assert(!array_key_exists('target_inventory', $v2Data), 'Default V2 builder emitted V3 fields.');
    $assert(str_contains($v2Output, 'Built ' . $v2), 'V2 output diagnostic changed unexpectedly.');
} finally {
    if ($payload !== null) {
        $payload->cleanup();
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_dir($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $remove($path . DIRECTORY_SEPARATOR . $entry);
                }
            }
            @rmdir($path);
        } else {
            @unlink($path);
        }
    };
    $remove($root);
}

echo 'WU1 builder V3 focused tests passed: ' . $assertions . " assertions.\n";
