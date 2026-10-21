# COPOT — Legacy-to-V3 Architecture Injection Contract
Date version: 2026-10-17 20:16:08 WIB

Status: ACCEPTED / PROMOTED — NORMATIVE BOOTSTRAP AMENDMENT
Authority: Bounded amendment to existing Package Lifecycle v3 and Backup & Recovery contracts; not a parallel engine, replacement of historical v1/v2 semantics, operational mutation authorization, release, or deployment.
Provenance: User-directed promotion of `precontracts/copot_legacy_to_v3_architecture_injection_precontract.md` (verified blob `407f782db4d805ca6b852b0b687a35e224d29f08`), technical pre-contract review and isolated historical restore rehearsal.

## 1. Companion authority and scope
This amendment applies together with:
- `docs/28_package_lifecycle_migration_foundation_contract.md`;
- `docs/31_backup_recovery_foundation_contract.md`;
- `docs/canonical_target_inventory_fingerprint_contract.md`;
- `docs/shared_destructive_plan_confirmation_operator_input_contract.md`.

Those accepted requirements prevail for canonical v3 manifest grammar, full/partial coverage, package identities, destructive consent, original-byte recovery, health/integrity finalization and durable committed state. This document adds the legacy-to-v3 entry and target-specific compatibility gates only. No ordinary v3 UPDATE may falsely claim a legacy installation already has a committed `SourceInventoryReference`.

Initial bounded testbed: `85eb6a4c`; separate APP_ROOT `C:\xampp\copot-sdr\85eb6a4c`, PUBLIC_ROOT `C:\xampp\htdocs\85eb6a4c`, installation `inst_2781ec5ec5ed67f18d9e1e5bb972536f`, Webcore `0.13.0`, database `127.0.0.1:3306/copot_sdr_85eb6a4c`. Verify these identities again before execution; labels are not identity authority. `dev` and `97e03b5-after` are protected and out of mutation scope. Native XAMPP configuration remains unchanged.

## 2. Explicit bootstrap entry and pre-state
A named **LEGACY_TO_V3_BOOTSTRAP** operation is distinct from an ordinary manifest-v3 update/apply. Exact persisted enum and integration naming are bounded implementation details, but the semantic operation type must be durable, inspectable, and non-ambiguous.

Before mutation, the bootstrap classifies target installation identity, installed marker, legacy package/release evidence, committed lifecycle state, effective database/schema baseline and migration ledger. An empty `core_migration_history` is not automatically a defect: it is eligible only if the canonical baseline classifier accepts the observed schema and ledger identity. Blank `DB_NAMESPACE` is not itself a reason to rewrite configuration when the effective database namespace is correctly proven.

Capture a complete pre-mutation live-tree inventory with roots, canonical paths, byte sizes, hashes and source provenance. Preserve it durably as an immutable operation-bound **provisional bootstrap source artifact**, linked to operation, installation, plan, target and recovery identities. A live scan or legacy `.copot/package.json` may supply evidence candidates but never proves historical Git authority or confers package ownership by itself. No source-inventory artifact is committed as v3 authority before finalization.

## 3. Candidate target and collision discipline
A concrete canonical manifest-v3 package and verified payload/target-inventory evidence are required before pre-mutation eligibility may pass. `full` coverage must deliver all resulting package-owned bytes. `partial` coverage, explicit removals and source-relative retained ownership require the canonical committed source authority and proof prescribed by the companion contract; a provisional bootstrap inventory cannot silently substitute for it.

Classify every relevant physical destination within the separate APP_ROOT/PUBLIC_ROOT mappings:
- `ADD`: absent destination with valid delivered bytes and proven destination ownership;
- `PRESERVE`: retained bytes unchanged, with compatible verified authority; this never waives required full coverage;
- `REPLACE`: exact planned package-owned replacement with proven existing ownership and verified original-byte recovery;
- `BLOCK`: ownership ambiguity, unexpected collisions, type/path conflict, stale source or integrity/compatibility failure;
- `UNVERIFIED`: missing evidence, non-executable.

Unknown legacy/operator files outside the approved change set remain untouched; unknown files colliding with approved destinations are `BLOCK`, not implicitly adoptable. No arbitrary cleanup, replacement or removal. Before any `REPLACE`, `REMOVE` or `RENAME`, the existing Backup & Recovery authority must capture, durably record and reread the original bytes and bind operation/plan/action ordinal/recovery identities. Where v3 destructive consent is applicable, use the accepted typed shared confirmation contract.

## 4. Recovery and manual fallback
Canonical bootstrap mutation must use existing Backup & Recovery ownership, immutable recovery-set publication and lifecycle/action journal linkage. A legacy historical filesystem/database snapshot is **not** a canonical `RecoveryManifest` and must not be rebranded or rewritten in place. Operation-specific canonical capture is required before first live mutation; no second recovery engine, synthetic recovery completion or direct-write restore path is created.

Testbed historical manual fallback evidence: APP_ROOT historical 592/592 match; PUBLIC_ROOT 127/128 match with expected rename-only `.htaccess` routing adjustment; logical SQL restored in an isolated rehearsal with schema/data and installation/lifecycle evidence matching historical state. Rehearsal remains `PARTIAL` because isolated Apache HTTP smoke was not run. Direct physical MariaDB data-directory copy is rejected; a scoped, controlled logical SQL restore with necessary session foreign-key guards is the only evidenced database recovery route.

The user accepts loss of **only** the observed `users.last_login_at` login-history drift (historical `2026-10-12 11:30:15`, live `2026-10-17 18:16:34`) as non-critical for this disposable testbed. This acceptance does not waive other future data drift, database consistency checks, or canonical recovery obligations. Preserve historical snapshots/manifests unchanged; any manual fallback must use the correct target-specific roots/database, explicit routing correction, safe isolated schema handling and independent applicable-domain verification. Do not claim `RESTORED` solely from rehearsal, backup existence or identity matches.

## 5. Guard, interruption and finalization
Acquire the installation-scoped mutex, durably record bootstrap operation identity and enter effective maintenance/application-write protection before protected mutation. Reject conflicting lifecycle operations; retain truthful non-terminal state upon incomplete or indeterminate bootstrap. The minimum application guard and conditional database quiescence remain separate capabilities. Database consistency/capture may demand quiescence, but global DB quiescence is not imposed merely because the application guard is active.

No retrospective construction of pre-mutation source bytes after an uncertain write. Interrupted operations reconcile immutable bootstrap inventory, actual live bytes, recovery manifest, operation/plan identity and action journal before any bounded retry; never blind-replay removals. Preserve existing `BLOCKED`, `INDETERMINATE`, recovery-required and `AWAITING_WU6` semantics as applicable.

Successful WU6 health/integrity verification must cover applicable runtime, filesystem fingerprints, target inventory, database/migrations, module/theme/public/Admin behavior, operation and recovery linkage. Only after those checks succeed may canonical source-inventory content be durably published and `SourceInventoryReference` committed, followed by authorized lifecycle/maintenance closure. A published but unreferenced artifact is not authoritative, and failure cannot be labeled finalized.

## 6. Separate acceptance and execution gates
1. **CONTRACT ACCEPTANCE:** this normative amendment is accepted/promoted by the user. This gate records architecture policy, not an executable plan.
2. **PRE-MUTATION READINESS:** independent validation of current target identity, exact v3 candidate manifest/payload, dependency closure, database baseline classification, physical collision and ownership matrix, application guard, operation-specific canonical recovery capture, original-byte evidence, and applicable destructive authorization. Missing evidence blocks mutation.
3. **OPERATIONAL ACCEPTANCE:** only after separately authorized controlled injection, authentic WU6 finalization and applicable interruption/recovery tests. Neither contract promotion nor isolated rehearsal proves this gate.

Technical implementation may refine field layout and interfaces without weakening these invariants. Technical proposals requiring new ownership authority, expanded runtime scope, new recovery engine, or different v1/v2 semantics require separate acceptance. `dev`, `97e03b5-after`, native XAMPP configuration, full Maintenance Management, releases, checkpoint/tag/package publication and unrelated implementation are excluded.

## 7. Promotion disposition
`CONTRACT ACCEPTED / BOOTSTRAP IMPLEMENTATION NOT STARTED / PRE-MUTATION READINESS BLOCKED / OPERATIONAL ACCEPTANCE NOT STARTED`.

## 8. Shared-runtime protection amendment
Date version: 2026-10-18

`LEGACY_TO_V3_BOOTSTRAP` eligibility must prove two distinct protections:

- installation-scoped application-write protection, including Maintenance
  Guard ownership, verified in-flight drain and exclusion of relevant HTTP,
  CLI, scheduled and background writers; and
- namespace-scoped database consistency bound to the target installation's
  canonical physical tableset.

Neither namespace identity nor an active application guard alone proves the
other capability. If writer isolation, drain, tableset ownership or database
consistency is unknown, incomplete or ambiguous, bootstrap remains
`BLOCKED` before mutation. Existing `ADD`, `PRESERVE`, `REPLACE`, `BLOCK` and
`UNVERIFIED` collision classifications are unchanged; this amendment grants
no ownership from a live scan, legacy metadata or namespace string.

The provisional source inventory, recovery artifact, operation, plan, action
journal and installation identity must carry the same scoped database and
tableset linkage where database evidence is applicable. Provisional evidence
must not become `SourceInventoryReference` or committed v3 state before the
existing WU6 health, integrity and finalization gates succeed. Interruption
continues to use the existing `BLOCKED`, `INDETERMINATE`, recovery-required
and `AWAITING_WU6` semantics.

Existing server-level or server-wide consistency mechanisms remain available
as controlled transitional profiles only when their blast radius, ownership
and authorized scope are proven. The progressive policy is inventory, risk
classification, scoped/custom replacement, equivalence validation and later
retirement; it is not a blanket prohibition and does not automatically expand
WU3 or create a separate recovery engine. WU3 may consume only an already
proven protection capability; provider implementation and project-wide
mechanism rationalization remain separately bounded work.

## 9. Maintenance Guard authority clarification

The installation-scoped Maintenance Guard is an application-level write-freeze
for the current COPOT installation. Its enforcement boundary is the verified
installation identity, canonical database namespace and owned physical
tableset. Bootstrap must prove admission, in-flight drain and exclusion for
all relevant COPOT-owned writer paths that enter that boundary, including the
HTTP application, installer, CLI, scheduled, background and any future COPOT
entrypoint explicitly registered with the guard. An unregistered or
unprotected COPOT writer remains a pre-mutation `BLOCKED` condition.

The Maintenance Guard is not a MariaDB or server-wide lock. It does not block,
intercept or claim authority over direct writes from external applications,
DBA sessions, privileged users, `root`, or third-party database writers. It
must not interfere with another installation that shares a MariaDB instance or
database. The inability to control those external writers is a **KNOWN
LIMITATION / NOT A DEFECT** of the current installation-scoped boundary and a
possible future refinement outside this bootstrap scope.

The limitation does not waive the independent namespace-scoped database
consistency requirement. An active Maintenance Guard is not evidence that
concurrent external writes or DDL are absent, and namespace identity alone is
not a consistency proof. Bootstrap may use scoped protection only when the
independent database capability proves the target database identity, canonical
physical tableset, schema stability, data and migration-ledger integrity, and
applicable safety against the observed external-writer boundary. Missing,
stale, incomplete or ambiguous proof remains `BLOCKED` before mutation.

This clarification preserves the existing immutable RecoveryManifest,
original-byte evidence, operation/action-journal linkage, interruption,
recovery-required, `AWAITING_WU6`, v1/v2 compatibility and WU6 finalization
semantics. It does not expand COPOT ownership, select a server-wide mechanism
automatically, or create a parallel recovery engine.

## 10. Separation from committed source-authority reconciliation

`COMMITTED_SOURCE_AUTHORITY_RECONCILIATION` is separate from
`LEGACY_TO_V3_BOOTSTRAP` and applies only to a valid committed lifecycle
installation whose `SourceInventoryReference` is absent. It must not use the
legacy classifier, broaden `isLegacyCandidate()`, or treat provisional
bootstrap evidence as committed source authority.

Bootstrap remains governed by its existing legacy eligibility, provisional
inventory, recovery, Guard, scoped database-consistency, and WU6 requirements.
The committed-authority route has its own durable operation identity and exact
manifest/target/ownership evidence while reusing canonical lifecycle,
recovery, database, Guard, and WU6 authorities.

Authority-only reconciliation may avoid package-owned filesystem mutation when
the target already matches the trusted manifest, but it is not an implicit
readiness result. Unresolved `BLOCK` or `UNVERIFIED` collision evidence,
missing provenance, stale identity, incomplete recovery linkage, or failed WU6
verification remains fail-closed. `SourceInventoryReference` is published only
by the existing successful WU6 finalization boundary.
