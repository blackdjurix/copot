<section class="admin-panel" aria-labelledby="framework-status-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="framework-status-title">Framework status</h2>
            <p class="admin-panel__description">Copot Admin Shell foundation is running.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <dl>
            <dt>Application</dt>
            <dd><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></dd>

            <dt>Framework status</dt>
            <dd><?= htmlspecialchars($frameworkStatus ?? 'M1.4.1 Admin Shell', ENT_QUOTES, 'UTF-8') ?></dd>

            <dt>Admin path</dt>
            <dd><?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?></dd>

            <dt>User</dt>
            <dd>
                <?= htmlspecialchars($userName ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                &lt;<?= htmlspecialchars($userEmail ?? '', ENT_QUOTES, 'UTF-8') ?>&gt;
            </dd>
        </dl>
    </div>
</section>


<section aria-labelledby="module-overview-title">
    <div class="admin-page-section-heading">
        <h2 id="module-overview-title">Module overview</h2>
        <p>Quick access to enabled modules available to your account.</p>
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
