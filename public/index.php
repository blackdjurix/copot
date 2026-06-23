<?php

use Copot\Core\Request;

$app = require __DIR__ . '/../bootstrap/app.php';

$response = $app->run(Request::capture());
$response->send();
