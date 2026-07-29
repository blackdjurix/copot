<section class="admin-panel" aria-labelledby="navigation-menus-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="navigation-menus-title">Navigation menus</h2>
            <p class="admin-panel__description">Manage menu structures independently from Admin Shell navigation and Theme presentation.</p>
        </div>
        <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('navigation/create'), ENT_QUOTES, 'UTF-8') ?>">Create menu</a>
    </header>
    <div class="admin-panel__body">
        <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert">Navigation is temporarily unavailable.</div><?php endif; ?>
        <?php if (($notice ?? null) === 'saved'): ?><div class="admin-alert admin-alert--success" role="status">Menu saved.</div><?php endif; ?>
        <?php if (($notice ?? null) === 'deleted'): ?><div class="admin-alert admin-alert--success" role="status">Menu deleted.</div><?php endif; ?>
        <?php if ($menus === []): ?>
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No navigation menus yet</h3>
                <p class="admin-empty-state__description">Create the first menu to begin managing navigation items.</p>
                <div class="admin-empty-state__actions"><a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('navigation/create'), ENT_QUOTES, 'UTF-8') ?>">Create menu</a></div>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th scope="col">Name</th><th scope="col">Slug</th><th scope="col">Actions</th></tr></thead>
                <tbody><?php foreach ($menus as $menu): ?><tr>
                    <td><strong class="admin-table-primary"><?= htmlspecialchars($menu->name(), ENT_QUOTES, 'UTF-8') ?></strong></td>
                    <td><span class="admin-table-meta admin-table-wrap-anywhere"><?= htmlspecialchars($menu->slug(), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td><div class="admin-row-actions">
                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('navigation/' . $menu->id() . '/items'), ENT_QUOTES, 'UTF-8') ?>">Manage items</a>
                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('navigation/' . $menu->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a>
                        <form class="admin-inline-form" method="post" action="<?= htmlspecialchars($adminUrl('navigation/' . $menu->id() . '/delete'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"><button class="admin-button admin-button--link admin-action-danger" type="submit">Delete</button></form>
                    </div></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>
