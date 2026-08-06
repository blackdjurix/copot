# Existing-Runtime Webcore Lifecycle Adoption Contract

## Preparation status

```text
Existing-Runtime Webcore Lifecycle Adoption: ARCHITECTURE LOCKED
Implementation Unit 1: COMPLETE
Implementation Unit 2 — Legacy Webcore Runtime Reconciliation: WU1–WU3 COMPLETE; WU4+ NOT STARTED
Backup & Recovery Foundation: COMPLETE AND CLOSED
Module Package Lifecycle WU7 human/E2E acceptance: BLOCKED pending authoritative committed Webcore state
Server-Empty Bootstrap & Package Clean Install: DEFERRED / UNSCHEDULED — KEEP DEFERRED
```

This contract defines the smallest safe capability for converting an already
running legacy Webcore installation into authoritative committed lifecycle
state. It is a Webcore prerequisite for Module Package Lifecycle WU7
acceptance. WU1 establishes the non-mutating classification/planning decision
layer and WU2 establishes the recovery, confirmation, quiescence, and
mutation-eligibility boundary; production runtime reconciliation remains
**NOT STARTED**.

The current acceptance runtime is evidence for this contract, not an adoption
target. It is `LEGACY`, reports `0.8.0` through `storage/installed.lock`, has
no committed lifecycle state or Core migration-history ledger, and differs
from the inspected package evidence in six package-owned files. This current
XAMPP evidence is not production-grade reconciliation readiness; in
particular, `docs/31_backup_recovery_foundation_contract.md` records that its
optional MariaDB quiescence profile is not satisfied.

## Boundary and actions

Legacy handling is not hidden inside normal `INSTALL`, `REPAIR`, `PATCH`,
`UPDATE`, or `UPGRADE` planning. Two distinct actions are required:

1. **Adopt Existing Installation** — a non-mutating exact-match operation that
   establishes committed state only after every authoritative proof gate passes.
2. **Reconcile Legacy Installation** — a separately confirmed, potentially
   mutating convergence operation for a runtime that is not an exact match.

A committed runtime is never re-adopted as legacy. Both actions are explicit
operator actions, have durable operation identity, are idempotent after
success, and commit lifecycle state last. Failure before commit leaves the
runtime legacy or otherwise explicitly non-committed.

## Trusted target and exact-match adoption

IU2 reconciles toward an explicit trusted Webcore package. The intended current
target version is `0.13.0`, but `Version::CURRENT`, Git state, a timestamp, or
operator text alone is not trusted release/package identity. Git tags, GitHub
Releases, public publication, and remote distribution are not prerequisites
unless an existing authoritative source independently requires them.

Mutating reconciliation requires an authoritative package artifact carrying
the existing package/release identity, archive identity, package-owned
inventory, compatibility requirements, and migration contract defined by
`docs/28_package_lifecycle_migration_foundation_contract.md`.

Exact-match adoption is permitted only when that trusted package contract (or
another already-authoritative release source) proves all of the following:

- supported package type and manifest contract version;
- exact target compatibility with the existing runtime;
- trusted package/release identity, archive identity, and complete package-owned
  inventory;
- zero package-owned drift and complete separation from operator/runtime-owned
  paths;
- required WU6 health and integrity gates;
- authoritative canonical schema baseline;
- deterministic canonical migration-state identity without reconstructing or
  replaying historical migrations or marking unknown migrations applied;
- valid, target-compatible `storage/installed.lock`.

Version equality alone is never sufficient. Any mismatch rejects exact-match
adoption without mutation. Successful adoption records the trusted package's
release identity as the verified lifecycle target; it does not claim to recover
unknown historical provenance.

## Deterministic legacy classification

Before planning, IU2 must classify the existing database using only objective,
deterministic evidence. The supported classifications are exactly:

- **PROVABLE_CANONICAL_SCHEMA_BASELINE** — authoritative canonical schema
  evidence is proven;
- **KNOWN_MIGRATION_PREFIX** — an authoritative known migration prefix is
  proven and ordered forward migration is available;
- **UNKNOWN_OR_UNPROVABLE** — state cannot be proven and reconciliation fails
  closed;
- **COMMITTED_LIFECYCLE_STATE** — already lifecycle-managed state, which is not
  an IU2 legacy candidate.

IU2 must not infer state from version labels, application behavior, approximate
table presence, empty migration history, or operator assertion. A committed
runtime is not reclassified as legacy.

## Immutable reconciliation plan

For a legacy candidate, IU2 must produce an immutable deterministic
reconciliation plan before any mutation. The plan binds:

- operation identity;
- trusted target, package/release, and archive identities;
- source classification;
- package-owned filesystem actions;
- database baseline and ordered forward-migration plan;
- relevant pre-operation state identities; and
- expected post-operation state identities.

Package-owned convergence may create, replace, or leave unchanged only within
the existing WU5 ownership and guarded apply boundaries. Operator/runtime-owned
paths remain untouched. Stale-file deletion is not introduced.

## Recovery, confirmation, and quiescence

Mutating reconciliation must bind the exact immutable reconciliation plan and
trusted target to one accepted Backup & Recovery recovery identity. The
recovery set must be complete, integrity-verified, and `READY` before mutation.
The four accepted recovery domains remain intact:

1. configured Webcore database, including migration history;
2. affected package-owned filesystem;
3. committed Webcore lifecycle state; and
4. `storage/installed.lock`.

The pre-operation `storage/installed.lock` is preserved in recovery evidence.
Explicit operator confirmation must bind the exact operation, plan, recovery,
and target identities. Any material plan change invalidates that confirmation.

`DatabaseQuiescenceCapability` is mandatory for production-grade mutating
reconciliation and must cover the accepted recovery and reconciliation
boundary. If suitable quiescence is unavailable or cannot be proven, IU2 fails
closed before mutation.

## Migration semantics

IU2 never fabricates or reconstructs historical migration records, replays
historical migrations merely to manufacture a ledger, or marks unknown
migrations applied. A legacy database without migration history may establish
an initial migration-state identity only from deterministically proven
authoritative canonical schema baseline evidence. Otherwise it fails closed.

After an authoritative baseline or known prefix is proven, IU2 runs only
objectively applicable, ordered, forward Core migrations under the WU4
contract. Downgrade and reverse migration remain unsupported.

## Finalization ordering

IU2 must not advance installed state merely because package files or migrations
started. It must verify package-owned filesystem identity, schema and migration
identity, integrity, and required health gates before final installed-state
reconciliation. Authoritative committed Webcore lifecycle state is written
last and must be consistent with the final `storage/installed.lock`.

## Failure and retry

IU2 reuses the accepted Backup & Recovery failure and recovery semantics. After
mutation may have begun, it must never recapture the uncertain state. Recovery
uses the same immutable recovery identity and manifest. A new reconciliation
must not bypass unresolved `RESTORE_REQUIRED`, `RESTORE_INDETERMINATE`,
verification-failed, or equivalent recovery-required state.

A successfully reconciled committed runtime must no longer be processed as
uncommitted legacy state. Restore does not rerun package application, Core
migrations, or legacy reconciliation planning; it restores and verifies the
same four accepted recovery domains under the `docs/31` ordering and retains
recovery-required state when verification does not pass.

## Acceptance boundary

Successful IU2 must leave deterministic evidence sufficient to unblock the
separate Module Package Lifecycle final acceptance:

- trusted reconciled Webcore target identity;
- verified package-owned runtime;
- verified schema and migration identity;
- target-consistent `storage/installed.lock`;
- authoritative committed Webcore lifecycle state;
- required health and integrity gates PASS; and
- no unresolved recovery-required state.

This task does not perform the separate human/E2E acceptance. It also does not
adopt `DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean
Install`; that item remains **DEFERRED / UNSCHEDULED — KEEP DEFERRED**.

## Relationship to completed and excluded work

IU2 consumes the accepted package identity, ownership, WU5 apply, WU4
migration, WU6 finalization, and Backup & Recovery boundaries. It does not
reopen Package Lifecycle, Backup & Recovery, or IU1 without a concrete
regression or contract inconsistency relevant to this task.

Module legacy adoption, remote distribution, signing/trust infrastructure,
channels, automatic updates, downgrade, reverse migration, destructive
rollback/restore implementation, and Module Manager changes remain outside
this preparation.

## Implementation decomposition

### Implementation Unit 1 — Exact-Match Webcore Runtime Adoption

Complete. The explicit non-mutating action, authoritative identity/inventory
proof, canonical schema-baseline verification, WU6 health gates,
durable operation/idempotency behavior, and committed-state-last finalization
are implemented and accepted under the existing boundaries.

### Implementation Unit 2 — Legacy Webcore Runtime Reconciliation

**WU1–WU3 COMPLETE; WU4+ NOT STARTED.** Separate authorization is required for
each later implementation unit. WU1 provides the non-mutating trusted-target,
deterministic-classification, and immutable-planning boundary. WU2 provides
only the accepted recovery binding, exact confirmation, database quiescence,
and mutation-eligibility boundary; it does not start mutation. WU3 consumes
that boundary and reuses the accepted WU5 guarded package-owned apply path for
CREATE, REPLACE, and UNCHANGED actions only. WU3 does not perform schema,
migration, installed-state, or committed-lifecycle mutation. IU2 must not add
generic legacy migration inference, stale-file deletion, or silent
normalization of unknown runtimes.

The current runtime is not an acceptance fixture until its drift and missing
schema/migration evidence are addressed through an explicitly authorized
process.

## Recommended next gate

The Backup & Recovery Foundation is independently accepted and closed.
Authorize **IU2 WU4 or later** only through a separate implementation
decision. Do not begin schema/migration mutation, finalization, Server-Empty
Bootstrap, Module legacy adoption, or Module WU7 browser acceptance under this
preparation lock.
