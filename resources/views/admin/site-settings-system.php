<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$status = is_array($systemStatus ?? null) ? $systemStatus : [];
$participants = is_array($runtimeParticipants ?? null) ? $runtimeParticipants : [];
$operation = is_array($status['operation'] ?? null) ? $status['operation'] : null;
$release = is_file(($releasePath ?? '')) ? json_decode((string) file_get_contents($releasePath), true) : [];
$release = is_array($release) ? $release : [];
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
        <?php if ($operation !== null): ?><div class="admin-alert admin-alert--info" role="status"><strong>Current operation</strong>: <?= $escape($operation['classification'] ?? 'Lifecycle operation') ?> · <?= $escape($operation['phase'] ?? 'Unknown phase') ?>. <?= $escape($operation['recovery_state'] ?? '') ?></div><?php endif; ?>
        <?php if (($status['reason'] ?? '') !== ''): ?><p class="admin-field__help">Status: <?= $escape($status['reason']) ?></p><?php endif; ?>
    </section>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-update-title">
        <h3 id="site-settings-system-update-title">Update &amp; Upgrade</h3>
        <p>Upload a released Webcore package for authoritative preflight. Update is the operator-facing umbrella; the lifecycle planner determines Patch, Update, Upgrade, Database-only Update, or Repair eligibility.</p>
        <?php if (!empty($canManageSystem)): ?><form class="site-settings-system-upload" method="post" enctype="multipart/form-data" action="<?= $escape($systemPreflightPath ?? '') ?>" data-apply-action="<?= $escape($systemApplyPath ?? '') ?>" data-system-manager-upload>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <label class="admin-field"><span class="admin-field__label">Webcore package</span><input type="file" name="package" accept=".zip" required></label>
            <button class="admin-button admin-button--primary" type="submit">Preflight package</button>
        </form><?php else: ?><p class="admin-field__help">Webcore lifecycle management requires <code>system.webcore.manage</code>.</p><?php endif; ?>
        <div class="site-settings-system-result" data-system-manager-result hidden aria-live="polite"></div>
        <?php if (!empty($release['whats_new']) && is_array($release['whats_new'])): ?><details><summary>What&rsquo;s New</summary><ul><?php foreach ($release['whats_new'] as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?></ul></details><?php endif; ?>
    </section>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-repair-title">
        <h3 id="site-settings-system-repair-title">Repair, Retry &amp; Reconciliation</h3>
        <p>Repair reconciles eligible same-version work. Retry and Reconciliation appear only when existing lifecycle evidence permits them; recovery and compatibility blockers cannot be bypassed here.</p>
        <?php if (!empty($canManageSystem) && $operation !== null && !empty($operation['operation_id'])): ?><form method="post" action="<?= $escape($systemRetryPath ?? '') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="operation_id" value="<?= $escape($operation['operation_id']) ?>"><button class="admin-button admin-button--secondary" type="submit">Retry eligible operation</button></form><?php endif; ?>
        <?php if (!empty($canManageSystem) && ($status['reconciliation_available'] ?? false) === true): ?><form method="post" action="<?= $escape($systemReconcilePath ?? '') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><label class="admin-field"><span class="admin-field__label">Approved package path</span><input name="package_path" type="text" required></label><input type="hidden" name="confirmed" value="1"><button class="admin-button admin-button--secondary" type="submit">Reconcile</button></form><?php endif; ?>
    </section>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-runtime-title">
        <h3 id="site-settings-system-runtime-title">Runtime Participation &amp; Runtime Handoff</h3>
        <p>Runtime Participation is read-only in this slice. Runtime Handoff is a separate capability and has no executable controls here.</p>
        <?php if ($participants === []): ?><p class="admin-field__help">No runtime participants are currently reported.</p><?php else: ?><div class="site-settings-system-runtime-list"><?php foreach ($participants as $participant): ?><article><h4><code><?= $escape($participant['runtime_id'] ?? 'Unknown runtime') ?></code></h4><dl class="site-settings-system-details"><div><dt>State</dt><dd><?= $escape($participant['state'] ?? 'Unavailable') ?></dd></div><div><dt>Last seen</dt><dd><?= $escape($participant['last_seen_at'] ?? 'Unavailable') ?></dd></div></dl></article><?php endforeach; ?></div><?php endif; ?>
    </section>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-compatibility-title">
        <h3 id="site-settings-system-compatibility-title">Compatibility</h3>
        <p>Compatibility is authoritative evidence from the lifecycle and runtime boundaries. Current state is <?= $escape($status['installed_state'] ?? 'unavailable') ?>; target package eligibility is determined during preflight.</p>
        <p class="admin-field__help">Unsupported, blocked, forward-only, schema, database, and runtime outcomes are reported by the lifecycle planner without exposing internal diagnostics.</p>
    </section>

    <section class="admin-settings-system-group" aria-labelledby="site-settings-system-permissions-title">
        <h3 id="site-settings-system-permissions-title">Permissions</h3>
        <dl class="site-settings-system-details"><div><dt>Admin surface</dt><dd><code>admin.access</code></dd></div><div><dt>Webcore lifecycle actions</dt><dd><code>system.webcore.manage</code></dd></div><div><dt>Site Settings writes</dt><dd><code>settings.update</code> remains separate</dd></div><div><dt>Module lifecycle</dt><dd><code>modules.manage</code> remains separate</dd></div></dl>
    </section>
</div>
<script src="<?= $escape(is_callable($url ?? null) ? $url('/admin-assets/js/system-manager.js?v=wu4-b2-system-1') : '/admin-assets/js/system-manager.js?v=wu4-b2-system-1') ?>" defer></script>
