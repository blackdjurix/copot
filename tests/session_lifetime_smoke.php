<?php

declare(strict_types=1);

use Copot\Core\Config;
use Copot\Core\Session;

$basePath = dirname(__DIR__);
require $basePath . '/bootstrap/autoload.php';

$assertions = 0;
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    $assertions++;

    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true) . '.');
    }
};

$oldAppEnv = $_ENV['APP_ENV'] ?? null;
$oldAppEnvProcess = getenv('APP_ENV');
$oldGcLifetime = ini_get('session.gc_maxlifetime');
$sessionPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-session-lifetime-' . bin2hex(random_bytes(5));
mkdir($sessionPath, 0777, true);

$setEnvironment = static function (string $environment): void {
    $_ENV['APP_ENV'] = $environment;
    putenv('APP_ENV=' . $environment);
};

try {
    $setEnvironment('local');
    $localConfig = require $basePath . '/config/session.php';
    $assertSame(43200, $localConfig['lifetime'], 'Local session lifetime is incorrect.');

    $setEnvironment('production');
    $nonLocalConfig = require $basePath . '/config/session.php';
    $assertSame(120, $nonLocalConfig['lifetime'], 'Non-local session default changed.');

    session_save_path($sessionPath);
    $setEnvironment('local');
    session_id('copotlocal' . bin2hex(random_bytes(4)));
    (new Session(new Config($basePath . '/config')))->start();
    $assertSame(2592000, (int) ini_get('session.gc_maxlifetime'), 'Local GC lifetime is incorrect.');
    $assertSame(2592000, (int) session_get_cookie_params()['lifetime'], 'Local cookie and GC lifetimes diverged.');
    session_destroy();

    $setEnvironment('production');
    session_id('copotnonlocal' . bin2hex(random_bytes(4)));
    (new Session(new Config($basePath . '/config')))->start();
    $assertSame(7200, (int) ini_get('session.gc_maxlifetime'), 'Non-local GC lifetime changed.');
    $assertSame(7200, (int) session_get_cookie_params()['lifetime'], 'Non-local cookie and GC lifetimes diverged.');

} finally {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    ini_set('session.gc_maxlifetime', (string) $oldGcLifetime);

    if ($oldAppEnv === null) {
        unset($_ENV['APP_ENV']);
    } else {
        $_ENV['APP_ENV'] = $oldAppEnv;
    }
    if ($oldAppEnvProcess === false) {
        putenv('APP_ENV');
    } else {
        putenv('APP_ENV=' . $oldAppEnvProcess);
    }

    foreach (glob($sessionPath . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($sessionPath);
}

echo "Session lifetime smoke tests passed ({$assertions} assertions)." . PHP_EOL;
