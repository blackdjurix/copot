<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$bounded = static fn (mixed $value, int $limit = 180): string => strlen((string) $value) > $limit ? substr((string) $value, 0, $limit - 1) . '…' : (string) $value;
$display = static fn (mixed $value, int $limit = 180): string => $escape($bounded($value, $limit));
$active = (($item['activation_status'] ?? '') === 'active');
?>
<div class="admin-theme-workspace admin-theme-settings">
    <header class="admin-panel__header admin-theme-workspace__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title">Theme settings</h2>
            <p class="admin-panel__description">Configure <?= $display($theme->name(), 120) ?> without changing the public frontend appearance.</p>
        </div>
        <a class="admin-button" href="<?= $escape($inventoryPath) ?>">Back to Themes</a>
    </header>
    <?php if ($saved): ?><div class="admin-notice admin-notice--success" role="status">Theme settings saved successfully.</div><?php endif; ?>
    <?php if ($reset): ?><div class="admin-notice admin-notice--success" role="status">Theme settings reset to their defaults.</div><?php endif; ?>
    <?php foreach ($formErrors as $error): ?><div class="admin-notice admin-notice--error" role="alert"><?= $display($error) ?></div><?php endforeach; ?>
    <p class="admin-theme-settings__state" role="status"><strong><?= $active ? 'Active Theme' : 'Inactive Theme' ?></strong> — <?= $active ? 'These values affect the current frontend.' : 'Values for this inactive Theme are preserved and will apply if it becomes active.' ?></p>
    <?php if ($fields === []): ?>
        <section class="admin-empty-state"><h2 class="admin-empty-state__title">No settings declared</h2><p class="admin-empty-state__description">This Theme does not declare configurable settings.</p></section>
    <?php else: ?>
        <form method="post" action="<?= $escape($formPath) ?>" class="admin-form">
            <input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="theme_id" value="<?= $escape($theme->id()) ?>">
            <?php foreach ($fields as $section): ?><fieldset class="admin-panel admin-theme-settings__section"><legend class="admin-panel__title"><?= $display($section['label'], 120) ?></legend><?php if (($section['description'] ?? null) !== null): ?><p><?= $display($section['description']) ?></p><?php endif; ?>
                <?php foreach ($section['fields'] as $field): $key = (string) $field['key']; $value = $values[$key] ?? $field['default']; $error = $errors[$key] ?? null; $id = 'theme-setting-' . $key; $validation = $field['validation'] ?? []; ?>
                    <div class="admin-form__field"><label for="<?= $escape($id) ?>"><?= $display($field['label'], 120) ?><?php if (($validation['required'] ?? false)): ?> <span aria-hidden="true">*</span><span class="admin-sr-only">(required)</span><?php endif; ?></label>
                    <?php if (($field['description'] ?? null) !== null): ?><p id="<?= $escape($id) ?>-description"><?= $display($field['description']) ?></p><?php endif; ?>
                    <?php $described = ($field['description'] ?? null) !== null ? $id . '-description' : null; $aria = ($error !== null ? ' aria-invalid="true"' : '') . ($described !== null ? ' aria-describedby="' . $escape($described) . '"' : ''); ?>
                    <?php if ($field['control'] === 'select'): ?><select id="<?= $escape($id) ?>" name="settings[<?= $escape($key) ?>]"<?= $aria ?>><?php foreach (($validation['allowed_values'] ?? []) as $option): ?><option value="<?= $escape($option) ?>"<?= ((string) $option === (string) $value) ? ' selected' : '' ?>><?= $display($option, 100) ?></option><?php endforeach; ?></select>
                    <?php elseif ($field['control'] === 'checkbox'): ?><input id="<?= $escape($id) ?>" type="checkbox" name="settings[<?= $escape($key) ?>]" value="1"<?= $value ? ' checked' : '' ?><?= $aria ?>>
                    <?php else: ?><input id="<?= $escape($id) ?>" type="<?= $field['control'] === 'color' ? 'color' : ($field['control'] === 'number' ? 'number' : 'text') ?>" name="settings[<?= $escape($key) ?>]" value="<?= $escape($value) ?>"<?= $field['type'] === 'integer' ? ' step="1"' : ($field['type'] === 'float' ? ' step="any"' : '') ?><?= isset($validation['min']) ? ' min="' . $escape($validation['min']) . '"' : '' ?><?= isset($validation['max']) ? ' max="' . $escape($validation['max']) . '"' : '' ?><?= isset($validation['max_length']) ? ' maxlength="' . $escape($validation['max_length']) . '"' : '' ?><?= $aria ?>><?php endif; ?>
                    <?php if ($error !== null): ?><p class="admin-form__error" id="<?= $escape($id) ?>-error" role="alert"><?= $display($error) ?></p><?php endif; ?></div>
                <?php endforeach; ?></fieldset><?php endforeach; ?>
            <div class="admin-panel__actions"><button class="admin-button admin-button--primary" type="submit">Save Theme settings</button></div>
        </form>
        <form method="post" action="<?= $escape($resetPath) ?>" class="admin-theme-settings__reset"><input type="hidden" name="_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="theme_id" value="<?= $escape($theme->id()) ?>"><p>Reset removes all saved overrides for this Theme and restores its declared defaults.</p><button class="admin-button" type="submit">Reset to defaults</button></form>
    <?php endif; ?>
    <?php if (!empty($theme->supports()['navigation_locations'])): ?><section class="admin-panel admin-theme-settings__navigation"><h2 class="admin-panel__title">Navigation locations</h2><p>This Theme declares these locations for later Navigation configuration:</p><ul><?php foreach ($theme->supports()['navigation_locations'] as $location): ?><li><code><?= $display($location, 100) ?></code></li><?php endforeach; ?></ul></section><?php endif; ?>
</div>
