<?php

namespace Copot\Core;

final class ModuleLifecycleTarget
{
    public function __construct(private ModulePackageContract $contract, private string $packageIntegrityIdentity)
    {
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($packageIntegrityIdentity)) !== 1) throw new \InvalidArgumentException('Module package integrity identity is invalid.');
        $this->packageIntegrityIdentity = strtolower($packageIntegrityIdentity);
    }
    public function contract(): ModulePackageContract { return $this->contract; }
    public function packageIntegrityIdentity(): string { return $this->packageIntegrityIdentity; }
}
