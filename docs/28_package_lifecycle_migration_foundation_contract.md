# Package Lifecycle & Migration Foundation

## Preparation status

Status: WU1 COMPLETE AND CLOSED — WU2 COMPLETE AND CLOSED — WU3 COMPLETE AND CLOSED — WU4 COMPLETE AND CLOSED — WU5 COMPLETE AND CLOSED — WU6 COMPLETE AND CLOSED — WU7 COMPLETE AND CLOSED

Next work unit: NONE — WU1–WU7 COMPLETE AND CLOSED

This document records the completed Post-M3 platform-foundation target and its
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

Module Package Lifecycle preparation is now complete and contract-locked in
`docs/29_module_package_lifecycle_contract.md`. It is the second target over
this foundation and uses shared primitives through reuse, generalization, or
target adapters as documented there. Module Package Lifecycle implementation
and WU1 remain not started.

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

WU5 provides bounded in-place package-owned application with normalized
live-root containment and component-wise ancestor validation. Safe activation
is an explicit platform capability; replacement fails closed when atomic
semantics cannot be proven. The complete staged payload is preflighted before
the first live mutation, and files are revalidated by streamed size and SHA-256
checks while copying. WU5 performs no stale package-owned deletion.

`InstallationMutex` provides lifecycle exclusion. One private file-backed
durable lifecycle-operation record is created before mutation; maintenance is
derived from its non-terminal state, and interruption is classified from that
state plus mutex ownership. The record binds operation, package, staging,
apply-plan, migration, and deterministic progress identities. WU5 coordinates
WU4 migration execution and blocks `INDETERMINATE` outcomes, then hands off to
WU6 without advancing committed installed state.

Package-owned files may be changed only inside the approved apply boundary.
Operator-owned and runtime-owned paths remain untouched. Destructive rollback
and restore remain unavailable without Backup & Recovery.

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

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver ordered forward Core migration semantics and durable
applied-state requirements.

**Principal deliverables:** Explicit immutable Core migration IDs and permanent
monotonic sequence ordering; an explicit code-defined registry rather than
filename discovery or ordering; checksum-protected descriptor/executable
identity; a database-backed durable applied-migration ledger; deterministic
migration-state identity binding committed state to applied history; exact
registry-prefix validation; evolving virtual Webcore/schema/migration state for
unapplied forward migrations; TRANSACTIONAL and NON_TRANSACTIONAL execution
modes; deterministic preconditions/postconditions and bounded retry semantics;
canonical fresh-install ledger/schema baseline without historical migration
replay; and strict downgrade/reverse rejection.

Legacy installations without an approved migration baseline remain blocked.
Ambiguous non-transactional outcomes remain for later WU5 handling. WU4 does
not add durable interrupted-operation/job persistence, package-file application,
or maintenance behavior. The WU3 `storage/installed.lock` boundary remains
unchanged; Module migration lifecycle remains later, and Backup & Recovery
remains separate.

**Direct dependencies:** WU1 and WU3.

**Important exclusions:** Module migrations, downgrade/reverse migration,
execution of existing upgrade files during preparation.

**Acceptance evidence:** Focused migration lifecycle and failure/retry tests;
committed-state/applied-ledger consistency and identity cases; evolving virtual
suffix planning; canonical fresh-install baseline evidence; and existing
upgrade files explicitly classified as inputs, not a migration system.

**Runtime/browser/human validation:** Not materially required during
preparation.

### WU5 — Webcore Apply, Maintenance & Interrupted-Operation Boundary

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver bounded package-owned file application, maintenance,
concurrency, interruption, and recovery-interface boundaries without advancing
committed installed state.

**Principal deliverables:** Guarded in-place apply; explicit activation
capability; complete staged-payload preflight; streamed file identity checks;
no stale-file deletion; `InstallationMutex` lifecycle exclusion; private
file-backed durable operation state; derived maintenance and interruption
classification; deterministic progress/apply-plan/package identity binding;
WU4 migration coordination including `INDETERMINATE` blocking; and WU6
handoff without installed-state advancement.

**Direct dependencies:** WU2, WU3, WU4, and the separately approved bounded
recovery interface decision.

**Important exclusions:** Backup & Recovery implementation, destructive
rollback/restore, stale package-owned deletion, generic job infrastructure,
Module package application, WU6 health gates, and installed-state commit.

**Acceptance evidence:** Focused apply, preflight, containment, activation,
operation-state, interruption, maintenance, migration-handoff, and WU6-boundary
tests passed. The WU2 regression was unavailable because the local XAMPP
ZipArchive API lacked the required capability, and the WU4 regression was
unavailable because PDO SQLite was unavailable; these are execution-environment
limitations, not observed regressions.

**Runtime/browser/human validation:** Focused runtime validation passed for the
WU5 boundary; browser validation was not required. The WU2 and WU4 regression
limitations are recorded above as execution-environment evidence limitations.

### WU6 — Health, Integrity & Commit-State Closure

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver the mandatory health, integrity, and finalization gates before installed state advances.

**Principal deliverables:** Bounded rich committed lifecycle state under the
private lifecycle storage namespace; exact preservation of the two-field
`storage/installed.lock`; WU3 inspection integration producing COMMITTED only
when rich state and the legacy marker agree; target package-owned live-tree
integrity without stale ownership inference from arbitrary extra paths;
database/schema health; WU4 migration-ledger prefix and integrity verification;
final migration-state identity derivation; deterministic runtime/bootstrap,
module/theme, public, and Admin health gates; `InstallationMutex` finalization;
committed-state persistence before operation completion and maintenance
clearing; cleanup-pending state and exact identity reconciliation for idempotent
cleanup retry.

**Direct dependencies:** WU3, WU4, and WU5.

**Important exclusions:** Package application, migration execution, stale-file
deletion, rollback/restore, Backup & Recovery, Module package lifecycle, browser
automation, and release publication.

**Acceptance evidence:** Focused health/integrity/commit-state validation proves
that failed pre-commit gates preserve the prior committed target, and that
cleanup retry requires exact committed/package/operation/apply-plan/
migration-plan/ledger reconciliation without rerunning file application,
migrations, or full health gates. The WU4 PDO SQLite regression could not
execute because the available executor lacked the PDO SQLite driver; this is an
environment evidence limitation, not an observed regression.

**Runtime/browser/human validation:** Deterministic runtime/bootstrap and HTTP
health-gate boundaries were implemented; browser validation is not required for
WU6.

### WU7 — CLI Operator Surface & End-to-End Non-Recovery Acceptance

**Status:** COMPLETE AND CLOSED.

**Objective:** Deliver the first operator surface over the shared lifecycle
service and its non-recovery acceptance boundary.

**Principal deliverables:** `bin/copot` with `package:plan`, `package:apply`,
`package:repair`, and `package:status`; human/JSON output; deterministic
exit-code mapping; strict `.copot/package.json` metadata; official
package-builder lifecycle manifest/inventory emission; staged metadata
exclusion from the live payload; shared `PackageLifecycleService` orchestration
over WU1–WU6; planner-derived PATCH/UPDATE/UPGRADE classifications; bounded
installed-state/operation/maintenance status reporting; materially real
runtime/bootstrap/module/theme/public/Admin health probes; and an external
project-isolated apply-temporary namespace.

**Direct dependencies:** WU1–WU6.

**Important exclusions:** Admin upload as lifecycle authority, automatic update,
remote source, production recovery closure.

**Acceptance evidence:** Linux Cloud runtime acceptance consumed the official
builder artifact through the actual WU7 CLI/shared lifecycle path, performed
real package-owned live-file replacement and database-backed lifecycle
behavior, reached WU6 committed target state, cleaned the lifecycle operation,
made maintenance inactive, and reported the committed result through
`package:status`. A tampered package/inventory was rejected safely through the
same CLI surface. Repository source remained unchanged during final E2E
acceptance.

**Runtime/browser/human validation:** Runtime validation passed in the Linux
Cloud acceptance environment; browser/human validation is not required.

The accepted Webcore package lifecycle first delivery slice is complete and
closed for local/operator-provided package operation over an existing Copot
runtime. It does not claim all possible Webcore installation or distribution
capability. Remote discovery/download, signing/trust, channels, automatic
updates, Admin upload, differential packages, downgrade/reverse migration,
Module Package Lifecycle, and destructive rollback/restore remain excluded;
Backup & Recovery remains separate. Existing-Runtime Webcore Lifecycle
Adoption is a separate prerequisite capability documented in
`docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`; it does not
reopen this completed WU1–WU7 foundation slice.

### Deferred Item — DI-PACKAGE-LIFECYCLE-WU7-01

- **Title:** Server-Empty Bootstrap & Package Clean Install
- **Status:** Deferred
- **Detail:** Provide a standalone bootstrap path for a server where Copot
  source/runtime is not yet present, capable of acquiring/receiving a Webcore
  package, validating it, establishing the Copot runtime, and handing off into
  the canonical installation/lifecycle path.
- **Reason:** Official Copot package hosting/release source and a stable
  release-metadata/download contract do not yet exist.
- **Impact:** WU7 and the existing-runtime local-package Webcore lifecycle
  remain accepted. True server-empty package-driven installation is not
  delivered and must not be implied by current closure wording.
- **Revisit trigger:** An official Copot package hosting/release source exists
  with a stable package metadata and download contract.
- **Initial target disposition:** Unscheduled / KEEP DEFERRED

## Separate dependency: Backup & Recovery Foundation

Backup & Recovery is a separate platform capability. Package Lifecycle may
prepare against its bounded interface, but production-grade lifecycle closure
requires a real restore-capable implementation and integration evidence.

## Explicit exclusions

Outside the delivered WU1–WU5 boundaries, this contract does not authorize:

- further PHP or source implementation beyond the accepted WU5 boundary;
- package-builder changes;
- runtime synchronization or browser validation;
- Module package lifecycle implementation;
- Backup & Recovery implementation;
- remote discovery, signing, channels, marketplace, automatic updates;
- generic downgrade, reverse migration, or differential packages.
