<?php $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<div class="admin-panel">
    <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= $esc($adminUrl('media/upload')) ?>">
        <input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>">
        <div class="admin-field"><label class="admin-field__label" for="media-file">File</label><input id="media-file" name="media" type="file" required aria-describedby="media-file-help"><p class="admin-field__help" id="media-file-help">Upload one supported image or PDF document.</p></div>
        <div class="admin-field"><label class="admin-field__label" for="media-title">Title</label><input id="media-title" name="title" value="<?= $esc($title) ?>" maxlength="190"><p class="admin-field__help">Optional; defaults to the filename.</p></div>
        <div class="admin-form__actions"><a class="admin-button admin-button--secondary" href="<?= $esc($adminUrl('media')) ?>">Cancel</a><button class="admin-button admin-button--primary" type="submit">Upload media</button></div>
    </form>
</div>
