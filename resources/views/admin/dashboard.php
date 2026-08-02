<?php
$coreWidget = null;
$moduleWidgets = [];
foreach (($widgets ?? []) as $dashboardWidget) {
    if (($dashboardWidget['id'] ?? null) === 'core.system-overview') {
        $coreWidget = $dashboardWidget;
        continue;
    }

    $moduleWidgets[] = $dashboardWidget;
}
$coreContent = is_array($coreWidget['content'] ?? null) ? $coreWidget['content'] : [];
?>
<div class="admin-dashboard">
    <p class="admin-dashboard__description">Overview of your Copot Admin workspace.</p>

    <section class="admin-panel admin-dashboard-widget admin-dashboard-widget--wide" data-widget-id="core.system-overview" data-footprint="wide" aria-labelledby="framework-status-title">
        <header class="admin-panel__header">
            <div class="admin-panel__heading">
                <h2 class="admin-panel__title" id="framework-status-title">System overview</h2>
                <p class="admin-panel__description">Your current Admin environment at a glance.</p>
            </div>
        </header>

        <div class="admin-panel__body">
            <div class="admin-dashboard-overview__grid">
                <dl>
                    <dt>Application</dt>
                    <dd><?= htmlspecialchars((string) ($coreContent['application'] ?? $appName ?? 'Copot'), ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt>Admin path</dt>
                    <dd><?= htmlspecialchars((string) ($coreContent['admin_path'] ?? $adminBaseUrl), ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt>User</dt>
                    <dd>
                        <?= htmlspecialchars((string) ($coreContent['user_name'] ?? $userName ?? 'User'), ENT_QUOTES, 'UTF-8') ?>
                        &lt;<?= htmlspecialchars((string) ($coreContent['user_email'] ?? $userEmail ?? ''), ENT_QUOTES, 'UTF-8') ?>&gt;
                    </dd>
                </dl>

                <aside class="admin-dashboard-status" aria-labelledby="framework-status-label">
                    <span class="admin-dashboard-status__label" id="framework-status-label">Framework status</span>
                    <strong><?= htmlspecialchars((string) ($coreContent['framework_status'] ?? $frameworkStatus ?? 'Admin Shell'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <p>Copot Admin is ready for use.</p>
                </aside>
            </div>
        </div>
    </section>

    <section aria-labelledby="module-overview-title">
        <div class="admin-page-section-heading">
            <h2 id="module-overview-title">Quick access</h2>
            <p>Open enabled modules available to your account.</p>
        </div>

    <?php if ($moduleWidgets === []): ?>
        <div class="admin-empty-state">
            <h3 class="admin-empty-state__title">No module shortcuts available</h3>
            <p class="admin-empty-state__description">Enabled modules can register permission-aware dashboard shortcuts here.</p>
        </div>
    <?php else: ?>
        <div class="admin-dashboard-widgets">
            <?php foreach ($moduleWidgets as $widget): ?>
                <?php $widgetHeadingId = 'dashboard-widget-' . ($widget['id'] ?? 'item'); ?>
                <?php $widgetFootprint = in_array(($widget['footprint'] ?? 'compact'), ['compact', 'standard', 'wide'], true) ? $widget['footprint'] : 'compact'; ?>
                <article class="admin-panel admin-dashboard-widget admin-dashboard-widget--<?= htmlspecialchars($widgetFootprint, ENT_QUOTES, 'UTF-8') ?>" data-widget-id="<?= htmlspecialchars((string) ($widget['id'] ?? 'item'), ENT_QUOTES, 'UTF-8') ?>" data-footprint="<?= htmlspecialchars($widgetFootprint, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($widgetHeadingId, ENT_QUOTES, 'UTF-8') ?>">
                    <header class="admin-panel__header">
                        <div class="admin-panel__heading">
                            <h3 class="admin-panel__title" id="<?= htmlspecialchars($widgetHeadingId, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($widget['title'] ?? 'Module', ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <p class="admin-panel__description">
                                <?= htmlspecialchars($widget['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        </div>
                    </header>

                    <?php if (isset($widget['content']['draft_count'])): ?>
                        <div class="admin-panel__body admin-dashboard-widget__body">
                            <p class="admin-dashboard-count"><strong><?= htmlspecialchars((string) $widget['content']['draft_count'], ENT_QUOTES, 'UTF-8') ?></strong><span>draft Content entries</span></p>
                        </div>
                    <?php elseif (isset($widget['content']['items']) && is_array($widget['content']['items'])): ?>
                        <div class="admin-panel__body admin-dashboard-widget__body">
                            <?php if ($widget['content']['items'] === []): ?>
                                <p>No recently updated Content entries.</p>
                            <?php else: ?>
                                <ul class="admin-dashboard-recent-list">
                                    <?php foreach ($widget['content']['items'] as $item): ?>
                                        <li><?= htmlspecialchars((string) ($item['title'] ?? 'Untitled'), ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($widget['url'])): ?>
                        <div class="admin-panel__actions admin-dashboard-widget__actions">
                            <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($widget['url'], ENT_QUOTES, 'UTF-8') ?>">Open</a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>
</div>
