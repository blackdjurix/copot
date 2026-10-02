<?php

// The module keeps this contribution for compatibility, but registers the
// canonical Core resolver and repository only.
return new \Copot\Core\RedirectResolver(
    new \Copot\Core\RedirectRepository($app->database()),
    $app->adminUrl()->baseUrl()
);
