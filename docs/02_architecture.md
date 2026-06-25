# Copot Architecture

## Architecture Overview

copot consists of three primary layers:

```text
Core
->
Modules
->
Themes
```

---

## Core Layer

The Core layer provides infrastructure services.

Responsibilities:

* Application Bootstrap
* Configuration Management
* Routing
* Authentication
* Permissions
* Database Access
* Module Loading
* Theme Loading
* Request Lifecycle Management

These responsibilities are introduced progressively by milestone. Basic Authentication and Permissions are implemented in M1.2, Module Loading begins in M1.3, and Theme Loading is implemented in M1.4.

Example:

```text
Request
->
Router
->
Controller
->
Service
->
View
->
Response
```

---

## Authentication and Permissions

M1.2 implements a basic core authentication and authorization foundation.

Current capabilities:

* Session-based login and logout
* CSRF protection for auth forms
* Native PHP password hashing
* User lookup through the core database layer
* Active/inactive user validation
* Basic role and permission checks
* Manual database schema for auth tables
* Protected milestone test route

Current limits:

* No admin dashboard
* No user management UI
* No password reset
* No email verification
* No OAuth or 2FA
* No policy or gate system
* No middleware system
* No ORM or migration runner
* No module permission registration

---

## Admin Shell

M1.4.1 adds a minimal core Admin Shell.

Current capabilities:

* Configurable single-segment admin path through `config/admin.php`
* Default admin path value `admin`, which becomes `/admin` at runtime
* Admin login form served at the same admin path
* CSRF-protected admin login and logout
* Admin access protected by the existing authentication system
* Minimal `admin.access` permission requirement
* Core admin layout under `resources/views/admin`
* Static Dashboard navigation
* Minimal dashboard/status page

The Admin Shell uses the existing include-only `View` renderer and core `resources/views/admin` views. It does not use the frontend Theme System, active frontend theme, `ViewRenderer`, or `ViewResolver`.

Current limits:

* No Content CRUD
* No module management UI
* No theme management UI
* No role or permission UI
* No settings UI
* No analytics
* No editor integration
* No media or image service
* No localization
* No admin theming
* No admin navigation manager
* No middleware system

---

## Module Layer

Modules provide business functionality.

Examples:

```text
Articles
Catalog
Workflow
Assets
Store
```

Each module should remain as self-contained as possible.

A module may contain:

```text
module.json
routes.php

Controllers/
Models/
views/
Services/
Assets/
Migrations/
```

---

## Module Manager

M1.3 introduces a local module manager foundation.

Current goals:

* Discover local modules from `modules/`
* Validate `module.json` metadata
* Register installed modules in the database
* Enable and disable modules
* Uninstall module registrations without deleting module files
* Load routes from enabled modules
* Store module permission metadata separately from core permissions

Current limits:

* No marketplace
* No admin module UI
* No remote package download
* No composer package system
* No migration runner
* No asset publishing
* No theme integration
* No Content module
* No complex dependency resolver
---

## Theme Layer

Themes provide presentation.

A theme may contain:

```text
theme.json

layouts/
partials/
assets/
```

Themes are responsible for:

* Layouts
* Templates
* Styling
* Assets

Themes are not responsible for:

* Database Access
* Business Logic
* Authentication Logic

M1.4 implements the first frontend theme system foundation.

Current capabilities:

* Local theme discovery from `themes/*/theme.json`
* Theme registry in the `themes` database table
* Single active frontend theme
* Theme activation and active-theme guards
* Layout rendering through the active theme
* Core, theme-owned, and module view namespace resolution
* Theme overrides for core and module views
* Controlled active-theme asset serving through `/theme-assets/{theme-id}/{asset-path}`
* Minimal default theme at `themes/default`

Current limits:

* No admin theme support
* No theme marketplace
* No theme installer
* No ZIP upload
* No asset pipeline, bundler, or minifier
* No template engine beyond PHP includes
* No theme settings UI or editor
* No child themes
* No multi-site theme support
* No generic theme hooks

---

## Namespace Strategy

Initial namespace structure:

```text
Copot\Core
Copot\Modules
Copot\Themes
```

Example:

```php
Copot\Core\Application
Copot\Core\Router
Copot\Core\Database
```

---

## Database Strategy

Initial implementation:

```text
PDO
```

The framework should provide a lightweight database layer.

Initial versions should not include:

* ORM
* Active Record
* Repository Framework

These may be evaluated in future milestones.

---

## Configuration Strategy

Configuration should be separated from code.

Planned locations:

```text
config/
.env
```

The framework should support environment-specific configuration.

---

## Deployment Strategy

Primary Target:

* Shared Hosting
* cPanel Hosting

Secondary Target:

* VPS
* Cloud Infrastructure

All architectural decisions should consider compatibility with the primary deployment target first.

---

## Future Expansion Areas

Future milestones may introduce:

* Event System
* Queue System
* API Layer
* Background Jobs
* Package Ecosystem

These features are not part of M1.1 or M1.2.



