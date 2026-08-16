<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\BackupRecovery\UnavailableDatabaseQuiescence;
use Copot\Core\LegacyReconciliationOperator;
use Copot\Core\PackageLifecycleFactory;
use Copot\Core\PackageLifecycleService;

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
$property = static function (object $object, string $name): mixed {
    return (new ReflectionProperty($object, $name))->getValue($object);
};
$setRecoveryRoot = static function (?string $root): void {
    if ($root === null) {
        putenv('COPOT_RECOVERY_ROOT');
        unset($_ENV['COPOT_RECOVERY_ROOT']);
        return;
    }
    putenv('COPOT_RECOVERY_ROOT=' . $root);
    $_ENV['COPOT_RECOVERY_ROOT'] = $root;
};

$factorySource = file_get_contents($base . '/app/Core/PackageLifecycleFactory.php');
$assert(is_string($factorySource), 'Package lifecycle factory source could not be read.');
$normalPosition = strpos($factorySource, '$normalRecoveryCapture = $recoveryComposition?->normalCaptureService();');
$applyPosition = strpos($factorySource, 'new WebcoreApplyCoordinator(');
$assert($normalPosition !== false && $applyPosition !== false && $normalPosition < $applyPosition, 'Normal recovery capture service is not composed before the apply coordinator.');
$assert(str_contains($factorySource, 'return new NormalWebcoreRecoveryCaptureService('), 'Normal lifecycle does not obtain the production capture service from the shared composition.');
$assert(str_contains($factorySource, 'PackageLifecycleRecoveryComposition $recovery'), 'Reconciliation does not receive the shared recovery composition.');
$assert(str_contains($factorySource, '$recovery->coordinator, $recovery->store, $recovery->quiescence'), 'Legacy reconciliation orchestration is not based on the shared composition.');
$assert(substr_count($factorySource, 'new RecoveryRootResolver(') === 1, 'Factory constructs more than one authoritative recovery root resolver.');
$assert(substr_count($factorySource, 'new RecoveryLifecycleStore(') === 1, 'Factory constructs more than one recovery lifecycle store.');
$assert(substr_count($factorySource, 'new RecoveryArtifactStore(') === 1, 'Factory constructs more than one recovery artifact store.');
$assert(substr_count($factorySource, 'new MySqlRecoveryProvider()') === 1, 'Factory constructs more than one MySQL recovery provider.');
$assert(!str_contains(substr($factorySource, (int) strpos($factorySource, 'private static function reconciliationOperator'), (int) strpos($factorySource, 'private static function recoveryComposition') - (int) strpos($factorySource, 'private static function reconciliationOperator')), 'new RecoveryLifecycleStore('), 'Reconciliation still constructs a private recovery store.');

$saved = [];
$installationIdentityPath = $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . '.copot-lifecycle' . DIRECTORY_SEPARATOR . 'installation-identity.json';
$installationIdentityExisted = is_file($installationIdentityPath);
foreach (['COPOT_RECOVERY_ROOT', 'COPOT_MARIADB_ADMIN_USERNAME', 'COPOT_MARIADB_ADMIN_PASSWORD', 'COPOT_MARIADB_QUIESCENCE_CONFIRMED'] as $name) {
    $value = getenv($name);
    $saved[$name] = $value === false ? null : $value;
    putenv($name);
    unset($_ENV[$name]);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-wu3-b2a-' . bin2hex(random_bytes(5));
mkdir($root, 0700, true);

try {
    $setRecoveryRoot(null);
    $missing = PackageLifecycleFactory::forProject($base);
    $assert(!$missing->reconciliationAvailable(), 'Missing recovery configuration did not fail closed.');
    $assert($property($missing, 'reconciliationUnavailableReason') === 'A configured private recovery root is required.', 'Missing recovery configuration reason changed.');

    $setRecoveryRoot($base . DIRECTORY_SEPARATOR . 'storage');
    $invalid = PackageLifecycleFactory::forProject($base);
    $assert(!$invalid->reconciliationAvailable(), 'Invalid overlapping recovery root did not fail closed.');
    $invalidReason = $property($invalid, 'reconciliationUnavailableReason');
    $assert(is_string($invalidReason) && $invalidReason !== '', 'Invalid recovery root did not retain a fail-closed reason.');

    $setRecoveryRoot($root);
    $service = PackageLifecycleFactory::forProject($base);
    $assert($service instanceof PackageLifecycleService && $service->reconciliationAvailable(), 'Valid production recovery composition was not available.');
    $operator = $property($service, 'reconciliationOperator');
    $assert($operator instanceof LegacyReconciliationOperator, 'Legacy reconciliation operator was not composed.');

    $store = $property($operator, 'recoveryStore');
    $artifacts = $property($operator, 'artifactStore');
    $coordinator = $property($operator, 'recoveryCoordinator');
    $quiescence = $property($operator, 'quiescence');
    $mutex = $property($operator, 'mutex');
    $maintenance = $property($coordinator, 'maintenance');
    $assert($property($coordinator, 'store') === $store, 'Legacy coordinator does not share the authoritative lifecycle store.');
    $assert($property($coordinator, 'quiescence') === $quiescence, 'Legacy coordinator does not share the authoritative quiescence capability.');
    $assert($property($coordinator, 'mutex') === $mutex, 'Legacy coordinator does not share the authoritative recovery mutex.');
    $assert($property($maintenance, 'mutex') === $mutex, 'Recovery maintenance does not share the authoritative recovery mutex.');
    $assert($property($property($operator, 'filesystemRecovery'), 'artifactStore') === $artifacts, 'Legacy filesystem recovery does not share the authoritative artifact store.');
    $assert($property($operator, 'databaseRecovery') instanceof Copot\Core\BackupRecovery\MySqlRecoveryProvider, 'Legacy reconciliation did not receive the shared MySQL recovery provider.');
    $assert($quiescence instanceof UnavailableDatabaseQuiescence, 'Missing admin quiescence credentials did not remain fail-closed.');

    $compositionFactory = new ReflectionMethod(PackageLifecycleFactory::class, 'recoveryComposition');
    [$composition, $compositionReason] = $compositionFactory->invoke(
        null,
        $base,
        $base . DIRECTORY_SEPARATOR . 'public',
        $base . DIRECTORY_SEPARATOR . 'storage',
        new Copot\Core\InstallationState($base . DIRECTORY_SEPARATOR . 'storage'),
        new Copot\Core\CommittedLifecycleStateStore($base . DIRECTORY_SEPARATOR . 'storage'),
        new Copot\Core\LiveTreePathGuard($base)
    );
    $assert($compositionReason === null, 'Valid shared recovery composition returned an unavailable reason.');
    $normalCapture = $composition->normalCaptureService();
    $assert($normalCapture instanceof NormalWebcoreRecoveryCaptureService, 'Normal lifecycle did not obtain its production capture service.');
    foreach ([
        'coordinator' => 'coordinator',
        'store' => 'store',
        'artifacts' => 'artifacts',
        'filesystem' => 'filesystem',
        'lifecycle' => 'lifecycle',
        'installedLock' => 'installedLock',
        'database' => 'database',
        'quiescence' => 'quiescence',
    ] as $serviceProperty => $compositionProperty) {
        $assert($property($normalCapture, $serviceProperty) === $composition->{$compositionProperty}, 'Normal capture service does not share recovery component: ' . $compositionProperty);
    }

    $compositionClass = new ReflectionClass(Copot\Core\PackageLifecycleRecoveryComposition::class);
    $assert($compositionClass->hasMethod('normalCaptureService'), 'Shared composition does not expose normal recovery capture construction.');
    $assert($compositionClass->getMethod('normalCaptureService')->getReturnType()?->getName() === NormalWebcoreRecoveryCaptureService::class, 'Normal capture service return contract is incorrect.');
} finally {
    foreach ($saved as $name => $value) {
        if ($value === null) {
            putenv($name);
            unset($_ENV[$name]);
        } else {
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $remove($path . DIRECTORY_SEPARATOR . $entry); }
        }
        @rmdir($path);
    };
    $remove($root);
    if (!$installationIdentityExisted && is_file($installationIdentityPath)) {
        @unlink($installationIdentityPath);
        @rmdir(dirname($installationIdentityPath));
    }
}

echo "package_lifecycle_wu3_recovery_b2a: {$assertions} assertions passed\n";
