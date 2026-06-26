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

## M1.6 Taxonomy System

Status: Next planned milestone after M1.5.

### Scope

* Generic Categories
* Generic Tags
* Reusable Taxonomies

### Deliverable

Reusable classification system.

---

## M1.7 Site Settings

### Scope

* Site Title
* Site Description
* Logo
* Email
* Localization

### Deliverable

Centralized site configuration management.

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

## M2 Business Platform

Modules:

* Asset Management
* Workflow Automation
* Internal Dashboard

---

## M3 Commerce Platform

Modules:

* Catalog
* Store
* Orders
* Customers

---

## M4 Platform Services

Core Services:

* API Layer
* Event System
* Queue System
* Background Jobs

---

## M5 Ecosystem

Platform Expansion:

* Marketplace
* Package System
* External Integrations
* Developer SDK


