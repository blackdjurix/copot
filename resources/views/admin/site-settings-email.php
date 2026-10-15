<?php
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$emailValues = is_array($emailValues ?? null) ? $emailValues : [];
$transport = is_array($emailValues['transport'] ?? null) ? $emailValues['transport'] : [];
$sender = is_array($emailValues['sender'] ?? null) ? $emailValues['sender'] : [];
$credential = is_array($emailValues['credential'] ?? null) ? $emailValues['credential'] : [];
$feedback = is_array($emailFeedback ?? null) ? $emailFeedback : null;
$feedbackCode = (string) ($feedback['code'] ?? '');
$feedbackMessages = [
    'configuration_updated' => 'Email configuration saved successfully.',
    'credential_updated' => 'SMTP credential replaced successfully.',
    'test_delivery_succeeded' => 'Test email sent successfully.',
    'configuration_unavailable' => 'Email configuration is temporarily unavailable.',
    'invalid_configuration' => 'Enter valid SMTP and sender values.',
    'configuration_update_failed' => 'Email configuration could not be saved.',
    'credential_unavailable' => 'A configured SMTP credential is required for test delivery.',
    'credential_update_failed' => 'SMTP credential could not be replaced.',
    'reauthentication_required' => 'Recent password verification is required before replacing the SMTP credential.',
    'invalid_recipient' => 'Enter a valid test recipient email address.',
    'test_delivery_failed' => 'Test email delivery failed. The failure was not persisted as configuration.',
];
$feedbackMessage = $feedbackMessages[$feedbackCode] ?? null;
$feedbackIsSuccess = ($feedback['type'] ?? 'error') === 'success';
$feedbackId = 'site-settings-email-feedback';
?>
<div class="site-settings-capability-projection site-settings-email">
    <header class="site-settings-capability-header">
        <h3>Email</h3>
        <p>Manage the canonical Webcore SMTP sender and controlled test delivery. Email access requires <code>email.manage</code> and does not grant generic Site Settings mutation.</p>
    </header>
    <?php if ($feedbackMessage !== null): ?><div id="<?= $feedbackId ?>" class="admin-alert admin-alert--<?= $feedbackIsSuccess ? 'success' : 'danger' ?>" role="<?= $feedbackIsSuccess ? 'status' : 'alert' ?>" tabindex="-1"><?= $escape($feedbackMessage) ?></div><?php endif; ?>

    <section class="site-settings-email__section" aria-labelledby="site-settings-email-server-title">
        <h4 id="site-settings-email-server-title">SMTP Server</h4>
        <form method="post" action="<?= $escape($emailPath ?? '') ?>" class="admin-form" data-settings-dirty-form<?= in_array($feedbackCode, ['invalid_configuration', 'configuration_update_failed'], true) ? ' aria-describedby="' . $feedbackId . '"' : '' ?>>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Connection</legend>
                <div class="admin-settings-field-grid">
                    <div class="admin-field"><label class="admin-field__label" for="email_smtp_host">Host</label><input id="email_smtp_host" name="email_smtp_host" type="text" value="<?= $escape($transport['host'] ?? '') ?>" autocomplete="url" required></div>
                    <div class="admin-field"><label class="admin-field__label" for="email_smtp_port">Port</label><input id="email_smtp_port" name="email_smtp_port" type="number" min="1" max="65535" value="<?= $escape($transport['port'] ?? '') ?>" inputmode="numeric" required></div>
                    <div class="admin-field"><label class="admin-field__label" for="email_smtp_security">Security</label><select id="email_smtp_security" name="email_smtp_security" required aria-describedby="email-smtp-security-help"><?php foreach (['none' => 'None', 'ssl' => 'SSL', 'tls' => 'TLS'] as $mode => $label): ?><option value="<?= $escape($mode) ?>"<?= ($transport['security'] ?? '') === $mode ? ' selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select><p class="admin-field__help" id="email-smtp-security-help">Choose the security mode supported by the SMTP server.</p></div>
                    <div class="admin-field"><label class="admin-field__label" for="email_smtp_username">Username</label><input id="email_smtp_username" name="email_smtp_username" type="text" value="<?= $escape($transport['username'] ?? '') ?>" autocomplete="username"><p class="admin-field__help">Leave blank when the server does not require a username.</p></div>
                </div>
            </fieldset>
            <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Save Email Configuration</button></div>
        </form>
    </section>

    <section class="site-settings-email__section" aria-labelledby="site-settings-email-sender-title">
        <h4 id="site-settings-email-sender-title">Sender Identity</h4>
        <form method="post" action="<?= $escape($emailPath ?? '') ?>" class="admin-form" data-settings-dirty-form<?= in_array($feedbackCode, ['invalid_configuration', 'configuration_update_failed'], true) ? ' aria-describedby="' . $feedbackId . '"' : '' ?>>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Outbound sender</legend>
                <div class="admin-settings-field-grid">
                    <div class="admin-field"><label class="admin-field__label" for="email_sender_name">Sender name</label><input id="email_sender_name" name="email_sender_name" type="text" value="<?= $escape($sender['name'] ?? '') ?>" autocomplete="organization" required></div>
                    <div class="admin-field"><label class="admin-field__label" for="email_sender_email">Sender email</label><input id="email_sender_email" name="email_sender_email" type="email" value="<?= $escape($sender['email'] ?? '') ?>" autocomplete="email" required></div>
                </div>
            </fieldset>
            <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--primary" type="submit">Save Sender Identity</button></div>
        </form>
    </section>

    <section class="site-settings-email__section" aria-labelledby="site-settings-email-credential-title">
        <h4 id="site-settings-email-credential-title">SMTP Credential</h4>
        <form method="post" action="<?= $escape($emailCredentialPath ?? '') ?>" class="admin-form" data-settings-dirty-form<?= in_array($feedbackCode, ['reauthentication_required', 'credential_update_failed'], true) ? ' aria-describedby="' . $feedbackId . '"' : '' ?>>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Credential state</legend>
                <p class="site-settings-email__state" role="status">Current credential: <strong><?= !empty($credential['configured']) ? 'Configured' : 'Not configured' ?></strong></p>
                <div class="admin-field"><label class="admin-field__label" for="email_credential">Replacement secret</label><input id="email_credential" name="email_credential" type="password" autocomplete="new-password" required aria-describedby="email-credential-help"><p class="admin-field__help" id="email-credential-help">The existing secret is never displayed. Recent password verification is required when the canonical re-authentication proof has expired.</p></div>
            </fieldset>
            <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--secondary" type="submit">Replace SMTP Credential</button></div>
        </form>
    </section>

    <section class="site-settings-email__section" aria-labelledby="site-settings-email-test-title">
        <h4 id="site-settings-email-test-title">Test Email</h4>
        <form method="post" action="<?= $escape($emailTestPath ?? '') ?>" class="admin-form" data-settings-dirty-form<?= in_array($feedbackCode, ['invalid_recipient', 'credential_unavailable', 'test_delivery_failed'], true) ? ' aria-describedby="' . $feedbackId . '"' : '' ?>>
            <input type="hidden" name="_token" value="<?= $escape($csrfToken ?? '') ?>">
            <fieldset class="admin-fieldset"><legend class="admin-fieldset__legend">Controlled delivery</legend>
                <div class="admin-field"><label class="admin-field__label" for="email_test_recipient">Recipient</label><input id="email_test_recipient" name="email_test_recipient" type="email" autocomplete="email" required aria-describedby="email-test-help"><p class="admin-field__help" id="email-test-help">Sends one controlled message through the canonical transport. Delivery failures do not change Email configuration.</p></div>
            </fieldset>
            <div class="admin-actions admin-form__actions"><button class="admin-button admin-button--secondary" type="submit">Send Test Email</button></div>
        </form>
    </section>
</div>
