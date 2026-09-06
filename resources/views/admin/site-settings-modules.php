<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$items = is_array($items ?? null) ? $items : [];
$moduleMessages = [
    'invalid_module_name' => 'The submitted Module name is invalid.',
    'module_manager_self_management_denied' => 'Module Manager cannot disable or uninstall itself.',
    'action_not_allowed' => 'This Module action is not currently allowed.',
    'module_action_failed' => 'The Module lifecycle operation could not be completed.',
    'module_package_invalid' => 'The Module package could not be accepted for preflight.',
    'module_package_failed' => 'The Module package could not be registered.',
    'module_candidate_invalid' => 'The selected Module package candidate is invalid.',
    'module_lifecycle_failed' => 'The Module package lifecycle operation could not be completed.',
];
$moduleNotices = [
    'install_success' => 'Module installed successfully.',
    'enable_success' => 'Module enabled successfully.',
    'disable_success' => 'Module disabled successfully.',
    'uninstall_success' => 'Module uninstalled successfully.',
    'module_package_registered' => 'Module package accepted. Open the affected Module to review the next eligible action.',
    'module_install_success' => 'Module package installed successfully.',
    'module_repair_success' => 'Module package repaired successfully.',
    'module_patch_success' => 'Module package patched successfully.',
    'module_update_success' => 'Module package updated successfully.',
    'module_upgrade_success' => 'Module package upgraded successfully.',
];
$lifecyclePresentation = [
    'not_installed' => ['Not installed', 'admin-badge--info'],
    'installed_disabled' => ['Disabled', 'admin-badge--warning'],
    'installed_enabled' => ['Enabled', 'admin-badge--success'],
    'invalid' => ['Unavailable', 'admin-badge--warning'],
];
$severityRank = ['critical' => 4, 'error' => 3, 'warning' => 2, 'info' => 1];
$issuePresentation = static function (array $item) use ($severityRank): array {
    $diagnostics = is_array($item['diagnostics'] ?? null) ? array_values(array_filter($item['diagnostics'], 'is_array')) : [];
    usort($diagnostics, static function (array $left, array $right) use ($severityRank): int {
        $leftRank = $severityRank[(string) ($left['severity'] ?? '')] ?? 0;
        $rightRank = $severityRank[(string) ($right['severity'] ?? '')] ?? 0;
        return $rightRank <=> $leftRank;
    });
    if ($diagnostics === []) return ['—', '', 0];
    $code = strtolower((string) ($diagnostics[0]['code'] ?? ''));
    $label = match (true) {
        str_contains($code, 'dependency') || str_contains($code, 'dependent') => 'Dependency error',
        str_contains($code, 'package') || str_contains($code, 'integrity') => 'Package integrity error',
        str_contains($code, 'discovery') || str_contains($code, 'route_') || str_contains($code, 'listener_') => 'Discovery failure',
        str_contains($code, 'metadata') || str_contains($code, 'stored_') => 'Metadata mismatch',
        default => 'Module diagnostic',
    };
    $severity = strtolower((string) ($diagnostics[0]['severity'] ?? 'warning'));
    $class = in_array($severity, ['critical', 'error'], true) ? 'admin-action-danger' : ($severity === 'warning' ? 'admin-module-issue--warning' : 'admin-text-muted');
    return [$label, $class, max(0, count($diagnostics) - 1)];
};
$versionOptions = [];
$issueOptions = [];
$statusOptions = [];
foreach ($items as $filterItem) {
    $version = trim((string) ($filterItem['version'] ?? '—')) ?: '—';
    [$issueLabel] = $issuePresentation($filterItem);
    $lifecycle = $lifecyclePresentation[(string) ($filterItem['lifecycle_state'] ?? '')] ?? ['Unavailable', 'admin-badge--warning'];
    $versionOptions[$version] = $version;
    $issueOptions[$issueLabel === '—' ? 'none' : strtolower($issueLabel)] = $issueLabel === '—' ? 'No issue' : $issueLabel;
    $statusOptions[strtolower($lifecycle[0])] = $lifecycle[0];
}
natcasesort($versionOptions);
natcasesort($issueOptions);
natcasesort($statusOptions);
?>
<div class="site-settings-modules" data-site-settings-modules>
    <header class="admin-settings-panel__header site-settings-modules__header">
        <div class="site-settings-modules__intro"><h3>Modules</h3><p>Review discovered Modules and open a Module for lifecycle actions and operational evidence.</p></div>
        <form method="post" action="<?= $escape($packagePath ?? '') ?>" enctype="multipart/form-data" class="admin-form site-settings-module-package-form">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <label class="admin-field__label" for="site-settings-module-package">Add Module package (ZIP)</label>
            <div class="admin-actions site-settings-module-package-controls"><input id="site-settings-module-package" type="file" name="module_package" accept=".zip,application/zip" required><button class="admin-button admin-button--primary" type="submit">Add Module</button></div>
        </form>
    </header>
    <?php if (!empty($notice)): ?><div class="admin-alert admin-alert--success" role="status"><?= $escape($moduleNotices[(string) $notice] ?? 'Module operation completed.') ?></div><?php endif; ?>
    <?php if (!empty($error)): ?><div class="admin-alert admin-alert--danger" role="alert"><?= $escape($moduleMessages[(string) $error] ?? 'The Module operation could not be completed.') ?></div><?php endif; ?>
    <div class="site-settings-modules__tools" role="group" aria-label="Filter Modules">
        <div class="admin-field site-settings-module-filter">
            <label class="admin-field__label" for="site-settings-module-filter-name">Name</label>
            <input id="site-settings-module-filter-name" type="search" placeholder="Title or identity" autocomplete="off" data-site-settings-module-filter="name">
        </div>
        <div class="admin-field site-settings-module-filter">
            <label class="admin-field__label" for="site-settings-module-filter-version">Version</label>
            <select id="site-settings-module-filter-version" data-site-settings-module-filter="version">
                <option value="">All versions</option>
                <?php foreach ($versionOptions as $version): ?><option value="<?= $escape(strtolower($version)) ?>"><?= $escape($version) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="admin-field site-settings-module-filter">
            <label class="admin-field__label" for="site-settings-module-filter-issue">Issue</label>
            <select id="site-settings-module-filter-issue" data-site-settings-module-filter="issue">
                <option value="">All issues</option>
                <?php foreach ($issueOptions as $value => $label): ?><option value="<?= $escape($value) ?>"><?= $escape($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="admin-field site-settings-module-filter">
            <label class="admin-field__label" for="site-settings-module-filter-status">Status</label>
            <select id="site-settings-module-filter-status" data-site-settings-module-filter="status">
                <option value="">All statuses</option>
                <?php foreach ($statusOptions as $value => $label): ?><option value="<?= $escape($value) ?>"><?= $escape($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <p class="admin-field__help" data-site-settings-module-count aria-live="polite"><?= count($items) ?> Modules</p>
    </div>
    <?php if ($items === []): ?>
        <div class="admin-empty-state"><h4 class="admin-empty-state__title">No Modules found</h4><p class="admin-empty-state__description">No Modules were discovered or installed.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap site-settings-modules-table-wrap">
            <table class="admin-table site-settings-modules-table">
                <caption class="admin-visually-hidden">Module inventory</caption>
                <thead><tr><th scope="col">Module</th><th scope="col">Version</th><th scope="col">Issue</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php
                    $name = (string) ($item['name'] ?? '');
                    $title = (string) ($item['title'] ?? $name);
                    [$issueLabel, $issueClass, $additionalIssues] = $issuePresentation($item);
                    $lifecycle = $lifecyclePresentation[(string) ($item['lifecycle_state'] ?? '')] ?? ['Unavailable', 'admin-badge--warning'];
                    $searchIndex = strtolower(trim($title . ' ' . $name));
                    $versionFilter = strtolower(trim((string) ($item['version'] ?? '—'))) ?: '—';
                    $issueFilter = $issueLabel === '—' ? 'none' : strtolower($issueLabel);
                    $statusFilter = strtolower($lifecycle[0]);
                    ?>
                    <tr class="site-settings-module-row" data-site-settings-module-row data-detail-url="<?= $escape($detailPath($name)) ?>" data-filter-name="<?= $escape($searchIndex) ?>" data-filter-version="<?= $escape($versionFilter) ?>" data-filter-issue="<?= $escape($issueFilter) ?>" data-filter-status="<?= $escape($statusFilter) ?>" tabindex="0" aria-label="Open <?= $escape($title) ?> Module details">
                        <th scope="row" data-label="Module"><span class="admin-module-identity__title"><?= $escape($title) ?></span><span class="admin-module-identity__name"><code><?= $escape($name) ?></code></span></th>
                        <td data-label="Version"><span class="admin-module-version__primary"><?= $escape($item['version'] ?? '—') ?></span><?php if (!empty($item['available_package_version'])): ?><span class="admin-text-muted">Available: <?= $escape($item['available_package_version']) ?></span><?php endif; ?></td>
                        <td data-label="Issue"><span class="site-settings-module-issue <?= $escape($issueClass) ?>"><?= $escape($issueLabel) ?></span><?php if ($additionalIssues > 0): ?><span class="admin-text-muted"> +<?= $additionalIssues ?> more</span><?php endif; ?></td>
                        <td data-label="Status"><span class="admin-badge <?= $escape($lifecycle[1]) ?>"><?= $escape($lifecycle[0]) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-empty-state site-settings-modules__no-match" data-site-settings-module-no-match hidden><h4 class="admin-empty-state__title">No matching Modules</h4><p class="admin-empty-state__description">Try a different title or technical Module identity.</p></div>
    <?php endif; ?>
</div>
<script src="<?= $escape(is_callable($url ?? null) ? $url('/admin-assets/js/site-settings-modules.js?v=wu4-modules-2') : '/admin-assets/js/site-settings-modules.js?v=wu4-modules-2') ?>" defer></script>
