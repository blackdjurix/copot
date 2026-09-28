<?php

namespace Copot\Core;

final class SettingReadResult
{
    public function __construct(
        private mixed $value,
        private bool $storageReadable
    ) {
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function storageReadable(): bool
    {
        return $this->storageReadable;
    }
}
