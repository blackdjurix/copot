# Copot Principles

## Purpose

copot is a modular PHP-based website framework designed to support websites, content platforms, business applications, and workflow automation systems through a shared architecture.

The framework should remain lightweight, extensible, maintainable, and deployable on common hosting environments.

---

## Core Philosophy

copot is organized around four architectural layers:

```text
Core Infrastructure
+
Platform Capabilities
+
Modules
+
Themes
```

Modules are further classified as:

```text
Core Modules
Business / Application Modules
```

### Core Infrastructure

Provides the minimum runtime and lifecycle foundation.

Examples:

* Application bootstrap
* Configuration
* Routing
* Authentication
* Permissions
* Database access
* Module lifecycle
* Theme lifecycle
* Request and response handling

Core responsibilities are implemented progressively according to the roadmap.

### Platform Capabilities

Provide reusable services, contracts, registries, adapters, processing, and extension foundations.

Examples:

* Admin UI Foundation
* Event Foundation
* Editor Framework
* Media Foundation
* Image Service
* Navigation Foundation
* Search Foundation
* Notification Foundation
* Workflow / Automation Foundation

Platform Capabilities do not need standalone management interfaces and must not represent business-specific domains.

### Modules

Modules provide reusable management functionality or domain-specific application behavior.

Core Module examples:

* Content
* Taxonomy
* Users & Access
* Settings Manager
* Media Library
* Theme Manager
* Navigation Manager
* Internal Dashboard

Business/Application Module examples:

* Catalog
* Property
* Booking
* CRM
* Inventory
* Project Management

### Themes

Themes provide presentation and visual layout only.

Themes must not own business logic, persistence, authentication, platform services, or module lifecycle behavior.

---

## Separation of Concerns

Runtime and lifecycle infrastructure belongs to Core Infrastructure.

Reusable services, registries, adapters, processing, and extension contracts belong to Platform Capabilities.

Reusable administrative functionality belongs to Core Modules.

Domain-specific application behavior belongs to Business/Application Modules.

Presentation belongs to Themes.

A layer must not take responsibility for another layer's concerns.

---

## Documentation First

Architecture decisions should be documented before implementation.

Documentation is considered part of the source code.

Major architectural changes require documentation updates.

---

## Modular First

Reusable management functionality should be implemented as Core Modules whenever practical.

Domain-specific functionality should be implemented as Business/Application Modules.

Platform services must not be disguised as modules merely because they may later receive management UI.

The framework should encourage modular development instead of monolithic development.

---

## Shared Hosting First

The framework must operate on:

* Shared Hosting
* cPanel Hosting

without requiring advanced server configuration.

---

## Progressive Scalability

Projects should be able to evolve from:

```text
Simple Website
->
Content Platform
->
Business Platform
->
Automation Platform
```

without requiring a complete rebuild.

---

## Technology Principles

Backend:

* PHP 8.x

Database:

* MySQL
* MariaDB

Frontend:

* Bootstrap

Development Environment:

* XAMPP

---

## Simplicity Over Complexity

Prefer simple solutions before introducing abstractions.

Avoid unnecessary dependencies.

Avoid premature optimization.

Avoid introducing patterns without a demonstrated need.

---

## Long-Term Goal

Build a modular framework capable of supporting content management, business operations, automation workflows, and future platform services from a unified architecture.

