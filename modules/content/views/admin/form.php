<?php
$fieldErrors = [
    'type' => [],
    'title' => [],
    'slug' => [],
    'excerpt' => [],
    'body' => [],
    'featured_media' => [],
    'status' => [],
    'taxonomy' => [],
];
$formErrors = [];

foreach (($errors ?? []) as $error) {
    $error = (string) $error;

    if (str_contains($error, 'Title is required.')) {
        $fieldErrors['title'][] = $error;
    } elseif (str_contains($error, 'Body is required.')) {
        $fieldErrors['body'][] = $error;
    } elseif (str_contains($error, 'slug')) {
        $fieldErrors['slug'][] = $error;
    } elseif (str_contains($error, 'Publishing status')) {
        $fieldErrors['status'][] = $error;
    } elseif (str_contains($error, 'Taxonomy')) {
        $fieldErrors['taxonomy'][] = $error;
    } elseif (str_contains($error, 'Featured Media')) {
        $fieldErrors['featured_media'][] = $error;
    } else {
        $formErrors[] = $error;
    }
}

$allFormErrors = array_merge($formErrors, ...array_values($fieldErrors));
$fieldErrorId = static fn (string $field): string => 'content-' . $field . '-error';
$fieldDescribedBy = static function (string $field) use ($fieldErrors, $fieldErrorId): string {
    return $fieldErrors[$field] === [] ? '' : $fieldErrorId($field);
};
$fieldHasError = static fn (string $field): bool => $fieldErrors[$field] !== [];
$renderFieldErrors = static function (string $field) use ($fieldErrors, $fieldErrorId): void {
    if ($fieldErrors[$field] === []) {
        return;
    }

    echo '<div class="admin-field__error" id="' . htmlspecialchars($fieldErrorId($field), ENT_QUOTES, 'UTF-8') . '">';
    foreach ($fieldErrors[$field] as $error) {
        echo '<p>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</div>';
};
$taxonomyAvailable = !empty($taxonomy['available']);
$contentAdminUrl = is_callable($adminUrl ?? null)
    ? $adminUrl
    : static fn (string $path = ''): string => '/' . trim($path, '/');
?>
<section class="admin-content-form-page" aria-labelledby="content-form-title">
    <header class="admin-content-form-header">
        <div>
            <p class="admin-content-eyebrow"><?= ($formMode ?? 'create') === 'edit' ? 'Content workspace' : 'Content workspace' ?></p>
            <h2 id="content-form-title"><?= htmlspecialchars($heading ?? 'Content details', ENT_QUOTES, 'UTF-8') ?></h2>
            <p><?= ($formMode ?? 'create') === 'edit' ? 'Update the content entry and its publishing details.' : 'Create a page or article and prepare it for publishing.' ?></p>
        </div>
        <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($contentAdminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">Back to content</a>
    </header>

    <div class="admin-panel">
        <div class="admin-content-form-card">
        <?php if ($allFormErrors !== []): ?>
            <div class="admin-alert admin-alert--danger" role="alert" id="content-form-errors" aria-live="assertive" tabindex="-1">
                <strong class="admin-alert__title">Please correct the following errors.</strong>
                <ul class="admin-alert__list">
                    <?php foreach ($formErrors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                    <?php foreach ($fieldErrors as $field => $messages): ?>
                        <?php foreach ($messages as $error): ?>
                            <?php $errorTarget = $field === 'taxonomy' && !$taxonomyAvailable ? null : $field; ?>
                            <li><?php if ($errorTarget !== null): ?><a href="#<?= htmlspecialchars($errorTarget, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></a><?php else: ?><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="admin-form admin-content-form" method="post" action="<?= htmlspecialchars($formAction, ENT_QUOTES, 'UTF-8') ?>"<?= $allFormErrors !== [] ? ' aria-describedby="content-form-errors"' : '' ?>>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php if (!empty($content['updated_at'])): ?>
                <input type="hidden" name="expected_updated_at" value="<?= htmlspecialchars($content['updated_at'], ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>

            <div class="admin-content-form-layout">
                <fieldset class="admin-content-form-section admin-content-form-section--main admin-fieldset">
                    <legend>Content details</legend>

                    <div class="admin-field">
                        <label class="admin-field__label" for="type">Type</label>
                        <select id="type" name="type"<?= $fieldHasError('type') ? ' aria-invalid="true" aria-describedby="' . htmlspecialchars($fieldDescribedBy('type'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php foreach (['page' => 'Page', 'article' => 'Article'] as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= (($content['type'] ?? 'page') === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php $renderFieldErrors('type'); ?>
                    </div>

                    <div class="admin-field">
                        <label class="admin-field__label" for="title">
                            Title
                            <span class="admin-field__required" aria-hidden="true">*</span>
                            <span class="admin-visually-hidden">required</span>
                        </label>
                        <input id="title" name="title" type="text" value="<?= htmlspecialchars($content['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required<?= $fieldHasError('title') ? ' aria-invalid="true" aria-describedby="' . htmlspecialchars($fieldDescribedBy('title'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                        <?php $renderFieldErrors('title'); ?>
                    </div>

                    <div class="admin-field">
                        <label class="admin-field__label" for="slug">Slug</label>
                        <input id="slug" name="slug" type="text" value="<?= htmlspecialchars($content['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" aria-describedby="slug-help<?= $fieldHasError('slug') ? ' ' . htmlspecialchars($fieldDescribedBy('slug'), ENT_QUOTES, 'UTF-8') : '' ?>"<?= $fieldHasError('slug') ? ' aria-invalid="true"' : '' ?>>
                        <p class="admin-field__help" id="slug-help">Leave blank to derive the slug from the title. Existing slugs remain stable when only the title changes.</p>
                        <?php $renderFieldErrors('slug'); ?>
                    </div>

                    <div class="admin-field">
                        <label class="admin-field__label" for="excerpt">Excerpt</label>
                        <textarea id="excerpt" name="excerpt" rows="4"<?= $fieldHasError('excerpt') ? ' aria-invalid="true" aria-describedby="' . htmlspecialchars($fieldDescribedBy('excerpt'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($content['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <?php $renderFieldErrors('excerpt'); ?>
                    </div>

                    <div class="admin-field">
                        <label class="admin-field__label" for="body">
                            Body
                            <span class="admin-field__required" aria-hidden="true">*</span>
                            <span class="admin-visually-hidden">required</span>
                        </label>
                        <textarea id="body" name="body" rows="14" required aria-describedby="body-help<?= $fieldHasError('body') ? ' ' . htmlspecialchars($fieldDescribedBy('body'), ENT_QUOTES, 'UTF-8') : '' ?>"<?= $fieldHasError('body') ? ' aria-invalid="true"' : '' ?>><?= htmlspecialchars($content['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <p class="admin-field__help" id="body-help">Use plain text content. Rich text, media, and preview workflows remain outside this batch.</p>
                        <?php $renderFieldErrors('body'); ?>
                    </div>
                </fieldset>

                <div class="admin-content-form-sidebar">
                    <fieldset class="admin-content-form-section" data-media-picker data-content-id="<?= htmlspecialchars((string) ($content['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-picker-url="<?= htmlspecialchars($contentAdminUrl('media/context-picker'), ENT_QUOTES, 'UTF-8') ?>" data-upload-url="<?= htmlspecialchars($contentAdminUrl('media/context-picker/upload'), ENT_QUOTES, 'UTF-8') ?>" data-media-url="<?= htmlspecialchars($url('/media/'), ENT_QUOTES, 'UTF-8') ?>" data-csrf-token="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <legend>Featured Media</legend>
                        <input type="hidden" name="featured_media_id" value="<?= htmlspecialchars((string) ($content['featured_media_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-media-picker-input>
                        <input type="hidden" name="featured_media_pending_token" value="" data-media-picker-pending-input>
                        <p class="admin-field__help" data-media-picker-status aria-live="polite"><?= !empty($content['featured_media_id']) ? 'A featured image is selected.' : 'Optional. Select an image for this content entry.' ?></p>
                        <div class="admin-media-picker__selected" data-media-picker-selected hidden></div>
                        <?php $renderFieldErrors('featured_media'); ?>
                        <div class="admin-actions">
                            <button class="admin-button admin-button--secondary" type="button" data-media-picker-open>Select Media</button>
                            <button class="admin-button admin-button--link" type="button" data-media-picker-clear>Clear</button>
                        </div>
                        <label class="admin-field__label" for="featured-media-upload">Upload one image</label>
                        <input id="featured-media-upload" type="file" accept="image/jpeg,image/png,image/webp" data-media-picker-upload>
                        <button class="admin-button admin-button--secondary" type="button" data-media-picker-upload-button>Upload and prepare</button>
                        <dialog class="admin-media-picker" data-media-picker-dialog aria-labelledby="media-picker-title">
                            <div class="admin-media-picker__panel">
                                <h3 id="media-picker-title">Select featured Media</h3>
                                <label for="media-picker-search">Search title or original filename</label>
                                <input id="media-picker-search" type="search" data-media-picker-search>
                                <div class="admin-media-picker__results" data-media-picker-results aria-live="polite"></div>
                                <div class="admin-media-picker__prepare" data-media-picker-prepare hidden><p data-media-picker-prepare-status aria-live="polite">Prepare featured image</p><div class="admin-media-picker__crop-frame" data-media-picker-crop-frame><img data-media-picker-crop-image alt="Source image for crop" draggable="false"><div class="admin-media-picker__crop-box" data-media-picker-crop-box role="group" tabindex="0" aria-label="16:9 crop box. Drag to move. Use arrow keys to position; Shift plus arrow keys to resize."><span class="admin-media-picker__crop-handle admin-media-picker__crop-handle--nw" data-media-picker-crop-handle="nw" aria-hidden="true"></span><span class="admin-media-picker__crop-handle admin-media-picker__crop-handle--ne" data-media-picker-crop-handle="ne" aria-hidden="true"></span><span class="admin-media-picker__crop-handle admin-media-picker__crop-handle--sw" data-media-picker-crop-handle="sw" aria-hidden="true"></span><span class="admin-media-picker__crop-handle admin-media-picker__crop-handle--se" data-media-picker-crop-handle="se" aria-hidden="true"></span></div></div><input type="hidden" value="0" data-media-picker-crop-x><input type="hidden" value="0" data-media-picker-crop-y><input type="hidden" value="0" data-media-picker-crop-width><input type="hidden" value="0" data-media-picker-crop-height><div class="admin-actions"><button type="button" class="admin-button admin-button--primary" data-media-picker-process>Confirm crop</button><button type="button" class="admin-button admin-button--secondary" data-media-picker-process-cancel data-media-picker-choose-another>Choose another</button></div></div>
                                <div class="admin-actions"><button type="button" class="admin-button admin-button--secondary" data-media-picker-cancel>Cancel</button></div>
                            </div>
                        </dialog>
                    </fieldset>
                    <fieldset class="admin-content-form-section">
                        <legend>Status</legend>
                        <?php if (($content['status'] ?? 'draft') === 'archived'): ?>
                            <input type="hidden" name="status" value="archived">
                            <p class="admin-content-form-status admin-content-form-status--archived">Archived</p>
                        <?php elseif (!empty($canPublish)): ?>
                            <label class="admin-field__label" for="status">Publishing status</label>
                            <select id="status" name="status"<?= $fieldHasError('status') ? ' aria-invalid="true" aria-describedby="' . htmlspecialchars($fieldDescribedBy('status'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                                <option value="draft" <?= (($content['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Draft</option>
                                <option value="published" <?= (($content['status'] ?? 'draft') === 'published') ? 'selected' : '' ?>>Published</option>
                            </select>
                        <?php else: ?>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($content['status'] ?? 'draft', ENT_QUOTES, 'UTF-8') ?>">
                            <p class="admin-content-form-status"><?= htmlspecialchars(ucfirst($content['status'] ?? 'draft'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="admin-field__help">Publishing status changes require content.publish permission.</p>
                        <?php endif; ?>
                        <?php $renderFieldErrors('status'); ?>
                    </fieldset>

                    <?php if ($taxonomyAvailable): ?>
                        <fieldset id="taxonomy" class="admin-content-form-section admin-fieldset"<?= $fieldHasError('taxonomy') ? ' aria-describedby="' . htmlspecialchars($fieldDescribedBy('taxonomy'), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <legend>Taxonomy</legend>

                            <fieldset class="admin-content-taxonomy-group">
                                <legend>Categories</legend>
                                <?php if (empty($taxonomy['categories'])): ?>
                                    <p class="admin-field__help">No category terms yet.</p>
                                <?php else: ?>
                                    <div class="admin-check-list">
                                        <?php foreach (($taxonomy['categories'] ?? []) as $term): ?>
                                            <label class="admin-check-option">
                                                <input type="checkbox" name="category_ids[]" value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>" <?= in_array($term->id(), $selectedTaxonomy['category_ids'] ?? [], true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>

                            <fieldset class="admin-content-taxonomy-group">
                                <legend>Tags</legend>
                                <?php if (empty($taxonomy['tags'])): ?>
                                    <p class="admin-field__help">No tag terms yet.</p>
                                <?php else: ?>
                                    <div class="admin-check-list">
                                        <?php foreach (($taxonomy['tags'] ?? []) as $term): ?>
                                            <label class="admin-check-option">
                                                <input type="checkbox" name="tag_ids[]" value="<?= htmlspecialchars((string) $term->id(), ENT_QUOTES, 'UTF-8') ?>" <?= in_array($term->id(), $selectedTaxonomy['tag_ids'] ?? [], true) ? 'checked' : '' ?>>
                                                <span><?= htmlspecialchars($term->name(), ENT_QUOTES, 'UTF-8') ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </fieldset>
                            <?php $renderFieldErrors('taxonomy'); ?>
                        </fieldset>
                    <?php endif; ?>
                </div>
            </div>

            <div class="admin-actions admin-form__actions admin-content-form-actions">
                <a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($contentAdminUrl('content'), ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
                <button class="admin-button admin-button--primary" type="submit"><?= htmlspecialchars($submitLabel ?? 'Save content', ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </form>
        </div>
    </div>
</section>
<script src="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/admin-assets/js/content-media-picker.js') : '/admin-assets/js/content-media-picker.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
