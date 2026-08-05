# Existing-Runtime Webcore Lifecycle Adoption Contract

## Preparation status

```text
Existing-Runtime Webcore Lifecycle Adoption: ARCHITECTURE LOCKED
Implementation Unit 1: COMPLETE
Implementation Unit 2 — Legacy Reconciliation: NOT STARTED
Backup & Recovery Foundation: PREPARATION / CONTRACT LOCKED; IMPLEMENTATION NOT STARTED
Module Package Lifecycle WU7 acceptance: BLOCKED ON THIS PREREQUISITE
Server-Empty Bootstrap & Package Clean Install: DEFERRED / UNSCHEDULED
```

This contract defines the smallest safe capability for converting an already
running legacy Webcore installation into authoritative committed lifecycle
state. It is a newly discovered Webcore prerequisite for Module Package
Lifecycle WU7 acceptance. It does not reopen the completed Webcore WU1–WU7
delivery slice, and it does not implement the capability.

The current acceptance runtime is evidence for this contract, not an adoption
target. It is `LEGACY`, reports `0.8.0` through `storage/installed.lock`, has
no committed lifecycle state or Core migration-history ledger, and differs
from the inspected `copot-v0.12.0` package in six package-owned files.

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

## Exact-match adoption

Exact-match adoption is permitted only when a trusted package contract or
another already-authoritative release source proves all of the following:

- package type and manifest contract version are supported;
- target Webcore version exactly matches the compatible existing runtime state;
- release/package identity comes from the trusted package, never from Git,
  `Version::CURRENT`, a timestamp, or operator-supplied fabrication;
- every package-owned live file has the declared byte size and SHA-256, with
  zero package-owned drift;
- package-owned and operator/runtime-owned paths are completely separated;
- runtime/bootstrap and required WU6 health gates pass;
- the database matches an authoritative canonical schema baseline;
- the migration-state identity is deterministically established as the
  canonical baseline without replaying historical migrations or marking
  unknown migrations applied;
- the existing `storage/installed.lock` is valid and compatible with the
  adopted target.

Version equality alone is never sufficient. Any mismatch rejects exact-match
adoption without mutation. Successful adoption records the trusted package's
release identity as the verified lifecycle target; it does not claim to recover
unknown historical provenance.

## Legacy reconciliation

Reconciliation is a distinct operation with this bounded shape:

```text
legacy runtime
→ inspect
→ select explicit trusted target package
→ plan reconciliation
→ explicit operator confirmation
→ guarded package-owned convergence
→ canonical schema-baseline verification or ordered forward migration
→ WU6 health/integrity gates
→ committed lifecycle state last
```

It may replace only package-owned files through the existing WU5 guarded apply
boundary. Stale-file deletion remains unsupported. Operator/runtime-owned
paths are untouched. It must not silently become an ordinary lifecycle
classification, fabricate release or migration history, replay historical
migrations solely to manufacture a ledger, or mark unknown migrations applied.

If canonical schema-baseline verification cannot deterministically prove the
database state, reconciliation fails closed. A version string, application
behavior, or empty migration ledger is not schema proof.

## Schema and migration baseline

The implementation must provide an authoritative canonical schema-baseline
verification boundary before establishing initial migration-state identity.
That verifier must use deterministic schema descriptors/checksums and the
approved Webcore schema contract. It must distinguish a verified canonical
baseline from an unknown or merely version-labelled database.

Historical migration records are not reconstructed. Existing forward Core
migrations remain applicable only when a known committed migration prefix
exists. A legacy database without a provable canonical baseline is not
reconciled by this contract.

## Safety and recovery

Exact-match adoption is non-mutating apart from the lifecycle operation and
final committed-state records, so it does not require Backup & Recovery.

Mutating reconciliation is subject to the existing WU5/WU6 safety boundary.
Planning, inspection, and test doubles may proceed without Backup & Recovery,
but production-grade acceptance of irreversible file/schema convergence
requires the restore-capable implementation defined by the separate preparation
contract in `docs/31_backup_recovery_foundation_contract.md`. This contract does
not implement or absorb Backup & Recovery.

## Relationship to deferred and excluded work

`DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install`
remains **DEFERRED / UNSCHEDULED — KEEP DEFERRED**. It concerns a server where
Copot source/runtime does not yet exist; this contract concerns an existing
legacy runtime.

Module legacy adoption remains separate and is not solved by this Webcore
capability. Remote distribution, signing/trust, channels, automatic updates,
downgrades, reverse migration, destructive rollback/restore, and Module
Manager changes remain outside this preparation.

## Candidate implementation decomposition

### Implementation Unit 1 — Exact-Match Webcore Runtime Adoption

Deliver the explicit non-mutating action, authoritative identity/inventory
proof, canonical schema-baseline verification, WU6 health gates, durable
operation/idempotency behavior, and committed-state-last finalization.

Acceptance must prove that exact matches adopt, every mismatch fails closed,
legacy state remains unchanged on failure, and committed state is visible to
normal status/planning afterward.

### Implementation Unit 2 — Legacy Webcore Runtime Reconciliation

Deliver only after Unit 1 and an approved restore-capable recovery boundary.
Implement explicit target selection/confirmation, guarded package-owned
convergence, deterministic schema/migration handling, interruption/retry, and
WU6 finalization. This unit must not add generic legacy migration inference or
silently normalize unknown runtimes.

The current runtime is not an acceptance fixture for Unit 1 or Unit 2 until
the six drifted files and the missing schema/migration evidence are addressed
through an explicitly authorized process.

## Implementation Unit 1 delivery

Implementation Unit 1 provides the explicit `package:adopt` operator command.
It reuses shared ZIP intake, inventory verification, WU6 integrity/health
finalization, committed-state persistence, and operation cleanup. It verifies
the canonical Core table shape and empty migration ledger without applying
package files or replaying migrations. It is independently tested for success,
repeat adoption, version/hash/schema mismatch, failed health gates, and
committed package-integrity identity persistence.

The current XAMPP runtime remains legacy and is rejected because its installed
evidence does not match the supplied target package. It was not normalized.

## Recommended next gate

Authorize Backup & Recovery Foundation implementation preparation against
`docs/31_backup_recovery_foundation_contract.md`. Authorize **Implementation
Unit 2 — Legacy Webcore Runtime Reconciliation** only after that implementation
is accepted as restore-capable. Do not begin reconciliation, Server-Empty
Bootstrap, Module legacy adoption, or Module WU7 browser acceptance under this
contract.
