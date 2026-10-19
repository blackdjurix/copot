<?php

namespace Copot\Core;

/** Typed input for the bootstrap-only mutation, migration, and WU6 handoff. */
final class LegacyBootstrapLifecycleRequest
{
    private ?DestructivePlanConfirmationAuthority $confirmationAuthority;
    private ?DestructivePlanConfirmation $confirmation;
    private $journal;
    private $beforeActivation;

    public function __construct(
        private PackageManifest $manifest,
        private StagedPayload $payload,
        private LegacyBootstrapV3ExecutionContext $context,
        private LegacyBootstrapExecutionAdmission $admission,
        ?DestructivePlanConfirmationAuthority $confirmationAuthority = null,
        ?DestructivePlanConfirmation $confirmation = null,
        ?callable $journal = null,
        ?callable $beforeActivation = null
    ) {
        $this->confirmationAuthority = $confirmationAuthority;
        $this->confirmation = $confirmation;
        $this->journal = $journal;
        $this->beforeActivation = $beforeActivation;

        if ($manifest->contract()->manifestContractVersion() !== PackageContract::V3_MANIFEST_CONTRACT_VERSION
            || $manifest->contract()->coverage() !== 'full'
            || $manifest->contract()->removals() !== []
            || $manifest->contract()->renames() !== []) {
            throw new \InvalidArgumentException('Bootstrap lifecycle requires a full deliver-only manifest.');
        }

        $operation = $context->operation();
        if ($payload->archiveSha256() !== $operation->archiveSha256()
            || $payload->stagingPath() !== $operation->stagingPath()
            || $manifest->payload()->archiveSha256() !== $context->handoff()->target()->archiveIdentity()
            || WebcoreApplyPlan::fromPayload($manifest->payload())->identity() !== $context->handoff()->applyPlan()->identity()) {
            throw new \InvalidArgumentException('Bootstrap lifecycle package or staging identity is not bound to the execution context.');
        }
    }

    public function manifest(): PackageManifest { return $this->manifest; }
    public function payload(): StagedPayload { return $this->payload; }
    public function context(): LegacyBootstrapV3ExecutionContext { return $this->context; }
    public function admission(): LegacyBootstrapExecutionAdmission { return $this->admission; }
    public function confirmationAuthority(): ?DestructivePlanConfirmationAuthority { return $this->confirmationAuthority; }
    public function confirmation(): ?DestructivePlanConfirmation { return $this->confirmation; }
    public function journal(): ?callable { return $this->journal; }
    public function beforeActivation(): ?callable { return $this->beforeActivation; }
}
