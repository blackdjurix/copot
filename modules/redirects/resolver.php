<?php

require_once __DIR__ . '/Services/RedirectExceptions.php';
require_once __DIR__ . '/Services/Redirect.php';
require_once __DIR__ . '/Services/RedirectRepository.php';
require_once __DIR__ . '/Services/RedirectResolver.php';

return new RedirectResolver(
    new RedirectRepository($app->database()),
    $app->adminUrl()->baseUrl()
);
