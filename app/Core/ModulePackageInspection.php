<?php

namespace Copot\Core;

final class ModulePackageInspection
{
    public function __construct(
        private ModulePackageManifest $manifest,
        private array $runtimeManifest,
        private array $staticFindings = [],
        private array $limitations = []
    ) {
        foreach ($staticFindings as $finding) {
            if (!is_array($finding) || !isset($finding['code'], $finding['message'])) {
                throw new \InvalidArgumentException('Module package inspection findings are invalid.');
            }
        }
    }

    public function manifest(): ModulePackageManifest { return $this->manifest; }
    public function contract(): ModulePackageContract { return $this->manifest->contract(); }
    public function livePayload(): StagedPayload { return $this->manifest->livePayload(); }
    public function runtimeManifest(): array { return $this->runtimeManifest; }
    public function staticFindings(): array { return $this->staticFindings; }
    public function limitations(): array { return $this->limitations; }
    public function accepted(): bool { return $this->staticFindings === []; }
}
