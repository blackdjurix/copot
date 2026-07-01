<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$tests = [
    'Batch 2 Core Dispatcher' => $basePath . '/tests/extensibility_batch2_smoke.php',
    'Batch 3 Enabled-Module Listener Wiring' => $basePath . '/tests/extensibility_batch3_integration.php',
    'M2.1 Admin UI Regression' => $basePath . '/tests/admin_ui_m2_1_regression.php',
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

echo 'Copot M2.2 Extensibility regression gate' . PHP_EOL;
echo str_repeat('=', 39) . PHP_EOL;

foreach ($results as $result) {
    $status = $result['exit_code'] === 0 ? 'PASS' : 'FAIL';

    echo PHP_EOL . "[{$status}] {$result['label']}" . PHP_EOL;

    foreach ($result['output'] as $line) {
        echo "  {$line}" . PHP_EOL;
    }
}

echo PHP_EOL . str_repeat('-', 39) . PHP_EOL;

if ($failed) {
    fwrite(STDERR, 'M2.2 regression gate failed.' . PHP_EOL);
    exit(1);
}

echo 'M2.2 regression gate passed.' . PHP_EOL;
echo 'Implemented acceptance: Core dispatcher and enabled-module listener wiring.' . PHP_EOL;
echo 'GATED: Batch 4 concrete caller/listener integration remains deferred until a real use case exists.' . PHP_EOL;
echo 'M2.2 must not be marked complete while that acceptance criterion remains gated.' . PHP_EOL;
