<?php

declare(strict_types=1);

use Copot\Core\Application;
use Copot\Core\SiteFormatter;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$temporaryPaths = [];

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$assertSame = static function (mixed $expected, mixed $actual, string $message) use ($assert): void {
    $assert(
        $actual === $expected,
        $message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.'
    );
};

$expectException = static function (
    string $exceptionClass,
    callable $operation,
    string $message
) use ($assert): void {
    $caught = null;

    try {
        $operation();
    } catch (Throwable $exception) {
        $caught = $exception;
    }

    $assert($caught instanceof $exceptionClass, $message);
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

$applicationRoot = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'copot-m2-3-batch2-'
    . bin2hex(random_bytes(6));

if (!mkdir($applicationRoot . DIRECTORY_SEPARATOR . 'config', 0777, true)) {
    throw new RuntimeException('Unable to create the application fixture directory.');
}

$temporaryPaths[] = $applicationRoot;

file_put_contents(
    $applicationRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php',
    <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '1',
            'database' => 'copot_batch2_fixture',
            'username' => 'fixture',
            'password' => 'fixture',
            'charset' => 'utf8mb4',
        ],
    ],
];
PHP
);

$originalTimezone = date_default_timezone_get();
$originalNumericLocale = setlocale(LC_NUMERIC, '0');
$originalTimeLocale = setlocale(LC_TIME, '0');

try {
    $application = new Application($applicationRoot);
    $secondApplication = new Application($applicationRoot);

    $assert(
        $application->formatter() === $application->formatter(),
        'Application did not retain one formatter instance.'
    );
    $assert(
        $application->formatter() !== $secondApplication->formatter(),
        'Application instances shared a formatter.'
    );

    $instant = new DateTimeImmutable('2026-07-02T01:15:00+00:00');
    $english = new SiteFormatter('en_US', 'America/New_York', 'm/d/Y', 'h:i A');
    $indonesian = new SiteFormatter('id_ID', 'Asia/Jakarta', 'd/m/Y', 'H:i');

    $assertSame('07/01/2026', $english->formatDate($instant), 'English date formatting is incorrect.');
    $assertSame('09:15 PM', $english->formatTime($instant), 'English time formatting is incorrect.');
    $assertSame(
        '07/01/2026 09:15 PM',
        $english->formatDateTime($instant),
        'English date-time formatting is incorrect.'
    );
    $assertSame('02/07/2026', $indonesian->formatDate($instant), 'Indonesian date formatting is incorrect.');
    $assertSame('08:15', $indonesian->formatTime($instant), 'Indonesian time formatting is incorrect.');
    $assertSame(
        '02/07/2026 08:15',
        $indonesian->formatDateTime($instant),
        'Indonesian date-time formatting is incorrect.'
    );

    $assertSame('1,234,567', $english->formatInteger(1234567), 'English integer formatting is incorrect.');
    $assertSame('1.234.567', $indonesian->formatInteger(1234567), 'Indonesian integer formatting is incorrect.');
    $assertSame('-1,234,567', $english->formatInteger(-1234567), 'Negative integer formatting is incorrect.');
    $assertSame('1,234.00', $english->formatDecimal(1234), 'Integer decimal formatting is incorrect.');
    $assertSame('1,234.50', $english->formatDecimal(1234.5), 'English decimal formatting is incorrect.');
    $assertSame('1.234,50', $indonesian->formatDecimal(1234.5), 'Indonesian decimal formatting is incorrect.');
    $assertSame('1,234.568', $english->formatDecimal(1234.5678, 3), 'Decimal precision is incorrect.');
    $assertSame('-9,876.50', $english->formatDecimal(-9876.5), 'Negative decimal formatting is incorrect.');
    $assertSame('-9.877', $indonesian->formatNumber(-9876.5), 'Negative integer rounding is incorrect.');

    $unsupported = new SiteFormatter('fr_FR', 'UTC', 'Y-m-d', 'H:i');
    $assertSame(
        '1,234.50',
        $unsupported->formatDecimal(1234.5),
        'Unsupported locale did not fall back deterministically to en_US.'
    );

    $expectException(
        InvalidArgumentException::class,
        static fn (): SiteFormatter => new SiteFormatter('en_US', 'Not/A_Timezone', 'Y-m-d', 'H:i'),
        'Invalid timezone was accepted.'
    );
    $expectException(
        InvalidArgumentException::class,
        static fn (): SiteFormatter => new SiteFormatter('en_US', 'UTC', 'invalid', 'H:i'),
        'Invalid date format was accepted.'
    );
    $expectException(
        InvalidArgumentException::class,
        static fn (): SiteFormatter => new SiteFormatter('en_US', 'UTC', 'Y-m-d', 'invalid'),
        'Invalid time format was accepted.'
    );
    $expectException(
        InvalidArgumentException::class,
        static fn (): string => $english->formatDecimal(1.2, -1),
        'Negative precision was accepted.'
    );
    $expectException(
        InvalidArgumentException::class,
        static fn (): string => $english->formatDecimal(1.2, 7),
        'Precision above the contract maximum was accepted.'
    );
    $expectException(
        InvalidArgumentException::class,
        static fn (): string => $english->formatDecimal(INF),
        'Infinite input was accepted.'
    );
    $expectException(
        TypeError::class,
        static fn (): string => $english->formatDate('2026-07-02'),
        'Non-DateTime input was accepted.'
    );

    $stableDateTime = $indonesian->formatDateTime($instant);
    $stableNumber = $indonesian->formatDecimal(1234.5);

    date_default_timezone_set('Pacific/Honolulu');
    setlocale(LC_NUMERIC, 'C');
    setlocale(LC_TIME, 'C');

    $assertSame(
        $stableDateTime,
        $indonesian->formatDateTime($instant),
        'Formatter output changed with the server timezone or time locale.'
    );
    $assertSame(
        $stableNumber,
        $indonesian->formatDecimal(1234.5),
        'Formatter output changed with the server numeric locale.'
    );

    echo "M2.3 Batch 2 formatting smoke tests passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    date_default_timezone_set($originalTimezone);

    if (is_string($originalNumericLocale)) {
        setlocale(LC_NUMERIC, $originalNumericLocale);
    }

    if (is_string($originalTimeLocale)) {
        setlocale(LC_TIME, $originalTimeLocale);
    }

    foreach (array_reverse($temporaryPaths) as $path) {
        $removeDirectory($path);
    }
}
