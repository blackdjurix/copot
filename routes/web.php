<?php

$app->router()->get('/', function () use ($app): string {
    return $app->view()->render('welcome', [
        'appName' => $app->config()->get('app.name', 'Copot'),
    ]);
});
