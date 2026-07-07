<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$phpBinary = PHP_BINARY;

$tests = [
    'Batch 2 Minimal Diagnostics Baseline' => $basePath . '/tests/platform_hardening_batch2_smoke.php',
    'Batch 3 Application Boundary and Rendering Safety' => $basePath . '/tests/platform_hardening_batch3_smoke.php',
    'Batch 4 Admin In-Shell Errors' => $basePath . '/tests/platform_hardening_batch4_smoke.php',
    'Batch 5 Runtime and Storage Hardening' => $basePath . '/tests/platform_hardening_batch5_smoke.php',
    'M2.3 Minimal Site Capabilities Regression' => $basePath . '/tests/minimal_site_capabilities_m2_3_regression.php',
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

echo 'Copot M2.4 Platform Hardening regression gate' . PHP_EOL;
echo str_repeat('=', 46) . PHP_EOL;

foreach ($results as $result) {
    $status = $result['exit_code'] === 0 ? 'PASS' : 'FAIL';

    echo PHP_EOL . "[{$status}] {$result['label']}" . PHP_EOL;

    foreach ($result['output'] as $line) {
        echo "  {$line}" . PHP_EOL;
    }
}

echo PHP_EOL . str_repeat('-', 46) . PHP_EOL;

if ($failed) {
    fwrite(STDERR, 'M2.4 regression gate failed.' . PHP_EOL);
    exit(1);
}

echo 'M2.4 regression gate passed.' . PHP_EOL;
echo 'Implemented: diagnostics, sanitized application boundaries, eligible Admin in-shell errors, runtime/session hardening, and observable Site Asset degradation.' . PHP_EOL;
echo 'Verified: focused M2.4 Batches 2-5 plus the complete chained M2.3/M2.2/M2.1 regression coverage.' . PHP_EOL;
echo 'Deployment checks: live HTTPS Secure-cookie, production document-root isolation, and symlink-capable host semantics remain environment-specific verification responsibilities.' . PHP_EOL;
echo 'M2.4 implementation and lean M2 Platform Capabilities implementation are complete. Ready for merge and release preparation.' . PHP_EOL;
