<?php

namespace Copot\Core;

interface BundledCoreModuleHealthEvidenceSource
{
    /** @return list<BundledCoreModuleHealthEvidence> */
    public function collect(SystemHealthContext $context): array;
}
