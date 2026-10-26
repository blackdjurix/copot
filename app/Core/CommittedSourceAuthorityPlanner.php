<?php

namespace Copot\Core;

use Copot\Core\BackupRecovery\DatabaseScope;

/** Composes canonical planning identities without persisting or executing them. */
final class CommittedSourceAuthorityPlanner
{
    public function plan(
        CommittedLifecycleState $committed,
        InstallationIdentity $installation,
        PackageManifest $manifest,
        CanonicalTargetInventory $actualTarget,
        DatabaseScope $databaseScope,
        InstallerOwnershipProof $ownershipProof,
        ?CoreMigrationPlan $migrationPlan = null,
        ?CommittedSourceAuthorityPreservationPolicy $preservationPolicy = null
    ): CommittedSourceAuthorityPlan {
        $desiredTarget = $manifest->contract()->targetInventory();
        if (!$desiredTarget instanceof CanonicalTargetInventory) {
            throw new \RuntimeException('A canonical target inventory is required.');
        }
        $requiresMutation = $desiredTarget->fingerprint() !== $actualTarget->fingerprint();
        $eligibility = (new CommittedSourceAuthorityEligibility())->evaluate($committed, $installation, $manifest, $actualTarget, $databaseScope, $ownershipProof, $requiresMutation);
        if (!$eligibility->eligible()) {
            throw new \RuntimeException($eligibility->reason());
        }
        if ($requiresMutation && (!$migrationPlan instanceof CoreMigrationPlan || !$migrationPlan->isAccepted())) {
            throw new \RuntimeException('Mutation-required planning requires an accepted canonical migration plan.');
        }
        if ($requiresMutation && ($migrationPlan->initialWebcoreVersion() !== $committed->webcoreVersion() || $migrationPlan->virtualFinalWebcoreVersion() !== $manifest->contract()->targetWebcoreVersion() || $migrationPlan->initialSchemaIdentity() !== $committed->schemaStateIdentity())) {
            throw new \RuntimeException('Canonical migration plan identity does not match committed source-authority state.');
        }
        // Mutation-mode authority reconciliation composes its own guarded
        // source plan so an explicit reviewed preservation profile can retain
        // deployment-owned routing bytes without turning them into V3 package
        // actions. Authority-only mode still requires the complete V3 plan.
        $basePlan = $requiresMutation ? null : (new PackageV3SourceRelativePlanner())->plan($manifest);
        $sourcePlan = $requiresMutation
            ? $this->mutationSourcePlan($manifest, $actualTarget, $preservationPolicy)
            : $basePlan;
        if ($requiresMutation && $preservationPolicy !== null && !$preservationPolicy->matches($actualTarget)) {
            throw new \RuntimeException('Preservation policy does not match the verified target inventory.');
        }
        if ($requiresMutation && $preservationPolicy === null && $this->hasPreservationRequired($manifest, $actualTarget)) {
            throw new \RuntimeException('A divergent routing path requires explicit preservation policy evidence.');
        }
        $applyPlan = WebcoreApplyPlan::fromPayload($manifest->payload());
        $mode = $requiresMutation ? CommittedSourceAuthorityPlan::MUTATION_REQUIRED : CommittedSourceAuthorityPlan::AUTHORITY_ONLY;
        $migrationPlanIdentity = $requiresMutation ? $migrationPlan->identity() : null;
        $operationId = 'source-authority-reconciliation-sha256:' . hash('sha256', json_encode([
            'operation_type' => 'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION',
            'mode' => $mode,
            'installation_identity' => $installation->value(),
            'manifest_identity' => $manifest->packageEvidenceIdentity(),
            'target_inventory_fingerprint' => $actualTarget->fingerprint(),
            'database_scope_identity' => $databaseScope->identity(),
            'apply_plan_identity' => $applyPlan->identity(),
            'migration_plan_identity' => $migrationPlanIdentity,
            'preservation_policy' => $preservationPolicy?->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $now = gmdate(DATE_ATOM);
        $operation = new LifecycleOperationRecord(
            $operationId,
            'COMMITTED_SOURCE_AUTHORITY_RECONCILIATION',
            $committed->webcoreVersion(),
            $committed->releaseIdentity(),
            $manifest->payload()->archiveSha256(),
            $manifest->payload()->stagingPath(),
            $applyPlan->identity(),
            $applyPlan->identity(),
            LifecycleOperationRecord::PREPARING,
            0,
            null,
            $migrationPlanIdentity,
            null,
            $now,
            $now,
            $requiresMutation ? 'Mutation-required source convergence is planned; execution, recovery and WU6 are pending.' : 'Authority-only source inventory reconciliation is planned; execution and WU6 are pending.'
        );
        $committedLifecycleIdentity = hash('sha256', json_encode($committed->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $identity = hash('sha256', json_encode([
            'plan_scheme' => 'committed-source-authority-reconciliation-v1',
            'operation_identity' => $operationId,
            'source_plan_identity' => $sourcePlan->identity(),
            'apply_plan_identity' => $applyPlan->identity(),
            'database_scope_identity' => $databaseScope->identity(),
            'target_inventory_fingerprint' => $actualTarget->fingerprint(),
            'desired_target_inventory_fingerprint' => $desiredTarget->fingerprint(),
            'mode' => $mode,
            'migration_plan_identity' => $migrationPlanIdentity,
            'preservation_policy' => $preservationPolicy?->toArray(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return new CommittedSourceAuthorityPlan($identity, $operation, $sourcePlan, $applyPlan, $databaseScope, !$requiresMutation, $committedLifecycleIdentity, $committed->migrationStateIdentity(), $mode, $actualTarget->fingerprint(), $desiredTarget->fingerprint(), $migrationPlanIdentity, $preservationPolicy);
    }

    private function mutationSourcePlan(PackageManifest $manifest, CanonicalTargetInventory $actual, ?CommittedSourceAuthorityPreservationPolicy $policy): V3SourceRelativePlan
    {
        $desired = $manifest->contract()->targetInventory();
        $actualIndex = $this->index($actual);
        $desiredIndex = $this->index($desired);
        $actions = [];
        foreach ($manifest->contract()->inventory() as $entry) {
            [$root, $path] = $this->rootPath($entry->path());
            $key = strtolower($root . "\0" . $path);
            $desiredEntry = $desiredIndex[$key] ?? null;
            if (!$desiredEntry instanceof CanonicalTargetInventoryEntry || $desiredEntry->byteSize() !== $entry->byteSize() || $desiredEntry->sha256() !== $entry->sha256()) { throw new \RuntimeException('Package inventory and target inventory are inconsistent.'); }
            $current = $actualIndex[$key] ?? null;
            $isPolicyPath = $policy !== null && $policy->root() === $root && $policy->path() === $path;
            if ($isPolicyPath) {
                $actions[] = new V3SourceRelativeAction(V3SourceRelativeAction::RETAIN, $root, $path, $entry->byteSize(), $entry->sha256(), null, null, null, 'PRESENT', $current->byteSize(), $current->sha256());
            } elseif (!$current instanceof CanonicalTargetInventoryEntry) {
                $actions[] = new V3SourceRelativeAction(V3SourceRelativeAction::DELIVER, $root, $path, $entry->byteSize(), $entry->sha256(), null, null, null, 'ABSENT');
            } elseif ($current->byteSize() === $entry->byteSize() && hash_equals($current->sha256(), $entry->sha256())) {
                $actions[] = new V3SourceRelativeAction(V3SourceRelativeAction::RETAIN, $root, $path, $entry->byteSize(), $entry->sha256(), null, null, null, 'PRESENT', $current->byteSize(), $current->sha256());
            } else {
                $actions[] = new V3SourceRelativeAction(V3SourceRelativeAction::REPLACE, $root, $path, $entry->byteSize(), $entry->sha256(), $root, $path, 'replaced', 'PRESENT', $current->byteSize(), $current->sha256());
            }
        }
        foreach ($actualIndex as $key => $_entry) {
            if (!isset($desiredIndex[$key])) { throw new \RuntimeException('Target contains an unplanned package-owned collision.'); }
        }
        usort($actions, static fn (V3SourceRelativeAction $left, V3SourceRelativeAction $right): int => strcmp(json_encode($left->toArray(), JSON_UNESCAPED_SLASHES), json_encode($right->toArray(), JSON_UNESCAPED_SLASHES)));
        return new V3SourceRelativePlan($manifest->contract(), $manifest->packageEvidenceIdentity() ?? PackageV3SourceRelativePlanner::packageIdentity($manifest->contract()), '', null, $actions, $desired->fingerprint(), $manifest->contract()->coverage(), []);
    }

    private function hasPreservationRequired(PackageManifest $manifest, CanonicalTargetInventory $actual): bool
    {
        $desired = $manifest->contract()->targetInventory();
        foreach ($manifest->contract()->inventory() as $entry) {
            [$root, $path] = $this->rootPath($entry->path());
            if ($root === CanonicalTargetInventoryEntry::PUBLIC_ROOT && $path === '.htaccess') {
                foreach ($actual->entries() as $current) if ($current->root() === $root && $current->path() === $path && $current->sha256() !== $entry->sha256()) return true;
            }
        }
        return false;
    }

    private function index(CanonicalTargetInventory $inventory): array
    {
        $result = [];
        foreach ($inventory->entries() as $entry) $result[strtolower($entry->root() . "\0" . $entry->path())] = $entry;
        return $result;
    }

    private function rootPath(string $path): array
    {
        return str_starts_with($path, 'public/') ? [CanonicalTargetInventoryEntry::PUBLIC_ROOT, substr($path, 7)] : [CanonicalTargetInventoryEntry::APP_ROOT, $path];
    }
}
