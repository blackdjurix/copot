<section class="admin-panel" aria-labelledby="navigation-menu-form-title">
    <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="navigation-menu-form-title">Menu details</h2><p class="admin-panel__description"><?= htmlspecialchars($heading ?? 'Menu details', ENT_QUOTES, 'UTF-8') ?>.</p></div></header>
    <div class="admin-panel__body">
        <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert" id="navigation-menu-errors"><strong>Please correct the following errors.</strong><ul class="admin-alert__list"><?php foreach ($errors as $fieldErrors): foreach ((array) $fieldErrors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; endforeach; ?></ul></div><?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <div class="admin-field"><label class="admin-field__label" for="name">Name</label><input id="name" name="name" type="text" required value="<?= htmlspecialchars($menu['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div class="admin-field"><label class="admin-field__label" for="slug">Slug</label><input id="slug" name="slug" type="text" required value="<?= htmlspecialchars($menu['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><p class="admin-field__help">Lowercase letters, numbers, and separators are normalized by NavigationService.</p></div>
            <div class="admin-actions admin-form__actions"><a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('navigation'), ENT_QUOTES, 'UTF-8') ?>">Cancel</a><button class="admin-button admin-button--primary" type="submit"><?= htmlspecialchars($submitLabel ?? 'Save menu', ENT_QUOTES, 'UTF-8') ?></button></div>
        </form>
    </div>
</section>
