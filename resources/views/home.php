<section class="builtin-site-home">
    <h1><?= htmlspecialchars($branding?->name() ?? ($title ?? 'Site'), ENT_QUOTES, 'UTF-8') ?></h1>
    <?php if (trim((string) ($branding?->tagline() ?? '')) !== ''): ?>
        <p class="builtin-site-home__tagline"><?= nl2br(htmlspecialchars($branding->tagline(), ENT_QUOTES, 'UTF-8')) ?></p>
    <?php endif; ?>
</section>
