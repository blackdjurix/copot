<?php

namespace Copot\Core;

/** Explicit bootstrap entrypoint over the shared V3 executor. */
final class LegacyBootstrapV3ExecutionBridge
{
    public function __construct(private V3ExplicitApplyExecutor $executor) {}

    public function execute(
        LegacyBootstrapV3ExecutionContext $context,
        LegacyBootstrapExecutionAdmission $admission,
        StagedPayload $payload,
        LifecycleOperationStore $operations,
        PackageOwnedFileApplier $applier,
        LiveTreePathGuard $guard,
        ?DestructivePlanConfirmationAuthority $confirmationAuthority = null,
        ?DestructivePlanConfirmation $confirmation = null,
        ?callable $journal = null,
        ?callable $beforeActivation = null
    ): WebcoreApplyResult {
        return new WebcoreApplyResult(
            WebcoreApplyResult::BLOCKED,
            [],
            'Legacy bootstrap execution is retired from the canonical V3 executor.',
            $context->operation()->operationId()
        );
    }
}
