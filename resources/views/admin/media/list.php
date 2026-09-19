<?php

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$noticeText = match ($notice ?? null) {
    'uploaded' => 'Media uploaded.',
    'deleted' => 'Media deleted.',
    default => null,
};
?>
<?php if ($noticeText): ?><div class="admin-alert admin-alert--success" role="status"><?= $esc($noticeText) ?></div><?php endif; ?>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<div class="admin-panel admin-media-filters admin-filter-toolbar" data-media-list-filters aria-label="Media filters">
    <div class="admin-content-filter-field admin-content-search"><label for="admin-media-search">Search</label><input id="admin-media-search" type="search" data-media-list-search placeholder="Title or filename" autocomplete="off"></div>
    <div class="admin-content-filter-field"><label for="admin-media-kind">File type</label><select id="admin-media-kind" data-media-list-kind><option value="">All types</option><option value="image">Images</option><option value="document">Documents</option></select></div>
    <div class="admin-content-filter-field"><label for="admin-media-usage">Usage</label><select id="admin-media-usage" data-media-list-usage><option value="">All usage</option><option value="used">In use</option><option value="unused">Unused</option></select></div>
    <div class="admin-content-filter-field"><label for="admin-media-page-size">Per page</label><select id="admin-media-page-size" data-media-list-page-size><option value="24">24</option><option value="48">48</option><option value="96">96</option></select></div>
    <div class="admin-content-filter-actions"><button class="admin-button admin-button--secondary" type="button" data-media-list-apply>Apply Filter</button></div>
</div>
<div class="admin-panel admin-media-table-panel">
    <div class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title">Media entries</h2></div><?php if ($canUpload): ?><a class="admin-button admin-button--primary" href="<?= $esc($adminUrl('media/upload')) ?>">Upload Media</a><?php endif; ?></div>
    <div class="admin-panel__body">
    <?php if ($items === []): ?>
        <div class="admin-empty-state"><h3>No media yet</h3><p>Upload an image or document to begin.</p></div>
    <?php else: ?>
    <p class="admin-media-result-context" data-media-list-summary aria-live="polite"></p><div class="admin-table-wrap admin-media-baseline-table"><table class="admin-table" id="admin-media-list-table" aria-label="Media"><thead><tr><th scope="col">Media</th><th scope="col">Type</th><th scope="col">Usage</th></tr></thead><tbody>
    <?php foreach ($items as $item): $id = $item->id()->value(); $usageCount = count($evidence[$id] ?? []); $eligibleForDelete = $canDelete && $usageCount === 0; ?>
        <tr data-media-preview-open data-media-list-row data-media-title-filter="<?= $esc(strtolower($item->title())) ?>" data-media-filename-filter="<?= $esc(strtolower($item->originalFilename())) ?>" data-media-kind-filter="<?= $esc($item->kind()) ?>" data-media-usage-count="<?= $esc($usageCount) ?>" tabindex="0" role="button" aria-label="Preview <?= $esc($item->originalFilename()) ?>" data-media-title="<?= $esc($item->title()) ?>" data-media-filename="<?= $esc($item->originalFilename()) ?>" data-media-kind="<?= $esc($item->kind()) ?>" data-media-mime="<?= $esc($item->mimeType()) ?>" data-media-width="<?= $esc($item->width() ?? '') ?>" data-media-height="<?= $esc($item->height() ?? '') ?>" data-media-url="<?= $esc($mediaUrl($id)) ?>" data-media-usage="<?= $esc($usageCount) ?>" data-media-delete-eligible="<?= $eligibleForDelete ? '1' : '0' ?>" data-media-delete-url="<?= $esc($adminUrl('media/' . $id . '/delete')) ?>">
            <td>
                <div class="admin-media-baseline-identity">
                    <div class="admin-media-baseline-thumbnail">
                        <?php if ($item->kind() === 'image'): ?><img src="<?= $esc($mediaUrl($id)) ?>" alt="" loading="lazy">
                        <?php else: ?><span aria-hidden="true">PDF</span><?php endif; ?>
                    </div>
                    <div><strong><?= $esc($item->originalFilename()) ?></strong></div>
                </div>
            </td>
            <td><?= $esc($item->mimeType()) ?><?php if ($item->width() !== null): ?><br><small><?= $esc($item->width() . ' × ' . $item->height()) ?></small><?php endif; ?></td>
            <td><?= $usageCount > 0 ? $esc($usageCount . ' reference' . ($usageCount === 1 ? '' : 's')) : 'Unused' ?></td>
        </tr>
    <?php endforeach; ?></tbody></table></div><p class="admin-empty-state admin-media-list-no-results" data-media-list-empty hidden role="status">No media matches your filters.</p>
    <?php endif; ?></div><?php if ($canUpload): ?><div class="admin-panel__footer"><a class="admin-button admin-button--primary" href="<?= $esc($adminUrl('media/upload')) ?>">Upload Media</a></div><?php endif; ?></div>
    <div class="admin-media-preview" data-media-preview hidden aria-hidden="true">
        <div class="admin-media-preview__backdrop" data-media-preview-close></div>
        <section class="admin-media-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="media-preview-title" tabindex="-1">
            <button class="admin-media-preview__close" type="button" data-media-preview-close aria-label="Close">×</button>
            <header class="admin-media-preview__header"><h2 id="media-preview-title">Media preview</h2></header>
            <div class="admin-media-preview__stage" data-media-preview-stage></div>
            <dl class="admin-media-preview__details" data-media-preview-details></dl>
            <?php if ($canDelete): ?><div class="admin-media-preview__actions"><form method="post" data-media-preview-delete><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><button class="admin-button admin-button--danger" type="submit" data-media-preview-delete-button>Delete</button></form></div><?php endif; ?>
        </section>
    </div>
    <script src="/admin-assets/js/admin-core-media.js" defer></script><script src="/admin-assets/js/admin-media-list.js?v=wu4-1" defer></script>
