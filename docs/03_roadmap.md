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

The first M2 minor milestone is M2.1 Admin UI Foundation.

M2.1 is currently in Preparation and scope lock.

Its target release is v0.9.0.

Implementation must not begin until the M2.1 documentation, architecture boundaries, batch plan, and acceptance criteria are approved.

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

### Locked and Candidate Capabilities

#### M2.1 Admin UI Foundation

Status: Preparation and scope lock.

Target release: v0.9.0.

##### Objective

Establish the first M2 Platform Capability: a theme-independent, shared-hosting-safe Admin UI Foundation for Core and module-owned administrative interfaces.

##### Scope

* centralized admin path validation and URL generation;
* centralized Admin Shell and page rendering;
* static Admin UI assets and design tokens;
* reusable layout, alert, form, action, panel, table, and empty-state patterns;
* responsive and accessibility baseline;
* stable permission-aware admin navigation with ordering and active-state resolution;
* minimal dashboard-widget contribution registry;
* migration of existing Admin Dashboard, Settings, Content, and Taxonomy presentation.

##### Deliverable

A reusable Admin UI Foundation that removes duplicated admin rendering and presentation patterns while preserving existing routes, permissions, CSRF protection, validation, persistence, and domain behavior.

Detailed scope and architecture are defined in:

```text
docs/10_admin_ui_foundation.md
```

A full admin theme or skin system, M3 Internal Dashboard, manager modules, database-backed dashboard customization, frontend build tooling, and domain behavior changes are outside M2.1.

#### 2. Event Foundation

Planned direction:

* application and module events;
* listener registration;
* controlled dispatch;
* decoupled module integration;
* extension hooks for Notifications and Workflow.

Queue infrastructure remains deferred until concrete asynchronous workloads require it.

#### 3. Editor Framework

Planned direction:

* editor contract;
* adapter support;
* editor configuration;
* serialization boundaries;
* sanitization integration;
* media-picker integration;
* plugin or adapter extension points.

Editor.js is the leading planned default adapter, not the permanent platform contract.

Content Manager / Workspace belongs to M3.

#### 4. Media Foundation

Planned direction:

* upload validation;
* storage abstraction;
* media records and metadata;
* ownership and references;
* URL and delivery handling;
* variant lifecycle;
* delete safeguards.

Media Library belongs to M3.

#### 5. Image Service

Planned direction:

* GD baseline adapter;
* optional Imagick adapter;
* dimensions and metadata;
* resize;
* crop;
* rotate;
* format and quality handling;
* thumbnail and variant generation;
* processing limits suitable for shared hosting.

Browser editing libraries such as Cropper.js may provide UI interaction but do not replace server-side processing.

#### 6. Navigation Foundation

Planned direction:

* navigation registry;
* menu locations;
* hierarchical item model;
* target resolution;
* permission-aware rendering;
* active-state resolution;
* module-contributed navigation entries;
* theme-declared menu locations.

Navigation Manager belongs to M3.

#### 7. Search Foundation

Planned direction:

* searchable-resource contract;
* provider or adapter registration;
* permission-aware result handling;
* database-backed baseline search;
* cross-module discovery.

#### 8. Notification Foundation

Planned direction:

* notification contract;
* in-application notification channel;
* persistence;
* read and unread state;
* module extension hooks.

External mail, SMS, push, and queue-backed delivery remain outside the initial foundation unless separately approved.

#### 9. Workflow / Automation Foundation

Planned direction:

* trigger and action contracts;
* event integration;
* workflow definitions;
* controlled execution records;
* extension points for future automation modules.

A full visual workflow builder is not assumed.

### M2 Exclusions

M2 does not include:

* Media Library management UI;
* Content Manager / Workspace;
* Theme Manager;
* Settings Manager;
* Navigation Manager;
* Business/Application Modules;
* Commerce;
* marketplace or package distribution;
* general API platform;
* queue infrastructure without a concrete workload;
* generic Asset Management Foundation.

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
3. Media Library
4. Theme Manager
5. Content Manager / Workspace
6. Taxonomy Manager
7. Navigation Manager
8. Internal Dashboard

### Supporting Candidates

9. Redirect Manager
10. Form Manager

This order represents candidate priority and dependency direction, not a final locked M3.x submilestone sequence.

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
Event Foundation
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
