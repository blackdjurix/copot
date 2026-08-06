<?php

namespace Copot\Core\BackupRecovery;

use Copot\Core\CommittedLifecycleState;

final class RecoveryStateVerification
{
    /** @param array{version: string, release_identity: string}|null $expectedTarget */
    public function verify(RecoveryManifest $manifest, LifecycleRecoveryArtifact $lifecycle, InstalledLockRecoveryArtifact $marker, DatabaseVerificationResult $database, ?array $expectedTarget = null): RecoveryStateVerificationResult
    {
        $failures = [];
        if (!$database->isValid()) { $failures['database-verification'] = $database->failure() ?? 'Database verification failed.'; }
        $databaseLedger = $database->identities()['migration_ledger'] ?? null;
        if (!is_string($databaseLedger)) { $failures['migration-ledger-identity'] = 'Verified database migration identity is unavailable.'; }
        if ($manifest->preOperationLifecycleIdentity() !== $lifecycle->identity()) { $failures['lifecycle-identity-mismatch'] = 'Lifecycle artifact identity does not match the recovery manifest.'; }
        if (is_string($databaseLedger) && $manifest->preOperationMigrationLedgerIdentity() !== $databaseLedger) { $failures['manifest-migration-identity-mismatch'] = 'Manifest migration identity does not match the verified database ledger identity.'; }
        $state = $lifecycle->state();
        if ($state instanceof CommittedLifecycleState) {
            if (is_string($databaseLedger) && $state->migrationStateIdentity() !== $databaseLedger) { $failures['lifecycle-migration-identity-mismatch'] = 'Committed lifecycle migration identity does not match the verified database ledger identity.'; }
            if ($state->webcoreVersion() !== $marker->marker()['version'] || $state->committedAt()->format(\DATE_ATOM) !== $marker->marker()['installed_at']) { $failures['installed-lock-mismatch'] = 'Installed.lock is inconsistent with committed lifecycle state.'; }
            if ($expectedTarget !== null && ($state->webcoreVersion() !== $expectedTarget['version'] || $state->releaseIdentity() !== $expectedTarget['release_identity'])) { $failures['package-release-mismatch'] = 'Committed lifecycle state does not match the supplied target identity.'; }
        } elseif ($lifecycle->stateKind() !== 'ABSENT_BEFORE_OPERATION') { $failures['malformed-lifecycle-state'] = 'Lifecycle recovery state is malformed.'; }
        if ($failures !== []) { return RecoveryStateVerificationResult::failure($failures); }
        return RecoveryStateVerificationResult::success(['lifecycle' => $lifecycle->identity(), 'installed_lock' => $marker->identity(), 'migration_ledger' => (string) $databaseLedger]);
    }
}
