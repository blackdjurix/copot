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

The older milestone-sequencing narrative below is retained as historical
context. The current post-M3 planning state is recorded in the authoritative
block in the M3.11 section.

M2.1 Admin UI Foundation is complete and released as v0.9.0.

M2.2 Extensibility Foundation is complete and released as v0.10.0.

M2.3 Minimal Site Capabilities is complete and released as v0.11.0. M2.4 Platform Hardening, Post-M2 Distribution & Release Preparation, and the package reproducibility correction are complete and released as v0.12.0. M3 Preparation and M3.1 Users & Access are complete; M3.1 merged to `main` through `5c4cf8c`. Post-M3.1 Roadmap Sync and all five M3.2 Settings Manager batches are complete; M3.2 merged to `main` through `afd82f0`. M3.3 Module Manager Batches 1–5 are complete and were fast-forward merged into `main` at `020f2b2`; local and remote `feature/m3.3-module-manager` branches are deleted. M3.3 remains unreleased, untagged, and unpublished. M3.4 Batch 1 and Batch 2 Foundation are complete and branch-closed; Batch 3 Admin Content Workspace implementation and validation are complete and were fast-forward merged into `main` at `b175098f1afcfa02594706e5bf98886b7887e1b2`. Its local and remote feature branches were deleted after verified containment. Batch 3 is complete. Batch 4 implementation and validation are complete and fast-forward merged into `main` at `48c1ca12ada0fe813b8efc1f4e8e0b9d52c03ccc` (`feat(m3.4): harden content manager batch 4`). The main push was previously completed and freshly re-verified; the local and remote feature branches were safely deleted after verified containment, and the branch lifecycle is closed. Batch 4 is complete after this final documentation commit and verification. Batch 5 implementation, validation, documentation, commit, feature push, fast-forward merge, and main synchronization are complete at `912bec86f2fdc799ab6c554da5341979f6be7cb5`; its local and remote feature branches were deleted after verified containment, and its branch lifecycle is closed. Batch 5 is complete. Batch 6 implementation, focused source review, automated validation, targeted runtime synchronization, authenticated browser validation, fast-forward integration, `main` push, feature-branch cleanup, and final verification are complete at final documentation commit `ae2c404be195dbf4395986ad21f7cb0be7da83a8`; no merge commit was created. Batch 6 and full M3.4 are complete; M3.R1 is next and must complete before M3.5.

The preceding milestone summary records the pre-classification sequence state. The current M3.R1 status is classification and lifecycle closure complete with `NO MATERIAL RETOUCH REQUIRED`; final documentation commit `c5d27adeba6c1f440f6b9c62309a447f82e43a08` is pushed, `main` is clean and synchronized at `0/0`, and full M3.R1 is complete. M3.5 was fast-forward integrated into `main` at `b09a01ca0a93cfa2eb9eccafd648f7a708df1576` with no merge commit; Work Units 1–5 and full M3.5 are complete, feature containment passed, and local/remote feature branches are deleted. M3.6 Navigation Manager is next and not started. Release, tag, and publication remain not started and separately authorized.

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

Deferred Item: `DI-M2.2-01` — First production consumer integration. Status:
Deferred. It remains unscheduled until a concrete caller/listener pair with a
safe transaction boundary is explicitly approved.

Detailed scope, architecture, batch planning, and acceptance criteria are defined in:

```text
docs/12_extensibility_foundation.md
```

##### Deferred from M2.2

Deferred Item: `DI-M2.2-02` — Expanded event delivery and integration
capabilities. Status: Deferred. The following capability set was considered
for M2.2 and intentionally postponed; it remains unscheduled and does not
authorize placeholder infrastructure.

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

Deferred Item: `DI-M2.3-01` — Core palette and semantic mapping. Status:
Deferred. It remains unscheduled pending an explicitly approved Branding
Foundation scope; it is not implied by the M2.3 identity contract.

##### Deferred from M2.3

Deferred Item: `DI-M2.3-02` — Localization expansion. Status: Deferred and
Unscheduled; it covers multilingual content and per-user or per-module locale
and timezone support.

Deferred Item: `DI-M2.3-03` — Advanced branding and theme presentation.
Status: Deferred and Unscheduled; it covers advanced branding UI,
theme-specific advanced colors, and Custom CSS.

Deferred Item: `DI-M2.3-04` — Generic file-management, SVG security, and
external storage. Status: Deferred and Unscheduled; it covers arbitrary
generic file management, SVG without a security contract, and CDN/external
storage expansion.

Deferred Item: `DI-M2.3-05` — Media organization and bulk workspace actions.
Status: Deferred and Unscheduled; it covers Media folders or organizational
grouping and bulk Media workspace actions. The historical Media library,
picker, and search portions were subsequently adopted by M3.8 and are not
part of this surviving Deferred Item.

Deferred Item: `DI-M2.3-06` — Advanced image editing and optimization.
Status: Deferred and Unscheduled; it covers broader image-editor capability
and optimization beyond the accepted Media processing baseline. Bounded
crop/resize processing was subsequently adopted by M3.8, while CDN/external
storage remains represented by `DI-M2.3-04`. This item authorizes no generic
image editor, optimization platform, or M3.8 scope expansion.

These records do not authorize adoption. The following historical list is
preserved as written; its partially adopted portions are not active Deferred
scope unless explicitly identified above.

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

## Deferred Items

This registry indexes intentional postponements only. A Deferred Item is not
authorization, and an `Unscheduled` target does not create future scope.

| ID and title | Source | Class | Status | Target |
| --- | --- | --- | --- | --- |
| `DI-M2.2-01` — First production consumer integration | M2.2 Extensibility Foundation | Integration | Deferred | Unscheduled |
| `DI-M2.2-02` — Expanded event delivery and integration capabilities | M2.2 Deferred from | Platform capability set | Deferred | Unscheduled |
| `DI-M2.3-01` — Core palette and semantic mapping | M2.3 Minimal Site Capabilities | Branding capability | Deferred | Unscheduled |
| `DI-M2.3-02` — Localization expansion | M2.3 Deferred from | Localization capability set | Deferred | Unscheduled |
| `DI-M2.3-03` — Advanced branding and theme presentation | M2.3 Deferred from | Branding/presentation capability set | Deferred | Unscheduled |
| `DI-M2.3-04` — Generic file-management, SVG security, and external storage | M2.3 Deferred from | Media/storage capability set | Deferred | Unscheduled |
| `DI-M2.3-05` — Media organization and bulk workspace actions | M2.3 Deferred from | Media workspace capability set | Deferred | Unscheduled |
| `DI-M2.3-06` — Advanced image editing and optimization | M2.3 Deferred from | Image-processing capability set | Deferred | Unscheduled |
| `DI-M3-ADMIN-UX-01` — Admin UX follow-up refinements | M3 Admin UX Refinement 1 / Shell Foundation | Admin presentation capability set | Deferred | Unscheduled |
| `DI-M3.4-01` — Advanced Content workflow and ownership features | M3.4 Content Manager | Content workflow capability set | Deferred | Unscheduled |
| `DI-M3.4-02` — Rich authoring and Content extensibility | M3.4 Content Manager | Content authoring capability set | Deferred | Unscheduled |
| `DI-M3.4-03` — Content bulk, interchange, and service capabilities | M3.4 Content Manager | Content management capability set | Deferred | Unscheduled |
| `DI-M3.4-04` — Public Content taxonomy archive integration | M3.4 Content Manager | Public Content integration | Deferred | Unscheduled |
| `DI-M3.5-01` — Taxonomy term search and filtering | M3.5 Taxonomy Manager | Taxonomy workspace capability | Deferred | Unscheduled |
| `DI-M3.6-01` — Production Taxonomy Navigation targets | M3.6 Navigation Manager | Navigation provider integration | Deferred | Unscheduled |
| `DI-M3.7-WU5-01` — Real-settings Theme Settings and color-control spot-check | M3.7 WU5 | Theme validation review | Deferred | Unscheduled |
| `DI-M3.8-WU6-01` — Further Media Manager visual/presentation refinement | M3.8 WU6 | Presentation refinement | Deferred | Unscheduled |
| `DI-M3.9-01` — User-customizable Dashboard layout | M3.9 Internal Dashboard preparation | Dashboard capability set | Deferred | Unscheduled |
| `DI-M3.9-02` — Admin Media CSS token normalization | M3.9 WU4 affected Admin regression audit | Design-system technical debt | Deferred | Unscheduled |
| `DI-M3.9-03` — Admin UI Batch 4 regression-contract modernization | M3.9 WU4 affected Admin regression audit | Regression-test contract debt | Deferred | Unscheduled |
| `DI-PACKAGE-LIFECYCLE-WU7-01` — Server-Empty Bootstrap & Package Clean Install | Package Lifecycle / WU7 contract | Installation/distribution capability | Deferred | Unscheduled |

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

M3.11 Form Manager preparation is COMPLETE. WU1–WU4 and full M3.11 are
COMPLETE and integrated into `main` at
`0b86eae4c9bb50d7ed8928f4f4b63f8be572a4f9` by fast-forward only, with no merge
commit. Feature containment is confirmed. v0.13.0 is released, tagged, and
published. Deferred integrations remain excluded.

### Current authoritative post-M3 state

M3 Core Modules: COMPLETE AND CLOSED
Latest completed milestone: M3.11 Form Manager
M3.11: COMPLETE AND CLOSED
Latest completed Post-M3 workstream: Webcore Product Completeness & Stabilization — WU3 COMPLETE AND CLOSED
Portability preparation: COMPLETE / CONTRACT LOCKED
Portability WU1–WU6: COMPLETE AND CLOSED
Portability acceptance: generic Apache/XAMPP shared-host-like matrix PASSED
Webcore Deployment & Portability Foundation: COMPLETE AND CLOSED
Module Package Lifecycle WU1–WU7: COMPLETE AND CLOSED; final human/E2E acceptance: PASS
v0.13.0 Release Readiness: COMPLETE AND CLOSED / RELEASED AND PUBLICLY VERIFIED
Frozen release documentation baseline: current release-readiness contract with Gate 9 verification recorded
Version & Release Reconciliation: COMPLETE
Webcore reconciled source version: 0.13.0 (released and published; production reconciliation remains separate)
Current Core Module versions: 0.1.0 independently owned; modules/example excluded
Current release metadata: Webcore release.json and per-module release.json files
Release advancement policy: release-based, not feature-based; REPAIR is not a release event
Current boundary: production Webcore reconciliation remains NOT STARTED and separately authorized; Server-empty Bootstrap remains DEFERRED / UNSCHEDULED; v0.13.0 tag and publication are complete, while future release actions remain separately controlled.
System Health & Status: PROMOTED / COMPLETE / CLOSED / CONTRACT LOCKED at `docs/35_system_health_status_contract.md`; WU1–WU6 implementation and validation are COMPLETE for the accepted contract scope. The current static Framework Status remains presentation content; the System Health widget is the authorized health consumer. MR.x refinement remains separate, and no follow-on work is automatically authorized.
MR.2 WU3 — System Manager Lifecycle & Modules UX Refinement: COMPLETE AND CLOSED for the accepted bounded scope. System Manager is the canonical product-facing Module lifecycle surface, with richer inventory/lifecycle evidence, diagnostics, package/result guidance, scalable search/filtering, and Module Detail presentation. This does not claim that all System Manager tabs or later MR.2 refinement work is complete; the authoritative contract is `docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`.
Module Permission Dependency / Base Access: separate planning concern; not a System Health dependency.
Existing-Runtime Webcore Lifecycle Adoption: technical work is closed; production reconciliation remains NOT STARTED and separately authorized.
Module Package Lifecycle WU1–WU7 implementation and final human/E2E acceptance: COMPLETE AND CLOSED.
Current active implementation branch: `feature/wu4-batch1-site-settings`
Database Ownership & Lifecycle Management Foundation: WU1–WU6 COMPLETE AND CLOSED for the accepted scope
WU4 Database Lifecycle Classification: COMPLETE AND CLOSED; accepted implementation and focused validation are integrated into `main` at `70782fdc9dddeb353cf27cf78f6f20e294e6fa30`
WU5 Installer Intent Reconciliation: COMPLETE AND CLOSED; Installer intents are Fresh / Coexist / Adopt, with normal existing-install Update / Upgrade / Repair outside Installer under System Manager / Webcore Lifecycle
WU6 Cross-Lifecycle Acceptance: COMPLETE AND CLOSED; mandatory persisted production-composed Retry success-path acceptance passed and the accepted implementation is integrated into `main` at `91de0fd829e52d185c4823fe27d7e849bf147622`
Known repository risk: `tests/multi_installation_wu3_module_namespace.php` was reproduced at the accepted WU4 checkpoint under the same PHP/MySQL environment; it remains a pre-existing/environmental WU4-era predecessor fixture risk, is not evidence of a current WU6 regression, and is separate from the accepted WU6 authorization-aware Module namespace evidence.
Package Lifecycle & Migration Foundation: COMPLETE AND CLOSED — Webcore first delivery slice
Foundation scope: Webcore + independently distributed Module packages
Completed delivery slice: Webcore first
WU1 — Webcore Package Contract & Release Identity: COMPLETE AND CLOSED
WU2 — ZIP Intake, Validation & Isolated Staging: COMPLETE AND CLOSED
WU2 delivered safe local ZIP intake, bounded archive validation, private isolated
staging, streamed extraction, package-ownership enforcement, staged
payload/inventory verification, and cleanup. Focused WU2 validation passed;
ext-zip-disabled execution requires a separate executor, and the filesystem
symlink cleanup fixture was unavailable on Windows. Both limitations are
non-blocking for the accepted WU2 boundary.
WU3 — Installed-State Registry & Transition Planner: COMPLETE AND CLOSED
WU3 delivered a serialization-neutral installed-state status/snapshot model,
explicit existing-installation evidence inspection, FRESH/LEGACY/COMMITTED/
INCONSISTENT/INVALID state handling, and one shared INSTALL/PATCH/UPDATE/
UPGRADE/REPAIR transition planner. It enforces the canonical-current
fresh-install policy, evaluates source/runtime compatibility, rejects
downgrades, requires explicit bootstrap for legacy state, and hands opaque
schema/migration identity information toward WU4. WU3 preserves the exact
two-field `storage/installed.lock` format and selects no new installed-state
persistence format. Durable interrupted-operation persistence remains outside
WU3 and is a later WU5 concern.
WU4 — Core Migration Registry & Runner: COMPLETE AND CLOSED
WU4 delivered explicit immutable Core migration IDs with monotonic sequence
ordering, an explicit code-defined registry, checksum-protected descriptor and
executable identity, a database-backed durable applied-migration ledger,
deterministic migration-state identity binding, exact registry-prefix
validation, and evolving virtual planning for unapplied forward migrations.
It also delivered TRANSACTIONAL and NON_TRANSACTIONAL execution modes,
deterministic preconditions/postconditions, bounded retry semantics, canonical
fresh-install ledger/schema baseline behavior without historical replay, and
strict downgrade/reverse rejection. Legacy installations without an approved
migration baseline remain blocked. Ambiguous non-transactional outcomes remain
for later WU5 handling; WU4 adds no durable interrupted-operation/job
persistence, package-file application, or maintenance behavior.
WU5 — Webcore Apply, Maintenance & Interrupted-Operation Boundary: COMPLETE AND CLOSED
WU5 delivered bounded in-place package-owned live-tree application with guarded
normalized containment and ancestor validation, an explicit platform activation
capability with fail-closed replacement behavior, complete staged-payload
preflight before the first live mutation, and streamed size/hash revalidation
during apply. WU5 performs no stale package-owned deletion. It reuses
`InstallationMutex` for lifecycle exclusion and stores one private file-backed
durable lifecycle-operation record; maintenance is derived from its non-terminal
state, and interruption is classified from that state plus mutex ownership.
The record binds deterministic progress, apply-plan, package, staging, and
migration identity. WU5 coordinates WU4 migrations, blocks on
`INDETERMINATE` outcomes, and hands off to WU6 without advancing committed
installed state. Package Lifecycle does not own destructive rollback or
restore; those operations belong to the closed Backup & Recovery Foundation.
WU2 retains staging ownership; WU3 retains transition and
installed-state boundaries; WU4 retains migration identity and ledger
semantics; no new installed-state persistence, stale-file deletion, generic job
infrastructure, or Module package application was introduced. Focused WU5
validation passed. The WU2 regression could not execute because the available
XAMPP ZipArchive API was insufficient, and the WU4 regression could not execute
because PDO SQLite was unavailable; both are execution-environment limitations,
not observed regressions.
WU6 — Health, Integrity & Commit-State Closure: COMPLETE AND CLOSED
WU6 delivered bounded rich committed lifecycle state under the private lifecycle
storage namespace while preserving the exact two-field `storage/installed.lock`.
WU3 inspection now produces COMMITTED only when the rich state and legacy marker
are consistent. WU6 verifies target package-owned live-tree files without
inferring stale ownership from arbitrary extra paths, verifies Core database and
schema health, validates the WU4 migration-ledger prefix and integrity, and
derives the final migration-state identity from the verified ledger. It provides
deterministic runtime/bootstrap/module/theme/public/Admin health gates, finalizes
under `InstallationMutex`, and persists committed state before operation
completion or maintenance clearing. If committed persistence succeeds but
operation cleanup fails, cleanup-pending state is retained. Cleanup retry
requires exact committed/package/operation/apply-plan/migration-plan/ledger
reconciliation and does not rerun file application, migrations, or full health
gates. Failed pre-commit gates preserve the prior committed state. WU6 performs
no package application or migration execution; stale-file deletion remains
unavailable. The subsequent WU7 Module Package Lifecycle work is complete and
closed, and Package Lifecycle does not own destructive rollback or restore. The
WU4 PDO SQLite
regression could not execute in the available executor because the driver was
unavailable; this is an environment evidence limitation, not an observed
regression.
WU7 — CLI Operator Surface & End-to-End Non-Recovery Acceptance: COMPLETE AND CLOSED
WU7 delivered the `bin/copot` local-package operator surface with
`package:plan`, `package:apply`, `package:repair`, and `package:status`, human
and JSON output, deterministic exit-code mapping, strict `.copot/package.json`
metadata, official package-builder lifecycle manifest/inventory emission, and
staged metadata exclusion from the live payload. `PackageLifecycleService`
orchestrates the shared WU1–WU6 lifecycle; PATCH, UPDATE, and UPGRADE remain
planner-derived classifications. Status reporting is bounded to installed
state, operation state, and maintenance state. WU7 binds materially real
runtime/bootstrap/module/theme/public/Admin health probes and an external
project-isolated apply-temporary namespace. The accepted local-package-only
delivery slice covers operation over an existing Copot runtime; server-empty
package bootstrap remains deferred.
Linux Cloud runtime acceptance consumed the official builder artifact through
the actual CLI/shared lifecycle path, performed real package-owned live-file
replacement and database-backed lifecycle behavior, reached WU6 committed
target state, cleaned the lifecycle operation, made maintenance inactive, and
reported the committed result through `package:status`. A tampered
package/inventory was rejected safely through the same CLI surface. Repository
source remained unchanged during final E2E acceptance. Remote discovery or
download, signing/trust, channels, automatic updates, Admin upload, and
differential packages remain excluded; downgrade/reverse migration remains
unsupported.
Server-empty Bootstrap & Package Clean Install: DEFERRED / UNSCHEDULED under
`DI-PACKAGE-LIFECYCLE-WU7-01`; Module Package Lifecycle preparation is complete
and contract-locked, WU1–WU7 implementation is complete for its accepted scope,
and Existing-Runtime Webcore Lifecycle Adoption technical work is closed;
final WU7 acceptance is complete for the accepted scope; the completed
Portability foundation and committed Webcore runtime evidence supported it.
Backup &
Recovery preparation is contract-locked in
`docs/31_backup_recovery_foundation_contract.md`; WU1–WU7 are complete, and IU2
WU1–WU6 are complete for the technical adoption slice.
Existing-Runtime Webcore Lifecycle Adoption IU2 WU1–WU6: COMPLETE;
classification/planning, recovery/confirmation/quiescence eligibility, guarded
package-owned filesystem convergence, schema/migration reconciliation, and
installed-state/lifecycle finalization and interruption/restore/retry
acceptance are implemented; production legacy reconciliation remains NOT
STARTED.
Backup & Recovery Foundation: COMPLETE AND CLOSED — WU1–WU7 COMPLETE.
Version & Release Reconciliation closure gate: COMPLETE.
Module Package Lifecycle preparation: COMPLETE / CONTRACT LOCKED
Full Module Package Lifecycle: COMPLETE AND CLOSED for the accepted WU1–WU7 scope; production reconciliation remains NOT STARTED; v0.13.0 release/tag/publication are complete; future release actions remain separate gates
Module Package Lifecycle contract: `docs/29_module_package_lifecycle_contract.md`
Module Package Lifecycle implementation and acceptance: COMPLETE AND CLOSED — WU1–WU7 automated/runtime validation and final human/E2E acceptance PASS
Module Package Lifecycle WU1: COMPLETE
Module Package Lifecycle WU2: COMPLETE
Module Package Lifecycle WU3: COMPLETE
Module Package Lifecycle WU4: COMPLETE
Module Package Lifecycle WU5: COMPLETE
Module Package Lifecycle WU6: COMPLETE
Module Package Lifecycle WU7: COMPLETE AND CLOSED — implementation, automated validation, and final human/E2E acceptance PASS
Post-M3 development model: DEPENDENCY-DRIVEN / NON-LINEAR
Release: v0.13.0 PUBLISHED
Tag: v0.13.0 CREATED AND VERIFIED
Publication: v0.13.0 PUBLIC / VERIFIED

M4, M5, M6, MR.x, and post-M3 platform foundations are planning domains, not
an automatically sequential execution order. Future work is selected by actual
product need, dependency, readiness, risk, and architecture boundary.

The Webcore Deployment & Portability Foundation remains complete and closed. Its
contract is
`docs/32_webcore_deployment_portability_foundation_contract.md`. Module Package
Lifecycle preparation is complete and contract-locked in
`docs/29_module_package_lifecycle_contract.md`; WU1–WU7 implementation is
complete and closed for its accepted scope, including final WU7 human/E2E
acceptance. Existing-Runtime Webcore Lifecycle
Adoption IU1 and IU2 WU1–WU6
are complete and technically closed; production reconciliation remains NOT
STARTED and requires separate explicit authorization and eligible
runtime/quiescence evidence. No
milestone number is assigned. The Webcore package lifecycle
first delivery slice is complete and closed for local/operator-provided
package operation over an existing Copot runtime; it does not claim all
possible Webcore installation/distribution capability. The current active
Post-M3 workstream is Webcore Product Completeness & Stabilization: WU1 and WU2
are complete, and WU3 implementation, technical validation, AI acceptance,
human/product acceptance, and closure-documentation reconciliation are complete.
WU3 was fast-forward integrated into `main` at
`2ade6d22c8ef0c78e0371960617c70bf865854b0`; feature containment was verified,
the local and remote feature branches were deleted, and the branch lifecycle is
closed. WU4 Batch 1 — Site Settings, Site Identity, Homepage assignment, and
directly affected Built-in Public View behavior — is implemented and accepted
on `feature/wu4-batch1-site-settings` under
`docs/54_webcore_site_settings_appearance_consolidation_contract.md`. WU4
Batch 2 is the next implementation target and is not started; WU4 Batches 3–4
remain not started. The feature branch remains intentionally unmerged pending
separately controlled integration.
v0.13.0 Release Readiness,
tag/publication, and Gate 9 verification are complete. Post-release
documentation reconciliation is complete; Multi-Installation Isolation
Foundation is COMPLETE AND CLOSED for the accepted WU1–WU6 scope, in
`docs/34_multi_installation_isolation_foundation_contract.md`, with
WU1 contract/evidence closure COMPLETE AND CLOSED, WU2 Core
implementation/evidence COMPLETE AND CLOSED, WU3 Module
implementation/evidence COMPLETE AND CLOSED, and WU4 Runtime
implementation/evidence COMPLETE AND CLOSED. WU5 — Installer Database
Occupancy Classification, Namespace Selection & Existing-Installation Routing
— implementation/evidence is COMPLETE AND ACCEPTED and integrated into
authoritative `main` at `a83c780cf7c5e1ca614ab4dc51a30b44c3aff53c`, including
DB-backed proof assembly and adoption/migration routing.
WU6 — Cross-Subsystem Integration & Multi-Installation / Multi-Runtime
Acceptance — is COMPLETE AND ACCEPTED and fast-forward integrated into
authoritative `main` at `b095d26285f80d5b4caa8d1ac686acc02e17913a`.
The authoritative remote branch lifecycle is closed on `main`, with no active
Multi-Installation feature branch.
No additional Multi-Installation WU is currently defined. Any other candidate
work requires separate planning and contract authorization. Server-Empty Bootstrap remains DEFERRED /
UNSCHEDULED, and production Webcore reconciliation remains NOT STARTED and
separately authorized.
MR.1 Installation Refinement has WU1–WU5 and full MR.1 **COMPLETE AND CLOSED**
in `docs/36_mr_1_installation_refinement_contract.md`. Its five-WU installer
topology is authoritative there: WU1 Installer Shell, Requirements & Navigation
Framework; WU2 Staged Installation Plan & Database Decision; WU3 Administrator
& Site Staging; and WU4 Review, Installation Commit & Result are complete and
closed with accepted validation; WU5 Cross-Step Responsive, Accessibility &
Human Acceptance is also complete and closed. WU4 implementation, technical
validation, functional installation validation, and human UI acceptance are
**PASS**; WU5 implementation, technical/objective validation, and human
acceptance are **PASS**.
Reconciliation Batches 1–5 are **COMPLETE**.
The accepted flow is Requirements → Database → Administrator & Site → Review &
Install → Installation Result. The former WU4 Module-selection feature is
superseded and removed from MR.1 installer UX. WU3 preserves staged
Administrator/Site values across revisit without pre-Install mutation and records
the accepted Database feedback, shared form/visual, inspection-derived intent,
namespace-collision, and ownership-proof/compatibility outcomes. WU5 accepted
the full-screen mobile shell (no card treatment; phase content is the only
conditional scroll region), shared field error/help association and invalid
state semantics, and installation-identity creation before Installation Result.
Review & Install is the first COPOT mutation boundary; database-container
provisioning at Database is the explicit capable-environment exception.
MR.1 is main-only / no-op for branch lifecycle.
Blocker A is **RESOLVED / VALIDATED** after focused persistence regression coverage.
Blocker B is **CLASSIFIED / NOT A DEFECT**.
This promotion does not reopen the completed Multi-Installation WU1–WU6
scope.
Backup & Recovery is a separate platform capability with implementation and
acceptance complete and lifecycle closure recorded in
`docs/31_backup_recovery_foundation_contract.md`.

### Post-M3 lifecycle classification

Post-M3 work is classified before a target is selected. The three outcomes are:

1. **Originating Milestone Re-open** — exceptional repair of an invalid
   closure. Use only when the originating contract promised X, closure claimed
   X passed, and new evidence proves X was already unsatisfied at closure.
   Unfinished scope and unresolved acceptance gaps remain owned by that
   originating milestone; they are not routed into maintenance or refinement.

2. **MT.x — Maintenance** — restorative work for a previously valid,
   accepted, complete, and closed baseline that later became broken,
   incompatible, or outdated. This is project-wide and may cover Webcore,
   Core Modules, shared platform, delivered M4/M5/M6 domains, package/runtime
   compatibility, or release-supported surfaces. Numbering is chronological
   (`MT.1`, `MT.2`, ...), and a closed MT milestone is not reopened for an
   unrelated later maintenance issue by default.

3. **MR.x — Refinement** — intentional improvement to a still-valid accepted
   baseline whose originating milestone and integration are complete, whose
   feature branch is closed, and which has no unresolved milestone-blocking
   defect. MR.x is limited to Webcore, Core Modules, and shared core/platform/
   Admin UX concerns; it is not the namespace for domain-specific M4, M5, M6,
   or independent third-party work. Numbering is chronological (`MR.1`,
   `MR.2`, ...), and revisiting a surface does not reopen an earlier Work Unit.

The decision rule is:

```text
Issue / change request
-> Was the originating closure actually invalid?
   YES -> Originating Milestone Re-open
   NO  -> Is a valid accepted baseline now broken, incompatible, or outdated?
          YES -> MT.x Maintenance
          NO  -> Is the still-valid baseline intentionally being improved?
                 YES -> MR.x Refinement
```

In short: re-open repairs an invalid closure; MT restores a valid closure that
later degraded; MR improves a valid closure that still works. An original
milestone defect that violated its contract is re-opened. A later regression,
compatibility drift, environment change, or obsolete validation assumption is
an MT.x candidate. Intentional quality, UX, presentation, or interaction
improvement is an MR.x candidate. Security work follows the same provenance
test and does not receive a generic security namespace.

Deferred Items remain governed independently: applicability review must first
result in ADOPT, KEEP DEFERRED, REJECT, SUPERSEDE, or NOT APPLICABLE before an
execution target is assigned. M3.R1 is historical M3-specific Admin Shell
refinement governance and remains CLOSED; it is not the general post-M3 MR.x
namespace. The obsolete `tests/admin_ui_batch2_smoke.php` CSS token guard is a
separate unselected maintenance candidate, not an automatic MT.1.

Older detailed phase and sequencing paragraphs below are retained historical
context and do not declare a current active phase or next target.

Total planning envelope: 59 domain implementation batches. This count is the approved M3.1–M3.11 domain-batch planning envelope and does not count the horizontal M3.R1 Admin Shell Retouch 1 work unit or the required Admin Shell design-adjustment checkpoints attached to M3.5–M3.11. Those design work units are governed separately and do not silently renumber the milestone batch envelopes.

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

#### Dashboard Widget Applicability Rule

After M3.9 establishes the Dashboard contribution capability, every future
module or materially evolved Admin-facing module must explicitly review
Dashboard integration applicability during preparation or planning. The review
question is whether the capability owns information, status, contextual
navigation, or a bounded administrative action that is materially useful at
Dashboard overview level. Each review must record one disposition: `REQUIRED`,
`OPTIONAL / JUSTIFIED`, `NOT APPLICABLE`, or `DEFERRED`.

The review is mandatory, but widget creation is not. A contributor may provide
`0..n` widgets, and no placeholder widget is required. Multiple widgets from
one owner must serve materially distinct purposes. Existing permissions and
module ownership remain authoritative, and `widget` is not a Module Manager
module type. A concrete postponed contribution follows normal Deferred Item
governance. This rule is prospective; it does not reopen completed milestones.
The M3.9 WU1 first-wave inventory remains authoritative for already-completed
managers.

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

Admin UX Refinement 1 and the subsequent Shell Foundation have completed the approved presentation work after M3.3 and before reserved M3.4 Content Manager. Shell Foundation covers the shared responsive Admin shell, accessibility, navigation, menus, configured-path presentation, and mobile drawer behavior. Settings Category 1 covers the locked tab presentation, truthful deferred panels, and standalone Branding presentation while preserving existing Settings and Site Asset workflows. Focused automated, runtime, and authenticated browser validation pass with documented non-material browser-surface limitations. Remaining non-blocking Admin UX items include global floating notifications, effective-permission explanation for multi-role users, and reusable dashboard block spacing; these remain deferred until separately approved.

Post-M3.1 Roadmap Sync is complete. It preserves the approved M3 sequence, locks the planned Database Upgrade / Migration System concept, and completed M3.2 preparation before Batch 1 began.

#### Database Upgrade / Migration System Checkpoint

Status: superseded as an unselected M3 checkpoint. The Package Lifecycle &
Migration Foundation preparation contract is adopted in
`docs/28_package_lifecycle_migration_foundation_contract.md`; implementation is
not started and no milestone number is assigned.

Fresh installs use the canonical `database/schema.sql`; existing installations
currently use ordered SQL artifacts in `database/upgrades/`. Those artifacts are
inputs and evidence, not a migration system. The adopted foundation will define
ordered execution, durable migration history, idempotency, explicit failure and
retry behavior, supported transaction boundaries, and Webcore-owned Core
migration ownership. Module package lifecycle remains a later architectural
slice.

#### Admin UX Refinement 1

Status: implementation and validation complete and integrated into `main` at `69fda0d` through parent-first `--ff-only` closure. Both completed feature branches were subsequently deleted locally and remotely after verified containment; only `main` remains locally and remotely, and no PR was used. It follows M3.3 and precedes reserved M3.4 Content Manager.

M3.2 and M3.3 provided management-UI patterns. Admin UX Refinement 1 completed the Module Manager and User/Role Detail refinements, and Shell Foundation completed the shared shell presentation, responsive drawer, accessibility behavior, and configured-path integration. Settings Category 1 completed the presentation-only Settings tab and Branding refinement without adding partial saves or backend capability. Global floating notifications, effective-permission explanation for multi-role users, and reusable dashboard block spacing remain deferred.

The approved Copot Admin Shell image is the canonical visual authority, and the latest UI Refinement Plan is the external scope and implementation authority. Neither source authorizes new backend or Core behavior. WordPress and other Admin interfaces are supporting references only.

Authenticated Public Toolbar is not part of Webcore or Admin UX Refinement 1. It remains Theme-owned future scope; Webcore may expose existing authentication, current-user, or permission facts, or a minimal hook if later proven necessary, but must not render or own the toolbar UI contract.

#### M3.2 Settings Manager

M3.2 Settings Manager is complete in the approved sequence and merged to `main` through `afd82f0`. Exact scope, permission reuse, approved Core touchpoints, five-batch plan, acceptance criteria, and completion evidence remain authoritative in `docs/17_m3_2_settings_manager_contract.md`. All five batches pass their gates. Final automated validation passes 366 focused M3.2 assertions, required M2.1 Admin UI, M2.3 Branding/Site Asset and unified, plus M3.1 permission/access regressions; PHP lint, repository, and boundary checks pass. The final manual matrix passes, with permission-denial and configured non-default Admin-path cases verified automated-assisted and the remaining Admin workflows verified manually. No unresolved Core, schema, runtime, or security blocker remains.

#### M3.3 Module Manager Entry Contract

Status: Batches 1–5 complete, fast-forward merged into synchronized `main`, and closed with the feature branch deleted. Batch 5 validation, manual Admin verification, documentation synchronization, and final focused review are complete.

The Module Manager module owns Admin inventory, metadata and discovery-error presentation, navigation, configured-path routes, and controlled lifecycle workflows. It consumes the existing Core discovery, repository, lifecycle, Admin URL, Admin Shell, CSRF, navigation, and sanitized-error contracts. The activation gate approves only the narrowly scoped `InstallerFinalizer::BASELINE_MODULES` addition; no other confirmed Core blocker exists, and any further Core change requires separate approval and executable evidence.

Authorization is locked to both the configured base permission `admin.access` and the dedicated runtime permission `modules.manage` (`Manage modules`). One dedicated permission covers inventory plus install, enable, disable, and uninstall. Fresh installations will provision `modules.manage` and map it to the seeded `admin` role through `database/schema.sql`. Existing installations will use the controlled, idempotent, operator-run `database/upgrades/m3_3_module_manager_permission.sql`; it may add only the permission and missing seeded-admin mapping, must not run automatically or install/enable modules, and must not introduce permission synchronization. `module_permissions` remains metadata only and grants no runtime access.

The proposed M3.3 artifact is the second independent upgrade artifact. The Database Upgrade / Migration System trigger is not currently reached, and a generic migration runner remains out of scope.

The approved activation policy adds `module-manager` to `InstallerFinalizer::BASELINE_MODULES`, so fresh installations install and enable it through the existing generic ModuleManager lifecycle. This is the sole approved activation Core touchpoint; no new activation framework, bootstrap synchronization, or automatic module reconciliation is introduced. Existing installations apply `database/upgrades/m3_3_module_manager_permission.sql`, then explicitly install and enable `module-manager` through ModuleManager before its routes are available on the next request through the enabled-module loader.

`modules/module-manager` is included in `build/package_manifest.php` and fresh-install baseline activation. Package, clean-install, focused regression, and manual Admin evidence pass. The Admin workflow denies self-disable and self-uninstall with visibly disabled controls and human-readable denial text; stable denial codes remain internal. No additional schema change, upgrade SQL artifact, migration runner, or automatic permission synchronization is approved.

Baseline automated validation passes 816 assertions: 272 focused regression, 58 clean-install, and 486 package builder smoke assertions. Patch-focused reruns pass 130 assertions: 35 Batch 3 integration, 41 Batch 3 security, and 54 Batch 4 lifecycle assertions. Cumulative executed evidence is 946 assertions with overlap and is not a unique full-suite total. Disposable manual-verification resources were fully cleaned.

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
5. Unified regression, manual verification, documentation closure, and completion audit — validation, manual verification, documentation synchronization, and final focused review complete.

Explicit non-goals include marketplace or remote installation, package download or ZIP upload, signing, automatic updates, rollback, generic version solving, Composer-style dependency resolution, automatic permission or role synchronization, a second authorization system, a generic migration framework, Theme Manager, Media Library, module settings UI, module code editor, Admin UX Refinement 1, M3.4, release, tagging, and package publication.

The M3.3 implementation, validation, documentation synchronization, focused review, commit, push, fast-forward merge, and clean synchronized `main` verification are complete. M3.3 reached implementation completion and branch lifecycle closure; it remains unreleased, untagged, and unpublished.

#### M3.4 Content Manager

Status: Batch 1 and Batch 2 Foundation complete and branch-closed on synchronized `main` at `5b51a1471da63b280e1444cd2f7ba8da4d168f28`. Focused validation passes 99 assertions: provisioning and upgrade (9), transaction/lifecycle/slug/stale-write/Taxonomy atomicity (37), and authorization matrix (53). PHP lint, `git diff --check`, disposable database cleanup, runtime synchronization, and browser validation pass. The foundation feature branch was fast-forward merged, pushed and verified against `origin/main`, and deleted locally and remotely.

Batch 3 Admin Content Workspace implementation and validation are complete and were fast-forward merged into `main` at `b175098f1afcfa02594706e5bf98886b7887e1b2`. Batch 3 focused validation passes 33 assertions, for 132 total focused executed M3.4 assertions including the foundation. Runtime synchronization and browser validation pass; the local and remote feature branches were deleted after verified containment, and Batch 3 is complete.

Batch 4 Cross-module Integration and Security Hardening implementation and validation are complete. Commit `48c1ca12ada0fe813b8efc1f4e8e0b9d52c03ccc` (`feat(m3.4): harden content manager batch 4`) was fast-forward merged into `main`; the main push was previously completed and freshly re-verified. The local and remote feature branches were safely deleted after verified containment, and the branch lifecycle is closed. This final documentation commit records Batch 4 as complete after final verification. Focused Batch 4 validation passes 33 assertions. Directly affected Content regressions pass: Batch 1 provisioning (9), Batch 1 transaction/lifecycle (37), Batch 2 authorization (53), and Batch 3 workspace (33). Package builder smoke passes 825 assertions and clean-install verification passes 60 assertions. PHP lint, `git diff --check`, source review, and targeted synchronization of the two runtime Content files pass. Browser validation passes with limitations: normal lifecycle, published rendering, Draft/Archived denial, plaintext escaping, malformed read-ID containment, current configured Admin path, and desktop/390 × 844 smoke pass. Browser request replay was unavailable for missing/invalid CSRF, authorization-before-CSRF, malformed mutation payloads/identifiers, duplicate slug, stale write, repeated transitions, and injected persistence-error responses; focused automated tests and source review cover those cases. Optional Taxonomy-disabled browser behavior was not exercised. Batch 5 follows as the runtime acceptance and closure work unit. Release, tag, and publication have not started. The authoritative six-batch M3.4 contract remains defined in `docs/18_m3_4_content_manager_contract.md`.

The contract keeps the existing `modules/content` module as the sole Content owner, locks fixed `page` and `article` types, defines the draft/published/archived lifecycle with archive-to-draft restore and no hard delete, adds the required `content.read` permission boundary, preserves optional Taxonomy integration and the existing `content::show` Theme boundary, and defers revisions, autosave, preview, scheduling, custom fields, content-type management, Media Library integration, bulk actions, APIs, and other future capabilities. Batches 1–5 implementation and validation remain documented above. Batch 6 implementation, validation, fast-forward integration, `main` push, feature-branch cleanup, and final verification are complete at `ae2c404be195dbf4395986ad21f7cb0be7da83a8`; Batch 6 and full M3.4 are complete. M3.R1 follows full M3.4 closure and must complete before M3.5 begins.

Current branch/state:

```text
main at the M3.R1 preparation post-cleanup documentation checkpoint; HEAD 5887da1f612e6edd8c3abc808bd76b4d4af51743 (`docs(m3.r1): record preparation post-merge state`)
-> Batch 1 and Batch 2 Foundation complete and branch-closed
-> Batch 3 Admin Content Workspace implementation and validation complete (branches deleted)
-> Batch 4 Integration and Security Hardening implementation/validation complete and fast-forward merged into main (feature branches deleted; branch lifecycle closed)
-> Main and origin/main are clean and synchronized at 5887da1f612e6edd8c3abc808bd76b4d4af51743 (0/0)
-> Batch 5 focused, package, clean-install, lint, runtime, and manual acceptance evidence complete
-> Batch 5 implementation, validation, documentation, commit, feature push, fast-forward merge, main synchronization, branch cleanup, and post-cleanup verification complete
-> Batch 5 lifecycle is closed; release, tag, and publication remain outstanding
-> Batch 6 Content Admin workspace redesign and Admin navigation ordering implementation complete
-> Focused source review, automated validation, runtime SHA-256 synchronization, and authenticated browser validation complete
-> Batch 6 feature commit fast-forward merged into main and main pushed; no merge commit created
-> Batch 6 feature branches were safely deleted after containment verification; only the main worktree remains
-> Batch 6 and full M3.4 are complete after final documentation and verification
-> M3.R1 preparation contract integrated, corrected, and branch-closed; classification gate complete with `NO MATERIAL RETOUCH REQUIRED`
-> M3.R1 has no implementation batches or implementation branch; full M3.R1 is complete after pushed documentation closure and synchronized clean `main`
-> M3.5 was subsequently completed and branch-closed; M3.6 WU1–WU4 are complete, WU4 is closure pending, and WU5 is the next separately authorized gate; release, tag, and publication remain separately authorized
```

### M3.R1 — Admin Shell Retouch 1

M3.4 is complete. The M3.R1 preparation and scope
contract is integrated into synchronized `main`; its source review, commit,
push, fast-forward integration, post-merge documentation correction, and
feature-branch cleanup are complete after verified containment. The local and
remote preparation branches are absent. M3.R1 classification and lifecycle
closure are complete with the final outcome `NO MATERIAL RETOUCH REQUIRED`;
final documentation commit `c5d27adeba6c1f440f6b9c62309a447f82e43a08`
(`docs(m3.r1): record classification closure`) is pushed, local and remote
`main` are synchronized at `0/0`, and the workspace is clean. No
implementation batches, implementation branch, production/test changes,
runtime synchronization, or browser validation were required. Full M3.R1 is
complete. M3.5 subsequently completed its approved preparation,
implementation, validation, integration, documentation, and branch-lifecycle
closure. M3.6 WU1–WU4 are complete, with WU4 closure pending and WU5 the
next separately authorized gate. The contract is
`docs/20_m3_r1_admin_shell_retouch_contract.md`.
M3.R1 is a completed horizontal Admin Shell work unit after full M3.4 closure
and before M3.5:

```text
M3.4 closure
->
M3.R1 Admin Shell Retouch 1
->
M3.5 Taxonomy Manager
```

M3.R1 is outside M3.4, Batch 5, Batch 6, and M3.5. It reviews the M3.1 Users & Access Admin pages, M3.2 Settings Manager Admin pages, M3.3 Module Manager Admin pages, and the shared Admin Shell navigation. Review includes sidebar, top bar, Quick menu, Admin account control, mobile drawer, grouping, labels, order, active states, responsive behavior, and accessibility.

Each reviewed page is classified as `redesign required`, `retouch required`, `review only`, or `NO CHANGE REQUIRED`. Product Designer input supports the review and recommendation; it does not authorize domain behavior, permissions, routes, schema, data contracts, ownership, Core architecture, or Git/release actions.

M3.R1 may change approved presentation and Admin navigation ordering within the existing navigation contract. Its accepted outcome required no implementation. M3.5 product scope was accepted, and its dedicated preparation contract is committed to `main` at `1e6c837340b0ea561870b7fe729791edcc0aa9f5` (`docs(m3.5): lock taxonomy manager preparation contract`). M3.5 was fast-forward integrated into `main` at `b09a01ca0a93cfa2eb9eccafd648f7a708df1576` with no merge commit; its five work units and full M3.5 are complete, feature containment passed, and the local/remote feature branches are deleted. WU5 confirms 25/39/41/11 Taxonomy assertions, 9/37/53/33/33/31 Content/package-compatible assertions, 825 package assertions, 59 clean-install assertions, and clean PHP lint/diff checks. The scope remains fixed to the existing Taxonomy module's `category` hierarchy and flat `tag` management, with no taxonomy type CRUD, filtering, or Navigation Manager work. M3.6 WU1–WU4 are now complete, WU4 is closure pending, and WU5 is the next separately authorized gate. Release, tag, and publication remain not started and separately authorized.

### M3.5–M3.11 Admin Shell Design-Adjustment Checkpoints

The reusable requirements are defined in `docs/19_m3_admin_shell_design_adjustment_contract.md`. Each milestone contract determines the checkpoint placement within its own batch structure; identical batch numbering is not required. `NO CHANGE REQUIRED` is valid when evidence supports it.

| Milestone | Required design-adjustment review |
|---|---|
| M3.5 Taxonomy Manager | Taxonomy workspace, category hierarchy, flat tags, relationship to Content, and placement/consistency in the Admin Shell. |
| M3.6 Navigation Manager | Navigation-management workspace and Admin placement; preserve the Navigation ownership boundary. |
| M3.7 Theme Manager | Admin Theme Manager and settings surfaces; frontend Theme rendering remains excluded. |
| M3.8 Media Library | Media-management and selection surfaces when implemented. |
| M3.9 Internal Dashboard | Dashboard hierarchy, density, placement, and responsive behavior. |
| M3.10 Redirect Manager | Only when the approved milestone contract includes relevant Admin UI. |
| M3.11 Form Manager | Admin management or builder UI; unrelated public form rendering remains outside this checkpoint. |

These checkpoints are horizontal design-governance work units and are not included in the 59 domain implementation batches unless a future approved planning decision explicitly changes the accounting model.

## MR.2 Webcore Admin Refinement

MR.2 WU1 — Webcore Admin View Foundation is COMPLETE AND CLOSED on the
integrated `main` baseline. Its authoritative contract is
`docs/38_mr_2_wu1_webcore_admin_view_foundation_contract.md`. WU1 provides only
the reusable Webcore-owned Admin Page Frame inside the existing `admin-main`
boundary, with bounded semantic `surface` and `spacing` intent and preserved
consumer-owned Content. MR.2 WU2 — Webcore System Manager Baseline is COMPLETE
AND CLOSED on the integrated `main` baseline. Its authoritative contract and
closure record is `docs/39_mr_2_wu2_webcore_system_manager_baseline_contract.md`;
technical validation and human UI acceptance are PASS / APPROVED.

### Post-M3 Webcore & Extension Architecture Reconciliation

The authoritative contract is
`docs/40_post_m3_webcore_extension_architecture_reconciliation_contract.md`.
The workstream is PROMOTED / CONTRACT LOCKED and WU1-WU7 implementation and
cross-lifecycle acceptance are COMPLETE AND CLOSED. Its topology is WU1
Architecture Contract & Ownership Reconciliation, WU2 Built-in Public View &
Theme Decoupling, WU3 Webcore Content Extraction, WU4 Webcore Media Extraction,
WU5 Webcore Navigation & Redirects Extraction, WU6 Bundled Module & Installer
Reconciliation, and WU7 Cross-Lifecycle Acceptance & Architecture Closure.
MR.2 WU1 and WU2 are complete. MR.2 WU3 is COMPLETE AND CLOSED for its
accepted bounded scope, and its promoted contract and closure record are
available at
`docs/41_mr_2_wu3_system_manager_lifecycle_modules_ux_refinement_contract.md`;
System Manager is the canonical product-facing Module lifecycle surface. This
does not claim that all System Manager tabs or later MR.2 refinement work is
complete and does not reopen accepted WU1/WU2 implementation.
MR.2 WU4 — Content Manager Refinement is COMPLETE AND CLOSED for its accepted
bounded scope. The Content list/form refinement and narrow-screen Featured
Media control correction passed objective validation and human/product
acceptance; Webcore Content ownership and the retained Content Manager
extension model remain unchanged. Rich-text/editor and later MR.2 work remain
outside this closure. The authoritative contract is
`docs/42_mr_2_wu4_content_manager_refinement_contract.md`.

MR.2 WU5 shared Admin primitive audit/contract, WU6 shared-artifact
implementation, and WU7 representative propagation proof are COMPLETE AND
CLOSED/ACCEPTED for their bounded scopes. WU8 cross-surface verification is
COMPLETE / PASS and MR.2 shared Admin refinement is COMPLETE / CLOSED under
`docs/48_mr_2_wu8_cross_surface_verification_closure_contract.md`. Shared File
Intake is resolved at the GPT/user planning layer as ADOPT / FUTURE
CROSS-SURFACE CONCEPT / OUTSIDE MR.2 / NOT IMPLEMENTATION-AUTHORIZED; it
remains a thread-level saved concept without an individual Concept file. WU8
does not authorize global flat/radius policy, standard eyebrow restoration,
per-Module refinement, or repair of the accepted NON-BLOCKING PRE-EXISTING
VALIDATION DEBT in the historical Media test.

---

## Historical M3 Core Modules Context

The following M3-era checkpoint summaries are historical records, not the
current active-phase or next-target declaration.

### M3.7 and M3.8 post-merge history

Current authoritative state: M3.8 WU1–WU7 and full M3.8 are complete and
closed on `main`; post-merge documentation closure, verified containment, and
local/remote feature-branch deletion are complete. M3.9 Internal Dashboard
WU1–WU4 are complete and integrated into `main` by fast-forward with no merge
commit. M3.9 feature containment is complete; local and remote
`feature/m3.9-internal-dashboard` are deleted, and the M3.9 feature-branch
lifecycle is CLOSED. Full M3.9 Internal Dashboard is complete and closed. The
Admin Shell design-adjustment checkpoint is `NO CHANGE REQUIRED` for the
reviewed M3.9 baseline. M3.10 Redirect Manager preparation is LOCKED in
`docs/26_m3_10_redirect_manager_contract.md`; WU1–WU4 are COMPLETE AND
CLOSED, fast-forward integrated into `main` at
`5181b45c7107f4b324f68ed02958623b69e2e5a5`; containment is VERIFIED, its
feature-branch lifecycle is CLOSED, and local/remote
`feature/m3.10-redirect-manager` are deleted. Its Admin Shell
design-adjustment checkpoint is `NO CHANGE REQUIRED`. Content historical-slug
redirects remain deferred; Navigation and Dashboard integration remain not
applicable. M3.11 Form Manager preparation is LOCKED in
`docs/27_m3_11_form_manager_contract.md`; the M3.11 feature branch is CREATED
FOR PREPARATION DOCUMENTATION, its current content is PREPARATION DOCUMENTATION
ONLY, and implementation is NOT STARTED. M3.11 Work Unit 1 is NOT STARTED. Release,
tag, and publication remain separately authorized.

M3.7 Work Units 1–6 are complete and integrated into `main` at
`667ae1f0dbb8079ea0420a107bc1795c43cc5bea` by fast-forward with no merge
commit. Post-merge documentation closure commit `8dea71c82a4076c8b9d399047031e0a1ad18b0c6` preceded this correction. Objective automated acceptance and reachable browser/presentation
review passed; feature containment and local/remote feature-branch cleanup
passed. Full M3.7 is complete. The real-settings Theme Settings/color-control spot-check remains
deferred and non-blocking because the bundled Default Theme declares no
settings. Release, tag, and publication remain not started. M3.8 WU1–WU7 are
complete and fast-forward integrated into `main` at
`d5ce5fb32e3f61014e389f6d0634a29985723e09` with no merge commit. Post-merge
documentation closure was recorded at
`e21e7b281fecdb7619022be0457381a5ce31ce85`; verified containment and
local/remote feature-branch deletion are complete. M3.9 Internal Dashboard
WU1–WU4 are complete and integrated into `main` by fast-forward with no merge
commit. M3.9 feature containment is complete; local and remote
`feature/m3.9-internal-dashboard` are deleted, and the M3.9 feature-branch
lifecycle is CLOSED. Full M3.9 Internal Dashboard is complete and closed. The
Admin Shell design-adjustment checkpoint is `NO CHANGE REQUIRED` for the
reviewed M3.9 baseline. M3.10 Redirect Manager preparation is LOCKED in
`docs/26_m3_10_redirect_manager_contract.md`; WU1–WU4 are COMPLETE AND
CLOSED, fast-forward integrated into `main` at
`5181b45c7107f4b324f68ed02958623b69e2e5a5`; containment is VERIFIED, its
feature-branch lifecycle is CLOSED, and local/remote
`feature/m3.10-redirect-manager` are deleted. Its Admin Shell
design-adjustment checkpoint is `NO CHANGE REQUIRED`. Content historical-slug
redirects remain deferred; Navigation and Dashboard integration remain not
applicable. M3.11 Form Manager preparation is LOCKED in
`docs/27_m3_11_form_manager_contract.md`; the M3.11 feature branch is CREATED
FOR PREPARATION DOCUMENTATION, its current content is PREPARATION DOCUMENTATION
ONLY, and implementation is NOT STARTED. M3.11 Work Unit 1 is NOT STARTED. Release,
tag, and publication remain separately authorized. Further Media Manager visual refinement remains Deferred / Unscheduled and non-blocking.
Older M3.7 preparation
checkpoint statements below are historical.

M3.6 WU1–WU6 are complete, fast-forward integrated into `main` at
`8deb4f67d47f476e254b36e3802bd250e393921d` with no merge commit. WU1–WU5 are
complete; WU1–WU6 closure evidence includes the WU6 security closure
passed 15 assertions after focused validation,
runtime/browser evidence, and human acceptance. The WU5 implementation commit
is `b9febc49071d8997a26b0488ecde7bedc1748a72` (`feat(m3.6): consume navigation
in frontend themes`). The narrow Core frontend Theme context seam is
request-scoped, deterministic, frozen before dispatch, lazily composed, and
fail-closed; Core and public/Content routes do not depend directly on
Navigation, while Themes own markup, styling, composition, and responsive
presentation. `supports.navigation_locations`, assigned menus, hierarchy,
sibling order, visibility, custom targets, published Content targets, and
fail-closed unavailable/hidden/disabled-provider behavior are accepted. The
WU4 Admin design-adjustment checkpoint is `NO CHANGE REQUIRED`. Production
Taxonomy targets remain deferred. The feature branch lifecycle is closed. Full
M3.6 Navigation Manager is complete. M3.7 WU1–WU6 are complete and
full M3.7 is complete, fast-forward integrated at
`667ae1f0dbb8079ea0420a107bc1795c43cc5bea`; M3.8 Media Library preparation is
the next target, not implementation.
Release, tag, and publication remain not started and separately authorized.

The following paragraph is retained as a historical pre-WU5 checkpoint record
and does not describe the final M3.6 state:

At that checkpoint, M3.6 WU1–WU5 were complete on the feature branch; WU5 was a candidate and WU6 was the next separately authorized gate. The evidence and boundaries recorded there remain historical. The accepted preparation contract was `docs/22_m3_6_navigation_manager_contract.md`; no generic Core abstraction or migration framework was introduced.

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
   * Batch 6 follows Batch 5 and precedes full M3.4 closure.
   * M3.R1 follows full M3.4 closure and precedes M3.5; it is outside M3.4.
5. M3.5 Taxonomy Manager
   * preparation contract and exact work-unit breakdown before implementation
   * relevant Admin Shell design-adjustment checkpoint before closure
6. M3.6 Navigation Manager
   * relevant Admin Shell design-adjustment checkpoint before closure
7. M3.7 Theme Manager
   * relevant Admin Shell design-adjustment checkpoint before closure
8. M3.8 Media Library
   * relevant Admin Shell design-adjustment checkpoint before closure when Admin UI exists
9. M3.9 Internal Dashboard
   * relevant Admin Shell design-adjustment checkpoint before closure
10. M3.10 Redirect Manager
   * preparation contract locked before implementation
   * relevant Admin Shell design-adjustment checkpoint before closure
11. M3.11 Form Manager
   * preparation contract locked before implementation
   * relevant Admin Shell design-adjustment checkpoint before closure

This sequence is approved by M3 Prep Stage 2 as refined by the documented Batch 6, M3.R1, and Admin Shell design-governance decisions. It remains subject to the documented evidence-based Sequence Change Rule and is not silently reordered. The reusable design-adjustment contract is `docs/19_m3_admin_shell_design_adjustment_contract.md`.

Navigation data remains module-owned by the future Navigation boundary. Themes declare locations and control rendering through a documented consumption contract. Domain-owned target resolution is contributed through explicit contracts, registries, or resolvers.

### M3.7 Theme Manager status

M3.7 Work Units 1–6 are implemented, objectively accepted, and fast-forward
integrated into `main` at `667ae1f0dbb8079ea0420a107bc1795c43cc5bea` with no
merge commit. Reachable browser and human presentation review passed. The
actual Theme Settings form and color-control spot-check remains deferred and
non-blocking because the bundled Default Theme declares no settings. Theme-
specific feature configuration belongs to Theme Manager. Global feature or
content-domain expansion is outside M3.7. ZIP installation, marketplace,
uninstall, Media Library, page building, Custom CSS, and other excluded
capabilities remain outside M3.7. Feature containment and branch cleanup
passed. Full M3.7 is complete; release, tag, and publication have not
started. M3.8 WU1–WU7 are complete and fast-forward integrated into `main` at
`d5ce5fb32e3f61014e389f6d0634a29985723e09` with no merge commit; post-merge
documentation closure was recorded at
`e21e7b281fecdb7619022be0457381a5ce31ce85`, verified containment and
local/remote feature-branch deletion are complete, and the authoritative
branch is `main`. M3.9 Internal Dashboard WU1–WU4 are complete and integrated
into `main` by fast-forward with no merge commit. M3.9 feature containment is
complete; local and remote `feature/m3.9-internal-dashboard` are deleted, and
the M3.9 feature-branch lifecycle is CLOSED. Full M3.9 Internal Dashboard is
complete and closed. The Admin Shell design-adjustment checkpoint is `NO
CHANGE REQUIRED` for the reviewed M3.9 baseline. M3.10 Redirect Manager
WU1–WU4 are COMPLETE AND CLOSED, fast-forward integrated into `main` at
`5181b45c7107f4b324f68ed02958623b69e2e5a5`; containment is VERIFIED, its
feature-branch lifecycle is CLOSED, and local/remote
`feature/m3.10-redirect-manager` are deleted. Its Admin Shell
design-adjustment checkpoint is `NO CHANGE REQUIRED`. Content historical-slug
redirects remain deferred; Navigation and Dashboard integration remain not
applicable. M3.11 Form Manager preparation is LOCKED in
`docs/27_m3_11_form_manager_contract.md`; the M3.11 feature branch is CREATED
FOR PREPARATION DOCUMENTATION, its current content is PREPARATION DOCUMENTATION
ONLY, and implementation is NOT STARTED. M3.11 Work Unit 1 is NOT STARTED. Release,
tag, and publication remain separately authorized.

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

### M3.8 Media Library preparation status

WU6 is an accepted predecessor for the approved Content featured-Media picker,
consumer-scoped pending preparation and save-time promotion, usage
synchronization, and unused-only Media deletion safety. Focused validation and
human functional/browser acceptance are complete. WU7 closure evidence is
complete; full M3.8 was fast-forward integrated into `main` at
`d5ce5fb32e3f61014e389f6d0634a29985723e09` with no merge commit. Post-merge
documentation closure was recorded at
`e21e7b281fecdb7619022be0457381a5ce31ce85`; verified containment and
local/remote feature-branch deletion are complete. M3.9 Internal Dashboard
WU1–WU4 are complete and integrated into `main` by fast-forward with no merge
commit. M3.9 feature containment is complete; local and remote
`feature/m3.9-internal-dashboard` are deleted, and the M3.9 feature-branch
lifecycle is CLOSED. Full M3.9 Internal Dashboard is complete and closed. The
Admin Shell design-adjustment checkpoint is `NO CHANGE REQUIRED` for the
reviewed M3.9 baseline. M3.10 Redirect Manager preparation is LOCKED in
`docs/26_m3_10_redirect_manager_contract.md`; WU1–WU4 are COMPLETE AND
CLOSED, fast-forward integrated into `main` at
`5181b45c7107f4b324f68ed02958623b69e2e5a5`; containment is VERIFIED, its
feature-branch lifecycle is CLOSED, and local/remote
`feature/m3.10-redirect-manager` are deleted. Its Admin Shell
design-adjustment checkpoint is `NO CHANGE REQUIRED`. Content historical-slug
redirects remain deferred; Navigation and Dashboard integration remain not
applicable. M3.11 Form Manager preparation is LOCKED in
`docs/27_m3_11_form_manager_contract.md`; the M3.11 feature branch is CREATED
FOR PREPARATION DOCUMENTATION, its current content is PREPARATION DOCUMENTATION
ONLY, and implementation is NOT STARTED. M3.11 Work Unit 1 is NOT STARTED. Release,
tag, and publication remain separately authorized.

#### Deferred Item — DI-M3.8-WU6-01

- **Title:** Further Media Manager visual/presentation refinement
- **Status:** Deferred
- **Detail:** Follow-on visual refinement of the Media workspace and detail
  presentation after the accepted WU6 functional baseline.
- **Reason:** The delivered picker, consumer-scoped preparation/save boundary,
  deletion safety, and public rendering are accepted; additional presentation
  work is not required to preserve that baseline.
- **Impact:** Non-blocking. It must not reopen the accepted WU6 functional
  baseline or alter WU7 scope.
- **Revisit trigger:** A separately approved presentation review with a
  concrete, bounded target.
- **Initial target disposition:** Unscheduled. This item authorizes no future
  milestone or work unit.

#### Deferred Item — DI-M3.9-01

- **Title:** User-customizable Dashboard layout
- **Status:** Deferred
- **Target:** Unscheduled
- **Source:** M3.9 Internal Dashboard preparation contract
- **Detail:** Drag-and-drop placement, bounded user resizing, per-user layout
  persistence, default/reset behavior, and responsive reconciliation.
- **Reason:** The baseline composition and widget contracts should stabilize
  before persistent user customization is introduced.
- **Impact:** Non-blocking for the M3.9 baseline.
- **Revisit trigger:** A stable baseline Dashboard composition plus a concrete
  requirement for persistent user customization.

#### Deferred Item — DI-M3.9-02

- **Title:** Admin Media CSS token normalization
- **Status:** Deferred
- **Target:** Unscheduled
- **Source:** M3.9 WU4 affected Admin regression audit
- **Classification:** Design-system technical debt; non-fatal and non-blocking.
- **Detail:** Admin UI Batch 2 detects hardcoded Media/Media-picker color values
  outside the Admin design-token definitions.
- **Impact:** Design-system consistency and maintainability; future Admin
  color/theme changes may not propagate consistently.
- **Revisit trigger:** Future work materially touching Admin design tokens,
  Admin visual-system normalization, Media Admin presentation, or Admin
  baseline remediation.

#### Deferred Item — DI-M3.9-03

- **Title:** Admin UI Batch 4 regression-contract modernization
- **Status:** Deferred
- **Target:** Unscheduled
- **Source:** M3.9 WU4 affected Admin regression audit
- **Classification:** Stale regression-test contract; non-fatal and non-blocking.
- **Detail:** The older Batch 4 guard prohibits JavaScript in Content Admin
  views, but M3.8 legitimately introduced the approved Content contextual
  Media-picker script.
- **Impact:** Persistent false-negative regression signal and reduced trust in
  the Admin regression baseline.
- **Revisit trigger:** Future work materially touching the Content Media
  picker, Admin regression-suite maintenance, Content Admin presentation, or
  Admin baseline remediation.

M3.8 initial clarification and preparation audit are complete. WU1–WU4
are accepted predecessors, implemented, focused-validated, and durably
delivered on `main`; WU4 is complete. WU5
presentation refinement implements the accepted visual grid/card management
direction and centralized filename title fallback; focused validation and AI
acceptance are `PASS`, and human visual/browser acceptance is `PASS` for
bounded cards, grid presentation, card-to-preview interaction, preview
overlay usability, and the Admin/public action boundary. WU5 is finally closed
and complete. Manual upload accepts one file per submission;
drag-and-drop and multiple-file/batch upload remain unsupported future Media
enhancements and are not WU5 blockers; video remains outside the current M3.8
contract. WU6 is complete for the approved Content featured-Media picker,
consumer-scoped pending preparation and save-time promotion, usage
synchronization, and unused-only Media deletion safety; focused validation and
human functional/browser acceptance are complete. WU7 closure evidence covers
upgrade, authorization, package, and packaged clean-install boundaries. Full
M3.8 is complete and was fast-forward integrated into `main` at
`d5ce5fb32e3f61014e389f6d0634a29985723e09` with no merge commit. The feature
post-merge documentation closure was recorded at
`e21e7b281fecdb7619022be0457381a5ce31ce85`; verified containment and
local/remote feature-branch deletion are complete. M3.9 Internal Dashboard
WU1–WU4 are complete and integrated into `main` by fast-forward with no merge
commit. M3.9 feature containment is complete; local and remote
`feature/m3.9-internal-dashboard` are deleted, and the M3.9 feature-branch
lifecycle is CLOSED. Full M3.9 Internal Dashboard is complete and closed. The
Admin Shell design-adjustment checkpoint is `NO CHANGE REQUIRED` for the
reviewed M3.9 baseline. M3.10 Redirect Manager preparation is LOCKED in
`docs/26_m3_10_redirect_manager_contract.md`; WU1–WU4 are COMPLETE AND
CLOSED, fast-forward integrated into `main` at
`5181b45c7107f4b324f68ed02958623b69e2e5a5`; containment is VERIFIED, its
feature-branch lifecycle is CLOSED, and local/remote
`feature/m3.10-redirect-manager` are deleted. Its Admin Shell
design-adjustment checkpoint is `NO CHANGE REQUIRED`. Content historical-slug
redirects remain deferred; Navigation and Dashboard integration remain not
applicable. M3.11 Form Manager preparation is LOCKED in
`docs/27_m3_11_form_manager_contract.md`; the M3.11 feature branch is CREATED
FOR PREPARATION DOCUMENTATION, its current content is PREPARATION DOCUMENTATION
ONLY, and implementation is NOT STARTED. M3.11 Work Unit 1 is NOT STARTED. Release,
tag, and publication remain separately authorized.
The
authoritative preparation contract is
`docs/24_m3_8_media_library_contract.md`.

The clarification brief adds no new roadmap capability. It strengthens the
existing Media direction by locking first-party module ownership, a database
catalogue with filesystem binary storage, stable Media identity, controlled
delivery, a reusable picker, bounded synchronous image processing, managed PDF
behavior, usage-aware deletion safety, and exactly seven work units. Actual
Content, Theme, and Site Settings field adoption remains post-M3.8 through
explicit consumer contracts.

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
