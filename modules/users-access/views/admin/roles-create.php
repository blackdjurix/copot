<section class="admin-panel" aria-labelledby="role-create-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="role-create-title">Create role</h2>
            <p class="admin-panel__description">Create a permission bundle with an immutable slug.</p>
        </div>
    </header>
    <div class="admin-panel__body">
        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <?php foreach ($errors as $message): ?><div><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-field">
                <label class="admin-field__label" for="name">Display name</label>
                <input id="name" name="name" type="text" maxlength="80" value="<?= htmlspecialchars($values['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="admin-field">
                <label class="admin-field__label" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" maxlength="100" value="<?= htmlspecialchars($values['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">Create role</button>
                <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('roles'), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
