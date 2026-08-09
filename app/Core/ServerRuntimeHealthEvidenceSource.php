<?php

namespace Copot\Core;

interface ServerRuntimeHealthEvidenceSource
{
    public function collect(SystemHealthContext $context): ServerRuntimeHealthEvidence;
}
