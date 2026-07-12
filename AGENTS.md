# AGENTS

## Project

**copot**

## Purpose

copot is a modular PHP-based website framework designed for flexible website, content, business, and automation use cases.

M1 Framework Foundation is complete and released as v0.8.0.

The Post-M1 Roadmap Review is complete. M2 Platform Capabilities and Post-M2 Distribution & Release Preparation are complete. Copot v0.12.0 is the current stable Webcore baseline. M3 Preparation is complete and M3 Core Modules is the active phase.

---

## Current Phase

### M3 Core Modules

Current milestone: M3.1 Users & Access.

Current work: M3.1 Batch 5 — Documentation Sync + Completion Audit.

Primary goal:

Close M3.1 after its five approved batches without entering M3.2 or running the separate Post-M3.1 Roadmap Sync checkpoint.

Current state:

* M3 Prep Stage 1 Governance + Architecture Lock is complete.
* M3 Prep Stage 2 M3 Sequencing Lock is complete.
* M3 Prep Stage 3 Final Review + Entry Audit is complete, and M3 Prep is closed.
* The v0.12.0 Webcore baseline remains maintenance-only and M3 development remains module-first.
* The approved M3.1-M3.11 sequence, 59-batch planning envelope, risk labels, Sequence Change Rule, Parallelization Rule, and Just-in-Time Batch Lock Rule remain locked.
* M3.1 Users & Access is complete on its approved milestone branch, pending the user-owned commit, push, and merge workflow.
* M3.1 retains the exact five-batch structure locked during M3 Prep.
* M3.1 Batches 1–5 are complete.
* Users, Roles, user-role assignments, role-permission assignments, security/integration hardening, manual Admin verification, and completion validation pass.
* Focused M3.1 Batches 1–4 provide 487 assertions; the access-denied recovery regression adds 17 assertions, for 504 focused assertions total.
* The complete M2.4 unified platform regression chain passes.
* M3.2 is not active. Post-M3.1 Roadmap Sync is the next checkpoint after M3.1 is committed, pushed, and merged to `main`.
* The post-v0.12.0 Core freeze remains in force.

Latest release: v0.12.0.

The Post-M1 Roadmap Review is complete.

M2.1 is complete and released as v0.9.0.

M2.2 is complete and released as v0.10.0.

M2.3 is complete and released as v0.11.0.

M2.4 Platform Hardening and Post-M2 Distribution & Release Preparation are complete and released in v0.12.0.

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

### Local Runtime Mirror

* Codex and developers work only in a local Git clone; a local path such as `C:\Git\copot` is an example, not a project architecture requirement.
* A XAMPP htdocs copy is a runtime mirror, not an active Git worktree.
* Synchronization is one-way from the Git clone to the runtime mirror through `tools/sync-local-xampp.ps1`.
* Never edit source, run commits, or copy changes back from the runtime mirror.
* GitHub remains the source of truth between machines, and every machine uses its own local clone.

* Use one Codex session per minor milestone by default.
* If a batch becomes unusually large or its working context becomes too heavy, stop at a stable checkpoint and consider continuing in a new Codex session.
* Git and GitHub operations are user-owned.
* The agent must not create or delete branches, commit, push, merge, tag, create releases, or perform repository-history operations unless the user explicitly requests an exception.
* Documentation and instruction files that guide Codex execution are maintained manually by the user.
* Codex instructions must be usage-friendly: rely on repository documentation as source of truth and include only the target, required preparation, scope limits, reporting, testing, and explicit Git permission.
* Before coding, testing, or Git operations, Codex must verify the repository root, target branch, current HEAD, and worktree status. If they do not match the instruction or contain unknown changes, stop and report instead of improvising.
* Planning and implementation are separate approval gates when requested; an audit-only task must stop after reporting its plan.
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

### Post-v0.12.0 Webcore Freeze Rules

* Treat the v0.12.0 Webcore as a stable baseline.
* Webcore changes are allowed only for bug fixes, security fixes, compatibility work, runtime upgrades, performance improvements, extension-point corrections, architectural corrections, or explicitly approved generic platform capabilities.
* Do not add module-specific business logic, module-specific schema, module-specific UI, module-specific workflow, or single-module storage behavior to Webcore.
* Solve module requirements inside the module first.
* Before proposing a Core change, verify in order whether the need can be solved by module-local design, an existing public service, a registry, an event/listener pair with a real consumer, or an existing extension point.
* A remaining Core change proposal must be generic, reusable, justified by a concrete dependency, and handled as explicit Core maintenance or platform-capability work rather than hidden inside a module milestone.
* Do not implement Navigation, Media, or other M3 capability work during M3 Prep before the approved M3 implementation entry has passed.

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
* M2.3 owns only the lean site-identity contract for Site Name, optional Tagline, Logo, and Favicon.
* The separate Core four-color palette and semantic-mapping proposal in `docs/11_branding_foundation.md` remains deferred and is not an M2.3 acceptance requirement.
* Advanced color settings and Custom CSS belong to the frontend Theme and future Theme Manager.
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

## M2.2 Extensibility Foundation Rules

* M2.2 provides a minimal synchronous extension boundary for Core and enabled modules.
* The initial contract is request-scoped and in-process only.
* Event routing uses stable lowercase dotted string names with object payloads.
* Listener execution follows registration order only; M2.2 does not add listener priority.
* Dispatch with no listeners is a successful no-op.
* Duplicate explicit registrations are allowed and execute independently.
* Events must use explicit stable names or explicit event classes approved by the implementation design; do not add wildcard matching.
* Listener registration must be explicit and deterministic.
* Listener priority may be introduced only where a concrete ordering requirement exists.
* Dispatch must preserve predictable failure behavior. Exceptions must not be silently swallowed.
* Event payloads must be small, documented, and must not expose the application container as an escape hatch.
* Core must own the dispatcher contract and runtime wiring.
* Modules may contribute listeners only through one optional dedicated `listeners.php` file declared by module metadata.
* Listener contribution is controlled, but listener code remains trusted local application code and is not sandboxed.
* The optional listener file may access `$app` through include scope, matching existing trusted module route wiring.
* Disabled modules must not contribute listeners.
* Extension points must correspond to current Core or module lifecycle needs. Do not add speculative hooks.
* Controlled temporary fixture coverage is sufficient to prove the foundation end to end; fixture event names are not production API.
* First Production Consumer Integration is deferred until a later milestone has one real caller/listener pair with a narrow payload and safe transaction boundary.
* Production events are demand-driven and must not be added merely to complete M2.2.
* M2.2 must not introduce asynchronous execution, queues, scheduler infrastructure, event persistence, replay, wildcard buses, distributed messaging, external APIs, or webhooks.
* M2.2 must not rewrite the service container, Module Manager, Router, or application bootstrap.
* M2.2 must not add a generic plugin framework, package marketplace, or user-facing management UI.
* Database schema changes and new third-party dependencies are outside scope unless separately approved.
* Detailed scope, architecture, batches, and acceptance criteria are defined in `docs/12_extensibility_foundation.md`.

---

## M2.3 Minimal Site Capabilities Rules

* M2.3 is a lean platform capability, not a Media Library, multilingual system, or Branding Manager.
* Reuse the existing `SettingsService` definitions for Site Name, Tagline, Locale, Timezone, Date Format, and Time Format.
* Locale and timezone remain site-level only. Per-user locale/timezone and multilingual content are deferred.
* Formatting must use one explicit Core boundary. Views and new runtime consumers must not introduce scattered date, time, or number formatting.
* The site timezone must be applied explicitly; server timezone must not silently determine display output.
* The M2.3 formatter must remain deterministic without requiring `ext-intl`. Extension availability must not change the initial supported output contract.
* M2.3 site identity consists only of Site Name, optional Tagline, optional Logo, and optional Favicon.
* Empty Tagline, Logo, and Favicon values are valid. Site Name falls back to its registered non-empty Core default.
* Branding consumers receive a controlled site-branding contract; themes must not query Settings or the database directly.
* The separate four-color palette and semantic-mapping proposal remains deferred and is not part of M2.3.
* Uploaded assets are limited to the Logo and Favicon slots. Arbitrary file uploads and generic asset browsing are forbidden.
* Uploads must fail closed with explicit size and MIME allowlists, canonical generated filenames, path containment, and generic public errors.
* SVG upload is excluded from M2.3.
* Site identity files remain outside the public document root and are exposed only through controlled stable public URLs.
* Replacement must make the new file active only after successful validation and persistence. Removal must clear the active reference even if later orphan cleanup cannot complete.
* Raw filesystem paths, client filenames, stack traces, and sensitive runtime details must not reach public or Admin responses.
* Shared-hosting compatibility requires synchronous request-local PHP and local filesystem operations only; no daemon, queue, scheduler, worker, cloud storage, or new dependency.
* Do not rewrite Settings, Theme, Router, Module Manager, or the service architecture for M2.3.
* Do not create a production event merely to exercise the M2.2 dispatcher. Record a candidate only when a real caller and listener both exist.
* Detailed scope, architecture, batches, and acceptance criteria are defined in `docs/13_minimal_site_capabilities.md`.

---

## M2.4 Platform Hardening Rules

* M2.4 is a hardening and release-readiness milestone, not a new user-facing manager or product capability.
* Batch 1 is documentation-only. Do not add runtime implementation until Batch 2 is separately approved.
* Unexpected bootstrap, dispatch, and rendering failures must end at a controlled sanitized application boundary.
* Public and Admin responses must not expose raw exception messages, warnings, stack traces, absolute paths, SQL, credentials, environment data, request bodies, tokens, cookies, or uploaded client filenames.
* `APP_DEBUG` must not enable raw exception rendering in HTTP responses.
* Authenticated Admin errors should render inside the existing Admin Shell only when normal application, session, authentication, user, and renderer state remain safely available.
* Early bootstrap, login, or renderer failures must use a minimal standalone sanitized response rather than recursively rebuilding the failed Admin path.
* Pre-autoload failures use a fixed emergency `500` without Diagnostics. Post-autoload bootstrap and dispatch failures use Diagnostics and expose a reference only after logging succeeds.
* Unexpected failures default to `500`. `503` requires an explicit positively identified availability condition and must not be inferred by parsing raw exception messages or by broadly classifying every `PDOException`.
* Application and renderer boundaries must clean every buffer they own back to the exact caller level and reject direct or unbalanced output.
* Logging must remain a small local request-synchronous diagnostic boundary outside the public document root.
* Logging context must be explicit and allowlisted. Arbitrary request, environment, server, exception, object, or array dumps are forbidden.
* Raw `Throwable::getMessage()` output must not be stored. Diagnostics records use controlled summaries, exception class, and project-relative source location only.
* Error references use random opaque values and may be returned only after the corresponding local append succeeds. Warnings do not receive references.
* Logging failure must be best-effort, non-recursive, and must not replace the intended sanitized response.
* Storage and filesystem failures must fail closed, suppress response leakage, preserve existing active state where the capability contract requires it, and record only redacted diagnostics where useful.
* Site Asset cleanup may remain best-effort and may leave an unreachable orphan; do not add a cleanup worker, queue, scheduler, or Media Library.
* Production deployment must retain `public/` as the document root, `display_errors=Off`, private writable storage/logs, and HTTPS-capable Secure session cookies.
* M2.4 must not add a database change, dependency, Admin redesign, logging framework, observability platform, external service, queue, worker, scheduler, global rate limiter, storage abstraction, or Media Library.
* M2.4 completion requires a unified regression gate across focused M2.4 coverage and the existing M2.3/M2.2/M2.1 chain.
* Detailed scope, architecture, error taxonomy, redaction rules, batches, risks, and acceptance criteria are defined in `docs/14_platform_hardening.md`.

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
* Extensibility Foundation
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
* M2.3 may add only the registered internal references required for the Logo and Favicon slots; it must not turn Settings into a generic file store.
* The future M3 Settings Manager and the deferred Core palette proposal remain outside M2.3.
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

Module ownership should remain local where applicable:

* routes;
* permissions;
* services;
* repositories;
* migrations and schema ownership;
* Admin views;
* module assets;
* module tests;
* module version and changelog metadata where an independent lifecycle is introduced.

Modules should not assume another module exists unless dependency rules are documented.

Cross-module integration must use an explicit public service contract, event/listener boundary, registry contribution, documented module API, or declared dependency. A module must not read another module's private files, depend on its filesystem path, or write directly into schema owned by another module without an approved shared contract.

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
* `docs/12_extensibility_foundation.md`

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

Copot v0.12.0 is released and is the current stable Webcore baseline.

M3 Preparation is complete and closed. M3.1 Users & Access is the active milestone.

The active checkpoint is M3.1 Batch 5 documentation sync and completion audit.

The immediate goal is to:

* preserve the released v0.12.0 runtime and distribution contracts;
* preserve the Stage 1 Webcore freeze, module ownership, dependency, Theme/Module, Navigation, Media, and repository boundaries;
* preserve the Stage 2 approved sequence, planning envelopes, risk labels, and sequencing governance;
* record the completed five-batch M3.1 implementation and verification evidence;
* preserve the single runtime authorization model and completed administrator lockout protections;
* keep deferred Admin UX refinements non-blocking and outside M3.1 closure;
* keep M3.2 inactive;
* defer Post-M3.1 Roadmap Sync until after the user-owned commit, push, and merge workflow.

M2.2 scope and architecture are defined in `docs/12_extensibility_foundation.md`.

M2.3 scope and architecture are defined in `docs/13_minimal_site_capabilities.md`.

M2.4 scope and architecture are defined in `docs/14_platform_hardening.md`.

Distribution and packaging rules are defined in `docs/15_distribution_and_packaging.md`.

M3 governance, Core freeze, module ownership, dependency, sequencing, and entry contracts are defined in `docs/16_m3_core_freeze_and_module_contract.md`.
