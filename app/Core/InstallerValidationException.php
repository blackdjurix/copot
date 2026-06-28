<?php

namespace Copot\Core;

class InstallerValidationException extends InstallationException
{
    public function __construct(
        private array $errors,
        private array $submittedValues
    ) {
        parent::__construct('Installer input validation failed.');
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function submittedValues(): array
    {
        return $this->submittedValues;
    }
}
