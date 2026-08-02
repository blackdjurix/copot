<?php

declare(strict_types=1);

namespace {
    $basePath = dirname(__DIR__);
    $assertions = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions): void {
        $assertions++;

        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}" . PHP_EOL);
            exit(1);
        }
    };

    $render = static function (array $data) use ($basePath): string {
        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $basePath . '/resources/views/admin/dashboard.php';
            return (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    };

    $widgets = [
        [
            'id' => 'core.system-overview',
            'title' => 'System overview',
            'description' => 'Safe system status.',
            'url' => null,
            'footprint' => 'wide',
            'content' => [
                'application' => 'Copot <safe>',
                'admin_path' => '/dapur',
                'user_name' => 'Admin',
                'user_email' => 'admin@example.test',
                'framework_status' => 'Ready',
            ],
        ],
        [
            'id' => 'content.drafts',
            'title' => 'Draft Content <unsafe>',
            'description' => 'Draft count.',
            'url' => '/dapur/content?status=draft',
            'footprint' => 'standard',
            'content' => ['draft_count' => 7],
        ],
        [
            'id' => 'content.recent',
            'title' => 'Recent Content',
            'description' => 'Recent list.',
            'url' => '/dapur/content',
            'footprint' => 'wide',
            'content' => ['items' => [['title' => 'Recent <entry>']]],
        ],
        [
            'id' => 'content.overview',
            'title' => 'Content',
            'description' => 'Open Content.',
            'url' => '/dapur/content',
            'footprint' => 'compact',
            'content' => null,
        ],
        [
            'id' => 'taxonomy.overview',
            'title' => 'Taxonomy',
            'description' => 'Open Taxonomy.',
            'url' => '/dapur/taxonomy',
            'footprint' => 'invalid',
            'content' => null,
        ],
    ];

    $dashboard = $render([
        'appName' => 'Copot',
        'frameworkStatus' => 'Fallback',
        'adminBaseUrl' => '/dapur',
        'userName' => 'Fallback User',
        'userEmail' => 'fallback@example.test',
        'widgets' => $widgets,
    ]);
    $empty = $render([
        'appName' => 'Copot',
        'frameworkStatus' => 'Fallback',
        'adminBaseUrl' => '/dapur',
        'userName' => 'Admin',
        'userEmail' => 'admin@example.test',
        'widgets' => [],
    ]);

    $assert(str_contains($dashboard, 'data-widget-id="core.system-overview" data-footprint="wide"'), 'Core system overview footprint presentation is missing.');
    $assert(str_contains($dashboard, 'admin-dashboard-widget--standard') && str_contains($dashboard, 'admin-dashboard-widget--compact'), 'Semantic footprint classes are not rendered.');
    $assert(str_contains($dashboard, 'class="admin-dashboard-count"') && str_contains($dashboard, '<strong>7</strong>') && str_contains($dashboard, 'draft Content entries'), 'Draft count presentation is not scannable.');
    $assert(str_contains($dashboard, 'class="admin-dashboard-recent-list"') && str_contains($dashboard, 'Recent &lt;entry&gt;'), 'Recent Content list presentation or escaping is missing.');
    $assert(str_contains($dashboard, 'href="/dapur/content?status=draft"'), 'Draft contextual navigation was not preserved.');
    $assert(!str_contains($dashboard, '<unsafe>') && str_contains($dashboard, 'Draft Content &lt;unsafe&gt;'), 'Widget title escaping failed.');
    $assert(str_contains($dashboard, 'href="/dapur/content"') && str_contains($dashboard, 'href="/dapur/taxonomy"'), 'Compact navigation widget actions are missing.');
    $assert(strpos($dashboard, 'data-widget-id="content.drafts"') < strpos($dashboard, 'data-widget-id="content.recent"') && strpos($dashboard, 'data-widget-id="content.recent"') < strpos($dashboard, 'data-widget-id="content.overview"'), 'Dashboard DOM order does not preserve priority order.');
    $assert(str_contains($empty, 'class="admin-empty-state"'), 'Empty Dashboard state is missing.');

    $css = (string) file_get_contents($basePath . '/public/admin-assets/css/admin.css');
    $assert(str_contains($css, 'grid-template-columns: repeat(4, minmax(0, 1fr));'), 'Desktop Dashboard grid is not four logical columns.');
    $assert(str_contains($css, '.admin-dashboard-widget--standard,') && str_contains($css, 'grid-column: span 2;'), 'Standard/wide footprint mapping is missing.');
    $assert(str_contains($css, '@media (max-width: 720px)') && str_contains($css, 'grid-template-columns: 1fr;'), '720px Dashboard collapse rule is missing.');
    $assert(str_contains($css, 'grid-auto-flow: row;') && !str_contains($css, 'grid-auto-flow: dense;'), 'Dashboard placement must not use dense packing.');
    $assert(str_contains($css, 'min-width: 0;') && str_contains($css, 'overflow-wrap: anywhere;'), 'Dashboard overflow containment is incomplete.');

    echo "M3.9 Work Unit 3 dashboard presentation passed ({$assertions} assertions)." . PHP_EOL;
}
