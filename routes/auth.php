<?php

use Copot\Core\Response;

$app->router()->get('/login', function () use ($app): Response|string {
    if ($app->auth()->check()) {
        return Response::redirect($app->config()->get('auth.after_login', '/protected'));
    }

    return $app->view()->render('auth/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'csrfToken' => $app->session()->csrfToken(),
        'email' => '',
        'error' => null,
    ]);
});

$app->router()->post('/login', function ($request) use ($app): Response|string {
    $email = strtolower(trim((string) $request->input('email', '')));
    $password = (string) $request->input('password', '');
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    if ($email === '' || $password === '' || !$app->auth()->attempt($email, $password)) {
        return Response::html($app->view()->render('auth/login', [
            'appName' => $app->config()->get('app.name', 'Copot'),
            'csrfToken' => $app->session()->csrfToken(),
            'email' => $email,
            'error' => 'Invalid credentials or inactive account.',
        ]), 422);
    }

    return Response::redirect($app->config()->get('auth.after_login', '/protected'));
});

$app->router()->post('/logout', function ($request) use ($app): Response {
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->auth()->logout();

    return Response::redirect($app->config()->get('auth.after_logout', '/'));
});

$app->router()->get('/protected', function () use ($app): Response|string {
    if (!$app->auth()->check()) {
        return Response::redirect($app->config()->get('auth.login_path', '/login'));
    }

    $user = $app->auth()->user();

    if (!$user?->can('protected.access')) {
        return Response::html('403 Forbidden', 403);
    }

    return $app->view()->render('auth/protected', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'csrfToken' => $app->session()->csrfToken(),
        'user' => $user,
    ]);
});
