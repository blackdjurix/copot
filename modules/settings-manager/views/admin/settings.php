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
?>
<section class="admin-panel admin-settings-page" aria-labelledby="settings-title" aria-describedby="settings-description">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <h2 class="admin-panel__title" id="settings-title">Site and localization settings</h2>
            <p class="admin-panel__description" id="settings-description">Manage global site and localization settings.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <nav class="admin-settings-navigation" aria-label="Settings sections">
            <a href="#settings-general">General</a>
            <a href="#settings-localization">Localization</a>
            <a href="#settings-branding">Branding</a>
        </nav>

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

        <form class="admin-form admin-settings-form" method="post" action="<?= htmlspecialchars($formAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <?php foreach (($sections ?? []) as $section): ?>
                <?php $sectionAnchor = match ($section->identifier()) {
                    'site' => 'settings-general',
                    'localization' => 'settings-localization',
                    default => 'settings-section-' . $section->identifier(),
                }; ?>
                <section class="admin-settings-section" id="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES, 'UTF-8') ?>-title">
                    <fieldset class="admin-fieldset">
                        <legend class="admin-fieldset__legend" id="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES, 'UTF-8') ?>-title"><?= htmlspecialchars($section->label(), ENT_QUOTES, 'UTF-8') ?></legend>
                        <?php if ($section->description() !== null): ?>
                            <p class="admin-field__help"><?= htmlspecialchars($section->description(), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>

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
                        ?>
                            <div class="admin-field">
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
                    </fieldset>
                </section>
            <?php endforeach; ?>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">Save Settings</button>
            </div>
        </form>

        <section class="admin-settings-section" id="settings-branding" aria-labelledby="site-branding-title">
            <div class="admin-settings-section__header">
                <h3 id="site-branding-title">Site Branding</h3>
                <p>Upload the public Logo and Favicon used by the active frontend theme.</p>
            </div>

            <?php if (!empty($assetNotice)): ?>
                <div class="admin-alert admin-alert--success" role="status">
                    <?= htmlspecialchars($assetNotice, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <div class="admin-asset-grid">
                <article class="admin-asset-card" aria-labelledby="logo-slot-title">
                    <div>
                        <h4 id="logo-slot-title">Logo</h4>
                        <p class="admin-field__help">PNG, JPG, or WebP. Maximum 2 MB and 4096 × 4096 px.</p>
                    </div>

                    <?php if (!empty($logoUrl)): ?>
                        <div class="admin-asset-preview">
                            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Current site logo">
                        </div>
                    <?php else: ?>
                        <p class="admin-empty-state">No Logo uploaded.</p>
                    <?php endif; ?>

                    <?php if (isset($assetErrors['logo'])): ?>
                        <div class="admin-alert admin-alert--danger" role="alert">
                            <?= htmlspecialchars($assetErrors['logo'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form class="admin-form admin-asset-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($logoUploadAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="admin-field">
                            <label class="admin-field__label" for="site_logo">Upload Logo</label>
                            <input id="site_logo" name="site_asset" type="file" accept="image/png,image/jpeg,image/webp" required>
                        </div>
                        <button class="admin-button admin-button--primary" type="submit">Upload Logo</button>
                    </form>

                    <?php if (!empty($logoUrl)): ?>
                        <form method="post" action="<?= htmlspecialchars($logoRemoveAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button class="admin-button admin-button--danger" type="submit">Remove Logo</button>
                        </form>
                    <?php endif; ?>
                </article>

                <article class="admin-asset-card" aria-labelledby="favicon-slot-title">
                    <div>
                        <h4 id="favicon-slot-title">Favicon</h4>
                        <p class="admin-field__help">PNG or ICO. Maximum 512 KB and 512 × 512 px.</p>
                    </div>

                    <?php if (!empty($faviconUrl)): ?>
                        <div class="admin-asset-preview admin-asset-preview--favicon">
                            <img src="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Current site favicon">
                        </div>
                    <?php else: ?>
                        <p class="admin-empty-state">No Favicon uploaded.</p>
                    <?php endif; ?>

                    <?php if (isset($assetErrors['favicon'])): ?>
                        <div class="admin-alert admin-alert--danger" role="alert">
                            <?= htmlspecialchars($assetErrors['favicon'], ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form class="admin-form admin-asset-form" method="post" enctype="multipart/form-data" action="<?= htmlspecialchars($faviconUploadAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="admin-field">
                            <label class="admin-field__label" for="site_favicon">Upload Favicon</label>
                            <input id="site_favicon" name="site_asset" type="file" accept="image/png,image/x-icon,image/vnd.microsoft.icon,.ico" required>
                        </div>
                        <button class="admin-button admin-button--primary" type="submit">Upload Favicon</button>
                    </form>

                    <?php if (!empty($faviconUrl)): ?>
                        <form method="post" action="<?= htmlspecialchars($faviconRemoveAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button class="admin-button admin-button--danger" type="submit">Remove Favicon</button>
                        </form>
                    <?php endif; ?>
                </article>
            </div>
        </section>
    </div>
</section>
