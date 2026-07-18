<?php
$sectionErrors = static fn (string $section): array => ($errorSection ?? null) === $section ? ($errors ?? []) : [];
$permissionGroupOrder = [
    'system' => 'System',
    'content' => 'Content',
    'taxonomy' => 'Taxonomy',
    'users' => 'Users',
    'roles' => 'Roles',
    'other' => 'Other',
];
$systemPermissionSlugs = [
    'admin.access' => true,
    'protected.access' => true,
    'settings.update' => true,
];
$permissionGroupForSlug = static function (string $slug) use ($systemPermissionSlugs): string {
    if (isset($systemPermissionSlugs[$slug])) return 'system';
    foreach (['content.' => 'content', 'taxonomy.' => 'taxonomy', 'users.' => 'users', 'roles.' => 'roles'] as $prefix => $group) {
        if (str_starts_with($slug, $prefix)) return $group;
    }
    return 'other';
};
$permissionGroups = array_fill_keys(array_keys($permissionGroupOrder), []);
foreach ($permissions as $permission) {
    $permissionGroups[$permissionGroupForSlug((string) $permission['slug'])][] = $permission;
}
?>
<?php if (!empty($notice)): ?><div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="admin-role-edit-layout">
<div class="admin-role-edit-column admin-role-edit-column--primary">
<section class="admin-panel admin-role-edit-section admin-role-edit-identity" aria-labelledby="role-identity-title">
    <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="role-identity-title">Identity</h2><p class="admin-panel__description">Role #<?= htmlspecialchars((string) $role->id(), ENT_QUOTES, 'UTF-8') ?></p></div></header>
    <div class="admin-panel__body">
        <?php if ($sectionErrors('identity') !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><?php foreach ($sectionErrors('identity') as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
        <dl class="admin-role-identity-meta"><dt>Slug</dt><dd><?= htmlspecialchars($role->slug(), ENT_QUOTES, 'UTF-8') ?></dd><dt>Type</dt><dd><?= $role->isSeeded() ? 'Seeded' : 'Custom' ?></dd></dl>
        <?php if (!empty($canManage)): ?>
            <form class="admin-form" method="post" action="<?= htmlspecialchars($identityAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-field"><label class="admin-field__label" for="name">Display name</label><input class="admin-role-identity-name" id="name" name="name" type="text" maxlength="80" value="<?= htmlspecialchars($identityValues['name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Save display name</button></div>
            </form>
        <?php else: ?><dl class="admin-role-identity-meta"><dt>Display name</dt><dd><?= htmlspecialchars($role->name(), ENT_QUOTES, 'UTF-8') ?></dd></dl><?php endif; ?>
    </div>
</section>
<section class="admin-panel admin-role-edit-section admin-role-edit-lifecycle" aria-labelledby="role-delete-title">
    <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="role-delete-title">Delete / lifecycle</h2><p class="admin-panel__description"><?= htmlspecialchars((string) $assignedUserCount, ENT_QUOTES, 'UTF-8') ?> assigned users.</p></div></header>
    <div class="admin-panel__body">
        <?php if ($sectionErrors('delete') !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><?php foreach ($sectionErrors('delete') as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
        <?php if (!empty($canManage)): ?><form method="post" action="<?= htmlspecialchars($deleteAction, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><button class="admin-button admin-button--secondary" type="submit">Delete role</button></form><?php endif; ?>
    </div>
</section>
</div>
<div class="admin-role-edit-column admin-role-edit-column--secondary">
<section class="admin-panel admin-role-edit-section admin-role-edit-permissions" aria-labelledby="role-permissions-title">
    <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="role-permissions-title">Permissions</h2><p class="admin-panel__description">Selected permissions form the complete desired set.</p></div></header>
    <div class="admin-panel__body">
        <?php if ($sectionErrors('permissions') !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><?php foreach ($sectionErrors('permissions') as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?></div><?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($permissionsAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="permission_ids_present" value="1">
            <fieldset class="admin-fieldset admin-permissions-set">
                <legend class="admin-fieldset__legend">Permission assignment</legend>
                <div class="admin-permission-groups">
                    <?php foreach ($permissionGroupOrder as $groupKey => $groupLabel): ?>
                        <?php if ($permissionGroups[$groupKey] === []) continue; ?>
                        <section class="admin-permission-group" aria-labelledby="permission-group-<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>">
                            <h3 class="admin-permission-group__heading" id="permission-group-<?= htmlspecialchars($groupKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?></h3>
                            <div class="admin-check-list admin-permissions-list">
                                <?php foreach ($permissionGroups[$groupKey] as $permission): $permissionId = (int) $permission['id']; ?>
                                    <label class="admin-check-option admin-permission-option"><input type="checkbox" name="permission_ids[]" value="<?= htmlspecialchars((string) $permissionId, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($permissionId, $assignedPermissionIds, true) ? 'checked' : '' ?> <?= empty($canManagePermissions) ? 'disabled' : '' ?>><span class="admin-permission-option__content"><span class="admin-permission-option__header"><span class="admin-permission-option__title"><?= htmlspecialchars((string) $permission['name'], ENT_QUOTES, 'UTF-8') ?></span><code class="admin-permission-option__slug"><?= htmlspecialchars((string) $permission['slug'], ENT_QUOTES, 'UTF-8') ?></code></span></span></label>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php if (!empty($canManagePermissions)): ?><div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Replace permissions</button></div><?php endif; ?>
        </form>
    </div>
</section>
</div>
</div>
