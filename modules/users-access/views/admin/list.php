<section class="admin-panel" aria-labelledby="users-list-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="users-list-title">User accounts</h2>
            <p class="admin-panel__description">Review and manage user identity and account status.</p>
        </div>

        <?php if (!empty($canCreate)): ?>
            <div class="admin-actions">
                <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('users/create'), ENT_QUOTES, 'UTF-8') ?>">Create user</a>
            </div>
        <?php endif; ?>
    </header>

    <div class="admin-panel__body">
        <?php if (empty($users)): ?>
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No users found</h3>
                <p class="admin-empty-state__description">Create a user account to begin managing access.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-users-table">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last login</th>
                            <th scope="col">Updated</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $managedUser): ?>
                            <?php
                            $status = $managedUser->status();
                            $statusBadgeClass = match ($status) {
                                'active' => 'admin-badge--success',
                                'inactive' => 'admin-badge--warning',
                                default => '',
                            };
                            $statusLabel = match ($status) {
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                default => $status,
                            };
                            ?>
                            <tr>
                                <td class="admin-users-name"><span class="admin-users-name__value"><?= htmlspecialchars($managedUser->name(), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="admin-users-email"><?= htmlspecialchars($managedUser->email(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="admin-users-status"><span class="admin-badge <?= htmlspecialchars($statusBadgeClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td class="admin-users-meta"><?= htmlspecialchars($managedUser->lastLoginAt() ?? 'Never', ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="admin-users-meta"><?= htmlspecialchars($managedUser->updatedAt(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="admin-users-actions">
                                    <div class="admin-row-actions">
                                        <a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('users/' . $managedUser->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Open</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
