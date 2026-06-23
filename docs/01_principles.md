# Copot Principles

## Purpose

copot is a modular PHP-based website framework designed to support websites, content platforms, business applications, and workflow automation systems through a shared architecture.

The framework should remain lightweight, extensible, maintainable, and deployable on common hosting environments.

---

## Core Philosophy

copot is built around three primary layers:

```text
Core
+
Modules
+
Themes
```

### Core

Provides infrastructure and framework services.

Examples:

* Routing
* Configuration
* Authentication
* Permissions
* Database Access
* Module Loading
* Theme Loading

### Modules

Provide business functionality.

Examples:

* Articles
* Catalog
* Workflow
* Assets
* Store

### Themes

Provide presentation and visual layout.

Themes should never own business logic.

---

## Separation of Concerns

Business logic belongs to modules.

Infrastructure belongs to the core.

Presentation belongs to themes.

A layer should not take responsibility for another layer's concerns.

---

## Documentation First

Architecture decisions should be documented before implementation.

Documentation is considered part of the source code.

Major architectural changes require documentation updates.

---

## Modular First

New business functionality should be implemented as modules whenever practical.

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
↓
Content Platform
↓
Business Platform
↓
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
