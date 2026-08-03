<?php

namespace Copot\Core;

interface UnresolvedRouteResolver
{
    public function resolve(Request $request): ?Response;
}
