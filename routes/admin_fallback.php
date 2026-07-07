<?php

$adminFallbackPath = $app->adminUrl()->childUrl('{path}');

$app->router()->get($adminFallbackPath, function ($request) use ($app) {
    return $app->adminErrors()->response($request, 404);
});

$app->router()->post($adminFallbackPath, function ($request) use ($app) {
    return $app->adminErrors()->response($request, 404);
});
