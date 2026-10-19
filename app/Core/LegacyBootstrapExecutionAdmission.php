<?php

namespace Copot\Core;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'NormalWebcoreProtectedMutationBoundary.php';

use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\ApplicationWriteProtectionEvidence;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\ScopedDatabaseConsistencyEvidence;
use Copot\Core\V3SourceRelativeAction;

/**
 * Canonical, reread-verified admission for the bootstrap execution boundary.
 * The object is intentionally issued only after all pre-mutation evidence has
 * been checked again against the durable operation and recovery authorities.
 */
final class LegacyBootstrapExecutionAdmission
{
    private bool $authorized = false;

    private function __construct(
        private LegacyBootstrapV3ExecutionContext $context,
        private NormalWebcoreProtectedMutationSession $session,
        private string $identity
    ) {}

    public static function issue(
        LegacyBootstrapV3ExecutionContext $context,
        LegacyBootstrapRecoveryBindingResult $binding,
        NormalWebcoreProtectedMutationSession $session,
        RecoveryArtifactStore $artifacts,
        LifecycleOperationStore $operations,
        LegacyBootstrapScopedProtectionComposer $protectionComposer,
        ApplicationWriteProtectionEvidence $application,
        ScopedDatabaseConsistencyEvidence $database
    ): self {
        $protection = $protectionComposer->compose($context->handoff(), $application, $database);
        $protectionData = $protection->toArray();
        $bindingData = $binding->toArray();
        $operation = $context->operation();
        $handoff = $context->handoff();
        $artifact = $handoff->artifact();
        $recovery = $context->recovery();

        if (!$protection->valid()
            || $protectionData['installation_identity'] !== $artifact->installationIdentity()
            || $protectionData['operation_identity'] !== $operation->operationId()
            || $protectionData['plan_identity'] !== $artifact->planIdentity()
            || $protectionData['target_inventory_fingerprint'] !== $artifact->targetInventoryFingerprint()
            || $protectionData['handoff_identity'] !== $handoff->identity()
            || $protectionData['application_protection_identity'] === null
            || $protectionData['database_consistency_identity'] === null
            || $protectionData['freshness_verification_identity'] === null
        ) {
            throw new \RuntimeException('Bootstrap protected evidence is incomplete or mismatched.');
        }

        if (!$binding->bound()
            || $bindingData['handoff_identity'] !== $handoff->identity()
            || $bindingData['operation_identity'] !== $operation->operationId()
            || $bindingData['recovery_identity'] !== $recovery->value()
            || $bindingData['manifest_identity'] !== $operation->recoveryManifestIdentity()
        ) {
            throw new \RuntimeException('Bootstrap recovery binding is incomplete or mismatched.');
        }

        $persisted = $operations->read();
        if (!$persisted instanceof LifecycleOperationRecord
            || $persisted->operationId() !== $operation->operationId()
            || $persisted->applyPlanIdentity() !== $handoff->applyPlan()->identity()
            || $persisted->recoveryIdentity() !== $recovery->value()
            || $persisted->recoveryManifestIdentity() !== $operation->recoveryManifestIdentity()
            || $persisted->recoveryState() !== RecoveryLifecycleState::READY
            || $persisted->fileCursor() !== 0
            || $persisted->lastVerifiedPath() !== null
        ) {
            throw new \RuntimeException('Persisted bootstrap operation is not admission-ready.');
        }

        $decoded = $artifacts->readManifest($recovery);
        $manifest = $decoded['manifest'];
        if ($manifest->identity() !== $operation->recoveryManifestIdentity()
            || $manifest->operationIdentity() !== $operation->operationId()
            || $manifest->applyPlanIdentity() !== $handoff->applyPlan()->identity()
            || $manifest->archiveIdentity() !== $handoff->target()->archiveIdentity()
        ) {
            throw new \RuntimeException('Persisted recovery manifest is not bound to bootstrap execution.');
        }

        $linkageRecord = null;
        $requiresLinkage = false;
        foreach ($context->plan()->actions() as $action) {
            if (in_array($action->action(), [V3SourceRelativeAction::REPLACE, V3SourceRelativeAction::REMOVE, V3SourceRelativeAction::RENAME], true)) {
                $requiresLinkage = true;
                break;
            }
        }
        if ($requiresLinkage) {
            foreach ($decoded['artifacts'] as $record) {
                if ($record instanceof RecoveryArtifactRecord && $record->domainIdentifier() === 'filesystem.action-linkage') {
                    $linkageRecord = $record;
                    break;
                }
            }
            if (!$linkageRecord instanceof RecoveryArtifactRecord
                || $linkageRecord->artifactIdentity() !== $bindingData['action_linkage_identity']
            ) {
                throw new \RuntimeException('Persisted filesystem action linkage is missing or mismatched.');
            }
            $artifacts->readArtifact($recovery, $linkageRecord);
        }

        $evidence = $session->evidence();
        if (($evidence['identity'] ?? null) !== $recovery->value()
            || ($evidence['manifest'] ?? null) !== $manifest->identity()
            || ($evidence['state'] ?? null) !== RecoveryLifecycleState::READY
        ) {
            throw new \RuntimeException('Canonical protected recovery session is not READY for this bootstrap.');
        }

        $identity = hash('sha256', json_encode([
            'context' => $context->identity(),
            'protection' => $protection->identity(),
            'binding' => $binding->identity(),
            'operation' => $operation->operationId(),
            'plan' => $context->plan()->identity(),
            'recovery' => $recovery->value(),
            'manifest' => $manifest->identity(),
            'action_linkage' => $linkageRecord?->artifactIdentity(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return new self($context, $session, $identity);
    }

    public function identity(): string { return $this->identity; }
    public function context(): LegacyBootstrapV3ExecutionContext { return $this->context; }
    public function authorized(): bool { return $this->authorized; }

    public function authorize(): void
    {
        if ($this->authorized) throw new \RuntimeException('Bootstrap execution admission was already consumed.');
        $this->session->authorize();
        $this->authorized = true;
    }

    public function fail(string $reason): void { $this->session->fail($reason); }
}
