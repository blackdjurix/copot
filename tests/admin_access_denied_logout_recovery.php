<?php

declare(strict_types=1);

use Copot\Core\PasswordHasher;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);

chdir($basePath);
session_save_path(sys_get_temp_dir());
session_id('copotadminrecovery' . bin2hex(random_bytes(5)));

/** @var \Copot\Core\Application $app */
$app = require $basePath . '/bootstrap/app.php';
$connection = $app->database()->connection();
$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$responseValue = static function (Response $response, string $property): mixed {
    return (new ReflectionProperty($response, $property))->getValue($response);
};

$adminBase = $app->adminUrl()->baseUrl();
$logoutUrl = $app->adminUrl()->childUrl('logout');
$sessionKey = (string) $app->config()->get('auth.session_key', '_copot_user_id');
$suffix = bin2hex(random_bytes(8));

$connection->beginTransaction();

try {
    $guestError = $app->run(new Request('GET', $app->adminUrl()->childUrl('missing-recovery-fixture')));
    $guestBody = (string) $responseValue($guestError, 'content');
    $assert((int) $responseValue($guestError, 'status') === 404, 'Guest fixture must receive standalone 404.');
    $assert(!str_contains($guestBody, 'Sign out'), 'Guest standalone errors must not expose session recovery.');

    $statement = $connection->prepare(
        "INSERT INTO users (name, email, password_hash, status, created_at, updated_at)
        VALUES (:name, :email, :password_hash, 'active', NOW(), NOW())"
    );
    $statement->execute([
        'name' => 'Denied Admin Recovery Fixture',
        'email' => "denied-admin-recovery-{$suffix}@example.test",
        'password_hash' => (new PasswordHasher())->make('Denied recovery fixture password'),
    ]);
    $userId = (int) $connection->lastInsertId();

    $app->session()->set($sessionKey, $userId);
    $csrfToken = $app->session()->csrfToken();
    $forbidden = $app->run(new Request('GET', $adminBase));
    $status = (int) $responseValue($forbidden, 'status');
    $body = (string) $responseValue($forbidden, 'content');

    $assert($status === 403, 'Authenticated user without admin.access must receive 403.');
    $assert(!str_contains($body, 'admin-shell'), 'Denied user must not receive the Admin shell.');
    $assert(str_contains($body, '<form method="post"'), 'Standalone 403 must provide a POST form.');
    $assert(
        str_contains($body, 'action="' . htmlspecialchars($logoutUrl, ENT_QUOTES, 'UTF-8') . '"'),
        'Standalone 403 logout must use the configured Admin logout URL.'
    );
    $assert(str_contains($body, 'name="_token"'), 'Standalone 403 logout must include a CSRF field.');
    $assert(
        str_contains($body, 'value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '"'),
        'Standalone 403 logout must include the active CSRF token.'
    );
    $assert(str_contains($body, '>Sign out</button>'), 'Standalone 403 must label its recovery action.');

    $invalidLogout = $app->run(new Request('POST', $logoutUrl, [], ['_token' => 'invalid-token']));
    $assert((int) $responseValue($invalidLogout, 'status') === 419, 'Recovery logout must require valid CSRF.');
    $assert($app->session()->get($sessionKey) === $userId, 'Invalid CSRF must not clear the user session.');

    $logout = $app->run(new Request('POST', $logoutUrl, [], ['_token' => $csrfToken]));
    $assert((int) $responseValue($logout, 'status') === 302, 'Recovery logout must redirect.');
    $headers = (array) $responseValue($logout, 'headers');
    $assert(($headers['Location'] ?? null) === $adminBase, 'Recovery logout must return to Admin login.');
    $assert($app->session()->get($sessionKey) === null, 'Recovery logout must clear the user session.');
    $assert(!$app->auth()->check(), 'Recovery logout must clear the authenticated user.');

    $login = $app->run(new Request('GET', $adminBase));
    $assert((int) $responseValue($login, 'status') === 200, 'Admin login must be reachable after logout.');
    $assert(
        str_contains((string) $responseValue($login, 'content'), 'action="' . $adminBase . '"'),
        'Configured Admin login form must render after recovery logout.'
    );

    echo "Admin access-denied logout recovery passed ({$assertions} assertions)." . PHP_EOL;
} finally {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
