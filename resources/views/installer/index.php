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

        .step-completed {
            border-color: #15803d;
            color: #166534;
        }

        .step-current {
            border-color: #2563eb;
            color: #1d4ed8;
        }

        .step-blocked {
            color: #991b1b;
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
    </style>
</head>
<body>
    <main>
        <section>
            <h1>Copot Installer</h1>
            <p>Check the server, test a dedicated empty database, then save its configuration and install the canonical schema.</p>
            <p id="installer_status" class="status"><?= htmlspecialchars($message ?? 'Installer is unavailable.', ENT_QUOTES, 'UTF-8') ?></p>

            <nav aria-label="Installation progress">
                <ol class="steps">
                    <?php foreach (($steps ?? []) as $step): ?>
                        <?php $stepState = in_array(($step['state'] ?? ''), ['completed', 'current', 'pending', 'blocked'], true) ? $step['state'] : 'pending'; ?>
                        <li class="step step-<?= htmlspecialchars($stepState, ENT_QUOTES, 'UTF-8') ?>" <?= $stepState === 'current' ? 'aria-current="step"' : '' ?>>
                            <strong><?= htmlspecialchars($step['label'] ?? 'Installer step', ENT_QUOTES, 'UTF-8') ?></strong>
                            <span><?= htmlspecialchars($stepState, ENT_QUOTES, 'UTF-8') ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <h2>Requirements</h2>
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
                    <?php if (!empty($databaseResult['warning'])): ?>
                        <p class="warning"><?= htmlspecialchars($databaseResult['warning'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                <?php endif; ?>

                <p id="database_test_result" class="pass" hidden></p>

                <form id="database_form" method="post" action="/install" autocomplete="off">
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

                    <div class="actions">
                        <button id="database_action" class="database-action" type="submit" name="action" value="test_database" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Test Database</button>
                    </div>
                </form>
            <?php elseif (($currentStep ?? '') === 'administrator'): ?>
                <h2>Administrator and Site</h2>
                <p><a class="text-link" href="/install?step=database">Change Database</a></p>

                <?php if (!empty($setupErrors['storage'])): ?>
                    <p class="field-error"><?= htmlspecialchars($setupErrors['storage'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form method="post" action="/install" autocomplete="off">
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

                    <button type="submit" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Save Administrator and Settings</button>
                </form>
            <?php else: ?>
                <h2>Finalize Installation</h2>
                <p><a class="text-link" href="/install?step=database">Change Database</a></p>

                <?php if (is_string($finalizationError ?? null) && $finalizationError !== ''): ?>
                    <p class="field-error"><?= htmlspecialchars($finalizationError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <ul>
                    <li><span>First administrator</span><strong class="pass">READY</strong></li>
                    <li><span>Default frontend theme</span><strong>default</strong></li>
                    <li><span>Baseline module</span><strong>Content</strong></li>
                    <li><span>Baseline module</span><strong>Taxonomy</strong></li>
                </ul>

                <form method="post" action="/install">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="finalize_installation">
                    <button type="submit" <?= empty($requirementsPassed) ? 'disabled' : '' ?>>Finalize Installation</button>
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

            const status = document.getElementById('installer_status');
            const result = document.getElementById('database_test_result');
            const fields = [
                'database_host',
                'database_port',
                'database_name',
                'database_username',
                'database_password',
            ].map((name) => form.elements.namedItem(name)).filter(Boolean);
            let tested = false;

            const resetTest = () => {
                if (!tested) {
                    return;
                }

                tested = false;
                button.value = 'test_database';
                button.textContent = 'Test Database';
                result.hidden = true;
                result.textContent = '';
                status.textContent = 'Database fields changed. Test the connection again.';
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
                    const response = await fetch('/install', {
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
                        status.textContent = payload?.message || 'Database connection could not be verified.';
                        return;
                    }

                    tested = true;
                    button.value = 'install_database';
                    button.textContent = 'Install Database';
                    status.textContent = payload.message;
                    result.textContent = `${payload.database.vendor} ${payload.database.version} verified.`;
                    result.hidden = false;
                } catch (error) {
                    tested = false;
                    button.value = 'test_database';
                    button.textContent = 'Test Database';
                    status.textContent = 'Database connection could not be verified.';
                } finally {
                    button.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
