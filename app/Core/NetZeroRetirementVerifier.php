<?php

namespace Copot\Core;

final class NetZeroRetirementVerifier
{
    public function verify(LifecycleOperationRecord $operation, WebcoreApplyPlan $plan, NetZeroRetirementContext $context): NetZeroRetirementVerification
    {
        $fail = static fn (string $reason): never => throw new \RuntimeException('Net-zero retirement rejected: ' . $reason);
        if ($operation->operationId() !== $context->operationId()) $fail('operation identity mismatch.');
        if (!in_array($operation->phase(), [LifecycleOperationRecord::BLOCKED, LifecycleOperationRecord::INDETERMINATE, LifecycleOperationRecord::APPLYING, LifecycleOperationRecord::MIGRATING], true)) $fail('operation is not stranded and non-terminal.');
        if ($operation->migrationOutcome() !== null) $fail('migration outcome is already present.');
        if ($operation->recoveryIdentity() !== null || $operation->recoveryManifestIdentity() !== null || $operation->recoveryState() !== null) $fail('retrospective recovery evidence is present.');
        if ($operation->stagingPath() !== $plan->payload()->stagingPath() || $operation->archiveSha256() !== $plan->payload()->archiveSha256()) $fail('archive or staging identity mismatch.');
        $payloadIdentity = hash('sha256', implode(':', array_map(static fn (StagedFile $file): string => $file->path() . ':' . $file->sha256(), $plan->files())));
        if ($operation->payloadIdentity() !== $payloadIdentity) $fail('payload identity mismatch.');
        if ($operation->applyPlanIdentity() !== $plan->identity()) $fail('apply-plan identity mismatch.');
        if ($operation->migrationPlanIdentity() === null || $operation->migrationPlanIdentity() !== $context->migrationPlanIdentity()) $fail('migration-plan identity mismatch.');
        if (!is_dir($plan->payload()->payloadPath()) || !is_file($plan->payload()->archivePath())) $fail('retained package evidence is unavailable.');
        if (@hash_file('sha256', $plan->payload()->archivePath()) !== $operation->archiveSha256()) $fail('retained archive identity mismatch.');

        $files = $plan->files();
        $cursor = $operation->fileCursor();
        if ($cursor < 1 || $cursor > count($files)) $fail('persisted cursor is incoherent.');
        if ($operation->lastVerifiedPath() !== $files[$cursor - 1]->path()) $fail('last verified path does not match apply order.');

        $liveRoot = realpath($context->runtimeRoot());
        $acceptedRoot = realpath($context->acceptedTargetRoot());
        if ($liveRoot === false || !is_dir($liveRoot) || $acceptedRoot === false || !is_dir($acceptedRoot)) $fail('runtime or accepted target root is unavailable.');
        $state = $context->currentState();
        foreach ($context->expectedState() as $key => $expected) {
            if (!isset($state[$key]) || !is_string($state[$key]) || !hash_equals($expected, $state[$key])) $fail('state invariant mismatch: ' . $key . '.');
        }

        $prefix = [];
        foreach (array_slice($files, 0, $cursor) as $file) {
            $packagePath = $plan->payload()->payloadPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->path());
            $targetPath = $liveRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->path());
            $acceptedPath = $acceptedRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file->path());
            if (is_link($packagePath) || is_link($targetPath) || is_link($acceptedPath) || !is_file($packagePath) || !is_file($targetPath) || !is_file($acceptedPath)) $fail('applied path is missing or unresolved: ' . $file->path());
            $packageHash = @hash_file('sha256', $packagePath);
            $targetHash = @hash_file('sha256', $targetPath);
            $acceptedHash = @hash_file('sha256', $acceptedPath);
            if ($packageHash !== $file->sha256() || $targetHash !== $file->sha256() || $acceptedHash !== $file->sha256()) $fail('applied path bytes diverge: ' . $file->path());
            $prefix[] = [$file->path(), $file->byteSize(), $file->sha256()];
        }

        $body = [
            'operation_id' => $operation->operationId(),
            'archive_identity' => $operation->archiveSha256(),
            'payload_identity' => $operation->payloadIdentity(),
            'apply_plan_identity' => $operation->applyPlanIdentity(),
            'migration_plan_identity' => $operation->migrationPlanIdentity(),
            'file_cursor' => $cursor,
            'last_verified_path' => $operation->lastVerifiedPath(),
            'staging_path' => $operation->stagingPath(),
            'accepted_target_identity' => $context->acceptedTargetIdentity(),
            'state' => $context->expectedState(),
            'prefix' => $prefix,
        ];
        $body['forensic_identity'] = hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return new NetZeroRetirementVerification($body);
    }
}
