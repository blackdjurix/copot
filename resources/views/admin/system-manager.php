<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$activeSection = in_array($section ?? 'system', ['system', 'branding', 'modules', 'health'], true) ? $section : 'system';
$tabs = [
    'system' => 'System',
    'branding' => 'Branding',
    'modules' => 'Modules',
    'health' => 'System Health',
];
$status = is_array($status ?? null) ? $status : [];
$palette = is_array($branding['palette'] ?? null) ? $branding['palette'] : [];
$localization = is_array($localization ?? null) ? $localization : [];
$health = is_array($health ?? null) ? $health : [];
$modules = is_array($modules ?? null) ? $modules : [];
$release = is_array($release ?? null) ? $release : [];
$message = is_string($message ?? null) ? $message : null;
$error = is_string($error ?? null) ? $error : null;
$basePath = (string) ($systemManagerPath ?? '');
$query = static fn (string $name): string => $basePath . '?section=' . rawurlencode($name);
?>
<div class="system-manager-workspace" data-system-manager>
    <?php if ($message !== null): ?><div class="admin-alert admin-alert--success" role="status"><?= $escape($message) ?></div><?php endif; ?>
    <?php if ($error !== null): ?><div class="admin-alert admin-alert--error" role="alert"><?= $escape($error) ?></div><?php endif; ?>

    <?php if ($activeSection === 'system'): ?>
        <div class="system-manager-system-overview">
        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-release-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-release-title">What’s New</h2><p class="admin-panel__description">Authoritative release metadata included with the Webcore package.</p></div></header>
            <div class="admin-panel__body"><ul class="system-manager-release-list"><?php foreach ((is_array($release['whats_new'] ?? null) ? $release['whats_new'] : []) as $item): ?><li><?= $escape($item) ?></li><?php endforeach; ?><?php if (empty($release['whats_new'])): ?><li>No release notes were supplied.</li><?php endif; ?></ul></div>
        </section>

        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-status-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-status-title">Webcore lifecycle</h2><p class="admin-panel__description">Review authoritative state and apply a released Webcore package only when the lifecycle engine marks an action eligible.</p></div></header>
            <div class="admin-panel__body">
                <dl class="system-manager-status-grid">
                    <div><dt>Installed state</dt><dd><?= $escape($status['installed_state'] ?? 'Unavailable') ?></dd></div>
                    <div><dt>Webcore version</dt><dd><?= $escape($status['installed_version'] ?? 'Not available') ?></dd></div>
                    <div><dt>Schema state</dt><dd><?= $escape($status['schema_state_identity'] ?? 'Not available') ?></dd></div>
                    <div><dt>Migration state</dt><dd><?= $escape($status['migration_state_identity'] ?? 'Not available') ?></dd></div>
                    <div><dt>Maintenance</dt><dd><?= $escape($status['maintenance'] ?? 'Unavailable') ?></dd></div>
                    <div><dt>Next valid action</dt><dd><?= $escape($status['next_action'] ?? 'None reported') ?></dd></div>
                </dl>
                <?php if (is_array($status['operation'] ?? null)): ?>
                    <div class="admin-status-card" data-status="<?= $escape($status['operation']['state'] ?? 'unknown') ?>"><strong>Current operation</strong><span><?= $escape($status['operation']['classification'] ?? 'Unknown') ?> · <?= $escape($status['operation']['phase'] ?? 'Unknown phase') ?></span></div>
                <?php endif; ?>
            </div>
        </section>
        </div>

        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-package-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-package-title">Update Webcore</h2><p class="admin-panel__description">Choose a released Webcore ZIP. Preflight derives the eligible lifecycle action before any mutation.</p></div></header>
            <div class="admin-panel__body">
                <form class="system-manager-upload" method="post" enctype="multipart/form-data" action="<?= $escape($preflightPath ?? '') ?>" data-apply-action="<?= $escape($applyPath ?? '') ?>" data-system-manager-upload>
                    <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
                    <label class="admin-field"><span class="admin-field__label">Released Webcore ZIP</span><input type="file" name="package" accept="application/zip,.zip" required data-system-manager-package><span class="admin-field__help">Online discovery and download are not part of this baseline.</span></label>
                    <button class="admin-button admin-button--primary" type="submit">Preflight package</button>
                </form>
                <div class="system-manager-result" data-system-manager-result hidden aria-live="polite"></div>
                <?php if (($status['reconciliation_available'] ?? false) === true): ?><form method="post" action="<?= $escape($reconcilePath ?? '') ?>" class="system-manager-inline-form"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="confirmed" value="1"><input name="package_path" type="text" placeholder="Approved package path" required><button class="admin-button admin-button--secondary" type="submit">Reconcile</button></form><?php endif; ?>
                <?php if (is_array($status['operation'] ?? null) && !empty($status['operation']['operation_id'])): ?><form method="post" action="<?= $escape($retryPath ?? '') ?>" class="system-manager-inline-form"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="operation_id" value="<?= $escape($status['operation']['operation_id']) ?>"><button class="admin-button admin-button--secondary" type="submit">Retry eligible operation</button></form><?php endif; ?>
            </div>
        </section>

    <?php elseif ($activeSection === 'branding'): ?>
        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-localization-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-localization-title">Localization</h2><p class="admin-panel__description">These values remain registered Core settings. Language is not implemented in WU2.</p></div></header>
            <div class="admin-panel__body"><form method="post" action="<?= $escape($localizationPath ?? '') ?>" class="system-manager-form"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><label class="admin-field"><span class="admin-field__label">Locale</span><select name="locale"><option value="en_US"<?= ($localization['locale'] ?? '') === 'en_US' ? ' selected' : '' ?>>en_US</option><option value="id_ID"<?= ($localization['locale'] ?? '') === 'id_ID' ? ' selected' : '' ?>>id_ID</option></select></label><label class="admin-field"><span class="admin-field__label">Timezone</span><select name="timezone"><?php foreach (timezone_identifiers_list() as $timezone): ?><option value="<?= $escape($timezone) ?>"<?= ($localization['timezone'] ?? '') === $timezone ? ' selected' : '' ?>><?= $escape($timezone) ?></option><?php endforeach; ?></select></label><label class="admin-field"><span class="admin-field__label">Date Format</span><select name="date_format"><?php foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd M Y'] as $format): ?><option value="<?= $escape($format) ?>"<?= ($localization['date_format'] ?? '') === $format ? ' selected' : '' ?>><?= $escape($format) ?></option><?php endforeach; ?></select></label><label class="admin-field"><span class="admin-field__label">Time Format</span><select name="time_format"><?php foreach (['H:i', 'h:i A'] as $format): ?><option value="<?= $escape($format) ?>"<?= ($localization['time_format'] ?? '') === $format ? ' selected' : '' ?>><?= $escape($format) ?></option><?php endforeach; ?></select></label><button class="admin-button admin-button--primary" type="submit">Save Localization</button></form></div>
        </section>
        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-branding-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-branding-title">Webcore Basic Branding</h2><p class="admin-panel__description">Global Webcore values for public consumers. Theme-specific branding and advanced color controls remain separate.</p></div></header>
            <div class="admin-panel__body"><form method="post" action="<?= $escape($brandingPath ?? '') ?>" class="system-manager-form"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><?php foreach (['main' => 'Main', 'accent' => 'Accent', 'neutral-dark' => 'Neutral Dark', 'neutral-light' => 'Neutral Light'] as $key => $label): ?><label class="admin-field"><span class="admin-field__label"><?= $escape($label) ?></span><input type="color" name="<?= $escape($key) ?>" value="<?= $escape($palette[$key] ?? '') ?>"><code><?= $escape($palette[$key] ?? '') ?></code></label><?php endforeach; ?><label class="admin-field"><span class="admin-field__label">Admin identity display</span><select name="identity_mode"><option value="text"<?= ($branding['identity_mode'] ?? '') === 'text' ? ' selected' : '' ?>>Text — Site Name</option><option value="logo"<?= ($branding['identity_mode'] ?? '') === 'logo' ? ' selected' : '' ?>>Logo</option></select></label><label class="admin-field"><span class="admin-field__label">Admin text color</span><select name="identity_color"><?php foreach (['main' => 'Main', 'accent' => 'Accent', 'neutral-dark' => 'Neutral Dark', 'neutral-light' => 'Neutral Light'] as $key => $label): ?><option value="<?= $escape($key) ?>"<?= ($branding['identity_color'] ?? '') === $key ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></label><p class="admin-field__help">Required foreground relationships use contrast-aware Neutral Dark / Neutral Light resolution.</p><button class="admin-button admin-button--primary" type="submit">Save Branding</button></form></div>
        </section>
    <?php elseif ($activeSection === 'modules'): ?>
        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-modules-title"><header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-modules-title">Module recovery fallback</h2><p class="admin-panel__description">This minimum operator is shown because Module Manager is not operationally available. It uses Core Module lifecycle authority and is withdrawn when Module Manager becomes available.</p></div></header><div class="admin-panel__body"><div class="system-manager-module-list"><?php foreach ($modules as $module): ?><article class="admin-status-card"><div><h3><?= $escape($module['title'] ?? $module['name'] ?? 'Module') ?></h3><p><?= $escape($module['name'] ?? '') ?> · <?= $escape($module['version'] ?? '') ?> · <?= $escape($module['status'] ?? '') ?></p><?php foreach (($module['blocking_reasons'] ?? []) as $reason): ?><p class="admin-field__help"><?= $escape($reason) ?></p><?php endforeach; ?></div><div class="admin-actions"><?php foreach (($module['available_actions'] ?? []) as $action => $enabled): ?><?php if ($enabled): ?><form method="post" action="<?= $escape($moduleActionPath ?? '') ?>"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="module" value="<?= $escape($module['name'] ?? '') ?>"><input type="hidden" name="action" value="<?= $escape($action) ?>"><button class="admin-button admin-button--secondary" type="submit"><?= $escape(ucfirst($action)) ?></button></form><?php endif; ?><?php endforeach; ?></div></article><?php endforeach; ?><?php if ($modules === []): ?><p>No discovered or installed Modules were available for fallback presentation.</p><?php endif; ?></div></div></section>
    <?php else: ?>
        <section class="admin-panel system-manager-panel" aria-labelledby="system-manager-health-title"><header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="system-manager-health-title">System Health</h2><p class="admin-panel__description">Authorized health evidence from existing producers. This page does not diagnose or remediate.</p></div></header><div class="admin-panel__body"><div class="system-health-overview" data-health-status="<?= $escape($health['status'] ?? 'unavailable') ?>"><strong><?= $escape($health['status_label'] ?? 'Health data unavailable') ?></strong><span><?= $escape($health['message'] ?? '') ?></span></div><h3>Findings</h3><?php if (!empty($health['findings'])): ?><ul class="system-manager-findings"><?php foreach ($health['findings'] as $finding): ?><li><strong><?= $escape($finding['severity'] ?? '') ?></strong> <?= $escape($finding['summary'] ?? '') ?><?php if (!empty($finding['target'])): ?> <span>(<?= $escape($finding['target']) ?>)</span><?php endif; ?></li><?php endforeach; ?></ul><?php else: ?><p>No material health findings were reported.</p><?php endif; ?><h3>Producers</h3><ul class="system-manager-producers"><?php foreach (($health['producers'] ?? []) as $producer): ?><li><?= $escape($producer['source'] ?? '') ?> — <?= $escape($producer['availability'] ?? '') ?></li><?php endforeach; ?></ul></div></section>
    <?php endif; ?>
</div>
