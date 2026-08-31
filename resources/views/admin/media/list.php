<?php

$esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$noticeText = match ($notice ?? null) {
    'uploaded' => 'Media uploaded.',
    'title-updated' => 'Media title updated.',
    'deleted' => 'Media deleted.',
    default => null,
};
?>
<section class="admin-stack" aria-labelledby="core-media-title">
    <header class="admin-page-heading">
        <div class="admin-page-heading__copy">
            <h2 class="admin-page-heading__title" id="core-media-title">Media</h2>
            <p class="admin-page-heading__description">Core Media inventory and original files.</p>
        </div>
        <?php if ($canUpload): ?><a class="admin-button admin-button--primary" href="<?= $esc($adminUrl('media/upload')) ?>">Upload media</a><?php endif; ?>
    </header>
    <?php if ($noticeText): ?><div class="admin-alert admin-alert--success" role="status"><?= $esc($noticeText) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
    <?php if ($items === []): ?>
        <div class="admin-panel admin-empty-state"><h3>No media yet</h3><p>Upload an image or document to begin.</p></div>
    <?php else: ?>
        <div class="admin-panel admin-table-wrapper"><table class="admin-table"><caption class="sr-only">Core Media inventory</caption><thead><tr><th>Media</th><th>Type</th><th>Uploaded</th><th>Usage</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($items as $item): $id = $item->id()->value(); $usageCount = count($evidence[$id] ?? []); ?>
            <tr>
                <td><strong><?= $esc($item->title()) ?></strong><br><small><?= $esc($item->originalFilename()) ?></small><br><small>ID <?= $esc($id) ?></small></td>
                <td><?= $esc($item->mimeType()) ?><?php if ($item->width() !== null): ?><br><small><?= $esc($item->width() . ' × ' . $item->height()) ?></small><?php endif; ?></td>
                <td><time datetime="<?= $esc($item->createdAt()) ?>"><?= $esc($item->createdAt()) ?></time></td>
                <td><?= $usageCount > 0 ? $esc($usageCount . ' reference' . ($usageCount === 1 ? '' : 's')) : 'Unused' ?></td>
                <td>
                    <?php if ($canEdit): ?><form method="post" action="<?= $esc($adminUrl('media/' . $id . '/title')) ?>" class="admin-inline-field"><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><label class="sr-only" for="media-title-<?= $esc($id) ?>">Title</label><input id="media-title-<?= $esc($id) ?>" name="title" value="<?= $esc($item->title()) ?>" maxlength="190"><button class="admin-button admin-button--secondary" type="submit">Save title</button></form><?php endif; ?>
                    <?php if ($canDelete): ?><form method="post" action="<?= $esc($adminUrl('media/' . $id . '/delete')) ?>"><input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>"><button class="admin-button admin-button--danger" type="submit">Delete</button></form><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?></tbody></table></div>
    <?php endif; ?>
</section>
