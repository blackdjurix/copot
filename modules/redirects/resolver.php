<?php

return new RedirectResolver(
    new RedirectRepository($app->database()),
    $app->adminUrl()->baseUrl()
);
