<?php

use Copot\Core\Env;

$environment = strtolower(trim((string) Env::get('APP_ENV', 'local')));
$lifetime = $environment === 'local' ? 43200 : 120;

return [
    'name' => 'COPOTSESSID',
    'lifetime' => $lifetime,
    'path' => '/',
    'secure' => Env::get('SESSION_SECURE', false) === true,
    'http_only' => true,
    'same_site' => 'Lax',
    'csrf_key' => '_copot_csrf_token',
];
