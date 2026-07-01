# Module System

## Purpose

The module system allows copot projects to add reusable management functionality and domain-specific application behavior without mixing those responsibilities into Core Infrastructure, Platform Capabilities, or Themes.

Modules may be classified as:

* Core Modules
* Business/Application Modules

M1.3 focuses on a local module-manager foundation. It does not introduce a marketplace, remote package downloads, a module UI, a Composer package system, a migration runner, or theme integration.

---

## M1.3 Scope

Included:

* Local module folder convention
* `module.json` metadata
* Module discovery
* Module install registration
* Enable and disable state
* Uninstall registration removal
* Simple dependency validation
* Enabled module route loading
* Simple route duplicate detection when practical
* Module permission metadata storage
* Minimal sample module for testing: `modules/example`

Excluded:

* Marketplace
* Admin module UI
* Theme system
* Content Module
* Installer
* Complex dependency resolver
* Composer package system
* Migration runner
* Asset publishing
* Remote module download
* Module permission auto-sync into core permissions

Core Modules and Business/Application Modules use the same Module Manager lifecycle.

The classification describes module responsibility, not a separate installation or registry mechanism.

Platform Capabilities are not automatically modules. A shared service, registry, adapter, or runtime facility may remain in the platform layer without a standalone `module.json`.

---

## Module Folder Convention

Modules live under:

```text
modules/
```

M1.3 uses lowercase slug folder names. The folder name must match `module.json.name`.

Example:

```text
modules/
  example/
    module.json
    routes.php
    Controllers/
    Models/
    Views/
    Services/
    migrations/
    assets/
```

Only `module.json` and `routes.php` are active in M1.3. Other folders are reserved by convention for future milestones.

---

## module.json Structure

Example:

```json
{
  "name": "example",
  "title": "Example Module",
  "description": "Minimal test module for the module manager.",
  "version": "0.1.0",
  "author": "Copot",
  "requires": {
    "copot": ">=0.3.0",
    "modules": []
  },
  "routes": "routes.php",
  "listeners": "listeners.php",
  "permissions": [
    {
      "slug": "example.access",
      "name": "Access Example Module"
    }
  ]
}
```

Required fields:

* `name`
* `title`
* `version`

Optional fields:

* `description`
* `author`
* `requires`
* `routes`
* `listeners`
* `permissions`

Validation rules:

* `name` must be a lowercase slug.
* `name` must match the module folder name.
* `routes`, when present, must point to a file inside the module folder.
* `listeners`, when present, must be a non-empty safe relative path inside the module folder.
* Permission metadata is informational in M1.3 and is not auto-synced to the core `permissions` table.

---

## Discovery Strategy

Discovery scans direct children of `modules/` only:

```text
modules/*/module.json
```

Discovery flow:

1. Find each direct module folder.
2. Read `module.json`.
3. Decode JSON.
4. Validate required fields.
5. Validate folder name and module name match.
6. Return normalized module definitions.

No recursive deep scan, remote scan, composer discovery, or marketplace lookup is included in M1.3.

---

## Install Strategy

Install means local registration in the database.

Install flow:

1. Discover the module locally.
2. Validate metadata.
3. Insert the module into the `modules` table.
4. Store module permission metadata in `module_permissions` when declared.
5. Default status is `disabled`.

Install does not validate enabled dependencies, download code, publish assets, run migrations, or enable the module automatically. Dependency validation happens when enabling the module.

---

## Enable and Disable Strategy

Runtime module state is stored in the database.

Enable flow:

1. Confirm module is installed.
2. Confirm module files still exist.
3. Validate dependencies.
4. Set status to `enabled`.
5. Enabled module routes load on subsequent requests.

Disable flow:

1. Confirm module is installed.
2. Reject the operation if another enabled module requires this module.
3. Set status to `disabled`.
4. Disabled module routes no longer load.

Disable does not remove module files or delete module data.

---

## Uninstall Strategy

Uninstall removes module registration only.

M1.3 uninstall behavior:

* Delete the module row from the `modules` table.
* Delete related rows from `module_permissions`.
* Reject uninstall if another enabled module requires this module.
* Do not delete the module folder.
* Do not drop module-owned data.
* Do not run migration rollback logic.

This avoids destructive behavior while the framework does not yet have a migration runner or installer system.

---

## Database Tables

M1.3 module schema is stored in:

```text
database/schema.sql
```

Required tables:

```text
modules
module_permissions
```

`modules` tracks installed local modules and runtime status.

`module_permissions` stores permission metadata declared by modules. It does not grant permissions and does not auto-sync to the core `permissions` table in M1.3.

Permission metadata is intended for visibility and future tooling. Actual permission checks continue to use the M1.2 core `permissions` table manually.

---

## Route Loading Strategy

Only enabled modules load routes.

Expected flow:

```text
bootstrap/app.php
-> core routes
-> auth routes
-> enabled module listeners
-> enabled module routes
```

Module routes are loaded from the route file declared in `module.json`, usually:

```text
routes.php
```

Route loading rules:

* Load routes only for enabled modules.
* Route files must stay inside the module folder.
* Disabled modules must not register routes.
* Simple duplicate route detection should be included if lightweight.
* Do not build a complex route conflict manager in M1.3.

Module route files are trusted local project code. M1.3 does not provide sandboxing, marketplace trust checks, or remote package validation.

---

## Listener Loading Strategy

M2.2 adds one optional enabled-module listener contribution boundary. A module opts in explicitly with:

```json
{
  "listeners": "listeners.php"
}
```

The declared file must remain inside the module folder and return an insertion-ordered map of approved event names to callables. Listener contributions load only for installed and enabled modules, in the existing enabled-module order, before enabled module routes. Disabled or merely installed modules contribute nothing.

Listener files are trusted local application code. `$app` is available through include scope, matching route-file wiring. Loading is controlled through metadata, path containment, return-type, event-name, and callable validation; it is not sandboxing and does not restrict service access inside trusted module code.

M2.2 does not establish a production event merely to exercise this boundary. Controlled temporary fixtures prove end-to-end loading, deterministic registration, disabled-module exclusion, and route preservation. Fixture event names are not production API. The first production event belongs to the first consumer milestone with one real caller/listener pair.

---

## Permission Relationship

M1.2 provides core roles and permissions.

M1.3 modules may declare permission metadata in `module.json`. That metadata may be stored in `module_permissions` for visibility and future use.

M1.3 does not automatically sync module permissions into the core `permissions` table. Actual permission checks still use the M1.2 core permission system manually.

Example module route permission check:

```php
if (!$app->auth()->user()?->can('example.access')) {
    return Response::html('403 Forbidden', 403);
}
```

---

## Sample Module

M1.3 includes a minimal local sample module for testing:

```text
modules/example
```

The sample module exists only to verify discovery, install, enable, disable, route loading, and permission metadata behavior.

It is not a content-management module, admin dashboard, marketplace package, or product feature.

---

## Architecture Risks

* Module scope can easily expand into marketplace behavior.
* Dependency resolution can become too complex if version solving grows too early.
* Route conflicts need lightweight detection or clear documentation.
* Loading module PHP files assumes local module code is trusted project code.
* Permission metadata can drift from core permissions if sync behavior is unclear.
* Uninstall must not become destructive before migration and installer systems exist.
* Module views must not become theme system behavior before M1.4.

---

## M1.3 Implementation Batches

Recommended implementation order:

1. Documentation and scope lock.
2. Database tables and core module data objects.
3. Module discovery and metadata validation.
4. Module manager operations: install, enable, disable, uninstall.
5. Enabled module route loading and simple duplicate detection.
6. Sample module test and manual test documentation.
7. README, CHANGELOG, architecture, and roadmap updates.
