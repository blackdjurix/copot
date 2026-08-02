<?php

declare(strict_types=1);

namespace Copot\Core {
    class User
    {
        public function __construct(private array $permissions = [])
        {
        }

        public function can(string $permission): bool
        {
            return in_array($permission, $this->permissions, true);
        }
    }
}

namespace {
    use Copot\Core\Admin\AdminDashboardRegistry;
    use Copot\Core\User;

    $basePath = dirname(__DIR__);
    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        $assertions++;

        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
            exit(1);
        }
    };

    $contract = (string) file_get_contents($basePath . '/docs/25_m3_9_internal_dashboard_contract.md');
    $registrySource = (string) file_get_contents($basePath . '/app/Core/Admin/AdminDashboardRegistry.php');
    require_once $basePath . '/app/Core/Admin/AdminDashboardRegistry.php';

    foreach ([
        'core.system-overview',
        'content.drafts',
        'content.recent',
        'content.overview',
        'taxonomy.overview',
    ] as $widgetId) {
        $assert(str_contains($contract, '`' . $widgetId . '`'), "WU1 inventory is missing [{$widgetId}].");
    }

    $assert(substr_count($contract, '### WU') === 4, 'M3.9 contract must contain exactly four domain work units.');
    $assert(str_contains($contract, 'cardinality `0..n` per contributor;'), 'The contribution cardinality is not locked.');
    $assert(str_contains($contract, 'semantic presets `compact` `(1,1)`, `standard` `(2,1)`, and `wide` `(2,2)`'), 'Bounded footprint presets are not locked.');
    $assert(str_contains($contract, 'does not lock an exact desktop column count'), 'The contract prematurely locks a desktop column count.');
    $assert(str_contains($contract, 'layout is system-controlled'), 'Baseline layout is not system-controlled.');
    $assert(str_contains($contract, 'information or status') && str_contains($contract, 'contextual navigation') && str_contains($contract, 'bounded quick administrative action'), 'Widget purpose boundaries are incomplete.');
    $assert(str_contains($contract, 'Complex management workflows remain'), 'Complex manager workflows are not excluded from widgets.');
    $assert(str_contains($contract, 'No parallel dashboard registry is authorized.'), 'The existing registry evolution boundary is missing.');
    $assert(str_contains($contract, 'DI-M3.9-01') && str_contains($contract, 'User-customizable Dashboard layout'), 'The WU1 contract does not preserve the layout deferral.');
    $assert(!str_contains($contract, 'e21e7b281fecdb7619022be0457381a5ce31ce85'), 'The obsolete preparation anchor remains in the WU1 contract.');
    $assert(str_contains($contract, 'feature/m3.9-internal-dashboard') && str_contains($contract, 'WU2–WU4 have not started'), 'WU1 branch status is not recorded correctly.');

    $registry = new AdminDashboardRegistry();
    $registry->add('content.drafts', 'Draft Content', 'Draft count.', '/dapur/content?status=draft', 'content.read', 120);
    $registry->add('content.recent', 'Recent Content', 'Recently updated Content.', '/dapur/content', 'content.read', 140);
    $registry->add('content.overview', 'Content', 'Open Content.', '/dapur/content', 'content.read', 200);
    $registry->add('taxonomy.overview', 'Taxonomy', 'Open Taxonomy.', '/dapur/taxonomy', ['taxonomy.create', 'taxonomy.update', 'taxonomy.delete'], 300);

    $contentUser = new User(['content.read']);
    $widgets = $registry->itemsFor($contentUser);
    $assert(count($widgets) === 3, 'A contributor must be able to provide zero-to-many visible widgets.');
    $assert(array_column($widgets, 'id') === ['content.drafts', 'content.recent', 'content.overview'], 'Priority ordering did not remain deterministic.');
    $assert(str_contains((string) $widgets[0]['url'], 'status=draft'), 'Contextual navigation URL was not preserved as a controlled root-relative URL.');
    $assert(count((new AdminDashboardRegistry())->itemsFor($contentUser)) === 0, 'A contributor may legitimately provide zero visible widgets.');

    $duplicateRejected = false;
    try {
        $registry->add('content.drafts', 'Duplicate', 'Duplicate widget.');
    } catch (\InvalidArgumentException) {
        $duplicateRejected = true;
    }
    $assert($duplicateRejected, 'Duplicate stable widget identity was not rejected.');

    $invalidIdRejected = false;
    try {
        $registry->add('Invalid Widget', 'Invalid', 'Invalid identity.');
    } catch (\InvalidArgumentException) {
        $invalidIdRejected = true;
    }
    $assert($invalidIdRejected, 'Invalid widget identity was not rejected.');

    $externalUrlRejected = false;
    try {
        $registry->add('content.external', 'External', 'Unsafe URL.', 'https://example.com');
    } catch (\InvalidArgumentException) {
        $externalUrlRejected = true;
    }
    $assert($externalUrlRejected, 'External widget navigation was not rejected.');

    $assert(str_contains($registrySource, 'registration_order'), 'Existing registration-order tie-breaking is missing.');
    $assert(str_contains($registrySource, 'public function itemsFor(?User $user)'), 'Existing permission-aware resolution boundary is missing.');

    echo "M3.9 Work Unit 1 dashboard contract passed ({$assertions} assertions)." . PHP_EOL;
}
