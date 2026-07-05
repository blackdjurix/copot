<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\Diagnostics;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$skips = [];
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

$removeDirectory = static function (string $path) use (&$removeDirectory): void {
    if (!is_dir($path) || is_link($path)) {
        if (is_link($path) || is_file($path)) {
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
        . DIRECTORY_SEPARATOR . 'copot-m2-4-batch2-' . $label . '-' . bin2hex(random_bytes(6));

    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create a temporary diagnostics test directory.');
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
            throw new RuntimeException('Diagnostics record is not a JSON object.');
        }

        $records[] = $record;
    }

    return $records;
};

$repoLogEntriesBefore = $directoryEntries($basePath . '/storage/logs');

try {
    $root = $temporaryRoot('success');
    $logDirectory = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    mkdir($logDirectory, 0777, true);
    $fixtureDirectory = $root . DIRECTORY_SEPARATOR . 'fixture';
    mkdir($fixtureDirectory, 0777, true);
    $fixturePath = $fixtureDirectory . DIRECTORY_SEPARATOR . 'failure.php';
    file_put_contents(
        $fixturePath,
        "<?php\nreturn new RuntimeException('DB_PASSWORD=fixture-secret mysql:host=private client-logo.png');\n"
    );

    $diagnostics = new Diagnostics($root);
    $outsideException = new RuntimeException(
        'DB_PASSWORD=fixture-secret mysql:host=private;password=fixture '
        . 'Authorization: Bearer fixture Cookie: sid=fixture '
        . 'C:\\private\\application.php /var/www/private/application.php client-logo.png'
    );
    $firstReference = $diagnostics->report('application.unexpected', $outsideException, [
        'component' => 'runtime',
        'operation' => 'dispatch',
        'method' => 'post',
        'path' => '/safe/path?token=fixture-secret#fragment',
        'status' => 500,
        'slot' => 'logo',
        'password' => 'fixture-secret',
        'authorization' => 'Bearer fixture',
        'nested' => ['unsafe'],
    ]);
    $assert(is_string($firstReference), 'Successful diagnostics report did not return a reference.');
    $assert(
        preg_match('/^ERR-[A-F0-9]{24}$/', (string) $firstReference) === 1,
        'Diagnostics reference format is invalid.'
    );

    $insideException = require $fixturePath;
    $assert($insideException instanceof RuntimeException, 'Project-relative exception fixture is invalid.');
    $secondReference = $diagnostics->report('application.fixture', $insideException, [
        'component' => 'runtime',
        'operation' => 'fixture',
    ]);
    $assert(is_string($secondReference), 'Second diagnostics report did not return a reference.');
    $assert($secondReference !== $firstReference, 'Diagnostics references are not unique.');

    $warningWritten = $diagnostics->warning(
        'storage.degraded',
        "token=fixture-secret\nC:\\private\\storage.log",
        [
            'component' => 'storage',
            'operation' => 'write',
            'path' => '/reset/token=fixture-secret?authorization=fixture',
            'status' => 503,
            'slot' => 'favicon',
        ]
    );
    $assert($warningWritten, 'Controlled diagnostics warning was not written.');

    $logPath = $logDirectory . DIRECTORY_SEPARATOR . 'copot.log';
    $assert(is_file($logPath), 'Diagnostics log file was not created in the fixed private location.');
    $records = $decodeLog($logPath);
    $assertSame(3, count($records), 'Diagnostics did not append exactly one JSON line per record.');

    $first = $records[0];
    $assertSame('error', $first['level'] ?? null, 'Unexpected diagnostics level is incorrect.');
    $assertSame('application.unexpected', $first['event'] ?? null, 'Unexpected diagnostics event is incorrect.');
    $assertSame($firstReference, $first['reference'] ?? null, 'Stored diagnostics reference is incorrect.');
    $assertSame(RuntimeException::class, $first['exception'] ?? null, 'Stored exception class is incorrect.');
    $assertSame(
        'Unexpected application failure.',
        $first['summary'] ?? null,
        'Unexpected diagnostics summary is not controlled.'
    );
    $assert(!isset($first['source']), 'Exception outside the project root exposed a source location.');
    $assertSame('POST', $first['context']['method'] ?? null, 'Request method was not normalized.');
    $assertSame('/safe/path', $first['context']['path'] ?? null, 'Query or fragment entered diagnostics path context.');
    $assertSame(500, $first['context']['status'] ?? null, 'HTTP status context is incorrect.');
    $assert(!isset($first['context']['password']), 'Unknown sensitive context key was retained.');
    $assert(!isset($first['context']['authorization']), 'Authorization context was retained.');
    $assert(!isset($first['context']['nested']), 'Non-scalar context was retained.');

    $second = $records[1];
    $assert(
        isset($second['source'])
        && preg_match('#^fixture/failure\.php:[1-9][0-9]*$#', (string) $second['source']) === 1,
        'Project source location was not converted to a relative path.'
    );

    $warning = $records[2];
    $assertSame('warning', $warning['level'] ?? null, 'Warning diagnostics level is incorrect.');
    $assert(!isset($warning['reference']), 'Warning unexpectedly received an error reference.');
    $assertSame('[redacted]', $warning['summary'] ?? null, 'Sensitive warning summary was not redacted.');
    $assertSame('[redacted]', $warning['context']['path'] ?? null, 'Sensitive path context was not redacted.');

    foreach ($records as $record) {
        $assert(
            isset($record['timestamp'])
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) $record['timestamp']) === 1,
            'Diagnostics timestamp is not UTC ISO-8601.'
        );
    }

    $logContents = (string) file_get_contents($logPath);

    foreach ([
        'fixture-secret',
        'mysql:host',
        'Authorization:',
        'Cookie:',
        'client-logo.png',
        'C:\\private',
        '/var/www/private',
        str_replace('\\', '/', $root),
    ] as $forbidden) {
        $assert(!str_contains($logContents, $forbidden), "Sensitive diagnostics value leaked [{$forbidden}].");
    }

    $recordCount = count($records);
    $assertSame(
        null,
        $diagnostics->report('Invalid Event', $outsideException),
        'Invalid event name produced an error reference.'
    );
    $assert(!$diagnostics->warning('Invalid Event', 'Controlled warning.'), 'Invalid warning event was accepted.');
    $assertSame(
        $recordCount,
        count($decodeLog($logPath)),
        'Invalid event wrote a diagnostics record.'
    );

    $failureCases = [];

    $missingRoot = $temporaryRoot('missing');
    mkdir($missingRoot . DIRECTORY_SEPARATOR . 'storage');
    $failureCases['missing directory'] = new Diagnostics($missingRoot);

    $fileRoot = $temporaryRoot('directory-is-file');
    mkdir($fileRoot . DIRECTORY_SEPARATOR . 'storage');
    file_put_contents($fileRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 'not a directory');
    $failureCases['log directory is a file'] = new Diagnostics($fileRoot);

    $targetRoot = $temporaryRoot('target-is-directory');
    mkdir($targetRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
    mkdir($targetRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'copot.log');
    $failureCases['log file is a directory'] = new Diagnostics($targetRoot);

    $runtimeWarnings = [];
    set_error_handler(static function (int $severity, string $message) use (&$runtimeWarnings): bool {
        if ((error_reporting() & $severity) !== 0) {
            $runtimeWarnings[] = $message;
        }

        return true;
    });
    ob_start();

    try {
        foreach ($failureCases as $label => $unavailableDiagnostics) {
            $assertSame(
                null,
                $unavailableDiagnostics->report('application.unavailable', $outsideException),
                "Unavailable diagnostics case returned a reference [{$label}]."
            );
            $assert(
                !$unavailableDiagnostics->warning('storage.unavailable', 'Controlled warning.'),
                "Unavailable diagnostics warning succeeded [{$label}]."
            );
        }
    } finally {
        $unexpectedOutput = (string) ob_get_clean();
        restore_error_handler();
    }

    $assertSame('', $unexpectedOutput, 'Unavailable diagnostics emitted response output.');
    $assertSame([], $runtimeWarnings, 'Unavailable diagnostics emitted unsuppressed PHP warnings.');

    $symlinkRoot = $temporaryRoot('symlink');
    $symlinkTarget = $temporaryRoot('symlink-target');
    mkdir($symlinkRoot . DIRECTORY_SEPARATOR . 'storage');
    mkdir($symlinkTarget . DIRECTORY_SEPARATOR . 'logs');
    $logLink = $symlinkRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';

    if (@symlink($symlinkTarget . DIRECTORY_SEPARATOR . 'logs', $logLink)) {
        $symlinkDiagnostics = new Diagnostics($symlinkRoot);
        $assertSame(
            null,
            $symlinkDiagnostics->report('application.symlink', $outsideException),
            'Symlinked diagnostics directory was accepted.'
        );
    } else {
        $skips[] = 'Symlinked log directory check: symlink creation is unavailable.';
    }

    $unwritableRoot = $temporaryRoot('unwritable');
    $unwritableDirectory = $unwritableRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    mkdir($unwritableDirectory, 0777, true);
    @chmod($unwritableDirectory, 0555);

    if (!is_writable($unwritableDirectory)) {
        $unwritableDiagnostics = new Diagnostics($unwritableRoot);
        $assertSame(
            null,
            $unwritableDiagnostics->report('application.unwritable', $outsideException),
            'Unwritable diagnostics directory returned a reference.'
        );
    } else {
        $skips[] = 'Unwritable log directory check: platform still reports the chmod target writable.';
    }

    @chmod($unwritableDirectory, 0777);

    $unwritableFileRoot = $temporaryRoot('unwritable-file');
    $unwritableFileDirectory = $unwritableFileRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    mkdir($unwritableFileDirectory, 0777, true);
    $unwritableFile = $unwritableFileDirectory . DIRECTORY_SEPARATOR . 'copot.log';
    file_put_contents($unwritableFile, 'existing record' . "\n");
    @chmod($unwritableFile, 0444);

    if (!is_writable($unwritableFile)) {
        $unwritableFileDiagnostics = new Diagnostics($unwritableFileRoot);
        $assertSame(
            null,
            $unwritableFileDiagnostics->report('application.unwritable', $outsideException),
            'Unwritable diagnostics file returned a reference.'
        );
        $assertSame(
            'existing record' . "\n",
            file_get_contents($unwritableFile),
            'Unwritable diagnostics file was modified.'
        );
    } else {
        $skips[] = 'Unwritable log file check: platform still reports the chmod target writable.';
    }

    @chmod($unwritableFile, 0666);

    $applicationRoot = $temporaryRoot('application');
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'config', 0777, true);
    mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'storage', 0777, true);
    file_put_contents(
        $applicationRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
        "<?php\nreturn ['default'=>'mysql','connections'=>['mysql'=>['driver'=>'mysql','host'=>'127.0.0.1','port'=>'1','database'=>'fixture','username'=>'fixture','password'=>'fixture','charset'=>'utf8mb4']]];\n"
    );
    $application = new Application($applicationRoot);
    $secondApplication = new Application($applicationRoot);
    $assert(
        $application->diagnostics() === $application->diagnostics(),
        'Application did not retain one request-scoped Diagnostics instance.'
    );
    $assert(
        $application->diagnostics() !== $secondApplication->diagnostics(),
        'Application instances shared Diagnostics state.'
    );

    $diagnosticsSource = (string) file_get_contents($basePath . '/app/Core/Diagnostics.php');
    $indexSource = (string) file_get_contents($basePath . '/public/index.php');
    $routerSource = (string) file_get_contents($basePath . '/app/Core/Router.php');
    $adminRoutesSource = (string) file_get_contents($basePath . '/routes/admin.php');
    $assert(!str_contains($diagnosticsSource, 'getMessage('), 'Diagnostics reads raw Throwable messages.');
    $assert(!str_contains($diagnosticsSource, 'error_log('), 'Diagnostics adds a secondary PHP logging sink.');
    $assert(!str_contains($diagnosticsSource, 'mkdir('), 'Diagnostics creates its log directory at runtime.');
    $assert(
        str_contains($diagnosticsSource, 'is_link($this->storageDirectory)'),
        'Diagnostics no longer rejects a symlinked storage directory.'
    );
    $assert(
        str_contains($diagnosticsSource, 'is_link($this->logDirectory)'),
        'Diagnostics no longer rejects a symlinked log directory.'
    );
    $assert(
        str_contains($diagnosticsSource, 'is_link($this->logPath)'),
        'Diagnostics no longer rejects a symlinked log file.'
    );
    $assert(!str_contains($indexSource, 'set_exception_handler'), 'Batch 2 added a global exception handler.');
    $assert(!str_contains($indexSource, 'set_error_handler'), 'Batch 2 added a global error handler.');
    $assert(!str_contains($routerSource, 'Diagnostics'), 'Batch 2 changed Router diagnostics behavior.');
    $assert(!str_contains($adminRoutesSource, 'Diagnostics'), 'Batch 2 changed Admin error rendering behavior.');

    $repoLogEntriesAfter = $directoryEntries($basePath . '/storage/logs');
    $assertSame(
        $repoLogEntriesBefore,
        $repoLogEntriesAfter,
        'Focused diagnostics tests wrote to the repository log directory.'
    );

    echo "M2.4 Batch 2 minimal diagnostics smoke tests passed ({$assertions} assertions)." . PHP_EOL;

    foreach ($skips as $skip) {
        echo "SKIP: {$skip}" . PHP_EOL;
    }
} finally {
    foreach (array_reverse($temporaryPaths) as $path) {
        if (is_dir($path) && !is_link($path)) {
            @chmod($path, 0777);
        }

        $removeDirectory($path);
    }
}
