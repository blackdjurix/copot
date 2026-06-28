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

## Core Services

Small Core Services provide reusable infrastructure capabilities without becoming feature modules.

Current examples:

* `AdminNavigation` provides request-scope admin navigation items without a database-backed menu manager.
* `Csrf` centralizes POST-body-only CSRF validation and controlled `419` responses.

M1.7 implements Settings as a Core/platform service rather than an optional module.

The Settings components have focused responsibilities:

* `SettingDefinition` describes a known namespace/key, type, default, validation, allowed values, and metadata.
* `SettingsRegistry` owns code-defined definitions and rejects invalid or duplicate identifiers.
* `SettingsRepository` persists raw override rows through PDO.
* `SettingsService` resolves effective values, validates writes, serializes/casts typed values, and provides the public Settings API.

Defaults come from definitions. The `settings` table stores overrides only. Core and module consumers read settings through `Application::settings()` rather than querying the settings table or repository directly.

Dependency direction:

```text
Core Settings
->
Core and Modules
```

Settings must not depend on Content, Taxonomy, Theme, or business modules. Modules may depend on Settings and may own module-specific namespaces in future, but module-specific definitions and UI are outside M1.7. Runtime consumers use effective service values with controlled definition-default fallback when storage or an individual stored override is unusable.

Feature routes should use these services instead of repeating security-sensitive logic manually.

---

## Installer Bootstrap Boundary

M1.8 adds a fresh-install boundary before normal `Application` construction. `public/index.php` loads only the Core autoloader, captures the request, validates installation state through `InstallationState`, and asks `InstallerGate` whether to redirect to `/install`, run the isolated installer bootstrap, block the installer, or continue to `bootstrap/app.php`.

Normal application bootstrap is allowed only when `storage/installed.lock` exists and matches the exact marker contract. A missing marker sends normal requests to `/install`. A malformed marker fails safely and does not allow normal bootstrap or marker overwrite. Once the marker is valid, `/install` returns `404`.

Installer responsibilities are divided across focused Core components:

* `InstallerRequirements` checks the supported PHP/extensions and actual writable filesystem prerequisites.
* `InstallerDatabaseValidator` and `InstallerDatabaseProbe` validate controlled input, server version, connection, and the dedicated empty database.
* `InstallerEnvironmentWriter` persists only the approved database keys through same-directory atomic replacement.
* `InstallerSchemaRunner` executes only the controlled statement format in canonical `database/schema.sql`.
* `InstallerAdministratorSetup` creates the first active administrator, assigns the seeded admin role, and saves initial Settings in one database transaction.
* `InstallerFinalizer` rechecks live state, activates the default theme, enables Content and Taxonomy, and creates the final marker last.
* `InstallationMutex` serializes state-changing workflows with exclusive non-blocking `flock()`.

The installer bootstrap reuses Request, Response, Session/CSRF, Database, Settings, Theme, and Module primitives without constructing the complete normal `Application` prematurely. It provides no upgrade, migration, repair, reset, table-prefix, or destructive cleanup path.

```text
public/index.php
-> InstallationState + InstallerGate
-> uninstalled: bootstrap/installer.php
-> installed: bootstrap/app.php -> Application
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

* No built-in Content CRUD owned by Admin Shell
* No module management UI
* No theme management UI
* No role or permission UI
* No arbitrary settings definition editor
* No analytics
* No editor integration
* No media or image service
* No translation or multilingual UI
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
* No complex dependency resolver
---

## Content Module

M1.5 adds the first publishing foundation as a local module at `modules/content`.

Content is the primary domain concept. Article, Page, News, Video, Gallery, and similar labels are content types or use cases, not separate core architecture models.

Current capabilities:

* `content` database table
* PDO-based content repository
* Simple string content types
* Default content types: `page`, `article`
* Draft, published, and archived statuses
* Admin list, create, edit, publish, draft, and archive workflows
* Frontend route at `/content/{slug}` for published content
* Theme System rendering through `content::show`
* Theme override support through `themes/<active-theme>/views/modules/content/show.php`

The Content Module is not a hardcoded core Article system. It remains a module loaded by the local Module Manager.

Current limits:

* No Editor.js implementation
* No media or image service
* No SEO module
* No comments
* No advanced search
* No revisions or autosave
* No approval workflow
* No Content Workspace

---

## Taxonomy Module

M1.6 adds a reusable Taxonomy Foundation as a local module at `modules/taxonomy`.

Taxonomy is the primary classification domain concept. Category and Tag are taxonomy types, not separate primary architecture models.

Current capabilities:

* `taxonomy_types` database table
* `taxonomy_terms` database table
* `taxonomy_assignments` database table
* Seeded taxonomy types: `category`, `tag`
* Flat admin term management for category and tag terms
* Generic assignment engine using `entity_type` and `entity_id`
* Content integration using `entity_type = content`
* Delete guard that rejects deleting assigned terms

The Taxonomy Module does not replace the Content Module. Content remains responsible for content lifecycle, publishing, and frontend rendering. Taxonomy provides reusable classification that Content can use when the Taxonomy module is enabled.

Current limits:

* No public taxonomy URLs
* No taxonomy archive pages
* No taxonomy type management UI
* No tree UI or drag-drop hierarchy UI
* No SEO taxonomy pages
* No multilingual taxonomy
* No API endpoints
* No search indexing
* No import/export
* No taxonomy custom fields
* No taxonomy media or icon handling

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



