<?php

namespace Copot\Core;

interface WebcoreLifecycleHealthEvidenceSource
{
    public function collect(SystemHealthContext $context): WebcoreLifecycleHealthEvidence;
}
