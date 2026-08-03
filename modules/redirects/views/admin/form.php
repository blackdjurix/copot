<?php
$adminUrl = is_callable($adminUrl ?? null) ? $adminUrl : static fn (string $path = ''): string => '/' . trim($path, '/');
$redirect = is_array($redirect ?? null) ? $redirect : [];
$errors = array_values(array_filter(array_map(static fn (mixed $error): string => is_scalar($error) ? (string) $error : '', $errors ?? []), static fn (string $error): bool => $error !== ''));
$formMode = ($formMode ?? 'create') === 'edit' ? 'edit' : 'create';
?>
<section class="admin-content-form-page" aria-labelledby="redirect-form-title">
    <header class="admin-content-form-header"><div><p class="admin-content-eyebrow">Redirect Manager</p><h2 id="redirect-form-title"><?= htmlspecialchars($heading ?? 'Redirect details', ENT_QUOTES, 'UTF-8') ?></h2><p>Use a canonical source path and a root-relative or absolute HTTP(S) target.</p></div><a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('redirects'), ENT_QUOTES, 'UTF-8') ?>">Back to redirects</a></header>
    <div class="admin-panel"><div class="admin-content-form-card">
        <?php if ($errors !== []): ?><div class="admin-alert admin-alert--danger" role="alert"><strong class="admin-alert__title">Please correct the following errors.</strong><ul class="admin-alert__list"><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($formMode === 'edit'): ?><input type="hidden" name="expected_updated_at" value="<?= htmlspecialchars((string) ($redirect['updated_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
            <div class="admin-field"><label class="admin-field__label" for="redirect-source">Source path</label><input id="redirect-source" name="source_path" type="text" value="<?= htmlspecialchars((string) ($redirect['source_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required maxlength="512" aria-describedby="redirect-source-help"><p class="admin-field__help" id="redirect-source-help">Use one exact public path. Query strings and reserved namespaces are not allowed.</p></div>
            <div class="admin-field"><label class="admin-field__label" for="redirect-target">Target</label><input id="redirect-target" name="target" type="text" value="<?= htmlspecialchars((string) ($redirect['target'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required maxlength="2048" aria-describedby="redirect-target-help"><p class="admin-field__help" id="redirect-target-help">Use a root-relative path or an absolute http:// or https:// URL.</p></div>
            <div class="admin-field"><label class="admin-field__label" for="redirect-status">Status</label><select id="redirect-status" name="status_code"><option value="302" <?= (string) ($redirect['status_code'] ?? 302) === '302' ? 'selected' : '' ?>>302 — Temporary</option><option value="301" <?= (string) ($redirect['status_code'] ?? 302) === '301' ? 'selected' : '' ?>>301 — Permanent</option></select></div>
            <div class="admin-actions"><button class="admin-button admin-button--primary" type="submit"><?= htmlspecialchars($submitLabel ?? 'Save Redirect', ENT_QUOTES, 'UTF-8') ?></button><a class="admin-button admin-button--secondary" href="<?= htmlspecialchars($adminUrl('redirects'), ENT_QUOTES, 'UTF-8') ?>">Cancel</a></div>
        </form>
    </div></div>
</section>
