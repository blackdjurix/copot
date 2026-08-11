<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copot Installer</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #f3f4f6;
        }

        main {
            width: min(100% - 32px, 640px);
            margin: 0 auto;
            padding: 64px 0;
        }

        section {
            padding: 24px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #ffffff;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        p {
            margin: 0;
            line-height: 1.6;
        }

        h2 {
            margin: 28px 0 12px;
            font-size: 20px;
        }

        .requirements,
        .steps {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .requirements li {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .pass {
            color: #166534;
        }

        .fail,
        .field-error {
            color: #991b1b;
        }

        .text-link {
            color: #1d4ed8;
        }

        .requirement-copy {
            display: grid;
            gap: 4px;
        }

        .warning {
            color: #92400e;
            font-size: 14px;
        }

        .status {
            margin-top: 20px;
            padding: 12px 14px;
            border-left: 4px solid <?= !empty($installerReady) ? '#15803d' : '#b91c1c' ?>;
            background: <?= !empty($installerReady) ? '#f0fdf4' : '#fef2f2' ?>;
        }

        form {
            display: grid;
            gap: 12px;
        }

        label {
            font-weight: 700;
        }

        input,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #9ca3af;
            border-radius: 5px;
            font: inherit;
        }

        input:focus-visible,
        select:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #2563eb;
            outline-offset: 2px;
        }

        button {
            width: fit-content;
            margin-top: 8px;
            padding: 10px 16px;
            border: 0;
            border-radius: 5px;
            color: #ffffff;
            background: #111827;
            font: inherit;
            cursor: pointer;
        }

        button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .installer-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .installer-actions .button-secondary {
            margin-top: 0;
        }

        .database-action {
            width: 156px;
        }

        .field-error {
            margin-top: -8px;
            font-size: 14px;
        }

        .steps {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin: 24px 0;
        }

        .step {
            min-width: 0;
            padding: 10px;
            border-top: 3px solid #9ca3af;
            color: #4b5563;
            font-size: 13px;
        }

        .step strong,
        .step span {
            display: block;
            overflow-wrap: anywhere;
        }

        .step span {
            margin-top: 4px;
            text-transform: uppercase;
            font-size: 11px;
        }

        .step-state,
        .visually-hidden {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .step-completed {
            border-color: #15803d;
            color: #166534;
        }

        .step-current {
            border-color: #2563eb;
            color: #1d4ed8;
            border-top-width: 5px;
            font-weight: 700;
        }

        .step-blocked {
            color: #991b1b;
        }

        .step-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }

        .mobile-requirements-review {
            display: none;
            margin: -8px 0 12px;
        }

        @media (max-width: 560px) {
            main {
                width: min(100% - 20px, 640px);
                padding: 24px 0;
            }

            section {
                padding: 18px;
            }

            .steps {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .requirements li {
                align-items: flex-start;
            }
        }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        main {
            width: min(calc(100% - 32px), 720px);
            margin: 0 auto;
            padding: 20px 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        section {
            width: 100%;
            padding: 18px;
            min-height: min(760px, calc(100vh - 40px));
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.10);
        }

        h1 { margin-bottom: 8px; }
        h2 { margin: 18px 0 8px; }
        .status { margin-top: 12px; }
        .steps { margin: 16px 0; }
        .requirements-actions { margin-top: 14px; }

        .status {
            display: grid;
            grid-template-columns: 1fr;
            gap: 4px;
            align-items: baseline;
            width: 100%;
            min-width: 0;
            border-left-width: 5px;
        }
        .status-label,
        .status-message { grid-column: 1; min-width: 0; }

        .status--success { border-left-color: #15803d; background: #f0fdf4; }
        .status--info { border-left-color: #2563eb; background: #eff6ff; }
        .status--warning { border-left-color: #a16207; background: #fefce8; }
        .status--error { border-left-color: #b91c1c; background: #fef2f2; }
        .status-label { font-weight: 700; }

        .requirements-intro { margin-top: 0; }
        .requirements-actions { margin-top: auto; display: flex; justify-content: flex-end; }
        .button-secondary { background: #fff; color: #1e293b; border: 1px solid #94a3b8; }
        .button { display: inline-block; padding: 10px 16px; border-radius: 6px; background: #1d4ed8; color: #fff; font-weight: 700; text-decoration: none; }
        .phase-form { flex: 1; align-content: start; }
        .phase-operation-actions { margin-top: auto; }
        .installer-navigation { margin-top: 12px; align-items: stretch; }
        .installer-navigation > a,
        .installer-navigation > button { flex: 0 1 48%; text-align: center; }
        .installer-navigation > button { margin-top: 0; }

        @media (max-width: 560px) {
            body { align-items: flex-start; }
            main { width: min(calc(100% - 20px), 720px); min-height: 100vh; padding: 20px 0; align-items: flex-start; }
            section { padding: 20px; }
            .status { grid-template-columns: 1fr; align-items: start; }
            .steps { grid-template-columns: 1fr; }
            .steps .step { display: none; }
            .steps .step-current {
                display: grid;
                grid-template-columns: 1fr;
                grid-template-rows: auto auto;
                gap: 4px;
            }
            .steps .step-current span { margin-top: 0; }
            .mobile-requirements-review { display: block; }
            section { min-height: 0; }
            .requirements-actions { margin-top: 20px; justify-content: flex-end; }
            .installer-navigation { flex-direction: row; align-items: stretch; }
            .installer-navigation > a, .installer-navigation > button { flex: 1 1 0; width: auto; text-align: center; }
            .phase-operation-actions > button { width: 100%; }
        }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Copot Installer</h1>
            <?php $phaseDescriptions = ['requirements' => 'Check that this server meets the requirements to run COPOT.', 'database' => 'Choose and validate the database for this installation.', 'administrator' => 'Configure the first administrator and initial site settings.', 'finalize' => 'Review the installation details and finalize COPOT.']; ?>
            <p><?= htmlspecialchars($phaseDescriptions[$currentStep ?? 'requirements'] ?? $phaseDescriptions['requirements'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($showStatus)): ?>
                <?php $statusKind = in_array(($statusKind ?? 'info'), ['success', 'info', 'warning', 'error'], true) ? $statusKind : 'info'; $statusLabel = ['success' => 'Success', 'info' => 'Information', 'warning' => 'Warning', 'error' => 'Error'][$statusKind]; ?>
                <p id="installer_status" class="status status--<?= htmlspecialchars($statusKind, ENT_QUOTES, 'UTF-8') ?>" role="<?= $statusKind === 'error' ? 'alert' : 'status' ?>" aria-live="polite"><span class="status-label"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span><span class="status-message"><?= htmlspecialchars($message ?? 'Installer is unavailable.', ENT_QUOTES, 'UTF-8') ?></span></p>
            <?php endif; ?>

            <nav aria-label="Installation progress">
                <ol class="steps">
                    <?php foreach (($steps ?? []) as $step): ?>
                        <?php $stepState = in_array(($step['state'] ?? ''), ['completed', 'current', 'pending', 'blocked'], true) ? $step['state'] : 'pending'; $stepDisplayState = in_array(($step['displayState'] ?? ''), ['completed', 'current', 'pending', 'blocked'], true) ? $step['displayState'] : $stepState; ?>
                        <li class="step step-<?= htmlspecialchars($stepDisplayState, ENT_QUOTES, 'UTF-8') ?>" <?= $stepDisplayState === 'current' ? 'aria-current="step"' : '' ?>>
                            <?php $stepIsCurrentReview = (($step['label'] ?? '') === 'Requirements' && ($currentStep ?? '') === 'requirements') || (($step['label'] ?? '') === 'Database' && ($currentStep ?? '') === 'database') || (($step['label'] ?? '') === 'Administrator & Site' && ($currentStep ?? '') === 'administrator'); ?>
                            <?php $stepIsReviewable = $stepState === 'completed' && is_string($step['reviewUrl'] ?? null) && !$stepIsCurrentReview; ?>
                            <?php if ($stepIsReviewable): ?><a class="step-link" href="<?= htmlspecialchars($step['reviewUrl'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Review completed <?= htmlspecialchars($step['label'] ?? 'installer step', ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?><strong><?= htmlspecialchars($step['label'] ?? 'Installer step', ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="step-state">State: <?= htmlspecialchars($stepState, ENT_QUOTES, 'UTF-8') ?></span><?php if ($stepIsReviewable): ?></a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php if (!empty($requirementsPassed) && !empty($requirementsAcknowledged) && ($currentStep ?? '') !== 'requirements'): ?>
                <p class="mobile-requirements-review"><a class="text-link" href="<?= htmlspecialchars($requirementsReviewUrl ?? '/install?step=requirements', ENT_QUOTES, 'UTF-8') ?>">Review completed Requirements</a></p>
            <?php endif; ?>

            <?php if (($currentStep ?? '') === 'requirements'): ?><h2>Requirements</h2>
            <ul class="requirements">
                <?php foreach (($requirements ?? []) as $requirement): ?>
                    <li>
                        <span class="requirement-copy">
                            <span><?= htmlspecialchars($requirement['label'] ?? 'Requirement', ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if (!empty($requirement['warning'])): ?>
                                <span class="warning"><?= htmlspecialchars($requirement['warning'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </span>
                        <strong class="<?= !empty($requirement['passed']) ? 'pass' : 'fail' ?>">
                            <?= !empty($requirement['passed']) ? 'PASS' : 'FAIL' ?>
                        </strong>
                    </li>
                <?php endforeach; ?>
            </ul>
                <div class="requirements-actions">
                    <?php if (!empty($requirementsPassed)): ?>
                        <a class="button" href="<?= htmlspecialchars($requirementsReview ? ($requirementsForwardUrl ?? '/install') : (is_callable($url ?? null) ? $url('/install?step=database') : '/install?step=database'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($requirementsReview ? ('Return to ' . ($requirementsForwardLabel ?? 'current step')) : 'Continue to Database', ENT_QUOTES, 'UTF-8') ?></a>
                    <?php else: ?>
                        <span class="warning" role="status">Resolve the failed requirements to continue.</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (($currentStep ?? 'database') === 'database'): ?>
                <h2>Database</h2>
                <?php if (!empty($errors['connection'])): ?>
                    <p class="field-error"><?= htmlspecialchars($errors['connection'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if (is_array($databaseResult ?? null)): ?>
                    <p class="pass">
                        <?= htmlspecialchars($databaseResult['vendor'] ?? 'Database', ENT_QUOTES, 'UTF-8') ?>
                        <?= htmlspecialchars($databaseResult['version'] ?? '', ENT_QUOTES, 'UTF-8') ?> verified.
                    </p>
                    <p>Occupancy: <?= htmlspecialchars($databaseResult['occupancy'] ?? 'unknown', ENT_QUOTES, 'UTF-8') ?>; namespace route: <?= htmlspecialchars($databaseResult['namespace'] ?? '', ENT_QUOTES, 'UTF-8') === '' ? '(empty)' : htmlspecialchars($databaseResult['namespace'], ENT_QUOTES, 'UTF-8') ?>.</p>
                    <?php if (!empty($databaseResult['warning'])): ?>
                        <p class="warning"><?= htmlspecialchars($databaseResult['warning'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php foreach (($databaseResult['warnings'] ?? []) as $warning): ?>
                        <p class="warning"><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>

                <p id="database_test_result" class="pass" hidden></p>

                <form id="database_form" class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <label for="database_host">Host</label>
                    <input id="database_host" name="database_host" type="text" maxlength="255" required value="<?= htmlspecialchars($values['host'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($errors['host'])): ?><p class="field-error"><?= htmlspecialchars($errors['host'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <label for="database_port">Port</label>
                    <input id="database_port" name="database_port" type="number" min="1" max="65535" required value="<?= htmlspecialchars($values['port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($errors['port'])): ?><p class="field-error"><?= htmlspecialchars($errors['port'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <label for="database_name">Database Name</label>
                    <input id="database_name" name="database_name" type="text" maxlength="64" required value="<?= htmlspecialchars($values['database'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($errors['database'])): ?><p class="field-error"><?= htmlspecialchars($errors['database'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <label for="database_username">Username</label>
                    <input id="database_username" name="database_username" type="text" maxlength="128" required value="<?= htmlspecialchars($values['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($errors['username'])): ?><p class="field-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <label for="database_password">Password</label>
                    <input id="database_password" name="database_password" type="password" value="">
                    <?php if (!empty($errors['password'])): ?><p class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <label for="installer_intent">Installer intent</label>
                    <select id="installer_intent" name="installer_intent" required>
                        <?php foreach ([
                            \Copot\Core\InstallerIntent::FRESH => 'Fresh installation',
                            \Copot\Core\InstallerIntent::COEXIST => 'New independent installation',
                            \Copot\Core\InstallerIntent::ADOPT => 'Adopt existing COPOT installation',
                            \Copot\Core\InstallerIntent::MIGRATE => 'Migrate/update existing COPOT installation',
                        ] as $intentValue => $intentLabel): ?>
                            <option value="<?= htmlspecialchars($intentValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($values['intent'] ?? \Copot\Core\InstallerIntent::FRESH) === $intentValue ? 'selected' : '' ?>><?= htmlspecialchars($intentLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="database_namespace">Database namespace (blank preserves the empty namespace)</label>
                    <input id="database_namespace" name="database_namespace" type="text" maxlength="31" pattern="[a-z][a-z0-9_]{0,30}" value="<?= htmlspecialchars($values['namespace'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <?php if (!empty($errors['namespace'])): ?><p class="field-error"><?= htmlspecialchars($errors['namespace'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <div class="phase-operation-actions">
                        <button id="database_action" class="database-action" type="submit" name="action" value="test_database" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Test Database</button>
                    </div>
                    <div class="actions installer-actions installer-navigation">
                        <?php if (!empty($requirementsAcknowledged)): ?><a class="button button-secondary" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=requirements') : '/install?step=requirements', ENT_QUOTES, 'UTF-8') ?>">Previous: Requirements</a><?php else: ?><span></span><?php endif; ?>
                        <button type="button" class="button-secondary" disabled>Next</button>
                    </div>
                </form>
            <?php elseif (($currentStep ?? '') === 'administrator'): ?>
                <h2>Administrator and Site</h2>
                <?php if (!empty($setupErrors['storage'])): ?>
                    <p class="field-error"><?= htmlspecialchars($setupErrors['storage'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="create_administrator">

                        <label for="admin_name">Administrator Name</label>
                        <input id="admin_name" name="admin_name" type="text" maxlength="120" required value="<?= htmlspecialchars($setupValues['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (!empty($setupErrors['admin_name'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_name'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="admin_email">Administrator Email</label>
                        <input id="admin_email" name="admin_email" type="email" maxlength="190" required value="<?= htmlspecialchars($setupValues['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (!empty($setupErrors['admin_email'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_email'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="admin_password">Password</label>
                        <input id="admin_password" name="admin_password" type="password" minlength="10" required value="">
                        <?php if (!empty($setupErrors['admin_password'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_password'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="admin_password_confirmation">Confirm Password</label>
                        <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="10" required value="">
                        <?php if (!empty($setupErrors['admin_password_confirmation'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_password_confirmation'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="site_name">Site Name</label>
                        <input id="site_name" name="site_name" type="text" maxlength="150" required value="<?= htmlspecialchars($setupValues['site_name'] ?? 'copot', ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (!empty($setupErrors['site_name'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['site_name'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="site_tagline">Site Tagline</label>
                        <input id="site_tagline" name="site_tagline" type="text" maxlength="255" value="<?= htmlspecialchars($setupValues['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php if (!empty($setupErrors['site_tagline'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['site_tagline'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="timezone">Timezone</label>
                        <select id="timezone" name="timezone" required>
                            <?php foreach (($timezones ?? ['UTC']) as $timezone): ?>
                                <option value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>" <?= ($setupValues['timezone'] ?? 'UTC') === $timezone ? 'selected' : '' ?>><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($setupErrors['timezone'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['timezone'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                        <label for="locale">Locale</label>
                        <select id="locale" name="locale" required>
                            <?php foreach (($locales ?? ['en_US', 'id_ID']) as $locale): ?>
                                <option value="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" <?= ($setupValues['locale'] ?? 'en_US') === $locale ? 'selected' : '' ?>><?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($setupErrors['locale'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['locale'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

                    <div class="phase-operation-actions">
                        <button type="submit" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Save Administrator and Settings</button>
                    </div>
                    <div class="installer-actions installer-navigation">
                        <a class="button button-secondary" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=database') : '/install?step=database', ENT_QUOTES, 'UTF-8') ?>">Previous: Database</a>
                        <button type="button" class="button-secondary" disabled>Next</button>
                    </div>
                </form>
            <?php elseif (($currentStep ?? '') === 'finalize'): ?>
                <h2>Finalize Installation</h2>
                <?php if (is_string($finalizationError ?? null) && $finalizationError !== ''): ?>
                    <p class="field-error"><?= htmlspecialchars($finalizationError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <ul>
                    <li><span>First administrator</span><strong class="pass">READY</strong></li>
                    <li><span>Default frontend theme</span><strong>default</strong></li>
                    <li><span>Baseline module</span><strong>Content</strong></li>
                    <li><span>Baseline module</span><strong>Taxonomy</strong></li>
                </ul>

                <form class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="finalize_installation">
                    <div class="installer-actions installer-navigation">
                        <a class="button button-secondary" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=administrator') : '/install?step=administrator', ENT_QUOTES, 'UTF-8') ?>">Previous: Administrator &amp; Site</a>
                        <button type="submit" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Finalize Installation</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
    <script>
        (() => {
            const form = document.getElementById('database_form');
            const button = document.getElementById('database_action');

            if (!form || !button) {
                return;
            }

            let status = document.getElementById('installer_status');
            const result = document.getElementById('database_test_result');
            const fields = [
                'database_host',
                'database_port',
                'database_name',
                'database_username',
                'database_password',
                'database_namespace',
                'installer_intent',
            ].map((name) => form.elements.namedItem(name)).filter(Boolean);
            let tested = false;

            const showStatusMessage = (message, kind = 'info') => {
                const labels = { info: 'Information', success: 'Success', warning: 'Warning', error: 'Error' };
                if (!status) {
                    status = document.createElement('p');
                    status.id = 'installer_status';
                    const phaseDescription = document.querySelector('main > section > p');
                    phaseDescription?.insertAdjacentElement('afterend', status);
                }
                status.className = `status status--${kind}`;
                status.setAttribute('role', kind === 'error' ? 'alert' : 'status');
                status.setAttribute('aria-live', 'polite');
                status.replaceChildren();
                const labelElement = document.createElement('span');
                labelElement.className = 'status-label';
                labelElement.textContent = labels[kind] || labels.info;
                const messageElement = document.createElement('span');
                messageElement.className = 'status-message';
                messageElement.textContent = message;
                status.append(labelElement, messageElement);
            };

            const resetTest = () => {
                if (!tested) {
                    return;
                }

                tested = false;
                button.value = 'test_database';
                button.textContent = 'Test Database';
                result.hidden = true;
                result.textContent = '';
                showStatusMessage('Database fields changed. Test the connection again.');
            };

            fields.forEach((field) => field.addEventListener('input', resetTest));

            form.addEventListener('submit', async (event) => {
                if (button.value === 'install_database') {
                    return;
                }

                event.preventDefault();
                button.disabled = true;
                button.textContent = 'Testing...';
                result.hidden = true;

                const data = new FormData(form);
                data.set('action', 'test_database');
                data.set('response_mode', 'json');

                try {
                    const response = await fetch(<?= json_encode(is_callable($url ?? null) ? $url('/install') : '/install', JSON_THROW_ON_ERROR) ?>, {
                        method: 'POST',
                        body: data,
                        headers: { Accept: 'application/json' },
                    });
                    const contentType = response.headers.get('content-type') || '';
                    const payload = contentType.includes('application/json')
                        ? await response.json()
                        : null;

                    if (!response.ok || !payload?.ok) {
                        tested = false;
                        button.value = 'test_database';
                        button.textContent = 'Test Database';
                        showStatusMessage(payload?.message || 'Database connection could not be verified.', 'error');
                        return;
                    }

                    tested = true;
                    button.value = 'install_database';
                    button.textContent = 'Install Database';
                    showStatusMessage(payload.message, 'success');
                    result.textContent = `${payload.database.vendor} ${payload.database.version} verified.`;
                    result.hidden = false;
                } catch (error) {
                    tested = false;
                    button.value = 'test_database';
                    button.textContent = 'Test Database';
                    showStatusMessage('Database connection could not be verified.', 'error');
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
