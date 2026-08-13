<?php

use Copot\Core\Admin\AdminUrl;
use Copot\Core\Config;
use Copot\Core\Csrf;
use Copot\Core\Database;
use Copot\Core\DeploymentContext;
use Copot\Core\Env;
use Copot\Core\CommittedLifecycleStateStore;
use Copot\Core\InstallationException;
use Copot\Core\InstallationMutex;
use Copot\Core\InstallerAdministratorSetup;
use Copot\Core\InstallerAdministratorValidator;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerOwnershipProofAssembler;
use Copot\Core\CoreMigrationRegistry;
use Copot\Core\InstallerDatabaseValidator;
use Copot\Core\InstallerFinalizer;
use Copot\Core\InstallerRequirements;
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

$deploymentContext ??= DeploymentContext::forApplicationRoot($basePath);
$basePath = $deploymentContext->appRoot();
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
$requirementsSessionKey = 'installer_requirements_acknowledged';
$requirementsHaveWarnings = count(array_filter(
    $requirements,
    static fn (array $requirement): bool => is_string($requirement['warning'] ?? null) && $requirement['warning'] !== ''
)) > 0;
$requirementsAcknowledged = $sessionReady
    && $session instanceof Session
    && $session->get($requirementsSessionKey, false) === true;
$databaseSessionKey = 'installer_database_staged';
$stagedDatabase = $sessionReady && $session instanceof Session
    ? $session->get($databaseSessionKey, [])
    : [];
$stagedDatabase = is_array($stagedDatabase) ? $stagedDatabase : [];
$databaseStaged = ($stagedDatabase['staged'] ?? false) === true
    && is_array($stagedDatabase['inspection'] ?? null);
$administratorSessionKey = 'installer_administrator_staged';
$stagedAdministrator = $sessionReady && $session instanceof Session
    ? $session->get($administratorSessionKey, [])
    : [];
$stagedAdministrator = is_array($stagedAdministrator) ? $stagedAdministrator : [];
$administratorStaged = ($stagedAdministrator['staged'] ?? false) === true;

if (!$requirementsPassed && $requirementsAcknowledged && $session instanceof Session) {
    $session->remove($requirementsSessionKey);
    $requirementsAcknowledged = false;
}
$installerReady = !$installationStateError && $storageReady && $requirementsPassed;
$status = 200;
$message = $requirementsPassed
    ? 'Requirements are satisfied. Database configuration can be tested.'
    : 'Resolve the failed requirements before continuing.';
$values = [
    'host' => (string) ($stagedDatabase['host'] ?? '127.0.0.1'),
    'port' => (string) ($stagedDatabase['port'] ?? '3306'),
    'database' => (string) ($stagedDatabase['database'] ?? ''),
    'username' => (string) ($stagedDatabase['username'] ?? ''),
    'namespace' => (string) ($stagedDatabase['namespace'] ?? ''),
    'intent' => (string) ($stagedDatabase['intent'] ?? \Copot\Core\InstallerIntent::FRESH),
];
$errors = [];
$databaseResult = is_array($stagedDatabase['inspection'] ?? null) ? $stagedDatabase['inspection'] : null;
$databaseFeedback = null;
$schemaReady = false;
$administratorExists = false;
$administratorSetup = null;
$finalizer = null;
$setupErrors = [];
$finalizationError = null;
$setupValues = [
    'admin_name' => (string) ($stagedAdministrator['admin_name'] ?? ''),
    'admin_email' => (string) ($stagedAdministrator['admin_email'] ?? ''),
    'site_name' => (string) ($stagedAdministrator['site_name'] ?? 'copot'),
    'site_tagline' => (string) ($stagedAdministrator['site_tagline'] ?? ''),
    'timezone' => (string) ($stagedAdministrator['timezone'] ?? 'UTC'),
    'locale' => (string) ($stagedAdministrator['locale'] ?? 'en_US'),
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
        new CommittedLifecycleStateStore($basePath . '/storage'),
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
$requestedStep = is_string($requestedStep) && in_array($requestedStep, ['database', 'requirements', 'administrator', 'finalize'], true) ? $requestedStep : null;
$requirementsReview = false;
if ($requirementsPassed && $requestedStep === 'database' && $session instanceof Session) {
    $session->set($requirementsSessionKey, true);
    $requirementsAcknowledged = true;
}
$forwardStep = ((!$schemaReady && !$databaseStaged) || $requestedStep === 'database')
    ? 'database'
    : ($administratorStaged ? 'finalize' : ($administratorExists ? 'finalize' : 'administrator'));
$currentStep = $forwardStep;

if ($requirementsPassed && $requirementsAcknowledged && $requestedStep === 'requirements') {
    $requirementsReview = true;
    $currentStep = 'requirements';
} elseif ($requirementsPassed && $requirementsAcknowledged && $requestedStep === 'database') {
    $currentStep = 'database';
} elseif ($requirementsPassed && $requirementsAcknowledged && $requestedStep === 'administrator' && $schemaReady) {
    $currentStep = 'administrator';
} elseif ($requirementsPassed && $requirementsAcknowledged && $requestedStep === 'administrator' && !$schemaReady && $databaseStaged) {
    $currentStep = 'administrator';
} elseif ($requirementsPassed && $requirementsAcknowledged && $requestedStep === 'finalize' && $schemaReady && $administratorExists) {
    $currentStep = 'finalize';
}

$requirementsForwardUrl = $forwardStep === 'database'
    ? $deploymentContext->url('/install?step=database')
    : $deploymentContext->url('/install');
$requirementsForwardLabel = match ($forwardStep) {
    'administrator' => 'Administrator & Site',
    'finalize' => 'Review & Install',
    default => 'Database',
};

if ($currentStep === 'finalize' && $requirementsPassed) {
    $message = 'The first administrator and initial settings are ready. Finalize the installation.';
} elseif ($currentStep === 'administrator' && $requirementsPassed && $schemaReady) {
    $message = 'Database schema is ready. Create the first administrator and initial site settings.';
} elseif ($currentStep === 'administrator' && $requirementsPassed && $databaseStaged) {
    $message = '';
} elseif ($requestedStep === 'database' && $requirementsPassed) {
    $message = 'Choose and validate a dedicated empty database.';
}

if (is_array($databaseResult) && $requirementsPassed && $currentStep === 'database') {
    $message = 'Database decision is staged and can be revisited before installation.';
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
            if ($action === 'continue_requirements') {
                if ($session instanceof Session) {
                    $session->set($requirementsSessionKey, true);
                }

                return Response::redirect($deploymentContext->url('/install?step=database'));
            }

            if (!$requirementsAcknowledged) {
                return Response::redirect($deploymentContext->url('/install'));
            }

            if ($action === 'finalize_installation') {
                try {
                    if (!$finalizer instanceof InstallerFinalizer) {
                        throw new InstallationException('Installation prerequisites are not ready.');
                    }

                    $adminUrl = new AdminUrl(new Config($basePath . '/config'));

                    $finalizer->finalize();

                    return Response::redirect($deploymentContext->url($adminUrl->baseUrl()));
                } catch (\Throwable) {
                    $status = 503;
                    $message = 'Installation could not be finalized.';
                    $finalizationError = $currentStep === 'finalize' ? $message : null;
                }
            } elseif (in_array($action, ['stage_administrator', 'create_administrator'], true)) {
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
                if (
                    $input['admin_password'] === ''
                    && $input['admin_password_confirmation'] === ''
                    && is_string($stagedAdministrator['password'] ?? null)
                ) {
                    $input['admin_password'] = $stagedAdministrator['password'];
                    $input['admin_password_confirmation'] = $stagedAdministrator['password'];
                }

                try {
                    if (!$databaseStaged) {
                        throw new InstallationException('Database decision must be staged before Administrator & Site.');
                    }

                    $validated = InstallerAdministratorValidator::validate($input);
                    $stagedAdministrator = [
                        'admin_name' => $validated['name'],
                        'admin_email' => $validated['email'],
                        'password' => $validated['password'],
                        'site_name' => $validated['site_name'],
                        'site_tagline' => $validated['site_tagline'],
                        'timezone' => $validated['timezone'],
                        'locale' => $validated['locale'],
                        'staged' => true,
                    ];
                    if ($session instanceof Session) {
                        $session->set($administratorSessionKey, $stagedAdministrator);
                    }
                    $administratorStaged = true;
                    $setupValues = [
                        'admin_name' => $validated['name'],
                        'admin_email' => $validated['email'],
                        'site_name' => $validated['site_name'],
                        'site_tagline' => $validated['site_tagline'],
                        'timezone' => $validated['timezone'],
                        'locale' => $validated['locale'],
                    ];
                    $message = 'Administrator & Site decision staged. No installation records were created.';

                    return Response::redirect($deploymentContext->url('/install?step=finalize'));
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
                    'namespace' => $request->post('database_namespace', ''),
                ];

                if ($input['password'] === '' && is_string($stagedDatabase['password'] ?? null)) {
                    $input['password'] = $stagedDatabase['password'];
                }

                $stagedDatabase = array_merge($stagedDatabase, [
                    'host' => (string) $input['host'],
                    'port' => (string) $input['port'],
                    'database' => (string) $input['database'],
                    'username' => (string) $input['username'],
                    'password' => (string) $input['password'],
                    'namespace' => (string) $input['namespace'],
                    'intent' => (string) $request->post('installer_intent', \Copot\Core\InstallerIntent::FRESH),
                    'inspection' => null,
                ]);
                if ($session instanceof Session) {
                    $session->set($databaseSessionKey, $stagedDatabase);
                }

                try {
                    if (!is_string($action) || !in_array($action, ['test_database', 'stage_database'], true)) {
                        throw new InstallationException('Installer action is invalid.');
                    }

                    $configuration = (new InstallerDatabaseValidator())->validate($input);
                    $values = [
                        'host' => $configuration['host'],
                        'port' => (string) $configuration['port'],
                        'database' => $configuration['database'],
                        'username' => $configuration['username'],
                        'namespace' => $configuration['namespace'] ?? '',
                        'intent' => $request->post('installer_intent', \Copot\Core\InstallerIntent::FRESH),
                    ];
                    $intent = $values['intent'];
                    $probe = new InstallerDatabaseProbe(
                        5,
                        new InstallerOwnershipProofAssembler(
                            $basePath . '/storage',
                            new CoreMigrationRegistry('copot-core-current', [])
                        )
                    );

                    $inspection = $probe->inspect($configuration);
                    $planner = new \Copot\Core\InstallerRoutingPlanner();
                    $eligibleIntents = $planner->eligibleIntents($inspection['occupancy']);
                    $routing = null;
                    $namespaceResult = (new \Copot\Core\InstallerNamespaceAnalyzer())->analyze(
                        $inspection['occupancy']->objects(),
                        (string) ($configuration['namespace'] ?? ''),
                        $inspection['occupancy']
                    );
                    $effectiveIntent = in_array($intent, $eligibleIntents, true)
                        ? $intent
                        : ($eligibleIntents[0] ?? null);
                    if (
                        $action === 'test_database'
                        && in_array($effectiveIntent, [\Copot\Core\InstallerIntent::FRESH, \Copot\Core\InstallerIntent::COEXIST], true)
                        && !$namespaceResult->usable()
                    ) {
                        throw new InstallationException('The selected Database namespace is already in use. Choose another namespace and test again.');
                    }
                    if ($action === 'stage_database') {
                        $routing = $planner->plan(
                            $inspection['occupancy'],
                            $intent,
                            array_key_exists('namespace', $configuration) ? (string) $configuration['namespace'] : null
                        );
                    } elseif (!in_array($intent, $eligibleIntents, true)) {
                        $values['intent'] = $eligibleIntents[0] ?? '';
                    }
                    $resolvedNamespace = $routing?->namespace() ?? (string) ($configuration['namespace'] ?? '');
                    $databaseResult = array_merge($inspection['server'], [
                        'occupancy' => $inspection['occupancy']->classification(),
                        'namespace' => $resolvedNamespace,
                        'route' => $routing?->route(),
                        'eligible_intents' => $eligibleIntents,
                        'namespace_availability' => $namespaceResult->availability(),
                        'warnings' => array_merge($inspection['occupancy']->warnings(), $routing?->warnings() ?? []),
                        'objects' => $inspection['occupancy']->objects(),
                        'copot_namespaces' => $inspection['occupancy']->copotNamespaces(),
                        'decision_evidence' => [
                            'classification' => $inspection['occupancy']->classification(),
                            'objects' => $inspection['occupancy']->objects(),
                            'copot_namespaces' => $inspection['occupancy']->copotNamespaces(),
                        ],
                    ]);
                    $stagedDatabase = [
                        'host' => $configuration['host'],
                        'port' => (string) $configuration['port'],
                        'database' => $configuration['database'],
                        'username' => $configuration['username'],
                        'password' => $configuration['password'],
                        'namespace' => $resolvedNamespace,
                        'intent' => $intent,
                        'inspection' => $databaseResult,
                        'staged' => $action === 'stage_database',
                    ];
                    if ($session instanceof Session) {
                        $session->set($databaseSessionKey, $stagedDatabase);
                    }
                    $values['namespace'] = $resolvedNamespace;
                    $message = $action === 'stage_database'
                        ? 'Database decision staged. No COPOT schema or tables were created.'
                        : 'Database connection and installer routing verified.';
                    if ($action === 'test_database') {
                        $databaseFeedback = ['kind' => 'success', 'message' => $message];
                    }
                    if ($action === 'stage_database') {
                        return Response::redirect($deploymentContext->url('/install?step=administrator'));
                    }
                } catch (InstallerValidationException $exception) {
                    $status = 422;
                    $message = 'Correct the database configuration fields.';
                    $errors = $exception->errors();
                    $values = $exception->submittedValues();
                    $databaseFeedback = ['kind' => 'error', 'message' => $message];
                } catch (InstallationException $exception) {
                    $status = 422;
                    $message = $exception->getMessage();
                    $errors['connection'] = $exception->getMessage();
                    $databaseFeedback = ['kind' => 'error', 'message' => $message];
                } catch (\Throwable) {
                    $status = 503;
                    $message = 'Database setup could not be completed.';
                    $errors['connection'] = $message;
                    $databaseFeedback = ['kind' => 'error', 'message' => $message];
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

if (!$requirementsPassed || !$requirementsAcknowledged) {
$currentStep = 'requirements';
}

$statusKind = $status >= 400 || !$requirementsPassed
    ? 'error'
    : (is_array($databaseResult) ? 'success' : ($requirementsHaveWarnings ? 'warning' : 'info'));

if ($currentStep === 'requirements' && !$requirementsPassed) {
    $message = 'Resolve the failed requirements before continuing.';
} elseif ($currentStep === 'requirements' && $requirementsReview) {
    $message = 'Review the completed requirements, then return to the current installer step.';
}

$installerReady = $installerReady && $status < 400;
$databaseContextualState = $currentStep === 'database'
    && is_array($databaseFeedback);
$showStatus = ($status >= 400 || !$requirementsPassed || $requirementsHaveWarnings)
    && !$databaseContextualState;

$steps = [
    [
        'label' => 'Requirements',
        'state' => !$requirementsPassed || !$requirementsAcknowledged ? 'current' : 'completed',
    ],
    [
        'label' => 'Database',
        'state' => !$requirementsPassed || !$requirementsAcknowledged
            ? 'blocked'
            : ($databaseStaged || $schemaReady ? 'completed' : 'current'),
    ],
    [
        'label' => 'Administrator & Site',
        'state' => !$requirementsPassed || !$requirementsAcknowledged || (!$schemaReady && !$databaseStaged)
            ? 'blocked'
            : ($administratorStaged || $administratorExists ? 'completed' : ($forwardStep === 'administrator' ? 'current' : 'pending')),
    ],
    [
        'label' => 'Review & Install',
        'state' => !$requirementsPassed || !$requirementsAcknowledged || (!$administratorStaged && (!$schemaReady || !$administratorExists))
            ? 'blocked'
            : ($forwardStep === 'finalize' ? 'current' : 'pending'),
    ],
];

$displayStep = $currentStep;
foreach ($steps as &$step) {
    $step['displayState'] = $step['label'] === match ($displayStep) {
        'administrator' => 'Administrator & Site',
        'finalize' => 'Review & Install',
        'database' => 'Database',
        default => 'Requirements',
    }
        ? 'current'
        : (($step['state'] ?? '') === 'completed'
            ? 'completed'
            : (($step['state'] ?? '') === 'blocked' && !in_array($step['label'], ['Requirements', 'Database'], true)
                ? 'pending'
                : (($step['state'] ?? '') === 'current' ? 'pending' : ($step['state'] ?? 'pending'))));
}
unset($step);

$stepReviewUrls = [
    'Requirements' => $deploymentContext->url('/install?step=requirements'),
    'Database' => $deploymentContext->url('/install?step=database'),
    'Administrator & Site' => $deploymentContext->url('/install?step=administrator'),
];

foreach ($steps as &$step) {
    if (($step['state'] ?? '') === 'completed' && isset($stepReviewUrls[$step['label']])) {
        $step['reviewUrl'] = $stepReviewUrls[$step['label']];
    }
}
unset($step);

$view = new View($basePath . '/resources/views');

return Response::html($view->render('installer/index', [
    'message' => $message,
    'installerReady' => $installerReady,
    'databaseStaged' => $databaseStaged,
    'requirements' => $requirements,
    'requirementsPassed' => $requirementsPassed,
    'statusKind' => $statusKind,
    'csrfToken' => $csrf?->token() ?? '',
    'values' => $values,
    'errors' => $errors,
    'databaseResult' => $databaseResult,
    'databaseFeedback' => $databaseFeedback,
    'schemaReady' => $schemaReady,
    'administratorExists' => $administratorExists,
        'currentStep' => $currentStep,
        'forwardStep' => $forwardStep,
        'displayStep' => $displayStep,
        'showStatus' => $showStatus,
    'requirementsAcknowledged' => $requirementsAcknowledged,
    'requirementsReview' => $requirementsReview,
    'requirementsReviewUrl' => $deploymentContext->url('/install?step=requirements'),
    'requirementsForwardUrl' => $requirementsForwardUrl,
    'requirementsForwardLabel' => $requirementsForwardLabel,
    'setupValues' => $setupValues,
    'setupErrors' => $setupErrors,
    'finalizationError' => $finalizationError,
    'timezones' => array_values(array_unique(array_merge(['UTC'], timezone_identifiers_list()))),
    'locales' => ['en_US', 'id_ID'],
    'steps' => $steps,
    'url' => fn (string $path): string => $deploymentContext->url($path),
]), $status);
