<?php $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title">Upload media</h2><p class="admin-panel__description">Supported images and PDF documents are inspected before storage. The original file is preserved in Media-owned storage.</p></div></header>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<form class="admin-panel" method="post" enctype="multipart/form-data" action="<?= $esc($adminUrl('media/upload')) ?>">
    <div class="admin-panel__body admin-form">
        <input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>">
        <p class="admin-form__hint admin-media-upload__guidance" id="media-file-help">Upload one supported image or PDF document. File contents are inspected, and browser-provided type information is not trusted.</p>
        <input id="media-file" name="media" type="file" aria-describedby="media-file-help" required>
        <div class="admin-media-upload__title-row"><label for="media-title">Title</label><div><input id="media-title" name="title" value="<?= $esc($title) ?>" maxlength="190" aria-describedby="media-title-help"><p class="admin-form__hint" id="media-title-help">Optional, defaults to the filename.</p></div></div>
        <div class="admin-form__actions"><a class="admin-button admin-button--secondary" href="<?= $esc($adminUrl('media')) ?>">Cancel</a><button class="admin-button admin-button--primary" type="submit">Upload media</button></div>
    </div>
</form>
