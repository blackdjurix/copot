# Package Lifecycle & Migration Foundation

## Preparation status

Status: WU1 COMPLETE AND CLOSED — WU2 COMPLETE AND CLOSED — WU3 COMPLETE AND CLOSED — WU4–WU7 NOT STARTED

Next work unit: WU4 — Core Migration Registry & Runner (NEXT / NOT STARTED)

This document records the selected Post-M3 platform-foundation target and its
contract. WU1 is limited to serialization-neutral Webcore package-contract
primitives and focused tests. WU2 delivers safe local ZIP intake, bounded
archive validation, private isolated staging, streamed extraction,
package-ownership enforcement, staged payload/inventory verification, and
cleanup. WU2 does not authorize installed-state persistence, transition
planning, schema, migration, package application, operator UX, or Module
package lifecycle work.

No authoritative milestone number is assigned. The existing roadmap does not
provide an approved post-M3 numbering mechanism for this target.

## Foundation scope

The architectural target is:

```text
Webcore + independently distributed Module packages
```

The first delivery slice is Webcore. Module package lifecycle is a later
architectural slice. Shared lifecycle abstractions must remain capable of
supporting that later slice, but the first slice must not implement Module
package intake, distribution, migration, or update behavior.

The first package source is a local or operator-provided ZIP containing a full
target package. Differential packages, remote discovery, official update
services, signing, channels, marketplaces, automatic updates, generic
downgrades, and other remote distribution capabilities remain outside this
foundation slice.

## Adopted lifecycle decisions

- One shared forward lifecycle pipeline owns fresh INSTALL, forward transition
  classification, and same-version REPAIR.
- PATCH is a forward-transition classification or policy level, not a separate
  engine. UPDATE and UPGRADE use the same pipeline.
- Fresh installation uses the canonical current Webcore state.
- Existing installations use ordered forward Core migrations.
- Core migrations are Webcore-owned.
- Installed target version/state does not advance until apply, migration, and
  health/integrity gates succeed.
- Package-owned paths and runtime/operator-owned paths are separate ownership
  domains. A Webcore package must not overwrite `.env`, `storage/`, logs,
  caches, uploaded assets, media, backups, or other operator-owned state.
- The operator surface does not own lifecycle semantics. CLI/local intake is the
  first surface direction; a future Admin upload surface must call the same
  application service.

## Release and installed-state identity

The lifecycle must distinguish these concepts even when implementation details
remain deferred:

1. **Release/package target identity** — the target Webcore release represented
   by the received package.
2. **Installed Webcore version/state** — the last target whose lifecycle gates
   completed and whose state was committed.
3. **Source-tree development identity** — repository/tree identity used when
   relevant to development and reproducibility; it is not installed-version
   state.
4. **Migration/schema state** — the ordered Core migration state and resulting
   database/schema capability state.
5. **Package-manifest contract version** — the format/meaning version used to
   interpret package metadata and file inventory.

`Version::CURRENT` remains useful as the source-tree/release-builder version
source for the current repository, but it is not sufficient as the complete
installed lifecycle state. Exact persistence structures are implementation-
deferred.

## WU1 implementation boundary

WU1 provides serialization-neutral Core primitives for package version
validation, package identity, source compatibility, runtime requirements,
ownership classification, package-owned inventory entries, file size and
SHA-256 identity, and the migration declaration boundary. The primitives do
not select a manifest filename or serialization format.

`source_tree_identity` is optional opaque provenance and is never an installed
compatibility key. `release_identity` is an opaque immutable package/release
identity whose generation is outside WU1.

WU1 preserves `Version::CURRENT` and the exact two-field
`storage/installed.lock` contract. It adds no persistence, archive handling,
staging, transition planning, migration execution, package application, or
operator surface. The focused WU1 test is
`tests/package_contract_wu1.php`.

The WU1 reader boundary accepts only the Webcore package type and the current
manifest contract version. It uses strict SemVer 2.0.0 validation and
precedence, including numeric core/prerelease leading-zero rules, prerelease
ordering, and build-metadata exclusion from precedence. The `modules/example/`
source fixture remains governed by the build package exclusion policy; WU1 does
not assign it a separate Module lifecycle ownership rule.

## Minimum package contract

The logical v1 contract requires, at minimum:

- package type identifying a Webcore package;
- package/manifest contract version;
- target release identity and target Webcore version;
- supported source-version range or explicit compatibility rule;
- runtime compatibility requirements;
- ordered migration metadata and applicability information;
- package-owned path inventory;
- per-file size and integrity identity;
- explicit ownership/exclusion rules for operator-owned paths.

Exact serialization, filenames, and schema are implementation-deferred until
the contract Work Unit provides evidence requiring those choices. The existing
build manifest remains a source-package allowlist; it is not by itself the
runtime package contract.

## WU2 delivered implementation boundary

WU2 accepts a local filesystem ZIP path, copies the source archive into a
private immutable staging namespace, inspects the staged archive before
extraction, and produces an isolated staged payload. The boundary includes:

- bounded archive bytes, entry count, extracted bytes, individual-file bytes,
  compression-ratio, canonical-path, and nesting limits;
- normalized portable archive paths with traversal, absolute, device-name,
  control-character, non-ASCII, collision, type-conflict, and ownership checks;
- external-attribute checks for symlinks and special files, with fail-closed
  contradictory directory metadata handling;
- component-wise parent-directory creation and exclusive regular-file writes;
- streamed byte counting and SHA-256 identity for the copied archive and each
  staged regular file;
- comparison of a supplied WU1 inventory against staged regular files; and
- immediate failure cleanup plus bounded stale-staging reconciliation.

The package manifest filename and serialization remain unselected. The WU2
output is the normalized staged regular-file set; directories and the deferred
package metadata artifact are not `PackageInventoryEntry` members.

Focused WU2 validation passed. The ext-zip-disabled capability branch requires
a separate executor, and the filesystem symlink cleanup fixture was unavailable
on Windows; both are non-blocking evidence limitations for the accepted WU2
boundary.

## Shared lifecycle boundaries

The planned pipeline is:

```text
receive ZIP
→ isolated staging
→ archive and manifest validation
→ installed-state inspection
→ transition planning and classification
→ compatibility validation
→ bounded maintenance/recovery preparation
→ package-owned file application
→ ordered Core migrations
→ state reconciliation
→ health and integrity verification
→ installed-state commit
→ staging cleanup
```

INSTALL, PATCH, UPDATE, UPGRADE, and REPAIR are classifications over this
pipeline. Downgrade and reverse migration are not supported by this contract.

REPAIR means same-version reconciliation or retry of incomplete forward work.
It does not mean reset, rollback, destructive cleanup, or generic reverse
migration.

## Core migration contract

Core migrations are Webcore-owned and must be ordered, forward-only, and
durably tracked as applied state. Each migration requires a stable identity,
ordering, source applicability, target/capability outcome, ownership category,
preconditions, postconditions, and defined retry behavior.

Existing files under `database/upgrades/` are reusable migration inputs and
evidence of feature-specific upgrade work. They do not constitute a migration
registry, runner, ordering protocol, or durable applied-state system.

Fresh installation uses the canonical current schema/state. Existing-install
migrations converge an older supported installation toward that canonical
state; fresh installation must not replay the existing-install migration path.

## ZIP intake and staging contract

ZIP is the first required archive format. Intake must use isolated staging and
must reject traversal, absolute or escaping paths, symlinks, special files,
duplicate entries, case-colliding paths, manifest/payload mismatches, and
integrity mismatches.

WU2 v1 locks these hard safety limits: 64 MiB maximum archive bytes, 5,000
entries, 256 MiB total extracted bytes, 64 MiB per regular file, 100:1 per-file
and aggregate compression ratio, 240 canonical relative-path bytes, and 32
path segments. Tests may inject lower limits for deterministic small fixtures.

Staging must be private, separately identified from the live tree, quota-aware
where possible, and cleaned on success, failure, and interruption.

## Apply, maintenance, and commit boundary

Application must protect against partial writes, unsuitable permissions,
shared-hosting limitations, concurrent requests, and replacement of the
currently running Webcore. Package-owned files may be changed only inside the
approved apply boundary. Operator-owned paths remain untouched.

The installed target version/state is committed last. Any failed apply,
migration, reconciliation, health, or integrity gate leaves the prior committed
state unadvanced and leaves enough durable operation information for bounded
repair or operator recovery.

## Backup & Recovery dependency

Backup & Recovery is a separate platform capability consumed by Package
Lifecycle. It is not a Package Lifecycle Work Unit and is not implemented by
this contract.

Package Lifecycle preparation may define and consume a bounded recovery
interface. Production-grade acceptance of destructive or in-place file/schema
transitions requires an approved restore-capable Backup & Recovery
implementation. Without that capability, planning, validation, dry-run,
non-destructive checks, and test doubles may proceed, but production closure of
irreversible apply and migration behavior is not accepted.

## Work Unit decomposition

### WU1 — Webcore Package Contract & Release Identity

**Objective:** Lock the logical package, ownership, release identity, and
compatibility contract.

**Principal deliverables:** Contract fields, identity boundaries, ownership
rules, package-vs-source distinction, and compatibility decisions.

**Direct dependencies:** Existing package-builder and version evidence.

**Important exclusions:** Serialization implementation, signing, remote
discovery, Module package implementation.

**Acceptance evidence:** Reviewed contract examples, invalid-contract cases,
and consistency with distribution documentation.

**Runtime/browser/human validation:** Not materially required.

### WU2 — ZIP Intake, Validation & Isolated Staging

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver safe receipt, inspection, extraction, bounds, and cleanup.

**Principal deliverables:** ZIP security rules, staging boundary, ownership
checks, manifest/payload consistency rules, and locked-limit disposition.

**Direct dependencies:** WU1.

**Important exclusions:** File application, remote download, signing.

**Acceptance evidence:** Focused archive threat, staging, inventory, and cleanup
cases passed. The ext-zip-disabled executor and Windows filesystem
symlink-cleanup limitations remain non-blocking evidence limitations.

**Runtime/browser/human validation:** Browser validation is not materially
required. Runtime capability evidence established the required local
`ZipArchive` boundary; a separate ext-zip-disabled executor is still required
to exercise the fail-closed capability branch.

### WU3 — Installed-State Registry & Transition Planner

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver serialization-neutral installed-state inspection and one
shared forward transition planner without selecting a new persistence format.

**Principal deliverables:** Serialization-neutral installed-state
status/snapshot model; explicit existing-installation evidence inspection;
FRESH, LEGACY, COMMITTED, INCONSISTENT, and INVALID state handling; shared
INSTALL/PATCH/UPDATE/UPGRADE/REPAIR classification; canonical-current
fresh-install policy; source/runtime compatibility evaluation; strict
downgrade rejection; legacy bootstrap-required behavior; and opaque
schema/migration identity handoff toward WU4.

WU3 preserves the exact existing two-field `storage/installed.lock` format and
selects no new installed-state serialization or persistence format. Durable
interrupted-operation persistence, operation records, and recovery behavior are
outside WU3 and remain later WU5 concerns. WU3 does not apply package files,
execute migrations, or change Module package lifecycle or Backup & Recovery
boundaries.

**Direct dependencies:** WU1 and WU2.

**Important exclusions:** Persistence schema, apply execution, generic rollback.

**Acceptance evidence:** Focused state and transition tests covering absent
marker with and without existing-install evidence, valid legacy and malformed
markers, supported forward transitions, same-version repair, legacy bootstrap
rejection, incompatible runtime/source state, and downgrade rejection.

**Runtime/browser/human validation:** Not materially required.

### WU4 — Core Migration Registry & Runner

**Objective:** Define ordered forward Core migration semantics and durable
applied-state requirements.

**Principal deliverables:** Migration identity, ordering, applicability,
pre/postconditions, retry behavior, checksum meaning, and canonical-schema
convergence rules.

**Direct dependencies:** WU1 and WU3.

**Important exclusions:** Module migrations, downgrade/reverse migration,
execution of existing upgrade files during preparation.

**Acceptance evidence:** Migration lifecycle matrix and failure/retry cases;
existing upgrade files explicitly classified as inputs, not a system.

**Runtime/browser/human validation:** Not materially required during
preparation.

### WU5 — Webcore Apply, Maintenance & Interrupted-Operation Boundary

**Objective:** Define package-owned file application, maintenance, concurrency,
permissions, interruption, and recovery-interface boundaries.

**Principal deliverables:** Apply ownership contract, maintenance state,
partial-write behavior, interrupted-operation handling, and bounded recovery
interface.

**Direct dependencies:** WU2, WU3, WU4, and the separately approved bounded
recovery interface decision.

**Important exclusions:** Backup & Recovery implementation, destructive
rollback, Module package application.

**Acceptance evidence:** Failure-mode matrix and restore dependency gate.

**Runtime/browser/human validation:** Runtime validation becomes material when
implementation begins; browser validation is not required for preparation.

### WU6 — Health, Integrity & Commit-State Closure

**Objective:** Define the mandatory gates before installed state advances.

**Principal deliverables:** File integrity, runtime/bootstrap, database/schema,
migration, module/theme, public/Admin smoke, maintenance-clear, and final
commit conditions.

**Direct dependencies:** WU3, WU4, and WU5.

**Important exclusions:** Health implementation, browser automation, release
publication.

**Acceptance evidence:** Gate matrix proving every failed gate leaves the prior
committed target unchanged.

**Runtime/browser/human validation:** Runtime and browser validation are not
materially required during preparation; they become required for implementation
acceptance where the gate requires them.

### WU7 — CLI Operator Surface & End-to-End Non-Recovery Acceptance

**Objective:** Define the first operator surface over the shared lifecycle
service and its non-recovery acceptance boundary.

**Principal deliverables:** Local ZIP path, plan/status/apply/repair direction,
safe diagnostics, and end-to-end acceptance scope excluding real restore.

**Direct dependencies:** WU1–WU6.

**Important exclusions:** Admin upload as lifecycle authority, automatic update,
remote source, production recovery closure.

**Acceptance evidence:** End-to-end fresh and forward-package scenarios using a
shared service, with recovery-dependent cases explicitly blocked.

**Runtime/browser/human validation:** Runtime validation is materially required
for implementation acceptance; browser/human validation is not required for the
preparation contract.

## Separate dependency: Backup & Recovery Foundation

Backup & Recovery is a separate platform capability. Package Lifecycle may
prepare against its bounded interface, but production-grade lifecycle closure
requires a real restore-capable implementation and integration evidence.

## Explicit exclusions

Outside the delivered WU1 and WU2 boundaries, this contract does not authorize:

- installed-state registry or transition-planner implementation;
- further PHP or source implementation beyond the accepted WU2 boundary;
- schema or migration execution;
- package-builder changes;
- runtime synchronization or browser validation;
- Module package lifecycle implementation;
- Backup & Recovery implementation;
- remote discovery, signing, channels, marketplace, automatic updates;
- generic downgrade, reverse migration, or differential packages.
