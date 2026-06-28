<?php

use Copot\Core\InstallationState;
use Copot\Core\InstallerGate;
use Copot\Core\Request;
use Copot\Core\Response;

$basePath = dirname(__DIR__);

require_once $basePath . '/bootstrap/autoload.php';

$request = Request::capture();
$installationState = new InstallationState($basePath . '/storage');
$gate = new InstallerGate($installationState);
$decision = $gate->decide($request);

if ($decision === InstallerGate::REDIRECT_TO_INSTALLER) {
    Response::redirect('/install')->send();

    return;
}

if ($decision === InstallerGate::BLOCK_INSTALLER) {
    Response::html('404 Not Found', 404)->send();

    return;
}

if ($decision === InstallerGate::INSTALLER || $decision === InstallerGate::INSTALLATION_STATE_ERROR) {
    $installationStateError = $decision === InstallerGate::INSTALLATION_STATE_ERROR;
    $response = require $basePath . '/bootstrap/installer.php';
    $response->send();

    return;
}

$app = require $basePath . '/bootstrap/app.php';

$response = $app->run($request);
$response->send();
