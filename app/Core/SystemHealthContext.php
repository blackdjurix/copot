<?php

namespace Copot\Core;

final class SystemHealthContext
{
    public function __construct(
        private InstallationIdentity $installation,
        private mixed $viewer
    ) {
    }

    public function installation(): InstallationIdentity
    {
        return $this->installation;
    }

    public function viewer(): mixed
    {
        return $this->viewer;
    }
}
