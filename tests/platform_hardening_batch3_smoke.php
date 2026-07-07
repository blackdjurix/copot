<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Diagnostics;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ServerErrorResponse;
use Copot\Core\View;
use Copot\Core\ViewRenderer;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

ini_set('display_errors', '1');

$assertions = 0;
$temporaryPaths = [];

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;

    if ($expected !== $actual) {
        throw new RuntimeException(
            $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
        );
    }
};

$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty($response, $property))->getValue($response);
};

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path) || is_link($path)) {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
        }

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
            @unlink($candidate);
        }
    }

    @rmdir($path);
};

$temporaryRoot = static function (string $label) use (&$temporaryPaths): string {
    $path = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR . 'copot-m2-4-batch3-' . $label . '-' . bin2hex(random_bytes(6));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create a temporary Batch 3 test directory.');
    }

    $temporaryPaths[] = $path;

    return $path;
};

$directoryEntries = static function (string $path): array {
    if (!is_dir($path)) {
        return [];
    }

    $entries = array_values(array_filter(
        scandir($path) ?: [],
        static fn (string $entry): bool => $entry !== '.' && $entry !== '..'
    ));
    sort($entries, SORT_STRING);

    return $entries;
};

$decodeLog = static function (string $path): array {
    $records = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $record = json_decode($line, true, 32, JSON_THROW_ON_ERROR);

        if (!is_array($record)) {
            throw new RuntimeException('Server error diagnostic is not a JSON object.');
        }

        $records[] = $record;
    }

    return $records;
};

$extractReference = static function (string $body): ?string {
    return preg_match('/ERR-[A-F0-9]{24}/', $body, $matches) === 1
        ? $matches[0]
        : null;
};

$runPhp = static function (string $script): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' -d display_errors=1 '
        . escapeshellarg($script)
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($command, $output, $exitCode);

    return [$exitCode, implode(PHP_EOL, $output)];
};

$writeApplicationConfig = static function (string $root): void {
    mkdir($root . DIRECTORY_SEPARATOR . 'config', 0777, true);
    file_put_contents(
        $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
        "<?php\nreturn ['default'=>'mysql','connections'=>['mysql'=>['driver'=>'mysql','host'=>'127.0.0.1','port'=>'1','database'=>'fixture','username'=>'fixture','password'=>'fixture','charset'=>'utf8mb4']]];\n"
    );
};

$repoLogEntriesBefore = $directoryEntries($basePath . '/storage/logs');

try {
    $responseRoot = $temporaryRoot('response');
    mkdir($responseRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
    $responseDiagnostics = new Diagnostics($responseRoot);
    $secretMessage = 'DB_PASSWORD=server-secret mysql:host=private C:\\private\\failure.php';
    $serverResponse = ServerErrorResponse::fromThrowable(
        new RuntimeException($secretMessage),
        $responseDiagnostics,
        'application.response.failure',
        ['component' => 'application', 'operation' => 'response', 'method' => 'GET', 'path' => '/failure']
    );
    $serverBody = (string) $responseValue($serverResponse, 'content');
    $serverHeaders = $responseValue($serverResponse, 'headers');
    $serverReference = $extractReference($serverBody);
    $assertSame(500, $responseValue($serverResponse, 'status'), 'Unexpected server response did not default to 500.');
    $assert(is_string($serverReference), 'Successful server-error logging did not expose a safe reference.');
    $assertSame('no-store', $serverHeaders['Cache-Control'] ?? null, 'Server error response is cacheable.');
    $assertSame('nosniff', $serverHeaders['X-Content-Type-Options'] ?? null, 'Server error response lacks nosniff.');
    $assert(!str_contains($serverBody, $secretMessage), 'Raw exception message leaked into a server response.');
    $assert(!str_contains($serverBody, 'DB_PASSWORD'), 'Credential label leaked into a server response.');

    $responseLogPath = $responseRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log';
    $responseRecords = $decodeLog($responseLogPath);
    $assertSame(1, count($responseRecords), 'Server error response did not write one diagnostic record.');
    $assertSame($serverReference, $responseRecords[0]['reference'] ?? null, 'Response reference does not match its diagnostic.');
    $assertSame(500, $responseRecords[0]['context']['status'] ?? null, 'Default diagnostic status is not 500.');
    $assert(!str_contains((string) file_get_contents($responseLogPath), 'server-secret'), 'Raw exception message leaked into diagnostics.');

    $pdoDefault = ServerErrorResponse::fromThrowable(
        new PDOException('database unavailable secret'),
        $responseDiagnostics,
        'application.pdo.failure'
    );
    $assertSame(500, $responseValue($pdoDefault, 'status'), 'PDOException was implicitly mapped to 503.');

    $explicitUnavailable = ServerErrorResponse::fromThrowable(
        new RuntimeException('controlled availability fixture'),
        $responseDiagnostics,
        'application.availability.failure',
        ['component' => 'application', 'operation' => 'availability'],
        ServerErrorResponse::SERVICE_UNAVAILABLE
    );
    $unavailableHeaders = $responseValue($explicitUnavailable, 'headers');
    $assertSame(503, $responseValue($explicitUnavailable, 'status'), 'Explicit availability failure did not use 503.');
    $assertSame('no-store', $unavailableHeaders['Cache-Control'] ?? null, '503 response is cacheable.');

    $invalidReferenceResponse = ServerErrorResponse::response(500, '<script>alert(1)</script>');
    $invalidReferenceBody = (string) $responseValue($invalidReferenceResponse, 'content');
    $assert(!str_contains($invalidReferenceBody, 'script'), 'Untrusted reference entered server-error markup.');

    $applicationRoot = $temporaryRoot('application');
    $writeApplicationConfig($applicationRoot);
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
    $application = new Application($applicationRoot);
    $application->router()->get('/explode', static function (): Response {
        echo 'PARTIAL_ROUTE_SECRET';
        ob_start();
        echo 'NESTED_ROUTE_SECRET';

        throw new RuntimeException('ROUTE_EXCEPTION_SECRET C:\\private\\route.php');
    });
    $application->router()->get('/pdo', static function (): Response {
        throw new PDOException('PDO_ROUTE_SECRET');
    });
    $application->router()->get('/direct-output', static function (): Response {
        echo 'DIRECT_OUTPUT_SECRET';

        return Response::html('should not be returned');
    });
    $application->router()->get('/expected', static fn (): Response => Response::html('Expected validation.', 422));
    $application->router()->get('/trusted-string', static fn (): string => '<strong>Trusted route HTML</strong>');

    $callerLevel = ob_get_level();
    ob_start();
    $callerBufferLevel = ob_get_level();
    $dispatchFailure = $application->run(new Request('GET', '/explode'));
    $assertSame($callerBufferLevel, ob_get_level(), 'Application dispatch removed a caller-owned output buffer.');
    $dispatchLeak = (string) ob_get_clean();
    $assertSame($callerLevel, ob_get_level(), 'Caller output-buffer level was not restored.');
    $assertSame('', $dispatchLeak, 'Partial route output escaped the dispatch boundary.');
    $dispatchBody = (string) $responseValue($dispatchFailure, 'content');
    $dispatchReference = $extractReference($dispatchBody);
    $assertSame(500, $responseValue($dispatchFailure, 'status'), 'RuntimeException route did not return 500.');
    $assert(is_string($dispatchReference), 'Dispatch failure did not return a safe reference.');

    foreach (['PARTIAL_ROUTE_SECRET', 'NESTED_ROUTE_SECRET', 'ROUTE_EXCEPTION_SECRET', 'C:\\private'] as $forbidden) {
        $assert(!str_contains($dispatchBody, $forbidden), "Dispatch response leaked [{$forbidden}].");
    }

    $applicationLogPath = $applicationRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log';
    $applicationRecords = $decodeLog($applicationLogPath);
    $matchingRecords = array_values(array_filter(
        $applicationRecords,
        static fn (array $record): bool => ($record['reference'] ?? null) === $dispatchReference
    ));
    $assertSame(1, count($matchingRecords), 'Dispatch reference does not have exactly one diagnostic record.');
    $assertSame('application.dispatch.failure', $matchingRecords[0]['event'] ?? null, 'Dispatch event name is incorrect.');
    $assertSame('/explode', $matchingRecords[0]['context']['path'] ?? null, 'Dispatch path context is incorrect.');

    $pdoRouteResponse = $application->run(new Request('GET', '/pdo'));
    $assertSame(500, $responseValue($pdoRouteResponse, 'status'), 'PDO route was implicitly mapped to 503.');

    $beforeExpectedRecords = count($decodeLog($applicationLogPath));
    $expectedResponse = $application->run(new Request('GET', '/expected'));
    $assertSame(422, $responseValue($expectedResponse, 'status'), 'Expected 422 response status changed.');
    $assertSame('Expected validation.', $responseValue($expectedResponse, 'content'), 'Expected response body changed.');
    $assertSame(
        $beforeExpectedRecords,
        count($decodeLog($applicationLogPath)),
        'Expected response created an unexpected diagnostic.'
    );

    ob_start();
    $directOutputResponse = $application->run(new Request('GET', '/direct-output'));
    $directOutputLeak = (string) ob_get_clean();
    $assertSame('', $directOutputLeak, 'Direct successful route output escaped the dispatch boundary.');
    $assertSame(500, $responseValue($directOutputResponse, 'status'), 'Direct route output was not rejected.');
    $assert(!str_contains((string) $responseValue($directOutputResponse, 'content'), 'DIRECT_OUTPUT_SECRET'), 'Direct output entered error response.');

    $trustedStringResponse = $application->run(new Request('GET', '/trusted-string'));
    $assertSame(200, $responseValue($trustedStringResponse, 'status'), 'Trusted scalar route response changed status.');
    $assertSame(
        '<strong>Trusted route HTML</strong>',
        $responseValue($trustedStringResponse, 'content'),
        'Trusted scalar route HTML contract changed.'
    );

    $missingLogRoot = $temporaryRoot('missing-log');
    $writeApplicationConfig($missingLogRoot);
    mkdir($missingLogRoot . DIRECTORY_SEPARATOR . 'storage', 0777, true);
    $missingLogApplication = new Application($missingLogRoot);
    $missingLogApplication->router()->get('/explode', static function (): Response {
        throw new RuntimeException('MISSING_LOG_SECRET');
    });
    $missingLogResponse = $missingLogApplication->run(new Request('GET', '/explode'));
    $missingLogBody = (string) $responseValue($missingLogResponse, 'content');
    $assertSame(500, $responseValue($missingLogResponse, 'status'), 'Missing log sink changed failure status.');
    $assertSame(null, $extractReference($missingLogBody), 'Failed logging exposed a dead reference.');
    $assert(!str_contains($missingLogBody, 'MISSING_LOG_SECRET'), 'Missing-sink response leaked exception detail.');

    $viewRoot = $temporaryRoot('view');
    mkdir($viewRoot . DIRECTORY_SEPARATOR . 'views', 0777, true);
    file_put_contents($viewRoot . '/views/success.php', '<?php echo "safe view";');
    file_put_contents(
        $viewRoot . '/views/failure.php',
        '<?php echo "PARTIAL_VIEW_SECRET"; ob_start(); echo "NESTED_VIEW_SECRET"; throw new RuntimeException("VIEW_EXCEPTION_SECRET");'
    );
    file_put_contents(
        $viewRoot . '/views/unbalanced.php',
        '<?php echo "PARTIAL_UNBALANCED_SECRET"; ob_start(); echo "NESTED_UNBALANCED_SECRET";'
    );
    $view = new View($viewRoot . DIRECTORY_SEPARATOR . 'views');
    $assertSame('safe view', $view->render('success'), 'Successful View rendering changed output.');

    foreach (['failure', 'unbalanced'] as $viewName) {
        $outerLevel = ob_get_level();
        ob_start();
        $callerOwnedLevel = ob_get_level();
        $caught = null;

        try {
            $view->render($viewName);
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        $assert($caught instanceof Throwable, "View fixture [{$viewName}] did not fail.");
        $assertSame($callerOwnedLevel, ob_get_level(), "View fixture [{$viewName}] damaged caller buffer level.");
        $viewLeak = (string) ob_get_clean();
        $assertSame($outerLevel, ob_get_level(), "View fixture [{$viewName}] did not restore exact initial level.");
        $assertSame('', $viewLeak, "View fixture [{$viewName}] leaked partial output.");
    }

    $rendererRoot = $temporaryRoot('view-renderer');
    $rendererSuccess = $rendererRoot . DIRECTORY_SEPARATOR . 'success.php';
    $rendererFailure = $rendererRoot . DIRECTORY_SEPARATOR . 'failure.php';
    file_put_contents($rendererSuccess, '<?php echo "safe theme view";');
    file_put_contents(
        $rendererFailure,
        '<?php echo "PARTIAL_THEME_SECRET"; ob_start(); echo "NESTED_THEME_SECRET"; throw new RuntimeException("THEME_EXCEPTION_SECRET");'
    );
    $rendererReflection = new ReflectionClass(ViewRenderer::class);
    $renderer = $rendererReflection->newInstanceWithoutConstructor();
    $renderMethod = $rendererReflection->getMethod('renderPhpFile');
    $renderMethod->setAccessible(true);
    $assertSame(
        'safe theme view',
        $renderMethod->invoke($renderer, $rendererSuccess, []),
        'Successful ViewRenderer output changed.'
    );
    $rendererInitialLevel = ob_get_level();
    ob_start();
    $rendererCallerLevel = ob_get_level();
    $rendererCaught = null;

    try {
        $renderMethod->invoke($renderer, $rendererFailure, []);
    } catch (Throwable $exception) {
        $rendererCaught = $exception;
    }

    $assert($rendererCaught instanceof Throwable, 'ViewRenderer failure fixture did not fail.');
    $assertSame($rendererCallerLevel, ob_get_level(), 'ViewRenderer damaged caller buffer level.');
    $rendererLeak = (string) ob_get_clean();
    $assertSame($rendererInitialLevel, ob_get_level(), 'ViewRenderer did not restore exact initial level.');
    $assertSame('', $rendererLeak, 'ViewRenderer leaked partial output.');

    $preAutoloadRoot = $temporaryRoot('pre-autoload');
    mkdir($preAutoloadRoot . DIRECTORY_SEPARATOR . 'public');
    mkdir($preAutoloadRoot . DIRECTORY_SEPARATOR . 'bootstrap');
    file_put_contents(
        $preAutoloadRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
        file_get_contents($basePath . '/public/index.php')
    );
    file_put_contents(
        $preAutoloadRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php',
        '<?php echo "PRE_AUTOLOAD_PARTIAL"; throw new RuntimeException("PRE_AUTOLOAD_SECRET");'
    );
    [$preAutoloadExit, $preAutoloadOutput] = $runPhp(
        $preAutoloadRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'
    );
    $assertSame(0, $preAutoloadExit, 'Pre-autoload emergency fixture exited unsuccessfully.');
    $assert(str_contains($preAutoloadOutput, '<h1>Server Error</h1>'), 'Pre-autoload emergency response is missing.');
    $assert(!str_contains($preAutoloadOutput, 'PRE_AUTOLOAD_SECRET'), 'Pre-autoload exception leaked.');
    $assert(!str_contains($preAutoloadOutput, 'PRE_AUTOLOAD_PARTIAL'), 'Pre-autoload partial output leaked.');
    $assertSame(null, $extractReference($preAutoloadOutput), 'Pre-autoload response exposed an unavailable reference.');

    $bootstrapRoot = $temporaryRoot('bootstrap');
    mkdir($bootstrapRoot . DIRECTORY_SEPARATOR . 'public');
    mkdir($bootstrapRoot . DIRECTORY_SEPARATOR . 'bootstrap');
    mkdir($bootstrapRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
    file_put_contents(
        $bootstrapRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php',
        file_get_contents($basePath . '/public/index.php')
    );
    file_put_contents(
        $bootstrapRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'autoload.php',
        '<?php require ' . var_export($basePath . '/bootstrap/autoload.php', true) . ';'
    );
    file_put_contents(
        $bootstrapRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php',
        '<?php echo "BOOTSTRAP_PARTIAL"; ob_start(); echo "BOOTSTRAP_NESTED"; throw new RuntimeException("BOOTSTRAP_SECRET C:\\\\private\\\\bootstrap.php");'
    );
    file_put_contents(
        $bootstrapRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'installed.lock',
        json_encode([
            'installed_at' => gmdate(DATE_ATOM),
            'version' => '0.11.0',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL
    );
    [$bootstrapExit, $bootstrapOutput] = $runPhp(
        $bootstrapRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php'
    );
    $assertSame(0, $bootstrapExit, 'Post-autoload bootstrap fixture exited unsuccessfully.');
    $bootstrapReference = $extractReference($bootstrapOutput);
    $assert(is_string($bootstrapReference), 'Post-autoload bootstrap response lacks a reference.');
    $assert(!str_contains($bootstrapOutput, 'BOOTSTRAP_SECRET'), 'Bootstrap exception leaked.');
    $assert(!str_contains($bootstrapOutput, 'BOOTSTRAP_PARTIAL'), 'Bootstrap partial output leaked.');
    $assert(!str_contains($bootstrapOutput, 'BOOTSTRAP_NESTED'), 'Nested bootstrap output leaked.');
    $bootstrapLogPath = $bootstrapRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log';
    $bootstrapRecords = $decodeLog($bootstrapLogPath);
    $assertSame(1, count($bootstrapRecords), 'Bootstrap failure did not write exactly one diagnostic.');
    $assertSame($bootstrapReference, $bootstrapRecords[0]['reference'] ?? null, 'Bootstrap response/log reference mismatch.');
    $assertSame('runtime.bootstrap.failure', $bootstrapRecords[0]['event'] ?? null, 'Bootstrap event name is incorrect.');

    $indexSource = (string) file_get_contents($basePath . '/public/index.php');
    $routerSource = (string) file_get_contents($basePath . '/app/Core/Router.php');
    $responseSource = (string) file_get_contents($basePath . '/app/Core/Response.php');
    $webRoutesSource = (string) file_get_contents($basePath . '/routes/web.php');
    $contentRoutesSource = (string) file_get_contents($basePath . '/modules/content/routes.php');
    $taxonomyRoutesSource = (string) file_get_contents($basePath . '/modules/taxonomy/routes.php');
    $exampleRoutesSource = (string) file_get_contents($basePath . '/modules/example/routes.php');
    $adminLayoutSource = (string) file_get_contents($basePath . '/resources/views/admin/layout.php');
    $themeLayoutSource = (string) file_get_contents($basePath . '/themes/default/layouts/app.php');
    $assert(str_contains($indexSource, 'runtime.bootstrap.failure'), 'Bootstrap boundary event is missing.');
    $assert(str_contains($indexSource, 'ServerErrorResponse::fromThrowable'), 'Bootstrap boundary lacks sanitized response integration.');
    $assert(!str_contains($indexSource, 'set_exception_handler'), 'Batch 3 added a global exception handler.');
    $assert(!str_contains($indexSource, 'set_error_handler'), 'Batch 3 added a global error handler.');
    $assert(!str_contains($indexSource, 'register_shutdown_function'), 'Batch 3 added a shutdown handler framework.');
    $assert(!str_contains($routerSource, 'ServerErrorResponse'), 'Batch 3 redesigned Router error handling.');
    $assert(!str_contains($responseSource, 'ServerErrorResponse'), 'Batch 3 redesigned Response error handling.');
    $assert(!str_contains($webRoutesSource, 'Theme rendering error.'), 'Home route still swallows rendering failures.');
    $assert(!str_contains($contentRoutesSource, 'Theme rendering error.'), 'Content route still swallows rendering failures.');

    foreach ([$contentRoutesSource, $taxonomyRoutesSource, $exampleRoutesSource] as $moduleRoutesSource) {
        $assert(str_contains($moduleRoutesSource, 'initialOutputLevel'), 'Module renderer lacks targeted buffer ownership.');
        $assert(!str_contains($moduleRoutesSource, 'new View('), 'Module renderer was refactored into the View abstraction.');
    }

    $assert(str_contains($adminLayoutSource, '<?= $content ?? \'\' ?>'), 'Admin trusted-fragment contract changed.');
    $assert(str_contains($themeLayoutSource, '<?= $content ?? \'\' ?>'), 'Theme trusted-fragment contract changed.');
    $assert(!is_file($basePath . '/app/Core/SafeHtml.php'), 'Batch 3 introduced a SafeHtml abstraction.');

    $repoLogEntriesAfter = $directoryEntries($basePath . '/storage/logs');
    $assertSame(
        $repoLogEntriesBefore,
        $repoLogEntriesAfter,
        'Batch 3 tests wrote to the repository log directory.'
    );

    echo "M2.4 Batch 3 application boundary smoke tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
