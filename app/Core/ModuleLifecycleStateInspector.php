<?php

namespace Copot\Core;

final class ModuleLifecycleStateInspector
{
    public function __construct(private ModuleLifecycleStateStore $store, private $runtimeReader) {}

    public function inspect(ModuleIdentity|string $module): ModuleLifecycleInspection
    {
        try {
            $identity = $module instanceof ModuleIdentity ? $module : new ModuleIdentity($module);
            $runtime = ($this->runtimeReader)($identity->value());
            if ($runtime !== null && !is_array($runtime)) return ModuleLifecycleInspection::invalid('Runtime Module state is invalid.');
            if (is_array($runtime)) $this->validateRuntime($runtime, $identity->value());
            $state = $this->store->read($identity);
        } catch (\Throwable $exception) { return ModuleLifecycleInspection::invalid($exception->getMessage()); }

        if ($runtime === null && $state === null) return ModuleLifecycleInspection::fresh();
        if ($runtime !== null && $state === null) return ModuleLifecycleInspection::legacy($runtime);
        if ($runtime === null && $state !== null) return ModuleLifecycleInspection::inconsistent('Committed Module lifecycle state exists without runtime Module registration.', $state);
        if ($state->moduleIdentity()->value() !== $identity->value()) return ModuleLifecycleInspection::inconsistent('Committed Module technical identity does not match the inspected Module.', $state, $runtime);
        if ($state->packageVersion() !== (string) $runtime['version'] || $state->enabled() !== ((string) $runtime['status'] === 'enabled')) return ModuleLifecycleInspection::inconsistent('Committed Module lifecycle state contradicts runtime Module status or version.', $state, $runtime);
        return ModuleLifecycleInspection::committed($state, $runtime);
    }

    private function validateRuntime(array $runtime, string $identity): void
    {
        foreach (['name', 'version', 'status'] as $field) if (!isset($runtime[$field]) || !is_string($runtime[$field])) throw new \InvalidArgumentException('Runtime Module state is incomplete.');
        if ($runtime['name'] !== $identity || !PackageVersion::isValid($runtime['version']) || !in_array($runtime['status'], ['enabled', 'disabled'], true)) throw new \InvalidArgumentException('Runtime Module state is invalid.');
    }
}
