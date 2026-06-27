<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title ?? 'Admin Shell', ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($siteName ?? 'copot', ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        body {
            box-sizing: border-box;
            margin: 0;
            font-family: Arial, sans-serif;
            color: #1f2937;
            background: #f3f4f6;
        }

        *,
        *::before,
        *::after {
            box-sizing: inherit;
        }

        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 16px 24px;
            color: #ffffff;
            background: #111827;
        }

        header h1 {
            margin: 0;
            font-size: 20px;
        }

        header p {
            margin: 4px 0 0;
            color: #d1d5db;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        nav {
            flex: 0 0 220px;
            width: 220px;
            padding: 24px;
            background: #ffffff;
            border-right: 1px solid #e5e7eb;
        }

        nav a {
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: #111827;
            text-decoration: none;
            font-weight: 700;
        }

        nav a:hover {
            background: #f3f4f6;
        }

        main {
            flex: 1;
            min-width: 0;
            padding: 32px;
        }

        button {
            padding: 9px 12px;
            border: 0;
            border-radius: 6px;
            color: #111827;
            background: #ffffff;
            font-weight: 700;
            cursor: pointer;
        }

        dl {
            display: grid;
            grid-template-columns: max-content minmax(0, 1fr);
            gap: 10px 16px;
        }

        dt {
            font-weight: 700;
        }

        dd {
            margin: 0;
            overflow-wrap: anywhere;
        }

        .shell {
            display: flex;
            min-height: calc(100vh - 73px);
        }

        .panel {
            max-width: 880px;
            padding: 24px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
        }

        @media (max-width: 720px) {
            header {
                align-items: stretch;
                flex-direction: column;
                gap: 16px;
                padding: 16px;
            }

            header form {
                width: 100%;
            }

            header button {
                width: 100%;
            }

            .shell {
                flex-direction: column;
                min-height: 0;
            }

            nav {
                width: 100%;
                flex-basis: auto;
                padding: 12px 16px;
                border-right: 0;
                border-bottom: 1px solid #e5e7eb;
            }

            nav a {
                padding: 12px;
            }

            main {
                padding: 16px;
            }

            .panel {
                max-width: none;
                padding: 18px;
            }

            dl {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></h1>
            <p>
                <?= htmlspecialchars($userName ?? 'User', ENT_QUOTES, 'UTF-8') ?>
                &lt;<?= htmlspecialchars($userEmail ?? '', ENT_QUOTES, 'UTF-8') ?>&gt;
                - <?= htmlspecialchars($adminPath ?? '/admin', ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <form method="post" action="<?= htmlspecialchars(($adminPath ?? '/admin') . '/logout', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Logout</button>
        </form>
    </header>

    <div class="shell">
        <nav aria-label="Admin navigation">
            <?php foreach (($navigation ?? []) as $item): ?>
                <a href="<?= htmlspecialchars($item['url'] ?? '#', ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($item['label'] ?? 'Navigation', ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <main>
            <?= $content ?? '' ?>
        </main>
    </div>
</body>
</html>
