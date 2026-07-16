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
?>
<!doctype html>
<html lang="<?= htmlspecialchars($documentLocale ?? 'en', ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Admin Shell', ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($siteName ?? 'copot', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/admin-assets/css/admin.css">
</head>
<body>
    <a class="admin-skip-link" href="#admin-main">Skip to main content</a>

    <div class="admin-shell">
        <aside class="admin-sidebar" aria-label="Admin shell">
            <div class="admin-brand">
                <p class="admin-brand-title"><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></p>
                <p class="admin-brand-meta"><?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <nav class="admin-nav" aria-label="Admin navigation">
                <?php foreach (($navigation ?? []) as $item): ?>
                    <?php $isActive = !empty($item['active']); ?>
                    <a
                        class="admin-nav-link<?= $isActive ? ' is-active' : '' ?>"
                        href="<?= htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>"
                        <?= $isActive ? 'aria-current="page"' : '' ?>
                    >
                        <?= htmlspecialchars($item['label'] ?? 'Navigation', ENT_QUOTES, 'UTF-8') ?>
                    </a>
                <?php endforeach; ?>
            </nav>

        </aside>

        <div class="admin-main-shell">
            <header class="admin-topbar">
                <div class="admin-topbar__title">
                    <h1><?= htmlspecialchars($title ?? 'Admin Shell', ENT_QUOTES, 'UTF-8') ?></h1>
                </div>

                <details class="admin-account-menu">
                    <summary class="admin-account-menu__trigger">
                        <span class="admin-account-menu__initials" aria-hidden="true"><?= htmlspecialchars($identityInitials, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="admin-account-menu__trigger-name"><?= htmlspecialchars($identityName, ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="admin-account-menu__chevron" aria-hidden="true">▾</span>
                    </summary>
                    <div class="admin-account-menu__panel">
                        <div class="admin-account-menu__identity">
                            <span class="admin-account-menu__name"><?= htmlspecialchars($identityName, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($identityEmail !== ''): ?><span class="admin-account-menu__email"><?= htmlspecialchars($identityEmail, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                        </div>
                        <form class="admin-account-menu__logout" method="post" action="<?= htmlspecialchars($adminLogoutUrl, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <button class="admin-button admin-button--secondary" type="submit">Logout</button>
                        </form>
                    </div>
                </details>
            </header>

            <main id="admin-main" class="admin-main" tabindex="-1">
                <?= $content ?? '' ?>
            </main>
        </div>
    </div>
</body>
</html>
