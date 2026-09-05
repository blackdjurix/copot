<?php
$colorScheme = $branding?->palette() ?? \Copot\Core\WebcoreColorScheme::defaults();
$pageTypeValue = $pageType ?? 'homepage';
$pageType = in_array($pageTypeValue, ['homepage', 'general', 'system'], true) ? $pageTypeValue : 'homepage';
$cssVariables = sprintf(
    '--builtin-main:%s;--builtin-main-soft:%s;--builtin-main-strong:%s;--builtin-main-foreground:%s',
    $colorScheme['main'] ?? '#1769e0', $colorScheme['main-soft'] ?? '#e7f0fc',
    $colorScheme['main-strong'] ?? '#1356b8', $colorScheme['main-foreground'] ?? '#ffffff'
);
$siteName = $branding?->name() ?? 'Site';
$logoUrl = $branding?->logoUrl();
$faviconUrl = $branding?->faviconUrl();
$navigation = is_array($context['navigation']['locations']['primary'] ?? null)
    ? $context['navigation']['locations']['primary']
    : [];
$currentPath = is_string($context['currentPath'] ?? null) ? (string) $context['currentPath'] : '';
$renderNavigation = function (array $items, string $prefix = 'primary') use (&$renderNavigation, $currentPath): void {
    foreach ($items as $index => $item) {
        $label = htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
        $rawUrl = (string) ($item['url'] ?? '');
        $url = htmlspecialchars($rawUrl, ENT_QUOTES, 'UTF-8');
        $children = is_array($item['children'] ?? null) ? $item['children'] : [];
        $id = htmlspecialchars($prefix . '-' . $index, ENT_QUOTES, 'UTF-8');
        $kind = (string) ($item['kind'] ?? 'link');
        $itemPath = parse_url($rawUrl, PHP_URL_PATH);
        $isActive = $kind !== 'navigation_group' && is_string($itemPath) && $currentPath !== '' && rtrim($itemPath, '/') === rtrim($currentPath, '/');
        $activeClass = $isActive ? ' is-active' : '';
        $activeAttribute = $isActive ? ' aria-current="page"' : '';
        echo '<li class="builtin-site-nav__item' . ($children !== [] ? ' has-children' : '') . $activeClass . '">';
        if ($kind === 'navigation_group') {
            echo '<button class="builtin-site-nav__trigger" type="button" aria-expanded="false" aria-controls="' . $id . '">' . $label . '<span aria-hidden="true">⌄</span></button>';
        } else {
            echo '<a class="builtin-site-nav__link' . $activeClass . '" href="' . $url . '"' . $activeAttribute . '>' . $label . '</a>';
        }
        if ($children !== []) { echo '<ul id="' . $id . '" class="builtin-site-nav__submenu">'; $renderNavigation($children, $prefix . '-' . $index); echo '</ul>'; }
        echo '</li>';
    }
};
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
         a { color: var(--builtin-main-strong); }
        .builtin-site-header { background: #fff; }
        .builtin-site-header__inner, .builtin-site-main, .builtin-site-footer { width: min(900px, calc(100% - 32px)); margin: 0 auto; }
        .builtin-site-header__inner { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 20px 0; }
        .builtin-site-header__identity-row { display: contents; }
        .builtin-site-identity { display: inline-flex; align-items: center; gap: 14px; color: #17202a; text-decoration: none; }
        .builtin-site-identity img { display: block; width: auto; max-width: 180px; height: 48px; object-fit: contain; }
        .builtin-site-identity__name { font-size: 1.15rem; font-weight: 700; }
        .builtin-site-nav { width: auto; }
        .builtin-site-nav__link { position: relative; }
        .builtin-site-nav__link.is-active::after { position: absolute; right: 0; bottom: -8px; left: 0; height: 2px; background: var(--builtin-main); content: ''; }
        .builtin-site-header__bar { width: 100%; height: 4px; background: var(--builtin-main); }
        .builtin-site-main { min-height: calc(100vh - 170px); padding: 0 0 56px; }
        .builtin-site-content { width: min(900px, 100%); max-width: 900px; margin: 56px auto 0; padding: clamp(24px, 5vw, 48px); background: #fff; border: 1px solid #d9e0e7; border-radius: 12px; box-shadow: 0 12px 32px rgba(23, 32, 42, .06); }
        .builtin-site-main--general .builtin-site-content { margin-top: 0; border-radius: 0 0 12px 12px; }
        .builtin-site-main--system .builtin-site-content { margin-top: 56px; }
         .builtin-site-hero { width: 100%; max-width: 900px; margin: 0 auto 28px; overflow: hidden; border-radius: 0 0 12px 12px; }
         .builtin-site-hero img { display: block; width: 100%; aspect-ratio: 16 / 7; object-fit: cover; }
        .builtin-site-content h1 { margin: 0 0 18px; color: #17202a; font-size: clamp(1.75rem, 4vw, 2rem); line-height: 1.08; }
        .builtin-site-content h1.builtin-site-content__title--detail { margin-bottom: 0; }
        .builtin-site-breadcrumb { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 24px; color: #596674; font-size: .9rem; line-height: 1.4; }
        .builtin-site-breadcrumb a { color: var(--builtin-main-strong); }
        .builtin-site-page__intro { font-size: 1.2rem !important; }
        .builtin-site-page__meta { margin: 0 0 0; color: #596674 !important; font-size: .9rem !important; }
        .builtin-site-page__meta + .builtin-site-featured-image { margin-top: 16px; }
        .builtin-site-featured-image { display: block; width: 100%; height: auto; aspect-ratio: 16 / 9; object-fit: cover; margin: 0 0 32px; border-radius: 8px; }
        .builtin-site-content p, .builtin-site-content div { color: #3e4b59; font-size: 1.08rem; line-height: 1.7; }
        .builtin-site-content p + p { margin-top: 12px; }
        .builtin-site-content article > div { white-space: normal; }
        .builtin-site-home__tagline { margin: 0; color: #3e4b59; font-size: 1.15rem; line-height: 1.6; }
        .builtin-site-footer { padding: 22px 0 28px; border-top: 1px solid #d9e0e7; color: #596674; font-size: .9rem; }
         .builtin-site-accent { color: var(--builtin-main-strong); }
        .builtin-site-content img { display: block; max-width: 100%; height: auto; }
        .builtin-site-article-collection__items { display: grid; gap: 28px; }
        .builtin-site-article-item { display: grid; gap: 24px; }
        .builtin-site-article-item.has-image { grid-template-columns: minmax(0, 2.5fr) minmax(0, 6fr); align-items: end; }
        .builtin-site-article-item__image { display: block; aspect-ratio: 4 / 3; overflow: hidden; }
        .builtin-site-article-item__image img { width: 100%; height: 100%; object-fit: cover; }
        .builtin-site-article-item__body h2 { margin: 0; }
        .builtin-site-article-item__meta { margin: 0 0 8px; color: #596674 !important; font-size: .9rem !important; }
        .builtin-site-article-item__body p { margin-top: 0; }
        .builtin-site-article-item__excerpt { margin: 0; font-style: italic; }
        .builtin-site-page__intro--article { font-style: italic; }
        @media (max-width: 640px) {
            .builtin-site-header__inner { display: block; padding: 16px 0; }
            .builtin-site-header__identity-row { position: relative; display: flex; align-items: center; justify-content: center; min-height: 48px; }
            .builtin-site-nav { width: 100%; }
            .builtin-site-main { min-height: calc(100vh - 220px); padding: 0 0 28px; }
            .builtin-site-content { width: 100%; padding: 24px 20px; border-radius: 8px; }
            .builtin-site-hero { width: calc(100% + 32px); margin-left: -16px; border-radius: 0; }
            .builtin-site-hero img { aspect-ratio: 3 / 4; }
            .builtin-site-featured-image { aspect-ratio: 16 / 9; border-radius: 6px; }
            .builtin-site-main--general .builtin-site-content { margin-top: 0; border-radius: 0 0 8px 8px; }
            .builtin-site-article-item.has-image { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="builtin-site-header">
        <div class="builtin-site-header__inner">
            <div class="builtin-site-header__identity-row">
            <a class="builtin-site-identity" href="<?= htmlspecialchars(is_callable($url) ? (string) $url('/') : '/', ENT_QUOTES, 'UTF-8') ?>">
                <?php if (is_string($logoUrl) && $logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                    <span class="builtin-site-identity__name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </a>
            <?php if ($navigation !== []): ?><button class="builtin-site-nav__toggle" type="button" aria-expanded="false" aria-controls="builtin-site-primary-nav" aria-label="Open menu"><span aria-hidden="true">☰</span></button><?php endif; ?>
            </div>
            <?php if ($navigation !== []): ?>
                <nav id="builtin-site-primary-nav" class="builtin-site-nav" aria-label="Primary navigation">
                    <ul><?php $renderNavigation($navigation); ?></ul>
                </nav>
                <link rel="stylesheet" href="/assets/navigation.css?v=wu3">
                <script defer src="/assets/navigation.js?v=wu3"></script>
            <?php endif; ?>
        </div>
        <div class="builtin-site-header__bar" aria-hidden="true"></div>
    </header>
    <main class="builtin-site-main builtin-site-main--<?= htmlspecialchars($pageType, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($pageType === 'homepage' && ($homepageHero ?? null) instanceof \Copot\Core\Media): ?><div class="builtin-site-hero"><img src="<?= htmlspecialchars(is_callable($url) ? (string) $url('/media/' . $homepageHero->id()->value()) : '/media/' . $homepageHero->id()->value(), ENT_QUOTES, 'UTF-8') ?>" alt="Homepage hero image"></div><?php endif; ?>
        <?php if ($pageType !== 'homepage' || trim((string) ($content ?? '')) !== ''): ?>
            <div class="builtin-site-content builtin-site-content--<?= htmlspecialchars($pageType, ENT_QUOTES, 'UTF-8') ?>">
                <?= $content ?? '' ?>
            </div>
        <?php endif; ?>
    </main>
    <footer class="builtin-site-footer">© <?= date('Y') ?> <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></footer>
</body>
</html>
