<?php

declare(strict_types=1);

use Copot\Core\View;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
        exit(1);
    }
};

$readFile = static function (string $path) use ($assert): string {
    $assert(is_file($path), "Required file is missing [{$path}].");

    return (string) file_get_contents($path);
};

$cssFile = $basePath . '/public/admin-assets/css/admin.css';
$settingsFile = $basePath . '/resources/views/admin/settings.php';
$dashboardFile = $basePath . '/resources/views/admin/dashboard.php';
$loginFile = $basePath . '/resources/views/admin/login.php';
$layoutFile = $basePath . '/resources/views/admin/layout.php';
$routesFile = $basePath . '/routes/admin.php';

$css = $readFile($cssFile);
$settingsSource = $readFile($settingsFile);
$dashboardSource = $readFile($dashboardFile);
$loginSource = $readFile($loginFile);
$layoutSource = $readFile($layoutFile);
$routesSource = $readFile($routesFile);
$view = new View($basePath . '/resources/views');

$settingsData = [
    'formAction' => '/dapur/settings',
    'csrfToken' => 'csrf-token',
    'values' => [
        'site_name' => 'copot',
        'site_tagline' => 'Framework',
        'localization_timezone' => 'UTC',
        'localization_locale' => 'id_ID',
        'localization_date_format' => 'Y-m-d',
        'localization_time_format' => 'H:i',
    ],
    'errors' => [],
    'saved' => true,
    'timezones' => ['UTC', 'Asia/Jakarta'],
    'locales' => ['en_US', 'id_ID'],
    'dateFormats' => ['Y-m-d', 'd/m/Y'],
    'timeFormats' => ['H:i', 'h:i A'],
];

$settingsSuccess = $view->render('admin/settings', $settingsData);
$settingsError = $view->render('admin/settings', array_replace($settingsData, [
    'errors' => [
        'site_name' => 'Site Name is required.',
        'localization_timezone' => 'Invalid timezone.',
    ],
    'saved' => false,
]));
$dashboard = $view->render('admin/dashboard', [
    'appName' => 'Copot',
    'frameworkStatus' => 'M2.1 Admin UI Foundation',
    'adminBaseUrl' => '/dapur',
    'userName' => 'Admin',
    'userEmail' => 'admin@example.test',
]);
$login = $view->render('admin/login', [
    'appName' => 'Copot',
    'siteName' => 'copot',
    'documentLocale' => 'id-ID',
    'adminBaseUrl' => '/dapur',
    'csrfToken' => 'csrf-token',
    'email' => 'admin@example.test',
    'error' => 'Invalid credentials.',
]);

// Panel pattern: optional heading, description, body, and action areas.
$assert(str_contains($css, '.admin-panel__header'), 'Panel header pattern is missing.');
$assert(str_contains($css, '.admin-panel__description'), 'Panel description pattern is missing.');
$assert(str_contains($css, '.admin-panel__body'), 'Panel content pattern is missing.');
$assert(str_contains($css, '.admin-panel__actions'), 'Optional panel action area is missing.');
$assert(str_contains($dashboard, 'class="admin-panel"'), 'Dashboard does not consume the shared panel pattern.');
$assert(str_contains($dashboard, 'class="admin-panel__title" id="framework-status-title"'), 'Dashboard panel heading contract is missing.');
$assert(str_contains($dashboard, 'aria-labelledby="framework-status-title"'), 'Dashboard panel heading is not associated with its region.');

// Alert pattern: rendered semantics plus all four variants.
foreach (['success', 'warning', 'danger', 'info'] as $variant) {
    $assert(str_contains($css, ".admin-alert--{$variant}"), "Admin alert variant [{$variant}] is missing.");
}
$assert(str_contains($settingsSuccess, 'class="admin-alert admin-alert--success" role="status"'), 'Settings success message lacks shared status semantics.');
$assert(str_contains($settingsError, 'class="admin-alert admin-alert--danger" role="alert"'), 'Settings error summary lacks shared alert semantics.');
$assert(str_contains($login, 'class="admin-alert admin-alert--danger"'), 'Admin login does not consume the shared danger alert pattern.');
$assert(str_contains($login, 'role="alert"'), 'Admin login error lacks alert semantics.');

// Field pattern: label association, help/error references, invalid state, and required state.
$assert(str_contains($settingsError, '<label class="admin-field__label" for="site_name">'), 'Settings label is not associated with Site Name.');
$assert(str_contains($settingsError, 'aria-describedby="site_name-help site_name-error"'), 'Settings field does not reference help and error text.');
$assert(str_contains($settingsError, 'aria-invalid="true"'), 'Invalid Settings field lacks aria-invalid.');
$assert(str_contains($settingsError, 'class="admin-field__error" id="site_name-error"'), 'Settings field error contract is missing.');
$assert(str_contains($settingsSuccess, 'class="admin-field__help" id="site_name-help"'), 'Settings help-text contract is missing.');
$assert(str_contains($settingsSuccess, '<span class="admin-visually-hidden">required</span>'), 'Required field lacks accessible required text.');
$assert(str_contains($settingsSuccess, '<select'), 'Settings select rendering regressed.');
$assert(str_contains($css, '.admin-field textarea'), 'Textarea field pattern is missing.');

// Action variants preserve native element roles while sharing presentation classes.
foreach (['primary', 'secondary', 'danger', 'link'] as $variant) {
    $assert(str_contains($css, ".admin-button--{$variant}"), "Admin action variant [{$variant}] is missing.");
}
$assert(str_contains($settingsSuccess, 'class="admin-button admin-button--primary" type="submit"'), 'Settings submit does not use the primary button pattern.');
$assert(str_contains($login, 'class="admin-button admin-button--primary" type="submit"'), 'Admin login submit does not use the primary button pattern.');
$assert(str_contains($layoutSource, 'class="admin-button admin-button--secondary" type="submit"'), 'Logout does not use the secondary button pattern.');

// Table and empty-state contracts are available for Batch 4 consumers.
$assert(str_contains($css, '.admin-table-wrap'), 'Responsive table wrapper pattern is missing.');
$assert(preg_match('/\.admin-table-wrap\s*\{[^}]*overflow-x:\s*auto;/s', $css) === 1, 'Table wrapper lacks controlled horizontal overflow.');
$assert(str_contains($css, '.admin-table'), 'Shared table treatment is missing.');
$assert(str_contains($css, '.admin-table__empty'), 'Table empty-row treatment is missing.');
$assert(str_contains($css, '.admin-empty-state'), 'Empty-state container pattern is missing.');
$assert(str_contains($css, '.admin-empty-state__title'), 'Empty-state title pattern is missing.');
$assert(str_contains($css, '.admin-empty-state__description'), 'Empty-state description pattern is missing.');
$assert(str_contains($css, '.admin-empty-state__actions'), 'Optional empty-state action pattern is missing.');
$assert(str_contains($css, '.admin-actions .admin-button:not(.admin-button--link)'), 'Narrow-screen action stacking is missing.');
$assert(str_contains($css, ':focus-visible'), 'Shared patterns lack visible keyboard focus treatment.');

// Core behavior and security boundaries remain intact.
$assert(str_contains($settingsSuccess, 'method="post" action="/dapur/settings"'), 'Settings form action changed unexpectedly.');
$assert(str_contains($settingsSuccess, 'name="_token" value="csrf-token"'), 'Settings CSRF token rendering regressed.');
$assert(str_contains($login, 'method="post" action="/dapur"'), 'Admin login route contract changed unexpectedly.');
$assert(str_contains($login, 'name="_token" value="csrf-token"'), 'Admin login CSRF token rendering regressed.');
$assert(str_contains($routesSource, "'settings.update'"), 'Settings permission guard is missing.');
$assert(str_contains($routesSource, 'validateOrReject($request)'), 'Settings CSRF validation guard is missing.');
$assert(str_contains($routesSource, 'validateCsrf('), 'Admin authentication CSRF validation guard is missing.');

// Scope and dependency guards.
$assert(!str_contains($settingsSource, '<style>'), 'Settings retains a page-local stylesheet instead of shared Admin patterns.');
$assert(!preg_match('/theme-assets|themes\//', $css), 'Admin patterns depend on frontend theme assets.');
$assert(!is_file($basePath . '/package.json'), 'A Node/build dependency was introduced.');
$assert(!str_contains($dashboardSource, 'admin-widget'), 'Dashboard widget work entered Batch 3 scope.');
$assert(!str_contains($loginSource, '<script'), 'JavaScript entered Admin login patterns.');

echo "Admin UI Batch 3 smoke tests passed ({$assertions} assertions)." . PHP_EOL;
echo "Note: CSS contract guards supplement rendered Core-page behavior checks." . PHP_EOL;
