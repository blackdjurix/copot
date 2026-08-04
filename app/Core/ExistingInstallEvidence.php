<?php

namespace Copot\Core;

final class ExistingInstallEvidence
{
    public function __construct(
        private bool $databaseSchemaPresent = false,
        private bool $environmentConfigured = false
    ) {
    }

    public function hasMaterialInstallationEvidence(): bool
    {
        return $this->databaseSchemaPresent || $this->environmentConfigured;
    }
}
