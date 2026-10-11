<?php

namespace Copot\Core;

final class DestructivePlanConfirmationSystemManagerAdapter
{
    public static function fromTypedRequest(array $request): DestructivePlanConfirmation
    {
        if (array_keys($request) !== ['destructive_plan_confirmation'] || !is_array($request['destructive_plan_confirmation'])) { throw new \InvalidArgumentException('System Manager confirmation envelope is invalid.'); }
        return DestructivePlanConfirmation::fromArray($request['destructive_plan_confirmation']);
    }
}
