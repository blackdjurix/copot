<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\ModuleDefinition;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleLifecycleException;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';
Env::load($basePath . '/.env');

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$host = (string) (getenv('M33_DB_HOST') ?: getenv('D4_DB_HOST') ?: Env::get('DB_HOST', '127.0.0.1'));
$port = (int) (getenv('M33_DB_PORT') ?: getenv('D4_DB_PORT') ?: Env::get('DB_PORT', '3306'));
$username = (string) (getenv('M33_DB_USERNAME') ?: getenv('D4_DB_USERNAME') ?: Env::get('DB_USERNAME', 'root'));
$password = (string) (getenv('M33_DB_PASSWORD') ?: getenv('D4_DB_PASSWORD') ?: Env::get('DB_PASSWORD', ''));
$databaseName = 'copot_m33_batch4_' . bin2hex(random_bytes(6));
$databaseIdentifier = '`' . str_replace('`', '``', $databaseName) . '`';
$configuration = [
    'host' => $host,
    'port' => $port,
    'database' => $databaseName,
    'username' => $username,
    'password' => $password,
];

$server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$server->exec('CREATE DATABASE ' . $databaseIdentifier . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m33-batch4-' . bin2hex(random_bytes(6));
$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        is_dir($child) && !is_link($child) ? $removeDirectory($child) : unlink($child);
    }
    rmdir($path);
};
$makeModule = static function (string $name, array $metadata = [], array $files = []) use ($temporaryRoot): string {
    $path = $temporaryRoot . DIRECTORY_SEPARATOR . $name;
    mkdir($path, 0777, true);
    $metadata = array_merge([
        'name' => $name,
        'title' => ucfirst($name),
        'version' => '1.0.0',
        'permissions' => [['slug' => $name . '.read', 'name' => 'Read ' . $name]],
    ], $metadata);
    file_put_contents($path . DIRECTORY_SEPARATOR . 'module.json', json_encode($metadata, JSON_THROW_ON_ERROR));
    foreach ($files as $relative => $contents) {
        $file = $path . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
        file_put_contents($file, $contents);
    }
    return $path;
};
$makeManager = static function (string $root, ModuleRepository $repository): ModuleManager {
    return new ModuleManager(new ModuleDiscovery($root), $repository);
};

final class Batch4FailingPermissionRepository extends ModuleRepository
{
    public function __construct(Database $database, private bool $failAfterWrite = false)
    {
        parent::__construct($database);
    }

    public function replacePermissions(ModuleDefinition $module): void
    {
        parent::replacePermissions($module);
        if ($this->failAfterWrite) {
            throw new RuntimeException('RAW_PERMISSION_FAILURE');
        }
    }
}

try {
    (new Copot\Core\InstallerSchemaRunner($basePath . '/database/schema.sql'))->install($configuration);

    $_ENV['DB_DATABASE'] = $databaseName;
    putenv('DB_DATABASE=' . $databaseName);
    $database = new Database(new Config($basePath . '/config'));
    $repository = new ModuleRepository($database);
    mkdir($temporaryRoot, 0777, true);

    $validName = 'valid_' . bin2hex(random_bytes(3));
    $validPath = $makeModule($validName);
    $manager = $makeManager($temporaryRoot, $repository);
    $manager->install($validName);
    $row = $repository->findByName($validName);
    $assert(($row['status'] ?? null) === 'disabled', 'Valid install did not create disabled state.');
    $assert(!$database->connection()->inTransaction(), 'Root install left a transaction open.');
    $assert(count($repository->permissionsFor($validName)) === 1, 'Valid install did not replace permission metadata.');

    $failureName = 'failure_' . bin2hex(random_bytes(3));
    $makeModule($failureName);
    try {
        $failing = $makeManager($temporaryRoot, new Batch4FailingPermissionRepository($database, true));
        $failing->install($failureName);
        $assert(false, 'Injected permission failure unexpectedly succeeded.');
    } catch (ModuleLifecycleException $failure) {
        $assert($failure->getMessage() === 'Module installation failed.', 'Install failure message was not stable.');
        $assert($failure->getPrevious()?->getMessage() === 'RAW_PERMISSION_FAILURE', 'Original install cause was not retained.');
        $assert(!str_contains($failure->getMessage(), 'RAW_PERMISSION_FAILURE'), 'Raw install detail leaked.');
    }
    $assert($repository->findByName($failureName) === null, 'Failed install retained its module row.');
    $assert($repository->permissionsFor($failureName) === [], 'Failed install retained permissions.');
    $assert(!$database->connection()->inTransaction(), 'Failed root install left a transaction open.');
    $makeManager($temporaryRoot, $repository)->install($failureName);
    $assert(($repository->findByName($failureName)['status'] ?? null) === 'disabled', 'Failed install did not permit clean retry.');

    $nestedName = 'nested_' . bin2hex(random_bytes(3));
    $makeModule($nestedName);
    $database->connection()->beginTransaction();
    try {
        try {
            $makeManager($temporaryRoot, new Batch4FailingPermissionRepository($database, true))->install($nestedName);
            $assert(false, 'Injected nested permission failure unexpectedly succeeded.');
        } catch (ModuleLifecycleException) {
            $assert($database->connection()->inTransaction(), 'Nested failure closed the caller transaction.');
            $assert($repository->findByName($nestedName) === null, 'Nested failure retained module state.');
        }
    } finally {
        if ($database->connection()->inTransaction()) {
            $database->connection()->rollBack();
        }
    }

    $nestedSuccessName = 'nested_success_' . bin2hex(random_bytes(3));
    $makeModule($nestedSuccessName);
    $database->connection()->beginTransaction();
    try {
        $manager->install($nestedSuccessName);
        $assert($database->connection()->inTransaction(), 'Successful nested install closed the caller transaction.');
        $assert(($repository->findByName($nestedSuccessName)['status'] ?? null) === 'disabled', 'Nested install was not visible inside the caller transaction.');
        $database->connection()->rollBack();
    } finally {
        if ($database->connection()->inTransaction()) {
            $database->connection()->rollBack();
        }
    }
    $assert($repository->findByName($nestedSuccessName) === null, 'Caller rollback did not remove the nested install.');

    $manager->enable($validName);
    $enabledSnapshot = $repository->findByName($validName);
    try { $manager->enable($validName); $assert(false, 'Repeated enable succeeded.'); } catch (ModuleLifecycleException) { $assert(true, 'Repeated enable rejected.'); }
    $assert($repository->findByName($validName) === $enabledSnapshot, 'Rejected repeated enable mutated state.');
    $manager->disable($validName);
    $disabledSnapshot = $repository->findByName($validName);
    try { $manager->disable($validName); $assert(false, 'Repeated disable succeeded.'); } catch (ModuleLifecycleException) { $assert(true, 'Repeated disable rejected.'); }
    $assert($repository->findByName($validName) === $disabledSnapshot, 'Rejected repeated disable mutated state.');
    $manager->uninstall($validName);
    $assert(is_file($validPath . DIRECTORY_SEPARATOR . 'module.json'), 'Uninstall deleted module files.');

    $enabledName = 'enabled_uninstall_' . bin2hex(random_bytes(3));
    $makeModule($enabledName);
    $manager->install($enabledName);
    $manager->enable($enabledName);
    $enabledBeforeReject = $repository->findByName($enabledName);
    try { $manager->uninstall($enabledName); $assert(false, 'Enabled uninstall succeeded.'); } catch (ModuleLifecycleException) { $assert(true, 'Enabled uninstall rejected.'); }
    $assert($repository->findByName($enabledName) === $enabledBeforeReject, 'Rejected enabled uninstall mutated state.');
    $manager->disable($enabledName);
    $manager->uninstall($enabledName);

    $badStructure = 'bad_structure_' . bin2hex(random_bytes(3));
    $badPath = $makeModule($badStructure, ['title' => ['invalid']], []);
    $badDiscovery = new ModuleDiscovery($temporaryRoot);
    $badDiscovery->discover();
    $badErrors = array_filter($badDiscovery->errors(), static fn (array $error): bool => ($error['module'] ?? null) === $badStructure);
    $assert($badErrors !== [], 'Malformed structural manifest was accepted.');
    $assert(!is_file($badPath . DIRECTORY_SEPARATOR . 'routes.php'), 'Malformed fixture unexpectedly gained files.');
    $badRequires = 'bad_requires_' . bin2hex(random_bytes(3));
    $makeModule($badRequires, ['requires' => 'not-an-object']);
    $badRequiresDiscovery = new ModuleDiscovery($temporaryRoot);
    $badRequiresDiscovery->discover();
    $assert(array_filter($badRequiresDiscovery->errors(), static fn (array $error): bool => ($error['module'] ?? null) === $badRequires) !== [], 'Malformed dependency metadata was accepted.');
    $unsupportedRequires = 'unsupported_requires_' . bin2hex(random_bytes(3));
    $unsupportedRequiresPath = $makeModule($unsupportedRequires, ['requires' => ['copot' => '>=1.0']]);
    $unsupportedDiscovery = new ModuleDiscovery($temporaryRoot);
    $unsupportedDiscovery->discover();
    $assert(array_filter($unsupportedDiscovery->errors(), static fn (array $error): bool => ($error['module'] ?? null) === $unsupportedRequires) !== [], 'Unsupported requires keys were normalized as no dependencies.');
    $assert($repository->findByName($unsupportedRequires) === null, 'Malformed requires fixture unexpectedly had an installed row.');
    $assert($repository->permissionsFor($unsupportedRequires) === [], 'Malformed requires fixture unexpectedly had permission metadata.');
    try { $manager->install($unsupportedRequires); $assert(false, 'Malformed requires fixture was installed.'); } catch (ModuleLifecycleException) { $assert(true, 'Malformed requires fixture installation was rejected.'); }
    $assert($repository->findByName($unsupportedRequires) === null, 'Rejected malformed requires fixture created a module row.');
    $assert($repository->permissionsFor($unsupportedRequires) === [], 'Rejected malformed requires fixture created permission metadata.');
    try { $manager->enable($unsupportedRequires); $assert(false, 'Malformed requires fixture was enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Malformed requires fixture enablement was rejected.'); }
    $assert(is_file($unsupportedRequiresPath . DIRECTORY_SEPARATOR . 'module.json'), 'Malformed requires fixture files were altered.');

    $baseName = 'dependency_base_' . bin2hex(random_bytes(3));
    $makeModule($baseName);
    $manager->install($baseName);
    $manager->enable($baseName);

    $invalidStatusName = 'invalid_status_' . bin2hex(random_bytes(3));
    $makeModule($invalidStatusName);
    $manager->install($invalidStatusName);
    $database->connection()->prepare('UPDATE modules SET status = :status WHERE name = :name')->execute(['status' => 'invalid', 'name' => $invalidStatusName]);
    $invalidStatusBefore = $repository->findByName($invalidStatusName);
    try { $manager->enable($invalidStatusName); $assert(false, 'Invalid stored status was accepted.'); } catch (ModuleLifecycleException) { $assert(true, 'Invalid stored status was rejected.'); }
    $assert($repository->findByName($invalidStatusName) === $invalidStatusBefore, 'Rejected invalid status transition mutated persisted state.');

    $selfName = 'self_' . bin2hex(random_bytes(3));
    $makeModule($selfName, ['requires' => ['modules' => [$selfName]]]);
    $manager->install($selfName);
    try { $manager->enable($selfName); $assert(false, 'Self-dependency enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Self-dependency rejected.'); }

    $duplicateName = 'duplicate_' . bin2hex(random_bytes(3));
    $makeModule($duplicateName, ['requires' => ['modules' => [$baseName, $baseName]]]);
    $manager->install($duplicateName);
    try { $manager->enable($duplicateName); $assert(false, 'Duplicate dependency enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Duplicate dependency rejected.'); }

    $versionName = 'version_' . bin2hex(random_bytes(3));
    $makeModule($versionName, ['requires' => ['modules' => [['name' => $baseName, 'version' => '>=1.0']]]]);
    $manager->install($versionName);
    try { $manager->enable($versionName); $assert(false, 'Version constraint enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Version constraint rejected.'); }

    $cycleA = 'cycle_a_' . bin2hex(random_bytes(3));
    $cycleB = 'cycle_b_' . bin2hex(random_bytes(3));
    $makeModule($cycleA, ['requires' => ['modules' => [$cycleB]]]);
    $makeModule($cycleB, ['requires' => ['modules' => [$cycleA]]]);
    $manager->install($cycleA);
    $manager->install($cycleB);
    $database->connection()->prepare('UPDATE modules SET status = :status WHERE name = :name')->execute(['status' => 'enabled', 'name' => $cycleB]);
    try { $manager->enable($cycleA); $assert(false, 'Dependency cycle enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Dependency cycle rejected.'); }

    $indirectA = 'indirect_a_' . bin2hex(random_bytes(3));
    $indirectB = 'indirect_b_' . bin2hex(random_bytes(3));
    $indirectC = 'indirect_c_' . bin2hex(random_bytes(3));
    $makeModule($indirectA, ['requires' => ['modules' => [$indirectB]]]);
    $makeModule($indirectB, ['requires' => ['modules' => [$indirectC]]]);
    $makeModule($indirectC, ['requires' => ['modules' => [$indirectA]]]);
    $manager->install($indirectA);
    $manager->install($indirectB);
    $manager->install($indirectC);
    foreach ([$indirectA, $indirectB, $indirectC] as $indirectName) {
        $database->connection()->prepare('UPDATE modules SET status = :status WHERE name = :name')->execute(['status' => 'enabled', 'name' => $indirectName]);
    }
    try { $manager->enable($indirectA); $assert(false, 'Indirect dependency cycle enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Indirect dependency cycle rejected.'); }

    $missingName = 'missing_dep_' . bin2hex(random_bytes(3));
    $makeModule($missingName, ['requires' => ['modules' => ['not_installed_' . bin2hex(random_bytes(2))]]]);
    $manager->install($missingName);
    try { $manager->enable($missingName); $assert(false, 'Missing dependency enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Missing dependency rejected.'); }

    $disabledDep = 'disabled_dep_' . bin2hex(random_bytes(3));
    $makeModule($disabledDep, ['requires' => ['modules' => [$baseName]]]);
    $manager->install($disabledDep);
    $manager->disable($baseName);
    try { $manager->enable($disabledDep); $assert(false, 'Disabled dependency enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Disabled dependency rejected.'); }
    $manager->enable($baseName);

    $missingFile = 'missing_file_' . bin2hex(random_bytes(3));
    $makeModule($missingFile, ['routes' => 'routes.php']);
    $manager->install($missingFile);
    try { $manager->enable($missingFile); $assert(false, 'Missing route file enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Missing route file rejected.'); }
    $missingListener = 'missing_listener_' . bin2hex(random_bytes(3));
    $makeModule($missingListener, ['listeners' => 'listeners.php']);
    $manager->install($missingListener);
    try { $manager->enable($missingListener); $assert(false, 'Missing listener file enabled.'); } catch (ModuleLifecycleException) { $assert(true, 'Missing listener file rejected.'); }
    $escaping = 'escaping_' . bin2hex(random_bytes(3));
    $makeModule($escaping, ['routes' => '../outside.php']);
    $escapingDiscovery = new ModuleDiscovery($temporaryRoot);
    $escapingDiscovery->discover();
    $assert(array_filter($escapingDiscovery->errors(), static fn (array $error): bool => ($error['module'] ?? null) === $escaping) !== [], 'Escaping route path was accepted.');
    $escapingListener = 'escaping_listener_' . bin2hex(random_bytes(3));
    $makeModule($escapingListener, ['listeners' => '../outside.php']);
    $escapingListenerDiscovery = new ModuleDiscovery($temporaryRoot);
    $escapingListenerDiscovery->discover();
    $assert(array_filter($escapingListenerDiscovery->errors(), static fn (array $error): bool => ($error['module'] ?? null) === $escapingListener) !== [], 'Escaping listener path was accepted.');

    $dependent = 'dependent_' . bin2hex(random_bytes(3));
    $makeModule($dependent, ['requires' => ['modules' => [$baseName]]]);
    $manager->install($dependent);
    $manager->enable($dependent);
    try { $manager->disable($baseName); $assert(false, 'Enabled dependent did not block disable.'); } catch (ModuleLifecycleException) { $assert(true, 'Enabled dependent blocked disable.'); }
    $manager->disable($dependent);

    $recoveryTarget = 'recovery_target_' . bin2hex(random_bytes(3));
    $recoveryDependent = 'recovery_dependent_' . bin2hex(random_bytes(3));
    $makeModule($recoveryTarget);
    $makeModule($recoveryDependent, ['requires' => ['modules' => [$recoveryTarget]]]);
    $manager->install($recoveryTarget);
    $manager->enable($recoveryTarget);
    $manager->install($recoveryDependent);
    $manager->enable($recoveryDependent);
    unlink($temporaryRoot . DIRECTORY_SEPARATOR . $recoveryTarget . DIRECTORY_SEPARATOR . 'module.json');
    $manager->disable($recoveryTarget);
    $assert(($repository->findByName($recoveryTarget)['status'] ?? null) === 'disabled', 'Unavailable target was not disabled for recovery.');
    $unrelatedRecovery = 'unrelated_recovery_' . bin2hex(random_bytes(3));
    $makeModule($unrelatedRecovery);
    $manager->install($unrelatedRecovery);
    $manager->enable($unrelatedRecovery);
    $assert(($repository->findByName($unrelatedRecovery)['status'] ?? null) === 'enabled', 'Unrelated lifecycle remained blocked after recovery.');

    $unknown = 'unknown_enabled_' . bin2hex(random_bytes(3));
    $malformedEnabled = 'malformed_enabled_' . bin2hex(random_bytes(3));
    $uninstallUnavailable = 'uninstall_unavailable_' . bin2hex(random_bytes(3));
    $uninstallUnavailablePath = $makeModule($uninstallUnavailable, [], ['marker.txt' => 'keep']);
    $manager->install($uninstallUnavailable);
    $uninstallUnavailableBefore = $repository->findByName($uninstallUnavailable);
    $uninstallUnavailablePermissions = $repository->permissionsFor($uninstallUnavailable);
    unlink($uninstallUnavailablePath . DIRECTORY_SEPARATOR . 'module.json');
    $malformedEnabledPath = $temporaryRoot . DIRECTORY_SEPARATOR . $malformedEnabled;
    mkdir($malformedEnabledPath, 0777, true);
    file_put_contents($malformedEnabledPath . DIRECTORY_SEPARATOR . 'module.json', '{');
    $database->connection()->prepare('INSERT INTO modules (name,title,version,path,status,installed_at,created_at,updated_at) VALUES (:name,:title,:version,:path,:status,NOW(),NOW(),NOW())')->execute([
        'name' => $unknown, 'title' => 'Unknown', 'version' => '1.0.0', 'path' => $temporaryRoot . DIRECTORY_SEPARATOR . 'gone', 'status' => 'enabled',
    ]);
    $database->connection()->prepare('INSERT INTO modules (name,title,version,path,status,installed_at,created_at,updated_at) VALUES (:name,:title,:version,:path,:status,NOW(),NOW(),NOW())')->execute([
        'name' => $malformedEnabled, 'title' => 'Malformed', 'version' => '1.0.0', 'path' => $malformedEnabledPath, 'status' => 'enabled',
    ]);
    try { $manager->uninstall($uninstallUnavailable); $assert(false, 'Unavailable uninstall target bypassed fail-closed safety.'); } catch (ModuleLifecycleException) { $assert(true, 'Unavailable uninstall target failed closed.'); }
    $assert($repository->findByName($uninstallUnavailable) === $uninstallUnavailableBefore, 'Rejected unavailable uninstall mutated the target row.');
    $assert($repository->permissionsFor($uninstallUnavailable) === $uninstallUnavailablePermissions, 'Rejected unavailable uninstall mutated permissions.');
    $assert(is_file($uninstallUnavailablePath . DIRECTORY_SEPARATOR . 'marker.txt'), 'Rejected unavailable uninstall altered module files.');
    try { $manager->disable($baseName); $assert(false, 'Unknown enabled module did not fail closed.'); } catch (ModuleLifecycleException) { $assert(true, 'Unknown enabled module failed closed.'); }
    $manager->disable($unknown);
    $manager->disable($malformedEnabled);
    $manager->disable($baseName);

    echo "M3.3 Batch 4 lifecycle passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if (isset($database) && $database->connection()->inTransaction()) {
        $database->connection()->rollBack();
    }
    if (is_dir($temporaryRoot)) {
        $removeDirectory($temporaryRoot);
    }
    $server->exec('DROP DATABASE IF EXISTS ' . $databaseIdentifier);
}
