# Multi-Installation Isolation Foundation Contract

## Status and authority

- Multi-Installation Isolation Foundation: **PROMOTED / PREPARATION CONTRACT LOCKED**
- Preparation: **COMPLETE / CONTRACT LOCKED**
- Implementation: **WU1–WU5 COMPLETE; WU6 NOT STARTED**
- WU1 — Installation Identity, Database Namespace, Compatibility & Runtime
  Contract: **COMPLETE AND CLOSED — contract/evidence closure only**
- WU2 — Core Logical/Physical Table Naming, Schema Generation & Core
  Compatibility: **COMPLETE AND CLOSED — implementation/evidence scope**
- WU3 — Module Persistence, Provisioning, Migration & Lifecycle Namespace
  Adoption: **COMPLETE AND CLOSED — implementation/evidence scope**
- WU4 — Runtime, Session, Cookie, Filesystem & Coordination Isolation:
  **COMPLETE AND CLOSED — implementation/evidence scope**
- Next technical target: **WU6 — Cross-Subsystem Integration &
  Multi-Installation / Multi-Runtime Acceptance — implementation NOT STARTED**
- Scope: locked architecture, WU1 contract closure, and WU2–WU5 implementation only
- Production Webcore reconciliation: **NOT STARTED / separately authorized**
- `DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install`: **DEFERRED / UNSCHEDULED**

This contract promotes Multi-Installation Isolation Foundation into the
authoritative Post-M3 work stream. WU1 contract/evidence closure and WU2–WU4
implementation are complete for their accepted scopes. WU6 is the next
technical target and WU6 implementation remains NOT STARTED; any later
implementation authorization must preserve this contract or document an
evidence-backed amendment.

## Relationship to completed foundations

This foundation reuses and integrates the completed Package Lifecycle &
Migration, Module Package Lifecycle, Existing-Runtime Webcore Lifecycle
Adoption, Backup & Recovery, and Webcore Deployment & Portability foundations.
It does not reopen them. Portability continues to own generic `APP_ROOT`,
`PUBLIC_ROOT`, `BASE_PATH`, deployment-root, and subdirectory behavior.

Server-empty package bootstrap and production Webcore reconciliation remain
separate boundaries.

## Preparation-time repository evidence

The focused preparation audit established the following pre-WU2
implementation baseline. These findings are retained as historical evidence;
they do not describe the current WU2–WU4 implementation state.

1. `app/Core/Database.php`, `database/schema.sql`, Core repositories, Module
   repositories, and upgrade SQL used fixed physical table names. No
   centralized logical-to-physical table naming boundary or table-prefix
   implementation existed at the preparation audit checkpoint.
2. At the preparation audit checkpoint, `app/Core/InstallerDatabaseProbe.php`
   classified a database only by the total count of information-schema
   objects and rejected every non-empty database. The then-current
   `app/Core/InstallerSchemaRunner.php` executed the fixed schema without
   namespace substitution.
3. At the preparation audit checkpoint, `core_migration_history` was the fixed
   Core migration ledger table and Module migration state was maintained by
   the existing Module migration ledger under the private `.copot-lifecycle`
   storage namespace. WU2 and WU3 subsequently applied the accepted namespace
   boundary to those existing ledger semantics. These ledgers and the shared
   lifecycle/mutex machinery remain the integration points; this contract does
   not create a second migration framework.
4. At the preparation audit checkpoint, `InstallationMutex`, lifecycle
   operation state, committed lifecycle state, package-apply temporary
   storage, and recovery storage were installation-root or configured-root
   scoped. WU4 subsequently made the accepted installation/runtime scope
   explicit while preserving the existing coordination boundaries.
5. At the preparation audit checkpoint, `config/session.php` and
   `app/Core/Session.php` configured one session name, cookie path, and CSRF
   namespace from deployment configuration. WU4 subsequently added the
   accepted installation-derived session/cookie and runtime-path isolation;
   WU5 remains responsible for installer routing and namespace selection.
6. Existing information-schema inspection in installer, schema health, and
   Backup/Recovery code is available for ownership and compatibility evidence,
   but a generic physical table collision is not ownership proof.

WU2–WU4 subsequently superseded the implementation limitations recorded in
items 1–5 within their accepted scopes. The historical findings did not, and
do not, authorize WU5 or WU6 implementation.

## Locked architecture

### Installation identity and supported topology

Each COPOT installation has a stable `installation_id`, distinct from database
host, database name, table prefix or namespace, domain, filesystem path, and
runtime identity. Identity must be bound and validated under an explicit
lifecycle/authorization rule; it must not be silently inferred from a mutable
deployment coordinate.

The foundation supports independent COPOT installations with disjoint
COPOT-owned database object sets, and one complete COPOT installation/state
attached to one or more runtime participants subject to compatibility,
registration, and coordination gates.

It fails closed for partial sharing of COPOT-owned database objects between
independent installations and for ambiguous or colliding ownership that has
not been explicitly resolved.

COPOT does not provision or manage servers, VMs, containers, Kubernetes, DNS,
load balancers, PHP-FPM processes, or operating-system processes. Runtime
orchestration is outside this foundation.

### Database namespace and ownership

Table prefix/namespace is an isolation mechanism, not installation identity.
WU2 and WU3 provide one logical-to-physical database-object naming boundary
for Core and Module-owned objects, preserve empty-prefix backward
compatibility, and apply namespace-sensitive naming to constraints, indexes,
and foreign keys where material. WU5 owns installer-time namespace
availability, collision analysis against the complete COPOT-owned object set,
explicit intent routing, and fail-closed adoption/coexistence decisions.
Foreign objects remain observational evidence and are never claimed by
routing. COPOT ownership is accepted only when a transient proof assembled
from existing installation identity, Core schema-generation, migration-ledger,
and schema-health evidence matches the observed complete object set; physical
table shape alone is ambiguous.

Routing rules:

- a fresh install into an empty database may use the empty namespace by
  default, and may support an explicit non-empty namespace;
- a new independent installation into a non-empty database must select an
  available non-empty namespace and must not proceed with an unsafe empty
  namespace; and
- adoption or migration of an existing COPOT installation preserves its
  detected namespace, including a legitimately empty namespace.

Independent installations must satisfy:

`OwnershipSet(Installation A) ∩ OwnershipSet(Installation B) = empty`.

Sharing only users, roles, permissions, selected Module tables, or selected
migration state is unsupported.

### Database compatibility and versioning

The contract keeps three distinct concepts:

- **Migration Ledger** — exact applied migration history;
- **Schema Generation** — normalized current database/schema compatibility
  state; and
- **Compatibility Envelope** — Webcore, runtime, and Module versions or
  capabilities permitted to operate on that state.

These concepts must not be collapsed into an arbitrary standalone global
database-version integer. Compatibility ranges may overlap between schema
generations, and Module schema compatibility may require Module-specific state
in addition to Core state.

Before a schema or database transition that may affect multiple registered
runtimes, the design must evaluate compatibility, identify incompatible
participants, prevent unsafe mutation, coordinate with existing
lifecycle/migration/exclusion machinery, revalidate immediately before the
mutation, and update resulting compatibility metadata.

#### Database metadata responsibility

COPOT is responsible for the integrity and authorization semantics of
installation-scoped metadata: installation identity, namespace ownership,
schema generation, compatibility envelope, and Runtime Registry state. The
metadata belongs to the installation it describes and must not be treated as a
global database-server fact or as a shared authority between independent
installations. The Migration Ledger remains the exact migration-history
authority; schema-generation and compatibility metadata describe the permitted
state around that history.

The database server, database name, foreign objects, and unrelated external
metadata remain outside COPOT ownership. Installer occupancy evidence may be
observed and classified, but COPOT must not rewrite or claim foreign objects.
The physical persistence format and migration of the new metadata are later
implementation concerns. Any metadata mutation affecting compatibility or
destructive lifecycle operations requires explicit lifecycle authorization and
the existing coordination/exclusion boundary.

### Runtime Registry

Runtime Registry is part of this foundation. Each registered runtime
participant has a stable `runtime_id` associated with an `installation_id`.
Transient OS process identity, including PID, is not runtime identity.

The registry contract must define bounded semantics for runtime role and
capabilities, Webcore/runtime version identity, package identity, Module
inventory/version identity where material, deployment identity where material,
registration state, last-seen evidence, compatibility state, and detach
semantics.

The lifecycle vocabulary is expected to include `REGISTERED`, `ACTIVE`,
`STALE`, `DETACHED`, and `INCOMPATIBLE`. `STALE` is evidence requiring policy,
not automatic permission to ignore a runtime during destructive compatibility
decisions. Retired runtimes should normally be explicitly detached rather than
hard-deleted. Runtime operational metadata requires an explicit authorization
boundary.

### Database occupancy and namespace classification

Database occupancy/ownership evidence and requested namespace availability are
separate classifications. The contract must support equivalent meanings for:

| Occupancy / ownership evidence | Namespace availability |
| --- | --- |
| `EMPTY` | `AVAILABLE` |
| `FOREIGN_ONLY` | `PARTIAL_COLLISION` |
| `COPOT` | `FULL_COLLISION` |
| `MULTIPLE_COPOT` | `OWNED_BY_COPOT` |
| `MIXED` | `AMBIGUOUS` |
| `AMBIGUOUS` | |

Repository-native names may be chosen during implementation, but must retain
these distinctions. Generic table-name collision alone is not evidence of
COPOT ownership.

### Installer intent

Installer evidence and user intent are separate inputs. Later routing must
preserve distinct workflows for fresh installation, coexistence as a new
independent installation, existing installation adoption, and existing
installation migration/update.

COPOT must not infer migration solely from COPOT evidence, or infer fresh
coexistence solely because a database is non-empty.

### Runtime, session, filesystem, and coordination isolation

Later implementation must define isolation for session and cookie identity,
runtime-visible filesystem state, cache, temporary files, locks, and package
staging. It must integrate with existing deployment-root and portability
guards, lifecycle operation records, `InstallationMutex`, committed lifecycle
state, Module migration state, and package-apply temporary-root protections.

Shared-state compatibility transitions must use existing lifecycle, migration,
exclusion, and mutex machinery where applicable. A parallel migration or
lifecycle framework is out of scope.

Backup/Recovery must retain installation-scoped database and filesystem
identity, recovery-root separation, and artifact/domain ownership. Integration
must not weaken recovery isolation or silently broaden the completed Backup &
Recovery scope.

## Work units

### WU1 — Installation Identity, Database Namespace, Compatibility & Runtime Contract

Lock installation identity, supported topology, namespace and ownership
invariants, Migration Ledger/Schema Generation/Compatibility Envelope
semantics, database metadata responsibility, Runtime Registry, lifecycle
states, occupancy vocabulary, installer intent, and operational authorization
boundaries.

**Objective status: COMPLETE AND CLOSED — contract/evidence closure only.**
The authoritative contract now explicitly defines every WU1 requirement:

| WU1 requirement | Contract evidence |
| --- | --- |
| Installation identity | Stable `installation_id`, distinct deployment/database/runtime coordinates |
| Supported topology | Disjoint independent installations and compatibility-gated shared-state runtimes; unsupported partial/ambiguous ownership fails closed |
| Namespace and ownership invariants | Logical-to-physical naming boundary, namespace routing, complete ownership-set disjointness |
| Compatibility semantics | Distinct Migration Ledger, Schema Generation, and Compatibility Envelope with transition gates |
| Database metadata responsibility | Installation-scoped COPOT metadata ownership and explicit non-ownership of foreign/database-server state |
| Runtime Registry | Stable `runtime_id`, bounded identity/capability/version/last-seen/compatibility/detach semantics |
| Runtime lifecycle states | `REGISTERED`, `ACTIVE`, `STALE`, `DETACHED`, `INCOMPATIBLE` with stale-state safety rules |
| Occupancy vocabulary | Separate occupancy/ownership and namespace classifications |
| Installer intent | Fresh, coexist, adoption, and migration/update remain distinct from evidence |
| Operational authorization | Explicit lifecycle authorization, existing coordination/exclusion reuse, and bounded runtime metadata responsibility |

No WU1 runtime implementation is claimed or authorized. WU1 contract/evidence
closure, WU2 Core implementation, WU3 Module implementation, and WU4 Runtime
implementation are complete; at that closure checkpoint the next technical
target was WU5. WU5 is now complete and accepted on the current feature branch.

### WU2 — Core Logical/Physical Table Naming, Schema Generation & Core Compatibility

**Objective status: COMPLETE AND CLOSED — implementation/evidence scope.**

WU2 delivered one validated `DatabaseTableNames` boundary for Core-owned
objects, empty-namespace preservation, deterministic non-empty namespace
materialization, namespace-sensitive table/index/constraint/foreign-key
rewriting, installation/namespace-scoped `core_schema_generation` metadata,
and Core schema compatibility resolution that remains distinct from the
existing Core migration ledger. Core repositories, migration access, schema
health, canonical schema verification, and installer schema materialization
use the boundary where applicable. Module-owned persistence and migration
remain outside WU2.

### WU3 — Module Persistence, Provisioning, Migration & Lifecycle Namespace Adoption

**Objective status: COMPLETE AND CLOSED — implementation/evidence scope.**

WU3 delivered namespace-aware Module-owned table access and full-schema/module
SQL materialization, namespaced Module provisioning and repositories,
namespace-scoped Module migration ledger storage and migration context,
package/lifecycle wiring, and Module schema health verification. Core-owned
objects remain governed by WU2; Runtime Registry, session/cookie/filesystem
isolation, installer routing, and cross-subsystem acceptance remain outside
WU3.

### WU4 — Runtime, Session, Cookie, Filesystem & Coordination Isolation

**Objective status: COMPLETE AND CLOSED — implementation/evidence scope.**

WU4 delivered durable installation identity, installation-scoped Runtime
Registry participants and lifecycle states, compatibility evaluation and
fail-closed transition coordination through `InstallationMutex`, installation-
derived session/cookie identity, and isolated runtime cache/temp/lock/staging
and package-apply temporary paths. Runtime/process orchestration remains out
of scope; WU6 cross-subsystem acceptance remains NOT STARTED.

### WU5 — Installer Database Occupancy Classification, Namespace Selection & Existing-Installation Routing

**Objective status: COMPLETE AND ACCEPTED — implementation/evidence scope.**

Implemented occupancy/ownership classification, namespace collision analysis,
fresh-install routing, coexistence routing, adoption/migration routing,
namespace selection, warnings, and fail-closed blocking behavior. The prior
strict empty-database probe remains available for compatibility; WU5 uses
separate inspection and planning APIs. The bounded transient proof assembler
reuses installation identity, committed lifecycle state, Core schema-generation,
migration-ledger identity, and schema-health evidence; table shape alone never
proves COPOT ownership. Focused WU5 validation passed 36 assertions and
DB-backed acceptance passed 10 assertions, including real adoption and
migration/update service routing with zero schema reinstall statements.

### WU6 — Cross-Subsystem Integration & Multi-Installation / Multi-Runtime Acceptance

Prepare acceptance for isolated installations, shared-state runtime
compatibility, installer routing, Portability, Package Lifecycle, Modules,
and Backup/Recovery. Acceptance must prove disjoint ownership, compatibility
gates, explicit intent routing, collision handling, and preservation of the
completed predecessor boundaries.

No work unit authorizes implementation by itself. Implementation requires a
separate explicit scope and validation decision.

## Boundaries and preparation closure

This contract does not adopt or implement server-empty package bootstrap.
`DI-PACKAGE-LIFECYCLE-WU7-01 — Server-Empty Bootstrap & Package Clean Install`
remains **DEFERRED / UNSCHEDULED**.

Production Webcore reconciliation remains **NOT STARTED** and separately
authorized. It is not a prerequisite silently absorbed by this foundation.

The completed Package Lifecycle, Module Package Lifecycle, Existing-Runtime
Webcore Lifecycle Adoption, Backup & Recovery, and Webcore Deployment &
Portability foundations remain complete and closed for their accepted scopes.
This preparation does not reopen historical checkpoints or claim installer,
package, release, or publication completion.

The Multi-Installation Isolation Foundation remains **PROMOTED / PREPARATION
CONTRACT LOCKED** with WU5 implementation/evidence **COMPLETE AND ACCEPTED on
the current feature branch**: WU1 contract/evidence closure, WU2 Core
implementation, WU3 Module implementation, and WU4 Runtime implementation are
complete; WU6 remains NOT STARTED.
Subsequent implementation planning must cite this contract and provide
evidence for any amendment to its topology, ownership, compatibility, runtime,
namespace, or deferred boundaries.
