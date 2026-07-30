<?php

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$bounded = static function (mixed $value, int $limit = 160): string {
    $value = (string) $value;

    return strlen($value) > $limit ? substr($value, 0, $limit - 1) . '…' : $value;
};
$display = static fn (mixed $value, int $limit = 160): string => htmlspecialchars($bounded($value, $limit), ENT_QUOTES, 'UTF-8');
$stateLabels = [
    'active' => 'Active',
    'inactive' => 'Inactive',
    'discovered' => 'Discovered, not registered',
    'stale' => 'Stale registration',
    'invalid' => 'Invalid',
    'unavailable' => 'Unavailable',
];
$statusLabels = [
    'healthy' => 'Healthy',
    'invalid' => 'Invalid',
    'unavailable' => 'Unavailable',
    'missing' => 'Missing from disk',
];
?>
<div class="admin-theme-workspace">
    <header class="admin-panel__header admin-theme-workspace__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title">Theme Manager</h2>
            <p class="admin-panel__description">Review installed and discovered frontend Themes. Activating a Theme changes the public frontend appearance.</p>
        </div>
    </header>

    <?php if ($notice !== null): ?><div class="admin-notice admin-notice--success" role="status"><?= $escape($notice) ?></div><?php endif; ?>
    <?php if ($error !== null): ?><div class="admin-notice admin-notice--error" role="alert"><?= $escape($error) ?></div><?php endif; ?>

    <?php if ($diagnostics !== []): ?>
        <section class="admin-panel admin-theme-workspace__diagnostics" aria-labelledby="theme-diagnostics-title">
            <div class="admin-panel__body">
                <h2 class="admin-panel__title" id="theme-diagnostics-title">Catalog notice</h2>
                <?php foreach ($diagnostics as $diagnostic): ?>
                    <p><strong><?= $escape($diagnostic['code'] ?? 'catalog_error') ?></strong>: <?= $escape($diagnostic['message'] ?? 'Theme catalog inspection failed.') ?></p>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <section class="admin-empty-state" aria-labelledby="theme-empty-title">
            <h2 class="admin-empty-state__title" id="theme-empty-title">No Themes discovered</h2>
            <p class="admin-empty-state__description">There are no safe Theme definitions available for this installation.</p>
        </section>
    <?php else: ?>
        <section aria-labelledby="theme-inventory-title">
            <h2 class="admin-page-section-heading" id="theme-inventory-title">Theme inventory</h2>
            <div class="admin-theme-grid">
                <?php foreach ($items as $item): ?>
                    <?php
                    $themeId = (string) ($item['theme_id'] ?? 'unknown');
                    $definition = $item['definition'] ?? null;
                    $state = (string) ($item['lifecycle_state'] ?? 'unavailable');
                    $discovery = (string) ($item['discovery_status'] ?? 'unavailable');
                    $identified = preg_match('/^[a-z0-9-]+$/D', $themeId) === 1;
                    $healthy = $definition instanceof \Copot\Core\ThemeDefinition && $discovery === 'healthy';
                    ?>
                    <article class="admin-panel admin-theme-card" aria-labelledby="theme-<?= $escape($bounded($themeId, 120)) ?>-title">
                        <?php if ($healthy && $definition->screenshot() !== null): ?>
                            <div class="admin-theme-card__media"><img src="<?= $escape($screenshotPath($themeId)) ?>" alt="Screenshot of <?= $display($definition->name(), 120) ?> Theme"></div>
                        <?php else: ?>
                            <div class="admin-theme-card__placeholder" aria-label="No Theme screenshot available">No screenshot</div>
                        <?php endif; ?>
                        <div class="admin-panel__body admin-theme-card__body">
                            <div class="admin-theme-card__heading">
                                <div>
                                    <h3 class="admin-panel__title" id="theme-<?= $escape($bounded($themeId, 120)) ?>-title"><?= $display($definition?->name() ?? $item['registry']['name'] ?? $themeId, 120) ?></h3>
                                    <p class="admin-theme-card__id"><code><?= $display($themeId, 120) ?></code></p>
                                </div>
                                <span class="admin-badge admin-badge--<?= $state === 'active' ? 'success' : ($healthy ? 'info' : 'warning') ?>"><?= $escape($stateLabels[$state] ?? 'Unavailable') ?></span>
                            </div>
                            <dl class="admin-theme-card__meta">
                                <div><dt>Version</dt><dd><?= $display($definition?->version() ?? $item['registry']['version'] ?? '—', 80) ?></dd></div>
                                <div><dt>Author</dt><dd><?= $display($definition?->author() ?? '—', 120) ?></dd></div>
                                <div><dt>Discovery</dt><dd><?= $display($statusLabels[$discovery] ?? 'Unavailable', 80) ?></dd></div>
                                <div><dt>Registration</dt><dd><?= $display($item['registration_status'] ?? '—', 80) ?></dd></div>
                                <div><dt>Activation</dt><dd><?= $display($item['activation_status'] ?? '—', 80) ?></dd></div>
                            </dl>
                            <?php if ($definition?->description() !== null): ?><p class="admin-theme-card__description"><?= $display($definition->description()) ?></p><?php endif; ?>
                            <?php if (!empty($item['diagnostic'])): ?><p class="admin-theme-card__diagnostic" role="note"><?= $display($item['diagnostic']['message'] ?? 'Theme definition is unavailable.') ?></p><?php endif; ?>
                            <?php if ($healthy && $definition->supports() !== []): ?><p class="admin-theme-card__capabilities"><strong>Capabilities:</strong> <?= $display(implode(', ', array_keys($definition->supports())), 160) ?></p><?php endif; ?>
                        </div>
                        <?php if ($healthy && $state !== 'active' && $identified): ?>
                            <div class="admin-panel__actions"><form method="post" action="<?= $escape($activationPath($themeId)) ?>">
                                <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="theme_id" value="<?= $escape($themeId) ?>">
                                <button class="admin-button admin-button--primary" type="submit">Activate <?= $display($definition->name(), 120) ?></button>
                            </form></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>
