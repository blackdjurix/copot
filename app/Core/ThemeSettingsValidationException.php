<?php

namespace Copot\Core;

final class ThemeSettingsValidationException extends SettingsException
{
    public function __construct(private array $fieldErrors, private array $submitted)
    {
        parent::__construct('Theme settings validation failed.');
    }
    public function fieldErrors(): array { return $this->fieldErrors; }
    public function submitted(): array { return $this->submitted; }
}
