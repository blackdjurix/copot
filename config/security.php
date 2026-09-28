<?php

use Copot\Core\Env;

return [
    'authenticated_idle_timeout_minutes' => Env::get('AUTHENTICATED_IDLE_TIMEOUT_MINUTES'),
];
