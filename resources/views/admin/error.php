<section class="admin-panel" aria-labelledby="admin-error-heading">
    <div class="admin-alert admin-alert--error" role="alert">
        <h2 id="admin-error-heading"><?= htmlspecialchars($heading ?? 'Request failed', ENT_QUOTES, 'UTF-8') ?></h2>
        <p><?= htmlspecialchars($message ?? 'The request could not be completed.', ENT_QUOTES, 'UTF-8') ?></p>

        <?php if (is_string($reference ?? null) && preg_match('/^ERR-[A-F0-9]{24}$/', $reference) === 1): ?>
            <p>Error reference: <code><?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?></code></p>
        <?php endif; ?>
    </div>
</section>
