<?php

declare(strict_types=1);

$sourcePath = dirname(__DIR__);
$phpBinary = PHP_BINARY;
$assertions = 0;
$failures = [];
$createdPaths = [];

ob_start();

$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;

    if (!$condition) {
        $failures[] = $message;
    }
};

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir()
            ? @rmdir($item->getPathname())
            : @unlink($item->getPathname());
    }

    @rmdir($path);
};

$run = static function (string $command, string $cwd) use (&$failures): array {
    $output = [];
    $exitCode = 0;
    $descriptor = $command . ' 2>&1';
    exec($descriptor, $output, $exitCode);

    if ($exitCode !== 0) {
        $failures[] = 'Command failed: ' . $command . "\n" . implode("\n", $output);
    }

    return [$exitCode, $output];
};

$path = static fn (string $base, string $relative): string => $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

$responseValue = static function (object $response, string $property): mixed {
    $reflection = new ReflectionObject($response);
    $propertyReflection = $reflection->getProperty($property);
    $propertyReflection->setAccessible(true);

    return $propertyReflection->getValue($response);
};

$safeDatabaseName = static function (string $database): bool {
    return $database === 'copot_d4_clean_install_test'
        || str_starts_with($database, 'copot_d4_');
};

try {
    if (!is_file($sourcePath . '/build/package.php')) {
        $fail('Package builder is missing.');
    }

    [$buildExitCode] = $run(escapeshellarg($phpBinary) . ' ' . escapeshellarg($sourcePath . '/build/package.php'), $sourcePath);

    $version = '0.12.0';
    $packagePath = $sourcePath . '/dist/copot-v' . $version . '.zip';
    $assert($buildExitCode === 0, 'Official package builder must run successfully.');
    $assert(is_file($packagePath), 'D4 package input must exist at dist/copot-v0.12.0.zip.');

    $installTarget = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-d4-clean-install-' . bin2hex(random_bytes(8));

    if (realpath($sourcePath) === realpath($installTarget)) {
        $fail('Install target must not be the source repository.');
    }

    if (file_exists($installTarget)) {
        $fail('Install target unexpectedly already exists: ' . $installTarget);
    } elseif (!mkdir($installTarget, 0775, true)) {
        $fail('Unable to create isolated install target.');
    } else {
        $createdPaths[] = $installTarget;
    }

    if ($failures === []) {
        $powerShellScript = '$ErrorActionPreference = "Stop"; Expand-Archive -LiteralPath "'
            . str_replace('"', '`"', $packagePath)
            . '" -DestinationPath "'
            . str_replace('"', '`"', $installTarget)
            . '" -Force';
        $encodedCommand = base64_encode((string) iconv('UTF-8', 'UTF-16LE', $powerShellScript));
        $expandCommand = 'powershell -NoProfile -EncodedCommand ' . $encodedCommand;
        [$expandExitCode] = $run($expandCommand, $sourcePath);
        $assert($expandExitCode === 0, 'Package ZIP must extract with PowerShell Expand-Archive.');
    }

    $requiredPackageFiles = [
        'public/index.php',
        '.env.example',
        'app/Core/Version.php',
        'bootstrap/autoload.php',
        'bootstrap/app.php',
        'bootstrap/installer.php',
        'database/schema.sql',
        'storage/cache/.gitkeep',
        'storage/logs/.gitkeep',
    ];

    foreach ($requiredPackageFiles as $file) {
        $assert(is_file($path($installTarget, $file)), 'Extracted package must contain: ' . $file);
    }

    foreach ([
        '.env',
        'tests',
        'docs',
        'build',
        'dist',
        'modules/example',
        'storage/installed.lock',
        'storage/.install.lock',
        'storage/logs/copot.log',
        'storage/site-assets',
    ] as $forbidden) {
        $assert(!file_exists($path($installTarget, $forbidden)), 'Extracted package must not contain: ' . $forbidden);
    }

    $sourceLeakCandidates = [
        '.env',
        'storage/installed.lock',
        'storage/.install.lock',
        'storage/logs/copot.log',
        'storage/site-assets',
    ];

    foreach ($sourceLeakCandidates as $candidate) {
        if (file_exists($path($sourcePath, $candidate))) {
            $assert(
                !file_exists($path($installTarget, $candidate)),
                'Source runtime state must not be mirrored into install target: ' . $candidate
            );
        }
    }

    $databaseHost = getenv('D4_DB_HOST') ?: '127.0.0.1';
    $databasePort = (int) (getenv('D4_DB_PORT') ?: '3306');
    $databaseName = getenv('D4_DB_DATABASE') ?: 'copot_d4_clean_install_test';
    $databaseUser = getenv('D4_DB_USERNAME') ?: 'root';
    $databasePassword = getenv('D4_DB_PASSWORD');
    $databasePassword = $databasePassword === false ? '' : $databasePassword;

    echo 'D4 database target:' . PHP_EOL;
    echo '- host: ' . $databaseHost . PHP_EOL;
    echo '- port: ' . $databasePort . PHP_EOL;
    echo '- database: ' . $databaseName . PHP_EOL;
    echo '- user: ' . $databaseUser . PHP_EOL;
    echo '- action: DROP DATABASE IF EXISTS, then CREATE DATABASE for dedicated D4 verification.' . PHP_EOL;

    if (!$safeDatabaseName($databaseName)) {
        $fail('D4 database name is not dedicated enough for destructive verification: ' . $databaseName);
    }

    if ($failures === []) {
        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $databaseHost, $databasePort);
        $server = new PDO($serverDsn, $databaseUser, $databasePassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $quotedDatabase = '`' . str_replace('`', '``', $databaseName) . '`';
        $server->exec('DROP DATABASE IF EXISTS ' . $quotedDatabase);
        $server->exec('CREATE DATABASE ' . $quotedDatabase . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    if ($failures === []) {
        chdir($installTarget);
        require $installTarget . '/bootstrap/autoload.php';

        $configuration = [
            'host' => $databaseHost,
            'port' => $databasePort,
            'database' => $databaseName,
            'username' => $databaseUser,
            'password' => $databasePassword,
        ];

        $requirementsService = new Copot\Core\InstallerRequirements($installTarget);
        $requirements = $requirementsService->check(true);
        $requirementsPassed = $requirementsService->allPassed($requirements);
        $assert($requirementsPassed, 'Extracted package must satisfy installer requirements.');

        $validatedConfiguration = (new Copot\Core\InstallerDatabaseValidator())->validate([
            'host' => $databaseHost,
            'port' => (string) $databasePort,
            'database' => $databaseName,
            'username' => $databaseUser,
            'password' => $databasePassword,
        ]);
        $assert($validatedConfiguration === $configuration, 'Installer database validator must accept the D4 database configuration.');

        $databaseSetup = new Copot\Core\InstallerDatabaseSetup(
            new Copot\Core\InstallerDatabaseProbe(),
            new Copot\Core\InstallerEnvironmentWriter($installTarget . '/.env'),
            new Copot\Core\InstallerSchemaRunner($installTarget . '/database/schema.sql'),
            new Copot\Core\InstallationMutex($installTarget . '/storage')
        );
        $databaseResult = $databaseSetup->install($configuration, $requirementsPassed);
        $assert(($databaseResult['statement_count'] ?? 0) > 0, 'Installer database setup must install canonical schema.');
        $assert(is_file($installTarget . '/.env'), 'Installer database setup must create fresh package .env.');
        $assert(str_contains((string) file_get_contents($installTarget . '/.env'), 'DB_DATABASE="' . $databaseName . '"'), 'Package .env must target the D4 database.');

        try {
            (new Copot\Core\InstallerDatabaseProbe())->test($configuration);
            $assert(false, 'Repeated database install probe must reject the non-empty D4 database.');
        } catch (Copot\Core\InstallationException) {
            $assert(true, 'Repeated database install probe rejects the non-empty D4 database.');
        }

        Copot\Core\Env::load($installTarget . '/.env');
        $config = new Copot\Core\Config($installTarget . '/config');
        $database = new Copot\Core\Database($config);
        $schema = new Copot\Core\InstallerSchemaState($database);
        $assert($schema->isReady(), 'Installed database schema must be ready.');

        $settingsRepository = new Copot\Core\SettingsRepository($database);
        $settings = new Copot\Core\SettingsService(
            Copot\Core\SettingsRegistry::core(),
            $settingsRepository
        );
        $administratorSetup = new Copot\Core\InstallerAdministratorSetup(
            $database,
            new Copot\Core\UserProvider($database),
            new Copot\Core\PasswordHasher(),
            $settings,
            $schema,
            new Copot\Core\InstallationMutex($installTarget . '/storage')
        );
        $administrator = $administratorSetup->install([
            'admin_name' => 'D4 Admin',
            'admin_email' => 'd4-admin@example.test',
            'admin_password' => 'D4-clean-pass-123',
            'admin_password_confirmation' => 'D4-clean-pass-123',
            'site_name' => 'Copot D4 Clean Install',
            'site_tagline' => 'Package verification',
            'timezone' => 'UTC',
            'locale' => 'en_US',
        ], $requirementsPassed);
        $assert(($administrator['email'] ?? null) === 'd4-admin@example.test', 'Installer administrator setup must create the first admin.');

        $installationState = new Copot\Core\InstallationState($installTarget . '/storage');
        $finalizer = new Copot\Core\InstallerFinalizer(
            $database,
            $schema,
            $settings,
            $settingsRepository,
            new Copot\Core\ThemeDiscovery($installTarget . '/themes'),
            new Copot\Core\ThemeManager(new Copot\Core\ThemeRepository($database), $database, $installTarget),
            new Copot\Core\ModuleManager(
                new Copot\Core\ModuleDiscovery($installTarget . '/modules'),
                new Copot\Core\ModuleRepository($database)
            ),
            $installationState,
            new Copot\Core\InstallationMutex($installTarget . '/storage')
        );
        $finalized = $finalizer->finalize();
        $assert(($finalized['version'] ?? null) === Copot\Core\Version::CURRENT, 'Installer finalizer must return Version::CURRENT.');

        try {
            $finalizer->finalize();
            $assert(false, 'Repeated finalization must be rejected after installed marker exists.');
        } catch (Copot\Core\InstallationException) {
            $assert(true, 'Repeated finalization is rejected after installed marker exists.');
        }

        $marker = $installationState->readMarker();
        $assert(is_array($marker), 'Installed marker must be readable.');
        $assert(($marker['version'] ?? null) === Copot\Core\Version::CURRENT, 'Installed marker version must equal Version::CURRENT.');
        $assert(($marker['version'] ?? null) !== '0.8.0', 'Installed marker must not contain stale 0.8.0 version.');
        $assert(array_keys($marker ?? []) === ['version', 'installed_at'] || array_keys($marker ?? []) === ['installed_at', 'version'], 'Installed marker must contain only installed_at and version.');

        $gate = new Copot\Core\InstallerGate($installationState);
        $assert($gate->decide(new Copot\Core\Request('GET', '/install')) === Copot\Core\InstallerGate::BLOCK_INSTALLER, 'Installed package must block /install.');
        $assert($gate->decide(new Copot\Core\Request('GET', '/')) === Copot\Core\InstallerGate::NORMAL_APPLICATION, 'Installed package must allow normal application bootstrap.');

        $connection = $database->connection();
        $assert((int) $connection->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1, 'Installed database must have one first administrator.');
        $assert((int) $connection->query("SELECT COUNT(*) FROM themes WHERE theme_id = 'default' AND is_active = 1")->fetchColumn() === 1, 'Default theme must be active.');
        $assert((int) $connection->query("SELECT COUNT(*) FROM modules WHERE name IN ('content', 'taxonomy') AND status = 'enabled'")->fetchColumn() === 2, 'Content and Taxonomy modules must be enabled.');
        $assert($settings->get('site', 'name') === 'Copot D4 Clean Install', 'Initial site name must be persisted.');

        $app = require $installTarget . '/bootstrap/app.php';
        $assert($app instanceof Copot\Core\Application, 'Installed package application must boot.');

        $homeResponse = $app->run(new Copot\Core\Request('GET', '/'));
        $homeStatus = $responseValue($homeResponse, 'status');
        $homeContent = (string) $responseValue($homeResponse, 'content');
        $assert($homeStatus === 200, 'Installed package public home must render.');
        $assert(str_contains($homeContent, 'Copot D4 Clean Install'), 'Public home must render installed site name.');
        $assert(!str_contains($homeContent, $sourcePath), 'Public home must not leak source repository path.');
        $assert(!str_contains($homeContent, $installTarget), 'Public home must not leak install target path.');

        $adminLoginResponse = $app->run(new Copot\Core\Request('GET', '/admin'));
        $adminLoginStatus = $responseValue($adminLoginResponse, 'status');
        $adminLoginContent = (string) $responseValue($adminLoginResponse, 'content');
        $assert($adminLoginStatus === 200, 'Installed package admin entry must render login page.');
        $assert(str_contains($adminLoginContent, 'D4 Admin') === false, 'Admin login page must not expose authenticated admin details.');

        $loginResponse = $app->run(new Copot\Core\Request('POST', '/admin', [], [
            '_token' => $app->session()->csrfToken(),
            'email' => 'd4-admin@example.test',
            'password' => 'D4-clean-pass-123',
        ]));
        $assert($responseValue($loginResponse, 'status') === 302, 'Admin login with installer-created credentials must redirect.');

        $dashboardResponse = $app->run(new Copot\Core\Request('GET', '/admin'));
        $dashboardStatus = $responseValue($dashboardResponse, 'status');
        $dashboardContent = (string) $responseValue($dashboardResponse, 'content');
        $assert($dashboardStatus === 200, 'Authenticated Admin shell must render dashboard.');
        $assert(str_contains($dashboardContent, 'D4 Admin'), 'Admin dashboard must show installer-created admin.');

        $settingsResponse = $app->run(new Copot\Core\Request('GET', '/admin/settings'));
        $assert($responseValue($settingsResponse, 'status') === 200, 'Authenticated Admin settings route must render.');

        $logoResponse = $app->run(new Copot\Core\Request('GET', '/site-assets/logo'));
        $assert($responseValue($logoResponse, 'status') === 404, 'Missing Logo site asset must return controlled 404.');

        $extractedFileCount = iterator_count(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($installTarget, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        ));
        echo 'D4 isolated install target: ' . $installTarget . PHP_EOL;
        echo 'D4 extracted/created filesystem entries after verification: ' . $extractedFileCount . PHP_EOL;
        echo 'D4 installed marker version: ' . ($marker['version'] ?? 'unknown') . PHP_EOL;
    }
} catch (Throwable $throwable) {
    $fail('Unexpected D4 clean-install verification failure: ' . $throwable->getMessage());
} finally {
    foreach ($createdPaths as $createdPath) {
        if (is_string($createdPath) && str_contains($createdPath, 'copot-d4-clean-install-')) {
            $removeDirectory($createdPath);
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Post-M2 clean-install verification failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }

    if (ob_get_level() > 0) {
        ob_end_flush();
    }

    exit(1);
}

echo 'Post-M2 clean-install verification passed (' . $assertions . ' assertions).' . PHP_EOL;

if (ob_get_level() > 0) {
    ob_end_flush();
}
