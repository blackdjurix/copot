<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? ($branding?->name() ?? 'copot'), ENT_QUOTES, 'UTF-8') ?></title>
    <?php if ($branding?->faviconUrl() !== null): ?>
        <link rel="icon" href="<?= htmlspecialchars($branding->faviconUrl(), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
    <?php if (isset($themeAsset) && is_callable($themeAsset)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($themeAsset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <?php if ($branding?->logoUrl() !== null): ?>
                <img class="site-header__logo" src="<?= htmlspecialchars($branding->logoUrl(), ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
            <div>
                <p class="site-header__name"><?= htmlspecialchars($branding?->name() ?? 'copot', ENT_QUOTES, 'UTF-8') ?></p>
                <?php if (trim((string) ($branding?->tagline() ?? '')) !== ''): ?>
                    <p class="site-header__tagline"><?= htmlspecialchars($branding->tagline(), ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="page-shell">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
