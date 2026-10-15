<?php

namespace Copot\Core;

final class SourceInventoryArtifact
{
    public function __construct(private CanonicalTargetInventory $inventory, private SourceInventoryReference $reference)
    {
        if ($inventory->fingerprint() !== $reference->fingerprint()) { throw new \InvalidArgumentException('Source inventory fingerprint linkage is invalid.'); }
    }
    public function inventory(): CanonicalTargetInventory { return $this->inventory; }
    public function reference(): SourceInventoryReference { return $this->reference; }
}
