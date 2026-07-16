<?php if (!empty($notice)): ?><div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="admin-panel admin-roles-page" aria-labelledby="roles-list-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="roles-list-title">Roles</h2>
            <p class="admin-panel__description">Manage role identities and permission bundles.</p>
        </div>
        <?php if (!empty($canCreate)): ?><div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('roles/create'), ENT_QUOTES, 'UTF-8') ?>">Create role</a></div><?php endif; ?>
    </header>
    <div class="admin-panel__body">
        <?php if (empty($roles)): ?>
            <div class="admin-empty-state"><h3 class="admin-empty-state__title">No roles found</h3></div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table admin-roles-table">
                <thead><tr><th>Display name</th><th>Slug</th><th>Type</th><th>Assigned users</th><th>Permissions</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($roles as $entry): $role = $entry['role']; ?><tr>
                    <td class="admin-roles-name"><span class="admin-roles-name__value"><?= htmlspecialchars($role->name(), ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="admin-roles-slug"><code><?= htmlspecialchars($role->slug(), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td class="admin-roles-type"><span class="admin-badge <?= $role->isSeeded() ? 'admin-badge--info' : '' ?>"><?= $role->isSeeded() ? 'Seeded' : 'Custom' ?></span></td>
                    <td class="admin-roles-count"><span><?= htmlspecialchars((string) $entry['assignedUserCount'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="admin-roles-count"><span><?= htmlspecialchars((string) $entry['permissionCount'], ENT_QUOTES, 'UTF-8') ?></span></td>
                    <td class="admin-roles-actions"><div class="admin-row-actions"><a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('roles/' . $role->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a></div></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>
