# COPOT — Webcore Installation-Scoped Maintenance Guard Foundation Contract Candidate

Status: REFINED REVIEWED CANDIDATE / NON-NORMATIVE / PROMOTION PENDING
Technical disposition: CR-1 SLICE 1 SEMANTICALLY RECONCILED / MATERIALIZED; IMPLEMENTATION VALIDATION PENDING
Workstream: CR-1 — Platform Contract Reconciliation
Source provenance: S2 pre-contract, S3 source/technical reconciliation, and CR-1 Slice 1 Codex technical proposal with GPT semantic refinements. This is the active candidate revision; the pre-contract is retained for provenance.
Authority boundary: This candidate is not an authoritative contract and grants no implementation, repository/runtime mutation, release, or Alternative C closure authority.

## 1. Purpose and capability ownership

Maintenance Guard Foundation is one reusable Webcore-owned capability protecting a verified COPOT installation against conflicting COPOT-controlled writes during separately authorized operations. Package Lifecycle, Backup & Recovery, Legacy-to-V3 Injection and future authorized consumers reuse this boundary. Guard ownership does not confer package apply, restoration, lifecycle finalization, operator approval, or release authority; no independently competing Guard may be established.

## 2. Canonical identity and isolation

Protection binds verified installation identity, operation identity/type, execution-attempt identity when applicable, durable protection-session and current evidence identities. Database-affecting operations also bind canonical database identity, namespace, and verified owned physical tableset. A derived opaque identity is acceptable only when the canonical scope can be independently reconstructed and verified. Admission, resume, consumer settlement and release must revalidate the same applicable scope. Shared MariaDB instance or database does not imply shared maintenance ownership; ambiguous, stale or cross-installation binding blocks mutation and release. Other installations remain unaffected.

## 3. Writer registration and admission

All relevant COPOT-controlled conflicting write entrypoints, including HTTP, Admin, API, installer, CLI, scheduled, background, and worker surfaces, must participate in one backend-enforced admission boundary. An auditable inventory, registry, generated map or technically equivalent completeness proof must map implemented entrypoints to classification, admission mechanism, target installation, operation and applicable attempt. Absent or deferred surfaces must be explicitly distinguished from implemented ones. Unknown, unregistered, stale or bypassed conflicting writer paths fail closed. UI disablement, declared writer-class strings and method existence alone are not coverage proof.

## 4. Semantic protection states

Required distinguishable semantic conditions: NORMAL (no obligation), REQUESTED, DRAINING, READY (verified current exclusion and drain), ACTIVE (authorized protected operation), and RECOVERY_REQUIRED (unresolved protection/recovery obligation). They are not mandatory storage enums. Persisted flags are insufficient proof of READY. Guard condition differs from Package Lifecycle phase, Recovery Lifecycle state, process-local mutex ownership, and independently verified database consistency.

## 5. Drain, accounting and evidence

Admission and drain transitions are synchronized through one installation-scoped exclusion mechanism. Evidence accounts for queued, executing, and in-flight COPOT conflicting writers; an empty queue or zero aggregate counter cannot alone prove drain. Each COPOT-controlled writer admitted into the protected conflicting-write domain must carry durable, independently reconcilable in-flight lease evidence, including its writer identity/class, canonical installation, relevant operation/attempt and protection session, acquisition and settlement. Abnormal termination does not silently discharge a lease; independently verified reconciliation must establish a safe terminal no-write condition before READY. Counter reset or decrement without such proof is prohibited. Read-only and non-conflicting activities do not automatically require these leases. Evidence must be fresh, identity-bound, and replay-safe. Unknown or stale evidence fails closed.

**MGF-R01 — Writer coverage.** Maintain an auditable complete entrypoint or equivalent coverage proof, installation/operation binding, and positive/negative admission and bypass-rejection tests.

**MGF-R02 — Crash-safe drain.** Abnormal termination must not silently discharge outstanding leases. Durable lease identity, ownership and settlement are reconciled with independent execution evidence before READY or release; no unsafe counter normalization. Storage format and process-tracking mechanism remain implementation choices.

## 6. Scoped executor exceptions

A consumer exception requires separately authorized installation, actor/execution identity, operation, protection session, and relevant plan, package, recovery, and database bindings. Its permission is bounded to that operation. It never bypasses resource immutability, destructive confirmation, consistency proofs, or recovery requirements. Guard does not independently authorize mutation.

## 7. Durable interruption and resumption

Durable protection obligations remain verifiable across crashes and restarts. Resume and READY readmission revalidate canonical installation/database scope, operation and attempt, durable protection session, current evidence/freshness, unsettled leases, exclusive lock ownership or explicit adoption, and applicable consumer recovery linkage. New sessions cannot silently supersede stale or foreign protection. Unknown, missing, ambiguous or contradictory evidence blocks unsafe continuation; recovered process-local ownership never fabricates a recovery lifecycle record.

## 8. Recovery-bound release

**MGF-R03 — Safe release.** Guard owns a shared, consumer-neutral release eligibility policy, not consumer-specific settlement authority. Release requires matching canonical scope/session/evidence, reconciled applicable writer leases, fresh protection evidence, and settlement evidence from **each consumer with an outstanding obligation for the protected operation**, never unrelated consumers. Applicable mutation, lifecycle, recovery, verification and cleanup ambiguity blocks release. Each consumer owns its relevant terminal proof: Package Lifecycle retains apply/migration/WU6 settlement; Backup & Recovery retains capture/restore/verification/cleanup settlement; Injection consumes established authorities. Release decisions must be independently verifiable. RecoveryLifecycleCoordinator is not the universal Guard release owner. Ordinary session closure, maintenance leave, process exit, manual unlock or zero counter cannot prove safe release. Failed/interrupted mutation cannot be disguised as safe NORMAL state.

## 9. Independent protection mechanisms

Maintenance Guard prevents conflicting COPOT-controlled writes; InstallationMutex provides existing execution exclusion; DatabaseQuiescenceCapability and separately proven scoped database consistency serve database recovery requirements. None implies the others. External/privileged MariaDB writers are outside Guard enforcement, a known limitation rather than a waiver of consistency gates. Authorized transitional server-level mechanisms remain subject to separate blast-radius validation.

## 10. Consumer integration and observation

Consumers reuse the shared boundary without competing lifecycle/recovery ownership. Read-only observers may report installation, operation, protection condition, blockers, evidence freshness and safe-release eligibility, without conferring state transition or mutation authority.

## 11. Source-based implementation status (non-normative)

Observed in E4 source: InstallationScopedMaintenanceGuard scoped state and admission methods; installer HTTP POST and selected CLI command admission; application write lease; scoped evidence revalidation; RecoveryLifecycleCoordinator resume/readmission; PackageLifecycleFactory composition; separate InstallationMutex and DatabaseQuiescenceCapability. This does **not** prove complete coverage, crash-safe in-flight reconciliation, recovery-bound safe release, cross-installation isolation or end-to-end interruption acceptance. S3 findings MGF-R01/R02/R03 remain implementation verification gaps, not exceptions to required behavior.

## 12. Acceptance criteria

Contract compatibility review must establish consistent authority, interfaces and invariants across Guard, Package Lifecycle, Backup & Recovery and Injection. Subsequent implementation acceptance must separately prove writer coverage/bypass denial, scoped drain and exceptions, durable lease crash/restart reconciliation, canonical-scope isolation, consumer-relevant release proofs, independent quiescence, truthful status and no regression of v1/v2/v3 or recovery invariants. Outstanding tests remain implementation validation obligations, not assumed successes. Presence of methods or source-only review is not operational acceptance.

## 13. Explicit exclusions

No full Maintenance Management UI, delegated permission/role product, scheduler/retention, installation portability backup ZIP, global DB locking, generic job engine, new recovery coordinator, or independent mutation authority. Deferred capabilities require independent gates.

## 14. Promotion and continuity

Promotion requires distinct GPT/user review and approval and normative interface/invariant compatibility across docs/28, docs/31, docs/30 and accepted Legacy-to-V3 Injection contract. End-to-end runtime acceptance remains a separate implementation gate and is not represented as already passed. Promotion does not authorize deployment or advance Alternative C.

## 15. Coordinated authority adoption (pre-promotion clarification)

This candidate is the proposed **standalone Webcore-owned Guard foundation authority**, not a replacement for existing Backup & Recovery or Existing-Runtime Webcore Lifecycle Adoption contracts. Existing docs/31 Guard and database-quiescence clarifications remain in force until an accepted coordinated amendment explicitly reconciles them. The Guard does not inherit Recovery-owned terminal proof, Package Lifecycle WU6 authority, or deployment routing ownership. docs/28 and docs/31 consume its shared interfaces only once these interfaces are adopted together through a separately approved coordinated normative set. An earlier historical acceptance is not retroactively void merely because new lease/release requirements are proposed. Do not promote a unilateral Guard contract that leaves consumer release/admission interfaces unspecified or contradictory.

## 16. Shared release-evidence semantic interface (F-03)

Each Guard release decision consumes an independently verifiable **release-evidence envelope**. The canonical minimum comprises: verified installation and applicable database scope identity; protection-session identity; operation identity and immutable execution-attempt identity when applicable; consumer identifier and declared obligation class; consumer-owned terminal disposition and settlement-result identity; reconciled durable writer-lease set/result; applicable recovery-settlement identity; evidence provenance, integrity, observation/freshness boundary and decision time. Fields can be represented by linked immutable records rather than a universal storage schema, but their complete binding must be independently reconstructable, consistent and replay-safe.

The Guard must derive and verify the complete applicable consumer-obligation set from durable, scope-bound operation/protection-session obligation registration and independently verifiable lifecycle transitions; it must not infer completeness from whichever consumer records happen to be present. Registration must identify each applicable consumer obligation and its owner, and record authoritative no-obligation classification where a consumer domain was evaluated but never acquired an obligation (including valid pre-capture paths). Missing, stale, contradictory or unregistered obligation evidence fails closed. The Guard must derive the exact outstanding-consumer obligation set from that verified complete registration. It may release only when **every applicable obligation** has an owner-authenticated terminal settlement (including explicit authoritative no-obligation classification when applicable), all relevant leases are independently reconciled, scope/session/attempt cross-references match, and no stale, contradictory or ambiguous evidence remains. A missing consumer record is not equivalent to a settled obligation. The Guard validates envelopes and decides generic eligibility; it does not fabricate, reinterpret or override a consumer's domain-specific settlement. No WU6 or restore proof is demanded for operations where those obligations never applied. Source implementation and negative-path acceptance remain separate.
