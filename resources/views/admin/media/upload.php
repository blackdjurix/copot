<?php $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<?php if ($error): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<div class="admin-panel"><div class="admin-panel__body">
    <form class="admin-form" method="post" enctype="multipart/form-data" action="<?= $esc($adminUrl('media/upload')) ?>">
        <input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>">
        <div class="admin-field"><label class="admin-field__label" for="media-file">File</label><input id="media-file" name="media" type="file" required aria-describedby="media-file-help"><p class="admin-field__help" id="media-file-help">Upload one supported image or PDF document.</p></div>
        <div class="admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Upload</button></div>
    </form>
</div></div>
