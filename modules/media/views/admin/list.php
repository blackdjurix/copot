<?php

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};
$noticeText = match ($notice ?? null) {
    'uploaded' => 'Media uploaded.',
    'title-updated' => 'Media title updated.',
    'processed' => 'Processing preset completed.',
    default => null,
};
?>
<header class="admin-panel__header">
    <div class="admin-panel__heading"><h2 class="admin-panel__title">Media</h2><p class="admin-panel__description">Browse and manage uploaded images and documents without exposing storage details.</p></div>
    <?php if ($canUpload): ?><div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= $esc($adminUrl('media/upload')) ?>">Upload media</a></div><?php endif; ?>
</header>
<?php if ($noticeText): ?><div class="admin-alert admin-alert--success" role="status"><?= $esc($noticeText) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<form class="admin-panel admin-content-filters admin-media-filters" method="get" action="<?= $esc($adminUrl('media')) ?>" aria-label="Media filters">
    <label>Search<input type="search" name="q" value="<?= $esc($search) ?>" placeholder="Title or original filename"></label>
    <label>Kind<select name="kind"><option value="">All</option><option value="image"<?= $selectedKind === 'image' ? ' selected' : '' ?>>Image</option><option value="document"<?= $selectedKind === 'document' ? ' selected' : '' ?>>Document</option></select></label>
    <label>Capability<select name="capability"><option value="">All</option><option value="editable"<?= $selectedCapability === 'editable' ? ' selected' : '' ?>>Editable</option><option value="manage-only"<?= $selectedCapability === 'manage-only' ? ' selected' : '' ?>>Manage-only</option></select></label>
    <button class="admin-button admin-button--secondary" type="submit">Apply filters</button>
    <?php if ($hasFilters): ?><a class="admin-button admin-button--link" href="<?= $esc($adminUrl('media')) ?>">Clear filters</a><?php endif; ?>
</form>
<?php if ($total === 0): ?>
    <div class="admin-empty-state"><h3 class="admin-empty-state__title"><?= $hasFilters ? 'No matching media' : 'No media yet' ?></h3><p class="admin-empty-state__description"><?= $hasFilters ? 'Try changing the search or filters.' : 'Upload the first image or document.' ?></p></div>
<?php else: ?>
    <section class="admin-panel admin-media-grid-panel" aria-label="Media items">
        <div class="admin-panel__body">
            <div class="admin-media-grid">
                <?php foreach ($mediaItems as $index => $item): $editable = $isEditable($item); $mediaId = $item->id()->value(); ?>
                    <article class="admin-media-card" tabindex="0" role="button" data-media-card data-media-index="<?= $esc($index) ?>" data-media-id="<?= $esc($mediaId) ?>" data-media-title="<?= $esc($item->title()) ?>" data-media-filename="<?= $esc($item->originalFilename()) ?>" data-media-kind="<?= $esc($item->kind()) ?>" data-media-mime="<?= $esc($item->mimeType()) ?>" data-media-bytes="<?= $esc($formatBytes($item->byteSize())) ?>" data-media-width="<?= $esc($item->width() ?? '') ?>" data-media-height="<?= $esc($item->height() ?? '') ?>" data-media-updated="<?= $esc($item->updatedAt()) ?>" data-media-editable="<?= $editable ? '1' : '0' ?>" data-media-public-url="<?= $esc('/media/' . $mediaId) ?>" data-media-title-url="<?= $esc($adminUrl('media/' . $mediaId . '/title')) ?>" data-media-process-url="<?= $esc($adminUrl('media/' . $mediaId . '/process')) ?>" aria-label="Open media <?= $esc($item->title()) ?>">
                        <div class="admin-media-card__preview">
                            <?php if ($item->kind() === 'image'): ?><img src="<?= $esc('/media/' . $item->id()->value()) ?>" alt="<?= $esc($item->title()) ?>" loading="lazy">
                            <?php else: ?><div class="admin-media-card__document" aria-label="PDF or document"><span class="admin-media-card__document-icon" aria-hidden="true">PDF</span><span>Document</span></div><?php endif; ?>
                        </div>
                        <div class="admin-media-card__body">
                            <div class="admin-media-card__identity"><h3><?= $esc($item->title()) ?></h3><p><?= $esc($item->originalFilename()) ?></p></div>
                            <div class="admin-media-card__meta"><span class="admin-badge <?= $item->kind() === 'document' ? 'admin-badge--warning' : 'admin-badge--info' ?>"><?= $esc(ucfirst($item->kind())) ?></span><span><?= $esc($formatBytes($item->byteSize())) ?></span><?php if ($item->width() !== null): ?><span><?= $esc($item->width() . ' × ' . $item->height()) ?></span><?php endif; ?><span><?= $editable ? 'Editable' : 'Manage-only' ?></span></div>
                            <p class="admin-media-card__timestamp"><time datetime="<?= $esc($item->updatedAt()) ?>">Updated <?= $esc($item->updatedAt()) ?></time></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <nav class="admin-content-pagination admin-pagination" aria-label="Media pagination"><span>Page <?= $esc($page) ?> of <?= $esc($lastPage) ?></span><?php if ($page > 1): ?><a class="admin-button admin-button--link" href="<?= $esc($paginationUrl($page - 1)) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="admin-button admin-button--link" href="<?= $esc($paginationUrl($page + 1)) ?>">Next</a><?php endif; ?></nav>
        </div>
    </section>
    <div class="admin-media-preview" data-media-preview hidden aria-hidden="true">
        <div class="admin-media-preview__backdrop" data-media-preview-close></div>
        <section class="admin-media-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="media-preview-title" tabindex="-1">
            <header class="admin-media-preview__header"><h2 id="media-preview-title">Media preview</h2><button class="admin-button admin-button--secondary" type="button" data-media-preview-close aria-label="Close media preview">Close</button></header>
            <button class="admin-media-preview__nav admin-button admin-button--secondary" type="button" data-media-preview-prev aria-label="Previous media">Previous</button>
            <div class="admin-media-preview__stage" data-media-preview-stage></div>
            <button class="admin-media-preview__nav admin-button admin-button--secondary" type="button" data-media-preview-next aria-label="Next media">Next</button>
            <div class="admin-media-preview__details" data-media-preview-details></div>
            <div class="admin-media-preview__actions" data-media-preview-actions></div>
        </section>
    </div>
    <template data-media-preview-actions-template>
        <a class="admin-button admin-button--secondary" data-preview-public-link href="">Open public view</a>
        <?php if ($canEdit): ?>
            <form method="post" data-preview-title-form><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><label class="admin-sr-only" data-preview-title-label>Title</label><input name="title" maxlength="190" data-preview-title-input><button class="admin-button admin-button--secondary" type="submit">Save title</button></form>
            <div class="admin-media-preview__process" data-preview-process-actions>
                <?php foreach (['square' => 'Square', 'landscape' => 'Landscape', 'contain' => 'Contain'] as $preset => $label): ?><form method="post" data-preview-process-form><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><input type="hidden" name="preset" value="<?= $esc($preset) ?>"><button class="admin-button admin-button--secondary" type="submit"><?= $esc($label) ?></button></form><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </template>
    <script src="/admin-assets/js/admin-media.js" defer></script>
<?php endif; ?>
