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

    require_once $basePath . '/app/Core/Admin/AdminDashboardRegistry.php';

    $registry = new AdminDashboardRegistry();
    $registry->add(
        'core.system-overview',
        'System overview',
        'Safe system status.',
        null,
        'admin.access',
        100,
        [
            'owner' => 'core',
            'purpose' => 'status',
            'footprint' => 'wide',
            'provider' => static fn (): array => ['framework_status' => 'ready'],
        ]
    );
    $registry->add(
        'content.drafts',
        'Draft Content',
        'Draft count.',
        '/dapur/content?status=draft',
        'content.read',
        120,
        [
            'owner' => 'content',
            'purpose' => 'status',
            'footprint' => 'standard',
            'provider' => static fn (): array => ['draft_count' => 3],
        ]
    );
    $registry->add(
        'content.recent',
        'Recent Content',
        'Recently updated Content.',
        '/dapur/content',
        'content.read',
        140,
        [
            'owner' => 'content',
            'purpose' => 'management-overview',
            'footprint' => 'wide',
            'provider' => static fn (): array => ['items' => [['id' => 4], ['id' => 2]]],
        ]
    );
    $registry->add('content.overview', 'Content', 'Open Content.', '/dapur/content', 'content.read', 200);
    $registry->add('taxonomy.overview', 'Taxonomy', 'Open Taxonomy.', '/dapur/taxonomy', ['taxonomy.create', 'taxonomy.update', 'taxonomy.delete'], 300, ['owner' => 'taxonomy', 'purpose' => 'navigation']);
    $registry->add('core.failing', 'Unavailable', 'Fails closed.', null, 'admin.access', 400, ['provider' => static fn (): array => throw new \RuntimeException('provider failure')]);

    $admin = new User(['admin.access', 'content.read', 'taxonomy.create']);
    $widgets = $registry->itemsFor($admin);
    $assert(array_column($widgets, 'id') === ['core.system-overview', 'content.drafts', 'content.recent', 'content.overview', 'taxonomy.overview'], 'WU2 contribution ordering or failure isolation is incorrect.');
    $assert($widgets[0]['owner'] === 'core' && $widgets[0]['footprint'] === 'wide' && $widgets[0]['content']['framework_status'] === 'ready', 'Core contribution metadata or payload is incorrect.');
    $assert($widgets[1]['url'] === '/dapur/content?status=draft' && $widgets[1]['footprint'] === 'standard' && $widgets[1]['content']['draft_count'] === 3, 'Draft contribution metadata, contextual URL, or payload is incorrect.');
    $assert($widgets[2]['content']['items'] === [['id' => 4], ['id' => 2]], 'Recent Content contribution payload is not preserved.');
    $assert($widgets[3]['footprint'] === 'compact' && $widgets[4]['footprint'] === 'compact', 'Compatibility footprint defaults are incorrect.');
    $assert(count((new AdminDashboardRegistry())->itemsFor($admin)) === 0, 'An empty registry must provide zero visible widgets.');
    $assert(count($registry->itemsFor(new User(['admin.access']))) === 1, 'Content permission isolation failed.');

    $invalidFootprintRejected = false;
    try {
        $registry->add('invalid.footprint', 'Invalid', 'Invalid footprint.', null, null, 500, ['footprint' => 'freeform']);
    } catch (\InvalidArgumentException) {
        $invalidFootprintRejected = true;
    }
    $assert($invalidFootprintRejected, 'Invalid semantic footprints must be rejected.');

    $contentRoutes = (string) file_get_contents($basePath . '/modules/content/routes.php');
    $taxonomyRoutes = (string) file_get_contents($basePath . '/modules/taxonomy/routes.php');
    $adminRoutes = (string) file_get_contents($basePath . '/routes/admin.php');
    $contentRepository = (string) file_get_contents($basePath . '/app/Core/ContentRepository.php');
    $assert(str_contains($adminRoutes, "'core.system-overview'") && str_contains($adminRoutes, "'owner' => 'core'"), 'Core system overview is not registered through the shared registry.');
    foreach (['content.drafts', 'content.recent', 'content.overview'] as $id) {
        $assert(str_contains($contentRoutes, "'{$id}'"), "Content contribution [{$id}] is missing.");
    }
    $assert(str_contains($taxonomyRoutes, "'taxonomy.overview'") && str_contains($taxonomyRoutes, "'footprint' => 'compact'"), 'Taxonomy contribution metadata is missing.');
    $assert(str_contains($contentRoutes, "['status' => 'draft']") && str_contains($contentRoutes, '$contentRepository->workspace([], 5, 0)'), 'Content providers do not use the bounded public workspace boundary.');
    $assert(str_contains($contentRepository, 'ORDER BY updated_at DESC, id DESC'), 'Recent Content does not retain deterministic repository ordering.');
    $assert(!str_contains($contentRoutes, 'CREATE TABLE') && !str_contains($contentRoutes, 'SELECT * FROM content'), 'Content contribution routes bypassed the repository boundary.');

    echo "M3.9 Work Unit 2 dashboard contributions passed ({$assertions} assertions)." . PHP_EOL;
}
