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
        $windows = DIRECTORY_SEPARATOR === '\\';

        return new self(true, !$windows);
    }

    public function supportsCreation(): bool { return $this->supportsCreation; }
    public function supportsReplacement(): bool { return $this->supportsReplacement; }
}
