<?php if (!empty($notice)): ?><div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<section class="admin-panel" aria-labelledby="roles-list-title">
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
            <div class="admin-table-wrap"><table class="admin-table">
                <thead><tr><th>Display name</th><th>Slug</th><th>Type</th><th>Assigned users</th><th>Permissions</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($roles as $entry): $role = $entry['role']; ?><tr>
                    <td><?= htmlspecialchars($role->name(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($role->slug(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= $role->isSeeded() ? 'Seeded' : 'Custom' ?></td>
                    <td><?= htmlspecialchars((string) $entry['assignedUserCount'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars((string) $entry['permissionCount'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('roles/' . $role->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a></td>
                </tr><?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </div>
</section>
