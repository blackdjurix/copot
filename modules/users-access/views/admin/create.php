<section class="admin-panel" aria-labelledby="user-create-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="user-create-title">Create user</h2>
            <p class="admin-panel__description">Create an account without assigning roles.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <strong class="admin-alert__title">Please correct the following errors.</strong>
                <ul class="admin-alert__list">
                    <?php foreach ($errors as $message): ?>
                        <li><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

            <div class="admin-field">
                <label class="admin-field__label" for="name">Name</label>
                <input id="name" name="name" type="text" maxlength="120" value="<?= htmlspecialchars($values['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="email">Email</label>
                <input id="email" name="email" type="email" maxlength="190" value="<?= htmlspecialchars($values['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            <?php if (!empty($canManageStatus)): ?>
                <div class="admin-field">
                    <label class="admin-field__label" for="status">Status</label>
                    <select id="status" name="status">
                        <option value="inactive" <?= ($values['status'] ?? 'inactive') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        <option value="active" <?= ($values['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    </select>
                </div>
            <?php else: ?>
                <input type="hidden" name="status" value="inactive">
                <p class="admin-field__help">New users are created inactive.</p>
            <?php endif; ?>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">Create user</button>
                <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('users'), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
