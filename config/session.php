<?php

use Copot\Core\Env;

return [
    'name' => 'COPOTSESSID',
    'lifetime' => 120,
    'path' => '/',
    'secure' => Env::get('SESSION_SECURE', false) === true,
    'http_only' => true,
    'same_site' => 'Lax',
    'csrf_key' => '_copot_csrf_token',
];
