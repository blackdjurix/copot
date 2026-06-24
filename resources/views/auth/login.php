<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - <?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #f8fafc;
        }

        main {
            max-width: 420px;
            margin: 0 auto;
            padding: 72px 24px;
        }

        h1 {
            margin: 0 0 24px;
            font-size: 32px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
        }

        input {
            box-sizing: border-box;
            width: 100%;
            margin-bottom: 18px;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            background: #111827;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        .error {
            margin-bottom: 18px;
            padding: 12px;
            border-radius: 6px;
            color: #991b1b;
            background: #fee2e2;
        }
    </style>
</head>
<body>
    <main>
        <h1>Login</h1>

        <?php if (!empty($error)): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" action="/login">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?= htmlspecialchars($email ?? '', ENT_QUOTES, 'UTF-8') ?>" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" required>

            <button type="submit">Login</button>
        </form>
    </main>
</body>
</html>
