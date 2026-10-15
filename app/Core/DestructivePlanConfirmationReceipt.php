<?php

namespace Copot\Core;

final class DestructivePlanConfirmationReceipt
{
    public function __construct(private DestructivePlanConfirmation $confirmation, private string $acceptedAt)
    {
        if (\DateTimeImmutable::createFromFormat(DATE_ATOM, $acceptedAt) === false) { throw new \InvalidArgumentException('Confirmation acceptance time is invalid.'); }
    }
    public function confirmation(): DestructivePlanConfirmation { return $this->confirmation; }
    public function acceptedAt(): string { return $this->acceptedAt; }
    public function bindingIdentity(): string { return $this->confirmation->bindingIdentity(); }
}
