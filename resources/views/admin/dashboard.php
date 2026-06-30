<section class="panel">
    <h2>Framework status</h2>
    <p>Copot Admin Shell foundation is running.</p>

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
</section>
