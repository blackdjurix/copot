<?php

return [
    'name' => 'COPOTSESSID',
    'lifetime' => 120,
    'path' => '/',
    'secure' => false,
    'http_only' => true,
    'same_site' => 'Lax',
    'csrf_key' => '_copot_csrf_token',
];
