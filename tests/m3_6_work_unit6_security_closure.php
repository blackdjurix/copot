<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Config;
use Copot\Core\Database;
use Copot\Core\Diagnostics;
use Copot\Core\FrontendThemeContext;
use Copot\Core\FrontendThemeContextContributor;
use Copot\Core\FrontendThemeContextRegistry;
use Copot\Core\ModuleDiscovery;
use Copot\Core\ModuleLoader;
use Copot\Core\ModuleRepository;

$basePath = dirname(__DIR__);
chdir($basePath);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-m36-wu6-' . bin2hex(random_bytes(8));
$modulesRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'modules';
$logRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'project';
mkdir($modulesRoot, 0777, true);
mkdir($logRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);

try {
    $diagnostics = new Diagnostics($logRoot);
    $registry = new FrontendThemeContextRegistry($diagnostics);

    $first = new class implements FrontendThemeContextContributor {
        public function contextKey(): string { return 'collision'; }
        public function contribute(FrontendThemeContext $context): array { return ['winner' => $context->themeId()]; }
    };
    $second = new class implements FrontendThemeContextContributor {
        public function contextKey(): string { return 'collision'; }
        public function contribute(FrontendThemeContext $context): array { return ['winner' => 'wrong']; }
    };
    $failing = new class implements FrontendThemeContextContributor {
        public function contextKey(): string { return 'private_failure'; }
        public function contribute(FrontendThemeContext $context): array { throw new RuntimeException('PRIVATE_REFERENCE secret=credential stack trace repository details'); }
    };

    $registry->register($first);
    $registry->register($second);
    $registry->register($failing);
    $registry->freeze();
    $composedA = $registry->compose(['theme_id' => 'default', 'metadata' => ['supports' => ['navigation_locations' => ['primary']]]]);
    $composedB = $registry->compose(['theme_id' => 'alternate', 'metadata' => ['supports' => ['navigation_locations' => ['secondary']]]]);
    $assert($composedA['collision']['winner'] === 'default', 'First registered context key did not win deterministically.');
    $assert($composedB['collision']['winner'] === 'alternate', 'Frontend context leaked between composition calls.');
    $assert(!isset($composedA['private_failure'], $composedB['private_failure']), 'Contributor failure was not omitted.');
    $logPath = $logRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log';
    $log = is_file($logPath) ? (file_get_contents($logPath) ?: '') : '';
    foreach (['PRIVATE_REFERENCE', 'secret=', 'credential', 'stack trace', 'repository details'] as $unsafe) {
        $assert(!str_contains($log, $unsafe), 'Diagnostics exposed unsafe detail: ' . $unsafe);
    }

    mkdir($modulesRoot . DIRECTORY_SEPARATOR . 'bad-path', 0777, true);
    file_put_contents($modulesRoot . DIRECTORY_SEPARATOR . 'bad-path' . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'bad-path', 'title' => 'Bad path', 'version' => '1.0.0', 'frontend_context' => '../outside.php',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($temporaryRoot . DIRECTORY_SEPARATOR . 'outside.php', '<?php throw new RuntimeException("outside");');
    $discovery = new ModuleDiscovery($modulesRoot);
    $discovery->discover();
    $assert($discovery->errors() !== [], 'Unsafe frontend_context path was accepted.');
    $assert(str_contains((string) ($discovery->errors()[0]['error'] ?? ''), 'safe relative path'), 'Unsafe path rejection was not explicit.');

    $safePath = $modulesRoot . DIRECTORY_SEPARATOR . 'safe-module';
    mkdir($safePath, 0777, true);
    file_put_contents($safePath . DIRECTORY_SEPARATOR . 'module.json', json_encode([
        'name' => 'safe-module', 'title' => 'Safe module', 'version' => '1.0.0', 'frontend_context' => 'context.php',
    ], JSON_THROW_ON_ERROR));
    file_put_contents($safePath . DIRECTORY_SEPARATOR . 'context.php', '<?php throw new RuntimeException("PRIVATE_REFERENCE secret=credential");');
    $safeDiscovery = new ModuleDiscovery($modulesRoot);
    $fakeRepository = new class(new Database(new Config($temporaryRoot . DIRECTORY_SEPARATOR . 'config'))) extends ModuleRepository {
        public function enabled(): array { return [['name' => 'safe-module']]; }
    };
    $loaderRegistry = new FrontendThemeContextRegistry($diagnostics);
    $fakeApp = new class($diagnostics, $loaderRegistry) extends Application {
        public function __construct(private Diagnostics $wu6Diagnostics, private FrontendThemeContextRegistry $wu6Registry) {}
        public function diagnostics(): Diagnostics { return $this->wu6Diagnostics; }
        public function frontendThemeContext(): FrontendThemeContextRegistry { return $this->wu6Registry; }
    };
    (new ModuleLoader($safeDiscovery, $fakeRepository))->loadFrontendContextContributors($fakeApp);
    $safeNames = array_map(static fn ($module): string => $module->name(), $safeDiscovery->discover());
    $assert(in_array('safe-module', $safeNames, true), 'Safe module discovery unexpectedly failed.');
    $assert($loaderRegistry->compose(['theme_id' => 'after-loader']) === [], 'Failed contributor registration was not fail-closed.');
    $log = is_file($logPath) ? (file_get_contents($logPath) ?: '') : '';
    $assert(!str_contains($log, 'PRIVATE_REFERENCE') && !str_contains($log, 'credential'), 'Registration diagnostics exposed contributor details.');

    $routes = file_get_contents($basePath . '/modules/navigation/routes.php') ?: '';
    $assert(substr_count($routes, 'is_scalar') >= 3, 'Navigation Admin does not retain representative scalar payload guards.');
    $assert(str_contains($routes, 'return $app->adminErrors()->response($request, 403);'), 'Navigation Admin forbidden response is not controlled.');

    echo "M3.6 Work Unit 6 security closure passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporaryRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $path) {
        $path->isDir() ? rmdir($path->getPathname()) : unlink($path->getPathname());
    }
    if (is_dir($temporaryRoot)) {
        rmdir($temporaryRoot);
    }
}
