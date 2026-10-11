<?php

namespace Copot\Core;

final class DestructivePlanConfirmationCliAdapter
{
    public static function fromTypedInput(array $input): DestructivePlanConfirmation
    {
        return DestructivePlanConfirmation::fromArray($input);
    }
}
