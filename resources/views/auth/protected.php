<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Protected - <?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #f8fafc;
        }

        main {
            max-width: 760px;
            margin: 0 auto;
            padding: 72px 24px;
        }

        h1 {
            margin: 0 0 12px;
            font-size: 36px;
        }

        p {
            margin: 0 0 24px;
            color: #4b5563;
            font-size: 18px;
            line-height: 1.6;
        }

        button {
            padding: 10px 14px;
            border: 0;
            border-radius: 6px;
            color: #ffffff;
            background: #111827;
            font-weight: 700;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <main>
        <h1>Protected</h1>
        <p>
            Logged in as
            <?= htmlspecialchars($user?->name() ?? 'User', ENT_QUOTES, 'UTF-8') ?>
            (<?= htmlspecialchars($user?->email() ?? '', ENT_QUOTES, 'UTF-8') ?>).
        </p>

        <form method="post" action="/logout">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Logout</button>
        </form>
    </main>
</body>
</html>
