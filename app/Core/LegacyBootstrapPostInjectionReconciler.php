<?php

namespace Copot\Core;

/** Reconciles immutable pre-injection evidence with a reviewed target transition. */
final class LegacyBootstrapPostInjectionReconciler
{
    public function __construct(
        private LegacyBootstrapSourceArtifactStore $artifacts,
        private LegacyBootstrapLiveInventoryProvider $liveInventory
    ) {}

    public function reconcile(LegacyBootstrapPreparationHandoff $handoff): LegacyBootstrapPostInjectionReconciliationResult
    {
        $preparation = $handoff->preparation();
        $manifest = $handoff->reviewedInjectionManifest();
        $diagnostics = [];
        $currentFingerprint = '';
        $originalFingerprint = '';

        try {
            $artifact = $this->artifacts->rehydrate($preparation->artifactPath() ?? '', [
                'operation_identity' => $preparation->operationIdentity(),
                'installation_identity' => $preparation->installationIdentity(),
                'plan_identity' => $preparation->planIdentity(),
            ]);
            $original = $artifact->sourceInventory();
            $originalFingerprint = $artifact->sourceInventoryFingerprint();
            if ($originalFingerprint !== $preparation->sourceInventory()?->fingerprint()) $diagnostics[] = 'Preparation source evidence does not match rehydrated artifact.';
            $manifest->assertBoundTo($artifact->installationIdentity(), $handoff->target(), $handoff->sourceRoot(), $artifact->operationIdentity(), $artifact->planIdentity(), $artifact->sourceInventoryFingerprint());
            if ($manifest->targetInventoryFingerprint() !== $artifact->targetInventoryFingerprint()) $diagnostics[] = 'Reviewed injection manifest target inventory fingerprint changed.';
            $current = $this->liveInventory->capture();
            $currentFingerprint = $current->fingerprint();
            $expected = $this->expectedDelta($original, $current);
            $declared = $this->declaredDelta($manifest->changes());
            if ($expected !== $declared) $diagnostics[] = 'Actual post-injection delta is not exactly covered by the reviewed injection manifest.';
        } catch (\Throwable $exception) {
            $diagnostics[] = $exception->getMessage();
        }

        $status = $diagnostics === [] ? LegacyBootstrapPostInjectionReconciliationResult::MATCHED : (
            $currentFingerprint === '' || $originalFingerprint === ''
                ? LegacyBootstrapPostInjectionReconciliationResult::UNVERIFIED
                : LegacyBootstrapPostInjectionReconciliationResult::STALE
        );
        $collisionIdentity = isset($artifact) && $artifact instanceof LegacyBootstrapSourceArtifact
            ? hash('sha256', json_encode($artifact->collisions(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))
            : '';
        $collisionEvidence = isset($artifact) && $artifact instanceof LegacyBootstrapSourceArtifact ? $artifact->collisions() : [];
        return new LegacyBootstrapPostInjectionReconciliationResult(
            $status,
            $preparation->installationIdentity(),
            $preparation->operationIdentity(),
            $preparation->planIdentity(),
            $originalFingerprint,
            $currentFingerprint,
            $manifest->identity(),
            $collisionIdentity,
            array_values(array_unique($diagnostics)),
            $collisionEvidence
        );
    }

    private function expectedDelta(LegacyBootstrapSourceInventory $original, LegacyBootstrapSourceInventory $current): array
    {
        $before = $this->index($original);
        $after = $this->index($current);
        $delta = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if ($old instanceof LegacyBootstrapSourceEntry && $new instanceof LegacyBootstrapSourceEntry && $old->byteSize() === $new->byteSize() && hash_equals($old->sha256(), $new->sha256())) continue;
            if ($new instanceof LegacyBootstrapSourceEntry) {
                $delta[$key] = ['action' => $old instanceof LegacyBootstrapSourceEntry ? 'REPLACE' : 'ADD', 'root' => $new->root(), 'path' => $new->path(), 'byte_size' => $new->byteSize(), 'sha256' => $new->sha256()];
            } elseif ($old instanceof LegacyBootstrapSourceEntry) {
                $delta[$key] = ['action' => 'REMOVE', 'root' => $old->root(), 'path' => $old->path(), 'byte_size' => $old->byteSize(), 'sha256' => $old->sha256()];
            }
        }
        ksort($delta);
        return $delta;
    }

    private function declaredDelta(array $changes): array
    {
        $declared = [];
        foreach ($changes as $change) {
            $key = strtolower($change['root'] . "\0" . $change['path']);
            $declared[$key] = ['action' => $change['action'], 'root' => $change['root'], 'path' => $change['path'], 'byte_size' => $change['byte_size'], 'sha256' => strtolower($change['sha256'])];
        }
        ksort($declared);
        return $declared;
    }

    private function index(LegacyBootstrapSourceInventory $inventory): array
    {
        $index = [];
        foreach ($inventory->entries() as $entry) $index[strtolower($entry->root() . "\0" . $entry->path())] = $entry;
        return $index;
    }
}
