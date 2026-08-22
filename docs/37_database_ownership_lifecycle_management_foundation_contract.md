# Post-M3 — Database Ownership & Lifecycle Management Foundation Contract

## Status and authority

```text
Database Ownership & Lifecycle Management Foundation: PROMOTED / CONTRACT LOCKED
Preparation audit: COMPLETE
Implementation: WU1 COMPLETE AND CLOSED; WU2 COMPLETE AND CLOSED; WU3 COMPLETE AND CLOSED; WU4 COMPLETE AND CLOSED; WU5 COMPLETE AND CLOSED; WU6 COMPLETE AND CLOSED
WU topology: WU1–WU6 LOCKED
Next active implementation target: NONE within this six-WU topology
Implementation authorization: NOT IMPLIED BY THIS CONTRACT
Branch lifecycle: integrated WU6 state is on `main`; `feature/database-lifecycle-wu6-cross-lifecycle-acceptance` remains fully contained in `main` with zero commits ahead, pending separately authorized cleanup
```

This is the authoritative repository contract for the Post-M3 Database Ownership
& Lifecycle Management Foundation.

It promotes the accepted planning direction for database table ownership,
schema compatibility, migration authority, System Manager Webcore lifecycle,
database lifecycle classification, Installer intent reconciliation, and
cross-lifecycle acceptance.

Promotion of this contract establishes scope and authority boundaries only. It
does not authorize source/schema/runtime mutation, WU implementation, production
reconciliation, Deferred Item adoption, branch creation/deletion, release, tag,
or publication.

## Core invariant

Every database table has exactly one authoritative owner.

Allowed owners are:

- Webcore; or
- one specific Module.

Shared usage does not create shared ownership.

COPOT must not introduce jointly owned tables. The owner is authoritative for
canonical schema, schema/database lineage, compatibility, migration, repair,
and lifecycle transition behavior for that schema surface.

Ownership is logical. Physical table names remain resolved through the accepted
installation namespace/table-name boundary.

The catalog in this contract records and enforces the delivered current
ownership baseline. WU1 of the Post-M3 Webcore & Extension Architecture
Reconciliation adds explicit target-owner and transition-work-unit metadata
without changing current runtime authorization. A target transition is not a
runtime reclassification: the current owner continues to authorize schema and
data mutation until its later extraction WU closes.

## Authoritative ownership model

The target architecture requires one authoritative logical ownership
catalog/registry rather than inferring authority from runtime consumers,
directory placement, aggregate installer provisioning, or migration location.

The authoritative owner proof must be able to identify at least:

- logical table identity;
- owner type: Webcore or Module;
- Module identity when Module-owned;
- canonical schema source;
- namespace-aware physical resolution;
- authorized extension grants where applicable;
- historical-baseline mapping where applicable.

The ownership catalog is authority metadata. It is not a second migration
engine.

## Locked current Webcore ownership baseline

The following current schema surfaces are classified as Webcore-owned for this
workstream:

- `users`;
- `roles`;
- `permissions`;
- `user_roles`;
- `role_permissions`;
- `settings`;
- `themes`;
- `modules`;
- `module_permissions`;
- `core_migration_history`;
- `core_schema_generation`.

Management Modules or Admin surfaces may consume these tables without owning
them. In particular, `modules` and `module_permissions` are generic Webcore
platform registry/lifecycle schema despite their names.

## Locked current Module ownership baseline

The following current bundled capability tables are classified as Module-owned:

### Navigation

- `navigation_menus`;
- `navigation_items`;
- `navigation_menu_assignments`.

### Content

- `content`.

### Taxonomy

- `taxonomy_types`;
- `taxonomy_terms`;
- `taxonomy_assignments`.

### Media

- `media`;
- `media_variants`;
- `media_usages`.

### Redirects

- `redirects`.

### Form Manager

- `forms`;
- `form_fields`;
- `form_field_options`;
- `form_submissions`;
- `form_submission_values`;
- `form_submission_attempts`.

A Module-owned table is not a Webcore prerequisite merely because the current
aggregate installer schema historically provisions it.

## Historical global pre-provisioning

Accepted historical installations provision Module-owned tables through the
aggregate installer schema.

That provisioning history is valid compatibility evidence, not ownership
authority.

Locked reconciliation rules:

- historically valid installations remain valid;
- historical global provisioning does not transfer Module ownership to Webcore;
- owner-aware future materialization must not invalidate an installation merely
  because Module tables were historically pre-provisioned;
- historical state must be mapped to the canonical owner model before later
  owner-sensitive transitions;
- ownership metadata modernization alone does not justify rebuilding a valid
  installation.

The target historical posture is classify-and-map/adopt rather than invalidate
or rebuild.

## Webcore tables provided for Module use

Webcore may intentionally provide shared platform schema for Module use.

Those tables remain Webcore-owned. Consumption does not transfer or dilute
ownership.

## Module extension boundary

A Module may extend a Webcore-owned table or another Module-owned table only
through an explicit owner-authorized platform contract.

The target table remains owned by its existing authoritative owner. An
extension never creates shared ownership or transfers ownership.

Default permitted extension surface for this foundation:

- add a Module-specific column;
- add a bounded Module-specific index associated with an authorized extension.

Not permitted by default:

- dropping or renaming target-owner schema;
- repurposing target-owner schema;
- destructive modification of target-owner columns or indexes;
- arbitrary DDL against target-owner tables;
- new cross-owner constraints;
- new cross-owner foreign keys.

Any widening beyond add-column/add-index requires an explicit later contract
decision.

## Extension provenance

Extension provenance is separate from table ownership.

An authorized extension must identify at least:

- extending Module;
- authoritative target owner (Webcore or one specific Module);
- affected table;
- added schema element(s);
- migration/declaration identity;
- lifecycle operation/transition that authorized the extension.

Extension provenance must never be represented as shared table ownership.

## Cross-Module schema boundary

A Module must not write directly to another Module's private schema.

Default policy:

- private cross-Module table references are prohibited;
- cross-Module foreign keys are prohibited;
- a bounded add-column/add-index extension is permitted only when an explicit
  owner-authorized extension grant identifies the target Module owner;
- all other cross-Module schema writes remain prohibited;
- normal cross-Module integration should use public service, event, registry,
  API, or declared dependency boundaries.

## Independent schema/version lineages

Webcore schema/database lineage and each Module schema/database lineage evolve
independently.

Webcore owns Webcore schema and migrations.

Each Module owns its Module schema and migrations, plus only explicitly
authorized extensions.

The architecture must not collapse these lineages into one generic global
`DB_VERSION`.

## Compatibility model

Compatibility is evaluated from declared compatibility plus an available
authorized transition path.

Material inputs may include:

- current and target Webcore package/version;
- current and target Webcore schema state;
- current and target Module package/version;
- current and target Module schema state;
- required Webcore capabilities;
- Module dependencies;
- available migration path;
- historical-baseline classification.

Unsupported transitions fail closed.

A future release-support policy may define a bounded historical support window,
but runtime enforcement must not be reduced to arithmetic on one global database
version.

WU2 locks the following compatibility behavior:

- supported states are the current lineage and historical baselines already
  recognized by canonical-baseline, legacy-classification, adoption, or
  recovery machinery, only where an authorized forward transition exists;
- compatibility reuses minimum-inclusive/maximum-exclusive
  `PackageCompatibility` semantics;
- downgrade and reverse migration are unsupported and fail closed;
- unsupported state exposes a stable machine-readable status/code and concise
  sanitized human reason without raw SQL, paths, credentials, or exceptions.

## Existing migration machinery remains authoritative execution machinery

This workstream does not create a new migration engine.

Existing Package Lifecycle & Migration machinery remains the execution
machinery.

Locked relationship:

- ownership decides **who may migrate what**;
- lifecycle machinery decides **how an authorized transition executes**;
- Webcore migration machinery may mutate only Webcore-owned schema;
- Module migration machinery may mutate only that Module's schema plus explicit
  extension grants;
- attempted migration outside authority fails closed.

## Lifecycle-bounded migration authorization

Database/schema mutation is permitted only inside an authorized lifecycle
operation.

The authorization context must bind at least:

- installation identity;
- database namespace;
- lifecycle operation identity;
- owner type and owner identity;
- authorized migration set;
- allowed logical schema surface;
- authorized extension grants;
- source state;
- target state;
- compatibility gate;
- completion/failure state.

Raw PDO/database connection access is not itself migration authority.

The authorization layer belongs at existing planner/coordinator/runner
boundaries and must not create a parallel migration engine.

## Multi-Installation, namespace, and shared-tableset verdict

The ownership model must preserve installation identity, namespace isolation,
collision detection, routing, and independent lifecycle boundaries.

Locked verdicts:

### Same database, different namespaces

Supported. Distinct namespaces resolve to distinct physical COPOT object sets.

### Same prefix / namespace

Not valid as two independent installations. The same namespace resolves to the
same physical tableset.

### Full shared COPOT tableset

Valid only as one legitimate shared installation/state topology recognized by
the accepted installation/runtime identity model.

It must not be represented as two independent installations both claiming the
same physical tables.

### Partial COPOT table sharing

Rejected / fail closed.

Partial sharing would create ambiguous ownership, identity, compatibility,
migration, and recovery authority.

## System Manager relationship

System Manager is the intended operator-facing surface for normal existing
Webcore lifecycle operations.

Target capability includes:

- intake of a full released Webcore ZIP;
- Update;
- Upgrade;
- Repair / Retry / Reconciliation;
- compatibility evaluation;
- authorized Webcore-owned migration execution when required;
- lifecycle result/status.

System Manager consumes the existing Package Lifecycle & Migration Foundation.
It is not the migration engine, schema owner, or release-metadata authority.

Normal Webcore update remains a bounded in-place operation against the existing
installation root while preserving operator/runtime-owned state.

## Database lifecycle classification

### Case A

Webcore package/version changes and database migration is required.

Disposition: **NORMAL WEBcore UPDATE / UPGRADE**.

### Case B

A previously authorized migration failed or is incomplete.

Disposition: **REPAIR / RETRY / RECONCILIATION**.

### Case C

Database requires forward transition while Webcore package/version remains
unchanged and no failed prior operation exists.

Locked negative rule:

- COPOT must not expose a generic globally available `Update Database` operation
  or button.

Bounded direction:

- Case C may exist only as an explicitly declared same-version schema-forward
  maintenance/reconciliation transition;
- it must be package-defined;
- it must have a valid authorized migration path;
- it must use the same ownership and lifecycle authorization boundaries as other
  transitions.

WU4 closure locks:

- operator-facing name;
- exact eligibility rules;
- whether the operation is directly exposed or only lifecycle-derived;
- release/support policy for same-version schema-forward transitions.

## Installer intent reconciliation

The target Installer intent model is:

- Fresh;
- Coexist;
- Adopt / Use Existing Installation.

### Fresh

Create a new COPOT installation.

### Coexist

Create a new independent COPOT installation alongside existing database objects
or another COPOT installation using a safe distinct namespace.

### Adopt

Use an existing compatible COPOT installation.

Adopt:

- uses one positively proven compatible existing COPOT installation;
- preserves the proven namespace and existing Administrator/User/Site state;
- does not provision schema or tables, silently migrate, repair, or create an Administrator;
- skips the Installer Administrator & Site phase and proceeds to Review & Install / Installation Result;
- does not perform normal Webcore Update / Upgrade / Repair;
- requires compatibility;
- fails closed on incompatible, ambiguous, contradictory, unhealthy, partial, colliding, or unprovable state.

Normal existing-install Update / Upgrade / Repair belongs to System Manager /
Webcore Lifecycle, not Installer.

The accepted Multi-Installation and MR.1 Installer contracts remain historical
and current-authority evidence within their defined boundaries. WU5 subsequently
reconciled current Installer semantics to Fresh, Coexist, and Adopt; normal
existing-install Update / Upgrade / Repair remains owned by System Manager /
Webcore Lifecycle. Review & Install remains the first installation-owned
mutation boundary unless later accepted evidence explicitly amends that
contract.

## Work Unit topology

This workstream has exactly six Work Units.

### WU1 — Table Ownership & Authority

Objective:

- establish the authoritative owner inventory;
- establish the ownership catalog/proof model;
- reconcile historical pre-provisioned Module tables;
- establish extension provenance;
- establish cross-owner schema boundaries.

Dependency: **NONE**.

Status: **COMPLETE AND CLOSED**.

The WU1 implementation establishes the locked ownership catalog, historical
aggregate-installer provenance classification, namespace-aware physical lookup,
and bounded owner-authorized cross-owner extension provenance. Focused WU1
validation passes 34 assertions. WU1 does not authorize or execute migrations,
schema splitting, Installer changes, or later Work Units.

### WU2 — Schema Compatibility & Migration Authority

Objective:

- enforce owner-aware migration authority;
- preserve independent Webcore/Module lineages;
- establish compatibility relationship/window policy;
- bind migration authorization to lifecycle operation and namespace;
- fail closed on unauthorized schema mutation.

Dependency: **HARD → WU1**.

Status: **COMPLETE AND CLOSED**.

WU2 binds migration execution to installation identity, namespace, lifecycle
operation, owner identity, migration identity/checksum, source and target
state, compatibility, declared schema surface, and WU1 extension grants.
Migration callbacks receive an authorization-aware context with bounded
mutation primitives; unrestricted PDO is not a migration authority surface.
Compatibility remains minimum-inclusive/maximum-exclusive, forward-only, and
fails closed for unsupported or unrecognized historical states. Existing
canonical-baseline, adoption, and recovery evidence is reused without
fabricating ledger history. Webcore and Module migration lineages remain
independent.

### WU3 — System Manager Webcore Lifecycle Capability

Objective:

- expose the accepted Webcore lifecycle capability through System Manager;
- support released Webcore ZIP intake, Update, Upgrade, Repair/Retry/
  Reconciliation;
- evaluate compatibility;
- invoke only authorized Webcore migrations;
- report lifecycle result/status.

Execution engine: existing Package Lifecycle & Migration Foundation.

Dependency: **HARD → WU1 + WU2**.

Status: **COMPLETE AND CLOSED — FOCUSED IMPLEMENTATION/VALIDATION EVIDENCE ACCEPTED**.

WU3 delivered the bounded operator layer over the existing Package Lifecycle engine. It adds the dedicated `system.webcore.manage` Admin boundary, private upload staging, sanitized preflight/result handling, and mandatory fail-closed recovery gating. WU3 does not introduce a migration, package, or recovery engine and does not expose WU4 database-only Case C behavior.

Recovery Slice B2a extracts one factory-owned recovery composition. Recovery Slice
B2b injects its production `ProtectedWebcoreMutationBoundary` into the existing
`WebcoreApplyCoordinator`; the coordinator remains the sole lifecycle-mutex
owner, and package-file plus Core-migration mutation remains inside the live
recovery permit. Focused B2b evidence passed 33 assertions. Recovery Slice C binds
persisted recovery identity/manifest evidence for Retry and delegates System Manager
execution through Package Lifecycle with sanitized status fields. Focused Slice C
evidence covers 23 assertions. The real Retry invocation and eligibility
evidence are accepted for WU3 closure. Full production-like persisted Retry
success-path execution was carried forward as a mandatory WU6 cross-lifecycle
acceptance scenario and was subsequently satisfied by the accepted
production-composed WU6 persisted Retry E2E acceptance. This carried requirement
was not a WU3 defect or Deferred Item.

### WU4 — Database Lifecycle Classification

Objective:

- formalize Case A/B/C;
- lock Case C eligibility and operator semantics;
- prevent generic globally writable database-update behavior.

Dependency: **HARD → WU1 + WU2**.

Additional gate satisfied: Case C product/lifecycle semantics were explicitly
locked before WU4 mutation behavior was implemented.

Status: **COMPLETE AND CLOSED — CLASSIFICATION/WIRING IMPLEMENTATION AND VALIDATION ACCEPTED**.

Locked Case C semantics for this slice:

- operator-facing action: **Database-only Update**;
- eligibility is lifecycle-derived only from a trusted same-Webcore-version
  package with a declared, accepted, non-empty forward Core migration plan;
- same-version packages without a forward Core migration remain **Repair**;
- the operation reuses existing ownership, migration authorization, recovery,
  retry, and reconciliation boundaries; no generic database-update operation is
  exposed.

Accepted WU4 evidence is integrated into authoritative `main` at
`70782fdc9dddeb353cf27cf78f6f20e294e6fa30`. The focused WU4 and WU3/System
Manager validation, impacted recovery/retry validation, PHP lint, and diff
checks passed. The Module namespace test failure is a separate pre-existing /
environmental repository risk: it reproduces on the accepted WU4 base and on
the final WU4 `main` under the same PHP/MySQL environment.

### WU5 — Installer Intent Reconciliation

Objective:

- reconcile Fresh / Coexist / Adopt with owner-aware provisioning;
- preserve valid historical installations;
- keep normal existing-install lifecycle operations outside Installer;
- preserve namespace and installation-identity safety.

Dependency: **HARD → WU1**.

WU5 consumes accepted WU3/WU4 outcomes where System Manager or database
lifecycle classification affects Installer routing.

Accepted outcome: Installer exposes exactly **Fresh**, **Coexist**, and **Adopt / Use Existing Installation**. Fresh and Coexist retain namespace and installation-identity collision safety and validate provisioning against the authoritative ownership catalog while preserving valid historical aggregate-schema compatibility. Adopt is available only for one positively proven compatible existing installation, preserves its namespace and existing state, performs no schema provisioning, migration, repair, Administrator creation, or Site setup, and fails closed on unsupported or ambiguous state. Normal existing-install Update / Upgrade / Repair remains outside Installer under System Manager / Webcore Lifecycle.

Integration evidence: WU5 was accepted at `eb9367ec4e81b4e6b88c6893a68984a22c74b80a` and fast-forward integrated into `main`; the feature branch was subsequently deleted locally and remotely after containment and zero-ahead verification.

Status: **COMPLETE AND CLOSED**.

### WU6 — Cross-Lifecycle Acceptance

Objective:

Prove coherent boundaries across:

- Installer;
- installation identity;
- namespace isolation;
- System Manager;
- Webcore lifecycle;
- Module lifecycle;
- Webcore-owned migrations;
- Module-owned migrations;
- compatibility enforcement;
- ownership enforcement;
- repair/recovery boundaries.

Dependency: **HARD → WU1–WU5**.

Status: **COMPLETE AND CLOSED — IMPLEMENTATION AND ACCEPTANCE EVIDENCE INTEGRATED**.

Accepted evidence includes the mandatory production-composed persisted Retry
success path: 15 assertions passed for persisted evidence acceptance, System
Manager routing, recovery resume and mutation permit, package-owned file
mutation, final health/integrity finalization, committed lifecycle state,
operation-lineage preservation, absence of terminal `awaiting_wu6`, and retained
staging cleanup. The accepted WU6 implementation is integrated into authoritative
`main` at `91de0fd829e52d185c4823fe27d7e849bf147622`. The package used an
accepted/no-op Core migration plan, so no migration execution was required by
that package. No further implementation WU is defined in this six-WU topology.

## Acceptance model

### AI / deterministic acceptance

Prefer deterministic evidence for:

- owner-inventory completeness and uniqueness;
- namespace-aware physical resolution;
- same-prefix independent-install rejection;
- partial-sharing fail-closed behavior;
- owner/extension authorization;
- lifecycle-plan/operation binding;
- compatibility classification;
- historical-baseline mapping;
- independent Webcore/Module lineages;
- repair/retry/indeterminate behavior;
- Installer routing;
- focused regression integrity.

### Genuine human/product acceptance

Human/product decision is required only when objective repository evidence cannot
settle the product meaning.

Current reserved human/product decision classes are:

- exact compatibility support-window policy;
- final Case C operator semantics;
- any widening of extension types beyond add-column/add-index;
- any exception to the default cross-Module private-schema prohibition;
- operator-facing historical-reconciliation posture if exposed to users.

Routine regression testing is not a human acceptance task.

## Interaction with existing authoritative contracts

This contract extends and constrains, but does not silently rewrite, accepted
boundaries in:

- `docs/28_package_lifecycle_migration_foundation_contract.md`;
- `docs/29_module_package_lifecycle_contract.md`;
- `docs/30_existing_runtime_webcore_lifecycle_adoption_contract.md`;
- `docs/34_multi_installation_isolation_foundation_contract.md`;
- `docs/36_mr_1_installation_refinement_contract.md`.

Where later WUs require behavior changes to those accepted surfaces, the
material contract must be explicitly reconciled in that WU before closure.

## Separate unresolved / non-adopted concerns

The following are not automatically adopted by this contract:

- Cross-Fileset Upgrade Ownership Proof Gap — separate / unresolved /
  non-blocking by default;
- Stale Package-Owned File Reconciliation — separate audit candidate;
- Server-Empty Bootstrap & Package Clean Install — KEEP DEFERRED / UNSCHEDULED;
- Production Webcore Reconciliation — NOT STARTED / separately authorized;
- Installation & Runtime Identity Exposure — separate unresolved concern;
- Admin Shell CSS Single-Source — separate unresolved concern;
- MR.2 UI refinement;
- Dashboard refinement;
- release/tag/publication.

Backup & Recovery remains a separate accepted capability. This contract may
require it to consume owner-aware facts later, but does not redefine selective
backup/restore semantics.

## Implementation and mutation boundary

Contract promotion is complete when this document is durable on authoritative
`main` and independently remotely verified.

After promotion:

- WU1 implementation is **COMPLETE AND CLOSED**;
- WU2 implementation is **COMPLETE AND CLOSED**;
- accepted WU2 validation passed 11 focused authority assertions, 30 Core
  lifecycle assertions, 15 Module lifecycle/provisioning assertions, and the
  existing 34 WU1 ownership assertions;
- WU3 implementation is **COMPLETE AND CLOSED** under its HARD dependency on WU1 + WU2;
- Recovery Slices B2a–C and focused implementation/validation evidence are accepted;
- WU4 implementation and accepted validation are **COMPLETE AND CLOSED**;
- WU5 implementation and accepted integration are **COMPLETE AND CLOSED**;
- WU6 implementation, mandatory persisted Retry acceptance, and integrated
  cross-lifecycle evidence are **COMPLETE AND CLOSED**;
- the full Database Ownership & Lifecycle Management Foundation is **COMPLETE
  AND CLOSED** for its accepted scope;
- source/schema/runtime mutation requires a separately authorized execution
  slice;
- no later WU may skip a HARD dependency;
- no Deferred Item or production reconciliation is implicitly adopted;
- no branch, release, tag, or publication action is implied.
