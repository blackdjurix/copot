<?php

declare(strict_types=1);

use Copot\Core\Config;
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
} catch (Throwable $exception) {
    fwrite(STDOUT, 'WU2 runtime staging skipped: database unavailable.' . PHP_EOL);
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
    $namespace = 'wu2rt_' . bin2hex(random_bytes(4));
} else {
    fwrite(STDOUT, 'WU2 runtime staging skipped: configured database has no safe test route.' . PHP_EOL);
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
    $GLOBALS['_GET'] = str_contains($uri, '?step=database')
        ? ['step' => 'database']
        : (str_contains($uri, '?step=administrator') ? ['step' => 'administrator'] : []);
    $GLOBALS['_POST'] = $post;
    $GLOBALS['_FILES'] = [];
    ob_start();
    require dirname(__DIR__) . '/public/index.php';
    return (string) ob_get_clean();
};

http_response_code(200);
$databasePage = $request('GET', '/install?step=database');
$assert(http_response_code() === 200 && str_contains($databasePage, '<h2>Database</h2>'), 'Configured installer entrypoint did not render Database.');
preg_match('/name="_token" value="([^"]+)"/', $databasePage, $matches);
$csrf = $matches[1] ?? '';
$assert($csrf !== '', 'Runtime Database page did not expose a CSRF token.');

$post = [
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
$testPost = $post;
$testPost['action'] = 'test_database';
$testPost['response_mode'] = 'json';
http_response_code(200);
$testResponse = $request('POST', '/install', $testPost);
$testPayload = json_decode($testResponse, true);
$assert(http_response_code() === 200 && is_array($testPayload) && ($testPayload['ok'] ?? false) === true, 'Test Database did not complete as an inspection-only runtime action.');
$assert(($testPayload['database']['occupancy'] ?? null) !== null, 'Test Database did not return occupancy inspection evidence.');
$assert($originalEnvironment === (is_file($base . '/.env') ? hash_file('sha256', $base . '/.env') : false), 'Test Database changed the environment file.');

http_response_code(200);
$stageResponse = $request('POST', '/install', $post);
$assert(http_response_code() === 302 && $stageResponse === '', 'Database Next did not return the staged forward response.');
$assert(is_array($_SESSION['installer_database_staged'] ?? null), 'Database staging state was not retained in the runtime session.');
$assert(($_SESSION['installer_database_staged']['staged'] ?? false) === true, 'Database Next did not advance the staged lifecycle state.');

$after = $probe->inspect($configuration);
$assert($before['occupancy']->objects() === $after['occupancy']->objects(), 'Database Next changed the inspected database object set.');
$assert($originalEnvironment === (is_file($base . '/.env') ? hash_file('sha256', $base . '/.env') : false), 'Database Next changed the environment file.');

http_response_code(200);
$revisited = $request('GET', '/install?step=database');
$assert(http_response_code() === 200 && str_contains($revisited, 'Database decision is staged'), 'Database revisit did not return the staged Database state.');
$assert(str_contains($revisited, htmlspecialchars($configuration['database'], ENT_QUOTES, 'UTF-8')), 'Database revisit did not retain the database value.');
$assert($configuration['password'] === '' || !str_contains($revisited, 'value="' . htmlspecialchars($configuration['password'], ENT_QUOTES, 'UTF-8') . '"'), 'Database password was rendered back into the form.');

http_response_code(200);
$nextPhase = $request('GET', '/install?step=administrator');
$assert(http_response_code() === 200 && str_contains($nextPhase, 'inputs will be available in the next work unit'), 'Staged Database Next did not reach the WU3 handoff boundary.');
$assert(!str_contains($nextPhase, 'name="admin_password"'), 'WU3 Administrator form was exposed during WU2 runtime validation.');

fwrite(STDOUT, "WU2 runtime staging assertions: {$assertions}" . PHP_EOL);
