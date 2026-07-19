<?php
$rawUserName = $userName ?? null;
$identityName = is_scalar($rawUserName) ? trim((string) $rawUserName) : '';
$identityName = $identityName !== '' ? $identityName : 'User';
$rawUserEmail = $userEmail ?? null;
$identityEmail = is_scalar($rawUserEmail) ? trim((string) $rawUserEmail) : '';
$identityParts = preg_split('/\s+/', $identityName) ?: [];
$identityInitials = count($identityParts) > 1
    ? strtoupper(substr((string) $identityParts[0], 0, 1) . substr((string) end($identityParts), 0, 1))
    : strtoupper(substr($identityName, 0, 2));
$pageTitle = is_scalar($title ?? null) ? trim((string) $title) : '';
$pageTitle = $pageTitle !== '' ? $pageTitle : 'Admin Shell';
$navigationItems = is_array($navigation ?? null) ? $navigation : [];
$icon = is_callable($renderAdminIcon ?? null)
    ? $renderAdminIcon
    : static fn (?string $key, string $class = 'admin-icon'): string => '';
$normalizePath = static function (mixed $path): string {
    $path = is_scalar($path) ? parse_url((string) $path, PHP_URL_PATH) : null;

    return is_string($path) && trim($path) !== '' ? '/' . trim($path, '/') : '';
};
$normalizedCurrentPath = $normalizePath($currentPath ?? '');
$normalizedAdminBase = $normalizePath($adminBaseUrl ?? '');
$isAdminRoot = $normalizedCurrentPath !== '' && $normalizedCurrentPath === $normalizedAdminBase;
?>
<!doctype html>
<html lang="<?= htmlspecialchars($documentLocale ?? 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($siteName ?? 'copot', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/admin-assets/css/admin.css">
    <script defer src="/admin-assets/js/admin-shell.js"></script>
</head>
<body class="admin-shell-page">
    <a class="admin-skip-link" href="#admin-main">Skip to main content</a>

    <div class="admin-shell" data-admin-shell>
        <aside class="admin-sidebar"
            id="admin-sidebar"
            aria-label="Admin navigation panel"
            tabindex="-1"
            data-admin-sidebar
        >
            <button
                class="admin-sidebar-close"
                type="button"
                aria-label="Close admin navigation"
                data-admin-nav-close
            >
                <?= $icon('close', 'admin-shell-control-icon') ?>
            </button>

            <div class="admin-brand">
                <a class="admin-brand-link" href="<?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="admin-brand-icon" aria-hidden="true">
                        <?= $icon('modules', 'admin-brand-icon__svg') ?>
                    </span>
                    <span class="admin-brand-copy">
                        <span class="admin-brand-title"><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="admin-brand-meta"><?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?></span>
                    </span>
                </a>
            </div>

            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach ($navigationItems as $item): ?>
                    <?php $isActive = !empty($item['active']); ?>
                    <a
                        class="admin-nav-link<?= $isActive ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <span class="admin-nav-icon" aria-hidden="true">
                            <?= $icon($item['icon'] ?? 'module', 'admin-nav-icon__svg') ?>
                        </span>
                        <span class="admin-nav-label"><?= htmlspecialchars($item['label'] ?? 'Navigation', ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>

        <button
            class="admin-mobile-overlay"
            type="button"
            aria-label="Close admin navigation"
            data-admin-nav-overlay
            hidden
            tabindex="-1"
        ></button>

        <div class="admin-main-shell" data-admin-main-shell>
            <header class="admin-topbar">
                <button
                    class="admin-mobile-menu"
                    type="button"
                    aria-label="Open admin navigation"
                    aria-controls="admin-sidebar"
                    aria-expanded="false"
                    data-admin-nav-open
                >
                    <?= $icon('menu', 'admin-shell-control-icon') ?>
                </button>

                <nav class="admin-breadcrumb" aria-label="Breadcrumb">
                    <ol>
                        <?php if (!$isAdminRoot): ?>
                            <li><a href="<?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?>">Dashboard</a></li>
                            <li class="admin-breadcrumb__separator" aria-hidden="true">›</li>
                        <?php endif; ?>
                        <li aria-current="page"><h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1></li>
                    </ol>
                </nav>

                <div class="admin-topbar__actions">
                    <?php if ($navigationItems !== []): ?>
                        <details class="admin-quick-menu" data-admin-popover>
                            <summary class="admin-quick-menu__trigger">
                                <?= $icon('quick-menu', 'admin-quick-menu__icon') ?>
                                <span class="admin-quick-menu__label">Quick menu</span>
                                <?= $icon('chevron-down', 'admin-quick-menu__chevron') ?>
                            </summary>
                            <nav class="admin-quick-menu__panel" aria-label="Quick menu">
                                <?php foreach ($navigationItems as $item): ?>
                                    <?php $isActive = !empty($item['active']); ?>
                                    <a
                                        href="<?= htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>"
                                        <?= $isActive ? 'aria-current="page"' : '' ?>
                                    >
                                        <span class="admin-quick-menu__item-icon" aria-hidden="true">
                                            <?= $icon($item['icon'] ?? 'module', 'admin-quick-menu__item-icon-svg') ?>
                                        </span>
                                        <span><?= htmlspecialchars($item['label'] ?? 'Navigation', ENT_QUOTES, 'UTF-8') ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </nav>
                        </details>
                    <?php endif; ?>

                    <details class="admin-account-menu" data-admin-popover>
                        <summary class="admin-account-menu__trigger">
                            <span class="admin-account-menu__initials" aria-hidden="true"><?= htmlspecialchars($identityInitials, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="admin-account-menu__trigger-name"><?= htmlspecialchars($identityName, ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="admin-account-menu__chevron" aria-hidden="true">
                                <?= $icon('chevron-down', 'admin-account-menu__chevron-icon') ?>
                            </span>
                        </summary>
                        <div class="admin-account-menu__panel">
                            <div class="admin-account-menu__identity">
                                <span class="admin-account-menu__name"><?= htmlspecialchars($identityName, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php if ($identityEmail !== ''): ?><span class="admin-account-menu__email"><?= htmlspecialchars($identityEmail, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                            <form class="admin-account-menu__logout" method="post" action="<?= htmlspecialchars($adminLogoutUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <button class="admin-button admin-button--secondary" type="submit">
                                    <?= $icon('external-link', 'admin-button__icon') ?>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            <main id="admin-main" class="admin-main" tabindex="-1">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
</body>
</html>
