<?php

use Copot\Core\Redirect\RedirectContract;
use Copot\Core\Request;
use Copot\Core\Response;
use Copot\Core\UnresolvedRouteResolver;

final class RedirectResolver implements UnresolvedRouteResolver
{
    public function __construct(private RedirectRepository $repository, private string $adminBase = '/admin')
    {
    }

    public function resolve(Request $request): ?Response
    {
        if ($request->method() !== 'GET') {
            return null;
        }

        try {
            $source = RedirectContract::source($request->path(), $this->adminBase);
        } catch (InvalidArgumentException) {
            return null;
        }

        $redirect = $this->repository->findBySource($source);

        return $redirect === null ? null : Response::redirect($redirect->target(), $redirect->statusCode());
    }
}
