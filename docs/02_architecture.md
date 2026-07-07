# Copot Architecture

## Architecture Overview

copot is organized into four architectural layers:

```text
Core Infrastructure
->
Platform Capabilities
->
Modules
->
Themes
```

The layers have different responsibilities:

* Core Infrastructure provides the minimum runtime, lifecycle, security, persistence, and extension foundations.
* Platform Capabilities provide reusable services, contracts, registries, adapters, and shared processing.
* Modules provide reusable management functionality or domain-specific application behavior.
* Themes provide frontend presentation only.

Modules are further classified as:

```text
Core Modules
Business / Application Modules
```

Core Modules are reusable first-party management modules.

Business/Application Modules implement specific domains or use cases.

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

## Platform Capability Layer

Platform Capabilities provide reusable infrastructure above the minimum Core runtime.

A Platform Capability may provide:

* a service;
* a registry;
* an adapter interface;
* lifecycle hooks;
* resolution logic;
* storage abstraction;
* shared processing;
* extension points.

A Platform Capability does not need a standalone management UI and must not represent a business-specific domain.

Planned M2 Platform Capabilities are:

* Admin UI Foundation
* Extensibility Foundation
* Minimal Site Capabilities
* Editor Framework
* Media Foundation
* Image Service
* Navigation Foundation
* Search Foundation
* Notification Foundation
* Workflow / Automation Foundation

Dependency direction:

```text
Core Infrastructure
->
Platform Capabilities
->
Core Modules
->
Business / Application Modules
```

### Important Naming Boundaries

The following names refer to different architectural responsibilities:

```text
Theme System
!=
Theme Manager
```

The Theme System provides discovery, validation, registry, activation, view resolution, and rendering lifecycle behavior.

Theme Manager is a future M3 Core Module that provides administrative theme listing, activation controls, validation status, and theme-settings UI.

```text
SettingsService
!=
Settings Manager
```

SettingsService provides definitions, persistence, retrieval, type casting, and validation.

Settings Manager is a future M3 Core Module that expands administrator-facing settings management on top of the existing SettingsService foundation.

The minimal Admin Settings UI introduced in M1.7 remains part of the M1 platform foundation and is not itself the future Settings Manager.

The future Settings Manager may organize registered settings sections, render reusable setting field types, and support module-contributed settings without allowing arbitrary unregistered keys.

```text
Minimal Site Capabilities
!=
Settings Manager
!=
Theme Manager
```

M2.3 Minimal Site Capabilities defines only the site-level formatting boundary and the Core site-identity contract for Site Name, optional Tagline, optional Logo, and optional Favicon. The existing Admin Settings surface persists registered values, but it does not become the future M3 Settings Manager. Themes consume a controlled read-only branding value and retain presentation ownership without direct Settings or database access. Batches 1–6 are complete, the unified regression and manual verification pass, and M2.3 is released as v0.11.0.

The separate Core four-color palette and semantic-mapping proposal in `docs/11_branding_foundation.md` remains deferred. Advanced theme colors and Custom CSS remain future Theme Manager concerns.

```text
Media Foundation + Image Service
!=
Media Library
```

Media Foundation and Image Service provide storage, metadata, references, delivery, and image processing.

Media Library is a future M3 Core Module that provides upload, browsing, selection, metadata management, usage visibility, and basic image-editing UI.

---

## Core Services

Small Core Services provide reusable infrastructure capabilities without becoming feature modules.

Current examples:

* `AdminNavigation` provides request-scope admin navigation items without a database-backed menu manager.
* `Csrf` centralizes POST-body-only CSRF validation and controlled `419` responses.
* `EventDispatcher` defines explicit synchronous event registration and object-payload dispatch.
* `SynchronousEventDispatcher` stores request-scoped listeners in registration order and propagates listener failures unchanged.

`Application` owns one dispatcher instance per request. Enabled modules may contribute listeners through one optional metadata-declared `listeners.php` file loaded before enabled module routes. Contribution files are trusted local code with `$app` available through include scope; controlled loading is not sandboxing or restricted service access.

The foundation is proven end to end with controlled temporary fixtures. Fixture event names are not production API. Production events remain demand-driven and may be introduced only when a consumer milestone has one real caller/listener pair. M2.2 therefore does not add speculative lifecycle hooks.

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

M2.3 extends this foundation without changing the dependency direction:

```text
Core Settings
->
Site Formatter + Site Branding + Focused Site Asset Storage
->
Core and Theme presentation consumers
```

The formatter uses an explicit configured `DateTimeZone` and deterministic `en_US`/`id_ID` conventions without depending on server locale packages or requiring `ext-intl`. Site identity assets remain outside `public/` and are delivered only through fixed Logo/Favicon routes that resolve the currently active validated descriptor. The focused storage boundary is not a Media Library or generic upload service.

M2.3 Batch 2 implements `SiteFormatter` as one request-scoped object owned by `Application`. It formats presentation dates, times, date-times, integers, and decimals, falls back from unsupported locales to `en_US`, and leaves database, lock, and protocol timestamps on their existing machine contracts.

M2.3 Batch 3 registers optional strictly validated `site.logo` and `site.favicon` JSON descriptors and adds one request-scoped `SiteBranding` snapshot. The snapshot exposes effective Site Name and Tagline without exposing descriptors, filenames, Settings, or database access.

M2.3 Batch 4 adds request-scoped `SiteAssetStorage` for the fixed Logo and Favicon slots. It validates content MIME, image structure, dimensions, size, generated filenames, containment, and symlink boundaries; persists active descriptors through `SettingsService`; provides safe replacement/removal ordering; and serves only `/site-assets/logo` and `/site-assets/favicon` with controlled headers. `SiteBranding` receives only the stable URL when the active descriptor resolves to an available safe file. Batch 5 completed the permission- and CSRF-protected HTTP upload/removal controls and active-Theme integration.

Complete M2.3 ownership, security, storage, batch, and acceptance contracts are defined in `docs/13_minimal_site_capabilities.md`.

Feature routes should use these services instead of repeating security-sensitive logic manually.

---

## Platform Hardening Boundary

M2.4 Platform Hardening implementation is complete. Batch 2 establishes minimal diagnostics, Batch 3 adds sanitized application boundaries and exact owned-buffer cleanup, Batch 4 adds eligible Admin in-shell recovery, Batch 5 hardens session deployment configuration plus Site Asset filesystem observability, and Batch 6 adds the chained M2.4 regression gate plus final release-readiness evidence without changing Router, Response, auth, permission, CSRF, or storage ownership. This closes the lean M2 Platform Capabilities implementation phase; M3 has not started.

The planned hardening direction is:

```text
Request entry
-> early installation/bootstrap decision
-> normal Application bootstrap
-> route/contribution registration
-> request dispatch and rendering
-> controlled Response
```

Unexpected failures that escape capability-specific handling must terminate at the smallest available boundary. A normal application boundary may use application-owned services that were constructed successfully. An early bootstrap boundary must remain standalone and must not rebuild dependencies that already failed.

Batch 3 uses three narrow boundaries:

* a fixed pre-autoload emergency boundary that can return only a generic `500` without Diagnostics or a reference;
* a post-autoload boundary around request capture, installation routing, Installer/normal bootstrap, route/module registration, and response preparation, using a standalone local Diagnostics instance;
* an `Application::run()` dispatch boundary using the request-scoped Application Diagnostics instance.

No global exception, error, or shutdown-handler framework is registered. `Response::send()` remains outside recovery because a second response cannot safely replace bytes already sent.

The error taxonomy distinguishes:

* expected request, authorization, missing-resource, CSRF, and validation outcomes (`403`, `404`, `419`, `422`, and related controlled statuses);
* controlled availability failures such as unavailable required storage or database access (`503`);
* unexpected application failures (`500` with a safe reference and best-effort diagnostic record);
* failures before the normal Application and Admin services are available (standalone sanitized response).

Unexpected failures default to `500`. `503` is available only when a caller positively identifies an availability failure through an explicit controlled status. Batch 3 does not infer availability by parsing exception messages and does not map every `PDOException` to `503`.

The response boundary is always sanitized. `APP_DEBUG` does not authorize raw exception rendering. Responses exclude raw exceptions, warnings, traces, paths, SQL, credentials, environment data, request bodies, tokens, cookies, uploaded client filenames, and partial failed-template output.

Plain values remain escaped for their output context. The existing Admin and Theme page-content slots are trusted rendered fragments owned only by controlled internal renderers; they are not general string escape bypasses.

`View`, `ViewRenderer`, Content, Taxonomy, Example, bootstrap, and dispatch rendering paths record their caller output-buffer level. They must close every owned buffer back to that exact level on failure, reject unbalanced nested buffers, and reject direct output outside the returned response. They must not close a caller-owned buffer. Existing module renderers retain their focused local structure rather than being refactored into a new abstraction.

Authenticated Admin errors render through the existing Admin Shell only when Application construction, session/authentication state, current-user resolution, and `AdminPageRenderer` remain safely available. The original status is preserved. Earlier failures use a standalone sanitized response. This rule does not weaken permission checks or redesign Admin.

Batch 2 adds one request-scoped `Diagnostics` instance per `Application`. The service is side-effect free during construction and writes synchronous append-locked JSON lines only to `storage/logs/copot.log`. Unexpected reports use a controlled summary, exception class, project-relative source location when available, and explicit allowlisted scalar context. Raw `Throwable::getMessage()` output is never read or stored. A random opaque `ERR-` reference is returned only after its record is appended successfully; warnings return only success/failure and receive no reference.

Diagnostics must not log credentials, DSNs, SQL, passwords, hashes, CSRF/session/auth values, cookies, environment contents, request bodies, arbitrary query values, client filenames, full server arrays, stack arguments, or unredacted absolute paths. Missing, unsafe, symlinked, or unwritable destinations return `null`/`false` without throwing, emitting output, creating the directory, calling a secondary sink, or recursively logging the logging failure.

Batch 2 does not register an exception/error/shutdown handler. Batch 3 integrates Diagnostics at the post-autoload bootstrap and Application dispatch boundaries while preserving the Batch 2 sink, reference, redaction, and failure contracts.

Filesystem ownership remains capability-specific. M2.4 does not add a generic storage abstraction. Missing, unreadable, unwritable, symlinked, partial-write, rename, read, and cleanup failures remain controlled and do not emit warnings into responses. Batch 5 passes the existing request-scoped Diagnostics instance into `SiteAssetStorage`, records material read/cleanup degradation as warning records without references or raw paths, and suppresses filesystem warnings at the capability boundary. The existing Site Asset ordering remains authoritative: failed replacement preserves the previous active asset, while cleanup after a persisted replacement/removal is best-effort and may leave an unreachable orphan without adding a worker. HTTPS deployments enable the existing Secure session cookie via `SESSION_SECURE=true`; HttpOnly and the approved SameSite baseline remain unchanged.

Complete M2.4 scope, non-goals, error and redaction contracts, batch plan, acceptance criteria, deployment checklist, and risks are defined in `docs/14_platform_hardening.md`.

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

The current Admin Shell is M1 infrastructure.

The future Admin UI Foundation in M2 will provide reusable design tokens, layout components, form patterns, table patterns, extension slots, and dashboard/widget registration.

A full admin theme or skin system is not part of the initial Admin UI Foundation scope.

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

Modules provide reusable user-facing functionality or domain-specific application behavior.

Modules are classified as either Core Modules or Business/Application Modules.

### Core Modules

Core Modules are first-party reusable modules that provide general management functionality.

They:

* are not tied to one business domain;
* are built on Core Infrastructure and Platform Capabilities;
* follow the normal Module Manager lifecycle;
* may become dependencies of other modules;
* belong to M3.

Planned examples:

```text
Users & Access
Settings Manager
Media Library
Theme Manager
Content Manager / Workspace
Taxonomy Manager
Navigation Manager
Internal Dashboard
Redirect Manager
Form Manager
```

The existing Content and Taxonomy modules remain the same modules. Their future Manager or Workspace naming describes expanded management UI and capability, not replacement modules.

### Business / Application Modules

Business/Application Modules implement specific domains or use cases.

They:

* depend on shared Platform Capabilities and Core Modules;
* are not required by every copot installation;
* belong to M4 or later domain phases.

Examples:

```text
Catalog
Property
Booking
CRM
Inventory
Project Management
```

Commerce is treated as a dedicated M5 phase because orders, checkout, payment integrations, and transactional state require a separate scope.

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
* No expanded Content Manager / Workspace

The future Content Manager / Workspace is an M3 evolution of the existing Content module. It is not a separate replacement module.

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

Under M2.3, Themes may render the controlled Site Name, Tagline, Logo URL, and Favicon URL supplied by Core. Themes must not query Settings, the database, or site-asset storage directly. Empty optional values have explicit fallbacks.

The separate palette and semantic-mapping proposal remains deferred. Advanced component colors and Custom CSS remain future Theme/Theme Manager capabilities.

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

M2.4 release-readiness requires:

* `public/` as the web document root;
* private `.env`, source, schema, storage, and log paths;
* production `display_errors=Off` with host-level logging for failures before userland handling;
* minimum necessary PHP-user write access for existing storage and the private diagnostic destination;
* HTTPS-capable Secure session cookies with the existing HttpOnly and approved SameSite behavior;
* PHP 8.2+ and the existing extension contract;
* fail-closed Fileinfo-dependent uploads;
* the configured Admin path and shared-hosting routing remaining authoritative;
* no Node process, daemon, queue, worker, scheduler, external service, or advanced server module.

---

## Future Expansion Areas

Future phases are organized as:

```text
M1 = Framework Foundation
M2 = Platform Capabilities
M3 = Core Modules
M4 = Business / Application Modules
M5 = Commerce
M6 = Ecosystem
```

M2 may introduce:

* Admin UI Foundation
* Extensibility Foundation
* Minimal Site Capabilities
* Platform Hardening
* Editor Framework
* Media Foundation
* Image Service
* Navigation Foundation
* Search Foundation
* Notification Foundation
* Workflow / Automation Foundation

The following are explicitly deferred until a concrete requirement exists:

* Queue Foundation
* General API Foundation
* Background-job infrastructure
* Remote package distribution
* Marketplace infrastructure

Generic “Asset Management Foundation” is not used as an M2 milestone because the term is ambiguous.

The architecture distinguishes:

* Media Foundation for uploaded files, metadata, storage, references, variants, and delivery;
* future Digital Asset Management for advanced collections, ownership, approval, and lifecycle use cases;
* physical or business asset management as an M4 Business/Application domain.
