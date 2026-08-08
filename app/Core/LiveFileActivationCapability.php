<?php

namespace Copot\Core;

final class LiveFileActivationCapability
{
    public function __construct(
        private bool $supportsCreation,
        private bool $supportsReplacement
    ) {
    }

    public static function current(): self
    {
        return new self(true, true);
    }

    public function supportsCreation(): bool { return $this->supportsCreation; }
    public function supportsReplacement(): bool { return $this->supportsReplacement; }
}
