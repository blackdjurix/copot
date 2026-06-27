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

### Scope

* Installation Wizard
* Database Setup
* Environment Configuration
* Administrator Creation

### Deliverable

Fresh installation process for new deployments.

---

# Future Milestones

The roadmap phases are organized as:

```text
M1 = Framework Foundation
M2 = Platform Capabilities
M3 = Business Modules
M4 = Commerce
M5 = Ecosystem
```

M1 establishes the framework foundation. M2 adds reusable platform capabilities needed for production and commercial solutions. After M2, copot should be mature enough to support production/commercial projects as a platform foundation, even though not every business module will exist yet.

---

## M2 Platform Capabilities

Objective:

Make copot ready to support production/commercial applications and websites by adding reusable platform capabilities. M2 is not a collection of business modules; it strengthens the shared capabilities that later modules can build on.

Candidate capabilities:

* Media Library
* Image Service and basic image processing
* Editor Framework and editor adapter/plugin support
* Content Workspace
* Navigation / Menu system
* Search foundation
* Notification foundation
* Workflow / Automation foundation
* Asset Management foundation
* Internal Dashboard

---

## M3 Business Modules

Objective:

Use the Core and M2 Platform Capabilities to build business-oriented modules.

Candidate modules:

* CRM
* Inventory
* POS
* HR
* Finance
* Project / Task Management

---

## M4 Commerce

Objective:

Build commerce capabilities on top of the framework foundation and platform capabilities.

Candidate directions:

* Catalog
* Orders
* Checkout
* Payment integration
* Customer/account commerce flows

---

## M5 Ecosystem

Objective:

Support distribution, extension, and integration around the copot platform.

Candidate directions:

* Module/package distribution
* Update lifecycle
* Extension ecosystem
* Developer tooling
* Integration/API ecosystem


