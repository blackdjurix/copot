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
    <div class="admin-panel__heading"><h2 class="admin-panel__title">Media</h2><p class="admin-panel__description">Manage uploaded images and documents without exposing storage details.</p></div>
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
    <section class="admin-panel admin-media-table-panel" aria-label="Media items">
        <div class="admin-panel__body">
            <div class="admin-table-wrap admin-media-table-wrap"><table class="admin-table admin-media-table"><thead><tr><th>Preview</th><th>Media</th><th>Details</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($mediaItems as $item): $editable = $isEditable($item); ?>
        <tr>
            <td data-label="Preview"><?php if ($item->kind() === 'image'): ?><img src="<?= $esc('/media/' . $item->id()->value()) ?>" alt="<?= $esc($item->title()) ?>" width="96" height="72" loading="lazy"><?php else: ?><span aria-label="PDF or document" class="admin-media-document">Document</span><?php endif; ?></td>
            <td data-label="Media"><strong><?= $esc($item->title()) ?></strong><br><span><?= $esc($item->originalFilename()) ?></span><br><span><?= $esc(ucfirst($item->kind())) ?> · <?= $esc($item->mimeType()) ?></span></td>
            <td data-label="Details"><?= $item->width() !== null ? $esc($item->width() . ' × ' . $item->height()) . '<br>' : '' ?><?= $esc($formatBytes($item->byteSize())) ?><br><span><?= $editable ? 'Editable' : 'Manage-only' ?></span><br><time datetime="<?= $esc($item->updatedAt()) ?>"><?= $esc($item->updatedAt()) ?></time></td>
            <td data-label="Actions"><div class="admin-row-actions"><a class="admin-button admin-button--link" href="<?= $esc('/media/' . $item->id()->value()) ?>">View</a><a class="admin-button admin-button--link" href="<?= $esc('/media/' . $item->id()->value() . '/download') ?>">Download</a>
                <?php if ($canEdit): ?><form method="post" action="<?= $esc($adminUrl('media/' . $item->id()->value() . '/title')) ?>"><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><label class="admin-sr-only" for="media-title-<?= $item->id()->value() ?>">Title</label><input id="media-title-<?= $item->id()->value() ?>" name="title" value="<?= $esc($item->title()) ?>" maxlength="190"><button class="admin-button admin-button--link" type="submit">Save title</button></form><?php endif; ?>
                <?php if ($canEdit && $editable): ?><form method="post" action="<?= $esc($adminUrl('media/' . $item->id()->value() . '/process')) ?>"><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><label class="admin-sr-only" for="media-preset-<?= $item->id()->value() ?>">Processing preset</label><select id="media-preset-<?= $item->id()->value() ?>" name="preset"><option value="square">Square</option><option value="landscape">Landscape</option><option value="contain">Contain</option></select><button class="admin-button admin-button--link" type="submit">Process</button></form><?php endif; ?>
            </div></td>
        </tr>
            <?php endforeach; ?></tbody></table></div>
            <nav class="admin-content-pagination admin-pagination" aria-label="Media pagination"><span>Page <?= $esc($page) ?> of <?= $esc($lastPage) ?></span><?php if ($page > 1): ?><a class="admin-button admin-button--link" href="<?= $esc($paginationUrl($page - 1)) ?>">Previous</a><?php endif; ?><?php if ($page < $lastPage): ?><a class="admin-button admin-button--link" href="<?= $esc($paginationUrl($page + 1)) ?>">Next</a><?php endif; ?></nav>
        </div>
    </section>
<?php endif; ?>
