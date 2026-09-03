<?php

declare(strict_types=1);

use Copot\Core\Request;

$base = dirname(__DIR__);
chdir($base);
$app = require $base . '/bootstrap/app.php';
$connection = $app->database()->connection();
$query = $connection->query("SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN role_permissions rp ON rp.role_id=ur.role_id JOIN permissions p ON p.id=rp.permission_id WHERE u.status='active' AND p.slug IN ('admin.access','settings.update') GROUP BY u.id HAVING COUNT(DISTINCT p.slug)=2 LIMIT 1");
$userId = $query->fetchColumn();
if (!is_numeric($userId)) throw new RuntimeException('No local authorized user is available.');
$app->session()->set((string) $app->config()->get('auth.session_key', '_copot_user_id'), (int) $userId);
$status = static function ($response): int {
    $property = new ReflectionProperty($response, 'status');
    return (int) $property->getValue($response);
};
$content = static function ($response): string {
    $property = new ReflectionProperty($response, 'content');
    return (string) $property->getValue($response);
};
$settings = $app->run(new Request('GET', $app->adminUrl()->childUrl('settings')));
if ($status($settings) !== 200 || !str_contains($content($settings), 'System Health')) throw new RuntimeException('Authenticated Site Settings route did not render.');
$legacy = $app->run(new Request('GET', $app->adminUrl()->childUrl('settings/system-manager')));
if ($status($legacy) !== 404) throw new RuntimeException('Retired System Manager route is still active.');
$csrf = $app->run(new Request('POST', $app->adminUrl()->childUrl('settings'), [], ['_token' => 'invalid']));
if ($status($csrf) !== 419) throw new RuntimeException('Site Settings CSRF rejection failed.');
echo 'WU4 Batch 1 local authenticated runtime smoke passed (3 assertions).' . PHP_EOL;
