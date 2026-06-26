# AGENTS

## Project

**copot**

## Purpose

copot is a modular PHP-based website framework designed for flexible website, content, business, and automation use cases.

The project has completed M1.5 Content Module and is preparing the M1.6 Taxonomy Foundation release.

---

## Current Phase

### M1.6 Taxonomy Foundation

Primary goal:

Prepare the M1.6 Taxonomy Foundation release with taxonomy types, terms, generic assignments, admin term management, and minimal Content integration while avoiding category-only architecture and future optional taxonomy features.

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
## Future Core Service Strategy

* Editor.js is the planned default editor strategy.
* Treat editor capability as a future Core Service, not as a regular feature module.
* Do not implement editor functionality until its milestone is approved.
* Future image handling should use an ImageService abstraction.
* Browser image editor candidates may include Cropper.js.
* Server-side image processing should support GD as baseline and Imagick as optional enhancement.
* Do not make Imagick required for core.
* UI/system localization should be treated as a future Core Service.
* Localization may include language, timezone, locale, date format, number format, currency format, and UI translation.
* Content translation is separate from UI localization and belongs to future Content/multilanguage work.
* Do not implement localization yet.

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

---
## Core Services vs Core Features

* Core Services provide capabilities.
* Core Features provide UI/business features.
* Editor, Media/Image, Localization, and Cache are future Core Services unless explicitly changed.
* Content, Menu, Settings, and Users are Core Features.

---
## Architecture Rules

* Core system handles infrastructure.
* Modules handle business logic.
* Themes handle presentation only.
* Themes must not contain business logic.
* Themes must not directly access the database.
* Modules should be independent whenever practical.
* Database access should go through the core database layer.
* Initial database strategy should use PDO.
* Do not introduce an ORM in M1.1.

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

* `docs/01_principles.md`
* `docs/02_architecture.md`
* `docs/03_roadmap.md`
* `docs/04_module_system.md`
* `docs/05_theme_system.md`
* `docs/06_container_engine.md`
* `docs/07_taxonomy_system.md`
* `docs/07_installer_system.md`

Update `CHANGELOG.md` for meaningful project changes.

---

## Git Rules

Use milestone branches.

Examples:

```text
feature/m1.1-core-bootstrap
feature/m1.2-user-authentication
feature/m1.3-module-manager
```

Commit messages should be clear and milestone-aware.

Example:

```text
M1.1 Define core bootstrap architecture
```

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

1. Update documentation.
2. Confirm milestone scope.
3. Create implementation skeleton.
4. Add minimal working behavior.
5. Test locally.
6. Update changelog.
7. Commit.
8. Merge after review.

---

## Current Immediate Goal

M1.6 Taxonomy Foundation is in release preparation.

The next task should finalize documentation, audit, and release readiness for M1.6 without adding public taxonomy URLs, taxonomy archive pages, taxonomy type UI, tree UI, SEO, search, API endpoints, M1.7 work, or a larger roadmap overhaul.

Roadmap overhaul and M1.7 planning are deferred until after M1.6 is merged and tagged.



