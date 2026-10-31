# COPOT — Webcore Installation-Scoped Maintenance Guard Foundation Contract Candidate

Status: REVIEWED CANDIDATE / NON-NORMATIVE / PROMOTION PENDING
Workstream: CR-1 — Platform Contract Reconciliation
Source provenance: S2 pre-contract and S3 source/technical reconciliation. This is the active candidate revision; the pre-contract is retained for provenance.
Authority boundary: This candidate is not an authoritative contract and grants no implementation, repository/runtime mutation, release, or Alternative C closure authority.

## 1. Purpose and capability ownership

Maintenance Guard Foundation is one reusable Webcore-owned capability protecting a verified COPOT installation against conflicting COPOT-controlled writes during separately authorized operations. Package Lifecycle, Backup & Recovery, Legacy-to-V3 Injection and future authorized consumers reuse this boundary. Guard ownership does not confer package apply, restoration, lifecycle finalization, operator approval, or release authority; no independently competing Guard may be established.

## 2. Identity and isolation

Protection binds verified installation identity and applicable operation context. Database-affecting operations also bind canonical database namespace and verified owned physical tableset. Shared MariaDB instance or database does not imply shared maintenance ownership. Ambiguous installation/namespace/operation binding blocks mutation. Other installations remain unaffected.

## 3. Writer registration and admission

All relevant COPOT-controlled conflicting write entrypoints, including HTTP, Admin, API, installer, CLI, scheduled, background, and worker surfaces, must participate in one backend-enforced admission boundary. Every writer classification maps to actual entrypoints, target installation, permitted operation type, and mechanism. Unregistered or unguarded conflicting writers prevent readiness. UI disablement and declared writer-class strings alone are not coverage proof.

## 4. Semantic protection states

Required distinguishable semantic conditions: NORMAL (no obligation), REQUESTED, DRAINING, READY (verified current exclusion and drain), ACTIVE (authorized protected operation), and RECOVERY_REQUIRED (unresolved protection/recovery obligation). They are not mandatory storage enums. Persisted flags are insufficient proof of READY. Guard condition differs from Package Lifecycle phase, Recovery Lifecycle state, process-local mutex ownership, and independently verified database consistency.

## 5. Drain, accounting and evidence

Admission and drain transitions are synchronized through one installation-scoped exclusion mechanism. Evidence accounts for queued, executing, and in-flight COPOT writers; an empty queue cannot prove drain. Evidence must be fresh, identity-bound, and replay-safe. Unknown or stale evidence fails closed.

**MGF-R01 — Writer coverage.** Maintain an auditable complete entrypoint inventory and applicable bypass-rejection tests.

**MGF-R02 — Crash-safe drain.** Abnormal termination must not silently discharge outstanding leases. Durable counters and ownership are reconciled with independent execution evidence before returning to READY; no unsafe counter normalization.

## 6. Scoped executor exceptions

A consumer exception requires separately authorized installation, actor/execution identity, operation, protection session, and relevant plan, package, recovery, and database bindings. Its permission is bounded to that operation. It never bypasses resource immutability, destructive confirmation, consistency proofs, or recovery requirements. Guard does not independently authorize mutation.

## 7. Durable interruption and resumption

Durable protection obligations remain verifiable across crashes and restarts. Resume checks persisted operation identity, exclusive process ownership, evidence freshness, and outstanding recovery linkage. New sessions cannot silently supersede stale or foreign protection. Unknown, missing, ambiguous or contradictory evidence blocks unsafe continuation; recovered process-local ownership never fabricates a recovery lifecycle record.

## 8. Recovery-bound release

**MGF-R03 — Safe release.** Release eligibility requires verified discharge of applicable operation, recovery, and terminal cleanup obligations. Ordinary session closure, maintenance leave, or manual unlock cannot by themselves prove recovery closure. Backup & Recovery continues owning capture, restore, verification and authorized cleanup. Failed/interrupted mutation cannot be disguised as safe normal state.

## 9. Independent protection mechanisms

Maintenance Guard prevents conflicting COPOT-controlled writes; InstallationMutex provides existing execution exclusion; DatabaseQuiescenceCapability and separately proven scoped database consistency serve database recovery requirements. None implies the others. External/privileged MariaDB writers are outside Guard enforcement, a known limitation rather than a waiver of consistency gates. Authorized transitional server-level mechanisms remain subject to separate blast-radius validation.

## 10. Consumer integration and observation

Consumers reuse the shared boundary without competing lifecycle/recovery ownership. Read-only observers may report installation, operation, protection condition, blockers, evidence freshness and safe-release eligibility, without conferring state transition or mutation authority.

## 11. Source-based implementation status (non-normative)

Observed in E4 source: InstallationScopedMaintenanceGuard scoped state and admission methods; installer HTTP POST and selected CLI command admission; application write lease; scoped evidence revalidation; RecoveryLifecycleCoordinator resume/readmission; PackageLifecycleFactory composition; separate InstallationMutex and DatabaseQuiescenceCapability. This does **not** prove complete coverage, crash-safe in-flight reconciliation, recovery-bound safe release, cross-installation isolation or end-to-end interruption acceptance. S3 findings MGF-R01/R02/R03 remain implementation verification gaps, not exceptions to required behavior.

## 12. Acceptance criteria

Focused evidence must prove complete writer entrypoints, denial of bypass, proper drain and scoped exceptions, abnormal termination/restart/retry, recovery-bound release, independent quiescence, target installation isolation, truthful status, and no regression of applicable Package Lifecycle v1/v2/v3, Backup & Recovery and Injection invariants. Presence of methods or source-only review is not operational acceptance.

## 13. Explicit exclusions

No full Maintenance Management UI, delegated permission/role product, scheduler/retention, installation portability backup ZIP, global DB locking, generic job engine, new recovery coordinator, or independent mutation authority. Deferred capabilities require independent gates.

## 14. Promotion and continuity

Promotion requires distinct GPT/user review and approval, reconciliation of consumers docs/28, docs/31, docs/30 and accepted Legacy-to-V3 Injection contract, plus technical compatibility evidence. Promotion does not authorize deployment or advance Alternative C.
