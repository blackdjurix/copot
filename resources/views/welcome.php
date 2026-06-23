<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></title>
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
            font-size: 40px;
            font-weight: 700;
        }

        p {
            margin: 0;
            color: #4b5563;
            font-size: 18px;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <main>
        <h1><?= htmlspecialchars($appName ?? 'Copot', ENT_QUOTES, 'UTF-8') ?></h1>
        <p>M1.1 Core Bootstrap is running.</p>
    </main>
</body>
</html>
