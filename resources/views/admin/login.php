<!doctype html>
<html lang="<?= htmlspecialchars($documentLocale, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login | <?= htmlspecialchars($siteName ?? 'copot', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/admin-assets/css/admin.css">
</head>
<body class="admin-login-body">
    <main class="admin-login-main">
        <section class="admin-login-card" aria-labelledby="admin-login-title">
            <h1 id="admin-login-title">Admin Login</h1>
            <p><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?> admin shell</p>

            <?php if (!empty($error)): ?>
                <div class="admin-alert admin-alert--danger" id="admin-login-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <form class="admin-form" method="post" action="<?= htmlspecialchars($adminBaseUrl, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

                <div class="admin-field">
                    <label class="admin-field__label" for="email">
                        Email
                        <span class="admin-field__required" aria-hidden="true">*</span>
                        <span class="admin-visually-hidden">required</span>
                    </label>
                    <input id="email" name="email" type="email" autocomplete="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="admin-field">
                    <label class="admin-field__label" for="password">
                        Password
                        <span class="admin-field__required" aria-hidden="true">*</span>
                        <span class="admin-visually-hidden">required</span>
                    </label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                </div>

                <div class="admin-actions">
                    <button class="admin-button admin-button--primary" type="submit">Login</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
