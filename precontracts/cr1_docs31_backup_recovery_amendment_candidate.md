# CR-1 — docs/31 Backup & Recovery Coordinated Amendment Candidate

Status: REFINED CANDIDATE / NON-NORMATIVE / NOT PROMOTED
Technical disposition: CR-1 SLICE 2 SEMANTICALLY RECONCILED / MATERIALIZED; IMPLEMENTATION VALIDATION PENDING
Target authority: docs/31_backup_recovery_foundation_contract.md
Basis: CD-V3-HTACCESS-20261026-01, LCR-03/04/06/07/08, Codex Slice 2 technical proposal, GPT semantic refinements, and the refined Slice 1 Maintenance Guard candidate.
Authority boundary: This candidate grants no implementation, repository/runtime mutation, operational continuation, release, or contract-promotion authority.

This amendment preserves immutable recovery manifests, original-byte recovery for permitted domains, independent database consistency, historical v1/v2 recovery, WU6 finalization, and existing Backup & Recovery ownership. It creates no parallel recovery engine, universal Guard ownership, mandatory storage format, or new lifecycle authority.

## 1. Protected deployment resource

The exact physical `PUBLIC_ROOT/.htaccess` is deployment-owned and absolutely immutable to COPOT. Backup & Recovery may read, inspect, fingerprint, and verify routing compatibility, but may not create, overwrite, rename, remove, restore, rollback, or cleanup-mutate this resource.

Every COPOT-controlled recovery mutation path must reject protected-resource mutation before side effects. Historical evidence referencing the resource remains immutable and readable but grants no current mutation permission. Prohibited work cannot be silently skipped or counted as completed; existing original-byte guarantees remain effective for permitted package-owned resources. Relevant unresolved protected-resource blockers prevent success claims under each owning lifecycle's actual state model.

## 2. Canonical recovery scope binding

Current attempt-aware recovery identity, immutable manifest, artifacts, mutable recovery lifecycle, journal, and settlement evidence must bind the applicable canonical scope: verified installation, operation and attempt, recovery identity, applicable database identity/namespace, verified owned physical tableset/fingerprint, and derived scope fingerprint. Scope components need not each occupy a separate manifest field if an authoritative verifier can independently reconstruct and compare the complete canonical identity. Unverifiable opaque hashes are insufficient.

**Historical exception boundary:** historical evidence is verified under the identity contract valid when it was created. It is not retroactively required to contain today's scope representation. Such evidence may confer current attempt-aware authority only through an explicit proven compatibility binding; otherwise preserve it and block unsupported current mutation/resume authority without rewriting or deleting evidence.

Foreign installation, namespace, tableset, operation, plan or attempt identity cannot be borrowed to establish a current recovery set.

## 3. Operation and attempt isolation

For attempt-aware operations, capture, manifest/artifact, recovery lifecycle, action journal and settlement proof must bind exact persisted lifecycle operation and immutable execution attempt. Same-operation different-attempt records remain distinct. Stale, duplicate, cross-attempt or ambiguous evidence fails closed wherever it could authorize capture, READY resume, mutation, restore, cleanup or release. Historical null-attempt forms are preserved; never silently normalized, rewritten, reassigned or promoted to current attempt identity.

## 4. Authoritative pre-capture negative proof

Backup & Recovery owns the authoritative negative-proof result consumed by Package Lifecycle before `PRE_CAPTURE_ABANDONED`. Inspect under applicable installation exclusion all relevant recovery-set namespaces, manifests, artifacts/metadata, mutable lifecycle records, action journals, and operation/attempt cross-references; classify residue and integrity including orphan, manifestless, unreadable, stale or ambiguous evidence.

The proof must establish absence of conflicting capture, manifest/artifact, recovery lifecycle or mutation-crossing obligation **for the exact operation and applicable attempt**, and prove the inspected storage/scope boundary is complete and applicable.

Classify evidence into:
- evidence bound to the target operation/attempt, which must satisfy the no-obligation condition;
- foreign evidence proven independent of the target, which does not itself block abandonment;
- unclassified, contradictory or ambiguous residue capable of affecting the decision, which must fail closed.

A matching-manifest miss alone never proves safe absence. The immutable proof binds installation/scope, operation/attempt classification, inspected boundary identity, evidence/result and creation time. Persist and independently read back the proof before Package Lifecycle terminalizes and clears its active operation. Caller-supplied freshness or local lifecycle-field checks alone are insufficient; Package Lifecycle consumes Recovery-owned proof without implementing recovery-store enumeration.

## 5. First-capture consistency

First-capture requires persisted authoritative lifecycle operation and immutable attempt where applicable, verified installation/database scope and owned tableset, exact immutable plan and independently verified pre/post-composition database state. Existing installation mutex covers preflight and capture handoff; the same held lock may be adopted, never silently replaced by a nested competing mutex.

Mismatch before mutation fails closed; uncertainty beyond the mutation boundary retains recovery-required semantics. Guard admission does not prove database quiescence; scoped consistency evidence, DatabaseQuiescenceCapability and specifically accepted transitional mechanisms retain independent obligations.

## 6. READY resume and recovery-specific settlement

READY resume only reconstitutes an existing valid recovery lifecycle with exact identity/attempt/plan/target binding, complete integrity-verified artifacts, applicable canonical scope evidence, fresh Guard exclusion, exclusive/adopted lock and independent database consistency. It cannot fabricate recovery state, borrow another attempt, overwrite uncertain evidence, or waive required consent and permit gates.

Backup & Recovery supplies a **recovery-specific settlement proof** to the Guard's consumer-neutral release eligibility policy. The proof binds recovery/manifest/operation/attempt/applicable scope and current recovery outcome, and establishes discharge of **the recovery obligations relevant to that operation**. A legitimate no-mutation/capture-withdrawal or other canonical pre-mutation closure can settle recovery obligations without actual restore, provided its owning lifecycle permits this and no uncertainty remains. Where restoration is required, actual restoration and verified domain outcomes are mandatory. Guard does not acquire authority over recovery-specific settlement; RecoveryLifecycleCoordinator is not a universal Guard release owner.

## 7. Restore, cleanup, and honest terminal states

Outstanding RESTORE_REQUIRED or RESTORE_INDETERMINATE obligations, failed integrity/verification, post-mutation uncertainty, unresolved durable capture, or prohibited protected-resource mutation must remain visible and enforceable. RESTORED requires actual recovery and verified applicable domains. CLEANUP_PENDING and CLEANED retain their own recovery cleanup meanings; immutable history/provenance survives permitted cleanup.

`RESTORED`, `COMPLETE`, `CLOSED`, `CLEANUP_PENDING` and `CLEANED` are **not interchangeable states of a single state machine**. Each owning Package Lifecycle, Backup & Recovery or cleanup authority may report success only according to its own applicable terminal requirements; no authority may claim successful terminal disposition while a relevant protected-resource blocker or recovery obligation remains unresolved.

## 8. Historical compatibility

Historical v1/v2 manifests, original-byte evidence, artifacts and lifecycle records remain immutable and subject to their original accepted identity contracts. Read/preserve through a bounded legacy-compatible path; allow historical recovery only when the original recovery identity, operation, physical domains and available scope evidence can be verified under that historical contract. Current attempt-aware mutation/resume/abandonment/release may consume historical evidence only under an explicit independently verified compatibility binding; otherwise it is retained and current authority is blocked.

Do not normalize, silently reinterpret, scavenge, overwrite or delete historical evidence. Preserve unresolved records for bounded recovery/operator disposition; do not misclassify them as an empty safe store.

## 9. Acceptance boundaries

Before contract promotion, the normative interface/invariants must clearly specify canonical current scope verification, operation/attempt isolation, Recovery-owned negative-proof classification and consumption, legacy compatibility, READY resume, recovery-specific settlement, exact protected-resource immutability and truthful per-authority terminal outcomes.

Separate implementation validation must cover cross-attempt/foreign evidence, orphan/manifestless residue, negative-proof interruption/retry, first-capture consistency, READY resume, Guard-release proof, exact `.htaccess` byte preservation across capture/restore/rollback/cleanup, original-byte recovery for permitted domains, other-installation isolation, historical v1/v2 and WU6 regressions. Source inspection alone supplies no operational acceptance.

## 10. Exclusions and continuity

No parallel recovery engine, second Guard, new lifecycle authority, mandatory serialization format, full-site or scheduled backups, retention product, cloud backup, Admin backup UI, Server-Empty Bootstrap, Module legacy adoption, or operational reconciliation authorization. Promotion requires separate GPT/user approval; this candidate does not authorize deployment, Alternative C continuation, or Slice 3.
