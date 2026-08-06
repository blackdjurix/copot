# Backup & Recovery Foundation Contract

## Preparation status

```text
Backup & Recovery Foundation: IMPLEMENTATION IN PROGRESS
Backup & Recovery WU1: COMPLETE
Backup & Recovery WU2: COMPLETE
Backup & Recovery WU3: COMPLETE
Backup & Recovery WU4: COMPLETE
Backup & Recovery WU5: COMPLETE
Backup & Recovery WU6: NOT STARTED
Backup & Recovery implementation overall: IN PROGRESS
Existing-Runtime Webcore Lifecycle Adoption IU2: BLOCKED ON THIS DEPENDENCY
Module Package Lifecycle WU7 human/E2E acceptance: BLOCKED ON AUTHORITATIVE COMMITTED WEBCORE STATE
```

This contract defines the smallest reusable Backup & Recovery Foundation
required before mutating Existing-Runtime Webcore Lifecycle Adoption IU2 may
be implemented. It is a separate Core/platform capability consumed by Package
Lifecycle. Its WU1–WU3 implementation is recorded separately from this
contract; it does not implement IU2, Module Package Lifecycle, Server-Empty
Bootstrap, or Module legacy adoption.

The implementation namespace is `Copot\\Core\\BackupRecovery`.
WU2 uses bounded canonical JSON for the immutable manifest and artifact
metadata. WU4 provides the MySQL/MariaDB database recovery provider and its
disposable round-trip proof; WU5 remains implementation-deferred.

## Capability ownership and boundary

Backup & Recovery owns durable recovery identity and manifests, private
recovery storage, recovery-domain orchestration, capture, restore,
verification, interruption/retry, and controlled cleanup.

Package Lifecycle owns lifecycle planning and invokes the foundation for a
reconciliation operation. IU2 owns explicit target selection, confirmation,
and legacy convergence policy. IU2 must not create an IU2-owned backup
subsystem.

The first delivery slice is narrowly scoped to the recovery domains required by
IU2 while preserving a reusable foundation boundary for later approved
capabilities. It is not a generic full-site backup product.

## Initial physical recovery domains

The first slice has exactly four physical recovery domains:

1. configured Webcore database/schema/data;
2. package-owned filesystem state affected by the authoritative reconciliation
   apply plan;
3. committed Webcore lifecycle state;
4. `storage/installed.lock`.

`core_migration_history` belongs to the database recovery domain. It may have a
distinct deterministic identity and verification contract, but it is not a
separately restored physical domain. Database restoration owns it once and
restore ownership must not overlap.

## Recovery identity and manifest

Each reconciliation operation must have one durable, immutable recovery-set
identity and manifest. WU2 owns the manifest's immutable recovery-set identity
and publication/integrity facts. At minimum, it binds:

- recovery identity;
- lifecycle/reconciliation operation identity;
- trusted target package and release identity;
- package/archive identity;
- apply-plan identity;
- captured domain identities;
- pre-operation lifecycle identity;
- pre-operation migration-ledger identity;
- artifact identities;
- artifact sizes and hashes;
- canonical manifest identity;
- capture/publication completeness.

Once successfully published, these facts do not mutate. Any incomplete
capture, identity mismatch, or artifact-integrity failure blocks reconciliation
before the first live mutation.

The WU2 serialization is bounded canonical JSON: fixed field ordering,
UTF-8, `JSON_UNESCAPED_SLASHES`, `JSON_THROW_ON_ERROR`, no pretty printing,
and strict required/unknown-field validation. The manifest must be durable,
independently readable, integrity verifiable, and separate from lifecycle state
being restored. WU2 must not make the immutable manifest own mutable restore,
post-restore verification, cleanup, or interruption/retry state.

WU6 owns the mutable durable recovery lifecycle/state-machine record, including
the following states and statuses:

```text
CREATED
CAPTURING
CAPTURED
READY
RESTORING
VERIFYING
RESTORED
CLEANUP_PENDING
CLEANED

FAILED_BEFORE_MUTATION
RESTORE_REQUIRED
RESTORE_INDETERMINATE
VERIFICATION_FAILED
```

WU6 also owns restore status, post-restore verification status, cleanup status,
and interruption/retry state. The WU6 lifecycle record references the immutable
WU2 recovery manifest; it must not duplicate or rewrite immutable
artifact/manifest identity. Recovery evidence remains independent from the
lifecycle state being restored.

## Recovery-domain contract

The foundation may expose a narrow recovery-domain registry because the initial
domains have distinct capture, restore, and verification rules. Each domain
must provide a stable identifier, scope descriptor, artifact identity, capture
result, restore result, and verification result.

The recovery coordinator owns ordering. WU6 owns mutable recovery-set lifecycle
state. A domain owns only its declared persisted state. Future domains may be
added through the same boundary only after separate authorization.

## Committed lifecycle-state recovery boundary

The committed Webcore lifecycle-state domain must represent one of two explicit
pre-operation states:

- `PRESENT_COMMITTED_STATE`, binding the exact canonical committed lifecycle
  state and its deterministic identity;
- `ABSENT_BEFORE_OPERATION`, binding explicit canonical absence as a recovery
  fact.

Absence must not be represented by an empty string, an omitted manifest field,
or an accidental null. The selected state and identity are immutable facts
bound by the recovery manifest.

When committed lifecycle state is present before the operation, restore must
reproduce that committed state exactly. When it is authoritatively absent,
restore must reproduce absence; already-absent state is idempotent success.
Removal of an operation-created committed state is allowed only when its
current identity matches the explicitly expected mutated identity supplied by
later orchestration. Unrelated, malformed, or ambiguous state fails closed.

The `storage/installed.lock` domain remains separately owned. For the IU2
source-runtime boundary, a committed lifecycle state requires an existing
valid marker whose version and timestamp are consistent with that state.
WU5 verifies this relationship but does not merge the two physical domains.

## Private storage and containment

Recovery artifacts must be stored in a private, durable recovery namespace
owned by the Backup & Recovery capability. The namespace must be structurally
separate from the live Webcore tree, package staging, and apply-temporary
storage. It must not be restored as part of the captured lifecycle state.

Storage must enforce real-path containment, reject symlinks and path escapes,
use restrictive permissions, write artifacts atomically, and verify complete
artifact identity before the recovery set becomes `READY`.

Recovery storage failure or loss of durable manifest state must fail closed
before reconciliation mutation begins.

## Filesystem recovery boundary

The filesystem domain captures only package-owned paths that may be changed by
the authoritative reconciliation apply plan. It does not infer ownership from
arbitrary extra paths and does not capture operator/runtime-owned state.

The captured state distinguishes:

- a pre-existing file;
- an operation-created path;
- pre-operation absence.

Restore must support byte-for-byte restoration of replaced files, removal of
operation-created files, safe recreation of required parent directories,
strict containment, symlink and path-traversal rejection, and preservation of
operator/runtime-owned paths.

Stale package-owned deletion remains unsupported unless separately authorized.

## Database recovery boundary

The foundation requires a restore-capable MySQL provider behind a narrow
recovery-provider contract. The contract must require objective round-trip
fidelity for the database state needed by IU2, including:

- schema;
- rows/data;
- indexes;
- constraints;
- relevant table metadata;
- auto-increment state where material;
- `core_migration_history`;
- migration-induced effects.

The architecture does not select or require an external executable dependency.
PDO-native logical capture/restore may be the preferred first implementation
direction only if its fidelity is objectively proven; it is not mandatory in
this contract. If no implementation strategy meets the required fidelity,
implementation must stop for a separate provider/tooling decision.

## Consistency and locking

The foundation does not claim cross-filesystem/database atomicity. Its
consistency boundary is the state whose mutation is controlled by the Copot
maintenance/lifecycle boundary.

Capture and reconciliation require:

- `InstallationMutex` ownership;
- maintenance/quiescence;
- complete recovery capture before the first mutation;
- recovery-set verification before mutation;
- durable recovery-required state after partial mutation or restore.

If sufficiently quiescent and exclusive access to the configured database
cannot be established or reasonably proven, capture must fail closed before
reconciliation mutation begins. External database writers must not be assumed
to be excluded silently.

## Durable recovery lifecycle

The conceptual durable lifecycle is:

```text
CREATED
→ CAPTURING
→ CAPTURED
→ READY
→ RESTORING
→ VERIFYING
→ RESTORED
→ CLEANUP_PENDING
→ CLEANED
```

Required explicit failure/recovery states are:

- `FAILED_BEFORE_MUTATION`;
- `RESTORE_REQUIRED`;
- `RESTORE_INDETERMINATE`;
- `VERIFICATION_FAILED`.

The recovery record remains independent from the lifecycle state being
restored. An interrupted or failed operation must not erase the recovery
manifest or make it appear successfully committed.

## Restore semantics and ordering

Restore must be resumable and idempotent. It must not rerun package
application, Core migrations, or legacy reconciliation planning.

Logical restore ordering is:

```text
acquire InstallationMutex
→ establish maintenance
→ verify immutable recovery manifest
→ restore database/schema/data, including core_migration_history
→ restore package-owned filesystem state
→ restore committed Webcore lifecycle state and storage/installed.lock
→ verify every recovery domain
→ run required WU6-compatible health/integrity gates
→ mark RESTORED or retain recovery-required state
```

Committed lifecycle state must not be restored or finalized as authoritative
until underlying database/filesystem restoration and verification succeed.
`core_migration_history` is restored once through the database domain.

## Verification

Post-restore verification must deterministically prove:

- recovery manifest and artifact integrity;
- package-owned filesystem identities;
- database schema/data identity;
- Core migration-ledger identity;
- committed lifecycle identity;
- `storage/installed.lock` consistency;
- required WU6-compatible health and integrity gates.

The foundation must not claim proof of unrelated operator-owned filesystem
state that the recovery set does not capture.

## Interruption, retry, and cleanup

Capture interruption before mutation results in `FAILED_BEFORE_MUTATION` and
must block IU2. Interruption after mutation results in `RESTORE_REQUIRED` or
`RESTORE_INDETERMINATE` until the immutable recovery set is successfully
restored and verified.

Retry may resume capture only before mutation and may resume restoration only
against the same recovery identity and manifest. It must not create a new
recovery point over an uncertain mutated state.

Cleanup is controlled and occurs only after successful post-restore or
post-reconciliation verification. Cleanup failure produces
`CLEANUP_PENDING`; it must not invalidate already verified state or erase
recovery evidence. Retention-management product behavior is outside this
slice.

IU2 operator confirmation must cover the explicit trusted target and the
completed recovery-set identity before mutation. Destructive cleanup requires
the applicable operator confirmation or separately approved cleanup policy.

## First-slice exclusions and future direction

The following are excluded from implementation scope:

- Media backup/recovery;
- Module backup contributions;
- generic full-site backup;
- scheduled backups;
- retention-management product features;
- remote or cloud backup storage;
- Admin backup UI;
- automatic backup policy;
- generic disaster-recovery product surfaces;
- Server-Empty Bootstrap;
- Module legacy adoption;
- IU2 implementation itself.

Future directions remain possible only through separate authorization and may
reuse the foundation's identity, manifest, storage, domain, verification, and
state boundaries.

## Proposed implementation units

1. WU1 — Recovery identity, manifest, namespace, and domain registry.
2. WU2 — Private durable recovery storage and integrity handling.
3. WU3 — Package-owned filesystem capture and restore.
4. WU4 — MySQL recovery-provider implementation and round-trip proof.
5. WU5 — Lifecycle-state and migration-identity verification integration.
6. WU6 — Locking, maintenance, durable state, interruption, and retry behavior.
7. WU7 — Independent Foundation Acceptance:
   - Package Lifecycle adapters, interfaces, test doubles, and recovery seams;
   - restore ordering and post-restore WU6-compatible verification;
   - failure-injection and interruption/retry acceptance for Backup & Recovery;
   - tamper and partial-restore handling;
   - cleanup and operator-confirmation behavior.

Actual Existing-Runtime Webcore Lifecycle Adoption IU2 implementation,
production legacy reconciliation, target selection, reconciliation planning,
and convergence policy remain outside Backup & Recovery Foundation scope.
Backup & Recovery Foundation must be independently accepted as restore-capable
before IU2 implementation begins.

## Acceptance prerequisite for IU2

IU2 must remain blocked until acceptance evidence proves:

- complete capture before mutation;
- tamper and incomplete-capture rejection;
- exact filesystem round-trip for replaced, created, absent, and nested paths;
- preservation of operator/runtime-owned paths;
- objective MySQL schema/data/metadata/ledger round-trip fidelity;
- recovery of transactional and non-transactional migration effects;
- interruption and retry at every capture, mutation, restore, and verification
  boundary;
- idempotent restore without rerunning package application, migrations, or
  reconciliation planning;
- mutex and maintenance exclusion;
- explicit recovery-required behavior after partial restore;
- post-restore WU6-compatible health/integrity success;
- cleanup only after verification and required confirmation;
- no unapproved external executable dependency;
- containment to the four initial physical domains.

## Dependency consequence

Backup & Recovery Foundation is **IMPLEMENTATION IN PROGRESS**; WU1, WU2, WU3,
WU4, and WU5 are **COMPLETE**, WU6 is **NOT STARTED**, and implementation overall is **IN
PROGRESS**. Existing-Runtime Webcore Lifecycle Adoption IU2 remains
**BLOCKED** pending an accepted restore-capable implementation.
Module Package Lifecycle WU7 human/E2E acceptance remains **BLOCKED** pending
authoritative COMMITTED Webcore runtime state. WU7 is not resumed by this
contract.
