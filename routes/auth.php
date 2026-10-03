<?php

use Copot\Core\Response;

$app->router()->get('/login', function () use ($app): Response|string {
    if ($app->auth()->check()) {
        return Response::redirect($app->url((string) $app->config()->get('auth.after_login', '/protected')));
    }

    return $app->view()->render('auth/login', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'csrfToken' => $app->session()->csrfToken(),
        'url' => fn (string $path): string => $app->url($path),
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
            'url' => fn (string $path): string => $app->url($path),
            'email' => $email,
            'error' => 'Invalid credentials or inactive account.',
        ]), 422);
    }

    return Response::redirect($app->url((string) $app->config()->get('auth.after_login', '/protected')));
});

$app->router()->post('/logout', function ($request) use ($app): Response {
    $token = $request->input('_token');

    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $app->auth()->logout();

    return Response::redirect($app->url((string) $app->config()->get('auth.after_logout', '/')));
});

$app->router()->post('/account/password', function ($request) use ($app): Response {
    if (!$app->auth()->check()) {
        return Response::redirect($app->url((string) $app->config()->get('auth.login_path', '/login')));
    }

    $token = $request->input('_token');
    if (!$app->session()->validateCsrf(is_string($token) ? $token : null)) {
        return Response::html('Invalid CSRF token.', 419);
    }

    $result = $app->currentUserPassword()->change(
        (string) $request->post('password', ''),
        (string) $request->post('password_confirmation', '')
    );

    if ($result->succeeded()) {
        return Response::html('Password updated.');
    }

    return Response::html($result->code(), $result->code() === 'reauthentication_required' ? 403 : 422);
});

$selfSessionJson = static function (array $payload, int $status = 200): Response {
    return Response::content(
        json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        $status,
        ['Content-Type' => 'application/json; charset=UTF-8']
    );
};
$selfSessionContext = static function () use ($app): ?array {
    if (!$app->auth()->check()) {
        return null;
    }

    $user = $app->auth()->user();
    $identity = $app->auth()->durableSessionIdentity();

    return $user instanceof \Copot\Core\User && is_string($identity) && $identity !== ''
        ? ['user' => $user, 'identity' => $identity]
        : null;
};
$selfSessionRecord = static function (\Copot\Core\AuthenticatedSessionRecord $session, bool $current): array {
    return [
        'identity' => $session->identity(),
        'current' => $current,
        'device_descriptor' => $session->deviceDescriptor(),
        'created_at' => $session->createdAt()->format(DATE_ATOM),
        'last_active_at' => $session->lastActiveAt()->format(DATE_ATOM),
        'expires_at' => $session->expiresAt()->format(DATE_ATOM),
        'revoked_at' => $session->revokedAt()?->format(DATE_ATOM),
        'revocation_reason' => $session->revocationReason(),
    ];
};

$app->router()->get('/account/sessions', function () use ($app, $selfSessionContext, $selfSessionJson, $selfSessionRecord): Response {
    $context = $selfSessionContext();
    if ($context === null) {
        return $selfSessionJson(['error' => 'unauthenticated'], 401);
    }

    $sessions = array_map(
        static fn (array $entry): array => $selfSessionRecord($entry['session'], (bool) $entry['current']),
        $app->selfSessions()->listOwn($context['user']->id(), $context['identity'])
    );

    return $selfSessionJson(['sessions' => $sessions]);
});

$app->router()->post('/account/sessions/revoke-others', function ($request) use ($app, $selfSessionContext, $selfSessionJson): Response {
    $context = $selfSessionContext();
    if ($context === null) {
        return $selfSessionJson(['error' => 'unauthenticated'], 401);
    }
    if (!$app->session()->validateCsrf(is_string($request->input('_token')) ? $request->input('_token') : null)) {
        return $selfSessionJson(['error' => 'invalid_csrf'], 419);
    }

    try {
        $revoked = $app->selfSessions()->revokeOtherSessions($context['user']->id(), $context['identity']);

        return $selfSessionJson(['revoked_count' => $revoked]);
    } catch (\Copot\Core\ReauthenticationRequiredException) {
        return $selfSessionJson(['error' => 'reauthentication_required'], 403);
    }
});

$app->router()->post('/account/sessions/{identity}/revoke', function ($request, array $params) use ($app, $selfSessionContext, $selfSessionJson): Response {
    $context = $selfSessionContext();
    if ($context === null) {
        return $selfSessionJson(['error' => 'unauthenticated'], 401);
    }
    if (!$app->session()->validateCsrf(is_string($request->input('_token')) ? $request->input('_token') : null)) {
        return $selfSessionJson(['error' => 'invalid_csrf'], 419);
    }

    $identity = (string) ($params['identity'] ?? '');
    if ($identity === $context['identity']) {
        return $selfSessionJson(['error' => 'use_logout'], 409);
    }

    try {
        if (!$app->selfSessions()->revokeOwn($context['user']->id(), $identity)) {
            return $selfSessionJson(['error' => 'session_unavailable'], 404);
        }

        return $selfSessionJson(['revoked' => true]);
    } catch (\Copot\Core\ReauthenticationRequiredException) {
        return $selfSessionJson(['error' => 'reauthentication_required'], 403);
    }
});

$app->router()->get('/protected', function () use ($app): Response|string {
    if (!$app->auth()->check()) {
        return Response::redirect($app->url((string) $app->config()->get('auth.login_path', '/login')));
    }

    $user = $app->auth()->user();

    if (!$user?->can('protected.access')) {
        return Response::html('403 Forbidden', 403);
    }

    return $app->view()->render('auth/protected', [
        'appName' => $app->config()->get('app.name', 'Copot'),
        'csrfToken' => $app->session()->csrfToken(),
        'url' => fn (string $path): string => $app->url($path),
        'user' => $user,
    ]);
});
