<?php

$app->router()->get('/example', function () {
    ob_start();

    require __DIR__ . '/views/index.php';

    return (string) ob_get_clean();
});
