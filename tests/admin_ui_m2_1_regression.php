<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$tests = [
    'Batch 1 smoke' => $basePath . '/tests/admin_ui_batch1_smoke.php',
    'Batch 1 integration' => $basePath . '/tests/admin_ui_batch1_integration.php',
    'Batch 2 smoke' => $basePath . '/tests/admin_ui_batch2_smoke.php',
    'Batch 3 smoke' => $basePath . '/tests/admin_ui_batch3_smoke.php',
    'Batch 4 smoke' => $basePath . '/tests/admin_ui_batch4_smoke.php',
    'Batch 5 smoke' => $basePath . '/tests/admin_ui_batch5_smoke.php',
];

$results = [];
$failed = false;

foreach ($tests as $label => $testFile) {
    if (!is_file($testFile)) {
        fwrite(STDERR, "FAIL: {$label} file is missing [{$testFile}]." . PHP_EOL);
        $failed = true;
        continue;
    }

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($testFile);
    $output = [];
    $exitCode = 0;

    exec($command . ' 2>&1', $output, $exitCode);

    $results[] = [
        'label' => $label,
        'exit_code' => $exitCode,
        'output' => $output,
    ];

    if ($exitCode !== 0) {
        $failed = true;
    }
}

echo "Copot M2.1 Admin UI regression gate" . PHP_EOL;
echo str_repeat('=', 36) . PHP_EOL;

foreach ($results as $result) {
    $status = $result['exit_code'] === 0 ? 'PASS' : 'FAIL';

    echo PHP_EOL . "[{$status}] {$result['label']}" . PHP_EOL;

    foreach ($result['output'] as $line) {
        echo "  {$line}" . PHP_EOL;
    }
}

echo PHP_EOL . str_repeat('-', 36) . PHP_EOL;

if ($failed) {
    fwrite(STDERR, "M2.1 regression gate failed." . PHP_EOL);
    exit(1);
}

echo "M2.1 regression gate passed." . PHP_EOL;
echo "Manual browser verification remains required for responsive layout, 200% zoom, keyboard flow, focus visibility, and live static-asset delivery." . PHP_EOL;
