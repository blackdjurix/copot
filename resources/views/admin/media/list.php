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
<?php if ($items === []): ?>
    <div class="admin-panel admin-empty-state"><h2>No media yet</h2><p>Upload an image or document to begin.</p></div>
<?php else: ?>
    <div class="admin-table-wrap"><table class="admin-table"><caption class="sr-only">Core Media inventory</caption><thead><tr><th>Media</th><th>Type</th><th>Usage</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($items as $item): $id = $item->id()->value(); $usageCount = count($evidence[$id] ?? []); ?>
        <tr>
            <td>
                <div class="admin-media-baseline-identity">
                    <div class="admin-media-baseline-thumbnail">
                        <?php if ($item->kind() === 'image'): ?><img src="<?= $esc($mediaUrl($id)) ?>" alt="" loading="lazy">
                        <?php else: ?><span aria-hidden="true">PDF</span><?php endif; ?>
                    </div>
                    <div><strong><?= $esc($item->title()) ?></strong><br><small><?= $esc($item->originalFilename()) ?></small></div>
                </div>
            </td>
            <td><?= $esc($item->mimeType()) ?><?php if ($item->width() !== null): ?><br><small><?= $esc($item->width() . ' × ' . $item->height()) ?></small><?php endif; ?></td>
            <td><?= $usageCount > 0 ? $esc($usageCount . ' reference' . ($usageCount === 1 ? '' : 's')) : 'Unused' ?></td>
            <td><button class="admin-button admin-button--secondary" type="button" data-media-preview-open data-media-title="<?= $esc($item->title()) ?>" data-media-filename="<?= $esc($item->originalFilename()) ?>" data-media-kind="<?= $esc($item->kind()) ?>" data-media-mime="<?= $esc($item->mimeType()) ?>" data-media-width="<?= $esc($item->width() ?? '') ?>" data-media-height="<?= $esc($item->height() ?? '') ?>" data-media-url="<?= $esc($mediaUrl($id)) ?>" data-media-usage="<?= $esc($usageCount) ?>" data-media-delete-url="<?= $esc($adminUrl('media/' . $id . '/delete')) ?>">Preview</button></td>
        </tr>
    <?php endforeach; ?></tbody></table></div>
    <div class="admin-media-preview" data-media-preview hidden aria-hidden="true">
        <div class="admin-media-preview__backdrop" data-media-preview-close></div>
        <section class="admin-media-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="media-preview-title" tabindex="-1">
            <header class="admin-media-preview__header"><h2 id="media-preview-title">Media preview</h2><button class="admin-button admin-button--secondary" type="button" data-media-preview-close>Close</button></header>
            <div class="admin-media-preview__stage" data-media-preview-stage></div>
            <div class="admin-media-preview__details" data-media-preview-details></div>
            <?php if ($canDelete): ?><div class="admin-media-preview__actions"><form method="post" data-media-preview-delete><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><button class="admin-button admin-button--danger" type="submit">Delete</button><p class="admin-form__hint" data-media-preview-delete-help>Deletion is blocked automatically while this media is in use.</p></form></div><?php endif; ?>
        </section>
    </div>
    <script src="/admin-assets/js/admin-core-media.js" defer></script>
<?php endif; ?>
