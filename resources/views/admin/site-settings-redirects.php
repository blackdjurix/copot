<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$redirects = is_array($redirects ?? null) ? $redirects : [];
$redirectsPath = is_string($redirectsProjectionPath ?? null) ? $redirectsProjectionPath : '/admin/redirects';
$redirectCreatePath = rtrim($redirectsPath, '/') . '/create';
?>
<div class="site-settings-capability-projection site-settings-redirects">
    <header class="site-settings-capability-header">
        <h3>Redirects</h3>
        <p>Manage exact, one-hop redirects for unresolved public paths. Redirects access requires <code>redirects.manage</code> and does not grant generic Site Settings mutation.</p>
    </header>
    <?php if (!empty($redirectsError)): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $escape($redirectsError) ?></div><?php endif; ?>
    <div class="site-settings-redirects__toolbar">
        <p class="site-settings-redirects__count" role="status"><?= count($redirects) ?> redirect<?= count($redirects) === 1 ? '' : 's' ?>.</p>
        <div class="admin-actions"><a class="admin-button admin-button--primary" href="<?= $escape($redirectCreatePath) ?>">Add Redirect</a><a class="admin-button admin-button--secondary" href="<?= $escape($redirectsPath) ?>">Open Redirect Manager</a></div>
    </div>
    <?php if ($redirects === []): ?>
        <div class="admin-empty-state site-settings-redirects__empty"><h4 class="admin-empty-state__title">No redirects yet</h4><p class="admin-empty-state__description">Add a redirect to manage an unresolved public path.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap site-settings-redirects__table-wrap"><table class="admin-table site-settings-redirects__table"><thead><tr><th scope="col">Source</th><th scope="col">Destination</th><th scope="col">Type</th><th scope="col">Actions</th></tr></thead><tbody>
        <?php foreach ($redirects as $redirect): $statusCode = (int) $redirect->statusCode(); $statusLabel = $statusCode === 301 ? '301 Permanent' : ($statusCode === 302 ? '302 Temporary' : (string) $statusCode); ?>
            <tr><td data-label="Source"><code><?= $escape($redirect->sourcePath()) ?></code></td><td data-label="Destination"><code><?= $escape($redirect->target()) ?></code></td><td data-label="Type"><span class="admin-badge"><?= $escape($statusLabel) ?></span></td><td data-label="Actions"><div class="admin-actions"><a class="admin-button admin-button--link" href="<?= $escape(rtrim($redirectsPath, '/') . '/' . $redirect->id() . '/edit') ?>">Edit</a><form method="post" action="<?= $escape(rtrim($redirectsPath, '/') . '/' . $redirect->id() . '/delete') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><button class="admin-button admin-button--link" type="submit">Delete</button></form></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
</div>
