<section class="admin-panel" aria-labelledby="content-form-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="content-form-title">Content details</h2>
            <p class="admin-panel__description">Create or update the content entry and its publishing details.</p>
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
                <label class="admin-field__label" for="type">Type</label>
                <select id="type" name="type">
                    <?php foreach (['page' => 'Page', 'article' => 'Article'] as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (($content['type'] ?? 'page') === $value) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="title">
                    Title
                    <span class="admin-field__required" aria-hidden="true">*</span>
                    <span class="admin-visually-hidden">required</span>
                </label>
                <input id="title" name="title" type="text" value="<?= htmlspecialchars($content['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($content['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="excerpt">Excerpt</label>
                <textarea id="excerpt" name="excerpt" rows="3"><?= htmlspecialchars($content['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="body">
                    Body
                    <span class="admin-field__required" aria-hidden="true">*</span>
                    <span class="admin-visually-hidden">required</span>
                </label>
                <textarea id="body" name="body" rows="12" required><?= htmlspecialchars($content['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="admin-field">
                <span class="admin-field__label">Status</span>
                <?php if (($content['status'] ?? 'draft') === 'archived'): ?>
                    <input type="hidden" name="status" value="archived">
                    <p class="admin-field__help">Archived</p>
                <?php elseif (!empty($canPublish)): ?>
                    <label class="admin-visually-hidden" for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= (($content['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= (($content['status'] ?? 'draft') === 'published') ? 'selected' : '' ?>>Published</option>
                    </select>
                <?php else: ?>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($content['status'] ?? 'draft', ENT_QUOTES, 'UTF-8') ?>">
                    <p class="admin-field__help"><?= htmlspecialchars(ucfirst($content['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if (empty($canPublish)): ?>
                    <p class="admin-field__help">Publishing status changes require content.publish permission.</p>
                <?php endif; ?>
            </div>

            <?php if (!empty($taxonomy['available'])): ?>
                <fieldset class="admin-fieldset">
                    <legend class="admin-fieldset__legend">Taxonomy</legend>

                    <div class="admin-field">
                        <span class="admin-field__label">Categories</span>
                        <?php if (empty($taxonomy['categories'])): ?>
                            <p class="admin-field__help">No category terms yet.</p>
                        <?php else: ?>
                            <div class="admin-check-list">
                                <?php foreach (($taxonomy['categories'] ?? []) as $term): ?>
                                    <label class="admin-check-option">
                                        <input
                                            type="checkbox"
                                            name="category_ids[]"
                                            value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= in_array($term->id(), $selectedTaxonomy['category_ids'] ?? [], true) ? 'checked' : '' ?>
                                        >
                                        <span><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="admin-field">
                        <span class="admin-field__label">Tags</span>
                        <?php if (empty($taxonomy['tags'])): ?>
                            <p class="admin-field__help">No tag terms yet.</p>
                        <?php else: ?>
                            <div class="admin-check-list">
                                <?php foreach (($taxonomy['tags'] ?? []) as $term): ?>
                                    <label class="admin-check-option">
                                        <input
                                            type="checkbox"
                                            name="tag_ids[]"
                                            value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>"
                                            <?= in_array($term->id(), $selectedTaxonomy['tag_ids'] ?? [], true) ? 'checked' : '' ?>
                                        >
                                        <span><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>
            <?php endif; ?>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">
                    <?= htmlspecialchars($submitLabel ?? 'Save content', ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</section>
