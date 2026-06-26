<?php

namespace Copot\Core;

class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        return $this->session->csrfToken();
    }

    public function validate(Request $request, string $field = '_token'): bool
    {
        $token = $request->post($field);

        return $this->session->validateCsrf(is_string($token) ? $token : null);
    }

    public function reject(): Response
    {
        return Response::html('Invalid CSRF token.', 419);
    }

    public function validateOrReject(Request $request, string $field = '_token'): ?Response
    {
        return $this->validate($request, $field) ? null : $this->reject();
    }
}
