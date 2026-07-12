<section class="admin-panel" aria-describedby="settings-description">
    <header class="admin-panel__header">
        <div class="admin-panel__heading">
            <p class="admin-panel__description" id="settings-description">Manage global site and localization settings.</p>
        </div>
    </header>

    <div class="admin-panel__body">
        <?php if (!empty($saved)): ?>
            <div class="admin-alert admin-alert--success" role="status">Settings saved successfully.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="admin-alert admin-alert--danger" role="alert" aria-labelledby="settings-error-title">
                <strong class="admin-alert__title" id="settings-error-title">Settings could not be saved.</strong>
                <ul class="admin-alert__list">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="admin-form" method="post" action="<?= htmlspecialchars($formAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <fieldset class="admin-fieldset">
                <legend class="admin-fieldset__legend">General</legend>

                <div class="admin-field">
                    <label class="admin-field__label" for="site_name">
                        Site Name
                        <span class="admin-field__required" aria-hidden="true">*</span>
                        <span class="admin-visually-hidden">required</span>
                    </label>
                    <input
                        id="site_name"
                        name="site_name"
                        type="text"
                        maxlength="150"
                        value="<?= htmlspecialchars($values['site_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="site_name-help<?= isset($errors['site_name']) ? ' site_name-error' : '' ?>"
                        <?= isset($errors['site_name']) ? 'aria-invalid="true"' : '' ?>
                        required
                    >
                    <p class="admin-field__help" id="site_name-help">Used as the public site name.</p>
                    <?php if (isset($errors['site_name'])): ?>
                        <p class="admin-field__error" id="site_name-error"><?= htmlspecialchars($errors['site_name'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label class="admin-field__label" for="site_tagline">Site Tagline</label>
                    <input
                        id="site_tagline"
                        name="site_tagline"
                        type="text"
                        maxlength="255"
                        value="<?= htmlspecialchars($values['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        aria-describedby="site_tagline-help<?= isset($errors['site_tagline']) ? ' site_tagline-error' : '' ?>"
                        <?= isset($errors['site_tagline']) ? 'aria-invalid="true"' : '' ?>
                    >
                    <p class="admin-field__help" id="site_tagline-help">Optional short description of the site.</p>
                    <?php if (isset($errors['site_tagline'])): ?>
                        <p class="admin-field__error" id="site_tagline-error"><?= htmlspecialchars($errors['site_tagline'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <fieldset class="admin-fieldset">
                <legend class="admin-fieldset__legend">Localization</legend>

                <div class="admin-field">
                    <label class="admin-field__label" for="localization_timezone">Timezone</label>
                    <select
                        id="localization_timezone"
                        name="localization_timezone"
                        aria-describedby="localization_timezone-help<?= isset($errors['localization_timezone']) ? ' localization_timezone-error' : '' ?>"
                        <?= isset($errors['localization_timezone']) ? 'aria-invalid="true"' : '' ?>
                    >
                        <?php foreach (($timezones ?? []) as $timezone): ?>
                            <option
                                value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($values['localization_timezone'] ?? 'UTC') === $timezone) ? 'selected' : '' ?>
                            ><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="admin-field__help" id="localization_timezone-help">Controls the default application timezone.</p>
                    <?php if (isset($errors['localization_timezone'])): ?>
                        <p class="admin-field__error" id="localization_timezone-error"><?= htmlspecialchars($errors['localization_timezone'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label class="admin-field__label" for="localization_locale">Locale</label>
                    <select
                        id="localization_locale"
                        name="localization_locale"
                        aria-describedby="localization_locale-help<?= isset($errors['localization_locale']) ? ' localization_locale-error' : '' ?>"
                        <?= isset($errors['localization_locale']) ? 'aria-invalid="true"' : '' ?>
                    >
                        <?php foreach (($locales ?? []) as $locale): ?>
                            <option
                                value="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($values['localization_locale'] ?? 'en_US') === $locale) ? 'selected' : '' ?>
                            ><?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="admin-field__help" id="localization_locale-help">Controls the configured runtime locale.</p>
                    <?php if (isset($errors['localization_locale'])): ?>
                        <p class="admin-field__error" id="localization_locale-error"><?= htmlspecialchars($errors['localization_locale'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label class="admin-field__label" for="localization_date_format">Date Format</label>
                    <select
                        id="localization_date_format"
                        name="localization_date_format"
                        aria-describedby="localization_date_format-help<?= isset($errors['localization_date_format']) ? ' localization_date_format-error' : '' ?>"
                        <?= isset($errors['localization_date_format']) ? 'aria-invalid="true"' : '' ?>
                    >
                        <?php foreach (($dateFormats ?? []) as $dateFormat): ?>
                            <option
                                value="<?= htmlspecialchars($dateFormat, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($values['localization_date_format'] ?? 'Y-m-d') === $dateFormat) ? 'selected' : '' ?>
                            ><?= htmlspecialchars($dateFormat, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="admin-field__help" id="localization_date_format-help">Controls the configured default date display format.</p>
                    <?php if (isset($errors['localization_date_format'])): ?>
                        <p class="admin-field__error" id="localization_date_format-error"><?= htmlspecialchars($errors['localization_date_format'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-field">
                    <label class="admin-field__label" for="localization_time_format">Time Format</label>
                    <select
                        id="localization_time_format"
                        name="localization_time_format"
                        aria-describedby="localization_time_format-help<?= isset($errors['localization_time_format']) ? ' localization_time_format-error' : '' ?>"
                        <?= isset($errors['localization_time_format']) ? 'aria-invalid="true"' : '' ?>
                    >
                        <?php foreach (($timeFormats ?? []) as $timeFormat): ?>
                            <option
                                value="<?= htmlspecialchars($timeFormat, ENT_QUOTES, 'UTF-8') ?>"
                                <?= (($values['localization_time_format'] ?? 'H:i') === $timeFormat) ? 'selected' : '' ?>
                            ><?= htmlspecialchars($timeFormat, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="admin-field__help" id="localization_time_format-help">Controls the configured default time display format.</p>
                    <?php if (isset($errors['localization_time_format'])): ?>
                        <p class="admin-field__error" id="localization_time_format-error"><?= htmlspecialchars($errors['localization_time_format'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </div>
            </fieldset>

            <div class="admin-actions admin-form__actions">
                <button class="admin-button admin-button--primary" type="submit">Save Settings</button>
            </div>
        </form>

        <section class="admin-settings-section" aria-labelledby="site-branding-title">
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
