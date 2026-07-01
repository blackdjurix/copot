<section class="admin-panel" aria-labelledby="taxonomy-form-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-form-title">Term details</h2>
            <p class="admin-panel__description">Create or update a reusable taxonomy term.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <strong class="admin-alert__title">Please correct the following errors.</strong>
                <ul class="admin-alert__list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <div class="admin-field">
                <label class="admin-field__label" for="name">
                    Name
                    <span class="admin-field__required" aria-hidden="true">*</span>
                    <span class="admin-visually-hidden">required</span>
                </label>
                <input id="name" name="name" type="text" value="<?= htmlspecialchars($term['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($term['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($term['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="sort_order">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" value="<?= htmlspecialchars((string) ($term['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">
                    <?= htmlspecialchars($submitLabel ?? 'Save term', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '')), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
