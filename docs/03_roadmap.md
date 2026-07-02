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

M2.2 Extensibility Foundation is complete.

The active focus is M2.3 Minimal Site Capabilities, beginning with Batch 1 documentation and contract lock.

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

Status: Active. Batch 1 scope, repository audit, architecture, and contract lock.

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

1. Scope, audit, architecture, and contract lock.
2. Localization and formatting foundation.
3. Core Branding settings contract.
4. Minimal local asset/upload foundation.
5. Logo and Favicon integration.
6. Regression, manual verification, and completion.

#### M2.4 Platform Hardening

Status: Planned.

##### Minimum scope

* consistent application error boundaries;
* internal logging baseline without exposing sensitive details;
* security and escaping review;
* authentication, permission, CSRF, upload, and storage review;
* regression gate across M1 and lean M2;
* shared-hosting operational checks;
* documentation and release-readiness review.

M2.4 is a release gate, not an invitation to build enterprise observability before the framework has managers to observe.

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

## M3 Core Modules

### Objective

Build reusable first-party management modules on top of M1 Framework Foundation and M2 Platform Capabilities.

Core Modules:

* provide user-facing or administrative management functionality;
* are not tied to a specific business domain;
* follow the Module Manager lifecycle;
* may become dependencies of other modules;
* remain modular even when distributed with copot.

### Essential Candidates

1. Users & Access
2. Settings Manager
3. Module Manager
4. Navigation Manager
5. Theme Manager
6. Content Manager / Workspace
7. Taxonomy Manager
8. Media Library

### Supporting Candidates

9. Internal Dashboard
10. Redirect Manager
11. Form Manager

This order represents the current lean priority and dependency direction, not an immutable M3.x sequence.

Navigation data belongs to Core/Navigation Manager, while themes declare locations and control rendering. Theme Manager follows Navigation Manager so the first basic theme can consume a stable menu contract.

Content and Taxonomy already have Webcore foundations. Media Library may follow them because M2.3 provides only the minimal asset/upload boundary needed for logo, favicon, theme, and early content use cases. If a concrete Content Manager requirement makes a reusable media picker mandatory, Media Library may move earlier.

### Existing Module Evolution

The existing Content and Taxonomy modules remain the same modules.

```text
Content Module
->
Content Manager / Workspace
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
Media Foundation + Image Service
!=
Media Library
```

Media Foundation and Image Service provide infrastructure.

Media Library provides management and selection UI.

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
Media Foundation + Image Service
->
Media Library
->
Theme Manager and Content Manager media fields
```

```text
Editor Framework
->
Content Manager / Workspace
```

```text
Navigation Foundation
->
Navigation Manager
```

```text
Content + Taxonomy + Theme menu locations
->
Navigation target integration
```

Dependencies should remain directional.

Later modules may depend on earlier capabilities, but shared platform services must not depend on user-facing manager modules or business domains.
