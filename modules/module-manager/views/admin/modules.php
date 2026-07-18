<?php

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$actionLabels = [
    'install' => 'Install',
    'enable' => 'Enable',
    'disable' => 'Disable',
    'uninstall' => 'Uninstall',
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
?>
<section class="admin-panel admin-modules-page" aria-labelledby="modules-list-title">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="modules-list-title">Modules</h2>
            <p class="admin-panel__description">Review discovered modules and manage their lifecycle.</p>
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
            <div class="admin-empty-state">
                <h3 class="admin-empty-state__title">No modules found</h3>
                <p class="admin-empty-state__description">No modules were discovered or installed.</p>
            </div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table admin-modules-table">
                    <thead>
                    <tr>
                        <th scope="col">Module</th>
                        <th scope="col">Version</th>
                        <th scope="col">Lifecycle</th>
                        <th scope="col">Discovery</th>
                        <th scope="col">Notes</th>
                        <th scope="col">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $name = (string) ($item['name'] ?? '');
                        $lifecycleState = (string) ($item['lifecycle_state'] ?? '');
                        $discoveryState = (string) ($item['discovery_state'] ?? '');
                        $lifecycle = $lifecyclePresentation[$lifecycleState] ?? [$lifecycleState, ''];
                        $discovery = $discoveryPresentation[$discoveryState] ?? [$discoveryState, ''];
                        $notes = [];
                        foreach ($actionLabels as $noteAction => $noteLabel) {
                            $noteEligibility = is_array($item['available_actions'][$noteAction] ?? null)
                                ? $item['available_actions'][$noteAction]
                                : ['visible' => false, 'enabled' => false];
                            $noteVisible = $visibleActions($lifecycleState, $noteAction)
                                && (($noteEligibility['visible'] ?? false) === true);
                            $noteReason = $item['denial_reasons'][$noteAction][0] ?? null;
                            if ($noteVisible && (($noteEligibility['enabled'] ?? false) !== true) && $noteReason !== null) {
                                $notes[] = (string) $noteReason;
                            }
                        }
                        ?>
                        <tr>
                            <th scope="row" class="admin-module-identity">
                                <a class="admin-module-identity__title" href="<?= $escape($detailPath($name)) ?>"><?= $escape($item['title'] ?? $name) ?></a>
                                <div class="admin-module-identity__name"><code><?= $escape($name) ?></code></div>
                            </th>
                            <td class="admin-module-version">
                                <span class="admin-module-version__primary"><?= $escape($item['version'] ?? '') ?></span>
                            </td>
                            <td class="admin-module-lifecycle">
                                <span class="admin-badge<?= $lifecycle[1] !== '' ? ' ' . $lifecycle[1] : '' ?>"><?= $escape($lifecycle[0]) ?></span>
                            </td>
                            <td class="admin-module-discovery">
                                <span class="admin-badge<?= $discovery[1] !== '' ? ' ' . $discovery[1] : '' ?>"><?= $escape($discovery[0]) ?></span>
                            </td>
                            <td class="admin-module-notes">
                                <?php if ($notes === []): ?><span class="admin-text-muted">—</span><?php else: ?><?= $escape(implode('; ', array_unique($notes))) ?><?php endif; ?>
                            </td>
                            <td class="admin-module-actions">
                                <div class="admin-row-actions">
                                    <a class="admin-button admin-button--link" href="<?= $escape($detailPath($name)) ?>">Open</a>
                                    <?php foreach ($actionLabels as $action => $label): ?>
                                        <?php
                                        $eligibility = is_array($item['available_actions'][$action] ?? null)
                                            ? $item['available_actions'][$action]
                                            : ['visible' => false, 'enabled' => false];
                                        $visible = $visibleActions($lifecycleState, $action)
                                            && (($eligibility['visible'] ?? false) === true);
                                        $enabled = ($eligibility['enabled'] ?? false) === true;
                                        $reason = $item['denial_reasons'][$action][0] ?? null;
                                        ?>
                                        <?php if ($visible): ?>
                                            <form method="post" action="<?= $escape($actionPaths[$action] ?? '') ?>" class="admin-form admin-form--inline admin-module-action">
                                                <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
                                                <input type="hidden" name="module" value="<?= $escape($name) ?>">
                                                <input type="hidden" name="return_context" value="list">
                                                <button class="admin-button<?= $enabled ? ($action === 'uninstall' ? ' admin-button--danger' : ' admin-button--primary') : '' ?>" type="submit"<?= $enabled ? '' : ' disabled' ?>><?= $escape($label) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
