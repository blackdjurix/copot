<?php

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actionLabels = [
    'install' => 'Install',
    'enable' => 'Enable',
    'disable' => 'Disable',
    'uninstall' => 'Uninstall',
];
$diagnosticLabels = [
    'malformed_discovery' => 'Malformed discovery data',
    'invalid_metadata' => 'Invalid module metadata',
    'discovery_missing' => 'Module is not discoverable',
    'invalid_stored_status' => 'Stored status is invalid',
    'metadata_drift' => 'Stored metadata differs from discovered metadata',
    'stored_path_unavailable' => 'Stored module path is unavailable',
    'route_file_missing' => 'Declared route file is missing',
    'listener_file_missing' => 'Declared listener file is missing',
    'self_dependency' => 'Self-dependency declared',
    'duplicate_dependency' => 'Duplicate dependency declared',
    'unsupported_version_constraint' => 'Version constraint is unsupported',
    'dependency_missing' => 'Required dependency is missing',
    'dependency_disabled' => 'Required dependency is disabled',
    'dependency_cycle' => 'Dependency cycle detected',
    'enabled_dependent' => 'Enabled dependent blocks this action',
    'dependent_safety_unknown' => 'Dependent safety is unresolved',
];
$lifecyclePresentation = [
    'not_installed' => ['Not installed', 'admin-badge--info'],
    'installed_disabled' => ['Disabled', 'admin-badge--warning'],
    'installed_enabled' => ['Enabled', 'admin-badge--success'],
];
$discoveryPresentation = [
    'valid' => ['Valid', 'admin-badge--success'],
    'missing' => ['Missing', 'admin-badge--warning'],
    'malformed' => ['Malformed', 'admin-badge--warning'],
    'invalid_metadata' => ['Invalid metadata', 'admin-badge--warning'],
];
$visibleActions = static function (string $lifecycle, string $action): bool {
    return match ($lifecycle) {
        'not_installed' => $action === 'install',
        'installed_disabled' => in_array($action, ['enable', 'uninstall'], true),
        'installed_enabled' => $action === 'disable',
        default => false,
    };
};
$itemName = (string) ($item['name'] ?? '');
$lifecycleState = (string) ($item['lifecycle_state'] ?? '');
$discoveryState = (string) ($item['discovery_state'] ?? '');
$lifecycle = $lifecyclePresentation[$lifecycleState] ?? [$lifecycleState, ''];
$discovery = $discoveryPresentation[$discoveryState] ?? [$discoveryState, ''];
$dependencies = is_array($item['dependencies'] ?? null) ? $item['dependencies'] : [];
$contributions = is_array($item['contribution_files'] ?? null) ? $item['contribution_files'] : [];
$storedPermissions = is_array($item['permission_metadata_summary'] ?? null)
    ? $item['permission_metadata_summary'] : [];
$discoveredPermissions = is_array($item['discovered_permission_metadata_summary'] ?? null)
    ? $item['discovered_permission_metadata_summary'] : [];
$diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];
$whatsNew = is_array($item['release_whats_new'] ?? null)
    ? array_values(array_filter($item['release_whats_new'], 'is_string')) : [];
$siteSettingsModulesProjection = !empty($siteSettingsModulesProjection);
$dependencyNames = array_values(array_filter(array_map(
    static fn (mixed $dependency): string => is_array($dependency) ? (string) ($dependency['name'] ?? '') : '',
    $dependencies
)));
$hasIssues = $diagnostics !== [];
$diagnosticSeverityClass = static function (string $severity): string {
    return match (strtolower($severity)) {
        'critical', 'error' => 'admin-badge--danger',
        'warning' => 'admin-badge--warning',
        default => 'admin-badge--info',
    };
};
?>
<?php if (!empty($notice)): ?>
    <div class="admin-alert admin-alert--success" role="status"><?= $escape($notice) ?></div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="admin-alert admin-alert--danger" role="alert">
        <strong class="admin-alert__title">Module action could not be completed.</strong>
        <p><?= $escape($error) ?></p>
    </div>
<?php endif; ?>

<?php if ($siteSettingsModulesProjection): ?>
    <header class="admin-module-detail-header" aria-labelledby="module-identity-title">
        <div class="admin-module-detail-header__identity">
            <h2 id="module-identity-title"><?= $escape($item['title'] ?? $itemName) ?></h2>
        </div>
        <div class="admin-actions"><a class="admin-button admin-button--secondary" href="<?= $escape($inventoryPath) ?>">Back to Modules</a></div>
    </header>
    <section class="admin-panel admin-module-detail-panel admin-module-detail-overview" aria-labelledby="module-overview-title">
        <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-overview-title">Module Overview</h2><p class="admin-panel__description">Current Module properties and lifecycle state.</p></div></header>
        <div class="admin-panel__body"><dl class="admin-module-detail-meta">
            <dt>Lifecycle / Status</dt><dd><span class="admin-badge<?= $lifecycle[1] !== '' ? ' ' . $lifecycle[1] : '' ?>"><?= $escape($lifecycle[0]) ?></span></dd>
            <dt>Effective version</dt><dd><?= $escape($item['version'] ?? '—') ?></dd>
            <dt>Discovery state</dt><dd><span class="admin-badge<?= $discovery[1] !== '' ? ' ' . $discovery[1] : '' ?>"><?= $escape($discovery[0]) ?></span></dd>
            <dt>Technical name</dt><dd><code><?= $escape($itemName) ?></code></dd>
            <dt>Dependencies</dt><dd><?= $dependencyNames === [] ? 'None declared' : $escape(implode(', ', $dependencyNames)) ?></dd>
        </dl></div>
    </section>
    <section class="admin-panel admin-module-detail-panel" aria-labelledby="module-whats-new-title">
        <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-whats-new-title">What's New</h2></div></header>
        <div class="admin-panel__body">
            <?php if ($whatsNew === []): ?>
                <p>What's New information is unavailable.</p>
            <?php else: ?>
                <ul class="admin-module-detail-list"><?php foreach ($whatsNew as $entry): ?><li><?= $escape($entry) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<div class="admin-module-detail-layout">
    <div class="admin-module-detail-column admin-module-detail-column--primary">
        <?php if ($siteSettingsModulesProjection): ?>
            <section class="admin-panel admin-module-detail-panel admin-module-detail-issues" aria-labelledby="module-issues-title">
                <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-issues-title">Issues &amp; Guidance</h2><p class="admin-panel__description">Current diagnosable conditions and lifecycle impact.</p></div></header>
                <div class="admin-panel__body">
                    <?php if (!$hasIssues): ?>
                        <p>No issues detected.</p>
                    <?php else: ?>
                        <?php if ($diagnostics !== []): ?><ul class="admin-module-detail-list"><?php foreach ($diagnostics as $diagnostic): ?><?php $code = (string) ($diagnostic['code'] ?? 'unknown'); $severity = (string) ($diagnostic['severity'] ?? 'notice'); $blockedActions = array_values(array_filter(array_map('strval', is_array($diagnostic['blocked_actions'] ?? null) ? $diagnostic['blocked_actions'] : []), function (string $action) use ($actionLabels, $item, $lifecycleState, $visibleActions): bool { $eligibility = is_array($item['available_actions'][$action] ?? null) ? $item['available_actions'][$action] : []; return isset($actionLabels[$action]) && $visibleActions($lifecycleState, $action) && (($eligibility['visible'] ?? false) === true); })); ?><li><strong><?= $escape($diagnosticLabels[$code] ?? 'Module issue') ?></strong> <span class="admin-badge <?= $escape($diagnosticSeverityClass($severity)) ?>"><?= $escape(ucfirst($severity)) ?></span><?php if ($blockedActions !== []): ?><span class="admin-text-muted"> Blocks: <?= $escape(implode(', ', array_map(static fn (string $action): string => $actionLabels[$action], $blockedActions))) ?>.</span><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <details class="admin-panel admin-module-detail-panel admin-module-detail-disclosure">
                <summary>Technical evidence</summary>
                <div class="admin-panel__body">
                    <dl class="admin-module-detail-meta">
                        <dt>Stored path available</dt><dd><?= !empty($item['stored_path_available']) ? 'Yes' : 'No' ?></dd>
                        <dt>Discovered path available</dt><dd><?= !empty($item['discovered_path_available']) ? 'Yes' : 'No' ?></dd>
                        <dt>Stored version</dt><dd><?= $escape($item['stored_version'] ?? '—') ?></dd>
                        <dt>Discovered version</dt><dd><?= $escape($item['discovered_version'] ?? '—') ?></dd>
                        <dt>Stored title</dt><dd><?= $escape($item['stored_title'] ?? '—') ?></dd>
                        <dt>Discovered title</dt><dd><?= $escape($item['discovered_title'] ?? '—') ?></dd>
                    </dl>
                    <div class="admin-module-detail-evidence">
                        <section aria-labelledby="module-stored-permissions-title"><h3 id="module-stored-permissions-title">Stored permissions</h3><?php if ($storedPermissions === []): ?><p>None</p><?php else: ?><ul class="admin-module-detail-list"><?php foreach ($storedPermissions as $permission): ?><li><code><?= $escape($permission['slug'] ?? '') ?></code> — <?= $escape($permission['name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></section>
                        <section aria-labelledby="module-discovered-permissions-title"><h3 id="module-discovered-permissions-title">Discovered permissions</h3><?php if ($discoveredPermissions === []): ?><p>None</p><?php else: ?><ul class="admin-module-detail-list"><?php foreach ($discoveredPermissions as $permission): ?><li><code><?= $escape($permission['slug'] ?? '') ?></code> — <?= $escape($permission['name'] ?? '') ?></li><?php endforeach; ?></ul><?php endif; ?></section>
                        <section aria-labelledby="module-contributions-title"><h3 id="module-contributions-title">Contribution files</h3><?php if ($contributions === []): ?><p>None declared</p><?php else: ?><ul class="admin-module-detail-list"><?php foreach ($contributions as $type => $contribution): ?><li><?= $escape((string) $type) ?>: <?= !empty($contribution['declared']) ? 'declared' : 'not declared' ?>; <?= !empty($contribution['available']) ? 'available' : 'missing' ?></li><?php endforeach; ?></ul><?php endif; ?></section>
                    </div>
                </div>
            </details>
        <?php else: ?>
        <section class="admin-panel admin-module-detail-panel" aria-labelledby="module-evidence-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-evidence-title">Operational evidence</h2><p class="admin-panel__description">Stored database state and discovered filesystem evidence are shown separately.</p></div></header>
            <div class="admin-panel__body">
                <dl class="admin-module-detail-meta">
                    <dt>Stored path available</dt><dd><?= !empty($item['stored_path_available']) ? 'Yes' : 'No' ?></dd>
                    <dt>Discovered path available</dt><dd><?= !empty($item['discovered_path_available']) ? 'Yes' : 'No' ?></dd>
                </dl>

                <div class="admin-module-detail-evidence">
                    <section aria-labelledby="module-stored-permissions-title">
                        <h3 id="module-stored-permissions-title">Stored permissions</h3>
                        <?php if ($storedPermissions === []): ?>
                            <p>None</p>
                        <?php else: ?>
                            <ul class="admin-module-detail-list"><?php foreach ($storedPermissions as $permission): ?><li><code><?= $escape($permission['slug'] ?? '') ?></code> — <?= $escape($permission['name'] ?? '') ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </section>
                    <section aria-labelledby="module-discovered-permissions-title">
                        <h3 id="module-discovered-permissions-title">Discovered permissions</h3>
                        <?php if ($discoveredPermissions === []): ?>
                            <p>None</p>
                        <?php else: ?>
                            <ul class="admin-module-detail-list"><?php foreach ($discoveredPermissions as $permission): ?><li><code><?= $escape($permission['slug'] ?? '') ?></code> — <?= $escape($permission['name'] ?? '') ?></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </section>
                    <section aria-labelledby="module-dependencies-title">
                        <h3 id="module-dependencies-title">Dependencies</h3>
                        <?php if ($dependencies === []): ?><p>None</p><?php else: ?><ul class="admin-module-detail-list"><?php foreach ($dependencies as $dependency): ?><li><code><?= $escape($dependency['name'] ?? '') ?></code></li><?php endforeach; ?></ul><?php endif; ?>
                    </section>
                    <section aria-labelledby="module-contributions-title">
                        <h3 id="module-contributions-title">Contribution files</h3>
                        <?php if ($contributions === []): ?><p>None declared</p><?php else: ?><ul class="admin-module-detail-list"><?php foreach ($contributions as $type => $contribution): ?><li><?= $escape((string) $type) ?>: <?= !empty($contribution['declared']) ? 'declared' : 'not declared' ?>; <?= !empty($contribution['available']) ? 'available' : 'missing' ?></li><?php endforeach; ?></ul><?php endif; ?>
                    </section>
                </div>
            </div>
        </section>

        <section class="admin-panel admin-module-detail-panel" aria-labelledby="module-diagnostics-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-diagnostics-title">Diagnostics</h2><p class="admin-panel__description">Discovery and lifecycle evidence; no automatic synchronization is implied.</p></div></header>
            <div class="admin-panel__body">
                <?php if ($diagnostics === []): ?>
                    <p>None</p>
                <?php else: ?>
                    <ul class="admin-module-detail-list"><?php foreach ($diagnostics as $diagnostic): $code = (string) ($diagnostic['code'] ?? 'unknown'); ?><li><?= $escape($diagnosticLabels[$code] ?? 'Module diagnostic') ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <h3>Denial reasons</h3>
                <dl class="admin-module-detail-meta admin-module-detail-denials">
                    <?php foreach ($actionLabels as $action => $label): ?>
                        <?php $reasons = is_array($item['denial_reasons'][$action] ?? null) ? $item['denial_reasons'][$action] : []; ?>
                        <?php if ($reasons !== []): ?><dt><?= $escape($label) ?></dt><dd><?= $escape(implode('; ', array_map('strval', $reasons))) ?></dd><?php endif; ?>
                    <?php endforeach; ?>
                </dl>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($item['available_package_version']) || !empty($item['available_package_dependencies']) || !empty($item['available_package_conflicts']) || !empty($item['lifecycle_evidence']) || !empty($item['operation'])): ?>
            <section class="admin-panel admin-module-detail-panel" aria-labelledby="module-package-title">
                <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-package-title">Package and lifecycle evidence</h2><p class="admin-panel__description">Package eligibility and operation state are derived from the existing Module lifecycle authority.</p></div></header>
                <div class="admin-panel__body">
                    <?php if (!empty($item['available_package_version'])): ?><dl class="admin-module-detail-meta"><dt>Available package</dt><dd><?= $escape($item['available_package_version']) ?></dd><dt>Package release</dt><dd><?= $escape($item['available_package_release'] ?? '—') ?></dd></dl><?php endif; ?>
                    <?php if (!empty($item['available_package_dependencies'])): ?><h3>Package dependencies</h3><ul class="admin-module-detail-list"><?php foreach ($item['available_package_dependencies'] as $dependency): ?><li><code><?= $escape($dependency) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php if (!empty($item['available_package_conflicts'])): ?><h3>Package conflicts</h3><ul class="admin-module-detail-list"><?php foreach ($item['available_package_conflicts'] as $conflict): ?><li><code><?= $escape($conflict) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
                    <?php if (is_array($item['lifecycle_evidence'] ?? null)): ?><h3>Committed lifecycle state</h3><p><?= $escape($item['lifecycle_evidence']['status'] ?? 'Available') ?></p><?php endif; ?>
                    <?php if (is_array($item['operation'] ?? null)): ?><h3>Current operation</h3><p><?= $escape($item['operation']['state'] ?? 'Recovery state requires review.') ?></p><?php endif; ?>
                    <?php if (!empty($item['available_package_candidate']) && empty($item['lifecycle_blocker']) && !empty($lifecyclePath)): ?>
                        <form method="post" action="<?= $escape($lifecyclePath) ?>" class="admin-form admin-module-detail-action"><input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>"><input type="hidden" name="candidate" value="<?= $escape($item['available_package_candidate']) ?>"><button class="admin-button admin-button--primary" type="submit"><?= $escape(ucfirst((string) ($item['lifecycle_action'] ?? 'Apply package'))) ?></button></form>
                    <?php elseif (!empty($item['lifecycle_blocker'])): ?><p class="admin-text-muted"><?= $escape($item['lifecycle_blocker']) ?></p><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="admin-module-detail-column admin-module-detail-column--secondary">
        <?php if (!$siteSettingsModulesProjection): ?>
        <section class="admin-panel admin-module-detail-panel"<?= $siteSettingsModulesProjection ? ' aria-label="Module state"' : ' aria-labelledby="module-identity-title"' ?>>
            <header class="admin-panel__header">
                <div class="admin-panel__heading">
                    <h2 class="admin-panel__title" id="module-identity-title"><?= $escape($item['title'] ?? $itemName) ?></h2>
                    <p class="admin-panel__description"><code><?= $escape($itemName) ?></code></p>
                </div>
                <div class="admin-actions"><a class="admin-button admin-button--link" href="<?= $escape($inventoryPath) ?>">Back to Modules</a></div>
            </header>
            <div class="admin-panel__body">
                <dl class="admin-module-detail-meta">
                    <dt>Lifecycle</dt>
                    <dd><span class="admin-badge<?= $lifecycle[1] !== '' ? ' ' . $lifecycle[1] : '' ?>"><?= $escape($lifecycle[0]) ?></span></dd>
                    <dt>Discovery</dt>
                    <dd><span class="admin-badge<?= $discovery[1] !== '' ? ' ' . $discovery[1] : '' ?>"><?= $escape($discovery[0]) ?></span></dd>
                    <dt>Effective version</dt><dd><?= $escape($item['version'] ?? '—') ?></dd>
                    <dt>Stored version</dt><dd><?= $escape($item['stored_version'] ?? '—') ?></dd>
                    <dt>Discovered version</dt><dd><?= $escape($item['discovered_version'] ?? '—') ?></dd>
                    <dt>Stored title</dt><dd><?= $escape($item['stored_title'] ?? '—') ?></dd>
                    <dt>Discovered title</dt><dd><?= $escape($item['discovered_title'] ?? '—') ?></dd>
                </dl>
            </div>
        </section>
        <?php endif; ?>

        <section class="admin-panel admin-module-detail-panel" aria-labelledby="module-actions-title">
            <header class="admin-panel__header"><div class="admin-panel__heading"><h2 class="admin-panel__title" id="module-actions-title">Lifecycle actions</h2><p class="admin-panel__description">Actions follow the existing Module Manager policy.</p></div></header>
            <div class="admin-panel__body">
                <div class="admin-module-detail-actions">
                    <?php foreach ($actionLabels as $action => $label): ?>
                        <?php
                        $eligibility = is_array($item['available_actions'][$action] ?? null)
                            ? $item['available_actions'][$action]
                            : ['visible' => false, 'enabled' => false];
                        $visible = $visibleActions($lifecycleState, $action)
                            && (($eligibility['visible'] ?? false) === true);
                        $enabled = ($eligibility['enabled'] ?? false) === true;
                        $reasons = is_array($item['denial_reasons'][$action] ?? null)
                            ? $item['denial_reasons'][$action] : [];
                        ?>
                        <?php if ($visible): ?>
                            <form method="post" action="<?= $escape($actionPaths[$action] ?? '') ?>" class="admin-form admin-module-detail-action">
                                <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
                                <input type="hidden" name="module" value="<?= $escape($itemName) ?>">
                                <input type="hidden" name="return_context" value="detail">
                                <button class="admin-button<?= $enabled ? ($action === 'uninstall' ? ' admin-button--danger' : ' admin-button--primary') : '' ?>" type="submit"<?= $enabled ? '' : ' disabled' ?>><?= $escape($label) ?></button>
                                <?php if (!$siteSettingsModulesProjection && !$enabled && $reasons !== []): ?><ul class="admin-module-detail-action__reasons"><?php foreach ($reasons as $reason): ?><li><?= $escape($reason) ?></li><?php endforeach; ?></ul><?php endif; ?>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

    </div>
</div>
