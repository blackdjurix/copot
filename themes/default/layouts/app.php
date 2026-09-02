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
        <script defer src="<?= htmlspecialchars($themeAsset('js/navigation.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endif; ?>
</head>
<body<?= ($branding?->cssVariables() ?? '') !== '' ? ' style="' . htmlspecialchars($branding->cssVariables(), ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
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
        <nav class="site-nav" aria-label="Primary navigation">
            <?php $renderNavigation = function (array $items, bool $nested = false) use (&$renderNavigation): void { ?>
                <ul<?= $nested ? ' class="site-nav-submenu"' : '' ?>>
                    <?php foreach ($items as $item): ?>
                        <?php $hasChildren = is_array($item['children'] ?? null) && $item['children'] !== []; $isGroup = ($item['kind'] ?? '') === 'navigation_group'; ?>
                        <li class="site-nav-item<?= $hasChildren ? ' has-children' : '' ?>">
                            <?php if ($isGroup): ?>
                                <button class="site-nav-group-toggle" type="button" aria-expanded="false"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></button>
                            <?php else: ?>
                                <a href="<?= htmlspecialchars((string) ($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
                            <?php endif; ?>
                            <?php if ($hasChildren): ?>
                                <?php $renderNavigation($item['children'], true); ?>
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
