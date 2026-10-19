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
        if ($admission->context()->identity() !== $context->identity()
            || $context->mutationAuthorized()
            || $context->operationalReady()
            || $admission->authorized()) {
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], 'Bootstrap execution context is not mutation-eligible.', $context->operation()->operationId());
        }
        try {
            $admission->authorize();
            $result = $this->executor->execute(
                $context->plan(), $payload, null, $context->operation(), $operations,
                $applier, $guard, static fn (): bool => true, $confirmationAuthority,
                $confirmation, $journal, $beforeActivation, $context
            );
            if (in_array($result->status(), [WebcoreApplyResult::BLOCKED, WebcoreApplyResult::INDETERMINATE], true)) {
                $admission->fail($result->reason());
            }
            return $result;
        } catch (\Throwable $exception) {
            try { $admission->fail($exception->getMessage()); } catch (\Throwable) {}
            return new WebcoreApplyResult(WebcoreApplyResult::BLOCKED, [], $exception->getMessage(), $context->operation()->operationId());
        }
    }
}
