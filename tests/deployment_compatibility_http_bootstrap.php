<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

$source = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
if (!str_contains($source, 'use Copot\\Core\\DeploymentCompatibilityVerifier;')) {
    throw new RuntimeException('HTTP bootstrap does not import the deployment compatibility verifier.');
}
if (!class_exists(Copot\Core\DeploymentCompatibilityVerifier::class)) {
    throw new RuntimeException('Deployment compatibility verifier cannot be autoloaded.');
}

echo "HTTP bootstrap compatibility focused test passed.\n";
