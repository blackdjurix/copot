# Module Package Lifecycle Contract

## Preparation status

```text
Module Package Lifecycle preparation: COMPLETE / CONTRACT LOCKED
Full Module Package Lifecycle: IN PROGRESS / NOT COMPLETE
WU1: COMPLETE
WU2: COMPLETE
WU3: COMPLETE
WU4: NOT STARTED
Implementation: IN PROGRESS — WU1–WU3 COMPLETE ONLY
```

This document records the locked architecture and delivered WU1–WU2 contract for the second
lifecycle target over the completed shared Package Lifecycle & Migration
Foundation. It authorizes no WU4 or later implementation, schema change,
package builder change, Admin upload UI, or remote distribution.

No milestone number is assigned. Module Manager is the operator/Admin
consumer; it is not the lifecycle engine. Generic archive handling, staging,
locking, operation persistence, integrity primitives, and migration
infrastructure remain shared lifecycle responsibilities.

The first lifecycle delivery remains Webcore-first and complete:
`docs/28_package_lifecycle_migration_foundation_contract.md`.

## Target position and scope boundary

Module Package Lifecycle is the second architectural slice over the shared
foundation. It must reuse shared lifecycle infrastructure where appropriate,
while using target strategies or adapters where Webcore-specific assumptions
do not apply to independently distributed Modules.

The baseline package shape is:

```text
one package → one technical module
```

Multi-module packages are deferred. Broad capability/provider arbitration,
publisher/provenance ecosystems, and remote distribution are not part of this
slice.

## Identity model

The following concepts are distinct:

| Identity | Meaning |
| --- | --- |
| Package identity | Stable identity of the distributable Module package. |
| Technical module identity | Runtime identity, initially compatible with `module.json.name`. |
| Human-facing title | Display metadata and not a lifecycle key. |
| Package version | SemVer version of the package contents and behavior. |
| Release/package instance identity | Immutable identity for the particular packaged release. |
| Installed lifecycle state | Durable record of the last committed Module package target. |

Technical module identity remains subject to the current invariant that the
lowercase module folder name matches `module.json.name`.

Package identity must be conceptually independent of technical module
identity, even if an initial serialization or generated value is related to
it. Silent package-identity rebinding is forbidden. Any future identity
rename requires an explicit migration/mapping contract and operator-visible
resolution.

Publisher, capability-provider, marketplace, signing, trust, and provenance
identities are not introduced by this contract.

## Package contract

The logical baseline contract requires, at minimum:

- a package type distinct from `copot-webcore`;
- manifest contract version;
- stable package identity;
- technical module identity;
- title and package version;
- release/package instance identity;
- supported Webcore version range;
- runtime requirements where applicable;
- one-module package ownership declaration;
- package-owned file inventory with byte size and SHA-256 identity;
- dependency declarations;
- conflict declarations;
- Module migration declaration and migration identity;
- permission/provisioning metadata needed for reconciliation.

Exact manifest filename, serialization, SQL representation, and generated
release identity remain implementation-deferred unless a later Work Unit has
evidence requiring those choices.

## Webcore and runtime compatibility

Module compatibility is evaluated against committed Webcore lifecycle state,
not source-tree identity. The existing SemVer and runtime compatibility
primitives should be generalized for this target.

The compatibility gate applies to `INSTALL`, `REPAIR`, `PATCH`, `UPDATE`, and
`UPGRADE`. An incompatible target is rejected before mutation, including when
the current Module is enabled.

## Shared forward lifecycle

The lifecycle pipeline remains one forward pipeline:

```text
receive ZIP
→ isolated staging
→ archive and Module contract validation
→ installed-state inspection
→ dependency/conflict and transition planning
→ Webcore/runtime compatibility validation
→ bounded maintenance and operation preparation
→ Module-owned file application
→ ordered Module migrations and provisioning
→ permissions metadata reconciliation
→ health and integrity verification
→ Module committed-state update
→ cleanup
```

`INSTALL`, `PATCH`, `UPDATE`, `UPGRADE`, and `REPAIR` are classifications over
this pipeline, not separate engines. `REPAIR` means same-target reconciliation
or bounded retry of incomplete forward work. Downgrade and reverse migration
remain unsupported.

## Dependencies

The baseline required dependency declaration contains:

```text
module/package target identity
version constraint
```

Optional dependency semantics are not introduced unless later evidence
requires them.

Deterministic outcomes are required for:

| Condition | Required outcome |
| --- | --- |
| Missing dependency | `MISSING_DEPENDENCY` |
| Installed but disabled dependency | `DEPENDENCY_DISABLED` |
| Incompatible dependency version | `INCOMPATIBLE_DEPENDENCY` |
| Dependency update required | Block and propose an operator plan. |
| Self-dependency | `CYCLIC_DEPENDENCY` or equivalent blocked result. |
| Dependency cycle | `CYCLIC_DEPENDENCY` |
| Target update invalidates dependency graph | Block before commit and preserve prior state. |

No generic ecosystem package solver is authorized. The lifecycle may inspect a
deterministic graph and propose an order, but cross-module mutation requires
explicit operator approval.

## Conflict detection and resolution

Conflict detection is lifecycle responsibility. The baseline checks at least:

- package identity collision;
- technical module identity collision;
- declared version-scoped conflict;
- owned-path collision;
- manifest identity inconsistency;
- explicitly knowable migration/schema ownership collision;
- permission ownership collision;
- materially knowable provisioning ownership collision.

The resolution boundary is:

```text
detect → classify → explain → propose resolution → explicit operator action
```

The lifecycle must not silently install, update, downgrade, disable,
uninstall, or replace another Module; replace a provider; or mutate another
Module's private schema/state.

Safe automatic behavior is limited to deterministic same-target outcomes such
as a no-op, same-target repair, same-target replacement of files owned by the
target Module, or safe rejection without mutation.

The smallest coherent outcome model is equivalent to:

```text
SATISFIED
MISSING_DEPENDENCY
DEPENDENCY_DISABLED
INCOMPATIBLE_DEPENDENCY
DECLARED_CONFLICT
IDENTITY_CONFLICT
CYCLIC_DEPENDENCY
RESOLUTION_REQUIRED
UNRESOLVABLE
```

Exact enum and class names remain implementation-deferred.

## Module-owned files and stale-file policy

The baseline live ownership boundary is:

```text
modules/<technical-module-name>/
```

A package owns exactly one Module directory in the first slice. Additional
live paths require explicit declaration. Arbitrary existing files must never
be inferred as package-owned.

Shared staging, inventory, containment, size, and SHA-256 primitives should be
reused. Package metadata is excluded from the live payload.

Stale-file deletion is **NOT SUPPORTED** in the baseline. A file may not be
deleted merely because it is absent from a newer package. Future deletion
requires an explicit package-owned deletion contract and separately approved
policy.

## Installed Module lifecycle state

The lifecycle requires a bounded per-Module durable registry. Independently
managed Module state must not be collapsed into Webcore's single-target
committed state.

The registry must support, at minimum:

- package identity;
- technical module identity;
- installed version;
- release/package identity;
- manifest contract version;
- migration-state identity;
- package integrity identity;
- enabled/disabled state;
- last committed lifecycle target;
- committed timestamp;
- operation/cleanup reconciliation identity where required.

The current runtime Module registration/status model remains in place for
compatibility. The lifecycle registry supplements it rather than replacing
unrelated runtime semantics.

The exact SQL or file-backed representation remains implementation-deferred.

## Fresh install and existing install

Fresh Module `INSTALL` establishes the package's canonical current Module
schema and state baseline. It must not replay historical migrations.

Existing installations converge toward the target canonical state through
ordered forward migrations. Failed pre-commit work leaves the prior committed
Module target authoritative.

## Module migrations, provisioning, and permissions

Module migrations require:

- stable immutable migration identity;
- deterministic ordering;
- Module ownership;
- checksum and executable identity;
- preconditions and postconditions;
- transactional or non-transactional classification;
- bounded retry behavior;
- durable applied-state ledger;
- deterministic migration-state identity.

Existing `database/upgrades/*.sql` files remain inputs and evidence, not the
Module migration system. A Module must never directly mutate another Module's
private schema or state unless a separately defined public contract explicitly
allows it.

Permission reconciliation preserves current authorization semantics. Lifecycle
may reconcile Module permission metadata, but must not silently:

- grant new permissions to roles;
- revoke role grants;
- delete Core permission rows solely because a manifest declaration disappears.

Permission and provisioning ownership conflicts block the lifecycle. Module
schema/provisioning changes belong to forward Module migrations.

## Enabled and disabled state

| Operation | State requirement |
| --- | --- |
| `INSTALL` | Finishes disabled by default. |
| `REPAIR` | Preserves prior enabled/disabled state. |
| `PATCH` | Preserves prior enabled/disabled state. |
| `UPDATE` | Preserves prior enabled/disabled state. |
| `UPGRADE` | Preserves prior enabled/disabled state. |

Temporary execution quiescing may be introduced later if technically required,
but must not become an implicit permanent disable. Package installation must
not silently enable a Module. Activation remains Module Manager/runtime
responsibility.

## Health, integrity, and finalization

Mandatory deterministic pre-commit checks cover the applicable evidence:

- package/live inventory integrity;
- package, manifest, and Module identity consistency;
- technical name/folder consistency;
- dependency validity;
- conflict validity;
- migration-ledger/state consistency;
- schema/provisioning viability;
- permission reconciliation viability;
- Module discovery/bootstrap viability;
- enabled-Module contribution viability where applicable.

A generic arbitrary Module health callback is not required in the baseline.
Committed Module lifecycle state advances last. Failed gates preserve the
previous committed target.

## Failure and interrupted-operation boundary

The shared principle remains:

```text
failure before commit → prior committed state remains authoritative
```

Interrupted operations use durable operation state and bounded
retry/reconciliation. Ambiguous irreversible outcomes fail closed as an
indeterminate/operator-intervention state. Automatic rollback is not claimed.
Backup & Recovery remains a separate capability and is not implemented here.

## Shared lifecycle reuse and adaptation

| Area | Classification |
| --- | --- |
| Package contract | GENERALIZE |
| ZIP intake | REUSE AS-IS |
| Archive validation | REUSE AS-IS |
| Isolated staging | REUSE AS-IS |
| Inventory/integrity | GENERALIZE |
| Installed-state inspection | TARGET STRATEGY / ADAPTER |
| Transition planning | GENERALIZE |
| Runtime compatibility | GENERALIZE |
| Lifecycle mutex | REUSE AS-IS |
| Operation persistence | REUSE AS-IS with target identity adaptation |
| Maintenance behavior | REUSE AS-IS |
| File application | TARGET STRATEGY / ADAPTER |
| Migration registry/runner | TARGET STRATEGY / ADAPTER |
| Health/integrity | TARGET STRATEGY / ADAPTER |
| Committed-state persistence | MODULE-SPECIFIC bounded registry using shared conventions |
| Cleanup/retry | REUSE AS-IS with Module identity reconciliation |
| `PackageLifecycleService` | TARGET STRATEGY / ADAPTER |
| CLI/operator surface | TARGET STRATEGY |
| Module Manager | Operator/Admin consumer |

Generalization is not required where a target adapter is sufficient.

## Candidate Work Units

### WU1 — Module Package Identity & Contract

**Objective:** Lock Module package identity, package contract, Webcore/runtime
compatibility, and the one-package/one-module boundary.

**Direct dependencies:** Completed Webcore WU1–WU7 foundation; current Module
manifest and discovery evidence.

**Principal deliverables:** Logical contract fields, identity rules,
compatibility rules, package ownership boundary, and invalid-contract cases.

**Important exclusions:** Publisher ecosystem, signing/trust, marketplace,
multi-module packages, remote distribution, runtime implementation beyond WU1.

**Acceptance evidence:** Reviewed contract examples, identity collision cases,
SemVer/compatibility cases, and consistency with Module and distribution docs.

**Runtime/browser/human validation:** Not materially required.

### WU3 delivery boundary

WU3 adds a bounded file-backed per-Module registry under the existing
`.copot-lifecycle/modules/` namespace. It supplements the runtime `modules`
registration/status table and does not replace or mutate that unrelated runtime
authority. Inspection distinguishes `FRESH`, `LEGACY`, `COMMITTED`,
`INCONSISTENT`, and `INVALID` states; legacy installations are explicitly
blocked pending lifecycle bootstrap rather than being silently adopted.

The Module transition adapter reuses the existing SemVer, runtime requirement,
and committed-Webcore state primitives. It plans `INSTALL`, `REPAIR`, `PATCH`,
`UPDATE`, and `UPGRADE` through one forward planner, rejects downgrade and
identity rebinding, evaluates compatibility against committed Webcore state,
and preserves enabled state for non-install transitions. `INSTALL` plans a
disabled final state. WU3 performs no file application, registration mutation,
activation, dependency/conflict resolution, migration execution, permission
reconciliation, or committed-state advancement.

### WU2 — Module Package Intake, Ownership & Conflict Inspection

**Objective:** Adapt shared ZIP, staging, inventory, and safety checks to Module
packages and inspect target ownership, identity, and static collisions before
live mutation.

**Direct dependencies:** WU1; shared WU2 staging and inventory primitives.

**Principal deliverables:** Module package type intake, Module-root ownership,
inventory validation, identity/path collision inspection, and static
schema/permission ownership checks.

**Important exclusions:** Live application, stale deletion, cross-Module
mutation, dependency graph mutation.

**Acceptance evidence:** Containment, inventory, package/technical identity,
manifest, path, migration/schema, permission, and provisioning conflict cases.

**Runtime/browser/human validation:** Focused runtime/filesystem validation;
browser not materially required.

### WU2 delivery boundary

WU2 reuses `ZipIntakeService`, `StagingSession`, staged extraction,
`ArchiveEntryPath`, and `PackageInventoryVerifier` without a parallel Module
archive or hashing implementation. The concrete Module package metadata path is
the shared package-only `.copot/package.json` path. Its strict Module contract
fields are serialized separately from the runtime
`modules/<technical-module-name>/module.json`; the runtime manifest remains the
existing discovery contract and is validated against the folder/name invariant.

The Module package inventory contains only live files under exactly
`modules/<technical-module-name>/`. `.copot/package.json` is excluded from that
live inventory. WU2 validates normalized paths, staged byte size and SHA-256,
one-root ownership, runtime manifest identity, and static package/path
consistency before returning immutable inspection evidence. It performs no live
mutation and does not inspect installed lifecycle state, classify transitions,
resolve dependencies, execute migrations, reconcile permissions, or update
committed state. Cross-Module schema, permission, and provisioning ownership
remain an explicit limitation until an authoritative ownership source exists.

### WU3 — Module Installed-State & Transition Planner

**Objective:** Provide per-Module state inspection and deterministic
`INSTALL`/`REPAIR`/`PATCH`/`UPDATE`/`UPGRADE` classification.

**Direct dependencies:** WU1 and WU2; approved bounded Module lifecycle
registry boundary.

**Principal deliverables:** State model, committed/legacy/inconsistent
inspection, version relation, compatibility gate, enabled-state preservation,
and transition plan.

**Important exclusions:** Migration execution, file application, activation,
rollback.

**Acceptance evidence:** Fresh, legacy, committed, inconsistent, invalid,
same-version repair, forward classification, downgrade rejection, and
enabled-state cases.

**Runtime/browser/human validation:** Not materially required.

### WU4 — Module Dependency & Conflict Planner

**Objective:** Provide deterministic dependency and conflict evaluation plus
operator-facing resolution planning.

**Direct dependencies:** WU1–WU3.

**Principal deliverables:** Required dependency constraints, dependency graph
inspection, cycle handling, conflict classifications, explanations, proposed
resolution plans, and explicit operator-action boundary.

**Important exclusions:** Generic ecosystem solver, automatic cross-Module
mutation, capability/provider arbitration, automatic provider replacement.

**Acceptance evidence:** Missing, disabled, incompatible, update-required,
self-cycle, cycle, invalidation, package/path/schema/permission conflicts, and
no-silent-mutation cases.

**Runtime/browser/human validation:** Not materially required.

### WU5 — Module Migration, Provisioning & Permission Reconciliation

**Objective:** Deliver Module-owned forward migration semantics and bounded
provisioning and permission-metadata reconciliation.

**Direct dependencies:** WU1–WU4 and the WU3 persistence decision.

**Principal deliverables:** Module migration registry/ledger, stable identity,
ordering, checksums, retry semantics, canonical fresh baseline, provisioning
postconditions, and permission metadata reconciliation.

**Important exclusions:** Reverse migration, downgrade, cross-Module private
state mutation, automatic role grant/revoke, generic migration discovery.

**Acceptance evidence:** Fresh install, ordered existing-install migration,
ledger identity, retry, provisioning, permission add/remove/change, and
authorization-preservation cases.

**Runtime/browser/human validation:** Focused database/runtime validation;
browser not materially required.

### WU6 — Module Apply, Integrity & Commit-State Closure

**Objective:** Apply Module-owned files through the shared lifecycle boundary
and finalize committed Module state after all required gates pass.

**Direct dependencies:** WU2, WU3, WU5, and shared lifecycle services.

**Principal deliverables:** Module-root apply adapter, preflight and streamed
identity verification, migration/apply coordination, integrity and health
gates, enabled-state preservation, committed-state update, interruption, and
cleanup reconciliation.

**Important exclusions:** Stale-file deletion, destructive rollback/restore,
automatic activation, Backup & Recovery implementation.

**Acceptance evidence:** Root-only apply, no cross-Module mutation, failure
before commit, indeterminate blocking, migration/apply identity, health gates,
committed-state-last, and cleanup-retry cases.

**Runtime/browser/human validation:** Focused runtime validation materially
required; browser only if the selected health gate changes the Admin/runtime
flow.

### WU7 — Module Manager Operator Surface & End-to-End Acceptance

**Objective:** Make Module Manager the operator/Admin consumer of the shared
Module Package Lifecycle and prove the first Module-package slice end-to-end.

**Direct dependencies:** WU1–WU6.

**Principal deliverables:** Deterministic plan/apply/repair/status integration,
dependency/conflict explanations, explicit resolution requirements, Module
Manager inventory integration, and representative package acceptance.

**Important exclusions:** Admin ZIP upload unless separately authorized,
remote discovery/download, automatic updates, marketplace, signing/trust,
channels.

**Acceptance evidence:** Real representative package plan/apply/repair/status,
enabled and disabled preservation, tamper rejection, operator-resolution
cases, and unchanged repository source during acceptance.

**Runtime/browser/human validation:** Runtime validation materially required;
browser/human validation required only for changed Admin Module Manager flows.

## Deferred and explicit exclusions

`DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install`
remains **DEFERRED / UNSCHEDULED — KEEP DEFERRED**. It is not a dependency for
Module Package Lifecycle preparation.

The following remain excluded:

- remote package discovery/download;
- official update service;
- automatic updates;
- signing/trust;
- channels;
- marketplace;
- differential packages;
- downgrade;
- reverse migration;
- destructive rollback/restore;
- Backup & Recovery implementation;
- broad capability/provider ecosystem;
- publisher/provenance ecosystem;
- automatic dependency mutation;
- automatic provider selection;
- multi-module package orchestration.

## Documentation and next gate

This contract is the authoritative preparation artifact. Concise project-state
references point here; the full contract is not duplicated in `AGENTS.md`,
`README.md`, or the roadmap.

WU1, WU2, and WU3 are complete on the authorized implementation branch. WU4 is
not started. Full Module Package Lifecycle implementation remains in progress
and is not complete or closed.
