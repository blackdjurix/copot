<?php

declare(strict_types=1);

use Copot\Core\Env;
use Copot\Core\InstallerDatabaseProbe;
use Copot\Core\InstallerIntent;
use Copot\Core\InstallerRoutingPlanner;

$base = dirname(__DIR__);
require $base . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

Env::load($base . '/.env');
$runtimeDatabaseOverride = getenv('COPOT_WU2_TEST_DATABASE');
if (is_string($runtimeDatabaseOverride) && $runtimeDatabaseOverride !== '') {
    $_ENV['DB_DATABASE'] = $runtimeDatabaseOverride;
}
$configuration = [
    'host' => (string) Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => (string) Env::get('DB_DATABASE', ''),
    'username' => (string) Env::get('DB_USERNAME', ''),
    'password' => (string) Env::get('DB_PASSWORD', ''),
    'namespace' => '',
];

try {
    $probe = new InstallerDatabaseProbe();
    $before = $probe->inspect($configuration);
} catch (Throwable) {
    fwrite(STDOUT, 'WU3 runtime staging skipped: database unavailable.' . PHP_EOL);
    exit(0);
}

$occupancy = $before['occupancy'];
$intent = InstallerIntent::FRESH;
$namespace = '';

if ($occupancy->classification() === 'copot' && count($occupancy->copotNamespaces()) === 1) {
    $intent = InstallerIntent::ADOPT;
    $namespace = $occupancy->copotNamespaces()[0];
} elseif ($occupancy->isEmpty()) {
    $intent = InstallerIntent::FRESH;
} elseif ($occupancy->classification() === 'foreign_only') {
    $intent = InstallerIntent::COEXIST;
    $namespace = 'wu3rt_' . bin2hex(random_bytes(4));
} else {
    fwrite(STDOUT, 'WU3 runtime staging skipped: configured database has no safe test route.' . PHP_EOL);
    exit(0);
}

$configuration['namespace'] = $namespace;
(new InstallerRoutingPlanner())->plan($occupancy, $intent, $namespace);
$originalEnvironment = is_file($base . '/.env') ? hash_file('sha256', $base . '/.env') : false;

$_SERVER['COPOT_APP_ROOT'] = $base;
$_SERVER['COPOT_PUBLIC_ROOT'] = $base . '/public';
$_SERVER['COPOT_BASE_PATH'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SESSION = [];
session_save_path(sys_get_temp_dir());

$request = static function (string $method, string $uri, array $post = []): string {
    $GLOBALS['_SERVER']['REQUEST_METHOD'] = $method;
    $GLOBALS['_SERVER']['REQUEST_URI'] = $uri;
    $GLOBALS['_GET'] = match (true) {
        str_contains($uri, '?step=database') => ['step' => 'database'],
        str_contains($uri, '?step=administrator') => ['step' => 'administrator'],
        default => [],
    };
    $GLOBALS['_POST'] = $post;
    $GLOBALS['_FILES'] = [];
    ob_start();
    require dirname(__DIR__) . '/public/index.php';

    return (string) ob_get_clean();
};

http_response_code(200);
$databasePage = $request('GET', '/install?step=database');
preg_match('/name="_token" value="([^"]+)"/', $databasePage, $matches);
$csrf = $matches[1] ?? '';
$assert(http_response_code() === 200 && $csrf !== '', 'Runtime Database page did not expose a CSRF token.');

$databasePost = [
    '_token' => $csrf,
    'action' => 'stage_database',
    'database_host' => $configuration['host'],
    'database_port' => (string) $configuration['port'],
    'database_name' => $configuration['database'],
    'database_username' => $configuration['username'],
    'database_password' => $configuration['password'],
    'database_namespace' => $configuration['namespace'],
    'installer_intent' => $intent,
];
http_response_code(200);
$databaseStageResponse = $request('POST', '/install', $databasePost);
$assert(http_response_code() === 302 && $databaseStageResponse === '', 'Database staging did not reach the WU3 boundary.');
$assert(($GLOBALS['_SESSION']['installer_database_staged']['staged'] ?? false) === true, 'Database staging state was not retained.');

$administratorPage = $request('GET', '/install?step=administrator');
preg_match('/name="_token" value="([^"]+)"/', $administratorPage, $matches);
$csrf = $matches[1] ?? $csrf;
$assert(http_response_code() === 200 && str_contains($administratorPage, 'name="action" value="stage_administrator"'), 'WU3 Administrator form did not render after Database staging.');

$administratorPost = [
    '_token' => $csrf,
    'action' => 'stage_administrator',
    'admin_name' => '',
    'admin_email' => 'invalid',
    'admin_password' => 'short',
    'admin_password_confirmation' => 'different',
    'site_name' => '',
    'site_tagline' => '',
    'timezone' => 'invalid',
    'locale' => 'xx_XX',
];
http_response_code(200);
$invalidResponse = $request('POST', '/install', $administratorPost);
$assert(http_response_code() === 422 && str_contains($invalidResponse, 'Correct the administrator and site settings fields.'), 'Invalid Administrator/Site input did not fail safely.');
$assert(empty($GLOBALS['_SESSION']['installer_administrator_staged']['staged']), 'Invalid Administrator/Site input created staged state.');

preg_match('/name="_token" value="([^"]+)"/', $invalidResponse, $matches);
$administratorPost = [
    '_token' => $matches[1] ?? $csrf,
    'action' => 'stage_administrator',
    'admin_name' => 'WU3 Administrator',
    'admin_email' => 'wu3@example.test',
    'admin_password' => 'SafePassword123!',
    'admin_password_confirmation' => 'SafePassword123!',
    'site_name' => 'WU3 Site',
    'site_tagline' => 'Staged only',
    'timezone' => 'UTC',
    'locale' => 'en_US',
];
http_response_code(200);
$stageResponse = $request('POST', '/install', $administratorPost);
$assert(http_response_code() === 302 && $stageResponse === '', 'Valid Administrator/Site input did not stage successfully.');
$assert(($GLOBALS['_SESSION']['installer_administrator_staged']['staged'] ?? false) === true, 'Administrator/Site staged state was not retained.');
$assert(($GLOBALS['_SESSION']['installer_administrator_staged']['password'] ?? '') === 'SafePassword123!', 'Staged password was not retained server-side.');

$reviewPage = $request('GET', '/install?step=finalize');
$assert(http_response_code() === 200 && str_contains($reviewPage, 'Review &amp; Install'), 'WU3 did not reach Review & Install.');

$revisited = $request('GET', '/install?step=administrator');
$assert(http_response_code() === 200 && str_contains($revisited, 'value="WU3 Administrator"'), 'Administrator name did not survive revisit.');
$assert(str_contains($revisited, 'value="WU3 Site"'), 'Site name did not survive revisit.');
$assert(!str_contains($revisited, 'value="SafePassword123!"'), 'Staged password was rendered back into the form.');
$assert(substr_count($revisited, 'id="admin_password"') === 1 && !str_contains($revisited, 'id="admin_password" name="admin_password" type="password" minlength="10" required'), 'Revisited staged password field still requires browser re-entry.');
$assert(!str_contains($revisited, 'id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="10" required'), 'Revisited password confirmation field still requires browser re-entry.');
$revisitCsrf = $matches[1] ?? $csrf;
preg_match('/name="_token" value="([^"]+)"/', $revisited, $matches);
$revisitCsrf = $matches[1] ?? $revisitCsrf;
$sameStatePost = $administratorPost;
$sameStatePost['_token'] = $revisitCsrf;
$sameStatePost['site_tagline'] = 'Changed without password re-entry';
$sameStatePost['admin_password'] = '';
$sameStatePost['admin_password_confirmation'] = '';
http_response_code(200);
$sameStateResponse = $request('POST', '/install', $sameStatePost);
$assert(http_response_code() === 302 && ($GLOBALS['_SESSION']['installer_administrator_staged']['password'] ?? '') === 'SafePassword123!', 'Blank password revisit did not preserve the staged password.');

$failedReplacement = $sameStatePost;
$failedReplacement['_token'] = $revisitCsrf;
$failedReplacement['admin_password'] = 'replacement-only';
$failedReplacement['admin_password_confirmation'] = '';
http_response_code(200);
$failedReplacementResponse = $request('POST', '/install', $failedReplacement);
$assert(http_response_code() === 422 && ($GLOBALS['_SESSION']['installer_administrator_staged']['password'] ?? '') === 'SafePassword123!', 'Invalid replacement password mutated the staged Administrator state.');
$assert($before['occupancy']->objects() === $probe->inspect($configuration)['occupancy']->objects(), 'WU3 staging changed the Database object set.');
$assert($originalEnvironment === (is_file($base . '/.env') ? hash_file('sha256', $base . '/.env') : false), 'WU3 staging changed the environment file.');

fwrite(STDOUT, "WU3 runtime staging assertions: {$assertions}" . PHP_EOL);
