<?php

use Copot\Core\Autoloader;

require __DIR__ . '/../app/Core/Autoloader.php';

$autoloader = new Autoloader('Copot\\Core', __DIR__ . '/../app/Core');
$autoloader->register();
