<?php
$adminUrl = is_callable($adminUrl ?? null) ? $adminUrl : static fn (string $path = ''): string => '/' . trim($path, '/');
$noticeMessages = [
    'created' => 'Redirect created successfully.',
    'updated' => 'Redirect updated successfully.',
    'deleted' => 'Redirect deleted successfully.',
];
$notice = is_scalar($notice ?? null) ? ($noticeMessages[(string) $notice] ?? null) : null;
$error = is_scalar($error ?? null) ? trim((string) $error) : '';
?>
<section class="admin-content-page" aria-labelledby="redirect-list-title">
    <header class="admin-content-header">
        <div class="admin-content-header__copy">
            <p class="admin-content-eyebrow">Redirect Manager</p>
            <h2 id="redirect-list-title">Redirects</h2>
            <p>Manage exact, one-hop redirects for unresolved public paths.</p>
        </div>
        <a class="admin-button admin-button--primary" href="<?= htmlspecialchars($adminUrl('redirects/create'), ENT_QUOTES, 'UTF-8') ?>">Create Redirect</a>
    </header>

    <?php if ($notice !== null): ?><div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="admin-alert admin-alert--danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <section class="admin-panel admin-content-table-panel" aria-labelledby="redirect-entries-title">
        <header class="admin-content-table-panel__header"><div><h3 id="redirect-entries-title">Managed redirects</h3><p><?= count($redirects ?? []) ?> redirect<?= count($redirects ?? []) === 1 ? '' : 's' ?>.</p></div></header>
        <?php if (empty($redirects)): ?>
            <div class="admin-empty-state"><h3 class="admin-empty-state__title">No redirects yet</h3><p class="admin-empty-state__description">Create a redirect to manage an unresolved public path.</p></div>
        <?php else: ?>
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th scope="col">Source</th><th scope="col">Target</th><th scope="col">Status</th><th scope="col">Updated</th><th scope="col">Actions</th></tr></thead><tbody>
            <?php foreach ($redirects as $redirect): ?>
                <tr>
                    <td><code><?= htmlspecialchars($redirect->sourcePath(), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><code><?= htmlspecialchars($redirect->target(), ENT_QUOTES, 'UTF-8') ?></code></td>
                    <td><?= htmlspecialchars((string) $redirect->statusCode(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($redirect->updatedAt(), ENT_QUOTES, 'UTF-8') ?></td>
                    <td><div class="admin-actions"><a class="admin-button admin-button--link" href="<?= htmlspecialchars($adminUrl('redirects/' . $redirect->id() . '/edit'), ENT_QUOTES, 'UTF-8') ?>">Edit</a><form method="post" action="<?= htmlspecialchars($adminUrl('redirects/' . $redirect->id() . '/delete'), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>"><button class="admin-button admin-button--link" type="submit">Delete</button></form></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
</section>
