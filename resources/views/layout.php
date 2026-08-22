<?php
$accentPalette = \Copot\Core\WebcoreBranding::builtInAccentPalette($branding?->palette()['accent'] ?? null);
$cssVariables = sprintf(
    '--builtin-accent:%s;--builtin-accent-soft:%s;--builtin-accent-strong:%s;--builtin-accent-foreground:%s',
    $accentPalette['accent'],
    $accentPalette['accent-soft'],
    $accentPalette['accent-strong'],
    $accentPalette['accent-foreground']
);
$siteName = $branding?->name() ?? 'Site';
$logoUrl = $branding?->logoUrl();
$faviconUrl = $branding?->faviconUrl();
$navigation = is_array($context['navigation']['locations']['primary'] ?? null)
    ? $context['navigation']['locations']['primary']
    : [];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? $siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (is_string($faviconUrl) && $faviconUrl !== ''): ?>
        <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <style>
        :root { <?= htmlspecialchars($cssVariables, ENT_QUOTES, 'UTF-8') ?> }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: #17202a; background: #f4f6f8; font-family: Arial, sans-serif; }
        a { color: var(--builtin-accent-strong); }
        .builtin-site-header { border-bottom: 1px solid #d9e0e7; background: #fff; }
        .builtin-site-header__inner, .builtin-site-main, .builtin-site-footer { width: min(900px, calc(100% - 32px)); margin: 0 auto; }
        .builtin-site-header__inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 20px 0; }
        .builtin-site-identity { display: inline-flex; align-items: center; gap: 14px; color: #17202a; text-decoration: none; }
        .builtin-site-identity img { display: block; width: auto; max-width: 180px; height: 48px; object-fit: contain; }
        .builtin-site-identity__name { font-size: 1.15rem; font-weight: 700; }
        .builtin-site-nav ul { display: flex; flex-wrap: wrap; gap: 14px 20px; margin: 0; padding: 0; list-style: none; }
        .builtin-site-nav a { font-size: .95rem; font-weight: 700; text-decoration: none; }
        .builtin-site-main { min-height: calc(100vh - 170px); padding: 56px 0; }
        .builtin-site-content { max-width: 720px; padding: clamp(24px, 5vw, 48px); background: #fff; border: 1px solid #d9e0e7; border-top: 4px solid var(--builtin-accent); border-radius: 12px; box-shadow: 0 12px 32px rgba(23, 32, 42, .06); }
        .builtin-site-content h1 { margin: 0 0 18px; color: #17202a; font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.08; }
        .builtin-site-content p, .builtin-site-content div { color: #3e4b59; font-size: 1.08rem; line-height: 1.7; }
        .builtin-site-content p + p { margin-top: 12px; }
        .builtin-site-content article > div { white-space: normal; }
        .builtin-site-home__tagline { margin: 0; color: #3e4b59; font-size: 1.15rem; line-height: 1.6; }
        .builtin-site-footer { padding: 22px 0 28px; border-top: 1px solid #d9e0e7; color: #596674; font-size: .9rem; }
        .builtin-site-accent { color: var(--builtin-accent-strong); }
        .builtin-site-content img { display: block; max-width: 100%; height: auto; }
        @media (max-width: 640px) {
            .builtin-site-header__inner { align-items: flex-start; flex-direction: column; padding: 16px 0; }
            .builtin-site-nav { width: 100%; }
            .builtin-site-nav ul { gap: 10px 16px; }
            .builtin-site-main { min-height: calc(100vh - 220px); padding: 28px 0; }
            .builtin-site-content { padding: 24px 20px; border-radius: 8px; }
        }
    </style>
</head>
<body>
    <header class="builtin-site-header">
        <div class="builtin-site-header__inner">
            <a class="builtin-site-identity" href="<?= htmlspecialchars(is_callable($url) ? (string) $url('/') : '/', ENT_QUOTES, 'UTF-8') ?>">
                <?php if (is_string($logoUrl) && $logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                    <span class="builtin-site-identity__name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </a>
            <?php if ($navigation !== []): ?>
                <nav class="builtin-site-nav" aria-label="Primary navigation">
                    <ul>
                        <?php foreach ($navigation as $item): ?>
                            <li><a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </header>
    <main class="builtin-site-main">
        <div class="builtin-site-content">
            <?= $content ?? '' ?>
        </div>
    </main>
    <footer class="builtin-site-footer">© <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></footer>
</body>
</html>
