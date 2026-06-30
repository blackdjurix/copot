<section class="panel">
    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $error): ?>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <p>
            <label for="name">Name</label><br>
            <input id="name" name="name" type="text" value="<?= htmlspecialchars($term['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

        <p>
            <label for="slug">Slug</label><br>
            <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($term['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </p>

        <p>
            <label for="description">Description</label><br>
            <textarea id="description" name="description" rows="4"><?= htmlspecialchars($term['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </p>

        <p>
            <label for="sort_order">Sort order</label><br>
            <input id="sort_order" name="sort_order" type="number" value="<?= htmlspecialchars((string) ($term['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>">
        </p>

        <p>
            <button type="submit"><?= htmlspecialchars($submitLabel ?? 'Save term', ENT_QUOTES, 'UTF-8') ?></button>
            <a href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '')), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
        </p>
    </form>
</section>
