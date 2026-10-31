# COPOT — Webcore Installation-Scoped Maintenance Guard Foundation Pre-contract

Status: DRAFT / NON-NORMATIVE / SEMANTICALLY REVIEWED / TECHNICAL DELTA PENDING
Workstream: CR-1 — Platform Contract Reconciliation
Source provenance: CR-1 S2 semantic draft, refined during S3 targeted source review.
Authority: Planning artifact only. No implementation, runtime mutation, contract promotion, or Alternative C closure authority.

## 1. Purpose and ownership

Maintenance Guard Foundation is a reusable, Webcore-owned capability providing installation-scoped application-write protection for authorized COPOT operations. Package Lifecycle V3, Backup & Recovery, Legacy-to-V3 Injection, and future authorized capabilities share one foundation. The Guard does not own package mutation, recovery, finalization, release, or consumer/operator authorization. Consumers must not create competing guards or parallel recovery coordinators.

## 2. Protection scope

Protection binds verified installation identity, relevant canonical database namespace and owned physical tableset where database writes matter, and authorized operation/execution context. Other COPOT installations, even on a shared MariaDB server/database, remain unaffected. Identity ambiguity blocks mutation admission.

## 3. Writer registration and admission

Relevant COPOT-controlled HTTP, Admin, API, installer, CLI, scheduled, background and worker write entrypoints must enter the common protection boundary. Unknown, unregistered, or unguarded writers capable of affecting the protected installation block verified readiness. Backend enforcement, not UI-only disabling, is required. Complete entrypoint coverage must be evidenced.

## 4. Semantic conditions

NORMAL, REQUESTED, DRAINING, READY, ACTIVE, and RECOVERY_REQUIRED describe required semantic distinctions, not mandated persisted enums. READY requires fresh writer exclusion and drain proof; ACTIVE identifies a protected authorized operation; RECOVERY_REQUIRED blocks unsafe release. Guard state, lifecycle phase, recovery state, mutex ownership and database consistency must remain distinguishable.

## 5. Drain and execution exception

Drain must account for queued jobs, executing jobs, active writes and transactions, registered scheduled/background work, and unknown writers; an empty queue is insufficient. Scoped executor exceptions require exact installation, operation, execution identity, protected session, and applicable package/plan/recovery/database evidence. Guard exceptions never grant mutation rights or override protected-resource immutability, destructive confirmation, or independent recovery and consistency gates.

## 6. Durability and recovery

Protection obligations must survive interruption where required. Resume reconciles durable evidence with actual ownership and outstanding recovery obligations. Unknown or contradictory evidence fails closed. New operations may not displace unresolved obligations. Manual release is prohibited during mutation, required recovery, or mandatory cleanup.

## 7. Independent controls

Maintenance Guard, InstallationMutex, and DatabaseQuiescenceCapability are independent. An active Guard neither proves stable database snapshots nor controls direct external, DBA, root, or third-party database writers. Existing authorized scoped and transitional server/database quiescence mechanisms remain available under docs/31.

## 8. Consumer integration and observation

Package Lifecycle consumes mutation readiness; Backup & Recovery retains capture/restore/verification authority; Injection reuses both without its own guard. System Health/Dashboard may read blockers, evidence freshness, target and operation identity, and release eligibility without acquiring transition authority.

## 9. Required technical refinements

- MGF-R01: Enumerate and verify every relevant writer-entrypoint enforcement path; test negative bypass.
- MGF-R02: Reconcile in-flight leases and process ownership after abnormal termination; never reset counters merely to infer READY.
- MGF-R03: Verify outstanding lifecycle, recovery, and cleanup obligations before protection release; session close is not recovery closure.

## 10. Acceptance and exclusions

Focused acceptance must cover writer coverage, queue/in-flight drain, scoped exceptions, crash/restart/retry, safe release, other-installation isolation, existing mutex/quiescence regressions, accurate read-only status, and applicable v1/v2/v3 recovery invariants. Full Maintenance Management UI, delegation, scheduling, installation backup portability, retention, global DB locks and generic jobs are deferred/independently governed. No source code or runtime acceptance is claimed.

## 11. Promotion

Technical Executor reviews this exact persisted payload and proposes bounded technical deltas. User approval is required for authoritative contract promotion; file persistence alone changes no existing contract or Alternative C status.
