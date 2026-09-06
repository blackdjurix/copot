<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$status = is_array($systemStatus ?? null) ? $systemStatus : [];
$operation = is_array($status['operation'] ?? null) ? $status['operation'] : null;
$release = is_file(($releasePath ?? '')) ? json_decode((string) file_get_contents($releasePath), true) : [];
$release = is_array($release) ? $release : [];
$whatsNew = is_array($release['whats_new'] ?? null) ? array_values(array_filter($release['whats_new'], 'is_scalar')) : [];
$hasRecoveryEvidence = $operation !== null;
?>
<div class="site-settings-system" data-site-settings-system>
    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-state-title">
        <h3 id="site-settings-system-state-title">Current System State</h3>
        <dl class="site-settings-system-details">
            <div><dt>Webcore version</dt><dd><?= $escape($status['installed_version'] ?? 'Not available') ?></dd></div>
            <div><dt>Installed lifecycle state</dt><dd><?= $escape($status['installed_state'] ?? 'Unavailable') ?></dd></div>
            <div><dt>Schema and migration state</dt><dd><?= $escape($status['schema_state_identity'] ?? 'Not available') ?> · <?= $escape($status['migration_state_identity'] ?? 'Not available') ?></dd></div>
            <div><dt>Maintenance</dt><dd><?= $escape($status['maintenance'] ?? 'Unavailable') ?></dd></div>
            <div><dt>Installation ID</dt><dd><code><?= $escape($installationId ?? 'Not available') ?></code></dd></div>
        </dl>
        <?php if ($operation !== null): ?><div class="admin-alert admin-alert--info" role="status"><strong>Current lifecycle operation</strong>: <?= $escape($operation['phase'] ?? 'Unknown phase') ?>. <?= $escape($operation['recovery_state'] ?? '') ?></div><?php endif; ?>
        <?php if (($status['reason'] ?? '') !== ''): ?><p class="admin-field__help">Status: <?= $escape($status['reason']) ?></p><?php endif; ?>
    </section>

    <?php if ($whatsNew !== []): ?><section class="admin-settings-system-group" aria-labelledby="site-settings-system-whats-new-title">
        <h3 id="site-settings-system-whats-new-title">What&rsquo;s New</h3>
        <ul><?php foreach ($whatsNew as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?></ul>
    </section><?php endif; ?>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-update-title">
        <h3 id="site-settings-system-update-title">Update</h3>
        <p>Upload a released Webcore package to begin Update. The lifecycle planner determines the applicable technical transition from the package and current system evidence.</p>
        <?php if (!empty($canManageSystem)): ?><form class="site-settings-system-upload" method="post" enctype="multipart/form-data" action="<?= $escape($systemPreflightPath ?? '') ?>" data-apply-action="<?= $escape($systemApplyPath ?? '') ?>" data-system-manager-upload>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <label class="admin-field"><span class="admin-field__label">Webcore package</span><input type="file" name="package" accept=".zip" required></label>
            <button class="admin-button admin-button--primary" type="submit">Preflight Update</button>
        </form><?php else: ?><p class="admin-field__help">Update requires <code>system.webcore.manage</code>.</p><?php endif; ?>
        <div class="site-settings-system-result" data-system-manager-result hidden aria-live="polite"></div>
    </section>

    <?php if ($hasRecoveryEvidence): ?><section class="admin-settings-system-group" aria-labelledby="site-settings-system-recovery-title">
        <h3 id="site-settings-system-recovery-title">Lifecycle recovery</h3>
        <p>The current lifecycle evidence requires contextual recovery guidance before the next valid action can be completed.</p>
        <?php if (!empty($canManageSystem) && !empty($retryEligible) && !empty($operation['operation_id'])): ?><form method="post" action="<?= $escape($systemRetryPath ?? '') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="operation_id" value="<?= $escape($operation['operation_id']) ?>"><button class="admin-button admin-button--secondary" type="submit">Retry Update</button></form><?php endif; ?>
        <?php if (!empty($canManageSystem) && ($status['reconciliation_available'] ?? false) === true): ?><form method="post" action="<?= $escape($systemReconcilePath ?? '') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><label class="admin-field"><span class="admin-field__label">Approved package</span><input name="package_path" type="text" required></label><input type="hidden" name="confirmed" value="1"><button class="admin-button admin-button--secondary" type="submit">Reconcile Update</button></form><?php endif; ?>
    </section><?php endif; ?>
</div>
<script src="<?= $escape(is_callable($url ?? null) ? $url('/admin-assets/js/system-manager.js?v=wu4-b2-system-1') : '/admin-assets/js/system-manager.js?v=wu4-b2-system-1') ?>" defer></script>
