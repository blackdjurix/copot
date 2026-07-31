<?php $esc = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); ?>
<header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title">Upload media</h2><p class="admin-panel__description">Supported images and PDF documents are inspected before storage.</p></div></header>
<?php if ($error): ?><div class="admin-error" role="alert"><?= $esc($error) ?></div><?php endif; ?>
<form class="admin-panel admin-form" method="post" enctype="multipart/form-data" action="<?= $esc($adminUrl('media/upload')) ?>">
    <input type="hidden" name="_token" value="<?= $esc($csrfToken) ?>">
    <label for="media-title">Title</label><input id="media-title" name="title" value="<?= $esc($title) ?>" maxlength="190" required>
    <label for="media-file">File</label><input id="media-file" name="media" type="file" required>
    <div class="admin-form__actions"><a class="admin-button admin-button--secondary" href="<?= $esc($adminUrl('media')) ?>">Cancel</a><button class="admin-button admin-button--primary" type="submit">Upload media</button></div>
</form>
