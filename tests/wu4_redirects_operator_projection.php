<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$read = static fn (string $relative): string => (string) file_get_contents($basePath . '/' . $relative);

$coreRepository = $read('app/Core/RedirectRepository.php');
$coreService = $read('app/Core/RedirectService.php');
$coreResolver = $read('app/Core/RedirectResolver.php');
$moduleRepository = $read('modules/redirects/Services/RedirectRepository.php');
$moduleService = $read('modules/redirects/Services/RedirectService.php');
$moduleResolver = $read('modules/redirects/Services/RedirectResolver.php');
$moduleRoutes = $read('modules/redirects/routes.php');
$moduleContribution = $read('modules/redirects/resolver.php');
$siteSettingsRoutes = $read('routes/site_settings.php');
$siteSettingsView = $read('resources/views/admin/site-settings.php');

$assert(substr_count($coreRepository, 'final class RedirectRepository') === 1, 'Core Repository authority is not singular.');
$assert(substr_count($coreService, 'final class RedirectService') === 1, 'Core Service authority is not singular.');
$assert(substr_count($coreResolver, 'final class RedirectResolver') === 1, 'Core Resolver authority is not singular.');
$assert(str_contains($moduleRepository, 'class_alias(\\Copot\\Core\\RedirectRepository::class'), 'Module Repository compatibility does not delegate to Core.');
$assert(str_contains($moduleService, 'class_alias(\\Copot\\Core\\RedirectService::class'), 'Module Service compatibility does not delegate to Core.');
$assert(str_contains($moduleResolver, 'class_alias(\\Copot\\Core\\RedirectResolver::class'), 'Module Resolver compatibility does not delegate to Core.');
$assert(str_contains($moduleRoutes, 'use Copot\\Core\\RedirectRepository;') && str_contains($moduleRoutes, 'use Copot\\Core\\RedirectService;'), 'Module operator routes do not consume Core Redirect services.');
$assert(str_contains($moduleContribution, 'new \\Copot\\Core\\RedirectResolver(') && str_contains($moduleContribution, 'new \\Copot\\Core\\RedirectRepository('), 'Module resolver contribution does not register Core authority.');
$assert(!str_contains($moduleRepository, 'moduleTable(\'redirects\')') && !str_contains($moduleService, 'function assertAvailable'), 'Duplicate module Redirect business logic remains active.');
$assert(str_contains($siteSettingsRoutes, "'redirects.manage'") && str_contains($siteSettingsRoutes, 'redirectsProjectionPath'), 'Site Settings lacks the Redirect capability projection.');
$assert(str_contains($siteSettingsView, "'redirects' => 'Redirects'") && str_contains($siteSettingsView, 'Open Redirect Manager'), 'Site Settings Redirects panel is missing.');
$assert(str_contains($moduleRoutes, "['admin.access', 'redirects.manage']") && !str_contains($moduleRoutes, "['admin.access', 'settings.update']"), 'Redirect permission boundary changed.');

require_once $basePath . '/bootstrap/autoload.php';
$names = new \Copot\Core\DatabaseTableNames('wu4');
$assert((new \Copot\Core\DatabaseTableNames())->table('redirects') === 'redirects', 'Unnamespaced Redirect table changed.');
$assert($names->table('redirects') === 'wu4_redirects' && $names->moduleTable('redirects') === 'wu4_redirects', 'Namespaced Core/compatibility Redirect table paths diverged.');
$catalog = \Copot\Core\DatabaseTableOwnershipCatalog::current();
$assert($catalog->owner('redirects')->isWebcore(), 'Redirect table ownership is not Webcore.');

echo "WU4 Redirects operator projection tests passed ({$assertions} assertions)." . PHP_EOL;
