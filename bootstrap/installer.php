<?php

use Copot\Core\Admin\AdminUrl;
use Copot\Core\Config;
use Copot\Core\Csrf;
use Copot\Core\Database;
use Copot\Core\Env;
use Copot\Core\InstallationException;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallerAdministratorSetup;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerDatabaseSetup;
use Copot\Core\InstallerDatabaseValidator;
use Copot\Core\InstallerEnvironmentWriter;
use Copot\Core\InstallerFinalizer;
use Copot\Core\InstallerRequirements;
use Copot\Core\InstallerSchemaRunner;
use Copot\Core\InstallerSchemaState;
use Copot\Core\InstallerValidationException;
use Copot\Core\PasswordHasher;
use Copot\Core\Response;
use Copot\Core\Session;
use Copot\Core\SettingsRegistry;
use Copot\Core\SettingsRepository;
use Copot\Core\SettingsService;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleManager;
use Copot\Core\ModuleRepository;
use Copot\Core\ThemeDiscovery;
use Copot\Core\ThemeManager;
use Copot\Core\ThemeRepository;
use Copot\Core\UserProvider;
use Copot\Core\View;

$storageReady = $installationState->storageIsWritable();
$sessionReady = false;
$csrf = null;

if (extension_loaded('session') && function_exists('session_start') && function_exists('session_status')) {
    $session = new Session(new Config($basePath . '/config'));
    @$session->start();
    $sessionReady = session_status() === PHP_SESSION_ACTIVE;

    if ($sessionReady) {
        $csrf = new Csrf($session);
    }
}

$requirementsService = new InstallerRequirements($basePath);
$requirements = $requirementsService->check($sessionReady);
$requirementsPassed = $requirementsService->allPassed($requirements);
$installerReady = !$installationStateError && $storageReady && $requirementsPassed;
$status = 200;
$message = $requirementsPassed
    ? 'Requirements are satisfied. Database configuration can be tested.'
    : 'Resolve the failed requirements before continuing.';
$values = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => '',
    'username' => '',
];
$errors = [];
$databaseResult = null;
$schemaReady = false;
$administratorExists = false;
$administratorSetup = null;
$finalizer = null;
$setupErrors = [];
$finalizationError = null;
$setupValues = [
    'admin_name' => '',
    'admin_email' => '',
    'site_name' => 'copot',
    'site_tagline' => '',
    'timezone' => 'UTC',
    'locale' => 'en_US',
];

$loadAdministratorSetup = function () use ($basePath, $installationState): array {
    if (!is_file($basePath . '/.env')) {
        return [false, false, null, null];
    }

    Env::load($basePath . '/.env');
    $database = new Database(new Config($basePath . '/config'));
    $schema = new InstallerSchemaState($database);

    if (!$schema->isReady()) {
        return [false, false, null, null];
    }

    $settingsRepository = new SettingsRepository($database);
    $settings = new SettingsService(
        SettingsRegistry::core(),
        $settingsRepository
    );

    $setup = new InstallerAdministratorSetup(
        $database,
        new UserProvider($database),
        new PasswordHasher(),
        $settings,
        $schema,
        new InstallationMutex($basePath . '/storage')
    );

    $themeRepository = new ThemeRepository($database);
    $finalizer = new InstallerFinalizer(
        $database,
        $schema,
        $settings,
        $settingsRepository,
        new ThemeDiscovery($basePath . '/themes'),
        new ThemeManager($themeRepository, $database, $basePath),
        new ModuleManager(
            new ModuleDiscovery($basePath . '/modules'),
            new ModuleRepository($database)
        ),
        $installationState,
        new InstallationMutex($basePath . '/storage')
    );

    return [true, $setup->administratorExists(), $setup, $finalizer];
};

if (!$installationStateError) {
    try {
        [$schemaReady, $administratorExists, $administratorSetup, $finalizer] = $loadAdministratorSetup();
    } catch (\Throwable) {
        $schemaReady = false;
        $administratorExists = false;
        $administratorSetup = null;
        $finalizer = null;
    }
}

$requestedStep = $request->method() === 'GET' ? $request->input('step') : null;
$requestedStep = is_string($requestedStep) && $requestedStep === 'database' ? 'database' : null;
$currentStep = !$schemaReady || $requestedStep === 'database'
    ? 'database'
    : ($administratorExists ? 'finalize' : 'administrator');

if ($currentStep === 'finalize' && $requirementsPassed) {
    $message = 'The first administrator and initial settings are ready. Finalize the installation.';
} elseif ($currentStep === 'administrator' && $requirementsPassed) {
    $message = 'Database schema is ready. Create the first administrator and initial site settings.';
} elseif ($requestedStep === 'database' && $requirementsPassed) {
    $message = 'Choose and validate a dedicated empty database.';
}

if ($installationStateError) {
    $status = 500;
    $message = 'Installation state could not be verified.';
} elseif (!$storageReady) {
    $status = 503;
    $message = 'Installer storage is not writable.';
} elseif ($request->method() === 'POST') {
    if (!$csrf instanceof Csrf) {
        $status = 503;
        $message = 'Installer session support is unavailable.';
    } else {
        $csrfResponse = $csrf->validateOrReject($request);

        if ($csrfResponse instanceof Response) {
            return $csrfResponse;
        }

        if (!$requirementsPassed) {
            $status = 503;
            $message = 'Resolve the failed requirements before continuing.';
        } else {
            $action = $request->post('action', 'test_database');
            if ($action === 'finalize_installation') {
                try {
                    if (!$finalizer instanceof InstallerFinalizer) {
                        throw new InstallationException('Installation prerequisites are not ready.');
                    }

                    $adminUrl = new AdminUrl(new Config($basePath . '/config'));

                    $finalizer->finalize();

                    return Response::redirect($adminUrl->baseUrl());
                } catch (\Throwable) {
                    $status = 503;
                    $message = 'Installation could not be finalized.';
                    $finalizationError = $currentStep === 'finalize' ? $message : null;
                }
            } elseif ($action === 'create_administrator') {
                if ($administratorExists) {
                    return Response::redirect('/install');
                }

                $input = [
                    'admin_name' => $request->post('admin_name', ''),
                    'admin_email' => $request->post('admin_email', ''),
                    'admin_password' => $request->post('admin_password', ''),
                    'admin_password_confirmation' => $request->post('admin_password_confirmation', ''),
                    'site_name' => $request->post('site_name', ''),
                    'site_tagline' => $request->post('site_tagline', ''),
                    'timezone' => $request->post('timezone', ''),
                    'locale' => $request->post('locale', ''),
                ];

                try {
                    if (!$schemaReady || !$administratorSetup instanceof InstallerAdministratorSetup) {
                        throw new InstallationException('Database schema is not ready.');
                    }

                    $administratorSetup->install($input, $requirementsPassed);

                    return Response::redirect('/install');
                } catch (InstallerValidationException $exception) {
                    $status = 422;
                    $message = 'Correct the administrator and site settings fields.';
                    $setupErrors = $exception->errors();
                    $setupValues = array_merge($setupValues, $exception->submittedValues());
                    $currentStep = 'administrator';
                } catch (InstallationException $exception) {
                    $status = 422;
                    $message = $exception->getMessage();
                    $setupErrors['storage'] = $exception->getMessage();
                    $currentStep = 'administrator';
                } catch (\Throwable) {
                    $status = 503;
                    $message = 'Administrator and settings could not be saved.';
                    $setupErrors['storage'] = $message;
                    $currentStep = 'administrator';
                } finally {
                    $input['admin_password'] = '';
                    $input['admin_password_confirmation'] = '';
                    unset($input);
                }
            } else {
                $currentStep = 'database';
                $jsonDatabaseTest = $action === 'test_database'
                    && $request->post('response_mode') === 'json';
                $input = [
                    'host' => $request->post('database_host', ''),
                    'port' => $request->post('database_port', ''),
                    'database' => $request->post('database_name', ''),
                    'username' => $request->post('database_username', ''),
                    'password' => $request->post('database_password', ''),
                ];

                try {
                    if (!is_string($action) || !in_array($action, ['test_database', 'install_database'], true)) {
                        throw new InstallationException('Installer action is invalid.');
                    }

                    $configuration = (new InstallerDatabaseValidator())->validate($input);
                    $values = [
                        'host' => $configuration['host'],
                        'port' => (string) $configuration['port'],
                        'database' => $configuration['database'],
                        'username' => $configuration['username'],
                    ];
                    $probe = new InstallerDatabaseProbe();

                    if ($action === 'install_database') {
                        $setup = new InstallerDatabaseSetup(
                            $probe,
                            new InstallerEnvironmentWriter($basePath . '/.env'),
                            new InstallerSchemaRunner($basePath . '/database/schema.sql'),
                            new InstallationMutex($basePath . '/storage')
                        );
                        $setup->install($configuration, $requirementsPassed);

                        return Response::redirect('/install');
                    } else {
                        $databaseResult = $probe->test($configuration);
                        $message = 'Database connection verified. The database is supported and empty.';
                    }
                } catch (InstallerValidationException $exception) {
                    $status = 422;
                    $message = 'Correct the database configuration fields.';
                    $errors = $exception->errors();
                    $values = $exception->submittedValues();
                } catch (InstallationException $exception) {
                    $status = 422;
                    $message = $exception->getMessage();
                    $errors['connection'] = $exception->getMessage();
                } catch (\Throwable) {
                    $status = 503;
                    $message = 'Database setup could not be completed.';
                    $errors['connection'] = $message;
                } finally {
                    if (isset($configuration) && is_array($configuration)) {
                        $configuration['password'] = '';
                        unset($configuration);
                    }

                    $input['password'] = '';
                }

                if ($jsonDatabaseTest) {
                    $payload = [
                        'ok' => $status < 400 && is_array($databaseResult),
                        'message' => $message,
                        'errors' => $errors,
                        'database' => $databaseResult,
                    ];

                    return Response::content(
                        (string) json_encode($payload, JSON_UNESCAPED_SLASHES),
                        $status,
                        [
                            'Content-Type' => 'application/json; charset=UTF-8',
                            'Cache-Control' => 'no-store',
                        ]
                    );
                }
            }
        }
    }
}

$installerReady = $installerReady && $status < 400;

$steps = [
    [
        'label' => 'Requirements',
        'state' => $requirementsPassed ? 'completed' : 'current',
    ],
    [
        'label' => 'Database',
        'state' => !$requirementsPassed
            ? 'blocked'
            : ($currentStep === 'database' ? 'current' : ($schemaReady ? 'completed' : 'pending')),
    ],
    [
        'label' => 'Administrator & Site',
        'state' => !$requirementsPassed
            ? 'blocked'
            : ($administratorExists ? 'completed' : ($currentStep === 'administrator' ? 'current' : 'pending')),
    ],
    [
        'label' => 'Finalize',
        'state' => !$requirementsPassed
            ? 'blocked'
            : ($currentStep === 'finalize' ? 'current' : 'pending'),
    ],
];

$view = new View($basePath . '/resources/views');

return Response::html($view->render('installer/index', [
    'message' => $message,
    'installerReady' => $installerReady,
    'requirements' => $requirements,
    'requirementsPassed' => $requirementsPassed,
    'csrfToken' => $csrf?->token() ?? '',
    'values' => $values,
    'errors' => $errors,
    'databaseResult' => $databaseResult,
    'schemaReady' => $schemaReady,
    'administratorExists' => $administratorExists,
    'currentStep' => $currentStep,
    'setupValues' => $setupValues,
    'setupErrors' => $setupErrors,
    'finalizationError' => $finalizationError,
    'timezones' => array_values(array_unique(array_merge(['UTC'], timezone_identifiers_list()))),
    'locales' => ['en_US', 'id_ID'],
    'steps' => $steps,
]), $status);
