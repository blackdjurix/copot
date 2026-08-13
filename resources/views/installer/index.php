<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copot Installer</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/installer-assets/css/installer.css') : '/installer-assets/css/installer.css', ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
    <main>
        <section>
            <h1>Copot Installer</h1>
            <?php $phaseDescriptions = ['requirements' => 'Check that this server meets the requirements to run COPOT.', 'database' => 'Choose and validate the database for this installation.', 'administrator' => 'Configure the first administrator and initial site settings.', 'finalize' => 'Review the staged installation details before installing COPOT.', 'result' => 'Review the result of the COPOT installation operation.']; ?>
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
                            <?php $stepIsCurrentReview = (($step['label'] ?? '') === 'Requirements' && ($currentStep ?? '') === 'requirements') || (($step['label'] ?? '') === 'Database' && ($currentStep ?? '') === 'database') || (($step['label'] ?? '') === 'Administrator & Site' && ($currentStep ?? '') === 'administrator') || (($step['label'] ?? '') === 'Review & Install' && ($currentStep ?? '') === 'finalize') || (($step['label'] ?? '') === 'Installation Result' && ($currentStep ?? '') === 'result'); ?>
                            <?php $stepIsReviewable = $stepState === 'completed' && is_string($step['reviewUrl'] ?? null) && !$stepIsCurrentReview; ?>
                            <?php if ($stepIsReviewable): ?><a class="step-link" href="<?= htmlspecialchars($step['reviewUrl'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Review completed <?= htmlspecialchars($step['label'] ?? 'installer step', ENT_QUOTES, 'UTF-8') ?>">
                            <?php endif; ?><strong><?= htmlspecialchars($step['label'] ?? 'Installer step', ENT_QUOTES, 'UTF-8') ?></strong>
                            <span class="step-state">State: <?= htmlspecialchars($stepState, ENT_QUOTES, 'UTF-8') ?></span><?php if ($stepIsReviewable): ?></a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php if (($currentStep ?? '') === 'database'): ?>
                <?php $activeDatabaseFeedback = is_array($databaseFeedback ?? null) ? $databaseFeedback : null; $databaseFeedbackKind = in_array(($activeDatabaseFeedback['kind'] ?? ''), ['success', 'info', 'warning', 'error'], true) ? $activeDatabaseFeedback['kind'] : 'info'; $databaseFeedbackLabel = ['success' => 'Success', 'info' => 'Information', 'warning' => 'Warning', 'error' => 'Error'][$databaseFeedbackKind]; ?>
                <p id="database_feedback" class="status status--<?= $databaseFeedbackKind ?>" role="<?= $databaseFeedbackKind === 'error' ? 'alert' : 'status' ?>" aria-live="polite" <?= $activeDatabaseFeedback === null ? 'hidden' : '' ?>><span class="status-label"><?= $activeDatabaseFeedback === null ? '' : htmlspecialchars($databaseFeedbackLabel, ENT_QUOTES, 'UTF-8') ?></span><span class="status-message"><?= $activeDatabaseFeedback === null ? '' : htmlspecialchars((string) ($activeDatabaseFeedback['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></p>
            <?php endif; ?>
            <?php if (($currentStep ?? '') === 'requirements'): ?>
            <div class="installer-phase">
            <div class="installer-phase-header"><h2>Requirements</h2></div>
            <div class="installer-phase-content" tabindex="0">
            <ul class="requirements installer-list">
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
            </div>
                <div class="installer-footer installer-actions">
                    <span class="installer-footer__start" aria-hidden="true"></span>
                    <span class="installer-footer__center" aria-hidden="true"></span>
                    <?php if (!empty($requirementsPassed)): ?>
                        <span class="installer-footer__end"><a class="button button-secondary nav-button" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=database') : '/install?step=database', ENT_QUOTES, 'UTF-8') ?>">Next</a></span>
                    <?php else: ?>
                        <span class="installer-footer__end warning" role="status">Resolve the failed requirements to continue.</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (($currentStep ?? 'database') === 'database'): ?>
                <div class="installer-phase">
                <div class="installer-phase-header">
                <h2>Database</h2>
                <?php if (is_array($databaseResult ?? null)): ?>
                    <?php if (!empty($databaseResult['warning'])): ?><p class="warning"><?= htmlspecialchars($databaseResult['warning'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    <?php foreach (($databaseResult['warnings'] ?? []) as $warning): ?><p class="warning"><?= htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') ?></p><?php endforeach; ?>
                <?php endif; ?>
                </div>
                <div class="installer-phase-content" tabindex="0">
                <form id="database_form" class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-fields">
                        <div class="form-inline-fields">
                            <div class="form-inline-field">
                            <label for="database_host">Host</label>
                            <div class="form-control">
                                <input id="database_host" name="database_host" type="text" maxlength="255" required value="<?= htmlspecialchars($values['host'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['host'])): ?><p class="field-error"><?= htmlspecialchars($errors['host'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            </div>
                            </div>
                            <div class="form-inline-field">
                            <label for="database_port">Port</label>
                            <div class="form-control">
                                <input id="database_port" name="database_port" type="number" min="1" max="65535" required value="<?= htmlspecialchars($values['port'] ?? '3306', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['port'])): ?><p class="field-error"><?= htmlspecialchars($errors['port'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label for="database_name">Database name</label>
                            <div class="form-control">
                                <input id="database_name" name="database_name" type="text" maxlength="64" required value="<?= htmlspecialchars($values['database'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['database'])): ?><p class="field-error"><?= htmlspecialchars($errors['database'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-inline-fields">
                            <div class="form-inline-field">
                                <label for="database_username">Username</label>
                                <div class="form-control">
                                    <input id="database_username" name="database_username" type="text" maxlength="128" required value="<?= htmlspecialchars($values['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($errors['username'])): ?><p class="field-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                            <div class="form-inline-field">
                                <label for="database_password">Password</label>
                                <div class="form-control">
                                    <input id="database_password" name="database_password" type="password" value="">
                                    <?php if (!empty($errors['password'])): ?><p class="field-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <label for="installer_intent">Installer intent</label>
                            <div class="form-control">
                        <?php
                        $intentLabels = [
                            \Copot\Core\InstallerIntent::FRESH => 'Fresh installation',
                            \Copot\Core\InstallerIntent::COEXIST => 'New independent installation',
                            \Copot\Core\InstallerIntent::ADOPT => 'Adopt existing COPOT installation',
                            \Copot\Core\InstallerIntent::MIGRATE => 'Migrate/update existing COPOT installation',
                        ];
                        $eligibleIntents = is_array($databaseResult ?? null)
                            ? array_values(array_filter($databaseResult['eligible_intents'] ?? [], static fn ($intent): bool => is_string($intent) && array_key_exists($intent, $intentLabels)))
                            : [];
                        ?>
                                <select id="installer_intent" name="installer_intent"<?= $eligibleIntents === [] ? '' : ' required' ?>>
                        <?php
                        if ($eligibleIntents === []): ?>
                            <option value="" selected disabled>Test Database to determine eligible installation paths.</option>
                        <?php else: foreach ($eligibleIntents as $intentValue): $intentLabel = $intentLabels[$intentValue]; ?>
                            <option value="<?= htmlspecialchars($intentValue, ENT_QUOTES, 'UTF-8') ?>" <?= ($values['intent'] ?? \Copot\Core\InstallerIntent::FRESH) === $intentValue ? 'selected' : '' ?>><?= htmlspecialchars($intentLabel, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-action-row">
                            <label for="database_namespace">DB Namespace</label>
                            <div class="form-control">
                                <input id="database_namespace" name="database_namespace" type="text" maxlength="31" pattern="[a-z][a-z0-9_]{0,30}" value="<?= htmlspecialchars($values['namespace'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($errors['namespace'])): ?><p class="field-error"><?= htmlspecialchars($errors['namespace'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                <p class="form-help">Blank preserves the empty namespace.</p>
                            </div>
                            <button id="database_action" class="database-action" type="submit" name="action" value="test_database" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Test Database</button>
                        </div>
                    </div>
                </form>
                </div>
                    <div class="installer-footer installer-actions">
                        <span class="installer-footer__start"><?php if (!empty($requirementsAcknowledged)): ?><a class="button button-secondary nav-button" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=requirements') : '/install?step=requirements', ENT_QUOTES, 'UTF-8') ?>">Previous</a><?php endif; ?></span>
                        <span class="installer-footer__center" aria-hidden="true"></span>
                        <span class="installer-footer__end"><button class="nav-button" type="submit" form="database_form" name="action" value="stage_database" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Next</button></span>
                    </div>
                </div>
            <?php elseif (($currentStep ?? '') === 'administrator'): ?>
                <?php if (empty($databaseStaged)): ?>
                <div class="installer-phase">
                <div class="installer-phase-header"><h2>Administrator and Site</h2></div>
                <div class="installer-phase-content" tabindex="0">
                <p>Stage the Database decision before configuring Administrator &amp; Site.</p>
                </div>
                <div class="installer-footer installer-actions">
                    <span class="installer-footer__start"><a class="button button-secondary nav-button" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=database') : '/install?step=database', ENT_QUOTES, 'UTF-8') ?>">Previous</a></span>
                    <span class="installer-footer__center" aria-hidden="true"></span>
                    <span class="installer-footer__end"><button class="nav-button" type="button" disabled>Next</button></span>
                </div>
                </div>
                <?php else: ?>
                <div class="installer-phase">
                <div class="installer-phase-header">
                <h2>Administrator and Site</h2>
                <?php if (!empty($setupErrors['storage'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['storage'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                </div>
                <div class="installer-phase-content" tabindex="0">
                <form id="administrator_form" class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>" autocomplete="off">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="stage_administrator">

                    <div class="form-fields">
                        <div class="form-inline-fields">
                            <div class="form-inline-field">
                                <label for="admin_name">Username</label>
                                <div class="form-control">
                                    <input id="admin_name" name="admin_name" type="text" maxlength="120" required value="<?= htmlspecialchars($setupValues['admin_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($setupErrors['admin_name'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_name'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                            <div class="form-inline-field">
                                <label for="admin_email">Email</label>
                                <div class="form-control">
                                    <input id="admin_email" name="admin_email" type="email" maxlength="190" required value="<?= htmlspecialchars($setupValues['admin_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    <?php if (!empty($setupErrors['admin_email'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_email'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-inline-fields">
                            <div class="form-inline-field">
                                <label for="admin_password">Password</label>
                                <div class="form-control">
                                    <input id="admin_password" name="admin_password" type="password" minlength="10" <?= empty($administratorStaged) ? 'required' : '' ?> value="">
                                    <?php if (!empty($setupErrors['admin_password'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_password'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                            <div class="form-inline-field">
                                <label for="admin_password_confirmation">Confirm Password</label>
                                <div class="form-control">
                                    <input id="admin_password_confirmation" name="admin_password_confirmation" type="password" minlength="10" <?= empty($administratorStaged) ? 'required' : '' ?> value="">
                                    <?php if (!empty($setupErrors['admin_password_confirmation'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['admin_password_confirmation'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <label for="site_name">Site Name</label>
                            <div class="form-control">
                                <input id="site_name" name="site_name" type="text" maxlength="150" required value="<?= htmlspecialchars($setupValues['site_name'] ?? 'copot', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($setupErrors['site_name'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['site_name'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <label for="site_tagline">Site Tagline</label>
                            <div class="form-control">
                                <input id="site_tagline" name="site_tagline" type="text" maxlength="255" value="<?= htmlspecialchars($setupValues['site_tagline'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <?php if (!empty($setupErrors['site_tagline'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['site_tagline'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                            </div>
                        </div>

                        <div class="form-inline-fields">
                            <div class="form-inline-field">
                                <label for="timezone">Time Zone</label>
                                <div class="form-control">
                                    <select id="timezone" name="timezone" required>
                                        <?php foreach (($timezones ?? ['UTC']) as $timezone): ?>
                                            <option value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?>" <?= ($setupValues['timezone'] ?? 'UTC') === $timezone ? 'selected' : '' ?>><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($setupErrors['timezone'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['timezone'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                            <div class="form-inline-field">
                                <label for="locale">Locale</label>
                                <div class="form-control">
                                    <select id="locale" name="locale" required>
                                        <?php foreach (($locales ?? ['en_US', 'id_ID']) as $locale): ?>
                                            <option value="<?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?>" <?= ($setupValues['locale'] ?? 'en_US') === $locale ? 'selected' : '' ?>><?= htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (!empty($setupErrors['locale'])): ?><p class="field-error"><?= htmlspecialchars($setupErrors['locale'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                </div>
                <div class="installer-footer installer-actions">
                    <span class="installer-footer__start"><a class="button button-secondary nav-button" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=database') : '/install?step=database', ENT_QUOTES, 'UTF-8') ?>">Previous</a></span>
                    <span class="installer-footer__center" aria-hidden="true"></span>
                    <span class="installer-footer__end"><button class="nav-button" type="submit" form="administrator_form" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Next</button></span>
                </div>
                </div>
                <?php endif; ?>
            <?php elseif (($currentStep ?? '') === 'finalize'): ?>
                <div class="installer-phase">
                <div class="installer-phase-header"><h2>Review &amp; Install</h2></div>
                <div class="installer-phase-content" tabindex="0">
                <?php if (is_string($finalizationError ?? null) && $finalizationError !== ''): ?>
                    <p class="field-error"><?= htmlspecialchars($finalizationError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php $reviewIntentLabels = ['fresh_installation' => 'Fresh installation', 'coexistence' => 'New independent installation', 'adopt_existing_installation' => 'Adopt existing installation', 'migrate_existing_installation' => 'Migrate existing installation']; ?>
                <ul class="installer-summary installer-list">
                    <li><span>Database</span><strong><?= htmlspecialchars((string) ($values['database'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>DB Namespace</span><strong><?= htmlspecialchars((string) (($values['namespace'] ?? '') !== '' ? $values['namespace'] : 'empty'), ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>Installation intent</span><strong><?= htmlspecialchars($reviewIntentLabels[$values['intent'] ?? ''] ?? 'Selected database plan', ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>First administrator</span><strong><?= htmlspecialchars((string) ($setupValues['admin_name'] ?? 'Ready'), ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>Administrator email</span><strong><?= htmlspecialchars((string) ($setupValues['admin_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>Site</span><strong><?= htmlspecialchars((string) ($setupValues['site_name'] ?? 'Ready'), ENT_QUOTES, 'UTF-8') ?></strong></li>
                    <li><span>Default frontend theme</span><strong>default</strong></li>
                    <li><span>Baseline modules</span><strong>Core platform set</strong></li>
                </ul>

                <form id="review_install_form" class="phase-form" method="post" action="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install') : '/install', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="finalize_installation">
                </form>
                </div>
                <div class="installer-footer installer-actions">
                    <span class="installer-footer__start"><a class="button button-secondary nav-button" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/install?step=administrator') : '/install?step=administrator', ENT_QUOTES, 'UTF-8') ?>">Previous</a></span>
                    <span class="installer-footer__center" aria-hidden="true"></span>
                    <span class="installer-footer__end"><button class="nav-button install-action" type="submit" form="review_install_form" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Install</button></span>
                </div>
                </div>
            <?php elseif (($currentStep ?? '') === 'result'): ?>
                <div class="installer-phase">
                <div class="installer-phase-header"><h2>Installation Result</h2></div>
                <div class="installer-phase-content" tabindex="0">
                <?php $resultStatus = (($installationResult['status'] ?? '') === 'success') ? 'success' : 'error'; ?>
                <p class="status status--<?= $resultStatus ?>" role="<?= $resultStatus === 'error' ? 'alert' : 'status' ?>" aria-live="polite"><span class="status-label"><?= $resultStatus === 'success' ? 'Success' : 'Error' ?></span><span class="status-message"><?= htmlspecialchars((string) ($installationResult['message'] ?? 'Installation did not complete.'), ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php if ($resultStatus === 'success'): ?>
                    <ul class="installer-summary installer-list">
                        <li><span>Installed version</span><strong><?= htmlspecialchars((string) ($installationResult['details']['finalization']['version'] ?? 'current'), ENT_QUOTES, 'UTF-8') ?></strong></li>
                        <li><span>Default theme</span><strong><?= htmlspecialchars((string) ($installationResult['details']['finalization']['theme'] ?? 'default'), ENT_QUOTES, 'UTF-8') ?></strong></li>
                        <li><span>Administrator</span><strong>Created</strong></li>
                    </ul>
                <?php endif; ?>
                </div>
                <?php if ($resultStatus === 'success'): ?>
                <div class="installer-footer installer-actions">
                    <span class="installer-footer__start" aria-hidden="true"></span>
                    <span class="installer-footer__center" aria-hidden="true"></span>
                    <span class="installer-footer__end"><a class="nav-button install-action" href="<?= htmlspecialchars(is_callable($url ?? null) ? $url('/admin') : '/admin', ENT_QUOTES, 'UTF-8') ?>">Continue to Admin</a></span>
                </div>
                <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        (() => {
            const adminPassword = document.getElementById('admin_password');
            const adminPasswordConfirmation = document.getElementById('admin_password_confirmation');

            if (adminPassword && adminPasswordConfirmation) {
                const updatePasswordConfirmationValidity = () => {
                    const mismatch = adminPasswordConfirmation.value !== ''
                        && adminPassword.value !== adminPasswordConfirmation.value;
                    adminPasswordConfirmation.setCustomValidity(mismatch ? 'Passwords must match.' : '');
                };

                adminPassword.addEventListener('input', updatePasswordConfirmationValidity);
                adminPasswordConfirmation.addEventListener('input', updatePasswordConfirmationValidity);
                updatePasswordConfirmationValidity();
            }

            const form = document.getElementById('database_form');
            const button = document.getElementById('database_action');

            if (!form || !button) {
                return;
            }

            const status = document.getElementById('database_feedback');
            const intentSelect = document.getElementById('installer_intent');
            const intentLabels = {
                fresh_installation: 'Fresh installation',
                coexistence: 'New independent installation',
                adopt_existing_installation: 'Adopt existing COPOT installation',
                migrate_existing_installation: 'Migrate/update existing COPOT installation',
            };
            const fields = [
                'database_host',
                'database_port',
                'database_name',
                'database_username',
                'database_password',
                'database_namespace',
                'installer_intent',
            ].map((name) => form.elements.namedItem(name)).filter(Boolean);
            let tested = <?= !empty($databaseStaged) ? 'true' : 'false' ?>;

            const renderEligibleIntents = (eligibleIntents) => {
                if (!intentSelect) {
                    return;
                }

                const options = Array.isArray(eligibleIntents)
                    ? eligibleIntents.filter((intent) => typeof intent === 'string' && intentLabels[intent])
                    : [];
                intentSelect.replaceChildren();
                if (options.length === 0) {
                    const placeholder = new Option('Test Database to determine eligible installation paths.', '', true, true);
                    placeholder.disabled = true;
                    intentSelect.append(placeholder);
                    intentSelect.required = false;
                    return;
                }

                options.forEach((intent) => intentSelect.append(new Option(intentLabels[intent], intent)));
                intentSelect.value = options[0];
                intentSelect.required = true;
            };

            const showStatusMessage = (message, kind = 'info') => {
                const labels = { info: 'Information', success: 'Success', warning: 'Warning', error: 'Error' };
                status.className = `status status--${kind}`;
                status.setAttribute('role', kind === 'error' ? 'alert' : 'status');
                status.setAttribute('aria-live', 'polite');
                status.hidden = false;
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
                showStatusMessage('Database fields changed. Test the connection again.');
            };

            fields.forEach((field) => field.addEventListener('input', resetTest));

            form.addEventListener('submit', async (event) => {
                if (event.submitter?.value !== 'test_database') {
                    return;
                }

                event.preventDefault();
                button.disabled = true;
                button.value = 'test_database';
                button.textContent = 'Testing...';

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
                        renderEligibleIntents([]);
                        button.value = 'test_database';
                        button.textContent = 'Test Database';
                        const errors = payload?.errors || {};
                        showStatusMessage(errors.connection || errors.namespace || payload?.message || 'Database connection could not be verified.', 'error');
                        return;
                    }

                    tested = true;
                    renderEligibleIntents(payload.database?.eligible_intents || []);
                    button.value = 'test_database';
                    button.textContent = 'Test Database';
                    showStatusMessage(payload.message, 'success');
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
