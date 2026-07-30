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

    <?php $navigationLocations = is_array($context['navigation']['locations'] ?? null) ? $context['navigation']['locations'] : []; ?>
    <?php if ($navigationLocations !== []): ?>
        <nav aria-label="Primary navigation">
            <?php $renderNavigation = function (array $items) use (&$renderNavigation): void { ?>
                <ul>
                    <?php foreach ($items as $item): ?>
                        <li>
                            <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php if (is_array($item['children'] ?? null) && $item['children'] !== []): ?>
                                <?php $renderNavigation($item['children']); ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php }; ?>
            <?php $renderNavigation($navigationLocations['primary'] ?? []); ?>
        </nav>
    <?php endif; ?>

    <main class="page-shell">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
