# COPOT CR-1 S4 — Lifecycle, Recovery & Injection Amendment Backlog

Status: REVIEWED PLANNING BACKLOG / NON-NORMATIVE / NOT PROMOTED
Scope: docs/28 Package Lifecycle, docs/31 Backup & Recovery, accepted Legacy-to-V3 Injection contract, and shared Guard candidate.
Source evidence: targeted read of codex/e4-d2-attempt-recovery; no runtime mutation or execution evidence claimed.
Authority: This file does not close Alternative C, authorize operations, or replace historical acceptance.

## 1. Existing normative authority

docs/28 owns package operation, immutable plan, attempt binding, apply, committed source inventory and WU6 finalization. docs/31 owns immutable recovery identity/manifest, original-byte capture, restore, durable recovery journal, verification and cleanup. Accepted Legacy-to-V3 Injection consumes them without parallel engine. Shared installation-scoped Maintenance Guard (CR-1 candidate, non-normative) owns application-writer protection, never independent mutation/recovery authority. InstallationMutex and independent database consistency/quiescence remain separate.

## 2. Amendment register

- LCR-01 [docs/28] Execution-attempt identity is immutable once assigned, binds the lifecycle operation, and prevents stale/cross-attempt evidence from authorizing a different attempt.
- LCR-02 [docs/28] PRE_CAPTURE_ABANDONED is a tightly bounded terminal state for genuinely pre-capture PREPARING operations with cursor zero, no verified mutated path, no migration outcome, and no recovery identity/manifest/state. Require durable evidence, exclusive ownership and idempotent continuation; existing recovery evidence must not be discarded.
- LCR-03 [docs/31] Recovery manifests/artifacts are operation- and attempt-bound. Enumerate canonical operation-scoped candidates, integrity-check manifests, reject orphan/unclassified residue and namespace mismatches, and fail closed on ambiguity.
- LCR-04 [docs/28 + docs/31] First-capture continuation uses the persisted canonical lifecycle operation and its immutable execution attempt, not a fabricated or independently regenerated attempt. Recovery request and source authority bind the correct committed lifecycle identity.
- LCR-05 [docs/28 + Guard] First-capture critical boundary maintains one exclusive installation mutex session across preflight, Guard adoption, capture admission and handoff. Do not reacquire a nested mutex or lose process ownership silently.
- LCR-06 [docs/31] Scoped DB consistency evidence must bind installation, namespace, verified physical tableset and migration state; read-before/read-after must prove no change at evidence composition. Guard activity alone is not DB quiescence.
- LCR-07 [docs/31 + Guard] READY recovery resume must reconstitute/revalidate scoped protection and operation binding with fresh evidence; no synthetic recovery state, cross-attempt identity borrowing or stale permission.
- LCR-08 [docs/28 + docs/31] Recovery obligations survive interruption until verified appropriate finalization, restoration or authorized cleanup; session closure cannot falsely release outstanding obligations.
- LCR-09 [docs/28] SourceInventoryReference and committed lifecycle advancement remain forbidden until WU6 health/integrity/fingerprint and migration gates succeed. ALREADY AUTHORITATIVE, reference rather than re-authorize.

All above except LCR-09 are proposed normative delta targets, not accepted amendments.

## 3. Targeted implementation observations, not acceptance

In branch codex/e4-d2-attempt-recovery: LifecycleOperationRecord supports execution_attempt_identity and PRE_CAPTURE_ABANDONED; PreCaptureAbandonmentService supports durable terminalization; RecoveryArtifactStore provides manifest candidate enumeration and residue checks; PackageLifecycleService provides first-capture continuation with persisted operation, one adopted lock and scoped database state comparison; RecoveryLifecycleCoordinator provides READY Guard re-entry. Existence of methods is not a passing regression or runtime proof.

## 4. Outstanding technical verification

- Confirm all cross-attempt and namespace-binding manifest negative paths, including orphan artifact-only residue and stale cross-attempt captures.
- Validate first-capture interruption/atomicity, persisted attempt, mutex handoff, and nonmutating rejection paths.
- Validate READY resume authorization, Guard exclusion, recovery identity/capture completeness, mutation_started boundary and WU6 continuation.
- Validate original-byte recovery, immutable artifacts, database tableset consistency, final fingerprint/SourceInventoryReference and cleanup.
- Verify historical v1/v2 and accepted non-E4 scopes are preserved. Never infer operational E4 acceptance from this repository-only review.

## 5. Injection compliance

Accepted injection contract requires canonical V3 package and verified source evidence, collision/preservation planning, before-mutation recovery capture, installation writer protection and independent database consistency, explicit destructive authority where applicable, original-byte recovery and WU6 finalization. E4 implementation provides relevant entrypoint surfaces; final compatibility/operational acceptance remains UNVERIFIED in CR-1.

## 6. Non-goals and handoff

No parallel recovery engine, second Guard, lifecycle topology rewrite, operational retry, SDR mutation, or authorization to continue Alternative C. Preserve original thread's pending READY resume/WU6 execution gate. Formal amendments and promotion require separate authorization and technical evidence. Carry these deltas into CR-1 S5 return report with exact artifact identity.
