<section class="panel settings-panel">
    <p>Manage global site and localization settings.</p>

    <?php if (!empty($saved)): ?>
        <div class="settings-message settings-success" role="status">Settings saved successfully.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="settings-message settings-error" role="alert">
            <strong>Settings could not be saved.</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

        <fieldset>
            <legend>General</legend>

            <label for="site_name">Site Name</label>
            <input
                id="site_name"
                name="site_name"
                type="text"
                maxlength="150"
                value="<?= htmlspecialchars($values['site_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <label for="site_tagline">Site Tagline</label>
            <input
                id="site_tagline"
                name="site_tagline"
                type="text"
                maxlength="255"
                value="<?= htmlspecialchars($values['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >
        </fieldset>

        <fieldset>
            <legend>Localization</legend>

            <label for="localization_timezone">Timezone</label>
            <select id="localization_timezone" name="localization_timezone">
                <?php foreach (($timezones ?? []) as $timezone): ?>
                    <option
                        value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (($values['localization_timezone'] ?? 'UTC') === $timezone) ? 'selected' : '' ?>
                    ><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>

            <label for="localization_locale">Locale</label>
            <select id="localization_locale" name="localization_locale">
                <?php foreach (($locales ?? []) as $locale): ?>
                    <option
                        value="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (($values['localization_locale'] ?? 'en_US') === $locale) ? 'selected' : '' ?>
                    ><?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>

            <label for="localization_date_format">Date Format</label>
            <select id="localization_date_format" name="localization_date_format">
                <?php foreach (($dateFormats ?? []) as $dateFormat): ?>
                    <option
                        value="<?= htmlspecialchars($dateFormat, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (($values['localization_date_format'] ?? 'Y-m-d') === $dateFormat) ? 'selected' : '' ?>
                    ><?= htmlspecialchars($dateFormat, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>

            <label for="localization_time_format">Time Format</label>
            <select id="localization_time_format" name="localization_time_format">
                <?php foreach (($timeFormats ?? []) as $timeFormat): ?>
                    <option
                        value="<?= htmlspecialchars($timeFormat, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (($values['localization_time_format'] ?? 'H:i') === $timeFormat) ? 'selected' : '' ?>
                    ><?= htmlspecialchars($timeFormat, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </fieldset>

        <button class="settings-submit" type="submit">Save Settings</button>
    </form>
</section>

<style>
    .settings-panel {
        width: 100%;
    }

    .settings-panel fieldset {
        display: grid;
        gap: 10px;
        margin: 24px 0;
        padding: 20px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
    }

    .settings-panel legend,
    .settings-panel label {
        font-weight: 700;
    }

    .settings-panel input,
    .settings-panel select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #9ca3af;
        border-radius: 6px;
        background: #ffffff;
        font: inherit;
    }

    .settings-message {
        margin: 18px 0;
        padding: 12px 14px;
        border-radius: 6px;
    }

    .settings-message ul {
        margin-bottom: 0;
    }

    .settings-success {
        color: #166534;
        background: #dcfce7;
    }

    .settings-error {
        color: #991b1b;
        background: #fee2e2;
    }

    .settings-submit {
        color: #ffffff;
        background: #111827;
    }

    @media (max-width: 720px) {
        .settings-panel fieldset {
            padding: 16px;
        }
    }
</style>
