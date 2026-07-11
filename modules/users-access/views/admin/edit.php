<?php
$sectionErrors = static fn (string $section): array => ($errorSection ?? null) === $section ? ($errors ?? []) : [];
?>

<?php if (!empty($notice)): ?>
    <div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<section class="admin-panel" aria-labelledby="user-identity-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="user-identity-title">Identity</h2>
            <p class="admin-panel__description">Account #<?= htmlspecialchars((string) $target->id(), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
    </header>
    <div class="admin-panel__body">
        <?php if ($sectionErrors('identity') !== []): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <?php foreach ($sectionErrors('identity') as $message): ?>
                    <div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($canUpdate)): ?>
            <form class="admin-form" method="post" action="<?= htmlspecialchars($identityAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-field">
                    <label class="admin-field__label" for="name">Name</label>
                    <input id="name" name="name" type="text" maxlength="120" value="<?= htmlspecialchars($identityValues['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="admin-field">
                    <label class="admin-field__label" for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="190" value="<?= htmlspecialchars($identityValues['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="admin-actions admin-form__actions">
                    <button class="admin-button admin-button--primary" type="submit">Save identity</button>
                </div>
            </form>
        <?php else: ?>
            <dl>
                <dt>Name</dt><dd><?= htmlspecialchars($target->name(), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Email</dt><dd><?= htmlspecialchars($target->email(), ENT_QUOTES, 'UTF-8') ?></dd>
                <dt>Status</dt><dd><?= htmlspecialchars($target->status(), ENT_QUOTES, 'UTF-8') ?></dd>
            </dl>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($canManagePassword)): ?>
    <section class="admin-panel" aria-labelledby="user-password-title">
        <header class="admin-panel__header"><h2 class="admin-panel__title" id="user-password-title">Password</h2></header>
        <div class="admin-panel__body">
            <?php if ($sectionErrors('password') !== []): ?>
                <div class="admin-alert admin-alert--danger" role="alert">
                    <?php foreach ($sectionErrors('password') as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form class="admin-form" method="post" action="<?= htmlspecialchars($passwordAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-field"><label class="admin-field__label" for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>
                <div class="admin-field"><label class="admin-field__label" for="password_confirmation">Confirm password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
                <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Update password</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($canManageStatus)): ?>
    <section class="admin-panel" aria-labelledby="user-status-title">
        <header class="admin-panel__header"><h2 class="admin-panel__title" id="user-status-title">Account status</h2></header>
        <div class="admin-panel__body">
            <?php if ($sectionErrors('status') !== []): ?>
                <div class="admin-alert admin-alert--danger" role="alert">
                    <?php foreach ($sectionErrors('status') as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form class="admin-form" method="post" action="<?= htmlspecialchars($statusAction, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="admin-field">
                    <label class="admin-field__label" for="status">Status</label>
                    <select id="status" name="status">
                        <option value="active" <?= $target->status() === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $target->status() === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Update status</button></div>
            </form>
        </div>
    </section>
<?php endif; ?>
