<?php

$app->router()->get('/example', function () {
    ob_start();

    require __DIR__ . '/Views/index.php';

    return (string) ob_get_clean();
});
