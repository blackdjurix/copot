<?php

use Copot\Core\Application;
use Copot\Core\Env;

$basePath = dirname(__DIR__);

require $basePath . '/bootstrap/autoload.php';

Env::load($basePath . '/.env');

$app = new Application($basePath);
$app->session()->start();

require $basePath . '/routes/web.php';
require $basePath . '/routes/auth.php';

return $app;
