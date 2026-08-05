<?php
declare(strict_types=1);

$base = dirname(__DIR__); chdir($base); require $base . '/bootstrap/autoload.php';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items = [];
$csrfToken = 'csrf-token';
$addPath = '/dapur/modules/add';
$lifecyclePath = '/dapur/modules/lifecycle';
$actionPaths = [];
$detailPath = static fn (string $name): string => '/dapur/modules/' . $name;
$notice = null;
$error = null;
ob_start();
require $base . '/modules/module-manager/views/admin/modules.php';
$html = (string) ob_get_clean();
if (!str_contains($html, 'Add Module') || !str_contains($html, 'action="/dapur/modules/add"') || !str_contains($html, 'enctype="multipart/form-data"')) {
    throw new RuntimeException('The Module Manager Add Module control was not rendered with the local intake route.');
}
echo "Module Package Lifecycle WU7 presentation routing passed.\n";
