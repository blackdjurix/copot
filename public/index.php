<?php

use Copot\Core\Diagnostics;
use Copot\Core\InstallationState;
use Copot\Core\InstallerGate;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\ServerErrorResponse;

$basePath = dirname(__DIR__);
$initialOutputLevel = ob_get_level();
$discardOutputBuffersTo = static function (int $initialLevel): void {
    while (ob_get_level() > $initialLevel) {
        $level = ob_get_level();

        if (!@ob_end_clean() || ob_get_level() >= $level) {
            break;
        }
    }
};
$sendEmergencyResponse = static function (): void {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
    }

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Server Error</title></head><body><main>'
        . '<h1>Server Error</h1><p>The request could not be completed.</p>'
        . '</main></body></html>';
};

if (!@ob_start()) {
    $sendEmergencyResponse();

    return;
}

try {
    require_once $basePath . '/bootstrap/autoload.php';
} catch (Throwable) {
    $discardOutputBuffersTo($initialOutputLevel);
    $sendEmergencyResponse();

    return;
}

$request = null;
$diagnostics = null;

try {
    $diagnostics = new Diagnostics($basePath);
    $request = Request::capture();
    $installationState = new InstallationState($basePath . '/storage');
    $gate = new InstallerGate($installationState);
    $decision = $gate->decide($request);

    if ($decision === InstallerGate::REDIRECT_TO_INSTALLER) {
        $response = Response::redirect('/install');
    } elseif ($decision === InstallerGate::BLOCK_INSTALLER) {
        $response = Response::html('404 Not Found', 404);
    } elseif ($decision === InstallerGate::INSTALLER || $decision === InstallerGate::INSTALLATION_STATE_ERROR) {
        $installationStateError = $decision === InstallerGate::INSTALLATION_STATE_ERROR;
        $response = require $basePath . '/bootstrap/installer.php';
    } else {
        $app = require $basePath . '/bootstrap/app.php';
        $response = $app->run($request);
    }

    if (!$response instanceof Response) {
        throw new RuntimeException('Application bootstrap did not return a response.');
    }

    if (ob_get_level() !== $initialOutputLevel + 1) {
        throw new RuntimeException('Application bootstrap output buffer state is invalid.');
    }

    $unexpectedOutput = @ob_get_clean();

    if (!is_string($unexpectedOutput)) {
        throw new RuntimeException('Application bootstrap output buffer could not be read.');
    }

    if ($unexpectedOutput !== '') {
        throw new RuntimeException('Application bootstrap emitted direct output.');
    }
} catch (Throwable $exception) {
    $discardOutputBuffersTo($initialOutputLevel);

    try {
        $diagnostics ??= new Diagnostics($basePath);
        $context = [
            'component' => 'runtime',
            'operation' => 'bootstrap',
        ];

        if ($request instanceof Request) {
            $context['method'] = $request->method();
            $context['path'] = $request->path();
        }

        $response = ServerErrorResponse::fromThrowable(
            $exception,
            $diagnostics,
            'runtime.bootstrap.failure',
            $context
        );
    } catch (Throwable) {
        $sendEmergencyResponse();

        return;
    }
}

$response->send();
