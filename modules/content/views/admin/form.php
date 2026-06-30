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
            <label for="type">Type</label><br>
            <select id="type" name="type">
                <?php foreach (['page' => 'Page', 'article' => 'Article'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (($content['type'] ?? 'page') === $value) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="title">Title</label><br>
            <input id="title" name="title" type="text" value="<?= htmlspecialchars($content['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        </p>

        <p>
            <label for="slug">Slug</label><br>
            <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($content['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </p>

        <p>
            <label for="excerpt">Excerpt</label><br>
            <textarea id="excerpt" name="excerpt" rows="3"><?= htmlspecialchars($content['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </p>

        <p>
            <label for="body">Body</label><br>
            <textarea id="body" name="body" rows="12" required><?= htmlspecialchars($content['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </p>

        <p>
            <label for="status">Status</label><br>
            <?php if (($content['status'] ?? 'draft') === 'archived'): ?>
                <input type="hidden" name="status" value="archived">
                Archived
            <?php elseif (!empty($canPublish)): ?>
                <select id="status" name="status">
                    <option value="draft" <?= (($content['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= (($content['status'] ?? 'draft') === 'published') ? 'selected' : '' ?>>Published</option>
                </select>
            <?php else: ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($content['status'] ?? 'draft', ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars(ucfirst($content['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </p>

        <?php if (empty($canPublish)): ?>
            <p>Publishing status changes require content.publish permission.</p>
        <?php endif; ?>

        <?php if (!empty($taxonomy['available'])): ?>
            <fieldset>
                <legend>Taxonomy</legend>

                <p>
                    <strong>Categories</strong><br>
                    <?php if (empty($taxonomy['categories'])): ?>
                        No category terms yet.
                    <?php endif; ?>

                    <?php foreach (($taxonomy['categories'] ?? []) as $term): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="category_ids[]"
                                value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>"
                                <?= in_array($term->id(), $selectedTaxonomy['category_ids'] ?? [], true) ? 'checked' : '' ?>
                            >
                            <?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?>
                        </label><br>
                    <?php endforeach; ?>
                </p>

                <p>
                    <strong>Tags</strong><br>
                    <?php if (empty($taxonomy['tags'])): ?>
                        No tag terms yet.
                    <?php endif; ?>

                    <?php foreach (($taxonomy['tags'] ?? []) as $term): ?>
                        <label>
                            <input
                                type="checkbox"
                                name="tag_ids[]"
                                value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>"
                                <?= in_array($term->id(), $selectedTaxonomy['tag_ids'] ?? [], true) ? 'checked' : '' ?>
                            >
                            <?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?>
                        </label><br>
                    <?php endforeach; ?>
                </p>
            </fieldset>
        <?php endif; ?>

        <p>
            <button type="submit"><?= htmlspecialchars($submitLabel ?? 'Save content', ENT_QUOTES, 'UTF-8') ?></button>
        </p>
    </form>
</section>
