<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\ModuleDefinition;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleLoader;
use Copot\Core\ModuleRepository;
use Copot\Core\Request;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

final class Batch3ModuleRepository extends ModuleRepository
{
    public function __construct(private array $enabledModules)
    {
    }

    public function enabled(): array
    {
        return $this->enabledModules;
    }
}

final class Batch3ModuleDiscovery extends ModuleDiscovery
{
    public function __construct(
        private array $modules,
        private array $fixtureErrors = []
    ) {
    }

    public function discover(): array
    {
        return $this->modules;
    }

    public function errors(): array
    {
        return $this->fixtureErrors;
    }
}

$assertions = 0;
$temporaryPaths = [];

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temporaryDirectory = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m2-2-batch3-' . $label . '-' . bin2hex(random_bytes(6));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create a temporary test directory.');
    }

    $temporaryPaths[] = $path;

    return $path;
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $candidate = $path . DIRECTORY_SEPARATOR . $entry;

        if (is_dir($candidate) && !is_link($candidate)) {
            $removeDirectory($candidate);
        } else {
            unlink($candidate);
        }
    }

    rmdir($path);
};

$createApplicationRoot = static function (string $path): void {
    foreach (['config', 'modules', 'resources/views'] as $directory) {
        $fullPath = $path . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (!mkdir($fullPath, 0777, true) && !is_dir($fullPath)) {
            throw new RuntimeException('Unable to create an application fixture directory.');
        }
    }

    file_put_contents($path . '/config/database.php', <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'copot_batch3_fixture',
            'username' => 'fixture',
            'password' => 'fixture',
            'charset' => 'utf8mb4',
        ],
    ],
];
PHP
    );
};

$createModule = static function (string $modulesPath, string $name, array $metadata, array $files = []): string {
    $modulePath = $modulesPath . DIRECTORY_SEPARATOR . $name;

    if (!mkdir($modulePath, 0777, true) && !is_dir($modulePath)) {
        throw new RuntimeException('Unable to create a module fixture directory.');
    }

    $metadata = array_merge([
        'name' => $name,
        'title' => ucfirst($name),
        'version' => '1.0.0',
    ], $metadata);

    file_put_contents(
        $modulePath . DIRECTORY_SEPARATOR . 'module.json',
        json_encode($metadata, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
    );

    foreach ($files as $relativePath => $contents) {
        $filePath = $modulePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $directory = dirname($filePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create a module fixture file directory.');
        }

        file_put_contents($filePath, $contents);
    }

    return $modulePath;
};

$findModule = static function (array $modules, string $name): ?ModuleDefinition {
    foreach ($modules as $module) {
        if ($module instanceof ModuleDefinition && $module->name() === $name) {
            return $module;
        }
    }

    return null;
};

$expectListenerFailure = static function (
    ModuleLoader $loader,
    Application $app,
    string $moduleName,
    string $expectedMessage
) use ($assert): void {
    $exception = null;

    try {
        $loader->loadListeners($app);
    } catch (RuntimeException $caught) {
        $exception = $caught;
    }

    $assert($exception instanceof RuntimeException, "Malformed listener contribution [{$moduleName}] was accepted.");
    $assert($exception?->getMessage() === $expectedMessage, "Listener contribution [{$moduleName}] did not use its sanitized error message.");
};

try {
    $applicationRoot = $temporaryDirectory('application');
    $createApplicationRoot($applicationRoot);
    $app = new Application($applicationRoot);
    $secondApp = new Application($applicationRoot);

    $assert($app->events() === $app->events(), 'Application did not retain one dispatcher instance.');
    $assert($app->events() !== $secondApp->events(), 'Two Application instances shared a dispatcher.');

    $modulesPath = $temporaryDirectory('modules');
    $createModule($modulesPath, 'plain', []);
    $createModule($modulesPath, 'alpha', [
        'listeners' => 'listeners.php',
        'routes' => 'routes.php',
    ], [
        'listeners.php' => <<<'PHP'
<?php
if (!isset($app) || !$app instanceof \Copot\Core\Application) {
    throw new RuntimeException('Application scope is unavailable.');
}

return [
    'fixture.order.checked' => static function (object $event): void {
        $event->calls[] = 'alpha';
    },
    'fixture.map.second' => static function (object $event): void {
        $event->calls[] = 'alpha-second';
    },
];
PHP,
        'routes.php' => <<<'PHP'
<?php
$app->router()->get('/fixture-alpha', static fn (): string => 'fixture route loaded');
PHP,
    ]);
    $createModule($modulesPath, 'zeta', [
        'listeners' => 'listeners.php',
    ], [
        'listeners.php' => <<<'PHP'
<?php
return [
    'fixture.order.checked' => static function (object $event): void {
        $event->calls[] = 'zeta';
    },
];
PHP,
    ]);
    $createModule($modulesPath, 'disabled', [
        'listeners' => 'listeners.php',
    ], [
        'listeners.php' => "<?php\nthrow new RuntimeException('Disabled module listener file was loaded.');\n",
    ]);
    $createModule($modulesPath, 'installed', [
        'listeners' => 'listeners.php',
    ], [
        'listeners.php' => "<?php\nthrow new RuntimeException('Merely installed module listener file was loaded.');\n",
    ]);
    $createModule($modulesPath, 'missing-route', [
        'routes' => 'routes.php',
    ]);

    $discovery = new ModuleDiscovery($modulesPath);
    $discovered = $discovery->discover();
    $plain = $findModule($discovered, 'plain');
    $alpha = $findModule($discovered, 'alpha');

    $assert($plain instanceof ModuleDefinition && $plain->listeners() === null, 'Module metadata without listeners was not accepted.');
    $assert($alpha instanceof ModuleDefinition && $alpha->listeners() === 'listeners.php', 'Valid listener metadata was not retained.');
    $assert(($alpha?->toArray()['listeners'] ?? null) === 'listeners.php', 'Listener metadata was not included in the normalized definition.');

    $enabledRepository = new Batch3ModuleRepository([
        ['name' => 'alpha', 'status' => 'enabled'],
        ['name' => 'zeta', 'status' => 'enabled'],
        ['name' => 'missing-route', 'status' => 'enabled'],
    ]);
    $loader = new ModuleLoader($discovery, $enabledRepository);
    $loader->loadListeners($app);

    $listenersProperty = new ReflectionProperty($app->events(), 'listeners');
    $listenersProperty->setAccessible(true);
    $registeredListeners = $listenersProperty->getValue($app->events());
    $assert(
        array_keys($registeredListeners) === ['fixture.order.checked', 'fixture.map.second'],
        'Listener contribution map insertion order was not preserved.'
    );

    $payload = new stdClass();
    $payload->calls = [];
    $app->events()->dispatch('fixture.order.checked', $payload);
    $assert($payload->calls === ['alpha', 'zeta'], 'Enabled modules did not register in repository order.');

    $secondPayload = new stdClass();
    $secondPayload->calls = [];
    $app->events()->dispatch('fixture.map.second', $secondPayload);
    $assert($secondPayload->calls === ['alpha-second'], 'An enabled module listener contribution was not loaded.');

    $loader->loadRoutes($app);
    $routeResponse = $app->run(new Request('GET', '/fixture-alpha'));
    $routeContentProperty = new ReflectionProperty($routeResponse, 'content');
    $routeContentProperty->setAccessible(true);
    $responseContent = $routeContentProperty->getValue($routeResponse);
    $assert($responseContent === 'fixture route loaded', 'Existing enabled-module route loading behavior regressed.');
    $missingRouteResponse = $app->run(new Request('GET', '/missing-route'));
    $missingRouteStatusProperty = new ReflectionProperty($missingRouteResponse, 'status');
    $missingRouteStatusProperty->setAccessible(true);
    $missingRouteStatus = $missingRouteStatusProperty->getValue($missingRouteResponse);
    $assert($missingRouteStatus === 404, 'Missing declared route files must remain skipped by ModuleLoader.');

    $unsafeModulesPath = $temporaryDirectory('unsafe-metadata');
    $createModule($unsafeModulesPath, 'traversal', ['listeners' => '../outside.php']);
    $createModule($unsafeModulesPath, 'absolute', ['listeners' => '/outside.php']);
    $createModule($unsafeModulesPath, 'windows-absolute', ['listeners' => 'C:\\outside.php']);
    $unsafeDiscovery = new ModuleDiscovery($unsafeModulesPath);
    $unsafeDiscovery->discover();
    $unsafeErrors = $unsafeDiscovery->errors();
    $assert(count($unsafeErrors) === 3, 'Unsafe listener metadata paths were not rejected.');

    foreach ($unsafeErrors as $error) {
        $assert(
            ($error['error'] ?? null) === 'Module listeners path must be a safe relative path inside the module folder.',
            'Unsafe listener metadata did not produce the controlled discovery error.'
        );
    }

    $invalidMetadataLoader = new ModuleLoader(
        $unsafeDiscovery,
        new Batch3ModuleRepository([['name' => 'traversal', 'status' => 'enabled']])
    );
    $expectListenerFailure(
        $invalidMetadataLoader,
        $app,
        'traversal',
        'Module [traversal] listener contribution metadata is invalid.'
    );

    $missingPath = $temporaryDirectory('missing');
    $createModule($missingPath, 'missing', ['listeners' => 'listeners.php']);
    $expectListenerFailure(
        new ModuleLoader(new ModuleDiscovery($missingPath), new Batch3ModuleRepository([['name' => 'missing']])),
        $app,
        'missing',
        'Module [missing] listener contribution file is missing.'
    );

    $nonArrayPath = $temporaryDirectory('non-array');
    $createModule($nonArrayPath, 'non-array', ['listeners' => 'listeners.php'], [
        'listeners.php' => "<?php\nreturn 'not-an-array';\n",
    ]);
    $expectListenerFailure(
        new ModuleLoader(new ModuleDiscovery($nonArrayPath), new Batch3ModuleRepository([['name' => 'non-array']])),
        $app,
        'non-array',
        'Module [non-array] listener contribution must return an array.'
    );

    $invalidEventPath = $temporaryDirectory('invalid-event');
    $createModule($invalidEventPath, 'invalid-event', ['listeners' => 'listeners.php'], [
        'listeners.php' => "<?php\nreturn ['Invalid.Event' => static function (object \$event): void {}];\n",
    ]);
    $expectListenerFailure(
        new ModuleLoader(new ModuleDiscovery($invalidEventPath), new Batch3ModuleRepository([['name' => 'invalid-event']])),
        $app,
        'invalid-event',
        'Module [invalid-event] listener contribution contains an invalid event name.'
    );

    $nonCallablePath = $temporaryDirectory('non-callable');
    $createModule($nonCallablePath, 'non-callable', ['listeners' => 'listeners.php'], [
        'listeners.php' => "<?php\nreturn ['fixture.not.callable' => 'not-callable'];\n",
    ]);
    $expectListenerFailure(
        new ModuleLoader(new ModuleDiscovery($nonCallablePath), new Batch3ModuleRepository([['name' => 'non-callable']])),
        $app,
        'non-callable',
        'Module [non-callable] listener contribution map is invalid.'
    );

    $outsideRoot = $temporaryDirectory('outside');
    $outsideModulePath = $outsideRoot . DIRECTORY_SEPARATOR . 'outside';
    mkdir($outsideModulePath);
    file_put_contents($outsideRoot . DIRECTORY_SEPARATOR . 'listeners.php', "<?php\nreturn [];\n");
    $outsideDefinition = new ModuleDefinition(
        name: 'outside',
        title: 'Outside',
        version: '1.0.0',
        path: $outsideModulePath,
        listeners: '../listeners.php'
    );
    $outsideLoader = new ModuleLoader(
        new Batch3ModuleDiscovery([$outsideDefinition]),
        new Batch3ModuleRepository([['name' => 'outside']])
    );
    $expectListenerFailure(
        $outsideLoader,
        $app,
        'outside',
        'Module [outside] listener contribution must stay inside the module folder.'
    );

    $moduleLoaderSource = (string) file_get_contents($basePath . '/app/Core/ModuleLoader.php');
    $bootstrapSource = (string) file_get_contents($basePath . '/bootstrap/app.php');
    $assert(!str_contains($moduleLoaderSource, '->dispatch('), 'Module listener loading dispatches an event.');
    $assert(!str_contains($bootstrapSource, '->dispatch('), 'Application bootstrap dispatches an event.');
    $assert(
        strpos($bootstrapSource, 'loadListeners($app)') < strpos($bootstrapSource, 'loadRoutes($app)'),
        'Listener contributions are not loaded before enabled module routes.'
    );

    echo "Extensibility Batch 3 integration tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
