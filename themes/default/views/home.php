<section class="welcome-panel">
    <p class="eyebrow">M2.3 Minimal Site Capabilities</p>
    <h1><?= htmlspecialchars($branding?->name() ?? ($title ?? 'copot'), ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (trim((string) ($branding?->tagline() ?? '')) !== ''): ?>
        <p><?= htmlspecialchars($branding->tagline(), ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
        <p>Default frontend theme rendering is active.</p>
    <?php endif; ?>
</section>
