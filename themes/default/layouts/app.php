<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></title>
    <?php if (isset($themeAsset) && is_callable($themeAsset)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($themeAsset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body>
    <main class="page-shell">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
