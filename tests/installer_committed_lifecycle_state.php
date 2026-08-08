<?php

declare(strict_types=1);

$base = dirname(__DIR__);
chdir($base);
require $base . '/bootstrap/autoload.php';

$host = (string) (getenv('D4_DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('D4_DB_PORT') ?: '3306');
$username = (string) (getenv('D4_DB_USERNAME') ?: 'root');
$password = (string) (getenv('D4_DB_PASSWORD') ?: '');
$databaseName = 'copot_installer_state_' . bin2hex(random_bytes(6));
$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-installer-state-' . bin2hex(random_bytes(6));
$storage = $root . DIRECTORY_SEPARATOR . 'storage';
$configRoot = $root . DIRECTORY_SEPARATOR . 'config';

$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    if (!is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $remove($path . DIRECTORY_SEPARATOR . $entry);
        }
    }
    @rmdir($path);
};

mkdir($storage, 0700, true);
mkdir($configRoot, 0700, true);

$server = null;
$quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';

try {
    $server = new PDO(sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port), $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $config = "<?php return ['default' => 'mysql', 'connections' => ['mysql' => ['driver' => 'mysql', 'host' => "
        . var_export($host, true) . ', ' . "'port' => " . $port . ', ' . "'database' => " . var_export($databaseName, true) . ', ' . "'username' => " . var_export($username, true) . ', ' . "'password' => " . var_export($password, true) . ", 'charset' => 'utf8mb4']]];";
    file_put_contents($configRoot . DIRECTORY_SEPARATOR . 'database.php', $config);

    $configuration = [
        'host' => $host,
        'port' => $port,
        'database' => $databaseName,
        'username' => $username,
        'password' => $password,
    ];
    (new Copot\Core\InstallerSchemaRunner($base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'))->install($configuration);

    $database = new Copot\Core\Database(new Copot\Core\Config($configRoot));
    $schema = new Copot\Core\InstallerSchemaState($database);
    if (!$schema->isReady()) {
        $tables = $database->connection()->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
        throw new RuntimeException('Disposable installer schema is not ready: ' . implode(',', $tables));
    }

    $settingsRepository = new Copot\Core\SettingsRepository($database);
    $settings = new Copot\Core\SettingsService(Copot\Core\SettingsRegistry::core(), $settingsRepository);
    (new Copot\Core\InstallerAdministratorSetup(
        $database,
        new Copot\Core\UserProvider($database),
        new Copot\Core\PasswordHasher(),
        $settings,
        $schema,
        new Copot\Core\InstallationMutex($storage)
    ))->install([
        'admin_name' => 'Installer State Test',
        'admin_email' => 'installer-state@example.test',
        'admin_password' => 'Installer-state-pass-123',
        'admin_password_confirmation' => 'Installer-state-pass-123',
        'site_name' => 'Installer State Test',
        'site_tagline' => 'Focused regression',
        'timezone' => 'UTC',
        'locale' => 'en_US',
    ], true);

    $installationState = new Copot\Core\InstallationState($storage);
    $committedStore = new Copot\Core\CommittedLifecycleStateStore($storage);
    if ($committedStore->read() !== null) {
        throw new RuntimeException('Regression fixture unexpectedly had committed Webcore lifecycle state before finalization.');
    }

    $finalizer = new Copot\Core\InstallerFinalizer(
        $database,
        $schema,
        $settings,
        $settingsRepository,
        new Copot\Core\ThemeDiscovery($base . DIRECTORY_SEPARATOR . 'themes'),
        new Copot\Core\ThemeManager(new Copot\Core\ThemeRepository($database), $database, $base),
        new Copot\Core\ModuleManager(
            new Copot\Core\ModuleDiscovery($base . DIRECTORY_SEPARATOR . 'modules'),
            new Copot\Core\ModuleRepository($database)
        ),
        $installationState,
        $committedStore,
        new Copot\Core\InstallationMutex($storage)
    );
    $finalizer->finalize();

    $marker = $installationState->readMarker();
    $state = $committedStore->read();
    if (!is_array($marker) || $marker['version'] !== Copot\Core\Version::CURRENT) {
        throw new RuntimeException('Fresh install marker is not canonical.');
    }
    if (!$state instanceof Copot\Core\CommittedLifecycleState
        || $state->webcoreVersion() !== Copot\Core\Version::CURRENT
        || $state->releaseIdentity() !== 'copot-v' . Copot\Core\Version::CURRENT
        || $state->schemaStateIdentity() !== 'canonical-current'
        || $state->migrationStateIdentity() !== Copot\Core\CoreMigrationStateIdentity::fromRecords([])
        || $state->committedAt()->format(DATE_ATOM) !== $marker['installed_at']) {
        throw new RuntimeException('Fresh install committed lifecycle state is inconsistent.');
    }

    $module = new Copot\Core\ModuleIdentity('wu7-acceptance');
    $contract = new Copot\Core\ModulePackageContract(
        Copot\Core\ModulePackageContract::MODULE_PACKAGE_TYPE,
        Copot\Core\ModulePackageContract::CURRENT_CONTRACT_VERSION,
        new Copot\Core\PackageIdentity('copot-wu7-acceptance-package'),
        $module,
        'WU7 Acceptance Module',
        '0.1.0',
        'wu7-acceptance-release-1',
        new Copot\Core\PackageCompatibility(Copot\Core\Version::CURRENT),
        null,
        new Copot\Core\ModulePackageOwnership($module, 'modules/wu7-acceptance'),
        [],
        [],
        new Copot\Core\ModuleMigrationDeclaration($module),
        new Copot\Core\ModuleProvisioningDeclaration()
    );
    $plan = (new Copot\Core\ModuleTransitionPlanner(
        $committedStore,
        new Copot\Core\RuntimeCompatibilityContext(PHP_VERSION, [], get_loaded_extensions())
    ))->plan(
        Copot\Core\ModuleLifecycleInspection::fresh(),
        new Copot\Core\ModuleLifecycleTarget($contract, str_repeat('a', 64))
    );
    if (!$plan->accepted() || $plan->classification() !== Copot\Core\ModuleTransitionPlan::INSTALL) {
        throw new RuntimeException('Compatible fresh Module target did not reach INSTALL planning.');
    }

    echo "Installer committed lifecycle-state regression test passed.\n";
} finally {
    if ($server instanceof PDO) {
        try {
            $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
        } catch (Throwable) {
        }
    }
    $remove($root);
}
