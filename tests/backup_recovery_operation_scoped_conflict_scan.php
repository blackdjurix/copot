<?php

declare(strict_types=1);

use Copot\Core\BackupRecovery\RecoveryArtifactRecord;
use Copot\Core\BackupRecovery\RecoveryArtifactStore;
use Copot\Core\BackupRecovery\RecoveryDomainIdentity;
use Copot\Core\BackupRecovery\RecoveryIdentity;
use Copot\Core\BackupRecovery\RecoveryLifecycleRecord;
use Copot\Core\BackupRecovery\RecoveryLifecycleState;
use Copot\Core\BackupRecovery\RecoveryLifecycleStore;
use Copot\Core\BackupRecovery\RecoveryManifest;
use Copot\Core\BackupRecovery\RecoveryManifestCodec;
use Copot\Core\BackupRecovery\RecoveryStoragePathPolicy;
use Copot\Core\BackupRecovery\RecoveryStorageRoot;
use Copot\Core\BackupRecovery\NormalWebcoreRecoveryCaptureService;
use Copot\Core\LifecycleExecutionAttemptIdentity;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$throws = static function (callable $callback) use ($assert): void {
    try {
        $callback();
    } catch (Throwable) {
        $assert(true, 'Expected recovery conflict rejection.');
        return;
    }
    $assert(false, 'Recovery conflict was not rejected.');
};
$remove = static function (string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $remove($path . DIRECTORY_SEPARATOR . $entry);
            }
        }
        @rmdir($path);
    } elseif (is_file($path) || is_link($path)) {
        @unlink($path);
    }
};

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'copot-operation-conflict-' . bin2hex(random_bytes(6));
mkdir($base, 0700, true);

$newRoot = static function (string $name) use ($base): RecoveryStorageRoot {
    $project = $base . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'project';
    $recovery = $base . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'recovery';
    mkdir($project, 0700, true);
    mkdir($recovery, 0700, true);
    return new RecoveryStorageRoot($project, $recovery, $name);
};
$makeEvidence = static function (string $operation, ?string $attempt, string $planSuffix): array {
    $plan = hash('sha256', 'plan:' . $planSuffix);
    $recovery = NormalWebcoreRecoveryCaptureService::forOperation($operation, $plan, $attempt);
    $bytes = 'database:' . $operation . ':' . ($attempt ?? 'legacy');
    $artifactIdentity = hash('sha256', $bytes);
    $artifact = new RecoveryArtifactRecord('database.webcore', $artifactIdentity, strlen($bytes));
    $domain = new RecoveryDomainIdentity('database.webcore', 'database.webcore', 'scope-' . $operation, $artifactIdentity);
    $manifest = new RecoveryManifest(
        $recovery,
        $operation,
        'package-' . $operation,
        'release-' . $operation,
        hash('sha256', 'archive:' . $operation),
        $plan,
        [$domain],
        'pre-lifecycle-' . $operation,
        'pre-ledger-' . $operation,
        $attempt
    );
    return [$manifest, $artifact, $bytes];
};
$publishReady = static function (RecoveryStorageRoot $root, RecoveryManifest $manifest, RecoveryArtifactRecord $artifact, string $bytes): void {
    (new RecoveryArtifactStore($root))->publish($manifest, [['record' => $artifact, 'bytes' => $bytes]]);
    $store = new RecoveryLifecycleStore($root);
    $record = new RecoveryLifecycleRecord(
        $manifest->recoveryIdentity(),
        $manifest->identity(),
        $manifest->operationIdentity(),
        RecoveryLifecycleState::CREATED,
        false,
        false,
        null,
        null,
        null,
        null,
        null,
        '',
        '',
        null,
        null,
        false,
        $manifest->executionAttemptIdentity()
    );
    $store->create($record);
    $store->transition($manifest->recoveryIdentity(), RecoveryLifecycleState::CAPTURING);
    $store->transition($manifest->recoveryIdentity(), RecoveryLifecycleState::CAPTURED);
    $store->save($store->read($manifest->recoveryIdentity())->withCaptureComplete());
    $store->transition($manifest->recoveryIdentity(), RecoveryLifecycleState::READY);
    $store->save($store->read($manifest->recoveryIdentity())->withConfirmation($manifest->recoveryIdentity(), $manifest->identity(), 'target'));
};

try {
    $operation = 'operation-scan';
    $attemptA = LifecycleExecutionAttemptIdentity::create();
    $attemptB = LifecycleExecutionAttemptIdentity::create();

    // Clean storage is enumerable and contains no candidates.
    $cleanRoot = $newRoot('clean');
    $cleanArtifacts = new RecoveryArtifactStore($cleanRoot);
    $cleanLifecycle = new RecoveryLifecycleStore($cleanRoot);
    $assert($cleanArtifacts->findManifestCandidatesByOperationIdentity($operation) === [], 'Clean recovery storage was not empty.');
    $assert($cleanLifecycle->findByOperationIdentity($operation) === [], 'Clean lifecycle storage was not empty.');

    // A valid unrelated operation is discoverable but does not match this operation.
    $unrelatedRoot = $newRoot('unrelated');
    [$unrelatedManifest, $unrelatedArtifact, $unrelatedBytes] = $makeEvidence('unrelated-operation', $attemptA, 'unrelated');
    $publishReady($unrelatedRoot, $unrelatedManifest, $unrelatedArtifact, $unrelatedBytes);
    $assert((new RecoveryArtifactStore($unrelatedRoot))->findManifestCandidatesByOperationIdentity($operation) === [], 'Unrelated manifest caused a false operation match.');
    $assert((new RecoveryLifecycleStore($unrelatedRoot))->findByOperationIdentity($operation) === [], 'Unrelated lifecycle record caused a false operation match.');

    // The same operation under another attempt remains visible to the scanner.
    $differentAttemptRoot = $newRoot('different-attempt');
    [$differentManifest, $differentArtifact, $differentBytes] = $makeEvidence($operation, $attemptB, 'same-plan');
    $publishReady($differentAttemptRoot, $differentManifest, $differentArtifact, $differentBytes);
    $differentCandidates = (new RecoveryArtifactStore($differentAttemptRoot))->findManifestCandidatesByOperationIdentity($operation);
    $assert(count($differentCandidates) === 1 && $differentCandidates[0]['manifest']->executionAttemptIdentity() === $attemptB, 'Different-attempt manifest was not discovered.');

    // Lifecycle-only partial evidence is visible even without a manifest.
    $partialRoot = $newRoot('lifecycle-only');
    $partialIdentity = NormalWebcoreRecoveryCaptureService::forOperation($operation, hash('sha256', 'plan:partial'), $attemptB);
    (new RecoveryLifecycleStore($partialRoot))->create(new RecoveryLifecycleRecord(
        $partialIdentity,
        hash('sha256', 'partial-manifest'),
        $operation,
        RecoveryLifecycleState::CREATED,
        false,
        false,
        null,
        null,
        null,
        null,
        null,
        '',
        '',
        null,
        null,
        false,
        $attemptB
    ));
    $assert((new RecoveryArtifactStore($partialRoot))->findManifestCandidatesByOperationIdentity($operation) === [], 'Lifecycle-only evidence was misreported as a manifest.');
    $partialRecords = (new RecoveryLifecycleStore($partialRoot))->findByOperationIdentity($operation);
    $assert(count($partialRecords) === 1 && $partialRecords[0]->state() === RecoveryLifecycleState::CREATED, 'Lifecycle-only partial evidence was not discovered.');

    // Historical attempt-null evidence is retained as an explicit candidate.
    $nullRoot = $newRoot('null-attempt');
    [$nullManifest, $nullArtifact, $nullBytes] = $makeEvidence($operation, null, 'legacy');
    $publishReady($nullRoot, $nullManifest, $nullArtifact, $nullBytes);
    $nullCandidates = (new RecoveryArtifactStore($nullRoot))->findManifestCandidatesByOperationIdentity($operation);
    $nullRecords = (new RecoveryLifecycleStore($nullRoot))->findByOperationIdentity($operation);
    $assert(count($nullCandidates) === 1 && $nullCandidates[0]['manifest']->executionAttemptIdentity() === null, 'Historical null-attempt manifest was not retained as a candidate.');
    $assert(count($nullRecords) === 1 && $nullRecords[0]->executionAttemptIdentity() === null, 'Historical null-attempt lifecycle was not retained as a candidate.');

    // Incomplete metadata is discoverable without being treated as READY.
    $incompleteRoot = $newRoot('incomplete');
    [$incompleteManifest, $incompleteArtifact] = $makeEvidence($operation, $attemptB, 'incomplete');
    $incompletePolicy = new RecoveryStoragePathPolicy($incompleteRoot);
    $incompleteManifestPath = $incompletePolicy->manifestPath($incompleteManifest->recoveryIdentity());
    mkdir(dirname($incompleteManifestPath), 0700, true);
    file_put_contents($incompleteManifestPath, (new RecoveryManifestCodec())->encode($incompleteManifest, [$incompleteArtifact], false));
    $incompleteCandidates = (new RecoveryArtifactStore($incompleteRoot))->findManifestCandidatesByOperationIdentity($operation);
    $assert(count($incompleteCandidates) === 1 && $incompleteCandidates[0]['complete'] === false, 'Incomplete manifest was not classified as incomplete evidence.');

    // A decoded manifest is authoritative only inside the canonical namespace
    // derived from that manifest identity. Both complete and incomplete
    // manifests must fail closed when physically placed under another set.
    $wrongNamespaceRoot = $newRoot('wrong-namespace');
    $wrongNamespacePolicy = new RecoveryStoragePathPolicy($wrongNamespaceRoot);
    [$wrongCompleteManifest, $wrongCompleteArtifact, $wrongCompleteBytes] = $makeEvidence($operation, $attemptB, 'wrong-complete');
    $wrongCompleteIdentity = new RecoveryIdentity('wrong-complete-' . bin2hex(random_bytes(4)));
    $wrongCompletePath = $wrongNamespacePolicy->manifestPath($wrongCompleteIdentity);
    mkdir(dirname($wrongCompletePath), 0700, true);
    file_put_contents($wrongCompletePath, (new RecoveryManifestCodec())->encode($wrongCompleteManifest, [$wrongCompleteArtifact], true));
    $wrongCompleteStore = new RecoveryArtifactStore($wrongNamespaceRoot);
    $throws(static fn () => $wrongCompleteStore->findManifestCandidatesByOperationIdentity($operation));
    $throws(static fn () => $wrongCompleteStore->readManifest($wrongCompleteIdentity));

    $wrongIncompleteRoot = $newRoot('wrong-incomplete');
    $wrongIncompletePolicy = new RecoveryStoragePathPolicy($wrongIncompleteRoot);
    [$wrongIncompleteManifest, $wrongIncompleteArtifact] = $makeEvidence($operation, $attemptB, 'wrong-incomplete');
    $wrongIncompleteIdentity = new RecoveryIdentity('wrong-incomplete-' . bin2hex(random_bytes(4)));
    $wrongIncompletePath = $wrongIncompletePolicy->manifestPath($wrongIncompleteIdentity);
    mkdir(dirname($wrongIncompletePath), 0700, true);
    file_put_contents($wrongIncompletePath, (new RecoveryManifestCodec())->encode($wrongIncompleteManifest, [$wrongIncompleteArtifact], false));
    $throws(static fn () => (new RecoveryArtifactStore($wrongIncompleteRoot))->findManifestCandidatesByOperationIdentity($operation));

    // A malformed manifest is ambiguity, not absence.
    $malformedRoot = $newRoot('malformed');
    $malformedIdentity = new RecoveryIdentity('malformed-' . bin2hex(random_bytes(4)));
    $malformedPath = (new RecoveryStoragePathPolicy($malformedRoot))->manifestPath($malformedIdentity);
    mkdir(dirname($malformedPath), 0700, true);
    file_put_contents($malformedPath, '{"malformed":true}');
    $throws(static fn () => (new RecoveryArtifactStore($malformedRoot))->findManifestCandidatesByOperationIdentity($operation));

    // CLEANED is visible and can be classified as disposed by the adapter.
    $cleanedRoot = $newRoot('cleaned');
    [$cleanedManifest, $cleanedArtifact, $cleanedBytes] = $makeEvidence($operation, $attemptB, 'cleaned');
    (new RecoveryArtifactStore($cleanedRoot))->publish($cleanedManifest, [['record' => $cleanedArtifact, 'bytes' => $cleanedBytes]]);
    $cleanedStore = new RecoveryLifecycleStore($cleanedRoot);
    $cleanedStore->create(new RecoveryLifecycleRecord(
        $cleanedManifest->recoveryIdentity(),
        $cleanedManifest->identity(),
        $operation,
        RecoveryLifecycleState::CREATED,
        false,
        false,
        null,
        null,
        null,
        null,
        null,
        '',
        '',
        null,
        null,
        false,
        $attemptB
    ));
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::CAPTURING);
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::CAPTURED);
    $cleanedStore->save($cleanedStore->read($cleanedManifest->recoveryIdentity())->withCaptureComplete());
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::READY);
    $cleanedStore->save($cleanedStore->read($cleanedManifest->recoveryIdentity())->withConfirmation($cleanedManifest->recoveryIdentity(), $cleanedManifest->identity(), 'target'));
    $cleanedStore->markMutationStarting($cleanedManifest->recoveryIdentity());
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::RESTORING);
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::VERIFYING);
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::RESTORED);
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::CLEANUP_PENDING);
    $cleanedStore->transition($cleanedManifest->recoveryIdentity(), RecoveryLifecycleState::CLEANED);
    $cleanedRecord = $cleanedStore->findByOperationIdentity($operation)[0] ?? null;
    $assert($cleanedRecord instanceof RecoveryLifecycleRecord && $cleanedRecord->state() === RecoveryLifecycleState::CLEANED, 'CLEANED lifecycle history was not retained for classification.');

    // A physical recovery-set residue without authoritative metadata is ambiguous.
    $orphanRoot = $newRoot('orphan');
    $orphanIdentity = new RecoveryIdentity('orphan-' . bin2hex(random_bytes(4)));
    $orphanArtifacts = (new RecoveryStoragePathPolicy($orphanRoot))->recoverySetRoot($orphanIdentity) . DIRECTORY_SEPARATOR . 'artifacts';
    mkdir($orphanArtifacts, 0700, true);
    file_put_contents($orphanArtifacts . DIRECTORY_SEPARATOR . 'orphan.bin', 'orphan');
    $throws(static fn () => (new RecoveryArtifactStore($orphanRoot))->findManifestCandidatesByOperationIdentity($operation));

    echo "backup_recovery_operation_scoped_conflict_scan: {$assertions} assertions passed\n";
} finally {
    $remove($base);
}
