<?php
$settingsSelectComparableValue = static function (SettingsField $field, mixed $value): array {
    return match ($field->valueType()) {
        'string' => [is_string($value), $value],
        'integer' => match (true) {
            is_int($value) => [true, $value],
            is_string($value) && preg_match('/^[+-]?[0-9]+$/', $value) === 1 => (static function () use ($value): array {
                $negative = str_starts_with($value, '-');
                $digits = ltrim(ltrim($value, '+-'), '0');
                $canonical = ($negative && $digits !== '' ? '-' : '') . ($digits === '' ? '0' : $digits);
                $integer = filter_var($canonical, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

                return [is_int($integer), $integer];
            })(),
            default => [false, null],
        },
        'float' => match (true) {
            is_float($value) && is_finite($value) => [true, $value],
            is_int($value) => [true, (float) $value],
            is_string($value) && is_numeric($value) && is_finite((float) $value) => [true, (float) $value],
            default => [false, null],
        },
        default => [false, null],
    };
};

$settingsSectionsByIdentifier = [];
foreach (($sections ?? []) as $section) {
    $settingsSectionsByIdentifier[$section->identifier()] = $section;
}

$settingsInitialTab = 'general';
if (!empty($assetErrors) || !empty($assetNotice)) {
    $settingsInitialTab = 'branding';
} elseif (!empty($fieldErrors)) {
    foreach (array_keys($fieldErrors) as $fieldIdentifier) {
        if (str_starts_with((string) $fieldIdentifier, 'localization.')) {
            $settingsInitialTab = 'localization';
            break;
        }
    }
}

$renderSettingsFields = static function ($section) use (
    $settingsSelectComparableValue,
    $fieldErrors,
    $values
): void {
    if ($section === null) {
        return;
    }
    $sectionAnchor = match ($section->identifier()) {
        'site' => 'settings-general',
        'localization' => 'settings-localization',
        default => 'settings-section-' . $section->identifier(),
    };
    ?>
    <fieldset class="admin-fieldset">
        <legend class="admin-fieldset__legend" id="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES, 'UTF-8') ?>-title"><?= htmlspecialchars($section->label(), ENT_QUOTES, 'UTF-8') ?></legend>
        <?php if ($section->description() !== null): ?>
            <p class="admin-settings-panel__description"><?= htmlspecialchars($section->description(), ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <div class="admin-settings-field-grid">
            <?php foreach ($section->fields() as $field): ?>
                <?php
                $identifier = $field->identifier();
                $fieldId = 'setting-' . bin2hex($identifier);
                $helpId = $fieldId . '-help';
                $errorId = $fieldId . '-error';
                $errorsForField = $fieldErrors[$identifier] ?? [];
                $describedBy = [];
                if ($field->description() !== null) {
                    $describedBy[] = $helpId;
                }
                if ($errorsForField !== []) {
                    $describedBy[] = $errorId;
                }
                $value = $values[$identifier] ?? $field->defaultValue();
                [$hasComparableValue, $comparableValue] = $field->fieldType() === 'select'
                    ? $settingsSelectComparableValue($field, $value)
                    : [false, null];
                $hasSelectedOption = $hasComparableValue
                    && in_array($comparableValue, $field->options(), true);
                $showInvalidOption = $field->fieldType() === 'select'
                    && $errorsForField !== []
                    && !$hasSelectedOption
                    && (is_string($value) || is_int($value) || (is_float($value) && is_finite($value)));
                $wideField = $field->fieldType() === 'textarea' || ($field->maximumLength() !== null && $field->maximumLength() > 190);
                ?>
                <div class="admin-field<?= $wideField ? ' admin-field--wide' : '' ?>">
                    <label class="admin-field__label" for="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($field->label(), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($field->required()): ?>
                            <span class="admin-field__required" aria-hidden="true">*</span>
                            <span class="admin-visually-hidden">required</span>
                        <?php endif; ?>
                    </label>

                    <?php if ($field->fieldType() === 'select'): ?>
                        <select
                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"
                            name="settings[<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>]"
                            <?= $describedBy !== [] ? 'aria-describedby="' . htmlspecialchars(implode(' ', $describedBy), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                            <?= $errorsForField !== [] ? 'aria-invalid="true"' : '' ?>
                            <?= $field->required() ? 'required' : '' ?>
                        >
                            <?php if ($showInvalidOption): ?>
                                <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars((string) $value . ' (invalid)', ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endif; ?>
                            <?php foreach ($field->options() as $option): ?>
                                <option value="<?= htmlspecialchars((string) $option, ENT_QUOTES, 'UTF-8') ?>" <?= ($hasComparableValue && $comparableValue === $option) ? 'selected' : '' ?>><?= htmlspecialchars((string) $option, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($field->fieldType() === 'textarea'): ?>
                        <textarea
                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"
                            name="settings[<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>]"
                            <?= $field->maximumLength() !== null ? 'maxlength="' . $field->maximumLength() . '"' : '' ?>
                            <?= $describedBy !== [] ? 'aria-describedby="' . htmlspecialchars(implode(' ', $describedBy), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                            <?= $errorsForField !== [] ? 'aria-invalid="true"' : '' ?>
                            <?= $field->required() ? 'required' : '' ?>
                        ><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <?php else: ?>
                        <input
                            id="<?= htmlspecialchars($fieldId, ENT_QUOTES, 'UTF-8') ?>"
                            name="settings[<?= htmlspecialchars($identifier, ENT_QUOTES, 'UTF-8') ?>]"
                            type="<?= $field->fieldType() === 'checkbox' ? 'checkbox' : htmlspecialchars($field->fieldType(), ENT_QUOTES, 'UTF-8') ?>"
                            <?= $field->fieldType() === 'checkbox' ? 'value="1"' : 'value="' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '"' ?>
                            <?= $field->fieldType() === 'checkbox' && (bool) $value ? 'checked' : '' ?>
                            <?= $field->fieldType() === 'number' ? 'step="' . ($field->valueType() === 'integer' ? '1' : 'any') . '"' : '' ?>
                            <?= $field->maximumLength() !== null ? 'maxlength="' . $field->maximumLength() . '"' : '' ?>
                            <?= $describedBy !== [] ? 'aria-describedby="' . htmlspecialchars(implode(' ', $describedBy), ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                            <?= $errorsForField !== [] ? 'aria-invalid="true"' : '' ?>
                            <?= $field->required() ? 'required' : '' ?>
                        >
                    <?php endif; ?>

                    <?php if ($field->description() !== null): ?>
                        <p class="admin-field__help" id="<?= htmlspecialchars($helpId, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($field->description(), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($errorsForField !== []): ?>
                        <div class="admin-field__error" id="<?= htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8') ?>">
                            <?php foreach ($errorsForField as $error): ?>
                                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <?php
};
?>
<section class="admin-panel admin-settings-page" aria-labelledby="settings-title" aria-describedby="settings-description" data-settings-page data-initial-tab="<?= htmlspecialchars($settingsInitialTab, ENT_QUOTES, 'UTF-8') ?>">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="settings-title">Settings</h2>
            <p class="admin-panel__description" id="settings-description">Manage site, localization, and branding settings available in this build.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <div class="admin-settings-tabs-wrap">
            <div class="admin-settings-tabs" role="tablist" aria-label="Settings sections">
                <?php foreach ([
                    'general' => 'General',
                    'localization' => 'Localization',
                    'security' => 'Security',
                    'email' => 'Email',
                    'maintenance' => 'Maintenance',
                    'branding' => 'Branding',
                ] as $tabId => $tabLabel): ?>
                    <?php $isSelected = $settingsInitialTab === $tabId; ?>
                    <button
                        class="admin-settings-tab<?= $isSelected ? ' is-active' : '' ?>"
                        id="settings-tab-<?= $tabId ?>"
                        type="button"
                        role="tab"
                        aria-selected="<?= $isSelected ? 'true' : 'false' ?>"
                        aria-controls="settings-panel-<?= $tabId ?>"
                        tabindex="<?= $isSelected ? '0' : '-1' ?>"
                        data-settings-tab="<?= $tabId ?>"
                    >
                        <span data-settings-tab-label><?= htmlspecialchars($tabLabel, ENT_QUOTES, 'UTF-8') ?></span><span class="admin-settings-tab__dirty" aria-hidden="true" hidden>•</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="admin-settings-feedback" aria-live="polite">
            <?php if (!empty($saved)): ?>
                <div class="admin-alert admin-alert--success" role="status">Settings saved successfully.</div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
                <div class="admin-alert admin-alert--danger" role="alert" aria-labelledby="settings-error-title">
                    <strong class="admin-alert__title" id="settings-error-title">Settings could not be saved.</strong>
                    <ul class="admin-alert__list">
                        <?php foreach ($formErrors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <form class="admin-form admin-settings-form" method="post" action="<?= htmlspecialchars($formAction ?? '', ENT_QUOTES, 'UTF-8') ?>" data-settings-dirty-form>
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <section class="admin-settings-panel" id="settings-panel-general" role="tabpanel" aria-labelledby="settings-tab-general" data-settings-panel="general" <?= $settingsInitialTab === 'general' ? '' : 'hidden' ?>>
                <header class="admin-settings-panel__header">
                    <h3>General</h3>
                    <p>Global site settings currently supported by Copot.</p>
                </header>
                <?php $renderSettingsFields($settingsSectionsByIdentifier['site'] ?? null); ?>
                <div class="admin-actions admin-form__actions">
                    <button class="admin-button admin-button--primary" type="submit" data-saving-label="Saving…">Save settings</button>
                </div>
            </section>

            <section class="admin-settings-panel" id="settings-panel-localization" role="tabpanel" aria-labelledby="settings-tab-localization" data-settings-panel="localization" <?= $settingsInitialTab === 'localization' ? '' : 'hidden' ?>>
                <header class="admin-settings-panel__header">
                    <h3>Localization</h3>
                    <p>Timezone, locale, date, and time presentation settings currently supported by Copot.</p>
                </header>
                <?php $renderSettingsFields($settingsSectionsByIdentifier['localization'] ?? null); ?>
                <div class="admin-actions admin-form__actions">
                    <button class="admin-button admin-button--primary" type="submit" data-saving-label="Saving…">Save settings</button>
                </div>
            </section>
        </form>

        <?php foreach ([
            'security' => ['Security', 'Security settings are not configurable in this build.'],
            'email' => ['Email', 'Email settings are not configurable in this build.'],
            'maintenance' => ['Maintenance', 'Maintenance settings are not configurable in this build.'],
        ] as $panelId => [$panelTitle, $panelMessage]): ?>
            <section class="admin-settings-panel" id="settings-panel-<?= $panelId ?>" role="tabpanel" aria-labelledby="settings-tab-<?= $panelId ?>" data-settings-panel="<?= $panelId ?>" <?= $settingsInitialTab === $panelId ? '' : 'hidden' ?>>
                <header class="admin-settings-panel__header">
                    <h3><?= htmlspecialchars($panelTitle, ENT_QUOTES, 'UTF-8') ?></h3>
                </header>
                <div class="admin-empty-state admin-settings-empty-state">
                    <h4 class="admin-empty-state__title">Not configurable yet</h4>
                    <p class="admin-empty-state__description"><?= htmlspecialchars($panelMessage, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </section>
        <?php endforeach; ?>

        <section class="admin-settings-panel admin-settings-branding" id="settings-panel-branding" role="tabpanel" aria-labelledby="settings-tab-branding" data-settings-panel="branding" <?= $settingsInitialTab === 'branding' ? '' : 'hidden' ?>>
            <header class="admin-settings-panel__header">
                <h3>Branding</h3>
                <p>Manage the public Logo and Favicon used by the active frontend theme.</p>
            </header>

            <?php if (!empty($assetNotice)): ?>
                <div class="admin-alert admin-alert--success" role="status"><?= htmlspecialchars($assetNotice, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="admin-settings-brand-assets">
                <?php foreach ([
                    'logo' => [
                        'title' => 'Logo',
                        'help' => 'PNG, JPG, or WebP. Maximum 2 MB and 4096 × 4096 px.',
                        'url' => $logoUrl ?? null,
                        'uploadAction' => $logoUploadAction ?? '',
                        'removeAction' => $logoRemoveAction ?? '',
                        'accept' => 'image/png,image/jpeg,image/webp',
                        'alt' => 'Current site logo',
                    ],
                    'favicon' => [
                        'title' => 'Favicon',
                        'help' => 'PNG or ICO. Maximum 512 KB and 512 × 512 px.',
                        'url' => $faviconUrl ?? null,
                        'uploadAction' => $faviconUploadAction ?? '',
                        'removeAction' => $faviconRemoveAction ?? '',
                        'accept' => 'image/png,image/x-icon,image/vnd.microsoft.icon,.ico',
                        'alt' => 'Current site favicon',
                    ],
                ] as $slot => $asset): ?>
                    <article class="admin-asset-card admin-settings-brand-asset" aria-labelledby="<?= $slot ?>-slot-title">
                        <header class="admin-settings-brand-asset__header">
                            <div>
                                <h4 id="<?= $slot ?>-slot-title"><?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                <p class="admin-field__help"><?= htmlspecialchars($asset['help'], ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </header>

                        <div class="admin-asset-preview<?= $slot === 'favicon' ? ' admin-asset-preview--favicon' : '' ?>" data-asset-preview="<?= $slot ?>" data-asset-preview-alt="<?= htmlspecialchars($asset['alt'], ENT_QUOTES, 'UTF-8') ?>" <?= empty($asset['url']) ? 'hidden' : '' ?>>
                            <?php if (!empty($asset['url'])): ?>
                                <img src="<?= htmlspecialchars($asset['url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($asset['alt'], ENT_QUOTES, 'UTF-8') ?>" data-asset-preview-image>
                            <?php endif; ?>
                        </div>
                        <p class="admin-empty-state" data-asset-empty="<?= $slot ?>" <?= !empty($asset['url']) ? 'hidden' : '' ?>>No <?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?> uploaded.</p>

                        <?php if (isset($assetErrors[$slot])): ?>
                            <div class="admin-alert admin-alert--danger" role="alert"><?= htmlspecialchars($assetErrors[$slot], ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>

                        <form class="admin-form admin-asset-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($asset['uploadAction'], ENT_QUOTES, 'UTF-8') ?>" data-settings-dirty-form data-asset-form="<?= $slot ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <div class="admin-field">
                                <label class="admin-field__label" for="site_<?= $slot ?>">Choose <?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input id="site_<?= $slot ?>" name="site_asset" type="file" accept="<?= htmlspecialchars($asset['accept'], ENT_QUOTES, 'UTF-8') ?>" required data-asset-input="<?= $slot ?>">
                            </div>
                            <button class="admin-button admin-button--primary" type="submit" data-saving-label="Uploading…"><?= !empty($asset['url']) ? 'Replace' : 'Upload' ?> <?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></button>
                        </form>

                        <?php if (!empty($asset['url'])): ?>
                            <form method="post" action="<?= htmlspecialchars($asset['removeAction'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button class="admin-button admin-button--danger" type="submit">Remove <?= htmlspecialchars($asset['title'], ENT_QUOTES, 'UTF-8') ?></button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</section>
<script src="/admin-assets/js/admin-settings.js" defer></script>
