# AGENTS

## Project

**copot**

## Purpose

copot is a modular PHP-based website framework designed for flexible website, content, business, and automation use cases.

M1 Framework Foundation is complete and released as v0.8.0.

The Post-M1 Roadmap Review is complete. M2.1 Admin UI Foundation implementation and verification are complete. The next focus is M2.2 Extensibility Foundation.

---

## Current Phase

### M2.2 Extensibility Foundation

Primary goal:

Prepare and implement the next lean M2 Platform Capability: a minimal, predictable extension boundary for Core and modules.

Current work:

* M2.1 Admin UI Foundation implementation and verification are complete.
* All six M2.1 batches are complete.
* Current focus is M2.2 Extensibility Foundation scope lock and implementation planning.
* Keep the initial extensibility contract synchronous, explicit, and small.
* Defer asynchronous events, persistent event logs, wildcard buses, external APIs, webhooks, and unrelated future capabilities until a concrete dependency requires them.

Latest release: v0.8.0.

The Post-M1 Roadmap Review is complete.

M2.1 targets v0.9.0. Merge, tag, and release remain user-owned Git/GitHub operations.

---

## Core Rules

* Documentation comes before implementation.
* Follow the roadmap in `docs/03_roadmap.md`.
* Follow the architecture in `docs/02_architecture.md`.
* Follow the principles in `docs/01_principles.md`.
* Do not introduce major architecture changes without updating documentation.
* Keep the framework deployable on shared hosting.
* Prefer simple PHP solutions before introducing heavy dependencies.
* Do not turn this project into Laravel-lite unless explicitly instructed.

---

## Execution Workflow Rules

* Use one Codex session per minor milestone by default.
* If a batch becomes unusually large or its working context becomes too heavy, stop at a stable checkpoint and consider continuing in a new Codex session.
* Git and GitHub operations are user-owned.
* The agent must not create or delete branches, commit, push, merge, tag, create releases, or perform repository-history operations unless the user explicitly requests an exception.
* Documentation and instruction files that guide Codex execution are maintained manually by the user.
* The assistant prepares documentation material as targeted replacements, copy-ready sections, patches, or full files when requested.
* Codex must not edit documentation or instruction source-of-truth files unless the user explicitly requests an exception.
* Codex may perform read-only documentation audits, terminology searches, reference checks, and validation when requested.

---

## Approval Rules

* Agent may:

  * read files
  * analyze code
  * review architecture
  * create proposals
  * modify files when explicitly requested

* Agent must request approval before:

  * creating commits
  * amending commits
  * rebasing branches
  * merging branches
  * deleting branches
  * creating tags
  * creating releases
  * pushing to remote repositories
  * deleting files
  * renaming files or folders
  * modifying database schemas
  * introducing new dependencies
  * introducing new frameworks
  * changing roadmap scope
  * changing architecture direction

* If uncertain whether an action has long-term impact or is difficult to reverse, request approval first.

---

## Architecture Protection Rules

* Do not expand milestone scope without approval.
* Do not implement features from future milestones.
* Do not introduce abstractions for hypothetical future needs.
* Do not add dependencies to solve problems that do not yet exist.
* Focus only on the active milestone.
* If a future feature appears necessary, propose it first and wait for approval.

---

## Domain Naming Rules

* Use Content as the future primary domain concept instead of Article.
* Article, Page, News, Video, Gallery, and similar terms are content types or use cases.
* Do not hardcode Article as the primary domain model unless explicitly approved.
* Use Taxonomy as the primary classification domain concept instead of Category.
* Category and Tag are taxonomy types, not separate primary architecture models.
* Internal architecture should use stable domain terminology.
* UI labels may initially mirror internal terminology.
* Future localization may translate or customize UI labels without renaming internal classes, tables, or architecture concepts.
* Do not rename internal concepts only to improve UI wording.
* When naming is uncertain, prefer the term that is most stable for database and code.

---

## Future Platform Capability Strategy

* Editor functionality belongs to the future Editor Framework in M2.
* Editor.js is the leading planned default editor adapter, not the permanent platform contract.
* Content modules must depend on an editor abstraction rather than hardcoding one editor implementation.
* Future image handling must use an Image Service abstraction.
* Browser image-editor candidates may include Cropper.js.
* Server-side image processing must support GD as the baseline and may support Imagick as an optional enhancement.
* Imagick must not be required for the shared-hosting baseline.
* Basic timezone, locale, date-format, and time-format support is already provided by M1.7 Settings Foundation.
* Broader UI and system localization may become a future platform capability.
* Content translation is separate from UI localization and belongs to future Content or multilanguage work.
* A future M2 Branding Foundation owns the Core four-color palette contract, locked semantic mapping, validation, fallback, and consumer contract.
* Advanced color settings and Custom CSS belong to the frontend Theme and future Theme Manager, not the base Branding Foundation.
* Do not implement editor, media, image-processing, translation, or multilingual capability before its milestone is approved.

---

## Permission Strategy

* Use one permission system for core and modules.
* Do not create separate role systems for web/core and modules.
* Role means permission bundle.
* Workflow approval should be permission-driven, not hardcoded by role hierarchy.

---

## Admin Path Strategy

* Admin URL path must be configurable.
* Do not hardcode `/admin` as the only possible admin path.
* Default admin path may be `/admin`.
* Future installer may allow selecting admin path such as `/administrator`, `/backend`, `/dapur`, etc.

## M2.1 Admin UI Foundation Rules

* M2.1 provides reusable Admin UI infrastructure, not a full admin theme or skin system.
* Admin UI must remain independent from the frontend Theme System.
* Admin assets must be directly deployable from the public document root without Node, a bundler, a daemon, or advanced server configuration.
* Admin path validation and URL generation must have one centralized owner.
* Runtime templates must not depend on literal `/admin` fallbacks.
* Admin page and shell rendering must use one centralized contract with common context.
* Shared UI patterns should cover alerts, fields, actions, panels, tables, empty states, focus behavior, and responsive layout.
* Navigation must support stable IDs, explicit ordering, permission-aware visibility, and active-state resolution.
* Initial navigation remains flat. Grouping, nested menus, and icon systems are deferred.
* M2.1 must provide a minimal dashboard-widget registry with stable IDs, permission checks, ordering, and controlled rendering.
* M2.1 must not introduce database-backed dashboard layout, drag-drop widgets, analytics, or the M3 Internal Dashboard.
* M2.1 must not add an admin theme marketplace, user-selectable skins, SPA runtime, CSS framework, JavaScript framework, or frontend build pipeline.
* M2.1 uses internal, contrast-safe Admin UI color tokens and does not read Site Branding.
* Any future Admin UI brand-color integration must remain limited, explicit, and contrast-safe.
* M2.1 must not change Content, Taxonomy, Settings, authentication, or other domain behavior beyond migrating their admin presentation to the shared Admin UI Foundation.
* Public login redesign, installer redesign, localization implementation, Editor, Media, Image, Navigation Manager, and other M2/M3 capabilities remain outside scope.
* Database schema changes and new third-party dependencies are outside scope unless separately approved.

---

## Architecture Classification Model

### Platform Capability

A Platform Capability is reusable infrastructure, a service, a registry, an adapter contract, or a shared runtime facility.

A Platform Capability:

* is not a business-specific domain;
* does not require a standalone management UI;
* may be used by multiple modules;
* may provide APIs, registries, adapters, lifecycle hooks, resolution, storage, or shared processing;
* belongs to M2.

Planned examples include:

* Admin UI Foundation
* Branding Foundation
* Event Foundation
* Editor Framework
* Media Foundation
* Image Service
* Navigation Foundation
* Search Foundation
* Notification Foundation
* Workflow / Automation Foundation

### Core Module

A Core Module is a first-party reusable module that provides user-facing or administrative management functionality without representing a specific business domain.

A Core Module:

* is built on M1 infrastructure and M2 Platform Capabilities;
* follows the existing Module Manager lifecycle;
* may become a dependency of other modules;
* remains modular even when included in the standard copot distribution;
* belongs to M3.

Planned examples include:

* Users & Access
* Settings Manager
* Media Library
* Theme Manager
* Content Manager / Workspace
* Taxonomy Manager
* Navigation Manager
* Internal Dashboard
* Redirect Manager
* Form Manager

The existing Content and Taxonomy modules remain the same modules. Future “Manager” naming describes their management-UI evolution, not duplicate replacement modules.

Theme Manager is distinct from the existing Theme System lifecycle services.

Settings Manager is distinct from the existing SettingsService persistence and retrieval foundation.

### Business / Application Module

A Business or Application Module implements a specific domain or use case.

Examples include:

* Catalog
* Property
* Booking
* CRM
* Inventory
* Project Management

Business/Application Modules belong to M4 or later domain phases and are not universal copot requirements.

Commerce remains a separate M5 phase because its transactional and integration requirements are substantial.


---

## Settings Rules

* Settings provides core persistence, retrieval, validation, and type casting for known setting definitions.
* M1.7 covers global/site settings and basic localization settings only.
* Database rows store overrides; defaults remain defined in code or configuration.
* Modules may use their own settings namespaces in future, but remain responsible for their definitions and UI.
* The future M3 Settings Manager may edit the four Core palette values defined by Branding Foundation, but must not expose the locked semantic mapping as Site Settings.
* Settings must not depend on Content, Taxonomy, Theme, or business modules.
* Do not store secrets, passwords, SMTP credentials, API tokens, or environment configuration in Settings.
* The M1.7 Admin Settings UI must not allow arbitrary namespace or key creation.

---

## Installer Rules

* M1.8 provides a web installer for fresh installations on PHP/MySQL shared hosting.
* The installer URL is `/install`.
* The minimum supported PHP runtime is PHP 8.2. PHP 8.2 is accepted with an aging warning, PHP 8.3 or newer is recommended, and PHP 8.4 is preferred. Newer compatible PHP releases must not be rejected solely for being newer.
* Installation requires a dedicated empty database and does not support table prefixes.
* Supported database baselines are MySQL 8.0+ and MariaDB 10.4.32+. MariaDB releases before 10.6 are accepted with a legacy/end-of-life warning. Production recommendations are MySQL 8.4 LTS+ or MariaDB 10.11+, with MariaDB 11.4 LTS preferred.
* The installer must check runtime requirements and writable locations before changing persistent state.
* Database credentials must be validated with a connection test and persisted through the existing root `.env` pattern.
* The installer must execute the canonical `database/schema.sql` with a runner designed only for copot's schema format, not a universal SQL parser.
* The first administrator must be created through a dedicated installer workflow service that reuses existing password, user, role, and permission primitives. The password requires at least 10 characters and confirmation.
* The installer must save initial site/localization values through `SettingsService`, activate the default theme, and enable the Content and Taxonomy modules.
* The final installation marker is `storage/installed.lock`. Its exact JSON contract contains only `installed_at` as an ISO-8601 timestamp and `version` as the installed copot version; it contains no status flag, schema version, or secrets.
* The final installation marker may be created only after every required setup step succeeds.
* Concurrent installation execution must use an exclusive non-blocking `flock`; temporary lock-file presence alone is not installation state.
* Installer step presentation must be derived from live schema/administrator state. `/install?step=database` may request the database form without persisting step state or weakening empty-database and duplicate-administrator guards.
* A client-side successful database-test state is UX only. Database installation must always repeat server-side requirements, CSRF, connection/version, empty-database, mutex, environment-write, and schema checks.
* Once the installation lock exists, the installer must be unavailable.
* Public installer errors must not expose database credentials, SQL, filesystem paths, or stack traces.
* M1.8 does not include a CLI installer, upgrade or migration engine, repair/reset flow, table prefix, multisite, module/theme selection UI, marketplace, infrastructure provisioning, or M2 work.
* M1.8 batch order is: installation state/gate; requirements/database validation; atomic config/schema; first admin/settings; baseline theme/modules/final lock; installer UI/session; failure/security/shared-hosting hardening; documentation/release prep.
* M1.8 is complete and released as v0.8.0.
* Installer behavior remains frozen unless a future approved milestone explicitly changes it.

---

## Architecture Rules

* Core infrastructure handles bootstrap, configuration, routing, persistence access, security primitives, and lifecycle management.
* Platform Capabilities provide reusable services, contracts, registries, adapters, and shared runtime behavior.
* Core Modules provide reusable user-facing and administrative functionality.
* Business/Application Modules provide domain-specific functionality.
* Themes handle presentation only.
* Themes must not contain business logic.
* Themes must not directly access the database.
* Modules should remain independent whenever practical.
* Database access should go through the core database layer.
* Initial database access uses PDO.
* Do not introduce an ORM without an approved milestone and documented need.

---

## Planned Core Responsibilities

The core system may include:

* Application bootstrap
* Configuration loader
* Router
* Database connection
* Module loader
* Theme loader
* Request handling
* Response rendering

---

## Planned Folder Direction

Future implementation may use this structure:

```text
app/
bootstrap/
config/
database/
modules/
themes/
public/
resources/
routes/
storage/
tests/
docs/
```

Do not create implementation folders before the milestone requires them.

---

## Module Rules

A module may contain:

```text
module.json
routes.php
Controllers/
Models/
views/
Services/
migrations/
assets/
```

Each module should define its own metadata in `module.json`.

Modules should not assume another module exists unless dependency rules are documented.

Modules may be classified as Core Modules or Business/Application Modules.

Core Modules provide reusable management functionality and belong to M3.

Business/Application Modules implement specific domains and belong to M4 or later.

A Platform Capability is not automatically a module. Shared services, registries, adapters, and runtime infrastructure may remain in the platform layer without a standalone module UI.

---

## Taxonomy Rules

* M1.6 is Taxonomy Foundation, not a category-only module.
* Default taxonomy types for M1.6 are `category` and `tag`.
* M1.6 must not add public taxonomy URLs such as `/category/{slug}` or `/tag/{slug}`.
* M1.6 must not add taxonomy type management UI.
* M1.6 must not add tree UI, drag-drop hierarchy UI, SEO taxonomy pages, multilingual taxonomy, API endpoints, search indexing, import/export, taxonomy custom fields, or taxonomy media/icon handling.
* Taxonomy assignments should use a generic `entity_type` domain key, but M1.6 implementation should only use `content` unless explicitly approved.

---

## Theme Rules

A theme may contain:

```text
theme.json
layouts/
views/
partials/
assets/
```

Themes are responsible for layout and visual presentation.

Themes may ignore the Core brand palette, consume it with the default mapping, or provide scoped palette and semantic-mapping overrides. Theme overrides must not write back to the Core palette. Advanced color settings and Custom CSS remain Theme/Theme Manager responsibilities.

Themes must not contain business logic.
Themes must not directly access the service container, database, authentication, or module lifecycle services.

---

## M1.1 Scope

### Included

* Folder structure
* Config loader
* Autoloader
* Router
* Database connection
* Application bootstrap
* Default page rendering

### Excluded

* Full authentication system
* Full module manager
* Full theme marketplace
* Content CMS
* Admin dashboard
* Online store
* Workflow automation
* Custom ORM

---

## Coding Style

* Use PHP 8.x.
* Prefer clear class names.
* Prefer readable code over clever code.
* Use namespaces under `Copot`.
* Keep files focused and small.
* Avoid hidden magic.
* Avoid unnecessary dependencies.
* Avoid premature abstraction.

---

## Documentation Rules

When adding or changing major behavior, update the relevant documentation:

* `docs/00_vision.md`
* `docs/01_principles.md`
* `docs/02_architecture.md`
* `docs/03_roadmap.md`
* `docs/04_module_system.md`
* `docs/05_theme_system.md`
* `docs/07_taxonomy_system.md`
* `docs/08_settings_system.md`
* `docs/09_installer_system.md`
* `docs/10_admin_ui_foundation.md`
* `docs/11_branding_foundation.md`

Documentation and instruction files are user-maintained unless the user explicitly authorizes Codex to edit them.

Codex may audit these files read-only and report inconsistencies, stale references, or broken documentation links.

Update `CHANGELOG.md` for meaningful project changes.

---

## Git Rules

Git and GitHub operations are performed by the user.

Codex must not perform the following unless the user explicitly requests an exception:

* create or delete branches;
* commit or amend commits;
* rebase;
* merge;
* push;
* create or delete tags;
* create releases;
* delete remote branches.

Codex may inspect Git state and report:

* current branch;
* working-tree status;
* diff summary;
* untracked or suspicious files;
* merge or tag consistency;
* validation findings.

Commit messages should remain clear and milestone-aware.

---

## Do Not Do

* Do not add Laravel unless explicitly instructed.
* Do not add Composer packages without a documented reason.
* Do not add frontend build tooling unless required.
* Do not mix module logic into themes.
* Do not put secrets into the repository.
* Do not commit `.env`.
* Do not commit `vendor/`.
* Do not commit `node_modules/`.
* Do not rewrite the roadmap without preserving intent.
* Do not skip documentation because "the code is obvious".

---

## Preferred Development Order

1. Lock scope and architecture decisions.
2. Prepare documentation changes.
3. User updates documentation and instruction files manually.
4. Confirm milestone scope.
5. Codex audits targeted implementation files.
6. Add the minimal working implementation.
7. Run automated tests and validation.
8. Perform manual testing.
9. User handles Git and GitHub operations.
10. Merge only after review and approval.

---

## Current Immediate Goal

M1 Framework Foundation is complete and released as v0.8.0.

The Post-M1 Roadmap Review is complete.

M2.1 Admin UI Foundation implementation and verification are complete across all six batches.

The current work is M2.2 Extensibility Foundation.

The immediate goal is to:

* lock a minimal synchronous event and listener contract;
* define controlled Core and module extension points;
* preserve predictable failure handling and request-scope behavior;
* avoid asynchronous processing, event persistence, external API/webhook scope, and speculative abstractions;
* keep deferred M2 capabilities available for recall only when a concrete dependency appears.

M2.1 targets v0.9.0. Merge, tag, and release remain pending user-owned operations.
