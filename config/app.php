<?php

use Copot\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'Copot'),
    'env' => Env::get('APP_ENV', 'local'),
    'debug' => Env::get('APP_DEBUG', false),
    'url' => Env::get('APP_URL', 'http://localhost'),
];
