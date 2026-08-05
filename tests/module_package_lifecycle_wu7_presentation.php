<?php
declare(strict_types=1);

$base = dirname(__DIR__); chdir($base); require $base . '/bootstrap/autoload.php';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items = [['name' => 'wu7-acceptance', 'title' => 'WU7 Acceptance Module', 'version' => '', 'lifecycle_state' => 'not_installed', 'discovery_state' => 'ready', 'available_package_version' => '0.1.0', 'available_package_candidate' => str_repeat('a', 64), 'lifecycle_action' => 'install', 'available_actions' => [], 'denial_reasons' => []]];
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
if (!str_contains($html, 'Add Module') || !str_contains($html, 'action="/dapur/modules/add"') || !str_contains($html, 'enctype="multipart/form-data"') || !str_contains($html, '>Install</button>') || str_contains($html, 'Run Package Lifecycle') || str_contains($html, 'Missing')) {
    throw new RuntimeException('The Module Manager Add Module control was not rendered with the local intake route.');
}
echo "Module Package Lifecycle WU7 presentation routing passed.\n";
$renderAction = static function (string $action, ?string $blocker = null) use ($base, $escape, $csrfToken, $addPath, $lifecyclePath, $actionPaths, $detailPath, $notice, $error): string {
    $items = [['name' => 'wu7-acceptance', 'title' => 'WU7 Acceptance Module', 'version' => '1.0.0', 'lifecycle_state' => 'installed_disabled', 'discovery_state' => 'valid', 'available_package_version' => '1.0.0', 'available_package_candidate' => str_repeat('b', 64), 'lifecycle_action' => $action, 'lifecycle_blocker' => $blocker, 'available_actions' => [], 'denial_reasons' => []]];
    ob_start(); require $base . '/modules/module-manager/views/admin/modules.php'; return (string) ob_get_clean();
};
foreach (['repair', 'patch', 'update', 'upgrade'] as $action) {
    $candidateHtml = $renderAction($action);
    if (!str_contains($candidateHtml, '>' . ucfirst($action) . '</button>')) throw new RuntimeException('CTA mapping did not render ' . $action . '.');
}
$blockedHtml = $renderAction('upgrade', 'Dependency or conflict resolution is required.');
if (str_contains($blockedHtml, 'action="/dapur/modules/lifecycle"') || !str_contains($blockedHtml, 'Dependency or conflict resolution is required.')) throw new RuntimeException('Blocked lifecycle candidate still exposed a mutation CTA.');
echo "Module Package Lifecycle WU7 CTA mappings passed.\n";
