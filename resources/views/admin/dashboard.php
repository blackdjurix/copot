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
