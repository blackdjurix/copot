<div class="admin-dashboard">
    <p class="admin-dashboard__description">Overview of your Copot Admin workspace.</p>

    <section class="admin-panel" aria-labelledby="framework-status-title">
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
                    <dd><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt>Admin path</dt>
                    <dd><?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?></dd>

                    <dt>User</dt>
                    <dd>
                        <?= htmlspecialchars($userName ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                        &lt;<?= htmlspecialchars($userEmail ?? '', ENT_QUOTES, 'UTF-8') ?>&gt;
                    </dd>
                </dl>

                <aside class="admin-dashboard-status" aria-labelledby="framework-status-label">
                    <span class="admin-dashboard-status__label" id="framework-status-label">Framework status</span>
                    <strong>Post-M3 · Admin UX Refinement 1</strong>
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

    <?php if (($widgets ?? []) === []): ?>
        <div class="admin-empty-state">
            <h3 class="admin-empty-state__title">No module shortcuts available</h3>
            <p class="admin-empty-state__description">Enabled modules can register permission-aware dashboard shortcuts here.</p>
        </div>
    <?php else: ?>
        <div class="admin-dashboard-widgets">
            <?php foreach ($widgets as $widget): ?>
                <?php $widgetHeadingId = 'dashboard-widget-' . ($widget['id'] ?? 'item'); ?>
                <article class="admin-panel" aria-labelledby="<?= htmlspecialchars($widgetHeadingId, ENT_QUOTES, 'UTF-8') ?>">
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

                    <?php if (!empty($widget['url'])): ?>
                        <div class="admin-panel__actions">
                            <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($widget['url'], ENT_QUOTES, 'UTF-8') ?>">Open</a>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>
</div>
