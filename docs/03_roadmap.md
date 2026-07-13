# Copot Roadmap

## M1.1 Core Bootstrap

### Objective

Establish the foundation of the framework.

### Scope

* Folder Structure
* Configuration System
* Environment Loading
* Autoloader
* Router
* Database Connection
* Application Bootstrap
* Default Page Rendering

### Deliverable

A minimal runnable framework skeleton capable of serving a basic page.

---

## M1.2 User & Authentication

Status: Implemented in v0.2.0.

### Scope

* Users
* Roles
* Permissions
* Login
* Logout
* Sessions

### Deliverable

Working authentication and authorization foundation with session login/logout, CSRF protection, basic roles, basic permissions, and a protected milestone test route.

---

## M1.3 Module Manager

Status: Implemented in v0.3.0.

### Scope

* Install Module
* Enable Module
* Disable Module
* Uninstall Module
* Dependency Validation

### Deliverable

Operational local module management system with discovery, install registration, enable, disable, uninstall registration removal, dependency validation, enabled route loading, and module permission metadata.

---

## M1.4 Theme System

Status: Implemented in v0.4.0.

### Scope

* Theme Loader
* Layout Engine
* Theme Discovery
* Theme Switching

### Deliverable

Operational frontend theme system with local theme discovery, registry, activation, layout rendering, view resolution, theme overrides, controlled active-theme asset serving, and a minimal default theme.

---

## M1.4.1 Admin Shell

Status: Implemented in v0.4.1.

### Scope

* Configurable Admin Path
* Admin Login
* Admin Logout
* Admin Access Permission
* Admin Layout
* Minimal Dashboard

### Deliverable

Minimal core admin shell with configurable single-segment admin path, same-path admin login, CSRF-protected admin logout, `admin.access` permission guard, static Dashboard navigation, and a responsive dashboard/status page.

---

## M1.5 Content Module

Status: Implemented in v0.5.0.

### Scope

* Content Module
* Basic Content Types
* Basic Content Creation
* Basic Publishing Lifecycle
* Frontend Content Rendering

### Deliverable

Content publishing foundation with a local Content module, admin create/edit/list/archive workflows, simple content types, status lifecycle, slug-based frontend rendering at `/content/{slug}`, and textarea-based body editing.

---

## M1.6 Taxonomy Foundation

Status: Implemented in v0.6.0.

### Scope

* Taxonomy Module
* Taxonomy Types
* Taxonomy Terms
* Generic Assignments
* Content Integration

### Deliverable

Reusable classification foundation with seeded category/tag taxonomy types, admin term management, generic assignments, delete guards for assigned terms, and minimal Content integration when the Taxonomy module is enabled.

---

## M1.7 Settings

Status: Implemented in v0.7.0.

### Scope

* Namespaced Settings Persistence
* Code-defined Defaults
* Typed Setting Values
* General Settings Admin UI
* Basic Localization Settings Admin UI
* Basic Runtime Integration

### Deliverable

Core Settings Foundation with known global/site definitions, database overrides, typed retrieval, controlled validation, General and Localization admin sections, and basic runtime use of site name and localization values.

---

## M1.8 Installer

Status: Implemented in v0.8.0.

### Scope

* Fresh Web Installation Wizard
* Pre-bootstrap Installation Gate
* Requirements and Dedicated Empty Database Validation
* Atomic Environment Configuration
* Canonical Schema Installation
* First Administrator and Initial Settings
* Default Theme and Baseline Module Activation
* Final Installation Lock

### Deliverable

Fresh web installation foundation for new deployments, with controlled failure handling and installer denial after successful completion.

M1 Framework Foundation is complete in v0.8.0. M2 remains planning direction only; no M2 capability is implemented as part of M1.8.

---

# Future Milestones

The roadmap phases are organized as:

```text
M1 = Framework Foundation
M2 = Platform Capabilities
M3 = Core Modules
M4 = Business / Application Modules
M5 = Commerce
M6 = Ecosystem
```

M1 establishes the minimum framework foundation.

M2 adds reusable services, contracts, registries, adapters, processing, and extension foundations.

M3 builds reusable first-party management modules on top of M1 and M2.

M4 introduces domain-specific Business/Application Modules.

M5 adds commerce-specific transactional capabilities.

M6 supports distribution, tooling, integrations, and the broader extension ecosystem.

The Post-M1 Roadmap Review is complete.

M2.1 Admin UI Foundation is complete and released as v0.9.0.

M2.2 Extensibility Foundation is complete and released as v0.10.0.

M2.3 Minimal Site Capabilities is complete and released as v0.11.0. M2.4 Platform Hardening, Post-M2 Distribution & Release Preparation, and the package reproducibility correction are complete and released as v0.12.0. M3 Preparation and M3.1 Users & Access are complete; M3.1 merged to `main` through `5c4cf8c`. Post-M3.1 Roadmap Sync and all five M3.2 Settings Manager batches are complete; M3.2 merged to `main` through `afd82f0`. M3.3 Module Manager Batches 1–2 are complete on `feature/m3.3-module-manager` at `57f68be`; Batch 3 has not started and M3.3 is not merged to `main`.

The approved M2.1 architecture boundaries, completed batch plan, and acceptance criteria remain defined in `docs/10_admin_ui_foundation.md`.

---

## M2 Platform Capabilities

### Objective

Strengthen copot with reusable platform capabilities that can support production and commercial applications without embedding business-specific domains into the platform layer.

M2 is not a collection of user-facing manager modules or business modules.

A Platform Capability may provide:

* a shared service;
* a registry;
* an adapter interface;
* extension points;
* lifecycle hooks;
* resolution logic;
* storage abstraction;
* processing infrastructure.

### Lean M2 Plan

M2 is intentionally compressed to four minor milestones so Core Module work can begin sooner.

Capabilities deferred from the earlier expanded M2 plan are not discarded. They may be recalled into M2 when a concrete Core Module dependency proves they are required.

#### M2.1 Admin UI Foundation

Status: Complete.

Target release: v0.9.0.

##### Objective

Provide theme-independent, shared-hosting-safe Admin UI infrastructure for Core and module-owned administrative interfaces.

##### Delivered

* centralized admin path validation and URL generation;
* centralized Admin Shell and page rendering;
* static Admin UI assets and internal design tokens;
* reusable layout, alert, form, action, panel, table, and empty-state patterns;
* responsive and accessibility baseline;
* permission-aware admin navigation with active-state resolution;
* minimal permission-aware dashboard-widget registry;
* migrated Admin Login, Dashboard, Settings, Content, and Taxonomy presentation;
* unified regression gate and completed manual browser verification.

Detailed scope and completion criteria are defined in:

```text
docs/10_admin_ui_foundation.md
```

#### M2.2 Extensibility Foundation

Status: Complete.

##### Minimum scope

* synchronous event dispatch;
* listener registration;
* explicit Core and module extension points;
* deterministic listener ordering where required;
* predictable failure handling;
* lifecycle integration only where current module behavior proves the need.

##### Implemented checkpoint

* synchronous request-scoped Core dispatcher;
* stable lowercase dotted event names with object payloads;
* explicit registration-order listener execution without priority;
* fail-fast exception propagation;
* controlled listener contribution from installed and enabled modules;
* disabled-module non-contribution;
* controlled temporary fixture coverage proving end-to-end wiring;
* unified M2.2 regression gate passing with M2.1 regression coverage preserved.
* automated-assisted runtime and manual browser verification passing.

First Production Consumer Integration is deferred to the first milestone that has a real caller/listener pair. It is not a blocker for completing the M2.2 foundation. Temporary fixture events are test-only and do not establish production API.

Detailed scope, architecture, batch planning, and acceptance criteria are defined in:

```text
docs/12_extensibility_foundation.md
```

##### Deferred from M2.2

* asynchronous events;
* queue infrastructure;
* event persistence or replay;
* wildcard event buses;
* external APIs;
* webhooks;
* distributed messaging.

Production lifecycle events without a real consumer are also deferred. M2.2 must not add placeholder events merely to manufacture a caller/listener pair.

#### M2.3 Minimal Site Capabilities

Status: Complete and released as v0.11.0. Implementation, unified regression, and manual verification pass.

##### Minimum scope

* locale and timezone baseline;
* date, time, and number formatting boundary;
* Core site-branding contract for Site Name, optional Tagline, optional Logo, and optional Favicon;
* minimal local asset and upload foundation;
* safe upload validation, controlled storage paths, public URL retrieval, replace/remove behavior, and minimum metadata;
* initial logo and favicon use cases without a full Media Library.

M2.3 reuses the existing Settings and Theme boundaries and does not require a database schema change. Its detailed scope, architecture, batch plan, and acceptance criteria are defined in:

```text
docs/13_minimal_site_capabilities.md
```

The separate Core four-color palette and semantic-mapping proposal in `docs/11_branding_foundation.md` remains deferred and is not an M2.3 acceptance requirement.

##### Deferred from M2.3

* multilingual content and translation management;
* per-user or per-module locale and timezone;
* advanced branding UI;
* Core color palette and semantic mapping;
* theme-specific advanced color controls;
* Custom CSS;
* media library, picker, folders, search, and bulk actions;
* arbitrary file upload and generic file management;
* SVG upload without a separately approved security contract;
* image editor, crop/resize pipeline, optimization, CDN, or external storage.

##### Batch plan

1. Scope, audit, architecture, and contract lock — complete.
2. Localization and formatting foundation — complete.
3. Core Branding settings contract — complete.
4. Minimal local asset/upload foundation — complete.
5. Logo and Favicon integration — complete.
6. Regression, manual verification, and completion — complete.

#### M2.4 Platform Hardening

Status: Complete and released in v0.12.0. Batches 1–6 are complete; unified regression and applicable local/manual verification pass.

Release: v0.12.0.

##### Objective

Harden the existing M1 and lean-M2 platform through narrow failure containment, sanitized rendering, minimal private diagnostics, controlled storage/filesystem failures, production runtime checks, and one release regression gate without adding a new product capability.

##### Minimum scope

* consistent application error boundaries;
* sanitized public and Admin rendering without raw exception, warning, trace, path, SQL, credential, environment, request-body, token, cookie, or client-filename leakage;
* authenticated Admin errors rendered inside the existing shell only when application, authentication, user, and renderer state remain safely available;
* small local request-synchronous logging baseline with safe error references, allowlisted context, redaction, and non-recursive failure behavior;
* security, session-cookie, and escaping review;
* authentication, permission, CSRF, upload, and storage review;
* controlled missing, unreadable, unwritable, symlinked, partial-write, rename, read, and cleanup filesystem paths;
* regression gate across M1 and lean M2;
* shared-hosting runtime and deployment checklist;
* documentation and release-readiness review.

M2.4 is a release gate, not an invitation to build enterprise observability before the framework has managers to observe.

##### Error taxonomy

* Expected request and authorization outcomes retain controlled `403`, `404`, `419`, `422`, and related statuses and are not server-error logs by default.
* Controlled dependency or storage availability failures normally use sanitized `503` responses and an operational diagnostic when useful.
* Unexpected application failures use a sanitized `500`, one safe error reference, and one best-effort internal record.
* Failures before normal Application/Admin services are available use a minimal standalone response.

##### Non-goals

M2.4 does not add:

* a database/schema change or new dependency;
* an Admin redesign or new UI system;
* an enterprise logging framework, log viewer, metrics, tracing, observability platform, or external service;
* a queue, worker, scheduler, daemon, retry service, or global rate limiter;
* a generic storage abstraction, cloud adapter, Media Library, arbitrary uploads, or background cleanup;
* raw public diagnostics through `APP_DEBUG`;
* a broad Router, Module, Theme, Settings, Content, Taxonomy, or service-container rewrite.

##### Batch plan

1. Audit, architecture, documentation, and contract lock — complete; documentation only.
2. Minimal Diagnostics Baseline — complete.
3. Application Error Boundary and Rendering Safety — complete.
4. Admin In-Shell Errors — complete.
5. Runtime, Security, Storage, and Deployment Hardening — complete.
6. Unified Regression and Release Readiness — complete.

Batch 2 provides request-scoped synchronous local diagnostics, controlled JSON-line records, opaque references returned only after successful append, strict context filtering, no raw exception messages, and no-throw unavailable-sink behavior. It adds no global handler or response integration.

Batch 3 provides sanitized pre-autoload, post-autoload bootstrap, and Application dispatch failure boundaries; standalone server-error responses with references only after successful diagnostics; exact owned-buffer cleanup; and centralized unexpected public rendering failures. Unexpected failures default to `500`; `503` requires an explicit positively identified availability condition.

Batch 6 added the unified M2.4 regression gate, final scope/status consistency, runtime-artifact ignore coverage, and explicit separation between passed local verification and deployment-environment checks. M2.4 implementation is complete and was released as part of Copot v0.12.0.

##### Acceptance direction

M2.4 completion requires sanitized early and normal failure responses, no partial render leakage, safe Admin in-shell errors, redacted and failure-safe diagnostics, covered storage/filesystem failures, production/shared-hosting checks, focused security regression, and one M2.4 gate that includes the existing complete M2.3 regression chain.

Detailed scope, architecture, error taxonomy, sanitization/logging contract, storage boundary, runtime/deployment checklist, batches, acceptance criteria, and risks are defined in:

```text
docs/14_platform_hardening.md
```

### M2 Exclusions and Deferred Capabilities

Lean M2 does not include:

* Media Library management UI;
* Content Manager / Workspace;
* Theme Manager;
* Settings Manager;
* Module Manager;
* Navigation Manager;
* Business/Application Modules;
* Commerce;
* marketplace or package distribution;
* general API platform or webhooks;
* queue and scheduler infrastructure without a concrete workload;
* notifications;
* search indexing;
* workflow or automation infrastructure;
* multilingual content management;
* advanced image processing;
* full Branding Manager functionality;
* generic Asset Management Foundation.

A deferred capability may return to M2 only when a concrete M3 dependency requires it before Core Module implementation can proceed safely.

### Asset Terminology

The roadmap does not use generic “Asset Management Foundation” because the term is ambiguous.

Use:

```text
Media Foundation
```

for uploaded files, storage, metadata, references, variants, and delivery.

Future Digital Asset Management may be considered as a Core or Application Module if advanced collections, ownership, approval, lifecycle, and usage requirements emerge.

Physical or business asset management belongs to M4 as a domain-specific Business/Application Module.

---

## Post-M2 Distribution & Release Preparation

Status: Complete and released as v0.12.0.

Purpose:

Convert the completed lean M2 Webcore implementation into a deterministic, installable, clean-verified release artifact before M3 module development begins. This is a release-preparation phase, not a new capability milestone.

Completed work sequence:

1. Distribution Contract & Version Foundation — complete.
2. Repository Cleanup & Package Manifest — complete.
3. Deterministic Package Builder — complete.
4. Clean Install Verification — complete.
5. Release Candidate Audit — complete.
6. Reproducibility blocker correction, final merge, tag, GitHub Release, and package publication — complete.

Release evidence:

* `Copot\Core\Version::CURRENT` is the single release-version source for installer markers and package naming.
* The official package builder produces `dist/copot-v0.12.0.zip` from the explicit package manifest.
* Repository text materialization is locked to LF for deterministic package builds.
* Cross-checkout package reproducibility, external extraction compatibility, package-content guards, and clean installation from the extracted artifact pass.
* Clean-install verification uses an isolated target and a dedicated guarded test database.
* Deployment-environment checks for real HTTPS Secure cookies, production document-root isolation, and symlink-capable host filesystem behavior remain environment-specific responsibilities.

The released v0.12.0 Webcore is the stable baseline for M3.

## M3 Preparation

Status: Complete. Stages 1-3 are complete and M3 Prep is closed.

M3 Prep has three stages:

1. Governance + Architecture Lock — complete.
2. M3 Sequencing Lock — complete.
3. Final Review + Entry Audit — complete.

### Stage 1 — Governance + Architecture Lock

Stage 1 is complete.

It locked:

* post-v0.12.0 Webcore maintenance-only policy;
* Core-change escalation rules;
* module ownership boundaries;
* cross-module interaction rules;
* dependency direction;
* Theme/Module boundaries;
* Navigation ownership direction;
* Media Library ownership direction;
* official-module and external-module repository strategy;
* M3 entry criteria and explicit non-goals.

Detailed rules are defined in:

```text
docs/16_m3_core_freeze_and_module_contract.md
```

### Stage 2 — M3 Sequencing Lock

Stage 2 is complete. It was documentation and planning work only and did not implement M3 runtime behavior.

The approved implementation sequence is:

| Milestone | Capability | Planning Batch Envelope | Risk |
|---|---|---:|---|
| M3.1 | Users & Access | 5 | High |
| M3.2 | Settings Manager | 4 | Medium |
| M3.3 | Module Manager | 5 | High |
| M3.4 | Content Manager | 6 | High |
| M3.5 | Taxonomy Manager | 5 | Medium-High |
| M3.6 | Navigation Manager | 6 | High |
| M3.7 | Theme Manager | 6 | High |
| M3.8 | Media Library | 7 | Very High |
| M3.9 | Internal Dashboard | 4 | Medium |
| M3.10 | Redirect Manager | 4 | Medium |
| M3.11 | Form Manager | 7 | Very High |

Total planning envelope: 59 batches.

The batch envelope is a planning boundary, not an immutable implementation count. Before each milestone begins, a focused milestone preparation step must audit the current repository state, completed dependencies, newly proven consumers, and active risks, then lock the exact batch breakdown for that milestone.

The sequencing rationale is:

```text
Users & Access
->
Settings Manager
->
Module Manager
```

establishes the initial management foundation before broader module evolution.

```text
Content Manager
->
Taxonomy Manager
```

matures existing domain modules and provides real target domains before Navigation target-resolution integration is proven.

```text
Content + Taxonomy
->
Navigation Manager
->
Theme Manager
```

lets Navigation prove explicit target resolver contributions against real domain owners, then lets Theme Manager consume stable Navigation and module-provided render contracts.

```text
Content + Theme + other proven consumers
->
Media Library
```

keeps Media module-owned and delays generic media infrastructure until reusable consumer need is concrete.

```text
Internal Dashboard
->
Redirect Manager
->
Form Manager
```

places aggregation and operational capabilities after the major management, domain, presentation, and media surfaces are established. Form Manager remains last because its public input, validation, persistence, notification, upload, spam, privacy, and security surface creates the broadest late-M3 operational risk.

#### Sequence Change Rule

The approved sequence may change only when concrete evidence proves one or more of the following:

* a hidden hard dependency;
* a reusable consumer requirement;
* an architecture prerequisite;
* a security prerequisite;
* a migration constraint;
* a concrete integration dependency.

A sequence change must:

1. document the reason;
2. identify affected milestones;
3. review dependency and risk impact;
4. update the roadmap;
5. update the active target in `AGENTS.md`;
6. avoid silent reordering.

Milestones must not be reordered merely because another feature appears more attractive, easier, or convenient to implement.

#### Parallelization Rule

M3 milestones are sequential by default.

Parallel execution requires explicit approval and proof that there is:

* no unresolved dependency;
* no shared mutable contract;
* no overlapping schema ownership;
* no overlapping Core touchpoint;
* a regression strategy capable of validating parallel integration.

Early M3 remains serial through at least M3.1-M3.3. Parallelization may be reconsidered later using actual milestone evidence.

#### Just-in-Time Batch Lock Rule

Stage 2 locks milestone order and planning envelopes.

Exact batch detail is locked immediately before each milestone starts. This allows evidence from completed milestones to refine batch structure without silently widening scope.

### Stage 3 — Final Review + Entry Audit

Stage 3 is complete. It remained documentation, audit, and entry-contract work only.

Stage 3 audited and locked:

* documentation consistency and stale current-state wording cleanup;
* Stage 1 governance and architecture boundaries;
* the approved Stage 2 sequence and change-control rules;
* unresolved architecture blockers;
* M3.1 Users & Access scope and exact batch structure;
* allowed and forbidden Core touchpoints;
* schema and migration ownership boundaries;
* test strategy;
* branch strategy;
* M3.1 entry criteria;
* M3.1 acceptance criteria;
* repository and worktree readiness.

Stage 3 passed, M3 Prep closed through the user-owned Git workflow, and M3.1 began on `feature/m3.1-users-access` from the updated `main` baseline.

#### M3.1 Users & Access Entry Contract

M3.1 evolves the existing authentication and authorization foundation into administrator-facing Users & Access management without redesigning authentication or introducing a second role or permission system.

Minimum M3.1 scope:

* administrator-facing user listing and user detail/edit workflows;
* controlled user creation and account-status management;
* role listing and role management within the existing permission model;
* controlled user-role assignment and removal;
* controlled role-permission assignment and removal;
* password creation/change behavior only where explicitly required for administrator-managed users;
* protection against accidental administrator lockout and unsafe self-demotion;
* permission-aware Admin navigation and routes;
* compatibility with the existing login, active-account, session, Admin guard, Content, Taxonomy, and Settings permission behavior.

M3.1 does not include password reset delivery, email verification, OAuth, 2FA, organization/team hierarchy, multitenancy, approval workflow, audit-log platform, notification delivery, public identity API, or a new authentication/session architecture.

The exact five-batch structure is:

1. M3.1 contract lock, repository audit, data ownership review, and focused test baseline.
2. Users administration foundation: module structure, permissions, repositories/services, listing, create/edit, and account-status controls.
3. Roles and assignments: role management, user-role assignment, role-permission assignment, and lockout/self-protection rules.
4. Security and integration hardening: CSRF, permission guards, configured Admin path, inactive-user behavior, sanitization, Admin in-shell errors, and compatibility regression.
5. Unified M3.1 regression, manual Admin verification, documentation sync, and completion audit.

Allowed Core touchpoints by default are consumption of existing public authentication, user, permission, Admin URL, Admin Shell, CSRF, error-rendering, and application service contracts. Changes to `Auth`, `User`, `UserProvider`, `PermissionChecker`, Application service wiring, shared Admin guard semantics, or shared permission semantics require a concrete blocker, separate review, and the Stage 1 Core-change escalation process.

Forbidden default scope includes authentication redesign, session redesign, unrelated login-route redesign, service-container rewrite, Router rewrite, separate role/permission systems, hardcoded role hierarchy, speculative identity abstractions, speculative production events, and future capabilities not listed in the approved M3.1 scope.

M3.1 testing must include focused domain tests, security tests, compatibility/integration tests, the complete existing platform regression chain, and manual browser verification of approved Admin flows.

#### M3.1 Completion Record

M3.1 Users & Access completed all five approved batches and merged to `main` through `5c4cf8c`. Local XAMPP runtime-mirror workflow commit `35863e9` followed on `main`. M3.1 is not yet included in a new release.

The locked M3.1 permission slugs are `users.read`, `users.create`, `users.update`, `users.password.manage`, `users.status.manage`, `roles.read`, `roles.manage`, `users.roles.manage`, and `roles.permissions.manage`.

Module manifest permissions and `module_permissions` are metadata declarations and installed-module metadata. Runtime authorization remains the single existing `permissions` + `role_permissions` + `user_roles` model. M3.1 adds no Module Manager auto-sync and no second permission system.

Fresh installations receive the nine runtime permissions and their initial seeded `admin` role mappings through `database/schema.sql`. Existing installations use the explicit, controlled, idempotent, operator-run `database/upgrades/m3_1_users_access_permissions.sql`. The SQL artifact does not register or enable `users-access`; those lifecycle steps use the existing `ModuleManager` flow. Provisioning is never an implicit bootstrap, discovery, install, or enable side effect, and the Installer remains fresh-install only.

Administrator protection is based on an active user's resulting effective permission union, not role membership alone. The required recovery permissions, protected `admin` role behavior, multi-role contribution, self-protection, final-administrator invariant, atomicity requirement, and role lifecycle rules are authoritative in `docs/16_m3_core_freeze_and_module_contract.md`.

The completed implementation provides administrator-facing Users and Roles management, user creation and identity editing, administrator-managed password changes, active/inactive controls, user-role and role-permission assignment, multi-role effective permission unions, final-administrator and self-lockout protection, permission-aware routes/navigation, configured Admin path support, and CSRF, escaping, error, and compatibility hardening.

Batch 5 also closes the concrete access-denied recovery blocker: an authenticated user without `admin.access` remains on a standalone `403` but receives a CSRF-protected POST Sign out action using the configured Admin path. Guest standalone errors do not receive that authenticated recovery action. Batch 3 integration fixtures are isolated from administrator-capable users already present in the local database without weakening the runtime invariant.

Focused M3.1 Batches 1–4 pass 487 assertions. The recovery regression adds 17 assertions, producing 504 focused M3.1 plus recovery assertions. The complete M2.4 unified platform regression chain and manual Admin verification also pass.

Non-blocking Admin UX improvements remain deferred: normalize permission checkbox sizing/alignment, group permissions by domain/function, hide technical slugs from the default UI, add global floating notifications while retaining inline field errors, explain effective permissions for multi-role users, and provide reusable dashboard block spacing. Gather patterns from M3.2 and M3.3, then schedule Admin UX Refinement 1 after M3.3 and before M3.4.

Post-M3.1 Roadmap Sync is complete. It preserves the approved M3 sequence, locks the planned Database Upgrade / Migration System concept, and completed M3.2 preparation before Batch 1 began.

#### Database Upgrade / Migration System Checkpoint

Status: planned. Concept: locked. Implementation: not started and excluded from M3.2.

Fresh installs use the canonical `database/schema.sql`; existing installations currently use ordered SQL artifacts in `database/upgrades/`. The planned system will own ordered execution, migration history, idempotency, explicit failure behavior, supported transaction boundaries, and clear Core/module migration ownership. Implement it before a milestone introduces the second cross-module upgrade dependency or before a third independently ordered upgrade artifact is accepted, whichever comes first.

#### Admin UX Refinement 1

Status: planned; implementation not started; non-blocking for M3.3 and scheduled after M3.3 before M3.4.

M3.2 and M3.3 collect management-UI patterns. After M3.3 and before M3.4, Admin UX Refinement 1 will normalize permission checkbox sizing/alignment, group permissions by domain/function, hide technical permission slugs by default, add floating global notifications while preserving inline validation, clarify effective permissions for multi-role users, and establish reusable dashboard block spacing.

#### M3.2 Settings Manager

M3.2 Settings Manager is complete in the approved sequence and merged to `main` through `afd82f0`. Exact scope, permission reuse, approved Core touchpoints, five-batch plan, acceptance criteria, and completion evidence remain authoritative in `docs/17_m3_2_settings_manager_contract.md`. All five batches pass their gates. Final automated validation passes 366 focused M3.2 assertions, required M2.1 Admin UI, M2.3 Branding/Site Asset and unified, plus M3.1 permission/access regressions; PHP lint, repository, and boundary checks pass. The final manual matrix passes, with permission-denial and configured non-default Admin-path cases verified automated-assisted and the remaining Admin workflows verified manually. No unresolved Core, schema, runtime, or security blocker remains.

#### M3.3 Module Manager Entry Contract

Status: Batches 1–2 complete; Batch 3 has not started. M3.3 remains a high-Core-risk milestone with a five-batch planning envelope and the existing Just-in-Time Batch Lock governance. Current checkpoint: Post-Batch 2 / Pre-Batch 3 Activation. Current work: activation policy, package inclusion, authoritative-state sync, and Batch 3 entry preparation.

The Module Manager module owns Admin inventory, metadata and discovery-error presentation, navigation, configured-path routes, and controlled lifecycle workflows. It consumes the existing Core discovery, repository, lifecycle, Admin URL, Admin Shell, CSRF, navigation, and sanitized-error contracts. The activation gate approves only the narrowly scoped `InstallerFinalizer::BASELINE_MODULES` addition; no other confirmed Core blocker exists, and any further Core change requires separate approval and executable evidence.

Authorization is locked to both the configured base permission `admin.access` and the dedicated runtime permission `modules.manage` (`Manage modules`). One dedicated permission covers inventory plus install, enable, disable, and uninstall. Fresh installations will provision `modules.manage` and map it to the seeded `admin` role through `database/schema.sql`. Existing installations will use the controlled, idempotent, operator-run `database/upgrades/m3_3_module_manager_permission.sql`; it may add only the permission and missing seeded-admin mapping, must not run automatically or install/enable modules, and must not introduce permission synchronization. `module_permissions` remains metadata only and grants no runtime access.

The proposed M3.3 artifact is the second independent upgrade artifact. The Database Upgrade / Migration System trigger is not currently reached, and a generic migration runner remains out of scope.

The approved activation policy adds `module-manager` to `InstallerFinalizer::BASELINE_MODULES`, so fresh installations install and enable it through the existing generic ModuleManager lifecycle. This is the sole approved activation Core touchpoint; no new activation framework, bootstrap synchronization, or automatic module reconciliation is introduced. Existing installations apply `database/upgrades/m3_3_module_manager_permission.sql`, then explicitly install and enable `module-manager` through ModuleManager before its routes are available on the next request through the enabled-module loader.

`modules/module-manager` must be included in `build/package_manifest.php` in the same activation gate as fresh-install baseline activation. Clean-install and package-smoke evidence are required before Batch 3 Admin integration. The Batch 3 Admin workflow must deny disabling or uninstalling `module-manager` itself while keeping those actions visibly disabled with stable denial reasons. No additional schema change, upgrade SQL artifact, migration runner, or automatic permission synchronization is approved.

Lifecycle rules are:

1. Install accepts only a valid discovered and uninstalled module and produces a disabled installation.
2. Module-row insertion and `module_permissions` replacement are atomic through existing transaction or caller-safe savepoint capability.
3. Failure restores prior persistent state, leaves no open transaction, and returns a sanitized controlled error.
4. Enable accepts only an installed disabled module with valid discoverable files, valid declared route/listener files, and satisfied dependencies.
5. Disable accepts only an installed enabled module with no enabled dependent.
6. Uninstall accepts only an installed disabled module with no enabled dependent.
7. An enabled module must be disabled successfully before uninstall.
8. Repeated or unsupported transitions fail without mutation.
9. Missing target files block enablement but do not block recovery disablement or later uninstall after normal checks.
10. If an enabled potential dependent is missing or invalid on disk, target disable/uninstall fails closed.
11. Self-dependencies and duplicate dependency declarations are invalid.
12. Name-graph dependency cycles are detected and block enablement.
13. M3.3 supports name-only dependencies; version-constraint resolution is out of scope.
14. Stored title, version, and path drift is detected and displayed, but not automatically synchronized.
15. Declared route and listener files pass preflight before enablement.
16. Existing runtime loader compatibility may remain: missing routes may still be skipped and missing listeners may remain fail-closed.
17. Module files are never deleted.
18. The contract locks required behavior and transaction outcomes, not a specific orchestration layer.

The exact five-batch plan is:

1. Contract lock, ownership, permission/migration decision, and focused baseline.
2. Manager domain and lifecycle presentation contract.
3. Admin routes, views, navigation, and controlled mutations.
4. Security, lifecycle failure, dependency, and compatibility hardening.
5. Unified regression, manual verification, documentation closure, and completion audit.

Explicit non-goals include marketplace or remote installation, package download or ZIP upload, signing, automatic updates, rollback, generic version solving, Composer-style dependency resolution, automatic permission or role synchronization, a second authorization system, a generic migration framework, Theme Manager, Media Library, module settings UI, module code editor, Admin UX Refinement 1, M3.4, release, tagging, and package publication.

The M3.3 preparation No-Return Point was reached when the approved contract documentation was merged to synchronized `main`. The next activation No-Return Point requires the approved baseline/package changes, clean-install and package-smoke evidence, documentation sync, and synchronized clean `main`; Batch 3 Admin implementation must not begin before that gate.

Branch strategy:

```text
feature/m3-prep
-> Stage 3 remediation and verification
-> user-owned commit/push/merge to main
-> feature/m3.1-users-access from updated main
```

---

## M3 Core Modules

### Objective

Build reusable first-party management modules on top of M1 Framework Foundation and M2 Platform Capabilities.

Core Modules:

* provide user-facing or administrative management functionality;
* are not tied to a specific business domain;
* follow the Module Manager lifecycle;
* may become dependencies of other modules;
* remain modular even when distributed with copot.

### Approved M3 Sequence

1. M3.1 Users & Access
2. M3.2 Settings Manager
3. M3.3 Module Manager
   * Admin UX Refinement 1 checkpoint follows M3.3 and precedes M3.4.
4. M3.4 Content Manager
5. M3.5 Taxonomy Manager
6. M3.6 Navigation Manager
7. M3.7 Theme Manager
8. M3.8 Media Library
9. M3.9 Internal Dashboard
10. M3.10 Redirect Manager
11. M3.11 Form Manager

This sequence is approved by M3 Prep Stage 2 and remains subject to the documented evidence-based Sequence Change Rule. It is not silently reordered.

Navigation data remains module-owned by the future Navigation boundary. Themes declare locations and control rendering through a documented consumption contract. Domain-owned target resolution is contributed through explicit contracts, registries, or resolvers.

Content and Taxonomy are evolved before Navigation so resolver integration can be proven against real domain owners. Theme Manager follows Navigation so presentation management can consume a stable navigation contract. Media Library follows those consumers so general media behavior is driven by proven need instead of hypothetical platform expansion.

### Existing Module Evolution

The existing Content and Taxonomy modules remain the same modules.

```text
Content Module
->
Content Manager
```

describes future evolution of its administrative and editorial experience.

```text
Taxonomy Module
->
Taxonomy Manager
```

describes future evolution of its management UI and capabilities.

These names do not create duplicate replacement modules.

### Manager and Service Boundaries

```text
Theme System
!=
Theme Manager
```

The Theme System provides lifecycle infrastructure.

Theme Manager provides administrative theme management and theme-settings UI.

```text
SettingsService
!=
Settings Manager
```

SettingsService provides definitions, persistence, retrieval, validation, and typed values.

Settings Manager provides administrator-facing settings management.

For Branding Foundation, Settings Manager edits only the four Core palette values. Theme Manager reads theme capabilities and manages active-theme-scoped palette or semantic-mapping overrides plus advanced theme color settings. Custom CSS is deferred to a later Theme Manager enhancement. Neither manager changes the locked Core semantic mapping.

```text
Branding-specific Site Asset capability
!=
Media Library
```

The existing Site Asset boundary owns only fixed Logo/Favicon lifecycle behavior.

Media Library is module-owned and provides general media management and selection behavior. Any future generic media or image-processing infrastructure must be justified by concrete reusable consumers before entering Webcore.

---

## M4 Business / Application Modules

### Objective

Use Platform Capabilities and Core Modules to build domain-specific applications and business functionality.

Candidate modules:

* Catalog
* Property
* Booking
* CRM
* Inventory
* POS
* HR
* Finance
* Project / Task Management
* Physical or Business Asset Management

Business/Application Modules are not universal copot requirements.

They may be installed only when their domain is needed.

---

## M5 Commerce

### Objective

Build commerce functionality on top of the framework, platform capabilities, and relevant core or business modules.

Candidate directions:

* Product Catalog
* Orders
* Cart
* Checkout
* Payment integration
* Customer accounts
* Transactional status flows
* Inventory integration
* Tax and pricing integration

Commerce remains a separate phase because transactional correctness, payments, order state, and external integrations require dedicated architecture and testing.

---

## M6 Ecosystem

### Objective

Support distribution, extension, integration, and long-term platform maintenance.

Candidate directions:

* module and package distribution;
* update discovery and lifecycle;
* extension ecosystem;
* developer tooling;
* integration and API ecosystem;
* package signing and verification;
* compatibility metadata;
* remote repository or marketplace concepts.

M6 depends on stable contracts established by the earlier phases.

---

## Dependency Direction

The high-level dependency direction is:

```text
M1 Framework Foundation
->
M2 Platform Capabilities
->
M3 Core Modules
->
M4 Business / Application Modules
->
M5 Commerce
->
M6 Ecosystem
```

Important dependency chains include:

```text
Admin UI Foundation
->
Core Module management interfaces
```

```text
Extensibility Foundation
->
Notifications
->
Workflow / Automation
->
Module integration
```

```text
Media Library
->
post-M3.8 Theme Manager and Content Manager media-field integration through explicit contracts
```

```text
Editor capability, only if proven necessary
->
Content Manager editor integration
```

```text
Navigation Manager
->
Theme location consumption through an explicit contract
```

```text
Content + Taxonomy + Theme menu locations
->
Navigation target integration
```

Dependencies should remain directional.

Later modules may depend on earlier capabilities, but shared platform services must not depend on user-facing manager modules or business domains.
