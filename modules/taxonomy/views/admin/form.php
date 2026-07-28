<section class="admin-panel" aria-labelledby="taxonomy-form-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="taxonomy-form-title">Term details</h2>
            <p class="admin-panel__description">
                <?= htmlspecialchars($heading ?? 'Term details', ENT_QUOTES, 'UTF-8') ?>.
                <?php if ($type?->slug() === 'category'): ?> Categories can be organized under a parent; choose root category for no parent.
                <?php else: ?> Tags are flat and do not use a parent.
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert--danger" role="alert" id="taxonomy-form-errors">
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

            <?php
            $fieldErrors = ['name' => [], 'slug' => [], 'description' => [], 'sort_order' => [], 'parent_id' => []];
            foreach (($errors ?? []) as $error) {
                $field = str_contains(strtolower((string) $error), 'sort order') ? 'sort_order' : (str_contains(strtolower((string) $error), 'parent') || str_contains(strtolower((string) $error), 'category') ? 'parent_id' : 'name');
                $fieldErrors[$field][] = $error;
            }
            $describedBy = static function (string $field) use ($fieldErrors): string {
                return $fieldErrors[$field] === [] ? '' : 'taxonomy-error-' . $field;
            };
            $fieldAttributes = static function (string $field) use ($fieldErrors, $describedBy): string {
                if ($fieldErrors[$field] === []) {
                    return '';
                }

                return ' aria-describedby="' . htmlspecialchars($describedBy($field), ENT_QUOTES, 'UTF-8') . '" aria-invalid="true"';
            };
            ?>
            <div class="admin-field">
                <label class="admin-field__label" for="name">
                    Name
                    <span class="admin-field__required" aria-hidden="true">*</span>
                    <span class="admin-visually-hidden">required</span>
                </label>
                <input id="name" name="name" type="text" value="<?= htmlspecialchars($term['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required<?= $fieldAttributes('name') ?>">
                <?php if ($fieldErrors['name'] !== []): ?><p class="admin-field__error" id="taxonomy-error-name"><?= htmlspecialchars(implode(' ', $fieldErrors['name']), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="slug">Slug</label>
                <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($term['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>"<?= $fieldAttributes('slug') ?>>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="description">Description</label>
                <textarea id="description" name="description" rows="4"><?= htmlspecialchars($term['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

            <div class="admin-field">
                <label class="admin-field__label" for="sort_order">Sort order</label>
                <input id="sort_order" name="sort_order" type="number" value="<?= htmlspecialchars((string) ($term['sort_order'] ?? 0), ENT_QUOTES, 'UTF-8') ?>"<?= $fieldAttributes('sort_order') ?>>
                <?php if ($fieldErrors['sort_order'] !== []): ?><p class="admin-field__error" id="taxonomy-error-sort_order"><?= htmlspecialchars(implode(' ', $fieldErrors['sort_order']), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>

            <?php if ($type?->isHierarchical()): ?>
                <div class="admin-field">
                    <label class="admin-field__label" for="parent_id">Parent category</label>
                    <select id="parent_id" name="parent_id"<?= $fieldAttributes('parent_id') ?>>
                        <option value="">Root category (no parent)</option>
                        <?php foreach (($parentCandidates ?? []) as $candidate): ?>
                            <?php $candidateTerm = $candidate['term']; ?>
                            <option value="<?= htmlspecialchars((string) $candidateTerm->id(), ENT_QUOTES, 'UTF-8') ?>" <?= (string) ($term['parent_id'] ?? '') === (string) $candidateTerm->id() ? 'selected' : '' ?>><?= str_repeat('— ', (int) ($candidate['depth'] ?? 0)) . htmlspecialchars($candidateTerm->name(), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($fieldErrors['parent_id'] !== []): ?><p class="admin-field__error" id="taxonomy-error-parent_id"><?= htmlspecialchars(implode(' ', $fieldErrors['parent_id']), ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">
                    <?= htmlspecialchars($submitLabel ?? 'Save term', ENT_QUOTES, 'UTF-8') ?>
                </button>
                <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('taxonomy/' . ($type?->slug() ?? '')), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            </div>
        </form>
    </div>
</section>
