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
    'metadata_drift' => 'Stored metadata differs from the manifest',
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
?>
<section class="admin-panel" aria-describedby="modules-description">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <p class="admin-panel__description" id="modules-description">Review discovered modules and manage their lifecycle.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($notice)): ?>
            <div class="admin-alert admin-alert--success" role="status"><?= $escape($notice) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="admin-alert admin-alert--danger" role="alert">
                <strong class="admin-alert__title">Module action could not be completed.</strong>
                <p><?= $escape($error) ?></p>
            </div>
        <?php endif; ?>

        <?php if (($items ?? []) === []): ?>
            <p class="admin-empty-state">No modules were discovered or installed.</p>
        <?php else: ?>
            <div class="admin-table-wrapper">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th scope="col">Module</th>
                        <th scope="col">Version</th>
                        <th scope="col">Lifecycle</th>
                        <th scope="col">Discovery</th>
                        <th scope="col">Diagnostics</th>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $name = (string) ($item['name'] ?? '');
                        $diagnostics = is_array($item['diagnostics'] ?? null) ? $item['diagnostics'] : [];
                        $dependencies = is_array($item['dependencies'] ?? null) ? $item['dependencies'] : [];
                        $contributions = is_array($item['contribution_files'] ?? null) ? $item['contribution_files'] : [];
                        $storedPermissions = is_array($item['permission_metadata_summary'] ?? null)
                            ? $item['permission_metadata_summary'] : [];
                        $discoveredPermissions = is_array($item['discovered_permission_metadata_summary'] ?? null)
                            ? $item['discovered_permission_metadata_summary'] : [];
                        ?>
                        <tr>
                            <th scope="row">
                                <strong><?= $escape($item['title'] ?? $name) ?></strong>
                                <div class="admin-field__help">Name: <?= $escape($name) ?></div>
                                <?php if (($item['discovered_title'] ?? null) !== null || ($item['stored_title'] ?? null) !== null): ?>
                                    <div class="admin-field__help">
                                        Stored: <?= $escape($item['stored_title'] ?? '—') ?>;
                                        Discovered: <?= $escape($item['discovered_title'] ?? '—') ?>
                                    </div>
                                <?php endif; ?>
                            </th>
                            <td>
                                <?= $escape($item['version'] ?? '') ?>
                                <?php if (($item['stored_version'] ?? null) !== null || ($item['discovered_version'] ?? null) !== null): ?>
                                    <div class="admin-field__help">
                                        Stored: <?= $escape($item['stored_version'] ?? '—') ?>;
                                        Discovered: <?= $escape($item['discovered_version'] ?? '—') ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= $escape($item['lifecycle_state'] ?? '') ?></td>
                            <td><?= $escape($item['discovery_state'] ?? '') ?></td>
                            <td>
                                <div class="admin-field__help"><strong>Stored permissions:</strong>
                                    <?php if ($storedPermissions === []): ?>
                                        None
                                    <?php else: ?>
                                        <?php foreach ($storedPermissions as $permission): ?>
                                            <span><?= $escape($permission['slug'] ?? '') ?> — <?= $escape($permission['name'] ?? '') ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-field__help"><strong>Discovered permissions:</strong>
                                    <?php if ($discoveredPermissions === []): ?>
                                        None
                                    <?php else: ?>
                                        <?php foreach ($discoveredPermissions as $permission): ?>
                                            <span><?= $escape($permission['slug'] ?? '') ?> — <?= $escape($permission['name'] ?? '') ?></span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($diagnostics === []): ?>
                                    <span>None</span>
                                <?php else: ?>
                                    <ul class="admin-alert__list">
                                        <?php foreach ($diagnostics as $diagnostic): ?>
                                            <?php $code = (string) ($diagnostic['code'] ?? 'unknown'); ?>
                                            <li><?= $escape($diagnosticLabels[$code] ?? 'Module diagnostic') ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <?php if ($dependencies !== []): ?>
                                    <div class="admin-field__help">Dependencies:
                                        <?= $escape(implode(', ', array_map(static fn (array $dependency): string => (string) ($dependency['name'] ?? ''), $dependencies))) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($contributions !== []): ?>
                                    <div class="admin-field__help">Contributions:
                                        <?php foreach ($contributions as $type => $contribution): ?>
                                            <?= $escape((string) $type) ?> <?= !empty($contribution['available']) ? 'available' : 'missing' ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php foreach ($actionLabels as $action => $label): ?>
                                    <?php
                                    $eligibility = is_array($item['available_actions'][$action] ?? null)
                                        ? $item['available_actions'][$action]
                                        : ['visible' => false, 'enabled' => false];
                                    $visible = ($eligibility['visible'] ?? false) === true;
                                    $enabled = ($eligibility['enabled'] ?? false) === true;
                                    $reason = $item['denial_reasons'][$action][0] ?? null;
                                    ?>
                                    <?php if ($visible): ?>
                                        <form method="post" action="<?= $escape($actionPaths[$action] ?? '') ?>" class="admin-form admin-form--inline">
                                            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
                                            <input type="hidden" name="module" value="<?= $escape($name) ?>">
                                            <button class="admin-button<?= $enabled ? ' admin-button--primary' : '' ?>" type="submit"<?= $enabled ? '' : ' disabled' ?>><?= $escape($label) ?></button>
                                            <?php if (!$enabled && $reason !== null): ?>
                                                <span class="admin-field__help"><?= $escape((string) $reason) ?></span>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
